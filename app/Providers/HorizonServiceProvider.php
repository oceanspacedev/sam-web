<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon night mode
        Horizon::night();

        // Horizon notifications (optional)
        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('admin@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            // Allow access to admin users only
            if (!$user) {
                return false;
            }

            // Check if user has admin role
            if (method_exists($user, 'hasRole') && $user->hasRole('admin')) {
                return true;
            }

            // Allow specific admin emails (fallback)
            return in_array($user->email, [
                'admin@example.com',
                'apriansyah@complete-selular.com', // Add your admin email
                // Add more admin emails as needed
            ]);
        });
    }
}
