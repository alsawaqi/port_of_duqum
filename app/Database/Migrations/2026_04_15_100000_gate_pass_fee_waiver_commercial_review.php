<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Department fee waiver is a request; commercial must approve/reject before payment or advance.
 */
class Gate_pass_fee_waiver_commercial_review extends Migration
{
    public function up()
    {
        $db = $this->db;
        $t = $db->prefixTable("gate_pass_requests");

        if (!$db->fieldExists("fee_waiver_requested", $t)) {
            $db->query(
                "ALTER TABLE `{$t}` ADD COLUMN `fee_waiver_requested` TINYINT(1) NOT NULL DEFAULT 0"
            );
        }
        if (!$db->fieldExists("fee_waiver_commercial_status", $t)) {
            $db->query(
                "ALTER TABLE `{$t}` ADD COLUMN `fee_waiver_commercial_status` VARCHAR(32) NULL DEFAULT NULL"
            );
        }

        // Legacy rows: fee already marked waived (old direct department waive) → treat as commercially settled.
        $db->query(
            "UPDATE `{$t}` SET `fee_waiver_requested` = 0, `fee_waiver_commercial_status` = 'approved' "
            . "WHERE `deleted` = 0 AND `fee_is_waived` = 1 AND (`fee_waiver_commercial_status` IS NULL OR `fee_waiver_commercial_status` = '')"
        );
    }

    public function down()
    {
        $db = $this->db;
        $t = $db->prefixTable("gate_pass_requests");
        if ($db->fieldExists("fee_waiver_commercial_status", $t)) {
            $this->forge->dropColumn("gate_pass_requests", "fee_waiver_commercial_status");
        }
        if ($db->fieldExists("fee_waiver_requested", $t)) {
            $this->forge->dropColumn("gate_pass_requests", "fee_waiver_requested");
        }
    }
}
