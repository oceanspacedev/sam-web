<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Throwable;

class StorageDisk
{
    public static function default(): string
    {
        return (string) config('filesystems.default', 'local');
    }

    public static function url(?string $path): ?string
    {
        $relativePath = ltrim((string) ($path ?? ''), '/');

        if ($relativePath === '' || in_array($relativePath, ['-', '0'], true)) {
            return null;
        }

        $disk = Storage::disk(self::default());

        try {
            if (method_exists($disk, 'providesTemporaryUrls') && $disk->providesTemporaryUrls()) {
                return $disk->temporaryUrl($relativePath, now()->addDay());
            }
        } catch (Throwable) {
            // Fall back to a plain disk URL when the driver cannot sign.
        }

        return $disk->url($relativePath);
    }

    public static function delete(?string $path, ?string $disk = null): bool
    {
        $relativePath = ltrim((string) ($path ?? ''), '/');

        if ($relativePath === '' || in_array($relativePath, ['-', '0'], true)) {
            return false;
        }

        return Storage::disk($disk ?? self::default())->delete($relativePath);
    }
}
