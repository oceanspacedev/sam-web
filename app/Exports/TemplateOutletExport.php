<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TemplateOutletExport implements ShouldAutoSize, WithHeadings
{
    /**
     * @return \Illuminate\Support\Collection
     */
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
            'status',
            'radius',
            'limit',
            'latlong',
        ];
    }
}
