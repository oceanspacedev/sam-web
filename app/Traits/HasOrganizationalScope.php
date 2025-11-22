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

        // Use cached organizational IDs from user to avoid N+1 queries
        $ids = $user->getOrganizationalIds();
        $scopeLevel = $ids['scope_level'];

        // Apply hierarchical filtering based on scope level
        // Empty arrays mean no filtering (user has 'all' access for that level)
        switch ($scopeLevel) {
            case 'badanusaha':
                if (! empty($ids['badanusaha'])) {
                    $query->whereIn($table.'.badanusaha_id', $ids['badanusaha']);
                }
                break;

            case 'divisi':
                if (! empty($ids['badanusaha'])) {
                    $query->whereIn($table.'.badanusaha_id', $ids['badanusaha']);
                }
                if (! empty($ids['divisi'])) {
                    $query->whereIn($table.'.divisi_id', $ids['divisi']);
                }
                break;

            case 'cluster':
                if (! empty($ids['badanusaha'])) {
                    $query->whereIn($table.'.badanusaha_id', $ids['badanusaha']);
                }
                if (! empty($ids['divisi'])) {
                    $query->whereIn($table.'.divisi_id', $ids['divisi']);
                }
                if (! empty($ids['region'])) {
                    $query->whereIn($table.'.region_id', $ids['region']);
                }
                if (! empty($ids['cluster'])) {
                    $query->whereIn($table.'.cluster_id', $ids['cluster']);
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

        // Use cached organizational IDs from user
        $ids = $user->getOrganizationalIds();
        $scopeLevel = $ids['scope_level'];

        // Empty pivot = 'all' access (return true)
        return match ($scopeLevel) {
            'badanusaha' => empty($ids['badanusaha']) || in_array($this->badanusaha_id, $ids['badanusaha'], true),
            'divisi' => (empty($ids['badanusaha']) || in_array($this->badanusaha_id, $ids['badanusaha'], true))
            && (empty($ids['divisi']) || in_array($this->divisi_id, $ids['divisi'], true)),
            'cluster' => (empty($ids['badanusaha']) || in_array($this->badanusaha_id, $ids['badanusaha'], true))
            && (empty($ids['divisi']) || in_array($this->divisi_id, $ids['divisi'], true))
            && (empty($ids['region']) || in_array($this->region_id, $ids['region'], true))
            && (empty($ids['cluster']) || in_array($this->cluster_id, $ids['cluster'], true)),
            default => false,
        };
    }
}
