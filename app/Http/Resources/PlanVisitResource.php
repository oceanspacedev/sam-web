<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanVisitResource extends JsonResource
{
    public function toArray($request): array
    {
        $periodStart = $this->period_start ? Carbon::parse($this->period_start) : null;
        $periodEnd = $this->period_end ? Carbon::parse($this->period_end) : null;

        if ($request->boolean('compact', true)) {
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
                    ];
                }),
            ];
        }

        return [
            'id' => $this->id,
            'tanggal_visit' => $this->tanggal_visit ? Carbon::parse($this->tanggal_visit)->getPreciseTimestamp(3) : null,
            'user_id' => $this->user_id,
            'outlet_id' => $this->outlet_id,
            'schedule_scope' => $this->schedule_scope,
            'period_start' => $periodStart?->getPreciseTimestamp(3),
            'period_end' => $periodEnd?->getPreciseTimestamp(3),
            'schedule_week' => $this->schedule_week,
            'schedule_year' => $this->schedule_year,
            'realized_at' => $this->realized_at ? Carbon::parse($this->realized_at)->getPreciseTimestamp(3) : null,
            'realized_visit_id' => $this->realized_visit_id,
            'is_realized' => (bool) $this->realized_at,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->getPreciseTimestamp(3) : null,
            'updated_at' => $this->updated_at ? Carbon::parse($this->updated_at)->getPreciseTimestamp(3) : null,

            // Relationships
            'user' => $this->whenLoaded('user', function () {
                return new UserResource($this->user);
            }),
            'outlet' => $this->whenLoaded('outlet', function () {
                return new OutletResource($this->outlet);
            }),
            'realized_visit' => $this->whenLoaded('realizedVisit', function () {
                return new VisitResource($this->realizedVisit);
            }),
        ];
    }
}
