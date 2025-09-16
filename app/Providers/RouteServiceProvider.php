<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * This is used by Laravel authentication to redirect users after login.
     *
     * @var string
     */
    public const HOME = '/admin';

    /**
     * The controller namespace for the application.
     *
     * When present, controller route declarations will automatically be prefixed with this namespace.
     *
     * @var string|null
     */
    // protected $namespace = 'App\\Http\\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::prefix('api')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        // Default API limiter: per user or IP, stricter for bots
        RateLimiter::for('api', function (Request $request) {
            $keyBase = optional($request->user())->id ?: $request->ip();
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

            // Normal users get a generous limit; bots get a lower one
            $normal = Limit::perMinute(600)->by($ip);
            $bot = Limit::perMinute(60)->by($ip.':bot');

            return $isBot ? $bot : $normal;
        });

        // Login limiter: protect against brute-force and scripted logins
        RateLimiter::for('login', function (Request $request) {
            $ip = $request->ip();
            // App ini menggunakan username, bukan email, untuk login.
            // Tetap fallback ke email jika ada agar kompatibel.
            $identifier = (string) ($request->input('username') ?? $request->input('email') ?? '');
            $ua = strtolower($request->userAgent() ?? '');
            $isBot = preg_match('/bot|crawl|spider|slurp|bing|duckduck|baidu|yandex|semrush|ahrefs|crawler/', $ua);

            $limits = [
                // Throttle by IP
                Limit::perMinute(10)->by('login:ip:'.$ip),
                // Throttle by username/email + IP untuk menahan brute force terarah
                Limit::perMinute(5)->by('login:user:'.md5($identifier).'@'.$ip),
            ];

            if ($isBot) {
                $limits[] = Limit::perMinute(3)->by('login:bot:'.$ip);
            }

            return $limits;
        });

        // Expensive operations limiter: exports, downloads, bulk imports, etc.
        RateLimiter::for('expensive', function (Request $request) {
            $ip = $request->ip();
            return [
                Limit::perMinute(20)->by($ip),
                // 200 per hour cap
                Limit::perMinutes(60, 200)->by($ip.':h'),
            ];
        });
    }
}
