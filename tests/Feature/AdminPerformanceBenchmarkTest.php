<?php

use App\Support\AdminPerformanceBenchmark;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

it('runs admin performance benchmark and optionally stores tagged results', function (): void {
    $benchmark = app(AdminPerformanceBenchmark::class);
    $results = $benchmark->run(iterations: 3);

    expect($results)->toHaveKeys(['generated_at', 'iterations', 'dataset', 'scenarios', 'totals'])
        ->and($results['scenarios'])->toHaveKeys([
            'register_list_page',
            'visit_list_page',
            'user_list_page',
            'outlet_list_page',
            'division_index',
            'region_index',
            'cluster_index',
            'dashboard_stats',
            'badan_usaha_index',
        ]);

    $tag = env('BENCHMARK_TAG');

    if (is_string($tag) && $tag !== '') {
        $directory = storage_path('app/benchmarks');
        File::ensureDirectoryExists($directory);
        File::put(
            $directory.DIRECTORY_SEPARATOR.$tag.'.json',
            json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );
    }

    expect($results['totals']['queries'])->toBeGreaterThan(0);
});
