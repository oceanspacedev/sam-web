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
        public int $userId
    ) {}

    public function handle(): void
    {
        $disk = StorageDisk::default();
        $fileName = 'plan-visit-template-'.now()->format('YmdHis').'.xlsx';
        $path = 'exports/templates/'.$fileName;

        Excel::store(new PlanVisitTemplateExport, $path, $disk);

        SendImportNotification::dispatch(
            $this->userId,
            'Template Plan Visit Siap',
            'Template plan visit sudah siap diunduh.',
            true,
            $path
        );
    }
}
