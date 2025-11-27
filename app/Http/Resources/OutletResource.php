<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OutletResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if ($request->boolean('compact', true)) {
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

        return [
            'id' => $this->id,
            'kode_outlet' => $this->kode_outlet,
            'nama_outlet' => $this->nama_outlet,
            'alamat_outlet' => str_replace(["\r", "\n"], ' ', $this->alamat_outlet),
            'nama_pemilik_outlet' => $this->nama_pemilik_outlet,
            'nomer_tlp_outlet' => $this->nomer_tlp_outlet,
            'distric' => $this->distric,
            'badanusaha' => $this->whenLoaded('badanusaha', function () {
                return $this->badanusaha ? $this->badanusaha->only(['id', 'name']) : null;
            }),
            'poto_shop_sign' => $this->poto_shop_sign,
            'poto_depan' => $this->poto_depan,
            'poto_kiri' => $this->poto_kiri,
            'poto_kanan' => $this->poto_kanan,
            'poto_ktp' => $this->poto_ktp,
            'video' => $this->video,
            'limit' => $this->limit,
            'radius' => $this->radius,
            'latlong' => $this->latlong,
            'status_outlet' => $this->status_outlet,
            'region' => $this->whenLoaded('region', function () {
                return $this->region ? $this->region->only(['id', 'name']) : null;
            }),
            'cluster' => $this->whenLoaded('cluster', function () {
                return $this->cluster ? $this->cluster->only(['id', 'name']) : null;
            }),
            'divisi' => $this->whenLoaded('divisi', function () {
                return $this->divisi ? $this->divisi->only(['id', 'name']) : null;
            }),
        ];
    }
}
