# Filament Admin WhatsApp Login — Design Spec

**Date:** 2026-07-09  
**Project:** web-sam  
**Status:** Approved for planning

## Goal

Add a WhatsApp OTP login path to the Filament admin panel (`/admin/login`), matching the helpdesk UI pattern (button → phone page → OTP verify page) while reusing SAM’s existing WhatsApp OTP backend (`WhatsappOtp` + gateway).

## Decisions

| Decision | Choice |
|----------|--------|
| UI pattern | Helpdesk-style: separate pages for phone + OTP |
| Social providers | WhatsApp only (no Google) |
| Eligibility | Same as mobile API: verified `whatsapp_number` + panel access |
| OTP storage | Existing `whatsapp_otps` table (not helpdesk Cache) |
| Auth result | Filament session via `Auth::login` (not Sanctum token) |
| Implementation approach | Mirror helpdesk UI; reuse SAM OTP logic |

## Current state

- **Admin login:** `App\Filament\Pages\Auth\Login` — username + password, Mekaya theme.
- **Mobile WhatsApp login:** `WhatsAppAuthController` + `WhatsappOtp` + `WhatsAppNotificationService` (already production-ready).
- **Helpdesk reference:** render hook + `PhoneLogin` Livewire + `PhoneOtpLoginController` + Blade views.

## User flow

```
/admin/login
  │  username + password (unchanged)
  │  + “Atau masuk dengan” + WhatsApp button
  ▼
/phone-login
  │  enter WhatsApp number
  │  normalize via WhatsAppNumber
  │  require: matching whatsapp_number, whatsapp_verified_at set,
  │           User::canAccessPanel()
  │  issue OTP (purpose: login) via WhatsappOtp
  │  send via WhatsAppNotificationService
  ▼
/phone-login/verify
  │  enter 6-digit OTP
  │  verify WhatsappOtp (TTL, max 5 attempts)
  │  Auth::login($user, true) + session regenerate
  ▼
/admin
```

## UI

### Login page addition

- Register `PanelsRenderHook::AUTH_LOGIN_FORM_AFTER` in `AdminPanelProvider`.
- View `resources/views/auth/login-extra.blade.php`:
  - Divider: “Atau masuk dengan”
  - Single outlined Filament button linking to `route('phone-login')` with WhatsApp icon (green fill).
  - No Google button.

### Phone entry page

- Livewire Filament `SimplePage`: `App\Filament\Auth\Pages\PhoneLogin`.
- Field: WhatsApp number (tel), label in Indonesian.
- Action: “Kirim OTP WhatsApp”.
- Subheading/back link to Filament login.
- View shell: Mekaya auth card (mirror helpdesk `filament/auth/phone-login.blade.php`).

### OTP verify page

- Controller + Blade (mirror helpdesk hybrid): `PhoneOtpLoginController`.
- Fields: hidden/pre-filled phone, 6-digit OTP.
- Submit: verify → session login → `/admin`.
- Flash status when OTP was sent; back link to phone-login or login.

Visual language stays Mekaya/Filament (dark card, amber primary) — same as current SAM login.

## Backend

### Lookup rules

1. Normalize input with `WhatsAppNumber::normalize` / `isValid` (`628…`).
2. Find user where `whatsapp_number` matches and `whatsapp_verified_at` is not null.
3. Reject if user cannot access panel (`canAccessPanel()` / `role.can_access_web`).
4. On failure, use a generic message (avoid account enumeration), aligned with API wording: e.g. “Nomor WhatsApp belum terdaftar atau belum aktif.”

### OTP issue / verify

Reuse the same rules as `WhatsAppAuthController`:

- Purpose: `WhatsappOtp::PURPOSE_LOGIN`
- Invalidate prior unused OTPs for that number/purpose
- Store hashed OTP, `expires_at` from `config('services.whatsapp.otp_expires_in')`
- Send via `WhatsAppNotificationService::sendOtp`
- Verify: latest unverified record, not expired, `attempt_count < 5`, `Hash::check`
- On send failure: expire the OTP row and show “OTP belum bisa dikirim…”

Implementation preference: extract a thin shared helper (trait or small service) for issue/verify so Filament and API do not duplicate OTP rules. Keep the extract mechanical (move existing `issueOtp` / `verifyStoredOtp` behavior); do not redesign the API layer in this work.

### Session login

On successful OTP verify:

```php
Auth::login($user, true);
$request->session()->regenerate();
return redirect('/admin'); // or Filament::getUrl()
```

Do not issue Sanctum tokens on the web path.

### Routes (`routes/web.php`)

| Method | Path | Handler | Middleware notes |
|--------|------|---------|------------------|
| GET | `/phone-login` | `PhoneLogin` Livewire | Filament-compatible session/CSRF stack |
| GET | `/phone-login/verify` | `PhoneOtpLoginController@showVerifyForm` | guest |
| POST | `/phone-login/verify` | `PhoneOtpLoginController@verifyOtp` | guest + throttle `whatsapp-verify` |

Apply `throttle:whatsapp-otp` on the Livewire send path where practical (route middleware and/or rate limiter already defined in `AppServiceProvider`).

### Files to add / touch

**Add**

- `app/Filament/Auth/Pages/PhoneLogin.php`
- `app/Filament/Auth/Concerns/InteractsWithWhatsAppLogin.php` (lookup/normalize helpers)
- `app/Http/Controllers/Auth/PhoneOtpLoginController.php`
- `resources/views/auth/login-extra.blade.php`
- `resources/views/filament/auth/phone-login.blade.php`
- `resources/views/auth/phone-login-verify.blade.php`
- `resources/views/auth/layout.blade.php` + `resources/views/auth/partials/*` (as needed from helpdesk)
- `tests/Feature/PhoneOtpLoginTest.php` (or equivalent name)

**Modify**

- `app/Providers/Filament/AdminPanelProvider.php` — render hook
- `routes/web.php` — phone-login routes
- CSS/theme only if WhatsApp icon fill needs a small rule (like helpdesk `.whatsapp-login-icon`)

**Do not change**

- Mobile API contract (`/api/login/whatsapp/*`)
- Password login credentials (username + password)

## Error handling

| Case | Behavior |
|------|----------|
| Invalid number format | Validation on phone field |
| Unknown / unverified / no panel access | Generic unavailable message |
| WhatsApp send failure | User-facing retry message; OTP invalidated |
| Wrong OTP | “OTP tidak valid atau sudah kedaluwarsa.”; increment attempts |
| Max attempts / expired | Same invalid OTP message; force re-request |
| Already authenticated | Redirect to admin |

## Testing

Feature coverage:

1. Login page includes WhatsApp entry point (route/view hook reachable).
2. Request OTP happy path for verified web-capable user (gateway mocked).
3. Verify OTP logs user into Filament session and redirects to admin.
4. Reject unverified WhatsApp number.
5. Reject user without `can_access_web`.
6. Reject wrong / expired OTP.
7. Existing `WhatsAppAuthApiTest` remains green.

## Out of scope

- Google / Socialite login
- Large shared-service refactor beyond thin reuse of OTP issue/verify
- Changes to mobile app or API response shapes
- Password reset / email auth
- Self-registration via WhatsApp

## Success criteria

- Admin can log in with username/password as today.
- Admin with verified WhatsApp and web access can complete phone → OTP → dashboard.
- UI visually consistent with helpdesk’s WhatsApp path (SAM branding/theme preserved).
- OTP behavior and security controls align with existing mobile WhatsApp login.
