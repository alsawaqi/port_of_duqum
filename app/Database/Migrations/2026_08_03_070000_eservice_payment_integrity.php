<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class Eservice_payment_integrity extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('eservice_payments')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'public_id' => ['type' => 'CHAR', 'constraint' => 32],
                'subject_type' => ['type' => 'VARCHAR', 'constraint' => 40],
                'subject_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'vendor_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'user_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'amount' => ['type' => 'DECIMAL', 'constraint' => '15,3'],
                'amount_minor' => ['type' => 'BIGINT', 'unsigned' => true],
                'currency' => ['type' => 'CHAR', 'constraint' => 3],
                'provider' => ['type' => 'VARCHAR', 'constraint' => 32],
                'status' => ['type' => 'VARCHAR', 'constraint' => 24, 'default' => 'pending'],
                'idempotency_key' => ['type' => 'CHAR', 'constraint' => 64],
                'provider_checkout_id' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'checkout_url' => ['type' => 'TEXT', 'null' => true],
                'provider_payment_id' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'metadata' => ['type' => 'TEXT', 'null' => true],
                'initiated_at' => ['type' => 'DATETIME'],
                'expires_at' => ['type' => 'DATETIME', 'null' => true],
                'paid_at' => ['type' => 'DATETIME', 'null' => true],
                'failed_at' => ['type' => 'DATETIME', 'null' => true],
                'failure_code' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
                'created_at' => ['type' => 'DATETIME'],
                'updated_at' => ['type' => 'DATETIME'],
                'deleted' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('public_id', 'uq_eservice_payments_public');
            $this->forge->addUniqueKey('idempotency_key', 'uq_eservice_payments_idempotency');
            $this->forge->addUniqueKey(['provider', 'provider_checkout_id'], 'uq_eservice_payments_checkout');
            $this->forge->addKey(['subject_type', 'subject_id', 'vendor_id'], false, false, 'idx_eservice_payments_subject');
            $this->forge->addKey(['status', 'expires_at'], false, false, 'idx_eservice_payments_status');
            $this->forge->createTable('eservice_payments', true, ['ENGINE' => 'InnoDB']);

            $table = $this->db->prefixTable('eservice_payments');
            $this->db->query(
                "ALTER TABLE `{$table}` ADD COLUMN `active_subject_key` VARCHAR(190)
                 GENERATED ALWAYS AS (
                    CASE WHEN `deleted` = 0 AND `status` IN ('pending', 'processing')
                    THEN CONCAT(`subject_type`, ':', `subject_id`, ':', COALESCE(`vendor_id`, 0))
                    ELSE NULL END
                 ) STORED"
            );
            $this->db->query(
                "ALTER TABLE `{$table}` ADD UNIQUE INDEX `uq_eservice_payments_active_subject` (`active_subject_key`)"
            );
        }

        if (!$this->db->tableExists('eservice_payment_events')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'payment_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'provider' => ['type' => 'VARCHAR', 'constraint' => 32],
                'provider_event_id' => ['type' => 'VARCHAR', 'constraint' => 255],
                'event_type' => ['type' => 'VARCHAR', 'constraint' => 100],
                'payload_sha256' => ['type' => 'CHAR', 'constraint' => 64],
                'status' => ['type' => 'VARCHAR', 'constraint' => 24, 'default' => 'received'],
                'received_at' => ['type' => 'DATETIME'],
                'processed_at' => ['type' => 'DATETIME', 'null' => true],
                'created_at' => ['type' => 'DATETIME'],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['provider', 'provider_event_id'], 'uq_eservice_payment_events_provider');
            $this->forge->addKey('payment_id');
            $this->forge->addKey(['status', 'received_at']);
            $this->forge->createTable('eservice_payment_events', true, ['ENGINE' => 'InnoDB']);
        }
    }

    public function down()
    {
        $this->forge->dropTable('eservice_payment_events', true);
        $this->forge->dropTable('eservice_payments', true);
    }
}
