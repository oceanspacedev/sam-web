<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class RegisterResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'kode_outlet' => $this->kode_outlet,
            'nama_outlet' => $this->nama_outlet,
            'alamat_outlet' => $this->alamat_outlet,
            'nama_pemilik_outlet' => $this->nama_pemilik_outlet,
            'nomer_tlp_outlet' => $this->nomer_tlp_outlet,
            'nomer_wakil_outlet' => $this->nomer_wakil_outlet,
            'ktp_outlet' => $this->ktp_outlet,
            'distric' => $this->distric,
            'poto_shop_sign' => $this->poto_shop_sign,
            'poto_depan' => $this->poto_depan,
            'poto_kiri' => $this->poto_kiri,
            'poto_kanan' => $this->poto_kanan,
            'poto_ktp' => $this->poto_ktp,
            'video' => $this->video,
            'oppo' => $this->oppo,
            'vivo' => $this->vivo,
            'realme' => $this->realme,
            'samsung' => $this->samsung,
            'xiaomi' => $this->xiaomi,
            'fl' => $this->fl,
            'latlong' => $this->latlong,
            'limit' => $this->limit,
            'status' => $this->status,
            'rejected_at' => $this->rejected_at ? Carbon::parse($this->rejected_at)->getPreciseTimestamp(3) : null,
            'rejected_by' => $this->rejected_by,
            'confirmed_at' => $this->confirmed_at ? Carbon::parse($this->confirmed_at)->getPreciseTimestamp(3) : null,
            'confirmed_by' => $this->confirmed_by,
            'approved_at' => $this->approved_at ? Carbon::parse($this->approved_at)->getPreciseTimestamp(3) : null,
            'approved_by' => $this->approved_by,
            'deleted_at' => $this->deleted_at ? Carbon::parse($this->deleted_at)->getPreciseTimestamp(3) : null,
            'created_at' => Carbon::parse($this->created_at)->getPreciseTimestamp(3),
            'updated_at' => Carbon::parse($this->updated_at)->getPreciseTimestamp(3),
            'keterangan' => $this->keterangan,
            'created_by' => $this->created_by,

            // Relationships
            'region' => $this->whenLoaded('region', function () {
                return $this->region->only(['id', 'name']);
            }),
            'cluster' => $this->whenLoaded('cluster', function () {
                return $this->cluster->only(['id', 'name']);
            }),
            'badanusaha' => $this->whenLoaded('badanusaha', function () {
                return $this->badanusaha->only(['id', 'name']);
            }),
            'divisi' => $this->whenLoaded('divisi', function () {
                return $this->divisi->only(['id', 'name']);
            }),
        ];
    }
}
