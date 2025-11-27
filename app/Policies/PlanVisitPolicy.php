<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PlanVisit;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PlanVisitPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PlanVisit');
    }

    public function view(AuthUser $authUser, PlanVisit $planVisit): bool
    {
        return $authUser->can('View:PlanVisit');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PlanVisit');
    }

    public function update(AuthUser $authUser, PlanVisit $planVisit): bool
    {
        return $authUser->can('Update:PlanVisit');
    }

    public function delete(AuthUser $authUser, PlanVisit $planVisit): bool
    {
        return $authUser->can('Delete:PlanVisit');
    }

    public function restore(AuthUser $authUser, PlanVisit $planVisit): bool
    {
        return $authUser->can('Restore:PlanVisit');
    }

    public function forceDelete(AuthUser $authUser, PlanVisit $planVisit): bool
    {
        return $authUser->can('ForceDelete:PlanVisit');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PlanVisit');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PlanVisit');
    }

    public function replicate(AuthUser $authUser, PlanVisit $planVisit): bool
    {
        return $authUser->can('Replicate:PlanVisit');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PlanVisit');
    }
}
