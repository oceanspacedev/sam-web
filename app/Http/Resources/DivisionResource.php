<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DivisionResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'badanusaha_id' => $this->badanusaha_id,

            // Relationships
            'badanusaha' => $this->whenLoaded('badanusaha', function () {
                return $this->badanusaha ? $this->badanusaha->only(['id', 'name']) : null;
            }),
        ];
    }
}
