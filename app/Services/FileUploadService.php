<?php

namespace App\Services;

use App\Support\StorageDisk;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileUploadService
{
    protected string $defaultDisk = 'public';

    public function __construct(protected ?string $disk = null)
    {
        // Resolve default disk from configuration so .env FILESYSTEM_DISK is respected.
        // Fallback to the class default ('public') when config is unavailable.
        $this->defaultDisk = StorageDisk::default();

        $this->disk = $disk ?? $this->defaultDisk;
    }

    /**
     * Resolve the filesystem adapter for the configured disk.
     */
    protected function storage(): FilesystemAdapter
    {
        return Storage::disk($this->disk);
    }

    /**
     * Upload image file with validation and optimization
     */
    public function uploadImage(UploadedFile $file, string $directory, array $options = []): string
    {
        if (! $file->isValid()) {
            throw new \RuntimeException('Invalid file upload');
        }

        $allowedMimes = $options['allowed_mimes'] ?? ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = $file->guessExtension() ?: $file->extension();

        if (! in_array(strtolower($ext), $allowedMimes)) {
            throw new \RuntimeException("File type {$ext} not allowed. Allowed: ".implode(', ', $allowedMimes));
        }

        $filename = $options['filename'] ?? ((string) Str::uuid().'.'.$ext);

        $putOptions = [];
        if (isset($options['visibility'])) {
            $putOptions['visibility'] = $options['visibility'];
        }

        return $this->storage()->putFileAs($directory, $file, $filename, $putOptions);
    }

    /**
     * Upload video file with validation
     */
    public function uploadVideo(UploadedFile $file, string $directory, array $options = []): string
    {
        if (! $file->isValid()) {
            throw new \RuntimeException('Invalid video file upload');
        }

        $allowedMimes = $options['allowed_mimes'] ?? ['mp4', 'mov', 'avi', 'mkv', 'webm'];
        $ext = $file->guessExtension() ?: $file->extension();

        if (! in_array(strtolower($ext), $allowedMimes)) {
            throw new \RuntimeException("Video type {$ext} not allowed. Allowed: ".implode(', ', $allowedMimes));
        }

        $filename = $options['filename'] ?? ((string) Str::uuid().'.'.$ext);

        $putOptions = [];
        if (isset($options['visibility'])) {
            $putOptions['visibility'] = $options['visibility'];
        }

        return $this->storage()->putFileAs($directory, $file, $filename, $putOptions);
    }

    /**
     * Delete file from storage
     */
    public function deleteFile(string $path): bool
    {
        if (empty($path)) {
            return false;
        }

        return $this->storage()->delete($path);
    }

    /**
     * Check if file exists
     */
    public function fileExists(string $path): bool
    {
        return $this->storage()->exists($path);
    }

    /**
     * Get file URL
     */
    public function getUrl(string $path): string
    {
        return $this->storage()->url($path);
    }

    /**
     * Get file size in bytes
     */
    public function getFileSize(string $path): int
    {
        return $this->storage()->size($path);
    }

    /**
     * Move file to different location
     */
    public function moveFile(string $from, string $to): bool
    {
        return $this->storage()->move($from, $to);
    }
}
