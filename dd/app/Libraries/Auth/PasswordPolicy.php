<?php

namespace App\Libraries\Auth;

/**
 * Pure password-policy evaluator shared by every password mutation endpoint.
 */
final class PasswordPolicy
{
    /**
     * @return string[] Human-readable validation failures.
     */
    public static function errors(string $password, int $minimum, int $maximum): array
    {
        $length = function_exists('mb_strlen')
            ? mb_strlen($password, '8bit')
            : strlen($password);
        $errors = [];

        if ($length < $minimum || $length > $maximum) {
            $errors[] = "Password must be between {$minimum} and {$maximum} characters.";
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain a lowercase letter.';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain an uppercase letter.';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain a number.';
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Password must contain a special character.';
        }

        return $errors;
    }
}
