<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Authentication controls that can be overridden with environment variables.
 *
 * MFA is deliberately off until both AUTH_SECURITY_MFA_ENABLED and a provider
 * are configured. For the bundled email provider, the application's outbound
 * mail transport must also be configured and tested before enabling it.
 */
class AuthSecurity extends BaseConfig
{
    public int $passwordMinLength = 10;
    public int $passwordMaxLength = 72;

    public int $loginFailureWindowSeconds = 900;
    public int $loginFailureLimit = 5;
    public int $loginLockoutSeconds = 900;

    public int $resetTokenLifetimeSeconds = 3600;
    public int $resetRequestWindowSeconds = 900;
    public int $resetRequestIdentityLimit = 3;
    public int $resetRequestIpLimit = 20;

    public bool $mfaEnabled = false;
    public string $mfaProvider = 'null';
    public array $mfaProvidersByUserType = [];
    public array $mfaRequiredUserTypes = ['staff'];
    public int $mfaCodeLength = 6;
    public int $mfaLifetimeSeconds = 300;
    public int $mfaMaxAttempts = 5;

    /**
     * Set through AUTH_SECURITY_MFA_HMAC_KEY to at least 32 random bytes.
     * It is never committed with a default value because it protects the
     * low-entropy one-time codes if the challenge table is disclosed.
     */
    public string $mfaHmacKey = '';

    /** iBulk SMS credentials must only be supplied through deployment secrets. */
    public string $mfaIbulkEndpoint = 'https://www.ismartsms.net/iBulkSMS/HttpWS/SMSDynamicRefIntlAPI.aspx';
    public string $mfaIbulkUserId = '';
    public string $mfaIbulkPassword = '';
    public string $mfaIbulkHeader = '';
    public int $mfaIbulkConnectTimeoutSeconds = 3;
    public int $mfaIbulkTimeoutSeconds = 8;

    public function __construct()
    {
        parent::__construct();

        $this->mfaEnabled = filter_var(
            env('AUTH_SECURITY_MFA_ENABLED', $this->mfaEnabled),
            FILTER_VALIDATE_BOOL
        );
        $this->mfaProvider = strtolower(trim((string) env(
            'AUTH_SECURITY_MFA_PROVIDER',
            $this->mfaProvider
        )));
        $this->mfaHmacKey = (string) env('AUTH_SECURITY_MFA_HMAC_KEY', '');

        $providerMap = trim((string) env('AUTH_SECURITY_MFA_PROVIDER_MAP', ''));
        if ($providerMap !== '') {
            foreach (explode(',', $providerMap) as $mapping) {
                $parts = array_map('trim', explode(':', $mapping, 2));
                if (count($parts) !== 2) {
                    continue;
                }

                $userType = strtolower($parts[0]);
                $provider = strtolower($parts[1]);
                if ($userType !== '' && in_array($provider, ['email', 'ibulk', 'null'], true)) {
                    $this->mfaProvidersByUserType[$userType] = $provider;
                }
            }
        }

        $this->mfaIbulkEndpoint = trim((string) env(
            'AUTH_SECURITY_MFA_IBULK_ENDPOINT',
            $this->mfaIbulkEndpoint
        ));
        $this->mfaIbulkUserId = trim((string) env('AUTH_SECURITY_MFA_IBULK_USER_ID', ''));
        $this->mfaIbulkPassword = (string) env('AUTH_SECURITY_MFA_IBULK_PASSWORD', '');
        $this->mfaIbulkHeader = trim((string) env('AUTH_SECURITY_MFA_IBULK_HEADER', ''));
        $this->mfaIbulkConnectTimeoutSeconds = $this->boundedTimeout(
            env('AUTH_SECURITY_MFA_IBULK_CONNECT_TIMEOUT_SECONDS', 3),
            1,
            10,
            3
        );
        $this->mfaIbulkTimeoutSeconds = $this->boundedTimeout(
            env('AUTH_SECURITY_MFA_IBULK_TIMEOUT_SECONDS', 8),
            $this->mfaIbulkConnectTimeoutSeconds,
            20,
            8
        );

        $requiredTypes = trim((string) env('AUTH_SECURITY_MFA_USER_TYPES', ''));
        if ($requiredTypes !== '') {
            $this->mfaRequiredUserTypes = array_values(array_filter(array_map(
                static fn(string $type): string => strtolower(trim($type)),
                explode(',', $requiredTypes)
            )));
        }

        if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
            $this->validateProductionMfa();
        }
    }

    public function mfaProviderForUserType(string $userType): string
    {
        $normalized = strtolower(trim($userType));
        return $this->mfaProvidersByUserType[$normalized] ?? $this->mfaProvider;
    }

    private function boundedTimeout($value, int $minimum, int $maximum, int $default): int
    {
        $timeout = filter_var($value, FILTER_VALIDATE_INT);
        if ($timeout === false || $timeout < $minimum || $timeout > $maximum) {
            return $default;
        }

        return $timeout;
    }

    private function validateProductionMfa(): void
    {
        if (!$this->mfaEnabled) {
            return;
        }

        if (!in_array('staff', $this->mfaRequiredUserTypes, true)) {
            throw new \RuntimeException('Enabled production MFA must include staff accounts.');
        }

        if (strlen($this->mfaHmacKey) < 32) {
            throw new \RuntimeException(
                'Production AUTH_SECURITY_MFA_HMAC_KEY must contain at least 32 random bytes.'
            );
        }

        foreach ($this->mfaRequiredUserTypes as $userType) {
            $provider = $this->mfaProviderForUserType($userType);
            if (!in_array($provider, ['email', 'ibulk'], true)) {
                throw new \RuntimeException('Every production MFA user type requires an enabled provider.');
            }

            if ($provider === 'ibulk') {
                $scheme = strtolower((string) parse_url($this->mfaIbulkEndpoint, PHP_URL_SCHEME));
                if (
                    $scheme !== 'https'
                    || $this->mfaIbulkUserId === ''
                    || $this->mfaIbulkPassword === ''
                    || $this->mfaIbulkHeader === ''
                ) {
                    throw new \RuntimeException(
                        'Production iBulk MFA requires an HTTPS endpoint and complete deployment credentials.'
                    );
                }
            }
        }
    }
}
