<?php

namespace App\Exports\Templates;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PlanVisitTemplate implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function title(): string
    {
        return 'PlanVisitTemplate';
    }

    public function headings(): array
    {
        return [
            'username',
            'kode_outlet',
            'divisi',
            'nama_outlet',
            'tanggal_visit',
        ];
    }

    public function collection(): Collection
    {
        // Template kosong untuk diisi user, tidak ambil data dari DB
        return new Collection([
            [
                '', // username
                '', // kode_outlet
                '', // divisi
                '', // nama_outlet (opsional, hanya untuk referensi)
                '', // tanggal_visit
            ],
        ]);
    }

    public function styles(Worksheet $sheet)
    {
        // Set background color untuk header - hijau dengan teks hitam
        $sheet->getStyle('A1:E1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D1FAD7');

        $sheet->getStyle('A1:E1')->getFont()
            ->setBold(true)
            ->getColor()->setRGB('000000');

        return $sheet;
    }
}
