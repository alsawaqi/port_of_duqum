<?php

namespace App\Libraries\Auth;

final class OmanMobileNumber
{
    /**
     * Normalize an Oman mobile number to E.164. Oman mobile services use an
     * eight-digit national number beginning with 7 or 9.
     */
    public static function normalize(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || preg_match('/[^0-9+()\s.-]/', $value)) {
            return null;
        }
        if (substr_count($value, '+') > 1
            || (str_contains($value, '+') && !str_starts_with($value, '+'))
            || (str_starts_with($value, '+') && !preg_match('/^\+968(?:[\s().-]|[0-9])/', $value))) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);
        if (!is_string($digits)) {
            return null;
        }

        if (str_starts_with($digits, '00968')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 8) {
            $digits = '968' . $digits;
        }

        if (!preg_match('/^968[79][0-9]{7}$/D', $digits)) {
            return null;
        }

        return '+' . $digits;
    }

    public static function forGateway(string $value): ?string
    {
        $normalized = self::normalize($value);
        return $normalized === null ? null : substr($normalized, 1);
    }

    public static function mask(string $value): string
    {
        $normalized = self::normalize($value);
        if ($normalized === null) {
            return 'registered mobile number';
        }

        return '+968 **** ' . substr($normalized, -4);
    }
}
