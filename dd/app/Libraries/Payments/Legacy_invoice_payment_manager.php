<?php

namespace App\Libraries\Payments;

use CodeIgniter\Database\BaseConnection;
use DomainException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Server-owned invoice checkout state and atomic provider settlement.
 *
 * Browser fields are treated only as a request to pay an invoice. The invoice,
 * client, contact, payment method, outstanding balance and currency are rebuilt
 * from the database before an immutable attempt is created.
 */
final class Legacy_invoice_payment_manager
{
    private const TABLE = 'legacy_invoice_payment_attempts';
    private const PROVIDER_METHOD_TYPES = [
        'stripe' => 'stripe',
        'paypal' => 'paypal_payments_standard',
        'paytm' => 'paytm',
    ];

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?: db_connect();
    }

    /**
     * @return object Immutable canonical attempt, including its opaque public_id.
     */
    public function start(string $provider, array $requestData, int $loggedInUserId = 0): object
    {
        $provider = strtolower(trim($provider));
        $this->assertProvider($provider);
        $this->assertSchema();

        $invoiceId = filter_var($requestData['invoice_id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if (!$invoiceId) {
            throw new InvalidArgumentException('The invoice payment request is invalid.');
        }
        if ($loggedInUserId < 1 && !(bool)get_setting('client_can_pay_invoice_without_login')) {
            throw new DomainException('Public invoice payment is disabled.');
        }

        $invoices = $this->db->prefixTable('invoices');
        $clients = $this->db->prefixTable('clients');
        $payments = $this->db->prefixTable('invoice_payments');
        $methods = $this->db->prefixTable('payment_methods');
        $users = $this->db->prefixTable('users');
        $attempts = $this->db->prefixTable(self::TABLE);
        $now = get_current_utc_time();

        $this->db->transBegin();
        try {
            $invoice = $this->db->query(
                "SELECT i.id, i.client_id, i.display_id, i.invoice_total, i.status, i.type,
                        c.currency, c.disable_online_payment
                 FROM {$invoices} i
                 INNER JOIN {$clients} c ON c.id = i.client_id AND c.deleted = 0
                 WHERE i.id = ? AND i.deleted = 0
                   AND i.type = 'invoice'
                   AND i.status NOT IN ('credited', 'cancelled')
                 LIMIT 1 FOR UPDATE",
                [(int)$invoiceId]
            )->getRow();
            if (!$invoice || (int)($invoice->disable_online_payment ?? 0) === 1) {
                throw new DomainException('Online payment is unavailable for this invoice.');
            }

            $methodType = self::PROVIDER_METHOD_TYPES[$provider];
            $method = $this->db->query(
                "SELECT id, type, minimum_payment_amount
                 FROM {$methods}
                 WHERE type = ? AND deleted = 0 AND online_payable = 1 AND available_on_invoice = 1
                 ORDER BY id ASC LIMIT 1 FOR UPDATE",
                [$methodType]
            )->getRow();
            if (!$method) {
                throw new DomainException('The selected online payment method is unavailable.');
            }

            $verificationCode = '';
            if ($loggedInUserId > 0) {
                $contact = $this->db->query(
                    "SELECT id, client_id
                     FROM {$users}
                     WHERE id = ? AND client_id = ? AND user_type = 'client'
                       AND deleted = 0 AND status = 'active' AND disable_login = 0
                     LIMIT 1 FOR UPDATE",
                    [$loggedInUserId, (int)$invoice->client_id]
                )->getRow();
            } else {
                $verificationCode = trim((string)($requestData['verification_code'] ?? ''));
                $contactId = $this->getPublicInvoiceContactId(
                    $verificationCode,
                    (int)$invoice->id,
                    (int)$invoice->client_id
                );
                $contact = $contactId > 0
                    ? $this->db->query(
                        "SELECT id, client_id
                         FROM {$users}
                         WHERE id = ? AND client_id = ? AND user_type = 'client'
                           AND deleted = 0 AND status = 'active' AND disable_login = 0
                         LIMIT 1 FOR UPDATE",
                        [$contactId, (int)$invoice->client_id]
                    )->getRow()
                    : null;
            }
            if (!$contact) {
                throw new DomainException('The invoice contact is not authorized to make this payment.');
            }

            $this->db->table($attempts)
                ->where('invoice_id', (int)$invoice->id)
                ->where('provider', $provider)
                ->whereIn('status', ['pending', 'processing'])
                ->where('expires_at <=', $now)
                ->update([
                    'status' => 'expired',
                    'failure_code' => 'checkout_expired',
                    'updated_at' => $now,
                ]);
            $recentAttempts = $this->db->query(
                "SELECT COUNT(*) AS attempt_count FROM {$attempts}
                 WHERE invoice_id = ? AND contact_user_id = ? AND provider = ?
                   AND created_at >= ? AND deleted = 0",
                [
                    (int)$invoice->id,
                    (int)$contact->id,
                    $provider,
                    gmdate('Y-m-d H:i:s', time() - 900),
                ]
            )->getRow();
            if ((int)($recentAttempts->attempt_count ?? 0) >= 10) {
                throw new DomainException('Too many payment attempts were started. Please try again later.');
            }

            $currency = strtoupper(trim((string)($invoice->currency ?: get_setting('default_currency'))));
            if (preg_match('/^[A-Z]{3}$/D', $currency) !== 1) {
                throw new DomainException('The invoice currency is invalid.');
            }
            if ($provider === 'paytm' && $currency !== 'INR') {
                throw new DomainException('Paytm invoice payments require an INR invoice.');
            }
            $exponent = self::minorUnitExponent($currency);

            $invoiceTotalMinor = Payment_amount::toMinor((string)$invoice->invoice_total, $exponent);
            $paidMinor = 0;
            $paymentRows = $this->db->query(
                "SELECT id, amount FROM {$payments}
                 WHERE invoice_id = ? AND deleted = 0
                 ORDER BY id ASC FOR UPDATE",
                [(int)$invoice->id]
            )->getResult();
            foreach ($paymentRows as $paymentRow) {
                $amount = trim((string)$paymentRow->amount);
                if ($amount !== '' && (float)$amount > 0) {
                    $paidMinor += Payment_amount::toMinor($amount, $exponent);
                }
            }
            $balanceMinor = $invoiceTotalMinor - $paidMinor;
            if ($balanceMinor < 1) {
                throw new DomainException('This invoice has no outstanding balance.');
            }

            $requestedMinor = $balanceMinor;
            if ((bool)get_setting('allow_partial_invoice_payment_from_clients')) {
                $requested = unformat_currency((string)($requestData['payment_amount'] ?? ''));
                $requestedMinor = Payment_amount::toMinor($requested, $exponent);
                if ($requestedMinor > $balanceMinor) {
                    throw new DomainException('The requested payment exceeds the outstanding invoice balance.');
                }
            }

            $minimum = trim((string)($method->minimum_payment_amount ?? ''));
            if ($minimum !== '' && (float)$minimum > 0) {
                $minimumMinor = Payment_amount::toMinor($minimum, $exponent);
                if ($requestedMinor < $minimumMinor) {
                    throw new DomainException('The requested amount is below the payment method minimum.');
                }
            }

            $publicId = bin2hex(random_bytes(16));
            $expectedAmount = Payment_amount::fromMinor($requestedMinor, $exponent);
            $inserted = $this->db->table($attempts)->insert([
                'public_id' => $publicId,
                'provider' => $provider,
                'invoice_id' => (int)$invoice->id,
                'client_id' => (int)$invoice->client_id,
                'contact_user_id' => (int)$contact->id,
                'payment_method_id' => (int)$method->id,
                'invoice_verification_code' => $verificationCode !== '' ? $verificationCode : null,
                'expected_amount' => $expectedAmount,
                'expected_amount_minor' => $requestedMinor,
                'currency' => $currency,
                'minor_unit_exponent' => $exponent,
                'status' => 'pending',
                'expires_at' => gmdate('Y-m-d H:i:s', time() + 3600),
                'created_at' => $now,
                'updated_at' => $now,
                'deleted' => 0,
            ]);
            if (!$inserted || $this->db->transStatus() === false) {
                throw new RuntimeException('Unable to create a secure payment attempt.');
            }
            $attemptId = (int)$this->db->insertID();
            $this->db->transCommit();

            $attempt = $this->getAttempt($publicId, $provider);
            if (!$attempt || (int)$attempt->id !== $attemptId) {
                throw new RuntimeException('Unable to read the secure payment attempt.');
            }
            $attempt->invoice_display_id = (string)$invoice->display_id;
            return $attempt;
        } catch (Throwable $exception) {
            if ($this->db->transStatus() !== null) {
                $this->db->transRollback();
            }
            throw $exception;
        }
    }

    public function getAttempt(string $publicId, string $provider): ?object
    {
        $provider = strtolower(trim($provider));
        $this->assertProvider($provider);
        $this->assertSchema();
        if (preg_match('/^[a-f0-9]{32}$/D', $publicId) !== 1) {
            return null;
        }

        return $this->db->table($this->db->prefixTable(self::TABLE))
            ->getWhere([
                'public_id' => $publicId,
                'provider' => $provider,
                'deleted' => 0,
            ], 1)
            ->getRow();
    }

    public function bindProviderReference(int $attemptId, string $providerReference): void
    {
        $providerReference = $this->cleanProviderReference($providerReference);
        $table = $this->db->prefixTable(self::TABLE);
        $updated = $this->db->table($table)
            ->where('id', $attemptId)
            ->where('status', 'pending')
            ->where('provider_reference', null)
            ->update([
                'provider_reference' => $providerReference,
                'status' => 'processing',
                'updated_at' => get_current_utc_time(),
            ]);
        if (!$updated || $this->db->affectedRows() !== 1) {
            throw new RuntimeException('Unable to bind the provider checkout to the payment attempt.');
        }
    }

    public function markFailed(string $publicId, string $provider, string $failureCode, string $providerReference = ''): void
    {
        $attempt = $this->getAttempt($publicId, $provider);
        if (!$attempt) {
            return;
        }
        if ($providerReference !== ''
            && !hash_equals((string)$attempt->provider_reference, $this->cleanProviderReference($providerReference))) {
            return;
        }
        $this->db->table($this->db->prefixTable(self::TABLE))
            ->where('id', (int)$attempt->id)
            ->whereIn('status', ['pending', 'processing'])
            ->update([
                'status' => 'failed',
                'failure_code' => mb_substr(preg_replace('/[^a-z0-9_\-]/i', '', $failureCode), 0, 64),
                'updated_at' => get_current_utc_time(),
            ]);
    }

    /**
     * Atomically records a provider-verified payment exactly once.
     *
     * @return array{success:bool,idempotent:bool,invoice_id:int,invoice_payment_id:int,contact_user_id:int,verification_code:string,message:string}
     */
    public function settle(
        string $publicId,
        string $provider,
        string $providerReference,
        string $providerTransactionId,
        int $amountMinor,
        string $currency
    ): array {
        $provider = strtolower(trim($provider));
        $this->assertProvider($provider);
        $this->assertSchema();
        if (preg_match('/^[a-f0-9]{32}$/D', $publicId) !== 1 || $amountMinor < 1) {
            throw new DomainException('The provider payment reference is invalid.');
        }
        $providerReference = $this->cleanProviderReference($providerReference);
        $providerTransactionId = $this->cleanProviderReference($providerTransactionId);
        $currency = strtoupper(trim($currency));
        if (preg_match('/^[A-Z]{3}$/D', $currency) !== 1) {
            throw new DomainException('The provider payment currency is invalid.');
        }

        $attempts = $this->db->prefixTable(self::TABLE);
        $invoices = $this->db->prefixTable('invoices');
        $clients = $this->db->prefixTable('clients');
        $payments = $this->db->prefixTable('invoice_payments');
        $this->db->transBegin();
        try {
            $attempt = $this->db->query(
                "SELECT * FROM {$attempts}
                 WHERE public_id = ? AND provider = ? AND deleted = 0
                 LIMIT 1 FOR UPDATE",
                [$publicId, $provider]
            )->getRow();
            if (!$attempt) {
                throw new DomainException('The payment attempt does not exist.');
            }

            if ((string)$attempt->status === 'completed') {
                $matches = hash_equals((string)$attempt->provider_reference, $providerReference)
                    && hash_equals((string)$attempt->provider_transaction_id, $providerTransactionId)
                    && (int)$attempt->expected_amount_minor === $amountMinor
                    && hash_equals((string)$attempt->currency, $currency);
                if (!$matches) {
                    throw new DomainException('The duplicate callback does not match the completed payment.');
                }
                $this->db->transCommit();
                return $this->result($attempt, true, true, 'Payment was already recorded.');
            }

            if ((string)$attempt->status === 'review_required') {
                $matches = hash_equals((string)$attempt->provider_reference, $providerReference)
                    && hash_equals((string)$attempt->provider_transaction_id, $providerTransactionId)
                    && (int)$attempt->expected_amount_minor === $amountMinor
                    && hash_equals((string)$attempt->currency, $currency);
                if (!$matches) {
                    throw new DomainException('The duplicate callback does not match the reconciliation record.');
                }
                $this->db->transCommit();
                return $this->result(
                    $attempt,
                    false,
                    true,
                    'The paid transaction is already queued for reconciliation.'
                );
            }

            if (!in_array((string)$attempt->status, ['pending', 'processing'], true)
                || !hash_equals((string)$attempt->provider_reference, $providerReference)
                || (int)$attempt->expected_amount_minor !== $amountMinor
                || !hash_equals((string)$attempt->currency, $currency)) {
                throw new DomainException('The provider payment does not match the pending payment attempt.');
            }

            $duplicateAttempt = $this->db->query(
                "SELECT id FROM {$attempts}
                 WHERE provider = ? AND provider_transaction_id = ? AND id <> ? AND deleted = 0
                 LIMIT 1 FOR UPDATE",
                [$provider, $providerTransactionId, (int)$attempt->id]
            )->getRow();
            if ($duplicateAttempt) {
                throw new DomainException('The provider transaction was already used by another payment attempt.');
            }

            $this->db->table($attempts)->where('id', (int)$attempt->id)->update([
                'provider_transaction_id' => $providerTransactionId,
                'status' => 'settling',
                'updated_at' => get_current_utc_time(),
            ]);

            $invoice = $this->db->query(
                "SELECT i.id, i.client_id, i.invoice_total, i.status, i.type, c.currency
                 FROM {$invoices} i
                 INNER JOIN {$clients} c ON c.id = i.client_id AND c.deleted = 0
                 WHERE i.id = ? AND i.deleted = 0
                 LIMIT 1 FOR UPDATE",
                [(int)$attempt->invoice_id]
            )->getRow();
            if (!$invoice
                || (int)$invoice->client_id !== (int)$attempt->client_id
                || (string)$invoice->type !== 'invoice'
                || in_array((string)$invoice->status, ['credited', 'cancelled'], true)) {
                throw new DomainException('The bound invoice is no longer payable.');
            }

            $invoiceCurrency = strtoupper(trim((string)($invoice->currency ?: get_setting('default_currency'))));
            if (!hash_equals((string)$attempt->currency, $invoiceCurrency)) {
                throw new DomainException('The invoice currency changed after checkout creation.');
            }

            $exponent = (int)$attempt->minor_unit_exponent;
            $invoiceTotalMinor = Payment_amount::toMinor((string)$invoice->invoice_total, $exponent);
            $paidMinor = 0;
            $paymentRows = $this->db->query(
                "SELECT id, amount FROM {$payments}
                 WHERE invoice_id = ? AND deleted = 0
                 ORDER BY id ASC FOR UPDATE",
                [(int)$attempt->invoice_id]
            )->getResult();
            foreach ($paymentRows as $paymentRow) {
                $rowAmount = trim((string)$paymentRow->amount);
                if ($rowAmount !== '' && (float)$rowAmount > 0) {
                    $paidMinor += Payment_amount::toMinor($rowAmount, $exponent);
                }
            }
            if (($invoiceTotalMinor - $paidMinor) < (int)$attempt->expected_amount_minor) {
                $this->db->table($attempts)->where('id', (int)$attempt->id)->update([
                    'status' => 'review_required',
                    'failure_code' => 'invoice_balance_changed',
                    'updated_at' => get_current_utc_time(),
                ]);
                $this->db->transCommit();
                log_message(
                    'critical',
                    'VERIFIED INVOICE PAYMENT REQUIRES RECONCILIATION: provider={provider} invoice={invoice}',
                    ['provider' => $provider, 'invoice' => (int)$attempt->invoice_id]
                );
                return $this->result($attempt, false, false, 'The paid transaction requires reconciliation because the invoice balance changed.');
            }

            $expectedAmount = Payment_amount::fromMinor((int)$attempt->expected_amount_minor, $exponent);
            $existingPayment = $this->db->query(
                "SELECT id, invoice_id, payment_method_id, amount, created_by
                 FROM {$payments}
                 WHERE transaction_id = ? AND deleted = 0
                 ORDER BY id ASC LIMIT 1 FOR UPDATE",
                [$providerTransactionId]
            )->getRow();
            $newPayment = false;
            if ($existingPayment) {
                $existingMinor = Payment_amount::toMinor((string)$existingPayment->amount, $exponent);
                if ((int)$existingPayment->invoice_id !== (int)$attempt->invoice_id
                    || (int)$existingPayment->payment_method_id !== (int)$attempt->payment_method_id
                    || (int)$existingPayment->created_by !== (int)$attempt->contact_user_id
                    || $existingMinor !== (int)$attempt->expected_amount_minor) {
                    throw new DomainException('The provider transaction conflicts with an existing invoice payment.');
                }
                $invoicePaymentId = (int)$existingPayment->id;
            } else {
                $paymentData = [
                    'invoice_id' => (int)$attempt->invoice_id,
                    'payment_date' => get_current_utc_time(),
                    'payment_method_id' => (int)$attempt->payment_method_id,
                    'note' => '',
                    'amount' => $expectedAmount,
                    'transaction_id' => $providerTransactionId,
                    'created_at' => get_current_utc_time(),
                    'created_by' => (int)$attempt->contact_user_id,
                    'deleted' => 0,
                ];
                if (!$this->db->table($payments)->insert($paymentData)) {
                    throw new RuntimeException('Unable to record the verified invoice payment.');
                }
                $invoicePaymentId = (int)$this->db->insertID();
                $newPayment = true;
            }

            $this->db->table($invoices)->where('id', (int)$attempt->invoice_id)->update([
                'status' => 'not_paid',
            ]);
            $this->db->table($attempts)->where('id', (int)$attempt->id)->where('status', 'settling')->update([
                'status' => 'completed',
                'invoice_payment_id' => $invoicePaymentId,
                'completed_at' => get_current_utc_time(),
                'failure_code' => null,
                'updated_at' => get_current_utc_time(),
            ]);
            if ($this->db->transStatus() === false) {
                throw new RuntimeException('The invoice payment transaction failed.');
            }
            $this->db->transCommit();

            $attempt->invoice_payment_id = $invoicePaymentId;
            $attempt->provider_transaction_id = $providerTransactionId;
            if ($newPayment) {
                $this->afterPaymentCommitted($invoicePaymentId, $attempt, $paymentData);
            }
            return $this->result($attempt, true, !$newPayment, 'Payment recorded successfully.');
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    public static function minorUnitExponent(string $currency): int
    {
        $currency = strtoupper(trim($currency));
        $zeroDecimal = [
            'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG',
            'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
        ];
        $threeDecimal = ['BHD', 'IQD', 'JOD', 'KWD', 'LYD', 'OMR', 'TND'];
        if (in_array($currency, $zeroDecimal, true)) {
            return 0;
        }
        return in_array($currency, $threeDecimal, true) ? 3 : 2;
    }

    private function getPublicInvoiceContactId(string $code, int $invoiceId, int $clientId): int
    {
        if (preg_match('/^(?:[A-Za-z0-9]{10}|[A-Za-z0-9]{32})$/D', $code) !== 1) {
            return 0;
        }
        $verification = $this->db->prefixTable('verification');
        $row = $this->db->query(
            "SELECT params FROM {$verification}
             WHERE code = ? AND type = 'invoice_payment' AND deleted = 0
             ORDER BY id DESC LIMIT 1 FOR UPDATE",
            [$code]
        )->getRow();
        if (!$row) {
            return 0;
        }
        $data = safe_unserialize((string)$row->params);
        if (!is_array($data)
            || (int)($data['invoice_id'] ?? 0) !== $invoiceId
            || (int)($data['client_id'] ?? 0) !== $clientId) {
            return 0;
        }
        return (int)($data['contact_id'] ?? 0);
    }

    private function cleanProviderReference(string $value): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 191 || preg_match('/^[A-Za-z0-9._:\-]+$/D', $value) !== 1) {
            throw new DomainException('The provider reference is invalid.');
        }
        return $value;
    }

    private function assertProvider(string $provider): void
    {
        if (!isset(self::PROVIDER_METHOD_TYPES[$provider])) {
            throw new InvalidArgumentException('Unsupported invoice payment provider.');
        }
    }

    private function assertSchema(): void
    {
        if (!$this->db->tableExists(self::TABLE)) {
            throw new RuntimeException('Secure invoice payment storage is not installed.');
        }
    }

    private function result(object $attempt, bool $success, bool $idempotent, string $message): array
    {
        return [
            'success' => $success,
            'idempotent' => $idempotent,
            'invoice_id' => (int)$attempt->invoice_id,
            'invoice_payment_id' => (int)($attempt->invoice_payment_id ?? 0),
            'contact_user_id' => (int)$attempt->contact_user_id,
            'verification_code' => (string)($attempt->invoice_verification_code ?? ''),
            'message' => $message,
        ];
    }

    private function afterPaymentCommitted(int $paymentId, object $attempt, array $paymentData): void
    {
        try {
            app_hooks()->do_action('app_hook_data_insert', [
                'id' => $paymentId,
                'table' => $this->db->prefixTable('invoice_payments'),
                'table_without_prefix' => 'invoice_payments',
                'data' => $paymentData,
            ]);
            log_notification('invoice_payment_confirmation', [
                'invoice_payment_id' => $paymentId,
                'invoice_id' => (int)$attempt->invoice_id,
            ], '0');
            log_notification('invoice_online_payment_received', [
                'invoice_payment_id' => $paymentId,
                'invoice_id' => (int)$attempt->invoice_id,
            ], (int)$attempt->contact_user_id);
        } catch (Throwable $exception) {
            log_message('error', 'POST-PAYMENT NOTIFICATION FAILED: {class}', [
                'class' => get_class($exception),
            ]);
        }
    }
}
