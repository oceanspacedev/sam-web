<?php

namespace App\Providers;

use App\Filament\Auth\Pages\PhoneLogin;
use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Outlet;
use App\Models\Permission;
use App\Models\PlanVisit;
use App\Models\Region;
use App\Models\Register;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;
use App\Observers\OrganizationalObserver;
use App\Observers\OutletObserver;
use App\Observers\PlanVisitObserver;
use App\Observers\RegisterObserver;
use App\Observers\UserObserver;
use App\Observers\VisitObserver;
use App\Support\ObservabilityAccess;
use App\Support\WhatsAppNumber;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use PDOException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Livewire::component('phone-login', PhoneLogin::class);
        ObservabilityAccess::register();
        $this->configureRateLimiting();
        $this->registerObservers();
        $this->configureScramble();
        $this->configureGates();
        $this->registerFilamentViewOverrides();
    }

    protected function registerFilamentViewOverrides(): void
    {
        View::prependNamespace('filament-panels', resource_path('views/vendor/filament-panels'));
        View::prependNamespace('mekaya', resource_path('views/vendor/mekaya'));
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // Default API limiter: per user or IP, stricter for bots
        RateLimiter::for('api', function (Request $request) {
            $keyBase = $request->user()?->id ?: $request->ip();
            $ua = strtolower($request->userAgent() ?? '');
            $isBot = preg_match('/bot|crawl|spider|slurp|bing|duckduck|baidu|yandex|semrush|ahrefs|crawler/', $ua);

            $perMinute = $isBot ? 30 : 60;

            return Limit::perMinute($perMinute)->by($keyBase);
        });

        // Global web limiter: high ceiling for normal users, stricter for bots
        RateLimiter::for('global-web', function (Request $request) {
            $ip = $request->ip();
            $ua = strtolower($request->userAgent() ?? '');
            $isBot = preg_match('/bot|crawl|spider|slurp|bing|duckduck|baidu|yandex|semrush|ahrefs|crawler/', $ua);

            $normal = Limit::perMinute(600)->by($ip);
            $bot = Limit::perMinute(60)->by($ip.':bot');

            return $isBot ? $bot : $normal;
        });

        // Login limiter: protect against brute-force and scripted logins
        RateLimiter::for('login', function (Request $request) {
            $ip = $request->ip();
            $identifier = (string) ($request->input('username') ?? $request->input('email') ?? '');
            $ua = strtolower($request->userAgent() ?? '');
            $isBot = preg_match('/bot|crawl|spider|slurp|bing|duckduck|baidu|yandex|semrush|ahrefs|crawler/', $ua);

            $limits = [
                Limit::perMinute(10)->by('login:ip:'.$ip),
                Limit::perMinute(5)->by('login:user:'.md5($identifier).'@'.$ip),
            ];

            if ($isBot) {
                $limits[] = Limit::perMinute(3)->by('login:bot:'.$ip);
            }

            return $limits;
        });

        RateLimiter::for('whatsapp-otp', function (Request $request) {
            $number = WhatsAppNumber::normalize((string) (
                $request->input('whatsapp_number')
                ?? $request->input('nomor_whatsapp')
                ?? $request->input('phone')
                ?? $request->input('data.whatsapp_number')
                ?? ''
            ));
            $identifier = $number !== '' ? md5($number) : 'unknown';
            $actor = $request->user()?->id ?: $request->ip();

            return [
                Limit::perMinutes(5, 3)->by('whatsapp-otp:5m:'.$identifier.':'.$actor),
                Limit::perMinutes(60, 10)->by('whatsapp-otp:1h:'.$identifier.':'.$actor),
                Limit::perMinute(20)->by('whatsapp-otp:ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('whatsapp-verify', function (Request $request) {
            $number = WhatsAppNumber::normalize((string) (
                $request->input('whatsapp_number')
                ?? $request->input('nomor_whatsapp')
                ?? $request->input('phone')
                ?? $request->input('data.whatsapp_number')
                ?? ''
            ));
            $identifier = $number !== '' ? md5($number) : 'unknown';

            return [
                Limit::perMinutes(5, 15)->by('whatsapp-verify:5m:'.$identifier.':'.$request->ip()),
                Limit::perMinute(20)->by('whatsapp-verify:ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('whatsapp-send', function ($job = null) {
            $delaySeconds = (int) config('services.whatsapp.queue_delay_seconds', 10);

            if ($delaySeconds <= 0) {
                return Limit::none();
            }

            return Limit::perSecond(1, $delaySeconds)->by('whatsapp-send:queue');
        });

        // Expensive operations limiter: exports, downloads, bulk imports, etc.
        RateLimiter::for('expensive', function (Request $request) {
            $ip = $request->ip();

            return [
                Limit::perMinute(20)->by($ip),
                Limit::perMinutes(60, 200)->by($ip.':h'),
            ];
        });
    }

    /**
     * Register all model observers.
     */
    protected function registerObservers(): void
    {
        // Organizational observers for cache clearing
        BadanUsaha::observe(OrganizationalObserver::class);
        Division::observe(OrganizationalObserver::class);
        Region::observe(OrganizationalObserver::class);
        Cluster::observe(OrganizationalObserver::class);
        Role::observe(OrganizationalObserver::class);

        // Domain observers
        User::observe(UserObserver::class);
        Visit::observe(VisitObserver::class);
        PlanVisit::observe(PlanVisitObserver::class);
        Register::observe(RegisterObserver::class);
        Outlet::observe(OutletObserver::class);
    }

    /**
     * Configure Scramble API documentation.
     */
    protected function configureScramble(): void
    {
        Scramble::afterOpenApiGenerated(function (OpenApi $openApi) {
            $openApi->secure(
                SecurityScheme::http('bearer')
            );
        });
    }

    /**
     * Configure application gates.
     */
    protected function configureGates(): void
    {
        Gate::define('viewApiDocs', function (User $user) {
            return $user->role->name === 'SUPER ADMIN';
        });

        $this->registerDynamicPermissions();
    }

    /**
     * Register dynamic permission gates from database.
     */
    protected function registerDynamicPermissions(): void
    {
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
        } catch (PDOException|QueryException) {
            return true;
        }
    }
}
