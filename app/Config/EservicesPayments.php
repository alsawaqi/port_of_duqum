<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * E-service payment secrets are environment-only. An unconfigured deployment
 * fails closed instead of marking a fee as paid.
 */
class EservicesPayments extends BaseConfig
{
    private const SMARTPAY_UAT_GATEWAY_URL = 'https://spayuattrns.bmtest.om/transaction.do?command=initiateTransaction';
    private const SMARTPAY_PRODUCTION_GATEWAY_URL = 'https://smartpaytrns.bankmuscat.com/transaction.do?command=initiateTransaction';

    public string $provider;
    public string $currency;
    public string $stripeSecretKey;
    public string $stripeWebhookSecret;
    public int $checkoutTtlSeconds;
    public int $webhookToleranceSeconds;
    public string $smartpayEnvironment;
    public string $smartpayMerchantId;
    public string $smartpayAccessCode;
    public string $smartpayWorkingKey;
    public string $smartpayApiAccessCode;
    public string $smartpayApiWorkingKey;
    public string $smartpayPublicBaseUrl;
    public string $smartpayBankTimezone;
    public int $smartpayTimeoutSeconds;

    public function __construct()
    {
        parent::__construct();
        $this->provider = strtolower(trim((string)env('eservices.payment.provider', 'disabled')));
        // Four SMARTPAY_* entries are sufficient; retain an explicit provider
        // override (including disabled) for deployments using the older setup.
        if (env('eservices.payment.provider', null) === null) {
            foreach (['SMARTPAY_MERCHANT_ID', 'SMARTPAY_ACCESS_CODE', 'SMARTPAY_WORKING_KEY', 'SMARTPAY_GATEWAY_URL'] as $name) {
                if (env($name, null) !== null) {
                    $this->provider = 'bank_muscat';
                    break;
                }
            }
        }
        $this->currency = strtoupper(trim((string)env('eservices.payment.currency', 'OMR')));
        $this->stripeSecretKey = trim((string)env('eservices.payment.stripeSecretKey', ''));
        $this->stripeWebhookSecret = trim((string)env('eservices.payment.stripeWebhookSecret', ''));
        $this->checkoutTtlSeconds = max(1800, min(86400, (int)env('eservices.payment.checkoutTtlSeconds', 1800)));
        $this->webhookToleranceSeconds = max(60, min(900, (int)env('eservices.payment.webhookToleranceSeconds', 300)));
        $this->smartpayEnvironment = strtolower(trim((string)env('eservices.payment.smartpayEnvironment', 'uat')));
        $gatewayUrl = env('SMARTPAY_GATEWAY_URL', null);
        if ($gatewayUrl !== null) {
            // Only the bank's official checkout URLs are accepted. An invalid
            // explicit URL disables readiness instead of receiving credentials.
            $this->smartpayEnvironment = match (trim((string)$gatewayUrl)) {
                self::SMARTPAY_UAT_GATEWAY_URL => 'uat',
                self::SMARTPAY_PRODUCTION_GATEWAY_URL => 'production',
                default => '',
            };
        }
        // An explicitly blank new key must not silently reuse an old secret.
        $this->smartpayMerchantId = trim((string)env('SMARTPAY_MERCHANT_ID', env('eservices.payment.smartpayMerchantId', '')));
        $this->smartpayAccessCode = trim((string)env('SMARTPAY_ACCESS_CODE', env('eservices.payment.smartpayAccessCode', '')));
        $this->smartpayWorkingKey = trim((string)env('SMARTPAY_WORKING_KEY', env('eservices.payment.smartpayWorkingKey', '')));
        $this->smartpayApiAccessCode = trim((string)env('eservices.payment.smartpayApiAccessCode', ''));
        $this->smartpayApiWorkingKey = trim((string)env('eservices.payment.smartpayApiWorkingKey', ''));
        $this->smartpayPublicBaseUrl = rtrim(trim((string)env('eservices.payment.smartpayPublicBaseUrl', $this->configuredApplicationBaseUrl())), '/');
        // Bank Muscat UAT returns Oman local time (confirmed against hosted
        // handoff and Status API timestamps). Keep an override for other setups.
        $this->smartpayBankTimezone = trim((string)env('eservices.payment.smartpayBankTimezone', 'Asia/Muscat'));
        $this->smartpayTimeoutSeconds = max(5, min(30, (int)env('eservices.payment.smartpayTimeoutSeconds', 15)));
    }

    private function configuredApplicationBaseUrl(): string
    {
        // Use the configured application address, never the request Host header.
        foreach (['PODC_BASE_URL', 'APP_BASE_URL', 'app.baseURL'] as $name) {
            $value = trim((string)env($name, ''));
            if ($value !== '') {
                return $value;
            }
        }
        return '';
    }

    public function isReady(): bool
    {
        if ($this->provider === 'bank_muscat') {
            $url = parse_url($this->smartpayPublicBaseUrl);
            $local = in_array(strtolower((string)($url['host'] ?? '')), ['localhost', 'www.localhost', '127.0.0.1', '::1'], true);
            return in_array($this->smartpayEnvironment, ['uat', 'production'], true)
                && preg_match('/^[0-9]{1,20}$/', $this->smartpayMerchantId) === 1
                && $this->smartpayAccessCode !== ''
                && preg_match('/^[a-zA-Z0-9]{32}$/', $this->smartpayWorkingKey) === 1
                && ($this->smartpayApiWorkingKey === '' || preg_match('/^[a-zA-Z0-9]{32}$/', $this->smartpayApiWorkingKey) === 1)
                && (($this->smartpayApiAccessCode === '') === ($this->smartpayApiWorkingKey === ''))
                && $this->currency === 'OMR'
                && filter_var($this->smartpayPublicBaseUrl, FILTER_VALIDATE_URL) !== false
                && !isset($url['user']) && !isset($url['pass']) && !isset($url['query']) && !isset($url['fragment'])
                && !preg_match('/[\r\n]/', $this->smartpayPublicBaseUrl)
                && (($url['scheme'] ?? '') === 'https' || ($this->smartpayEnvironment === 'uat' && $local && ($url['scheme'] ?? '') === 'http'));
        }
        return $this->provider === 'stripe'
            && $this->stripeSecretKey !== ''
            && $this->stripeWebhookSecret !== ''
            && preg_match('/^[A-Z]{3}$/', $this->currency) === 1;
    }

    public function smartpayGatewayUrl(): string
    {
        return $this->smartpayEnvironment === 'production'
            ? self::SMARTPAY_PRODUCTION_GATEWAY_URL
            : self::SMARTPAY_UAT_GATEWAY_URL;
    }

    public function smartpayStatusApiUrl(): string
    {
        return $this->smartpayEnvironment === 'production'
            ? 'https://smartpayapi.bankmuscat.com/apis/servlet/DoWebTrans'
            : 'https://spayuatapi.bmtest.om/apis/servlet/DoWebTrans';
    }
}
