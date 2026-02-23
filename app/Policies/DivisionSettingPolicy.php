<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DivisionSetting;
use Illuminate\Auth\Access\HandlesAuthorization;

class DivisionSettingPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DivisionSetting');
    }

    public function view(AuthUser $authUser, DivisionSetting $divisionSetting): bool
    {
        return $authUser->can('View:DivisionSetting');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DivisionSetting');
    }

    public function update(AuthUser $authUser, DivisionSetting $divisionSetting): bool
    {
        return $authUser->can('Update:DivisionSetting');
    }

    public function delete(AuthUser $authUser, DivisionSetting $divisionSetting): bool
    {
        return $authUser->can('Delete:DivisionSetting');
    }

    public function restore(AuthUser $authUser, DivisionSetting $divisionSetting): bool
    {
        return $authUser->can('Restore:DivisionSetting');
    }

    public function forceDelete(AuthUser $authUser, DivisionSetting $divisionSetting): bool
    {
        return $authUser->can('ForceDelete:DivisionSetting');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DivisionSetting');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DivisionSetting');
    }

}