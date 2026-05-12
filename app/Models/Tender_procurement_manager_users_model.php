<?php

namespace App\Models;

class Tender_procurement_manager_users_model extends Crud_model
{
    protected $table = null;

    public function __construct()
    {
        $this->table = "tender_procurement_manager_users";
        parent::__construct($this->table);
    }

    public function get_details($options = [])
    {
        $t = $this->db->prefixTable("tender_procurement_manager_users");
        $users = $this->db->prefixTable("users");
        $companies = $this->db->prefixTable("companies");

        $where = " WHERE $t.deleted=0";

        if (!empty($options["id"])) $where .= " AND $t.id=" . (int)$options["id"];
        if (!empty($options["user_id"])) $where .= " AND $t.user_id=" . (int)$options["user_id"];
        if (isset($options["company_id"]) && $options["company_id"] !== "") $where .= " AND $t.company_id=" . (int)$options["company_id"];

        $sql = "SELECT $t.*,
                    $users.first_name, $users.last_name, $users.email, $users.phone,
                    $companies.name AS company_name
                FROM $t
                LEFT JOIN $users ON $users.id=$t.user_id AND $users.deleted=0
                LEFT JOIN $companies ON $companies.id=$t.company_id AND $companies.deleted=0
                $where
                ORDER BY $t.id DESC";
        return $this->db->query($sql);
    }

    public function is_manager_for_company(int $user_id, int $company_id): bool
    {
        if (!$user_id || !$company_id) {
            return false;
        }

        $t = $this->db->prefixTable("tender_procurement_manager_users");
        $row = $this->db->query(
            "SELECT id FROM $t
             WHERE deleted=0
               AND status='active'
               AND user_id=?
               AND company_id=?
             LIMIT 1",
            [$user_id, $company_id]
        )->getRow();

        return (bool)$row;
    }
}
