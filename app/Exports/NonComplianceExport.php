<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class NonComplianceExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = collect($data);
    }

    public function collection()
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            "Sno", "Company Name", "Zone", "Unit", "Distributor Name", "Audit Firm",
            "Auditor", "Verifier Name", "Audit Period", "Non Compliance", "Action Taken",
            "Action to be Taken", "Comments/Action Details", "Amount Debit",
            "Document Number", "Document Attached", "Action Type"
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Make the header row (row 1) bold
            1 => ['font' => ['bold' => true]]
        ];
    }
}
