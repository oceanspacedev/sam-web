<?php

namespace App\Support;

class WhatsAppNumber
{
    public static function normalize(?string $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        if (str_starts_with($digits, '0')) {
            return '62'.substr($digits, 1);
        }

        if (str_starts_with($digits, '620')) {
            return '62'.substr($digits, 3);
        }

        return $digits;
    }

    public static function isValid(string $value): bool
    {
        return preg_match('/^628\d{8,12}$/', $value) === 1;
    }

    public static function mask(string $value): string
    {
        $length = strlen($value);

        if ($length <= 7) {
            return '+'.$value;
        }

        return '+'.substr($value, 0, 5).str_repeat('*', max(4, $length - 7)).substr($value, -2);
    }
}
