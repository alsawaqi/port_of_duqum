<?php

// Opt-in behavior tests. Bootstrap a disposable, populated local QA database first.
// Injected email delivery never contacts SMTP. Every fixture is rolled back.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (getenv('POD_RUN_TENDER_WORKFLOW_DB_TESTS') !== '1') { echo "SKIP: opt-in local tender workflow database test\n"; exit; }
if (!defined('FCPATH') || !defined('ENVIRONMENT') || ENVIRONMENT === 'production') {
    throw new RuntimeException('A development test bootstrap is required.');
}
$db = db_connect();
if (!in_array($db->hostname, ['localhost', '127.0.0.1'], true)) { throw new RuntimeException('Local QA database only.'); }
config('Rise')->app_settings_array['sms_notifications_enabled'] = 0;
$db->transException(true);
$checks = 0;
$assert = static function ($condition, string $message) use (&$checks) {
    if (!$condition) { throw new RuntimeException($message); } $checks++;
};
$clone = static function (string $table, array $changes) use ($db): int {
    $row = $db->table($table)->get(1)->getRowArray(); unset($row['id']);
    foreach ($db->query('SHOW COLUMNS FROM `' . $db->prefixTable($table) . '`')->getResult() as $column) {
        if (str_contains($column->Extra, 'GENERATED')) { unset($row[$column->Field]); }
    }
    $db->table($table)->insert(array_replace($row, $changes)); return (int) $db->insertID();
};
$db->transBegin();
try {
    $stamp = bin2hex(random_bytes(5));
    $actor = (int) $db->table('users')->where('deleted', 0)->get(1)->getRow()->id;
    $vendors = [];
    foreach (['winner', 'loser', 'nonbidder'] as $name) {
        $vendors[$name] = $clone('vendors', ['vendor_name' => '<' . $name . '> QA', 'cr_number' => 'WF' . $stamp . $name,
            'vendor_code' => null, 'email' => $stamp . '-' . $name . '@example.invalid', 'status' => 'approved', 'deleted' => 0]);
    }
    $tid = $clone('tenders', ['reference' => 'WFTEST-' . $stamp, 'title' => '<script>QA & award</script>', 'tender_request_id' => null,
        'status' => 'awarded', 'workflow_stage' => 'award_decision', 'award_vendor_id' => $vendors['winner'], 'loa_reference' => 'LOA-' . $stamp, 'deleted' => 0]);
    foreach (['winner', 'loser'] as $name) {
        $clone('tender_bids', ['tender_id' => $tid, 'vendor_id' => $vendors[$name], 'status' => 'accepted', 'deleted' => 0]);
    }
    $clone('tender_invited_vendors', ['tender_id' => $tid, 'vendor_id' => $vendors['nonbidder'], 'deleted' => 0]);
    $clone('tender_bids', ['tender_id' => $tid, 'vendor_id' => $vendors['nonbidder'], 'status' => 'draft', 'deleted' => 0]);
    $model = new App\Models\Tender_communications_model();
    $tender = $db->table('tenders')->where('id', $tid)->get()->getRow();
    $sent = [];
    $letters = new App\Libraries\Tender_award_letters($db, static function ($to, $subject, $body, $options) use (&$sent) {
        $attachment = $options['attachments'][0] ?? null;
        $bytes = $attachment ? file_get_contents($attachment['file_path']) : null;
        $sent[] = compact('to', 'subject', 'body', 'attachment', 'bytes'); return true;
    });
    $letters->record($tender, $vendors['winner'], $actor, '2026-10-04 10:00:00');
    $rows = $db->table('tender_communications')->where('tender_id', $tid)->get()->getResult();
    $assert(count($rows) === 2, 'Only actual bidders receive result letters.');
    foreach ($rows as $row) {
        $assert($row->type === ((int) $row->vendor_id === $vendors['winner'] ? 'award_letter' : 'regret_letter'), 'Each bidder receives the correct result.');
        $assert($row->status === 'email_pending', 'Letters are durable before delivery.');
    }
    $result = $letters->deliver($tid);
    $assert($result === ['sent' => 2, 'failed' => 0], 'Two result emails delivered.');
    $assert(count($sent) === 2 && !str_contains($sent[0]['body'], '<script>'), 'Email content is escaped.');
    $bySubject = array_column($sent, null, 'subject');
    $regretEmail = $bySubject['Regret Letter - ' . $tender->reference];
    $awardEmail = $bySubject['Letter of Award - ' . $tender->reference];
    $assert(str_starts_with($regretEmail['bytes'] ?? '', '%PDF-'), 'Only the unsuccessful bidder receives the template PDF attachment.');
    $assert($awardEmail['attachment'] === null, 'The successful bidder does not receive a regret PDF.');
    $assert(str_contains($regretEmail['body'], 'Buthaina Al Zadjali') && str_contains($regretEmail['body'], 'After compliments,'), 'Supplied regret template wording is used.');
    $assert(str_contains($regretEmail['body'], '&lt;script&gt;') && !str_contains($regretEmail['body'], '<script>'), 'Regret email preserves HTML escaping.');
    $assert(!is_file($regretEmail['attachment']['file_path']), 'Temporary PDF is removed after successful delivery.');
    $assert(str_ends_with($regretEmail['attachment']['file_path'], '.pdf'), 'SMTP helper can detect PDF attachment MIME from the path.');
    $letters->deliver($tid);
    $assert(count($sent) === 2, 'Retry does not resend successful letters.');
    $db->table('tender_communications')->where('id', $rows[0]->id)->update(['status' => 'email_failed']);
    $result = (new App\Libraries\Tender_award_letters($db, static fn() => false))->deliver($tid);
    $assert($result === ['sent' => 0, 'failed' => 1], 'Failed delivery is recorded honestly.');
    $letters->deliver($tid);
    $assert(count($sent) === 3, 'Only the failed letter is retried.');
    $regretRow = array_values(array_filter($rows, static fn($row) => $row->type === 'regret_letter'))[0];
    $snapshot = $regretRow->message;
    $db->table('vendors')->where('id', $vendors['loser'])->update(['vendor_name' => 'Changed after award']);
    $db->table('tender_communications')->where('id', $regretRow->id)->update(['status' => 'email_failed']);
    $failedPath = null;
    $result = (new App\Libraries\Tender_award_letters($db, static function ($to, $subject, $body, $options) use (&$failedPath) {
        $failedPath = $options['attachments'][0]['file_path'];
        throw new RuntimeException('Simulated SMTP error');
    }))->deliver($tid);
    $assert($result === ['sent' => 0, 'failed' => 1] && $failedPath && !is_file($failedPath), 'SMTP failure records failure and removes its private temporary attachment.');
    $letters->deliver($tid);
    $last = end($sent);
    $assert(str_starts_with($last['bytes'], '%PDF-') && !str_contains($last['body'], 'Changed after award'), 'Retry attaches the original personalised letter snapshot.');
    $assert($db->table('tender_communications')->where('id', $regretRow->id)->get()->getRow()->message === $snapshot, 'Retry leaves the saved letter unchanged.');
    foreach (['winner', 'loser', 'nonbidder'] as $name) {
        $visible = $model->get_clarification_conversation($tid, $vendors[$name], true);
        $assert(count($visible) === ($name === 'nonbidder' ? 0 : 1), 'Vendor letter visibility: ' . $name);
        if ($visible) { $assert((int) $visible[0]->vendor_id === $vendors[$name], 'No other vendor letter leaks.'); }
    }
    $root = $model->ci_save(['tender_id' => $tid, 'vendor_id' => $vendors['winner'], 'type' => 'clarification',
        'clarification_scope' => 'technical', 'message' => 'Vendor question', 'is_vendor_visible' => 1, 'status' => 'open', 'created_by' => $actor, 'deleted' => 0]);
    $forward = $model->ci_save(['tender_id' => $tid, 'vendor_id' => $vendors['winner'], 'parent_id' => $root,
        'type' => 'technical_clarification_response', 'clarification_scope' => 'technical', 'internal_audience' => 'technical',
        'message' => 'Internal forward', 'is_vendor_visible' => 0, 'status' => 'forwarded_to_technical', 'created_by' => $actor, 'deleted' => 0]);
    $assert(count($model->get_internal_conversation($tid, 'technical')) === 1, 'Forwarded vendor root appears to intended team.');
    $assert(count($model->get_internal_conversation($tid, 'commercial')) === 0, 'Forwarded message remains isolated from the other team.');
    $visibleIds = array_map(static fn($row) => (int) $row->id, $model->get_clarification_conversation($tid, $vendors['winner'], true));
    $assert(!in_array((int) $forward, $visibleIds, true), 'Internal forwarding is hidden from vendor.');
    $assert(!$model->has_vendor_visible_evaluator_clarification_request($tid, $vendors['winner']), 'A normal pre-bid question does not open late clarification access.');
    $evaluator = $model->ci_save(['tender_id' => $tid, 'vendor_id' => $vendors['winner'], 'type' => 'commercial_clarification_request',
        'clarification_scope' => 'commercial', 'internal_audience' => 'commercial', 'message' => 'Evaluation question', 'is_vendor_visible' => 0,
        'status' => 'pending_procurement', 'created_by' => $actor, 'deleted' => 0]);
    $assert(!$model->has_vendor_visible_evaluator_clarification_request($tid, $vendors['winner']), 'An unrelayed internal request does not permit a late vendor reply.');
    $model->ci_save(['tender_id' => $tid, 'vendor_id' => $vendors['winner'], 'parent_id' => $evaluator, 'type' => 'response',
        'message' => 'Procurement relays question', 'is_vendor_visible' => 1, 'status' => 'published', 'created_by' => $actor, 'deleted' => 0]);
    $assert($model->has_vendor_visible_evaluator_clarification_request($tid, $vendors['winner']), 'Relayed evaluation question permits the targeted vendor to respond.');
    $assert(!$model->has_vendor_visible_evaluator_clarification_request($tid, $vendors['loser']), 'The other vendor has no late reply permission.');
    $questions = (new ReflectionClass(App\Controllers\Tender_clarifications::class))->newInstanceWithoutConstructor();
    (new ReflectionProperty($questions, 'db'))->setValue($questions, $db);
    $participates = new ReflectionMethod($questions, '_vendor_participates_in_tender');
    $db->table('tender_invited_vendors')->where('tender_id', $tid)->where('vendor_id', $vendors['nonbidder'])->update(['deleted' => 1]);
    $assert(!$participates->invoke($questions, $tid, $vendors['nonbidder']), 'An unrelated vendor cannot receive targeted replies.');
    $question = $model->ci_save(['tender_id' => $tid, 'vendor_id' => $vendors['nonbidder'], 'type' => 'clarification',
        'message' => 'Question before deciding to bid', 'is_vendor_visible' => 1, 'status' => 'open', 'created_by' => $actor, 'deleted' => 0]);
    $assert($participates->invoke($questions, $tid, $vendors['nonbidder']), 'Procurement can answer an existing pre-bid enquiry.');
    $recipients = (new ReflectionMethod($questions, '_get_tender_vendors'))->invoke($questions, $tid);
    $assert(in_array($vendors['nonbidder'], array_map(static fn($row) => (int) $row->id, $recipients), true), 'Pre-bid questioner appears in correspondence recipients.');
    $db->table('tender_communications')->where('id', $question)->update(['deleted' => 1]);
    $assert(!$participates->invoke($questions, $tid, $vendors['nonbidder']), 'Deleted enquiries do not grant correspondence access.');
    // Preview notification creation only: never run the SMS sender in these tests.
    config('Rise')->app_settings_array['sms_notifications_enabled'] = 1;
    config('Rise')->app_settings_array['sms_tender_enabled'] = 1;
    config('Rise')->app_settings_array['sms_live_notifications'] = 0;
    $userIds = [];
    foreach ($vendors as $name => $vendorId) {
        $uid = $clone('users', ['email' => $stamp . '-sms-' . $name . '@example.invalid', 'phone' => '9689000000' . count($userIds),
            'status' => 'active', 'disable_login' => 0, 'deleted' => 0]);
        $userIds[$name] = $uid;
        $clone('vendor_users', ['vendor_id' => $vendorId, 'user_id' => $uid, 'status' => 'active', 'deleted' => 0]);
    }
    $db->table('tender_invited_vendors')->where('tender_id', $tid)->where('vendor_id', $vendors['nonbidder'])->update(['deleted' => 0]);
    (new App\Libraries\Sms\WorkflowSmsOutbox($db))->capture('tenders', array_replace((array) $tender, ['status' => 'closed']), (array) $tender);
    $notifications = $db->table('sms_outbox')->where('source_table', 'tenders')->where('subject_id', $tid)->get()->getResultArray();
    $assert(count($notifications) === 2, 'Award SMS records target actual bidders only.');
    $byUser = array_column($notifications, 'reason', 'recipient_user_id');
    $assert(($byUser[$userIds['winner']] ?? '') === 'awarded' && ($byUser[$userIds['loser']] ?? '') === 'not_awarded', 'Winner and unsuccessful bidder receive the correct SMS outcome.');
    $assert(!isset($byUser[$userIds['nonbidder']]) && array_sum(array_column($notifications, 'is_preview')) === 2, 'Invited non-bidders receive no regret SMS; test messages remain previews.');
    config('Rise')->app_settings_array['sms_notifications_enabled'] = 0;
    $assert(App\Libraries\Tender_team_validation::error($db, 999999, ['technical' => [$actor]]) !== null, 'Wrong-company team assignments rejected.');
    $assert(App\Libraries\Tender_team_validation::error($db, 1, ['technical' => [99999999]]) !== null, 'Missing user assignments rejected.');
    $assert(!App\Libraries\Tender_testing_stage::enabled(false), 'Non-admins cannot use testing stages.');
    putenv('TENDER_TESTING_STAGE_ENABLED=false');
    $assert(!App\Libraries\Tender_testing_stage::enabled(true), 'Testing stages disabled by default for admins too.');
    echo "$checks tender workflow database behavior checks passed.\n";
} finally {
    $db->transRollback();
}
