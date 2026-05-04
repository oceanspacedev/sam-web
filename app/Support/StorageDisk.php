<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
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
            $archiveDisk = self::archiveFallbackDisk($disk, $relativePath);

            if ($archiveDisk !== null) {
                return self::archiveUrlFromDisk($archiveDisk, $relativePath);
            }

            return self::urlFromDisk($disk, $relativePath);
        } catch (Throwable) {
            $suffix = $relativePath !== '' ? '/'.$relativePath : '/';

            return asset('storage'.$suffix);
        }
    }

    protected static function archiveFallbackDisk(string $disk, string $relativePath): ?string
    {
        if ($relativePath === '' || ! (bool) config('filesystems.archive.enabled', false)) {
            return null;
        }

        $sourceDisk = (string) config('filesystems.archive.source_disk', 'public');
        $targetDisk = (string) config('filesystems.archive.target_disk', 's3');

        if ($disk !== $sourceDisk || $targetDisk === '' || $targetDisk === $sourceDisk) {
            return null;
        }

        try {
            if (Storage::disk($sourceDisk)->exists($relativePath)) {
                return null;
            }
        } catch (Throwable) {
            // If the source disk cannot be checked, still try the archive disk.
        }

        try {
            return Storage::disk($targetDisk)->exists($relativePath) ? $targetDisk : null;
        } catch (Throwable) {
            return null;
        }
    }

    protected static function urlFromDisk(string $disk, string $relativePath, ?bool $useTemporaryUrl = null): string
    {
        if ($relativePath !== '' && ($useTemporaryUrl ?? self::shouldUseTemporaryUrl($disk))) {
            try {
                return Storage::disk($disk)->temporaryUrl($relativePath, Carbon::now()->addMinutes(30));
            } catch (Throwable) {
                if (self::shouldUseSignedLocalRoute($disk)) {
                    return URL::temporarySignedRoute(
                        'storage.archive.show',
                        Carbon::now()->addMinutes(30),
                        [
                            'path' => $relativePath,
                            'disk' => $disk,
                        ],
                    );
                }
            }
        }

        return Storage::disk($disk)->url($relativePath);
    }

    protected static function archiveUrlFromDisk(string $disk, string $relativePath): string
    {
        return asset('storage/'.$relativePath);
    }

    protected static function shouldUseTemporaryUrl(string $disk): bool
    {
        $driver = config("filesystems.disks.{$disk}.driver");

        return in_array($driver, ['s3', 'local'], true);
    }

    protected static function shouldUseSignedLocalRoute(string $disk): bool
    {
        return config("filesystems.disks.{$disk}.driver") === 'local'
            && blank(config("filesystems.disks.{$disk}.url"));
    }

    public static function delete(?string $path, ?string $sourceDisk = null): bool
    {
        $relativePath = ltrim((string) ($path ?? ''), '/');

        if ($relativePath === '' || in_array($relativePath, ['-', '0'], true)) {
            return false;
        }

        $disks = array_values(array_unique(array_filter([
            $sourceDisk ?: self::default(),
            self::archiveDiskForSource($sourceDisk ?: self::default()),
        ])));

        $deleted = false;

        foreach ($disks as $disk) {
            try {
                $deleted = Storage::disk($disk)->delete($relativePath) || $deleted;
            } catch (Throwable) {
                //
            }
        }

        return $deleted;
    }

    protected static function archiveDiskForSource(string $sourceDisk): ?string
    {
        if (! (bool) config('filesystems.archive.enabled', false)) {
            return null;
        }

        $archiveSourceDisk = (string) config('filesystems.archive.source_disk', 'public');
        $archiveTargetDisk = (string) config('filesystems.archive.target_disk', 's3');

        if ($sourceDisk !== $archiveSourceDisk || $archiveTargetDisk === '' || $archiveTargetDisk === $archiveSourceDisk) {
            return null;
        }

        return $archiveTargetDisk;
    }
}
