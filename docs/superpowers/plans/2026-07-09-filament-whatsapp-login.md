# Filament Admin WhatsApp Login Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add WhatsApp OTP login to Filament `/admin/login` (helpdesk-style UI) while reusing SAM’s `WhatsappOtp` backend for session auth.

**Architecture:** Mirror helpdesk’s three-step UI (login button → phone page → OTP verify). Extract thin shared OTP issue/verify helpers from `WhatsAppAuthController` so API and Filament share one OTP rule set. Filament path ends in `Auth::login` + session regenerate (not Sanctum).

**Tech Stack:** Laravel 12, Filament 4, Livewire, Mekaya theme, Pest, existing `WhatsAppNotificationService` / `WhatsappOtp`.

**Spec:** `docs/superpowers/specs/2026-07-09-filament-whatsapp-login-design.md`

---

## File map

| File | Responsibility |
|------|----------------|
| `app/Services/WhatsAppOtpService.php` | Shared issue/verify OTP (login + profile purposes) |
| `app/Http/Controllers/API/WhatsAppAuthController.php` | Delegate OTP issue/verify to service; keep JSON responses |
| `app/Filament/Auth/Concerns/InteractsWithWhatsAppLogin.php` | Normalize number + find eligible web user |
| `app/Filament/Auth/Pages/PhoneLogin.php` | Livewire phone entry + send OTP |
| `app/Http/Controllers/Auth/PhoneOtpLoginController.php` | Show verify form + verify OTP + session login |
| `resources/views/auth/login-extra.blade.php` | Divider + WhatsApp button under password form |
| `resources/views/filament/auth/phone-login.blade.php` | Mekaya shell for phone page |
| `resources/views/auth/layout.blade.php` | Filament simple layout + Mekaya card |
| `resources/views/auth/partials/header.blade.php` | Auth page header + back link |
| `resources/views/auth/partials/submit-button.blade.php` | Full-width primary submit |
| `resources/views/auth/phone-login-verify.blade.php` | OTP verify form |
| `app/Providers/Filament/AdminPanelProvider.php` | `AUTH_LOGIN_FORM_AFTER` render hook |
| `routes/web.php` | `/phone-login` + verify routes |
| `resources/css/app.css` | `.whatsapp-login-icon` fill |
| `app/Providers/AppServiceProvider.php` | Rate limiter also reads `phone` |
| `tests/Feature/PhoneOtpLoginTest.php` | Filament WhatsApp login feature tests |

---

### Task 1: Extract `WhatsAppOtpService`

**Files:**
- Create: `app/Services/WhatsAppOtpService.php`
- Modify: `app/Http/Controllers/API/WhatsAppAuthController.php`
- Test: `tests/Feature/WhatsAppAuthApiTest.php` (existing — must stay green)

- [ ] **Step 1: Create the shared OTP service**

Create `app/Services/WhatsAppOtpService.php`:

```php
<?php

namespace App\Services;

use App\Models\User;
use App\Models\WhatsappOtp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class WhatsAppOtpService
{
    public const MAX_ATTEMPTS = 5;

    public function __construct(
        protected WhatsAppNotificationService $whatsApp
    ) {}

    /**
     * @return array{otp_record: WhatsappOtp, expires_in: int}
     */
    public function issue(User $user, string $number, string $purpose): array
    {
        $expiresIn = (int) config('services.whatsapp.otp_expires_in', 60);
        $expiresAt = now()->addSeconds($expiresIn);
        $otp = (string) random_int(100000, 999999);

        WhatsappOtp::query()
            ->where('whatsapp_number', $number)
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->update(['expires_at' => now()]);

        $otpRecord = WhatsappOtp::query()->create([
            'user_id' => $user->id,
            'whatsapp_number' => $number,
            'purpose' => $purpose,
            'otp_hash' => Hash::make($otp),
            'expires_at' => $expiresAt,
            'attempt_count' => 0,
        ]);

        try {
            $this->whatsApp->sendOtp($number, $otp);
        } catch (Throwable $exception) {
            $otpRecord->forceFill(['expires_at' => now()])->save();

            Log::warning('Gagal mengirim OTP WhatsApp', [
                'user_id' => $user->id,
                'purpose' => $purpose,
                'whatsapp_last4' => substr($number, -4),
                'error' => $exception->getMessage(),
            ]);

            throw new RuntimeException('Gagal mengirim OTP WhatsApp.', previous: $exception);
        }

        return [
            'otp_record' => $otpRecord,
            'expires_in' => $expiresIn,
        ];
    }

    public function verify(User $user, string $number, string $purpose, string $otp): ?WhatsappOtp
    {
        $otpRecord = WhatsappOtp::query()
            ->where('whatsapp_number', $number)
            ->where('purpose', $purpose)
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)->orWhereNull('user_id');
            })
            ->whereNull('verified_at')
            ->latest('id')
            ->first();

        if (! $otpRecord || $otpRecord->expires_at->isPast() || $otpRecord->attempt_count >= self::MAX_ATTEMPTS) {
            return null;
        }

        if (! Hash::check($otp, $otpRecord->otp_hash)) {
            $attempts = $otpRecord->attempt_count + 1;
            $payload = ['attempt_count' => $attempts];

            if ($attempts >= self::MAX_ATTEMPTS) {
                $payload['expires_at'] = now();
            }

            $otpRecord->forceFill($payload)->save();

            return null;
        }

        DB::transaction(function () use ($otpRecord): void {
            $otpRecord->forceFill(['verified_at' => now()])->save();
        });

        return $otpRecord;
    }
}
```

- [ ] **Step 2: Refactor `WhatsAppAuthController` to use the service**

Replace constructor and OTP methods so the controller keeps JSON wrapping only:

```php
public function __construct(
    protected WhatsAppOtpService $otpService
) {}
```

In `requestLoginOtp` / `requestProfileOtp`, replace `$this->issueOtp(...)` with:

```php
try {
    $result = $this->otpService->issue($user, $number, WhatsappOtp::PURPOSE_LOGIN); // or PURPOSE_PROFILE_UPDATE
} catch (Throwable) {
    return response()->json([
        'meta' => [
            'code' => 502,
            'status' => 'error',
            'message' => 'Gagal mengirim OTP WhatsApp.',
        ],
        'data' => null,
        'errors' => null,
    ], 502);
}

return response()->json([
    'meta' => [
        'code' => 200,
        'status' => 'success',
        'message' => 'OTP berhasil dikirim.',
    ],
    'data' => [
        'expires_in' => $result['expires_in'],
        'masked_number' => WhatsAppNumber::mask($number),
    ],
    'errors' => null,
]);
```

In verify methods, replace `$this->verifyStoredOtp(...)` with:

```php
$otpRecord = $this->otpService->verify($user, $number, WhatsappOtp::PURPOSE_LOGIN, $otp);
```

Delete private `issueOtp`, `verifyStoredOtp`, and `MAX_ATTEMPTS` from the controller. Keep `requestNumber`, `requestOtp`, `loginUnavailableResponse`, etc.

- [ ] **Step 3: Run existing API tests**

Run: `php artisan test --compact tests/Feature/WhatsAppAuthApiTest.php`

Expected: all PASS

- [ ] **Step 4: Commit**

```bash
git add app/Services/WhatsAppOtpService.php app/Http/Controllers/API/WhatsAppAuthController.php
git commit -m "$(cat <<'EOF'
refactor: extract WhatsAppOtpService for shared OTP issue/verify

EOF
)"
```

---

### Task 2: WhatsApp login eligibility concern

**Files:**
- Create: `app/Filament/Auth/Concerns/InteractsWithWhatsAppLogin.php`
- Modify: `app/Providers/AppServiceProvider.php` (rate limiter accepts `phone`)

- [ ] **Step 1: Create the concern**

Create `app/Filament/Auth/Concerns/InteractsWithWhatsAppLogin.php`:

```php
<?php

namespace App\Filament\Auth\Concerns;

use App\Models\User;
use App\Support\WhatsAppNumber;
use Filament\Facades\Filament;

trait InteractsWithWhatsAppLogin
{
    protected function normalizeWhatsAppNumber(mixed $value): ?string
    {
        $number = WhatsAppNumber::normalize(is_scalar($value) ? (string) $value : null);

        return WhatsAppNumber::isValid($number) ? $number : null;
    }

    protected function findEligibleWebUserByWhatsApp(string $number): ?User
    {
        $user = User::query()
            ->with('role')
            ->where('whatsapp_number', $number)
            ->whereNotNull('whatsapp_verified_at')
            ->first();

        if (! $user) {
            return null;
        }

        $panel = Filament::getCurrentPanel() ?? Filament::getPanel('admin');

        if (! $user->canAccessPanel($panel)) {
            return null;
        }

        return $user;
    }

    protected function unavailableWhatsAppMessage(): string
    {
        return 'Nomor WhatsApp belum terdaftar atau belum aktif.';
    }
}
```

- [ ] **Step 2: Update WhatsApp rate limiters to also read `phone`**

In `app/Providers/AppServiceProvider.php`, change both `whatsapp-otp` and `whatsapp-verify` number resolution to:

```php
$number = WhatsAppNumber::normalize((string) (
    $request->input('whatsapp_number')
    ?? $request->input('nomor_whatsapp')
    ?? $request->input('phone')
    ?? $request->input('data.whatsapp_number')
    ?? ''
));
```

- [ ] **Step 3: Commit**

```bash
git add app/Filament/Auth/Concerns/InteractsWithWhatsAppLogin.php app/Providers/AppServiceProvider.php
git commit -m "$(cat <<'EOF'
feat: add WhatsApp login eligibility helpers for Filament auth

EOF
)"
```

---

### Task 3: Auth Blade shells + login WhatsApp button

**Files:**
- Create: `resources/views/auth/login-extra.blade.php`
- Create: `resources/views/auth/layout.blade.php`
- Create: `resources/views/auth/partials/header.blade.php`
- Create: `resources/views/auth/partials/submit-button.blade.php`
- Create: `resources/views/filament/auth/phone-login.blade.php`
- Modify: `app/Providers/Filament/AdminPanelProvider.php`
- Modify: `resources/css/app.css`

- [ ] **Step 1: Create `login-extra.blade.php` (WhatsApp only)**

```blade
<div class="mt-0 space-y-6">
    <div class="relative flex items-center justify-center">
        <div class="flex-grow border-t border-gray-200 dark:border-gray-700/80"></div>
        <span class="flex-shrink mx-4 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">
            Atau masuk dengan
        </span>
        <div class="flex-grow border-t border-gray-200 dark:border-gray-700/80"></div>
    </div>

    <div class="flex gap-3">
        <x-filament::button
            tag="a"
            href="{{ route('phone-login') }}"
            color="gray"
            :outlined="true"
            class="flex-1"
        >
            <span class="flex items-center justify-center gap-3">
                <svg class="whatsapp-login-icon size-5 shrink-0" viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M13.601 2.326A7.85 7.85 0 0 0 7.994 0C3.627 0 .068 3.558.064 7.926c0 1.399.366 2.76 1.043 3.963L0 16l4.204-1.102a7.9 7.9 0 0 0 3.79.965h.004c4.368 0 7.926-3.558 7.93-7.93A7.9 7.9 0 0 0 13.6 2.326zM7.994 14.521a6.6 6.6 0 0 1-3.356-.92l-.24-.144-2.494.654.666-2.433-.156-.251a6.56 6.56 0 0 1-1.007-3.505c0-3.626 2.957-6.584 6.591-6.584a6.56 6.56 0 0 1 4.66 1.931 6.56 6.56 0 0 1 1.928 4.66c-.004 3.639-2.961 6.592-6.592 6.592m3.615-4.934c-.197-.099-1.17-.578-1.353-.646-.182-.065-.315-.099-.445.099-.133.197-.513.646-.627.78-.114.133-.232.148-.43.05-.197-.1-.836-.308-1.592-.985-.59-.525-.99-1.174-1.105-1.372-.114-.198-.011-.304.088-.403.087-.088.197-.232.296-.346.1-.114.133-.198.198-.33.065-.134.034-.248-.015-.347-.05-.099-.445-1.076-.612-1.47-.16-.389-.323-.335-.445-.34-.114-.007-.247-.007-.38-.007a.73.73 0 0 0-.529.247c-.182.198-.691.677-.691 1.654s.71 1.916.81 2.049c.098.133 1.394 2.132 3.383 2.992.47.205.84.326 1.129.418.475.152.906.13 1.25.08.381-.058 1.171-.48 1.338-.941.164-.464.164-.86.114-.941-.049-.084-.182-.133-.38-.232"/>
                </svg>
                <span class="font-semibold text-gray-900 dark:text-white">WhatsApp</span>
            </span>
        </x-filament::button>
    </div>
</div>
```

- [ ] **Step 2: Create auth layout + partials**

Copy structure from helpdesk:

`resources/views/auth/layout.blade.php` — Filament base + Mekaya auth-card yielding content (same as helpdesk).

`resources/views/auth/partials/header.blade.php` — icon, title, description, back link (same as helpdesk).

`resources/views/auth/partials/submit-button.blade.php` — full-width primary submit (same as helpdesk).

`resources/views/filament/auth/phone-login.blade.php` — Mekaya auth-card with DevicePhoneMobile icon + heading/subheading + `$this->content` (same as helpdesk).

- [ ] **Step 3: Register render hook in `AdminPanelProvider`**

Add another `->renderHook(...)` (keep existing `HEAD_END` hook):

```php
->renderHook(
    PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
    fn () => view('auth.login-extra'),
)
```

- [ ] **Step 4: Add WhatsApp icon CSS**

Append to `resources/css/app.css`:

```css
.whatsapp-login-icon {
    fill: #25d366;
}
```

If Vite assets are required for admin CSS, run `npm run build` (or `npm run dev`) after this change.

- [ ] **Step 5: Commit**

```bash
git add resources/views/auth resources/views/filament/auth/phone-login.blade.php app/Providers/Filament/AdminPanelProvider.php resources/css/app.css
git commit -m "$(cat <<'EOF'
feat: add WhatsApp button and auth shells on Filament login

EOF
)"
```

---

### Task 4: Phone login page + routes

**Files:**
- Create: `app/Filament/Auth/Pages/PhoneLogin.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/PhoneOtpLoginTest.php` (created in Task 6; for now manual smoke OK)

- [ ] **Step 1: Create `PhoneLogin` Livewire page**

Create `app/Filament/Auth/Pages/PhoneLogin.php` based on helpdesk’s page, adapted for SAM:

```php
<?php

namespace App\Filament\Auth\Pages;

use App\Filament\Auth\Concerns\InteractsWithWhatsAppLogin;
use App\Models\WhatsappOtp;
use App\Services\WhatsAppOtpService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SimplePage;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Concerns\RestrictsFileUploadsToSchemaComponents;
use Filament\Schemas\Schema;
use Filament\Support\Facades\FilamentIcon;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsIconAlias;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * @property-read Action $loginAction
 * @property-read Schema $form
 */
class PhoneLogin extends SimplePage
{
    use InteractsWithWhatsAppLogin;
    use RestrictsFileUploadsToSchemaComponents;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        if (Filament::getCurrentPanel() === null) {
            Filament::setCurrentPanel(Filament::getPanel('admin'));
            Filament::bootCurrentPanel();
        }

        if (Filament::auth()->check()) {
            redirect()->intended(Filament::getUrl());
        }

        $this->maxWidth = 'full';
        $this->form->fill();
    }

    public function send(WhatsAppOtpService $otpService): void
    {
        $data = $this->form->getState();
        $number = $this->normalizeWhatsAppNumber($data['whatsapp_number'] ?? null);

        if (! $number) {
            throw ValidationException::withMessages([
                'data.whatsapp_number' => 'Nomor WhatsApp tidak valid.',
            ]);
        }

        $rateKey = 'whatsapp-otp:filament:'.md5($number).':'.request()->ip();

        if (RateLimiter::tooManyAttempts($rateKey, 3)) {
            throw ValidationException::withMessages([
                'data.whatsapp_number' => 'Terlalu banyak permintaan OTP. Coba lagi sebentar lagi.',
            ]);
        }

        RateLimiter::hit($rateKey, 300);

        $user = $this->findEligibleWebUserByWhatsApp($number);

        if (! $user) {
            throw ValidationException::withMessages([
                'data.whatsapp_number' => $this->unavailableWhatsAppMessage(),
            ]);
        }

        try {
            $otpService->issue($user, $number, WhatsappOtp::PURPOSE_LOGIN);
        } catch (RuntimeException|Throwable) {
            throw ValidationException::withMessages([
                'data.whatsapp_number' => 'OTP belum bisa dikirim ke WhatsApp. Coba lagi sebentar lagi.',
            ]);
        }

        session()->flashInput(['phone' => $number]);
        session()->flash('status', 'Kode OTP sudah dikirim ke WhatsApp.');

        $this->redirect(route('phone-login.verify'));
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('whatsapp_number')
                ->label('Nomor WhatsApp')
                ->tel()
                ->required()
                ->maxLength(30)
                ->autocomplete('tel')
                ->autofocus()
                ->helperText('Contoh: 081234567890'),
        ]);
    }

    public function loginAction(): Action
    {
        return Action::make('login')
            ->link()
            ->label('Kembali ke halaman masuk')
            ->icon(match (__('filament-panels::layout.direction')) {
                'rtl' => FilamentIcon::resolve(PanelsIconAlias::PAGES_PASSWORD_RESET_REQUEST_PASSWORD_RESET_ACTIONS_LOGIN_RTL) ?? Heroicon::ArrowRight,
                default => FilamentIcon::resolve(PanelsIconAlias::PAGES_PASSWORD_RESET_REQUEST_PASSWORD_RESET_ACTIONS_LOGIN) ?? Heroicon::ArrowLeft,
            })
            ->url(filament()->getLoginUrl());
    }

    public function getTitle(): string | Htmlable
    {
        return 'Masuk dengan WhatsApp';
    }

    public function getHeading(): string | Htmlable | null
    {
        return 'Masuk dengan WhatsApp';
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('send')
                ->label('Kirim OTP WhatsApp')
                ->submit('send'),
        ];
    }

    protected function hasFullWidthFormActions(): bool
    {
        return true;
    }

    public function getSubheading(): string | Htmlable | null
    {
        if (! filament()->hasLogin()) {
            return null;
        }

        return $this->loginAction;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('send')
                ->footer([
                    Actions::make($this->getFormActions())
                        ->alignment($this->getFormActionsAlignment())
                        ->fullWidth($this->hasFullWidthFormActions())
                        ->key('form-actions'),
                ]),
        ]);
    }

    public function getView(): string
    {
        return 'filament.auth.phone-login';
    }

    public function hasLogo(): bool
    {
        return false;
    }
}
```

- [ ] **Step 2: Register routes in `routes/web.php`**

Add imports and routes (keep existing routes):

```php
use App\Filament\Auth\Pages\PhoneLogin;
use App\Http\Controllers\Auth\PhoneOtpLoginController;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::get('/phone-login', PhoneLogin::class)
    ->middleware([
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
        StartSession::class,
        AuthenticateSession::class,
        ShareErrorsFromSession::class,
        VerifyCsrfToken::class,
        SubstituteBindings::class,
        DisableBladeIconComponents::class,
        DispatchServingFilamentEvent::class,
    ])
    ->name('phone-login');

Route::get('/phone-login/verify', [PhoneOtpLoginController::class, 'showVerifyForm'])
    ->middleware('guest')
    ->name('phone-login.verify');

Route::post('/phone-login/verify', [PhoneOtpLoginController::class, 'verifyOtp'])
    ->middleware(['guest', 'throttle:whatsapp-verify'])
    ->name('phone-login.verify.submit');
```

Note: `PhoneOtpLoginController` is created in Task 5 — if you commit mid-task, create a stub controller first or finish Task 5 in the same commit.

- [ ] **Step 3: Smoke-check route registration**

Run: `php artisan route:list --name=phone-login`

Expected: three routes listed (`phone-login`, `phone-login.verify`, `phone-login.verify.submit`)

- [ ] **Step 4: Commit (with Task 5 if controller not yet present)**

Prefer combining Tasks 4–5 into one commit if the controller is required for route:list / boot. Otherwise commit after Task 5.

---

### Task 5: OTP verify controller + view

**Files:**
- Create: `app/Http/Controllers/Auth/PhoneOtpLoginController.php`
- Create: `resources/views/auth/phone-login-verify.blade.php`

- [ ] **Step 1: Create controller**

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Filament\Auth\Concerns\InteractsWithWhatsAppLogin;
use App\Http\Controllers\Controller;
use App\Models\WhatsappOtp;
use App\Services\WhatsAppOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PhoneOtpLoginController extends Controller
{
    use InteractsWithWhatsAppLogin;

    public function showVerifyForm(Request $request): View
    {
        if (filament()->getCurrentPanel() === null) {
            filament()->setCurrentPanel(filament()->getPanel('admin'));
            filament()->bootCurrentPanel();
        }

        return view('auth.phone-login-verify', [
            'phone' => old('phone', session()->getOldInput('phone', '')),
        ]);
    }

    public function verifyOtp(Request $request, WhatsAppOtpService $otpService): RedirectResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'otp' => ['required', 'digits:6'],
        ]);

        $number = $this->normalizeWhatsAppNumber($validated['phone']);
        $user = $number ? $this->findEligibleWebUserByWhatsApp($number) : null;

        if (! $user || ! $number) {
            return back()
                ->withInput()
                ->withErrors(['phone' => $this->unavailableWhatsAppMessage()]);
        }

        $otpRecord = $otpService->verify(
            $user,
            $number,
            WhatsappOtp::PURPOSE_LOGIN,
            $validated['otp'],
        );

        if (! $otpRecord) {
            return back()
                ->withInput()
                ->withErrors(['otp' => 'OTP tidak valid atau sudah kedaluwarsa.']);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect('/admin');
    }
}
```

(`flashInput` from Livewire `send()` populates old input for the verify page.)

- [ ] **Step 2: Create verify Blade view**

Create `resources/views/auth/phone-login-verify.blade.php` mirroring helpdesk (OTP field, hidden `phone`, status flash, submit “Verifikasi & Login”, back to `route('phone-login')`). Use `old('phone', $phone)` in description and hidden input.

- [ ] **Step 3: Commit Tasks 4–5 together**

```bash
git add app/Filament/Auth/Pages/PhoneLogin.php app/Http/Controllers/Auth/PhoneOtpLoginController.php routes/web.php resources/views/auth/phone-login-verify.blade.php
git commit -m "$(cat <<'EOF'
feat: add Filament WhatsApp phone login and OTP verify flow

EOF
)"
```

---

### Task 6: Feature tests (TDD catch-up / verification)

**Files:**
- Create: `tests/Feature/PhoneOtpLoginTest.php`
- Re-run: `tests/Feature/WhatsAppAuthApiTest.php`

Because UI/routes already exist after Tasks 4–5, write tests that lock behavior. If a test fails, fix the implementation before committing.

- [ ] **Step 1: Write `tests/Feature/PhoneOtpLoginTest.php`**

Use Pest style to match `WhatsAppAuthApiTest.php`. Seed roles via factories (`Role::factory` with `can_access_web`).

```php
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
```

`tests/Pest.php` already applies `RefreshDatabase` to Feature tests — do not duplicate `uses(RefreshDatabase::class)` here.

- [ ] **Step 2: Run Filament WhatsApp login tests**

Run: `php artisan test --compact tests/Feature/PhoneOtpLoginTest.php`

Expected: all PASS. If form error keys differ (`data.whatsapp_number` vs `whatsapp_number`), adjust assertions to match Livewire/Filament actual keys.

- [ ] **Step 3: Re-run API WhatsApp tests**

Run: `php artisan test --compact tests/Feature/WhatsAppAuthApiTest.php`

Expected: all PASS

- [ ] **Step 4: Manual browser check**

1. Open `/admin/login` — see WhatsApp button under form.
2. Click WhatsApp → `/phone-login`.
3. With a verified web user in local DB, request OTP (gateway must be configured outside `testing`).
4. Confirm verify page styling matches Mekaya login card.

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/PhoneOtpLoginTest.php
git commit -m "$(cat <<'EOF'
test: cover Filament WhatsApp OTP login flow

EOF
)"
```

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| WhatsApp button on `/admin/login` (no Google) | Task 3 |
| `/phone-login` phone entry | Task 4 |
| `/phone-login/verify` OTP + session login | Task 5 |
| Verified WhatsApp + `canAccessPanel` | Task 2 |
| Reuse `WhatsappOtp` + gateway | Tasks 1, 4, 5 |
| Thin shared OTP extract | Task 1 |
| Throttle verify / OTP request | Tasks 2, 4, 5 |
| Feature tests + API tests green | Task 6 |
| Out of scope: Google, API contract changes | Not planned |

---

## Execution handoff

Plan complete and saved to `docs/superpowers/plans/2026-07-09-filament-whatsapp-login.md`.

**Two execution options:**

1. **Subagent-Driven (recommended)** — fresh subagent per task, review between tasks, fast iteration  
2. **Inline Execution** — execute tasks in this session with executing-plans and checkpoints  

Which approach?
