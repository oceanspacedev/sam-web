<?php

use App\Http\Middleware\RateLimitUploads;
use App\Models\Outlet;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;
use App\Services\FileUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

function bindLaravelFilesystemDisk(string $disk): void
{
    Storage::fake('local');
    Storage::fake('public');
    Storage::fake('s3');

    config([
        'filesystems.default' => $disk,
        'filament.default_filesystem_disk' => $disk,
    ]);
}

beforeEach(function () {
    $this->withoutMiddleware(RateLimitUploads::class);
});

it('writes api uploads to the configured filesystem disk and not the others', function (string $disk) {
    bindLaravelFilesystemDisk($disk);

    $service = new FileUploadService;
    $path = $service->uploadImageOptimized(
        UploadedFile::fake()->image('shop.jpg'),
        'outlet-photo',
    );

    expect($service->disk())->toBe($disk)
        ->and($path)->not->toBe('');

    Storage::disk($disk)->assertExists($path);

    foreach (array_diff(['local', 'public', 's3'], [$disk]) as $other) {
        Storage::disk($other)->assertMissing($path);
    }
})->with(['public', 's3', 'local']);

it('stores fifty uploads on the configured disk in a single hop', function () {
    bindLaravelFilesystemDisk('s3');

    $uploads = new FileUploadService;
    $paths = [];
    $started = hrtime(true);

    for ($i = 0; $i < 50; $i++) {
        $paths[] = $uploads->put(UploadedFile::fake()->image("shot-{$i}.jpg"), 'visit-in');
    }

    $elapsedMs = (hrtime(true) - $started) / 1e6;

    expect($paths)->toHaveCount(50)
        ->and($elapsedMs)->toBeLessThan(5000);

    foreach ($paths as $path) {
        expect($path)->not->toStartWith('tmp/');
        Storage::disk('s3')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
    }
});

it('stores profile photos on the configured filesystem disk via the api', function () {
    bindLaravelFilesystemDisk('s3');

    $user = User::factory()->create([
        'profile_photo_path' => 'profile-photos/old-photo.jpg',
    ]);
    Storage::disk('s3')->put('profile-photos/old-photo.jpg', 'legacy-photo');

    $response = $this->actingAs($user, 'sanctum')
        ->post('/api/user/photo', [
            'profile_photo' => UploadedFile::fake()->image('profile.jpg'),
        ]);

    $response->assertOk();

    $user->refresh();
    expect($user->profile_photo_path)->not->toBe('profile-photos/old-photo.jpg');
    Storage::disk('s3')->assertExists($user->profile_photo_path);
    Storage::disk('s3')->assertMissing('profile-photos/old-photo.jpg');
    Storage::disk('public')->assertMissing($user->profile_photo_path);
});

it('stores visit check-in photos on the configured filesystem disk via the api', function () {
    bindLaravelFilesystemDisk('s3');

    $role = Role::factory()->create([
        'can_access_web' => true,
        'organizational_scope_level' => 'cluster',
    ]);
    $user = User::factory()->create(['role_id' => $role->id]);
    $outlet = Outlet::factory()->create();

    $user->badanUsahas()->attach($outlet->badanusaha_id);
    $user->divisis()->attach($outlet->divisi_id);
    $user->regions()->attach($outlet->region_id);
    $user->clusters()->attach($outlet->cluster_id);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/visit/checkin', [
            'outlet_id' => $outlet->id,
            'latlong_in' => '-6.2,106.8',
            'tipe_visit' => 'EXTRACALL',
            'picture_visit' => UploadedFile::fake()->image('checkin.jpg', 800, 600),
        ]);

    $response->assertOk();

    $path = Visit::query()->where('user_id', $user->id)->latest('id')->value('picture_visit_in');

    expect($path)->not->toBeNull()
        ->and($path)->not->toStartWith('tmp/');
    Storage::disk('s3')->assertExists($path);
    Storage::disk('public')->assertMissing($path);
});

it('binds filament file uploads and filament config to the laravel filesystem disk', function () {
    expect(config('filament.default_filesystem_disk'))->toBe(config('filesystems.default'));

    $uploads = 0;

    foreach (File::allFiles(app_path('Filament')) as $file) {
        $contents = $file->getContents();

        if (! str_contains($contents, 'FileUpload::make')) {
            continue;
        }

        preg_match_all('/FileUpload::make\((.*?)\)/s', $contents, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as $match) {
            $uploads++;
            $window = substr($contents, $match[1], 600);

            expect($window)->toContain('->disk(StorageDisk::default())');
        }
    }

    expect($uploads)->toBeGreaterThan(0);
});
