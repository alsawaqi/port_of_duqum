<?php

namespace App\Models;

class Ptw_reasons_model extends Crud_model
{
    protected $table = null;

    public function __construct()
    {
        $this->table = "ptw_reasons";
        parent::__construct($this->table);
    }

    public function get_details($options = [])
    {
        $reasons = $this->db->prefixTable("ptw_reasons");

        $where = " WHERE $reasons.deleted=0 ";

        if (!empty($options["id"])) {
            $where .= " AND $reasons.id=" . (int)$options["id"];
        }

        if (!empty($options["stage"])) {
            $stage = $this->db->escapeString($options["stage"]);
            $where .= " AND ($reasons.stage='$stage' OR $reasons.stage='any')";
        }

        if (!empty($options["reason_type"])) {
            $reason_type = $this->db->escapeString($options["reason_type"]);
            $where .= " AND $reasons.reason_type='$reason_type'";
        }

        if (isset($options["only_active"]) && (int)$options["only_active"] === 1) {
            $where .= " AND $reasons.is_active=1";
        }

        $sql = "SELECT *
                FROM $reasons
                $where
                ORDER BY sort_order ASC, id ASC";

        return $this->db->query($sql);
    }
}