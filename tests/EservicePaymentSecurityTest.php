<?php

require_once __DIR__ . '/../app/Libraries/Payments/Payment_amount.php';

use App\Libraries\Payments\Payment_amount;

$root = dirname(__DIR__);
$read = static fn(string $path): string => (string)file_get_contents($root . '/' . $path);
$manager = $read('app/Libraries/Payments/Eservice_payment_manager.php');
$gateway = $read('app/Libraries/Payments/Stripe_payment_gateway.php');
$webhook = $read('app/Controllers/Eservice_payment_webhook.php');
$config = $read('app/Config/EservicesPayments.php');
$migration = $read('app/Database/Migrations/2026_08_03_070000_eservice_payment_integrity.php');
$gateController = $read('app/Controllers/Gate_pass_portal.php');

$fail = static function (string $message): void {
    fwrite(STDERR, 'Assertion failed: ' . $message . PHP_EOL);
    exit(1);
};
$same = static function ($expected, $actual, string $message) use ($fail): void {
    if ($expected !== $actual) {
        $fail($message . ' Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
};
$contains = static function (string $needle, string $text, string $message) use ($fail): void {
    if (!str_contains($text, $needle)) {
        $fail($message . ' Missing: ' . $needle);
    }
};
$notContains = static function (string $needle, string $text, string $message) use ($fail): void {
    if (str_contains($text, $needle)) {
        $fail($message . ' Unexpected: ' . $needle);
    }
};

$same(1012, Payment_amount::toMinor('10.12', 2), 'two-decimal provider conversion is exact');
$same(1012, Payment_amount::toMinor('10.120', 2), 'trailing zero is accepted without rounding');
$same('10.120', Payment_amount::fromMinor(10120, 3), 'three-decimal conversion is exact');
$threw = false;
try {
    Payment_amount::toMinor('10.123', 2);
} catch (InvalidArgumentException $exception) {
    $threw = true;
}
$same(true, $threw, 'unrepresentable amount is rejected instead of rounded');

$contains("env('eservices.payment.provider', 'disabled')", $config, 'payments default to disabled');
$contains('stripeWebhookSecret', $config, 'webhook secret comes from configuration');
$contains("'idempotency_key'", $gateway, 'checkout uses a provider idempotency key');
$contains('Webhook::constructEvent', $gateway, 'webhook signature uses the provider SDK');
$contains("(string)\$this->request->getBody()", $webhook, 'signature verification receives the raw body');
$contains('Stripe-Signature', $webhook, 'signature header is required');

$contains('uq_eservice_payments_active_subject', $migration, 'only one active checkout is allowed per fee');
$contains('uq_eservice_payment_events_provider', $migration, 'provider events are idempotent');
$contains("hash('sha256', \$rawBody)", $manager, 'webhook audit stores a payload digest');
$contains('assertPaidSessionMatches', $manager, 'settlement verifies amount, currency, and paid status');
$contains('applyPaidSubject', $manager, 'workflow advances only after verified settlement');
$contains('provider_checkout_failed', $manager, 'provider failures remain unpaid');

$contains('Eservice_payment_manager::GATE_PASS_FEE', $gateController, 'gate-pass fee uses secure payment manager');
$notContains('Payment recorded (portal).', $gateController, 'gate-pass portal cannot self-approve payment');

echo 'E-service payment security contracts passed.' . PHP_EOL;
