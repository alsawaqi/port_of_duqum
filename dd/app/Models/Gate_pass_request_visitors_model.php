<?php

namespace App\Models;

class Gate_pass_request_visitors_model extends Crud_model
{
    protected $table = null;

    public ?string $tariff_error = null;

    public function ci_save($data = [], $id = 0)
    {
        $this->tariff_error = null;
        // Security/ROP block flags do not change the billable list of people.
        $blockFields = ['is_blocked', 'block_reason', 'blocked_by', 'blocked_at'];
        if ($id && !array_diff(array_keys($data), $blockFields)) {
            return parent::ci_save($data, $id);
        }
        $this->db->transBegin();
        try {
            $existing = $id ? $this->get_one($id) : null;
            $requestId = (int)($existing->gate_pass_request_id ?? $data['gate_pass_request_id'] ?? 0);
            if ($existing && isset($data['gate_pass_request_id']) && (int)$data['gate_pass_request_id'] !== $requestId) {
                throw new \DomainException('forbidden');
            }
            $tariff = new \App\Libraries\Gate_pass_tariff($this->db);
            $request = $tariff->lock($requestId);
            $countChanges = !$id || (array_key_exists('deleted', $data)
                && (int)$data['deleted'] !== (int)($existing->deleted ?? 0));
            if ($countChanges) { $tariff->assertEditable($request); }
            $merged = (object) array_merge($existing ? (array) $existing : [], $data);
            if (empty($merged->deleted) && $existing && (new \App\Libraries\Gate_pass_eligibility($this->db))->isBlocked($existing)) {
                throw new \DomainException('gate_pass_blocked_cannot_process');
            }
            if (empty($merged->deleted)) { (new \App\Libraries\Gate_pass_eligibility($this->db))->assertVisitor($merged, true); }
            if (!empty($data['is_primary'])) {
                $this->db->table('gate_pass_request_visitors')->where('gate_pass_request_id', $requestId)->update(['is_primary' => 0]);
            }
            $saved = parent::ci_save($data, $id);
            if (!$saved) { throw new \RuntimeException('Visitor save failed.'); }
            if ($countChanges) { $tariff->refresh($requestId); }
            if ($this->db->transStatus() === false) { throw new \RuntimeException('Visitor tariff transaction failed.'); }
            $this->db->transCommit();
            return $saved;
        } catch (\Throwable $e) {
            $this->db->transRollback();
            $this->tariff_error = $e instanceof \DomainException ? $e->getMessage() : 'error_occurred';
            log_message('error', 'Gate pass visitor update refused: ' . $this->tariff_error);
            return false;
        }
    }

    public function delete($id = 0, $undo = false)
    {
        return $this->ci_save(['deleted' => $undo ? 0 : 1], $id);
    }

    function __construct()
    {
        $this->table = "gate_pass_request_visitors";  // => pod_gate_pass_request_visitors
        parent::__construct($this->table);
    }

    function get_details($options = array())
    {
        $visitors = $this->db->prefixTable("gate_pass_request_visitors");

        $where = "WHERE $visitors.deleted=0";

        $id = get_array_value($options, "id");
        if ($id) {
            $where .= " AND $visitors.id=$id";
        }

        $request_id = get_array_value($options, "gate_pass_request_id");
        if ($request_id) {
            $where .= " AND $visitors.gate_pass_request_id=$request_id";
        }

        $sql = "SELECT $visitors.*
                FROM $visitors
                $where
                ORDER BY $visitors.is_primary DESC, $visitors.id DESC";

        return $this->db->query($sql);
    }

    function get_distinct_nationalities()
    {
        $visitors = $this->db->prefixTable("gate_pass_request_visitors");

        return $this->db->query(
            "SELECT DISTINCT TRIM(nationality) AS nationality
             FROM $visitors
             WHERE deleted=0 AND TRIM(COALESCE(nationality, '')) <> ''
             ORDER BY TRIM(nationality) ASC"
        );
    }
}
