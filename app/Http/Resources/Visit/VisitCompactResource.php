<?php

namespace App\Http\Resources\Visit;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Compact version of Visit resource for list views.
 * Returns minimal fields for performance optimization.
 *
 * @property int $id
 * @property string $tanggal_visit
 * @property int $user_id
 * @property int $outlet_id
 * @property string $tipe_visit
 * @property string|null $check_in_time
 * @property string|null $check_out_time
 * @property int|null $transaksi
 * @property int|null $durasi_visit
 */
class VisitCompactResource extends JsonResource
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
}
