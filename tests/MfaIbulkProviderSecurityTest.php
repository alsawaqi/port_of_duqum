<?php

$root = dirname(__DIR__);

require_once $root . '/app/Libraries/Auth/MfaProviderInterface.php';
require_once $root . '/app/Libraries/Auth/OmanMobileNumber.php';
require_once $root . '/app/Libraries/Auth/IBulkSmsMfaProvider.php';

use App\Libraries\Auth\IBulkSmsMfaProvider;
use App\Libraries\Auth\OmanMobileNumber;

$fail = static function (string $message): void {
    fwrite(STDERR, 'Assertion failed: ' . $message . PHP_EOL);
    exit(1);
};
$assertSame = static function ($expected, $actual, string $message) use ($fail): void {
    if ($expected !== $actual) {
        $fail($message . ' Expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true));
    }
};
$assertContains = static function (string $needle, string $source, string $message) use ($fail): void {
    if (strpos($source, $needle) === false) {
        $fail($message . ' Missing: ' . $needle);
    }
};
$read = static function (string $path) use ($root, $fail): string {
    $source = file_get_contents($root . '/' . $path);
    if ($source === false) {
        $fail('Unable to read ' . $path);
    }
    return $source;
};

foreach ([
    '99123456',
    '+968 9912 3456',
    '00968-9912-3456',
    '96899123456',
] as $value) {
    $assertSame('+96899123456', OmanMobileNumber::normalize($value), 'Oman mobile normalizes to E.164');
}
$assertSame('+96879123456', OmanMobileNumber::normalize('79123456'), '7-series Oman mobile is accepted');
foreach ([
    '',
    '+971501234567',
    '+96824151020',
    '+9689912345',
    '+968991234567',
    '+96899ABC456',
    '99+123456',
    '++96899123456',
    '+0096899123456',
] as $value) {
    $assertSame(null, OmanMobileNumber::normalize($value), 'non-Oman or malformed mobile is rejected');
}
$assertSame('96899123456', OmanMobileNumber::forGateway('+968 9912 3456'), 'gateway gets numeric international format');
$assertSame('+968 **** 3456', OmanMobileNumber::mask('+96899123456'), 'mobile hint reveals only last four digits');

$settings = [
    'endpoint' => 'https://www.ismartsms.net/iBulkSMS/HttpWS/SMSDynamicRefIntlAPI.aspx',
    'user_id' => 'deployment-secret-user',
    'password' => 'deployment-secret-password',
    'header' => 'PODC',
    'connect_timeout' => 3,
    'timeout' => 8,
];
$provider = new IBulkSmsMfaProvider($settings);
$assertSame(function_exists('curl_init'), $provider->isConfigured(), 'complete TLS provider configuration is accepted');
$assertSame('+96899123456', $provider->normalizeDestination('99123456'), 'provider validates its destination');

$missingSecret = $settings;
$missingSecret['password'] = '';
$assertSame(false, (new IBulkSmsMfaProvider($missingSecret))->isConfigured(), 'missing credential fails closed');
$insecureEndpoint = $settings;
$insecureEndpoint['endpoint'] = 'http://www.ismartsms.net/example';
$assertSame(false, (new IBulkSmsMfaProvider($insecureEndpoint))->isConfigured(), 'HTTP provider endpoint fails closed');
$invalidHeader = $settings;
$invalidHeader['header'] = 'TOO-LONG-HEADER';
$assertSame(false, (new IBulkSmsMfaProvider($invalidHeader))->isConfigured(), 'invalid sender header fails closed');

$providerSource = $read('app/Libraries/Auth/IBulkSmsMfaProvider.php');
foreach ([
    'CURLOPT_SSL_VERIFYPEER => true',
    'CURLOPT_SSL_VERIFYHOST => 2',
    'CURLOPT_CONNECTTIMEOUT',
    'CURLOPT_TIMEOUT',
    'CURLOPT_FOLLOWLOCATION => false',
    "trim(\$response) === '1'",
] as $needle) {
    $assertContains($needle, $providerSource, 'iBulk transport is bounded and verified');
}

$configSource = $read('app/Config/AuthSecurity.php');
$assertContains('AUTH_SECURITY_MFA_PROVIDER_MAP', $configSource, 'provider can be selected by effective user type');
$assertContains('AUTH_SECURITY_MFA_IBULK_USER_ID', $configSource, 'iBulk user is deployment-configured');
$assertContains('AUTH_SECURITY_MFA_IBULK_PASSWORD', $configSource, 'iBulk password is deployment-configured');
$assertContains("public string \$mfaIbulkPassword = '';", $configSource, 'no gateway password is committed');

$authSource = $read('app/Models/Auth_security_model.php');
$assertContains('mfaProviderForUserType($this->mfa_user_type($user))', $authSource, 'provider mapping uses logical user type');
$assertContains('in_array($storedUserType', $authSource, 'vendor alias does not weaken existing staff MFA requirements');
$assertContains("? (string) (\$user->phone ?? '')", $authSource, 'SMS MFA uses the registered account phone');
$assertContains('normalizeDestination($rawDestination)', $authSource, 'destination is validated before challenge delivery');

$usersSource = $read('app/Models/Users_model.php');
$assertContains('email, phone, password', $usersSource, 'authentication loads the registered phone for MFA');
$assertContains("? 'vendor' : strtolower", $usersSource, 'vendor-only staff identities have a distinct MFA policy key');

echo 'iBulk/Oman MFA provider security contracts passed.' . PHP_EOL;
