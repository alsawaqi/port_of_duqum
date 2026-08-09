<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Replaces plaintext 3-key opening material with short-lived keyed hashes and
 * authenticated ciphertext. Existing plaintext cannot be safely transformed,
 * so active legacy sessions are expired and must be regenerated.
 */
class Tender_opening_secret_hardening extends Migration
{
    private const USER_FAILURE_INDEX = 'idx_tender_opening_failure_user';
    private const IP_FAILURE_INDEX = 'idx_tender_opening_failure_ip';
    private const EXPIRY_INDEX = 'idx_tender_opening_secret_expiry';

    public function up()
    {
        if (!$this->db->tableExists('tender_bid_openings')) {
            return;
        }

        $openings = $this->db->prefixTable('tender_bid_openings');
        $statusInfo = $this->db->query(
            "SHOW COLUMNS FROM `{$openings}` LIKE 'status'"
        )->getRow();
        if ($statusInfo && stripos((string) ($statusInfo->Type ?? ''), 'varchar') === false) {
            $this->db->query(
                "ALTER TABLE `{$openings}`
                 MODIFY COLUMN `status` VARCHAR(50) NOT NULL DEFAULT 'codes_generated'"
            );
        }

        $columns = [
            'signed_at' => [
                'type' => 'DATETIME',
                'null' => true,
                'after' => 'unlocked_at',
            ],
            'manual_form_path' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
                'after' => 'signed_at',
            ],
            'manual_form_original_name' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
                'after' => 'manual_form_path',
            ],
            'manual_form_uploaded_by' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'null' => true,
                'after' => 'manual_form_original_name',
            ],
            'manual_form_uploaded_at' => [
                'type' => 'DATETIME',
                'null' => true,
                'after' => 'manual_form_uploaded_by',
            ],
            'chairman_code_hash' => [
                'type' => 'CHAR',
                'constraint' => 64,
                'null' => true,
                'after' => 'member_code',
            ],
            'secretary_code_hash' => [
                'type' => 'CHAR',
                'constraint' => 64,
                'null' => true,
                'after' => 'chairman_code_hash',
            ],
            'member_code_hash' => [
                'type' => 'CHAR',
                'constraint' => 64,
                'null' => true,
                'after' => 'secretary_code_hash',
            ],
            'chairman_code_ciphertext' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
                'after' => 'member_code_hash',
            ],
            'secretary_code_ciphertext' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
                'after' => 'chairman_code_ciphertext',
            ],
            'member_code_ciphertext' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
                'after' => 'secretary_code_ciphertext',
            ],
        ];

        foreach ($columns as $name => $definition) {
            if (!$this->db->fieldExists($name, 'tender_bid_openings')) {
                $this->forge->addColumn('tender_bid_openings', [$name => $definition]);
            }
        }
        if (!$this->indexExists($openings, self::EXPIRY_INDEX)) {
            $this->db->query(
                "ALTER TABLE `{$openings}`
                 ADD INDEX `" . self::EXPIRY_INDEX . "`
                 (`status`, `deleted`, `expires_at`)"
            );
        }

        $this->db->query(
            "UPDATE `{$openings}`
             SET status='expired',
                 updated_at=NOW()
             WHERE deleted=0
               AND status='codes_generated'
               AND (
                    expires_at IS NULL
                    OR expires_at <= NOW()
                    OR chairman_code_hash IS NULL
                    OR secretary_code_hash IS NULL
                    OR member_code_hash IS NULL
                    OR chairman_code_ciphertext IS NULL
                    OR secretary_code_ciphertext IS NULL
                    OR member_code_ciphertext IS NULL
               )"
        );
        $this->db->query(
            "UPDATE `{$openings}`
             SET chairman_code=NULL,
                 secretary_code=NULL,
                 member_code=NULL"
        );
        $this->db->query(
            "UPDATE `{$openings}` AS older
             INNER JOIN `{$openings}` AS newer
                ON newer.tender_id=older.tender_id
               AND newer.stage=older.stage
               AND newer.deleted=0
               AND newer.status='codes_generated'
               AND newer.id>older.id
             SET older.status='expired',
                 older.chairman_code_hash=NULL,
                 older.secretary_code_hash=NULL,
                 older.member_code_hash=NULL,
                 older.chairman_code_ciphertext=NULL,
                 older.secretary_code_ciphertext=NULL,
                 older.member_code_ciphertext=NULL,
                 older.updated_at=NOW()
             WHERE older.deleted=0
               AND older.status='codes_generated'"
        );
        $this->db->query(
            "UPDATE `{$openings}` AS generated
             INNER JOIN `{$openings}` AS terminal
                ON terminal.tender_id=generated.tender_id
               AND terminal.stage=generated.stage
               AND terminal.deleted=0
               AND terminal.status IN ('unlocked','signed','manual_accepted')
             SET generated.status='expired',
                 generated.chairman_code_hash=NULL,
                 generated.secretary_code_hash=NULL,
                 generated.member_code_hash=NULL,
                 generated.chairman_code_ciphertext=NULL,
                 generated.secretary_code_ciphertext=NULL,
                 generated.member_code_ciphertext=NULL,
                 generated.updated_at=NOW()
             WHERE generated.deleted=0
               AND generated.status='codes_generated'"
        );
        $this->db->query(
            "UPDATE `{$openings}`
             SET chairman_code_hash=NULL,
                 secretary_code_hash=NULL,
                 member_code_hash=NULL,
                 chairman_code_ciphertext=NULL,
                 secretary_code_ciphertext=NULL,
                 member_code_ciphertext=NULL
             WHERE status <> 'codes_generated'
                OR deleted <> 0"
        );

        if (!$this->db->tableExists('tender_bid_opening_entries')) {
            return;
        }

        $entries = $this->db->prefixTable('tender_bid_opening_entries');
        $entryColumns = [
            'signature_statement' => [
                'type' => 'TEXT',
                'null' => true,
                'after' => 'confirmed_at',
            ],
            'signature_name' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
                'after' => 'signature_statement',
            ],
            'signature_image_path' => [
                'type' => 'VARCHAR',
                'constraint' => 500,
                'null' => true,
                'after' => 'signature_name',
            ],
            'signed_at' => [
                'type' => 'DATETIME',
                'null' => true,
                'after' => 'signature_image_path',
            ],
            'signature_ip_address' => [
                'type' => 'VARCHAR',
                'constraint' => 45,
                'null' => true,
                'after' => 'signed_at',
            ],
            'signature_user_agent' => [
                'type' => 'TEXT',
                'null' => true,
                'after' => 'signature_ip_address',
            ],
        ];
        foreach ($entryColumns as $name => $definition) {
            if (!$this->db->fieldExists($name, 'tender_bid_opening_entries')) {
                $this->forge->addColumn('tender_bid_opening_entries', [$name => $definition]);
            }
        }

        if (!$this->indexExists($entries, self::USER_FAILURE_INDEX)) {
            $this->db->query(
                "ALTER TABLE `{$entries}`
                 ADD INDEX `" . self::USER_FAILURE_INDEX . "`
                 (`tender_bid_opening_id`, `is_valid`, `user_id`, `confirmed_at`)"
            );
        }
        if (!$this->indexExists($entries, self::IP_FAILURE_INDEX)) {
            $this->db->query(
                "ALTER TABLE `{$entries}`
                 ADD INDEX `" . self::IP_FAILURE_INDEX . "`
                 (`tender_bid_opening_id`, `is_valid`, `ip_address`, `confirmed_at`)"
            );
        }

        if (
            $this->db->fieldExists('input_chairman_code', 'tender_bid_opening_entries')
            && $this->db->fieldExists('input_secretary_code', 'tender_bid_opening_entries')
            && $this->db->fieldExists('input_member_code', 'tender_bid_opening_entries')
        ) {
            $this->db->query(
                "UPDATE `{$entries}`
                 SET input_chairman_code=NULL,
                     input_secretary_code=NULL,
                     input_member_code=NULL
                 WHERE input_chairman_code IS NOT NULL
                    OR input_secretary_code IS NOT NULL
                    OR input_member_code IS NOT NULL"
            );
        }
    }

    public function down()
    {
        if ($this->db->tableExists('tender_bid_opening_entries')) {
            $entries = $this->db->prefixTable('tender_bid_opening_entries');
            foreach ([self::USER_FAILURE_INDEX, self::IP_FAILURE_INDEX] as $index) {
                if ($this->indexExists($entries, $index)) {
                    $this->db->query("ALTER TABLE `{$entries}` DROP INDEX `{$index}`");
                }
            }
        }

        if (!$this->db->tableExists('tender_bid_openings')) {
            return;
        }

        $openings = $this->db->prefixTable('tender_bid_openings');
        if ($this->indexExists($openings, self::EXPIRY_INDEX)) {
            $this->db->query(
                "ALTER TABLE `{$openings}` DROP INDEX `" . self::EXPIRY_INDEX . "`"
            );
        }

        foreach ([
            'member_code_ciphertext',
            'secretary_code_ciphertext',
            'chairman_code_ciphertext',
            'member_code_hash',
            'secretary_code_hash',
            'chairman_code_hash',
        ] as $column) {
            if ($this->db->fieldExists($column, 'tender_bid_openings')) {
                $this->forge->dropColumn('tender_bid_openings', $column);
            }
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return (bool) $this->db->query(
            'SELECT 1 FROM information_schema.STATISTICS'
            . ' WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND INDEX_NAME=? LIMIT 1',
            [$this->db->database, $table, $index]
        )->getRow();
    }
}
