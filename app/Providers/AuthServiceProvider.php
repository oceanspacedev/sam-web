<?php

namespace App\Providers;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use PDOException;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        if ($this->shouldSkipDynamicPermissionRegistration()) {
            return;
        }

        Permission::query()->each(function (Permission $permission): void {
            Gate::define($permission->name, function (User $user) use ($permission): bool {
                return $user->permissions->contains('id', $permission->id);
            });
        });
    }

    /**
     * Determine if the dynamic permission gates should be skipped.
     */
    protected function shouldSkipDynamicPermissionRegistration(): bool
    {
        try {
            return ! Schema::hasTable('permissions') || ! Schema::hasColumn('permissions', 'deleted_at');
        } catch (PDOException|QueryException $exception) {
            return true;
        }
    }
}
