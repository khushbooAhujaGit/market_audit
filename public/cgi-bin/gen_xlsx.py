#!/usr/bin/env python3
"""
CGI endpoint: receives POST JSON with {file, secret}, generates XLSX, returns binary.
Called by PHP via curl — no exec() needed.
"""
import sys
import os
import json
import tempfile

# Ensure xlsxwriter is importable (cPanel path)
for p in ['/usr/local/lib/python3.9/site-packages',
          '/usr/local/lib/python3.10/site-packages',
          '/usr/local/lib/python3.11/site-packages',
          '/usr/local/lib/python3.8/site-packages']:
    if os.path.isdir(p):
        sys.path.insert(0, p)
        break

import re
import xlsxwriter

FILE_EXT_RE = re.compile(
    r'\.(jpg|jpeg|png|gif|webp|pdf|xlsx|xls|doc|docx|mp3|mp4|wav|ogg|csv)$',
    re.IGNORECASE
)

def classify_cell(val, base_url):
    if not isinstance(val, str) or not val:
        return val, None, False
    if val.startswith('[') and val.startswith('["http'):
        return 'Multiple Images', None, False
    url = None
    is_zip = False
    label = 'Download'
    if '/download-images-zip/' in val:
        url, is_zip, label = val, True, 'Download ZIP'
    elif re.match(r'^https?://', val, re.IGNORECASE):
        url = val
    elif FILE_EXT_RE.search(val):
        url = base_url.rstrip('/') + '/' + val.lstrip('/')
    return (label if url else val), url, is_zip

def est_width(val):
    s = str(val) if val is not None else ''
    return min(max(len(s) + 2, 8), 55)

def write_main_sheet(wb, title, rows, base_url, fmts):
    ws = wb.add_worksheet(title[:31])
    col_widths = []
    for ri, row in enumerate(rows):
        for ci, val in enumerate(row):
            w = est_width(val)
            if ci >= len(col_widths):
                col_widths.extend([8] * (ci - len(col_widths) + 1))
            if w > col_widths[ci]:
                col_widths[ci] = w
            if ri == 0:
                ws.write(ri, ci, val, fmts['header'])
                continue
            label, url, is_zip = classify_cell(val, base_url)
            if url:
                fmt = fmts['link_zip'] if is_zip else fmts['link']
                ws.write_url(ri, ci, url, fmt, label)
            else:
                ws.write(ri, ci, label)
    ws.freeze_panes(1, 0)
    for i, w in enumerate(col_widths):
        ws.set_column(i, i, w)

def write_extra_sheet(wb, title, rows, base_url, fmts):
    ws = wb.add_worksheet(title[:31])
    col_widths = []
    total_cols = max((len(r) for r in rows if r), default=2)
    for ri, row in enumerate(rows):
        if not row:
            ws.write(ri, 0, '')
            continue
        for ci, val in enumerate(row):
            w = est_width(val)
            if ci >= len(col_widths):
                col_widths.extend([8] * (ci - len(col_widths) + 1))
            if w > col_widths[ci]:
                col_widths[ci] = w
        first = row[0] if row else ''
        if ri == 0:
            ws.merge_range(ri, 0, ri, total_cols - 1, first, fmts['title_large'])
        elif ri == 1:
            ws.merge_range(ri, 0, ri, total_cols - 1, first, fmts['title_medium'])
        elif ri < 5:
            ws.write(ri, 0, first, fmts['meta_label'])
            for ci, v in enumerate(row[1:], 1):
                ws.write(ri, ci, v, fmts['meta_value'])
        elif isinstance(first, str) and first.startswith('Activity:'):
            ws.merge_range(ri, 0, ri, total_cols - 1, first, fmts['act_label'])
        elif first == 'Activity Name':
            for ci, v in enumerate(row):
                ws.write(ri, ci, v, fmts['col_header'])
        else:
            for ci, val in enumerate(row):
                label, url, is_zip = classify_cell(val, base_url)
                if url:
                    fmt = fmts['link_zip'] if is_zip else fmts['link']
                    ws.write_url(ri, ci, url, fmt, label)
                else:
                    ws.write(ri, ci, label if label is not None else '')
    ws.freeze_panes(6, 0)
    for i, w in enumerate(col_widths):
        ws.set_column(i, i, w)

def main():
    # Read POST body
    try:
        content_length = int(os.environ.get('CONTENT_LENGTH', 0))
    except (ValueError, TypeError):
        content_length = 0

    body = sys.stdin.buffer.read(content_length) if content_length > 0 else sys.stdin.buffer.read()

    try:
        params = json.loads(body)
    except Exception as e:
        sys.stdout.buffer.write(b"Content-Type: text/plain\r\n\r\n")
        sys.stdout.buffer.write(("Invalid JSON: " + str(e)).encode())
        return

    # Validate secret
    expected_secret = os.environ.get('XLSX_SECRET', '')
    if expected_secret and params.get('secret') != expected_secret:
        sys.stdout.buffer.write(b"Content-Type: text/plain\r\n\r\nUnauthorized\r\n")
        return

    # Read data from temp file (written by PHP)
    json_file = params.get('file', '')
    if not json_file or not os.path.isfile(json_file):
        sys.stdout.buffer.write(b"Content-Type: text/plain\r\n\r\nData file not found\r\n")
        return

    with open(json_file, 'r', encoding='utf-8') as f:
        data = json.load(f)

    base_url = params.get('base_url', 'https://marketaudit.in')

    # Generate XLSX to temp file
    out_file = tempfile.mktemp(suffix='.xlsx')
    wb = xlsxwriter.Workbook(out_file, {
        'constant_memory': True,
        'strings_to_urls': False,
    })
    fmts = {
        'header':       wb.add_format({'bold': True, 'bg_color': '#1F3864', 'font_color': 'white', 'text_wrap': True, 'valign': 'vcenter'}),
        'link':         wb.add_format({'font_color': '#0070C0', 'underline': True}),
        'link_zip':     wb.add_format({'font_color': '#107C41', 'underline': True}),
        'title_large':  wb.add_format({'bold': True, 'font_size': 14, 'align': 'center', 'valign': 'vcenter'}),
        'title_medium': wb.add_format({'bold': True, 'font_size': 12, 'align': 'center', 'valign': 'vcenter'}),
        'meta_label':   wb.add_format({'bold': True, 'bg_color': '#CCC0DA'}),
        'meta_value':   wb.add_format({'bg_color': '#CCC0DA'}),
        'act_label':    wb.add_format({'bold': True, 'bg_color': '#BDD7EE'}),
        'col_header':   wb.add_format({'bold': True, 'bg_color': '#FFFF00'}),
    }

    main_sheet = data.get('main_sheet', {})
    write_main_sheet(wb, main_sheet.get('title', 'Report'), main_sheet.get('rows', []), base_url, fmts)

    for sheet in data.get('extra_sheets', []):
        write_extra_sheet(wb, sheet.get('title', 'Sheet'), sheet.get('rows', []), base_url, fmts)

    wb.close()

    # Stream XLSX binary to stdout
    with open(out_file, 'rb') as f:
        xlsx_bytes = f.read()
    os.unlink(out_file)

    sys.stdout.buffer.write(b"Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet\r\n")
    sys.stdout.buffer.write(b"Content-Length: " + str(len(xlsx_bytes)).encode() + b"\r\n")
    sys.stdout.buffer.write(b"\r\n")
    sys.stdout.buffer.write(xlsx_bytes)

if __name__ == '__main__':
    main()
