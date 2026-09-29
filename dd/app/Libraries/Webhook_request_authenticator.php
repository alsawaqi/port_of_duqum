<?php

namespace App\Libraries;

use CodeIgniter\HTTP\IncomingRequest;

/**
 * Verifies public webhook requests before controller code parses or acts on
 * their payload. Secrets are deployment-only and never accepted in a URL.
 */
final class Webhook_request_authenticator
{
    private const MAX_PAYLOAD_BYTES = 1048576;
    private const MIN_SECRET_BYTES = 32;

    public function isEnabled(): bool
    {
        return filter_var(
            env('PODC_LEGACY_WEBHOOKS_ENABLED', false),
            FILTER_VALIDATE_BOOL
        );
    }

    public function rawPayload(IncomingRequest $request): ?string
    {
        if (strtolower($request->getMethod()) !== 'post') {
            return null;
        }

        $payload = (string) $request->getBody();
        $declaredLength = (int) $request->getHeaderLine('Content-Length');
        if (
            $payload === ''
            || strlen($payload) > self::MAX_PAYLOAD_BYTES
            || $declaredLength > self::MAX_PAYLOAD_BYTES
        ) {
            return null;
        }

        return $payload;
    }

    public function verifyGithub(IncomingRequest $request, string $payload): bool
    {
        return $this->verifySha256Header(
            $payload,
            (string) $request->getHeaderLine('X-Hub-Signature-256'),
            (string) env('PODC_GITHUB_WEBHOOK_SECRET', '')
        );
    }

    public function verifyBitbucket(IncomingRequest $request, string $payload): bool
    {
        return $this->verifySha256Header(
            $payload,
            (string) $request->getHeaderLine('X-Hub-Signature'),
            (string) env('PODC_BITBUCKET_WEBHOOK_SECRET', '')
        );
    }

    /**
     * @return object|null Verified Stripe event, or null when verification
     *                     fails or the endpoint is not securely configured.
     */
    public function verifyStripe(
        IncomingRequest $request,
        string $payload,
        string $secretEnvironmentName
    ): ?object {
        $secret = trim((string) env($secretEnvironmentName, ''));
        $signature = trim((string) $request->getHeaderLine('Stripe-Signature'));
        if (!$this->hasStrongSecret($secret) || $signature === '') {
            return null;
        }

        require_once APPPATH . 'ThirdParty/Stripe/vendor/autoload.php';

        try {
            return \Stripe\Webhook::constructEvent($payload, $signature, $secret, 300);
        } catch (\Throwable $exception) {
            log_message('warning', 'Rejected Stripe webhook signature.');
            return null;
        }
    }

    private function verifySha256Header(string $payload, string $header, string $secret): bool
    {
        $secret = trim($secret);
        $header = strtolower(trim($header));
        if (!$this->hasStrongSecret($secret) || !preg_match('/\Asha256=([a-f0-9]{64})\z/', $header, $matches)) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $payload, $secret), $matches[1]);
    }

    private function hasStrongSecret(string $secret): bool
    {
        return strlen($secret) >= self::MIN_SECRET_BYTES;
    }
}
