<?php

namespace App\Http\Resources\PlanVisit;

use App\Http\Resources\Outlet\OutletResource;
use App\Http\Resources\Register\RegisterResource;
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
            'visitable_type' => $this->visitable_type === \App\Models\Outlet::class ? 'outlet' : 'register',
            'visitable_id' => $this->visitable_id,
            // Backward compatibility
            'outlet_id' => $this->outlet_id,
            'register_id' => $this->register_id,
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
            'realized_visit' => $this->whenLoaded('realizedVisit', function () {
                return new VisitResource($this->realizedVisit);
            }),
        ];
    }
}
