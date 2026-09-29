<?php

namespace App\Libraries;

use CodeIgniter\Session\Session;

/**
 * Issues a short-lived, one-time proof that a security user actually looked
 * up the QR token before recording an entry, exit, or check action.
 */
final class Gate_pass_scan_authorizer
{
    private const SESSION_KEY = 'gate_pass_scan_authorizations';
    private const TTL_SECONDS = 180;
    private const MAX_PENDING = 12;

    private Session $session;

    public function __construct(?Session $session = null)
    {
        $this->session = $session ?: service('session');
    }

    public function issue(int $gatePassId, int $userId): string
    {
        if ($gatePassId < 1 || $userId < 1) {
            throw new \InvalidArgumentException('A gate pass and security user are required.');
        }

        $now = time();
        $pending = $this->activeAuthorizations($now);
        if (count($pending) >= self::MAX_PENDING) {
            uasort($pending, static fn(array $left, array $right): int =>
                (int)($left['issued_at'] ?? 0) <=> (int)($right['issued_at'] ?? 0)
            );
            $pending = array_slice($pending, -1 * (self::MAX_PENDING - 1), null, true);
        }

        $nonce = bin2hex(random_bytes(32));
        $pending[$this->authorizationKey($gatePassId, $userId)] = [
            'nonce_hash' => hash('sha256', $nonce),
            'issued_at' => $now,
            'expires_at' => $now + self::TTL_SECONDS,
        ];
        $this->session->set(self::SESSION_KEY, $pending);

        return $nonce;
    }

    public function consume(int $gatePassId, int $userId, string $nonce): bool
    {
        $now = time();
        $pending = $this->activeAuthorizations($now);
        $key = $this->authorizationKey($gatePassId, $userId);
        $authorization = $pending[$key] ?? null;

        // Consume before validating so a captured proof cannot be retried.
        unset($pending[$key]);
        $this->session->set(self::SESSION_KEY, $pending);

        $providedHash = hash('sha256', trim($nonce));
        return is_array($authorization)
            && isset($authorization['nonce_hash'])
            && hash_equals((string)$authorization['nonce_hash'], $providedHash);
    }

    private function activeAuthorizations(int $now): array
    {
        $pending = $this->session->get(self::SESSION_KEY);
        if (!is_array($pending)) {
            return [];
        }

        return array_filter($pending, static fn($item): bool =>
            is_array($item) && (int)($item['expires_at'] ?? 0) >= $now
        );
    }

    private function authorizationKey(int $gatePassId, int $userId): string
    {
        return $userId . ':' . $gatePassId;
    }
}
