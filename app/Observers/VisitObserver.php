<?php

namespace App\Observers;

use App\Models\PlanVisit;
use App\Models\Visit;
use App\Support\PlanVisitMatcher;
use Carbon\Carbon;

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

    public function updated(Visit $visit): void
    {
        if (strtoupper((string) $visit->tipe_visit) !== 'PLANNED') {
            if ($visit->wasChanged('tipe_visit')) {
                PlanVisit::query()
                    ->where('realized_visit_id', $visit->id)
                    ->get()
                    ->each(function (PlanVisit $plan): void {
                        $plan->clearRealization();
                    });
            }

            return;
        }

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
        $realizedPlans = PlanVisitMatcher::realizeMatchingPlans($visit);

        if ($realizedPlans->isEmpty()) {
            return;
        }

        if ($visit->tipe_visit !== 'PLANNED') {
            Visit::withoutEvents(function () use ($visit) {
                $visit->forceFill(['tipe_visit' => 'PLANNED'])->save();
            });
        }
    }

    /**
     * Calculate visit duration based on check-in and check-out times.
     */
    protected function calculateDurasiVisit(Visit $visit): void
    {
        if (! empty($visit->check_in_time) && ! empty($visit->check_out_time)) {
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
