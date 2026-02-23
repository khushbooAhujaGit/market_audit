<?php

namespace App\Exports;

use App\Models\Project;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProjectsExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize, WithEvents
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return Project::with('getCompanyInfo', 'getUnit', 'getZone', 'getProjectType', 'getProjectTemplates.getTemplate')
            ->get()
            ->map(function ($item) {
                // Get all template names mapped to this project
                $templateNames = $item->getProjectTemplates->map(function($pt) {
                    return $pt->getTemplate->template_name ?? '-';
                })->toArray();

                return [
                    'project_name'       => $item->project_name,
                    'project_type'       => $item->getProjectType->project_type_name ?? '-',
                    'company'            => $item->getCompanyInfo->company_name ?? '-',
                    'zone'               => $item->getZone->zone_name ?? '-',
                    'unit'               => $item->getUnit->unit_name ?? '-',
                    // Join with newline so each template appears in a new line in the cell
                    'template_mapped'    => implode(", ", $templateNames),
                ];
            });
    }

    public function headings(): array
    {
        return [
            'Project Name',
            'Project Type',
            'Company',
            'Zone',
            'Unit',
            'Template Mapped'
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
                    'wrapText'   => true, // <-- important for line breaks
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
