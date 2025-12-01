<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Register;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class RegisterPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Register');
    }

    public function view(AuthUser $authUser, Register $register): bool
    {
        return $authUser->can('View:Register');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Register');
    }

    public function update(AuthUser $authUser, Register $register): bool
    {
        return $authUser->can('Update:Register');
    }

    public function delete(AuthUser $authUser, Register $register): bool
    {
        return $authUser->can('Delete:Register');
    }

    public function restore(AuthUser $authUser, Register $register): bool
    {
        return $authUser->can('Restore:Register');
    }

    public function forceDelete(AuthUser $authUser, Register $register): bool
    {
        return $authUser->can('ForceDelete:Register');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Register');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Register');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Register');
    }

    public function approve(AuthUser $authUser, Register $register): bool
    {
        return $authUser->can('Approve:Register');
    }

    public function confirm(AuthUser $authUser, Register $register): bool
    {
        return $authUser->can('Confirm:Register');
    }

    public function reject(AuthUser $authUser, Register $register): bool
    {
        return $authUser->can('Reject:Register');
    }

    public function export(AuthUser $authUser): bool
    {
        return $authUser->can('Export:Register');
    }

    public function upgrade(AuthUser $authUser, Register $register): bool
    {
        return $authUser->can('Upgrade:Register');
    }
}
