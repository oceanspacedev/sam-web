<?php

namespace App\Exports\User;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UserMasterSheet implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
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
        $query = User::query()
            ->with(['badanUsahas:id,name', 'divisis:id,name', 'regions:id,name', 'clusters:id,name'])
            ->select([
                'users.id',
                'users.username',
                'users.nama_lengkap',
            ])
            ->whereNotNull('users.nama_lengkap')
            ->where('users.nama_lengkap', '!=', '');

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

        $users = $query->orderBy('users.nama_lengkap', 'asc')->get();

        return $users->map(function ($user) {
            $badanUsaha = $user->badanUsahas->first();
            $divisi = $user->divisis->first();
            $region = $user->regions->first();
            $cluster = $user->clusters->first();

            return [
                $user->username,
                $user->nama_lengkap,
                $badanUsaha->name ?? '',
                $divisi->name ?? '',
                $region->name ?? '',
                $cluster->name ?? '',
            ];
        });
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:F1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('3B82F6');

        $sheet->getStyle('A1:F1')->getFont()
            ->setBold(true)
            ->getColor()->setRGB('FFFFFF');

        return $sheet;
    }
}
