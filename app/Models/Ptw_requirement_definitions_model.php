<?php

namespace App\Models;

class Ptw_requirement_definitions_model extends Crud_model
{
    protected $table = null;

    public function __construct()
    {
        $this->table = "ptw_requirement_definitions";
        parent::__construct($this->table);
    }

    public function get_active_definitions()
    {
        $table = $this->db->prefixTable("ptw_requirement_definitions");
        $sql = "SELECT * FROM $table WHERE deleted=0 AND is_active=1 ORDER BY sort_order ASC, id ASC";
        return $this->db->query($sql);
    }

    public function get_details($options = [])
    {
        $table = $this->db->prefixTable("ptw_requirement_definitions");

        $where = " WHERE $table.deleted=0 ";

        if (!empty($options["id"])) {
            $where .= " AND $table.id=" . (int)$options["id"];
        }

        if (!empty($options["category"])) {
            $category = $this->db->escapeString($options["category"]);
            $where .= " AND $table.category='$category'";
        }

        if (isset($options["only_active"]) && (int)$options["only_active"] === 1) {
            $where .= " AND $table.is_active=1";
        }

        $sql = "SELECT *
                FROM $table
                $where
                ORDER BY $table.sort_order ASC, $table.id ASC";

        return $this->db->query($sql);
    }
}