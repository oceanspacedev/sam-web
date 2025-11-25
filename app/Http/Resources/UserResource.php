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
            'badanusaha' => $this->whenLoaded('badanUsahas', function () {
                return $this->badanUsahas->first() ? [
                    'id' => $this->badanUsahas->first()->id,
                    'name' => $this->badanUsahas->first()->name,
                ] : null;
            }),
            'divisi' => $this->whenLoaded('divisis', function () {
                return $this->divisis->first() ? [
                    'id' => $this->divisis->first()->id,
                    'name' => $this->divisis->first()->name,
                ] : null;
            }),
            'region' => $this->whenLoaded('regions', function () {
                return $this->regions->first() ? [
                    'id' => $this->regions->first()->id,
                    'name' => $this->regions->first()->name,
                ] : null;
            }),
            'cluster' => $this->whenLoaded('clusters', function () {
                return $this->clusters->first() ? [
                    'id' => $this->clusters->first()->id,
                    'name' => $this->clusters->first()->name,
                ] : null;
            }),
            'role' => $this->whenLoaded('role', function () {
                return $this->role ? [
                    'id' => $this->role->id,
                    'name' => $this->role->name,
                ] : null;
            }),
            'id_notif' => $this->id_notif,
        ];
    }
}
