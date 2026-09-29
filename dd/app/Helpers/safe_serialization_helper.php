<?php

if (!function_exists('safe_unserialize')) {
    /**
     * Decode legacy scalar/array data without instantiating PHP objects.
     *
     * Invalid, empty, or non-string input fails closed as false, matching the
     * behavior callers historically expected from unserialize().
     *
     * @param mixed $value
     * @return mixed
     */
    function safe_unserialize($value)
    {
        if (!is_string($value) || $value === '') {
            return false;
        }

        try {
            return @\unserialize($value, ['allowed_classes' => false]);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
