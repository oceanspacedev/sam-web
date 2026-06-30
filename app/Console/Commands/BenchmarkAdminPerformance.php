<?php

namespace App\Console\Commands;

use App\Support\AdminPerformanceBenchmark;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class BenchmarkAdminPerformance extends Command
{
    protected $signature = 'admin:benchmark
        {--tag= : Simpan hasil ke storage/app/benchmarks/{tag}.json}
        {--compare= : Bandingkan dua tag, contoh: baseline,after}
        {--iterations=3 : Jumlah iterasi per skenario}';

    protected $description = 'Benchmark query count & durasi skenario admin Filament.';

    public function handle(AdminPerformanceBenchmark $benchmark): int
    {
        if ($compare = $this->option('compare')) {
            return $this->compareTags($compare);
        }

        $tag = $this->option('tag');

        if (! is_string($tag) || $tag === '') {
            $this->error('Wajib sertakan --tag, contoh: --tag=baseline');

            return self::FAILURE;
        }

        if (! app()->environment('testing')) {
            $this->warn('Benchmark paling valid di environment testing (sqlite :memory:).');
            $this->warn('Jalankan: php artisan test --filter=AdminPerformanceBenchmarkTest');
        }

        $results = $benchmark->run((int) $this->option('iterations'));
        $path = $this->storeResults($tag, $results);

        $this->info("Benchmark [{$tag}] disimpan: {$path}");
        $this->renderSummary($results);

        return self::SUCCESS;
    }

    protected function compareTags(string $compare): int
    {
        [$baselineTag, $currentTag] = array_pad(explode(',', $compare, 2), 2, null);

        if (! is_string($baselineTag) || $baselineTag === '' || ! is_string($currentTag) || $currentTag === '') {
            $this->error('Format --compare=baseline,after');

            return self::FAILURE;
        }

        $baseline = $this->loadResults($baselineTag);
        $current = $this->loadResults($currentTag);

        if ($baseline === null || $current === null) {
            return self::FAILURE;
        }

        $comparison = AdminPerformanceBenchmark::compare($baseline, $current);

        $this->info("Perbandingan {$baselineTag} → {$currentTag}");
        $this->newLine();

        $rows = [];
        foreach ($comparison['scenarios'] as $name => $row) {
            $rows[] = [
                $name,
                $row['queries']['before'],
                $row['queries']['after'],
                $row['queries']['delta'],
                self::formatPercent($row['queries']['pct']),
                $row['duration_ms']['before'],
                $row['duration_ms']['after'],
                $row['duration_ms']['delta'],
                self::formatPercent($row['duration_ms']['pct']),
            ];
        }

        $this->table(
            ['Scenario', 'Q before', 'Q after', 'Q Δ', 'Q %', 'ms before', 'ms after', 'ms Δ', 'ms %'],
            $rows,
        );

        $totals = $comparison['totals'];
        $this->newLine();
        $this->line(sprintf(
            'TOTAL queries: %s → %s (%s%s, %s%%)',
            $totals['queries']['before'],
            $totals['queries']['after'],
            $totals['queries']['delta'] <= 0 ? '' : '+',
            $totals['queries']['delta'],
            self::formatPercent($totals['queries']['pct']),
        ));
        $this->line(sprintf(
            'TOTAL duration: %s ms → %s ms (%s%s ms, %s%%)',
            $totals['duration_ms']['before'],
            $totals['duration_ms']['after'],
            $totals['duration_ms']['delta'] <= 0 ? '' : '+',
            $totals['duration_ms']['delta'],
            self::formatPercent($totals['duration_ms']['pct']),
        ));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $results
     */
    protected function storeResults(string $tag, array $results): string
    {
        $directory = storage_path('app/benchmarks');
        File::ensureDirectoryExists($directory);

        $path = $directory.DIRECTORY_SEPARATOR.$tag.'.json';
        File::put($path, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $path;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function loadResults(string $tag): ?array
    {
        $path = storage_path('app/benchmarks/'.$tag.'.json');

        if (! File::exists($path)) {
            $this->error("File benchmark tidak ditemukan: {$path}");

            return null;
        }

        $decoded = json_decode(File::get($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $results
     */
    protected function renderSummary(array $results): void
    {
        $rows = [];

        foreach ($results['scenarios'] as $name => $scenario) {
            $rows[] = [
                $name,
                $scenario['queries']['median'],
                $scenario['duration_ms']['median'],
            ];
        }

        $this->table(['Scenario', 'Queries (median)', 'Duration ms (median)'], $rows);
        $this->line(sprintf(
            'TOTAL: %s queries, %s ms',
            $results['totals']['queries'],
            $results['totals']['duration_ms'],
        ));
    }

    protected static function formatPercent(?float $value): string
    {
        if ($value === null) {
            return 'n/a';
        }

        return ($value > 0 ? '+' : '').$value;
    }
}
