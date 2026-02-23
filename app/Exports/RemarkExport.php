<?php

namespace App\Exports;

use App\Models\RemarkMaster;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class RemarkExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize, WithEvents
{
    public function collection()
    {
        return RemarkMaster::with('project')
            ->get()
            ->map(function ($item) {
                return [
                    'remark'       => $item->remark,
                    'project_name' => $item->project->project_name ?? '-',
                    'status'       => $item->status ? 'Active' : 'Inactive',
                ];
            });
    }

    public function headings(): array
    {
        return [
            'Remark',
            'Project Name',
            'Status',
        ];
    }

    // 🎨 Header Styling
    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER,
                ],
                'fill' => [
                    'fillType' => 'solid',
                    'startColor' => ['rgb' => '000000'],
                ],
            ]
        ];
    }

    // ✨ Borders + Freeze Header Row
    public function registerEvents(): array
    {
        return [
            \Maatwebsite\Excel\Events\AfterSheet::class => function ($event) {

                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();
                $cellRange = "A1:{$highestColumn}{$highestRow}";

                // Add thin borders
                $sheet->getStyle($cellRange)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                ]);

                // Freeze header
                $sheet->freezePane('A2');
            }
        ];
    }

}
