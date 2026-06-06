<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class Add_signature_image_to_tender_bid_opening_entries extends Migration
{
    public function up()
    {
        $table = $this->db->prefixTable("tender_bid_opening_entries");

        if (!$this->db->fieldExists("signature_image_path", $table)) {
            $this->db->query(
                "ALTER TABLE `{$table}` ADD COLUMN `signature_image_path` VARCHAR(500) DEFAULT NULL AFTER `signature_name`"
            );
        }
    }

    public function down()
    {
        $table = $this->db->prefixTable("tender_bid_opening_entries");

        if ($this->db->fieldExists("signature_image_path", $table)) {
            $this->forge->dropColumn("tender_bid_opening_entries", "signature_image_path");
        }
    }
}
