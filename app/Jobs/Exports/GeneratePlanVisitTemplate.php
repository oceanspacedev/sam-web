<?php

namespace App\Jobs\Exports;

use App\Exports\PlanVisitTemplateExport;
use App\Jobs\SendImportNotification;
use App\Support\StorageDisk;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;

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
        if (! in_array($this->scheduleScope, ['daily', 'weekly'], true)) {
            $this->scheduleScope = 'daily';
        }
    }

    public function handle(): void
    {
        $disk = StorageDisk::default();
        $fileName = sprintf(
            'plan-visit-template-%s-%s.xlsx',
            $this->scheduleScope,
            now()->format('YmdHis')
        );
        $path = 'exports/templates/'.$fileName;

        Excel::store(new PlanVisitTemplateExport($this->scheduleScope), $path, $disk);

        SendImportNotification::dispatch(
            $this->userId,
            'Template Plan Visit '.strtoupper($this->scheduleScope).' Siap',
            'Template plan visit (scope: '.strtoupper($this->scheduleScope).') sudah siap diunduh.',
            true,
            $path
        );
    }
}
