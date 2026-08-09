<?php

$root = dirname(__DIR__);

$fail = static function (string $message): void {
    fwrite(STDERR, 'Assertion failed: ' . $message . PHP_EOL);
    exit(1);
};
$assertTrue = static function ($condition, string $message) use ($fail): void {
    if (!$condition) {
        $fail($message);
    }
};
$assertContains = static function (string $needle, string $haystack, string $message) use ($fail): void {
    if (!str_contains($haystack, $needle)) {
        $fail($message . ' Missing: ' . $needle);
    }
};

require_once $root . '/app/Libraries/Notification_request_authenticator.php';
require_once $root . '/app/Libraries/Notification_payload_guard.php';

use App\Libraries\Notification_payload_guard;
use App\Libraries\Notification_request_authenticator;

$secret = str_repeat('notification-test-secret-', 2);
$now = 1_800_000_000;
$timestamp = (string) $now;
$nonce = str_repeat('a', 64);
$body = json_encode([
    'event' => 'signed-event-token',
    'user_id' => '7',
    'project_id' => 42,
], JSON_UNESCAPED_SLASHES);
$signature = Notification_request_authenticator::sign($secret, $now, $nonce, $body);

$claimed = [];
$claim = static function (string $hash, int $expiresAt) use (&$claimed): bool {
    if (isset($claimed[$hash])) {
        return false;
    }
    $claimed[$hash] = $expiresAt;
    return true;
};

$assertTrue(
    Notification_request_authenticator::authenticate(
        $secret, $timestamp, $nonce, $signature, $body, $claim, $now
    ),
    'a valid signed request is accepted once'
);
$assertTrue(
    !Notification_request_authenticator::authenticate(
        $secret, $timestamp, $nonce, $signature, $body, $claim, $now
    ),
    'the same nonce cannot be replayed'
);
$assertTrue(
    !Notification_request_authenticator::authenticate(
        $secret,
        $timestamp,
        str_repeat('b', 64),
        $signature,
        $body . 'tampered',
        static fn(): bool => true,
        $now
    ),
    'a tampered body/signature pair is rejected'
);
$assertTrue(
    !Notification_request_authenticator::authenticate(
        $secret,
        (string) ($now - Notification_request_authenticator::MAX_CLOCK_SKEW_SECONDS - 1),
        str_repeat('c', 64),
        Notification_request_authenticator::sign(
            $secret,
            $now - Notification_request_authenticator::MAX_CLOCK_SKEW_SECONDS - 1,
            str_repeat('c', 64),
            $body
        ),
        $body,
        static fn(): bool => true,
        $now
    ),
    'a stale request is rejected'
);
$assertTrue(
    !Notification_request_authenticator::authenticate(
        $secret, $timestamp, str_repeat('d', 64), '', $body, static fn(): bool => true, $now
    ),
    'an unsigned request is rejected'
);

$clean = Notification_payload_guard::sanitize([
    'event' => 'signed-event-token',
    'user_id' => '7',
    'project_id' => '42',
    'exclude_ticket_creator' => true,
]);
$assertTrue(is_array($clean) && $clean['user_id'] === 7 && $clean['project_id'] === 42, 'IDs are strictly normalized');
$assertTrue(
    Notification_payload_guard::sanitize(['event' => 'x', 'notify_to' => '1,2']) === null,
    'a caller cannot supply a raw recipient list'
);
$assertTrue(
    Notification_payload_guard::sanitize(['event' => 'x', 'plugin_target' => '7']) === null,
    'unregistered plugin fields are rejected'
);
$assertTrue(
    Notification_payload_guard::sanitize(
        ['event' => 'x', 'plugin_target' => '7'],
        ['plugin_target']
    )['plugin_target'] === '7',
    'an explicitly registered scalar plugin field is accepted'
);
$assertTrue(
    Notification_payload_guard::sanitize(['event' => 'x', 'project_id' => '1 OR 1=1']) === null,
    'non-numeric entity identifiers are rejected'
);

$controller = file_get_contents($root . '/app/Controllers/Notification_processor.php');
$helper = file_get_contents($root . '/app/Helpers/general_helper.php');
$rise = file_get_contents($root . '/app/Config/Rise.php');
$model = file_get_contents($root . '/app/Models/Notifications_model.php');
$manualSql = file_get_contents($root . '/app/Database/SQL/notification_processor_hardening_upgrade_pod.sql');

$assertTrue(!str_contains($controller, '$data = $_POST'), 'the processor does not trust form POST fields');
$assertContains('Notification_request_authenticator::authenticate', $controller, 'the public path authenticates signatures');
$assertContains('PODC_NOTIFICATION_PROCESSOR_KEY', $controller, 'the signing key is environment-derived');
$assertContains('Notification_processor_nonces_model', $controller, 'the public path claims a one-time nonce');
$assertContains('app_filter_notification_processor_allowed_plugin_fields', $controller, 'plugin fields require explicit registration');
$assertTrue(
    !str_contains($helper, 'notification_processor/create_notification') && !str_contains($helper, 'curl_init()'),
    'application notifications are processed in-process'
);
$assertTrue(
    !str_contains($rise, 'notification_processor/create_notification'),
    'the notification processor no longer bypasses CSRF'
);
$assertContains('FIND_IN_SET(?, COALESCE($notifications_table.notify_to', $model, 'read updates require recipient membership');
$assertContains('return $this->db->query($sql, $parameters);', $model, 'read updates use bound parameters');
$assertTrue(
    is_file($root . '/app/Database/Migrations/2026_08_03_090000_notification_processor_hardening.php'),
    'the replay nonce schema migration exists'
);
$assertContains('pod_notification_processor_nonces', $manualSql, 'manual SQL creates the replay nonce table');
$assertContains('idx_notification_processor_nonce_expiry', $manualSql, 'manual SQL creates the nonce expiry index');
$assertContains('does not enable SMS', $manualSql, 'manual SQL separates replay protection from deferred SMS delivery');

echo 'Notification processor security contracts passed.' . PHP_EOL;
