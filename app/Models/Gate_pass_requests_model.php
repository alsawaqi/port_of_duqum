<?php

namespace App\Models;

class Gate_pass_requests_model extends Crud_model
{
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

        $where = "WHERE $requests.deleted=0";

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

        // filter by single status (for request list filter page)
        $status = get_array_value($options, "status");
        if ($status !== "" && $status !== null) {
            $where .= " AND $requests.status=" . $this->db->escape($status);
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

        // filter by date range (visit_from date)
        $date_from = get_array_value($options, "date_from");
        if ($date_from) {
            $where .= " AND DATE($requests.visit_from)>=" . $this->db->escape($date_from);
        }
        $date_to = get_array_value($options, "date_to");
        if ($date_to) {
            $where .= " AND DATE($requests.visit_from)<=" . $this->db->escape($date_to);
        }

        $sql = "SELECT 
                    $requests.*,
                    $companies.name AS company_name,
                    $departments.name AS department_name,
                    $purposes.name AS purpose_name,
                    $users.first_name AS requester_first_name,
                    $users.last_name AS requester_last_name,
                    COALESCE($users.phone, $users.alternative_phone) AS requester_phone,
                    CONCAT($users.first_name,' ',$users.last_name) AS requester_name
                FROM $requests
                LEFT JOIN $companies ON $companies.id = $requests.company_id
                LEFT JOIN $departments ON $departments.id = $requests.department_id
                LEFT JOIN $purposes ON $purposes.id = $requests.gate_pass_purpose_id
                LEFT JOIN $users ON $users.id = $requests.requester_id
                $where
                ORDER BY $requests.id DESC";

        return $this->db->query($sql);
    }
}
