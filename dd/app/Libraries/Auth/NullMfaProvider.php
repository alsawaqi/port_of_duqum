<?php

namespace App\Libraries\Auth;

/**
 * Safe default: it cannot deliver a challenge and therefore cannot be enabled.
 */
final class NullMfaProvider implements MfaProviderInterface
{
    public function name(): string
    {
        return 'null';
    }

    public function isConfigured(): bool
    {
        return false;
    }

    public function normalizeDestination(string $destination): ?string
    {
        return null;
    }

    public function send(string $destination, string $code, int $lifetimeSeconds): bool
    {
        return false;
    }
}
