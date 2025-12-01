<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BadanUsaha;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class BadanUsahaPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:BadanUsaha');
    }

    public function view(AuthUser $authUser, BadanUsaha $badanUsaha): bool
    {
        return $authUser->can('View:BadanUsaha');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:BadanUsaha');
    }

    public function update(AuthUser $authUser, BadanUsaha $badanUsaha): bool
    {
        return $authUser->can('Update:BadanUsaha');
    }

    public function delete(AuthUser $authUser, BadanUsaha $badanUsaha): bool
    {
        return $authUser->can('Delete:BadanUsaha');
    }

    public function restore(AuthUser $authUser, BadanUsaha $badanUsaha): bool
    {
        return $authUser->can('Restore:BadanUsaha');
    }

    public function forceDelete(AuthUser $authUser, BadanUsaha $badanUsaha): bool
    {
        return $authUser->can('ForceDelete:BadanUsaha');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:BadanUsaha');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:BadanUsaha');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:BadanUsaha');
    }
}
