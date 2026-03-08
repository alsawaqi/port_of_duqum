<?php

namespace App\Models;

/**
 * POD Dashboard aggregated statistics for Gate Pass + PTW.
 */
class Pod_dashboard_stats_model extends Crud_model
{
    public function __construct()
    {
        // Only need Crud_model's $this->db connection.
        parent::__construct('users');
    }

    public function gate_pass_kpis(array $options = [])
    {
        $requests = $this->db->prefixTable('gate_pass_requests');

        $where = "WHERE $requests.deleted=0";
        $requester_id = (int) get_array_value($options, 'requester_id');
        if ($requester_id) {
            $where .= " AND $requests.requester_id=$requester_id";
        }

        $company_id = (int) get_array_value($options, 'company_id');
        if ($company_id) {
            $where .= " AND $requests.company_id=$company_id";
        }

        $from_utc = get_array_value($options, 'from_utc');
        if ($from_utc) {
            $where .= " AND COALESCE($requests.submitted_at,$requests.created_at,$requests.updated_at) >= ".$this->db->escape($from_utc);
        }

        $status_rows = $this->db->query("SELECT $requests.status, COUNT(*) AS total FROM $requests $where GROUP BY $requests.status")->getResult();
        $status_map = [];
        foreach ($status_rows as $r) {
            $status_map[(string) $r->status] = (int) $r->total;
        }

        $active_statuses = ['submitted', 'department_approved', 'commercial_approved', 'security_approved', 'rop_approved', 'returned'];
        $active_in = implode(',', array_map([$this->db, 'escape'], $active_statuses));

        $stage_rows = $this->db->query(
            "SELECT $requests.stage, COUNT(*) AS total
             FROM $requests
             $where AND $requests.status IN ($active_in)
             GROUP BY $requests.stage"
        )->getResult();

        $stage_map = [];
        foreach ($stage_rows as $r) {
            $stage_map[(string) $r->stage] = (int) $r->total;
        }

        $issued_valid = (int) $this->db->query(
            "SELECT COUNT(*) AS total
             FROM $requests
             $where AND $requests.status='issued' AND ($requests.visit_to IS NULL OR $requests.visit_to >= UTC_TIMESTAMP())"
        )->getRow()->total;

        $in_progress = 0;
        foreach ($active_statuses as $s) {
            $in_progress += (int) (get_array_value($status_map, $s) ?: 0);
        }

        return [
            'status' => $status_map,
            'stage' => $stage_map,
            'in_progress' => $in_progress,
            'issued_valid' => $issued_valid,
        ];
    }

    public function ptw_kpis(array $options = [])
    {
        $apps = $this->db->prefixTable('ptw_applications');
        $where = "WHERE $apps.deleted=0";

        $applicant_user_id = (int) get_array_value($options, 'applicant_user_id');
        if ($applicant_user_id) {
            $where .= " AND $apps.applicant_user_id=$applicant_user_id";
        }

        $from_utc = get_array_value($options, 'from_utc');
        if ($from_utc) {
            $where .= " AND COALESCE($apps.submitted_at,$apps.created_at,$apps.updated_at) >= ".$this->db->escape($from_utc);
        }

        $status_rows = $this->db->query("SELECT $apps.status, COUNT(*) AS total FROM $apps $where GROUP BY $apps.status")->getResult();
        $status_map = [];
        foreach ($status_rows as $r) {
            $status_map[(string) $r->status] = (int) $r->total;
        }

        $active_statuses = ['submitted', 'in_review', 'revise'];
        $active_in = implode(',', array_map([$this->db, 'escape'], $active_statuses));

        $stage_rows = $this->db->query(
            "SELECT $apps.stage, COUNT(*) AS total
             FROM $apps
             $where AND $apps.status IN ($active_in)
             GROUP BY $apps.stage"
        )->getResult();

        $stage_map = [];
        foreach ($stage_rows as $r) {
            $stage_map[(string) $r->stage] = (int) $r->total;
        }

        $in_progress = 0;
        foreach ($active_statuses as $s) {
            $in_progress += (int) (get_array_value($status_map, $s) ?: 0);
        }

        return [
            'status' => $status_map,
            'stage' => $stage_map,
            'in_progress' => $in_progress,
        ];
    }

    public function gate_pass_avg_processing_times(array $options = [])
    {
        $days = (int) (get_array_value($options, 'days') ?: 30);
        if ($days < 1) {
            $days = 30;
        }

        $requests = $this->db->prefixTable('gate_pass_requests');
        $approvals = $this->db->prefixTable('gate_pass_request_approvals');
        $departments = $this->db->prefixTable('departments');

        $scope = 'AND r.deleted=0';
        $requester_id = (int) get_array_value($options, 'requester_id');
        if ($requester_id) {
            $scope .= " AND r.requester_id=$requester_id";
        }
        $company_id = (int) get_array_value($options, 'company_id');
        if ($company_id) {
            $scope .= " AND r.company_id=$company_id";
        }

        $since = "DATE_SUB(UTC_TIMESTAMP(), INTERVAL $days DAY)";

        $dept_decided = "(
            SELECT gate_pass_request_id, MIN(decided_at) AS decided_at
            FROM $approvals
            WHERE deleted=0 AND stage='department' AND decision IS NOT NULL AND decided_at IS NOT NULL
            GROUP BY gate_pass_request_id
        )";
        $dept_approved = "(
            SELECT gate_pass_request_id, MIN(decided_at) AS approved_at
            FROM $approvals
            WHERE deleted=0 AND stage='department' AND decision='approved' AND decided_at IS NOT NULL
            GROUP BY gate_pass_request_id
        )";
        $comm_decided = "(
            SELECT gate_pass_request_id, MIN(decided_at) AS decided_at
            FROM $approvals
            WHERE deleted=0 AND stage='commercial' AND decision IS NOT NULL AND decided_at IS NOT NULL
            GROUP BY gate_pass_request_id
        )";
        $comm_approved = "(
            SELECT gate_pass_request_id, MIN(decided_at) AS approved_at
            FROM $approvals
            WHERE deleted=0 AND stage='commercial' AND decision='approved' AND decided_at IS NOT NULL
            GROUP BY gate_pass_request_id
        )";
        $sec_decided = "(
            SELECT gate_pass_request_id, MIN(decided_at) AS decided_at
            FROM $approvals
            WHERE deleted=0 AND stage='security' AND decision IS NOT NULL AND decided_at IS NOT NULL
            GROUP BY gate_pass_request_id
        )";
        $sec_approved = "(
            SELECT gate_pass_request_id, MIN(decided_at) AS approved_at
            FROM $approvals
            WHERE deleted=0 AND stage='security' AND decision='approved' AND decided_at IS NOT NULL
            GROUP BY gate_pass_request_id
        )";
        $rop_decided = "(
            SELECT gate_pass_request_id, MIN(decided_at) AS decided_at
            FROM $approvals
            WHERE deleted=0 AND stage='rop' AND decision IS NOT NULL AND decided_at IS NOT NULL
            GROUP BY gate_pass_request_id
        )";

        $stage_avg = [
            'department' => ['avg_sec' => 0, 'count' => 0],
            'commercial' => ['avg_sec' => 0, 'count' => 0],
            'security' => ['avg_sec' => 0, 'count' => 0],
            'rop' => ['avg_sec' => 0, 'count' => 0],
        ];

        $row = $this->db->query(
            "SELECT
                AVG(TIMESTAMPDIFF(SECOND, COALESCE(r.submitted_at,r.created_at,r.updated_at), d.decided_at)) AS avg_sec,
                COUNT(*) AS cnt
             FROM $requests r
             JOIN $dept_decided d ON d.gate_pass_request_id=r.id
             WHERE COALESCE(r.submitted_at,r.created_at,r.updated_at) IS NOT NULL
               AND COALESCE(r.submitted_at,r.created_at,r.updated_at) >= $since
               $scope"
        )->getRow();
        if ($row) {
            $stage_avg['department']['avg_sec'] = (int) round($row->avg_sec ?: 0);
            $stage_avg['department']['count'] = (int) ($row->cnt ?: 0);
        }

        $row = $this->db->query(
            "SELECT
                AVG(TIMESTAMPDIFF(SECOND, da.approved_at, c.decided_at)) AS avg_sec,
                COUNT(*) AS cnt
             FROM $requests r
             JOIN $dept_approved da ON da.gate_pass_request_id=r.id
             JOIN $comm_decided c ON c.gate_pass_request_id=r.id
             WHERE da.approved_at IS NOT NULL AND c.decided_at IS NOT NULL
               AND da.approved_at >= $since
               $scope"
        )->getRow();
        if ($row) {
            $stage_avg['commercial']['avg_sec'] = (int) round($row->avg_sec ?: 0);
            $stage_avg['commercial']['count'] = (int) ($row->cnt ?: 0);
        }

        $row = $this->db->query(
            "SELECT
                AVG(TIMESTAMPDIFF(SECOND, ca.approved_at, s.decided_at)) AS avg_sec,
                COUNT(*) AS cnt
             FROM $requests r
             JOIN $comm_approved ca ON ca.gate_pass_request_id=r.id
             JOIN $sec_decided s ON s.gate_pass_request_id=r.id
             WHERE ca.approved_at IS NOT NULL AND s.decided_at IS NOT NULL
               AND ca.approved_at >= $since
               $scope"
        )->getRow();
        if ($row) {
            $stage_avg['security']['avg_sec'] = (int) round($row->avg_sec ?: 0);
            $stage_avg['security']['count'] = (int) ($row->cnt ?: 0);
        }

        $row = $this->db->query(
            "SELECT
                AVG(TIMESTAMPDIFF(SECOND, sa.approved_at, ro.decided_at)) AS avg_sec,
                COUNT(*) AS cnt
             FROM $requests r
             JOIN $sec_approved sa ON sa.gate_pass_request_id=r.id
             JOIN $rop_decided ro ON ro.gate_pass_request_id=r.id
             WHERE sa.approved_at IS NOT NULL AND ro.decided_at IS NOT NULL
               AND sa.approved_at >= $since
               $scope"
        )->getRow();
        if ($row) {
            $stage_avg['rop']['avg_sec'] = (int) round($row->avg_sec ?: 0);
            $stage_avg['rop']['count'] = (int) ($row->cnt ?: 0);
        }

        $dept_rows = $this->db->query(
            "SELECT
                r.department_id,
                dpt.name AS department_name,
                AVG(TIMESTAMPDIFF(SECOND, COALESCE(r.submitted_at,r.created_at,r.updated_at), dd.decided_at)) AS avg_sec,
                COUNT(*) AS cnt
             FROM $requests r
             JOIN $dept_decided dd ON dd.gate_pass_request_id=r.id
             LEFT JOIN $departments dpt ON dpt.id=r.department_id
             WHERE COALESCE(r.submitted_at,r.created_at,r.updated_at) IS NOT NULL
               AND COALESCE(r.submitted_at,r.created_at,r.updated_at) >= $since
               $scope
             GROUP BY r.department_id
             ORDER BY avg_sec DESC
             LIMIT 10"
        )->getResult();

        $dept_avg = [];
        foreach ($dept_rows as $r) {
            $dept_avg[] = [
                'department_id' => (int) $r->department_id,
                'department_name' => (string) ($r->department_name ?: '-'),
                'avg_sec' => (int) round($r->avg_sec ?: 0),
                'count' => (int) ($r->cnt ?: 0),
            ];
        }

        return ['days' => $days, 'stage_avg' => $stage_avg, 'department_avg' => $dept_avg];
    }

    public function ptw_avg_processing_times(array $options = [])
    {
        $days = (int) (get_array_value($options, 'days') ?: 30);
        if ($days < 1) {
            $days = 30;
        }

        $apps = $this->db->prefixTable('ptw_applications');
        $reviews = $this->db->prefixTable('ptw_reviews');

        $scope = 'AND a.deleted=0';
        $applicant_user_id = (int) get_array_value($options, 'applicant_user_id');
        if ($applicant_user_id) {
            $scope .= " AND a.applicant_user_id=$applicant_user_id";
        }

        $since = "DATE_SUB(UTC_TIMESTAMP(), INTERVAL $days DAY)";

        $rows = $this->db->query(
            "SELECT
                r.stage,
                AVG(TIMESTAMPDIFF(SECOND, r.received_at, r.completed_at)) AS avg_sec,
                COUNT(*) AS cnt
             FROM $reviews r
             JOIN $apps a ON a.id=r.ptw_application_id
             WHERE r.deleted=0
               AND r.received_at IS NOT NULL
               AND r.completed_at IS NOT NULL
               AND r.received_at >= $since
               $scope
             GROUP BY r.stage"
        )->getResult();

        $stage_avg = [
            'hsse' => ['avg_sec' => 0, 'count' => 0],
            'hmo' => ['avg_sec' => 0, 'count' => 0],
            'terminal' => ['avg_sec' => 0, 'count' => 0],
        ];

        foreach ($rows as $r) {
            $stage = (string) $r->stage;
            if (!isset($stage_avg[$stage])) {
                $stage_avg[$stage] = ['avg_sec' => 0, 'count' => 0];
            }
            $stage_avg[$stage]['avg_sec'] = (int) round($r->avg_sec ?: 0);
            $stage_avg[$stage]['count'] = (int) ($r->cnt ?: 0);
        }

        return ['days' => $days, 'stage_avg' => $stage_avg];
    }

    public function gate_pass_top_rejection_reasons(array $options = [])
    {
        $days = (int) (get_array_value($options, 'days') ?: 90);
        if ($days < 1) {
            $days = 90;
        }

        $limit = (int) (get_array_value($options, 'limit') ?: 5);
        if ($limit < 1) {
            $limit = 5;
        }

        $requests = $this->db->prefixTable('gate_pass_requests');
        $approvals = $this->db->prefixTable('gate_pass_request_approvals');
        $reasons = $this->db->prefixTable('gate_pass_reasons');

        $scope = 'AND r.deleted=0';
        $requester_id = (int) get_array_value($options, 'requester_id');
        if ($requester_id) {
            $scope .= " AND r.requester_id=$requester_id";
        }

        $since = "DATE_SUB(UTC_TIMESTAMP(), INTERVAL $days DAY)";

        $rows = $this->db->query(
            "SELECT
                COALESCE(gs.title, '(No reason)') AS reason,
                COUNT(*) AS cnt
             FROM $approvals a
             JOIN $requests r ON r.id=a.gate_pass_request_id
             LEFT JOIN $reasons gs ON gs.id=a.reason_id
             WHERE a.deleted=0
               AND a.decision='rejected'
               AND a.decided_at IS NOT NULL
               AND a.decided_at >= $since
               $scope
             GROUP BY reason
             ORDER BY cnt DESC
             LIMIT $limit"
        )->getResult();

        $result = [];
        foreach ($rows as $r) {
            $result[] = ['reason' => (string) $r->reason, 'count' => (int) $r->cnt];
        }

        return ['days' => $days, 'rows' => $result];
    }

    public function ptw_top_rejection_reasons(array $options = [])
    {
        $days = (int) (get_array_value($options, 'days') ?: 90);
        if ($days < 1) {
            $days = 90;
        }

        $limit = (int) (get_array_value($options, 'limit') ?: 5);
        if ($limit < 1) {
            $limit = 5;
        }

        $apps = $this->db->prefixTable('ptw_applications');
        $reviews = $this->db->prefixTable('ptw_reviews');

        $scope = 'AND a.deleted=0';
        $applicant_user_id = (int) get_array_value($options, 'applicant_user_id');
        if ($applicant_user_id) {
            $scope .= " AND a.applicant_user_id=$applicant_user_id";
        }

        $since = "DATE_SUB(UTC_TIMESTAMP(), INTERVAL $days DAY)";

        $rows = $this->db->query(
            "SELECT
                COALESCE(NULLIF(TRIM(r.status_change_reason),''), '(No reason)') AS reason,
                COUNT(*) AS cnt
             FROM $reviews r
             JOIN $apps a ON a.id=r.ptw_application_id
             WHERE r.deleted=0
               AND r.decision='rejected'
               AND r.reviewed_at IS NOT NULL
               AND r.reviewed_at >= $since
               $scope
             GROUP BY reason
             ORDER BY cnt DESC
             LIMIT $limit"
        )->getResult();

        $result = [];
        foreach ($rows as $r) {
            $result[] = ['reason' => (string) $r->reason, 'count' => (int) $r->cnt];
        }

        return ['days' => $days, 'rows' => $result];
    }
}
