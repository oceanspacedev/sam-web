<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ProcessRegisterMedia;
use App\Models\Outlet;
use App\Models\Register;
use App\Services\FileUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessRegisterMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_moves_temporary_files_and_updates_register()
    {
        Storage::fake('public');
        Storage::fake('local');

        $register = Register::factory()->create([
            'poto_depan' => 'register/photos/original.jpg',
        ]);

        $outlet = Outlet::factory()->create([
            'kode_outlet' => 'LEAD'.$register->id,
            'badanusaha_id' => $register->badanusaha_id,
            'divisi_id' => $register->divisi_id,
            'region_id' => $register->region_id,
            'cluster_id' => $register->cluster_id,
            'poto_depan' => 'register/photos/original.jpg',
        ]);

        Storage::disk('local')->put('register/tmp/photos/temp.jpg', 'temporary-contents');

        $job = new ProcessRegisterMedia(
            registerId: $register->id,
            mediaItems: [[
                'field' => 'poto_depan',
                'tmp_path' => 'register/tmp/photos/temp.jpg',
                'final_directory' => 'register/photos',
                'filename' => 'final.jpg',
                'visibility' => 'public',
            ]],
            finalDisk: 'public',
            temporaryDisk: 'local'
        );

        $job->handle(app(FileUploadService::class));

        $this->assertTrue(Storage::disk('public')->exists('register/photos/final.jpg'));
        $this->assertFalse(Storage::disk('local')->exists('register/tmp/photos/temp.jpg'));

        $this->assertEquals('register/photos/final.jpg', $register->fresh()->poto_depan);
        $this->assertEquals('register/photos/final.jpg', $outlet->fresh()->poto_depan);
    }
}
