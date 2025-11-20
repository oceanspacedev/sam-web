<?php

namespace App\Observers;

use App\Models\PlanVisit;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class VisitObserver
{
    /**
     * Handle the Visit "saving" event.
     * Calculate visit duration before saving.
     */
    public function saving(Visit $visit): void
    {
        $this->calculateDurasiVisit($visit);
    }

    public function created(Visit $visit): void
    {
        $this->markRelatedPlanVisit($visit);
    }

    public function deleted(Visit $visit): void
    {
        PlanVisit::query()
            ->where('realized_visit_id', $visit->id)
            ->get()
            ->each(function (PlanVisit $plan): void {
                $plan->clearRealization();
            });
    }

    protected function markRelatedPlanVisit(Visit $visit): void
    {
        if (!$visit->user_id || !$visit->outlet_id || !$visit->tanggal_visit) {
            return;
        }

        $visitDate = Carbon::parse($visit->tanggal_visit)->startOfDay();

        /** @var PlanVisit|null $plan */
        $plan = PlanVisit::query()
            ->where('user_id', $visit->user_id)
            ->where('outlet_id', $visit->outlet_id)
            ->unrealized()
            ->where(function (Builder $query) use ($visitDate): void {
                $query->where(function (Builder $subQuery) use ($visitDate): void {
                    $subQuery
                        ->where('schedule_scope', 'weekly')
                        ->whereDate('period_start', '<=', $visitDate->toDateString())
                        ->whereDate('period_end', '>=', $visitDate->toDateString());
                })
                    ->orWhere(function (Builder $subQuery) use ($visitDate): void {
                        $subQuery
                            ->where('schedule_scope', 'daily')
                            ->whereDate('period_start', $visitDate->toDateString());
                    });
            })
            ->orderByDesc('schedule_scope')
            ->orderBy('period_start')
            ->first();

        if (!$plan) {
            return;
        }

        $realizedAt = $visit->check_out_time
            ? Carbon::parse($visit->check_out_time)
            : ($visit->check_in_time ? Carbon::parse($visit->check_in_time) : now());

        $plan->markAsRealized($visit, $realizedAt);
    }

    /**
     * Calculate visit duration based on check-in and check-out times.
     */
    protected function calculateDurasiVisit(Visit $visit): void
    {
        if (!empty($visit->check_in_time) && !empty($visit->check_out_time)) {
            try {
                $checkIn = Carbon::parse($visit->check_in_time);
                $checkOut = Carbon::parse($visit->check_out_time);

                $durationInMinutes = $checkIn->diffInMinutes($checkOut);

                $visit->durasi_visit = $durationInMinutes;
            } catch (\Exception $e) {
                $visit->durasi_visit = null;
            }
        } else {
            $visit->durasi_visit = null;
        }
    }
}
