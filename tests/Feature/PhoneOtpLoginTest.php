<?php

use App\Filament\Auth\Pages\PhoneLogin;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsappOtp;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

function makeWebUser(array $overrides = []): User
{
    $role = Role::factory()->create(['can_access_web' => true]);

    return User::factory()->create(array_merge([
        'role_id' => $role->id,
        'whatsapp_number' => '6281234567890',
        'whatsapp_verified_at' => now(),
    ], $overrides));
}

test('login page exposes WhatsApp entry point', function (): void {
    $this->withoutVite();

    $this->get('/admin/login')
        ->assertOk()
        ->assertSee('Atau masuk dengan')
        ->assertSee('WhatsApp')
        ->assertSee(route('phone-login'), false);
});

test('verified web user can request OTP and login via WhatsApp', function (): void {
    $user = makeWebUser();

    Livewire::test(PhoneLogin::class)
        ->fillForm(['whatsapp_number' => '081234567890'])
        ->call('send')
        ->assertRedirect(route('phone-login.verify'));

    $otp = WhatsappOtp::query()->where('user_id', $user->id)->latest('id')->firstOrFail();
    expect($otp->purpose)->toBe(WhatsappOtp::PURPOSE_LOGIN);

    // Force known OTP for verify (sendOtp is no-op in testing)
    $otp->forceFill([
        'otp_hash' => Hash::make('123456'),
        'expires_at' => now()->addMinute(),
        'attempt_count' => 0,
        'verified_at' => null,
    ])->save();

    $this->from(route('phone-login.verify'))
        ->post(route('phone-login.verify.submit'), [
            'phone' => '6281234567890',
            'otp' => '123456',
        ])
        ->assertRedirect('/admin');

    expect(Auth::check())->toBeTrue()
        ->and(Auth::id())->toBe($user->id);
});

test('rejects unverified WhatsApp number', function (): void {
    makeWebUser([
        'whatsapp_verified_at' => null,
    ]);

    Livewire::test(PhoneLogin::class)
        ->fillForm(['whatsapp_number' => '081234567890'])
        ->call('send')
        ->assertHasFormErrors(['whatsapp_number']);

    expect(WhatsappOtp::query()->count())->toBe(0);
});

test('rejects user without web panel access', function (): void {
    $role = Role::factory()->create(['can_access_web' => false]);
    User::factory()->create([
        'role_id' => $role->id,
        'whatsapp_number' => '6281234567890',
        'whatsapp_verified_at' => now(),
    ]);

    Livewire::test(PhoneLogin::class)
        ->fillForm(['whatsapp_number' => '081234567890'])
        ->call('send')
        ->assertHasFormErrors(['whatsapp_number']);
});

test('rejects wrong OTP', function (): void {
    $user = makeWebUser();

    WhatsappOtp::query()->create([
        'user_id' => $user->id,
        'whatsapp_number' => '6281234567890',
        'purpose' => WhatsappOtp::PURPOSE_LOGIN,
        'otp_hash' => Hash::make('123456'),
        'expires_at' => now()->addMinute(),
        'attempt_count' => 0,
    ]);

    $this->from(route('phone-login.verify'))
        ->post(route('phone-login.verify.submit'), [
            'phone' => '6281234567890',
            'otp' => '000000',
        ])
        ->assertSessionHasErrors('otp');

    expect(Auth::check())->toBeFalse();
});
