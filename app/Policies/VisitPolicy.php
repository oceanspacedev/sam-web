<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\Gate;

class VisitPolicy
{
    public function restoreAny(User $user): bool
    {
        return Gate::allows('restore_any_visit');
    }

    public function deleteAny(User $user): bool
    {
        return Gate::allows('delete_any_visit');
    }

    public function forceDeleteAny(User $user): bool
    {
        return Gate::allows('force_delete_any_visit');
    }

    public function viewAny(User $user): bool
    {
        return Gate::allows('view_any_visit');
    }

    public function view(User $user, Visit $visit): bool
    {
        return Gate::allows('view_visit');
    }

    public function create(User $user): bool
    {
        return Gate::allows('create_visit');
    }

    public function update(User $user, Visit $visit): bool
    {
        return Gate::allows('update_visit');
    }

    public function delete(User $user, Visit $visit): bool
    {
        return Gate::allows('delete_visit');
    }

    public function restore(User $user, Visit $visit): bool
    {
        return Gate::allows('view_any_visit');
    }

    public function forceDelete(User $user, Visit $visit): bool
    {
        return Gate::allows('force_delete_visit');
    }

    public function export(User $user): bool
    {
        return Gate::allows('export_visit');
    }
}
