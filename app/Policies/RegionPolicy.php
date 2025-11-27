<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Region;
use Illuminate\Auth\Access\HandlesAuthorization;

class RegionPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser, Region $region): bool
    {
        return $authUser->can('ViewAny:Region');
    }

    public function view(AuthUser $authUser, Region $region): bool
    {
        return $authUser->can('View:Region');
    }

    public function create(AuthUser $authUser, Region $region): bool
    {
        return $authUser->can('Create:Region');
    }

    public function update(AuthUser $authUser, Region $region): bool
    {
        return $authUser->can('Update:Region');
    }

    public function delete(AuthUser $authUser, Region $region): bool
    {
        return $authUser->can('Delete:Region');
    }

    public function restore(AuthUser $authUser, Region $region): bool
    {
        return $authUser->can('Restore:Region');
    }

    public function forceDelete(AuthUser $authUser, Region $region): bool
    {
        return $authUser->can('ForceDelete:Region');
    }

    public function forceDeleteAny(AuthUser $authUser, Region $region): bool
    {
        return $authUser->can('ForceDeleteAny:Region');
    }

    public function restoreAny(AuthUser $authUser, Region $region): bool
    {
        return $authUser->can('RestoreAny:Region');
    }

}