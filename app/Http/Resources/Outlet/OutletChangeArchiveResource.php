<?php

namespace App\Http\Resources\Outlet;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OutletChangeArchiveResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'outlet_id' => $this->outlet_id,
            'kode_outlet' => $this->kode_outlet,
            'action' => $this->action,
            'actor' => [
                'id' => $this->actor_user_id,
                'name' => $this->actor_name,
            ],
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'changed_fields' => $this->changed_fields,
            'restored_from_id' => $this->restored_from_id,
            'restored_by_user_id' => $this->restored_by_user_id,
            'restored_at' => $this->restored_at,
            'created_at' => $this->created_at,
        ];
    }
}
