<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Outlet;
use Illuminate\Auth\Access\HandlesAuthorization;

class OutletPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser, Outlet $outlet): bool
    {
        return $authUser->can('ViewAny:Outlet');
    }

    public function view(AuthUser $authUser, Outlet $outlet): bool
    {
        return $authUser->can('View:Outlet');
    }

    public function create(AuthUser $authUser, Outlet $outlet): bool
    {
        return $authUser->can('Create:Outlet');
    }

    public function update(AuthUser $authUser, Outlet $outlet): bool
    {
        return $authUser->can('Update:Outlet');
    }

    public function delete(AuthUser $authUser, Outlet $outlet): bool
    {
        return $authUser->can('Delete:Outlet');
    }

    public function restore(AuthUser $authUser, Outlet $outlet): bool
    {
        return $authUser->can('Restore:Outlet');
    }

    public function forceDelete(AuthUser $authUser, Outlet $outlet): bool
    {
        return $authUser->can('ForceDelete:Outlet');
    }

    public function forceDeleteAny(AuthUser $authUser, Outlet $outlet): bool
    {
        return $authUser->can('ForceDeleteAny:Outlet');
    }

    public function restoreAny(AuthUser $authUser, Outlet $outlet): bool
    {
        return $authUser->can('RestoreAny:Outlet');
    }

    public function export(AuthUser $authUser, Outlet $outlet): bool
    {
        return $authUser->can('Export:Outlet');
    }

    public function reset(AuthUser $authUser, Outlet $outlet): bool
    {
        return $authUser->can('Reset:Outlet');
    }

}