<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;

class FilenameGeneratorService
{
    protected static ?string $cachedDateStamp = null;

    /**
     * Generate optimized filename for flat storage
     * Format: {type}{date}{userId}{hash}{uuid}.{ext}
     * Example: vi241204123-a1b2c3-550e8400-e29b-41d4-a716-446655440000.jpg
     */
    public function generate(UploadedFile $file, string $type, ?int $userId = null): string
    {
        $userId = $userId ?? Auth::id();
        $userId = $userId ?? 0;
        $date = $this->resolveDateStamp();
        [$hash, $uuid] = $this->generateIdentifiers();
        $ext = strtolower($file->getClientOriginalExtension());

        // Get type prefix instead of full type name
        $typePrefix = self::getTypePrefix($type);

        return "{$typePrefix}{$date}{$userId}-{$hash}-{$uuid}.{$ext}";
    }

    /**
     * Get type prefix for different file categories
     */
    public static function getTypePrefix(string $type): string
    {
        return [
            // Visit photos
            'visit-in' => 'vi',
            'visit-out' => 'vo',

            // Register files
            'register-photo' => 'rp',
            'register-video' => 'rv',
            'register-ktp' => 'rk',

            // Outlet files
            'outlet-photo' => 'op',
            'outlet-video' => 'ov',
            'outlet-ktp' => 'ok',

            // Generic types
            'photo' => 'ph',
            'video' => 'vd',
            'document' => 'doc',
            'file' => 'f',
        ][$type] ?? 'f';
    }

    /**
     * Generate short hash from file for uniqueness
     */
    /**
     * Parse filename to extract metadata
     * Example: vi241204123-a1b2c3-uuid.jpg -> ['type' => 'visit-in', 'date' => '241204', 'userId' => 123, 'hash' => 'a1b2c3', 'uuid' => '...', 'ext' => 'jpg']
     */
    public function parse(string $filename): array
    {
        if (! preg_match('/^([a-z]{2})(\d{6})(\d+)-([a-f0-9]{6})-([a-f0-9-]{36})\.([a-z0-9]+)$/', $filename, $matches)) {
            return [];
        }

        // Get the type mapping for reverse lookup
        $typeMap = [
            'vi' => 'visit-in',
            'vo' => 'visit-out',
            'rp' => 'register-photo',
            'rk' => 'register-ktp',
            'rv' => 'register-video',
            'op' => 'outlet-photo',
            'ok' => 'outlet-ktp',
            'ov' => 'outlet-video',
            'ph' => 'photo',
            'vd' => 'video',
            'doc' => 'document',
            'f' => 'file',
        ];

        $typePrefix = $matches[1];
        $type = $typeMap[$typePrefix] ?? 'unknown';

        return [
            'type' => $type,
            'type_prefix' => $typePrefix,
            'date' => $matches[2],
            'user_id' => (int) $matches[3],
            'hash' => $matches[4],
            'uuid' => $matches[5],
            'extension' => $matches[6],
            'original' => $filename,
        ];
    }

    /**
     * Validate filename format
     */
    public function isValid(string $filename): bool
    {
        return ! empty($this->parse($filename));
    }

    /**
     * Generate filename with custom data
     */
    public function generateWithCustomData(UploadedFile $file, array $data): string
    {
        $type = $data['type'] ?? 'file';
        $userId = $data['user_id'] ?? Auth::id();
        $date = $data['date'] ?? $this->resolveDateStamp();
        $customPrefix = $data['prefix'] ?? '';

        $typePrefix = self::getTypePrefix($type);
        [$hash, $uuid] = $this->generateIdentifiers();
        $ext = strtolower($file->getClientOriginalExtension());

        $prefix = $customPrefix ? $customPrefix.'-' : '';

        return "{$prefix}{$typePrefix}{$date}{$userId}-{$hash}-{$uuid}.{$ext}";
    }

    protected function resolveDateStamp(): string
    {
        $current = gmdate('ymd');

        if (self::$cachedDateStamp !== $current) {
            self::$cachedDateStamp = $current;
        }

        return self::$cachedDateStamp;
    }

    protected function generateIdentifiers(): array
    {
        $bytes = random_bytes(16);
        $hex = bin2hex($bytes);

        $hash = substr($hex, 0, 6);

        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));

        return [$hash, $uuid];
    }
}
