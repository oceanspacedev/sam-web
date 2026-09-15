<?php

namespace App\Support;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class StoragePathResolver
{
    /**
     * Resolve a storage path into a locally accessible file path.
     *
     * Local disks can return their real filesystem path. Remote disks such as S3
     * do not, so the object is streamed into a temporary file that the caller
     * must clean up via cleanupTemporaryPath().
     *
     * @return array{0: string, 1: string|null} [$localPath, $temporaryPath]
     */
    public static function resolveForLocalAccess(string $disk, string $relativePath): array
    {
        $relativePath = ltrim($relativePath, '/');
        /** @var FilesystemAdapter $storage */
        $storage = Storage::disk($disk);

        try {
            $localPath = $storage->path($relativePath);

            if (is_string($localPath) && is_file($localPath)) {
                return [$localPath, null];
            }
        } catch (Throwable) {
            // Remote disks may not expose a real filesystem path.
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'storage-');

        if ($temporaryPath === false) {
            throw new RuntimeException('Unable to create a temporary file for storage import handling.');
        }

        $stream = $storage->readStream($relativePath);

        if (! is_resource($stream)) {
            @unlink($temporaryPath);

            throw new RuntimeException(
                sprintf('Unable to read file [%s] from disk [%s].', $relativePath, $disk),
            );
        }

        $destination = fopen($temporaryPath, 'w+b');

        if (! is_resource($destination)) {
            fclose($stream);
            @unlink($temporaryPath);

            throw new RuntimeException('Unable to open temporary file for writing imported contents.');
        }

        try {
            stream_copy_to_stream($stream, $destination);
        } finally {
            fclose($destination);
            fclose($stream);
        }

        return [$temporaryPath, $temporaryPath];
    }

    public static function cleanupTemporaryPath(?string $path): void
    {
        if ($path && is_file($path)) {
            @unlink($path);
        }
    }
}
