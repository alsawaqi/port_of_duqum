<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Ensures gate_pass_requests.created_at exists and is populated from submitted_at where missing.
 */
class Add_created_at_to_gate_pass_requests extends Migration
{
    public function up()
    {
        $db = $this->db;
        $table = $db->prefixTable('gate_pass_requests');

        if (!$db->fieldExists('created_at', $table)) {
            $db->query(
                "ALTER TABLE `{$table}` ADD COLUMN `created_at` DATETIME NULL DEFAULT NULL"
            );
        }

        if ($db->fieldExists('submitted_at', $table)) {
            $db->query(
                "UPDATE `{$table}` SET `created_at` = `submitted_at` "
                . "WHERE `deleted` = 0 AND `submitted_at` IS NOT NULL AND `submitted_at` <> '0000-00-00 00:00:00' "
                . "AND (`created_at` IS NULL OR `created_at` = '0000-00-00 00:00:00')"
            );
        }
    }

    public function down()
    {
        $db = $this->db;
        if ($db->fieldExists('created_at', $db->prefixTable('gate_pass_requests'))) {
            $this->forge->dropColumn('gate_pass_requests', 'created_at');
        }
    }
}
