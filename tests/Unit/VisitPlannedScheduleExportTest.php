<?php

use App\Exports\Visit\VisitsThisMonthSheet;
use App\Models\Outlet;
use App\Models\PlanVisit;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;

function visitExportUser(): User
{
    return User::factory()->create([
        'role_id' => Role::factory()->create(['can_access_web' => false])->id,
    ]);
}

it('labels planned visits as Daily or Weekly from the linked plan', function () {
    $user = visitExportUser();
    $outlet = Outlet::factory()->create();

    $dailyVisit = Visit::factory()->create([
        'user_id' => $user->id,
        'visitable_type' => Outlet::class,
        'visitable_id' => $outlet->id,
        'tipe_visit' => 'PLANNED',
        'tanggal_visit' => '2026-08-18',
    ]);
    PlanVisit::factory()->create(array_merge(
        PlanVisit::schedulePayload('2026-08-18', 'daily'),
        [
            'user_id' => $user->id,
            'visitable_type' => Outlet::class,
            'visitable_id' => $outlet->id,
            'realized_visit_id' => $dailyVisit->id,
            'realized_at' => now(),
        ]
    ));

    $weeklyVisit = Visit::factory()->create([
        'user_id' => $user->id,
        'visitable_type' => Outlet::class,
        'visitable_id' => $outlet->id,
        'tipe_visit' => 'PLANNED',
        'tanggal_visit' => '2026-08-17',
    ]);
    PlanVisit::factory()->create(array_merge(
        PlanVisit::schedulePayload('2026-08-17', 'weekly'),
        [
            'user_id' => $user->id,
            'visitable_type' => Outlet::class,
            'visitable_id' => $outlet->id,
            'realized_visit_id' => $weeklyVisit->id,
            'realized_at' => now(),
        ]
    ));

    expect($dailyVisit->fresh()->load('realizedPlanVisit')->plannedScheduleLabel())->toBe('Daily')
        ->and($weeklyVisit->fresh()->load('realizedPlanVisit')->plannedScheduleLabel())->toBe('Weekly');
});

it('labels extracall and orphan planned visits without a schedule', function () {
    $user = visitExportUser();
    $outlet = Outlet::factory()->create();

    $extracall = Visit::factory()->create([
        'user_id' => $user->id,
        'visitable_type' => Outlet::class,
        'visitable_id' => $outlet->id,
        'tipe_visit' => 'EXTRACALL',
    ]);

    $orphanPlanned = Visit::factory()->create([
        'user_id' => $user->id,
        'visitable_type' => Outlet::class,
        'visitable_id' => $outlet->id,
        'tipe_visit' => 'PLANNED',
    ]);

    expect($extracall->plannedScheduleLabel())->toBe('-')
        ->and($orphanPlanned->plannedScheduleLabel())->toBe('-');
});

it('includes jadwal in the monthly visit export sheet', function () {
    $user = visitExportUser();
    $outlet = Outlet::factory()->create();

    $visit = Visit::factory()->create([
        'user_id' => $user->id,
        'visitable_type' => Outlet::class,
        'visitable_id' => $outlet->id,
        'tipe_visit' => 'PLANNED',
        'tanggal_visit' => '2026-08-19',
    ]);
    PlanVisit::factory()->create(array_merge(
        PlanVisit::schedulePayload('2026-08-19', 'weekly'),
        [
            'user_id' => $user->id,
            'visitable_type' => Outlet::class,
            'visitable_id' => $outlet->id,
            'realized_visit_id' => $visit->id,
            'realized_at' => now(),
        ]
    ));

    $sheet = new VisitsThisMonthSheet($user, 8, 2026);
    $row = $sheet->collection()->first();

    expect($sheet->headings())->toContain('Tipe Visit', 'Jadwal')
        ->and($row['Tipe Visit'])->toBe('PLANNED')
        ->and($row['Jadwal'])->toBe('Weekly');
});
