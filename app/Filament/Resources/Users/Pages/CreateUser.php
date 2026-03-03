<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        $user = $this->getRecord();

        if (! $user instanceof User) {
            return;
        }

        $scopeLevel = $user->role?->organizational_scope_level ?? 'cluster';

        match ($scopeLevel) {
            'all' => $this->detachOrganizationalAssignments($user, detachBadanUsaha: true, detachDivisi: true, detachRegion: true, detachCluster: true),
            'badanusaha' => $this->detachOrganizationalAssignments($user, detachDivisi: true, detachRegion: true, detachCluster: true),
            'divisi' => $this->detachOrganizationalAssignments($user, detachRegion: true, detachCluster: true),
            'region' => $this->detachOrganizationalAssignments($user, detachCluster: true),
            default => null,
        };
    }

    private function detachOrganizationalAssignments(
        User $user,
        bool $detachBadanUsaha = false,
        bool $detachDivisi = false,
        bool $detachRegion = false,
        bool $detachCluster = false
    ): void {
        if ($detachBadanUsaha) {
            $user->badanUsahas()->detach();
        }

        if ($detachDivisi) {
            $user->divisis()->detach();
        }

        if ($detachRegion) {
            $user->regions()->detach();
        }

        if ($detachCluster) {
            $user->clusters()->detach();
        }
    }
}
