<?php

if (!function_exists('csv_safe_cell')) {
    /**
     * Neutralize values spreadsheet programs could interpret as formulas.
     * CSV quoting alone does not stop formula execution.
     *
     * @param mixed $value
     */
    function csv_safe_cell($value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        } elseif (is_scalar($value) || $value instanceof \Stringable) {
            $value = (string) $value;
        } else {
            return '';
        }

        // Treat control/Unicode spacing followed by an operator as dangerous,
        // and also neutralize leading tab/newline characters themselves.
        $dangerous = preg_match(
            '/\A(?:[\x00-\x20\x7F\x{00A0}\x{FEFF}]*[=+\-@]|[\t\r\n])/u',
            $value
        );
        if ($dangerous === false) {
            $dangerous = preg_match('/\A(?:[\x00-\x20\x7F]*[=+\-@]|[\t\r\n])/', $value);
        }

        return $dangerous === 1 ? "'" . $value : $value;
    }
}

if (!function_exists('csv_safe_row')) {
    /** @param array<int, mixed> $row */
    function csv_safe_row(array $row): array
    {
        return array_map('csv_safe_cell', $row);
    }
}
