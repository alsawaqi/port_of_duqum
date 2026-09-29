<?php

namespace App\Libraries;

/** Authoritative visitor eligibility, shared by writes, issuance and QR access. */
final class Gate_pass_eligibility
{
    public function __construct(private $db = null) { $this->db = $db ?? db_connect(); }

    public function isBlocked(object $visitor, bool $lock = false): bool
    {
        if (!empty($visitor->is_blocked)) { return true; }
        $normalized = preg_replace('/[\s\-]+/', '', strtoupper(trim((string) ($visitor->id_number ?? ''))));
        if ($normalized === '') { return false; }
        $table = $this->db->prefixTable('gate_pass_blocked_visitors');
        return (bool) $this->db->query("SELECT id FROM {$table} WHERE normalized_id_number=? AND deleted=0 AND status='blocked'" . ($lock ? ' FOR UPDATE' : ''), [$normalized])->getRow();
    }

    public function assertVisitor(object $visitor, bool $lock = false): void
    {
        if ($this->isBlocked($visitor, $lock)) { throw new \DomainException('gate_pass_blocked_cannot_process'); }
        if (trim((string) ($visitor->id_type ?? '')) === ''
            || trim((string) ($visitor->id_number ?? '')) === '') {
            throw new \DomainException('gate_pass_identity_required');
        }
    }

    public function assertRequestClear(int $requestId): void
    {
        $table = $this->db->prefixTable('gate_pass_request_visitors');
        $visitors = $this->db->query("SELECT * FROM {$table} WHERE gate_pass_request_id=? AND deleted=0 FOR UPDATE", [$requestId])->getResult();
        foreach ($visitors as $visitor) { $this->assertVisitor($visitor, true); }
    }

    public function canDownload(object $pass): bool
    {
        if (!empty($pass->deleted) || ($pass->status ?? '') !== 'active') { return false; }
        $query = $this->db->table('gate_pass_request_visitors')->where('gate_pass_request_id', (int) $pass->gate_pass_request_id)->where('deleted', 0);
        $assigned = (int) ($pass->gate_pass_request_visitor_id ?? 0);
        if ($assigned) { $query->where('id', $assigned); }
        $visitors = $query->get()->getResult();
        if ($assigned && !$visitors) { return false; }
        foreach ($visitors as $visitor) { if ($this->isBlocked($visitor)) { return false; } }
        return true;
    }

    public static function waiverStatus(object $request): string
    {
        if ((int) ($request->fee_is_waived ?? 0) === 1) { return 'approved'; }
        if (($request->fee_waiver_commercial_status ?? '') === 'rejected') { return 'rejected'; }
        if ((int) ($request->fee_waiver_requested ?? 0) === 1) { return 'requested'; }
        return 'none';
    }
}
