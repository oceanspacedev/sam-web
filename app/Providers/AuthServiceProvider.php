<?php

namespace App\Providers;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

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
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        // Hindari error pada environment testing ketika tabel belum dimigrasi
        if (Schema::hasTable('permissions') && Schema::hasColumn('permissions', 'deleted_at')) {
            $permissions = Permission::all();
            foreach ($permissions as $permission) {
                Gate::define($permission->name, function ($user) use ($permission) {
                    return $user->permissions->contains('id', $permission->id);
                });
            }
        }

        Gate::define('viewPulse', function (User $user) {
            return $user->role->name === 'SUPER ADMIN';
        });

    }
}
