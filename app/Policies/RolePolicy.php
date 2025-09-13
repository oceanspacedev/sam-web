<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return Gate::allows('view_any_role');
    }

    public function view(User $user, Role $role): bool
    {
        return Gate::allows('view_role');
    }

    public function create(User $user): bool
    {
        return Gate::allows('create_role');
    }

    public function update(User $user, Role $role): bool
    {
        return Gate::allows('update_role');
    }

    public function deleteAny(User $user): bool
    {
        return Gate::allows('delete_any_role');
    }

    public function delete(User $user, Role $role): bool
    {
        return Gate::allows('delete_role');
    }
}
