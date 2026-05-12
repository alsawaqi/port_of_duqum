<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Allows one issued QR/pass per visitor under the same gate pass request.
 */
class Gate_passes_per_visitor extends Migration
{
    public function up()
    {
        $db = $this->db;
        $table = $db->prefixTable("gate_passes");

        if (!$db->fieldExists("gate_pass_request_visitor_id", $table)) {
            $db->query(
                "ALTER TABLE `{$table}` ADD COLUMN `gate_pass_request_visitor_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `gate_pass_request_id`"
            );
            $db->query(
                "ALTER TABLE `{$table}` ADD INDEX `idx_gate_passes_request_visitor` (`gate_pass_request_id`, `gate_pass_request_visitor_id`)"
            );
        }

        $requests = $db->prefixTable("gate_pass_requests");
        $db->query(
            "UPDATE `{$table}` gp
             INNER JOIN `{$requests}` r ON r.id = gp.gate_pass_request_id AND r.deleted = 0
             SET gp.valid_from = r.visit_from, gp.valid_to = r.visit_to, gp.updated_at = NOW()
             WHERE gp.deleted = 0 AND r.visit_from IS NOT NULL AND r.visit_to IS NOT NULL"
        );
    }

    public function down()
    {
        $db = $this->db;
        $table = $db->prefixTable("gate_passes");

        if ($db->fieldExists("gate_pass_request_visitor_id", $table)) {
            $schema = $db->database;
            $idx = $db->query(
                "SELECT COUNT(*) AS total
                 FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND INDEX_NAME='idx_gate_passes_request_visitor'",
                [$schema, $table]
            )->getRow();
            if ((int)($idx->total ?? 0) > 0) {
                $db->query("ALTER TABLE `{$table}` DROP INDEX `idx_gate_passes_request_visitor`");
            }
            $this->forge->dropColumn("gate_passes", "gate_pass_request_visitor_id");
        }
    }
}
