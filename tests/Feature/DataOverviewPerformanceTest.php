<?php

use App\Filament\Widgets\DataOverview;
use App\Support\AdminPerformanceBenchmark;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('loads data overview cards with batched dashboard queries', function (): void {
    $benchmark = app(AdminPerformanceBenchmark::class);
    $seed = new ReflectionMethod(AdminPerformanceBenchmark::class, 'seedDataset');
    $seed->setAccessible(true);
    $seed->invoke($benchmark);

    $viewer = Auth::loginUsingId(cache()->get('admin-performance-benchmark:viewer-id'));
    $viewer->load(['role', 'roles', 'permissions', 'roles.permissions']);
    $viewer->getOrganizationalIds();
    Gate::allows('ViewAny:User');

    DB::flushQueryLog();
    DB::enableQueryLog();

    $widget = app(DataOverview::class);
    $method = new ReflectionMethod(DataOverview::class, 'getCards');
    $method->setAccessible(true);
    $cards = $method->invoke($widget);

    $queryCount = count(DB::getQueryLog());

    expect($cards)->not->toBeEmpty()
        ->and($queryCount)->toBeLessThanOrEqual(13);
});
