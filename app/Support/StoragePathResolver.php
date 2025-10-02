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
     * @return array{0: string, 1: string|null} [$localPath, $temporaryPath]
     */
    public static function resolveForLocalAccess(string $disk, string $relativePath): array
    {
        $relativePath = ltrim($relativePath, '/');
        /** @var FilesystemAdapter $storage */
        $storage = Storage::disk($disk);

        try {
            return [$storage->path($relativePath), null];
        } catch (Throwable $exception) {
            $temporaryPath = tempnam(sys_get_temp_dir(), 'storage-');

            if ($temporaryPath === false) {
                throw new RuntimeException('Unable to create a temporary file for storage import handling.', 0, $exception);
            }

            $stream = $storage->readStream($relativePath);

            if (! is_resource($stream)) {
                throw new RuntimeException(
                    sprintf('Unable to read file [%s] from disk [%s].', $relativePath, $disk),
                    0,
                    $exception,
                );
            }

            $destination = fopen($temporaryPath, 'w+b');

            if (! is_resource($destination)) {
                fclose($stream);

                throw new RuntimeException('Unable to open temporary file for writing imported contents.', 0, $exception);
            }

            try {
                stream_copy_to_stream($stream, $destination);
            } finally {
                fclose($destination);
                fclose($stream);
            }

            return [$temporaryPath, $temporaryPath];
        }
    }

    public static function cleanupTemporaryPath(?string $path): void
    {
        if ($path && file_exists($path)) {
            @unlink($path);
        }
    }
}
