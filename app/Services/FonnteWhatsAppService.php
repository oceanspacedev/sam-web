<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class FonnteWhatsAppService
{
    public function sendOtp(string $target, string $otp): void
    {
        $this->sendMessage($target, $this->otpMessage($otp));
    }

    public function sendAccountRegistered(string $target, ?string $name = null): void
    {
        $this->sendMessage($target, $this->accountRegisteredMessage($name));
    }

    public function sendMessage(string $target, string $message): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $token = config('services.fonnte.token');

        if (! $token) {
            throw new RuntimeException('Token Fonnte belum dikonfigurasi.');
        }

        $response = Http::asForm()
            ->withHeaders([
                'Authorization' => $token,
            ])
            ->timeout((int) config('services.fonnte.timeout', 10))
            ->post((string) config('services.fonnte.endpoint'), [
                'target' => $target,
                'message' => $message,
                'countryCode' => (string) config('services.fonnte.country_code', '0'),
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Fonnte mengembalikan status HTTP '.$response->status().'.');
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException('Fonnte mengembalikan response tidak valid.');
        }

        $status = $payload['status'] ?? $payload['Status'] ?? null;

        if ($status !== true && $status !== 'true' && $status !== 1) {
            $reason = $payload['reason'] ?? $payload['detail'] ?? 'Fonnte gagal mengirim pesan.';

            throw new RuntimeException((string) $reason);
        }
    }

    protected function otpMessage(string $otp): string
    {
        $template = (string) config(
            'services.fonnte.otp_message',
            'Kode OTP SAM Anda: {otp}. Berlaku 1 menit. Jangan bagikan kode ini kepada siapa pun.'
        );

        return str_replace('{otp}', $otp, $template);
    }

    protected function accountRegisteredMessage(?string $name = null): string
    {
        $template = (string) config(
            'services.fonnte.account_registered_message',
            "Halo {name}, nomor WhatsApp Anda sudah terdaftar di SAM dan bisa digunakan untuk login aplikasi.\n\n{download_links}"
        );

        return strtr($template, [
            '{name}' => filled($name) ? (string) $name : 'User',
            '{download_links}' => $this->downloadLinksText(),
            '{android_url}' => $this->androidDownloadText(),
            '{ios_url}' => $this->iosDownloadText(),
        ]);
    }

    protected function downloadLinksText(): string
    {
        return implode("\n", [
            'Android: '.$this->androidDownloadText().'.',
            'iOS: '.$this->iosDownloadText().'.',
        ]);
    }

    protected function androidDownloadText(): string
    {
        $url = trim((string) config('services.sam_app.android_url', ''));

        return $url !== '' ? $url : 'silakan download melalui Google Play Store';
    }

    protected function iosDownloadText(): string
    {
        $url = trim((string) config('services.sam_app.ios_testflight_url', ''));

        return $url !== '' ? $url : 'silakan akses melalui TestFlight';
    }
}
