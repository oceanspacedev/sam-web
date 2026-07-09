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

        $grants = $user->getEffectiveOrganizationalGrants();

        if (OrganizationalEffectiveGrants::isEmpty($grants)) {
            return self::block($query);
        }

        return OrganizationalEffectiveGrants::applyOrColumns($query, $grants, $table, $columns);
    }

    protected static function block(Builder $query): Builder
    {
        return $query->whereRaw('1 = 0');
    }
}
