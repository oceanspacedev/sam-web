<?php

namespace App\Support;

use App\Models\PlanVisit;
use App\Models\Visit;
use Illuminate\Support\Collection;

class VisitTypeHealer
{
    /**
     * @return Collection<int, int>
     */
    public static function plannedVisitIds(): Collection
    {
        $visitIds = Visit::query()
            ->where('tipe_visit', 'EXTRACALL')
            ->whereIn('id', PlanVisit::query()->whereNotNull('realized_visit_id')->pluck('realized_visit_id'))
            ->pluck('id');

        $visitIdsByRealizedAt = Visit::query()
            ->where('tipe_visit', 'EXTRACALL')
            ->whereNotIn('id', $visitIds)
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('plan_visits')
                    ->whereColumn('plan_visits.user_id', 'visits.user_id')
                    ->whereColumn('plan_visits.visitable_type', 'visits.visitable_type')
                    ->whereColumn('plan_visits.visitable_id', 'visits.visitable_id')
                    ->whereNotNull('plan_visits.realized_at')
                    ->whereRaw('DATE(plan_visits.realized_at) = visits.tanggal_visit');
            })
            ->pluck('id');

        return $visitIds
            ->merge($visitIdsByRealizedAt)
            ->unique()
            ->values();
    }
}