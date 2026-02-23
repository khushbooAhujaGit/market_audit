<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\FromArray;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class MultiSheetExport implements WithMultipleSheets
{
    /**
    * @return \Illuminate\Support\Collection
    */
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
            $sheets[] = new class($sheetData['header'], $sheetData['data']) implements FromArray, ShouldAutoSize,  WithTitle, WithStyles {
                protected $header;
                protected $data;

                public function __construct($header, $data)
                {
                    $this->header = $header;
                    $this->data = $data;
                }

                public function array(): array
                {
                    return $this->data;
                }

                public function title(): string
                {
                    return $this->header[0]; // Assuming the first element is the title
                }
                public function styles(Worksheet $sheet){
                    $maxColumns = max(array_map('count', $this->data)); // Get the maximum column count across all rows

                    // Determine the last column letter based on the number of columns
                    $lastColumn = Coordinate::stringFromColumnIndex($maxColumns);
                    $sheet->mergeCells("A1:{$lastColumn}1");
                    $sheet->mergeCells("A2:{$lastColumn}2");
                    $sheet->getStyle('1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle('4')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle('2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle('A2:C2')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('FF403151');
                    $sheet->getStyle('A2:C2')->getFont()->getColor()->setARGB('FFFFFFFF');
//                    $sheet->getStyle('A1:C2')->getFont()->setBold(true);
//                    $sheet->getStyle('1')->getFont()->setBold(true);
//                    $sheet->getStyle('2')->getFont()->setBold(true);
                    $sheet->getStyle('A3')->getFont()->setBold(true);
                    $sheet->getStyle('A4')->getFont()->setBold(true);
                    $sheet->getStyle('A5')->getFont()->setBold(true);
                    $sheet->getStyle('6')->getFont()->setBold(true);
                    $sheet->getStyle("A3:{$lastColumn}6")->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('FFCCC0DA');
                    $sheet->getStyle("A4:{$lastColumn}6")->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('FFCCC0DA');
                    $sheet->getStyle("A5:{$lastColumn}6")->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('FFCCC0DA');
                    $sheet->getStyle("A6:{$lastColumn}6")->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('FFCCC0DA');
                    $sheet->getStyle('A1:C1')->getFont()->setSize(19)->setName('Segoe Ul Semilight');  // Target specific range for font settings
                    $sheet->getStyle('A2:C2')->getFont()->setSize(16)->setName('Segoe Ul Semilight');  // Target specific range for font settings
                    $sheet->getStyle("A1:{$lastColumn}".(count($this->data) + 0))->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                                'color' => ['argb' => 'FF000000'],
                            ],
                        ],
                    ]);
                }

            };
        }
        return $sheets;
    }

}
