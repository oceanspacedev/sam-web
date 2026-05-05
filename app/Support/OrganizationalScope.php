<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class OrganizationalScope
{
    /**
     * @param  array{badanusaha?: string, divisi?: string, region?: string, cluster?: string}  $columns
     */
    public static function applyToQuery(Builder $query, ?User $user, string $table, array $columns = []): Builder
    {
        $columns = array_merge([
            'badanusaha' => 'badanusaha_id',
            'divisi' => 'divisi_id',
            'region' => 'region_id',
            'cluster' => 'cluster_id',
        ], $columns);

        if (! $user || ! $user->role) {
            return self::block($query);
        }

        $scopeLevel = strtolower((string) ($user->role->organizational_scope_level ?? 'cluster'));

        if ($scopeLevel === '') {
            return self::block($query);
        }

        if ($scopeLevel === 'all') {
            return $query;
        }

        $badanUsahaIds = $user->badanUsahas()->pluck('badan_usahas.id')->all();
        $divisiIds = $user->divisis()->pluck('divisions.id')->all();
        $regionIds = $user->regions()->pluck('regions.id')->all();
        $clusterIds = $user->clusters()->pluck('clusters.id')->all();

        match ($scopeLevel) {
            'badanusaha' => self::applyBadanUsahaScope($query, $table, $columns, $badanUsahaIds),
            'divisi' => self::applyDivisiScope($query, $table, $columns, $badanUsahaIds, $divisiIds),
            'region' => self::applyRegionScope($query, $table, $columns, $badanUsahaIds, $divisiIds, $regionIds),
            'cluster' => self::applyClusterScope($query, $table, $columns, $badanUsahaIds, $divisiIds, $regionIds, $clusterIds),
            default => self::block($query),
        };

        return $query;
    }

    /**
     * @param  array{badanusaha: string, divisi: string, region: string, cluster: string}  $columns
     * @param  array<int, int>  $badanUsahaIds
     */
    protected static function applyBadanUsahaScope(Builder $query, string $table, array $columns, array $badanUsahaIds): void
    {
        if (! empty($badanUsahaIds)) {
            $query->whereIn($table.'.'.$columns['badanusaha'], $badanUsahaIds);
        }
    }

    /**
     * @param  array{badanusaha: string, divisi: string, region: string, cluster: string}  $columns
     * @param  array<int, int>  $badanUsahaIds
     * @param  array<int, int>  $divisiIds
     */
    protected static function applyDivisiScope(Builder $query, string $table, array $columns, array $badanUsahaIds, array $divisiIds): void
    {
        self::applyBadanUsahaScope($query, $table, $columns, $badanUsahaIds);

        if (! empty($divisiIds)) {
            $query->whereIn($table.'.'.$columns['divisi'], $divisiIds);
        }
    }

    /**
     * @param  array{badanusaha: string, divisi: string, region: string, cluster: string}  $columns
     * @param  array<int, int>  $badanUsahaIds
     * @param  array<int, int>  $divisiIds
     * @param  array<int, int>  $regionIds
     */
    protected static function applyRegionScope(Builder $query, string $table, array $columns, array $badanUsahaIds, array $divisiIds, array $regionIds): void
    {
        self::applyDivisiScope($query, $table, $columns, $badanUsahaIds, $divisiIds);

        if (! empty($regionIds)) {
            $query->whereIn($table.'.'.$columns['region'], $regionIds);
        }
    }

    /**
     * @param  array{badanusaha: string, divisi: string, region: string, cluster: string}  $columns
     * @param  array<int, int>  $badanUsahaIds
     * @param  array<int, int>  $divisiIds
     * @param  array<int, int>  $regionIds
     * @param  array<int, int>  $clusterIds
     */
    protected static function applyClusterScope(Builder $query, string $table, array $columns, array $badanUsahaIds, array $divisiIds, array $regionIds, array $clusterIds): void
    {
        self::applyRegionScope($query, $table, $columns, $badanUsahaIds, $divisiIds, $regionIds);

        if (! empty($clusterIds)) {
            $query->whereIn($table.'.'.$columns['cluster'], $clusterIds);
        }
    }

    protected static function block(Builder $query): Builder
    {
        return $query->whereRaw('1 = 0');
    }
}
