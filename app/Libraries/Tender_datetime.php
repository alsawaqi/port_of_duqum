<?php

namespace App\Libraries;

/** Strict local tender dates. Never let PHP silently roll an invalid date forward. */
class Tender_datetime
{
    public static function normalize($value, string $edge = 'end'): ?string
    {
        if (!is_scalar($value) && $value !== null) {
            return null;
        }
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $value = str_replace('T', ' ', $value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
            $value .= $edge === 'start' ? ' 00:00:00' : ' 23:59:59';
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/D', $value)) {
            $value .= ':00';
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $value) || (int) substr($value, 0, 4) < 1000) {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new \DateTimeZone('Asia/Muscat'));
        return $date && $date->format('Y-m-d H:i:s') === $value ? $value : null;
    }
}
