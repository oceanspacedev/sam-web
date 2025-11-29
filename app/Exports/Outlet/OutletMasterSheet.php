<?php

namespace App\Exports\Outlet;

use App\Models\Outlet;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OutletMasterSheet implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function title(): string
    {
        return 'Outlet';
    }

    public function headings(): array
    {
        return [
            'kode_outlet',
            'nama_outlet',
            'badan_usaha',
            'divisi',
            'region',
            'cluster',
        ];
    }

    public function collection(): Collection
    {
        $query = Outlet::query()
            ->with(['badanusaha', 'divisi', 'region', 'cluster'])
            ->select([
                'outlets.kode_outlet',
                'outlets.nama_outlet',
                'badan_usahas.name as badan_usaha_name',
                'divisions.name as divisi_name',
                'regions.name as region_name',
                'clusters.name as cluster_name',
            ])
            ->leftJoin('badan_usahas', 'outlets.badanusaha_id', '=', 'badan_usahas.id')
            ->leftJoin('divisions', 'outlets.divisi_id', '=', 'divisions.id')
            ->leftJoin('regions', 'outlets.region_id', '=', 'regions.id')
            ->leftJoin('clusters', 'outlets.cluster_id', '=', 'clusters.id');

        if (Auth::check()) {
            $user = Auth::user();
            $role = $user->role;
            $scopeLevel = $role->organizational_scope_level ?? 'cluster';

            if ($scopeLevel !== 'all') {
                $badanUsahaIds = $user->badanUsahas()->pluck('badan_usahas.id')->toArray();
                $divisiIds = $user->divisis()->pluck('divisions.id')->toArray();
                $regionIds = $user->regions()->pluck('regions.id')->toArray();
                $clusterIds = $user->clusters()->pluck('clusters.id')->toArray();

                if (! empty($badanUsahaIds)) {
                    $query->whereIn('outlets.badanusaha_id', $badanUsahaIds);
                }
                if (! empty($divisiIds)) {
                    $query->whereIn('outlets.divisi_id', $divisiIds);
                }
                if (! empty($regionIds)) {
                    $query->whereIn('outlets.region_id', $regionIds);
                }
                if (! empty($clusterIds)) {
                    $query->whereIn('outlets.cluster_id', $clusterIds);
                }
            }
        }

        $outlets = $query->orderBy('outlets.kode_outlet', 'asc')->get();

        return $outlets->map(function ($outlet) {
            return [
                $outlet->kode_outlet,
                $outlet->nama_outlet,
                $outlet->badan_usaha_name ?? '',
                $outlet->divisi_name ?? '',
                $outlet->region_name ?? '',
                $outlet->cluster_name ?? '',
            ];
        });
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:F1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('10B981');

        $sheet->getStyle('A1:F1')->getFont()
            ->setBold(true)
            ->getColor()->setRGB('FFFFFF');

        return $sheet;
    }
}
