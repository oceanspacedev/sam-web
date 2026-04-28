<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Jobs\SendUserWhatsAppRegisteredNotificationJob;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected bool $shouldSendWhatsAppRegisteredNotification = false;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->getRecord();

        $data = UserResource::mutateWhatsAppData(
            $data,
            $record instanceof User ? $record : null
        );

        $this->shouldSendWhatsAppRegisteredNotification = $record instanceof User
            && filled($data['whatsapp_number'] ?? null)
            && ($record->whatsapp_number !== $data['whatsapp_number'] || ! $record->whatsapp_verified_at);

        return $data;
    }

    protected function afterSave(): void
    {
        $user = $this->getRecord();

        if (! $this->shouldSendWhatsAppRegisteredNotification || ! $user instanceof User) {
            return;
        }

        SendUserWhatsAppRegisteredNotificationJob::dispatch((int) $user->id);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
