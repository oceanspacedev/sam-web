<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VisitResource extends JsonResource
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
                'tanggal_visit' => $this->tanggal_visit ? Carbon::parse($this->tanggal_visit)->toDateString() : null,
                'user_id' => $this->user_id,
                'outlet_id' => $this->outlet_id,
                'tipe_visit' => $this->tipe_visit,
                'check_in_time' => $this->check_in_time ? Carbon::parse($this->check_in_time)->getPreciseTimestamp(3) : null,
                'check_out_time' => $this->check_out_time ? Carbon::parse($this->check_out_time)->getPreciseTimestamp(3) : null,
                'transaksi' => $this->transaksi,
                'durasi_visit' => $this->durasi_visit,
                'outlet' => $this->whenLoaded('outlet', function () {
                    return [
                        'id' => $this->outlet?->id,
                        'kode_outlet' => $this->outlet?->kode_outlet,
                        'nama_outlet' => $this->outlet?->nama_outlet,
                    ];
                }),
                'user' => $this->whenLoaded('user', function () {
                    return [
                        'id' => $this->user?->id,
                        'nama_lengkap' => $this->user?->nama_lengkap,
                    ];
                }),
            ];
        }

        return [
            'id' => $this->id,
            'tanggal_visit' => Carbon::parse($this->tanggal_visit)->getPreciseTimestamp(3),
            'user_id' => $this->user_id,
            'outlet_id' => $this->outlet_id,
            'tipe_visit' => $this->tipe_visit,
            'latlong_in' => $this->latlong_in,
            'latlong_out' => $this->latlong_out,
            'check_in_time' => Carbon::parse($this->check_in_time)->getPreciseTimestamp(3),
            'check_out_time' => $this->check_out_time ? Carbon::parse($this->check_out_time)->getPreciseTimestamp(3) : null,
            'laporan_visit' => $this->laporan_visit,
            'durasi_visit' => $this->durasi_visit,
            'picture_visit_in' => $this->picture_visit_in,
            'picture_visit_out' => $this->picture_visit_out,
            'transaksi' => $this->transaksi,

            // Relationships
            'outlet' => $this->whenLoaded('outlet', function () {
                return $this->outlet ? new OutletResource($this->outlet) : null;
            }),
            'user' => $this->whenLoaded('user', function () {
                return $this->user ? new UserResource($this->user) : null;
            }),
        ];
    }
}
