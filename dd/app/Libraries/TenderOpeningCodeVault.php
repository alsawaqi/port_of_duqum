<?php

namespace App\Libraries;

/**
 * Protects the short-lived 3-key tender opening codes at rest.
 *
 * Verification uses a dedicated keyed HMAC. AES-256-GCM ciphertext exists
 * only because the assigned committee role must be able to retrieve its own
 * short-lived code. Both independent keys are deployment secrets.
 */
final class TenderOpeningCodeVault
{
    public const HMAC_KEY_ENV = 'TENDER_OPENING_CODE_HMAC_KEY';
    public const ENCRYPTION_KEY_ENV = 'TENDER_OPENING_CODE_ENCRYPTION_KEY';
    private const MINIMUM_KEY_BYTES = 32;
    private const CIPHER = 'aes-256-gcm';
    private const ENVELOPE_VERSION = 'v1';

    private ?string $hmacKey = null;
    private ?string $encryptionKey = null;
    private ?string $configurationError = null;

    public function __construct()
    {
        $hmacMaterial = '';
        $encryptionMaterial = '';
        try {
            if (
                !function_exists('openssl_encrypt')
                || !function_exists('openssl_decrypt')
                || !in_array(self::CIPHER, openssl_get_cipher_methods(), true)
            ) {
                throw new TenderOpeningCodeVaultException(
                    'AES-256-GCM support from the OpenSSL extension is required.'
                );
            }

            $hmacMaterial = $this->environmentKey(self::HMAC_KEY_ENV);
            $encryptionMaterial = $this->environmentKey(self::ENCRYPTION_KEY_ENV);

            if (hash_equals($hmacMaterial, $encryptionMaterial)) {
                throw new TenderOpeningCodeVaultException('Tender opening keys must be independent.');
            }

            $this->hmacKey = hash('sha256', "hmac\0" . $hmacMaterial, true);
            $this->encryptionKey = hash('sha256', "encryption\0" . $encryptionMaterial, true);
        } catch (TenderOpeningCodeVaultException $e) {
            $this->configurationError = $e->getMessage();
            $this->hmacKey = null;
            $this->encryptionKey = null;
        } finally {
            $this->wipeSecretString($hmacMaterial);
            $this->wipeSecretString($encryptionMaterial);
        }
    }

    public function assertReady(): void
    {
        if ($this->hmacKey !== null && $this->encryptionKey !== null) {
            return;
        }

        $environment = defined('ENVIRONMENT') ? strtolower((string) ENVIRONMENT) : 'production';
        $prefix = $environment === 'production'
            ? 'Tender opening code protection is unavailable in production.'
            : 'Tender opening code protection is not configured.';

        $detail = $environment === 'production'
            ? ''
            : ' ' . ($this->configurationError ?: 'Dedicated keys are required.');
        throw new TenderOpeningCodeVaultException($prefix . $detail);
    }

    public function generateCode(): string
    {
        $this->assertReady();
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /** @return array{hash:string,ciphertext:string} */
    public function seal(#[\SensitiveParameter] string $code, string $context): array
    {
        $this->assertReady();
        $this->assertCode($code);
        $context = $this->normalizeContext($context);

        $nonce = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $code,
            self::CIPHER,
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $this->additionalAuthenticatedData($context),
            16
        );

        if (!is_string($ciphertext) || strlen($tag) !== 16) {
            throw new TenderOpeningCodeVaultException('Tender opening code encryption failed.');
        }

        return [
            'hash' => $this->codeHash($code, $context),
            'ciphertext' => implode('.', [
                self::ENVELOPE_VERSION,
                $this->base64UrlEncode($nonce),
                $this->base64UrlEncode($tag),
                $this->base64UrlEncode($ciphertext),
            ]),
        ];
    }

    public function verify(
        #[\SensitiveParameter] string $submittedCode,
        string $storedHash,
        string $context
    ): bool
    {
        $this->assertReady();
        $context = $this->normalizeContext($context);

        $validCode = preg_match('/^[0-9]{6}$/D', $submittedCode) === 1;
        $validStoredHash = preg_match('/^[a-f0-9]{64}$/D', strtolower($storedHash)) === 1;
        $candidate = $this->codeHash($validCode ? $submittedCode : '000000', $context);
        $expected = $validStoredHash ? strtolower($storedHash) : str_repeat('0', 64);

        // Always execute the constant-time comparison, including malformed input.
        $matches = hash_equals($expected, $candidate);
        return $validCode && $validStoredHash && $matches;
    }

    public function reveal(string $envelope, string $context): string
    {
        $this->assertReady();
        $context = $this->normalizeContext($context);
        $parts = explode('.', $envelope);

        if (count($parts) !== 4 || $parts[0] !== self::ENVELOPE_VERSION) {
            throw new TenderOpeningCodeVaultException('Invalid tender opening code envelope.');
        }

        $nonce = $this->base64UrlDecode($parts[1]);
        $tag = $this->base64UrlDecode($parts[2]);
        $ciphertext = $this->base64UrlDecode($parts[3]);
        if ($nonce === null || strlen($nonce) !== 12 || $tag === null || strlen($tag) !== 16 || $ciphertext === null) {
            throw new TenderOpeningCodeVaultException('Invalid tender opening code envelope.');
        }

        $code = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $this->additionalAuthenticatedData($context)
        );
        if (!is_string($code) || preg_match('/^[0-9]{6}$/D', $code) !== 1) {
            throw new TenderOpeningCodeVaultException('Tender opening code authentication failed.');
        }

        return $code;
    }

    private function codeHash(#[\SensitiveParameter] string $code, string $context): string
    {
        return hash_hmac(
            'sha256',
            "tender-opening-code\0" . self::ENVELOPE_VERSION . "\0" . $context . "\0" . $code,
            $this->hmacKey
        );
    }

    private function wipeSecretString(string &$value): void
    {
        if ($value !== '' && function_exists('sodium_memzero')) {
            sodium_memzero($value);
            return;
        }

        $value = '';
    }

    private function additionalAuthenticatedData(string $context): string
    {
        return 'tender-opening-code|' . self::ENVELOPE_VERSION . '|' . $context;
    }

    private function environmentKey(string $name): string
    {
        $value = trim((string) env($name, ''));
        if (preg_match('/(?:change[_-]?me|replace[_-]?me|placeholder|example)/i', $value) === 1) {
            throw new TenderOpeningCodeVaultException($name . ' contains a placeholder value.');
        }
        if (str_starts_with($value, 'base64:')) {
            $decoded = base64_decode(substr($value, 7), true);
            if ($decoded === false) {
                throw new TenderOpeningCodeVaultException($name . ' is not valid base64.');
            }
            $value = $decoded;
        }

        if (strlen($value) < self::MINIMUM_KEY_BYTES) {
            throw new TenderOpeningCodeVaultException($name . ' must contain at least 32 bytes.');
        }

        return $value;
    }

    private function assertCode(#[\SensitiveParameter] string $code): void
    {
        if (preg_match('/^[0-9]{6}$/D', $code) !== 1) {
            throw new TenderOpeningCodeVaultException('Tender opening codes must contain exactly six digits.');
        }
    }

    private function normalizeContext(string $context): string
    {
        $context = trim($context);
        if ($context === '' || strlen($context) > 190 || preg_match('/^[a-z0-9:_-]+$/D', $context) !== 1) {
            throw new TenderOpeningCodeVaultException('Invalid tender opening code context.');
        }
        return $context;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): ?string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_-]+$/D', $value) !== 1) {
            return null;
        }

        $padding = (4 - (strlen($value) % 4)) % 4;
        $decoded = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', $padding), true);
        return $decoded === false ? null : $decoded;
    }
}
