<?php

namespace CodeIgniter\Config {
    class BaseConfig { public function __construct() {} }
}
namespace CodeIgniter\Database {
    class BaseConnection {}
}
namespace {
    $environment = [];
    function env($key, $default = null) { global $environment; return $environment[$key] ?? $default; }
    function get_uri($path = '') { return 'http://localhost:8095/' . $path; }
    function get_current_utc_time() { return gmdate('Y-m-d H:i:s'); }
    function log_message($level, $message, $context = []) {}
    require_once __DIR__ . '/../app/Config/EservicesPayments.php';
    require_once __DIR__ . '/../app/Libraries/Payments/Payment_gateway_interface.php';
    require_once __DIR__ . '/../app/Libraries/Payments/Payment_amount.php';
    require_once __DIR__ . '/../app/Libraries/Payments/Bank_muscat_gateway.php';
    require_once __DIR__ . '/../app/Libraries/Payments/Smartpay_payment_processing.php';
    require_once __DIR__ . '/../app/Libraries/Payments/Eservice_payment_manager.php';

    use App\Libraries\Payments\Bank_muscat_gateway;
    use App\Libraries\Payments\Smartpay_payment_processing;
    use Config\EservicesPayments;

    $checks = 0;
    function check($condition, string $message): void {
        global $checks;
        if (!$condition) { throw new RuntimeException($message); }
        $checks++;
    }
    function rejects(callable $call, string $message): void {
        try { $call(); } catch (Throwable $e) { check(true, $message); return; }
        check(false, $message);
    }
    $config = new EservicesPayments();
    check(!$config->isReady(), 'Missing credentials must fail closed.');
    $environment = [
        'eservices.payment.provider' => 'bank_muscat',
        'eservices.payment.smartpayMerchantId' => '123',
        'eservices.payment.smartpayAccessCode' => 'unit-test-access-code',
        'eservices.payment.smartpayWorkingKey' => '0123456789abcdef0123456789abcdef',
        'eservices.payment.smartpayPublicBaseUrl' => 'http://localhost:8095',
    ];
    $config = new EservicesPayments();
    check($config->isReady(), 'A complete localhost UAT configuration is accepted.');
    $environment['eservices.payment.smartpayEnvironment'] = 'production';
    check(!(new EservicesPayments())->isReady(), 'Production must refuse HTTP callbacks.');
    $environment['eservices.payment.smartpayEnvironment'] = 'uat';
    $gateway = new Bank_muscat_gateway($config);
    $key = $config->smartpayWorkingKey;
    // Generated independently with the supplied example's Node crypto AES-256-GCM algorithm.
    $fixture = '00112233445566778899aabbccddeeffad6d3536cddaa6bfc8a526d56631dd2783b6964d6b8631ae133decc8c9e4cf366a8f39f70b201d9bf406cdf4e387698d3a6d4a034d1c967d7caae48e8de5e62c556190de10b7fceacc1ad63f4e55fbf383622c4908125a125aef3784632e27af8926';
    $plain = 'order_id=POD123&amount=10.123&currency=OMR&order_status=Success&tracking_id=123456';
    check(Bank_muscat_gateway::decrypt($fixture, $key) === $plain, 'PHP decrypts the independently generated Node fixture exactly.');
    $cipherA = Bank_muscat_gateway::encrypt($plain, $key);
    $cipherB = Bank_muscat_gateway::encrypt($plain, $key);
    check($cipherA !== $cipherB, 'Every encrypted request uses a fresh random IV.');
    check(Bank_muscat_gateway::decrypt($cipherA, $key) === $plain, 'Authenticated encryption round trip preserves the bank fields.');
    foreach ([0, 40, strlen($cipherA) - 1] as $position) {
        $tampered = $cipherA;
        $tampered[$position] = $tampered[$position] === '0' ? '1' : '0';
        rejects(fn() => Bank_muscat_gateway::decrypt($tampered, $key), 'Tampered IV, ciphertext or authentication tag must fail.');
    }
    rejects(fn() => Bank_muscat_gateway::decrypt($cipherA, str_repeat('a', 32)), 'Wrong merchant key must fail.');
    rejects(fn() => Bank_muscat_gateway::parseResponse('amount=1&amount=2'), 'Duplicate fields are rejected.');
    rejects(fn() => Bank_muscat_gateway::parseResponse('amount[]=1'), 'Array field injection is rejected.');
    $safe = Bank_muscat_gateway::safeResponse(['tracking_id' => '991', 'order_status' => 'Success', 'card_number' => '4111111111111111', 'cvv' => '123', 'merchant_param7' => '12/30', 'billing_name' => 'Person', 'customer_card_id' => 'secret-token']);
    check($safe === ['tracking_id' => '991', 'order_status' => 'Success', 'masked_card' => '**** 1111'], 'Stored JSON retains only the card last four, excluding PAN, card tokens and billing PII.');
    check(Bank_muscat_gateway::safeResponse($safe) === $safe, 'Sanitizing stored bank JSON again preserves the masked last four.');
    check(Bank_muscat_gateway::safeResponse(['masked_card_number' => '4111-11XX-XXXX-2233']) === ['masked_card' => '**** 2233'], 'A bank-masked card is reduced to the last four without retaining the BIN.');
    check(Bank_muscat_gateway::safeResponse(['merchant_param6' => '411111XXXXXX2233', 'merchant_param7' => '12/30']) === ['masked_card' => '**** 2233'], 'The bank documented merchant_param6 becomes masked last four while expiry param7 is discarded.');
    check(Bank_muscat_gateway::safeResponse(['card_number' => '123', 'cvv' => '123', 'expiry' => '12/30', 'card_token' => 'abc']) === [], 'Short card-like values and security data cannot become retained card information.');
    $message = Bank_muscat_gateway::safeResponse(['failure_message' => 'Rejected 4111 1111 1111 1111 CVV: 123 expiry=12/30', 'bank_ref_no' => '12345678901234567890']);
    check(!str_contains($message['failure_message'], '4111') && !str_contains($message['failure_message'], '123') && !str_contains($message['failure_message'], '12/30'), 'Free-text messages cannot store echoed PAN, security codes or expiry.');
    check($message['bank_ref_no'] === '12345678901234567890', 'Long numeric bank references remain available for reconciliation.');

    $payment = (object)[
        'id' => 1, 'provider_checkout_id' => 'POD123', 'gateway_merchant_id' => '123',
        'public_id' => str_repeat('a', 32), 'amount_minor' => 10123, 'amount' => '10.123', 'currency' => 'OMR',
        'initiated_at' => gmdate('Y-m-d H:i:s', time() - 30),
        'status' => 'processing', 'verified_at' => null, 'handed_off_at' => get_current_utc_time(),
        'settlement_status' => 'pending', 'provider_payment_id' => '', 'deleted' => 0,
        'subject_type' => 'gate_pass_fee', 'subject_id' => 1, 'vendor_id' => null, 'metadata' => '{}',
    ];
    $response = Bank_muscat_gateway::parseResponse($plain);
    check($gateway->bindingIssues($payment, $response) === [], 'An authenticated callback binds exact order, OMR amount and merchant.');
    foreach (['order_id' => 'POD999', 'amount' => '10.124', 'currency' => 'USD', 'merchant_id' => '999', 'order_status' => ''] as $field => $wrong) {
        check(count($gateway->bindingIssues($payment, array_merge($response, [$field => $wrong]))) > 0, 'Reject callback mismatch for ' . $field);
    }
    $apiResponse = ['order_no' => 'POD123', 'order_amt' => '10.123', 'order_currncy' => 'OMR', 'order_status' => 'Successful', 'reference_no' => '123456', 'status' => '0',
        'order_date_time' => (new DateTimeImmutable('now', new DateTimeZone('Asia/Muscat')))->format('Y-m-d H:i:s')];
    check($gateway->bindingIssues($payment, $apiResponse, true) === [], 'Status API validates the observed Bank Muscat currency alias and Oman timestamp.');
    foreach (['order_no', 'order_amt', 'order_currncy', 'reference_no', 'order_date_time'] as $field) {
        $missing = $apiResponse;
        unset($missing[$field]);
        check(count($gateway->bindingIssues($payment, $missing, true)) > 0, 'API confirmation requires ' . $field);
    }
    check(count($gateway->bindingIssues($payment, array_merge($apiResponse, ['order_date_time' => '2000-01-01 00:00:00']), true)) > 0, 'A replayed old order timestamp is rejected.');
    check(Bank_muscat_gateway::statusCategory('Awaited') === 'verification_required', 'Awaited never means paid.');
    check(Bank_muscat_gateway::statusCategory('Timeout') === 'verification_required', 'Observed UAT Timeout requires inquiry, never settlement or a new charge.');
    check(Bank_muscat_gateway::statusCategory('Initiated') === 'verification_required', 'An initiated bank order is not a final payment outcome.');
    check(Bank_muscat_gateway::statusCategory('Failure') === 'failed', 'Observed UAT incorrect-OTP failure is a final failed attempt.');
    check(Bank_muscat_gateway::statusCategory('Refunded') === 'verification_required', 'Refunded is not payment success.');
    check(Bank_muscat_gateway::statusCategory('Aborted') === 'cancelled', 'Customer cancellation is distinct from failure.');
    check(Bank_muscat_gateway::statusCategory('Successful') === 'paid', 'Documented status API success is recognized.');
    check(Bank_muscat_gateway::timestampsMatch('2026-09-05 12:10:00', '2026-09-05 12:10:00.0'), 'Zero fractional seconds are equivalent without stripping meaningful seconds.');
    check(Bank_muscat_gateway::timestampsMatch('2026-09-05 12:10:10.120', '2026-09-05 12:10:10.12'), 'Equivalent fractional precision is normalized.');
    check(!Bank_muscat_gateway::timestampsMatch('2026-09-05 12:10:10', '2026-09-05 12:10:01'), 'Different seconds remain different.');
    check(!Bank_muscat_gateway::timestampsMatch('invalid', 'invalid'), 'Malformed timestamps cannot compare as verified.');
    check(Bank_muscat_gateway::timestampsMatch('05/09/2026 18:45:32', '2026-09-05 18:45:32.467'), 'Observed hosted return and API dates agree at their shared second precision.');
    check(!Bank_muscat_gateway::timestampsMatch('05/09/2026 18:45:32', '2026-09-05 18:45:33.467'), 'Different seconds remain a mismatch across bank date formats.');
    check(!Bank_muscat_gateway::timestampsMatch('2026-09-05 18:45:32.467', '2026-09-05 18:45:32.468'), 'When both responses specify fractions, differences remain a mismatch.');
    $hostedDate = (new DateTimeImmutable('now', new DateTimeZone('Asia/Muscat')))->format('d/m/Y H:i:s');
    check($gateway->bindingIssues($payment, array_merge($response, ['order_date_time' => $hostedDate])) === [], 'Hosted DD/MM/YYYY Oman timestamps pass full payment binding validation.');
    $cancelledReturn = array_merge($response, ['order_status'=>'Aborted', 'order_date_time'=>'null', 'bank_ref_no'=>'null', 'card_name'=>'null']);
    check($gateway->bindingIssues($payment, $cancelledReturn) === [], 'A bank cancellation may omit optional callback time and reference using literal null.');
    check(Bank_muscat_gateway::normalizedResponse($cancelledReturn)['bank_ref_no'] === '', 'Missing bank reference does not falsely conflict with the API.');
    check(!isset(Bank_muscat_gateway::safeResponse($cancelledReturn)['card_name']), 'Accounting does not display literal null as a card name.');
    check(in_array('order_timestamp_missing_or_invalid', $gateway->bindingIssues($payment, array_merge($apiResponse, ['order_date_time'=>'null']), true), true), 'The independent API timestamp is still mandatory.');
    $form = $gateway->hostedForm($payment);
    check(array_keys($form['fields']) === ['access_code', 'encRequest'], 'Hosted POST exposes only bank-required fields.');
    check(!str_contains(json_encode($form), $key), 'The working key is never included in browser data.');
    $request = Bank_muscat_gateway::parseResponse(Bank_muscat_gateway::decrypt($form['fields']['encRequest'], $key));
    check($request['amount'] === '10.123' && $request['merchant_id'] === '123', 'Hosted request uses the immutable stored fee and merchant.');
    check($request['redirect_url'] === 'http://localhost:8095/eservice_payment/return_from_bank', 'The registered base URL determines the callback.');

    /** A small transactional store exercises settlement behavior without a live database or bank. */
    class PaymentMemoryDb extends \CodeIgniter\Database\BaseConnection {
        public object $payment;
        public array $events = [];
        public array $priorPaid = [];
        public ?array $snapshot = null;
        public function __construct(object $payment) { $this->payment = clone $payment; }
        public function tableExists($name) { return true; }
        public function fieldExists($name, $table) { return true; }
        public function prefixTable($name) { return $name; }
        public function transBegin() { $this->snapshot = [clone $this->payment, $this->events]; }
        public function transCommit() { $this->snapshot = null; }
        public function transRollback() { if ($this->snapshot) { [$this->payment, $this->events] = $this->snapshot; $this->snapshot = null; } }
        public function transStatus() { return true; }
        public function query($sql, $args) {
            $row = clone $this->payment;
            if (str_contains($sql, 'provider_checkout_id = ?') && $args[0] !== $row->provider_checkout_id) { $row = null; }
            return new class($row, $this->priorPaid) {
                public function __construct(private $row, private array $rows) {}
                public function getRow() { return $this->row; }
                public function getResult() { return $this->rows; }
            };
        }
        public function table($table) {
            return new class($this, $table) {
                private PaymentMemoryDb $db; private string $table;
                public function __construct($db, $table) { $this->db = $db; $this->table = $table; }
                public function where($key, $value = null) { return $this; }
                public function update(array $values) { foreach ($values as $key => $value) { $this->db->payment->$key = $value; } return true; }
                public function insert(array $row) { $this->db->events[] = $row; return true; }
            };
        }
    }
    class SettlementHarness {
        use Smartpay_payment_processing;
        private PaymentMemoryDb $db;
        private EservicesPayments $config;
        public int $applications = 0;
        public bool $rejectSettlement = false;
        public function __construct(PaymentMemoryDb $db, EservicesPayments $config) { $this->db = $db; $this->config = $config; }
        private function applyPaidSubject($payment, $ref) { if ($this->rejectSettlement) { throw new DomainException('Fee changed.'); } $this->applications++; }
        private function failure($status, $message) { return ['success' => false, 'status_code' => $status, 'message' => $message]; }
    }
    $paid = clone $payment;
    $paid->status = 'paid'; $paid->verified_at = get_current_utc_time(); $paid->provider_payment_id = '123456';
    $db = new PaymentMemoryDb($paid);
    $manager = new SettlementHarness($db, $config);
    $manager->rejectSettlement = true;
    $manager->recheckSmartpayPayment(1);
    check($db->payment->status === 'paid' && $db->payment->settlement_status === 'review_required', 'Workflow failure preserves independently verified funds and requires accounting review.');
    $manager->rejectSettlement = false;
    $manager->recheckSmartpayPayment(1);
    $manager->recheckSmartpayPayment(1);
    check($manager->applications === 1 && $db->payment->settlement_status === 'applied', 'Repeated settlement applies the fee once; failed business transition can safely recover.');
    $lateFailure = Bank_muscat_gateway::encrypt(str_replace('Success', 'Failure', $plain), $key);
    $manager->processSmartpayReturn($lateFailure, 'POD123');
    check($db->payment->status === 'paid' && $manager->applications === 1, 'A late failed callback cannot downgrade paid funds or replay settlement.');
    $extraReference = Bank_muscat_gateway::encrypt(str_replace('123456', '654321', $plain), $key);
    $manager->processSmartpayReturn($extraReference, 'POD123');
    $manager->recheckSmartpayPayment(1);
    check($db->payment->status === 'paid' && $db->payment->provider_payment_id === '123456'
        && $db->payment->settlement_status === 'applied' && str_contains($db->payment->verification_issues, 'bank_tracking_reference_conflict') && $manager->applications === 1,
        'A second bank reference is flagged without replacing original funds, revoking applied service or clearing the review on retry.');
    $unpaidDb = new PaymentMemoryDb($payment);
    $retry = clone $payment;
    $retry->handed_off_at = null;
    $retry->expires_at = gmdate('Y-m-d H:i:s', time() + 600);
    $retryDb = new PaymentMemoryDb($retry);
    $retryDb->priorPaid = [(object)['metadata' => '{}']];
    $retryManager = new SettlementHarness($retryDb, $config);
    $duplicateHandoff = $retryManager->prepareSmartpayHandoff($retry->public_id);
    check($duplicateHandoff['status_code'] === 409 && $retryDb->payment->status === 'expired' && empty($retryDb->payment->handed_off_at),
        'A retry cannot reach the bank when an older attempt was paid after retry creation.');
    $nextCycle = clone $retry;
    $nextCycle->subject_type = 'vendor_renewal';
    $nextCycle->vendor_id = 1;
    $nextCycle->metadata = '{"vendor_fee_request_id":88}';
    $nextCycleDb = new PaymentMemoryDb($nextCycle);
    $nextCycleDb->priorPaid = [(object)['metadata' => '{"vendor_fee_request_id":77}']];
    $nextCycleManager = new SettlementHarness($nextCycleDb, $config);
    check($nextCycleManager->prepareSmartpayHandoff($nextCycle->public_id)['success'] === true,
        'A paid previous renewal cycle does not block the current distinct renewal cycle.');
    $feePayment = clone $payment;
    $feePayment->user_id = 2;
    $feePayment->subject_id = 1;
    $returnedRequest = clone $payment;
    $returnedRequest->requester_id = 2;
    $returnedRequest->fee_amount = '10.123';
    $returnedRequest->fee_is_waived = 0;
    $returnedRequest->status = 'returned';
    $returnedDb = new PaymentMemoryDb($returnedRequest);
    $returnedManager = new \App\Libraries\Payments\Eservice_payment_manager($returnedDb, $config);
    $gateSettlement = new ReflectionMethod($returnedManager, 'applyPaidGatePassFee');
    rejects(fn() => $gateSettlement->invoke($returnedManager, $feePayment, '123456'), 'A gate pass returned during checkout cannot be silently marked as applied.');
    $initiation = new \App\Libraries\Payments\Eservice_payment_manager($unpaidDb, $config);
    $currencyResult = $initiation->start('gate_pass_fee', 1, null, 2, '10.123', 'Gate pass fee', get_uri('gate_pass_portal'), get_uri('gate_pass_portal'), ['currency' => 'USD']);
    check($currencyResult['status_code'] === 422 && count($unpaidDb->events) === 0, 'A source fee in a different currency is rejected before creating a bank attempt.');
    $unpaidManager = new SettlementHarness($unpaidDb, $config);
    $mismatch = Bank_muscat_gateway::encrypt(str_replace('10.123', '1.000', $plain), $key);
    $unpaidManager->processSmartpayReturn($mismatch, 'POD123');
    check($unpaidDb->payment->status === 'verification_required' && $unpaidManager->applications === 0, 'A mismatched amount is retained as evidence and never advances the subject.');
    check(str_contains($unpaidDb->payment->response_json, '1.000'), 'Mismatched decrypted response is available to accounting.');
    $eventsBefore = count($unpaidDb->events);
    $unpaidManager->processSmartpayReturn('invalid', 'POD123');
    check(count($unpaidDb->events) === $eventsBefore + 1 && end($unpaidDb->events)['payment_id'] === null, 'Unauthenticated outer order id cannot target a payment; rejected event remains logged.');
    echo 'Bank Muscat payment behavior: ' . $checks . " checks passed.\n";
}
