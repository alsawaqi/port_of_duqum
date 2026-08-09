<?php

if (!function_exists('env')) {
    function env(string $key, $default = null)
    {
        $value = getenv($key);
        return $value === false ? $default : $value;
    }
}

if (!defined('ENVIRONMENT')) {
    define('ENVIRONMENT', 'testing');
}

$root = dirname(__DIR__);
require_once $root . '/app/Libraries/TenderOpeningCodeVaultException.php';
require_once $root . '/app/Libraries/TenderOpeningCodeVault.php';

use App\Libraries\TenderOpeningCodeVault;
use App\Libraries\TenderOpeningCodeVaultException;

$read = static function (string $path) use ($root): string {
    $full = $root . '/' . $path;
    return is_file($full) ? (string) file_get_contents($full) : '';
};

$assertTrue = static function ($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        exit(1);
    }
};

$assertContains = static function (string $needle, string $haystack, string $message) use ($assertTrue): void {
    $assertTrue(strpos($haystack, $needle) !== false, $message);
};

$assertNotContains = static function (string $needle, string $haystack, string $message) use ($assertTrue): void {
    $assertTrue(strpos($haystack, $needle) === false, $message);
};

$assertThrows = static function (callable $callback, string $message) use ($assertTrue): void {
    $thrown = false;
    try {
        $callback();
    } catch (TenderOpeningCodeVaultException $e) {
        $thrown = true;
    }
    $assertTrue($thrown, $message);
};

$hmacEnv = TenderOpeningCodeVault::HMAC_KEY_ENV;
$encryptionEnv = TenderOpeningCodeVault::ENCRYPTION_KEY_ENV;
$hmacMaterial = random_bytes(32);
$encryptionMaterial = random_bytes(32);
putenv($hmacEnv . '=base64:' . base64_encode($hmacMaterial));
putenv($encryptionEnv . '=base64:' . base64_encode($encryptionMaterial));

$vault = new TenderOpeningCodeVault();
$vault->assertReady();
$code = $vault->generateCode();
$context = 'opening:17:tender:45:stage:technical:role:chairman';
$sealed = $vault->seal($code, $context);

$assertTrue(preg_match('/^[0-9]{6}$/D', $code) === 1, 'generated role codes must contain exactly six digits');
$assertTrue(preg_match('/^[a-f0-9]{64}$/D', $sealed['hash']) === 1, 'verification value must be a SHA-256 HMAC');
$assertTrue(strpos($sealed['ciphertext'], 'v1.') === 0, 'recoverable code must use the versioned authenticated envelope');
$assertTrue($sealed['ciphertext'] !== $code, 'recoverable value must not equal plaintext');
$assertTrue($vault->verify($code, $sealed['hash'], $context), 'correct role code should verify');
$assertTrue(
    !$vault->verify($code, $sealed['hash'], 'opening:17:tender:45:stage:technical:role:secretary'),
    'a role HMAC must not verify under another role context'
);
$assertTrue(!$vault->verify('99999', $sealed['hash'], $context), 'malformed role code should fail verification');
$assertTrue(!$vault->verify('000000', str_repeat('0', 64), $context), 'incorrect role code and hash should fail verification');
$assertTrue($vault->reveal($sealed['ciphertext'], $context) === $code, 'assigned role should recover its authenticated short-lived code');

$tampered = explode('.', $sealed['ciphertext']);
$tampered[3][0] = $tampered[3][0] === 'A' ? 'B' : 'A';
$tamperedEnvelope = implode('.', $tampered);
$assertThrows(
    static fn() => $vault->reveal($tamperedEnvelope, $context),
    'authenticated ciphertext tampering must be rejected'
);
$assertThrows(
    static fn() => $vault->reveal($sealed['ciphertext'], 'opening:17:tender:45:stage:technical:role:secretary'),
    'a role code envelope must not decrypt under another role context'
);

putenv($hmacEnv . '=short');
putenv($encryptionEnv . '=also-short');
$assertThrows(
    static fn() => (new TenderOpeningCodeVault())->assertReady(),
    'missing or short deployment keys must fail closed'
);

putenv($hmacEnv . '=CHANGE_ME_INDEPENDENT_32_BYTE_RANDOM_SECRET');
putenv($encryptionEnv . '=CHANGE_ME_DIFFERENT_32_BYTE_RANDOM_SECRET');
$assertThrows(
    static fn() => (new TenderOpeningCodeVault())->assertReady(),
    'public example placeholder keys must fail closed even when long enough'
);

$sameMaterial = base64_encode(random_bytes(32));
putenv($hmacEnv . '=base64:' . $sameMaterial);
putenv($encryptionEnv . '=base64:' . $sameMaterial);
$assertThrows(
    static fn() => (new TenderOpeningCodeVault())->assertReady(),
    'HMAC and encryption keys must be independent'
);

$vaultSource = $read('app/Libraries/TenderOpeningCodeVault.php');
$modelSource = $read('app/Models/Tender_bid_openings_model.php');
$controllerSource = $read('app/Controllers/Tender_committee_opening_inbox.php');
$viewSource = $read('app/Views/tender_committee_opening_inbox/modal_form.php');
$migrationSource = $read('app/Database/Migrations/2026_08_03_110000_tender_opening_secret_hardening.php');
$manualSql = $read('app/Database/SQL/tender_opening_secret_hardening_upgrade_pod.sql');
$documentation = $read('documentation/TENDER_3KEY_SECRET_HARDENING.md');
$environmentExample = $read('.env.example');
$cronSource = $read('app/Controllers/Cron.php');

$assertContains('hash_hmac(', $vaultSource, 'vault must verify codes with a keyed HMAC');
$assertContains('hash_equals(', $vaultSource, 'vault must use timing-safe comparison');
$assertContains('aes-256-gcm', $vaultSource, 'vault must use authenticated encryption for role display');
$assertContains('MINIMUM_KEY_BYTES = 32', $vaultSource, 'vault must enforce 32-byte deployment keys');
$assertContains('getRoleCodeForDisplay', $modelSource, 'model must retrieve only the assigned role code');
$assertContains('confirmRoleCode', $modelSource, 'model must expose role-specific atomic confirmation');
$assertContains('INNER JOIN $team_members', $modelSource, 'role-code display must revalidate the active committee assignment');
$assertContains('AND team_role=?', $modelSource, 'role confirmation must be bound to the assigned committee role');
$assertContains('FOR UPDATE', $modelSource, 'confirmation and unlock must serialize on the opening row');
$assertContains("status IN ('unlocked','signed','manual_accepted')", $modelSource, 'terminal openings must prevent code regeneration');
$assertContains('confirmation attempt', $modelSource, 'any confirmation attempt must prevent active-session regeneration');
$assertContains('CONFIRMATION_USER_FAILURE_LIMIT = 5', $modelSource, 'failed confirmations must be limited per user');
$assertContains('CONFIRMATION_IP_FAILURE_LIMIT = 20', $modelSource, 'failed confirmations must be limited per IP');
$assertContains('count($distinct_users)', $modelSource, 'unlock must require distinct committee users');
$assertContains('chairman_code_hash=NULL', $modelSource, 'terminal session transitions must scrub protected secrets');
$assertContains('stripOpeningSecrets', $modelSource, 'general session reads must not expose protected fields');
$assertNotContains('SELECT *', $modelSource, 'opening reads must explicitly exclude protected secret columns');
$assertNotContains('$entries.*', $modelSource, 'confirmation rows must not be returned with legacy plaintext input fields');
$assertNotContains('ALTER TABLE', $modelSource, 'normal web requests must not execute tender-opening DDL');
$assertNotContains('input_chairman_code=NULL', $modelSource, 'one-time legacy scrubbing must stay in the migration');
$assertNotContains("'MANUAL', 'MANUAL', 'MANUAL'", $modelSource, 'manual opening flow must not persist code sentinels');

$confirmationInsert = '';
if (preg_match('/table\\(\\$entries\\)->insert\\(\\[(.*?)\\]\\);/s', $modelSource, $matches) === 1) {
    $confirmationInsert = $matches[1];
}
$assertTrue($confirmationInsert !== '', 'test must locate the confirmation audit insert');
$assertNotContains('input_chairman_code', $confirmationInsert, 'confirmation insert must omit chairman plaintext input');
$assertNotContains('input_secretary_code', $confirmationInsert, 'confirmation insert must omit secretary plaintext input');
$assertNotContains('input_member_code', $confirmationInsert, 'confirmation insert must omit member plaintext input');

$assertContains('confirmRoleCode(', $controllerSource, 'controller must delegate timing-safe role confirmation to the model');
$assertContains('setStatusCode(429)', $controllerSource, 'controller must return HTTP 429 when confirmation is limited');
$assertContains('setStatusCode(409)', $controllerSource, 'controller must refuse unsafe session regeneration');
$assertContains('Cache-Control", "private, no-store"', $controllerSource, 'role code response must prohibit caching');
$assertNotContains('getPost("chairman_code")', $controllerSource, 'controller must not accept all three plaintext codes');
$assertNotContains('$session->chairman_code', $controllerSource, 'controller must not read plaintext session secrets');
$assertContains('name="opening_code"', $viewSource, 'modal must submit one assigned-role code');
$assertNotContains('name="chairman_code"', $viewSource, 'modal must not request the chairman code from every member');
$assertNotContains('name="secretary_code"', $viewSource, 'modal must not request the secretary code from every member');
$assertNotContains('name="member_code"', $viewSource, 'modal must not request the member code from every member');

$assertContains('chairman_code_ciphertext', $migrationSource, 'migration must add authenticated ciphertext fields');
$assertContains('input_chairman_code=NULL', $migrationSource, 'migration must scrub legacy submitted plaintext');
$assertContains('newer.id>older.id', $migrationSource, 'migration must expire older duplicate generated sessions');
$assertContains('newer.`id` > older.`id`', $manualSql, 'manual SQL must expire older duplicate generated sessions');
$assertContains('idx_tender_opening_failure_user', $migrationSource, 'migration must support user rate-limit lookup');
$assertContains('idx_tender_opening_secret_expiry', $migrationSource, 'migration must index scheduled secret expiry cleanup');
$assertContains('idx_tender_opening_failure_ip', $manualSql, 'manual SQL must support IP rate-limit lookup');
$assertContains('TENDER_OPENING_CODE_HMAC_KEY', $manualSql, 'manual SQL must identify required HMAC environment secret');
$assertContains('@pod_previous_sql_safe_updates', $manualSql, 'manual SQL restores the operator session safe-update setting');
$assertContains('information_schema.COLUMNS', $manualSql, 'manual SQL checks for optional legacy input-code columns');
$assertContains('Never put the real values', $documentation, 'deployment guide must prohibit committed key material');
$assertContains('There is no previous-key fallback', $documentation, 'deployment guide must explain safe key rotation');
$assertContains('expire_old_sessions()', $cronSource, 'signed Cron must physically scrub expired opening secrets');
$assertContains(
    'TENDER_OPENING_CODE_HMAC_KEY = "CHANGE_ME"',
    $environmentExample,
    'sample tender key must be intentionally too short for production'
);
$assertNotContains(
    'TENDER_OPENING_CODE_HMAC_KEY = "CHANGE_ME_INDEPENDENT_32_BYTE_RANDOM_SECRET"',
    $environmentExample,
    'sample tender HMAC key must not be a length-valid public placeholder'
);

echo 'OK' . PHP_EOL;
