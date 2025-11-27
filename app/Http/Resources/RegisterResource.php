<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class RegisterResource extends JsonResource
{
    public function toArray($request): array
    {
        if ($request->boolean('compact', true)) {
            return [
                'id' => $this->id,
                'kode_outlet' => $this->kode_outlet,
                'nama_outlet' => $this->nama_outlet,
                'alamat_outlet' => $this->alamat_outlet,
                'status' => $this->status,
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
            'latlong' => $this->latlong,
            'limit' => $this->limit,
            'status' => $this->status,
            'keterangan' => $this->keterangan,
            'created_by_id' => $this->created_by_id,
            'created_by' => $this->createdBy?->nama_lengkap,
            'rejected_at' => $this->rejected_at ? Carbon::parse($this->rejected_at)->getPreciseTimestamp(3) : null,
            'rejected_by_id' => $this->rejected_by_id,
            'rejected_by' => $this->rejectedBy?->nama_lengkap,
            'confirmed_at' => $this->confirmed_at ? Carbon::parse($this->confirmed_at)->getPreciseTimestamp(3) : null,
            'confirmed_by_id' => $this->confirmed_by_id,
            'confirmed_by' => $this->confirmedBy?->nama_lengkap,
            'approved_at' => $this->approved_at ? Carbon::parse($this->approved_at)->getPreciseTimestamp(3) : null,
            'approved_by_id' => $this->approved_by_id,
            'approved_by' => $this->approvedBy?->nama_lengkap,
            'created_at' => Carbon::parse($this->created_at)->getPreciseTimestamp(3),
            'updated_at' => Carbon::parse($this->updated_at)->getPreciseTimestamp(3),

            // Relationships
            'region' => $this->whenLoaded('region', function () {
                return $this->region ? $this->region->only(['id', 'name']) : null;
            }),
            'cluster' => $this->whenLoaded('cluster', function () {
                return $this->cluster ? $this->cluster->only(['id', 'name']) : null;
            }),
            'badanusaha' => $this->whenLoaded('badanusaha', function () {
                return $this->badanusaha ? $this->badanusaha->only(['id', 'name']) : null;
            }),
            'divisi' => $this->whenLoaded('divisi', function () {
                return $this->divisi ? $this->divisi->only(['id', 'name']) : null;
            }),

            // SDUI: Actions based on user permissions
            'actions' => $this->getActions($request),
        ];
    }

    protected function getActions($request): array
    {
        $user = $request->user();
        
        if (!$user) {
            return [
                'can_confirm' => false,
                'can_reject' => false,
                'can_approve' => false,
            ];
        }

        return [
            'can_confirm' => $user->can('confirm', $this->resource),
            'can_reject' => $user->can('reject', $this->resource),
            'can_approve' => $user->can('approve', $this->resource),
        ];
    }
}
