<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class Notification_processor_hardening extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('notification_processor_nonces')) {
            return;
        }

        $this->forge->addField([
            'nonce_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'expires_at' => ['type' => 'DATETIME'],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('nonce_hash', true);
        $this->forge->addKey('expires_at', false, false, 'idx_notification_processor_nonce_expiry');
        $this->forge->createTable('notification_processor_nonces', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('notification_processor_nonces', true);
    }
}
