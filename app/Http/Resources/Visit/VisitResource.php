<?php

namespace App\Http\Resources\Visit;

use App\Http\Resources\Outlet\OutletResource;
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
 * @property int $outlet_id
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
