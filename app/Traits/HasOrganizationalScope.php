<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait HasOrganizationalScope
{
    /**
     * Scope query berdasarkan organizational hierarchy user.
     * Uses many-to-many pivot tables as source of truth.
     * Empty pivot tables = user has 'all' scope (no filtering).
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        // Check if user has a role
        if (! $user->role) {
            return $query->whereRaw('1 = 0'); // Return empty result
        }

        // If role has full access, no filtering needed
        if ($user->role->hasFullAccess()) {
            return $query;
        }

        $table = $this->getTable();
        $scopeLevel = $user->role->getOrganizationalScopeLevel();

        // Get user's organizational assignments from pivot tables (source of truth)
        $badanUsahaIds = $user->badanUsahas()->pluck('badan_usahas.id')->toArray();
        $divisiIds = $user->divisis()->pluck('divisions.id')->toArray();
        $regionIds = $user->regions()->pluck('regions.id')->toArray();
        $clusterIds = $user->clusters()->pluck('clusters.id')->toArray();

        // Apply hierarchical filtering based on scope level
        // Empty arrays mean no filtering (user has 'all' access for that level)
        switch ($scopeLevel) {
            case 'badanusaha':
                if (! empty($badanUsahaIds)) {
                    $query->whereIn($table.'.badanusaha_id', $badanUsahaIds);
                }
                break;

            case 'divisi':
                if (! empty($badanUsahaIds)) {
                    $query->whereIn($table.'.badanusaha_id', $badanUsahaIds);
                }
                if (! empty($divisiIds)) {
                    $query->whereIn($table.'.divisi_id', $divisiIds);
                }
                break;

            case 'cluster':
                if (! empty($badanUsahaIds)) {
                    $query->whereIn($table.'.badanusaha_id', $badanUsahaIds);
                }
                if (! empty($divisiIds)) {
                    $query->whereIn($table.'.divisi_id', $divisiIds);
                }
                if (! empty($regionIds)) {
                    $query->whereIn($table.'.region_id', $regionIds);
                }
                if (! empty($clusterIds)) {
                    $query->whereIn($table.'.cluster_id', $clusterIds);
                }
                break;
        }

        return $query;
    }

    /**
     * Check if user dapat melihat record ini.
     * Uses many-to-many pivot tables as source of truth.
     */
    public function isVisibleTo(User $user): bool
    {
        // Check if user has a role
        if (! $user->role) {
            return false;
        }

        // Full access roles can see everything
        if ($user->role->hasFullAccess()) {
            return true;
        }

        // Get user's organizational assignments from pivot tables
        $badanUsahaIds = $user->badanUsahas()->pluck('badan_usahas.id')->toArray();
        $divisiIds = $user->divisis()->pluck('divisions.id')->toArray();
        $regionIds = $user->regions()->pluck('regions.id')->toArray();
        $clusterIds = $user->clusters()->pluck('clusters.id')->toArray();

        $scopeLevel = $user->role->getOrganizationalScopeLevel();

        // Empty pivot = 'all' access (return true)
        return match ($scopeLevel) {
            'badanusaha' => empty($badanUsahaIds) || in_array($this->badanusaha_id, $badanUsahaIds, true),
            'divisi' => (empty($badanUsahaIds) || in_array($this->badanusaha_id, $badanUsahaIds, true))
            && (empty($divisiIds) || in_array($this->divisi_id, $divisiIds, true)),
            'cluster' => (empty($badanUsahaIds) || in_array($this->badanusaha_id, $badanUsahaIds, true))
            && (empty($divisiIds) || in_array($this->divisi_id, $divisiIds, true))
            && (empty($regionIds) || in_array($this->region_id, $regionIds, true))
            && (empty($clusterIds) || in_array($this->cluster_id, $clusterIds, true)),
            default => false,
        };
    }
}
