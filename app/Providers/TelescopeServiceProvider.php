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
     * - Filters: Show only slow requests (>500ms) and errors in production
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

            // For request entries, only show 200 OK responses with slow queries (>500ms)
            if ($entry->type === 'request') {
                $content = $entry->content;

                // Check if it's a 200 OK response
                if (isset($content['response_status']) && $content['response_status'] === 200) {
                    // Check if duration is over 500ms
                    if (isset($content['duration']) && $content['duration'] > 500) {
                        return true;
                    }
                }

                // Don't show other 200 OK requests
                return false;
            }

            // For query entries, only show slow queries (>500ms)
            if ($entry->type === 'query') {
                $content = $entry->content;
                if (isset($content['time']) && $content['time'] > 500) {
                    return true;
                }
                return false;
            }

            // Show other types of entries
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
