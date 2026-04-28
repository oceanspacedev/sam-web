<?php

namespace App\Services;

use App\Models\Outlet;
use App\Models\Register;
use App\Models\Visit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * UNIFIED MEDIA PROCESSING SERVICE
 * Linear and consistent file upload processing for both Filament and API
 */
class MediaProcessingService
{
    protected FileUploadService $fileUpload;

    protected FilenameGeneratorService $filenameGenerator;

    public function __construct(
        ?FileUploadService $fileUpload = null,
        ?FilenameGeneratorService $filenameGenerator = null
    ) {
        $this->fileUpload = $fileUpload ?? new FileUploadService;
        $this->filenameGenerator = $filenameGenerator ?? new FilenameGeneratorService;
    }

    /**
     * Process single file upload with consistent flow
     * Used by both Filament and API Controllers
     */
    public function processFileUpload(UploadedFile $file, string $type, array $options = []): array
    {
        $userId = $options['user_id'] ?? auth()->id();

        // Generate consistent filename
        $filename = $this->filenameGenerator->generate($file, $type, $userId);

        // Determine upload method based on file type
        if (str_contains($type, 'video')) {
            $path = $this->fileUpload->uploadVideoOptimized($file, $type);
        } else {
            $path = $this->fileUpload->uploadImageOptimized($file, $type);
        }

        return [
            'path' => $path,
            'filename' => $filename,
            'type' => $type,
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'user_id' => $userId,
        ];
    }

    /**
     * Process temporary file to permanent storage
     * Used by queue jobs
     */
    public function processTemporaryFile(string $temporaryPath, string $type, array $options = []): array
    {
        $userId = $options['user_id'] ?? auth()->id();
        $disk = $options['disk'] ?? $this->fileUpload->disk();

        // Validate temporary file exists
        if (! Storage::disk($this->fileUpload->temporaryDisk())->exists($temporaryPath)) {
            throw new RuntimeException("Temporary file {$temporaryPath} does not exist");
        }

        try {
            // Get file info from temporary storage
            $tempStorage = Storage::disk($this->fileUpload->temporaryDisk());
            $fileInfo = [
                'path' => $tempStorage->path($temporaryPath),
                'name' => basename($temporaryPath),
                'size' => $tempStorage->size($temporaryPath),
                'mime_type' => $tempStorage->mimeType($temporaryPath),
            ];

            // Create UploadedFile instance
            $uploadedFile = new UploadedFile(
                $fileInfo['path'],
                $fileInfo['name'],
                $fileInfo['mime_type'],
                null,
                true
            );

            // Process with consistent flow
            $result = $this->processFileUpload($uploadedFile, $type, array_merge($options, [
                'user_id' => $userId,
            ]));

            return $result;

        } catch (\Exception $e) {
            throw new RuntimeException("Failed to process temporary file: {$e->getMessage()}");
        }
    }

    /**
     * Update model with file path consistently
     * Works for Register, Visit, and Outlet models
     */
    public function updateModelWithFile($model, string $field, array $fileResult): bool
    {
        try {
            // Delete old file if exists
            $oldPath = $fileResult['old_path'] ?? $model->{$field};
            $preserveOldPath = (bool) ($fileResult['preserve_old_path'] ?? false);
            if (! $preserveOldPath && $oldPath && $oldPath !== $fileResult['path'] && ! $this->isTemporaryPath($oldPath)) {
                $this->fileUpload->deleteFile($oldPath);
            }

            // Update with new file path
            $model->{$field} = $fileResult['path'];
            $model->save();

            return true;
        } catch (\Exception $e) {
            throw new RuntimeException("Failed to update model: {$e->getMessage()}");
        }
    }

    protected function isTemporaryPath(string $path): bool
    {
        return str_starts_with($path, 'tmp/');
    }

    /**
     * Get file type from field name (consistent mapping)
     */
    public static function getFileTypeFromField(string $field, string $context = 'register'): string
    {
        // Context-specific field mappings to avoid duplication
        if ($context === 'visit') {
            $fieldMappings = [
                'picture_visit_in' => 'visit-in',
                'picture_visit_out' => 'visit-out',
            ];

            return $fieldMappings[$field] ?? 'visit-photo';
        }

        if ($context === 'outlet') {
            $fieldMappings = [
                'poto_shop_sign' => 'outlet-photo',
                'poto_depan' => 'outlet-photo',
                'poto_kiri' => 'outlet-photo',
                'poto_kanan' => 'outlet-photo',
                'poto_ktp' => 'outlet-ktp',
                'video' => 'outlet-video',
            ];

            return $fieldMappings[$field] ?? 'outlet-photo';
        }

        // Default context: register
        $fieldMappings = [
            'poto_shop_sign' => 'register-photo',
            'poto_depan' => 'register-photo',
            'poto_kiri' => 'register-photo',
            'poto_kanan' => 'register-photo',
            'poto_ktp' => 'register-ktp',
            'video' => 'register-video',
        ];

        return $fieldMappings[$field] ?? 'register-photo';
    }

    /**
     * Get target model(s) for file updates
     */
    public function getTargetModels($model, array $fileResult): array
    {
        $targets = [$model];

        // For Register models, also update related Outlet
        if ($model instanceof Register) {
            $outlet = Outlet::query()
                ->where('register_id', $model->id)
                ->first();

            if ($outlet) {
                $targets[] = $outlet;
            }
        }

        return $targets;
    }

    /**
     * Process multiple file updates for a model
     */
    public function processMultipleFileUpdates($model, array $fileUpdates): array
    {
        $results = [];
        $errors = [];

        foreach ($fileUpdates as $field => $fileResult) {
            try {
                $targets = $this->getTargetModels($model, $fileResult);

                foreach ($targets as $target) {
                    $this->updateModelWithFile($target, $field, $fileResult);
                }

                $results[$field] = $fileResult['path'];

            } catch (\Exception $e) {
                $errors[$field] = $e->getMessage();
            }
        }

        return [
            'success' => $results,
            'errors' => $errors,
            'updated_fields' => array_keys($results),
        ];
    }

    /**
     * Validate file type and size
     */
    public function validateFile(UploadedFile $file, string $type): array
    {
        $errors = [];
        $maxSize = $this->getMaxFileSize($type);
        $allowedMimes = $this->getAllowedMimeTypes($type);

        if (! $file->isValid()) {
            $errors[] = 'File upload is invalid';
        }

        if ($file->getSize() > $maxSize) {
            $errors[] = 'File size exceeds limit of '.$this->formatBytes($maxSize);
        }

        if (! in_array($file->getMimeType(), $allowedMimes)) {
            $errors[] = 'File type not allowed. Allowed: '.implode(', ', $allowedMimes);
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    protected function getMaxFileSize(string $type): int
    {
        return str_contains($type, 'video') ? 50 * 1024 * 1024 : 10 * 1024 * 1024; // 50MB video, 10MB images
    }

    protected function getAllowedMimeTypes(string $type): array
    {
        if (str_contains($type, 'video')) {
            return ['video/mp4', 'video/avi', 'video/mov', 'video/mkv', 'video/webm'];
        }

        return ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2).' '.$units[$pow];
    }
}
