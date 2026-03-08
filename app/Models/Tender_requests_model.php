<?php

namespace App\Models;

class Tender_requests_model extends Crud_model
{
    protected $table = null;

    public function __construct()
    {
        $this->table = "tender_requests";
        parent::__construct($this->table);
    }

    public function get_by_request_id(int $tender_request_id)
    {
        $t = $this->db->prefixTable("tenders");

        $sql = "SELECT * FROM $t
                WHERE deleted=0 AND tender_request_id=?
                ORDER BY id DESC
                LIMIT 1";
        return $this->db->query($sql, [$tender_request_id])->getRow();
    }

    public function get_details($options = [])
    {
        $req = $this->db->prefixTable("tender_requests");
        $companies = $this->db->prefixTable("companies");
        $departments = $this->db->prefixTable("departments");
        $users = $this->db->prefixTable("users");

        $where = "WHERE $req.deleted=0";

        if ($id = get_array_value($options, "id")) {
            $where .= " AND $req.id=" . (int) $id;
        }

        if ($status = get_array_value($options, "status")) {
            $where .= " AND $req.status=" . $this->db->escape($status);
        }

        if ($statuses = get_array_value($options, "statuses")) {
            if (is_array($statuses) && count($statuses)) {
                $escaped = [];
                foreach ($statuses as $s) {
                    $escaped[] = $this->db->escape($s);
                }
                $where .= " AND $req.status IN (" . implode(",", $escaped) . ")";
            }
        }

        if ($requester_id = get_array_value($options, "requester_id")) {
            $where .= " AND $req.requester_id=" . (int) $requester_id;
        }



        if ($department_manager_user_id = get_array_value($options, "department_manager_user_id")) {
            $where .= " AND $req.department_manager_user_id=" . (int) $department_manager_user_id;
        }

        $sql = "SELECT
                    $req.*,
                    $companies.name AS company_name,
                    $departments.name AS department_name,
                    TRIM(CONCAT(COALESCE(requester.first_name,''), ' ', COALESCE(requester.last_name,''))) AS requester_name,
                    TRIM(CONCAT(COALESCE(manager.first_name,''), ' ', COALESCE(manager.last_name,''))) AS department_manager_name
                FROM $req
                LEFT JOIN $companies ON $companies.id = $req.company_id
                LEFT JOIN $departments ON $departments.id = $req.department_id
                LEFT JOIN $users requester ON requester.id = $req.requester_id
                LEFT JOIN $users manager ON manager.id = $req.department_manager_user_id
                $where
                ORDER BY $req.id DESC";

        return $this->db->query($sql);
    }
}