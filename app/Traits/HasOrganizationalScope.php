<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait HasOrganizationalScope
{
    /**
     * Scope query berdasarkan organizational hierarchy user.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        // Filter berdasarkan badan usaha (semua role)
        $query->where($this->getTable().'.badanusaha_id', $user->badanusaha_id);

        $roleName = $user->role?->name;
        $table = $this->getTable();
        $clusterIds = array_values(array_filter([$user->cluster_id, $user->cluster_id2]));

        switch ($roleName) {
            case 'SUPER ADMIN':
                // No additional filtering - can see all
                break;

            case 'ASM':
                // Area Sales Manager: filter by division only
                $query->where($table.'.divisi_id', $user->divisi_id);
                break;

            case 'ASC':
            case 'DSF/DM':
                // Area Sales Coordinator / District Sales Field Manager: filter by division + region + optional clusters
                $query->where($table.'.divisi_id', $user->divisi_id)
                    ->where($table.'.region_id', $user->region_id);

                if ($clusterIds !== []) {
                    $query->whereIn($table.'.cluster_id', $clusterIds);
                }
                break;

            default:
                // Default: strict filtering (division + region + cluster)
                $query->where($table.'.divisi_id', $user->divisi_id)
                    ->where($table.'.region_id', $user->region_id)
                    ->where($table.'.cluster_id', $user->cluster_id);
        }

        return $query;
    }

    /**
     * Check if user dapat melihat record ini.
     */
    public function isVisibleTo(User $user): bool
    {
        // Super admin can see everything
        if ($user->role?->name === 'SUPER ADMIN') {
            return true;
        }

        // Must match badan usaha
        if ($this->badanusaha_id !== $user->badanusaha_id) {
            return false;
        }

        $roleName = $user->role?->name;
        $clusterIds = array_values(array_filter([$user->cluster_id, $user->cluster_id2]));

        return match ($roleName) {
            'ASM' => $this->divisi_id === $user->divisi_id,
            'ASC', 'DSF/DM' => $this->divisi_id === $user->divisi_id
                && $this->region_id === $user->region_id
                && ($clusterIds === [] || in_array($this->cluster_id, $clusterIds, true)),
            default => $this->divisi_id === $user->divisi_id
                && $this->region_id === $user->region_id
                && $this->cluster_id === $user->cluster_id,
        };
    }
}
