<?php

namespace App\Providers;

use App\Services\AdminActivityLogger;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class ActivityLogServiceProvider extends ServiceProvider
{
    public function boot(AdminActivityLogger $logger): void
    {
        Event::listen(Login::class, function (Login $event) use ($logger): void {
            $logger->log(
                'login',
                'Login ke admin panel',
                null,
                [
                    'guard' => $event->guard,
                ],
                $event->user,
            );
        });

        Event::listen(Logout::class, function (Logout $event) use ($logger): void {
            $logger->log(
                'logout',
                'Logout dari admin panel',
                null,
                [
                    'guard' => $event->guard,
                ],
                $event->user,
            );
        });

        foreach (['created', 'updated', 'deleted', 'restored', 'forceDeleted'] as $event) {
            Event::listen("eloquent.{$event}: *", function (string $eventName, array $payload) use ($logger, $event): void {
                $model = $payload[0] ?? null;

                if (! $model instanceof Model) {
                    return;
                }

                if (! $logger->shouldLogModelEvent($model)) {
                    return;
                }

                $logger->logModelEvent($event === 'forceDeleted' ? 'force-deleted' : $event, $model);
            });
        }
    }
}
