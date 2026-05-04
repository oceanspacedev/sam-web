<?php

namespace App\Services;

use App\Support\StorageDisk;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class FileUploadService
{
    protected string $defaultDisk = 'public';

    protected FilenameGeneratorService $filenameGenerator;

    public function __construct(
        protected ?string $disk = null,
        protected ?string $temporaryDisk = null,
        ?FilenameGeneratorService $filenameGenerator = null
    ) {
        // Resolve default disk from configuration so .env FILESYSTEM_DISK is respected.
        // Fallback to the class default ('public') when config is unavailable.
        $this->defaultDisk = StorageDisk::default();

        $this->disk = $disk ?? $this->defaultDisk;
        $this->temporaryDisk = $temporaryDisk ?? $this->defaultDisk;
        $this->filenameGenerator = $filenameGenerator ?? new FilenameGeneratorService;
    }

    /**
     * Resolve the filesystem adapter for the configured disk.
     */
    protected function storage(): FilesystemAdapter
    {
        return Storage::disk($this->disk);
    }

    /**
     * Resolve the filesystem adapter for the temporary disk.
     */
    protected function temporaryStorage(): FilesystemAdapter
    {
        return Storage::disk($this->temporaryDisk);
    }

    public function disk(): string
    {
        return (string) $this->disk;
    }

    public function temporaryDisk(): string
    {
        return (string) $this->temporaryDisk;
    }

    public function withDisk(string $disk): self
    {
        $clone = clone $this;
        $clone->disk = $disk;

        return $clone;
    }

    public function withTemporaryDisk(string $disk): self
    {
        $clone = clone $this;
        $clone->temporaryDisk = $disk;

        return $clone;
    }

    /**
     * Upload optimized image with flat storage (TRUE FLAT STORAGE)
     * Format: {type}{date}{userId}-{hash}-{uuid}.{ext}
     */
    public function uploadImageOptimized(UploadedFile $file, string $type, array $options = []): string
    {
        if (! $file->isValid()) {
            throw new RuntimeException('Invalid file upload');
        }

        $allowedMimes = $options['allowed_mimes'] ?? ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = $this->resolveExtension($file);

        if (! in_array(strtolower($ext), $allowedMimes)) {
            throw new RuntimeException("File type {$ext} not allowed. Allowed: ".implode(', ', $allowedMimes));
        }

        // Generate optimized filename with type prefix
        $typePrefix = FilenameGeneratorService::getTypePrefix($type);
        $filename = $this->filenameGenerator->generate($file, $type);

        $putOptions = [
            'visibility' => $options['visibility'] ?? 'public',
            'mimetype' => $file->getMimeType(),
        ];

        $directory = trim($options['directory'] ?? '', '/');

        return $this->storage()->putFileAs($directory, $file, $filename, $putOptions);
    }

    /**
     * Upload video with flat storage optimization
     */
    public function uploadVideoOptimized(UploadedFile $file, string $type, array $options = []): string
    {
        if (! $file->isValid()) {
            throw new RuntimeException('Invalid video file upload');
        }

        $allowedMimes = $options['allowed_mimes'] ?? ['mp4', 'mov', 'avi', 'mkv', 'webm'];
        $ext = $this->resolveExtension($file);

        if (! in_array(strtolower($ext), $allowedMimes)) {
            throw new RuntimeException("Video type {$ext} not allowed. Allowed: ".implode(', ', $allowedMimes));
        }

        // Generate optimized filename with type prefix
        $filename = $this->filenameGenerator->generate($file, $type);

        $putOptions = [
            'visibility' => $options['visibility'] ?? 'public',
            'mimetype' => $file->getMimeType(),
        ];

        $directory = trim($options['directory'] ?? '', '/');

        return $this->storage()->putFileAs($directory, $file, $filename, $putOptions);
    }

    /**
     * Upload file with custom type prefix (for backward compatibility)
     */
    public function uploadWithCustomType(UploadedFile $file, string $type, array $options = []): string
    {
        return $this->uploadImageOptimized($file, $type, $options);
    }

    /**
     * Legacy method - kept for backward compatibility where a directory/filename needs to be honored.
     * Uses optimized filename when no custom filename is provided.
     */
    public function uploadImage(UploadedFile $file, string $directory, array $options = []): string
    {
        if (! $file->isValid()) {
            throw new RuntimeException('Invalid image file upload');
        }

        $allowedMimes = $options['allowed_mimes'] ?? ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower($this->resolveExtension($file));

        if (! in_array($ext, $allowedMimes)) {
            throw new RuntimeException("File type {$ext} not allowed. Allowed: ".implode(', ', $allowedMimes));
        }

        $filename = $options['filename']
            ?? $this->filenameGenerator->generate($file, $options['type'] ?? 'photo');

        $targetDirectory = trim($directory, '/');

        return $this->storage()->putFileAs(
            $targetDirectory,
            $file,
            $filename,
            $this->buildPutOptions($file, $options)
        );
    }

    /**
     * Upload video file with validation
     */
    public function uploadVideo(UploadedFile $file, string $directory, array $options = []): string
    {
        if (! $file->isValid()) {
            throw new RuntimeException('Invalid video file upload');
        }

        $allowedMimes = $options['allowed_mimes'] ?? ['mp4', 'mov', 'avi', 'mkv', 'webm'];
        $ext = $this->resolveExtension($file);

        if (! in_array(strtolower($ext), $allowedMimes)) {
            throw new RuntimeException("Video type {$ext} not allowed. Allowed: ".implode(', ', $allowedMimes));
        }

        $filename = $options['filename'] ?? ((string) Str::uuid().'.'.$ext);

        $putOptions = [];
        if (isset($options['visibility'])) {
            $putOptions['visibility'] = $options['visibility'];
        }

        return $this->storage()->putFileAs(trim($directory, '/'), $file, $filename, $putOptions);
    }

    /**
     * Build put options for storage writes.
     */
    protected function buildPutOptions(UploadedFile $file, array $options): array
    {
        $putOptions = [];

        if (isset($options['visibility'])) {
            $putOptions['visibility'] = $options['visibility'];
        }

        if (! isset($putOptions['visibility'])) {
            $putOptions['visibility'] = 'public';
        }

        $putOptions['mimetype'] = $file->getMimeType();

        return $putOptions;
    }

    /**
     * Store file temporarily before deferred processing.
     */
    public function storeTemporary(UploadedFile $file, string $directory, array $options = []): string
    {
        if (! $file->isValid()) {
            throw new RuntimeException('Invalid temporary file upload');
        }

        $ext = $this->resolveExtension($file);
        $filename = $options['filename'] ?? ((string) Str::uuid().'.'.$ext);

        $putOptions = [];
        if (isset($options['visibility'])) {
            $putOptions['visibility'] = $options['visibility'];
        }

        return $this->temporaryStorage()->putFileAs(trim($directory, '/'), $file, $filename, $putOptions);
    }

    /**
     * Move a temporarily stored file to the configured disk using streaming I/O.
     */
    public function transferFromTemporary(string $temporaryPath, string $directory, array $options = []): string
    {
        $tempStorage = $this->temporaryStorage();

        if (! $tempStorage->exists($temporaryPath)) {
            throw new RuntimeException("Temporary file {$temporaryPath} does not exist");
        }

        $stream = $tempStorage->readStream($temporaryPath);

        if ($stream === false) {
            throw new RuntimeException("Unable to read temporary file {$temporaryPath}");
        }

        $filename = $options['filename'] ?? ($options['preserve_name'] ?? false
            ? basename($temporaryPath)
            : ((string) Str::uuid().'.'.(pathinfo($temporaryPath, PATHINFO_EXTENSION) ?: 'bin')));

        $targetDisk = $options['disk'] ?? $this->disk;
        $visibility = $options['visibility'] ?? null;

        $putOptions = [];
        if ($visibility) {
            $putOptions['visibility'] = $visibility;
        }

        $path = trim($directory, '/').'/'.$filename;

        $storage = Storage::disk($targetDisk);
        $storage->writeStream($path, $stream, $putOptions);

        if (is_resource($stream)) {
            fclose($stream);
        }

        $tempStorage->delete($temporaryPath);

        return $path;
    }

    /**
     * Delete file from storage
     */
    public function deleteFile(string $path): bool
    {
        if (empty($path)) {
            return false;
        }

        return StorageDisk::delete($path, $this->disk);
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
        if ($this->disk === StorageDisk::default()) {
            return StorageDisk::url($path);
        }

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

    /**
     * Resolve a safe extension for storage/validation.
     *
     * `guessExtension()` depends on server MIME detection, which can return null
     * on some environments. We fall back to the original client extension.
     */
    protected function resolveExtension(UploadedFile $file): string
    {
        $ext = $file->guessExtension();
        if (is_string($ext) && $ext !== '') {
            return $ext;
        }

        $clientExt = $file->getClientOriginalExtension();
        if (is_string($clientExt) && $clientExt !== '') {
            return $clientExt;
        }

        return 'bin';
    }
}
