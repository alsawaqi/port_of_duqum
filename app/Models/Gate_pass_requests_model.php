<?php

namespace App\Models;

class Gate_pass_requests_model extends Crud_model
{
    use \App\Libraries\Sms\QueuesWorkflowSms { ci_save as private saveWithWorkflowSms; }

    public ?string $tariff_error = null;

    public function ci_save($data = [], $id = 0)
    {
        $this->tariff_error = null;
        $tariff = new \App\Libraries\Gate_pass_tariff($this->db);
        $this->db->transBegin();
        try {
            $before = $id ? $tariff->lock((int)$id) : null;
            $changedDates = !$before;
            foreach (['visit_from', 'visit_to', 'currency'] as $field) {
                if ($before && array_key_exists($field, $data) && (string)$data[$field] !== (string)$before->{$field}) {
                    $changedDates = true;
                }
            }
            $submitting = $before && in_array($before->status, ['draft', 'returned'], true)
                && isset($data['status']) && in_array($data['status'], ['submitted', 'department_approved', 'commercial_approved', 'security_approved'], true);
            // Resubmission after Security/ROP review retains the already approved fee.
            // Vehicle/document corrections must not trigger a second charge or a reprice.
            $preserveOnResubmit = $submitting && !$changedDates && $before->status === 'returned'
                && (in_array((string)$before->stage, ['security', 'rop'], true) || $tariff->hasPayment($before));
            if ($preserveOnResubmit) { $tariff->assertPayable($before); }
            // Approved/paid amounts cannot be overwritten by the editable commercial fee field.
            if ($before && !$changedDates && (!$submitting || $preserveOnResubmit) && isset($data['fee_amount'])
                && \App\Libraries\Gate_pass_tariff::snapshot($before)
                && (float)$data['fee_amount'] !== (float)$before->fee_amount) {
                throw new \DomainException('gate_pass_tariff_fixed_amount');
            }
            if ($changedDates || ($submitting && !$preserveOnResubmit) || ($before && isset($data['visit_from']) && in_array($before->status, ['draft', 'returned'], true))) {
                if ($before) { $tariff->assertEditable($before); }
                $candidate = (object)array_merge((array)$before, $data, ['id' => (int)$id]);
                $quote = $tariff->quote($candidate);
                if ($submitting && $quote['visitor_count'] < 1) {
                    throw new \DomainException('gate_pass_person_requires_visitor');
                }
                $data = array_merge($data, \App\Libraries\Gate_pass_tariff::fields($quote));
            }
            if ($before && isset($data['status']) && $data['status'] !== $before->status
                && in_array($data['status'], ['submitted','department_approved','commercial_approved','security_approved','rop_approved','issued'], true)) {
                (new \App\Libraries\Gate_pass_eligibility($this->db))->assertRequestClear((int) $id);
            }
            if (($data['status'] ?? '') === 'submitted' && (!$before || $before->status !== 'submitted')) {
                (new \App\Libraries\Gate_pass_notifications($this->db))->assertReady();
            }
            $saved = $this->saveWithWorkflowSms($data, $id);
            if ($saved && ($data['status'] ?? '') === 'submitted' && (!$before || $before->status !== 'submitted')) {
                (new \App\Libraries\Gate_pass_notifications($this->db))->submitted($this->get_one($id ?: $saved));
            }
            if (!$saved || $this->db->transStatus() === false) { throw new \RuntimeException('Gate pass save failed.'); }
            $this->db->transCommit();
            return $saved;
        } catch (\Throwable $e) {
            $this->db->transRollback();
            $this->tariff_error = $e instanceof \DomainException ? $e->getMessage() : 'error_occurred';
            log_message('error', 'Gate pass request update refused: ' . $this->tariff_error);
            return false;
        }
    }

    /** Persist a decision and its status together, including eligibility refusals. */
    public function saveDecision(array $data, array $approval, int $id, string $expectedStatus, string $expectedStage): bool
    {
        $this->db->transBegin();
        try {
            $current = (new \App\Libraries\Gate_pass_tariff($this->db))->lock($id);
            if ($current->status !== $expectedStatus || $current->stage !== $expectedStage) {
                throw new \DomainException('invalid_request');
            }
            if (!$this->ci_save($data, $id)) { throw new \DomainException($this->tariff_error ?: 'error_occurred'); }
            if (!(new Gate_pass_request_approvals_model())->ci_save($approval) || !$this->db->transStatus()) {
                throw new \RuntimeException('Approval could not be saved.');
            }
            $this->db->transCommit();
            return true;
        } catch (\Throwable $e) {
            $this->db->transRollback();
            $this->tariff_error = $e instanceof \DomainException ? $e->getMessage() : 'error_occurred';
            return false;
        }
    }

    protected $table = null;

    public function __construct()
    {
        $this->table = "gate_pass_requests";
        parent::__construct($this->table);
    }

    public function get_details($options = [])
    {
        $requests = $this->db->prefixTable("gate_pass_requests");
        $companies = $this->db->prefixTable("companies");
        $departments = $this->db->prefixTable("departments");
        $purposes = $this->db->prefixTable("gate_pass_purposes");
        $users = $this->db->prefixTable("users");
        $visitors = $this->db->prefixTable("gate_pass_request_visitors");

        $where = "WHERE $requests.deleted=0";
        $bindings = [];

        $id = get_array_value($options, "id");
        if ($id) {
            $where .= " AND $requests.id=" . (int)$id;
        }

        $requester_id = get_array_value($options, "requester_id");
        if ($requester_id) {
            $where .= " AND $requests.requester_id=" . (int)$requester_id;
        }

        // filter by department ids (for dept users)
        $department_ids = get_array_value($options, "department_ids");
        if ($department_ids && is_array($department_ids) && count($department_ids)) {
            $department_ids = array_map("intval", $department_ids);
            $where .= " AND $requests.department_id IN (" . implode(",", $department_ids) . ")";
        }

        // filter by company ids (for commercial users)
        $company_ids = get_array_value($options, "company_ids");
        if ($company_ids && is_array($company_ids) && count($company_ids)) {
            $company_ids = array_map("intval", $company_ids);
            $where .= " AND $requests.company_id IN (" . implode(",", $company_ids) . ")";
        }

        // filter by stage
        $stage = get_array_value($options, "stage");
        if ($stage) {
            $where .= " AND $requests.stage=" . $this->db->escape($stage);
        }

        // filter by statuses (array)
        $statuses = get_array_value($options, "statuses");
        if ($statuses && is_array($statuses) && count($statuses)) {
            $escaped = [];
            foreach ($statuses as $s) {
                $escaped[] = $this->db->escape($s);
            }
            $where .= " AND $requests.status IN (" . implode(",", $escaped) . ")";
        }

        // exclude statuses (e.g. hide "returned" from stage inboxes while requester amends)
        $exclude_statuses = get_array_value($options, "exclude_statuses");
        if ($exclude_statuses && is_array($exclude_statuses) && count($exclude_statuses) > 0) {
            $escaped_ex = [];
            foreach ($exclude_statuses as $s) {
                $escaped_ex[] = $this->db->escape((string) $s);
            }
            $where .= " AND $requests.status NOT IN (" . implode(",", $escaped_ex) . ")";
        }

        // filter by single status (for request list filter page)
        $status = get_array_value($options, "status");
        if ($status !== "" && $status !== null) {
            if ($status === "issued") {
                // ROP issuance uses this status/stage pair; the UI labels it Issued.
                $where .= " AND ($requests.status='issued' OR ($requests.status='rop_approved' AND $requests.stage='issued'))";
            } else {
                $where .= " AND $requests.status=" . $this->db->escape($status);
            }
        }

        // filter by company_id (single)
        $company_id = get_array_value($options, "company_id");
        if ($company_id !== "" && $company_id !== null) {
            $where .= " AND $requests.company_id=" . (int)$company_id;
        }

        // filter by department_id (single)
        $department_id = get_array_value($options, "department_id");
        if ($department_id !== "" && $department_id !== null) {
            $where .= " AND $requests.department_id=" . (int)$department_id;
        }

        // filter by gate_pass_purpose_id (purpose type)
        $gate_pass_purpose_id = get_array_value($options, "gate_pass_purpose_id");
        if ($gate_pass_purpose_id !== "" && $gate_pass_purpose_id !== null) {
            $where .= " AND $requests.gate_pass_purpose_id=" . (int)$gate_pass_purpose_id;
        }

        $nationality = trim((string)get_array_value($options, "nationality"));
        if ($nationality !== "") {
            $where .= " AND EXISTS (
                SELECT 1 FROM $visitors gp_nat
                WHERE gp_nat.gate_pass_request_id=$requests.id
                  AND gp_nat.deleted=0
                  AND TRIM(gp_nat.nationality)=" . $this->db->escape($nationality) . "
            )";
        }

        $visitor_identity = trim((string)get_array_value($options, "visitor_identity"));
        if ($visitor_identity !== "") {
            // Passport and civil ID values share id_number. EXISTS keeps each
            // request unique even when several of its visitors match.
            $where .= " AND EXISTS (
                SELECT 1 FROM $visitors gp_identity
                WHERE gp_identity.gate_pass_request_id=$requests.id
                  AND gp_identity.deleted=0
                  AND UPPER(gp_identity.id_number) LIKE UPPER(?) ESCAPE '!'";
            $bindings[] = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $visitor_identity) . '%';
            if ($nationality !== "") {
                $where .= " AND TRIM(gp_identity.nationality)=" . $this->db->escape($nationality);
            }
            $where .= ")";
        }

        // filter by date range (visit_from date)
        $date_from = get_array_value($options, "date_from");
        if ($date_from) {
            $where .= " AND DATE($requests.visit_from)>=" . $this->db->escape($date_from);
        }
        $date_to = get_array_value($options, "date_to");
        if ($date_to) {
            $where .= " AND DATE($requests.visit_from)<=" . $this->db->escape($date_to);
        }

        // Compare zero dates as text: strict MySQL rejects a zero-date literal
        // compared directly with a TIMESTAMP, even when no rows contain one.
        $sql = "SELECT 
                    $requests.*,
                    $companies.name AS company_name,
                    $departments.name AS department_name,
                    $purposes.name AS purpose_name,
                    $users.first_name AS requester_first_name,
                    $users.last_name AS requester_last_name,
                    $users.email AS requester_email,
                    COALESCE($users.phone, $users.alternative_phone) AS requester_phone,
                    CONCAT($users.first_name,' ',$users.last_name) AS requester_name,
                    (
                        SELECT GROUP_CONCAT(DISTINCT TRIM(gpv.nationality) ORDER BY TRIM(gpv.nationality) SEPARATOR ', ')
                        FROM $visitors gpv
                        WHERE gpv.gate_pass_request_id=$requests.id
                          AND gpv.deleted=0
                          AND TRIM(COALESCE(gpv.nationality, '')) <> ''
                    ) AS visitor_nationalities
                FROM $requests
                LEFT JOIN $companies ON $companies.id = $requests.company_id
                LEFT JOIN $departments ON $departments.id = $requests.department_id
                LEFT JOIN $purposes ON $purposes.id = $requests.gate_pass_purpose_id
                LEFT JOIN $users ON $users.id = $requests.requester_id
                $where
                ORDER BY COALESCE(
                    NULLIF(CAST($requests.created_at AS CHAR), '0000-00-00 00:00:00'),
                    NULLIF(CAST($requests.submitted_at AS CHAR), '0000-00-00 00:00:00'),
                    '1970-01-01 00:00:00'
                ) DESC, $requests.id DESC";

        return $this->db->query($sql, $bindings);
    }

    /**
     * BRD / commercial: block approving a waiver when the same company already has an active
     * overlapping pass for the same visitor ID number or vehicle plate.
     *
     * @param list<string> $id_numbers Visitor civil / ID numbers (trimmed, non-empty).
     * @param list<string> $plate_nos    Normalized plate strings (uppercase, no spaces).
     */
    public function has_overlapping_active_pass(
        int $exclude_request_id,
        ?string $visit_from,
        ?string $visit_to,
        int $company_id,
        array $id_numbers,
        array $plate_nos
    ): bool {
        $visit_from = trim((string) $visit_from);
        $visit_to = trim((string) $visit_to);
        if ($visit_from === "" || $visit_to === "") {
            return false;
        }

        $id_numbers = array_values(array_unique(array_filter(array_map(static function ($v) {
            return trim((string) $v);
        }, $id_numbers))));

        $plate_nos = array_values(array_unique(array_filter(array_map(static function ($p) {
            $p = strtoupper(str_replace("-", "", preg_replace('/\s+/', "", trim((string) $p))));

            return $p;
        }, $plate_nos))));

        if (!$id_numbers && !$plate_nos) {
            return false;
        }

        $requests = $this->db->prefixTable("gate_pass_requests");
        $visitors = $this->db->prefixTable("gate_pass_request_visitors");
        $vehicles = $this->db->prefixTable("gate_pass_request_vehicles");

        $active = ["submitted", "department_approved", "commercial_approved", "security_approved", "rop_approved", "issued"];
        $statusIn = implode(",", array_map(function ($s) {
            return $this->db->escape($s);
        }, $active));

        $overlap = "NOT (DATE($requests.visit_to) < DATE(" . $this->db->escape($visit_from) . ") OR DATE($requests.visit_from) > DATE(" . $this->db->escape($visit_to) . "))";

        if ($id_numbers) {
            $idIn = implode(",", array_map(function ($s) {
                return $this->db->escape($s);
            }, $id_numbers));
            $sql = "SELECT $requests.id
                    FROM $requests
                    INNER JOIN $visitors ON $visitors.gate_pass_request_id = $requests.id AND $visitors.deleted = 0
                    WHERE $requests.deleted = 0
                      AND $requests.id != " . (int) $exclude_request_id . "
                      AND $requests.company_id = " . (int) $company_id . "
                      AND $requests.status IN ($statusIn)
                      AND $requests.visit_from IS NOT NULL AND $requests.visit_to IS NOT NULL
                      AND $overlap
                      AND TRIM($visitors.id_number) IN ($idIn)
                    LIMIT 1";
            if ($this->db->query($sql)->getRow()) {
                return true;
            }
        }

        if ($plate_nos) {
            $plateExpr = "UPPER(REPLACE(REPLACE(TRIM($vehicles.plate_no),'-',''),' ',''))";
            $plateIn = implode(",", array_map(function ($s) {
                return $this->db->escape($s);
            }, $plate_nos));
            $sql = "SELECT $requests.id
                    FROM $requests
                    INNER JOIN $vehicles ON $vehicles.gate_pass_request_id = $requests.id AND $vehicles.deleted = 0
                    WHERE $requests.deleted = 0
                      AND $requests.id != " . (int) $exclude_request_id . "
                      AND $requests.company_id = " . (int) $company_id . "
                      AND $requests.status IN ($statusIn)
                      AND $requests.visit_from IS NOT NULL AND $requests.visit_to IS NOT NULL
                      AND $overlap
                      AND $plateExpr IN ($plateIn)
                    LIMIT 1";
            if ($this->db->query($sql)->getRow()) {
                return true;
            }
        }

        return false;
    }
}
