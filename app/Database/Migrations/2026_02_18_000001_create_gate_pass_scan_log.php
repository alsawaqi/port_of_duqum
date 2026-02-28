<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class Create_gate_pass_scan_log extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'gate_pass_request_id' => [
                'type'       => 'BIGINT',
                'unsigned'   => true,
                'null'       => false,
            ],
            'gate_pass_id' => [
                'type'       => 'BIGINT',
                'unsigned'   => true,
                'null'       => true,
            ],
            'security_user_id' => [
                'type'       => 'BIGINT',
                'unsigned'   => true,
                'null'       => true,
                'comment'     => 'id from pod_gate_pass_security_users (who performed the action)',
            ],
            'action' => [
                'type'       => 'ENUM',
                'constraint' => ['entry', 'exit', 'check'],
                'null'       => false,
            ],
            'note' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'recorded_at' => [
                'type' => 'DATETIME',
                'null' => false,
                'comment' => 'Date and time of the entry/exit',
            ],
            'performed_by' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => false,
                'comment'    => 'users.id who performed the action',
            ],
            'ip_address' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'user_agent' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('gate_pass_request_id');
        $this->forge->addKey('security_user_id');
        $this->forge->addKey('recorded_at');
        $this->forge->createTable('gate_pass_scan_log');
    }

    public function down()
    {
        $this->forge->dropTable('gate_pass_scan_log');
    }
}
