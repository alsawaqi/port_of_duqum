<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class Expand_tender_communication_types extends Migration
{
    public function up()
    {
        $table = $this->db->prefixTable("tender_communications");

        $this->db->query("ALTER TABLE `{$table}` MODIFY COLUMN `type` VARCHAR(50) NOT NULL DEFAULT 'clarification'");

        $this->db->query(
            "UPDATE `{$table}`
             SET `type` = CONCAT(COALESCE(NULLIF(`internal_audience`, ''), `clarification_scope`), '_clarification_request')
             WHERE deleted = 0
               AND (`type` IS NULL OR `type` = '')
               AND COALESCE(NULLIF(`internal_audience`, ''), `clarification_scope`) IN ('technical', 'commercial')
               AND (parent_id IS NULL OR parent_id = 0)"
        );

        $this->db->query(
            "UPDATE `{$table}` child
             INNER JOIN `{$table}` root
                ON root.id = child.parent_id
               AND root.deleted = 0
             SET child.`type` = CONCAT(COALESCE(NULLIF(root.`internal_audience`, ''), root.`clarification_scope`), '_clarification_response')
             WHERE child.deleted = 0
               AND (child.`type` IS NULL OR child.`type` = '')
               AND COALESCE(NULLIF(root.`internal_audience`, ''), root.`clarification_scope`) IN ('technical', 'commercial')"
        );

        $this->db->query(
            "UPDATE `{$table}`
             SET `type` = 'clarification'
             WHERE `type` IS NULL OR `type` = ''"
        );
    }

    public function down()
    {
        $table = $this->db->prefixTable("tender_communications");

        $this->db->query(
            "UPDATE `{$table}`
             SET `type` = CASE
                WHEN `type` IN ('technical_clarification_response', 'commercial_clarification_response') THEN 'response'
                WHEN `type` IN ('technical_clarification_request', 'commercial_clarification_request') THEN 'clarification'
                ELSE `type`
             END"
        );

        $this->db->query("ALTER TABLE `{$table}` MODIFY COLUMN `type` ENUM('clarification','response','circular','addendum','site_visit_notice') NOT NULL DEFAULT 'clarification'");
    }
}
