<?php

// Local database integration: real credentials, controller, memberships and OTP
// challenge storage. Delivery is in memory only; all fixture writes roll back.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
chdir(dirname(__DIR__));
define('FCPATH', getcwd() . DIRECTORY_SEPARATOR);
require FCPATH . 'app/Config/Paths.php';
require FCPATH . 'system/Boot.php';
class CrSigninBootstrap extends CodeIgniter\Boot {
    public static function init(): void {
        $paths = new Config\Paths();
        static::definePathConstants($paths);
        static::loadConstants();
        static::loadDotEnv($paths);
        static::defineEnvironment();
        static::loadCommonFunctions();
        static::loadAutoloader();
    }
}
CrSigninBootstrap::init();
$database = (new Config\Database())->default;
if (ENVIRONMENT === 'production' || $database['database'] !== 'bedotscpanel_poderp'
    || !in_array($database['hostname'], ['localhost', '127.0.0.1'], true)) {
    throw new RuntimeException('Restricted to the local development database.');
}
if (!defined('CI_DEBUG')) { define('CI_DEBUG', true); }
putenv('PODC_RECAPTCHA_ENABLED=false'); // Process only; never change application settings.
helper(['general', 'plugin', 'date_time', 'safe_serialization', 'url', 'language', 'email']);
require_once APPPATH . 'ThirdParty/PHP-Hooks/php-hooks.php';
config('Rise')->app_settings_array = ['language' => 'english'];

class CrMemorySms implements App\Libraries\Auth\MfaProviderInterface {
    public array $messages = [];
    public function name(): string { return 'ismartsms'; }
    public function isConfigured(): bool { return true; }
    public function normalizeDestination(string $destination): ?string {
        return App\Libraries\Auth\OmanMobileNumber::normalize($destination);
    }
    public function send(string $destination, string $code, int $lifetimeSeconds): bool {
        $this->messages[] = compact('destination', 'code');
        return true;
    }
}
class CrTestAuth extends App\Models\Auth_security_model {
    public CrMemorySms $delivery;
    public function mfa_provider(?object $user = null): App\Libraries\Auth\MfaProviderInterface {
        return $this->delivery;
    }
}

$db = db_connect();
$db->transException(true);
$users = new App\Models\Users_model();
$memberships = new App\Models\Vendor_users_model();
$auth = new CrTestAuth();
$auth->delivery = new CrMemorySms();
$auth->config()->mfaEnabled = false;
$auth->config()->mfaRequiredUserTypes = ['*'];
$auth->config()->mfaHmacKey = bin2hex(random_bytes(32));
$verification = new App\Models\Verification_model();
$sessionConfig = new Config\Session();
$sessionConfig->savePath = WRITEPATH . 'session';
$session = new CodeIgniter\Test\Mock\MockSession(
    new CodeIgniter\Session\Handlers\FileHandler($sessionConfig, '127.0.0.1'), $sessionConfig
);
Config\Services::injectMock('session', $session);
$_SESSION = [];

$checks = 0;
$check = static function ($condition, string $label) use (&$checks): void {
    $checks++;
    if (!$condition) { throw new RuntimeException($label); }
};
$setProperty = static function ($object, string $name, $value): void {
    $property = new ReflectionProperty(App\Controllers\Signin::class, $name);
    $property->setAccessible(true);
    $property->setValue($object, $value);
};
$controller = static function (array $post) use ($users, $memberships, $auth, $verification, $session, $setProperty) {
    $_POST = $post;
    $_REQUEST = $post;
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $app = config('App');
    $request = new CodeIgniter\HTTP\IncomingRequest(
        $app, new CodeIgniter\HTTP\SiteURI($app), null, new CodeIgniter\HTTP\UserAgent()
    );
    $request->setMethod('POST');
    $request->setHeader('X-Requested-With', 'XMLHttpRequest');
    $request->setGlobal('post', $post);
    $request->setGlobal('request', $post);
    $request->setLocale('english');
    Config\Services::injectMock('request', $request);
    Config\Services::resetSingle('validation');
    $instance = (new ReflectionClass(App\Controllers\Signin::class))->newInstanceWithoutConstructor();
    $instance->initController($request, new CodeIgniter\HTTP\Response($app), service('logger'));
    $instance->session = $session;
    $instance->Users_model = $users;
    $instance->Verification_model = $verification;
    $setProperty($instance, 'Auth_security_model', $auth);
    $setProperty($instance, 'Vendor_users_model', $memberships);
    $setProperty($instance, 'signin_validation_errors', []);
    return $instance;
};
$login = static function (string $identifier, string $password, array $extra = []) use ($controller): array {
    $_SESSION = [];
    $response = $controller(array_merge($extra, ['email' => $identifier, 'password' => $password]))->authenticate();
    return json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
};
$clone = static function (string $table, array $changes) use ($db): int {
    $row = $db->table($table)->get(1)->getRowArray();
    if (!$row) { throw new RuntimeException('Missing local fixture source: ' . $table); }
    unset($row['id']);
    foreach ($db->query('SHOW COLUMNS FROM `' . $db->prefixTable($table) . '`')->getResult() as $field) {
        if (str_contains($field->Extra, 'GENERATED')) { unset($row[$field->Field]); }
    }
    $db->table($table)->insert(array_replace($row, $changes));
    return (int) $db->insertID();
};
$stamp = strtoupper(bin2hex(random_bytes(5)));
$fixtureUsers = [];
$db->transBegin();
try {
    $a = $clone('vendors', ['vendor_name' => 'CR LOGIN TEST A', 'cr_number' => '00' . $stamp . 'A', 'status' => 'approved', 'deleted' => 0]);
    $b = $clone('vendors', ['vendor_name' => 'CR LOGIN TEST B', 'cr_number' => $stamp . 'B', 'status' => 'approved', 'deleted' => 0]);
    $c = $clone('vendors', ['vendor_name' => 'CR LOGIN TEST C', 'cr_number' => $stamp . 'C', 'status' => 'approved', 'deleted' => 0]);
    $password = 'CrLogin@' . $stamp;
    $contactPassword = 'Contact@' . $stamp;
    $ownerEmail = strtolower($stamp) . '-owner@example.invalid';
    $contactEmail = strtolower($stamp) . '-contact@example.invalid';
    $newUser = static function ($email, $secret) use ($clone, &$fixtureUsers): int {
        $id = $clone('users', ['first_name' => 'CR Login Test', 'last_name' => 'Fixture', 'email' => $email,
            'password' => password_hash($secret, PASSWORD_DEFAULT), 'phone' => '+96890000000',
            'user_type' => 'staff', 'client_id' => 0, 'role_id' => 0, 'is_admin' => 0,
            'status' => 'active', 'deleted' => 0, 'disable_login' => 0, 'auth_session_version' => 0]);
        $fixtureUsers[] = $id;
        return $id;
    };
    $owner = $newUser($ownerEmail, $password);
    $contact = $newUser($contactEmail, $contactPassword);
    $link = static function ($uid, $vid, $isOwner) use ($db): void {
        $db->table('vendor_users')->insert(['vendor_id' => $vid, 'user_id' => $uid,
            'vendor_role_id' => $isOwner ? 1 : 5, 'is_owner' => $isOwner, 'status' => 'active', 'deleted' => 0]);
    };
    $link($owner, $a, 1); $link($owner, $b, 1); $link($contact, $a, 0);
    $crA = '00' . $stamp . 'A'; $crB = $stamp . 'B';

    $result = $login(' ' . strtolower($crA) . ' ', $password, ['vendor_id' => $c]);
    if (empty($result['success'])) { throw new RuntimeException('Initial login response: ' . json_encode($result)); }
    $check($result['success'] && (int) $session->get('active_vendor_id') === $a, 'CR normalization, leading zeros and untrusted vendor id');
    $check((int) $session->get('user_id') === $owner && str_ends_with($result['redirect_url'], '/vendor_portal'), 'CR opens authenticated owner in vendor portal');
    $result = $login($crB, $password);
    $check($result['success'] && (int) $session->get('active_vendor_id') === $b, 'Second CR opens directly for same identity');
    $result = $login(strtoupper($ownerEmail), $password, ['vendor_id' => $b]);
    $check($result['success'] && str_ends_with($result['redirect_url'], '/signin/vendor_selection') && !$session->get('user_id'), 'Email retains multi-CR selector and no full session');
    $result = json_decode($controller(['vendor_id' => $c])->select_vendor()->getBody(), true);
    $check(!$result['success'] && !$session->get('user_id'), 'Unrelated CR selection refused');
    $result = json_decode($controller(['vendor_id' => $b])->select_vendor()->getBody(), true);
    $check($result['success'] && (int) $session->get('active_vendor_id') === $b, 'Email selection opens authorized CR');
    $result = $login($contactEmail, $contactPassword);
    $check($result['success'] && (int) $session->get('active_vendor_id') === $a, 'Single-CR email skips selection');
    $result = $login($crA, $contactPassword);
    $check($result['success'] && (int) $session->get('user_id') === $contact, 'Contact CR login uses the contact identity');

    foreach ([[$stamp . 'C', $password], [$crA, 'wrong'], ['not-a-cr', $password], ["' OR 1=1 --", $password], [$crB, $contactPassword]] as [$identifier, $secret]) {
        $check(!$users->authenticate_signin_credentials($identifier, $secret), 'Invalid or unrelated CR/password refused');
    }
    foreach ([['status', 'suspended'], ['status', 'invited'], ['deleted', 1]] as [$field, $value]) {
        $db->table('vendor_users')->where(['vendor_id' => $b, 'user_id' => $owner])->update([$field => $value]);
        $check(!$users->authenticate_signin_credentials($crB, $password), 'Inactive/deleted membership refused');
        $db->table('vendor_users')->where(['vendor_id' => $b, 'user_id' => $owner])->update(['status' => 'active', 'deleted' => 0]);
    }
    foreach ([['status', 'suspended'], ['status', 'rejected'], ['deleted', 1]] as [$field, $value]) {
        $db->table('vendors')->where('id', $b)->update([$field => $value]);
        $check(!$users->authenticate_signin_credentials($crB, $password), 'Unavailable vendor refused');
        $db->table('vendors')->where('id', $b)->update(['status' => 'approved', 'deleted' => 0]);
    }
    foreach ([['disable_login', 1], ['status', 'inactive'], ['deleted', 1], ['user_type', 'client']] as [$field, $value]) {
        $db->table('users')->where('id', $owner)->update([$field => $value]);
        $check(!$users->authenticate_signin_credentials($crB, $password), 'Ineligible user refused');
        $db->table('users')->where('id', $owner)->update(['status' => 'active', 'deleted' => 0, 'disable_login' => 0, 'user_type' => 'staff']);
    }
    $db->table('users')->where('id', $contact)->update(['password' => password_hash($password, PASSWORD_DEFAULT)]);
    $result = $login($crA, $password);
    $check(!$result['success'] && !$session->get('user_id'), 'Shared password ambiguity never picks another person');
    $check((int) $users->authenticate_signin_credentials($ownerEmail, $password)->id === $owner, 'Email remains usable when CR password is ambiguous');
    $db->table('users')->where('id', $contact)->update(['password' => password_hash($contactPassword, PASSWORD_DEFAULT)]);
    $db->table('users')->where('id', $owner)->update(['password' => md5($password)]);
    $check((int) $users->authenticate_signin_credentials($crB, $password)->id === $owner, 'Legacy password remains supported');
    $check(password_verify($password, $db->table('users')->where('id', $owner)->get()->getRow()->password), 'Legacy password upgraded after unique successful match');

    $auth->config()->mfaEnabled = true;
    $beginOtp = static function ($identifier) use ($login, $password, $owner): array {
        service('throttler')->remove('signin_otp_user_' . $owner . '_minute');
        service('throttler')->remove('signin_otp_user_' . $owner . '_window');
        return $login($identifier, $password);
    };
    $verify = static function ($code, $extra = []) use ($controller): array {
        return json_decode($controller(array_merge($extra, ['code' => $code]))->verify_mfa()->getBody(), true);
    };
    $result = $beginOtp($crB);
    $check($result['success'] && !$session->get('user_id') && !$session->get('active_vendor_id'), 'CR login must finish OTP before session access');
    $check((int) $session->get('pending_mfa_vendor_id') === $b, 'OTP stores server-verified CR context');
    $message = end($auth->delivery->messages);
    $check($message['destination'] === '+96890000000', 'OTP uses matched person mobile');
    $wrongCode = $message['code'] === '000000' ? '111111' : '000000';
    $check(!$verify($wrongCode)['success'] && !$session->get('user_id'), 'Wrong OTP does not authenticate');
    $result = $verify($message['code'], ['vendor_id' => $c, 'pending_mfa_vendor_id' => $c]);
    $check($result['success'] && (int) $session->get('active_vendor_id') === $b, 'Correct OTP opens bound CR ignoring posted overrides');
    $check(!$session->get('pending_mfa_vendor_id'), 'Bound CR cleared after login');

    $beginOtp($crB);
    $message = end($auth->delivery->messages);
    $db->table('vendor_users')->where(['vendor_id' => $b, 'user_id' => $owner])->update(['status' => 'suspended']);
    $check(!$verify($message['code'])['success'] && !$session->get('user_id'), 'Membership revoked during OTP denies without falling back to other CR');
    $db->table('vendor_users')->where(['vendor_id' => $b, 'user_id' => $owner])->update(['status' => 'active']);
    $beginOtp($crB);
    $message = end($auth->delivery->messages);
    $db->table('auth_mfa_challenges')->where('challenge_id', $session->get('pending_mfa_challenge_id'))->update(['expires_at' => gmdate('Y-m-d H:i:s', time() - 10)]);
    $check(!$verify($message['code'])['success'] && !$session->get('pending_mfa_vendor_id'), 'Expired OTP denies and clears CR context');
    $beginOtp($ownerEmail);
    $message = end($auth->delivery->messages);
    $check(!$session->get('pending_mfa_vendor_id'), 'Email OTP carries no stale CR');
    $result = $verify($message['code']);
    $check($result['success'] && str_ends_with($result['redirect_url'], '/signin/vendor_selection') && !$session->get('user_id'), 'Email plus OTP still requires multi-CR selection');
    $db->transRollback();
    $check($db->table('users')->whereIn('id', $fixtureUsers)->countAllResults() === 0, 'Fixture identities rolled back');
    echo "Vendor CR sign-in integration: {$checks} checks passed; fixtures rolled back; no SMS sent.\n";
} finally {
    $db->transRollback();
    $_SESSION = [];
    foreach ($fixtureUsers as $id) {
        service('throttler')->remove('signin_otp_user_' . $id . '_minute');
        service('throttler')->remove('signin_otp_user_' . $id . '_window');
    }
}
