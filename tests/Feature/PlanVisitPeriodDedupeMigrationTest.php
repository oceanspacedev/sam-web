<?php

use App\Models\Outlet;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('dedupe prefers realized keeper and demotes the duplicate realized planned visit', function () {
    $user = User::factory()->create([
        'role_id' => Role::factory()->create(['can_access_web' => false])->id,
    ]);
    $outlet = Outlet::factory()->create();
    $periodStart = '2026-07-20';

    Schema::table('plan_visits', function ($table) {
        $table->dropUnique('plan_visits_user_visitable_scope_period_unique');
    });

    $keeperVisit = Visit::factory()->create([
        'user_id' => $user->id,
        'visitable_type' => Outlet::class,
        'visitable_id' => $outlet->id,
        'tanggal_visit' => $periodStart,
        'tipe_visit' => 'PLANNED',
    ]);

    $duplicateVisit = Visit::factory()->create([
        'user_id' => $user->id,
        'visitable_type' => Outlet::class,
        'visitable_id' => $outlet->id,
        'tanggal_visit' => '2026-07-22',
        'tipe_visit' => 'PLANNED',
    ]);

    $keeperId = DB::table('plan_visits')->insertGetId([
        'user_id' => $user->id,
        'visitable_type' => Outlet::class,
        'visitable_id' => $outlet->id,
        'tanggal_visit' => $periodStart,
        'schedule_scope' => 'weekly',
        'period_start' => $periodStart,
        'period_end' => '2026-07-26',
        'schedule_week' => 30,
        'schedule_year' => 2026,
        'realized_visit_id' => $keeperVisit->id,
        'realized_at' => '2026-07-20 10:00:00',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $duplicateId = DB::table('plan_visits')->insertGetId([
        'user_id' => $user->id,
        'visitable_type' => Outlet::class,
        'visitable_id' => $outlet->id,
        'tanggal_visit' => $periodStart,
        'schedule_scope' => 'weekly',
        'period_start' => $periodStart,
        'period_end' => '2026-07-26',
        'schedule_week' => 30,
        'schedule_year' => 2026,
        'realized_visit_id' => $duplicateVisit->id,
        'realized_at' => '2026-07-22 10:00:00',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration = require database_path('migrations/2026_07_25_150000_unique_plan_visits_period_and_fix_double_planned.php');
    $method = new ReflectionMethod($migration, 'dedupePlanVisits');
    $method->setAccessible(true);
    $method->invoke($migration);

    expect(DB::table('plan_visits')->where('id', $keeperId)->exists())->toBeTrue()
        ->and(DB::table('plan_visits')->where('id', $duplicateId)->exists())->toBeFalse()
        ->and(DB::table('plan_visits')->where('id', $keeperId)->value('realized_visit_id'))->toBe($keeperVisit->id)
        ->and($duplicateVisit->fresh()->tipe_visit)->toBe('EXTRACALL')
        ->and($keeperVisit->fresh()->tipe_visit)->toBe('PLANNED');

    Schema::table('plan_visits', function ($table) {
        $table->unique(
            ['user_id', 'visitable_type', 'visitable_id', 'schedule_scope', 'period_start'],
            'plan_visits_user_visitable_scope_period_unique'
        );
    });
});

it('dedupe keeps the realized plan when an unrealized duplicate shares the period key', function () {
    $user = User::factory()->create([
        'role_id' => Role::factory()->create(['can_access_web' => false])->id,
    ]);
    $outlet = Outlet::factory()->create();
    $periodStart = '2026-07-20';

    Schema::table('plan_visits', function ($table) {
        $table->dropUnique('plan_visits_user_visitable_scope_period_unique');
    });

    $realizedVisit = Visit::factory()->create([
        'user_id' => $user->id,
        'visitable_type' => Outlet::class,
        'visitable_id' => $outlet->id,
        'tanggal_visit' => $periodStart,
        'tipe_visit' => 'PLANNED',
    ]);

    DB::table('plan_visits')->insert([
        'user_id' => $user->id,
        'visitable_type' => Outlet::class,
        'visitable_id' => $outlet->id,
        'tanggal_visit' => $periodStart,
        'schedule_scope' => 'daily',
        'period_start' => $periodStart,
        'period_end' => $periodStart,
        'schedule_week' => 30,
        'schedule_year' => 2026,
        'realized_visit_id' => null,
        'realized_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $realizedPlanId = DB::table('plan_visits')->insertGetId([
        'user_id' => $user->id,
        'visitable_type' => Outlet::class,
        'visitable_id' => $outlet->id,
        'tanggal_visit' => $periodStart,
        'schedule_scope' => 'daily',
        'period_start' => $periodStart,
        'period_end' => $periodStart,
        'schedule_week' => 30,
        'schedule_year' => 2026,
        'realized_visit_id' => $realizedVisit->id,
        'realized_at' => '2026-07-20 10:00:00',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration = require database_path('migrations/2026_07_25_150000_unique_plan_visits_period_and_fix_double_planned.php');
    $method = new ReflectionMethod($migration, 'dedupePlanVisits');
    $method->setAccessible(true);
    $method->invoke($migration);

    $remaining = DB::table('plan_visits')
        ->where('user_id', $user->id)
        ->where('visitable_id', $outlet->id)
        ->whereDate('period_start', $periodStart)
        ->get();

    expect($remaining)->toHaveCount(1)
        ->and($remaining->first()->id)->toBe($realizedPlanId)
        ->and($remaining->first()->realized_visit_id)->toBe($realizedVisit->id);

    Schema::table('plan_visits', function ($table) {
        $table->unique(
            ['user_id', 'visitable_type', 'visitable_id', 'schedule_scope', 'period_start'],
            'plan_visits_user_visitable_scope_period_unique'
        );
    });
});
