<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ArchiveOldStorageFiles extends Command
{
    protected $signature = 'storage:archive-old-files
        {--source-disk= : Disk source yang akan dilegakan}
        {--target-disk= : Disk tujuan arsip/backup}
        {--days= : Arsipkan file yang lebih tua dari jumlah hari ini}
        {--directory=* : Batasi scan ke directory tertentu}
        {--batch= : Maksimal file yang diproses dalam sekali jalan}
        {--dry-run : Tampilkan file yang akan diproses tanpa mengubah storage}
        {--keep-source : Copy ke backup tanpa menghapus file source}
        {--force : Upload ulang walaupun file target sudah ada dengan ukuran sama}';

    protected $description = 'Move old files from the primary storage disk to an archive disk.';

    public function handle(): int
    {
        $sourceDisk = (string) ($this->option('source-disk') ?: config('filesystems.archive.source_disk', 'public'));
        $targetDisk = (string) ($this->option('target-disk') ?: config('filesystems.archive.target_disk', 's3'));
        $days = (int) ($this->option('days') ?: config('filesystems.archive.older_than_days', 90));
        $batchSize = (int) ($this->option('batch') ?: config('filesystems.archive.batch_size', 500));
        $deleteSource = ! $this->option('keep-source') && (bool) config('filesystems.archive.delete_source', true);
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $directories = $this->directories();
        $excludePatterns = (array) config('filesystems.archive.exclude', []);

        if ($sourceDisk === '' || $targetDisk === '') {
            $this->error('Source disk dan target disk wajib diisi.');

            return self::FAILURE;
        }

        if ($sourceDisk === $targetDisk) {
            $this->error('Source disk dan target disk tidak boleh sama.');

            return self::FAILURE;
        }

        if ($days < 1) {
            $this->error('Opsi --days harus lebih besar dari 0.');

            return self::FAILURE;
        }

        $source = Storage::disk($sourceDisk);
        $target = Storage::disk($targetDisk);
        $threshold = Carbon::now()->subDays($days)->timestamp;

        $stats = [
            'scanned' => 0,
            'eligible' => 0,
            'processed' => 0,
            'uploaded' => 0,
            'already_archived' => 0,
            'deleted' => 0,
            'failed' => 0,
            'bytes_released' => 0,
        ];

        foreach ($this->candidateFiles($source, $directories, $excludePatterns) as $path) {
            $stats['scanned']++;

            try {
                if ($source->lastModified($path) > $threshold) {
                    continue;
                }

                $stats['eligible']++;
                $stats['processed']++;
                $size = $this->safeSize($source, $path);
                $alreadyArchived = ! $force && $this->targetHasSameSize($target, $path, $size);
                $readyToDelete = $alreadyArchived;

                if ($dryRun) {
                    $this->line(sprintf(
                        '[dry-run] %s -> %s%s',
                        $path,
                        $targetDisk,
                        $deleteSource ? ' (delete source after copy)' : ' (keep source)'
                    ));
                } elseif (! $alreadyArchived) {
                    $this->copyToTarget($source, $target, $path);
                    $stats['uploaded']++;
                    $readyToDelete = $this->targetHasSameSize($target, $path, $size);
                } else {
                    $stats['already_archived']++;
                }

                if ($deleteSource && ($dryRun || $readyToDelete)) {
                    if (! $dryRun) {
                        $source->delete($path);
                    }

                    $stats['deleted']++;
                    $stats['bytes_released'] += $size;
                } elseif ($deleteSource) {
                    throw new \RuntimeException('Target copy could not be verified, source file was kept.');
                }
            } catch (Throwable $exception) {
                $stats['failed']++;
                $this->warn(sprintf('Gagal memproses [%s]: %s', $path, $exception->getMessage()));
            }

            if ($batchSize > 0 && $stats['processed'] >= $batchSize) {
                break;
            }
        }

        $this->components->info(sprintf(
            'Storage archive selesai. Scanned: %d, eligible: %d, uploaded: %d, already archived: %d, deleted: %d, failed: %d, released: %s.',
            $stats['scanned'],
            $stats['eligible'],
            $stats['uploaded'],
            $stats['already_archived'],
            $stats['deleted'],
            $stats['failed'],
            $this->formatBytes($stats['bytes_released'])
        ));

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    protected function directories(): array
    {
        $directories = $this->option('directory');

        if (empty($directories)) {
            $directories = (array) config('filesystems.archive.directories', []);
        }

        $directories = array_map(
            fn (string $directory): string => trim($directory, '/'),
            array_filter($directories, fn ($directory): bool => filled($directory))
        );

        return array_values($directories ?: ['']);
    }

    /**
     * @param  array<int, string>  $directories
     * @param  array<int, string>  $excludePatterns
     * @return iterable<int, string>
     */
    protected function candidateFiles(FilesystemAdapter $source, array $directories, array $excludePatterns): iterable
    {
        $seen = [];

        foreach ($directories as $directory) {
            foreach ($source->allFiles($directory) as $path) {
                $path = ltrim((string) $path, '/');

                if (isset($seen[$path]) || $this->shouldSkip($path, $excludePatterns)) {
                    continue;
                }

                $seen[$path] = true;

                yield $path;
            }
        }
    }

    /**
     * @param  array<int, string>  $excludePatterns
     */
    protected function shouldSkip(string $path, array $excludePatterns): bool
    {
        $basename = basename($path);

        if (str_starts_with($basename, '.')) {
            return true;
        }

        foreach ($excludePatterns as $pattern) {
            $pattern = trim((string) $pattern);

            if ($pattern !== '' && (Str::is($pattern, $path) || Str::is($pattern, $basename))) {
                return true;
            }
        }

        return false;
    }

    protected function targetHasSameSize(FilesystemAdapter $target, string $path, int $sourceSize): bool
    {
        try {
            return $target->exists($path) && $target->size($path) === $sourceSize;
        } catch (Throwable) {
            return false;
        }
    }

    protected function copyToTarget(FilesystemAdapter $source, FilesystemAdapter $target, string $path): void
    {
        $stream = $source->readStream($path);

        if (! is_resource($stream)) {
            throw new \RuntimeException('Unable to read source stream.');
        }

        try {
            $result = $target->writeStream($path, $stream, $this->writeOptions());

            if ($result === false) {
                throw new \RuntimeException('Unable to write target stream.');
            }
        } finally {
            fclose($stream);
        }
    }

    /**
     * @return array<string, string>
     */
    protected function writeOptions(): array
    {
        $visibility = config('filesystems.archive.visibility');

        return filled($visibility) ? ['visibility' => (string) $visibility] : [];
    }

    protected function safeSize(FilesystemAdapter $source, string $path): int
    {
        try {
            return (int) $source->size($path);
        } catch (Throwable) {
            return 0;
        }
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return "{$bytes} B";
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = $bytes / 1024;

        foreach ($units as $unit) {
            if ($value < 1024) {
                return number_format($value, 2).' '.$unit;
            }

            $value /= 1024;
        }

        return number_format($value, 2).' PB';
    }
}
