<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Persistent authentication state, one-time credentials, and audit history.
 */
class Authentication_hardening extends Migration
{
    public function up()
    {
        $users = $this->db->prefixTable('users');
        if (!$this->db->fieldExists('auth_session_version', $users)) {
            $this->forge->addColumn($users, [
                'auth_session_version' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                    'null' => false,
                    'default' => 1,
                    'after' => 'password',
                ],
            ]);
        }

        $this->createLoginSecurityTable();
        $this->createPasswordResetTokenTable();
        $this->createMfaChallengeTable();
        $this->createAuditTable();
    }

    public function down()
    {
        foreach ([
            'auth_audit_events',
            'auth_mfa_challenges',
            'auth_password_reset_tokens',
            'auth_login_security',
        ] as $table) {
            $this->forge->dropTable($table, true);
        }

        $users = $this->db->prefixTable('users');
        if ($this->db->fieldExists('auth_session_version', $users)) {
            $this->forge->dropColumn($users, 'auth_session_version');
        }
    }

    private function createLoginSecurityTable(): void
    {
        if ($this->db->tableExists('auth_login_security')) {
            return;
        }

        $this->forge->addField([
            'identity_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'user_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'failed_attempts' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'first_failed_at' => ['type' => 'DATETIME', 'null' => true],
            'last_failed_at' => ['type' => 'DATETIME', 'null' => true],
            'locked_until' => ['type' => 'DATETIME', 'null' => true],
            'last_ip_hash' => ['type' => 'CHAR', 'constraint' => 64, 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => false],
        ]);
        $this->forge->addKey('identity_hash', true);
        $this->forge->addKey('user_id');
        $this->forge->addKey('locked_until');
        $this->forge->createTable('auth_login_security', true);
    }

    private function createPasswordResetTokenTable(): void
    {
        if ($this->db->tableExists('auth_password_reset_tokens')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'user_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
            'selector' => ['type' => 'CHAR', 'constraint' => 24, 'null' => false],
            'validator_hash' => ['type' => 'CHAR', 'constraint' => 64, 'null' => false],
            'expires_at' => ['type' => 'DATETIME', 'null' => false],
            'used_at' => ['type' => 'DATETIME', 'null' => true],
            'request_ip_hash' => ['type' => 'CHAR', 'constraint' => 64, 'null' => false],
            'created_at' => ['type' => 'DATETIME', 'null' => false],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('selector');
        $this->forge->addKey(['user_id', 'used_at']);
        $this->forge->addKey('expires_at');
        $this->forge->createTable('auth_password_reset_tokens', true);
    }

    private function createMfaChallengeTable(): void
    {
        if ($this->db->tableExists('auth_mfa_challenges')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'challenge_id' => ['type' => 'CHAR', 'constraint' => 32, 'null' => false],
            'user_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
            'purpose' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => false],
            'provider' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => false],
            'destination_hint' => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => false],
            'code_hash' => ['type' => 'CHAR', 'constraint' => 64, 'null' => false],
            'attempts' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'max_attempts' => ['type' => 'INT', 'unsigned' => true, 'null' => false],
            'expires_at' => ['type' => 'DATETIME', 'null' => false],
            'consumed_at' => ['type' => 'DATETIME', 'null' => true],
            'request_ip_hash' => ['type' => 'CHAR', 'constraint' => 64, 'null' => false],
            'user_agent_hash' => ['type' => 'CHAR', 'constraint' => 64, 'null' => false],
            'created_at' => ['type' => 'DATETIME', 'null' => false],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('challenge_id');
        $this->forge->addKey(['user_id', 'purpose', 'consumed_at']);
        $this->forge->addKey('expires_at');
        $this->forge->createTable('auth_mfa_challenges', true);
    }

    private function createAuditTable(): void
    {
        if ($this->db->tableExists('auth_audit_events')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'user_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'event_type' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => false],
            'outcome' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => false],
            'identity_hash' => ['type' => 'CHAR', 'constraint' => 64, 'null' => true],
            'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => false],
            'user_agent_hash' => ['type' => 'CHAR', 'constraint' => 64, 'null' => false],
            'context_json' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => false],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['user_id', 'created_at']);
        $this->forge->addKey(['event_type', 'created_at']);
        $this->forge->addKey('identity_hash');
        $this->forge->createTable('auth_audit_events', true);
    }
}
