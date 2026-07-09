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
