<?php

namespace App\Support;

use App\Models\Cluster;
use App\Models\Division;
use App\Models\Region;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class OrganizationalEffectiveGrants
{
    /**
     * Resolve full grants per level from raw pivot assignments.
     *
     * A coarser assignment is a "full" grant only when no finer assignment
     * exists under that branch. Visibility is the OR of these grants.
     *
     * @param  array{badanusaha?: array<int|string>, divisi?: array<int|string>, region?: array<int|string>, cluster?: array<int|string>}  $assignments
     * @return array{badanusaha: array<int>, divisi: array<int>, region: array<int>, cluster: array<int>}
     */
    public static function fromAssignments(array $assignments): array
    {
        $badanUsahaIds = self::normalizeIds($assignments['badanusaha'] ?? []);
        $divisiIds = self::normalizeIds($assignments['divisi'] ?? []);
        $regionIds = self::normalizeIds($assignments['region'] ?? []);
        $clusterIds = self::normalizeIds($assignments['cluster'] ?? []);

        $clusterAncestors = self::clusterAncestors($clusterIds);
        $regionAncestors = self::regionAncestors($regionIds);

        $regionsCoveredByClusters = array_fill_keys($clusterAncestors['regions'], true);
        $divisisCoveredByFiner = array_fill_keys(array_merge(
            $clusterAncestors['divisis'],
            $regionAncestors['divisis'],
        ), true);
        $badanUsahasCoveredByFiner = array_fill_keys(array_merge(
            $clusterAncestors['badanusahas'],
            $regionAncestors['badanusahas'],
            self::divisionBadanUsahaIds($divisiIds),
        ), true);

        return [
            'cluster' => $clusterIds,
            'region' => array_values(array_filter(
                $regionIds,
                static fn (int $id): bool => ! isset($regionsCoveredByClusters[$id]),
            )),
            'divisi' => array_values(array_filter(
                $divisiIds,
                static fn (int $id): bool => ! isset($divisisCoveredByFiner[$id]),
            )),
            'badanusaha' => array_values(array_filter(
                $badanUsahaIds,
                static fn (int $id): bool => ! isset($badanUsahasCoveredByFiner[$id]),
            )),
        ];
    }

    /**
     * @return array{badanusaha: array<int>, divisi: array<int>, region: array<int>, cluster: array<int>}
     */
    public static function forUser(User $user): array
    {
        $ids = $user->getOrganizationalIds();
        $scopeLevel = strtolower((string) ($ids['scope_level'] ?? 'cluster'));

        $assignments = [
            'badanusaha' => $ids['badanusaha'] ?? [],
            'divisi' => $ids['divisi'] ?? [],
            'region' => $ids['region'] ?? [],
            'cluster' => $ids['cluster'] ?? [],
        ];

        // Role scope is the maximum assignment depth. Ignore finer pivots that
        // may be stale leftovers from a previous role (e.g. clusters on a
        // region-scoped user).
        $assignments = match ($scopeLevel) {
            'badanusaha' => [
                'badanusaha' => $assignments['badanusaha'],
                'divisi' => [],
                'region' => [],
                'cluster' => [],
            ],
            'divisi' => [
                'badanusaha' => $assignments['badanusaha'],
                'divisi' => $assignments['divisi'],
                'region' => [],
                'cluster' => [],
            ],
            'region' => [
                'badanusaha' => $assignments['badanusaha'],
                'divisi' => $assignments['divisi'],
                'region' => $assignments['region'],
                'cluster' => [],
            ],
            default => $assignments,
        };

        return self::fromAssignments($assignments);
    }

    /**
     * @param  array{badanusaha: array<int>, divisi: array<int>, region: array<int>, cluster: array<int>}  $grants
     */
    public static function isEmpty(array $grants): bool
    {
        return $grants['badanusaha'] === []
            && $grants['divisi'] === []
            && $grants['region'] === []
            && $grants['cluster'] === [];
    }

    /**
     * Apply OR visibility for records that store badanusaha/divisi/region/cluster columns.
     *
     * @param  array{badanusaha?: string, divisi?: string, region?: string, cluster?: string}  $columnMap
     * @param  array{badanusaha: array<int>, divisi: array<int>, region: array<int>, cluster: array<int>}  $grants
     */
    public static function applyOrColumns(Builder $query, array $grants, string $table, array $columnMap): Builder
    {
        if (self::isEmpty($grants)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $scope) use ($grants, $table, $columnMap): void {
            $applied = false;

            foreach (['cluster', 'region', 'divisi', 'badanusaha'] as $level) {
                $column = $columnMap[$level] ?? null;
                $ids = $grants[$level] ?? [];

                if ($column === null || $ids === []) {
                    continue;
                }

                $qualified = str_contains($column, '.') ? $column : "{$table}.{$column}";

                if (! $applied) {
                    $scope->whereIn($qualified, $ids);
                    $applied = true;

                    continue;
                }

                $scope->orWhereIn($qualified, $ids);
            }

            if (! $applied) {
                $scope->whereRaw('1 = 0');
            }
        });
    }

    /**
     * Whether a record with org FK columns is covered by any effective grant.
     *
     * @param  array{badanusaha: array<int>, divisi: array<int>, region: array<int>, cluster: array<int>}  $grants
     */
    public static function coversRecord(array $grants, ?int $badanUsahaId, ?int $divisiId, ?int $regionId, ?int $clusterId): bool
    {
        if (self::isEmpty($grants)) {
            return false;
        }

        if ($clusterId !== null && in_array($clusterId, $grants['cluster'], true)) {
            return true;
        }

        if ($regionId !== null && in_array($regionId, $grants['region'], true)) {
            return true;
        }

        if ($divisiId !== null && in_array($divisiId, $grants['divisi'], true)) {
            return true;
        }

        if ($badanUsahaId !== null && in_array($badanUsahaId, $grants['badanusaha'], true)) {
            return true;
        }

        return false;
    }

    /**
     * Expand full grants downward so overlap checks can use concrete IDs
     * (e.g. full divisi grant → all regions/clusters under it).
     *
     * @param  array{badanusaha: array<int>, divisi: array<int>, region: array<int>, cluster: array<int>}  $grants
     * @return array{badanusaha: array<int>, divisi: array<int>, region: array<int>, cluster: array<int>}
     */
    public static function expandAccessibleIds(array $grants): array
    {
        $badanUsahaIds = $grants['badanusaha'];
        $divisiIds = $grants['divisi'];
        $regionIds = $grants['region'];
        $clusterIds = $grants['cluster'];

        if ($badanUsahaIds !== []) {
            $divisiIds = array_values(array_unique(array_merge(
                $divisiIds,
                Division::query()
                    ->whereIn('badanusaha_id', $badanUsahaIds)
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->all(),
            )));
        }

        if ($divisiIds !== [] || $badanUsahaIds !== []) {
            $regionQuery = Region::query();

            $regionQuery->where(function (Builder $query) use ($divisiIds, $badanUsahaIds): void {
                if ($divisiIds !== []) {
                    $query->whereIn('divisi_id', $divisiIds);
                }

                if ($badanUsahaIds !== []) {
                    if ($divisiIds !== []) {
                        $query->orWhereIn('badanusaha_id', $badanUsahaIds);
                    } else {
                        $query->whereIn('badanusaha_id', $badanUsahaIds);
                    }
                }
            });

            $regionIds = array_values(array_unique(array_merge(
                $regionIds,
                $regionQuery->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            )));
        }

        if ($regionIds !== [] || $divisiIds !== [] || $badanUsahaIds !== []) {
            $clusterQuery = Cluster::query();

            $clusterQuery->where(function (Builder $query) use ($regionIds, $divisiIds, $badanUsahaIds): void {
                $applied = false;

                if ($regionIds !== []) {
                    $query->whereIn('region_id', $regionIds);
                    $applied = true;
                }

                if ($divisiIds !== []) {
                    if ($applied) {
                        $query->orWhereIn('divisi_id', $divisiIds);
                    } else {
                        $query->whereIn('divisi_id', $divisiIds);
                        $applied = true;
                    }
                }

                if ($badanUsahaIds !== []) {
                    if ($applied) {
                        $query->orWhereIn('badanusaha_id', $badanUsahaIds);
                    } else {
                        $query->whereIn('badanusaha_id', $badanUsahaIds);
                    }
                }
            });

            $clusterIds = array_values(array_unique(array_merge(
                $clusterIds,
                $clusterQuery->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            )));
        }

        return [
            'badanusaha' => $badanUsahaIds,
            'divisi' => $divisiIds,
            'region' => $regionIds,
            'cluster' => $clusterIds,
        ];
    }

    /**
     * Hierarchy listing: node is visible if covered by a full coarser grant
     * or it is itself (or an ancestor of) a finer assignment.
     *
     * @param  array{badanusaha: array<int>, divisi: array<int>, region: array<int>, cluster: array<int>}  $grants
     * @param  array{badanusaha?: array<int>, divisi?: array<int>, region?: array<int>, cluster?: array<int>}  $raw
     */
    public static function applyHierarchyVisibility(
        Builder $query,
        array $grants,
        array $raw,
        string $entity,
    ): Builder {
        if (self::isEmpty($grants) && self::rawIsEmpty($raw)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $scope) use ($grants, $raw, $entity): void {
            match ($entity) {
                'badanusaha' => self::applyBadanUsahaHierarchy($scope, $grants, $raw),
                'divisi' => self::applyDivisiHierarchy($scope, $grants, $raw),
                'region' => self::applyRegionHierarchy($scope, $grants, $raw),
                'cluster' => self::applyClusterHierarchy($scope, $grants, $raw),
                default => $scope->whereRaw('1 = 0'),
            };
        });
    }

    /**
     * @param  array{badanusaha: array<int>, divisi: array<int>, region: array<int>, cluster: array<int>}  $grants
     * @param  array{badanusaha?: array<int>, divisi?: array<int>, region?: array<int>, cluster?: array<int>}  $raw
     */
    protected static function applyBadanUsahaHierarchy(Builder $scope, array $grants, array $raw): void
    {
        $ids = array_values(array_unique(array_merge(
            $grants['badanusaha'],
            $raw['badanusaha'] ?? [],
            self::divisionBadanUsahaIds($raw['divisi'] ?? []),
            self::regionAncestors($raw['region'] ?? [])['badanusahas'],
            self::clusterAncestors($raw['cluster'] ?? [])['badanusahas'],
        )));

        if ($ids === []) {
            $scope->whereRaw('1 = 0');

            return;
        }

        $scope->whereIn('badan_usahas.id', $ids);
    }

    /**
     * @param  array{badanusaha: array<int>, divisi: array<int>, region: array<int>, cluster: array<int>}  $grants
     * @param  array{badanusaha?: array<int>, divisi?: array<int>, region?: array<int>, cluster?: array<int>}  $raw
     */
    protected static function applyDivisiHierarchy(Builder $scope, array $grants, array $raw): void
    {
        $applied = false;

        if ($grants['badanusaha'] !== []) {
            $scope->whereIn('divisions.badanusaha_id', $grants['badanusaha']);
            $applied = true;
        }

        $divisiIds = array_values(array_unique(array_merge(
            $grants['divisi'],
            $raw['divisi'] ?? [],
            self::regionAncestors($raw['region'] ?? [])['divisis'],
            self::clusterAncestors($raw['cluster'] ?? [])['divisis'],
        )));

        if ($divisiIds !== []) {
            if ($applied) {
                $scope->orWhereIn('divisions.id', $divisiIds);
            } else {
                $scope->whereIn('divisions.id', $divisiIds);
                $applied = true;
            }
        }

        if (! $applied) {
            $scope->whereRaw('1 = 0');
        }
    }

    /**
     * @param  array{badanusaha: array<int>, divisi: array<int>, region: array<int>, cluster: array<int>}  $grants
     * @param  array{badanusaha?: array<int>, divisi?: array<int>, region?: array<int>, cluster?: array<int>}  $raw
     */
    protected static function applyRegionHierarchy(Builder $scope, array $grants, array $raw): void
    {
        $applied = false;

        if ($grants['badanusaha'] !== []) {
            $scope->whereIn('regions.badanusaha_id', $grants['badanusaha']);
            $applied = true;
        }

        if ($grants['divisi'] !== []) {
            if ($applied) {
                $scope->orWhereIn('regions.divisi_id', $grants['divisi']);
            } else {
                $scope->whereIn('regions.divisi_id', $grants['divisi']);
                $applied = true;
            }
        }

        $regionIds = array_values(array_unique(array_merge(
            $grants['region'],
            $raw['region'] ?? [],
            self::clusterAncestors($raw['cluster'] ?? [])['regions'],
        )));

        if ($regionIds !== []) {
            if ($applied) {
                $scope->orWhereIn('regions.id', $regionIds);
            } else {
                $scope->whereIn('regions.id', $regionIds);
                $applied = true;
            }
        }

        if (! $applied) {
            $scope->whereRaw('1 = 0');
        }
    }

    /**
     * @param  array{badanusaha: array<int>, divisi: array<int>, region: array<int>, cluster: array<int>}  $grants
     * @param  array{badanusaha?: array<int>, divisi?: array<int>, region?: array<int>, cluster?: array<int>}  $raw
     */
    protected static function applyClusterHierarchy(Builder $scope, array $grants, array $raw): void
    {
        $applied = false;

        if ($grants['badanusaha'] !== []) {
            $scope->whereIn('clusters.badanusaha_id', $grants['badanusaha']);
            $applied = true;
        }

        if ($grants['divisi'] !== []) {
            if ($applied) {
                $scope->orWhereIn('clusters.divisi_id', $grants['divisi']);
            } else {
                $scope->whereIn('clusters.divisi_id', $grants['divisi']);
                $applied = true;
            }
        }

        if ($grants['region'] !== []) {
            if ($applied) {
                $scope->orWhereIn('clusters.region_id', $grants['region']);
            } else {
                $scope->whereIn('clusters.region_id', $grants['region']);
                $applied = true;
            }
        }

        $clusterIds = array_values(array_unique(array_merge(
            $grants['cluster'],
            $raw['cluster'] ?? [],
        )));

        if ($clusterIds !== []) {
            if ($applied) {
                $scope->orWhereIn('clusters.id', $clusterIds);
            } else {
                $scope->whereIn('clusters.id', $clusterIds);
                $applied = true;
            }
        }

        if (! $applied) {
            $scope->whereRaw('1 = 0');
        }
    }

    /**
     * @param  array{badanusaha?: array<int>, divisi?: array<int>, region?: array<int>, cluster?: array<int>}  $raw
     */
    protected static function rawIsEmpty(array $raw): bool
    {
        return ($raw['badanusaha'] ?? []) === []
            && ($raw['divisi'] ?? []) === []
            && ($raw['region'] ?? []) === []
            && ($raw['cluster'] ?? []) === [];
    }

    /**
     * @param  array<int|string>  $ids
     * @return array<int>
     */
    protected static function normalizeIds(array $ids): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn ($id): int => (int) $id, $ids),
            static fn (int $id): bool => $id > 0,
        )));
    }

    /**
     * @param  array<int>  $clusterIds
     * @return array{clusters: array<int>, regions: array<int>, divisis: array<int>, badanusahas: array<int>}
     */
    protected static function clusterAncestors(array $clusterIds): array
    {
        if ($clusterIds === []) {
            return [
                'clusters' => [],
                'regions' => [],
                'divisis' => [],
                'badanusahas' => [],
            ];
        }

        $clusters = Cluster::query()
            ->whereKey($clusterIds)
            ->get(['id', 'region_id', 'divisi_id', 'badanusaha_id']);

        return [
            'clusters' => $clusters->pluck('id')->map(fn ($id): int => (int) $id)->unique()->values()->all(),
            'regions' => $clusters->pluck('region_id')->filter()->map(fn ($id): int => (int) $id)->unique()->values()->all(),
            'divisis' => $clusters->pluck('divisi_id')->filter()->map(fn ($id): int => (int) $id)->unique()->values()->all(),
            'badanusahas' => $clusters->pluck('badanusaha_id')->filter()->map(fn ($id): int => (int) $id)->unique()->values()->all(),
        ];
    }

    /**
     * @param  array<int>  $regionIds
     * @return array{regions: array<int>, divisis: array<int>, badanusahas: array<int>}
     */
    protected static function regionAncestors(array $regionIds): array
    {
        if ($regionIds === []) {
            return [
                'regions' => [],
                'divisis' => [],
                'badanusahas' => [],
            ];
        }

        $regions = Region::query()
            ->whereKey($regionIds)
            ->get(['id', 'divisi_id', 'badanusaha_id']);

        return [
            'regions' => $regions->pluck('id')->map(fn ($id): int => (int) $id)->unique()->values()->all(),
            'divisis' => $regions->pluck('divisi_id')->filter()->map(fn ($id): int => (int) $id)->unique()->values()->all(),
            'badanusahas' => $regions->pluck('badanusaha_id')->filter()->map(fn ($id): int => (int) $id)->unique()->values()->all(),
        ];
    }

    /**
     * @param  array<int>  $divisionIds
     * @return array<int>
     */
    protected static function divisionBadanUsahaIds(array $divisionIds): array
    {
        if ($divisionIds === []) {
            return [];
        }

        return Division::query()
            ->whereKey($divisionIds)
            ->pluck('badanusaha_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
