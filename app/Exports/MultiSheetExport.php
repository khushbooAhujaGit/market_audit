<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class MultiSheetExport implements WithMultipleSheets
{
    use Exportable;

    protected $sheetsData;

    public function __construct($sheetsData)
    {
        $this->sheetsData = $sheetsData;
    }

    public function sheets(): array
    {
        $sheets = [];
        foreach ($this->sheetsData as $sheetData) {
            $sheets[] = new class($sheetData['header'], $sheetData['data']) implements FromArray, ShouldAutoSize, WithTitle, WithStyles, WithEvents {
                protected $header;
                protected $data;

                public function __construct($header, $data)
                {
                    $this->header = $header;
                    $this->data   = $data;
                }

                public function array(): array
                {
                    return $this->data;
                }

                public function title(): string
                {
                    return substr($this->header[0], 0, 31);
                }

                public function registerEvents(): array
                {
                    return [
                        AfterSheet::class => function (AfterSheet $event) {
                            $sheet         = $event->sheet->getDelegate();
                            $highestRow    = $sheet->getHighestRow();
                            $highestCol    = $sheet->getHighestColumn();
                            $highestColIdx = Coordinate::columnIndexFromString($highestCol);
                            $baseUrl       = rtrim(config('app.url'), '/');

                            // Freeze column A from row 7 (header rows 1-6, data from 7)
                            $sheet->freezePane('B7');

                            // Scan EVERY row (not just data rows) to catch URLs in any position
                            for ($row = 1; $row <= $highestRow; $row++) {
                                for ($colIdx = 1; $colIdx <= $highestColIdx; $colIdx++) {
                                    $colLetter = Coordinate::stringFromColumnIndex($colIdx);
                                    $cell      = $sheet->getCell("{$colLetter}{$row}");
                                    $val       = $cell->getValue();

                                    if (!is_string($val) || $val === '') continue;
                                    if (str_starts_with($val, 'http') && str_contains($val, '["')) { $cell->setValue('Multiple Images'); continue; }

                                    $url   = null;
                                    $label = 'Download';

                                    if (str_contains($val, '/download-images-zip/')) {
                                        $url   = $val;
                                        $label = 'Download ZIP';
                                    } elseif (preg_match('/^https?:\/\//i', $val)) {
                                        $url = $val;
                                    } elseif (preg_match('/\.(jpg|jpeg|png|gif|webp|pdf|xlsx|xls|doc|docx|mp3|mp4|wav|ogg|csv)$/i', $val)) {
                                        $url = $baseUrl . '/' . ltrim($val, '/');
                                    }

                                    if ($url) {
                                        $cell->setValue($label);
                                        $cell->getHyperlink()->setUrl($url)->setTooltip($url);
                                        $argb = $label === 'Download ZIP' ? 'FF107C41' : 'FF0070C0';
                                        $sheet->getStyle("{$colLetter}{$row}")->applyFromArray([
                                            'font' => [
                                                'color'     => ['argb' => $argb],
                                                'underline' => true,
                                                'bold'      => true,
                                            ],
                                        ]);
                                    }
                                }
                            }

                            // Explicitly auto-size all columns after URL replacement
                            for ($colIdx = 1; $colIdx <= $highestColIdx; $colIdx++) {
                                $colLetter = Coordinate::stringFromColumnIndex($colIdx);
                                $sheet->getColumnDimension($colLetter)->setAutoSize(true);
                            }
                        },
                    ];
                }

                public function styles(Worksheet $sheet)
                {
                    if (empty($this->data)) return;

                    $maxColumns = max(array_map('count', $this->data));
                    $lastColumn = Coordinate::stringFromColumnIndex($maxColumns);

                    $sheet->mergeCells("A1:{$lastColumn}1");
                    $sheet->mergeCells("A2:{$lastColumn}2");
                    $sheet->getStyle('1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle('4')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle('2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle('A2:C2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF403151');
                    $sheet->getStyle('A2:C2')->getFont()->getColor()->setARGB('FFFFFFFF');
                    $sheet->getStyle('A3')->getFont()->setBold(true);
                    $sheet->getStyle('A4')->getFont()->setBold(true);
                    $sheet->getStyle('A5')->getFont()->setBold(true);
                    $sheet->getStyle('6')->getFont()->setBold(true);
                    foreach (['3', '4', '5', '6'] as $r) {
                        $sheet->getStyle("A{$r}:{$lastColumn}{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFCCC0DA');
                    }
                    $sheet->getStyle('A1:C1')->getFont()->setSize(19)->setName('Segoe UI Semilight');
                    $sheet->getStyle('A2:C2')->getFont()->setSize(16)->setName('Segoe UI Semilight');
                    $totalRows = count($this->data);
                    if ($totalRows > 0) {
                        $sheet->getStyle("A1:{$lastColumn}{$totalRows}")->applyFromArray([
                            'borders' => [
                                'allBorders' => [
                                    'borderStyle' => Border::BORDER_THIN,
                                    'color'       => ['argb' => 'FF000000'],
                                ],
                            ],
                        ]);
                    }
                }
            };
        }
        return $sheets;
    }
}
