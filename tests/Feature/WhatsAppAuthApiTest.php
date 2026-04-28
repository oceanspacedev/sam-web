<?php

use App\Models\User;
use App\Models\WhatsappOtp;
use Illuminate\Support\Facades\Hash;

test('user terverifikasi dapat request OTP login WhatsApp', function (): void {
    $user = User::factory()->create([
        'whatsapp_number' => '6281234567890',
        'whatsapp_verified_at' => now(),
    ]);

    $response = $this->postJson('/api/login/whatsapp/request-otp', [
        'whatsapp_number' => '6281234567890',
    ]);

    $response
        ->assertStatus(200)
        ->assertJsonPath('meta.status', 'success')
        ->assertJsonPath('data.expires_in', 60)
        ->assertJsonPath('data.masked_number', '+62812******90');

    $otp = WhatsappOtp::query()->where('user_id', $user->id)->firstOrFail();

    expect($otp->purpose)->toBe(WhatsappOtp::PURPOSE_LOGIN)
        ->and($otp->otp_hash)->not->toHaveLength(6);
});

test('login WhatsApp menolak nomor belum terverifikasi', function (): void {
    User::factory()->create([
        'whatsapp_number' => '6281234567890',
        'whatsapp_verified_at' => null,
    ]);

    $response = $this->postJson('/api/login/whatsapp/request-otp', [
        'whatsapp_number' => '6281234567890',
    ]);

    $response
        ->assertStatus(422)
        ->assertJsonPath('meta.status', 'error')
        ->assertJsonPath('errors.whatsapp_number.0', 'Nomor WhatsApp belum terdaftar atau belum aktif.');
});

test('verify OTP login WhatsApp mengembalikan token dan user', function (): void {
    $user = User::factory()->create([
        'whatsapp_number' => '6281234567890',
        'whatsapp_verified_at' => now(),
    ]);

    WhatsappOtp::query()->create([
        'user_id' => $user->id,
        'whatsapp_number' => '6281234567890',
        'purpose' => WhatsappOtp::PURPOSE_LOGIN,
        'otp_hash' => Hash::make('123456'),
        'expires_at' => now()->addMinute(),
    ]);

    $response = $this->postJson('/api/login/whatsapp/verify-otp', [
        'whatsapp_number' => '6281234567890',
        'otp' => '123456',
        'notif_id' => 'onesignal-player-id',
        'version' => '2.0.1',
    ]);

    $response
        ->assertStatus(200)
        ->assertJsonPath('meta.status', 'success')
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonPath('data.user.whatsapp_number', '6281234567890')
        ->assertJsonPath('data.user.nomor_whatsapp', '6281234567890')
        ->assertJsonPath('data.user.permissions.can_monitor_visit', false)
        ->assertJsonStructure(['data' => ['access_token']]);

    $user->refresh();

    expect($user->id_notif)->toBe('onesignal-player-id')
        ->and($user->tokens()->count())->toBe(1)
        ->and(WhatsappOtp::query()->whereNotNull('verified_at')->exists())->toBeTrue();
});

test('user dapat request dan verify OTP untuk update nomor WhatsApp profil', function (): void {
    $user = User::factory()->create();

    $requestResponse = $this->actingAs($user, 'sanctum')
        ->postJson('/api/user/whatsapp/request-otp', [
            'nomor_whatsapp' => '081234567890',
        ]);

    $requestResponse
        ->assertStatus(200)
        ->assertJsonPath('meta.status', 'success');

    WhatsappOtp::query()
        ->where('user_id', $user->id)
        ->where('purpose', WhatsappOtp::PURPOSE_PROFILE_UPDATE)
        ->update([
            'otp_hash' => Hash::make('654321'),
            'expires_at' => now()->addMinute(),
        ]);

    $verifyResponse = $this->actingAs($user, 'sanctum')
        ->postJson('/api/user/whatsapp/verify-otp', [
            'whatsapp_number' => '6281234567890',
            'nomor_whatsapp' => '6281234567890',
            'otp' => '654321',
        ]);

    $verifyResponse
        ->assertStatus(200)
        ->assertJsonPath('meta.status', 'success')
        ->assertJsonPath('data.whatsapp_number', '6281234567890')
        ->assertJsonPath('data.nomor_whatsapp', '6281234567890');

    $user->refresh();

    expect($user->whatsapp_number)->toBe('6281234567890')
        ->and($user->whatsapp_verified_at)->not->toBeNull();
});
