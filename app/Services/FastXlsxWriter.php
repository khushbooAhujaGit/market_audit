<?php

namespace App\Services;

/**
 * Lightweight streaming XLSX writer.
 * Builds XLSX (ZIP of XML) directly — no PhpSpreadsheet, no subprocess.
 * Writes row-by-row to temp files, then zips them together.
 *
 * Style indexes:
 *   0 = normal
 *   1 = bold header (dark blue bg, white text)
 *   2 = hyperlink blue
 *   3 = hyperlink green (ZIP)
 *   4 = merged title large (bold, 14pt, center)
 *   5 = merged title medium (bold, 12pt, center)
 *   6 = meta label (bold, purple bg)
 *   7 = meta value (purple bg)
 *   8 = activity section label (bold, blue bg)
 *   9 = column header row (bold, yellow bg)
 */
class FastXlsxWriter
{
    private string $baseUrl;
    private string $tmpDir;
    private array  $sheets = [];

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->tmpDir  = sys_get_temp_dir() . '/fxlsx_' . uniqid('', true);
        mkdir($this->tmpDir, 0777, true);
    }

    /** Add the main data sheet (row 0 = bold header). */
    public function addMainSheet(string $title, array $rows): void
    {
        $this->sheets[] = ['title' => substr($title, 0, 31), 'rows' => $rows, 'type' => 'main'];
    }

    /** Add an instance/extra sheet with metadata rows and activity sections. */
    public function addExtraSheet(string $title, array $rows): void
    {
        $this->sheets[] = ['title' => substr($title, 0, 31), 'rows' => $rows, 'type' => 'extra'];
    }

    /** Write the final XLSX to $outputPath and clean up temp files. */
    public function save(string $outputPath): void
    {
        $zip = new \ZipArchive();
        $zip->open($outputPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $sheetXmls   = [];
        $sheetRels   = [];
        $totalSheets = count($this->sheets);

        foreach ($this->sheets as $i => $sheet) {
            [$xmlFile, $relsContent, $merges] = $this->buildSheet(
                $sheet['rows'], $sheet['type'], $i + 1
            );
            $sheetXmls[] = ['file' => $xmlFile, 'merges' => $merges];
            $sheetRels[] = $relsContent;
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes($totalSheets));
        $zip->addFromString('_rels/.rels',          $this->rootRels());
        $zip->addFromString('xl/workbook.xml',       $this->workbook($this->sheets));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels($totalSheets));
        $zip->addFromString('xl/styles.xml',         $this->styles());

        foreach ($sheetXmls as $i => $info) {
            $n = $i + 1;
            $zip->addFile($info['file'], "xl/worksheets/sheet{$n}.xml");
            if ($sheetRels[$i]) {
                $zip->addFromString(
                    "xl/worksheets/_rels/sheet{$n}.xml.rels",
                    $sheetRels[$i]
                );
            }
        }

        $zip->close();

        // Cleanup
        foreach ($sheetXmls as $info) {
            @unlink($info['file']);
        }
        @rmdir($this->tmpDir);
    }

    // ── Sheet builder ────────────────────────────────────────────────────────

    private function buildSheet(array $rows, string $type, int $sheetNum): array
    {
        // Strip trailing all-empty rows so dimension ends at last meaningful row.
        // Rows beyond the dimension appear with Excel's lighter default style (no styled borders).
        if ($type === 'extra') {
            while (!empty($rows)) {
                $last = end($rows);
                $hasContent = is_array($last)
                    && count(array_filter($last, fn($v) => trim((string)($v ?? '')) !== '')) > 0;
                if ($hasContent) break;
                array_pop($rows);
            }
        }

        // Pass 1: compute column widths and max column count
        $colWidths = [];
        $maxCols   = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $cnt = count($row);
            if ($cnt > $maxCols) $maxCols = $cnt;
            foreach ($row as $ci => $val) {
                // Use the longest line in multi-line values for width calculation
                $lines = explode("\n", (string)($val ?? ''));
                $len   = max(array_map('mb_strlen', $lines));
                // Width = content length + padding, min 8, max 80 (prevents absurdly wide cols)
                $w = min(max($len + 2, 8), 80);
                if (!isset($colWidths[$ci]) || $w > $colWidths[$ci]) $colWidths[$ci] = $w;
            }
        }
        if ($maxCols < 2) $maxCols = 2;

        $xmlFile    = $this->tmpDir . "/sheet{$sheetNum}.xml";
        $fh         = fopen($xmlFile, 'w');
        $hyperlinks = [];
        $hlCount    = 0;
        $merges     = [];

        $lastColLetter = $this->colLetter($maxCols - 1);
        $totalRows     = count($rows);

        fwrite($fh, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>');
        fwrite($fh,
            '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        );

        // dimension (helps Excel parse the sheet faster)
        fwrite($fh, "<dimension ref=\"A1:{$lastColLetter}{$totalRows}\"/>");

        // sheetViews — OOXML requires <selection pane="topLeft"/> BEFORE the active-pane selection
        if ($type === 'main') {
            fwrite($fh,
                '<sheetViews><sheetView tabSelected="1" workbookViewId="0">'
                . '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
                . '<selection pane="topLeft"/>'
                . '<selection pane="bottomLeft" activeCell="A2" sqref="A2"/>'
                . '</sheetView></sheetViews>'
            );
        } else {
            // Extra sheets always freeze 6 rows (rows 1-6 = headers, row 7 = first data)
            $freezeAt  = 6;
            $firstData = 'A' . ($freezeAt + 1);
            fwrite($fh,
                '<sheetViews><sheetView workbookViewId="0">'
                . "<pane ySplit=\"{$freezeAt}\" topLeftCell=\"{$firstData}\" activePane=\"bottomLeft\" state=\"frozen\"/>"
                . '<selection pane="topLeft"/>'
                . "<selection pane=\"bottomLeft\" activeCell=\"{$firstData}\" sqref=\"{$firstData}\"/>"
                . '</sheetView></sheetViews>'
            );
        }

        // sheetFormatPr (default sizes)
        fwrite($fh, '<sheetFormatPr defaultRowHeight="15" defaultColWidth="8"/>');

        // cols
        // style="13" on EVERY column (including A-C) sets white fill as the column default.
        // Cell-level styles on written rows override this, so data rows stay bordered.
        // Unwritten rows (beyond last data row) inherit white fill → no visible grid in any column.
        fwrite($fh, '<cols>');
        foreach ($colWidths as $ci => $w) {
            $n = $ci + 1;
            fwrite($fh, "<col min=\"{$n}\" max=\"{$n}\" style=\"13\" width=\"{$w}\" bestFit=\"1\" customWidth=\"1\"/>");
        }
        $firstBlankCol = $maxCols + 1;
        fwrite($fh, "<col min=\"{$firstBlankCol}\" max=\"16384\" style=\"13\" width=\"8\"/>");
        fwrite($fh, '</cols>');

        // sheetData
        fwrite($fh, '<sheetData>');
        foreach ($rows as $ri => $row) {
            if (!is_array($row)) $row = [];
            $rowNum = $ri + 1;

            if ($type === 'extra') {
                // Write spans attribute so Excel only renders grid lines within the data columns
                fwrite($fh, "<row r=\"{$rowNum}\" spans=\"1:{$maxCols}\">");
                // Always write all $maxCols cells — empty ones get explicit no-border style
                // so they override Excel's default grid line rendering
                for ($ci = 0; $ci < $maxCols; $ci++) {
                    $val  = (string)($row[$ci] ?? '');
                    $ref  = $this->colLetter($ci) . $rowNum;
                    $sIdx = $this->styleIndex($val, $ri, $ci, $type, $row);

                    [$label, $url, $isZip] = $this->classifyCell($val);
                    if ($url) {
                        $hlCount++;
                        $relId            = "rId{$hlCount}";
                        $hyperlinks[$ref] = ['url' => $url, 'relId' => $relId, 'isZip' => $isZip];
                        $sIdx             = $isZip ? 3 : 2;
                    }

                    if ($val === '') {
                        fwrite($fh, "<c r=\"{$ref}\" s=\"{$sIdx}\"/>");
                    } else {
                        fwrite($fh,
                            "<c r=\"{$ref}\" t=\"inlineStr\" s=\"{$sIdx}\">"
                            . '<is><t xml:space="preserve">' . $this->xe($label) . '</t></is>'
                            . '</c>'
                        );
                    }
                }
            } else {
                fwrite($fh, "<row r=\"{$rowNum}\">");
                foreach ($row as $ci => $val) {
                    $val = (string)($val ?? '');
                    if ($val === '') continue;
                    $ref  = $this->colLetter($ci) . $rowNum;
                    $sIdx = $this->styleIndex($val, $ri, $ci, $type, $row);

                    [$label, $url, $isZip] = $this->classifyCell($val);
                    if ($url) {
                        $hlCount++;
                        $relId            = "rId{$hlCount}";
                        $hyperlinks[$ref] = ['url' => $url, 'relId' => $relId, 'isZip' => $isZip];
                        $sIdx             = $isZip ? 3 : 2;
                    }

                    fwrite($fh,
                        "<c r=\"{$ref}\" t=\"inlineStr\" s=\"{$sIdx}\">"
                        . '<is><t xml:space="preserve">' . $this->xe($label) . '</t></is>'
                        . '</c>'
                    );
                }
            }

            fwrite($fh, '</row>');
        }
        fwrite($fh, '</sheetData>');

        // mergeCells (must come after sheetData, only when we have real merges)
        if (!empty($merges)) {
            fwrite($fh, '<mergeCells count="' . count($merges) . '">');
            foreach ($merges as $m) {
                fwrite($fh, "<mergeCell ref=\"{$m}\"/>");
            }
            fwrite($fh, '</mergeCells>');
        }

        // hyperlinks (must come after sheetData/mergeCells)
        if (!empty($hyperlinks)) {
            fwrite($fh, '<hyperlinks>');
            foreach ($hyperlinks as $ref => $hl) {
                fwrite($fh, "<hyperlink ref=\"{$ref}\" r:id=\"{$hl['relId']}\"/>");
            }
            fwrite($fh, '</hyperlinks>');
        }

        fwrite($fh, '</worksheet>');
        fclose($fh);

        // Relationships XML for hyperlinks
        $relsXml = null;
        if (!empty($hyperlinks)) {
            $relsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
            foreach ($hyperlinks as $hl) {
                $eu       = $this->encodeRelTarget($hl['url']);
                $relsXml .= "<Relationship Id=\"{$hl['relId']}\""
                    . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink"'
                    . " Target=\"{$eu}\" TargetMode=\"External\"/>";
            }
            $relsXml .= '</Relationships>';
        }

        return [$xmlFile, $relsXml, $merges];
    }

    // ── Style determination ──────────────────────────────────────────────────

    private function styleIndex($val, int $ri, int $ci, string $type, array $row): int
    {
        if ($type === 'main') {
            return $ri === 0 ? 14 : 13; // bold no-border header on row 1, plain no-border elsewhere
        }

        // Extra sheet
        if ($ri === 0) {
            // Company title row: A gets bold title style, B/C get no-border blank (matches sample file)
            return $ci === 0 ? 10 : 11;
        }
        if ($ri === 1) return 1;  // Activity name row — dark purple fill extends across all cols
        if ($ri >= 2 && $ri <= 4) {
            return $ci === 0 ? 6 : 7; // meta rows (CD Name, CD Code, Audit Date) — purple
        }
        $first = (string)($row[0] ?? '');
        if (str_starts_with($first, 'Outlet:'))   return $ci === 0 ? 6 : 7;
        if ($first === 'Activity Name')            return $ci === 0 ? 6 : 7;
        if ($ri === 5)                             return 6;  // Sr. No/Particular/Remark — purple bold
        // Data + footer rows: col B gets border+wrap (long question text), others get border only.
        // ALL cells in A-C are written (even empty) matching the sample file.
        // D+ cells are never written → lighter default Excel grid lines (expected by user).
        return $ci === 1 ? 12 : 0;
    }

    // ── Cell URL classifier ──────────────────────────────────────────────────

    private static array $EXT_RE_CACHE = [];

    private function classifyCell(string $val): array
    {
        if ($val === '' || !is_string($val)) return [$val, null, false];

        // Multi-image JSON guard
        if (str_starts_with($val, 'http') && str_contains($val, '["')) {
            return ['Multiple Images', null, false];
        }

        if (str_contains($val, '/download-images-zip/')) {
            return ['Download ZIP', $val, true];
        }
        if (preg_match('/^https?:\/\//i', $val)) {
            return ['Download', $val, false];
        }
        if (preg_match('/\.(jpg|jpeg|png|gif|webp|pdf|xlsx|xls|doc|docx|mp3|mp4|wav|ogg|csv)$/i', $val)) {
            return ['Download', $this->baseUrl . '/' . ltrim($val, '/'), false];
        }

        return [$val, null, false];
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function colLetter(int $zeroIdx): string
    {
        $letter = '';
        $n = $zeroIdx + 1;
        while ($n > 0) {
            $n--;
            $letter = chr(65 + ($n % 26)) . $letter;
            $n = (int)($n / 26);
        }
        return $letter;
    }

    /**
     * Encode a URL for use in a .rels XML Target attribute.
     * - Spaces and & in the path must be percent-encoded (%20, %26)
     *   so the XML attribute value is a valid URI (not &amp; which breaks hyperlinks).
     */
    private function encodeRelTarget(string $url): string
    {
        // Separate scheme+host from path+query
        if (preg_match('@^(https?://[^/?#]*)(.*)$@i', $url, $m)) {
            $prefix = $m[1];
            $rest   = $m[2];
        } else {
            $prefix = '';
            $rest   = $url;
        }
        // Encode characters that are invalid/problematic in URI paths
        // but don't double-encode already percent-encoded sequences
        $rest = preg_replace_callback(
            '/[^\x21-\x7E]|[ &]/',
            fn($c) => rawurlencode($c[0]),
            $rest
        );
        return htmlspecialchars($prefix . $rest, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function xe(string $val): string
    {
        // Strip characters invalid in XML 1.0 (control chars except tab/LF/CR)
        $val = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $val) ?? $val;
        // Truncate to Excel's 32767-char cell limit
        if (mb_strlen($val) > 32767) $val = mb_substr($val, 0, 32767);
        return htmlspecialchars($val, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    // ── Static XML parts ─────────────────────────────────────────────────────

    private function contentTypes(int $n): string
    {
        $overrides = '';
        for ($i = 1; $i <= $n; $i++) {
            $overrides .= "<Override PartName=\"/xl/worksheets/sheet{$i}.xml\""
                . ' ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . $overrides
            . '</Types>';
    }

    private function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function workbook(array $sheets): string
    {
        $sheetEls = '';
        foreach ($sheets as $i => $s) {
            $n    = $i + 1;
            $name = htmlspecialchars($s['title'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $sheetEls .= "<sheet name=\"{$name}\" sheetId=\"{$n}\" r:id=\"rId{$n}\"/>";
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . "<sheets>{$sheetEls}</sheets>"
            . '</workbook>';
    }

    private function workbookRels(int $n): string
    {
        // styles relationship must come first (rId0), then sheets rId1..rIdN
        $rels = '<Relationship Id="rId0"'
            . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"'
            . ' Target="styles.xml"/>';
        for ($i = 1; $i <= $n; $i++) {
            $rels .= "<Relationship Id=\"rId{$i}\""
                . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"'
                . " Target=\"worksheets/sheet{$i}.xml\"/>";
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $rels
            . '</Relationships>';
    }

    private function styles(): string
    {
        // fonts: 0=normal 1=bold 2=white-16px 3=link-blue 4=link-green 5=title-19px 6=bold12 7=bold14(title)
        $fonts = '<fonts count="8">'
            . '<font><b val="0"/><sz val="11"/><color rgb="FF000000"/><name val="Calibri"/><family val="2"/><scheme val="minor"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FF000000"/><name val="Calibri"/><family val="2"/><scheme val="minor"/></font>'
            . '<font><sz val="16"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/><scheme val="minor"/></font>'
            . '<font><u val="single"/><sz val="11"/><color rgb="FF0070C0"/><name val="Calibri"/><family val="2"/><scheme val="minor"/></font>'
            . '<font><u val="single"/><sz val="11"/><color rgb="FF107C41"/><name val="Calibri"/><family val="2"/><scheme val="minor"/></font>'
            . '<font><sz val="19"/><color rgb="FF000000"/><name val="Calibri"/><family val="2"/><scheme val="minor"/></font>'
            . '<font><b/><sz val="12"/><color rgb="FF000000"/><name val="Calibri"/><family val="2"/><scheme val="minor"/></font>'
            . '<font><b/><sz val="14"/><color rgb="FF000000"/><name val="Calibri"/><family val="2"/><scheme val="minor"/></font>'
            . '</fonts>';

        // fills: 0=none 1=gray125 2=dark-purple 3=light-purple 4=light-blue 5=yellow 6=white
        $fills = '<fills count="7">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF403151"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFCCC0DA"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFBDD7EE"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFFFFF00"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="none"/></fill>'
            . '</fills>';

        // borders: 0=none, 1=thin all sides
        $borders = '<borders count="3">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border>'
            . '<left style="thin"><color rgb="FF000000"/></left>'
            . '<right style="thin"><color rgb="FF000000"/></right>'
            . '<top style="thin"><color rgb="FF000000"/></top>'
            . '<bottom style="thin"><color rgb="FF000000"/></bottom>'
            . '<diagonal/>'
            . '</border>'
            // borderId=2: white border — overrides Excel grid lines making empty cells appear truly blank
            . '<border>'
            . '<left style="thin"><color rgb="FFFFFFFF"/></left>'
            . '<right style="thin"><color rgb="FFFFFFFF"/></right>'
            . '<top style="thin"><color rgb="FFFFFFFF"/></top>'
            . '<bottom style="thin"><color rgb="FFFFFFFF"/></bottom>'
            . '<diagonal/>'
            . '</border>'
            . '</borders>';

        $cellStyleXfs = '<cellStyleXfs count="1">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>'
            . '</cellStyleXfs>';

        // All cellXfs MUST declare applyFont/applyFill/applyAlignment when they differ from base
        $cellXfs = '<cellXfs count="15">'
            // 0: normal + border
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>'
            // 1: header — bold white text, dark-blue fill, centered, wrap + border
            . '<xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyAlignment="1" applyBorder="1">'
            . '<alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            // 2: hyperlink blue + border
            . '<xf numFmtId="0" fontId="3" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1"/>'
            // 3: hyperlink green (ZIP) + border
            . '<xf numFmtId="0" fontId="4" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1"/>'
            // 4: title large — bold 14, centered, no border
            . '<xf numFmtId="0" fontId="5" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1">'
            . '<alignment horizontal="center" vertical="center"/></xf>'
            // 5: title medium — bold 12, centered, no border
            . '<xf numFmtId="0" fontId="6" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1">'
            . '<alignment horizontal="center" vertical="center"/></xf>'
            // 6: meta label — bold, purple bg + border
            . '<xf numFmtId="0" fontId="1" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>'
            // 7: meta value — purple bg + border
            . '<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFill="1" applyBorder="1"/>'
            // 8: bold, no fill, with border (unused — main sheet header now uses style 14, borderless)
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1"/>'
            // 9: (unused — was yellow) now same as meta label purple + border
            . '<xf numFmtId="0" fontId="1" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>'
            // 10: company title — bold 14px, no fill, no border, left-aligned
            . '<xf numFmtId="0" fontId="7" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            // 11: empty cell placeholder — white border (overrides grey grid lines even in Protected View)
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="2" xfId="0" applyBorder="1"/>'
            // 12: data cell with border + wrap text (for column B: long question/particular text)
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1">'
            . '<alignment wrapText="1" vertical="top"/></xf>'
            // 13: no fill, no border — column default for unwritten cells beyond data range
            //     lets Excel's natural light-grey grid show (subtle, not heavy styled borders)
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            // 14: bold, no fill, no border — main sheet header row (plain, not boxed like the
            //     styled instance sheets)
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '</cellXfs>';

        $cellStyles = '<cellStyles count="1">'
            . '<cellStyle name="Normal" xfId="0" builtinId="0"/>'
            . '</cellStyles>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xml:space="preserve" xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="0"/>'
            . $fonts . $fills . $borders . $cellStyleXfs . $cellXfs . $cellStyles
            . '<dxfs count="0"/>'
            . '<tableStyles defaultTableStyle="TableStyleMedium9" defaultPivotStyle="PivotTableStyle1"/>'
            . '</styleSheet>';
    }
}
