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

        $badanUsahaIds = $user->badanUsahas()->pluck('badan_usahas.id')->all();
        $divisiIds = $user->divisis()->pluck('divisions.id')->all();
        $regionIds = $user->regions()->pluck('regions.id')->all();
        $clusterIds = $user->clusters()->pluck('clusters.id')->all();

        $hasAnyAssignment = $badanUsahaIds !== []
            || $divisiIds !== []
            || $regionIds !== []
            || $clusterIds !== [];

        if (! $hasAnyAssignment) {
            return $query->whereRaw('1 = 0');
        }

        if ($badanUsahaIds !== []) {
            $query->whereIn($badanUsahaColumn, $badanUsahaIds);
        }

        if ($divisiColumn !== null && $divisiIds !== []) {
            $query->whereIn($divisiColumn, $divisiIds);
        }

        if ($regionColumn !== null && $regionIds !== []) {
            $query->whereIn($regionColumn, $regionIds);
        }

        if ($clusterColumn !== null && $clusterIds !== []) {
            $query->whereIn($clusterColumn, $clusterIds);
        }

        return $query;
    }
}
