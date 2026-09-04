<?php

use App\Models\Outlet;
use App\Models\PlanVisit;
use App\Models\User;
use App\Models\Visit;
use App\Support\PlanVisitMatcher;
use Carbon\Carbon;

function matcherWeeklyPlan(User $user, Outlet $outlet, Carbon $monday): PlanVisit
{
    return PlanVisit::create(array_merge(
        PlanVisit::schedulePayload($monday, 'weekly'),
        [
            'user_id' => $user->id,
            'visitable_type' => Outlet::class,
            'visitable_id' => $outlet->id,
        ]
    ));
}

it('does not match the previous weekly plan on monday', function () {
    $user = User::factory()->create();
    $outlet = Outlet::factory()->create();
    $lastMonday = Carbon::parse('2026-08-17')->startOfDay();
    $thisMonday = Carbon::parse('2026-08-24')->startOfDay();

    $lastWeek = matcherWeeklyPlan($user, $outlet, $lastMonday);
    $thisWeek = matcherWeeklyPlan($user, $outlet, $thisMonday);

    $matches = PlanVisitMatcher::unrealizedPlansForVisit(
        $user->id,
        Outlet::class,
        $outlet->id,
        $thisMonday,
    );

    expect($matches->pluck('id')->all())->toBe([$thisWeek->id])
        ->and(PlanVisitMatcher::shouldMarkPlanned($user->id, Outlet::class, $outlet->id, $thisMonday))->toBeTrue();

    $visit = Visit::withoutEvents(fn () => Visit::create([
        'user_id' => $user->id,
        'visitable_type' => Outlet::class,
        'visitable_id' => $outlet->id,
        'tanggal_visit' => $thisMonday->toDateString(),
        'tipe_visit' => 'PLANNED',
        'check_in_time' => $thisMonday->copy()->setTime(10, 0),
        'latlong_in' => '0,0',
    ]));

    PlanVisitMatcher::realizeMatchingPlans($visit);

    expect($thisWeek->fresh()->realized_visit_id)->toBe($visit->id)
        ->and($lastWeek->fresh()->realized_at)->toBeNull();
});

it('still matches a daily plan within the plus or minus one day window', function () {
    $user = User::factory()->create();
    $outlet = Outlet::factory()->create();
    $tuesday = Carbon::parse('2026-08-25')->startOfDay();

    $yesterdayPlan = PlanVisit::create(array_merge(
        PlanVisit::schedulePayload($tuesday->copy()->subDay(), 'daily'),
        [
            'user_id' => $user->id,
            'visitable_type' => Outlet::class,
            'visitable_id' => $outlet->id,
        ]
    ));

    $matches = PlanVisitMatcher::unrealizedPlansForVisit(
        $user->id,
        Outlet::class,
        $outlet->id,
        $tuesday,
    );

    expect($matches->pluck('id')->all())->toBe([$yesterdayPlan->id]);
});

it('prefers the daily plan whose date is the visit date over the plus or minus one neighbour', function () {
    $user = User::factory()->create();
    $outlet = Outlet::factory()->create();
    $tuesday = Carbon::parse('2026-08-25')->startOfDay();

    PlanVisit::create(array_merge(
        PlanVisit::schedulePayload($tuesday->copy()->subDay(), 'daily'),
        [
            'user_id' => $user->id,
            'visitable_type' => Outlet::class,
            'visitable_id' => $outlet->id,
        ]
    ));
    $todayPlan = PlanVisit::create(array_merge(
        PlanVisit::schedulePayload($tuesday, 'daily'),
        [
            'user_id' => $user->id,
            'visitable_type' => Outlet::class,
            'visitable_id' => $outlet->id,
        ]
    ));

    $matches = PlanVisitMatcher::unrealizedPlansForVisit(
        $user->id,
        Outlet::class,
        $outlet->id,
        $tuesday,
    );

    expect($matches->first()->id)->toBe($todayPlan->id);
});

it('does not treat a planned visit linked to another week as consuming this week', function () {
    $user = User::factory()->create();
    $outlet = Outlet::factory()->create();
    $lastMonday = Carbon::parse('2026-08-17')->startOfDay();
    $thisMonday = Carbon::parse('2026-08-24')->startOfDay();

    $lastWeek = matcherWeeklyPlan($user, $outlet, $lastMonday);
    $thisWeek = matcherWeeklyPlan($user, $outlet, $thisMonday);

    $mondayVisit = Visit::withoutEvents(fn () => Visit::create([
        'user_id' => $user->id,
        'visitable_type' => Outlet::class,
        'visitable_id' => $outlet->id,
        'tanggal_visit' => $thisMonday->toDateString(),
        'tipe_visit' => 'PLANNED',
        'check_in_time' => $thisMonday->copy()->setTime(10, 0),
        'latlong_in' => '0,0',
    ]));
    $lastWeek->markAsRealized($mondayVisit, $thisMonday->copy()->setTime(10, 0));

    expect(PlanVisitMatcher::periodAlreadyConsumed($thisWeek, $thisMonday->copy()->addDays(3)))->toBeFalse()
        ->and(PlanVisitMatcher::shouldMarkPlanned($user->id, Outlet::class, $outlet->id, $thisMonday->copy()->addDays(3)))->toBeTrue();
});

it('realizes a weekly plan when an orphan planned visit is saved', function () {
    $user = User::factory()->create();
    $outlet = Outlet::factory()->create();
    $monday = Carbon::parse('2026-08-24')->startOfDay();

    $plan = matcherWeeklyPlan($user, $outlet, $monday);

    $visit = Visit::withoutEvents(fn () => Visit::create([
        'user_id' => $user->id,
        'visitable_type' => Outlet::class,
        'visitable_id' => $outlet->id,
        'tanggal_visit' => $monday->copy()->addDays(4)->toDateString(),
        'tipe_visit' => 'PLANNED',
        'check_in_time' => $monday->copy()->addDays(4)->setTime(14, 0),
        'latlong_in' => '0,0',
    ]));

    expect($plan->fresh()->realized_at)->toBeNull();

    $visit->update(['laporan_visit' => 'done']);

    expect($plan->fresh()->realized_visit_id)->toBe($visit->id)
        ->and($visit->fresh()->load('realizedPlanVisit')->plannedScheduleLabel())->toBe('Weekly');
});
