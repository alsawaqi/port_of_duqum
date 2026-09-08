<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class Vendor_fee_requests extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('vendor_fee_requests')) {
            return;
        }
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'vendor_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'fee_type' => ['type' => 'VARCHAR', 'constraint' => 20],
            'period_key' => ['type' => 'CHAR', 'constraint' => 64],
            'fee_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'vendor_group_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'amount' => ['type' => 'DECIMAL', 'constraint' => '15,3'],
            'currency' => ['type' => 'CHAR', 'constraint' => 3],
            'prior_valid_until' => ['type' => 'DATE', 'null' => true],
            'validity_days' => ['type' => 'INT', 'unsigned' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 24, 'default' => 'pending'],
            'payment_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'review_status' => ['type' => 'VARCHAR', 'constraint' => 24, 'default' => 'pending'],
            'requested_by' => ['type' => 'BIGINT', 'unsigned' => true],
            'reviewed_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'reviewed_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['vendor_id', 'period_key'], 'uq_vendor_fee_period');
        $this->forge->addKey(['vendor_id', 'review_status'], false, false, 'idx_vendor_fee_review');
        $this->forge->createTable('vendor_fee_requests', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        // Financial/review evidence is retained deliberately; rollback code only.
    }
}
