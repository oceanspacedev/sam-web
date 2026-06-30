<?php

namespace App\Http\Resources;

use App\Support\OrganizationalName;
use Illuminate\Http\Resources\Json\JsonResource;

class ClusterResource extends JsonResource
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
            'badanusaha_id' => $this->badanusaha_id,
            'divisi_id' => $this->divisi_id,
            'region_id' => $this->region_id,

            // Relationships
            'badanusaha' => $this->whenLoaded('badanusaha', function () {
                return OrganizationalName::resource($this->badanusaha);
            }),
            'divisi' => $this->whenLoaded('divisi', function () {
                return OrganizationalName::resource($this->divisi);
            }),
            'region' => $this->whenLoaded('region', function () {
                return OrganizationalName::resource($this->region);
            }),
        ];
    }
}
