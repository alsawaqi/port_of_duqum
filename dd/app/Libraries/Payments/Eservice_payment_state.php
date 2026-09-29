<?php

namespace App\Libraries\Payments;

use CodeIgniter\Database\BaseConnection;

/** Legacy paid flags alone cannot unlock a chargeable service. */
final class Eservice_payment_state
{
    public function __construct(private BaseConnection $db) {}

    public function latestPaid(string $type, int $subjectId, ?int $vendorId): ?object
    {
        if (!$this->db->tableExists('eservice_payments')
            || !$this->db->fieldExists('verified_at', 'eservice_payments')
            || !$this->db->fieldExists('settlement_status', 'eservice_payments')) {
            return null;
        }
        $table = $this->db->prefixTable('eservice_payments');
        return $this->db->query("SELECT * FROM {$table} WHERE subject_type=? AND subject_id=? AND vendor_id <=> ?
            AND provider='bank_muscat' AND status='paid' AND verified_at IS NOT NULL AND deleted=0 ORDER BY id DESC LIMIT 1",
            [$type, $subjectId, $vendorId])->getRow() ?: null;
    }

    public function hasVerifiedPayment(string $type, int $subjectId, ?int $vendorId, string $amount, string $currency): bool
    {
        $row = $this->latestPaid($type, $subjectId, $vendorId);
        if (!$row || $row->settlement_status !== 'applied' || strtoupper($row->currency) !== strtoupper($currency)) {
            return false;
        }
        try {
            return Payment_amount::toMinor((string) $row->amount, 3) === Payment_amount::toMinor($amount, 3);
        } catch (\InvalidArgumentException $e) {
            return false;
        }
    }
}
