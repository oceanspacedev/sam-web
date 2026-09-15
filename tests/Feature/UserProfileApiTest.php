<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

test('user dapat mengupdate profil sendiri', function (): void {
    $user = User::factory()->create([
        'username' => 'old_user',
        'nama_lengkap' => 'Old Name',
        'password' => bcrypt('oldpassword123'),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->putJson('/api/user', [
            'username' => 'new_user',
            'nama_lengkap' => 'new name',
            'password' => 'newpassword123',
        ]);

    $response
        ->assertStatus(200)
        ->assertJsonPath('meta.code', 200)
        ->assertJsonPath('meta.status', 'success')
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.username', 'new_user')
        ->assertJsonPath('data.nama_lengkap', 'NEW NAME');

    $user->refresh();
    expect($user->username)->toBe('new_user');
    expect($user->nama_lengkap)->toBe('NEW NAME');
    expect(Hash::check('newpassword123', $user->password))->toBeTrue();
});

test('user dapat mengupload foto profil', function (): void {
    $disk = \App\Support\StorageDisk::default();
    Storage::fake($disk);

    $user = User::factory()->create([
        'profile_photo_path' => 'profile-photos/old-photo.jpg',
    ]);

    Storage::disk($disk)->put('profile-photos/old-photo.jpg', 'legacy-photo');

    $response = $this->actingAs($user, 'sanctum')
        ->post('/api/user/photo', [
            'profile_photo' => UploadedFile::fake()->image('profile.jpg'),
        ]);

    $response
        ->assertStatus(200)
        ->assertJsonPath('meta.code', 200)
        ->assertJsonPath('meta.status', 'success')
        ->assertJsonPath('data.id', $user->id);

    $user->refresh();
    expect($user->profile_photo_path)->not->toBeNull();
    expect(Storage::disk($disk)->exists($user->profile_photo_path))->toBeTrue();
    expect(Storage::disk($disk)->exists('profile-photos/old-photo.jpg'))->toBeFalse();
    expect($user->profile_photo_url)->toBe(\App\Support\StorageDisk::url($user->profile_photo_path));
});

test('user dapat menghapus akun sendiri', function (): void {
    $user = User::factory()->create();
    $user->createToken('mobile-token');

    $response = $this->actingAs($user, 'sanctum')
        ->deleteJson('/api/user');

    $response
        ->assertStatus(200)
        ->assertJsonPath('meta.code', 200)
        ->assertJsonPath('meta.status', 'success');

    $this->assertSoftDeleted('users', ['id' => $user->id]);
    $this->assertDatabaseMissing('personal_access_tokens', [
        'tokenable_type' => User::class,
        'tokenable_id' => $user->id,
    ]);
});
