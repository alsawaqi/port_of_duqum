<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Stores which visitor entered/exited/was checked for each QR scan action.
 */
class Gate_pass_scan_log_visitors extends Migration
{
    public function up()
    {
        $db = $this->db;
        $table = $db->prefixTable("gate_pass_scan_log");

        if (!$db->fieldExists("gate_pass_request_visitor_id", $table)) {
            $db->query(
                "ALTER TABLE `{$table}` ADD COLUMN `gate_pass_request_visitor_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `gate_pass_id`"
            );
            $db->query(
                "ALTER TABLE `{$table}` ADD INDEX `idx_gate_pass_scan_log_visitor` (`gate_pass_request_id`, `gate_pass_request_visitor_id`, `recorded_at`)"
            );
        }
    }

    public function down()
    {
        $db = $this->db;
        $table = $db->prefixTable("gate_pass_scan_log");

        if ($db->fieldExists("gate_pass_request_visitor_id", $table)) {
            $schema = $db->database;
            $idx = $db->query(
                "SELECT COUNT(*) AS total
                 FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND INDEX_NAME='idx_gate_pass_scan_log_visitor'",
                [$schema, $table]
            )->getRow();
            if ((int)($idx->total ?? 0) > 0) {
                $db->query("ALTER TABLE `{$table}` DROP INDEX `idx_gate_pass_scan_log_visitor`");
            }
            $this->forge->dropColumn("gate_pass_scan_log", "gate_pass_request_visitor_id");
        }
    }
}
