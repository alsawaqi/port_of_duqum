<?php

namespace App\Models;

class Gate_pass_request_visitors_model extends Crud_model
{
    protected $table = null;

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
}
