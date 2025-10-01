<?php

namespace Tests\Unit;

use App\Services\FileUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileUploadServiceTest extends TestCase
{
    public function test_it_uses_public_disk_when_configured_as_default_for_image_upload(): void
    {
        config(['filesystems.default' => 'public']);
        Storage::fake('public');

        $service = new FileUploadService;
        $file = UploadedFile::fake()->image('avatar.jpg');

        $path = $service->uploadImage($file, 'images');

        $this->assertNotEmpty($path);
        $this->assertTrue(Storage::disk('public')->exists($path));
    }

    public function test_it_uses_s3_disk_when_configured_as_default_for_image_upload(): void
    {
        config(['filesystems.default' => 's3']);
        Storage::fake('s3');

        $service = new FileUploadService;
        $file = UploadedFile::fake()->image('photo.png');

        $path = $service->uploadImage($file, 'images');

        $this->assertNotEmpty($path);
        $this->assertTrue(Storage::disk('s3')->exists($path));
    }

    public function test_delete_and_exists_use_configured_disk(): void
    {
        config(['filesystems.default' => 'public']);
        Storage::fake('public');

        $service = new FileUploadService;
        Storage::disk('public')->put('tmp/test.txt', 'content');

        $this->assertTrue($service->fileExists('tmp/test.txt'));
        $this->assertTrue($service->deleteFile('tmp/test.txt'));
        $this->assertFalse(Storage::disk('public')->exists('tmp/test.txt'));
    }
}
