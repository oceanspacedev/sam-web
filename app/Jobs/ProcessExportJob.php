<?php

namespace App\Jobs;

use App\Exports\OutletExport;
use App\Exports\PlanVisitExport;
use App\Support\StorageDisk;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ProcessExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 300; // 5 minutes for large exports

    public function __construct(
        protected string $exportType,
        protected string $filename,
        protected array $filters = [],
        protected ?int $userId = null
    ) {}

    public function handle(): void
    {
        try {
            $export = $this->getExportInstance();

            if (! $export) {
                Log::error('[ProcessExportJob] Unknown export type', ['type' => $this->exportType]);

                return;
            }

            $path = 'exports/'.$this->filename;

            $disk = StorageDisk::default();

            // Export ke storage
            Excel::store($export, $path, $disk);

            Log::info('[ProcessExportJob] Export completed successfully', [
                'type' => $this->exportType,
                'filename' => $this->filename,
                'path' => $path,
                'size' => Storage::disk($disk)->size($path),
            ]);

            // TODO: Notify user melalui notifikasi atau email bahwa export sudah selesai
            // Bisa kirim email dengan link download atau push notification
        } catch (\Exception $e) {
            Log::error('[ProcessExportJob] Export failed', [
                'type' => $this->exportType,
                'filename' => $this->filename,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e; // Re-throw untuk retry mechanism
        }
    }

    protected function getExportInstance(): mixed
    {
        return match ($this->exportType) {
            'register' => new \App\Exports\RegisterExport,
            'outlet' => new OutletExport,
            'plan_visit' => new PlanVisitExport(
                $this->filters['start_date'] ?? now()->startOfMonth()->format('Y-m-d'),
                $this->filters['end_date'] ?? now()->endOfMonth()->format('Y-m-d')
            ),
            default => null,
        };
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[ProcessExportJob] Job failed after retries', [
            'type' => $this->exportType,
            'filename' => $this->filename,
            'exception' => $exception->getMessage(),
        ]);

        // TODO: Notify user bahwa export gagal
    }
}
