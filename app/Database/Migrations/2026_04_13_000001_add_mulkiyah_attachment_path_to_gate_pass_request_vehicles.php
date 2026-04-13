<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds mulkiyah (vehicle registration) attachment path used by the portal and inbox UIs.
 * Run: php spark migrate
 */
class Add_mulkiyah_attachment_path_to_gate_pass_request_vehicles extends Migration
{
    public function up()
    {
        $db = $this->db;
        $table = $db->prefixTable('gate_pass_request_vehicles');

        if ($db->fieldExists('mulkiyah_attachment_path', $table)) {
            return;
        }

        $db->query(
            "ALTER TABLE `{$table}` ADD COLUMN `mulkiyah_attachment_path` VARCHAR(512) NULL DEFAULT NULL COMMENT 'Vehicle registration (mulkiyah) scan'"
        );

        if ($db->fieldExists('vehicle_registration_attachment_path', $table)) {
            $db->query(
                "UPDATE `{$table}` SET `mulkiyah_attachment_path` = `vehicle_registration_attachment_path` "
                . "WHERE (`mulkiyah_attachment_path` IS NULL OR `mulkiyah_attachment_path` = '') "
                . "AND `vehicle_registration_attachment_path` IS NOT NULL AND `vehicle_registration_attachment_path` != ''"
            );
        }
    }

    public function down()
    {
        $db = $this->db;
        $table = $db->prefixTable('gate_pass_request_vehicles');

        if ($db->fieldExists('mulkiyah_attachment_path', $table)) {
            $this->forge->dropColumn('gate_pass_request_vehicles', 'mulkiyah_attachment_path');
        }
    }
}
