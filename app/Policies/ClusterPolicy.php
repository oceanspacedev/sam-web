<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Cluster;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClusterPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser, Cluster $cluster): bool
    {
        return $authUser->can('ViewAny:Cluster');
    }

    public function view(AuthUser $authUser, Cluster $cluster): bool
    {
        return $authUser->can('View:Cluster');
    }

    public function create(AuthUser $authUser, Cluster $cluster): bool
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

    public function restore(AuthUser $authUser, Cluster $cluster): bool
    {
        return $authUser->can('Restore:Cluster');
    }

    public function forceDelete(AuthUser $authUser, Cluster $cluster): bool
    {
        return $authUser->can('ForceDelete:Cluster');
    }

    public function forceDeleteAny(AuthUser $authUser, Cluster $cluster): bool
    {
        return $authUser->can('ForceDeleteAny:Cluster');
    }

    public function restoreAny(AuthUser $authUser, Cluster $cluster): bool
    {
        return $authUser->can('RestoreAny:Cluster');
    }

}