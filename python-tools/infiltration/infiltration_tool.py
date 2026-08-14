"""
Infiltration Audit Report Tool

Reads the HCCB Infiltration Audit outlet-wise input workbook (Sheet 1 = outlet-wise
details, Sheet 2 = outlet-wise item-wise details), builds the Infiltration Audit
report workbook in the sample output format, and downloads the Countsheet/Stock
photo zips into one folder per outlet.
"""

import concurrent.futures
import io
import json
import logging
import os
import queue
import re
import sys
import threading
import zipfile
from copy import copy
from datetime import datetime, date
from urllib.parse import urlparse

import openpyxl
import requests

# ---------------------------------------------------------------------------
# App metadata
# ---------------------------------------------------------------------------

APP_NAME = "Infiltration Audit Report Tool"
APP_VERSION = "1.1.0"
APP_RELEASE_DATE = "31-Jul-2026 08:20"


def resource_path(relative_path):
    """Resolve a bundled data file, both when run from source and from a PyInstaller exe."""
    base_path = getattr(sys, "_MEIPASS", os.path.dirname(os.path.abspath(__file__)))
    return os.path.join(base_path, relative_path)


# ---------------------------------------------------------------------------
# Output format (matches Sample Output/Infiltration Audit report - 25-07-2026.xlsx)
# The exact fonts/fills/borders/column widths are cloned from this template at
# runtime, rather than reconstructed by hand, so the output formatting always
# matches the sample.
# ---------------------------------------------------------------------------

OUTPUT_TEMPLATE_PATH = resource_path(os.path.join("assets", "output_template.xlsx"))

# Photo downloads are one HTTP round-trip each; running several in parallel (they're
# I/O-bound, not CPU-bound) cuts total wall-clock time substantially for projects with
# many outlets/items without hammering the server.
DOWNLOAD_CONCURRENCY = 6

OUTPUT_HEADERS = [
    "Sr. No.",
    "Date on which stocks have been found",
    "SKU Name",
    "Name of Outlet or \xa0WS or B2B or B2C where Stock Available",
    "Location / Town",
    "Batch Number",
    "Manufacturing Date",
    "Manufact+M2uring Plant",
    "MRP",
    "Qty",
    "Source Zone",
    "Validation by Auditor (Visit/ Pic/ Video)",
    "Date and Est Time of Verification by Auditor",
    "Auditor Verification done \xa0by",
    "Other Remarks",
    "Batch No pictures attached",
    "Stock pictures attached",
    "Picture refrence",
    "Picture stock refrence",
]

STATIC_SOURCE_ZONE = "Andhra Pradesh"
STATIC_VERIFIED_BY = "Rutul Shah & Co LLP"

INVALID_FOLDER_CHARS = re.compile(r'[<>:"/\\|?*]')

YES_VALUES = {"yes", "y", "true"}

OUTPUT_SUBFOLDER_NAME = "Output"


def default_output_dir(input_path):
    """Output folder lives next to the input file, in an 'Output' subfolder."""
    return os.path.join(os.path.dirname(os.path.abspath(input_path)), OUTPUT_SUBFOLDER_NAME)


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

def norm(s):
    if s is None:
        return ""
    return str(s).strip()


def norm_key(s):
    return norm(s).lower()


def is_yes(value):
    return norm_key(value) in YES_VALUES


def sanitize_folder_name(name):
    name = INVALID_FOLDER_CHARS.sub("_", norm(name))
    name = name.strip(" .")
    return name or "Unknown Outlet"


def parse_date_value(value):
    """Best-effort parse of a date/datetime cell into a datetime object (or None)."""
    if value in (None, ""):
        return None
    if isinstance(value, datetime):
        return value
    if isinstance(value, date):
        return datetime(value.year, value.month, value.day)
    text = str(value).strip()
    if not text:
        return None
    formats = [
        "%d-%b-%Y %H:%M", "%d-%b-%Y", "%d-%m-%Y %H:%M", "%d-%m-%Y",
        "%d/%m/%Y %H:%M", "%d/%m/%Y", "%Y-%m-%d %H:%M:%S", "%Y-%m-%d",
    ]
    for fmt in formats:
        try:
            return datetime.strptime(text, fmt)
        except ValueError:
            continue
    return None


def header_index_map(header_row_cells):
    """Map normalized header text -> 0-based column index, for a row of cells."""
    mapping = {}
    for idx, cell in enumerate(header_row_cells):
        key = norm_key(cell.value)
        if key:
            mapping[key] = idx
    return mapping


def find_col(colmap, *candidates):
    for c in candidates:
        key = norm_key(c)
        if key in colmap:
            return colmap[key]
    return None


def cell_hyperlink_url(cell):
    if cell is not None and cell.hyperlink is not None:
        return cell.hyperlink.target
    return None


def clone_row_style(ws, src_row, dst_row, num_cols):
    """Copy font/fill/border/alignment/number_format from src_row onto dst_row."""
    for c_idx in range(1, num_cols + 1):
        src = ws.cell(row=src_row, column=c_idx)
        dst = ws.cell(row=dst_row, column=c_idx)
        dst.font = copy(src.font)
        dst.fill = copy(src.fill)
        dst.border = copy(src.border)
        dst.alignment = copy(src.alignment)
        dst.number_format = src.number_format
    src_height = ws.row_dimensions[src_row].height
    if src_height is not None:
        ws.row_dimensions[dst_row].height = src_height


# ---------------------------------------------------------------------------
# Input parsing
# ---------------------------------------------------------------------------

class OutletRow:
    def __init__(self, values, colmap, row_cells):
        self.values = values
        self.colmap = colmap
        self.row_cells = row_cells

    def get(self, *names):
        idx = find_col(self.colmap, *names)
        if idx is None or idx >= len(self.values):
            return None
        return self.values[idx]

    def hyperlink(self, *names):
        idx = find_col(self.colmap, *names)
        if idx is None or idx >= len(self.row_cells):
            return None
        return cell_hyperlink_url(self.row_cells[idx])


def parse_outlet_sheet(ws, log):
    """Sheet 1: one row per outlet. Header assumed on row 1."""
    rows = list(ws.iter_rows())
    if not rows:
        log.error("Sheet 1 ('%s') is empty.", ws.title)
        return {}
    header_cells = rows[0]
    colmap = header_index_map(header_cells)

    name_idx = find_col(colmap, "Name of Outlet")
    if name_idx is None:
        log.error("Sheet 1 ('%s') has no 'Name of Outlet' column - cannot match outlets.", ws.title)
        return {}

    outlets = {}
    for row_cells in rows[1:]:
        values = [c.value for c in row_cells]
        if all(v in (None, "") for v in values):
            continue
        outlet_name = norm(values[name_idx]) if name_idx < len(values) else ""
        if not outlet_name:
            log.warning("Sheet 1 row %s has no outlet name - skipped.", row_cells[0].row)
            continue
        if outlet_name in outlets:
            log.warning("Sheet 1 has duplicate outlet '%s' (row %s) - later row overwrites earlier.",
                        outlet_name, row_cells[0].row)
        outlets[outlet_name] = OutletRow(values, colmap, row_cells)
    log.info("Sheet 1: parsed %d outlet(s): %s", len(outlets), ", ".join(outlets) if outlets else "(none)")
    return outlets


def find_item_header_row(ws):
    """Locate the header row of the item-wise table (contains 'Sr. No' + 'Product Name with Pack Size')."""
    for row_cells in ws.iter_rows():
        keys = {norm_key(c.value) for c in row_cells if c.value is not None}
        if "sr. no" in keys and any(k.startswith("product name") for k in keys):
            return row_cells
    return None


def find_metadata_value(ws, header_row_num, label):
    """Look above the header row for a single 'label -> value' metadata row (col A/B)."""
    for row_cells in ws.iter_rows(max_row=header_row_num - 1):
        if not row_cells:
            continue
        first = norm(row_cells[0].value)
        if norm_key(first) == norm_key(label) and len(row_cells) > 1:
            return row_cells[1].value
    return None


def parse_item_sheet(ws, log):
    """Sheet 2: outlet-wise item-wise details. Returns list of dicts with resolved outlet name."""
    header_cells = find_item_header_row(ws)
    if header_cells is None:
        log.error("Sheet 2 ('%s'): could not locate the item table header row (expected 'Sr. No' + "
                   "'Product Name with Pack Size'). No item rows parsed.", ws.title)
        return []

    colmap = header_index_map(header_cells)
    header_row_num = header_cells[0].row
    has_outlet_col = find_col(colmap, "Outlet Name") is not None
    fallback_outlet_name = None
    use_first_column_as_outlet = False
    if not has_outlet_col:
        fallback_outlet_name = find_metadata_value(ws, header_row_num, "Name of Outlet")
        if fallback_outlet_name:
            log.info("Sheet 2 ('%s') has no 'Outlet Name' column; using metadata value '%s' for all rows.",
                      ws.title, fallback_outlet_name)
        else:
            # Newer report format: rows from multiple distributors/outlets are merged onto one
            # sheet, with the outlet-identifying value as column A of the item table itself —
            # under whatever label that head is configured with, not necessarily "Outlet Name".
            use_first_column_as_outlet = True
            log.info("Sheet 2 ('%s') has no 'Outlet Name' column or metadata row; using column A "
                      "('%s') as the per-row outlet name.", ws.title, norm(header_cells[0].value))

    items = []
    for row_cells in ws.iter_rows(min_row=header_row_num + 1):
        values = [c.value for c in row_cells]
        if all(v in (None, "") for v in values):
            continue
        sr_no_idx = find_col(colmap, "Sr. No")
        sku_idx = find_col(colmap, "Product Name with Pack Size")
        sr_no_val = values[sr_no_idx] if sr_no_idx is not None and sr_no_idx < len(values) else None
        sku_val = values[sku_idx] if sku_idx is not None and sku_idx < len(values) else None
        if not norm(sr_no_val) and not norm(sku_val):
            continue  # blank/spurious row (e.g. stray junk below the table)

        outlet_name = None
        if has_outlet_col:
            oidx = find_col(colmap, "Outlet Name")
            outlet_name = norm(values[oidx]) if oidx < len(values) else ""
        elif use_first_column_as_outlet:
            outlet_name = norm(values[0]) if values else ""
        else:
            outlet_name = norm(fallback_outlet_name)

        items.append(OutletRow(values, colmap, row_cells))
        items[-1].outlet_name = outlet_name

    log.info("Sheet 2 ('%s'): parsed %d item row(s).", ws.title, len(items))
    return items


# ---------------------------------------------------------------------------
# Photo download
# ---------------------------------------------------------------------------

def download_and_extract(url, dest_folder, prefix, log, local_map=None, timeout=30):
    """Get the content behind url and save it into dest_folder with a filename prefix.
    The report links to a zip when there's more than one photo, but links straight to
    the image file itself when there's only one — handle both.

    When this tool runs on the same server that hosts the report (the normal
    server-side pipeline, as opposed to someone running the standalone desktop app
    against a report from elsewhere), the caller can pass local_map — a {url: local
    file path} dict pre-resolved by the caller — so the file is read straight off disk
    instead of making an HTTP request back into the very server that's running this
    process. If the url isn't in local_map, falls back to a normal HTTP GET."""
    local_path = (local_map or {}).get(url)
    if local_path and os.path.isfile(local_path):
        with open(local_path, "rb") as f:
            content = f.read()
    else:
        resp = requests.get(url, timeout=timeout)
        resp.raise_for_status()
        content = resp.content

    if zipfile.is_zipfile(io.BytesIO(content)):
        with zipfile.ZipFile(io.BytesIO(content)) as zf:
            extracted = []
            for name in zf.namelist():
                if name.endswith("/"):
                    continue
                data = zf.read(name)
                base_name = os.path.basename(name) or "file"
                out_name = f"{prefix}{base_name}"
                out_path = os.path.join(dest_folder, out_name)
                with open(out_path, "wb") as f:
                    f.write(data)
                extracted.append(out_name)
        return extracted

    # Not a zip — a direct link to the single image itself.
    base_name = os.path.basename(urlparse(url).path) or "file.jpg"
    out_name = f"{prefix}{base_name}"
    out_path = os.path.join(dest_folder, out_name)
    with open(out_path, "wb") as f:
        f.write(content)
    return [out_name]


# ---------------------------------------------------------------------------
# Main processing
# ---------------------------------------------------------------------------

def process(input_path, output_dir, log, local_map=None):
    log.info("%s v%s (released %s)", APP_NAME, APP_VERSION, APP_RELEASE_DATE)
    log.info("Loading workbook: %s", input_path)
    try:
        wb = openpyxl.load_workbook(input_path, data_only=True)
    except Exception as exc:
        log.error("Failed to open input workbook: %s", exc)
        raise

    if len(wb.worksheets) < 2:
        log.error("Input workbook has only %d sheet(s); need at least 2 (outlet-wise, item-wise).",
                   len(wb.worksheets))
        raise ValueError("Input workbook must have at least 2 sheets")

    sheet1, sheet2 = wb.worksheets[0], wb.worksheets[1]
    log.info("Sheet 1 (outlet-wise): '%s'   Sheet 2 (item-wise): '%s'", sheet1.title, sheet2.title)

    outlets = parse_outlet_sheet(sheet1, log)
    items = parse_item_sheet(sheet2, log)

    if not items:
        log.error("No item rows found - nothing to report.")
        raise ValueError("No item rows found in Sheet 2")

    os.makedirs(output_dir, exist_ok=True)

    try:
        out_wb = openpyxl.load_workbook(OUTPUT_TEMPLATE_PATH)
    except Exception as exc:
        log.error("Failed to load formatting template (%s): %s", OUTPUT_TEMPLATE_PATH, exc)
        raise
    out_ws = out_wb.active
    style_row = 2  # template's styled-but-blank data row; cloned onto every output row

    outlet_item_counter = {}
    processed_rows = 0
    photo_downloads_ok = 0
    photo_downloads_failed = 0
    download_tasks = []  # queued here, run concurrently after the row-building loop below

    for item in items:
        outlet_name = norm(getattr(item, "outlet_name", ""))
        if not outlet_name:
            log.error("Item row (Sr.No=%s, SKU=%s) has no resolvable outlet name - skipped.",
                      item.get("Sr. No"), item.get("Product Name with Pack Size"))
            continue

        outlet = outlets.get(outlet_name)
        if outlet is None:
            log.warning("Item row for outlet '%s' has no matching row in Sheet 1 - using item-sheet data only.",
                        outlet_name)

        def field(*names):
            v = item.get(*names)
            if (v in (None, "")) and outlet is not None:
                v = outlet.get(*names)
            return v

        outlet_item_counter[outlet_name] = outlet_item_counter.get(outlet_name, 0) + 1
        item_sr_no_raw = item.get("Sr. No")
        try:
            item_sr_no = int(float(item_sr_no_raw))
        except (TypeError, ValueError):
            item_sr_no = outlet_item_counter[outlet_name]
            log.warning("Item row for outlet '%s' has non-numeric Sr. No (%r); using running count %d instead.",
                        outlet_name, item_sr_no_raw, item_sr_no)

        date_found = parse_date_value(field("Date"))
        mfg_date = parse_date_value(item.get("Manufacturing Date"))
        verify_dt = parse_date_value(field("Verification Date & Time")) or date_found

        entry_allowed = outlet.get("Entry Allowed in the Warehouse") if outlet is not None else None
        validation = "Visit" if is_yes(entry_allowed) else "Pic"

        other_remarks = norm(item.get("Other Reason")) or (norm(outlet.get("General Remark")) if outlet else "")

        countsheet_url = item.hyperlink("Countsheet Photo") or (outlet.hyperlink("Countsheet Photo") if outlet else None)
        stock_url = item.hyperlink("Stock Photos") or (outlet.hyperlink("Stock Photos") if outlet else None)
        captured_url = item.hyperlink("Captured Images Upload") or (outlet.hyperlink("Captured Images Upload") if outlet else None)
        batch_pic_attached = "Yes" if countsheet_url else "No"
        stock_pic_attached = "Yes" if stock_url else "No"

        picture_ref = item_sr_no
        picture_stock_ref = float(f"{item_sr_no}.1")

        row_values = [
            processed_rows + 1,
            date_found,
            item.get("Product Name with Pack Size"),
            outlet_name,
            field("Outlet Location/Town"),
            item.get("Batch No."),
            mfg_date,
            item.get("Manufacturing Plant Code"),
            item.get("MRP"),
            item.get("Total Cases Available"),
            STATIC_SOURCE_ZONE,
            validation,
            verify_dt,
            STATIC_VERIFIED_BY,
            other_remarks,
            batch_pic_attached,
            stock_pic_attached,
            picture_ref,
            picture_stock_ref,
        ]
        out_row = processed_rows + 2  # +1 for header, +1 since processed_rows is 0-based
        if out_row != style_row:
            clone_row_style(out_ws, style_row, out_row, len(OUTPUT_HEADERS))
        for c_idx, value in enumerate(row_values, start=1):
            out_ws.cell(row=out_row, column=c_idx, value=value)
        processed_rows += 1

        outlet_folder = os.path.join(output_dir, sanitize_folder_name(outlet_name))
        os.makedirs(outlet_folder, exist_ok=True)

        for url, prefix, label, missing_label in (
            (countsheet_url, f"{item_sr_no}_", "Countsheet zip", "Countsheet Photo"),
            (stock_url, f"{item_sr_no}.1_", "Stock Photos zip", "Stock Photos"),
            (captured_url, f"{item_sr_no}.2_", "Captured Images", "Captured Images Upload"),
        ):
            if url:
                download_tasks.append({
                    "url": url, "outlet_folder": outlet_folder, "prefix": prefix,
                    "label": label, "outlet_name": outlet_name, "item_sr_no": item_sr_no,
                })
            else:
                log.warning("Outlet '%s' item %s: no %s link found.", outlet_name, item_sr_no, missing_label)

    if download_tasks:
        log.info("Downloading %d photo link(s), up to %d at a time...", len(download_tasks), DOWNLOAD_CONCURRENCY)
        with concurrent.futures.ThreadPoolExecutor(max_workers=DOWNLOAD_CONCURRENCY) as executor:
            future_to_task = {
                executor.submit(download_and_extract, t["url"], t["outlet_folder"], t["prefix"], log, local_map): t
                for t in download_tasks
            }
            for future in concurrent.futures.as_completed(future_to_task):
                t = future_to_task[future]
                try:
                    files = future.result()
                    log.info("Outlet '%s' item %s: downloaded %s -> %s",
                              t["outlet_name"], t["item_sr_no"], t["label"], files)
                    photo_downloads_ok += 1
                except Exception as exc:
                    log.error("Outlet '%s' item %s: FAILED to download/extract %s (%s): %s",
                              t["outlet_name"], t["item_sr_no"], t["label"], t["url"], exc)
                    photo_downloads_failed += 1

    run_date_str = datetime.now().strftime("%d-%m-%Y")
    out_path = os.path.join(output_dir, f"Infiltration Audit report - {run_date_str}.xlsx")
    out_wb.save(out_path)
    log.info("Report saved: %s", out_path)
    log.info("Done. %d item row(s) written, %d photo zip(s) downloaded OK, %d failed.",
              processed_rows, photo_downloads_ok, photo_downloads_failed)
    return out_path


# ---------------------------------------------------------------------------
# Logging setup
# ---------------------------------------------------------------------------

def make_logger(output_dir, extra_handler=None):
    logger = logging.getLogger("infiltration_tool")
    logger.setLevel(logging.INFO)
    logger.handlers.clear()

    fmt = logging.Formatter("%(asctime)s [%(levelname)s] %(message)s", "%H:%M:%S")

    os.makedirs(output_dir, exist_ok=True)
    log_path = os.path.join(output_dir, f"run_log_{datetime.now().strftime('%Y%m%d_%H%M%S')}.txt")
    fh = logging.FileHandler(log_path, encoding="utf-8")
    fh.setFormatter(fmt)
    logger.addHandler(fh)

    sh = logging.StreamHandler(sys.stdout)
    sh.setFormatter(fmt)
    logger.addHandler(sh)

    if extra_handler is not None:
        extra_handler.setFormatter(fmt)
        logger.addHandler(extra_handler)

    return logger, log_path


# ---------------------------------------------------------------------------
# GUI
# ---------------------------------------------------------------------------

class QueueHandler(logging.Handler):
    def __init__(self, log_queue):
        super().__init__()
        self.log_queue = log_queue

    def emit(self, record):
        self.log_queue.put(record)


def run_gui():
    import tkinter as tk
    from tkinter import filedialog, messagebox

    import ttkbootstrap as tb
    from ttkbootstrap.constants import BOTH, LEFT, RIGHT, X, YES
    from ttkbootstrap.widgets.scrolled import ScrolledText

    root = tb.Window(title=APP_NAME, themename="flatly", size=(920, 700), resizable=(True, True))
    root.minsize(760, 560)
    root.position_center()

    input_var = tk.StringVar()
    output_var = tk.StringVar()
    status_var = tk.StringVar(value="Ready")

    # ---- Header banner -----------------------------------------------------
    header = tb.Frame(root, bootstyle="primary")
    header.pack(fill=X, side="top")
    header_inner = tb.Frame(header, bootstyle="primary", padding=(24, 16))
    header_inner.pack(fill=X)
    tb.Label(header_inner, text=APP_NAME, font=("Segoe UI", 18, "bold"),
             bootstyle="inverse-primary").pack(anchor="w")
    tb.Label(header_inner, text=f"Version {APP_VERSION}   •   Released {APP_RELEASE_DATE}",
             font=("Segoe UI", 9), bootstyle="inverse-primary").pack(anchor="w")

    # ---- Body ----------------------------------------------------------------
    body = tb.Frame(root, padding=20)
    body.pack(fill=BOTH, expand=YES)

    def on_input_chosen(path):
        if path:
            input_var.set(path)
            output_var.set(default_output_dir(path))

    picker = tb.Labelframe(body, text="Input", padding=15, bootstyle="secondary")
    picker.pack(fill=X, pady=(0, 15))
    picker.columnconfigure(1, weight=1)

    tb.Label(picker, text="Input Excel file", font=("Segoe UI", 9, "bold")).grid(
        row=0, column=0, sticky="w", pady=4)
    tb.Entry(picker, textvariable=input_var).grid(row=0, column=1, sticky="ew", padx=10, pady=4)
    tb.Button(picker, text="Browse...", bootstyle="secondary-outline", command=lambda: on_input_chosen(
        filedialog.askopenfilename(filetypes=[("Excel files", "*.xlsx")])
    )).grid(row=0, column=2, pady=4)

    def on_open_folder():
        out_dir = output_var.get().strip()
        if not out_dir:
            messagebox.showinfo("No output folder yet", "Choose an input file first.")
            return
        os.makedirs(out_dir, exist_ok=True)
        os.startfile(out_dir)

    tb.Label(picker, text="Output folder", font=("Segoe UI", 9, "bold")).grid(
        row=1, column=0, sticky="w", pady=(10, 4))
    out_entry = tb.Entry(picker, textvariable=output_var, state="readonly")
    out_entry.grid(row=1, column=1, sticky="ew", padx=10, pady=(10, 4))
    tb.Button(picker, text="Open Folder", bootstyle="link", command=on_open_folder).grid(
        row=1, column=2, pady=(10, 4))
    tb.Label(picker, text="auto-created next to the input file", bootstyle="secondary",
             font=("Segoe UI", 8)).grid(row=2, column=1, sticky="w", padx=10)

    action_row = tb.Frame(body)
    action_row.pack(fill=X, pady=(0, 15))
    run_btn = tb.Button(action_row, text="Generate Report", bootstyle="success", width=22)
    run_btn.pack(side=LEFT, ipady=6)
    progress = tb.Progressbar(action_row, mode="indeterminate", bootstyle="success-striped")
    status_label = tb.Label(action_row, textvariable=status_var, font=("Segoe UI", 9, "italic"),
                             bootstyle="secondary")
    status_label.pack(side=RIGHT, padx=(0, 10))

    log_frame = tb.Labelframe(body, text="Activity Log", padding=10, bootstyle="secondary")
    log_frame.pack(fill=BOTH, expand=YES)
    log_widget = ScrolledText(log_frame, autohide=True, height=18, font=("Consolas", 9))
    log_widget.pack(fill=BOTH, expand=YES)
    log_widget.text.tag_config("ERROR", foreground="#dc3545")
    log_widget.text.tag_config("WARNING", foreground="#b8860b")
    log_widget.text.tag_config("INFO", foreground="#495057")
    log_widget.text.configure(state="disabled")

    log_queue = queue.Queue()

    def poll_queue():
        while True:
            try:
                record = log_queue.get_nowait()
            except queue.Empty:
                break
            msg = record.getMessage()
            line = f"{datetime.now().strftime('%H:%M:%S')} [{record.levelname}] {msg}\n"
            log_widget.text.configure(state="normal")
            log_widget.text.insert(tk.END, line, record.levelname if record.levelname in
                                    ("ERROR", "WARNING", "INFO") else None)
            log_widget.text.see(tk.END)
            log_widget.text.configure(state="disabled")
        root.after(150, poll_queue)

    def on_run():
        in_path = input_var.get().strip()
        out_dir = output_var.get().strip()
        if not in_path or not os.path.isfile(in_path):
            messagebox.showerror("Missing input", "Please choose a valid input Excel file.")
            return
        if not out_dir:
            messagebox.showerror("Missing output folder", "Please choose an input file first.")
            return

        run_btn.config(state="disabled")
        status_var.set("Processing...")
        progress.pack(side=LEFT, fill=X, expand=YES, padx=15)
        progress.start(12)
        log_widget.text.configure(state="normal")
        log_widget.text.delete("1.0", tk.END)
        log_widget.text.configure(state="disabled")

        def worker():
            handler = QueueHandler(log_queue)
            logger, log_path = make_logger(out_dir, extra_handler=handler)
            ok = True
            try:
                process(in_path, out_dir, logger)
                logger.info("Log file saved to: %s", log_path)
            except Exception as exc:
                logger.error("Run aborted: %s", exc)
                ok = False
            finally:
                def finish():
                    progress.stop()
                    progress.pack_forget()
                    run_btn.config(state="normal")
                    status_var.set("Completed" if ok else "Failed - see log")
                root.after(0, finish)

        threading.Thread(target=worker, daemon=True).start()

    run_btn.config(command=on_run)
    poll_queue()
    root.mainloop()


# ---------------------------------------------------------------------------
# Entry point
# ---------------------------------------------------------------------------

def main():
    if len(sys.argv) >= 2:
        input_path = sys.argv[1]
        output_dir = sys.argv[2] if len(sys.argv) >= 3 else default_output_dir(input_path)

        # Optional 4th positional arg: a JSON file mapping photo URLs already known
        # to be local (the caller resolved them, since it runs on the same server) to
        # their local file paths. Absent when run standalone (e.g. the GUI), in which
        # case every photo is fetched over HTTP as before.
        local_map = {}
        if len(sys.argv) >= 4 and sys.argv[3]:
            try:
                with open(sys.argv[3], "r", encoding="utf-8") as f:
                    local_map = json.load(f)
            except Exception:
                local_map = {}

        logger, log_path = make_logger(output_dir)
        try:
            process(input_path, output_dir, logger, local_map)
        except Exception as exc:
            logger.error("Run aborted: %s", exc)
            sys.exit(1)
        return
    run_gui()


if __name__ == "__main__":
    main()
