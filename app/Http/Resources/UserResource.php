<?php

namespace App\Http\Resources;

use App\Models\User;
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
        $permissionUser = $request->user();
        if (! $permissionUser && $this->resource instanceof User) {
            $permissionUser = $this->resource;
        }

        return [
            'id' => $this->id,
            'username' => $this->username,
            'nama_lengkap' => $this->nama_lengkap,
            'role_id' => $this->role_id,
            'tm_id' => $this->tm_id,
            'id_notif' => $this->id_notif,
            'profile_photo_path' => $this->profile_photo_path,
            'profile_photo_url' => $this->profile_photo_url,
            'whatsapp_number' => $this->whatsapp_number,
            'nomor_whatsapp' => $this->whatsapp_number,
            'whatsapp_verified_at' => $this->whatsapp_verified_at,

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

            // SDUI menu permissions belong to the authenticated actor, not every listed user.
            'permissions' => $this->when(
                $permissionUser instanceof User && (int) $permissionUser->id === (int) $this->id,
                fn () => $this->getPermissions($permissionUser)
            ),
        ];
    }

    /**
     * Get menu permissions based on Spatie Permission.
     * Uses existing Filament Shield permissions for consistency.
     *
     * @return array<string, bool>
     */
    protected function getPermissions($user): array
    {
        return [
            // Menu visibility
            'can_monitor_visit' => $user->can('ViewAny:Visit'),
            'can_manage_user' => $user->can('ViewAny:User'),

            // User management actions
            'can_create_user' => $user->can('Create:User'),
            'can_update_user' => $user->can('Update:User'),
            'can_delete_user' => $user->can('Delete:User'),

            // Organizational management visibility
            'can_manage_badan_usaha' => $user->can('ViewAny:BadanUsaha'),
            'can_manage_division' => $user->can('ViewAny:Division'),
            'can_manage_region' => $user->can('ViewAny:Region'),
            'can_manage_cluster' => $user->can('ViewAny:Cluster'),

            // Badan usaha actions
            'can_create_badan_usaha' => $user->can('Create:BadanUsaha'),
            'can_update_badan_usaha' => $user->can('Update:BadanUsaha'),
            'can_delete_badan_usaha' => $user->can('Delete:BadanUsaha'),

            // Division actions
            'can_create_division' => $user->can('Create:Division'),
            'can_update_division' => $user->can('Update:Division'),
            'can_delete_division' => $user->can('Delete:Division'),

            // Region actions
            'can_create_region' => $user->can('Create:Region'),
            'can_update_region' => $user->can('Update:Region'),
            'can_delete_region' => $user->can('Delete:Region'),

            // Cluster actions
            'can_create_cluster' => $user->can('Create:Cluster'),
            'can_update_cluster' => $user->can('Update:Cluster'),
            'can_delete_cluster' => $user->can('Delete:Cluster'),
        ];
    }
}
