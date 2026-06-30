<?php

namespace App\Http\Resources;

use App\Support\OrganizationalName;
use Illuminate\Http\Resources\Json\JsonResource;

class BadanUsahaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'display_name' => OrganizationalName::label($this->resource),
        ];
    }
}
