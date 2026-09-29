<?php

namespace App\Libraries\Payments;

use CodeIgniter\Database\BaseConnection;
use DomainException;
use RuntimeException;

/** Registration and renewal fee snapshots, independent of gateway transport. */
final class Vendor_billing_service
{
    public function __construct(private BaseConnection $db)
    {
    }

    public function isReady(): bool
    {
        return $this->db->tableExists('vendor_fee_requests');
    }

    public static function periodKey(int $vendorId, string $type, ?string $validUntil): string
    {
        if ($vendorId < 1 || !in_array($type, ['registration', 'renewal'], true)) {
            throw new DomainException('Invalid vendor billing period.');
        }
        return hash('sha256', $vendorId . ':' . $type . ':' . ($type === 'renewal' ? ($validUntil ?: 'unregistered') : 'initial'));
    }

    public static function normalizedFee(string $amount): string
    {
        if (preg_match('/^0(?:\.0{1,3})?$/', trim($amount))) {
            return '0.000';
        }
        return Payment_amount::fromMinor(Payment_amount::toMinor($amount, 3), 3);
    }

    public function latestOpen(int $vendorId): ?object
    {
        if (!$this->isReady()) {
            return null;
        }
        $table = $this->db->prefixTable('vendor_fee_requests');
        return $this->db->query("SELECT * FROM {$table} WHERE vendor_id=? AND review_status IN ('pending','submitted','revise') ORDER BY id DESC LIMIT 1", [$vendorId])->getRow() ?: null;
    }

    public function quote(object $vendor, string $type): array
    {
        if (!in_array($type, ['registration', 'renewal'], true)) {
            throw new DomainException('Invalid vendor fee type.');
        }
        $fees = $this->db->prefixTable('vendor_group_fees');
        $groups = $this->db->prefixTable('vendor_groups');
        $date = gmdate('Y-m-d');
        $fee = $this->db->query(
            "SELECT f.*, g.default_validity_days FROM {$fees} f
             INNER JOIN {$groups} g ON g.id=f.vendor_group_id AND g.deleted=0 AND g.is_active=1
             WHERE f.vendor_group_id=? AND f.fee_type=? AND f.deleted=0 AND f.is_active=1
               AND (f.active_from IS NULL OR f.active_from<=?) AND (f.active_to IS NULL OR f.active_to>=?)
             ORDER BY f.active_from DESC, f.id DESC LIMIT 1",
            [(int) ($vendor->vendor_group_id ?? 0), $type, $date, $date]
        )->getRow();
        if (!$fee) {
            throw new DomainException('No active ' . $type . ' fee is configured for this vendor group. Please contact Procurement.');
        }
        $currency = strtoupper(trim((string) $fee->currency));
        if ($currency !== strtoupper(config('EservicesPayments')->currency)) {
            throw new DomainException('The vendor fee currency does not match the configured payment currency. Please contact Procurement.');
        }
        $days = (int) ($fee->default_validity_days ?? 0);
        if ($days < 1) {
            throw new DomainException('Registration validity is not configured for this vendor group. Please contact Procurement.');
        }
        return [
            'fee_id' => (int) $fee->id,
            'vendor_group_id' => (int) $fee->vendor_group_id,
            'fee_type' => $type,
            'amount' => self::normalizedFee((string) $fee->amount),
            'currency' => $currency,
            'validity_days' => $days,
        ];
    }

    public function summary(object $vendor): array
    {
        if (!$this->isReady()) {
            return ['error' => 'Vendor payment setup is not available. Please contact the administrator.'];
        }
        $open = $this->latestOpen((int) $vendor->id);
        $type = $open->fee_type ?? (in_array((string) $vendor->status, ['approved', 'expired'], true)
            || !empty($vendor->registration_valid_to) ? 'renewal' : 'registration');
        try {
            $quote = $open ? (array) $open : $this->quote($vendor, $type);
            return ['type' => $type, 'quote' => $quote, 'request' => $open,
                'settled' => $open && $this->isSettled($open),
                'can_start' => $open ? in_array($open->review_status, ['pending', 'revise'], true)
                    : in_array((string) $vendor->status, ['new', 'pending_payment', 'submitted', 'revise', 'approved', 'expired'], true)];
        } catch (DomainException $e) {
            return ['type' => $type, 'error' => $e->getMessage()];
        }
    }

    public function prepare(int $vendorId, int $userId, string $type): object
    {
        if (!$this->isReady()) {
            throw new DomainException('Vendor payment setup is not available. Please contact the administrator.');
        }
        $vendors = $this->db->prefixTable('vendors');
        $table = $this->db->prefixTable('vendor_fee_requests');
        $this->db->transBegin();
        try {
            $vendor = $this->db->query("SELECT * FROM {$vendors} WHERE id=? AND deleted=0 FOR UPDATE", [$vendorId])->getRow();
            if (!$vendor || in_array((string) $vendor->status, ['suspended', 'rejected'], true)) {
                throw new DomainException('This vendor is not eligible for payment.');
            }
            $open = $this->latestOpen($vendorId);
            if ($open) {
                if ($open->fee_type !== $type) {
                    throw new DomainException('Complete the existing vendor fee request first.');
                }
                $this->assertSnapshot($vendor, $open);
                $this->db->transCommit();
                return $open;
            }
            if ($type === 'renewal' && !in_array((string) $vendor->status, ['approved', 'expired'], true)) {
                throw new DomainException('Only an approved or expired registration can be renewed.');
            }
            if ($type === 'registration' && (!empty($vendor->registration_valid_to) || !in_array((string) $vendor->status, ['new','pending_payment','submitted','revise'], true))) {
                throw new DomainException('Use renewal for an existing registration.');
            }
            $quote = $this->quote($vendor, $type);
            $key = self::periodKey($vendorId, $type, $vendor->registration_valid_to ?? null);
            $existing = $this->db->query("SELECT * FROM {$table} WHERE vendor_id=? AND period_key=? LIMIT 1", [$vendorId, $key])->getRow();
            if ($existing) {
                throw new DomainException('This billing period has already been reviewed. Please contact Procurement.');
            }
            $now = get_current_utc_time();
            $data = $quote + ['vendor_id' => $vendorId, 'period_key' => $key,
                'prior_valid_until' => $vendor->registration_valid_to ?: null, 'status' => 'pending',
                'review_status' => 'pending', 'requested_by' => $userId, 'created_at' => $now, 'updated_at' => $now];
            if (!$this->db->table($table)->insert($data)) {
                throw new RuntimeException('Could not create vendor fee request.');
            }
            $requestId = (int) $this->db->insertID();
            // An active renewal remains valid while checkout is in progress.
            if ($type === 'registration') {
                $this->setVendorStatus($vendor, 'pending_payment', $userId, 'Registration fee requested');
            }
            if ($this->db->transStatus() === false) {
                throw new RuntimeException('Could not save vendor fee request.');
            }
            $this->db->transCommit();
            return $this->db->query("SELECT * FROM {$table} WHERE id=?", [$requestId])->getRow();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    public function metadata(object $request): array
    {
        return ['vendor_fee_request_id' => (int) $request->id, 'fee_id' => (int) $request->fee_id,
            'fee_type' => $request->fee_type, 'vendor_group_id' => (int) $request->vendor_group_id,
            'period_key' => $request->period_key, 'prior_valid_until' => $request->prior_valid_until,
            'validity_days' => (int) $request->validity_days, 'currency' => $request->currency];
    }

    public function isSettled(object $request): bool
    {
        if ($request->status === 'not_required' && self::normalizedFee((string) $request->amount) === '0.000') {
            return true;
        }
        if ($request->status !== 'paid' || empty($request->payment_id) || !$this->db->tableExists('eservice_payments')) {
            return false;
        }
        $payments = $this->db->prefixTable('eservice_payments');
        $payment = $this->db->query("SELECT * FROM {$payments} WHERE id=? AND status='paid' AND provider='bank_muscat'
            AND verified_at IS NOT NULL AND settlement_status='applied' AND deleted=0", [(int) $request->payment_id])->getRow();
        if (!$payment || empty($payment->verified_at) || ($payment->settlement_status ?? '') !== 'applied'
            || (int) $payment->subject_id !== (int) $request->vendor_id
            || (int) $payment->vendor_id !== (int) $request->vendor_id
            || (string) $payment->subject_type !== 'vendor_' . $request->fee_type
            || self::normalizedFee((string) $payment->amount) !== self::normalizedFee((string) $request->amount)
            || strtoupper((string) $payment->currency) !== strtoupper((string) $request->currency)) {
            return false;
        }
        $meta = json_decode((string) $payment->metadata, true) ?: [];
        return (int) ($meta['vendor_fee_request_id'] ?? 0) === (int) $request->id
            && (string) ($meta['period_key'] ?? '') === (string) $request->period_key;
    }

    public function submitSettled(int $requestId, int $userId): void
    {
        $table = $this->db->prefixTable('vendor_fee_requests');
        $vendors = $this->db->prefixTable('vendors');
        $this->db->transBegin();
        try {
            $request = $this->db->query("SELECT * FROM {$table} WHERE id=? FOR UPDATE", [$requestId])->getRow();
            if (!$request || !in_array($request->review_status, ['pending','submitted','revise'], true)) {
                throw new DomainException('This fee request cannot be submitted.');
            }
            if (self::normalizedFee((string) $request->amount) === '0.000') {
                $this->db->table($table)->where('id', $requestId)->update(['status' => 'not_required']);
            } elseif (!$this->isSettled($request)) {
                throw new DomainException('Verified Bank Muscat payment is required before Procurement review.');
            }
            $vendor = $this->db->query("SELECT * FROM {$vendors} WHERE id=? AND deleted=0 FOR UPDATE", [(int) $request->vendor_id])->getRow();
            if (!$vendor || in_array((string) $vendor->status, ['suspended','rejected'], true)) {
                throw new DomainException('This vendor cannot be submitted for review.');
            }
            $this->assertSnapshot($vendor, $request);
            $this->setVendorStatus($vendor, 'submitted', $userId, ucfirst($request->fee_type) . ' submitted; fee verified or explicitly zero');
            $this->db->table($table)->where('id', $requestId)->update(['review_status' => 'submitted', 'updated_at' => get_current_utc_time()]);
            if ($this->db->transStatus() === false) {
                throw new RuntimeException('Could not submit the fee request.');
            }
            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /** Called in the staff status-change transaction, never accepts a posted payment flag. */
    public function review(object $vendor, string $decision, int $reviewerId): array
    {
        $request = $this->latestOpen((int) $vendor->id);
        if (!$request) {
            if (in_array($decision, ['approved','submitted'], true) && (string) $vendor->status !== 'approved') {
                throw new DomainException('The vendor must submit its configured registration or renewal fee before approval.');
            }
            return [];
        }
        $this->assertSnapshot($vendor, $request);
        if (in_array($decision, ['approved','submitted'], true) && !$this->isSettled($request)) {
            throw new DomainException('Verified payment (or an explicitly configured zero fee) is required before approval.');
        }
        $dates = [];
        if ($decision === 'approved') {
            $start = gmdate('Y-m-d');
            if ($request->fee_type === 'renewal' && (string) $request->prior_valid_until > $start) {
                $start = (string) $request->prior_valid_until;
            }
            $dates = ['registration_valid_from' => $request->fee_type === 'renewal' && !empty($vendor->registration_valid_from)
                    ? $vendor->registration_valid_from : gmdate('Y-m-d'),
                'registration_valid_to' => (new \DateTimeImmutable($start))->modify('+' . (int) $request->validity_days . ' days')->format('Y-m-d')];
        }
        if (in_array($decision, ['approved','rejected','revise','submitted'], true)) {
            $this->db->table($this->db->prefixTable('vendor_fee_requests'))->where('id', (int) $request->id)->update([
                'review_status' => $decision, 'reviewed_by' => $reviewerId,
                'reviewed_at' => get_current_utc_time(), 'updated_at' => get_current_utc_time()]);
        }
        return $dates;
    }

    private function assertSnapshot(object $vendor, object $request): void
    {
        if ((int) $vendor->vendor_group_id !== (int) $request->vendor_group_id
            || (string) ($vendor->registration_valid_to ?? '') !== (string) ($request->prior_valid_until ?? '')) {
            throw new DomainException('The vendor group or registration period changed. Accounting must reconcile the existing fee request.');
        }
    }

    public function setVendorStatus(object $vendor, string $status, int $userId, string $reason): void
    {
        $now = get_current_utc_time();
        $this->db->table($this->db->prefixTable('vendors'))->where('id', (int) $vendor->id)->update([
            'status' => $status, 'updated_by' => $userId, 'updated_at' => $now]);
        if ((string) $vendor->status !== $status && $this->db->tableExists('vendor_status_histories')) {
            $history = ['vendor_id' => (int) $vendor->id, 'from_status' => $vendor->status, 'to_status' => $status,
                'action_by' => $userId, 'action_at' => $now, 'created_at' => $now, 'updated_at' => $now,
                'action' => 'submit', 'reason' => $reason];
            $fields = array_flip($this->db->getFieldNames('vendor_status_histories'));
            $this->db->table($this->db->prefixTable('vendor_status_histories'))->insert(array_intersect_key($history, $fields));
        }
    }
}
