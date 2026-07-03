<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\WhatsappOtp;
use App\Services\WhatsAppNotificationService;
use App\Support\WhatsAppNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Throwable;

class WhatsAppAuthController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        protected WhatsAppNotificationService $whatsApp
    ) {}

    public function requestLoginOtp(Request $request): JsonResponse
    {
        $number = $this->requestNumber($request);

        if (! $number) {
            return $this->validationError([
                'whatsapp_number' => ['Nomor WhatsApp tidak valid.'],
            ]);
        }

        $user = User::query()
            ->where('whatsapp_number', $number)
            ->whereNotNull('whatsapp_verified_at')
            ->first();

        if (! $user) {
            return $this->loginUnavailableResponse();
        }

        return $this->issueOtp($user, $number, WhatsappOtp::PURPOSE_LOGIN);
    }

    public function verifyLoginOtp(Request $request): JsonResponse
    {
        $number = $this->requestNumber($request);
        $otp = $this->requestOtp($request);

        if (! $number) {
            return $this->validationError([
                'whatsapp_number' => ['Nomor WhatsApp tidak valid.'],
            ]);
        }

        if (! $otp) {
            return $this->validationError([
                'otp' => ['OTP harus berupa 6 digit angka.'],
            ]);
        }

        if ($request->filled('version') && version_compare((string) $request->input('version'), '2.0.0', '<')) {
            return $this->validationError([
                'version' => ['Login gagal. Silakan update aplikasi SAM Anda ke versi minimal 2.0.0 melalui Google Play Store.'],
            ]);
        }

        $user = User::query()
            ->with(['regions', 'clusters', 'role', 'divisis', 'badanUsahas'])
            ->where('whatsapp_number', $number)
            ->whereNotNull('whatsapp_verified_at')
            ->first();

        if (! $user) {
            return $this->loginUnavailableResponse();
        }

        $otpRecord = $this->verifyStoredOtp($user, $number, WhatsappOtp::PURPOSE_LOGIN, $otp);

        if (! $otpRecord) {
            return $this->invalidOtpResponse();
        }

        if ($request->filled('notif_id')) {
            $user->forceFill(['id_notif' => $request->input('notif_id')])->save();
        }

        $user->tokens()->delete();

        $tokenResult = $user->createToken('authToken')->plainTextToken;
        $user->refresh()->load(['regions', 'clusters', 'role', 'divisis', 'badanUsahas']);

        return response()->json([
            'meta' => [
                'code' => 200,
                'status' => 'success',
                'message' => 'Login berhasil.',
            ],
            'data' => [
                'access_token' => $tokenResult,
                'token_type' => 'Bearer',
                'user' => new UserResource($user),
            ],
            'errors' => null,
        ]);
    }

    public function requestProfileOtp(Request $request): JsonResponse
    {
        $user = $request->user();
        $number = $this->requestNumber($request, allowAlias: true);

        if (! $number) {
            return $this->validationError([
                'whatsapp_number' => ['Nomor WhatsApp tidak valid.'],
            ]);
        }

        if ($this->numberIsUsedByAnotherUser($number, (int) $user->id)) {
            return $this->validationError([
                'whatsapp_number' => ['Nomor WhatsApp sudah digunakan.'],
            ]);
        }

        return $this->issueOtp($user, $number, WhatsappOtp::PURPOSE_PROFILE_UPDATE);
    }

    public function verifyProfileOtp(Request $request): JsonResponse
    {
        $user = $request->user();
        $number = $this->requestNumber($request, allowAlias: true);
        $otp = $this->requestOtp($request);

        if (! $number) {
            return $this->validationError([
                'whatsapp_number' => ['Nomor WhatsApp tidak valid.'],
            ]);
        }

        if (! $otp) {
            return $this->validationError([
                'otp' => ['OTP harus berupa 6 digit angka.'],
            ]);
        }

        if ($this->numberIsUsedByAnotherUser($number, (int) $user->id)) {
            return $this->validationError([
                'whatsapp_number' => ['Nomor WhatsApp sudah digunakan.'],
            ]);
        }

        $otpRecord = $this->verifyStoredOtp($user, $number, WhatsappOtp::PURPOSE_PROFILE_UPDATE, $otp);

        if (! $otpRecord) {
            return $this->invalidOtpResponse();
        }

        $user->forceFill([
            'whatsapp_number' => $number,
            'whatsapp_verified_at' => now(),
        ])->save();

        $user->refresh()->load(['clusters', 'regions', 'role', 'divisis', 'badanUsahas']);

        return response()->json([
            'meta' => [
                'code' => 200,
                'status' => 'success',
                'message' => 'WhatsApp berhasil diverifikasi.',
            ],
            'data' => new UserResource($user),
            'errors' => null,
        ]);
    }

    protected function issueOtp(User $user, string $number, string $purpose): JsonResponse
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
                'expires_in' => $expiresIn,
                'masked_number' => WhatsAppNumber::mask($number),
            ],
            'errors' => null,
        ]);
    }

    protected function verifyStoredOtp(User $user, string $number, string $purpose, string $otp): ?WhatsappOtp
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

    protected function requestNumber(Request $request, bool $allowAlias = false): ?string
    {
        $value = $request->input('whatsapp_number');

        if (! filled($value) && $allowAlias) {
            $value = $request->input('nomor_whatsapp');
        }

        $number = WhatsAppNumber::normalize(is_scalar($value) ? (string) $value : null);

        return WhatsAppNumber::isValid($number) ? $number : null;
    }

    protected function requestOtp(Request $request): ?string
    {
        $otp = preg_replace('/\s+/', '', (string) $request->input('otp', '')) ?? '';

        return preg_match('/^\d{6}$/', $otp) === 1 ? $otp : null;
    }

    protected function numberIsUsedByAnotherUser(string $number, int $userId): bool
    {
        return User::withTrashed()
            ->where('whatsapp_number', $number)
            ->whereKeyNot($userId)
            ->exists();
    }

    protected function loginUnavailableResponse(): JsonResponse
    {
        return $this->validationError([
            'whatsapp_number' => ['Nomor WhatsApp belum terdaftar atau belum aktif.'],
        ], 'Nomor WhatsApp belum terdaftar atau belum aktif.');
    }

    protected function invalidOtpResponse(): JsonResponse
    {
        return $this->validationError([
            'otp' => ['OTP tidak valid atau sudah kedaluwarsa.'],
        ]);
    }

    protected function validationError(array $errors, string $message = 'Validasi gagal.'): JsonResponse
    {
        return response()->json([
            'meta' => [
                'code' => 422,
                'status' => 'error',
                'message' => $message,
            ],
            'data' => null,
            'errors' => $errors,
        ], 422);
    }
}
