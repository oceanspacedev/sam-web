<?php

namespace App\Support;

use App\Exceptions\Api\BadRequestException;
use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Region;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OrganizationalManagementScope
{
    /** @var array<string, int> */
    private const LEVELS = [
        'badanusaha' => 1,
        'divisi' => 2,
        'region' => 3,
        'cluster' => 4,
    ];

    public static function scopeLevel(User $user): string
    {
        return strtolower((string) ($user->role?->organizational_scope_level ?? 'cluster'));
    }

    public static function canCreateAtLevel(User $user, string $recordLevel): bool
    {
        $scopeLevel = self::scopeLevel($user);

        if ($scopeLevel === 'all') {
            return true;
        }

        if ($recordLevel === 'badanusaha') {
            return false;
        }

        $scopeRank = self::LEVELS[$scopeLevel] ?? 99;
        $recordRank = self::LEVELS[$recordLevel] ?? 0;

        return $scopeRank >= $recordRank;
    }

    public static function assertCanCreateAtLevel(User $user, string $recordLevel): void
    {
        if (! self::canCreateAtLevel($user, $recordLevel)) {
            throw new BadRequestException('Scope organisasi Anda tidak memiliki akses untuk membuat record pada level ini.');
        }
    }

    public static function applyBadanUsahaQuery(Builder $query, User $user): Builder
    {
        $scopeLevel = self::scopeLevel($user);

        if ($scopeLevel === 'all') {
            return $query;
        }

        $badanUsahaIds = $user->badanUsahas()->pluck('badan_usahas.id')->all();

        if ($badanUsahaIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('id', $badanUsahaIds);
    }

    public static function applyDivisionQuery(Builder $query, User $user): Builder
    {
        return self::applyHierarchyQuery(
            $query,
            $user,
            badanUsahaColumn: 'badanusaha_id',
            divisiColumn: 'id',
        );
    }

    public static function applyRegionQuery(Builder $query, User $user): Builder
    {
        return self::applyHierarchyQuery(
            $query,
            $user,
            badanUsahaColumn: 'badanusaha_id',
            divisiColumn: 'divisi_id',
            regionColumn: 'id',
        );
    }

    public static function applyClusterQuery(Builder $query, User $user): Builder
    {
        return self::applyHierarchyQuery(
            $query,
            $user,
            badanUsahaColumn: 'badanusaha_id',
            divisiColumn: 'divisi_id',
            regionColumn: 'region_id',
            clusterColumn: 'id',
        );
    }

    public static function findVisibleBadanUsaha(User $user, int $id): ?BadanUsaha
    {
        return self::applyBadanUsahaQuery(BadanUsaha::query()->active(), $user)
            ->whereKey($id)
            ->first();
    }

    public static function findVisibleDivision(User $user, int $id): ?Division
    {
        return self::applyDivisionQuery(Division::query()->active(), $user)
            ->whereKey($id)
            ->first();
    }

    public static function findVisibleRegion(User $user, int $id): ?Region
    {
        return self::applyRegionQuery(Region::query()->active(), $user)
            ->whereKey($id)
            ->first();
    }

    public static function findVisibleCluster(User $user, int $id): ?Cluster
    {
        return self::applyClusterQuery(Cluster::query()->active(), $user)
            ->whereKey($id)
            ->first();
    }

    public static function assertVisibleParent(User $user, Model $parent, string $level): void
    {
        $visible = match ($level) {
            'badanusaha' => self::findVisibleBadanUsaha($user, (int) $parent->getKey()) !== null,
            'divisi' => self::findVisibleDivision($user, (int) $parent->getKey()) !== null,
            'region' => self::findVisibleRegion($user, (int) $parent->getKey()) !== null,
            default => false,
        };

        if (! $visible) {
            throw new BadRequestException('Parent organisasi tidak ditemukan atau di luar scope Anda.');
        }
    }

    /**
     * @param  callable(Builder, User): Builder|null  $scopeApplier
     */
    public static function findScopedRecord(
        Builder $query,
        User $user,
        int $id,
        callable $scopeApplier,
    ): ?Model {
        $scopedQuery = $scopeApplier($query, $user);

        return $scopedQuery
            ->whereKey($id)
            ->first();
    }

    protected static function applyHierarchyQuery(
        Builder $query,
        User $user,
        string $badanUsahaColumn,
        ?string $divisiColumn = null,
        ?string $regionColumn = null,
        ?string $clusterColumn = null,
    ): Builder {
        $scopeLevel = self::scopeLevel($user);

        if ($scopeLevel === 'all') {
            return $query;
        }

        $grants = $user->getEffectiveOrganizationalGrants();

        if (OrganizationalEffectiveGrants::isEmpty($grants)) {
            return $query->whereRaw('1 = 0');
        }

        $columnMap = ['badanusaha' => $badanUsahaColumn];

        if ($divisiColumn !== null) {
            $columnMap['divisi'] = $divisiColumn;
        }

        if ($regionColumn !== null) {
            $columnMap['region'] = $regionColumn;
        }

        if ($clusterColumn !== null) {
            $columnMap['cluster'] = $clusterColumn;
        }

        $table = $query->getModel()->getTable();

        // For hierarchy entity listing (id columns), expand full grants downward
        // so a full-divisi grant includes all regions/clusters under it.
        if (($divisiColumn === 'id' || $regionColumn === 'id' || $clusterColumn === 'id')) {
            $entity = match (true) {
                $clusterColumn === 'id' => 'cluster',
                $regionColumn === 'id' => 'region',
                $divisiColumn === 'id' => 'divisi',
                default => 'badanusaha',
            };

            $raw = [
                'badanusaha' => array_map('intval', $user->badanUsahas()->pluck('badan_usahas.id')->all()),
                'divisi' => array_map('intval', $user->divisis()->pluck('divisions.id')->all()),
                'region' => array_map('intval', $user->regions()->pluck('regions.id')->all()),
                'cluster' => array_map('intval', $user->clusters()->pluck('clusters.id')->all()),
            ];

            return OrganizationalEffectiveGrants::applyHierarchyVisibility($query, $grants, $raw, $entity);
        }

        return OrganizationalEffectiveGrants::applyOrColumns($query, $grants, $table, $columnMap);
    }
}
