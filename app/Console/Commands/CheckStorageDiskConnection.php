<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class CheckStorageDiskConnection extends Command
{
    protected $signature = 'storage:check-disk
        {disk=nas_sftp : Disk filesystem yang akan dicek}
        {--path= : Path probe file untuk opsi --write}
        {--write : Tulis lalu hapus file probe kecil}';

    protected $description = 'Check whether a configured filesystem disk can be reached.';

    public function handle(): int
    {
        $disk = (string) $this->argument('disk');
        $config = config("filesystems.disks.{$disk}");

        if (! is_array($config)) {
            $this->error("Disk [{$disk}] tidak ditemukan di config/filesystems.php.");

            return self::FAILURE;
        }

        $config['throw'] = true;

        $this->line(sprintf(
            'Checking disk [%s] driver=%s host=%s root=%s',
            $disk,
            $config['driver'] ?? '-',
            $config['host'] ?? '-',
            $config['root'] ?? '/',
        ));

        $probePath = null;
        $storage = null;

        try {
            $storage = Storage::build($config);
            $storage->files('');

            if ($this->option('write')) {
                $requestedProbePath = ltrim((string) ($this->option('path') ?: '.sam-storage-probe-'.Str::uuid().'.txt'), '/');
                $content = 'sam storage probe '.now()->toIso8601String().PHP_EOL;

                if ($storage->exists($requestedProbePath)) {
                    throw new \RuntimeException("Probe path [{$requestedProbePath}] sudah ada. Gunakan path unik agar tidak menimpa data.");
                }

                $probePath = $requestedProbePath;
                $storage->put($probePath, $content);

                if (! $storage->exists($probePath)) {
                    throw new \RuntimeException("Probe file [{$probePath}] tidak ditemukan setelah ditulis.");
                }

                $size = $storage->size($probePath);

                if (! $storage->delete($probePath)) {
                    throw new \RuntimeException("Probe file [{$probePath}] gagal dihapus.");
                }

                $probePath = null;

                $this->info("Disk [{$disk}] OK. Probe write/delete berhasil, size={$size} bytes.");
            } else {
                $this->info("Disk [{$disk}] OK. List root berhasil.");
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            if ($storage !== null && $probePath !== null) {
                try {
                    $storage->delete($probePath);
                } catch (Throwable) {
                    //
                }
            }

            $this->error(sprintf(
                'Disk [%s] gagal dicek: %s: %s',
                $disk,
                $exception::class,
                $exception->getMessage(),
            ));

            return self::FAILURE;
        }
    }
}
