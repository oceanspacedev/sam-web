<?php

namespace App\Jobs;

use App\Support\StorageDisk;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DeleteStorageFilesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 60;

    /**
     * @param  array<int, string|null>  $paths
     */
    public function __construct(public array $paths, public string $disk)
    {
        $this->onQueue('media');
    }

    public function handle(): void
    {
        $paths = array_values(array_filter(
            $this->paths,
            fn ($path): bool => is_string($path) && $path !== '' && ! in_array($path, ['-', '0'], true)
        ));

        if ($paths === []) {
            return;
        }

        try {
            foreach ($paths as $path) {
                StorageDisk::delete($path, $this->disk);
            }
        } catch (\Throwable $e) {
            Log::warning('Gagal menghapus file storage lewat queue', [
                'disk' => $this->disk,
                'count' => count($paths),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
