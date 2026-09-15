<?php

use App\Support\StoragePathResolver;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

it('returns the real local path for local disks without copying', function () {
    Storage::fake('public');
    Storage::disk('public')->put('photos/shop.jpg', 'shop-bytes');

    [$path, $temporary] = StoragePathResolver::resolveForLocalAccess('public', 'photos/shop.jpg');

    expect($temporary)->toBeNull()
        ->and(is_file($path))->toBeTrue()
        ->and(file_get_contents($path))->toBe('shop-bytes');
});

it('streams objects to a temporary file when path() is not a real local file', function () {
    $root = storage_path('framework/testing/disks/remote-like');
    (new Illuminate\Filesystem\Filesystem)->deleteDirectory($root);
    (new Illuminate\Filesystem\Filesystem)->ensureDirectoryExists($root);

    Storage::extend('remote_like', function ($app, array $config) {
        $adapter = new LocalFilesystemAdapter($config['root']);
        $filesystem = new Filesystem($adapter);

        return new class($filesystem, $adapter, $config) extends Illuminate\Filesystem\FilesystemAdapter
        {
            public function path($path)
            {
                return '/this/path/does/not/exist/'.$path;
            }
        };
    });

    config([
        'filesystems.disks.remote_like' => [
            'driver' => 'remote_like',
            'root' => $root,
        ],
    ]);

    Storage::disk('remote_like')->put('photos/shop.jpg', 'shop-bytes');

    [$path, $temporary] = StoragePathResolver::resolveForLocalAccess('remote_like', 'photos/shop.jpg');

    expect($temporary)->not->toBeNull()
        ->and($path)->toBe($temporary)
        ->and(is_file($path))->toBeTrue()
        ->and(file_get_contents($path))->toBe('shop-bytes');

    StoragePathResolver::cleanupTemporaryPath($temporary);

    expect(is_file($path))->toBeFalse();

    (new Illuminate\Filesystem\Filesystem)->deleteDirectory($root);
});
