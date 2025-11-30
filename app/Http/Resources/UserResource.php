<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'nama_lengkap' => $this->nama_lengkap,
            'role_id' => $this->role_id,
            'tm_id' => $this->tm_id,
            'id_notif' => $this->id_notif,
            'profile_photo_path' => $this->profile_photo_path,
            'profile_photo_url' => $this->profile_photo_url,

            // Support multiple: return arrays
            'badan_usahas' => $this->whenLoaded('badanUsahas', function () {
                return $this->badanUsahas->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                ])->values();
            }),
            'divisis' => $this->whenLoaded('divisis', function () {
                return $this->divisis->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                ])->values();
            }),
            'regions' => $this->whenLoaded('regions', function () {
                return $this->regions->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                ])->values();
            }),
            'clusters' => $this->whenLoaded('clusters', function () {
                return $this->clusters->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                ])->values();
            }),
            'role' => $this->whenLoaded('role', function () {
                return $this->role ? [
                    'id' => $this->role->id,
                    'name' => $this->role->name,
                    'organizational_scope_level' => $this->role->organizational_scope_level,
                ] : null;
            }),

            // SDUI: Menu permissions from Spatie Permission
            'permissions' => $this->getPermissions(),
        ];
    }

    /**
     * Get menu permissions based on Spatie Permission.
     * Uses existing Filament Shield permissions for consistency.
     *
     * @return array<string, bool>
     */
    protected function getPermissions(): array
    {
        $user = $this->resource;

        return [
            // Menu visibility
            'can_monitor_visit' => $user->can('ViewAny:Visit'),
            'can_manage_user' => $user->can('ViewAny:User'),

            // User management actions
            'can_create_user' => $user->can('Create:User'),
            'can_update_user' => $user->can('Update:User'),
            'can_delete_user' => $user->can('Delete:User'),
        ];
    }
}
