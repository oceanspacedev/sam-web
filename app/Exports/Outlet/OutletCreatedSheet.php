<?php

namespace App\Exports\Outlet;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class OutletCreatedSheet implements FromCollection, ShouldAutoSize, WithHeadings, WithTitle
{
    public function title(): string
    {
        return 'Created';
    }

    public function headings(): array
    {
        return [
            'badan_usaha',
            'divisi',
            'region',
            'cluster',
            'kode_outlet',
            'nama_outlet',
            'alamat_outlet',
            'distric',
            'limit',
        ];
    }

    public function collection(): Collection
    {
        return new Collection([
            [
                'MSI',
                'GROSIR',
                'JAKARTA',
                'JKT-UTARA',
                'GROSIR001',
                'TOKO MAJU JAYA',
                'JL. RAYA NO. 1',
                'PENJARINGAN',
                0,
            ],
        ]);
    }
}
