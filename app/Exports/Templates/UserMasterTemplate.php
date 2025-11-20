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
            ->with(['badanUsahas:id,name', 'divisis:id,name', 'regions:id,name', 'clusters:id,name'])
            ->select([
                'users.id',
                'users.username',
                'users.nama_lengkap',
            ])
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

                if (! empty($badanUsahaIds)) {
                    $query->whereHas('badanUsahas', fn ($q) => $q->whereIn('badan_usahas.id', $badanUsahaIds));
                }
                if (! empty($divisiIds)) {
                    $query->whereHas('divisis', fn ($q) => $q->whereIn('divisions.id', $divisiIds));
                }
                if (! empty($regionIds)) {
                    $query->whereHas('regions', fn ($q) => $q->whereIn('regions.id', $regionIds));
                }
                if (! empty($clusterIds)) {
                    $query->whereHas('clusters', fn ($q) => $q->whereIn('clusters.id', $clusterIds));
                }
            }
        }

        // Ambil semua user sesuai filter
        $users = $query->orderBy('users.nama_lengkap', 'asc')->get();

        // Transform data
        return $users->map(function ($user) {
            $badanUsaha = $user->badanUsahas->first();
            $divisi = $user->divisis->first();
            $region = $user->regions->first();
            $cluster = $user->clusters->first();

            return [
                $user->username,                        // username
                $user->nama_lengkap,                    // nama_lengkap
                $badanUsaha->name ?? '',                // badan_usaha
                $divisi->name ?? '',                    // divisi
                $region->name ?? '',                    // region
                $cluster->name ?? '',                   // cluster
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
