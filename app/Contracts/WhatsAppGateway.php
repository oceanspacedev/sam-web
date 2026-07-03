<?php

namespace App\Contracts;

interface WhatsAppGateway
{
    public function sendOtp(string $target, string $otp): void;

    public function sendAccountRegistered(string $target, ?string $name = null): void;
}
