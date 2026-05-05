<?php

use App\Imports\PlanVisitImport;
use Illuminate\Support\Carbon;

beforeEach(function () {
    config()->set('plan_visit.upload_cutoff.day', 'wednesday');
    config()->set('plan_visit.upload_cutoff.time', '17:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('allows importing weekly plan for current week before Wednesday 17:00', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-14 16:59:00'));

    $import = new PlanVisitImport(null, 'weekly');
    $method = new ReflectionMethod($import, 'assertScheduleDateIsAllowed');
    $method->setAccessible(true);

    $method->invoke($import, Carbon::parse('2026-01-12'));

    expect(true)->toBeTrue();
});

it('rejects importing weekly plan for current week after Wednesday 17:00', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-14 17:01:00'));

    $import = new PlanVisitImport(null, 'weekly');
    $method = new ReflectionMethod($import, 'assertScheduleDateIsAllowed');
    $method->setAccessible(true);

    $call = fn () => $method->invoke($import, Carbon::parse('2026-01-12'));

    expect($call)->toThrow(Exception::class, 'cut-off Rabu 17.00');
});

it('allows importing weekly plan for next week after cutoff', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-14 17:01:00'));

    $import = new PlanVisitImport(null, 'weekly');
    $method = new ReflectionMethod($import, 'assertScheduleDateIsAllowed');
    $method->setAccessible(true);

    $method->invoke($import, Carbon::parse('2026-01-19'));

    expect(true)->toBeTrue();
});

it('uses upload time when queued import is processed after cutoff', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-14 16:59:00'));

    $import = new PlanVisitImport(null, 'weekly');

    Carbon::setTestNow(Carbon::parse('2026-01-14 17:01:00'));

    $method = new ReflectionMethod($import, 'assertScheduleDateIsAllowed');
    $method->setAccessible(true);

    $method->invoke($import, Carbon::parse('2026-01-12'));

    expect(true)->toBeTrue();
});

it('uses configured upload cutoff day and time', function () {
    config()->set('plan_visit.upload_cutoff.day', 'thursday');
    config()->set('plan_visit.upload_cutoff.time', '18:30');
    Carbon::setTestNow(Carbon::parse('2026-01-15 18:31:00'));

    $import = new PlanVisitImport(null, 'weekly');
    $method = new ReflectionMethod($import, 'assertScheduleDateIsAllowed');
    $method->setAccessible(true);

    $call = fn () => $method->invoke($import, Carbon::parse('2026-01-12'));

    expect(PlanVisitImport::uploadCutoffLabel())->toBe('Kamis 18.30')
        ->and($call)->toThrow(Exception::class, 'cut-off Kamis 18.30');
});

test('example', function () {
    expect(true)->toBeTrue();
});
