<?php

namespace App\Http\Resources\Outlet;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full Outlet resource with all fields including media files.
 * Use this for detail views and complete outlet information.
 *
 * @property int $id
 * @property string $kode_outlet
 * @property string $nama_outlet
 * @property string $alamat_outlet
 * @property string $nama_pemilik_outlet
 * @property string $nomer_tlp_outlet
 * @property string $distric
 * @property string $poto_shop_sign
 * @property string $poto_depan
 * @property string $poto_kiri
 * @property string $poto_kanan
 * @property string $poto_ktp
 * @property string|null $video
 * @property int $limit
 * @property int $radius
 * @property string $latlong
 * @property string $status_outlet
 */
class OutletResource extends JsonResource
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
            'alamat_outlet' => str_replace(["\r", "\n"], ' ', $this->alamat_outlet),
            'nama_pemilik_outlet' => $this->nama_pemilik_outlet,
            'nomer_tlp_outlet' => $this->nomer_tlp_outlet,
            'distric' => $this->distric,
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
            'badanusaha' => $this->whenLoaded('badanusaha', function () {
                return $this->badanusaha ? $this->badanusaha->only(['id', 'name']) : null;
            }),
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
