<?php

namespace App\Jobs;

use App\Models\Outlet;
use App\Models\Register;
use App\Services\FileUploadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ProcessRegisterMedia implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<int, array<string, mixed>>  $mediaItems
     */
    public function __construct(
        protected int $registerId,
        protected array $mediaItems,
        protected string $finalDisk,
        protected string $temporaryDisk
    ) {}

    public function handle(FileUploadService $uploadService): void
    {
        $register = Register::query()->find($this->registerId);

        if (! $register) {
            $this->cleanupAll();

            return;
        }

        $service = $uploadService
            ->withDisk($this->finalDisk)
            ->withTemporaryDisk($this->temporaryDisk);

        $updates = [];

        foreach ($this->mediaItems as $item) {
            $tmpPath = Arr::get($item, 'tmp_path');
            $field = Arr::get($item, 'field');
            $directory = Arr::get($item, 'final_directory');

            if (! $tmpPath || ! $field || ! $directory) {
                $this->deleteTemporary($tmpPath);

                continue;
            }

            if (! Storage::disk($this->temporaryDisk)->exists($tmpPath)) {
                continue;
            }

            $extension = pathinfo($tmpPath, PATHINFO_EXTENSION) ?: Arr::get($item, 'extension', 'bin');
            $filename = Arr::get($item, 'filename', (string) Str::uuid().'.'.$extension);

            try {
                $path = $service->transferFromTemporary($tmpPath, $directory, [
                    'filename' => $filename,
                    'visibility' => Arr::get($item, 'visibility'),
                    'disk' => $this->finalDisk,
                ]);
            } catch (RuntimeException $exception) {
                $this->deleteTemporary($tmpPath);

                continue;
            }

            $updates[$field] = $path;
        }

        if ($updates !== []) {
            $register->forceFill($updates)->save();

            $outlet = Outlet::query()->where('register_id', $register->id)->first()
                ?? ($register->kode_outlet
                    ? Outlet::query()->where('kode_outlet', $register->kode_outlet)->first()
                    : null)
                ?? Outlet::query()->where('kode_outlet', 'LEAD'.$register->id)->first();

            if ($outlet) {
                $mediaFields = [
                    'poto_shop_sign',
                    'poto_depan',
                    'poto_kiri',
                    'poto_kanan',
                    'poto_ktp',
                    'video',
                ];

                $outletUpdates = array_intersect_key($updates, array_flip($mediaFields));

                if ($outletUpdates !== []) {
                    $outlet->forceFill($outletUpdates)->save();
                }
            }
        }
    }

    public function registerId(): int
    {
        return $this->registerId;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function mediaItems(): array
    {
        return $this->mediaItems;
    }

    protected function cleanupAll(): void
    {
        foreach ($this->mediaItems as $item) {
            $this->deleteTemporary(Arr::get($item, 'tmp_path'));
        }
    }

    protected function deleteTemporary(?string $path): void
    {
        if (! $path) {
            return;
        }

        Storage::disk($this->temporaryDisk)->delete($path);
    }
}
