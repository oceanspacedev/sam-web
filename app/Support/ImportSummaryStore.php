<?php

namespace App\Support;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ImportSummaryStore
{
    public function __construct(
        private readonly string $key,
        private readonly array $defaultSummary,
        private readonly ?int $ttlMinutes = null,
        private readonly ?int $errorExportLimit = null,
    ) {}

    public function initialize(): void
    {
        Cache::add(
            $this->key,
            $this->defaultSummary,
            $this->expiresAt(),
        );
    }

    /**
     * @param  callable(array<string, mixed>): void  $callback
     */
    public function mutate(callable $callback): void
    {
        $lock = Cache::lock($this->lockKey(), 15);

        try {
            $lock->block(10, function () use ($callback): void {
                $summary = Cache::get($this->key, $this->defaultSummary);
                $callback($summary);
                $this->trimErrorsExport($summary);
                Cache::put($this->key, $summary, $this->expiresAt());
            });
        } catch (LockTimeoutException $exception) {
            Log::warning('Import summary lock timeout, applying update without lock', [
                'key' => $this->key,
                'message' => $exception->getMessage(),
            ]);

            $summary = Cache::get($this->key, $this->defaultSummary);
            $callback($summary);
            $this->trimErrorsExport($summary);
            Cache::put($this->key, $summary, $this->expiresAt());
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function get(): array
    {
        return Cache::get($this->key, $this->defaultSummary);
    }

    public function forget(): void
    {
        Cache::forget($this->key);
    }

    public function key(): string
    {
        return $this->key;
    }

    private function lockKey(): string
    {
        return 'import-summary-lock:'.$this->key;
    }

    private function expiresAt(): \Illuminate\Support\Carbon
    {
        $minutes = $this->ttlMinutes ?? (int) config('imports.summary_ttl_minutes', 120);

        return now()->addMinutes($minutes);
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function trimErrorsExport(array &$summary): void
    {
        $limit = $this->errorExportLimit ?? (int) config('imports.summary_error_export_limit', 2000);
        $errorsExport = $summary['errors_export'] ?? [];

        if (! is_array($errorsExport) || count($errorsExport) <= $limit) {
            return;
        }

        $summary['errors_export'] = array_slice($errorsExport, 0, $limit);
        $summary['errors_export_truncated'] = true;
    }
}
