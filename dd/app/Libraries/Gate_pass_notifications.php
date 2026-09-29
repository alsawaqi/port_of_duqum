<?php

namespace App\Libraries;

use App\Libraries\Auth\OmanMobileNumber;
use App\Libraries\Sms\IsmartSmsGateway;

/** Durable notifications. Queue in the business transaction; dispatch only after commit. */
final class Gate_pass_notifications
{
    public function __construct(private $db = null) { $this->db = $db ?? db_connect(); }

    /** Check before a business write so an incomplete deployment cannot poison its transaction. */
    public function assertReady(): void
    {
        if (!$this->db->tableExists('gate_pass_notification_outbox')) {
            throw new \DomainException('gate_pass_notification_setup_required');
        }
    }

    private function enqueue(string $event, int $requestId, string $channel, string $destination,
        string $subject, string $message, ?int $userId = null, ?int $visitorId = null): void
    {
        $destination = $channel === 'email' ? strtolower(trim($destination)) : (OmanMobileNumber::normalize($destination) ?? '');
        $valid = $channel === 'email' ? (bool) filter_var($destination, FILTER_VALIDATE_EMAIL) : $destination !== '';
        $key = hash('sha256', $event . '|' . $channel . '|' . $destination);
        $table = $this->db->prefixTable('gate_pass_notification_outbox');
        $this->db->query("INSERT INTO {$table} (event_key,request_id,visitor_id,recipient_user_id,channel,destination,subject,message,status,is_preview,created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE event_key=VALUES(event_key)",
            [$key,$requestId,$visitorId,$userId,$channel,$destination,$subject,$message,$valid?'queued':'invalid_destination',
                $channel === 'sms' && !get_setting('sms_live_notifications') ? 1 : 0]);
    }

    public function submitted(object $request): void
    {
        $this->assertReady();
        $users = $this->db->prefixTable('users');
        $assignments = $this->db->prefixTable('gate_pass_department_users');
        $recipients = $this->db->query("SELECT DISTINCT u.id,u.email FROM {$users} u
            WHERE u.deleted=0 AND u.status='active' AND u.disable_login=0
              AND (u.id=? OR EXISTS (SELECT 1 FROM {$assignments} d WHERE d.user_id=u.id
                AND d.company_id=? AND d.department_id=? AND d.status='active' AND d.deleted=0))",
            [(int)$request->requester_id,(int)$request->company_id,(int)$request->department_id])->getResult();
        $event = 'submitted:' . $request->id . ':' . bin2hex(random_bytes(16));
        $ref = (string) $request->reference;
        foreach ($recipients as $user) {
            $text = (int)$user->id === (int)$request->requester_id
                ? "Your Gate Pass request {$ref} has been submitted. Sign in to track its progress."
                : "Gate Pass request {$ref} has been submitted to your department. Sign in to review it.";
            $this->enqueue($event, (int)$request->id, 'email', (string)$user->email, 'Gate Pass request submitted - ' . $ref,
                $text, (int)$user->id);
        }
        if (!array_filter($recipients, static fn($u) => (int)$u->id !== (int)$request->requester_id)) { log_message('error', 'No separate department reviewer email recipient for Gate Pass request ' . (int)$request->id); }
        if (!$recipients) { log_message('error', 'No active email recipients for Gate Pass request ' . (int)$request->id); }
    }

    public function blocked(object $block): void
    {
        $this->assertReady();
        $visitors = $this->db->table('gate_pass_request_visitors')->where('deleted',0)->get()->getResult();
        $seenRequests = [];
        $event = 'blocked:' . $block->id . ':' . bin2hex(random_bytes(16));
        foreach ($visitors as $visitor) {
            $normalized = preg_replace('/[\s\-]+/', '', strtoupper(trim((string)$visitor->id_number)));
            if ($normalized !== $block->normalized_id_number) { continue; }
            $request = $this->db->table('gate_pass_requests')->where('id',(int)$visitor->gate_pass_request_id)->where('deleted',0)->get()->getRow();
            if (!$request) { continue; }
            $text = 'Port of Duqm: your Gate Pass visitor access has been blocked. You cannot use or download the pass. Contact the requester or port security for assistance.';
            $this->enqueue($event, (int)$request->id, 'sms', (string)$visitor->phone, 'Gate Pass access blocked', $text, null, (int)$visitor->id);
            if (isset($seenRequests[$request->id])) { continue; }
            $seenRequests[$request->id] = true;
            $user = $this->db->table('users')->where('id',(int)$request->requester_id)->where('deleted',0)->where('status','active')->where('disable_login',0)->get()->getRow();
            if ($user) {
                $this->enqueue($event . ':' . $request->id, (int)$request->id, 'email', (string)$user->email,
                    'Gate Pass visitor blocked - ' . $request->reference,
                    'A visitor on Gate Pass request ' . $request->reference . ' has been blocked. Sign in to review the request. Their QR and PDF downloads are unavailable.', (int)$user->id);
            }
        }
    }

    /** Atomic claiming prevents web and scheduled workers sending the same row twice. */
    public function process(int $limit = 20, int $requestId = 0, ?callable $mail = null, ?callable $sms = null): int
    {
        $table = $this->db->prefixTable('gate_pass_notification_outbox');
        if (!$this->db->tableExists('gate_pass_notification_outbox')) { return 0; }
        $this->db->query("UPDATE {$table} SET status='unknown',processed_at=UTC_TIMESTAMP() WHERE status='processing' AND claimed_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 10 MINUTE)");
        $query = $this->db->table($table)->where('status','queued');
        if ($requestId) { $query->where('request_id',$requestId); }
        $rows = $query->orderBy('id')->limit(max(1,min($limit,100)))->get()->getResult();
        foreach ($rows as $row) {
            $this->db->query("UPDATE {$table} SET status='processing',claimed_at=UTC_TIMESTAMP() WHERE id=? AND status='queued'",[$row->id]);
            if ($this->db->affectedRows() !== 1) { continue; }
            $status = 'failed';
            try {
                if (strtotime($row->created_at . ' UTC') < time() - 86400) { $status = 'expired'; }
                elseif ($row->recipient_user_id) {
                    $user = $this->db->table('users')->where('id',(int)$row->recipient_user_id)->where('deleted',0)->where('status','active')->where('disable_login',0)->get()->getRow();
                    if (!$user || strtolower(trim((string)$user->email)) !== $row->destination) {
                        $status = 'recipient_changed';
                    } else {
                        $ok = $mail ? $mail($row->destination,$row->subject,$row->message)
                            : send_app_mail($row->destination,$row->subject,nl2br(esc($row->message)));
                        $status = $ok ? 'sent' : 'failed';
                    }
                } elseif ($row->channel === 'sms') {
                    $visitor = $this->db->table('gate_pass_request_visitors')->where('id',(int)$row->visitor_id)->where('deleted',0)->get()->getRow();
                    if (!$visitor || !(new Gate_pass_eligibility($this->db))->isBlocked($visitor)
                        || OmanMobileNumber::normalize((string)$visitor->phone) !== $row->destination) { $status = 'recipient_changed'; }
                    elseif (!get_setting('sms_notifications_enabled') || !get_setting('sms_gate_pass_enabled')) { $status = 'disabled'; }
                    elseif ($row->is_preview || !get_setting('sms_live_notifications')) { $status = 'dry_run'; }
                    else {
                        $result = $sms ? $sms($row->destination,$row->message) : (new IsmartSmsGateway())->send($row->destination,$row->message);
                        $status = ($result['status'] ?? '') === 'accepted' ? 'sent' : (($result['status'] ?? '') === 'unknown' ? 'unknown' : 'failed');
                    }
                }
            } catch (\Throwable $e) { $status = 'failed'; }
            $this->db->table($table)->where('id',$row->id)->update(['status'=>$status,'processed_at'=>gmdate('Y-m-d H:i:s')]);
            if ($status === 'failed') { log_message('error','Gate Pass notification delivery failed; outbox ID ' . (int)$row->id); }
        }
        return count($rows);
    }
}
