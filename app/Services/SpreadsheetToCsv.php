<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * Lets the CSV-based project data import accept Excel files: converts .xlsx/.xlsm
 * (streamed, fast for large files) and .xls (PhpSpreadsheet) to a CSV that the
 * existing import reads unchanged. CSV/TXT files are passed through as they are.
 */
class SpreadsheetToCsv
{
    public const EXTENSIONS = ['csv', 'txt', 'xlsx', 'xlsm', 'xls'];

    /** Returns the path of a CSV for $path (the original path when it's already a CSV). */
    public static function convert(string $path, string $extension): string
    {
        $ext = strtolower($extension);
        if (in_array($ext, ['csv', 'txt'], true)) return $path;
        if (!in_array($ext, self::EXTENSIONS, true)) {
            throw new RuntimeException("Unsupported file type .{$ext}.");
        }

        @ini_set('memory_limit', '1024M');
        @set_time_limit(600);

        $csvPath = preg_replace('/\.[^.\/\\\\]+$/', '', $path) . '.csv';
        $out = fopen($csvPath, 'w');
        if (!$out) throw new RuntimeException('Could not create the converted CSV file.');

        $width = null;   // number of columns, taken from the header row
        $write = function (array $cells) use ($out, &$width) {
            $cells = array_filter($cells, fn($v) => trim((string) $v) !== '');
            if (!$cells) return;                                  // skip fully blank rows
            if ($width === null) $width = max(array_keys($cells)); // first non-blank row = header
            $row = [];
            for ($i = 1; $i <= $width; $i++) $row[] = (string) ($cells[$i] ?? '');
            fputcsv($out, $row);
        };

        try {
            if (in_array($ext, ['xlsx', 'xlsm'], true)) {
                XlsxStream::eachRow($path, $write);
            } else {
                $reader = IOFactory::createReaderForFile($path);
                $spreadsheet = $reader->load($path);
                $sheet = $spreadsheet->getSheet(0);
                foreach ($spreadsheet->getWorksheetIterator() as $ws) {
                    if ($ws->getSheetState() === 'visible') { $sheet = $ws; break; }
                }
                foreach ($sheet->toArray('', true, true, false) as $values) {
                    $cells = [];
                    foreach (array_values($values) as $i => $v) $cells[$i + 1] = $v;
                    $write($cells);
                }
                $spreadsheet->disconnectWorksheets();
            }
        } finally {
            fclose($out);
        }

        if ($width === null) {
            @unlink($csvPath);
            throw new RuntimeException('The file has no data.');
        }
        @unlink($path);
        return $csvPath;
    }
}
