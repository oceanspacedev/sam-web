<?php

namespace Tests\Unit\Services;

use App\Services\FileUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileUploadServiceTest extends TestCase
{
    use RefreshDatabase;

    protected FileUploadService $fileService;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->fileService = new FileUploadService('public');
    }

    public function test_upload_image_successfully(): void
    {
        $file = UploadedFile::fake()->image('test.jpg', 1920, 1080);

        $path = $this->fileService->uploadImage($file, 'test/images');

        $this->assertNotEmpty($path);
        $this->assertTrue(str_contains($path, 'test/images'));
        $this->assertTrue(str_contains($path, '.jpg'));
        Storage::disk('public')->assertExists($path);
    }

    public function test_upload_video_successfully(): void
    {
        $file = UploadedFile::fake()->create('video.mp4', 5000, 'video/mp4');

        $path = $this->fileService->uploadVideo($file, 'test/videos');

        $this->assertNotEmpty($path);
        $this->assertTrue(str_contains($path, 'test/videos'));
        $this->assertTrue(str_contains($path, '.mp4'));
        Storage::disk('public')->assertExists($path);
    }

    public function test_upload_image_with_custom_filename(): void
    {
        $file = UploadedFile::fake()->image('test.jpg');

        $path = $this->fileService->uploadImage($file, 'test/images', [
            'filename' => 'custom-name.jpg',
        ]);

        $this->assertStringContainsString('custom-name.jpg', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_upload_image_rejects_invalid_mime_type(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('File type');

        $this->fileService->uploadImage($file, 'test/images');
    }

    public function test_delete_file_successfully(): void
    {
        $file = UploadedFile::fake()->image('test.jpg');
        $path = $this->fileService->uploadImage($file, 'test/images');

        Storage::disk('public')->assertExists($path);

        $result = $this->fileService->deleteFile($path);

        $this->assertTrue($result);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_file_exists(): void
    {
        $file = UploadedFile::fake()->image('test.jpg');
        $path = $this->fileService->uploadImage($file, 'test/images');

        $this->assertTrue($this->fileService->fileExists($path));
        $this->assertFalse($this->fileService->fileExists('nonexistent/file.jpg'));
    }

    public function test_get_file_size_returns_correct_size(): void
    {
        $file = UploadedFile::fake()->image('test.jpg')->size(100); // 100KB

        $path = $this->fileService->uploadImage($file, 'test/images');

        $size = $this->fileService->getFileSize($path);

        $this->assertGreaterThan(0, $size);
    }

    public function test_move_file_successfully(): void
    {
        $file = UploadedFile::fake()->image('test.jpg');
        $originalPath = $this->fileService->uploadImage($file, 'test/images');

        $newPath = 'test/moved/test.jpg';
        $result = $this->fileService->moveFile($originalPath, $newPath);

        $this->assertTrue($result);
        Storage::disk('public')->assertMissing($originalPath);
        Storage::disk('public')->assertExists($newPath);
    }
}
