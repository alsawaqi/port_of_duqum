<?php

namespace App\Libraries\Payments;

use Throwable;

/** Durable callback capture, independent bank verification, and recoverable business settlement. */
trait Smartpay_payment_processing
{
    private function smartpaySchemaReady(): bool
    {
        return $this->db->tableExists('eservice_payments')
            && $this->db->tableExists('eservice_payment_events')
            && $this->db->fieldExists('settlement_status', 'eservice_payments')
            && $this->db->fieldExists('response_json', 'eservice_payment_events');
    }

    private function insertSmartpayEvent(?int $paymentId, string $type, string $status, array $response = [], array $issues = [], string $digest = ''): void
    {
        $now = get_current_utc_time();
        $safe = Bank_muscat_gateway::safeResponse($response);
        if (!$this->db->table('eservice_payment_events')->insert([
            'payment_id' => $paymentId, 'provider' => 'bank_muscat',
            // Every arrival, including retries/invalid returns, remains independently auditable.
            'provider_event_id' => bin2hex(random_bytes(24)), 'event_type' => $type,
            'payload_sha256' => $digest ?: hash('sha256', json_encode($safe)),
            'response_json' => json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'verification_issues' => json_encode(array_values(array_unique($issues)), JSON_THROW_ON_ERROR),
            'status' => $status, 'received_at' => $now, 'processed_at' => $now, 'created_at' => $now,
        ])) {
            throw new \RuntimeException('Unable to persist the bank audit event.');
        }
    }

    public function smartpayPaymentByPublicId(string $publicId): ?object
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $publicId) || !$this->smartpaySchemaReady()) {
            return null;
        }
        return $this->db->table('eservice_payments')->where([
            'public_id' => $publicId, 'provider' => 'bank_muscat', 'deleted' => 0,
        ])->get()->getRow();
    }

    /** Called by a CSRF-protected POST. A unique order can be handed to the bank once. */
    public function prepareSmartpayHandoff(string $publicId): array
    {
        if (!$this->config->isReady() || !$this->smartpaySchemaReady() || !preg_match('/^[a-f0-9]{32}$/', $publicId)) {
            return $this->failure(503, 'Bank Muscat payment is not available.');
        }
        $this->db->transBegin();
        try {
            $table = $this->db->prefixTable('eservice_payments');
            $payment = $this->db->query("SELECT * FROM {$table} WHERE public_id = ? AND provider = 'bank_muscat' AND deleted = 0 FOR UPDATE", [$publicId])->getRow();
            if (!$payment || (string)$payment->status !== 'processing' || !empty($payment->handed_off_at)
                || (strtotime((string)$payment->expires_at . ' UTC') ?: 0) <= time()) {
                $this->db->transRollback();
                return $this->failure(409, 'This checkout was already sent to the bank or has expired. Check its payment status before trying again.');
            }
            // An older attempt may have been confirmed after this retry was created.
            // Recheck at the final handoff boundary, including the immutable vendor billing cycle.
            $priorPayments = $this->db->query("SELECT metadata FROM {$table}
                WHERE subject_type = ? AND subject_id = ? AND vendor_id <=> ?
                  AND deleted = 0 AND status = 'paid' FOR UPDATE",
                [(string)$payment->subject_type, (int)$payment->subject_id, $payment->vendor_id])->getResult();
            $metadata = json_decode((string)$payment->metadata, true) ?: [];
            foreach ($priorPayments as $priorPayment) {
                $priorMetadata = json_decode((string)$priorPayment->metadata, true) ?: [];
                if (!in_array((string)$payment->subject_type, ['vendor_registration', 'vendor_renewal'], true)
                    || (int)($priorMetadata['vendor_fee_request_id'] ?? 0) === (int)($metadata['vendor_fee_request_id'] ?? 0)) {
                    $this->db->table('eservice_payments')->where('id', (int)$payment->id)->update([
                        'status' => 'expired', 'failure_code' => 'already_paid_before_handoff', 'checkout_url' => null,
                        'updated_at' => get_current_utc_time(),
                    ]);
                    $this->insertSmartpayEvent((int)$payment->id, 'checkout.superseded', 'recorded');
                    if (!$this->db->transStatus()) {
                        throw new \RuntimeException('Unable to close a duplicate checkout.');
                    }
                    $this->db->transCommit();
                    return $this->failure(409, 'Payment was already received for this fee. This checkout was closed without sending another transaction to the bank.');
                }
            }
            $gateway = new Bank_muscat_gateway($this->config);
            $form = $gateway->hostedForm($payment);
            $this->db->table('eservice_payments')->where('id', (int)$payment->id)->update([
                'handed_off_at' => get_current_utc_time(), 'updated_at' => get_current_utc_time(),
            ]);
            $this->insertSmartpayEvent((int)$payment->id, 'checkout.handed_off', 'recorded', [], [], $form['request_sha256']);
            if (!$this->db->transStatus()) {
                throw new \RuntimeException('Unable to save the bank handoff.');
            }
            $this->db->transCommit();
            return ['success' => true, 'status_code' => 200, 'payment' => $payment, 'form' => $form];
        } catch (Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'SMARTPAY HANDOFF FAILED: {class}', ['class' => get_class($e)]);
            return $this->failure(503, 'The payment could not be sent to the bank. No payment was marked as paid.');
        }
    }

    /** The outer order id is an audit hint only; lookup and authority come from GCM-authenticated data. */
    public function processSmartpayReturn(string $encryptedResponse, string $outerOrderId = ''): array
    {
        if ($this->config->provider !== 'bank_muscat' || !$this->config->isReady() || !$this->smartpaySchemaReady()) {
            return $this->failure(503, 'Bank payment processing is not configured.');
        }
        $digest = hash('sha256', $encryptedResponse);
        try {
            $gateway = new Bank_muscat_gateway($this->config);
            $response = $gateway->decryptResponse($encryptedResponse);
        } catch (Throwable $e) {
            $reason = [
                'Invalid encrypted response.' => 'encrypted_response_invalid_format',
                'The encrypted response could not be authenticated.' => 'encrypted_response_tag_invalid',
                'Invalid or repeated response field.' => 'encrypted_response_fields_invalid',
            ][$e->getMessage()] ?? 'encrypted_response_authentication_failed';
            $this->insertSmartpayEvent(null, 'return.rejected', 'rejected', [], [$reason], $digest);
            return $this->failure(400, 'The bank response could not be authenticated. Accounting can recheck the transaction using its order number.');
        }
        $orderId = trim((string)($response['order_id'] ?? ''));
        $payments = $this->db->prefixTable('eservice_payments');
        $this->db->transBegin();
        try {
            $payment = $this->db->query("SELECT * FROM {$payments} WHERE provider = 'bank_muscat' AND provider_checkout_id = ? AND deleted = 0 FOR UPDATE", [$orderId])->getRow();
            if (!$payment) {
                $this->insertSmartpayEvent(null, 'return.unknown_order', 'rejected', $response, ['unknown_order_id'], $digest);
                $this->db->transCommit();
                return $this->failure(400, 'The bank response does not match a payment in this application.');
            }
            $issues = $gateway->bindingIssues($payment, $response);
            if ($outerOrderId !== '' && !hash_equals($orderId, $outerOrderId)) {
                $issues[] = 'outer_order_id_mismatch';
            }
            if (empty($payment->handed_off_at)) {
                $issues[] = 'checkout_not_handed_to_bank';
            }
            if ((string)$payment->status === 'paid' && !empty($response['tracking_id'])
                && !hash_equals((string)$payment->provider_payment_id, (string)$response['tracking_id'])) {
                $issues[] = 'bank_tracking_reference_conflict';
            }
            $safe = Bank_muscat_gateway::safeResponse($response);
            $this->insertSmartpayEvent((int)$payment->id, 'return.received', $issues ? 'rejected' : 'received', $response, $issues, $digest);
            // A duplicate/late browser response never replaces a previously verified payment.
            if ((string)$payment->status !== 'paid') {
                $capture = [
                    'response_json' => json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'verification_issues' => json_encode($issues),
                    'returned_at' => get_current_utc_time(), 'updated_at' => get_current_utc_time(),
                ];
                // A retry of an already final failure cannot seize a newer attempt's unique active slot.
                if (in_array((string)$payment->status, ['pending', 'processing', 'verification_required'], true)) {
                    $capture['status'] = 'verification_required';
                }
                $this->db->table('eservice_payments')->where('id', (int)$payment->id)->update($capture);
            } elseif (in_array('bank_tracking_reference_conflict', $issues, true)) {
                $this->db->table('eservice_payments')->where('id', (int)$payment->id)->update([
                    // Attention is separate from a fee already applied: do not revoke fulfilled service.
                    'verification_issues' => json_encode($issues),
                    'updated_at' => get_current_utc_time(),
                ]);
            }
            if (!$this->db->transStatus()) {
                throw new \RuntimeException('The returned payment response could not be saved.');
            }
            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'SMARTPAY RETURN CAPTURE FAILED: {class}', ['class' => get_class($e)]);
            return $this->failure(503, 'The payment response could not be saved. Please contact accounting with the bank transaction reference.');
        }
        if ($issues) {
            return ['success' => false, 'status_code' => 202, 'payment_id' => $payment->public_id,
                'message' => 'The returned payment requires verification. Accounting can recheck its bank status.'];
        }
        $result = $this->recheckSmartpayPayment((int)$payment->id, $response);
        $result['payment_id'] = (string)$payment->public_id;
        return $result;
    }

    /** Callable by scoped accounting POST actions or CLI reconciliation. Never accepts a desired status. */
    public function recheckSmartpayPayment(int $paymentId, ?array $callbackResponse = null): array
    {
        if ($paymentId < 1 || $this->config->provider !== 'bank_muscat' || !$this->config->isReady() || !$this->smartpaySchemaReady()) {
            return $this->failure(503, 'Bank payment verification is not available.');
        }
        $payments = $this->db->prefixTable('eservice_payments');
        $this->db->transBegin();
        try {
            $payment = $this->db->query("SELECT * FROM {$payments} WHERE id = ? AND provider = 'bank_muscat' AND deleted = 0 FOR UPDATE", [$paymentId])->getRow();
            if (!$payment || empty($payment->handed_off_at)) {
                $this->db->transRollback();
                return $this->failure(404, 'No bank transaction was initiated for this payment.');
            }
            if ((string)$payment->status === 'paid' && !empty($payment->verified_at)) {
                $this->db->transCommit();
                return $this->settleVerifiedSmartpayPayment($paymentId);
            }
            if (!empty($payment->last_status_check_at) && (strtotime($payment->last_status_check_at . ' UTC') ?: 0) > time() - 30) {
                $this->db->transRollback();
                return $this->failure(429, 'The bank status was checked recently. Please wait 30 seconds before rechecking.');
            }
            $this->db->table('eservice_payments')->where('id', $paymentId)->update(['last_status_check_at' => get_current_utc_time()]);
            $this->insertSmartpayEvent($paymentId, 'status.requested', 'recorded');
            if (!$this->db->transStatus()) {
                throw new \RuntimeException('Unable to record the bank status request.');
            }
            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            return $this->failure(503, 'The bank status request could not be saved.');
        }

        $response = [];
        $issues = [];
        $responseDigest = '';
        try {
            $gateway = new Bank_muscat_gateway($this->config);
            $lookupPayment = clone $payment;
            if ($callbackResponse !== null && preg_match('/^[0-9]{1,25}$/', (string)($callbackResponse['tracking_id'] ?? ''))) {
                $lookupPayment->provider_payment_id = (string)$callbackResponse['tracking_id'];
            }
            $response = $gateway->queryOrderStatus($lookupPayment);
            $responseDigest = $gateway->statusResponseDigest();
            $issues = $gateway->bindingIssues($payment, $response, true);
            if ($callbackResponse !== null) {
                $callback = Bank_muscat_gateway::normalizedResponse($callbackResponse);
                $api = Bank_muscat_gateway::normalizedResponse($response, true);
                foreach (['tracking_id', 'currency', 'bank_ref_no'] as $field) {
                    if ($callback[$field] !== '' && !hash_equals($callback[$field], $api[$field])) {
                        $issues[] = 'callback_api_' . $field . '_mismatch';
                    }
                }
                if (Bank_muscat_gateway::statusCategory($callback['order_status']) !== Bank_muscat_gateway::statusCategory($api['order_status'])) {
                    $issues[] = 'callback_api_status_mismatch';
                }
                if ($callback['order_date_time'] !== '' && !Bank_muscat_gateway::timestampsMatch($callback['order_date_time'], $api['order_date_time'])) {
                    $issues[] = 'callback_api_timestamp_mismatch';
                }
            }
        } catch (Throwable $e) {
            // Never log exception bodies: gateways can include credentials or customer data.
            $issues[] = 'bank_status_api_unavailable_or_unverified';
            if (isset($gateway)) {
                $responseDigest = $gateway->statusResponseDigest();
            }
            log_message('warning', 'SMARTPAY STATUS VERIFICATION UNAVAILABLE: {class}', ['class' => get_class($e)]);
        }

        $this->db->transBegin();
        try {
            $current = $this->db->query("SELECT * FROM {$payments} WHERE id = ? FOR UPDATE", [$paymentId])->getRow();
            $category = $issues ? 'verification_required' : Bank_muscat_gateway::statusCategory((string)($response['order_status'] ?? ''));
            $safe = Bank_muscat_gateway::safeResponse($response);
            $this->insertSmartpayEvent($paymentId, 'status.received', $issues ? 'rejected' : 'processed', $response, $issues, $responseDigest);
            if ((string)$current->status !== 'paid') {
                $update = [
                    'status_response_json' => json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'verification_issues' => json_encode($issues), 'updated_at' => get_current_utc_time(),
                ];
                if ($category !== 'verification_required' || in_array((string)$current->status, ['pending', 'processing', 'verification_required'], true)) {
                    $update['status'] = $category;
                }
                if (!$issues && $category !== 'verification_required') {
                    $normal = Bank_muscat_gateway::normalizedResponse($response, true);
                    $update['verified_at'] = get_current_utc_time();
                    $update['provider_payment_id'] = $normal['tracking_id'];
                    $update['bank_reference'] = $normal['bank_ref_no'];
                    $update['checkout_url'] = null;
                    $update['failure_code'] = $category === 'paid' ? null : 'bank_' . $category;
                    $update[$category === 'paid' ? 'paid_at' : 'failed_at'] = get_current_utc_time();
                }
                $this->db->table('eservice_payments')->where('id', $paymentId)->update($update);
            }
            if (!$this->db->transStatus()) {
                throw new \RuntimeException('Unable to save verified payment details.');
            }
            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            return $this->failure(503, 'Bank verification could not be saved. Accounting can safely recheck this payment.');
        }
        if ($category === 'paid' || (string)$current->status === 'paid') {
            return $this->settleVerifiedSmartpayPayment($paymentId);
        }
        return ['success' => !$issues, 'status_code' => $category === 'verification_required' ? 202 : 200,
            'message' => $category === 'verification_required'
                ? 'The bank transaction is saved and awaiting verification. Do not pay again until accounting has rechecked it.'
                : ($category === 'cancelled' ? 'The bank confirmed that the payment was cancelled.' : 'The bank confirmed that the payment failed.')];
    }

    /** Financial truth commits first. Workflow failure cannot erase received funds. */
    private function settleVerifiedSmartpayPayment(int $paymentId): array
    {
        $payments = $this->db->prefixTable('eservice_payments');
        $this->db->transBegin();
        try {
            $payment = $this->db->query("SELECT * FROM {$payments} WHERE id = ? FOR UPDATE", [$paymentId])->getRow();
            if (!$payment || (string)$payment->status !== 'paid' || empty($payment->verified_at)) {
                throw new \DomainException('No independently verified payment is available.');
            }
            if (in_array('bank_tracking_reference_conflict', json_decode((string)($payment->verification_issues ?? ''), true) ?: [], true)) {
                $this->db->transCommit();
                return ['success' => false, 'status_code' => 202,
                    'message' => 'Multiple bank transaction references were returned for this order. Accounting must investigate both references with the bank. The original payment and its application remain saved.'];
            }
            if ((string)$payment->settlement_status !== 'applied') {
                $this->applyPaidSubject($payment, (string)$payment->provider_payment_id);
                $this->db->table('eservice_payments')->where('id', $paymentId)->update([
                    'settlement_status' => 'applied', 'verification_issues' => '[]', 'updated_at' => get_current_utc_time(),
                ]);
                $this->insertSmartpayEvent($paymentId, 'subject.settled', 'processed');
            }
            if (!$this->db->transStatus()) {
                throw new \RuntimeException('Unable to apply the paid fee to the business workflow.');
            }
            $this->db->transCommit();
            return ['success' => true, 'status_code' => 200, 'message' => 'Bank Muscat confirmed the payment. The fee has been applied.'];
        } catch (Throwable $e) {
            $this->db->transRollback();
            if (method_exists($this->db, 'resetTransStatus')) {
                $this->db->resetTransStatus();
            }
            $this->db->table('eservice_payments')->where('id', $paymentId)->where('status', 'paid')->update([
                'settlement_status' => 'review_required', 'verification_issues' => '["subject_settlement_failed"]', 'updated_at' => get_current_utc_time(),
            ]);
            $this->insertSmartpayEvent($paymentId, 'subject.settlement_failed', 'rejected', [], ['subject_settlement_failed']);
            log_message('error', 'SMARTPAY SUBJECT SETTLEMENT FAILED: {class}', ['class' => get_class($e)]);
            return ['success' => false, 'status_code' => 202,
                'message' => 'Your payment was received and saved. Accounting must reconcile its application to this fee. Do not pay again.'];
        }
    }
}
