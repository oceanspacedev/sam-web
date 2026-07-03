<?php

namespace App\Services;

use RuntimeException;

class WhatsAppNotificationService
{
    public function __construct(
        private readonly WhatsAppGateway $gateway,
        private readonly WhatsAppMessageBuilder $messages,
    ) {}

    public function sendOtp(string $target, string $otp): void
    {
        if (app()->environment('testing')) {
            return;
        }

        if (! $this->gateway->send($target, $this->messages->otpMessage($otp))) {
            throw new RuntimeException('Gagal mengirim OTP WhatsApp.');
        }
    }

    public function sendAccountRegistered(string $target, ?string $name = null): void
    {
        if (app()->environment('testing')) {
            return;
        }

        if (! $this->gateway->send($target, $this->messages->accountRegisteredMessage($name))) {
            throw new RuntimeException('Gagal mengirim notifikasi WhatsApp akun terdaftar.');
        }
    }
}
