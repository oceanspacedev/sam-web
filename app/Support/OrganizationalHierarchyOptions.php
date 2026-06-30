<?php

namespace App\Support;

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Region;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class OrganizationalHierarchyOptions
{
    public static function badanUsahaLabel(int|string|null $id, ?User $user = null, bool $activeOnly = false): ?string
    {
        if (! $id) {
            return null;
        }

        return OrganizationalName::label(
            self::badanUsahaQuery($user, $activeOnly)->whereKey($id)->first(['id', 'code', 'name'])
                ?? BadanUsaha::query()->whereKey($id)->first(['id', 'code', 'name'])
        );
    }

    public static function divisionLabel(int|string|null $id, bool $activeOnly = false): ?string
    {
        if (! $id) {
            return null;
        }

        $division = Division::query()
            ->when($activeOnly, fn (Builder $query) => $query->active())
            ->whereKey($id)
            ->first(['id', 'code', 'name']);

        return OrganizationalName::label($division);
    }

    public static function regionLabel(int|string|null $id, bool $activeOnly = false): ?string
    {
        if (! $id) {
            return null;
        }

        $region = Region::query()
            ->when($activeOnly, fn (Builder $query) => $query->active())
            ->whereKey($id)
            ->first(['id', 'code', 'name']);

        return OrganizationalName::label($region);
    }

    public static function clusterLabel(int|string|null $id, bool $activeOnly = false): ?string
    {
        if (! $id) {
            return null;
        }

        $cluster = Cluster::query()
            ->when($activeOnly, fn (Builder $query) => $query->active())
            ->whereKey($id)
            ->first(['id', 'code', 'name']);

        return OrganizationalName::label($cluster);
    }

    public static function searchBadanUsaha(string $search, ?User $user = null, bool $activeOnly = true, int $limit = 50): array
    {
        $keyword = trim($search);

        $query = self::badanUsahaQuery($user, $activeOnly)
            ->when($keyword !== '', fn (Builder $query) => OrganizationalName::applySearch($query, $keyword))
            ->limit($limit);

        return self::options($query);
    }

    public static function searchDivision(
        string $search,
        ?int $badanUsahaId,
        ?User $user = null,
        bool $activeOnly = true,
        int $limit = 50
    ): array {
        if (! $badanUsahaId) {
            return [];
        }

        $keyword = trim($search);

        $query = self::divisionQuery($badanUsahaId, $user, $activeOnly)
            ->when($keyword !== '', fn (Builder $query) => OrganizationalName::applySearch($query, $keyword))
            ->limit($limit);

        return self::options($query);
    }

    public static function searchRegion(
        string $search,
        ?int $divisionId,
        ?User $user = null,
        bool $activeOnly = true,
        int $limit = 50
    ): array {
        if (! $divisionId) {
            return [];
        }

        $keyword = trim($search);

        $query = self::regionQuery($divisionId, $user, $activeOnly)
            ->when($keyword !== '', fn (Builder $query) => OrganizationalName::applySearch($query, $keyword))
            ->limit($limit);

        return self::options($query);
    }

    public static function searchCluster(
        string $search,
        ?int $regionId,
        ?User $user = null,
        bool $activeOnly = true,
        int $limit = 50
    ): array {
        if (! $regionId) {
            return [];
        }

        $keyword = trim($search);

        $query = self::clusterQuery($regionId, $user, $activeOnly)
            ->when($keyword !== '', fn (Builder $query) => OrganizationalName::applySearch($query, $keyword))
            ->limit($limit);

        return self::options($query);
    }

    public static function activeBadanUsaha(): array
    {
        $query = BadanUsaha::query()
            ->active()
            ->orderBy('code', 'asc');

        return self::options($query);
    }

    public static function activeDivision(?int $badanUsahaId = null): array
    {
        $query = Division::query()
            ->active()
            ->when($badanUsahaId, fn (Builder $query) => $query->where('badanusaha_id', $badanUsahaId))
            ->orderBy('code', 'asc');

        return self::options($query);
    }

    public static function activeRegion(?int $divisionId = null): array
    {
        $query = Region::query()
            ->active()
            ->when($divisionId, fn (Builder $query) => $query->where('divisi_id', $divisionId))
            ->orderBy('code', 'asc');

        return self::options($query);
    }

    public static function activeCluster(?int $regionId = null): array
    {
        $query = Cluster::query()
            ->active()
            ->when($regionId, fn (Builder $query) => $query->where('region_id', $regionId))
            ->orderBy('code', 'asc');

        return self::options($query);
    }

    public static function badanUsaha(?User $user = null, bool $activeOnly = true): array
    {
        return self::options(self::badanUsahaQuery($user, $activeOnly));
    }

    public static function division(?int $badanUsahaId, ?User $user = null, bool $activeOnly = true): array
    {
        return self::options(self::divisionQuery($badanUsahaId, $user, $activeOnly));
    }

    public static function region(?int $divisionId, ?User $user = null, bool $activeOnly = true): array
    {
        return self::options(self::regionQuery($divisionId, $user, $activeOnly));
    }

    public static function cluster(?int $regionId, ?User $user = null, bool $activeOnly = true): array
    {
        return self::options(self::clusterQuery($regionId, $user, $activeOnly));
    }

    public static function badanUsahaQuery(?User $user = null, bool $activeOnly = true): Builder
    {
        $query = BadanUsaha::query();

        return self::applyBadanUsahaScope($query, $user, $activeOnly);
    }

    public static function applyBadanUsahaScope(Builder $query, ?User $user = null, bool $activeOnly = true): Builder
    {
        if ($activeOnly) {
            $query->active();
        }

        $user = self::resolveUser($user);

        if (! $user || ! $user->role) {
            return $query->whereRaw('1 = 0');
        }

        if (self::scopeLevel($user) === 'all') {
            return $query->orderBy('code', 'asc');
        }

        $badanUsahaIds = self::organizationalIds($user, 'badanusaha');

        if (empty($badanUsahaIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->whereIn('badan_usahas.id', $badanUsahaIds)
            ->orderBy('code', 'asc');
    }

    public static function divisionQuery(?int $badanUsahaId, ?User $user = null, bool $activeOnly = true): Builder
    {
        $query = Division::query();

        if ($activeOnly) {
            $query->active();
        }

        if (! $badanUsahaId) {
            return $query->whereRaw('1 = 0');
        }

        $query->where('badanusaha_id', $badanUsahaId);

        $user = self::resolveUser($user);

        if (! $user || ! $user->role) {
            return $query->whereRaw('1 = 0');
        }

        if (self::scopeLevel($user) === 'all') {
            return $query->orderBy('code', 'asc');
        }

        $divisionIds = self::organizationalIds($user, 'divisi');

        if (! empty($divisionIds)) {
            $query->whereIn('divisions.id', $divisionIds);
        }

        return $query->orderBy('code', 'asc');
    }

    public static function regionQuery(?int $divisionId, ?User $user = null, bool $activeOnly = true): Builder
    {
        $query = Region::query();

        if ($activeOnly) {
            $query->active();
        }

        if (! $divisionId) {
            return $query->whereRaw('1 = 0');
        }

        $query->where('divisi_id', $divisionId);

        $user = self::resolveUser($user);

        if (! $user || ! $user->role) {
            return $query->whereRaw('1 = 0');
        }

        $scopeLevel = self::scopeLevel($user);

        if ($scopeLevel === 'all') {
            return $query->orderBy('code', 'asc');
        }

        if (in_array($scopeLevel, ['region', 'cluster'], true)) {
            $regionIds = self::organizationalIds($user, 'region');

            if (! empty($regionIds)) {
                $query->whereIn('regions.id', $regionIds);
            }
        }

        return $query->orderBy('code', 'asc');
    }

    public static function clusterQuery(?int $regionId, ?User $user = null, bool $activeOnly = true): Builder
    {
        $query = Cluster::query();

        if ($activeOnly) {
            $query->active();
        }

        if (! $regionId) {
            return $query->whereRaw('1 = 0');
        }

        $query->where('region_id', $regionId);

        $user = self::resolveUser($user);

        if (! $user || ! $user->role) {
            return $query->whereRaw('1 = 0');
        }

        $scopeLevel = self::scopeLevel($user);

        if ($scopeLevel === 'all') {
            return $query->orderBy('code', 'asc');
        }

        if ($scopeLevel === 'cluster') {
            $clusterIds = self::organizationalIds($user, 'cluster');

            if (! empty($clusterIds)) {
                $query->whereIn('clusters.id', $clusterIds);
            }
        }

        return $query->orderBy('code', 'asc');
    }

    protected static function resolveUser(?User $user = null): ?User
    {
        return $user ?? Auth::user();
    }

    protected static function scopeLevel(User $user): string
    {
        return strtolower((string) ($user->role?->organizational_scope_level ?? ''));
    }

    protected static function organizationalIds(User $user, string $key): array
    {
        return $user->getOrganizationalIds()[$key] ?? [];
    }

    private static function options(Builder $query): array
    {
        return OrganizationalName::optionList($query);
    }
}
