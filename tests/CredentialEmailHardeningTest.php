<?php

$root = dirname(__DIR__);
$sources = [
    "app/Libraries/Client.php",
    "app/Controllers/Team_members.php",
    "app/Controllers/Clients.php",
    "app/Controllers/Signup.php",
    "app/Controllers/Store.php",
    "app/Controllers/Leads.php",
];

$credentialAssignment = '/\[\s*["\x27](?:USER|CONTACT)_LOGIN_PASSWORD["\x27]\s*\]\s*=\s*\$(?:password|user_password)\b/';
foreach ($sources as $source) {
    $contents = file_get_contents($root . "/" . $source);
    if ($contents === false) {
        fwrite(STDERR, "Assertion failed: unable to read {$source}." . PHP_EOL);
        exit(1);
    }

    if (preg_match($credentialAssignment, $contents)) {
        fwrite(STDERR, "Assertion failed: {$source} places a reusable password in email." . PHP_EOL);
        exit(1);
    }

    if (!str_contains($contents, 'app_lang("password_not_sent_by_email")')) {
        fwrite(STDERR, "Assertion failed: {$source} must render the non-secret credential notice." . PHP_EOL);
        exit(1);
    }
}

$english = file_get_contents($root . "/app/Language/english/custom_lang.php");
$arabic = file_get_contents($root . "/app/Language/arabic/custom_lang.php");
foreach (["English" => $english, "Arabic" => $arabic] as $language => $contents) {
    if (!is_string($contents) || !str_contains($contents, 'password_not_sent_by_email')) {
        fwrite(STDERR, "Assertion failed: {$language} credential-email notice is missing." . PHP_EOL);
        exit(1);
    }
}

echo "Credential email hardening contracts passed." . PHP_EOL;
