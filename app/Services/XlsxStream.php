<?php

namespace App\Services;

use RuntimeException;
use XMLReader;
use ZipArchive;

/**
 * Minimal streaming .xlsx writer/reader for large sheets (tens of thousands of rows)
 * where PhpSpreadsheet would hold every cell in memory.
 *
 * Writer: one main sheet (streamed row by row to a temp file) plus small extra sheets,
 * with merged cells, list dropdowns and hidden sheets.
 * Reader: returns only the requested columns, loading just the shared strings they use.
 */
class XlsxStream
{
    public const STYLE_NORMAL        = 0;
    public const STYLE_HEADER        = 1;
    public const STYLE_GROUP_HEADER  = 2;
    public const STYLE_ASSIGN_HEADER = 3;
    public const STYLE_ASSIGN_GROUP  = 4;

    /** @var array<int, array{name:string, file:string, state:string}> */
    private array $sheets = [];
    private $handle = null;
    private ?array $current = null;

    public function beginSheet(string $name, array $columnWidths = [], int $freezeRows = 0, string $state = 'visible'): void
    {
        $file = tempnam(sys_get_temp_dir(), 'xlsx');
        $this->handle = fopen($file, 'w');
        $this->current = ['name' => $name, 'file' => $file, 'state' => $state, 'row' => 0, 'merges' => [], 'validations' => []];

        fwrite($this->handle, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">');

        $view = '<sheetView workbookViewId="0"' . (count($this->sheets) === 0 ? ' tabSelected="1"' : '') . '>';
        if ($freezeRows > 0) {
            $top = 'A' . ($freezeRows + 1);
            $view .= "<pane ySplit=\"{$freezeRows}\" topLeftCell=\"{$top}\" activePane=\"bottomLeft\" state=\"frozen\"/>";
        }
        fwrite($this->handle, '<sheetViews>' . $view . '</sheetView></sheetViews>');

        if ($columnWidths) {
            $cols = '';
            foreach (array_values($columnWidths) as $i => $width) {
                $n = $i + 1;
                $cols .= "<col min=\"{$n}\" max=\"{$n}\" width=\"" . (float) $width . "\" customWidth=\"1\"/>";
            }
            fwrite($this->handle, '<cols>' . $cols . '</cols>');
        }
        fwrite($this->handle, '<sheetData>');
    }

    public function row(array $values, int $style = self::STYLE_NORMAL, array $styles = []): void
    {
        $r = ++$this->current['row'];
        $xml = "<row r=\"{$r}\">";
        foreach (array_values($values) as $i => $value) {
            if ($value === null || $value === '') continue;
            $ref = self::columnLetter($i + 1) . $r;
            $s = $styles[$i] ?? $style;
            $sAttr = $s ? " s=\"{$s}\"" : '';
            if (is_int($value) || is_float($value)) {
                $xml .= "<c r=\"{$ref}\"{$sAttr}><v>{$value}</v></c>";
            } else {
                $xml .= "<c r=\"{$ref}\"{$sAttr} t=\"inlineStr\"><is><t xml:space=\"preserve\">" . self::escape((string) $value) . '</t></is></c>';
            }
        }
        fwrite($this->handle, $xml . '</row>');
    }

    public function merge(string $range): void
    {
        $this->current['merges'][] = $range;
    }

    /**
     * List dropdown; showError=false still lets users type other values (e.g. several,
     * comma-separated). $promptTitle/$prompt show as a tip when the cell is selected
     * (Excel limits: 32 / 255 characters).
     */
    public function listValidation(string $range, string $sourceFormula, bool $showError = false, string $promptTitle = '', string $prompt = ''): void
    {
        $this->current['validations'][] = [$range, $sourceFormula, $showError, mb_substr($promptTitle, 0, 32), mb_substr($prompt, 0, 255)];
    }

    /** Tip shown when a cell in $range is selected, with no dropdown or restriction. */
    public function inputTip(string $range, string $promptTitle, string $prompt): void
    {
        $this->current['validations'][] = [$range, null, false, mb_substr($promptTitle, 0, 32), mb_substr($prompt, 0, 255)];
    }

    public function endSheet(): void
    {
        $xml = '</sheetData>';
        if ($this->current['merges']) {
            $xml .= '<mergeCells count="' . count($this->current['merges']) . '">';
            foreach ($this->current['merges'] as $range) $xml .= "<mergeCell ref=\"{$range}\"/>";
            $xml .= '</mergeCells>';
        }
        if ($this->current['validations']) {
            $xml .= '<dataValidations count="' . count($this->current['validations']) . '">';
            foreach ($this->current['validations'] as [$range, $formula, $showError, $promptTitle, $prompt]) {
                $tip = ($promptTitle !== '' ? ' promptTitle="' . self::escape($promptTitle) . '"' : '')
                    . ($prompt !== '' ? ' prompt="' . self::escape($prompt) . '"' : '');
                if ($formula === null) {
                    // "Any value" — just the tip
                    $xml .= "<dataValidation allowBlank=\"1\" showInputMessage=\"1\"{$tip} sqref=\"{$range}\"/>";
                    continue;
                }
                $xml .= '<dataValidation type="list" allowBlank="1" showInputMessage="1" showErrorMessage="' . ($showError ? 1 : 0) . "\"{$tip} sqref=\"{$range}\">"
                    . '<formula1>' . self::escape($formula) . '</formula1></dataValidation>';
            }
            $xml .= '</dataValidations>';
        }
        fwrite($this->handle, $xml . '</worksheet>');
        fclose($this->handle);
        $this->sheets[] = ['name' => $this->current['name'], 'file' => $this->current['file'], 'state' => $this->current['state']];
        $this->handle = null;
        $this->current = null;
    }

    public function addSheet(string $name, array $rows, string $state = 'visible'): void
    {
        $this->beginSheet($name, [], 0, $state);
        foreach ($rows as $row) $this->row($row);
        $this->endSheet();
    }

    public function save(string $path): void
    {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Cannot create {$path}");
        }

        $count = count($this->sheets);
        $overrides = '';
        $sheetEls = '';
        $rels = '';
        foreach ($this->sheets as $i => $sheet) {
            $n = $i + 1;
            $zip->addFile($sheet['file'], "xl/worksheets/sheet{$n}.xml");
            $overrides .= "<Override PartName=\"/xl/worksheets/sheet{$n}.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml\"/>";
            $state = $sheet['state'] !== 'visible' ? " state=\"{$sheet['state']}\"" : '';
            $sheetEls .= '<sheet name="' . self::escape($sheet['name']) . "\" sheetId=\"{$n}\"{$state} r:id=\"rId{$n}\"/>";
            $rels .= "<Relationship Id=\"rId{$n}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet\" Target=\"worksheets/sheet{$n}.xml\"/>";
        }
        $stylesId = $count + 1;
        $rels .= "<Relationship Id=\"rId{$stylesId}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles\" Target=\"styles.xml\"/>";

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . $overrides . '</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<bookViews><workbookView activeTab="0"/></bookViews>'
            . '<sheets>' . $sheetEls . '</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels . '</Relationships>');
        $zip->addFromString('xl/styles.xml', self::stylesXml());
        $zip->close();

        foreach ($this->sheets as $sheet) @unlink($sheet['file']);
        $this->sheets = [];
    }

    private static function stylesXml(): string
    {
        // Fills: 0 none, 1 gray125 (both required), 2 light blue (data headers), 3 light amber (assignment headers)
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFDCE6F1"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFFCE4D6"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="5">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center"/></xf>'
            . '<xf numFmtId="0" fontId="1" fillId="3" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            . '<xf numFmtId="0" fontId="1" fillId="3" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center"/></xf>'
            . '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }

    // ── Reader ───────────────────────────────────────────────────────────────

    /** Sheet names in workbook order. */
    public static function sheetNames(string $path): array
    {
        $zip = self::openZip($path);
        $names = array_keys(self::sheetPaths($zip));
        $zip->close();
        return $names;
    }

    /**
     * Calls $onRow(int $rowNumber, array $values) for every row of $sheetName, with
     * $values keyed by the requested column letters (missing cells are '').
     */
    public static function readColumns(string $path, string $sheetName, array $columns, callable $onRow): void
    {
        $zip = self::openZip($path);
        $paths = self::sheetPaths($zip);
        if (!isset($paths[$sheetName])) {
            $zip->close();
            throw new RuntimeException("Sheet \"{$sheetName}\" not found.");
        }
        $sheetXml = $zip->getFromName($paths[$sheetName]);
        $sstXml   = $zip->getFromName('xl/sharedStrings.xml');
        $zip->close();
        if ($sheetXml === false) throw new RuntimeException("Sheet \"{$sheetName}\" could not be read.");

        $wanted = array_flip(array_map('strtoupper', $columns));

        // Pass 1: which shared strings do the wanted cells use?
        $needed = [];
        self::walkCells($sheetXml, $wanted, function ($row, $col, $type, $raw) use (&$needed) {
            if ($type === 's' && $raw !== '') $needed[(int) $raw] = true;
        });

        // Pass 2: load only those shared strings
        $strings = $sstXml !== false && $needed ? self::sharedStrings($sstXml, $needed) : [];
        unset($sstXml);

        // Pass 3: emit rows
        $current = null;
        $values = [];
        $blank = array_fill_keys(array_keys($wanted), '');
        self::walkCells($sheetXml, $wanted, function ($row, $col, $type, $raw) use (&$current, &$values, $blank, $strings, $onRow) {
            if ($current !== null && $row !== $current) {
                $onRow($current, $values);
                $values = [];
            }
            if ($row !== $current) $values = $blank;
            $current = $row;
            if ($col !== null) {
                $values[$col] = $type === 's' ? ($strings[(int) $raw] ?? '') : $raw;
            }
        }, true);
        if ($current !== null) $onRow($current, $values);
    }

    private static function walkCells(string $sheetXml, array $wanted, callable $onCell, bool $reportRows = false): void
    {
        $reader = new XMLReader();
        $reader->XML($sheetXml, null, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE);
        $row = 0;
        // next() already lands on the following sibling, so don't read() past it
        $positioned = false;
        while ($positioned || $reader->read()) {
            $positioned = false;
            if ($reader->nodeType !== XMLReader::ELEMENT) continue;
            if ($reader->localName === 'row') {
                $row = (int) $reader->getAttribute('r');
                if ($reportRows) $onCell($row, null, null, null);
                continue;
            }
            if ($reader->localName !== 'c') continue;

            $ref = (string) $reader->getAttribute('r');
            $col = preg_replace('/\d+/', '', $ref);
            if (!isset($wanted[$col])) {
                $positioned = $reader->next();
                continue;
            }
            $type = (string) ($reader->getAttribute('t') ?? 'n');
            $node = $reader->expand();
            $raw = '';
            if ($type === 'inlineStr') {
                foreach ($node->getElementsByTagName('t') as $t) $raw .= $t->textContent;
                $type = 'str';
            } else {
                $v = $node->getElementsByTagName('v')->item(0);
                $raw = $v ? $v->textContent : '';
            }
            $onCell($row, $col, $type, $raw);
            $positioned = $reader->next();
        }
        $reader->close();
    }

    /**
     * Streams every row of the workbook's first visible sheet, calling
     * $onRow(array $cells) with values keyed by 1-based column number (empty cells
     * omitted). Numbers with a number/date format are formatted the way Excel shows
     * them, so the result matches an Excel "Save as CSV".
     */
    public static function eachRow(string $path, callable $onRow): void
    {
        $zip = self::openZip($path);
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $paths = self::sheetPaths($zip);

        $wb = simplexml_load_string($workbookXml);
        $sheetName = null;
        foreach ($wb->sheets->sheet as $sheet) {
            $state = (string) $sheet['state'];
            if ($state === '' || $state === 'visible') { $sheetName = (string) $sheet['name']; break; }
        }
        $sheetName ??= array_key_first($paths);
        $date1904 = isset($wb->workbookPr) && in_array(strtolower((string) $wb->workbookPr['date1904']), ['1', 'true'], true);

        $sheetXml  = $sheetName !== null && isset($paths[$sheetName]) ? $zip->getFromName($paths[$sheetName]) : false;
        $sstXml    = $zip->getFromName('xl/sharedStrings.xml');
        $stylesXml = $zip->getFromName('xl/styles.xml');
        $zip->close();
        if ($sheetXml === false) throw new RuntimeException('The workbook has no readable sheet.');

        $strings = $sstXml !== false ? self::sharedStrings($sstXml, null) : [];
        unset($sstXml);
        $formats = $stylesXml !== false ? self::cellFormats($stylesXml) : [];

        $calendar = \PhpOffice\PhpSpreadsheet\Shared\Date::getExcelCalendar();
        if ($date1904) \PhpOffice\PhpSpreadsheet\Shared\Date::setExcelCalendar(\PhpOffice\PhpSpreadsheet\Shared\Date::CALENDAR_MAC_1904);

        try {
            $reader = new XMLReader();
            $reader->XML($sheetXml, null, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE);
            $cells = [];
            $positioned = false;
            while ($positioned || $reader->read()) {
                $positioned = false;
                if ($reader->localName === 'row') {
                    if ($reader->nodeType === XMLReader::ELEMENT) {
                        $cells = [];
                        if ($reader->isEmptyElement) $onRow([]);
                    } elseif ($reader->nodeType === XMLReader::END_ELEMENT) {
                        $onRow($cells);
                    }
                    continue;
                }
                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'c') continue;

                $col = self::columnNumber(preg_replace('/\d+/', '', (string) $reader->getAttribute('r')));
                $type = (string) ($reader->getAttribute('t') ?? 'n');
                $style = (int) ($reader->getAttribute('s') ?? 0);
                $node = $reader->expand();
                $positioned = $reader->next();

                if ($type === 'inlineStr') {
                    $value = '';
                    foreach ($node->getElementsByTagName('t') as $t) $value .= $t->textContent;
                } else {
                    $v = $node->getElementsByTagName('v')->item(0);
                    $raw = $v ? $v->textContent : '';
                    if ($raw === '') continue;
                    $value = match ($type) {
                        's'     => $strings[(int) $raw] ?? '',
                        'b'     => $raw === '1' ? 'TRUE' : 'FALSE',
                        'str', 'e', 'd' => $raw,
                        default => isset($formats[$style]) && is_numeric($raw)
                            ? (string) \PhpOffice\PhpSpreadsheet\Style\NumberFormat::toFormattedString($raw + 0, $formats[$style])
                            : $raw,
                    };
                }
                if ($value !== '') $cells[$col] = $value;
            }
            $reader->close();
        } finally {
            \PhpOffice\PhpSpreadsheet\Shared\Date::setExcelCalendar($calendar);
        }
    }

    /** cellXfs index => number format code, for styles that format numbers (not General/Text). */
    private static function cellFormats(string $stylesXml): array
    {
        $doc = simplexml_load_string($stylesXml);
        $custom = [];
        if (isset($doc->numFmts)) {
            foreach ($doc->numFmts->numFmt as $fmt) $custom[(int) $fmt['numFmtId']] = (string) $fmt['formatCode'];
        }
        $formats = [];
        if (isset($doc->cellXfs)) {
            $i = 0;
            foreach ($doc->cellXfs->xf as $xf) {
                $id = (int) $xf['numFmtId'];
                $code = $custom[$id] ?? \PhpOffice\PhpSpreadsheet\Style\NumberFormat::builtInFormatCode($id);
                if ($id !== 0 && $code !== '' && $code !== 'General' && $code !== '@') $formats[$i] = $code;
                $i++;
            }
        }
        return $formats;
    }

    public static function columnNumber(string $letters): int
    {
        $n = 0;
        foreach (str_split(strtoupper($letters)) as $ch) $n = $n * 26 + (ord($ch) - 64);
        return $n;
    }

    /** $needed = indexes to keep, or null for all shared strings */
    private static function sharedStrings(string $sstXml, ?array $needed): array
    {
        $reader = new XMLReader();
        $reader->XML($sstXml, null, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE);
        $strings = [];
        $index = -1;
        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'si') continue;
            $index++;
            if ($needed !== null && !isset($needed[$index])) continue;

            // Plain <t>, or rich-text runs <r><t>; skip phonetic hints <rPh>
            $text = '';
            foreach ($reader->expand()->childNodes as $child) {
                if ($child->localName === 't') {
                    $text .= $child->textContent;
                } elseif ($child->localName === 'r') {
                    foreach ($child->childNodes as $part) {
                        if ($part->localName === 't') $text .= $part->textContent;
                    }
                }
            }
            $strings[$index] = $text;
        }
        $reader->close();
        return $strings;
    }

    private static function openZip(string $path): ZipArchive
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) throw new RuntimeException('The file is not a valid .xlsx file.');
        return $zip;
    }

    /** sheet name => zip path of its XML */
    private static function sheetPaths(ZipArchive $zip): array
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels     = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbook === false || $rels === false) throw new RuntimeException('The file is not a valid .xlsx file.');

        $targets = [];
        $relDoc = simplexml_load_string($rels);
        foreach ($relDoc->Relationship as $rel) {
            $target = ltrim((string) $rel['Target'], '/');
            $targets[(string) $rel['Id']] = str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
        }

        $paths = [];
        $wbDoc = simplexml_load_string($workbook);
        foreach ($wbDoc->sheets->sheet as $sheet) {
            $rid = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            if (isset($targets[$rid])) $paths[(string) $sheet['name']] = $targets[$rid];
        }
        return $paths;
    }

    public static function columnLetter(int $index): string
    {
        $letter = '';
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letter = chr(65 + $mod) . $letter;
            $index = intdiv($index - 1, 26);
        }
        return $letter;
    }

    private static function escape(string $value): string
    {
        $value = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value) ?? '';
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
