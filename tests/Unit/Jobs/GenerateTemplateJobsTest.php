<?php

namespace Tests\Unit\Jobs;

use App\Jobs\Exports\GenerateOutletTemplate;
use App\Jobs\Exports\GeneratePlanVisitTemplate;
use App\Jobs\SendImportNotification;
use App\Support\StorageDisk;
use Carbon\Carbon;
use Illuminate\Support\Facades\Queue;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class GenerateTemplateJobsTest extends TestCase
{
    public function test_plan_visit_template_job_stores_file_and_dispatches_notification(): void
    {
        Excel::fake();
        Queue::fake();

        Carbon::setTestNow(Carbon::parse('2025-11-12 10:00:00'));

        $job = new GeneratePlanVisitTemplate(42);
        $job->handle();

        $expectedPath = 'exports/templates/plan-visit-template-20251112100000.xlsx';

        Excel::assertStored($expectedPath, StorageDisk::default());

        Queue::assertPushed(SendImportNotification::class, function (SendImportNotification $notification) use ($expectedPath): bool {
            return $notification->userId === 42
                && $notification->title === 'Template Plan Visit Siap'
                && $notification->downloadPath === $expectedPath;
        });

        Carbon::setTestNow();
    }

    public function test_outlet_template_job_stores_file_and_dispatches_notification(): void
    {
        Excel::fake();
        Queue::fake();

        Carbon::setTestNow(Carbon::parse('2025-11-12 11:15:00'));

        $job = new GenerateOutletTemplate(7, 'create');
        $job->handle();

        $expectedPath = 'exports/templates/outlet-create-template-20251112111500.xlsx';

        Excel::assertStored($expectedPath, StorageDisk::default());

        Queue::assertPushed(SendImportNotification::class, function (SendImportNotification $notification) use ($expectedPath): bool {
            return $notification->userId === 7
                && $notification->title === 'Template Outlet Siap'
                && $notification->downloadPath === $expectedPath
                && $notification->body === 'Template import create outlet sudah siap diunduh.';
        });

        Carbon::setTestNow();
    }
}
