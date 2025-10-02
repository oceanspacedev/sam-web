<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TemplatePlanVisiExport implements ShouldAutoSize, WithHeadings
{
    /**
     * @return Collection
     */
    public function headings(): array
    {
        return [
            'nama',
            'kode_outlet',
            'tanggal_visit',
        ];
    }
}
