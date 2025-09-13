<?php

namespace App\Policies;

namespace App\Policies;

use App\Models\Cluster;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class ClusterPolicy
{
    public function viewAny(User $user): bool
    {
        return Gate::allows('view_any_cluster');
    }

    public function view(User $user, Cluster $cluster): bool
    {
        return Gate::allows('view_cluster');
    }

    public function create(User $user): bool
    {
        return Gate::allows('create_cluster');
    }

    public function update(User $user, Cluster $cluster): bool
    {
        return Gate::allows('update_cluster');
    }

    public function deleteAny(User $user): bool
    {
        return Gate::allows('delete_any_cluster');
    }

    public function delete(User $user, Cluster $cluster): bool
    {
        return Gate::allows('delete_cluster');
    }
}
