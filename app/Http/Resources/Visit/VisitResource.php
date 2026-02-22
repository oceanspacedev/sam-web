<?php

namespace App\Http\Resources\Visit;

use App\Http\Resources\Outlet\OutletResource;
use App\Http\Resources\Register\RegisterResource;
use App\Http\Resources\UserResource;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full Visit resource with all fields including location and media.
 * Use this for detail views and complete visit information.
 *
 * @property int $id
 * @property string $tanggal_visit
 * @property int $user_id
 * @property int|null $outlet_id
 * @property int|null $register_id
 * @property string $visitable_type
 * @property int|null $visitable_id
 * @property string $tipe_visit
 * @property string $latlong_in
 * @property string|null $latlong_out
 * @property string $check_in_time
 * @property string|null $check_out_time
 * @property string|null $laporan_visit
 * @property int|null $durasi_visit
 * @property string $picture_visit_in
 * @property string|null $picture_visit_out
 * @property int|null $transaksi
 */
class VisitResource extends JsonResource
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
            'tanggal_visit' => Carbon::parse($this->tanggal_visit)->getPreciseTimestamp(3),
            'user_id' => $this->user_id,
            'visitable_type' => $this->visitable_type === \App\Models\Outlet::class ? 'outlet' : 'register',
            'visitable_id' => $this->visitable_id,
            // Backward compatibility
            'outlet_id' => $this->outlet_id,
            'register_id' => $this->register_id,
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
            'visitable' => $this->whenLoaded('visitable', function () {
                if ($this->isOutletVisit()) {
                    return $this->visitable ? new OutletResource($this->visitable) : null;
                }

                return $this->visitable ? new RegisterResource($this->visitable) : null;
            }),
            // Backward compatibility
            'outlet' => $this->whenLoaded('visitable', function () {
                return $this->isOutletVisit() && $this->visitable
                    ? new OutletResource($this->visitable)
                    : null;
            }),
            'register' => $this->whenLoaded('visitable', function () {
                return $this->isRegisterVisit() && $this->visitable
                    ? new RegisterResource($this->visitable)
                    : null;
            }),
            'user' => $this->whenLoaded('user', function () {
                return $this->user ? new UserResource($this->user) : null;
            }),
        ];
    }
}
