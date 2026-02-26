<?php

namespace App\Http\Resources\Register;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Compact version of Register resource for list views.
 * Returns minimal fields for performance optimization.
 *
 * @property int $id
 * @property string|null $kode_outlet
 * @property string $nama_outlet
 * @property string $alamat_outlet
 * @property string|null $status
 * @property string|null $type
 * @property string|null $keterangan
 * @property string $distric
 * @property string $latlong
 * @property string $created_at
 */
class RegisterCompactResource extends JsonResource
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
            'alamat_outlet' => $this->alamat_outlet,
            'status' => $this->status,
            'type' => $this->type,
            'keterangan' => $this->keterangan,
            'distric' => $this->distric,
            'latlong' => $this->latlong,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->getPreciseTimestamp(3) : null,
            'badanusaha' => $this->whenLoaded('badanusaha', function () {
                return $this->badanusaha ? $this->badanusaha->only(['id', 'name']) : null;
            }),
            'divisi' => $this->whenLoaded('divisi', function () {
                return $this->divisi ? $this->divisi->only(['id', 'name']) : null;
            }),
            'region' => $this->whenLoaded('region', function () {
                return $this->region ? $this->region->only(['id', 'name']) : null;
            }),
            'cluster' => $this->whenLoaded('cluster', function () {
                return $this->cluster ? $this->cluster->only(['id', 'name']) : null;
            }),
        ];
    }
}
