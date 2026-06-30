<?php

namespace App\Http\Resources;

use App\Support\OrganizationalName;
use Illuminate\Http\Resources\Json\JsonResource;

class DivisionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'display_name' => OrganizationalName::label($this->resource),
            'badanusaha_id' => $this->badanusaha_id,

            // Relationships
            'badanusaha' => $this->whenLoaded('badanusaha', function () {
                return OrganizationalName::resource($this->badanusaha);
            }),
        ];
    }
}
