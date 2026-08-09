<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * E-service payment secrets are environment-only. The disabled default makes
 * an unconfigured deployment fail closed instead of marking a fee as paid.
 */
class EservicesPayments extends BaseConfig
{
    public string $provider;
    public string $currency;
    public string $stripeSecretKey;
    public string $stripeWebhookSecret;
    public int $checkoutTtlSeconds;
    public int $webhookToleranceSeconds;

    public function __construct()
    {
        parent::__construct();
        $this->provider = strtolower(trim((string)env('eservices.payment.provider', 'disabled')));
        $this->currency = strtoupper(trim((string)env('eservices.payment.currency', 'OMR')));
        $this->stripeSecretKey = trim((string)env('eservices.payment.stripeSecretKey', ''));
        $this->stripeWebhookSecret = trim((string)env('eservices.payment.stripeWebhookSecret', ''));
        $this->checkoutTtlSeconds = max(1800, min(86400, (int)env('eservices.payment.checkoutTtlSeconds', 1800)));
        $this->webhookToleranceSeconds = max(60, min(900, (int)env('eservices.payment.webhookToleranceSeconds', 300)));
    }

    public function isReady(): bool
    {
        return $this->provider === 'stripe'
            && $this->stripeSecretKey !== ''
            && $this->stripeWebhookSecret !== ''
            && preg_match('/^[A-Z]{3}$/', $this->currency) === 1;
    }
}
