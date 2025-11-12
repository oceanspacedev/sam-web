<?php

namespace App\Jobs;

use App\Models\User;
use App\Support\StorageDisk;
use Filament\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendImportNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $userId,
        public string $title,
        public string $body,
        public bool $successful = true,
        public ?string $downloadPath = null
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

        if ($this->downloadPath) {
            $notification->actions([
                NotificationAction::make('download')
                    ->label('Download')
                    ->color('success')
                    ->url(StorageDisk::url($this->downloadPath), shouldOpenInNewTab: true),
            ]);
        }

        $notification->sendToDatabase($user);
    }
}
