<?php

namespace App\Models;

use App\Libraries\Runtime_schema_guard;

class Gate_pass_request_vehicles_model extends Crud_model
{
    protected $table = null;
    private static bool $international_plate_schema_checked = false;

    function __construct()
    {
        $this->table = "gate_pass_request_vehicles"; // => pod_gate_pass_request_vehicles
        parent::__construct($this->table);
        $this->ensure_international_plate_schema();
    }

    function ensure_international_plate_schema(): void
    {
        if (self::$international_plate_schema_checked) {
            return;
        }

        Runtime_schema_guard::requireTablesAndColumns($this->db, [
            "gate_pass_request_vehicles" => [
                "is_international_plate", "plate_country", "international_plate_no",
            ],
        ], "international vehicle plates");

        self::$international_plate_schema_checked = true;
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
