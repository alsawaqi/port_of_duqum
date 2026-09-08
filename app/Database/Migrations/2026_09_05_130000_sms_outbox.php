<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class Sms_outbox extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('sms_outbox')) { return; }
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'event_key' => ['type' => 'CHAR', 'constraint' => 64],
            'module' => ['type' => 'VARCHAR', 'constraint' => 20],
            'subject_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'source_table' => ['type' => 'VARCHAR', 'constraint' => 40],
            'source_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'reference' => ['type' => 'VARCHAR', 'constraint' => 100],
            'reason' => ['type' => 'VARCHAR', 'constraint' => 40],
            'recipient_user_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'recipient_name' => ['type' => 'VARCHAR', 'constraint' => 190],
            'mobile' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'message' => ['type' => 'TEXT'],
            'language' => ['type' => 'INT', 'default' => 0],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30],
            'is_preview' => ['type' => 'TINYINT', 'default' => 1],
            'provider_code' => ['type' => 'INT', 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
            'claimed_at' => ['type' => 'DATETIME', 'null' => true],
            'processed_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('event_key');
        $this->forge->addKey(['status', 'id']);
        $this->forge->addKey(['module', 'subject_id']);
        $this->forge->createTable('sms_outbox', true, ['ENGINE' => 'InnoDB']);
    }

    public function down() { /* Preserve notification audit records. */ }
}
