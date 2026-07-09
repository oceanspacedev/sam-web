<?php

namespace App\Traits;

use App\Models\User;
use App\Support\OrganizationalEffectiveGrants;
use Illuminate\Database\Eloquent\Builder;

trait HasOrganizationalScope
{
    /**
     * Scope query berdasarkan organizational hierarchy user.
     * Uses many-to-many pivot tables as source of truth.
     * Visibility is the OR of effective full grants per level.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->role) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->role->hasFullAccess()) {
            return $query;
        }

        $grants = $user->getEffectiveOrganizationalGrants();

        if (OrganizationalEffectiveGrants::isEmpty($grants)) {
            return $query->whereRaw('1 = 0');
        }

        $table = $this->getTable();

        return OrganizationalEffectiveGrants::applyOrColumns($query, $grants, $table, [
            'badanusaha' => 'badanusaha_id',
            'divisi' => 'divisi_id',
            'region' => 'region_id',
            'cluster' => 'cluster_id',
        ]);
    }

    /**
     * Check if user dapat melihat record ini.
     * Uses many-to-many pivot tables as source of truth.
     */
    public function isVisibleTo(User $user): bool
    {
        if (! $user->role) {
            return false;
        }

        if ($user->role->hasFullAccess()) {
            return true;
        }

        $grants = $user->getEffectiveOrganizationalGrants();

        return OrganizationalEffectiveGrants::coversRecord(
            $grants,
            isset($this->badanusaha_id) ? (int) $this->badanusaha_id : null,
            isset($this->divisi_id) ? (int) $this->divisi_id : null,
            isset($this->region_id) ? (int) $this->region_id : null,
            isset($this->cluster_id) ? (int) $this->cluster_id : null,
        );
    }
}
