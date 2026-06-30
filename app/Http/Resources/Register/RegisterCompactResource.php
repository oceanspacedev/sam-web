<?php

namespace App\Http\Resources\Register;

use App\Support\OrganizationalName;
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
                return OrganizationalName::resource($this->badanusaha);
            }),
            'divisi' => $this->whenLoaded('divisi', function () {
                return OrganizationalName::resource($this->divisi);
            }),
            'region' => $this->whenLoaded('region', function () {
                return OrganizationalName::resource($this->region);
            }),
            'cluster' => $this->whenLoaded('cluster', function () {
                return OrganizationalName::resource($this->cluster);
            }),
        ];
    }
}
