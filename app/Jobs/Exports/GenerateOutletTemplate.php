<?php

namespace App\Jobs\Exports;

use App\Exports\TemplateOutletExport;
use App\Jobs\SendImportNotification;
use App\Support\StorageDisk;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class GenerateOutletTemplate implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $userId,
        public ?string $mode = null
    ) {}

    public function handle(): void
    {
        $mode = $this->resolveMode();
        $disk = StorageDisk::default();
        $timestamp = now()->format('YmdHis');
        $fileName = sprintf('outlet-%s-template-%s.xlsx', $mode, $timestamp);
        $path = 'exports/templates/'.$fileName;

        Excel::store(new TemplateOutletExport($this->mode), $path, $disk);

        SendImportNotification::dispatch(
            $this->userId,
            'Template Outlet Siap',
            'Template import '.Str::lower($mode).' outlet sudah siap diunduh.',
            true,
            $path
        );
    }

    protected function resolveMode(): string
    {
        return match ($this->mode) {
            'create', 'update' => $this->mode,
            default => 'all',
        };
    }
}
