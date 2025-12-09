<?php

namespace App\Services;

use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;

class AdminActivityLogger
{
    /**
     * Ensure logging only happens inside the admin panel context
     * and while the logger is enabled.
     */
    public function isActive(?Request $request = null): bool
    {
        if (! config('activitylog.enabled', true)) {
            return false;
        }

        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return false;
        }

        $request ??= request();

        $panel = Filament::getCurrentPanel();
        if ($panel?->getId() === 'admin') {
            return true;
        }

        if (! $request) {
            return false;
        }

        $path = ltrim($request->path(), '/');

        if (str_starts_with($path, 'admin')) {
            return true;
        }

        $referer = (string) $request->headers->get('referer', '');
        $refererPath = ltrim(parse_url($referer, PHP_URL_PATH) ?? '', '/');

        return str_starts_with($refererPath, 'admin');
    }

    public function currentUser(): ?Authenticatable
    {
        return Filament::auth()?->user() ?? Auth::user();
    }

    public function log(
        string $event,
        string $description,
        ?Model $subject = null,
        array $properties = [],
        ?Authenticatable $user = null,
    ): ?Activity {
        if (! $this->isActive()) {
            return null;
        }

        $user ??= $this->currentUser();

        if (! $user) {
            return null;
        }

        $request = request();

        $meta = [
            'ip' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'url' => $request?->fullUrl(),
            'panel' => 'admin',
        ];

        if ($subject) {
            $meta['subject_id'] = $subject->getKey();
            $meta['subject_type'] = $subject::class;
        }

        $logger = activity('filament-admin')
            ->event($event)
            ->causedBy($user);

        if ($subject) {
            $logger->performedOn($subject);
        }

        return $logger
            ->withProperties(array_filter([...$meta, ...$properties], fn ($value) => $value !== null))
            ->log($description);
    }

    public function shouldLogModelEvent(Model $model): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        if (! $this->currentUser()) {
            return false;
        }

        if ($model instanceof Activity) {
            return false;
        }

        return str_starts_with($model::class, 'App\\');
    }

    public function logModelEvent(string $event, Model $model): ?Activity
    {
        $properties = [
            'model' => $model::class,
            'model_id' => $model->getKey(),
        ];

        if ($event === 'updated') {
            $properties['changed'] = array_keys($model->getChanges());
        }

        $description = sprintf('%s %s via admin panel', ucfirst(str_replace('-', ' ', $event)), class_basename($model));

        return $this->log($event, $description, $model, $properties);
    }
}
