<?php

namespace App\Policies;

use App\Models\BadanUsaha;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class BadanUsahaPolicy
{
    public function viewAny(User $user): bool
    {
        return Gate::allows('view_any_badan::usaha');
    }

    public function view(User $user, BadanUsaha $badanUsaha): bool
    {
        return Gate::allows('view_badan::usaha');
    }

    public function create(User $user): bool
    {
        return Gate::allows('create_badan::usaha');
    }

    public function update(User $user, BadanUsaha $badanUsaha): bool
    {
        return Gate::allows('update_badan::usaha');
    }

    public function deleteAny(User $user): bool
    {
        return Gate::allows('delete_any_badan::usaha');
    }

    public function delete(User $user, BadanUsaha $badanUsaha): bool
    {
        return Gate::allows('delete_badan::usaha');
    }
}
