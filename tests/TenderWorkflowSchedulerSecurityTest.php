<?php

$root = dirname(__DIR__);
$cron = (string) file_get_contents($root . '/app/Controllers/Cron.php');
$model = (string) file_get_contents($root . '/app/Models/Tenders_model.php');

$fail = static function (string $message): void {
    fwrite(STDERR, 'Assertion failed: ' . $message . PHP_EOL);
    exit(1);
};

if (!str_contains($cron, '(new Tenders_model())->auto_progress_workflow(true)')) {
    $fail('signed Cron must explicitly invoke tender workflow progression');
}
if (!str_contains($cron, '(new Tender_bid_openings_model())->expire_old_sessions()')) {
    $fail('signed Cron must physically scrub expired opening secrets');
}
if (!str_contains($model, 'auto_progress_workflow(bool $scheduledInvocation = false)')) {
    $fail('workflow mutation must default to a denied invocation');
}
if (!str_contains($model, 'if (!$scheduledInvocation)')) {
    $fail('workflow mutation must fail closed outside the scheduler');
}

$controllerFiles = glob($root . '/app/Controllers/*.php') ?: [];
foreach ($controllerFiles as $file) {
    if (basename($file) === 'Cron.php') {
        continue;
    }
    $source = (string) file_get_contents($file);
    if (str_contains($source, 'auto_progress_workflow(')) {
        $fail(basename($file) . ' mutates tender workflow outside signed Cron');
    }
}
if (str_contains($model, 'auto_close_expired_tenders')) {
    $fail('an alternate public scheduler bypass remains in the model');
}

echo 'Tender scheduler-only workflow contracts passed.' . PHP_EOL;
