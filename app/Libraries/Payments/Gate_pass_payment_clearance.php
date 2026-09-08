<?php

namespace App\Libraries\Payments;

use CodeIgniter\Database\BaseConnection;

/** Financial prerequisite for new Security approvals and final pass issuance. */
final class Gate_pass_payment_clearance
{
    public function __construct(private BaseConnection $db)
    {
    }

    public function allowsApproval(object $request): bool
    {
        if ((int) ($request->id ?? 0) < 1 || !empty($request->deleted)) {
            return false;
        }
        $waiverStatus = strtolower(trim((string) ($request->fee_waiver_commercial_status ?? '')));
        if ((int) ($request->fee_waiver_requested ?? 0) === 1 && in_array($waiverStatus, ['', 'pending'], true)) {
            return false;
        }

        // A missing/invalid amount is not an explicitly configured zero fee.
        $amount = trim((string) ($request->fee_amount ?? ''));
        if (preg_match('/^0(?:\.0{1,3})?$/', $amount) === 1) {
            return true;
        }
        try {
            Payment_amount::toMinor($amount, 3);
        } catch (\InvalidArgumentException $exception) {
            return false;
        }

        if ($this->hasApprovedWaiver($request)) {
            return true;
        }

        return (new Eservice_payment_state($this->db))->hasVerifiedPayment(
            Eservice_payment_manager::GATE_PASS_FEE,
            (int) $request->id,
            null,
            $amount,
            strtoupper(trim((string) ($request->currency ?? '')))
        );
    }

    private function hasApprovedWaiver(object $request): bool
    {
        if ((int) ($request->fee_is_waived ?? 0) !== 1
            || (int) ($request->fee_waived_by ?? 0) < 1
            || trim((string) ($request->fee_waived_reason ?? '')) === ''
            || empty($request->fee_waived_at)
            || str_starts_with((string) $request->fee_waived_at, '0000-00-00')
            || !$this->db->tableExists('gate_pass_request_approvals')) {
            return false;
        }

        // Direct Commercial waivers leave the request's waiver status NULL;
        // department-requested waivers set it to approved. Both write this
        // Commercial approval entry when advancing to Security. A different
        // authorized Commercial user may approve a waiver saved by a colleague.
        $table = $this->db->prefixTable('gate_pass_request_approvals');
        return (bool) $this->db->query(
            "SELECT id FROM {$table} WHERE gate_pass_request_id=? AND stage='commercial'
                AND decision='approved' AND deleted=0 AND decided_by>0
                AND decided_at>=? ORDER BY id DESC LIMIT 1",
            [(int) $request->id, (string) $request->fee_waived_at]
        )->getRow();
    }
}
