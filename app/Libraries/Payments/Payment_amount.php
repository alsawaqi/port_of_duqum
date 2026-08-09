<?php

namespace App\Libraries\Payments;

use InvalidArgumentException;

/** Exact string/decimal conversion; no floating point is used for charges. */
final class Payment_amount
{
    public static function toMinor(string $amount, int $exponent): int
    {
        $amount = trim($amount);
        if ($exponent < 0 || $exponent > 3 || preg_match('/^(0|[1-9][0-9]*)(?:\.([0-9]{1,3}))?$/', $amount, $matches) !== 1) {
            throw new InvalidArgumentException('The payment amount is invalid.');
        }

        $fraction = (string)($matches[2] ?? '');
        if (strlen($fraction) > $exponent && trim(substr($fraction, $exponent), '0') !== '') {
            throw new InvalidArgumentException('The configured provider cannot represent this amount exactly.');
        }

        $whole = ltrim($matches[1], '0');
        $whole = $whole === '' ? '0' : $whole;
        $fraction = str_pad(substr($fraction, 0, $exponent), $exponent, '0');
        $digits = ltrim($whole . $fraction, '0');
        $digits = $digits === '' ? '0' : $digits;
        if (strlen($digits) > 18 || (strlen($digits) === 18 && strcmp($digits, (string)PHP_INT_MAX) > 0)) {
            throw new InvalidArgumentException('The payment amount is too large.');
        }

        $minor = (int)$digits;
        if ($minor < 1) {
            throw new InvalidArgumentException('The payment amount must be greater than zero.');
        }

        return $minor;
    }

    public static function fromMinor(int $minor, int $exponent): string
    {
        if ($minor < 0 || $exponent < 0 || $exponent > 3) {
            throw new InvalidArgumentException('The minor-unit amount is invalid.');
        }
        if ($exponent === 0) {
            return (string)$minor;
        }

        $digits = str_pad((string)$minor, $exponent + 1, '0', STR_PAD_LEFT);
        return substr($digits, 0, -1 * $exponent) . '.' . substr($digits, -1 * $exponent);
    }
}
