<?php

use App\Filament\Auth\Pages\PhoneLogin;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsappOtp;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Livewire\Livewire;
use Livewire\Mechanisms\ComponentRegistry;

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

test('phone login relies on the web middleware group for csrf protection', function (): void {
    $middleware = app('router')->getRoutes()->getByName('phone-login')->gatherMiddleware();

    expect($middleware)->not->toContain(VerifyCsrfToken::class);
});

test('phone login is registered for subsequent Livewire requests', function (): void {
    expect(app(ComponentRegistry::class)->getClass('phone-login'))
        ->toBe(PhoneLogin::class);
});

test('verified web user can request OTP and login via WhatsApp', function (): void {
    $user = makeWebUser();

    $component = Livewire::test(PhoneLogin::class)
        ->fillForm(['whatsapp_number' => '081234567890'])
        ->call('send')
        ->assertNoRedirect()
        ->assertSet('awaitingOtp', true)
        ->assertSet('data.whatsapp_number', '6281234567890')
        ->assertSeeHtml('wire:submit="verify"');

    $otp = WhatsappOtp::query()->where('user_id', $user->id)->latest('id')->firstOrFail();
    expect($otp->purpose)->toBe(WhatsappOtp::PURPOSE_LOGIN);

    // Force known OTP for verify (sendOtp is no-op in testing)
    $otp->forceFill([
        'otp_hash' => Hash::make('123456'),
        'expires_at' => now()->addMinute(),
        'attempt_count' => 0,
        'verified_at' => null,
    ])->save();

    $component
        ->set('data.otp', '123456')
        ->call('verify')
        ->assertRedirect('/admin');

    expect(Auth::check())->toBeTrue()
        ->and(Auth::id())->toBe($user->id);
});

test('phone login does not expose a separate verification route', function (): void {
    expect(app('router')->getRoutes()->getByName('phone-login.verify'))->toBeNull()
        ->and(app('router')->getRoutes()->getByName('phone-login.verify.submit'))->toBeNull();
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

    Livewire::test(PhoneLogin::class)
        ->fillForm(['whatsapp_number' => '081234567890'])
        ->set('awaitingOtp', true)
        ->set('data.otp', '000000')
        ->call('verify')
        ->assertHasFormErrors(['otp']);

    expect(Auth::check())->toBeFalse();
});
