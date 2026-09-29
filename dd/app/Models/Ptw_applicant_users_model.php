<?php

namespace App\Models;

class Ptw_applicant_users_model extends Crud_model
{
    protected $table = null;

    public function __construct()
    {
        $this->table = "ptw_applicant_users";
        parent::__construct($this->table);
    }

    public function get_details($options = [])
    {
        $assignments = $this->db->prefixTable("ptw_applicant_users");
        $users = $this->db->prefixTable("users");
        $companies = $this->db->prefixTable("companies");
        $where = " WHERE $assignments.deleted=0";

        if (!empty($options["id"])) {
            $where .= " AND $assignments.id=" . (int) $options["id"];
        }
        if (!empty($options["user_id"])) {
            $where .= " AND $assignments.user_id=" . (int) $options["user_id"];
        }
        if (isset($options["company_id"]) && $options["company_id"] !== "") {
            $where .= " AND $assignments.company_id=" . (int) $options["company_id"];
        }
        if (isset($options["status"]) && $options["status"] !== "") {
            $status = $this->db->escapeString((string) $options["status"]);
            $where .= " AND $assignments.status='$status'";
        }

        return $this->db->query(
            "SELECT $assignments.*,
                    $users.first_name, $users.last_name, $users.email, $users.phone,
                    $users.status AS user_status, $companies.name AS company_name
             FROM $assignments
             INNER JOIN $users ON $users.id=$assignments.user_id AND $users.deleted=0
             INNER JOIN $companies ON $companies.id=$assignments.company_id AND $companies.deleted=0
             $where
             ORDER BY $companies.name ASC, $users.first_name ASC, $assignments.id DESC"
        );
    }

    public function get_active_company_ids(int $userId): array
    {
        if ($userId < 1) {
            return [];
        }

        $ids = [];
        foreach ($this->get_details([
            "user_id" => $userId,
            "status" => "active",
        ])->getResult() as $assignment) {
            $companyId = (int) ($assignment->company_id ?? 0);
            if ($companyId > 0) {
                $ids[$companyId] = $companyId;
            }
        }

        return array_values($ids);
    }

    public function has_active_company_assignment(int $userId, int $companyId): bool
    {
        if ($userId < 1 || $companyId < 1) {
            return false;
        }

        return (bool) $this->get_details([
            "user_id" => $userId,
            "company_id" => $companyId,
            "status" => "active",
        ])->getRow();
    }
}
