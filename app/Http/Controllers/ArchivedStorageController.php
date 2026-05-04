<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Throwable;

class ArchivedStorageController extends Controller
{
    public function __invoke(Request $request, string $path)
    {
        $archiveEnabled = (bool) config('filesystems.archive.enabled', false);
        $signedRoute = $request->routeIs('storage.archive.show');

        abort_unless($archiveEnabled || $signedRoute, 404);

        $relativePath = ltrim($path, '/');

        abort_if($relativePath === '' || str_contains($relativePath, '..'), 404);

        $disk = $this->diskFor($request, $relativePath, $archiveEnabled, $signedRoute);
        $storage = Storage::disk($disk);

        abort_unless($storage->exists($relativePath), 404);

        $headers = [
            'Content-Type' => $this->mimeType($disk, $relativePath),
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_INLINE,
                basename($relativePath),
            ),
            'Cache-Control' => 'private, max-age=1800',
            'Accept-Ranges' => 'bytes',
        ];

        $size = $this->size($disk, $relativePath);
        $range = $size !== null ? $this->parseRange($request->header('Range'), $size) : null;

        if ($size !== null) {
            $headers['Content-Length'] = (string) $size;
        }

        if ($range === false) {
            return response('', 416, [
                'Content-Range' => "bytes */{$size}",
                'Accept-Ranges' => 'bytes',
            ]);
        }

        $stream = $storage->readStream($relativePath);

        if (! is_resource($stream)) {
            throw new RuntimeException("Unable to read archived file [{$relativePath}].");
        }

        if (is_array($range)) {
            [$start, $end] = $range;
            $length = $end - $start + 1;
            $headers['Content-Length'] = (string) $length;
            $headers['Content-Range'] = "bytes {$start}-{$end}/{$size}";

            return response()->stream(function () use ($stream, $start, $length): void {
                try {
                    $this->streamBytes($stream, $start, $length);
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }
            }, 206, $headers);
        }

        return response()->stream(function () use ($stream): void {
            try {
                fpassthru($stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }, 200, $headers);
    }

    /**
     * @return array{0: int, 1: int}|false|null
     */
    protected function parseRange(?string $rangeHeader, int $size): array|false|null
    {
        if ($rangeHeader === null || $rangeHeader === '') {
            return null;
        }

        if ($size < 1 || ! preg_match('/^bytes=(\d*)-(\d*)$/', trim($rangeHeader), $matches)) {
            return false;
        }

        $start = $matches[1];
        $end = $matches[2];

        if ($start === '' && $end === '') {
            return false;
        }

        if ($start === '') {
            $suffixLength = (int) $end;

            if ($suffixLength < 1) {
                return false;
            }

            $start = max(0, $size - $suffixLength);
            $end = $size - 1;

            return [$start, $end];
        }

        $start = (int) $start;
        $end = $end === '' ? $size - 1 : (int) $end;

        if ($start >= $size || $end < $start) {
            return false;
        }

        return [$start, min($end, $size - 1)];
    }

    protected function streamBytes($stream, int $start, int $length): void
    {
        $this->skipBytes($stream, $start);

        $remaining = $length;

        while ($remaining > 0 && ! feof($stream)) {
            $chunkSize = min(8192, $remaining);
            $chunk = fread($stream, $chunkSize);

            if ($chunk === false || $chunk === '') {
                break;
            }

            echo $chunk;
            $remaining -= strlen($chunk);
        }
    }

    protected function skipBytes($stream, int $bytes): void
    {
        if ($bytes <= 0) {
            return;
        }

        $meta = stream_get_meta_data($stream);

        if (($meta['seekable'] ?? false) && fseek($stream, $bytes) === 0) {
            return;
        }

        $remaining = $bytes;

        while ($remaining > 0 && ! feof($stream)) {
            $chunk = fread($stream, min(8192, $remaining));

            if ($chunk === false || $chunk === '') {
                break;
            }

            $remaining -= strlen($chunk);
        }
    }

    protected function diskFor(Request $request, string $path, bool $archiveEnabled, bool $signedRoute): string
    {
        $signedDisk = $request->query('disk');

        if ($signedRoute && is_string($signedDisk) && $signedDisk !== '') {
            return $signedDisk;
        }

        $sourceDisk = (string) config('filesystems.archive.source_disk', 'public');
        $targetDisk = (string) config('filesystems.archive.target_disk', 's3');

        try {
            if ($sourceDisk !== '' && Storage::disk($sourceDisk)->exists($path)) {
                return $sourceDisk;
            }
        } catch (Throwable) {
            //
        }

        return $archiveEnabled ? $targetDisk : $sourceDisk;
    }

    protected function mimeType(string $disk, string $path): string
    {
        try {
            return Storage::disk($disk)->mimeType($path) ?: 'application/octet-stream';
        } catch (Throwable) {
            return 'application/octet-stream';
        }
    }

    protected function size(string $disk, string $path): ?int
    {
        try {
            return Storage::disk($disk)->size($path);
        } catch (Throwable) {
            return null;
        }
    }
}
