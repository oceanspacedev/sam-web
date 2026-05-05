<?php

namespace App\Jobs\Exports;

use App\Exports\PlanVisit\PlanVisitTemplateExport;
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

class GeneratePlanVisitTemplate implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $userId,
        public string $scheduleScope = 'daily'
    ) {
        $this->onQueue('exports');

        if (! in_array($this->scheduleScope, ['daily', 'weekly'], true)) {
            $this->scheduleScope = 'daily';
        }
    }

    public function handle(): void
    {
        $this->prepareRuntime();

        $disk = StorageDisk::default();
        $fileName = sprintf(
            'plan-visit-template-%s-%s.xlsx',
            $this->scheduleScope,
            now()->format('YmdHis')
        );
        $path = 'exports/templates/'.$fileName;

        Excel::store(new PlanVisitTemplateExport($this->scheduleScope), $path, $disk);

        SendImportNotification::dispatchSync(
            $this->userId,
            'Template Plan Visit '.strtoupper($this->scheduleScope).' Siap',
            'Template plan visit (scope: '.strtoupper($this->scheduleScope).') sudah siap diunduh.',
            true,
            $path
        );
    }

    public function failed(?Throwable $exception): void
    {
        SendImportNotification::dispatchSync(
            $this->userId,
            'Template Plan Visit '.strtoupper($this->scheduleScope).' Gagal',
            'Template plan visit (scope: '.strtoupper($this->scheduleScope).') gagal dibuat. '.$this->failureReason($exception),
            false
        );
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
