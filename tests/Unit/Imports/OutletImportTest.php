<?php

namespace Tests\Unit\Imports;

use App\Imports\OutletImport;
use App\Jobs\SendImportNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OutletImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_after_import_dispatches_summary_notification_with_errors(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $import = new OutletImport('create', $user->id);

        Cache::put($import->getSummaryKey(), [
            'processed' => 3,
            'created' => 1,
            'updated' => 1,
            'skipped' => 1,
            'error_total' => 2,
            'errors' => [
                ['row' => 2, 'message' => 'Outlet tidak ditemukan, tidak dapat diproses pada mode UPDATE.', 'kode_outlet' => 'OUT001'],
                ['row' => 4, 'message' => 'Badan usaha TEST tidak ditemukan.', 'kode_outlet' => null],
            ],
        ], now()->addMinutes(10));

        $events = $import->registerEvents();
        $this->assertArrayHasKey('Maatwebsite\\Excel\\Events\\AfterImport', $events);

        $afterImport = $events['Maatwebsite\\Excel\\Events\\AfterImport'];
        $afterImport();

        $this->assertFalse(Cache::has($import->getSummaryKey()));

        Queue::assertPushed(SendImportNotification::class, function (SendImportNotification $job) use ($user): bool {
            $this->assertSame($user->id, $job->userId);
            $this->assertFalse($job->successful);
            $this->assertStringContainsString('Gagal: 2', $job->body);
            $this->assertStringContainsString('Baris 2 [OUT001]', $job->body);
            $this->assertStringContainsString('Baris 4', $job->body);

            return true;
        });
    }

    public function test_after_import_marks_empty_sheet_as_failure(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $import = new OutletImport('update', $user->id);

        Cache::put($import->getSummaryKey(), [
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'error_total' => 0,
            'errors' => [],
        ], now()->addMinutes(10));

        $events = $import->registerEvents();
        $afterImport = $events['Maatwebsite\\Excel\\Events\\AfterImport'];
        $afterImport();

        $this->assertFalse(Cache::has($import->getSummaryKey()));

        Queue::assertPushed(SendImportNotification::class, function (SendImportNotification $job) use ($user): bool {
            $this->assertSame($user->id, $job->userId);
            $this->assertFalse($job->successful);
            $this->assertStringContainsString('Tidak ada baris data yang diproses', $job->body);

            return true;
        });
    }
}
