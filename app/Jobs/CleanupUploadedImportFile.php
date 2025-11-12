<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class CleanupUploadedImportFile implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $disk, public string $relativePath) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (empty($this->relativePath)) {
            return;
        }

        $storage = Storage::disk($this->disk);

        if ($storage->exists($this->relativePath)) {
            $storage->delete($this->relativePath);
        }
    }
}
