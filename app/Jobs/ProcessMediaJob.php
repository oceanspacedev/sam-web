<?php

namespace App\Jobs;

use App\Models\Register;
use App\Models\Visit;
use App\Models\Outlet;
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
        protected string $modelType, // 'register', 'visit', 'outlet'
        protected int $modelId,
        protected array $mediaItems
    ) {}

    public function handle(MediaProcessingService $mediaService): void
    {
        $model = $this->getModel();

        if (! $model) {
            $this->cleanupAll();
            return;
        }

        try {
            // Process all files using unified service
            $result = $mediaService->processMultipleFileUpdates($model, $this->mediaItems);

            // Log successful processing
            if (!empty($result['updated_fields'])) {
                \Log::info("{$this->modelType} media processed successfully", [
                    'model_id' => $this->modelId,
                    'updated_fields' => $result['updated_fields'],
                ]);
            }

            // Log any errors for debugging
            if (!empty($result['errors'])) {
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
        return match($this->modelType) {
            'register' => Register::query()->find($this->modelId),
            'visit' => Visit::query()->find($this->modelId),
            'outlet' => Outlet::query()->find($this->modelId),
            default => null,
        };
    }

    protected function cleanupAll(): void
    {
        // Cleanup temporary files from all media items
        foreach ($this->mediaItems as $item) {
            if (isset($item['tmp_path'])) {
                Storage::disk('local')->delete($item['tmp_path']);
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
}