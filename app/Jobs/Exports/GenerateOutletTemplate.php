<?php

namespace App\Jobs\Exports;

use App\Exports\Outlet\OutletTemplateExport;
use App\Jobs\SendImportNotification;
use App\Support\StorageDisk;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class GenerateOutletTemplate implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $userId,
        public ?string $mode = null
    ) {
        $this->onQueue('exports');
    }

    public function handle(): void
    {
        $this->prepareRuntime();

        $mode = $this->resolveMode();
        $disk = StorageDisk::default();
        $timestamp = now()->format('YmdHis');
        $fileName = sprintf('outlet-%s-template-%s.xlsx', $mode, $timestamp);
        $path = 'exports/templates/'.$fileName;

        Excel::store(new OutletTemplateExport($mode, $this->userId), $path, $disk);

        SendImportNotification::dispatchSync(
            $this->userId,
            'Template Outlet Siap',
            'Template import '.Str::lower($mode).' outlet sudah siap diunduh.',
            true,
            $path
        );
    }

    public function failed(?Throwable $exception): void
    {
        SendImportNotification::dispatchSync(
            $this->userId,
            'Template Outlet Gagal',
            'Template import '.Str::lower($this->resolveMode()).' outlet gagal dibuat. '.$this->failureReason($exception),
            false
        );
    }

    protected function resolveMode(): string
    {
        return match ($this->mode) {
            'create', 'update' => $this->mode,
            default => 'all',
        };
    }

    private function failureReason(?Throwable $exception): string
    {
        $message = trim((string) $exception?->getMessage());

        if ($message === '') {
            return 'Silakan coba lagi atau hubungi tim IT.';
        }

        return 'Penyebab: '.Str::limit(preg_replace('/\s+/', ' ', $message) ?: $message, 300);
    }

    private function prepareRuntime(): void
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(0);
    }
}
