<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Makes QR values unique and gives SELECT ... FOR UPDATE an exact movement
 * range to lock while enforcing entry/exit ordering.
 */
class Gate_pass_scan_replay_protection extends Migration
{
    private const QR_INDEX = 'uq_gate_passes_qr_token';
    private const MOVEMENT_INDEX = 'idx_gate_pass_scan_movement_lock';

    public function up()
    {
        $passes = $this->db->prefixTable('gate_passes');
        $logs = $this->db->prefixTable('gate_pass_scan_log');

        if ($this->db->tableExists($passes) && $this->db->fieldExists('qr_token', $passes)) {
            $this->repairQrTokens($passes);
            $this->db->query("ALTER TABLE `{$passes}` MODIFY COLUMN `qr_token` VARCHAR(64) NOT NULL");
            if (!$this->indexExists($passes, self::QR_INDEX)) {
                $this->db->query(
                    "ALTER TABLE `{$passes}` ADD UNIQUE INDEX `" . self::QR_INDEX . "` (`qr_token`)"
                );
            }
        }

        if ($this->db->tableExists($logs)
            && $this->db->fieldExists('gate_pass_id', $logs)
            && $this->db->fieldExists('gate_pass_request_visitor_id', $logs)
            && !$this->indexExists($logs, self::MOVEMENT_INDEX)
        ) {
            $this->db->query(
                "ALTER TABLE `{$logs}` ADD INDEX `" . self::MOVEMENT_INDEX
                . "` (`gate_pass_id`, `gate_pass_request_visitor_id`, `action`, `recorded_at`, `id`)"
            );
        }
    }

    public function down()
    {
        $passes = $this->db->prefixTable('gate_passes');
        $logs = $this->db->prefixTable('gate_pass_scan_log');
        if ($this->db->tableExists($passes) && $this->indexExists($passes, self::QR_INDEX)) {
            $this->db->query("ALTER TABLE `{$passes}` DROP INDEX `" . self::QR_INDEX . "`");
        }
        if ($this->db->tableExists($logs) && $this->indexExists($logs, self::MOVEMENT_INDEX)) {
            $this->db->query("ALTER TABLE `{$logs}` DROP INDEX `" . self::MOVEMENT_INDEX . "`");
        }
    }

    private function repairQrTokens(string $table): void
    {
        $rows = $this->db->query("SELECT `id`, `qr_token` FROM `{$table}` ORDER BY `id` ASC FOR UPDATE")->getResult();
        $seen = [];
        $rotated = 0;

        foreach ($rows as $row) {
            $token = strtolower(trim((string)($row->qr_token ?? '')));
            $valid = preg_match('/^[a-f0-9]{64}$/', $token) === 1;
            if ($valid && !isset($seen[$token])) {
                $seen[$token] = true;
                if ((string)$row->qr_token !== $token) {
                    $this->db->table($table)->where('id', (int)$row->id)->update(['qr_token' => $token]);
                }
                continue;
            }

            do {
                $token = bin2hex(random_bytes(32));
            } while (isset($seen[$token]));

            $this->db->table($table)->where('id', (int)$row->id)->update(['qr_token' => $token]);
            $seen[$token] = true;
            $rotated++;
        }

        if ($rotated > 0) {
            log_message('warning', 'Rotated {count} invalid or duplicate gate-pass QR token(s); affected passes must be reissued.', [
                'count' => $rotated,
            ]);
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return (bool)$this->db->query(
            'SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
            [$this->db->database, $table, $index]
        )->getRow();
    }
}
