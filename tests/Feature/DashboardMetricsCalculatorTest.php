<?php

use App\Support\AdminPerformanceBenchmark;
use App\Support\DashboardMetricsCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('computes dashboard metrics with batched queries', function (): void {
    $benchmark = app(AdminPerformanceBenchmark::class);
    $seed = new ReflectionMethod(AdminPerformanceBenchmark::class, 'seedDataset');
    $seed->setAccessible(true);
    $seed->invoke($benchmark);

    $viewer = Auth::loginUsingId(cache()->get('admin-performance-benchmark:viewer-id'));
    $viewer->load('role');
    $viewer->getOrganizationalIds();

    $range = [
        'currentStart' => now()->subDays(6)->startOfDay(),
        'currentEnd' => now()->endOfDay(),
        'previousStart' => now()->subDays(13)->startOfDay(),
        'previousEnd' => now()->subDays(7)->endOfDay(),
        'trendStart' => now()->subDays(6)->startOfDay(),
        'trendEnd' => now()->endOfDay(),
    ];

    DB::flushQueryLog();
    DB::enableQueryLog();

    $calculator = new DashboardMetricsCalculator($viewer, $range);
    $registers = $calculator->registers();
    $metrics = [
        $calculator->users(),
        $calculator->outlets(),
        $registers['noo'],
        $registers['lead'],
        $calculator->visits(),
    ];

    $queryCount = count(DB::getQueryLog());

    expect($metrics)->toHaveCount(5)
        ->and($metrics[0])->toHaveKeys(['current', 'previous', 'trend'])
        ->and($queryCount)->toBeLessThanOrEqual(9);
});
