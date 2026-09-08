<?php

namespace CodeIgniter\Config {
    // Keep configuration tests independent of the real process environment and .env.
    class BaseConfig { public function __construct() {} }
}

namespace {
    $smartpayFixtureEnvironment = [];
    function env($key, $default = null) {
        global $smartpayFixtureEnvironment;
        return array_key_exists($key, $smartpayFixtureEnvironment)
            ? $smartpayFixtureEnvironment[$key]
            : $default;
    }

    require_once __DIR__ . '/../app/Config/EservicesPayments.php';

    use Config\EservicesPayments;

    $checks = 0;
    function configCheck(bool $condition, string $message): void {
        global $checks;
        if (!$condition) {
            throw new RuntimeException($message);
        }
        $checks++;
    }
    function fixtureConfig(array $environment): EservicesPayments {
        global $smartpayFixtureEnvironment;
        $smartpayFixtureEnvironment = $environment;
        return new EservicesPayments();
    }

    $uatGateway = 'https://spayuattrns.bmtest.om/transaction.do?command=initiateTransaction';
    $productionGateway = 'https://smartpaytrns.bankmuscat.com/transaction.do?command=initiateTransaction';
    $uatStatusApi = 'https://spayuatapi.bmtest.om/apis/servlet/DoWebTrans';
    $productionStatusApi = 'https://smartpayapi.bankmuscat.com/apis/servlet/DoWebTrans';
    $fourVariables = [
        'SMARTPAY_MERCHANT_ID' => '162',
        'SMARTPAY_ACCESS_CODE' => 'unit-test-access-code',
        'SMARTPAY_WORKING_KEY' => '0123456789abcdef0123456789abcdef',
        'SMARTPAY_GATEWAY_URL' => $uatGateway,
    ];
    $localConfig = $fourVariables + ['PODC_BASE_URL' => 'http://localhost:8082/'];
    $legacyConfig = [
        'eservices.payment.provider' => 'bank_muscat',
        'eservices.payment.smartpayMerchantId' => '999',
        'eservices.payment.smartpayAccessCode' => 'legacy-test-access-code',
        'eservices.payment.smartpayWorkingKey' => 'abcdef0123456789abcdef0123456789',
        'eservices.payment.smartpayEnvironment' => 'production',
        'eservices.payment.smartpayPublicBaseUrl' => 'https://merchant.example.test/',
    ];

    $config = fixtureConfig([]);
    configCheck($config->provider === 'disabled' && !$config->isReady(), 'An unconfigured deployment stays disabled.');
    configCheck(!fixtureConfig(['PODC_BASE_URL' => 'http://localhost:8082/'])->isReady(), 'A base URL alone cannot enable payments.');
    configCheck(!fixtureConfig($fourVariables)->isReady(), 'Four bank variables still require a trusted configured application base URL.');

    $config = fixtureConfig($localConfig);
    configCheck($config->provider === 'bank_muscat' && $config->isReady(), 'The four SmartPay variables select Bank Muscat without a fifth provider setting.');
    configCheck($config->currency === 'OMR', 'The four-variable configuration defaults to OMR.');
    configCheck($config->smartpayBankTimezone === 'Asia/Muscat', 'Bank order timestamps default to Oman time, as verified on the UAT gateway.');
    configCheck($config->smartpayMerchantId === '162'
        && $config->smartpayAccessCode === $fourVariables['SMARTPAY_ACCESS_CODE']
        && $config->smartpayWorkingKey === $fourVariables['SMARTPAY_WORKING_KEY'], 'Uppercase credentials populate the gateway configuration.');
    configCheck($config->smartpayEnvironment === 'uat'
        && $config->smartpayGatewayUrl() === $uatGateway
        && $config->smartpayStatusApiUrl() === $uatStatusApi, 'The official UAT gateway selects matching UAT checkout and Status API endpoints.');
    configCheck($config->smartpayPublicBaseUrl === 'http://localhost:8082', 'The callback base derives from the application URL and removes trailing slashes.');

    $config = fixtureConfig(array_replace($localConfig, [
        'SMARTPAY_GATEWAY_URL' => $productionGateway,
        'PODC_BASE_URL' => 'https://merchant.example.test/',
    ]));
    configCheck($config->isReady() && $config->smartpayEnvironment === 'production'
        && $config->smartpayGatewayUrl() === $productionGateway
        && $config->smartpayStatusApiUrl() === $productionStatusApi, 'The official production gateway selects matching production checkout and Status API endpoints.');
    configCheck(!fixtureConfig(array_replace($localConfig, ['SMARTPAY_GATEWAY_URL' => $productionGateway]))->isReady(), 'Production still rejects HTTP localhost callbacks.');

    foreach (['', 'https://example.test/transaction.do?command=initiateTransaction',
        'http://spayuattrns.bmtest.om/transaction.do?command=initiateTransaction',
        $uatGateway . '&merchant=attacker',
        'https://spayuattrns.bmtest.om.evil.test/transaction.do?command=initiateTransaction'] as $invalidGateway) {
        configCheck(!fixtureConfig(array_replace($localConfig, ['SMARTPAY_GATEWAY_URL' => $invalidGateway]))->isReady(),
            'An empty or unapproved gateway URL fails closed: ' . ($invalidGateway ?: '(empty)'));
    }
    configCheck(!fixtureConfig(array_replace($localConfig, $legacyConfig, ['SMARTPAY_GATEWAY_URL' => '']))->isReady(),
        'An explicitly blank uppercase gateway cannot fall back to the legacy production environment.');

    $config = fixtureConfig(array_replace($legacyConfig, $fourVariables));
    configCheck($config->isReady() && $config->smartpayMerchantId === '162'
        && $config->smartpayAccessCode === $fourVariables['SMARTPAY_ACCESS_CODE']
        && $config->smartpayWorkingKey === $fourVariables['SMARTPAY_WORKING_KEY']
        && $config->smartpayEnvironment === 'uat', 'Uppercase values override conflicting legacy credentials and environment.');
    foreach (['SMARTPAY_MERCHANT_ID', 'SMARTPAY_ACCESS_CODE', 'SMARTPAY_WORKING_KEY'] as $credential) {
        configCheck(!fixtureConfig(array_replace($legacyConfig, $fourVariables, [$credential => '']))->isReady(),
            'An explicitly blank ' . $credential . ' cannot silently use an old secret.');
    }
    configCheck(!fixtureConfig(array_replace($localConfig, ['SMARTPAY_WORKING_KEY' => 'short']))->isReady(), 'Invalid working key length remains rejected.');
    configCheck(!fixtureConfig(array_replace($localConfig, ['eservices.payment.currency' => 'USD']))->isReady(), 'Bank Muscat cannot silently use a currency other than OMR.');

    $config = fixtureConfig(array_replace($localConfig, ['eservices.payment.provider' => 'disabled']));
    configCheck($config->provider === 'disabled' && !$config->isReady(), 'An explicit disabled provider remains a kill switch even with complete SmartPay keys.');
    $config = fixtureConfig(array_replace($localConfig, [
        'eservices.payment.provider' => 'stripe',
        'eservices.payment.stripeSecretKey' => 'unit-test-stripe-secret',
        'eservices.payment.stripeWebhookSecret' => 'unit-test-stripe-webhook',
    ]));
    configCheck($config->provider === 'stripe' && $config->isReady(), 'An explicitly configured Stripe provider is preserved.');

    $config = fixtureConfig($legacyConfig);
    configCheck($config->provider === 'bank_muscat' && $config->isReady()
        && $config->smartpayMerchantId === '999'
        && $config->smartpayEnvironment === 'production', 'Existing dotted-key configurations remain compatible.');
    $config = fixtureConfig($fourVariables + [
        'PODC_BASE_URL' => 'http://localhost:8082/',
        'APP_BASE_URL' => 'http://localhost:8095/',
        'app.baseURL' => 'http://localhost:8096/',
    ]);
    configCheck($config->smartpayPublicBaseUrl === 'http://localhost:8082', 'PODC_BASE_URL takes precedence over other configured application URLs.');
    $config = fixtureConfig($fourVariables + ['APP_BASE_URL' => 'http://localhost:8095/', 'app.baseURL' => 'http://localhost:8096/']);
    configCheck($config->isReady() && $config->smartpayPublicBaseUrl === 'http://localhost:8095', 'APP_BASE_URL supplies the callback when PODC_BASE_URL is absent.');
    $config = fixtureConfig($fourVariables + ['app.baseURL' => 'http://localhost:8096/']);
    configCheck($config->isReady() && $config->smartpayPublicBaseUrl === 'http://localhost:8096', 'The framework app.baseURL setting is the final configured URL fallback.');
    $config = fixtureConfig($localConfig + ['eservices.payment.smartpayPublicBaseUrl' => 'https://registered.example.test/']);
    configCheck($config->isReady() && $config->smartpayPublicBaseUrl === 'https://registered.example.test', 'A legacy explicit payment callback base retains precedence.');
    configCheck(!fixtureConfig($localConfig + ['eservices.payment.smartpayPublicBaseUrl' => ''])->isReady(),
        'An explicitly empty legacy callback base remains an override and cannot silently use another origin.');

    $savedServer = $_SERVER;
    $_SERVER['HTTP_HOST'] = 'attacker.example.test';
    $_SERVER['HTTP_X_FORWARDED_HOST'] = 'attacker.example.test';
    $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
    configCheck(!fixtureConfig($fourVariables)->isReady(), 'Request host headers cannot supply a missing trusted callback origin.');
    configCheck(fixtureConfig($localConfig)->smartpayPublicBaseUrl === 'http://localhost:8082', 'Request host headers cannot override a configured callback origin.');
    $_SERVER = $savedServer;
    foreach (['https://user:password@example.test/', 'https://example.test/?redirect=evil', 'https://example.test/#fragment'] as $unsafeBase) {
        configCheck(!fixtureConfig(array_replace($localConfig, ['PODC_BASE_URL' => $unsafeBase]))->isReady(), 'Unsafe callback base URL remains rejected: ' . $unsafeBase);
    }

    $config = fixtureConfig($localConfig + [
        'eservices.payment.smartpayApiAccessCode' => 'unit-test-api-access-code',
        'eservices.payment.smartpayApiWorkingKey' => 'aaaabbbbccccddddeeeeffff11112222',
        'eservices.payment.smartpayBankTimezone' => 'Asia/Muscat',
        'eservices.payment.smartpayTimeoutSeconds' => 20,
    ]);
    configCheck($config->isReady() && $config->smartpayApiAccessCode === 'unit-test-api-access-code'
        && $config->smartpayApiWorkingKey === 'aaaabbbbccccddddeeeeffff11112222'
        && $config->smartpayBankTimezone === 'Asia/Muscat'
        && $config->smartpayTimeoutSeconds === 20, 'Optional separate Status API credentials, timezone and timeout remain supported.');
    configCheck(!fixtureConfig($localConfig + ['eservices.payment.smartpayApiAccessCode' => 'unit-test-api-access-code'])->isReady(),
        'Partial separate Status API credentials remain rejected.');

    echo 'SmartPay environment configuration: ' . $checks . ' checks passed.' . PHP_EOL;
}
