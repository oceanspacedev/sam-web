<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileUploadService
{
    protected string $defaultDisk = 'public';

    public function __construct(protected ?string $disk = null)
    {
        $this->disk = $disk ?? $this->defaultDisk;
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

        return Storage::disk($this->disk)->putFileAs($directory, $file, $filename);
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

        return Storage::disk($this->disk)->putFileAs($directory, $file, $filename);
    }

    /**
     * Delete file from storage
     */
    public function deleteFile(string $path): bool
    {
        if (empty($path)) {
            return false;
        }

        return Storage::disk($this->disk)->delete($path);
    }

    /**
     * Check if file exists
     */
    public function fileExists(string $path): bool
    {
        return Storage::disk($this->disk)->exists($path);
    }

    /**
     * Get file URL
     */
    public function getUrl(string $path): string
    {
        return Storage::disk($this->disk)->url($path);
    }

    /**
     * Get file size in bytes
     */
    public function getFileSize(string $path): int
    {
        return Storage::disk($this->disk)->size($path);
    }

    /**
     * Move file to different location
     */
    public function moveFile(string $from, string $to): bool
    {
        return Storage::disk($this->disk)->move($from, $to);
    }
}
