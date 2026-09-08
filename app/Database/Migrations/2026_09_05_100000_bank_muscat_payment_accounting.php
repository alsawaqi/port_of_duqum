<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class Bank_muscat_payment_accounting extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('eservice_payments') || !$this->db->tableExists('eservice_payment_events')) {
            throw new \RuntimeException('Run the Eservice_payment_integrity migration before SmartPay accounting.');
        }
        $fields = [
            'gateway_merchant_id' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'bank_reference' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'response_json' => ['type' => 'MEDIUMTEXT', 'null' => true],
            'status_response_json' => ['type' => 'MEDIUMTEXT', 'null' => true],
            'verification_issues' => ['type' => 'TEXT', 'null' => true],
            'verified_at' => ['type' => 'DATETIME', 'null' => true],
            'returned_at' => ['type' => 'DATETIME', 'null' => true],
            'handed_off_at' => ['type' => 'DATETIME', 'null' => true],
            'last_status_check_at' => ['type' => 'DATETIME', 'null' => true],
            'settlement_status' => ['type' => 'VARCHAR', 'constraint' => 24, 'default' => 'pending'],
        ];
        foreach ($fields as $name => $definition) {
            if (!$this->db->fieldExists($name, 'eservice_payments')) {
                $this->forge->addColumn('eservice_payments', [$name => $definition]);
            }
        }
        foreach (['response_json' => ['type' => 'MEDIUMTEXT', 'null' => true], 'verification_issues' => ['type' => 'TEXT', 'null' => true]] as $name => $definition) {
            if (!$this->db->fieldExists($name, 'eservice_payment_events')) {
                $this->forge->addColumn('eservice_payment_events', [$name => $definition]);
            }
        }
        $table = $this->db->prefixTable('eservice_payments');
        $indexes = $this->db->getIndexData('eservice_payments');
        if (!isset($indexes['idx_eservice_payments_accounting'])) {
            $this->db->query("ALTER TABLE `{$table}` ADD INDEX `idx_eservice_payments_accounting` (`subject_type`, `initiated_at`, `id`)");
        }
        // An uncertain bank transaction must block a second charge for the same fee.
        $this->db->query("ALTER TABLE `{$table}` MODIFY COLUMN `active_subject_key` VARCHAR(190)
            GENERATED ALWAYS AS (CASE WHEN `deleted` = 0 AND `status` IN ('pending','processing','verification_required')
            THEN CONCAT(`subject_type`, ':', `subject_id`, ':', COALESCE(`vendor_id`, 0)) ELSE NULL END) STORED");
    }

    public function down()
    {
        // Payment evidence is retained deliberately. Roll back application code without deleting accounting data.
    }
}
