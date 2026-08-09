<?php

namespace App\Models;

class Vendor_contacts_model extends Crud_model
{
    protected $table = null;

    function __construct()
    {
        $this->table = "vendor_contacts";
        parent::__construct($this->table);
    }

    function get_details($options = array())
    {
        $vendor_contacts_table = $this->db->prefixTable("vendor_contacts");
        $users_table = $this->db->prefixTable("users");
        $vendor_users_table = $this->db->prefixTable("vendor_users");

        $where = "";
        $id = $this->_get_clean_value($options, "id");
        if ($id) {
            $where .= " AND $vendor_contacts_table.id=$id";
        }

        $vendor_id = $this->_get_clean_value($options, "vendor_id");
        if ($vendor_id) {
            $where .= " AND $vendor_contacts_table.vendor_id=$vendor_id";
        }

        $sql = "SELECT
                    $vendor_contacts_table.*,
                    $users_table.status AS account_status,
                    $users_table.disable_login AS account_login_disabled,
                    $vendor_users_table.status AS portal_access_status,
                    $vendor_users_table.is_owner AS portal_is_owner,
                    $vendor_users_table.invited_at AS portal_invited_at,
                    $vendor_users_table.credentials_ready_at AS portal_credentials_ready_at
                FROM $vendor_contacts_table
                LEFT JOIN $users_table
                    ON $users_table.id=$vendor_contacts_table.user_id
                   AND $users_table.deleted=0
                LEFT JOIN $vendor_users_table
                    ON $vendor_users_table.vendor_id=$vendor_contacts_table.vendor_id
                   AND $vendor_users_table.user_id=$vendor_contacts_table.user_id
                   AND $vendor_users_table.deleted=0
                WHERE $vendor_contacts_table.deleted=0 $where
                ORDER BY $vendor_contacts_table.is_primary DESC, $vendor_contacts_table.contacts_name ASC";

        return $this->db->query($sql);
    }
}
