<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class UserTempateExport implements ShouldAutoSize, WithHeadings
{
    /**
     * @return Collection
     */
    public function headings(): array
    {
        return [
            'nama_lengkap',
            'username',
            'role',
            'badan_usaha',
            'divisi',
            'region',
            'cluster',
            'tm',
            'password',
        ];
    }
}
