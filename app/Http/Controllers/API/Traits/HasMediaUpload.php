<?php

namespace App\Http\Controllers\API\Traits;

use App\Jobs\ProcessMediaJob;
use App\Services\FileUploadService;
use App\Services\MediaProcessingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;

/**
 * UNIFIED MEDIA UPLOAD TRAIT
 * Provides consistent media upload handling for all API controllers
 */
trait HasMediaUpload
{
    /**
     * Process media uploads with unified flow
     * Used by Register, Visit, and Outlet controllers
     */
    protected function processMediaUploads(Request $request, string $modelType, int $modelId): array
    {
        $temporaryFiles = [];
        $mediaQueue = [];
        $mediaDispatched = false;

        try {
            // Get media service
            $mediaService = app(MediaProcessingService::class);

            // Process photo uploads
            if ($request->hasFile('photo0')) {
                $this->processPhotoUpload($request->file('photo0'), 'photo0', $modelType, $temporaryFiles, $mediaQueue);
            }
            if ($request->hasFile('photo1')) {
                $this->processPhotoUpload($request->file('photo1'), 'photo1', $modelType, $temporaryFiles, $mediaQueue);
            }
            if ($request->hasFile('photo2')) {
                $this->processPhotoUpload($request->file('photo2'), 'photo2', $modelType, $temporaryFiles, $mediaQueue);
            }
            if ($request->hasFile('photo3')) {
                $this->processPhotoUpload($request->file('photo3'), 'photo3', $modelType, $temporaryFiles, $mediaQueue);
            }
            if ($request->hasFile('photo4')) {
                $this->processPhotoUpload($request->file('photo4'), 'photo4', $modelType, $temporaryFiles, $mediaQueue);
            }

            // Process video upload
            if ($request->hasFile('video')) {
                $this->processVideoUpload($request->file('video'), $modelType, $temporaryFiles, $mediaQueue);
            }

            // Dispatch queue job if there are media items
            if (! empty($mediaQueue)) {
                ProcessMediaJob::dispatch($modelType, $modelId, $mediaQueue);
                $mediaDispatched = true;
            }

            return [
                'temporary_files' => $temporaryFiles,
                'media_queue' => $mediaQueue,
                'media_dispatched' => $mediaDispatched,
            ];

        } catch (\Exception $e) {
            if (! $mediaDispatched) {
                $this->cleanupTemporaryFiles($temporaryFiles);
            }

            throw $e;
        }
    }

    /**
     * Process single photo upload
     */
    protected function processPhotoUpload($file, string $photoKey, string $modelType, array &$temporaryFiles, array &$mediaQueue): void
    {
        if (! $file || ! $file->isValid()) {
            return;
        }

        // Store temporarily
        $temporaryPath = app(FileUploadService::class)
            ->storeTemporary($file, 'tmp');

        $temporaryFiles[] = $temporaryPath;

        // Map photo key to field name and type
        $fieldMapping = $this->getPhotoFieldMapping($modelType);
        $field = $fieldMapping[$photoKey] ?? 'photo';
        $type = MediaProcessingService::getFileTypeFromField($field, $modelType);

        $mediaQueue[] = [
            'field' => $field,
            'tmp_path' => $temporaryPath,
            'type' => $type,
        ];
    }

    /**
     * Process video upload
     */
    protected function processVideoUpload($file, string $modelType, array &$temporaryFiles, array &$mediaQueue): void
    {
        if (! $file || ! $file->isValid()) {
            return;
        }

        // Store temporarily
        $temporaryPath = app(FileUploadService::class)
            ->storeTemporary($file, 'tmp');

        $temporaryFiles[] = $temporaryPath;

        $mediaQueue[] = [
            'field' => 'video',
            'tmp_path' => $temporaryPath,
            'type' => MediaProcessingService::getFileTypeFromField('video', $modelType),
        ];
    }

    /**
     * Get photo field mapping for different model types
     */
    protected function getPhotoFieldMapping(string $modelType): array
    {
        return match ($modelType) {
            'register' => [
                'photo0' => 'poto_shop_sign',
                'photo1' => 'poto_depan',
                'photo2' => 'poto_kiri',
                'photo3' => 'poto_kanan',
                'photo4' => 'poto_ktp',
            ],
            'visit' => [
                'photo0' => 'picture_visit_in',
                'photo1' => 'picture_visit_out',
            ],
            'outlet' => [
                'photo0' => 'poto_shop_sign',
                'photo1' => 'poto_depan',
                'photo2' => 'poto_kiri',
                'photo3' => 'poto_kanan',
                'photo4' => 'poto_ktp',
            ],
            default => [],
        };
    }

    /**
     * Cleanup temporary files on error
     */
    protected function cleanupTemporaryFiles(array $temporaryFiles): void
    {
        foreach ($temporaryFiles as $tempPath) {
            try {
                app(FileUploadService::class)
                    ->deleteFile($tempPath);
            } catch (\Exception $e) {
                // Log error but continue cleanup
                Log::error("Failed to cleanup temporary file: {$tempPath}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Dispatch media job with unified handling
     */
    protected function dispatchMediaJob(string $modelType, int $modelId, array $mediaQueue): bool
    {
        if (empty($mediaQueue)) {
            return false;
        }

        try {
            Queue::push((new ProcessMediaJob($modelType, $modelId, $mediaQueue))->onQueue('media'));

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to dispatch media job', [
                'model_type' => $modelType,
                'model_id' => $modelId,
                'media_count' => count($mediaQueue),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Validate media files
     */
    protected function validateMediaFiles(Request $request, string $modelType): array
    {
        $errors = [];
        $mediaService = app(MediaProcessingService::class);

        // Validate photos
        for ($i = 0; $i < 5; $i++) {
            $photoKey = "photo{$i}";
            if ($request->hasFile($photoKey)) {
                $file = $request->file($photoKey);
                $type = MediaProcessingService::getFileTypeFromField($photoKey, $modelType);
                $validation = $mediaService->validateFile($file, $type);

                if (! $validation['valid']) {
                    $errors[$photoKey] = $validation['errors'];
                }
            }
        }

        // Validate video
        if ($request->hasFile('video')) {
            $file = $request->file('video');
            $type = MediaProcessingService::getFileTypeFromField('video', $modelType);
            $validation = $mediaService->validateFile($file, $type);

            if (! $validation['valid']) {
                $errors['video'] = $validation['errors'];
            }
        }

        return $errors;
    }
}
