<?php

namespace App\Policies;

use App\Models\Noo;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class NooPolicy
{
    public function restoreAny(User $user): bool
    {
        return Gate::allows('restore_any_noo');
    }

    public function deleteAny(User $user): bool
    {
        return Gate::allows('delete_any_noo');
    }

    public function forceDeleteAny(User $user): bool
    {
        return Gate::allows('force_delete_any_noo');
    }

    public function viewAny(User $user): bool
    {
        return Gate::allows('view_any_noo');
    }

    public function view(User $user, Noo $noo): bool
    {
        return Gate::allows('view_noo');
    }

    public function create(User $user): bool
    {
        return Gate::allows('create_noo');
    }

    public function update(User $user, Noo $noo): bool
    {
        return Gate::allows('update_noo');
    }

    public function delete(User $user, Noo $noo): bool
    {
        return Gate::allows('delete_noo');
    }

    public function restore(User $user, Noo $noo): bool
    {
        return Gate::allows('restore_noo');
    }

    public function forceDelete(User $user, Noo $noo): bool
    {
        return Gate::allows('force_delete_noo');
    }

    public function export(User $user): bool
    {
        return Gate::allows('export_noo');
    }

    public function confirm(User $user, Noo $noo)
    {
        return Gate::allows('confirm_noo');
    }

    public function approve(User $user, Noo $noo)
    {
        return Gate::allows('approve_noo');
    }

    public function reject(User $user, Noo $noo)
    {
        return Gate::allows('reject_noo');
    }
}
