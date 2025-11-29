<?php

namespace App\Http\Resources\PlanVisit;

use App\Http\Resources\Outlet\OutletResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\Visit\VisitResource;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full PlanVisit resource with all fields and relationships.
 * Use this for detail views and complete plan visit information.
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
 * @property string $created_at
 * @property string $updated_at
 */
class PlanVisitResource extends JsonResource
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
