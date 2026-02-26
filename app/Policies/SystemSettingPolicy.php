<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SystemSetting;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class SystemSettingPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SystemSetting');
    }

    public function view(AuthUser $authUser, SystemSetting $systemSetting): bool
    {
        return $authUser->can('View:SystemSetting');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SystemSetting');
    }

    public function update(AuthUser $authUser, SystemSetting $systemSetting): bool
    {
        return $authUser->can('Update:SystemSetting');
    }

    public function delete(AuthUser $authUser, SystemSetting $systemSetting): bool
    {
        return $authUser->can('Delete:SystemSetting');
    }

    public function restore(AuthUser $authUser, SystemSetting $systemSetting): bool
    {
        return $authUser->can('Restore:SystemSetting');
    }

    public function forceDelete(AuthUser $authUser, SystemSetting $systemSetting): bool
    {
        return $authUser->can('ForceDelete:SystemSetting');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:SystemSetting');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:SystemSetting');
    }
}
