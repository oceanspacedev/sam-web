<?php

namespace App\Traits;

use App\Support\StorageDisk;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

use function Illuminate\Events\queueable;

trait CleansUpMedia
{
    protected static function bootCleansUpMedia(): void
    {
        static::updating(queueable(function (Model $model): void {
            $fields = $model->mediaCleanupFields();

            if (empty($fields)) {
                return;
            }

            $deletedFiles = collect($fields)
                ->filter(fn (string $field): bool => $model->isDirty($field) && filled($model->getOriginal($field)))
                ->map(fn (string $field): string => (string) $model->getOriginal($field))
                ->filter()
                ->values();

            if ($deletedFiles->isEmpty()) {
                return;
            }

            self::deleteFiles($deletedFiles);
        }));
    }

    protected function mediaCleanupFields(): array
    {
        return property_exists($this, 'mediaCleanupFields')
            ? (array) $this->mediaCleanupFields
            : [];
    }

    protected static function deleteFiles(Collection $files): void
    {
        $disk = StorageDisk::default();
        /** @var FilesystemAdapter $storage */
        $storage = Storage::disk($disk);

        $files
            ->filter(fn (string $path): bool => $storage->exists($path))
            ->each(function (string $path) use ($storage): void {
                $storage->delete($path);
            });
    }
}
