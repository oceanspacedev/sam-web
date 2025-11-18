<?php

namespace App\Jobs;

use App\Models\Outlet;
use App\Models\Register;
use App\Models\Visit;
use App\Services\FileUploadService;
use App\Services\MediaProcessingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * UNIFIED MEDIA PROCESSING JOB
 * Linear job for processing all types of media uploads
 * Works for Register, Visit, and Outlet models
 */
class ProcessMediaJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public array $backoff = [1, 5, 10]; // Exponential backoff

    public int $timeout = 300; // 5 minutes max

    public function __construct(
        public string $modelType, // 'register', 'visit', 'outlet'
        public int $modelId,
        public array $mediaItems
    ) {}

    /**
     * Get the queue the job should be sent to.
     */
    public function queue(): string
    {
        return 'media';
    }

    public function handle(MediaProcessingService $mediaService): void
    {
        $model = $this->getModel();

        if (! $model) {
            $this->cleanupAll();

            return;
        }

        try {
            $processedMedia = $this->convertTemporaryFiles($mediaService, $model);

            if (empty($processedMedia)) {
                \Log::warning("{$this->modelType} media job has no valid media items", [
                    'model_id' => $this->modelId,
                ]);

                return;
            }

            // Process all files using unified service
            $result = $mediaService->processMultipleFileUpdates($model, $processedMedia);

            // Log successful processing
            if (! empty($result['updated_fields'])) {
                \Log::info("{$this->modelType} media processed successfully", [
                    'model_id' => $this->modelId,
                    'updated_fields' => $result['updated_fields'],
                ]);
            }

            // Log any errors for debugging
            if (! empty($result['errors'])) {
                \Log::warning("Some {$this->modelType} media items failed to process", [
                    'model_id' => $this->modelId,
                    'errors' => $result['errors'],
                ]);
            }

        } catch (\Exception $e) {
            \Log::error("{$this->modelType} media processing failed", [
                'model_id' => $this->modelId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Cleanup on failure
            $this->cleanupAll();

            throw $e;
        }
    }

    protected function getModel()
    {
        return match ($this->modelType) {
            'register' => Register::query()->find($this->modelId),
            'visit' => Visit::query()->find($this->modelId),
            'outlet' => Outlet::query()->find($this->modelId),
            default => null,
        };
    }

    protected function cleanupAll(): void
    {
        $tempDisk = app(FileUploadService::class)->temporaryDisk();

        // Cleanup temporary files from all media items
        foreach ($this->mediaItems as $item) {
            if (isset($item['tmp_path'])) {
                Storage::disk($tempDisk)->delete($item['tmp_path']);
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        \Log::error("{$this->modelType} media job failed permanently", [
            'model_id' => $this->modelId,
            'error' => $exception->getMessage(),
        ]);

        // Ensure cleanup
        $this->cleanupAll();
    }

    protected function convertTemporaryFiles(MediaProcessingService $mediaService, $model): array
    {
        $processed = [];

        foreach ($this->mediaItems as $item) {
            if (empty($item['field']) || empty($item['tmp_path']) || empty($item['type'])) {
                \Log::warning("{$this->modelType} media item missing data", [
                    'model_id' => $this->modelId,
                    'item' => $item,
                ]);

                continue;
            }

            $processed[$item['field']] = $mediaService->processTemporaryFile(
                $item['tmp_path'],
                $item['type'],
                [
                    'user_id' => $model->user_id ?? null,
                ]
            );
        }

        return $processed;
    }
}
