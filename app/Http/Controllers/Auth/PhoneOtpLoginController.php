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
