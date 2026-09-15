<?php

namespace App\Services;

use App\Support\StorageDisk;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class FileUploadService
{
    protected FilenameGeneratorService $filenameGenerator;

    public function __construct(
        protected ?string $disk = null,
        protected ?string $temporaryDisk = null,
        ?FilenameGeneratorService $filenameGenerator = null,
    ) {
        $this->disk ??= StorageDisk::default();
        $this->temporaryDisk = $this->disk;
        $this->filenameGenerator = $filenameGenerator ?? new FilenameGeneratorService;
    }

    public function disk(): string
    {
        return (string) $this->disk;
    }

    public function temporaryDisk(): string
    {
        return (string) $this->disk;
    }

    /**
     * Store an uploaded file once on FILESYSTEM_DISK.
     */
    public function put(UploadedFile $file, string $type = 'file', array $options = []): string
    {
        $filename = $options['filename'] ?? $this->filenameGenerator->generate(
            $file,
            $type,
            $options['user_id'] ?? null,
        );

        $directory = trim((string) ($options['directory'] ?? ''), '/');

        return Storage::disk($this->disk)->putFileAs($directory, $file, $filename);
    }

    public function uploadImageOptimized(UploadedFile $file, string $type, array $options = []): string
    {
        return $this->put($file, $type, $options);
    }

    public function uploadVideoOptimized(UploadedFile $file, string $type, array $options = []): string
    {
        return $this->put($file, $type, $options);
    }

    public function storeTemporary(UploadedFile $file, string $directory = '', array $options = []): string
    {
        return $this->put($file, $options['type'] ?? 'file', $options);
    }

    public function deleteFile(?string $path): bool
    {
        return StorageDisk::delete($path, $this->disk);
    }

    public function getUrl(string $path): string
    {
        return Storage::disk($this->disk)->url($path);
    }
}
