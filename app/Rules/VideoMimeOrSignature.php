<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Accept common video uploads even when server-side MIME detection is flaky.
 *
 * Primary check uses `$file->getMimeType()` (server-detected via fileinfo).
 * Fallback check uses lightweight container signatures:
 * - MP4/MOV: ISO-BMFF `ftyp` atom near the start of the file
 * - WebM: EBML header + "webm" DocType in the header
 */
class VideoMimeOrSignature implements ValidationRule
{
    private const ISO_BMFF_VIDEO_BRANDS = [
        'isom', // ISO Base Media
        'iso2',
        'iso5',
        'iso6',
        'mp41',
        'mp42',
        'avc1', // H.264 in MP4
        'qt  ', // QuickTime
        '3gp4',
        '3gp5',
        '3gp6',
        'dash',
    ];

    /**
     * @param  array<int, string>  $allowedMimes
     */
    public function __construct(
        private readonly array $allowedMimes = [
            'video/mp4',
            'application/mp4',
            'video/quicktime',
            'video/webm',
        ],
        private readonly int $maxHeaderBytes = 65536,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail('File video tidak valid.');

            return;
        }

        $mime = $value->getMimeType();
        if (is_string($mime) && in_array($mime, $this->allowedMimes, true)) {
            return;
        }

        $path = $value->getRealPath() ?: $value->path();
        if (! is_string($path) || $path === '' || ! is_file($path)) {
            $fail('File video tidak valid.');

            return;
        }

        $signatureOk = $this->looksLikeWebm($path) || $this->looksLikeIsoBmffVideo($path);

        if (! $signatureOk) {
            $fail('Format video harus mp4, quicktime, atau webm.');
        }
    }

    private function looksLikeWebm(string $path): bool
    {
        $header = $this->readHeader($path);
        if ($header === null || strlen($header) < 4) {
            return false;
        }

        // EBML header magic: 1A 45 DF A3
        if (substr($header, 0, 4) !== "\x1A\x45\xDF\xA3") {
            return false;
        }

        // WebM doc type should appear early in the EBML header.
        return str_contains($header, 'webm');
    }

    private function looksLikeIsoBmffVideo(string $path): bool
    {
        $header = $this->readHeader($path);
        if ($header === null || strlen($header) < 16) {
            return false;
        }

        $len = strlen($header);
        $offset = 0;

        // Walk a few boxes to find the `ftyp` box.
        while ($offset + 8 <= $len) {
            $size32 = $this->readUint32(substr($header, $offset, 4));
            $type = substr($header, $offset + 4, 4);

            $headerSize = 8;
            $size = $size32;

            if ($size32 === 1) {
                if ($offset + 16 > $len) {
                    return false;
                }
                $size = $this->readUint64(substr($header, $offset + 8, 8));
                $headerSize = 16;
            } elseif ($size32 === 0) {
                // Box extends to EOF; can't safely scan further with a truncated header.
                return false;
            }

            if ($size < $headerSize) {
                return false;
            }

            if ($type === 'ftyp') {
                $payloadOffset = $offset + $headerSize;
                if ($payloadOffset + 8 > $len) {
                    return false;
                }

                $majorBrand = substr($header, $payloadOffset, 4);
                $boxEnd = min($offset + (int) $size, $len);
                $compatibleOffset = $payloadOffset + 8; // major(4) + minor(4)
                $compatible = $compatibleOffset < $boxEnd
                    ? substr($header, $compatibleOffset, $boxEnd - $compatibleOffset)
                    : '';

                foreach (self::ISO_BMFF_VIDEO_BRANDS as $brand) {
                    if ($majorBrand === $brand || ($compatible !== '' && str_contains($compatible, $brand))) {
                        return true;
                    }
                }

                return false;
            }

            $offset += (int) $size;
        }

        return false;
    }

    private function readHeader(string $path): ?string
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }

        try {
            return (string) fread($handle, $this->maxHeaderBytes);
        } finally {
            fclose($handle);
        }
    }

    private function readUint32(string $bytes): int
    {
        $unpacked = unpack('N', $bytes);

        return (int) ($unpacked[1] ?? 0);
    }

    private function readUint64(string $bytes): int
    {
        $unpacked = unpack('N2', $bytes);
        $high = (int) ($unpacked[1] ?? 0);
        $low = (int) ($unpacked[2] ?? 0);

        return ($high << 32) | $low;
    }
}
