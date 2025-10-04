<?php

namespace App\Jobs;

use App\Models\Register;
use App\Services\FileUploadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessFilamentUpload implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $backoff = [1, 5, 10]; // Exponential backoff
    public int $timeout = 300; // 5 minutes max

    public function __construct(
        protected UploadedFile $file,
        protected array $metadata
    ) {}

    public function handle(FileUploadService $uploadService): void
    {
        try {
            $type = $this->metadata['type'];
            $field = $this->metadata['field'];
            $userId = $this->metadata['user_id'];

            // Use optimized upload with flat storage
            if ($type === 'register-video') {
                $path = $uploadService->uploadVideoOptimized($this->file, $type);
            } else {
                $path = $uploadService->uploadImageOptimized($this->file, $type);
            }

            // Update related models if needed
            $this->updateRelatedModels($field, $path);

            Log::info('Filament upload processed successfully', [
                'field' => $field,
                'path' => $path,
                'user_id' => $userId,
            ]);

        } catch (\Exception $e) {
            Log::error('Filament upload processing failed', [
                'error' => $e->getMessage(),
                'metadata' => $this->metadata,
            ]);

            throw $e;
        }
    }

    protected function updateRelatedModels(string $field, string $path): void
    {
        // Find related register if register_id is provided
        if (isset($this->metadata['register_id'])) {
            $register = Register::find($this->metadata['register_id']);
            if ($register) {
                $register->update([$field => $path]);
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Filament upload job failed permanently', [
            'error' => $exception->getMessage(),
            'metadata' => $this->metadata,
        ]);
    }
}