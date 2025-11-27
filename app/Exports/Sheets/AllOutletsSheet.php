<?php

namespace App\Exports\Sheets;

use App\Models\Outlet;
use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class AllOutletsSheet implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(public User $user) {}

    public function collection(): Collection
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, Outlet> $outlets */
        $outlets = Outlet::visibleTo($this->user)->with(['region:id,name', 'cluster:id,name'])
            ->orderBy('kode_outlet')
            ->get();

        return $outlets->map(function (Outlet $o) {
            return [
                'Kode Outlet' => $o->kode_outlet,
                'Nama Outlet' => $o->nama_outlet,
                'Distrik' => $o->distric,
                'Region' => $o->region?->name,
                'Cluster' => $o->cluster?->name,
                'Status' => $o->status_outlet,
                'LatLong' => $o->latlong,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Kode Outlet', 'Nama Outlet', 'Distrik', 'Region', 'Cluster', 'Status', 'LatLong',
        ];
    }

    public function title(): string
    {
        return 'All Outlets';
    }
}
