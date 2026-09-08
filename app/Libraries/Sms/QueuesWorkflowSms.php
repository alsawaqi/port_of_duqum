<?php

namespace App\Libraries\Sms;

/** Keep the state transition and its notification in the same database transaction. */
trait QueuesWorkflowSms
{
    public function ci_save($data = [], $id = 0)
    {
        if (!get_setting('sms_notifications_enabled')) {
            return parent::ci_save($data, $id);
        }
        $this->db->transBegin();
        try {
            $before = $id ? ($this->db->query("SELECT * FROM {$this->table} WHERE id=? FOR UPDATE", [(int) $id])->getRowArray() ?? []) : [];
            $saved = parent::ci_save($data, $id);
            if (!$saved) { $this->db->transRollback(); return $saved; }
            $recordId = (int) ($id ?: $saved);
            $after = $this->db->table($this->table)->where('id', $recordId)->get()->getRowArray();
            if ($after) {
                (new WorkflowSmsOutbox($this->db))->capture($this->table_without_prefix, $before, $after);
            }
            if ($this->db->transStatus() === false) { throw new \RuntimeException('SMS outbox transaction failed.'); }
            $this->db->transCommit();
            return $saved;
        } catch (\Throwable $e) {
            $this->db->transRollback();
            // Never log SQL, gateway URLs, phone numbers, or credentials.
            log_message('error', 'Workflow notification could not be recorded; transition rolled back.');
            throw $e;
        }
    }
}
