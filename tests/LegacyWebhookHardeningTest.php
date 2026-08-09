<?php

$root = dirname(__DIR__);
$authenticator = file_get_contents($root . '/app/Libraries/Webhook_request_authenticator.php');
$controller = file_get_contents($root . '/app/Controllers/Webhooks_listener.php');
$rise = file_get_contents($root . '/app/Config/Rise.php');

$assertContains = static function (string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        exit(1);
    }
};
$assertNotContains = static function (string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) !== false) {
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        exit(1);
    }
};

$assertContains('PODC_LEGACY_WEBHOOKS_ENABLED', $authenticator, 'legacy webhooks must be explicitly enabled');
$assertContains('X-Hub-Signature-256', $authenticator, 'GitHub callbacks require a signed body');
$assertContains('X-Hub-Signature', $authenticator, 'Bitbucket callbacks require a signed body');
$assertContains("Stripe-Signature", $authenticator, 'Stripe callbacks require the provider signature');
$assertContains('hash_equals', $authenticator, 'HMAC comparison must be timing safe');
$assertContains('MAX_PAYLOAD_BYTES', $authenticator, 'webhook bodies must have a fixed size ceiling');
$assertContains('Webhook::constructEvent', $authenticator, 'Stripe verification must enforce signature age and payload integrity');
$assertContains('verifyGithub', $controller, 'GitHub controller must invoke signature verification');
$assertContains('verifyBitbucket', $controller, 'Bitbucket controller must invoke signature verification');
$assertContains('verifyStripe', $controller, 'Stripe controllers must invoke signature verification');
$assertNotContains('settings_key == $key', $controller, 'URL path tokens must not authorize webhooks');
$assertNotContains("file_get_contents('php://input')", $controller, 'controller must verify the exact bounded raw payload');

foreach ([
    'upload_pasted_image.*+',
    'events/snooze_reminder',
    'notifications/count_notifications',
    'messages/count_notifications',
    'google_api/save_access_token',
] as $unsafeExemption) {
    $assertNotContains($unsafeExemption, $rise, 'same-origin endpoint should remain protected by CSRF: ' . $unsafeExemption);
}

echo 'Legacy webhook hardening checks passed.' . PHP_EOL;
