<?php

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

test('storage check disk command writes then deletes a probe file', function (): void {
    $root = storage_path('framework/testing/disks/check-disk-probe');
    $filesystem = new Filesystem;
    $filesystem->deleteDirectory($root);
    $filesystem->ensureDirectoryExists($root);

    config([
        'filesystems.disks.check_disk_probe' => [
            'driver' => 'local',
            'root' => $root,
            'visibility' => 'private',
            'throw' => false,
        ],
    ]);

    $this->artisan('storage:check-disk check_disk_probe --write')
        ->assertSuccessful();

    expect($filesystem->allFiles($root))->toBeEmpty();

    $filesystem->deleteDirectory($root);
});

test('storage check disk command refuses to overwrite an existing probe path', function (): void {
    $root = storage_path('framework/testing/disks/check-disk-existing');
    $filesystem = new Filesystem;
    $filesystem->deleteDirectory($root);
    $filesystem->ensureDirectoryExists($root);

    config([
        'filesystems.disks.check_disk_existing' => [
            'driver' => 'local',
            'root' => $root,
            'visibility' => 'private',
            'throw' => false,
        ],
    ]);

    Storage::disk('check_disk_existing')->put('existing-probe.txt', 'do-not-touch');

    $this->artisan('storage:check-disk check_disk_existing --write --path=existing-probe.txt')
        ->assertFailed();

    expect(Storage::disk('check_disk_existing')->get('existing-probe.txt'))->toBe('do-not-touch');

    $filesystem->deleteDirectory($root);
});
