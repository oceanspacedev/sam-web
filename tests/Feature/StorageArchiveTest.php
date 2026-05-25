<?php

use App\Support\StorageDisk;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

function configureStorageArchive(array $overrides = []): void
{
    $targetDisk = $overrides['filesystems.archive.target_disk'] ?? 'nas_sftp';
    $readFallback = $overrides['filesystems.archive.read_fallback'] ?? [
        'nas' => [
            'enabled' => true,
            'disk' => $targetDisk,
        ],
        's3' => [
            'enabled' => false,
            'disk' => 's3',
        ],
    ];

    config(array_merge([
        'filesystems.default' => 'public',
        'filesystems.archive.enabled' => true,
        'filesystems.archive.source_disk' => 'public',
        'filesystems.archive.target_disk' => $targetDisk,
        'filesystems.archive.read_fallback' => $readFallback,
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
    Storage::fake('nas_sftp');
    configureStorageArchive();

    Storage::disk('public')->put('documents/old-report.jpg', 'old-content');
    Storage::disk('public')->put('documents/recent-report.jpg', 'recent-content');

    touch(Storage::disk('public')->path('documents/old-report.jpg'), now()->subDays(100)->timestamp);
    touch(Storage::disk('public')->path('documents/recent-report.jpg'), now()->subDays(10)->timestamp);

    $this->artisan('storage:archive-old-files')
        ->assertSuccessful();

    Storage::disk('nas_sftp')->assertExists('documents/old-report.jpg');
    Storage::disk('public')->assertMissing('documents/old-report.jpg');

    Storage::disk('public')->assertExists('documents/recent-report.jpg');
    Storage::disk('nas_sftp')->assertMissing('documents/recent-report.jpg');
});

test('storage archive command skips locally required apk files by default', function (): void {
    Storage::fake('public');
    Storage::fake('nas_sftp');
    configureStorageArchive();

    Storage::disk('public')->put('apk/SAM.apk', 'apk-content');

    touch(Storage::disk('public')->path('apk/SAM.apk'), now()->subDays(100)->timestamp);

    $this->artisan('storage:archive-old-files')
        ->assertSuccessful();

    Storage::disk('public')->assertExists('apk/SAM.apk');
    Storage::disk('nas_sftp')->assertMissing('apk/SAM.apk');
});

test('storage archive command skips unreadable excluded local directories', function (): void {
    Storage::fake('nas_sftp');

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
        'filesystems.archive.target_disk' => 'nas_sftp',
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

        Storage::disk('nas_sftp')->assertExists('documents/old-report.jpg');
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

test('legacy storage url falls back to archive disk even when archive job is disabled', function (): void {
    Storage::fake('public');

    $root = storage_path('framework/testing/disks/archive-read-fallback-storage');
    $filesystem = new Filesystem;
    $filesystem->deleteDirectory($root);
    $filesystem->ensureDirectoryExists($root);

    config([
        'filesystems.disks.archive_read_fallback_storage' => [
            'driver' => 'local',
            'root' => $root,
            'visibility' => 'private',
            'throw' => false,
        ],
    ]);

    configureStorageArchive([
        'filesystems.archive.enabled' => false,
        'filesystems.archive.target_disk' => 'archive_read_fallback_storage',
    ]);

    Storage::disk('archive_read_fallback_storage')->put('documents/nas-only-report.jpg', 'nas-only-content');

    $response = $this->get('/storage/documents/nas-only-report.jpg');

    $response->assertOk();
    expect($response->streamedContent())->toBe('nas-only-content');

    $filesystem->deleteDirectory($root);
});

test('legacy storage url ignores s3 when only nas read fallback is enabled', function (): void {
    Storage::fake('public');
    Storage::fake('nas_sftp');
    Storage::fake('s3');

    configureStorageArchive([
        'filesystems.archive.enabled' => false,
        'filesystems.archive.read_fallback' => [
            'nas' => [
                'enabled' => true,
                'disk' => 'nas_sftp',
            ],
            's3' => [
                'enabled' => false,
                'disk' => 's3',
            ],
        ],
    ]);

    Storage::disk('s3')->put('documents/s3-only-report.jpg', 's3-only-content');

    $this->get('/storage/documents/s3-only-report.jpg')
        ->assertNotFound();

    Storage::disk('nas_sftp')->put('documents/nas-only-report.jpg', 'nas-only-content');

    $response = $this->get('/storage/documents/nas-only-report.jpg');

    $response->assertOk();
    expect($response->streamedContent())->toBe('nas-only-content');
});

test('legacy storage url falls back to s3 when only s3 read fallback is enabled', function (): void {
    Storage::fake('public');
    Storage::fake('nas_sftp');
    Storage::fake('s3');

    configureStorageArchive([
        'filesystems.archive.enabled' => false,
        'filesystems.archive.read_fallback' => [
            'nas' => [
                'enabled' => false,
                'disk' => 'nas_sftp',
            ],
            's3' => [
                'enabled' => true,
                'disk' => 's3',
            ],
        ],
    ]);

    Storage::disk('nas_sftp')->put('documents/nas-only-report.jpg', 'nas-only-content');

    $this->get('/storage/documents/nas-only-report.jpg')
        ->assertNotFound();

    Storage::disk('s3')->put('documents/s3-only-report.jpg', 's3-only-content');

    $response = $this->get('/storage/documents/s3-only-report.jpg');

    $response->assertOk();
    expect($response->streamedContent())->toBe('s3-only-content');
});

test('legacy storage url checks nas then s3 when both read fallbacks are enabled', function (): void {
    Storage::fake('public');
    Storage::fake('nas_sftp');
    Storage::fake('s3');

    configureStorageArchive([
        'filesystems.archive.enabled' => false,
        'filesystems.archive.read_fallback' => [
            'nas' => [
                'enabled' => true,
                'disk' => 'nas_sftp',
            ],
            's3' => [
                'enabled' => true,
                'disk' => 's3',
            ],
        ],
    ]);

    Storage::disk('s3')->put('documents/s3-only-report.jpg', 's3-only-content');

    $s3Response = $this->get('/storage/documents/s3-only-report.jpg');

    $s3Response->assertOk();
    expect($s3Response->streamedContent())->toBe('s3-only-content');

    Storage::disk('nas_sftp')->put('documents/shared-report.jpg', 'nas-content');
    Storage::disk('s3')->put('documents/shared-report.jpg', 's3-content');

    $sharedResponse = $this->get('/storage/documents/shared-report.jpg');

    $sharedResponse->assertOk();
    expect($sharedResponse->streamedContent())->toBe('nas-content');
});

test('legacy storage url returns not found when local and archive target are both missing', function (): void {
    Storage::fake('public');
    Storage::fake('nas_sftp');

    configureStorageArchive([
        'filesystems.archive.enabled' => false,
    ]);

    $this->get('/storage/documents/missing-report.jpg')
        ->assertNotFound();
});

test('storage disk delete removes archive copy even when archive job is disabled', function (): void {
    Storage::fake('public');
    Storage::fake('nas_sftp');

    configureStorageArchive([
        'filesystems.archive.enabled' => false,
    ]);

    Storage::disk('nas_sftp')->put('documents/archived-report.jpg', 'archived-content');

    expect(StorageDisk::delete('documents/archived-report.jpg'))->toBeTrue();

    Storage::disk('nas_sftp')->assertMissing('documents/archived-report.jpg');
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
    Storage::fake('nas_sftp');
    configureStorageArchive();

    Storage::disk('nas_sftp')->put('documents/archived-report.jpg', 'archived-content');

    expect(StorageDisk::delete('documents/archived-report.jpg'))->toBeTrue();

    Storage::disk('nas_sftp')->assertMissing('documents/archived-report.jpg');
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

test('storage check disk command validates write probe and deletes it', function (): void {
    $root = storage_path('framework/testing/disks/archive-probe-storage');
    $filesystem = new Filesystem;
    $filesystem->deleteDirectory($root);
    $filesystem->ensureDirectoryExists($root);

    config([
        'filesystems.disks.archive_probe_storage' => [
            'driver' => 'local',
            'root' => $root,
            'visibility' => 'private',
            'throw' => false,
        ],
    ]);

    $this->artisan('storage:check-disk archive_probe_storage --write')
        ->assertSuccessful();

    expect($filesystem->allFiles($root))->toBeEmpty();

    $filesystem->deleteDirectory($root);
});

test('storage check disk command refuses to overwrite existing probe path', function (): void {
    $root = storage_path('framework/testing/disks/archive-probe-existing-storage');
    $filesystem = new Filesystem;
    $filesystem->deleteDirectory($root);
    $filesystem->ensureDirectoryExists($root);

    config([
        'filesystems.disks.archive_probe_existing_storage' => [
            'driver' => 'local',
            'root' => $root,
            'visibility' => 'private',
            'throw' => false,
        ],
    ]);

    Storage::disk('archive_probe_existing_storage')->put('existing-probe.txt', 'do-not-touch');

    $this->artisan('storage:check-disk archive_probe_existing_storage --write --path=existing-probe.txt')
        ->assertFailed();

    expect(Storage::disk('archive_probe_existing_storage')->get('existing-probe.txt'))->toBe('do-not-touch');

    $filesystem->deleteDirectory($root);
});

test('storage clone disk command copies s3 files to nas without deleting source', function (): void {
    Storage::fake('s3');
    Storage::fake('nas_sftp');

    Storage::disk('s3')->put('documents/report.jpg', 'report-content');
    Storage::disk('s3')->put('videos/visit.mp4', 'video-content');

    $this->artisan('storage:clone-disk --source-disk=s3 --target-disk=nas_sftp')
        ->assertSuccessful();

    Storage::disk('s3')->assertExists('documents/report.jpg');
    Storage::disk('s3')->assertExists('videos/visit.mp4');
    Storage::disk('nas_sftp')->assertExists('documents/report.jpg');
    Storage::disk('nas_sftp')->assertExists('videos/visit.mp4');

    expect(Storage::disk('nas_sftp')->get('documents/report.jpg'))->toBe('report-content')
        ->and(Storage::disk('nas_sftp')->get('videos/visit.mp4'))->toBe('video-content');
});

test('storage clone disk batch continues past existing target files', function (): void {
    Storage::fake('s3');
    Storage::fake('nas_sftp');

    Storage::disk('s3')->put('documents/a-existing.jpg', 'already-copied');
    Storage::disk('s3')->put('documents/b-new.jpg', 'new-content');
    Storage::disk('s3')->put('documents/c-new.jpg', 'another-new-content');
    Storage::disk('nas_sftp')->put('documents/a-existing.jpg', 'already-copied');

    $this->artisan('storage:clone-disk --source-disk=s3 --target-disk=nas_sftp --batch=1 --progress=0 --verify-sleep-ms=0')
        ->assertSuccessful();

    $targetFiles = Storage::disk('nas_sftp')->allFiles('documents');

    expect($targetFiles)->toHaveCount(2)
        ->and($targetFiles)->toContain('documents/a-existing.jpg');
});

test('storage clone disk verification retries direct target checks when target index is stale', function (): void {
    Storage::fake('s3');

    $root = storage_path('framework/testing/disks/stale-list-target');
    $filesystem = new Filesystem;
    $filesystem->deleteDirectory($root);
    $filesystem->ensureDirectoryExists($root);

    Storage::extend('stale_list_local', function ($app, array $config) {
        $adapter = new class($config['root']) extends \League\Flysystem\Local\LocalFilesystemAdapter
        {
            public function listContents(string $path, bool $deep): iterable
            {
                return [];
            }
        };

        return new \Illuminate\Filesystem\FilesystemAdapter(
            new \League\Flysystem\Filesystem($adapter, $config),
            $adapter,
            $config,
        );
    });

    config([
        'filesystems.disks.stale_list_target' => [
            'driver' => 'stale_list_local',
            'root' => $root,
            'visibility' => 'private',
            'throw' => false,
        ],
    ]);

    Storage::disk('s3')->put('documents/retry-verified.jpg', 'retry-verified-content');

    $this->artisan('storage:clone-disk --source-disk=s3 --target-disk=stale_list_target --verify-attempts=2 --verify-sleep-ms=0 --progress=0')
        ->assertSuccessful();

    expect(Storage::disk('stale_list_target')->get('documents/retry-verified.jpg'))
        ->toBe('retry-verified-content');

    $filesystem->deleteDirectory($root);
});
