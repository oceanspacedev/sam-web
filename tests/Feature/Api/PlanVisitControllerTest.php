<?php

namespace Tests\Feature\Api;

use App\Models\Outlet;
use App\Models\PlanVisit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanVisitControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_fetch_returns_formatted_tanggal_visit(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var Outlet $outlet */
        $outlet = Outlet::factory()->create();

        PlanVisit::create(array_merge(PlanVisit::schedulePayload(Carbon::today()), [
            'user_id' => $user->id,
            'outlet_id' => $outlet->id,
        ]));

        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/planvisit');

        $response->assertOk();
        $response->assertJsonStructure([
            'meta' => ['code', 'status', 'message'],
            'data' => [
                ['id', 'user_id', 'outlet_id', 'tanggal_visit', 'schedule_scope', 'period_start', 'period_end'],
            ],
        ]);

        $payload = $response->json('data')[0];
        $this->assertIsInt($payload['tanggal_visit']);
        $this->assertIsInt($payload['period_start']);
        $this->assertSame('daily', $payload['schedule_scope']);
    }

    public function test_add_returns_new_record_with_formatted_date(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var Outlet $outlet */
        $outlet = Outlet::factory()->create();

        $this->actingAs($user, 'sanctum');

        $futureDate = Carbon::today()->addDays(5);

        $response = $this->postJson('/api/planvisit', [
            'tanggal_visit' => $futureDate->toDateString(),
            'kode_outlet' => $outlet->kode_outlet,
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'meta' => ['code', 'status', 'message'],
            'data' => ['id', 'user_id', 'outlet_id', 'tanggal_visit', 'schedule_scope', 'period_start', 'period_end'],
        ]);

        $this->assertIsInt($response->json('data.tanggal_visit'));
        $this->assertSame('daily', $response->json('data.schedule_scope'));
    }

    public function test_bymonth_returns_only_daily_plan_visits(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var Outlet $outlet */
        $outlet = Outlet::factory()->create();

        $month = '05';
        $year = '2024';
        $monthInt = (int) $month;
        $yearInt = (int) $year;

        PlanVisit::create(array_merge(PlanVisit::schedulePayload(Carbon::create($yearInt, $monthInt, 10)), [
            'user_id' => $user->id,
            'outlet_id' => $outlet->id,
        ]));

        PlanVisit::create(array_merge(PlanVisit::schedulePayload(Carbon::create($yearInt, $monthInt, 1), 'weekly'), [
            'user_id' => $user->id,
            'outlet_id' => $outlet->id,
        ]));

        PlanVisit::create(array_merge(PlanVisit::schedulePayload(Carbon::create($yearInt, $monthInt - 1, 28)), [
            'user_id' => $user->id,
            'outlet_id' => $outlet->id,
        ]));

        $this->actingAs($user, 'sanctum');

        $response = $this->getJson("/api/planvisit/filter?bulan={$month}&tahun={$year}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $this->assertSame('daily', $response->json('data.0.schedule_scope'));
    }
}
