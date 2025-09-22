<?php

namespace App\Exports\Sheets;

use App\Models\Outlet;
use App\Models\User;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class UnvisitedOutletsSheet implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(public User $user) {}

    public function collection(): Collection
    {
        $start = Carbon::now()->startOfMonth()->toDateString();
        $end = Carbon::now()->endOfMonth()->toDateString();

        $visitedOutletIds = Visit::query()
            ->where('user_id', $this->user->id)
            ->whereBetween('tanggal_visit', [$start, $end])
            ->pluck('outlet_id')
            ->unique()
            ->filter()
            ->values();

        $outlets = $this->user->outlet()
            ->when($visitedOutletIds->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $visitedOutletIds))
            ->with(['region:id,name', 'cluster:id,name'])
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
        return 'Unvisited (This Month)';
    }
}
