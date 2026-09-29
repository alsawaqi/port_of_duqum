<?php

namespace App\Libraries;

/**
 * Authenticates the exact bytes of an internal notification request.
 *
 * Nonce persistence is deliberately supplied by the caller so signature
 * verification remains independently testable and replay claims can be made
 * atomically in the database.
 */
final class Notification_request_authenticator
{
    public const MAX_CLOCK_SKEW_SECONDS = 300;
    public const MAX_BODY_BYTES = 65536;

    public static function sign(string $secret, int $timestamp, string $nonce, string $body): string
    {
        return hash_hmac('sha256', self::message($timestamp, $nonce, $body), $secret);
    }

    /**
     * @param callable(string, int): bool $claimNonce Receives the SHA-256 nonce
     *        hash and its Unix expiry. It must return false for a replay.
     */
    public static function authenticate(
        string $secret,
        string $timestamp,
        string $nonce,
        string $signature,
        string $body,
        callable $claimNonce,
        ?int $now = null
    ): bool {
        $now = $now ?? time();
        if (strlen($secret) < 32 || strlen($body) > self::MAX_BODY_BYTES) {
            return false;
        }
        if (!preg_match('/^\d{10}$/D', $timestamp)) {
            return false;
        }

        $issuedAt = (int) $timestamp;
        if (abs($now - $issuedAt) > self::MAX_CLOCK_SKEW_SECONDS) {
            return false;
        }
        if (!preg_match('/^[A-Za-z0-9_-]{32,128}$/D', $nonce)) {
            return false;
        }
        if (!preg_match('/^[a-f0-9]{64}$/Di', $signature)) {
            return false;
        }

        $expected = self::sign($secret, $issuedAt, $nonce, $body);
        if (!hash_equals($expected, strtolower($signature))) {
            return false;
        }

        $expiresAt = max($now, $issuedAt) + self::MAX_CLOCK_SKEW_SECONDS;
        return (bool) $claimNonce(hash('sha256', $nonce), $expiresAt);
    }

    private static function message(int $timestamp, string $nonce, string $body): string
    {
        return $timestamp . "\n" . $nonce . "\n" . $body;
    }
}
