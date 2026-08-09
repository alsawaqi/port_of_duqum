<?php

$root = dirname(__DIR__);
$sqlDirectory = $root . '/app/Database/SQL';
$combinedPath = $sqlDirectory . '/current_release_security_upgrade_combined_pod.sql';

$fail = static function (string $message): void {
    fwrite(STDERR, 'Assertion failed: ' . $message . PHP_EOL);
    exit(1);
};

$normalize = static fn (string $sql): string => str_replace(["\r\n", "\r"], "\n", $sql);

$sourceFiles = [
    'vendor_multi_cr_contact_identities_upgrade_pod.sql',
    'vendor_contact_credentials_readiness_upgrade_pod.sql',
    'authentication_hardening_upgrade_pod.sql',
    'gate_pass_scan_replay_protection_upgrade_pod.sql',
    'vendor_portal_role_enforcement_upgrade_pod.sql',
    'notification_processor_hardening_upgrade_pod.sql',
    'ptw_company_scope_upgrade_pod.sql',
    'tender_opening_secret_hardening_upgrade_pod.sql',
    'ptw_applicant_company_assignments_upgrade_pod.sql',
    'runtime_schema_ownership_hardening_upgrade_pod.sql',
];

if (!is_file($combinedPath)) {
    $fail('combined manual security SQL must exist');
}

$combined = $normalize((string) file_get_contents($combinedPath));
$previousPosition = -1;

if (!str_contains($combined, 'Max number of result') || !str_contains($combined, 'at least 250')) {
    $fail('combined SQL must warn about the MySQL Workbench result-set limit');
}

foreach ($sourceFiles as $index => $sourceFile) {
    $sourcePath = $sqlDirectory . '/' . $sourceFile;
    if (!is_file($sourcePath)) {
        $fail($sourceFile . ' must exist');
    }

    $ordinal = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
    $beginMarker = "BEGIN {$ordinal}/10 {$sourceFile}";
    $completeMarker = "COMPLETE {$ordinal}/10 {$sourceFile}";
    $position = strpos($combined, $beginMarker);

    if ($position === false || $position <= $previousPosition) {
        $fail($sourceFile . ' must appear once in deployment order');
    }
    if (substr_count($combined, $beginMarker) !== 1 || substr_count($combined, $completeMarker) !== 1) {
        $fail($sourceFile . ' must have exactly one begin and completion marker');
    }

    $source = rtrim($normalize((string) file_get_contents($sourcePath)));
    if ($source === '' || substr_count($combined, $source) !== 1) {
        $fail($sourceFile . ' content must be included exactly once without modification');
    }

    $expectedHash = strtoupper(hash('sha256', $source . "\n"));
    if (!str_contains($combined, "{$sourceFile}: {$expectedHash}")) {
        $fail($sourceFile . ' SHA-256 evidence must match its normalized content');
    }

    $previousPosition = $position;
}

$safeSave = strpos($combined, 'SET @pod_combined_previous_sql_safe_updates := @@SESSION.SQL_SAFE_UPDATES;');
$safeDisable = strpos($combined, 'SET SESSION SQL_SAFE_UPDATES = 0;', $safeSave === false ? 0 : $safeSave);
$safeRestore = strrpos($combined, 'SET SESSION SQL_SAFE_UPDATES = @pod_combined_previous_sql_safe_updates;');

if ($safeSave === false || $safeDisable === false || $safeRestore === false
    || !($safeSave < $safeDisable && $safeDisable < $safeRestore)) {
    $fail('combined SQL must save, disable, and finally restore SQL_SAFE_UPDATES');
}

$preflightStart = strpos($combined, 'SET @pod_combined_required_table_count :=');
$preflightEnd = strpos($combined, 'SET @pod_combined_preflight_sql :=');
if ($preflightStart === false || $preflightEnd === false || $preflightStart >= $preflightEnd) {
    $fail('combined SQL must validate its database foundation before mutation');
}

$preflight = substr($combined, $preflightStart, $preflightEnd - $preflightStart);
foreach (
    [
        'pod_users',
        'pod_vendors',
        'pod_vendor_contacts',
        'pod_vendor_users',
        'pod_vendor_roles',
        'pod_gate_passes',
        'pod_gate_pass_scan_log',
        'pod_companies',
        'pod_ptw_applications',
        'pod_tender_bid_openings',
        'pod_tender_bid_opening_entries',
        'pod_gate_pass_request_vehicles',
        'pod_ptw_requirement_responses',
        'pod_ptw_attachments',
        'pod_tenders',
        'pod_tender_target_specialties',
        'pod_tender_invited_vendors',
        'pod_tender_communications',
        'pod_tender_evaluations',
    ] as $requiredTable
) {
    if (!str_contains($preflight, "'{$requiredTable}'")) {
        $fail('combined preflight must require ' . $requiredTable);
    }
}

if (!str_contains($preflight, "COLUMN_NAME = 'qr_token'")
    || !str_contains($preflight, "COLUMN_NAME = 'gate_pass_request_visitor_id'")) {
    $fail('combined preflight must validate the gate-pass QR foundation');
}

$vendorPrelude = strpos($combined, '-- Section 01 uses these legacy vendor fields.');
$firstSection = strpos($combined, 'BEGIN 01/10 vendor_multi_cr_contact_identities_upgrade_pod.sql');
if ($vendorPrelude === false || $firstSection === false || $vendorPrelude >= $firstSection) {
    $fail('vendor compatibility columns must be declared before the multi-CR section');
}

foreach (
    [
        'vendor_multi_cr_contact_identities_upgrade_pod.sql' => '@pod_vendor_identity_previous_sql_safe_updates',
        'gate_pass_scan_replay_protection_upgrade_pod.sql' => '@pod_gate_qr_previous_sql_safe_updates',
        'vendor_portal_role_enforcement_upgrade_pod.sql' => '@pod_vendor_roles_previous_sql_safe_updates',
        'ptw_company_scope_upgrade_pod.sql' => '@pod_ptw_scope_previous_sql_safe_updates',
        'runtime_schema_ownership_hardening_upgrade_pod.sql' => '@pod_runtime_previous_sql_safe_updates',
    ] as $sourceFile => $safeVariable
) {
    $source = (string) file_get_contents($sqlDirectory . '/' . $sourceFile);
    if (!str_contains($source, "SET {$safeVariable} := @@SESSION.SQL_SAFE_UPDATES;")
        || !str_contains($source, 'SET SESSION SQL_SAFE_UPDATES = 0;')
        || !str_contains($source, "SET SESSION SQL_SAFE_UPDATES = {$safeVariable};")) {
        $fail($sourceFile . ' must be safe-update compatible when run individually');
    }
}

foreach (['pod_legacy_invoice_payment_attempts', 'pod_stripe_ipn', 'pod_paypal_ipn'] as $deferredPaymentObject) {
    if (str_contains($combined, $deferredPaymentObject)) {
        $fail('deferred payment SQL must not be included: ' . $deferredPaymentObject);
    }
}

foreach (['IBULK', 'TWILIO', 'AUTH_SECURITY_MFA_IBULK_PASSWORD'] as $smsActivationMarker) {
    if (stripos($combined, $smsActivationMarker) !== false) {
        $fail('SMS activation must not be included: ' . $smsActivationMarker);
    }
}

if (!str_contains($combined, '__ABORT_WRONG_DATABASE_OR_REQUIRED_FOUNDATION_MISSING__')
    || !str_contains($combined, '__ABORT_GATE_PASS_QR_REISSUE_REQUIRED__')
    || !str_contains($combined, 'Combined security database upgrade completed')) {
    $fail('combined SQL must retain target, QR, and completion safeguards');
}

echo 'Combined manual security SQL contracts passed.' . PHP_EOL;
