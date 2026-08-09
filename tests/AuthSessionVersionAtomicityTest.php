<?php

$root = dirname(__DIR__);
$users = (string) file_get_contents($root . '/app/Models/Users_model.php');

$fail = static function (string $message): void {
    fwrite(STDERR, 'Assertion failed: ' . $message . PHP_EOL);
    exit(1);
};

$contains = static function (string $needle, string $haystack, string $message) use ($fail): void {
    if (!str_contains($haystack, $needle)) {
        $fail($message . ' Missing: ' . $needle);
    }
};

$notContains = static function (string $needle, string $haystack, string $message) use ($fail): void {
    if (str_contains($haystack, $needle)) {
        $fail($message . ' Unexpected: ' . $needle);
    }
};

$slice = static function (string $start, string $end) use ($users, $fail): string {
    $startAt = strpos($users, $start);
    $endAt = $startAt === false ? false : strpos($users, $end, $startAt + strlen($start));
    if ($startAt === false || $endAt === false) {
        $fail('unable to isolate Users_model method source');
    }

    return substr($users, $startAt, $endAt - $startAt);
};

$save = $slice('function ci_save(', 'function authenticate(');
$contains('transBegin()', $save, 'password changes must begin a transaction');
$contains('LIMIT 1 FOR UPDATE', $save, 'the account version must be locked before incrementing');
$contains('$nextVersion = max(1, (int) ($row->auth_session_version ?? 0)) + 1;', $save, 'the locked value must increment exactly once');
$contains('$data["auth_session_version"] = $nextVersion;', $save, 'password and session version must be written together');
$contains('parent::ci_save($data, $userId)', $save, 'the password update must remain in the locked transaction');
$contains('transStatus()', $save, 'transaction write failures must be detected');
$contains('transRollback()', $save, 'failed password changes must roll back');
$contains('transCommit()', $save, 'successful password changes must commit atomically');
$contains('Password mutation denied because session-version storage is unavailable.', $save, 'missing version storage must fail closed');
$notContains('->select("auth_session_version")', $save, 'password changes must not use an unlocked read-then-write increment');

$lockAt = strpos($save, 'LIMIT 1 FOR UPDATE');
$writeAt = strpos($save, 'parent::ci_save($data, $userId)');
$commitAt = strpos($save, 'transCommit()');
$sessionAt = strpos($save, '$session->set("auth_session_version", $nextVersion)');
if (!($lockAt < $writeAt && $writeAt < $commitAt && $commitAt < $sessionAt)) {
    $fail('lock, write, commit, and retained-session update must occur in that order');
}

$startSession = $slice('function start_user_session(', 'function is_login_enabled(');
$contains('|| !$this->_supports_auth_session_version()', $startSession, 'new logins must fail closed until version storage exists');
$contains('$session->set(', $startSession, 'new authenticated sessions must receive a version');

$loginEnabled = $slice('function is_login_enabled(', 'private function _supports_auth_session_version(');
$contains('$isAuthenticatedSession && !$supportsSessionVersion', $loginEnabled, 'authenticated sessions fail closed without version storage');
$contains('if (!$session->has("auth_session_version")) {', $loginEnabled, 'legacy sessions without a version are detected');
$contains('return false;', $loginEnabled, 'invalid session versions are rejected');
$contains('ctype_digit($sessionVersion)', $loginEnabled, 'malformed session-version values are rejected');
$notContains('Adopt old sessions', $loginEnabled, 'legacy sessions must not silently adopt the database version');
$notContains('$session->set("auth_session_version", $currentVersion)', $loginEnabled, 'validation must never upgrade a legacy session in place');

echo 'Auth session-version atomicity contracts passed.' . PHP_EOL;
