<?php

namespace App\Models;

use App\Libraries\Auth\MfaProviderFactory;
use App\Libraries\Auth\MfaProviderInterface;
use App\Libraries\Auth\OmanMobileNumber;
use App\Libraries\Auth\PasswordPolicy;
use Config\AuthSecurity;

/**
 * Persistent authentication controls and append-only security audit events.
 */
class Auth_security_model extends Crud_model
{
    private AuthSecurity $authConfig;

    public function __construct()
    {
        $this->authConfig = config('AuthSecurity');
        parent::__construct('auth_login_security');
    }

    public function config(): AuthSecurity
    {
        return $this->authConfig;
    }

    public function password_errors(string $password): array
    {
        return PasswordPolicy::errors(
            $password,
            $this->authConfig->passwordMinLength,
            $this->authConfig->passwordMaxLength
        );
    }

    public function identity_hash(string $email): string
    {
        return hash('sha256', strtolower(trim($email)));
    }

    public function ip_hash(string $ipAddress): string
    {
        return hash('sha256', trim($ipAddress));
    }

    /**
     * @return array{locked: bool, retry_after: int}
     */
    public function login_lock_status(string $identityHash): array
    {
        try {
            $row = $this->db_builder
                ->select('locked_until')
                ->getWhere(['identity_hash' => $identityHash], 1)
                ->getRow();
        } catch (\Throwable $exception) {
            $this->logStorageFailure('read login lock status', $exception);
            return ['locked' => false, 'retry_after' => 0];
        }

        $lockedUntil = $this->utc_timestamp($row->locked_until ?? null);
        $remaining = max(0, $lockedUntil - time());

        return ['locked' => $remaining > 0, 'retry_after' => $remaining];
    }

    /**
     * Persist a failed attempt and return the resulting lock state.
     *
     * @return array{locked: bool, retry_after: int, failed_attempts: int}
     */
    public function record_login_failure(
        string $identityHash,
        string $ipHash,
        int $userId = 0
    ): array {
        $now = time();
        $failedAttempts = 1;
        $lockedUntil = null;

        try {
            $this->db->transBegin();
            $table = $this->db->prefixTable('auth_login_security');
            $row = $this->db->query(
                "SELECT * FROM {$table} WHERE identity_hash = ? FOR UPDATE",
                [$identityHash]
            )->getRow();

            if ($row) {
                $firstFailure = $this->utc_timestamp($row->first_failed_at ?? null);
                $withinWindow = $firstFailure > 0
                    && ($now - $firstFailure) <= $this->authConfig->loginFailureWindowSeconds;
                $failedAttempts = $withinWindow
                    ? ((int) ($row->failed_attempts ?? 0) + 1)
                    : 1;
                $firstFailedAt = $withinWindow
                    ? (string) $row->first_failed_at
                    : gmdate('Y-m-d H:i:s', $now);
            } else {
                $firstFailedAt = gmdate('Y-m-d H:i:s', $now);
            }

            if ($failedAttempts >= $this->authConfig->loginFailureLimit) {
                $lockedUntil = gmdate(
                    'Y-m-d H:i:s',
                    $now + $this->authConfig->loginLockoutSeconds
                );
            }

            $data = [
                'user_id' => $userId ?: ($row->user_id ?? null),
                'failed_attempts' => $failedAttempts,
                'first_failed_at' => $firstFailedAt,
                'last_failed_at' => gmdate('Y-m-d H:i:s', $now),
                'locked_until' => $lockedUntil,
                'last_ip_hash' => $ipHash,
                'updated_at' => gmdate('Y-m-d H:i:s', $now),
            ];

            if ($row) {
                $this->db_builder->where('identity_hash', $identityHash)->update($data);
            } else {
                $data['identity_hash'] = $identityHash;
                $this->db_builder->insert($data);
            }

            if ($this->db->transStatus() === false) {
                $this->db->transRollback();
                throw new \RuntimeException('The login failure transaction failed.');
            }
            $this->db->transCommit();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            $this->logStorageFailure('record login failure', $exception);
            return ['locked' => false, 'retry_after' => 0, 'failed_attempts' => 0];
        }

        return [
            'locked' => $lockedUntil !== null,
            'retry_after' => $lockedUntil
                ? max(1, $this->utc_timestamp($lockedUntil) - $now)
                : 0,
            'failed_attempts' => $failedAttempts,
        ];
    }

    public function clear_login_failures(string $identityHash, int $userId): void
    {
        try {
            $this->db_builder
                ->where('identity_hash', $identityHash)
                ->update([
                    'user_id' => $userId,
                    'failed_attempts' => 0,
                    'first_failed_at' => null,
                    'last_failed_at' => null,
                    'locked_until' => null,
                    'updated_at' => gmdate('Y-m-d H:i:s'),
                ]);
        } catch (\Throwable $exception) {
            $this->logStorageFailure('clear login failures', $exception);
        }
    }

    public function mfa_is_required(object $user): bool
    {
        $userType = $this->mfa_user_type($user);
        $storedUserType = strtolower(trim((string) ($user->user_type ?? '')));
        return $this->authConfig->mfaEnabled
            && (
                in_array('*', $this->authConfig->mfaRequiredUserTypes, true)
                || in_array($userType, $this->authConfig->mfaRequiredUserTypes, true)
                || in_array($storedUserType, $this->authConfig->mfaRequiredUserTypes, true)
            );
    }

    public function mfa_provider(?object $user = null): MfaProviderInterface
    {
        $providerName = $user
            ? $this->authConfig->mfaProviderForUserType($this->mfa_user_type($user))
            : $this->authConfig->mfaProvider;
        return MfaProviderFactory::make($providerName, $this->authConfig);
    }

    public function mfa_destination(
        object $user,
        ?MfaProviderInterface $provider = null
    ): ?string {
        $provider = $provider ?? $this->mfa_provider($user);
        $rawDestination = in_array($provider->name(), ['ibulk', 'ismartsms'], true)
            ? (string) ($user->phone ?? '')
            : (string) ($user->email ?? '');

        return $provider->normalizeDestination($rawDestination);
    }

    public function mfa_destination_hint(
        object $user,
        ?MfaProviderInterface $provider = null
    ): string {
        $provider = $provider ?? $this->mfa_provider($user);
        $destination = $this->mfa_destination($user, $provider);
        if ($destination === null) {
            return in_array($provider->name(), ['ibulk', 'ismartsms'], true)
                ? 'registered mobile number'
                : 'registered address';
        }

        if (in_array($provider->name(), ['ibulk', 'ismartsms'], true)) {
            return OmanMobileNumber::mask($destination);
        }

        $parts = explode('@', $destination, 2);
        if (count($parts) !== 2) {
            return 'registered address';
        }
        $visible = substr($parts[0], 0, min(2, strlen($parts[0])));
        return $visible . str_repeat('*', max(2, strlen($parts[0]) - strlen($visible)))
            . '@' . $parts[1];
    }

    public function mfa_configuration_error(object $user): ?string
    {
        if (!$this->mfa_is_required($user)) {
            return null;
        }

        $provider = $this->mfa_provider($user);
        if (!$provider->isConfigured() || $provider->name() === 'null') {
            return 'The configured MFA provider is not available.';
        }
        if ($this->mfa_destination($user, $provider) === null) {
            return 'The account does not have a valid destination for the configured MFA provider.';
        }
        if (strlen($this->authConfig->mfaHmacKey) < 32) {
            return 'AUTH_SECURITY_MFA_HMAC_KEY must contain at least 32 random bytes.';
        }

        return null;
    }

    private function mfa_user_type(object $user): string
    {
        return strtolower(trim((string) (
            $user->mfa_user_type ?? $user->user_type ?? ''
        )));
    }

    /**
     * Append a security event without storing passwords, tokens, OTPs, or raw
     * login identities in the audit context.
     */
    public function audit(
        string $eventType,
        string $outcome,
        int $userId = 0,
        string $identityHash = '',
        array $context = []
    ): void {
        try {
            $request = service('request');
            $this->db->table($this->db->prefixTable('auth_audit_events'))->insert([
                'user_id' => $userId ?: null,
                'event_type' => substr($eventType, 0, 64),
                'outcome' => substr($outcome, 0, 32),
                'identity_hash' => $identityHash ?: null,
                'ip_address' => substr((string) $request->getIPAddress(), 0, 45),
                'user_agent_hash' => hash(
                    'sha256',
                    $request->getUserAgent()->getAgentString()
                ),
                'context_json' => $context
                    ? json_encode($context, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)
                    : null,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $exception) {
            $this->logStorageFailure('append authentication audit event', $exception);
        }
    }

    private function utc_timestamp($value): int
    {
        if (!$value) {
            return 0;
        }

        $date = \DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            (string) $value,
            new \DateTimeZone('UTC')
        );

        return $date ? $date->getTimestamp() : 0;
    }

    private function logStorageFailure(string $operation, \Throwable $exception): void
    {
        log_message(
            'error',
            'Authentication security storage failed while attempting to {operation}: {message}',
            ['operation' => $operation, 'message' => $exception->getMessage()]
        );
    }
}
