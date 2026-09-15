<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

class ImportSpreadsheetValidator
{
    /**
     * @param  array<int, string>  $requiredHeadings
     */
    public static function assertReady(string $disk, string $relativePath, array $requiredHeadings): void
    {
        $relativePath = ltrim($relativePath, '/');

        if ($relativePath === '') {
            throw new InvalidArgumentException('Berkas import belum dipilih. Silakan unggah file terlebih dahulu.');
        }

        if (! Storage::disk($disk)->exists($relativePath)) {
            throw new InvalidArgumentException('Berkas tidak ditemukan di server. Silakan unggah ulang dan coba lagi.');
        }

        [$absolutePath, $cleanupPath] = StoragePathResolver::resolveForLocalAccess($disk, $relativePath);

        try {
            $reader = IOFactory::createReaderForFile($absolutePath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($absolutePath);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException(
                'File tidak dapat dibaca. Pastikan format .xlsx/.csv valid dan tidak sedang dibuka di Excel.',
                previous: $exception,
            );
        } finally {
            StoragePathResolver::cleanupTemporaryPath($cleanupPath);
        }

        $sheet = $spreadsheet->getSheet(0);
        $highestColumn = $sheet->getHighestDataColumn();
        $headingRow = $sheet->rangeToArray("A1:{$highestColumn}1", null, true, false)[0] ?? [];
        $normalizedHeadings = self::normalizeHeadings($headingRow);

        $missing = [];

        foreach ($requiredHeadings as $heading) {
            if (! in_array(self::normalizeHeading($heading), $normalizedHeadings, true)) {
                $missing[] = $heading;
            }
        }

        if ($missing !== []) {
            throw new InvalidArgumentException(
                'Kolom wajib tidak ditemukan: '.implode(', ', $missing).'. '
                .'Gunakan template resmi dan pastikan sheet pertama berisi data import (bukan sheet referensi).'
            );
        }

        if (! self::sheetHasDataRows($sheet)) {
            throw new InvalidArgumentException(
                'File tidak memiliki baris data. Isi minimal satu baris setelah header sebelum import.'
            );
        }
    }

    /**
     * @param  array<int, mixed>  $headings
     * @return array<int, string>
     */
    public static function normalizeHeadings(array $headings): array
    {
        $normalized = [];

        foreach ($headings as $heading) {
            $value = self::normalizeHeading((string) $heading);

            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        return $normalized;
    }

    public static function normalizeHeading(string $heading): string
    {
        return Str::slug(trim($heading), '_');
    }

    /**
     * @param  array<int, string|null>  $values
     */
    public static function rowHasMeaningfulValue(array $values): bool
    {
        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }

            $trimmed = trim((string) $value);

            if ($trimmed !== '' && $trimmed !== '-') {
                return true;
            }
        }

        return false;
    }

    private static function sheetHasDataRows(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): bool
    {
        $highestRow = (int) $sheet->getHighestDataRow();

        if ($highestRow <= 1) {
            return false;
        }

        $highestColumn = $sheet->getHighestDataColumn();

        for ($row = 2; $row <= $highestRow; $row++) {
            $values = $sheet->rangeToArray("A{$row}:{$highestColumn}{$row}", null, true, false)[0] ?? [];

            if (self::rowHasMeaningfulValue($values)) {
                return true;
            }
        }

        return false;
    }
}
