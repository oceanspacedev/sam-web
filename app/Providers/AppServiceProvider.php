<?php

namespace App\Providers;

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use App\Observers\OrganizationalObserver;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Register observers untuk auto-clear cache organizational data
        BadanUsaha::observe(OrganizationalObserver::class);
        Division::observe(OrganizationalObserver::class);
        Region::observe(OrganizationalObserver::class);
        Cluster::observe(OrganizationalObserver::class);
        Role::observe(OrganizationalObserver::class);

        Scramble::afterOpenApiGenerated(function (OpenApi $openApi) {
            $openApi->secure(
                SecurityScheme::http('bearer')
            );
        });

        Gate::define('viewApiDocs', function (User $user) {
            return $user->role->name === 'SUPER ADMIN';
        });
    }
}
