<?php

namespace App\Policies;

use App\Models\Outlet;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class OutletPolicy
{
    public function restoreAny(User $user): bool
    {
        return Gate::allows('restore_any_outlet');
    }

    public function deleteAny(User $user): bool
    {
        return Gate::allows('delete_any_outlet');
    }

    public function forceDeleteAny(User $user): bool
    {
        return Gate::allows('force_delete_any_outlet');
    }

    public function viewAny(User $user): bool
    {
        return Gate::allows('view_any_outlet');
    }

    public function view(User $user, Outlet $outlet): bool
    {
        return Gate::allows('view_outlet');
    }

    public function create(User $user): bool
    {
        return Gate::allows('create_outlet');
    }

    public function update(User $user, Outlet $outlet): bool
    {
        return Gate::allows('update_outlet');
    }

    public function delete(User $user, Outlet $outlet): bool
    {
        return Gate::allows('delete_outlet');
    }

    public function restore(User $user, Outlet $outlet): bool
    {
        return Gate::allows('restore_outlet');
    }

    public function forceDelete(User $user, Outlet $outlet): bool
    {
        return Gate::allows('force_delete_outlet');
    }

    public function exportAll(User $user): bool
    {
        return Gate::allows('export_outlet');
    }

    public function resetAny(User $user): bool
    {
        return Gate::allows('reset_any_outlet');
    }
}
