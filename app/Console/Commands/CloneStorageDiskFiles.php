<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\FileAttributes;
use League\Flysystem\StorageAttributes;
use Throwable;

class CloneStorageDiskFiles extends Command
{
    protected ?string $lastTargetVerificationFailure = null;

    protected $signature = 'storage:clone-disk
        {--source-disk=s3 : Disk source yang akan diclone}
        {--target-disk=nas_sftp : Disk target clone}
        {--directory=* : Batasi clone ke directory tertentu}
        {--batch=0 : Maksimal file yang dicopy/gagal, 0 berarti tanpa batas}
        {--dry-run : Tampilkan file yang akan dicopy tanpa mengubah storage}
        {--force : Upload ulang walaupun file target sudah ada dengan ukuran sama}
        {--verify-attempts= : Jumlah retry verifikasi ukuran target setelah copy}
        {--verify-sleep-ms= : Jeda ms antar verifikasi target}
        {--progress=500 : Tampilkan progress setiap N file discan, 0 untuk nonaktif}
        {--skip-target-index : Cek target satu per satu, tanpa index awal}
        {--stop-on-failure : Berhenti saat ada file gagal}';

    protected $description = 'Clone files from one filesystem disk to another without deleting the source files.';

    public function handle(): int
    {
        $sourceDisk = (string) $this->option('source-disk');
        $targetDisk = (string) $this->option('target-disk');
        $batchSize = max(0, (int) $this->option('batch'));
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $progressEvery = max(0, (int) $this->option('progress'));
        $useTargetIndex = ! $force && ! $this->option('skip-target-index');
        $stopOnFailure = (bool) $this->option('stop-on-failure');

        if ($sourceDisk === '' || $targetDisk === '') {
            $this->error('Source disk dan target disk wajib diisi.');

            return self::FAILURE;
        }

        if ($sourceDisk === $targetDisk) {
            $this->error('Source disk dan target disk tidak boleh sama.');

            return self::FAILURE;
        }

        $source = Storage::disk($sourceDisk);
        $target = Storage::disk($targetDisk);

        $stats = [
            'scanned' => 0,
            'copied' => 0,
            'skipped' => 0,
            'verified' => 0,
            'failed' => 0,
            'bytes_copied' => 0,
            'bytes_skipped' => 0,
        ];

        $directories = $this->directories();
        $targetIndex = $useTargetIndex ? $this->targetIndex($target, $directories, $progressEvery, 'existing target') : null;
        $copiedForVerification = [];

        foreach ($this->sourceFiles($source, $directories) as $file) {
            $path = $file['path'];
            $stats['scanned']++;

            try {
                $size = $file['size'] ?? $this->safeSize($source, $path);

                if (! $force && $this->targetAlreadyHasSameSize($target, $targetIndex, $path, $size)) {
                    $stats['skipped']++;
                    $stats['bytes_skipped'] += $size ?? 0;
                } elseif ($dryRun) {
                    $stats['copied']++;
                    $stats['bytes_copied'] += $size ?? 0;
                    $this->line("[dry-run] {$sourceDisk}:{$path} -> {$targetDisk}:{$path}");
                } else {
                    $this->copyToTarget($source, $target, $path);
                    $stats['copied']++;
                    $stats['bytes_copied'] += $size ?? 0;
                    $copiedForVerification[$path] = $size;
                }
            } catch (Throwable $exception) {
                $stats['failed']++;
                $this->warn(sprintf('Gagal clone [%s]: %s', $path, $exception->getMessage()));

                if ($stopOnFailure) {
                    break;
                }
            }

            if ($progressEvery > 0 && $stats['scanned'] % $progressEvery === 0) {
                $this->line(sprintf(
                    'Progress: scanned=%d copied=%d skipped=%d failed=%d copied_bytes=%s skipped_bytes=%s',
                    $stats['scanned'],
                    $stats['copied'],
                    $stats['skipped'],
                    $stats['failed'],
                    $this->formatBytes($stats['bytes_copied']),
                    $this->formatBytes($stats['bytes_skipped']),
                ));
            }

            if ($batchSize > 0 && ($stats['copied'] + $stats['failed']) >= $batchSize) {
                break;
            }
        }

        if (! $dryRun && $copiedForVerification !== []) {
            $verificationFailures = $this->verifyCopiedFiles(
                $target,
                $directories,
                $copiedForVerification,
                $progressEvery,
                $this->verificationAttempts(),
                $this->verificationSleepMs(),
            );
            $stats['verified'] = count($copiedForVerification) - $verificationFailures;
            $stats['failed'] += $verificationFailures;
        }

        $this->components->info(sprintf(
            'Storage clone selesai. Source: %s, target: %s, scanned: %d, copied: %d (%s), verified: %d, skipped: %d (%s), failed: %d.',
            $sourceDisk,
            $targetDisk,
            $stats['scanned'],
            $stats['copied'],
            $this->formatBytes($stats['bytes_copied']),
            $stats['verified'],
            $stats['skipped'],
            $this->formatBytes($stats['bytes_skipped']),
            $stats['failed'],
        ));

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return iterable<int, array{path: string, size: int|null}>
     */
    protected function sourceFiles(FilesystemAdapter $source, array $directories): iterable
    {
        foreach ($directories as $directory) {
            foreach ($source->getDriver()->listContents($directory, true) as $attributes) {
                if (! $attributes instanceof StorageAttributes || ! $attributes->isFile()) {
                    continue;
                }

                yield [
                    'path' => ltrim($attributes->path(), '/'),
                    'size' => $attributes instanceof FileAttributes ? $attributes->fileSize() : null,
                ];
            }
        }
    }

    /**
     * @return array<int, string>
     */
    protected function directories(): array
    {
        $directories = array_filter(
            (array) $this->option('directory'),
            fn ($directory): bool => filled($directory),
        );

        return array_values(array_map(
            fn (string $directory): string => trim($directory, '/'),
            $directories ?: [''],
        ));
    }

    /**
     * @param  array<int, string>  $directories
     * @return array<string, int|null>
     */
    protected function targetIndex(FilesystemAdapter $target, array $directories, int $progressEvery, string $label): array
    {
        $index = [];
        $scanned = 0;

        $this->line("Indexing {$label}...");

        foreach ($directories as $directory) {
            foreach ($target->getDriver()->listContents($directory, true) as $attributes) {
                if (! $attributes instanceof StorageAttributes || ! $attributes->isFile()) {
                    continue;
                }

                $scanned++;
                $index[ltrim($attributes->path(), '/')] = $attributes instanceof FileAttributes ? $attributes->fileSize() : null;

                if ($progressEvery > 0 && $scanned % $progressEvery === 0) {
                    $this->line("Indexed {$scanned} target files...");
                }
            }
        }

        $this->line("Target index selesai: {$scanned} files.");

        return $index;
    }

    /**
     * @param  array<string, int|null>|null  $targetIndex
     */
    protected function targetAlreadyHasSameSize(
        FilesystemAdapter $target,
        ?array $targetIndex,
        string $path,
        ?int $sourceSize,
    ): bool {
        if ($targetIndex !== null) {
            return $this->targetIndexHasSameSize($targetIndex, $path, $sourceSize);
        }

        return $this->targetHasSameSize($target, $path, $sourceSize);
    }

    /**
     * @param  array<string, int|null>  $targetIndex
     */
    protected function targetIndexHasSameSize(array $targetIndex, string $path, ?int $sourceSize): bool
    {
        if ($sourceSize === null || ! array_key_exists($path, $targetIndex)) {
            return false;
        }

        return $targetIndex[$path] === $sourceSize;
    }

    /**
     * @param  array<int, string>  $directories
     * @param  array<string, int|null>  $copiedFiles
     */
    protected function verifyCopiedFiles(
        FilesystemAdapter $target,
        array $directories,
        array $copiedFiles,
        int $progressEvery,
        int $attempts,
        int $sleepMs,
    ): int {
        $targetIndex = $this->targetIndex($target, $directories, $progressEvery, 'target verification');
        $failed = 0;

        foreach ($copiedFiles as $path => $sourceSize) {
            if ($this->targetIndexHasSameSize($targetIndex, $path, $sourceSize)) {
                continue;
            }

            if ($this->targetHasSameSize($target, $path, $sourceSize, $attempts, $sleepMs)) {
                continue;
            }

            $failed++;
            $sourceSizeLabel = (string) ($sourceSize ?? 'unknown');
            $targetSize = array_key_exists($path, $targetIndex) ? (string) ($targetIndex[$path] ?? 'unknown') : 'missing';
            $reason = $this->lastTargetVerificationFailure !== null ? " ({$this->lastTargetVerificationFailure})" : '';
            $this->warn("Verifikasi target gagal [{$path}]: source={$sourceSizeLabel}, target={$targetSize}{$reason}");
        }

        return $failed;
    }

    protected function targetHasSameSize(
        FilesystemAdapter $target,
        string $path,
        ?int $sourceSize,
        int $attempts = 1,
        int $sleepMs = 0,
        bool $allowExistsOnly = false,
    ): bool {
        $attempts = max(1, $attempts);
        $sleepMs = max(0, $sleepMs);
        $this->lastTargetVerificationFailure = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                if (! $target->exists($path)) {
                    $this->lastTargetVerificationFailure = "target missing after attempt {$attempt}/{$attempts}";
                } elseif ($sourceSize === null) {
                    if ($allowExistsOnly) {
                        return true;
                    }

                    $this->lastTargetVerificationFailure = "source size unavailable after attempt {$attempt}/{$attempts}";
                } else {
                    $targetSize = (int) $target->size($path);

                    if ($targetSize === $sourceSize) {
                        return true;
                    }

                    $this->lastTargetVerificationFailure = "size mismatch after attempt {$attempt}/{$attempts}: source={$sourceSize}, target={$targetSize}";
                }
            } catch (Throwable $exception) {
                $this->lastTargetVerificationFailure = sprintf(
                    '%s after attempt %d/%d: %s',
                    $exception::class,
                    $attempt,
                    $attempts,
                    $exception->getMessage(),
                );
            }

            if ($attempt < $attempts && $sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }

        return false;
    }

    protected function copyToTarget(FilesystemAdapter $source, FilesystemAdapter $target, string $path): void
    {
        $stream = $source->readStream($path);

        if (! is_resource($stream)) {
            throw new \RuntimeException('Unable to read source stream.');
        }

        try {
            if ($target->writeStream($path, $stream) === false) {
                throw new \RuntimeException('Unable to write target stream.');
            }
        } finally {
            fclose($stream);
        }
    }

    protected function safeSize(FilesystemAdapter $source, string $path): ?int
    {
        try {
            return (int) $source->size($path);
        } catch (Throwable) {
            return null;
        }
    }

    protected function verificationAttempts(): int
    {
        $option = $this->option('verify-attempts');

        return max(1, (int) (($option !== null && $option !== '') ? $option : config('filesystems.archive.verify_attempts', 5)));
    }

    protected function verificationSleepMs(): int
    {
        $option = $this->option('verify-sleep-ms');

        return max(0, (int) (($option !== null && $option !== '') ? $option : config('filesystems.archive.verify_sleep_ms', 500)));
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
