<?php

namespace App\Http\Resources\Outlet;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Compact version of Outlet resource for list views and performance optimization.
 * Returns minimal fields needed for lists, maps, and dropdowns.
 *
 * @property int $id
 * @property string $kode_outlet
 * @property string $nama_outlet
 * @property string $status_outlet
 * @property string $latlong
 * @property int $radius
 */
class OutletCompactResource extends JsonResource
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
            'kode_outlet' => $this->kode_outlet,
            'nama_outlet' => $this->nama_outlet,
            'status_outlet' => $this->status_outlet,
            'latlong' => $this->latlong,
            'radius' => $this->radius,
            'badanusaha' => $this->whenLoaded('badanusaha', fn () => $this->badanusaha?->only(['id', 'name'])),
            'divisi' => $this->whenLoaded('divisi', fn () => $this->divisi?->only(['id', 'name'])),
            'region' => $this->whenLoaded('region', fn () => $this->region?->only(['id', 'name'])),
            'cluster' => $this->whenLoaded('cluster', fn () => $this->cluster?->only(['id', 'name'])),
        ];
    }
}
