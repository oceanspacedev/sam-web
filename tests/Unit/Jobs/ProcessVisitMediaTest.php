<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ProcessVisitMedia;
use App\Models\Visit;
use App\Services\FileUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessVisitMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_moves_temporary_files_and_updates_visit(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $visit = Visit::factory()->create([
            'picture_visit_in' => 'visits/in/original.jpg',
        ]);

        Storage::disk('local')->put('visits/tmp/out/temp.jpg', 'temporary-contents');

        $job = new ProcessVisitMedia(
            visitId: $visit->id,
            mediaItems: [[
                'field' => 'picture_visit_out',
                'tmp_path' => 'visits/tmp/out/temp.jpg',
                'final_directory' => 'visits/out',
                'filename' => 'final.jpg',
                'visibility' => 'public',
            ]],
            finalDisk: 'public',
            temporaryDisk: 'local'
        );

        $job->handle(app(FileUploadService::class));

        $this->assertTrue(Storage::disk('public')->exists('visits/out/final.jpg'));
        $this->assertFalse(Storage::disk('local')->exists('visits/tmp/out/temp.jpg'));

        $this->assertEquals('visits/out/final.jpg', $visit->fresh()->picture_visit_out);
    }
}
