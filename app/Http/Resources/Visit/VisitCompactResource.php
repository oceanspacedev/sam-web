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
 * @property int|null $outlet_id
 * @property int|null $register_id
 * @property string $visitable_type
 * @property int|null $visitable_id
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
            'visitable_type' => $this->visitable_type === \App\Models\Outlet::class ? 'outlet' : 'register',
            'visitable_id' => $this->visitable_id,
            // Backward compatibility
            'outlet_id' => $this->outlet_id,
            'register_id' => $this->register_id,
            'tipe_visit' => $this->tipe_visit,
            'check_in_time' => $this->check_in_time ? Carbon::parse($this->check_in_time)->getPreciseTimestamp(3) : null,
            'check_out_time' => $this->check_out_time ? Carbon::parse($this->check_out_time)->getPreciseTimestamp(3) : null,
            'transaksi' => $this->transaksi,
            'durasi_visit' => $this->durasi_visit,
            'picture_visit_in' => $this->picture_visit_in,
            'picture_visit_out' => $this->picture_visit_out,
            'latlong_in' => $this->latlong_in,
            'latlong_out' => $this->latlong_out,
            'laporan_visit' => $this->laporan_visit,
            'visitable' => $this->whenLoaded('visitable', function () {
                if ($this->isOutletVisit()) {
                    return [
                        'id' => $this->visitable?->id,
                        'kode_outlet' => $this->visitable?->kode_outlet,
                        'nama_outlet' => $this->visitable?->nama_outlet,
                    ];
                }

                return [
                    'id' => $this->visitable?->id,
                    'kode_outlet' => $this->visitable?->kode_outlet,
                    'nama_outlet' => $this->visitable?->nama_outlet,
                ];
            }),
            // Backward compatibility
            'outlet' => $this->whenLoaded('visitable', function () {
                return $this->isOutletVisit() ? [
                    'id' => $this->visitable?->id,
                    'kode_outlet' => $this->visitable?->kode_outlet,
                    'nama_outlet' => $this->visitable?->nama_outlet,
                ] : null;
            }),
            'register' => $this->whenLoaded('visitable', function () {
                return $this->isRegisterVisit() ? [
                    'id' => $this->visitable?->id,
                    'kode_outlet' => $this->visitable?->kode_outlet,
                    'nama_outlet' => $this->visitable?->nama_outlet,
                ] : null;
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
