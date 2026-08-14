"""
Fast Excel report generator using XlsxWriter (constant_memory mode).
Usage: python3 generate_report_excel.py <input.json> <output.xlsx> <base_url>

Input JSON structure:
{
  "main_sheet": { "title": "...", "rows": [[...], ...] },
  "extra_sheets": [
    { "title": "...", "rows": [[...], ...] }
  ]
}

Row 0 of main_sheet is the header row (bold).
Extra sheets have 5 metadata rows, then activity sections separated by blank rows.
"""

import sys
import json
import re
import os
import xlsxwriter

# ---------------------------------------------------------------------------
# URL detection helpers (matches PHP imageAnswerUrl / AfterSheet logic)
# ---------------------------------------------------------------------------
FILE_EXT_RE = re.compile(
    r'\.(jpg|jpeg|png|gif|webp|pdf|xlsx|xls|doc|docx|mp3|mp4|wav|ogg|csv)$',
    re.IGNORECASE
)


def classify_cell(val, base_url):
    """Return (label, url, style) or (val, None, None) for plain text."""
    if not isinstance(val, str) or not val:
        return val, None, None

    # Multi-image JSON array guard → plain text
    if val.startswith('http') and '["' in val:
        return 'Multiple Images', None, None

    url = None
    label = 'Download'

    if '/download-images-zip/' in val:
        url = val
        label = 'Download ZIP'
    elif re.match(r'^https?://', val, re.IGNORECASE):
        url = val
    elif FILE_EXT_RE.search(val):
        url = base_url.rstrip('/') + '/' + val.lstrip('/')

    return label if url else val, url, None


# ---------------------------------------------------------------------------
# Column-width estimator (good enough without autofit)
# ---------------------------------------------------------------------------
def est_width(val):
    s = str(val) if val is not None else ''
    return min(max(len(s) + 2, 8), 60)


def update_widths(widths, row):
    for i, v in enumerate(row):
        w = est_width(v)
        if i >= len(widths):
            widths.extend([8] * (i - len(widths) + 1))
        if w > widths[i]:
            widths[i] = w


# ---------------------------------------------------------------------------
# Main sheet writer
# ---------------------------------------------------------------------------
def write_main_sheet(wb, sheet_title, rows, base_url, formats):
    ws = wb.add_worksheet(sheet_title[:31])
    col_widths = []

    for row_idx, row in enumerate(rows):
        update_widths(col_widths, row)
        for col_idx, val in enumerate(row):
            if row_idx == 0:
                ws.write(row_idx, col_idx, val, formats['header'])
                continue

            label, url, _ = classify_cell(val, base_url)
            if url:
                fmt = formats['link_zip'] if label == 'Download ZIP' else formats['link']
                ws.write_url(row_idx, col_idx, url, fmt, label)
            else:
                ws.write(row_idx, col_idx, label)

    ws.freeze_panes(1, 0)
    for i, w in enumerate(col_widths):
        ws.set_column(i, i, w)


# ---------------------------------------------------------------------------
# Extra (instance) sheet writer
# ---------------------------------------------------------------------------
def write_extra_sheet(wb, sheet_title, rows, base_url, formats):
    ws = wb.add_worksheet(sheet_title[:31])
    col_widths = []

    total_cols = max((len(r) for r in rows if r), default=1)

    for row_idx, row in enumerate(rows):
        update_widths(col_widths, row)

        if not row:
            # blank separator row
            ws.write_row(row_idx, 0, [''])
            continue

        first_cell = row[0] if row else ''

        # ── Metadata rows 0-4 (project name, template, main, sub, audit date) ──
        if row_idx < 5:
            if row_idx in (0, 1):
                # Merged title rows
                ws.merge_range(row_idx, 0, row_idx, total_cols - 1, first_cell,
                               formats['title_large'] if row_idx == 0 else formats['title_medium'])
            else:
                ws.write(row_idx, 0, first_cell, formats['meta_label'])
                for ci, v in enumerate(row[1:], 1):
                    ws.write(row_idx, ci, v, formats['meta_value'])
            continue

        # ── "Activity: X" section label ──
        if isinstance(first_cell, str) and first_cell.startswith('Activity:'):
            ws.merge_range(row_idx, 0, row_idx, total_cols - 1, first_cell, formats['act_label'])
            continue

        # ── "Activity Name" column header row ──
        if first_cell == 'Activity Name':
            for ci, v in enumerate(row):
                ws.write(row_idx, ci, v, formats['col_header'])
            continue

        # ── Regular data row — detect hyperlinks ──
        for ci, val in enumerate(row):
            label, url, _ = classify_cell(val, base_url)
            if url:
                fmt = formats['link_zip'] if label == 'Download ZIP' else formats['link']
                ws.write_url(row_idx, ci, url, fmt, label)
            else:
                ws.write(row_idx, ci, label)

    ws.freeze_panes(6, 1)
    for i, w in enumerate(col_widths):
        ws.set_column(i, i, w)


# ---------------------------------------------------------------------------
# Entry point
# ---------------------------------------------------------------------------
def main():
    if len(sys.argv) < 4:
        print("Usage: generate_report_excel.py input.json output.xlsx base_url", file=sys.stderr)
        sys.exit(1)

    json_path   = sys.argv[1]
    output_path = sys.argv[2]
    base_url    = sys.argv[3]

    with open(json_path, 'r', encoding='utf-8') as f:
        data = json.load(f)

    wb = xlsxwriter.Workbook(output_path, {
        'constant_memory': True,
        'strings_to_urls':  False,   # we handle URLs manually
    })

    # ── Define all formats up-front (required for constant_memory mode) ──
    formats = {
        'header': wb.add_format({
            'bold': True, 'bg_color': '#1F3864', 'font_color': 'white',
            'border': 1, 'text_wrap': True, 'valign': 'vcenter',
        }),
        'link': wb.add_format({
            'font_color': '#0070C0', 'underline': True, 'bold': True,
        }),
        'link_zip': wb.add_format({
            'font_color': '#107C41', 'underline': True, 'bold': True,
        }),
        'title_large': wb.add_format({
            'bold': True, 'font_size': 14, 'align': 'center', 'valign': 'vcenter',
        }),
        'title_medium': wb.add_format({
            'bold': True, 'font_size': 12, 'align': 'center', 'valign': 'vcenter',
        }),
        'meta_label': wb.add_format({
            'bold': True, 'bg_color': '#CCC0DA', 'border': 1,
        }),
        'meta_value': wb.add_format({
            'bg_color': '#CCC0DA', 'border': 1,
        }),
        'act_label': wb.add_format({
            'bold': True, 'font_size': 11, 'bg_color': '#BDD7EE', 'border': 1,
        }),
        'col_header': wb.add_format({
            'bold': True, 'bg_color': '#FFFF00', 'border': 1,
        }),
    }

    main_sheet = data.get('main_sheet', {})
    write_main_sheet(wb, main_sheet.get('title', 'Report'),
                     main_sheet.get('rows', []), base_url, formats)

    for sheet in data.get('extra_sheets', []):
        write_extra_sheet(wb, sheet.get('title', 'Sheet'),
                          sheet.get('rows', []), base_url, formats)

    wb.close()


if __name__ == '__main__':
    main()
