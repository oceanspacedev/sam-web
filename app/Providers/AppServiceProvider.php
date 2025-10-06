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
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Pulse\Facades\Pulse;

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
        $environment = (string) config('app.env');

        $shouldForceHttps = in_array($environment, ['production', 'staging'], true)
            || str_starts_with((string) config('app.url'), 'https://')
            || (! $this->app->runningInConsole() && request()->isSecure());

        if ($shouldForceHttps) {
            URL::forceScheme('https');
        }

        // Register observers untuk auto-clear cache organizational data
        BadanUsaha::observe(OrganizationalObserver::class);
        Division::observe(OrganizationalObserver::class);
        Region::observe(OrganizationalObserver::class);
        Cluster::observe(OrganizationalObserver::class);
        Role::observe(OrganizationalObserver::class);

        Pulse::user(fn ($user) => [
            'name' => $user->nama_lengkap,
            'extra' => $user->username,
            'avatar' => $user->profile_photo_path,
        ]);

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
