<?php

namespace App\Models;

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

        $table = $this->db->prefixTable("gate_pass_request_vehicles");

        if (!$this->db->fieldExists("is_international_plate", $table)) {
            $this->db->query("ALTER TABLE `$table` ADD COLUMN `is_international_plate` TINYINT(1) NOT NULL DEFAULT 0 AFTER `plate_no`");
        }
        if (!$this->db->fieldExists("plate_country", $table)) {
            $this->db->query("ALTER TABLE `$table` ADD COLUMN `plate_country` VARCHAR(120) DEFAULT NULL AFTER `is_international_plate`");
        }
        if (!$this->db->fieldExists("international_plate_no", $table)) {
            $this->db->query("ALTER TABLE `$table` ADD COLUMN `international_plate_no` VARCHAR(120) DEFAULT NULL AFTER `plate_country`");
        }

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
