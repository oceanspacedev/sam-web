<?php

namespace App\Http\Resources\PlanVisit;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Compact version of PlanVisit resource for list views.
 * Returns minimal fields for calendar and list displays.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $outlet_id
 * @property int|null $register_id
 * @property string $visitable_type
 * @property int|null $visitable_id
 * @property string $schedule_scope
 * @property string $period_start
 * @property string $period_end
 * @property int|null $schedule_week
 * @property int|null $schedule_year
 * @property string|null $tanggal_visit
 * @property string|null $realized_at
 * @property int|null $realized_visit_id
 */
class PlanVisitCompactResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $periodStart = $this->period_start ? Carbon::parse($this->period_start) : null;
        $periodEnd = $this->period_end ? Carbon::parse($this->period_end) : null;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'visitable_type' => $this->visitable_type === \App\Models\Outlet::class ? 'outlet' : 'register',
            'visitable_id' => $this->visitable_id,
            // Backward compatibility
            'outlet_id' => $this->outlet_id,
            'register_id' => $this->register_id,
            'schedule_scope' => $this->schedule_scope,
            'period_start' => $periodStart?->toDateString(),
            'period_end' => $periodEnd?->toDateString(),
            'schedule_week' => $this->schedule_week,
            'schedule_year' => $this->schedule_year,
            'tanggal_visit' => $this->tanggal_visit ? Carbon::parse($this->tanggal_visit)->toDateString() : null,
            'visitable' => $this->whenLoaded('visitable', function () {
                return [
                    'id' => $this->visitable?->id,
                    'kode_outlet' => $this->visitable?->kode_outlet,
                    'nama_outlet' => $this->visitable?->nama_outlet,
                    'distric' => $this->visitable?->distric,
                    'latlong' => $this->visitable?->latlong,
                    'alamat_outlet' => $this->visitable?->alamat_outlet,
                ];
            }),
            // Backward compatibility
            'outlet' => $this->whenLoaded('visitable', function () {
                return $this->isOutletVisit() ? [
                    'id' => $this->visitable?->id,
                    'kode_outlet' => $this->visitable?->kode_outlet,
                    'nama_outlet' => $this->visitable?->nama_outlet,
                    'distric' => $this->visitable?->distric,
                    'latlong' => $this->visitable?->latlong,
                    'alamat_outlet' => $this->visitable?->alamat_outlet,
                ] : null;
            }),
            'register' => $this->whenLoaded('visitable', function () {
                return $this->isRegisterVisit() ? [
                    'id' => $this->visitable?->id,
                    'kode_outlet' => $this->visitable?->kode_outlet,
                    'nama_outlet' => $this->visitable?->nama_outlet,
                    'distric' => $this->visitable?->distric,
                    'latlong' => $this->visitable?->latlong,
                    'alamat_outlet' => $this->visitable?->alamat_outlet,
                ] : null;
            }),
        ];
    }
}
