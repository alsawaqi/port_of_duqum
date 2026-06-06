<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class Add_international_plate_to_gate_pass_vehicles extends Migration
{
    public function up()
    {
        $db = db_connect();
        $table = $db->prefixTable("gate_pass_request_vehicles");

        if (!$db->fieldExists("is_international_plate", $table)) {
            $this->forge->addColumn("gate_pass_request_vehicles", [
                "is_international_plate" => [
                    "type" => "TINYINT",
                    "constraint" => 1,
                    "default" => 0,
                    "after" => "plate_no",
                ],
            ]);
        }

        if (!$db->fieldExists("plate_country", $table)) {
            $this->forge->addColumn("gate_pass_request_vehicles", [
                "plate_country" => [
                    "type" => "VARCHAR",
                    "constraint" => 120,
                    "null" => true,
                    "after" => "is_international_plate",
                ],
            ]);
        }

        if (!$db->fieldExists("international_plate_no", $table)) {
            $this->forge->addColumn("gate_pass_request_vehicles", [
                "international_plate_no" => [
                    "type" => "VARCHAR",
                    "constraint" => 120,
                    "null" => true,
                    "after" => "plate_country",
                ],
            ]);
        }
    }

    public function down()
    {
        $db = db_connect();
        $table = $db->prefixTable("gate_pass_request_vehicles");

        foreach (["international_plate_no", "plate_country", "is_international_plate"] as $column) {
            if ($db->fieldExists($column, $table)) {
                $this->forge->dropColumn("gate_pass_request_vehicles", $column);
            }
        }
    }
}
