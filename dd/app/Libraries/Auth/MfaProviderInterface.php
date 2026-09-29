<?php

namespace App\Libraries\Auth;

interface MfaProviderInterface
{
    public function name(): string;

    public function isConfigured(): bool;

    /** Return the canonical destination, or null when it is not deliverable. */
    public function normalizeDestination(string $destination): ?string;

    public function send(string $destination, string $code, int $lifetimeSeconds): bool;
}
