<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Outlet;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class OutletPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Outlet');
    }

    public function view(AuthUser $authUser, Outlet $outlet): bool
    {
        return $authUser->can('View:Outlet');
    }

    public function create(AuthUser $authUser): bool
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

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Outlet');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Outlet');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Outlet');
    }

    public function export(AuthUser $authUser): bool
    {
        return $authUser->can('Export:Outlet');
    }

    public function reset(AuthUser $authUser): bool
    {
        return $authUser->can('Reset:Outlet');
    }

    public function resetLocation(AuthUser $authUser): bool
    {
        return $authUser->can('ResetLocation:Outlet');
    }
}
