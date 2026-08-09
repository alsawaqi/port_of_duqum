<?php

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $contents = file_get_contents($root . "/" . $path);
    if ($contents === false) {
        fwrite(STDERR, "Unable to read {$path}." . PHP_EOL);
        exit(1);
    }
    return $contents;
};

$fail = static function (string $message): void {
    fwrite(STDERR, "Assertion failed: {$message}" . PHP_EOL);
    exit(1);
};
$assertTrue = static function ($condition, string $message) use ($fail): void {
    if ($condition !== true) {
        $fail($message);
    }
};
$assertContains = static function (string $needle, string $haystack, string $message) use ($fail): void {
    if (strpos($haystack, $needle) === false) {
        $fail($message . " Missing: " . $needle);
    }
};
$assertNotContains = static function (string $needle, string $haystack, string $message) use ($fail): void {
    if (strpos($haystack, $needle) !== false) {
        $fail($message . " Unexpected: " . $needle);
    }
};

$signin = $read("app/Controllers/Signin.php");
$users = $read("app/Models/Users_model.php");
$verification = $read("app/Models/Verification_model.php");
$authModel = $read("app/Models/Auth_security_model.php");
$teamMembers = $read("app/Controllers/Team_members.php");
$clients = $read("app/Controllers/Clients.php");
$clientLibrary = $read("app/Libraries/Client.php");
$securityController = $read("app/Controllers/Security_Controller.php");
$signup = $read("app/Controllers/Signup.php");
$gatePassVisitors = $read("app/Controllers/Gate_pass_visitors.php");
$guestGatePass = $read("app/Controllers/Guest_gate_pass.php");
$leads = $read("app/Controllers/Leads.php");
$store = $read("app/Controllers/Store.php");
$vendorContactAccess = $read("app/Libraries/Vendor_contact_access.php");
$config = $read("app/Config/AuthSecurity.php");
$migration = $read("app/Database/Migrations/2026_08_03_010000_authentication_hardening.php");
$sql = $read("app/Database/SQL/authentication_hardening_upgrade_pod.sql");

// Reset credentials: random, hashed at rest, user-ID-bound, expiring, and
// consumed inside the same transaction as the password update.
$assertContains('bin2hex(random_bytes(32))', $verification, "reset validators use a CSPRNG");
$assertContains('"validator_hash" => hash("sha256", $validator)', $verification, "only the reset validator digest is stored");
$assertContains('"user_id" => $userId', $verification, "reset tokens bind to immutable user IDs");
$assertContains('FOR UPDATE', $verification, "one-time credentials are transaction locked");
$assertContains('auth_session_version = auth_session_version + 1', $verification, "reset revokes older sessions");
$assertContains('find_password_reset_user($email)', $signin, "reset resolves one unambiguous account");
$assertContains('password_reset_identity_{$identityHash}', $signin, "reset requests are identity throttled");
$assertContains('password_reset_ip_{$ipHash}', $signin, "reset requests are IP throttled");
$assertContains('return $this->_reset_request_response();', $signin, "reset requests use one generic response");
$assertNotContains('make_random_string()', $signin, "reset no longer uses the legacy short random value");
$assertNotContains('unserialize($verification_info->params)', $signin, "reset no longer trusts serialized email parameters");
$assertNotContains('update_password($email', $signin, "reset never changes every row sharing an email");

// Password policy and password-change authorization.
$assertContains('password_policy_errors($password)', $teamMembers, "staff registration and changes use the shared policy");
$assertContains('password_policy_errors($password)', $clients, "client registration and changes use the shared policy");
$assertNotContains(
    '$password = clean_data($password);',
    $clientLibrary,
    "plaintext passwords are never HTML-sanitized into a different secret"
);
foreach ([
    "operational staff assignments" => $securityController,
    "invitation signup" => $signup,
    "managed gate-pass visitors" => $gatePassVisitors,
    "guest gate-pass registration" => $guestGatePass,
    "lead-to-client conversion" => $leads,
    "store checkout registration" => $store,
    "vendor contact credentials" => $vendorContactAccess,
] as $flow => $source) {
    $assertContains(
        'password_policy_errors(',
        $source,
        "{$flow} uses the shared server-side password policy"
    );
}
foreach ([$signup, $gatePassVisitors, $guestGatePass, $leads, $store, $vendorContactAccess] as $source) {
    $assertNotContains(
        'clean_data($password)',
        $source,
        "password plaintext is not passed through clean_data"
    );
    $assertNotContains(
        'clean_data($initialPassword)',
        $source,
        "initial password plaintext is not passed through clean_data"
    );
}
$assertContains('verify_user_password($user_id, $currentPassword)', $teamMembers, "staff self-change verifies current password");
$assertContains('verify_user_password($user_id, $currentPassword)', $clients, "client self-change verifies current password");
$assertContains('array_key_exists("password", $data)', $users, "all model password writes trigger session invalidation");
$assertContains('auth_session_version', $users, "protected requests compare the session version");
$assertContains('_rehash_verified_password_if_needed', $users, "verified legacy hashes are migrated");
$assertContains('password_needs_rehash', $users, "older modern hashes are upgraded too");
$assertContains('count($matches) !== 1', $users, "legacy email password updates fail on ambiguity");

// Persistent lockout and audit events.
$assertContains('login_lock_status($identity_hash)', $signin, "login checks the persistent account lock");
$assertContains('record_login_failure(', $signin, "login failures persist beyond one PHP process");
$assertContains('auth_audit_events', $authModel, "authentication events have a dedicated audit store");
$assertContains('identity_hash', $authModel, "audit can correlate attempts without raw login identities");
$assertNotContains("'password' =>", $authModel, "audit records never include passwords");

// MFA is opt-in, provider-backed, keyed at rest, expiring, and retry limited.
$assertContains('if (!$this->mfaEnabled) {', $config, 'production boot allows the current release with MFA disabled');
$assertContains('Enabled production MFA must include staff accounts.', $config, 'enabled production MFA still requires staff coverage');
$assertNotContains('Production requires MFA for staff accounts.', $config, 'deferred MFA must not block production boot');
$assertContains('public bool $mfaEnabled = false;', $config, "MFA remains off until configured");
$assertContains("public string \$mfaProvider = 'null';", $config, "MFA has a safe null provider default");
$assertContains("public string \$mfaHmacKey = '';", $config, "no MFA secret is committed");
$assertContains('MfaProviderFactory::make', $authModel, "MFA delivery is provider abstracted");
$assertContains('random_int(0, $upperBound)', $verification, "OTP generation uses a CSPRNG");
$assertContains('hash_hmac("sha256"', $verification, "OTP values use a keyed digest at rest");
$assertContains('"expires_at"', $verification, "MFA challenges expire");
$assertContains('$attempts >= $maximum', $verification, "MFA retries are bounded");
$assertContains('mfa_is_required($user_info)', $signin, "configured users enter the MFA challenge");
$assertContains('autocomplete" => "one-time-code', $read("app/Views/signin/mfa_challenge_form.php"), "MFA UI identifies OTP input semantics");

foreach ([
    'auth_login_security',
    'auth_password_reset_tokens',
    'auth_mfa_challenges',
    'auth_audit_events',
] as $table) {
    $assertContains($table, $migration, "migration creates {$table}");
    $assertContains("pod_{$table}", $sql, "manual SQL creates pod_{$table}");
}
$assertContains('auth_session_version', $migration, "migration adds session versioning");

require_once $root . "/app/Libraries/Auth/PasswordPolicy.php";
use App\Libraries\Auth\PasswordPolicy;

$assertTrue(
    PasswordPolicy::errors('Valid-Password1!', 10, 72) === [],
    "a policy-compliant password is accepted"
);
$assertTrue(
    count(PasswordPolicy::errors('short', 10, 72)) >= 3,
    "short low-complexity passwords are rejected"
);
$assertTrue(
    PasswordPolicy::errors(str_repeat('A', 73) . 'a1!', 10, 72) !== [],
    "passwords beyond the bcrypt-safe maximum are rejected"
);

echo "Phase 2 authentication hardening contracts passed." . PHP_EOL;
