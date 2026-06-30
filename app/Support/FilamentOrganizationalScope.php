<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class FilamentOrganizationalScope
{
    /**
     * @return 'block'|'all'|array{badanusaha: array<int>, divisi: array<int>, region: array<int>, cluster: array<int>, scope_level: string}
     */
    public static function resolve(User $user): string|array
    {
        if (! $user->role || ! $user->role->organizational_scope_level) {
            return 'block';
        }

        if ($user->role->organizational_scope_level === 'all') {
            return 'all';
        }

        $ids = $user->getOrganizationalIds();

        if (! self::hasAnyAssignment($ids)) {
            return 'block';
        }

        return $ids;
    }

    /**
     * @param  array{badanusaha?: array<int>, divisi?: array<int>, region?: array<int>, cluster?: array<int>}  $ids
     */
    public static function hasAnyAssignment(array $ids): bool
    {
        return ! empty($ids['badanusaha'])
            || ! empty($ids['divisi'])
            || ! empty($ids['region'])
            || ! empty($ids['cluster']);
    }

    public static function applyDirectColumns(
        Builder $query,
        User $user,
        string $table,
        array $columnMap = [
            'badanusaha' => 'badanusaha_id',
            'divisi' => 'divisi_id',
            'region' => 'region_id',
            'cluster' => 'cluster_id',
        ],
    ): Builder {
        $resolved = self::resolve($user);

        if ($resolved === 'block') {
            return $query->whereRaw('1 = 0');
        }

        if ($resolved === 'all') {
            return $query;
        }

        return self::applyResolvedDirectColumns($query, $resolved, $table, $columnMap);
    }

    public static function applyDivisionScope(Builder $query, User $user): Builder
    {
        $resolved = self::resolve($user);

        if ($resolved === 'block') {
            return $query->whereRaw('1 = 0');
        }

        if ($resolved === 'all') {
            return $query;
        }

        if (empty($resolved['badanusaha']) && empty($resolved['divisi'])) {
            return $query->whereRaw('1 = 0');
        }

        return self::applyResolvedDirectColumns($query, $resolved, 'divisions', [
            'badanusaha' => 'badanusaha_id',
            'divisi' => 'id',
        ]);
    }

    public static function applyRegionScope(Builder $query, User $user): Builder
    {
        $resolved = self::resolve($user);

        if ($resolved === 'block') {
            return $query->whereRaw('1 = 0');
        }

        if ($resolved === 'all') {
            return $query;
        }

        if (empty($resolved['badanusaha']) && empty($resolved['divisi']) && empty($resolved['region'])) {
            return $query->whereRaw('1 = 0');
        }

        return self::applyResolvedDirectColumns($query, $resolved, 'regions', [
            'badanusaha' => 'badanusaha_id',
            'divisi' => 'divisi_id',
            'region' => 'id',
        ]);
    }

    /**
     * @param  array{badanusaha: array<int>, divisi: array<int>, region: array<int>, cluster: array<int>, scope_level: string}  $resolved
     * @param  array<string, string>  $columnMap
     */
    protected static function applyResolvedDirectColumns(
        Builder $query,
        array $resolved,
        string $table,
        array $columnMap,
    ): Builder {
        foreach ($columnMap as $key => $column) {
            if (! empty($resolved[$key])) {
                $query->whereIn("{$table}.{$column}", $resolved[$key]);
            }
        }

        return $query;
    }

    public static function applyBadanUsahaIds(Builder $query, User $user, string $column = 'badan_usahas.id'): Builder
    {
        $resolved = self::resolve($user);

        if ($resolved === 'block') {
            return $query->whereRaw('1 = 0');
        }

        if ($resolved === 'all') {
            return $query;
        }

        if (empty($resolved['badanusaha'])) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($column, $resolved['badanusaha']);
    }

    public static function applyViaUserForeignKey(Builder $query, User $user, string $foreignKey = 'user_id'): Builder
    {
        $resolved = self::resolve($user);

        if ($resolved === 'block') {
            return $query->whereRaw('1 = 0');
        }

        if ($resolved === 'all') {
            return $query;
        }

        $userIdsQuery = User::query()->select('users.id');
        self::applyUserScope($userIdsQuery, $user);

        return $query->whereIn($foreignKey, $userIdsQuery);
    }

    public static function applyUserScope(Builder $query, User $viewer): Builder
    {
        $resolved = self::resolve($viewer);

        if ($resolved === 'block') {
            return $query->whereRaw('1 = 0');
        }

        if ($resolved === 'all') {
            return $query;
        }

        return self::applyUserScopeWithIds($query, $resolved);
    }

    /**
     * @param  array{badanusaha: array<int>, divisi: array<int>, region: array<int>, cluster: array<int>, scope_level: string}  $ids
     */
    protected static function applyUserScopeWithIds(Builder $query, array $ids): Builder
    {
        $scopeLevel = $ids['scope_level'];

        if (! empty($ids['badanusaha'])) {
            $query->whereIn('users.id', DB::table('user_badan_usaha')
                ->select('user_id')
                ->whereIn('badanusaha_id', $ids['badanusaha']));
        }

        if (! empty($ids['divisi'])) {
            $query->whereIn('users.id', DB::table('user_divisi')
                ->select('user_id')
                ->whereIn('divisi_id', $ids['divisi']));
        }

        if (in_array($scopeLevel, ['region', 'cluster'], true) && ! empty($ids['region'])) {
            $query->whereIn('users.id', DB::table('user_regions')
                ->select('user_id')
                ->whereIn('region_id', $ids['region']));
        }

        if ($scopeLevel === 'cluster' && ! empty($ids['cluster'])) {
            $query->whereIn('users.id', DB::table('user_clusters')
                ->select('user_id')
                ->whereIn('cluster_id', $ids['cluster']));
        }

        return $query;
    }
}
