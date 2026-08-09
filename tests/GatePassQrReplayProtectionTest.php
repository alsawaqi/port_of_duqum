<?php

$root = dirname(__DIR__);
$read = static fn(string $path): string => (string)file_get_contents($root . '/' . $path);
$controller = $read('app/Controllers/Gate_pass_security_inbox.php');
$recorder = $read('app/Libraries/Gate_pass_scan_recorder.php');
$authorizer = $read('app/Libraries/Gate_pass_scan_authorizer.php');
$migration = $read('app/Database/Migrations/2026_08_03_060000_gate_pass_scan_replay_protection.php');
$manualSql = $read('app/Database/SQL/gate_pass_scan_replay_protection_upgrade_pod.sql');
$view = $read('app/Views/gate_pass_security_inbox/scan.php');
$model = $read('app/Models/Gate_passes_model.php');

$fail = static function (string $message): void {
    fwrite(STDERR, 'Assertion failed: ' . $message . PHP_EOL);
    exit(1);
};
$contains = static function (string $needle, string $text, string $message) use ($fail): void {
    if (!str_contains($text, $needle)) {
        $fail($message . ' Missing: ' . $needle);
    }
};

$contains('random_bytes(32)', $model, 'QR tokens use a cryptographic random source');
$contains("'uq_gate_passes_qr_token'", $migration, 'QR tokens receive a unique database index');
$contains("'idx_gate_pass_scan_movement_lock'", $migration, 'movement lookups receive a lockable index');
$contains('__ABORT_GATE_PASS_QR_REISSUE_REQUIRED__', $manualSql, 'manual SQL fails closed when QR values require secure reissuance');
$contains('uq_gate_passes_qr_token', $manualSql, 'manual SQL creates the QR uniqueness guard');
$contains('idx_gate_pass_scan_movement_lock', $manualSql, 'manual SQL creates the movement-lock index');
$contains('Portable SQL cannot safely', $manualSql, 'manual SQL documents why weak database randomness is rejected');

$contains('Gate_pass_scan_authorizer->issue', $controller, 'QR lookup issues a short-lived scan proof');
$contains('Gate_pass_scan_authorizer->consume', $controller, 'scan writes consume the one-time proof');
$contains('required|exact_length[64]|alpha_numeric', $controller, 'scan proof format is validated');
$contains('required|in_list[entry,exit,check]', $controller, 'unknown actions are rejected');
$contains('Gate_pass_scan_recorder->record', $controller, 'controller delegates atomic recording');

$contains("LIMIT 1 FOR UPDATE", $recorder, 'pass and movement state are locked');
$contains("gate_pass_request_visitor_id <=> ?", $recorder, 'request-level and visitor-level state are isolated');
$contains('gate_pass_scan_transition', $recorder, 'entry and exit replay rules are enforced');
$contains("\$action === 'entry'", $recorder, 'entry actions enforce visitor block status');
$contains('transBegin()', $recorder, 'scan recording begins a transaction');
$contains('transCommit()', $recorder, 'successful scan recording commits atomically');
$contains('transRollback()', $recorder, 'invalid or failed scan recording rolls back');

$contains("'nonce_hash' => hash('sha256', \$nonce)", $authorizer, 'only a nonce hash is kept in the session');
$contains('hash_equals', $authorizer, 'nonce comparison is timing safe');
$contains('unset($pending[$key])', $authorizer, 'nonce is one-time even after a failed comparison');

$contains('scan_nonce: currentScanNonce', $view, 'browser submits the lookup proof with the action');
$contains('currentScanNonce = ""', $view, 'browser discards the proof after use');
$contains('$("#btn_save_action").prop("disabled", true)', $view, 'browser requires another QR scan after an action');

echo 'Gate-pass QR replay protection contracts passed.' . PHP_EOL;
