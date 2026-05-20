<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class Add_phone_country_code_to_vendors extends Migration
{
    public function up()
    {
        $db = $this->db;
        $table = $db->prefixTable("vendors");

        if (!$db->fieldExists("phone_country_code", $table)) {
            $db->query(
                "ALTER TABLE `{$table}` ADD COLUMN `phone_country_code` VARCHAR(12) DEFAULT NULL AFTER `phone`"
            );
        }
    }

    public function down()
    {
        $db = $this->db;

        if ($db->fieldExists("phone_country_code", $db->prefixTable("vendors"))) {
            $this->forge->dropColumn("vendors", "phone_country_code");
        }
    }
}
