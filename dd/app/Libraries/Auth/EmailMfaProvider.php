<?php

namespace App\Libraries\Auth;

final class EmailMfaProvider implements MfaProviderInterface
{
    public function name(): string
    {
        return 'email';
    }

    public function isConfigured(): bool
    {
        return function_exists('send_app_mail');
    }

    public function normalizeDestination(string $destination): ?string
    {
        $normalized = strtolower(trim($destination));
        return filter_var($normalized, FILTER_VALIDATE_EMAIL) ? $normalized : null;
    }

    public function send(string $destination, string $code, int $lifetimeSeconds): bool
    {
        $destination = $this->normalizeDestination($destination);
        if ($destination === null) {
            return false;
        }

        $minutes = max(1, (int) ceil($lifetimeSeconds / 60));
        $safeCode = esc($code);
        $message = "Your Port of Duqm sign-in verification code is "
            . "<strong>{$safeCode}</strong>. It expires in {$minutes} minutes. "
            . 'If you did not try to sign in, you can ignore this message.';

        return (bool) send_app_mail(
            $destination,
            'Port of Duqm sign-in verification code',
            $message
        );
    }
}
