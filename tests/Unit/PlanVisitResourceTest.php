<?php

namespace Tests\Unit;

use App\Filament\Resources\PlanVisits\PlanVisitResource;
use App\Models\Division;
use App\Models\Outlet;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanVisitResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_prepare_schedule_payload_keeps_daily_scope_for_regular_outlet(): void
    {
        $outlet = Outlet::factory()->create();

        $payload = PlanVisitResource::prepareSchedulePayload([
            'user_id' => 1,
            'outlet_id' => $outlet->id,
            'schedule_scope' => 'daily',
            'tanggal_visit' => '2025-11-01',
            'outlet_division_id' => null,
        ]);

        $this->assertSame('daily', $payload['schedule_scope']);
        $this->assertSame('2025-11-01', $payload['period_start']);
        $this->assertSame('2025-11-01', $payload['period_end']);
        $this->assertArrayNotHasKey('outlet_division_id', $payload);
    }

    public function test_prepare_schedule_payload_respects_scope_for_division_4_outlet(): void
    {
        $division4 = Division::factory()->create(['id' => 4]);
        $outlet = Outlet::factory()->create([
            'divisi_id' => $division4->id,
        ]);

        $payload = PlanVisitResource::prepareSchedulePayload([
            'user_id' => 1,
            'outlet_id' => $outlet->id,
            'schedule_scope' => 'daily',
            'tanggal_visit' => '2025-11-12',
            'outlet_division_id' => 4,
        ]);

        $this->assertSame('daily', $payload['schedule_scope']);
        $this->assertSame('2025-11-12', $payload['period_start']);
        $this->assertSame('2025-11-12', $payload['period_end']);
    }

    public function test_weekly_schedule_creates_monday_to_saturday_range(): void
    {
        // Test with a Wednesday (2025-11-12)
        $payload = PlanVisitResource::prepareSchedulePayload([
            'user_id' => 1,
            'outlet_id' => 1,
            'schedule_scope' => 'weekly',
            'tanggal_visit' => '2025-11-12',
        ]);

        // Should start on Monday (2025-11-10) and end on Saturday (2025-11-15)
        $this->assertSame('2025-11-10', $payload['period_start']); // Monday
        $this->assertSame('2025-11-15', $payload['period_end']); // Saturday
        $this->assertSame('weekly', $payload['schedule_scope']);
    }

    public function test_week_selector_shows_five_upcoming_weeks(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 11, 18));

        $options = PlanVisitResource::weeklyScheduleOptions();

        $this->assertCount(5, $options);

        $firstKey = array_key_first($options);
        $expectedMonday = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $expectedFirstKey = sprintf('%d_%s', $expectedMonday->weekOfYear, $expectedMonday->toDateString());
        $this->assertSame($expectedFirstKey, $firstKey);

        $lastKey = array_key_last($options);
        $lastMonday = $expectedMonday->copy()->addWeeks(4);
        $expectedLastKey = sprintf('%d_%s', $lastMonday->weekOfYear, $lastMonday->toDateString());
        $this->assertSame($expectedLastKey, $lastKey);

        Carbon::setTestNow();
    }

    public function test_week_selector_value_used_in_schedule_payload(): void
    {
        $selectedWeek = '51_2025-12-15';

        $payload = PlanVisitResource::prepareSchedulePayload([
            'user_id' => 1,
            'outlet_id' => 1,
            'schedule_scope' => 'weekly',
            'schedule_week_selector' => $selectedWeek,
        ]);

        $this->assertSame('weekly', $payload['schedule_scope']);
        $this->assertSame('2025-12-15', $payload['period_start']);
        $this->assertSame('2025-12-20', $payload['period_end']);
    }
}
