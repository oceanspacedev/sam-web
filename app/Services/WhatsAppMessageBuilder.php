<?php

namespace App\Services;

class WhatsAppMessageBuilder
{
    public function otpMessage(string $otp): string
    {
        $template = (string) config(
            'services.whatsapp.otp_message',
            'Kode OTP SAM Anda: {otp}. Berlaku 1 menit. Jangan bagikan kode ini kepada siapa pun.'
        );

        return str_replace('{otp}', $otp, $template);
    }

    public function accountRegisteredMessage(?string $name = null): string
    {
        $template = (string) config(
            'services.whatsapp.account_registered_message',
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
