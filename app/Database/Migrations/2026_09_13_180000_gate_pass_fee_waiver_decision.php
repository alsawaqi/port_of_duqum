<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

/** Adds the existing waiver-history decision without removing legacy enum values. */
class Gate_pass_fee_waiver_decision extends Migration
{
    public function up()
    {
        $table = $this->db->prefixTable('gate_pass_request_approvals');
        $field = $this->db->query("SHOW FULL COLUMNS FROM `{$table}` WHERE Field = 'decision'")->getRow();
        if (!$field) {
            throw new RuntimeException('Gate pass approval decision column is missing.');
        }
        $type = (string) $field->Type;
        if (preg_match('/^(?:var)?char\(/i', $type) || in_array(strtolower($type), ['text', 'mediumtext', 'longtext'], true)) {
            return;
        }
        if (!str_starts_with(strtolower($type), 'enum(') || !str_ends_with($type, ')')) {
            throw new RuntimeException('Unsupported gate pass approval decision column type.');
        }
        if (str_contains($type, "'fee_waiver_rejected'")) {
            return;
        }
        $definition = substr($type, 0, -1) . ",'fee_waiver_rejected')";
        if (!empty($field->Collation) && preg_match('/^[a-zA-Z0-9_]+$/D', $field->Collation)) {
            $definition .= ' COLLATE ' . $field->Collation;
        }
        $definition .= $field->Null === 'YES' ? ' NULL' : ' NOT NULL';
        if ($field->Default !== null) {
            $definition .= ' DEFAULT ' . $this->db->escape($field->Default);
        } elseif ($field->Null === 'YES') {
            $definition .= ' DEFAULT NULL';
        }
        $definition .= ' COMMENT ' . $this->db->escape((string) ($field->Comment ?? ''));
        $this->db->query("ALTER TABLE `{$table}` MODIFY COLUMN `decision` {$definition}");
    }

    public function down()
    {
        // Retain the extra value: shrinking this enum could destroy audit history.
    }
}
