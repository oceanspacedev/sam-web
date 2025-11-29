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

class OutletUpdatedSheet implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
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
            'nama_outlet',
            'nama_pemilik_outlet',
            'nomer_tlp_outlet',
            'distric',
            'limit',
            'status_outlet',
            'badan_usaha_baru',
            'divisi_baru',
            'region_baru',
            'cluster_baru',
            'kode_outlet_baru',
            'nama_outlet_baru',
            'nama_pemilik_outlet_baru',
            'nomer_tlp_outlet_baru',
            'distric_baru',
            'limit_baru',
            'status_outlet_baru',
        ];
    }

    public function collection(): Collection
    {
        $query = Outlet::query()
            ->with(['badanusaha', 'divisi', 'region', 'cluster'])
            ->select([
                'outlets.*',
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
                $outlet->badan_usaha_name ?? '',
                $outlet->divisi_name ?? '',
                $outlet->region_name ?? '',
                $outlet->cluster_name ?? '',
                $outlet->kode_outlet,
                $outlet->nama_outlet,
                $outlet->nama_pemilik_outlet ?? '',
                $outlet->nomer_tlp_outlet ?? '',
                $outlet->distric ?? '',
                $outlet->limit ?? 0,
                $outlet->status_outlet ?? 'MAINTAIN',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
            ];
        });
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:K1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D1FAD7');

        $sheet->getStyle('A1:K1')->getFont()
            ->setBold(true)
            ->getColor()->setRGB('000000');

        $sheet->getStyle('L1:V1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('FEF3C7');

        $sheet->getStyle('L1:V1')->getFont()
            ->setBold(true)
            ->getColor()->setRGB('000000');

        return $sheet;
    }
}
