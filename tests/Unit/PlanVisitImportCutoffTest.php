<?php

use App\Imports\PlanVisitImport;
use Illuminate\Support\Carbon;

it('allows importing weekly plan for current week before Tuesday 10:00', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-12 08:00:00'));

    $import = new PlanVisitImport(null, 'weekly');
    $method = new ReflectionMethod($import, 'assertScheduleDateIsAllowed');
    $method->setAccessible(true);

    $method->invoke($import, Carbon::parse('2026-01-12'));

    expect(true)->toBeTrue();
});

it('rejects importing weekly plan for current week after Tuesday 10:00', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-13 10:01:00'));

    $import = new PlanVisitImport(null, 'weekly');
    $method = new ReflectionMethod($import, 'assertScheduleDateIsAllowed');
    $method->setAccessible(true);

    $call = fn () => $method->invoke($import, Carbon::parse('2026-01-12'));

    expect($call)->toThrow(Exception::class, 'cut-off Selasa 10.00');
});

it('allows importing weekly plan for next week after cutoff', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-13 10:01:00'));

    $import = new PlanVisitImport(null, 'weekly');
    $method = new ReflectionMethod($import, 'assertScheduleDateIsAllowed');
    $method->setAccessible(true);

    $method->invoke($import, Carbon::parse('2026-01-19'));

    expect(true)->toBeTrue();
});

test('example', function () {
    expect(true)->toBeTrue();
});
