<?php

require_once __DIR__ . '/../app/Libraries/Payments/Payment_amount.php';
require_once __DIR__ . '/../app/Libraries/Payments/Legacy_invoice_payment_manager.php';

use App\Libraries\Payments\Legacy_invoice_payment_manager;
use App\Libraries\Payments\Payment_amount;

$root = dirname(__DIR__);
$read = static fn(string $path): string => (string)file_get_contents($root . '/' . $path);
$manager = $read('app/Libraries/Payments/Legacy_invoice_payment_manager.php');
$stripe = $read('app/Libraries/Stripe.php');
$paypal = $read('app/Libraries/Paypal.php');
$paytm = $read('app/Libraries/Paytm.php');
$paytmCrypto = $read('app/ThirdParty/Paytm/encdec_paytm.php');
$stripeRedirect = $read('app/Controllers/Stripe_redirect.php');
$paypalRedirect = $read('app/Controllers/Paypal_redirect.php');
$paytmRedirect = $read('app/Controllers/Paytm_redirect.php');
$invoicePayments = $read('app/Controllers/Invoice_payments.php');
$publicInvoice = $read('app/Controllers/Pay_invoice.php');
$generalHelper = $read('app/Helpers/general_helper.php');
$webhooks = $read('app/Controllers/Webhooks_listener.php');
$paytmView = $read('app/Views/invoices/_paytm_payment_form.php');
$migration = $read('app/Database/Migrations/2026_08_03_130000_legacy_invoice_payment_hardening.php');

$fail = static function (string $message): void {
    fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
    exit(1);
};
$contains = static function (string $needle, string $text, string $message) use ($fail): void {
    if (strpos($text, $needle) === false) {
        $fail($message . ' Missing: ' . $needle);
    }
};
$notContains = static function (string $needle, string $text, string $message) use ($fail): void {
    if (strpos($text, $needle) !== false) {
        $fail($message . ' Unexpected: ' . $needle);
    }
};
$same = static function ($expected, $actual, string $message) use ($fail): void {
    if ($expected !== $actual) {
        $fail($message . ' Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
};

// Exact minor-unit behavior used by the persisted amount contract.
$same(3, Legacy_invoice_payment_manager::minorUnitExponent('OMR'), 'OMR uses three minor digits');
$same(0, Legacy_invoice_payment_manager::minorUnitExponent('JPY'), 'JPY is zero-decimal');
$same(2, Legacy_invoice_payment_manager::minorUnitExponent('USD'), 'USD uses two minor digits');
$same(1250, Payment_amount::toMinor('12.50', 2), 'decimal conversion is exact');

// Server canonicalization and atomic settlement invariants.
$contains("i.status NOT IN ('credited', 'cancelled')", $manager, 'cancelled and credited invoices are not payable');
$contains("user_type = 'client'", $manager, 'contact must be an active client contact');
$contains('client_can_pay_invoice_without_login', $manager, 'public payment setting is enforced below the controller');
$contains("available_on_invoice = 1", $manager, 'payment method is selected server-side');
$contains('expected_amount_minor', $manager, 'expected amount is persisted');
$contains("'currency' => \$currency", $manager, 'canonical currency is persisted');
$contains('FOR UPDATE', $manager, 'attempt, invoice and payments are locked');
$contains("'status' => 'settling'", $manager, 'attempt is claimed before insertion');
$contains("'status' => 'completed'", $manager, 'completion is persisted in the transaction');
$contains('provider_transaction_id', $manager, 'provider transaction is bound and single-use');
$contains('transBegin()', $manager, 'settlement uses a database transaction');
$contains('invoice_balance_changed', $manager, 'concurrent balance changes fail into reconciliation');
$contains("status === 'review_required'", $manager, 'reconciliation callbacks are idempotent');
$contains('attempt_count', $manager, 'checkout creation is bounded per invoice/contact/provider');
$contains('checkout_expired', $manager, 'expired active checkout records are closed');

$contains('uq_legacy_invoice_payment_provider_reference', $migration, 'provider checkout reference is unique');
$contains('uq_legacy_invoice_payment_provider_transaction', $migration, 'provider transaction is unique');
$contains("'expected_amount_minor'", $migration, 'expected amount has a durable field');
$contains("'currency'", $migration, 'expected currency has a durable field');

// Stripe validates both Checkout Session and PaymentIntent against the attempt.
$contains("'legacy_invoice_payment_id'", $stripe, 'Stripe metadata binds the attempt');
$contains('amount_total', $stripe, 'Stripe session amount is exact-matched');
$contains('amount_received', $stripe, 'Stripe received amount is exact-matched');
$contains("payment_status ?? '') !== 'paid'", $stripe, 'Stripe paid status is required');
$contains('settle_invoice_session', $webhooks, 'signed webhook uses shared atomic settlement');
$contains('checkout.session.async_payment_succeeded', $webhooks, 'asynchronous Stripe payments settle only after success');
$contains("'mode' => 'setup'", $stripe, 'existing Stripe subscription setup branch remains available');

// PayPal uses authenticated API execution, strict binding and secure TLS.
$contains("hash_equals((string)\$attempt->provider_reference, \$paymentId)", $paypal, 'PayPal callback payment id matches checkout');
$contains("(string)(\$payment->state ?? '') !== 'approved'", $paypal, 'PayPal must approve the executed payment');
$contains("(string)(\$sale->state ?? '') !== 'completed'", $paypal, 'PayPal must return a completed sale');
$contains('saleAmountMinor', $paypal, 'PayPal completed sale amount is exact-matched too');
$contains('PayPal amount, currency, or attempt binding mismatch.', $paypal, 'PayPal exact match is enforced');
$contains('CURLOPT_SSL_VERIFYPEER, true', $paypal, 'PayPal verifies the TLS certificate chain');
$contains('CURLOPT_SSL_VERIFYHOST, 2', $paypal, 'PayPal verifies the TLS hostname');
$contains('CURL_SSLVERSION_TLSv1_2', $paypal, 'PayPal requires TLS 1.2 or newer');
$contains('CURLOPT_CONNECTTIMEOUT, 10', $paypal, 'PayPal has a connection timeout');
$contains('CURLOPT_TIMEOUT, 30', $paypal, 'PayPal has an overall timeout');

// Paytm cannot be used as a checksum oracle and callback fields are exact-bound.
$contains("\$attempt = \$manager->start('paytm'", $paytm, 'Paytm starts from canonical server state');
$contains("'MID' => (string)\$this->paytm_config->merchant_id", $paytm, 'Paytm merchant id is server-owned');
$contains("'TXN_AMOUNT' => (string)\$attempt->expected_amount", $paytm, 'Paytm amount is server-owned');
$contains('verifychecksum_e', $paytm, 'Paytm callback signature is verified first');
$contains('hash_equals($website_hash, $paytm_hash)', $paytmCrypto, 'Paytm checksum comparison is timing safe and not type-juggled');
$contains('random_int(', $paytmCrypto, 'Paytm outgoing checksum salt uses a CSPRNG');
$notContains('srand(', $paytmCrypto, 'Paytm checksum generation cannot reseed a weak global PRNG');
$contains('Paytm amount, currency, merchant, or order binding mismatch.', $paytm, 'Paytm callback exact match is enforced');
$notContains('verification_data_params', $paytm, 'Paytm no longer serializes browser verification data');
$notContains('getChecksumFromArray($values_array', $paytm, 'Paytm no longer signs arbitrary browser fields');
$contains('access_only_clients()', $invoicePayments, 'logged-in Paytm signing endpoint requires a client');
$contains('client_can_pay_invoice_without_login', $publicInvoice, 'public Paytm endpoint obeys the public payment setting');
$notContains('public_pay_invoice_logs.txt', $publicInvoice, 'public invoice logging cannot write below the web root');
$contains('safe_unserialize(', $publicInvoice, 'public invoice token data cannot instantiate PHP objects');
$contains('random_int(0, $characters_length - 1)', $generalHelper, 'public invoice capability generation uses a CSPRNG');
$contains('make_random_string(32)', $read('app/Controllers/Invoices.php'), 'new public invoice capabilities have high entropy');
$contains('data: {payment_request: paymentRequest}', $paytmView, 'browser sends only an invoice payment request');
$contains('result.input_data', $paytmView, 'form is overwritten with canonical signed fields');

foreach ([$stripeRedirect, $paypalRedirect, $paytmRedirect] as $redirect) {
    $notContains('Invoice_payments_model->ci_save', $redirect, 'redirect cannot directly insert provider-reported values');
    $notContains('get_one_where(array("transaction_id"', $redirect, 'redirect cannot use a check-then-insert race');
}
$notContains('get_array_value($data_array, "TXNAMOUNT")', $paytmRedirect, 'Paytm redirect cannot record posted amount directly');
$notContains('get_array_value($payment->transactions, 0)->amount->total', $paypalRedirect, 'PayPal redirect cannot record returned amount directly');

echo 'Legacy invoice payment hardening contracts passed.' . PHP_EOL;
