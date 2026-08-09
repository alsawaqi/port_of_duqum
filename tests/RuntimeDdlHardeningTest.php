<?php

$root = dirname(__DIR__);

$fail = static function (string $message): void {
    fwrite(STDERR, 'Assertion failed: ' . $message . PHP_EOL);
    exit(1);
};

$runtimeRoots = [
    $root . '/app/Models',
    $root . '/app/Controllers',
    $root . '/app/Libraries',
];

$ddlPatterns = [
    '/\bCREATE\s+(?:TEMPORARY\s+)?TABLE\b/is' => 'table creation',
    '/\bALTER\s+TABLE\b/is' => 'table alteration',
    '/\bDROP\s+TABLE\b/is' => 'table removal',
    '/\bTRUNCATE\s+TABLE\b/is' => 'table truncation',
    '/\bRENAME\s+TABLE\b/is' => 'table rename',
    '/\b(?:CREATE|DROP)\s+INDEX\b/is' => 'index mutation',
    '/\b(?:CREATE|ALTER|DROP)\s+(?:DATABASE|SCHEMA|VIEW)\b/is' => 'database object mutation',
    '/\bAUTO_INCREMENT\s*=/is' => 'storage-engine sequence mutation',
];

foreach ($runtimeRoots as $runtimeRoot) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($runtimeRoot, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
            continue;
        }

        $path = $file->getPathname();
        if (str_contains(str_replace('\\', '/', $path), '/ThirdParty/')) {
            continue;
        }

        $source = (string) file_get_contents($path);
        foreach ($ddlPatterns as $pattern => $description) {
            if (preg_match($pattern, $source, $match, PREG_OFFSET_CAPTURE)) {
                $offset = $match[0][1];
                $line = substr_count(substr($source, 0, $offset), "\n") + 1;
                $relative = str_replace('\\', '/', substr($path, strlen($root) + 1));
                $fail("runtime {$description} remains in {$relative}:{$line}");
            }
        }
    }
}

$guardPath = $root . '/app/Libraries/Runtime_schema_guard.php';
$migrationPath = $root . '/app/Database/Migrations/2026_08_03_150000_runtime_schema_ownership_hardening.php';
$sqlPath = $root . '/app/Database/SQL/runtime_schema_ownership_hardening_upgrade_pod.sql';

foreach ([$guardPath, $migrationPath, $sqlPath] as $requiredPath) {
    if (!is_file($requiredPath)) {
        $fail(str_replace('\\', '/', substr($requiredPath, strlen($root) + 1)) . ' must exist');
    }
}

$guard = (string) file_get_contents($guardPath);
if (!str_contains($guard, 'throw new RuntimeException')) {
    $fail('runtime schema readiness checks must fail closed');
}
if (!str_contains($guard, 'requireTablesAndColumns') || !str_contains($guard, 'requireColumnProperties')) {
    $fail('runtime schema readiness checks must validate tables, fields, and compatibility properties');
}

$migration = (string) file_get_contents($migrationPath);
$manualSql = (string) file_get_contents($sqlPath);
foreach (
    [
        'gate_pass_blocked_visitors',
        'terminal_approval_required',
        'tender_target_vendors',
        'tender_fee_payments',
        'tender_workflow_history',
        'tender_communication_attachments',
        'tender_evaluation_attachments',
        'tender_bid_item_prices',
        'tender_rfq_details',
        'tender_rfq_items',
    ] as $ownedStructure
) {
    if (!str_contains($migration, $ownedStructure)) {
        $fail('deployment migration is missing ownership for ' . $ownedStructure);
    }
}

if (!str_contains($manualSql, '@pod_tender_request_compatibility_columns')
    || !str_contains($manualSql, 'information_schema.COLUMNS')) {
    $fail('manual runtime SQL must guard the optional tender-request compatibility backfill');
}

foreach (
    [
        'Contracts_model.php' => 'save_initial_number_of_contract',
        'Estimates_model.php' => 'save_initial_number_of_estimate',
        'Invoices_model.php' => 'save_initial_number_of_invoice',
        'Orders_model.php' => 'save_initial_number_of_order',
        'Proposals_model.php' => 'save_initial_number_of_proposal',
        'Subscriptions_model.php' => 'save_initial_number_of_subscription',
    ] as $model => $method
) {
    $source = (string) file_get_contents($root . '/app/Models/' . $model);
    if (!str_contains($source, $method) || !str_contains($source, 'database-managed')) {
        $fail($model . ' must retain a non-DDL compatibility path for initial-number settings');
    }
}

echo 'Runtime DDL hardening contracts passed.' . PHP_EOL;
