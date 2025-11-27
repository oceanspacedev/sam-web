<?php

namespace App\Exports;

use App\Models\Outlet;
use App\Models\User;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class UnvisitedOutletsExport implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(public User $user)
    {
        // no-op
    }

    public function collection(): Collection
    {
        $start = Carbon::now()->startOfMonth()->toDateString();
        $end = Carbon::now()->endOfMonth()->toDateString();

        // Visits by this user in current month
        $visitedOutletIds = Visit::query()
            ->where('user_id', $this->user->id)
            ->whereBetween('tanggal_visit', [$start, $end])
            ->pluck('outlet_id')
            ->unique()
            ->filter()
            ->values();

        // Outlets scoped by organizational visibility
        /** @var EloquentCollection<int, Outlet> $outlets */
        $outlets = Outlet::visibleTo($this->user)
            ->when($visitedOutletIds->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $visitedOutletIds))
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
            'Kode Outlet',
            'Nama Outlet',
            'Distrik',
            'Region',
            'Cluster',
            'Status',
            'LatLong',
        ];
    }

    public function title(): string
    {
        return 'Unvisited Outlets (This Month)';
    }
}
