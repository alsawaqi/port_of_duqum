<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class Legacy_invoice_payment_hardening extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('legacy_invoice_payment_attempts')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'public_id' => ['type' => 'CHAR', 'constraint' => 32],
            'provider' => ['type' => 'VARCHAR', 'constraint' => 24],
            'invoice_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'client_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'contact_user_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'payment_method_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'invoice_verification_code' => ['type' => 'CHAR', 'constraint' => 10, 'null' => true],
            'expected_amount' => ['type' => 'DECIMAL', 'constraint' => '15,3'],
            'expected_amount_minor' => ['type' => 'BIGINT', 'unsigned' => true],
            'currency' => ['type' => 'CHAR', 'constraint' => 3],
            'minor_unit_exponent' => ['type' => 'TINYINT', 'unsigned' => true],
            'provider_reference' => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true],
            'provider_transaction_id' => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true],
            'invoice_payment_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 24, 'default' => 'pending'],
            'expires_at' => ['type' => 'DATETIME'],
            'completed_at' => ['type' => 'DATETIME', 'null' => true],
            'failure_code' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME'],
            'deleted' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('public_id', 'uq_legacy_invoice_payment_public');
        $this->forge->addUniqueKey(
            ['provider', 'provider_reference'],
            'uq_legacy_invoice_payment_provider_reference'
        );
        $this->forge->addUniqueKey(
            ['provider', 'provider_transaction_id'],
            'uq_legacy_invoice_payment_provider_transaction'
        );
        $this->forge->addKey(['invoice_id', 'status'], false, false, 'idx_legacy_invoice_payment_invoice');
        $this->forge->addKey(['status', 'expires_at'], false, false, 'idx_legacy_invoice_payment_expiry');
        $this->forge->createTable('legacy_invoice_payment_attempts', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('legacy_invoice_payment_attempts', true);
    }
}
