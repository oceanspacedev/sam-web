<?php

namespace App\Observers;

use App\Models\PlanVisit;
use Illuminate\Validation\ValidationException;

class PlanVisitObserver
{
    /**
     * Handle the PlanVisit "creating" event.
     */
    public function creating(PlanVisit $plan): void
    {
        if (! $plan->schedule_scope) {
            $plan->fill(PlanVisit::schedulePayload($plan->tanggal_visit ?? now(), 'daily'));
        }

        $this->assertUniquePeriodKey($plan);
    }

    /**
     * Handle the PlanVisit "updating" event.
     */
    public function updating(PlanVisit $plan): void
    {
        if ($plan->isDirty('tanggal_visit') || $plan->isDirty('schedule_scope')) {
            $plan->fill(PlanVisit::schedulePayload($plan->tanggal_visit ?? now(), $plan->schedule_scope ?? 'daily'));
        }

        if ($plan->isDirty(['user_id', 'visitable_type', 'visitable_id', 'schedule_scope', 'period_start'])) {
            $this->assertUniquePeriodKey($plan);
        }
    }

    protected function assertUniquePeriodKey(PlanVisit $plan): void
    {
        if (! $plan->user_id || ! $plan->visitable_type || ! $plan->visitable_id || ! $plan->schedule_scope || ! $plan->period_start) {
            return;
        }

        if (! PlanVisit::periodKeyExists(
            (int) $plan->user_id,
            (string) $plan->visitable_type,
            (int) $plan->visitable_id,
            (string) $plan->schedule_scope,
            $plan->period_start,
            $plan->exists ? (int) $plan->id : null,
        )) {
            return;
        }

        throw ValidationException::withMessages([
            'period_start' => 'Plan visit untuk target, scope, dan periode ini sudah ada.',
        ]);
    }
}
