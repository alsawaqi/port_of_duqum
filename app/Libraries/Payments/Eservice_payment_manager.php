<?php

namespace App\Libraries\Payments;

use CodeIgniter\Database\BaseConnection;
use Config\EservicesPayments;
use Throwable;

/** Secure checkout initiation and signed, idempotent provider settlement. */
final class Eservice_payment_manager
{
    use Smartpay_payment_processing;

    public const TENDER_FEE = 'tender_fee';
    public const GATE_PASS_FEE = 'gate_pass_fee';
    public const VENDOR_REGISTRATION = 'vendor_registration';
    public const VENDOR_RENEWAL = 'vendor_renewal';

    private BaseConnection $db;
    private EservicesPayments $config;

    public function __construct(?BaseConnection $db = null, ?EservicesPayments $config = null)
    {
        $this->db = $db ?: db_connect();
        $this->config = $config ?: config('EservicesPayments');
    }

    /**
     * @return array{success:bool,status_code:int,message:string,checkout_url?:string,payment_id?:string}
     */
    public function start(
        string $subjectType,
        int $subjectId,
        ?int $vendorId,
        int $userId,
        string $amount,
        string $description,
        string $successUrl,
        string $cancelUrl,
        array $metadata = []
    ): array {
        if (!in_array($subjectType, [self::TENDER_FEE, self::GATE_PASS_FEE, self::VENDOR_REGISTRATION, self::VENDOR_RENEWAL], true)
            || $subjectId < 1
            || $userId < 1
        ) {
            return $this->failure(422, 'The payment request is invalid.');
        }
        if (!$this->config->isReady() || !$this->db->tableExists('eservice_payments')) {
            return $this->failure(503, 'Online payment is not configured. No fee has been marked as paid.');
        }
        $smartpay = $this->config->provider === 'bank_muscat';
        if (array_key_exists('currency', $metadata)
            && strtoupper(trim((string)$metadata['currency'])) !== $this->config->currency) {
            return $this->failure(422, 'The fee currency does not match the configured payment currency. No checkout was created.');
        }
        if ($smartpay && !$this->smartpaySchemaReady()) {
            return $this->failure(503, 'The SmartPay database upgrade is required before taking payments.');
        }

        try {
            $gateway = Payment_gateway_factory::make($this->config);
            $currency = $this->config->currency;
            $amountMinor = Payment_amount::toMinor($amount, $gateway->minorUnitExponent($currency));
        } catch (Throwable $exception) {
            log_message('error', 'E-SERVICE PAYMENT CONFIGURATION ERROR: {class}', ['class' => get_class($exception)]);
            return $this->failure(503, 'Online payment cannot represent this fee exactly or is not configured. No payment was recorded.');
        }

        $table = $this->db->prefixTable('eservice_payments');
        $now = get_current_utc_time();
        $this->db->transBegin();
        try {
            // Never charge an already paid subject twice. A vendor renewal has its own immutable quote/cycle.
            $paid = $this->db->query(
                "SELECT metadata FROM {$table} WHERE subject_type = ? AND subject_id = ? AND vendor_id <=> ? AND deleted = 0 AND status = 'paid' FOR UPDATE",
                [$subjectType, $subjectId, $vendorId]
            )->getResult();
            foreach ($paid as $prior) {
                $priorMetadata = json_decode((string)$prior->metadata, true) ?: [];
                if (!in_array($subjectType, [self::VENDOR_REGISTRATION, self::VENDOR_RENEWAL], true)
                    || (int)($priorMetadata['vendor_fee_request_id'] ?? 0) === (int)($metadata['vendor_fee_request_id'] ?? 0)) {
                    $this->db->transRollback();
                    return $this->failure(409, 'Payment has already been received for this fee. Contact accounting if reconciliation is required.');
                }
            }
            $existing = $this->db->query(
                "SELECT * FROM {$table}
                 WHERE subject_type = ? AND subject_id = ? AND vendor_id <=> ?
                   AND deleted = 0 AND status IN ('pending', 'processing', 'verification_required')
                 ORDER BY id DESC LIMIT 1 FOR UPDATE",
                [$subjectType, $subjectId, $vendorId]
            )->getRow();

            if ($existing) {
                if ((string)$existing->status === 'verification_required' || ($smartpay && !empty($existing->handed_off_at))) {
                    $this->db->transRollback();
                    return $this->failure(409, 'A bank transaction is awaiting confirmation. Please return from the bank or ask accounting to recheck its status before paying again.');
                }
                if ((int)$existing->amount_minor !== $amountMinor || (string)$existing->currency !== $currency || (string)$existing->provider !== $this->config->provider) {
                    $this->db->transRollback();
                    return $this->failure(409, 'The fee changed while a checkout was open. Accounting must reconcile the existing transaction first.');
                }
                $expiresAt = strtotime((string)($existing->expires_at ?? '') . ' UTC') ?: 0;
                if ($expiresAt > time() && !empty($existing->checkout_url)) {
                    $this->db->transCommit();
                    return [
                        'success' => true,
                        'status_code' => 200,
                        'message' => 'Continue to the secure payment provider.',
                        'checkout_url' => (string)$existing->checkout_url,
                        'payment_id' => (string)$existing->public_id,
                    ];
                }
                if ($expiresAt > time() && empty($existing->checkout_url)) {
                    $this->db->transRollback();
                    return $this->failure(409, 'A payment checkout is already being prepared. Please wait and try again.');
                }
                $this->db->table($table)->where('id', (int)$existing->id)->update([
                    'status' => 'expired',
                    'updated_at' => $now,
                ]);
            }

            $publicId = bin2hex(random_bytes(16));
            $idempotencyKey = bin2hex(random_bytes(32));
            $orderId = $smartpay ? 'POD' . strtoupper(bin2hex(random_bytes(11))) : null;
            $row = [
                'public_id' => $publicId,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'vendor_id' => $vendorId,
                'user_id' => $userId,
                'amount' => Payment_amount::fromMinor($amountMinor, $gateway->minorUnitExponent($currency)),
                'amount_minor' => $amountMinor,
                'currency' => $currency,
                'provider' => $this->config->provider,
                'status' => 'pending',
                'idempotency_key' => $idempotencyKey,
                'metadata' => json_encode(array_merge($metadata, ['description' => mb_substr($description, 0, 120), 'success_url' => $successUrl, 'cancel_url' => $cancelUrl]), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'initiated_at' => $now,
                'expires_at' => gmdate('Y-m-d H:i:s', time() + $this->config->checkoutTtlSeconds),
                'created_at' => $now,
                'updated_at' => $now,
                'deleted' => 0,
            ];
            if ($smartpay) {
                $row['provider_checkout_id'] = $orderId;
                $row['gateway_merchant_id'] = $this->config->smartpayMerchantId;
            }
            $inserted = $this->db->table($table)->insert($row);
            if (!$inserted || $this->db->transStatus() === false) {
                throw new \RuntimeException('Unable to create the payment record.');
            }
            $paymentId = (int)$this->db->insertID();
            if ($smartpay) {
                $this->insertSmartpayEvent($paymentId, 'checkout.created', 'recorded', [], [], hash('sha256', (string)$orderId));
            }
            $this->db->transCommit();

            try {
                $checkout = $gateway->createCheckout([
                    'public_id' => $publicId,
                    'order_id' => $orderId,
                    'subject_type' => $subjectType,
                    'subject_id' => $subjectId,
                    'amount_minor' => $amountMinor,
                    'currency' => $currency,
                    'description' => $description,
                    'success_url' => $successUrl,
                    'cancel_url' => $cancelUrl,
                    'idempotency_key' => $idempotencyKey,
                ]);
                $updated = $this->db->table($table)->where('id', $paymentId)->where('status', 'pending')->update([
                    'status' => 'processing',
                    'provider_checkout_id' => $checkout['checkout_id'],
                    'checkout_url' => $checkout['checkout_url'],
                    'expires_at' => gmdate('Y-m-d H:i:s', $checkout['expires_at']),
                    'updated_at' => get_current_utc_time(),
                ]);
                if (!$updated) {
                    throw new \RuntimeException('Unable to bind the checkout to the payment record.');
                }

                return [
                    'success' => true,
                    'status_code' => 200,
                    'message' => 'Continue to the secure payment provider.',
                    'checkout_url' => $checkout['checkout_url'],
                    'payment_id' => $publicId,
                ];
            } catch (Throwable $exception) {
                $this->db->table($table)->where('id', $paymentId)->whereIn('status', ['pending', 'processing'])->update([
                    'status' => 'failed',
                    'failed_at' => get_current_utc_time(),
                    'failure_code' => 'provider_checkout_failed',
                    'checkout_url' => null,
                    'updated_at' => get_current_utc_time(),
                ]);
                if ($smartpay) {
                    $this->insertSmartpayEvent($paymentId, 'checkout.failed', 'rejected', [], ['provider_checkout_failed']);
                }
                log_message('error', 'E-SERVICE CHECKOUT CREATION FAILED: {class}', ['class' => get_class($exception)]);
                return $this->failure(503, 'The secure payment provider is unavailable. No fee has been marked as paid.');
            }
        } catch (Throwable $exception) {
            $this->db->transRollback();
            log_message('error', 'E-SERVICE PAYMENT START FAILED: {class}', ['class' => get_class($exception)]);
            return $this->failure(409, 'A payment is already in progress or could not be started. No fee has been marked as paid.');
        }
    }

    /** @return array{success:bool,status_code:int,message:string} */
    public function processStripeWebhook(string $rawBody, string $signature): array
    {
        if ($this->config->provider !== 'stripe' || !$this->config->isReady()
            || !$this->db->tableExists('eservice_payments')
            || !$this->db->tableExists('eservice_payment_events')
        ) {
            return $this->failure(503, 'Payment processing is unavailable.');
        }

        try {
            $gateway = Payment_gateway_factory::make($this->config);
            $event = $gateway->verifyWebhook($rawBody, $signature);
        } catch (Throwable $exception) {
            log_message('warning', 'E-SERVICE PAYMENT WEBHOOK SIGNATURE REJECTED: {class}', ['class' => get_class($exception)]);
            return $this->failure(400, 'Invalid payment notification.');
        }

        $eventId = trim((string)($event->id ?? ''));
        $eventType = trim((string)($event->type ?? ''));
        if ($eventId === '' || $eventType === '') {
            return $this->failure(400, 'Invalid payment notification.');
        }

        if (!in_array($eventType, [
            'checkout.session.completed',
            'checkout.session.async_payment_succeeded',
            'checkout.session.async_payment_failed',
            'checkout.session.expired',
        ], true)) {
            return ['success' => true, 'status_code' => 200, 'message' => 'Payment notification ignored.'];
        }

        $session = $event->data->object ?? null;
        $publicId = trim((string)($session->metadata->eservice_payment_id ?? $session->client_reference_id ?? ''));
        if ($publicId === '' || preg_match('/^[a-f0-9]{32}$/', $publicId) !== 1) {
            return $this->failure(400, 'Invalid payment notification metadata.');
        }

        $payments = $this->db->prefixTable('eservice_payments');
        $events = $this->db->prefixTable('eservice_payment_events');
        $this->db->transBegin();
        try {
            $duplicate = $this->db->query(
                "SELECT id FROM {$events} WHERE provider = 'stripe' AND provider_event_id = ? LIMIT 1 FOR UPDATE",
                [$eventId]
            )->getRow();
            if ($duplicate) {
                $this->db->transCommit();
                return ['success' => true, 'status_code' => 200, 'message' => 'Payment notification already processed.'];
            }

            $payment = $this->db->query(
                "SELECT * FROM {$payments} WHERE public_id = ? AND provider = 'stripe' AND deleted = 0 LIMIT 1 FOR UPDATE",
                [$publicId]
            )->getRow();
            if (!$payment || (string)$payment->provider_checkout_id !== (string)($session->id ?? '')) {
                throw new \DomainException('The payment notification does not match a checkout.');
            }

            $eventRow = [
                'payment_id' => (int)$payment->id,
                'provider' => 'stripe',
                'provider_event_id' => $eventId,
                'event_type' => $eventType,
                'payload_sha256' => hash('sha256', $rawBody),
                'status' => 'received',
                'received_at' => get_current_utc_time(),
                'created_at' => get_current_utc_time(),
            ];
            if (!$this->db->table($events)->insert($eventRow)) {
                throw new \RuntimeException('Unable to save the provider event.');
            }
            $eventRowId = (int)$this->db->insertID();

            $failureEvent = in_array($eventType, ['checkout.session.async_payment_failed', 'checkout.session.expired'], true);
            if ($failureEvent) {
                $status = $eventType === 'checkout.session.expired' ? 'expired' : 'failed';
                $this->db->table($payments)->where('id', (int)$payment->id)->whereIn('status', ['pending', 'processing'])->update([
                    'status' => $status,
                    'failed_at' => get_current_utc_time(),
                    'failure_code' => $status === 'expired' ? 'checkout_expired' : 'provider_payment_failed',
                    'checkout_url' => null,
                    'updated_at' => get_current_utc_time(),
                ]);
            } else {
                $this->assertPaidSessionMatches($payment, $session);
                if ((string)$payment->status !== 'paid') {
                    $this->applyPaidSubject($payment, (string)($session->payment_intent ?? $session->id));
                    $this->db->table($payments)->where('id', (int)$payment->id)->update([
                        'status' => 'paid',
                        'provider_payment_id' => (string)($session->payment_intent ?? $session->id),
                        'checkout_url' => null,
                        'paid_at' => get_current_utc_time(),
                        'failure_code' => null,
                        'updated_at' => get_current_utc_time(),
                    ]);
                }
            }

            $this->db->table($events)->where('id', $eventRowId)->update([
                'status' => 'processed',
                'processed_at' => get_current_utc_time(),
            ]);
            if ($this->db->transStatus() === false) {
                throw new \RuntimeException('The payment transaction failed.');
            }
            $this->db->transCommit();
            return ['success' => true, 'status_code' => 200, 'message' => 'Payment notification processed.'];
        } catch (Throwable $exception) {
            $this->db->transRollback();
            log_message('error', 'E-SERVICE PAYMENT WEBHOOK PROCESSING FAILED: {class}', ['class' => get_class($exception)]);
            return $this->failure(400, 'Payment notification could not be verified against the pending fee.');
        }
    }

    private function assertPaidSessionMatches(object $payment, object $session): void
    {
        if ((string)($session->payment_status ?? '') !== 'paid'
            || strtolower((string)($session->currency ?? '')) !== strtolower((string)$payment->currency)
            || (int)($session->amount_total ?? -1) !== (int)$payment->amount_minor
        ) {
            throw new \DomainException('Provider amount, currency, or payment status mismatch.');
        }
    }

    private function applyPaidSubject(object $payment, string $providerReference): void
    {
        if ((string)$payment->subject_type === self::TENDER_FEE) {
            $this->applyPaidTenderFee($payment, $providerReference);
            return;
        }
        if ((string)$payment->subject_type === self::GATE_PASS_FEE) {
            $this->applyPaidGatePassFee($payment, $providerReference);
            return;
        }
        if (in_array((string)$payment->subject_type, [self::VENDOR_REGISTRATION, self::VENDOR_RENEWAL], true)) {
            Vendor_payment_settlement::apply($this->db, $payment, $providerReference);
            return;
        }

        throw new \DomainException('Unknown payment subject.');
    }

    private function applyPaidTenderFee(object $payment, string $providerReference): void
    {
        if (!$this->db->tableExists('tender_fee_payments')) {
            throw new \RuntimeException('Tender fee payment table is unavailable.');
        }
        $tenders = $this->db->prefixTable('tenders');
        $tender = $this->db->query("SELECT * FROM {$tenders} WHERE id = ? AND deleted = 0 LIMIT 1 FOR UPDATE", [(int)$payment->subject_id])->getRow();
        if (!$tender || Payment_amount::toMinor((string)($tender->tender_fee ?? '0'), 3) !== (int)$payment->amount_minor
            || strtoupper((string)($tender->currency ?? 'OMR')) !== (string)$payment->currency) {
            throw new \DomainException('The tender fee changed after checkout; accounting reconciliation is required.');
        }
        $table = $this->db->prefixTable('tender_fee_payments');
        $existing = $this->db->query(
            "SELECT id FROM {$table} WHERE tender_id = ? AND vendor_id = ? AND deleted = 0 ORDER BY id DESC LIMIT 1 FOR UPDATE",
            [(int)$payment->subject_id, (int)$payment->vendor_id]
        )->getRow();
        $data = [
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'status' => 'paid',
            'payment_reference' => $providerReference,
            'paid_at' => get_current_utc_time(),
            'updated_at' => get_current_utc_time(),
            'deleted' => 0,
        ];
        if ($existing) {
            $this->db->table($table)->where('id', (int)$existing->id)->update($data);
        } else {
            $data['tender_id'] = (int)$payment->subject_id;
            $data['vendor_id'] = (int)$payment->vendor_id;
            $data['created_by'] = (int)$payment->user_id;
            $data['created_at'] = get_current_utc_time();
            $this->db->table($table)->insert($data);
        }
    }

    private function applyPaidGatePassFee(object $payment, string $providerReference): void
    {
        $requests = $this->db->prefixTable('gate_pass_requests');
        $approvals = $this->db->prefixTable('gate_pass_request_approvals');
        $request = $this->db->query(
            "SELECT * FROM {$requests} WHERE id = ? AND deleted = 0 LIMIT 1 FOR UPDATE",
            [(int)$payment->subject_id]
        )->getRow();
        if (!$request) {
            throw new \DomainException('Gate-pass request no longer exists.');
        }
        if ((int)$request->requester_id !== (int)$payment->user_id) {
            throw new \DomainException('The gate-pass payer no longer matches the request owner.');
        }
        if ((int)($request->fee_is_waived ?? 0) === 1) {
            throw new \DomainException('A waived fee cannot be settled as an online payment.');
        }
        if (Payment_amount::toMinor((string)($request->fee_amount ?? '0'), 3) !== (int)$payment->amount_minor
            || strtoupper((string)($request->currency ?? 'OMR')) !== (string)$payment->currency) {
            throw new \DomainException('The gate-pass fee changed after checkout; accounting reconciliation is required.');
        }

        // Received money is already durable. A concurrent return/rejection must
        // remain visible as unapplied funds until the request is eligible again.
        if ((string)$request->status !== 'department_approved') {
            throw new \DomainException('The gate-pass workflow changed after checkout; accounting reconciliation is required.');
        }
        if ((string)$request->status === 'department_approved') {
            $this->db->table($requests)->where('id', (int)$request->id)->update([
                'status' => 'commercial_approved',
                'stage' => 'security',
                'stage_updated_at' => get_current_utc_time(),
            ]);
            $alreadyApproved = $this->db->query(
                "SELECT id FROM {$approvals}
                 WHERE gate_pass_request_id = ? AND stage = 'commercial' AND decision = 'approved'
                 ORDER BY id DESC LIMIT 1 FOR UPDATE",
                [(int)$request->id]
            )->getRow();
            if (!$alreadyApproved) {
                $this->db->table($approvals)->insert([
                    'gate_pass_request_id' => (int)$request->id,
                    'stage' => 'commercial',
                    'decision' => 'approved',
                    'comment' => 'Online payment verified: ' . mb_substr($providerReference, 0, 120),
                    'decided_by' => (int)$payment->user_id,
                    'decided_at' => get_current_utc_time(),
                    'ip_address' => null,
                    'user_agent' => 'Verified payment webhook',
                ]);
            }
        }
    }

    private function failure(int $statusCode, string $message): array
    {
        return ['success' => false, 'status_code' => $statusCode, 'message' => $message];
    }
}
