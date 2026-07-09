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
