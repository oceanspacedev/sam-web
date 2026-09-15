<?php

use App\Support\StorageDisk;
use Illuminate\Support\Facades\Storage;

it('uses the laravel default filesystem disk', function () {
    config(['filesystems.default' => 'public']);

    expect(StorageDisk::default())->toBe('public');
});

it('builds urls through the laravel storage facade on the default disk', function () {
    Storage::fake('public');
    config(['filesystems.default' => 'public']);

    Storage::disk('public')->put('photos/shop.jpg', 'content');

    $url = StorageDisk::url('photos/shop.jpg');

    expect($url)->toBeString()
        ->and($url)->toContain('photos/shop.jpg');
});

it('returns null urls for blank placeholder paths', function () {
    expect(StorageDisk::url(null))->toBeNull()
        ->and(StorageDisk::url(''))->toBeNull()
        ->and(StorageDisk::url('-'))->toBeNull()
        ->and(StorageDisk::url('0'))->toBeNull();
});

it('deletes files through the laravel storage facade', function () {
    Storage::fake('public');

    Storage::disk('public')->put('photos/old.jpg', 'old');

    expect(StorageDisk::delete('photos/old.jpg', 'public'))->toBeTrue();

    Storage::disk('public')->assertMissing('photos/old.jpg');
});

it('does not delete empty or placeholder paths', function () {
    expect(StorageDisk::delete(null))->toBeFalse()
        ->and(StorageDisk::delete(''))->toBeFalse()
        ->and(StorageDisk::delete('-'))->toBeFalse()
        ->and(StorageDisk::delete('0'))->toBeFalse();
});

it('registers only laravel local public and s3 disks', function () {
    expect(array_keys(config('filesystems.disks')))->toBe(['local', 'public', 's3'])
        ->and(config('filesystems.archive'))->toBeNull()
        ->and(config('filesystems.disks.nas_sftp'))->toBeNull();
});
