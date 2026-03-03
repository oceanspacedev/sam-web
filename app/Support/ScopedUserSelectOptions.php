<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ScopedUserSelectOptions
{
    /**
     * @return array<int, string>
     */
    public static function search(?User $viewer, string $search, int $limit = 50): array
    {
        $query = self::queryForViewer($viewer);
        $keyword = trim($search);

        $query->when($keyword !== '', function (Builder $builder) use ($keyword): void {
            $builder->where('nama_lengkap', 'like', '%'.$keyword.'%');
        });

        return $query
            ->orderBy('nama_lengkap')
            ->limit($limit)
            ->get()
            ->mapWithKeys(fn (User $user): array => [$user->id => self::formatLabel($user)])
            ->all();
    }

    public static function label(int|string|null $id): ?string
    {
        if (! $id) {
            return null;
        }

        $user = User::query()
            ->select(['id', 'nama_lengkap'])
            ->with(['badanUsahas:id,name', 'divisis:id,name'])
            ->find($id);

        if (! $user) {
            return null;
        }

        return self::formatLabel($user);
    }

    protected static function queryForViewer(?User $viewer): Builder
    {
        $query = User::query()
            ->select(['id', 'nama_lengkap'])
            ->with(['badanUsahas:id,name', 'divisis:id,name']);

        if (! $viewer || ! $viewer->role) {
            return $query->whereRaw('1 = 0');
        }

        $scopeLevel = strtolower((string) ($viewer->role->organizational_scope_level ?? ''));

        if ($scopeLevel === 'all') {
            return $query;
        }

        $ids = $viewer->getOrganizationalIds();
        $badanUsahaIds = $ids['badanusaha'] ?? [];
        $divisiIds = $ids['divisi'] ?? [];
        $regionIds = $ids['region'] ?? [];
        $clusterIds = $ids['cluster'] ?? [];

        $hasAnyAssignment = $badanUsahaIds !== [] || $divisiIds !== [] || $regionIds !== [] || $clusterIds !== [];
        if (! $hasAnyAssignment) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $builder) use ($badanUsahaIds, $divisiIds, $regionIds, $clusterIds): void {
            if ($badanUsahaIds !== []) {
                $builder->whereHas('badanUsahas', fn (Builder $scope): Builder => $scope->whereIn('badan_usahas.id', $badanUsahaIds));
            }

            if ($divisiIds !== []) {
                $builder->whereHas('divisis', fn (Builder $scope): Builder => $scope->whereIn('divisions.id', $divisiIds));
            }

            if ($regionIds !== []) {
                $builder->whereHas('regions', fn (Builder $scope): Builder => $scope->whereIn('regions.id', $regionIds));
            }

            if ($clusterIds !== []) {
                $builder->whereHas('clusters', fn (Builder $scope): Builder => $scope->whereIn('clusters.id', $clusterIds));
            }
        });
    }

    protected static function formatLabel(User $user): string
    {
        $badanusahaName = $user->badanUsahas->first()->name ?? 'Tidak ada badan usaha';
        $divisiName = $user->divisis->first()->name ?? 'Tidak ada divisi';

        return "{$user->nama_lengkap} - {$badanusahaName} / {$divisiName}";
    }
}
