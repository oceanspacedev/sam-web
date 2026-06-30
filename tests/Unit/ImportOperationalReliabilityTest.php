<?php

use App\Support\ImportJobDispatcher;
use App\Support\ImportSummaryStore;

it('runs imports synchronously when queue connection is sync', function (): void {
    config([
        'queue.default' => 'sync',
        'imports.force_sync' => false,
        'imports.sync_fallback' => true,
    ]);

    expect(ImportJobDispatcher::shouldRunSynchronously())->toBeTrue();
});

it('forces synchronous imports when configured', function (): void {
    config([
        'queue.default' => 'redis',
        'imports.force_sync' => true,
        'imports.sync_fallback' => false,
    ]);

    expect(ImportJobDispatcher::shouldRunSynchronously())->toBeTrue();
});

it('keeps atomic import summary counters under concurrent updates', function (): void {
    $store = new ImportSummaryStore('import-summary-test', [
        'processed' => 0,
        'errors_export' => [],
        'error_total' => 0,
    ], ttlMinutes: 5, errorExportLimit: 3);

    $store->initialize();

    for ($i = 0; $i < 5; $i++) {
        $store->mutate(function (array &$summary): void {
            $summary['processed']++;
            $summary['errors_export'][] = ['row' => $summary['processed'], 'message' => 'err'];
            $summary['error_total']++;
        });
    }

    $summary = $store->get();

    expect($summary['processed'])->toBe(5)
        ->and($summary['error_total'])->toBe(5)
        ->and($summary['errors_export'])->toHaveCount(3)
        ->and($summary['errors_export_truncated'] ?? false)->toBeTrue();
});
