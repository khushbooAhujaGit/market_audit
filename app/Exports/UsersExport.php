<?php
namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class UsersExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize, WithEvents
{
    protected $currentUserRole;

    public function __construct($currentUserRole)
    {
        $this->currentUserRole = $currentUserRole;
    }

    public function collection()
    {
        $query = User::select('id', 'name', 'email', 'mobile', 'city', 'state');

        if ($this->currentUserRole == 'Agency') {
            $query->where(function ($q) {
                $q->where('is_agency_user', 1)
                    ->orWhereHas('roles', function ($r) {
                        $r->where('name', $this->currentUserRole);
                    });
            });
        }

        $users = $query->get();

        $users->transform(function ($user) {
            $user->roles = implode(', ', $user->getRoleNames()->toArray());

            // Remove ID so it won’t appear in Excel
            unset($user->id);

            return $user;
        });


        return $users;
    }


    public function headings(): array
    {
        return [
            'Name',
            'Email',
            'Mobile',
            'City',
            'State',
            'Roles',
        ];
    }

    // 🎨 Header styling
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

                // Add thin borders to all cells
                $sheet->getStyle($cellRange)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                ]);

                // Freeze first row (header)
                $sheet->freezePane('A2');
            }
        ];
    }
}
