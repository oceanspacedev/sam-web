<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Jobs\SendUserWhatsAppRegisteredNotificationJob;
use App\Models\User;
use App\Support\UserOrganizationalScopeCleanup;
use App\Support\WhatsAppQueueDelay;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return UserResource::mutateWhatsAppData($data);
    }

    protected function handleRecordCreation(array $data): Model
    {
        UserResource::validateActorOrganizationalAssignments(array_merge($this->data, $data));

        return parent::handleRecordCreation($data);
    }

    protected function afterCreate(): void
    {
        $user = $this->getRecord();

        if (! $user instanceof User) {
            return;
        }

        UserResource::syncOrganizationalAssignmentsFromState($user, $this->data);
        UserOrganizationalScopeCleanup::pruneAssignments($user);
        UserResource::pruneInconsistentOrganizationalHierarchy($user);
        UserResource::revokeUnauthorizedOrganizationalAssignments($user);

        if (filled($user->whatsapp_number) && $user->whatsapp_verified_at) {
            SendUserWhatsAppRegisteredNotificationJob::dispatch((int) $user->id)
                ->delay(app(WhatsAppQueueDelay::class)->nextDelay());
        }
    }
}
