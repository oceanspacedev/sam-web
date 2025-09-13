<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Register any application services.
     *
     * Telescope Configuration:
     * - URL Path: env('TELESCOPE_PATH', 'telescope')
     * - Current: /admin-monitoring (configured in .env)
     * - Access: SUPER ADMIN role only
     * - Filters: Show slow requests (≥1s), all errors (4xx, 5xx), exceptions & failed jobs
     */
    public function register(): void
    {
        // Telescope::night();

        $this->hideSensitiveRequestDetails();

        // Disable Telescope completely in production if not explicitly enabled
        if ($this->app->environment('production') && !env('TELESCOPE_ENABLED', false)) {
            return;
        }

        $isLocal = $this->app->environment('local');

        Telescope::filter(function (IncomingEntry $entry) use ($isLocal) {
            // Always show in local environment
            if ($isLocal) {
                return true;
            }

            // Always show exceptions, failed requests, failed jobs, scheduled tasks, and monitored tags
            if ($entry->isReportableException() ||
                $entry->isFailedRequest() ||
                $entry->isFailedJob() ||
                $entry->isScheduledTask() ||
                $entry->hasMonitoredTag()) {
                return true;
            }

            // For request entries
            if ($entry->type === 'request') {
                $content = $entry->content;
                $statusCode = $content['response_status'] ?? 0;
                $duration = $content['duration'] ?? 0;

                // Show all error responses (4xx, 5xx) regardless of duration
                if ($statusCode >= 400) {
                    return true;
                }

                // Show successful responses (2xx, 3xx) ONLY if they're slow (≥1000ms)
                if ($statusCode >= 200 && $statusCode < 400) {
                    return $duration >= 1000; // Must be 1 second or more
                }

                // Don't show anything else
                return false;
            }

            // For query entries, only show slow queries (≥1 second)
            if ($entry->type === 'query') {
                $content = $entry->content;
                if (isset($content['time']) && $content['time'] >= 1000) {
                    return true;
                }
                return false;
            }

            // Show other types of entries (logs, cache, etc.)
            return true;
        });
    }

    /**
     * Prevent sensitive request details from being logged by Telescope.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        if ($this->app->environment('local')) {
            return;
        }

        // Hide request parameters that are actually used in the project
        Telescope::hideRequestParameters([
            '_token',                        // ✅ Laravel CSRF token
            'password',                      // ✅ Used in User model & login
            'current_password',              // ✅ Used in AuthController::updatePassword
            'new_password',                  // ✅ Used in AuthController::updatePassword
            'new_password_confirmation',     // ✅ Used in AuthController::updatePassword
            'password_confirmation',         // ✅ Common Laravel pattern
            'remember_token',                // ✅ Used in User model
        ]);

        // Hide request headers that are actually used in the project
        Telescope::hideRequestHeaders([
            'cookie',                        // ✅ Standard web cookies
            'x-csrf-token',                  // ✅ Laravel CSRF protection
            'x-xsrf-token',                  // ✅ Laravel CSRF protection
            'authorization',                 // ✅ Used for Bearer token auth
            'bearer',                        // ✅ Used in AuthController responses
        ]);
    }

    /**
     * Register the Telescope gate.
     *
     * This gate determines who can access Telescope in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewTelescope', function ($user) {
            // Check if user has SUPER ADMIN role
            return $user && $user->role && $user->role->name === 'SUPER ADMIN';
        });
    }
}
