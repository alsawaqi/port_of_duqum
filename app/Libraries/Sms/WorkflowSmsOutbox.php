<?php

namespace App\Libraries\Sms;

use App\Libraries\Auth\OmanMobileNumber;

final class WorkflowSmsOutbox
{
    private $db;
    public function __construct($db = null) { $this->db = $db ?? db_connect(); }

    public function capture(string $table, array $before, array $after): void
    {
        $event = WorkflowSmsPolicy::event($table, $before, $after);
        if (!$event || !get_setting('sms_notifications_enabled') || !get_setting('sms_' . $event['module'] . '_enabled')) { return; }
        $id = (int) $after['id'];
        $subjectId = $id;
        $reference = (string) ($after['reference'] ?? ($after['cr_number'] ?? '#' . $id));
        $recipients = [];
        if ($table === 'vendors') {
            $recipients = $this->vendorUsers($id);
        } elseif ($table === 'gate_pass_requests' || $table === 'ptw_applications' || $table === 'tender_requests') {
            $userId = (int) ($after['requester_id'] ?? $after['applicant_user_id'] ?? $after['created_by'] ?? 0);
            $recipients = $this->user($userId);
        } else {
            $subjectId = (int) ($after['tender_id'] ?? $id);
            $tender = $this->db->table('tenders')->where('id', $subjectId)->where('deleted', 0)->get()->getRowArray();
            if (!$tender) { return; }
            $reference = (string) ($tender['reference'] ?: '#' . $subjectId);
            $vendors = [];
            if (!empty($after['vendor_id'])) {
                $vendors = [(int) $after['vendor_id']];
            } else {
                $bids = $this->db->prefixTable('tender_bids');
                $invites = $this->db->prefixTable('tender_invited_vendors');
                $rows = $this->db->query("SELECT vendor_id FROM {$bids} WHERE tender_id=? AND deleted=0 AND status<>'draft' UNION SELECT vendor_id FROM {$invites} WHERE tender_id=? AND deleted=0", [$subjectId, $subjectId])->getResultArray();
                $vendors = array_map('intval', array_column($rows, 'vendor_id'));
            }
            foreach ($vendors as $vendorId) {
                $reason = $event['reason'];
                if ($reason === 'awarded' && $vendorId !== (int) $tender['award_vendor_id']) { $reason = 'not_awarded'; }
                foreach ($this->vendorUsers($vendorId) as $user) {
                    $user['reason'] = $reason;
                    $recipients[] = $user;
                }
            }
        }
        if (!$recipients) { $recipients = [['id' => 0, 'phone' => '', 'first_name' => 'No active recipient', 'last_name' => '']]; }
        $seen = [];
        $eventId = bin2hex(random_bytes(16));
        $language = (int) get_setting('sms_language') === 64 ? 64 : 0;
        foreach ($recipients as $user) {
            $reason = $user['reason'] ?? $event['reason'];
            $mobile = OmanMobileNumber::normalize((string) ($user['phone'] ?? ''));
            $key = ($mobile ?: 'user:' . $user['id']) . ':' . $reason;
            if (isset($seen[$key])) { continue; }
            $seen[$key] = true;
            $this->db->table('sms_outbox')->insert([
                'event_key' => hash('sha256', $eventId . ':' . $key), 'module' => $event['module'],
                'subject_id' => $subjectId, 'source_table' => $table, 'source_id' => $id,
                'reference' => mb_substr($reference, 0, 100), 'reason' => $reason,
                'recipient_user_id' => (int) $user['id'] ?: null,
                'recipient_name' => mb_substr(trim($user['first_name'] . ' ' . $user['last_name']), 0, 190),
                'mobile' => $mobile, 'message' => WorkflowSmsPolicy::message($event['module'], $reference, $reason, $language),
                'language' => $language, 'status' => !$user['id'] ? 'no_recipient' : ($mobile ? 'queued' : 'invalid_mobile'),
                // Preview/live mode is fixed at creation; turning on live SMS never releases old previews.
                'is_preview' => get_setting('sms_live_notifications') ? 0 : 1,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
        }
    }

    public function process(int $limit = 20): int
    {
        $gateway = new IsmartSmsGateway();
        $table = $this->db->prefixTable('sms_outbox');
        // A worker may have died after the provider accepted a send. Do not retry it automatically.
        $this->db->query("UPDATE {$table} SET status='unknown', processed_at=UTC_TIMESTAMP() WHERE status='processing' AND claimed_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 5 MINUTE)");
        $rows = $this->db->table('sms_outbox')->where('status', 'queued')->orderBy('id')->limit(max(1, min(100, $limit)))->get()->getResultArray();
        $count = 0;
        foreach ($rows as $row) {
            $this->db->query("UPDATE {$table} SET status='processing', claimed_at=UTC_TIMESTAMP() WHERE id=? AND status='queued'", [$row['id']]);
            if ($this->db->affectedRows() !== 1) { continue; }
            if (!get_setting('sms_notifications_enabled') || !get_setting('sms_' . $row['module'] . '_enabled')) {
                $result = IsmartSmsGateway::result('disabled');
            } elseif (strtotime($row['created_at'] . ' UTC') < time() - 86400) {
                $result = IsmartSmsGateway::result('expired');
            } elseif ($row['is_preview'] || !get_setting('sms_live_notifications')) {
                $result = IsmartSmsGateway::result('dry_run');
            } else {
                // Never send to an account disabled/deleted after the original notification was queued.
                $user = $this->user((int) $row['recipient_user_id']);
                if (!$this->recipientStillAllowed($row)) {
                    $result = IsmartSmsGateway::result('no_recipient');
                } elseif (!$user || OmanMobileNumber::normalize((string) $user[0]['phone']) !== $row['mobile']) {
                    $result = IsmartSmsGateway::result('invalid_mobile');
                } else {
                    $result = $gateway->send($row['mobile'], $row['message'], (int) $row['language']);
                }
            }
            $this->db->table('sms_outbox')->where('id', $row['id'])->update([
                'status' => $result['status'], 'provider_code' => $result['code'], 'processed_at' => gmdate('Y-m-d H:i:s'),
            ]);
            $count++;
        }
        return $count;
    }

    private function user(int $id): array
    {
        return $this->db->table('users')->select('id, first_name, last_name, phone')->where('id', $id)
            ->where('deleted', 0)->where('status', 'active')->where('disable_login', 0)->get()->getResultArray();
    }

    private function vendorUsers(int $vendorId): array
    {
        $u = $this->db->prefixTable('users');
        $v = $this->db->prefixTable('vendor_users');
        return $this->db->query("SELECT u.id,u.first_name,u.last_name,u.phone FROM {$u} u INNER JOIN {$v} v ON v.user_id=u.id WHERE v.vendor_id=? AND v.deleted=0 AND v.status='active' AND u.deleted=0 AND u.status='active' AND u.disable_login=0", [$vendorId])->getResultArray();
    }

    private function recipientStillAllowed(array $row): bool
    {
        if (!in_array($row['source_table'], WorkflowSmsPolicy::TABLES, true)) { return false; }
        $source = $this->db->table($row['source_table'])->where('id', $row['source_id'])->where('deleted', 0)->get()->getRowArray();
        if (!$source) { return false; }
        $userId = (int) $row['recipient_user_id'];
        if ($row['source_table'] === 'gate_pass_requests') { return (int) $source['requester_id'] === $userId; }
        if ($row['source_table'] === 'ptw_applications') { return (int) $source['applicant_user_id'] === $userId; }
        if ($row['source_table'] === 'tender_requests') { return (int) $source['created_by'] === $userId; }
        if ($row['source_table'] === 'tender_communications' && (!$source['is_vendor_visible'] || $source['status'] !== 'published')) { return false; }
        $links = $this->db->table('vendor_users')->select('vendor_id')->where('user_id', $userId)
            ->where('status', 'active')->where('deleted', 0)->get()->getResultArray();
        foreach ($links as $link) {
            $vendorId = (int) $link['vendor_id'];
            if ($row['source_table'] === 'vendors' && $vendorId === (int) $source['id']) { return true; }
            if ($row['source_table'] !== 'vendors') {
                if (!empty($source['vendor_id'])) { if ($vendorId === (int) $source['vendor_id']) { return true; } continue; }
                foreach (['tender_bids', 'tender_invited_vendors'] as $table) {
                    if ($this->db->table($table)->where('tender_id', $row['subject_id'])->where('vendor_id', $vendorId)->where('deleted', 0)->countAllResults()) { return true; }
                }
            }
        }
        return false;
    }
}
