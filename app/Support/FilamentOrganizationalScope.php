<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class FilamentOrganizationalScope
{
    /**
     * @return 'block'|'all'|array{badanusaha: array<int>, divisi: array<int>, region: array<int>, cluster: array<int>, scope_level: string, effective: array{badanusaha: array<int>, divisi: array<int>, region: array<int>, cluster: array<int>}}
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

        $ids['effective'] = $user->getEffectiveOrganizationalGrants();

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

        return OrganizationalEffectiveGrants::applyOrColumns(
            $query,
            $resolved['effective'],
            $table,
            $columnMap,
        );
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

        return OrganizationalEffectiveGrants::applyHierarchyVisibility(
            $query,
            $resolved['effective'],
            [
                'badanusaha' => $resolved['badanusaha'] ?? [],
                'divisi' => $resolved['divisi'] ?? [],
                'region' => $resolved['region'] ?? [],
                'cluster' => $resolved['cluster'] ?? [],
            ],
            'divisi',
        );
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

        return OrganizationalEffectiveGrants::applyHierarchyVisibility(
            $query,
            $resolved['effective'],
            [
                'badanusaha' => $resolved['badanusaha'] ?? [],
                'divisi' => $resolved['divisi'] ?? [],
                'region' => $resolved['region'] ?? [],
                'cluster' => $resolved['cluster'] ?? [],
            ],
            'region',
        );
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

        $accessible = $user->getExpandedOrganizationalIds();
        $badanUsahaIds = array_values(array_unique(array_merge(
            $accessible['badanusaha'],
            array_map('intval', $resolved['badanusaha'] ?? []),
        )));

        if ($badanUsahaIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($column, $badanUsahaIds);
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

        return self::applyUserScopeWithIds($query, $resolved, $viewer);
    }

    /**
     * @param  array{badanusaha: array<int>, divisi: array<int>, region: array<int>, cluster: array<int>, scope_level: string, effective: array{badanusaha: array<int>, divisi: array<int>, region: array<int>, cluster: array<int>}}  $ids
     */
    protected static function applyUserScopeWithIds(Builder $query, array $ids, User $viewer): Builder
    {
        $accessible = $viewer->getExpandedOrganizationalIds();

        return $query->where(function (Builder $scope) use ($accessible): void {
            $applied = false;

            if ($accessible['badanusaha'] !== []) {
                $scope->whereIn('users.id', DB::table('user_badan_usaha')
                    ->select('user_id')
                    ->whereIn('badanusaha_id', $accessible['badanusaha']));
                $applied = true;
            }

            if ($accessible['divisi'] !== []) {
                $method = $applied ? 'orWhereIn' : 'whereIn';
                $scope->{$method}('users.id', DB::table('user_divisi')
                    ->select('user_id')
                    ->whereIn('divisi_id', $accessible['divisi']));
                $applied = true;
            }

            if ($accessible['region'] !== []) {
                $method = $applied ? 'orWhereIn' : 'whereIn';
                $scope->{$method}('users.id', DB::table('user_regions')
                    ->select('user_id')
                    ->whereIn('region_id', $accessible['region']));
                $applied = true;
            }

            if ($accessible['cluster'] !== []) {
                $method = $applied ? 'orWhereIn' : 'whereIn';
                $scope->{$method}('users.id', DB::table('user_clusters')
                    ->select('user_id')
                    ->whereIn('cluster_id', $accessible['cluster']));
                $applied = true;
            }

            if (! $applied) {
                $scope->whereRaw('1 = 0');
            }
        });
    }
}
