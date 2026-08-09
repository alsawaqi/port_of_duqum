<?php

$source = (string) file_get_contents(
    dirname(__DIR__) . '/app/Controllers/Gate_pass_rop_inbox.php'
);

$fail = static function (string $message): void {
    fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
    exit(1);
};
$contains = static function (string $needle, string $message) use ($source, $fail): void {
    if (strpos($source, $needle) === false) {
        $fail($message . ' Missing: ' . $needle);
    }
};

$contains('$db->transBegin()', 'final ROP approval must start an atomic transaction');
$contains('FOR UPDATE', 'request row must be locked before issuing passes');
$contains('$db->transRollback()', 'failed issuance must roll back all workflow writes');
$contains('$db->transStatus()', 'transaction write failures must be detected');
$contains('$db->transCommit()', 'request transition, approval, and pass issuance must commit together');

$lockPosition = strpos($source, 'FOR UPDATE');
$insertPosition = strpos($source, '$this->Gate_passes_model->ci_save($pass_data)');
if ($lockPosition === false || $insertPosition === false || $lockPosition > $insertPosition) {
    $fail('request lock must be acquired before a gate pass can be inserted');
}

echo 'Gate-pass issuance concurrency checks passed.' . PHP_EOL;
