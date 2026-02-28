<?php

namespace App\Models;

class Gate_pass_request_vehicles_model extends Crud_model
{
    protected $table = null;

    function __construct()
    {
        $this->table = "gate_pass_request_vehicles"; // => pod_gate_pass_request_vehicles
        parent::__construct($this->table);
    }

    function get_details($options = array())
    {
        $vehicles = $this->db->prefixTable("gate_pass_request_vehicles");

        $where = "WHERE $vehicles.deleted=0";

        $id = get_array_value($options, "id");
        if ($id) {
            $where .= " AND $vehicles.id=$id";
        }

        $request_id = get_array_value($options, "gate_pass_request_id");
        if ($request_id) {
            $where .= " AND $vehicles.gate_pass_request_id=$request_id";
        }

        $sql = "SELECT $vehicles.*
                FROM $vehicles
                $where
                ORDER BY $vehicles.id DESC";

        return $this->db->query($sql);
    }
}
