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

        switch ($roleName) {
            case 'SUPER ADMIN':
                // No additional filtering - can see all
                break;

            case 'ASM':
                // Area Sales Manager: filter by division only
                $query->where($this->getTable().'.divisi_id', $user->divisi_id);
                break;

            case 'ASC':
                // Area Sales Coordinator: filter by division + region
                $query->where($this->getTable().'.divisi_id', $user->divisi_id)
                    ->where($this->getTable().'.region_id', $user->region_id);
                break;

            case 'DSF/DM':
                // District Sales Field/Manager: filter by division + region + cluster(s)
                $clusterIds = array_values(array_filter([$user->cluster_id, $user->cluster_id2]));

                $query->where($this->getTable().'.divisi_id', $user->divisi_id)
                    ->where($this->getTable().'.region_id', $user->region_id)
                    ->when(! empty($clusterIds), function ($q) use ($clusterIds) {
                        $q->whereIn($this->getTable().'.cluster_id', $clusterIds);
                    });
                break;

            default:
                // Default: strict filtering (division + region + cluster)
                $query->where($this->getTable().'.divisi_id', $user->divisi_id)
                    ->where($this->getTable().'.region_id', $user->region_id)
                    ->where($this->getTable().'.cluster_id', $user->cluster_id);
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

        return match ($roleName) {
            'ASM' => $this->divisi_id === $user->divisi_id,
            'ASC' => $this->divisi_id === $user->divisi_id && $this->region_id === $user->region_id,
            'DSF/DM' => $this->divisi_id === $user->divisi_id
                && $this->region_id === $user->region_id
                && in_array($this->cluster_id, [$user->cluster_id, $user->cluster_id2]),
            default => $this->divisi_id === $user->divisi_id
                && $this->region_id === $user->region_id
                && $this->cluster_id === $user->cluster_id,
        };
    }
}
