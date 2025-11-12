<?php

namespace App\Exports\Templates;

use App\Models\Outlet;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OutletMasterTemplate implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
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
        // Ambil data outlet dengan filter yang sama seperti di OutletResource
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

        // Terapkan filter yang sama seperti di OutletUpdatedTemplate berdasarkan role user
        if (Auth::check()) {
            $user = Auth::user();
            $role = $user->role;

            switch ($role->filter_type) {
                case 'badanusaha':
                    $query->whereIn('outlets.badanusaha_id', $role->filter_data ?? []);
                    break;
                case 'divisi':
                    $query->whereIn('outlets.divisi_id', $role->filter_data ?? []);
                    break;
                case 'region':
                    $query->whereIn('outlets.region_id', $role->filter_data ?? []);
                    break;
                case 'cluster':
                    $query->whereIn('outlets.cluster_id', $role->filter_data ?? []);
                    break;
                case 'all':
                default:
                    // Tidak ada filter tambahan
                    break;
            }
        }

        // Ambil semua outlet sesuai filter
        $outlets = $query->orderBy('outlets.kode_outlet', 'asc')->get();

        // Transform data
        return $outlets->map(function ($outlet) {
            return [
                $outlet->kode_outlet,                     // kode_outlet
                $outlet->nama_outlet,                     // nama_outlet
                $outlet->badan_usaha_name ?? '',          // badan_usaha
                $outlet->divisi_name ?? '',               // divisi
                $outlet->region_name ?? '',               // region
                $outlet->cluster_name ?? '',              // cluster
            ];
        });
    }

    public function styles(Worksheet $sheet)
    {
        // Set background color untuk header - hijau dengan teks putih
        $sheet->getStyle('A1:F1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('10B981');

        $sheet->getStyle('A1:F1')->getFont()
            ->setBold(true)
            ->getColor()->setRGB('FFFFFF');

        return $sheet;
    }
}
