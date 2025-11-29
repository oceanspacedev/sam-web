<?php

namespace App\Exports\Visit;

use App\Models\User;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class VisitsThisMonthSheet implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(public User $user) {}

    public function collection(): Collection
    {
        $start = Carbon::now()->startOfMonth()->toDateString();
        $end = Carbon::now()->endOfMonth()->toDateString();

        $visits = Visit::query()
            ->with(['outlet:id,kode_outlet,nama_outlet,distric,region_id,cluster_id,status_outlet,latlong', 'outlet.region:id,name', 'outlet.cluster:id,name'])
            ->where('user_id', $this->user->id)
            ->whereBetween('tanggal_visit', [$start, $end])
            ->orderBy('tanggal_visit')
            ->get();

        return $visits->map(function (Visit $v) {
            return [
                'Tanggal Visit' => Carbon::parse($v->tanggal_visit)->format('Y-m-d'),
                'Kode Outlet' => $v->outlet?->kode_outlet,
                'Nama Outlet' => $v->outlet?->nama_outlet,
                'Distrik' => $v->outlet?->distric,
                'Region' => $v->outlet?->region?->name,
                'Cluster' => $v->outlet?->cluster?->name,
                'Tipe Visit' => $v->tipe_visit,
                'Durasi (mnt)' => $v->durasi_visit,
                'Check In' => $v->check_in_time ? Carbon::parse($v->check_in_time)->format('Y-m-d H:i') : null,
                'Check Out' => $v->check_out_time ? Carbon::parse($v->check_out_time)->format('Y-m-d H:i') : null,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Tanggal Visit', 'Kode Outlet', 'Nama Outlet', 'Distrik', 'Region', 'Cluster', 'Tipe Visit', 'Durasi (mnt)', 'Check In', 'Check Out',
        ];
    }

    public function title(): string
    {
        return 'Visits (This Month)';
    }
}
