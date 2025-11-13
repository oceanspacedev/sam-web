<?php

namespace App\Jobs;

use App\Models\User;
use App\Support\StorageDisk;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Lang;

class SendImportNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<int, array{name?:string,label?:string,path:string}>|null  $downloads
     */
    public function __construct(
        public int $userId,
        public string $title,
        public string $body,
        public bool $successful = true,
        public ?string $downloadPath = null,
        public ?array $downloads = null
    ) {}

    public function handle(): void
    {
        $user = User::find($this->userId);

        if (! $user) {
            return;
        }

        $notification = Notification::make()
            ->title($this->title)
            ->body($this->body);

        if ($this->successful) {
            $notification->success();
        } else {
            $notification->danger();
        }

        $downloads = $this->downloads ?? [];

        if ($this->downloadPath && ! $this->containsDownloadPath($downloads, $this->downloadPath)) {
            $downloads[] = ['path' => $this->downloadPath];
        }

        if ($downloads !== []) {
            $notification->actions($this->buildDownloadActions($downloads));
        }

        $notification->sendToDatabase($user, isEventDispatched: true);
    }

    /**
     * @param  array<int|string, array{name?:string,label?:string,path:string}>  $downloads
     * @return array<int, Action>
     */
    private function buildDownloadActions(array $downloads): array
    {
        $actions = [];

        foreach ($downloads as $key => $download) {
            $path = $download['path'];
            $label = $download['label'] ?? $this->resolveLabelFromPath($path);
            $name = $download['name'] ?? $this->resolveActionNameFromPath($path, $key);

            $actions[] = Action::make($name)
                ->label($label)
                ->url(StorageDisk::url($path), shouldOpenInNewTab: true)
                ->markAsRead();
        }

        return $actions;
    }

    private function resolveActionNameFromPath(string $path, int|string $key): string
    {
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        if ($extension !== '') {
            return 'download_'.$extension;
        }

        return is_int($key) ? 'download-'.$key : (string) $key;
    }

    private function resolveLabelFromPath(string $path): string
    {
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === '') {
            return 'Unduh';
        }

        $translationKey = "filament-actions::export.notifications.completed.actions.download_{$extension}.label";

        if (Lang::has($translationKey)) {
            return __($translationKey);
        }

        return 'Unduh .'.$extension;
    }

    /**
     * @param  array<int|string, array{name?:string,label?:string,path:string}>  $downloads
     */
    private function containsDownloadPath(array $downloads, string $path): bool
    {
        foreach ($downloads as $download) {
            if (($download['path'] ?? null) === $path) {
                return true;
            }
        }

        return false;
    }
}
