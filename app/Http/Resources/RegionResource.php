<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RegionResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'badanusaha_id' => $this->badanusaha_id,
            'divisi_id' => $this->divisi_id,

            // Relationships
            'badanusaha' => $this->whenLoaded('badanusaha', function () {
                return $this->badanusaha->only(['id', 'name']);
            }),
            'divisi' => $this->whenLoaded('divisi', function () {
                return $this->divisi->only(['id', 'name']);
            }),
        ];
    }
}
