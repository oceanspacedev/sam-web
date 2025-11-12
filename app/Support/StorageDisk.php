<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Throwable;

class StorageDisk
{
    public static function default(): string
    {
        return (string) config('filesystems.default', 'public');
    }

    public static function url(?string $path): string
    {
        $relativePath = ltrim((string) ($path ?? ''), '/');
        $disk = self::default();

        try {
            if ($relativePath !== '' && self::shouldUseTemporaryUrl($disk)) {
                return Storage::disk($disk)->temporaryUrl($relativePath, Carbon::now()->addMinutes(30));
            }

            return Storage::disk($disk)->url($relativePath);
        } catch (Throwable) {
            $suffix = $relativePath !== '' ? '/'.$relativePath : '/';

            return asset('storage'.$suffix);
        }
    }

    protected static function shouldUseTemporaryUrl(string $disk): bool
    {
        $driver = config("filesystems.disks.{$disk}.driver");

        return in_array($driver, ['s3'], true);
    }
}
