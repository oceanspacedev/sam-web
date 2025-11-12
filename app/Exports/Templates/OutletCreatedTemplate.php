<?php

namespace App\Exports\Templates;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class OutletCreatedTemplate implements FromCollection, ShouldAutoSize, WithHeadings, WithTitle
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
                'MSI',                 // badan_usaha
                'GROSIR',              // divisi
                'JAKARTA',             // region
                'JKT-UTARA',           // cluster
                'GROSIR001',           // kode_outlet
                'TOKO MAJU JAYA',      // nama_outlet
                'JL. RAYA NO. 1',      // alamat_outlet
                'PENJARINGAN',         // distric
                0,                     // limit
            ],
        ]);
    }
}
