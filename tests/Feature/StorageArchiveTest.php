<?php

use App\Support\StorageDisk;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

function configureStorageArchive(array $overrides = []): void
{
    config(array_merge([
        'filesystems.default' => 'public',
        'filesystems.archive.enabled' => true,
        'filesystems.archive.source_disk' => 'public',
        'filesystems.archive.target_disk' => 's3',
        'filesystems.archive.older_than_days' => 90,
        'filesystems.archive.delete_source' => true,
        'filesystems.archive.directories' => [],
        'filesystems.archive.exclude' => ['.gitignore', 'apk/*', 'livewire-tmp/*'],
        'filesystems.archive.batch_size' => 500,
        'filesystems.archive.visibility' => 'private',
        'filesystems.archive.temporary_urls' => true,
    ], $overrides));
}

test('storage archive command moves files older than configured days', function (): void {
    Storage::fake('public');
    Storage::fake('s3');
    configureStorageArchive();

    Storage::disk('public')->put('documents/old-report.jpg', 'old-content');
    Storage::disk('public')->put('documents/recent-report.jpg', 'recent-content');

    touch(Storage::disk('public')->path('documents/old-report.jpg'), now()->subDays(100)->timestamp);
    touch(Storage::disk('public')->path('documents/recent-report.jpg'), now()->subDays(10)->timestamp);

    $this->artisan('storage:archive-old-files')
        ->assertSuccessful();

    Storage::disk('s3')->assertExists('documents/old-report.jpg');
    Storage::disk('public')->assertMissing('documents/old-report.jpg');

    Storage::disk('public')->assertExists('documents/recent-report.jpg');
    Storage::disk('s3')->assertMissing('documents/recent-report.jpg');
});

test('storage archive command skips locally required apk files by default', function (): void {
    Storage::fake('public');
    Storage::fake('s3');
    configureStorageArchive();

    Storage::disk('public')->put('apk/SAM.apk', 'apk-content');

    touch(Storage::disk('public')->path('apk/SAM.apk'), now()->subDays(100)->timestamp);

    $this->artisan('storage:archive-old-files')
        ->assertSuccessful();

    Storage::disk('public')->assertExists('apk/SAM.apk');
    Storage::disk('s3')->assertMissing('apk/SAM.apk');
});

test('storage archive command skips unreadable excluded local directories', function (): void {
    Storage::fake('s3');

    $root = storage_path('framework/testing/disks/archive-unreadable-source');
    $filesystem = new Filesystem;
    $filesystem->deleteDirectory($root);
    $filesystem->ensureDirectoryExists($root);

    config([
        'filesystems.disks.archive_unreadable_source' => [
            'driver' => 'local',
            'root' => $root,
            'throw' => false,
            'visibility' => 'public',
        ],
    ]);

    configureStorageArchive([
        'filesystems.archive.source_disk' => 'archive_unreadable_source',
        'filesystems.archive.target_disk' => 's3',
        'filesystems.archive.exclude' => ['livewire-tmp/*'],
    ]);

    Storage::disk('archive_unreadable_source')->put('documents/old-report.jpg', 'old-content');
    touch(Storage::disk('archive_unreadable_source')->path('documents/old-report.jpg'), now()->subDays(100)->timestamp);

    $unreadableDirectory = $root.'/private/livewire-tmp';
    $filesystem->ensureDirectoryExists($unreadableDirectory);
    chmod($unreadableDirectory, 0000);

    try {
        $this->artisan('storage:archive-old-files')
            ->assertSuccessful();

        Storage::disk('s3')->assertExists('documents/old-report.jpg');
        Storage::disk('archive_unreadable_source')->assertMissing('documents/old-report.jpg');
    } finally {
        chmod($unreadableDirectory, 0777);
        $filesystem->deleteDirectory($root);
    }
});

test('storage disk url stays consistent when archive disk has its own url', function (): void {
    Storage::fake('public');

    $root = storage_path('framework/testing/disks/archive-fallback');
    $filesystem = new Filesystem;
    $filesystem->deleteDirectory($root);
    $filesystem->ensureDirectoryExists($root);

    config([
        'filesystems.disks.archive_fallback' => [
            'driver' => 'local',
            'root' => $root,
            'url' => 'https://archive.example.com/files',
            'visibility' => 'public',
            'throw' => false,
        ],
    ]);

    configureStorageArchive([
        'filesystems.archive.target_disk' => 'archive_fallback',
        'filesystems.archive.temporary_urls' => false,
    ]);

    Storage::disk('archive_fallback')->put('documents/archived-report.jpg', 'archived-content');

    expect(StorageDisk::url('documents/archived-report.jpg'))
        ->toBe('http://localhost/storage/documents/archived-report.jpg');

    $filesystem->deleteDirectory($root);
});

test('local disk storage urls stay signed when archive is disabled', function (): void {
    $root = storage_path('framework/testing/disks/local-signed-storage');
    $filesystem = new Filesystem;
    $filesystem->deleteDirectory($root);
    $filesystem->ensureDirectoryExists($root);

    config([
        'filesystems.default' => 'local_signed_storage',
        'filesystems.disks.local_signed_storage' => [
            'driver' => 'local',
            'root' => $root,
            'throw' => false,
            'visibility' => 'private',
        ],
        'filesystems.archive.enabled' => false,
    ]);

    Storage::disk('local_signed_storage')->put('exports/report.xlsx', 'report-content');

    $url = StorageDisk::url('exports/report.xlsx');
    $path = parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY);

    expect($url)->toContain('/storage-archive/exports/report.xlsx')
        ->and($url)->toContain('signature=');

    $response = $this->get($path);

    $response->assertOk();
    expect($response->streamedContent())->toBe('report-content');

    $filesystem->deleteDirectory($root);
});

test('storage disk url can stream archived files when archive disk has no public url', function (): void {
    Storage::fake('public');

    $root = storage_path('framework/testing/disks/archive-private');
    $filesystem = new Filesystem;
    $filesystem->deleteDirectory($root);
    $filesystem->ensureDirectoryExists($root);

    config([
        'filesystems.disks.archive_private' => [
            'driver' => 'local',
            'root' => $root,
            'visibility' => 'private',
            'throw' => false,
        ],
    ]);

    configureStorageArchive([
        'filesystems.archive.target_disk' => 'archive_private',
    ]);

    Storage::disk('archive_private')->put('documents/archived-report.jpg', 'archived-content');

    $url = StorageDisk::url('documents/archived-report.jpg');
    $path = parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY);

    expect($url)->toBe('http://localhost/storage/documents/archived-report.jpg');

    $response = $this->get($path);

    $response->assertOk();
    expect($response->streamedContent())->toBe('archived-content');

    $filesystem->deleteDirectory($root);
});

test('legacy storage url streams archived file from archive disk', function (): void {
    Storage::fake('public');

    $root = storage_path('framework/testing/disks/archive-legacy-storage');
    $filesystem = new Filesystem;
    $filesystem->deleteDirectory($root);
    $filesystem->ensureDirectoryExists($root);

    config([
        'filesystems.disks.archive_legacy_storage' => [
            'driver' => 'local',
            'root' => $root,
            'visibility' => 'private',
            'throw' => false,
        ],
    ]);

    configureStorageArchive([
        'filesystems.archive.target_disk' => 'archive_legacy_storage',
    ]);

    Storage::disk('archive_legacy_storage')->put('documents/archived-report.jpg', 'archived-content');

    $response = $this->get('/storage/documents/archived-report.jpg');

    $response->assertOk();
    expect($response->streamedContent())->toBe('archived-content');

    $filesystem->deleteDirectory($root);
});

test('storage disk delete removes archived copy when local copy is gone', function (): void {
    Storage::fake('public');
    Storage::fake('s3');
    configureStorageArchive();

    Storage::disk('s3')->put('documents/archived-report.jpg', 'archived-content');

    expect(StorageDisk::delete('documents/archived-report.jpg'))->toBeTrue();

    Storage::disk('s3')->assertMissing('documents/archived-report.jpg');
});

test('legacy storage archive route is not throttled as expensive work', function (): void {
    $route = Route::getRoutes()->getByName('storage.archive.public');

    expect($route)->not->toBeNull()
        ->and($route->gatherMiddleware())->not->toContain('throttle:expensive');
});

test('legacy storage url supports byte range requests for archived files', function (): void {
    Storage::fake('public');

    $root = storage_path('framework/testing/disks/archive-range-storage');
    $filesystem = new Filesystem;
    $filesystem->deleteDirectory($root);
    $filesystem->ensureDirectoryExists($root);

    config([
        'filesystems.disks.archive_range_storage' => [
            'driver' => 'local',
            'root' => $root,
            'visibility' => 'private',
            'throw' => false,
        ],
    ]);

    configureStorageArchive([
        'filesystems.archive.target_disk' => 'archive_range_storage',
    ]);

    Storage::disk('archive_range_storage')->put('videos/archived-video.mp4', '0123456789');

    $response = $this->withHeaders(['Range' => 'bytes=2-5'])
        ->get('/storage/videos/archived-video.mp4');

    $response->assertStatus(206)
        ->assertHeader('Accept-Ranges', 'bytes')
        ->assertHeader('Content-Range', 'bytes 2-5/10')
        ->assertHeader('Content-Length', '4');

    expect($response->streamedContent())->toBe('2345');

    $filesystem->deleteDirectory($root);
});

test('legacy storage url rejects unsatisfiable byte range requests', function (): void {
    Storage::fake('public');

    $root = storage_path('framework/testing/disks/archive-invalid-range-storage');
    $filesystem = new Filesystem;
    $filesystem->deleteDirectory($root);
    $filesystem->ensureDirectoryExists($root);

    config([
        'filesystems.disks.archive_invalid_range_storage' => [
            'driver' => 'local',
            'root' => $root,
            'visibility' => 'private',
            'throw' => false,
        ],
    ]);

    configureStorageArchive([
        'filesystems.archive.target_disk' => 'archive_invalid_range_storage',
    ]);

    Storage::disk('archive_invalid_range_storage')->put('videos/archived-video.mp4', '0123456789');

    $this->withHeaders(['Range' => 'bytes=99-120'])
        ->get('/storage/videos/archived-video.mp4')
        ->assertStatus(416)
        ->assertHeader('Accept-Ranges', 'bytes')
        ->assertHeader('Content-Range', 'bytes */10');

    $filesystem->deleteDirectory($root);
});
