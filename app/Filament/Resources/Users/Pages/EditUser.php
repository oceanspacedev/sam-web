<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Jobs\SendUserWhatsAppRegisteredNotificationJob;
use App\Models\User;
use App\Support\UserOrganizationalScopeCleanup;
use App\Support\WhatsAppQueueDelay;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use STS\FilamentImpersonate\Actions\Impersonate;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected bool $shouldSendWhatsAppRegisteredNotification = false;

    protected function beforeSave(): void
    {
        UserResource::validateActorOrganizationalAssignments(array_merge($this->data, $this->form->getState()));
    }

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

        if ($user instanceof User) {
            UserResource::syncOrganizationalAssignmentsFromState($user, $this->data);
            UserOrganizationalScopeCleanup::pruneAssignments($user);
            UserResource::pruneInconsistentOrganizationalHierarchy($user);
            UserResource::revokeUnauthorizedOrganizationalAssignments($user);
        }

        if (! $this->shouldSendWhatsAppRegisteredNotification || ! $user instanceof User) {
            return;
        }

        SendUserWhatsAppRegisteredNotificationJob::dispatch((int) $user->id)
            ->delay(app(WhatsAppQueueDelay::class)->nextDelay());
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            Impersonate::make('impersonate')
                ->visible(fn (User $record): bool => (bool) $record->role?->can_access_web),
        ];
    }
}
