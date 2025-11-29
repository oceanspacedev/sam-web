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
 * @property int $outlet_id
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
            'outlet_id' => $this->outlet_id,
            'schedule_scope' => $this->schedule_scope,
            'period_start' => $periodStart?->toDateString(),
            'period_end' => $periodEnd?->toDateString(),
            'schedule_week' => $this->schedule_week,
            'schedule_year' => $this->schedule_year,
            'tanggal_visit' => $this->tanggal_visit ? Carbon::parse($this->tanggal_visit)->toDateString() : null,
            'outlet' => $this->whenLoaded('outlet', function () {
                return [
                    'id' => $this->outlet?->id,
                    'kode_outlet' => $this->outlet?->kode_outlet,
                    'nama_outlet' => $this->outlet?->nama_outlet,
                    'distric' => $this->outlet?->distric,
                    'latlong' => $this->outlet?->latlong, // For map markers
                    'alamat_outlet' => $this->outlet?->alamat_outlet,
                ];
            }),
        ];
    }
}
