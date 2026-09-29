<?php

namespace App\Libraries;

/** Session-bound, single-use OAuth authorization state protection. */
final class Oauth_state_guard
{
    private const SESSION_KEY = 'podc_oauth_states';
    private const LIFETIME_SECONDS = 600;
    private const MAX_STATES_PER_FLOW = 5;

    public function issue(string $flow, int $userId): string
    {
        $flow = $this->normalizeFlow($flow);
        if ($userId < 1) {
            throw new \InvalidArgumentException('OAuth state requires an authenticated user.');
        }

        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $states = $this->states();
        $now = time();
        $entries = array_values(array_filter(
            (array) ($states[$flow] ?? []),
            static fn($entry): bool => is_array($entry) && (int) ($entry['expires_at'] ?? 0) >= $now
        ));
        $entries[] = [
            'token_hash' => hash('sha256', $token),
            'user_id' => $userId,
            'expires_at' => $now + self::LIFETIME_SECONDS,
        ];
        $states[$flow] = array_slice($entries, -self::MAX_STATES_PER_FLOW);
        service('session')->set(self::SESSION_KEY, $states);

        return $token;
    }

    public function consume(string $flow, string $submittedState, int $userId): bool
    {
        $flow = $this->normalizeFlow($flow);
        $submittedState = trim($submittedState);
        if (
            $userId < 1
            || preg_match('/\A[A-Za-z0-9_-]{43}\z/D', $submittedState) !== 1
        ) {
            return false;
        }

        $states = $this->states();
        $entries = (array) ($states[$flow] ?? []);
        $candidateHash = hash('sha256', $submittedState);
        $now = time();
        $matched = false;
        $remaining = [];

        foreach ($entries as $entry) {
            if (!is_array($entry) || (int) ($entry['expires_at'] ?? 0) < $now) {
                continue;
            }

            $isMatch = (int) ($entry['user_id'] ?? 0) === $userId
                && is_string($entry['token_hash'] ?? null)
                && hash_equals($entry['token_hash'], $candidateHash);
            if ($isMatch && !$matched) {
                $matched = true;
                continue; // one-time use
            }
            $remaining[] = $entry;
        }

        if ($remaining) {
            $states[$flow] = $remaining;
        } else {
            unset($states[$flow]);
        }
        service('session')->set(self::SESSION_KEY, $states);

        return $matched;
    }

    private function states(): array
    {
        $states = service('session')->get(self::SESSION_KEY);
        return is_array($states) ? $states : [];
    }

    private function normalizeFlow(string $flow): string
    {
        $flow = strtolower(trim($flow));
        if (preg_match('/\A[a-z0-9_]{3,48}\z/D', $flow) !== 1) {
            throw new \InvalidArgumentException('Invalid OAuth flow identifier.');
        }
        return $flow;
    }
}
