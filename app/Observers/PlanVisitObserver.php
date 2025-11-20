<?php

namespace App\Observers;

use App\Models\PlanVisit;

class PlanVisitObserver
{
    /**
     * Handle the PlanVisit "creating" event.
     */
    public function creating(PlanVisit $plan): void
    {
        if ($plan->schedule_scope) {
            return;
        }

        $plan->fill(PlanVisit::schedulePayload($plan->tanggal_visit ?? now(), 'daily'));
    }

    /**
     * Handle the PlanVisit "updating" event.
     */
    public function updating(PlanVisit $plan): void
    {
        if (!$plan->isDirty('tanggal_visit') && !$plan->isDirty('schedule_scope')) {
            return;
        }

        $plan->fill(PlanVisit::schedulePayload($plan->tanggal_visit ?? now(), $plan->schedule_scope ?? 'daily'));
    }
}
