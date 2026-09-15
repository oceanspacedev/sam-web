<?php

namespace App\Http\Controllers\API\Traits;

use App\Services\FileUploadService;
use App\Services\MediaProcessingService;
use Illuminate\Http\UploadedFile;

trait HasMediaUpload
{
    protected function storeMediaFile(UploadedFile $file, string $type, array $options = []): string
    {
        return app(FileUploadService::class)->put($file, $type, $options);
    }

    protected function cleanupTemporaryFiles(array $paths): void
    {
        $uploads = app(FileUploadService::class);

        foreach ($paths as $path) {
            if (is_string($path) && $path !== '') {
                $uploads->deleteFile($path);
            }
        }
    }

    protected function mediaTypeFor(string $field, string $modelType): string
    {
        return MediaProcessingService::getFileTypeFromField($field, $modelType);
    }
}
