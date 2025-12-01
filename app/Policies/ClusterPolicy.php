<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Cluster;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ClusterPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Cluster');
    }

    public function view(AuthUser $authUser, Cluster $cluster): bool
    {
        return $authUser->can('View:Cluster');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Cluster');
    }

    public function update(AuthUser $authUser, Cluster $cluster): bool
    {
        return $authUser->can('Update:Cluster');
    }

    public function delete(AuthUser $authUser, Cluster $cluster): bool
    {
        return $authUser->can('Delete:Cluster');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Cluster');
    }

    public function restore(AuthUser $authUser, Cluster $cluster): bool
    {
        return $authUser->can('Restore:Cluster');
    }

    public function forceDelete(AuthUser $authUser, Cluster $cluster): bool
    {
        return $authUser->can('ForceDelete:Cluster');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Cluster');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Cluster');
    }
}
