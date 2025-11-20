<?php

namespace App\Exports\Templates;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UserMasterTemplate implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function title(): string
    {
        return 'User';
    }

    public function headings(): array
    {
        return [
            'username',
            'nama_lengkap',
            'badan_usaha',
            'divisi',
            'region',
            'cluster',
        ];
    }

    public function collection(): Collection
    {
        // Ambil data user dengan filter yang sama seperti di PlanVisitResource
        $query = User::query()
            ->with(['badanusaha', 'divisi', 'region', 'cluster'])
            ->select([
                'users.username',
                'users.nama_lengkap',
                'badan_usahas.name as badan_usaha_name',
                'divisions.name as divisi_name',
                'regions.name as region_name',
                'clusters.name as cluster_name',
            ])
            ->leftJoin('badan_usahas', 'users.badanusaha_id', '=', 'badan_usahas.id')
            ->leftJoin('divisions', 'users.divisi_id', '=', 'divisions.id')
            ->leftJoin('regions', 'users.region_id', '=', 'regions.id')
            ->leftJoin('clusters', 'users.cluster_id', '=', 'clusters.id')
            ->whereNotNull('users.nama_lengkap')
            ->where('users.nama_lengkap', '!=', '');

        // Terapkan filter yang sama seperti di UserResource berdasarkan role user
        if (Auth::check()) {
            $user = Auth::user();
            $role = $user->role;
            $scopeLevel = $role->organizational_scope_level ?? 'cluster';

            if ($scopeLevel !== 'all') {
                // Get user's organizational assignments from pivot tables
                $badanUsahaIds = $user->badanUsahas()->pluck('badan_usahas.id')->toArray();
                $divisiIds = $user->divisis()->pluck('divisions.id')->toArray();
                $regionIds = $user->regions()->pluck('regions.id')->toArray();
                $clusterIds = $user->clusters()->pluck('clusters.id')->toArray();

                // Note: Users table still uses single foreign keys for joins
                if (! empty($badanUsahaIds)) {
                    $query->whereIn('users.badanusaha_id', $badanUsahaIds);
                }
                if (! empty($divisiIds)) {
                    $query->whereIn('users.divisi_id', $divisiIds);
                }
                if (! empty($regionIds)) {
                    $query->whereIn('users.region_id', $regionIds);
                }
                if (! empty($clusterIds)) {
                    $query->whereIn('users.cluster_id', $clusterIds);
                }
            }
        }

        // Ambil semua user sesuai filter
        $users = $query->orderBy('users.nama_lengkap', 'asc')->get();

        // Transform data
        return $users->map(function ($user) {
            return [
                $user->username,                        // username
                $user->nama_lengkap,                    // nama_lengkap
                $user->badan_usaha_name ?? '',          // badan_usaha
                $user->divisi_name ?? '',               // divisi
                $user->region_name ?? '',               // region
                $user->cluster_name ?? '',              // cluster
            ];
        });
    }

    public function styles(Worksheet $sheet)
    {
        // Set background color untuk header - biru dengan teks putih
        $sheet->getStyle('A1:F1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('3B82F6');

        $sheet->getStyle('A1:F1')->getFont()
            ->setBold(true)
            ->getColor()->setRGB('FFFFFF');

        return $sheet;
    }
}
