<?php

namespace App\Exports\Templates;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class OutletUpdatedTemplate implements FromCollection, ShouldAutoSize, WithHeadings, WithTitle
{
    public function title(): string
    {
        return 'UpdatedCluster';
    }

    public function headings(): array
    {
        return [
            'badan_usaha',
            'divisi',
            'region',
            'cluster',
            'kode_outlet',
            // minimal untuk update cluster adalah referensi kode_outlet + divisi + badan_usaha + region + cluster baru
        ];
    }

    public function collection(): Collection
    {
        return new Collection([
            [
                'MSI',        // badan_usaha
                'GROSIR',     // divisi
                'JAKARTA',    // region (BARU)
                'JKT-BARAT',  // cluster (BARU)
                'GROSIR001',  // kode_outlet (existing)
            ],
        ]);
    }
}
