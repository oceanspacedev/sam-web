<?php

namespace Tests\Feature;

use App\Models\Outlet;
use App\Models\PlanVisit;
use App\Models\User;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanVisitRealizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_plan_marked_realized_after_visit_is_created(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var Outlet $outlet */
        $outlet = Outlet::factory()->create();

        $plan = PlanVisit::create(array_merge(PlanVisit::schedulePayload(Carbon::today()), [
            'user_id' => $user->id,
            'outlet_id' => $outlet->id,
        ]));

        /** @var Visit $visit */
        $visit = Visit::factory()->create([
            'user_id' => $user->id,
            'outlet_id' => $outlet->id,
            'tanggal_visit' => Carbon::today()->toDateString(),
            'check_in_time' => Carbon::now(),
            'check_out_time' => Carbon::now()->addHour(),
        ]);

        $plan->refresh();

        $this->assertNotNull($plan->realized_at);
        $this->assertSame($visit->id, $plan->realized_visit_id);
    }

    public function test_weekly_plan_is_detected_when_visit_occurs_within_period(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var Outlet $outlet */
        $outlet = Outlet::factory()->create();

        $nextWeek = Carbon::now()->addWeek();

        $plan = PlanVisit::create(array_merge(PlanVisit::schedulePayload($nextWeek, 'weekly'), [
            'user_id' => $user->id,
            'outlet_id' => $outlet->id,
        ]));

        /** @var Visit $visit */
        $visit = Visit::factory()->create([
            'user_id' => $user->id,
            'outlet_id' => $outlet->id,
            'tanggal_visit' => $nextWeek->copy()->startOfWeek()->addDays(2)->toDateString(),
            'check_in_time' => Carbon::now(),
        ]);

        $plan->refresh();

        $this->assertTrue($plan->isWeekly());
        $this->assertNotNull($plan->realized_at);
        $this->assertSame($visit->id, $plan->realized_visit_id);
    }
}
