<?php

namespace App\Exports\PlanVisit;

use App\Exports\Concerns\PreservesTextColumns;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PlanVisitSheet implements FromCollection, ShouldAutoSize, WithColumnFormatting, WithCustomValueBinder, WithHeadings, WithStyles, WithTitle
{
    use PreservesTextColumns;

    public function __construct(private string $scheduleScope = 'daily')
    {
        if (! in_array($this->scheduleScope, ['daily', 'weekly'], true)) {
            $this->scheduleScope = 'daily';
        }
    }

    public function title(): string
    {
        return $this->scheduleScope === 'weekly' ? 'PlanVisitWeekly' : 'PlanVisitDaily';
    }

    public function headings(): array
    {
        if ($this->scheduleScope === 'weekly') {
            return [
                'username',
                'badan_usaha',
                'kode_outlet',
                'divisi',
                'nama_outlet',
                'schedule_week',
                'schedule_year',
            ];
        }

        return [
            'username',
            'badan_usaha',
            'kode_outlet',
            'divisi',
            'nama_outlet',
            'tanggal_visit',
        ];
    }

    public function collection(): Collection
    {
        $row = [
            'sales01 (username tanpa spasi)',
            'MSI (wajib jika nama divisi sama di beberapa BU)',
            'OTL-001 (kode outlet sesuai master)',
            'GROSIR (nama divisi sesuai master)',
            'TOKO MAKMUR (opsional, referensi saja)',
        ];

        if ($this->scheduleScope === 'weekly') {
            $row[] = '35 atau "Week 35" (isi angka minggu sesuai kalender ISO)';
            $row[] = '2025 (tahun sesuai minggu yang dipilih)';
        } else {
            $row[] = 'Isi tanggal visit harian (YYYY-MM-DD), contoh: 2025-01-13';
        }

        return new Collection([$row]);
    }

    public function styles(Worksheet $sheet)
    {
        $lastColumn = Coordinate::stringFromColumnIndex(count($this->headings()));
        $headerRange = sprintf('A1:%s1', $lastColumn);

        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D1FAD7');

        $sheet->getStyle($headerRange)->getFont()
            ->setBold(true)
            ->getColor()->setRGB('000000');

        return $sheet;
    }

    protected function textColumns(): array
    {
        return ['B'];
    }
}
