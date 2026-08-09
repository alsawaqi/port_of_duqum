<?php

namespace App\Models;

/** Atomically records one-time notification processor request nonces. */
class Notification_processor_nonces_model extends Crud_model
{
    protected $table = null;

    public function __construct()
    {
        $this->table = 'notification_processor_nonces';
        parent::__construct($this->table);
    }

    public function claim(string $nonceHash, int $expiresAt): bool
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $nonceHash) !== 1
            || !$this->db->tableExists($this->table)
        ) {
            return false;
        }

        $table = $this->db->prefixTable($this->table);
        $now = gmdate('Y-m-d H:i:s');
        try {
            $this->db->table($table)->where('expires_at <', $now)->delete();
            $saved = $this->db->table($table)->insert([
                'nonce_hash' => $nonceHash,
                'expires_at' => gmdate('Y-m-d H:i:s', $expiresAt),
                'created_at' => $now,
            ]);
            return $saved && $this->db->affectedRows() === 1;
        } catch (\Throwable $e) {
            // A duplicate-key exception is the expected replay path. Database
            // or migration failures also fail closed rather than disabling it.
            return false;
        }
    }
}
