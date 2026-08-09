<?php

$migration = file_get_contents(
    __DIR__ . "/../app/Database/Migrations/2026_08_03_000001_vendor_contact_credentials_readiness.php"
);
$upgradeSql = file_get_contents(
    __DIR__ . "/../app/Database/SQL/vendor_contact_credentials_readiness_upgrade_pod.sql"
);

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

$assertContains(
    'credentials_ready_at',
    $migration,
    "the forward migration tracks when vendor credentials become usable"
);
$assertContains(
    'DATETIME NULL DEFAULT NULL',
    $migration,
    "credential readiness is nullable until account approval"
);
$assertContains(
    'ensureNormalizedLiveContactEmailUniqueness',
    $migration,
    "the forward migration verifies the existing duplicate boundary"
);
$assertContains(
    'LOWER(TRIM(`email`))',
    $migration,
    "contact email identity is case- and surrounding-space normalized"
);
$assertContains(
    'ADD UNIQUE INDEX `" . self::CONTACT_EMAIL_IDENTITY_INDEX',
    $migration,
    "the migration installs the stable unique index when it is absent"
);
$assertContains(
    '["vendor_id", self::CONTACT_EMAIL_IDENTITY_COLUMN]',
    $migration,
    "the migration requires the exact CR/email index order"
);
$assertContains(
    'Duplicate live contact emails exist within one or more CRs',
    $migration,
    "legacy duplicates stop rather than silently weaken the migration"
);
$assertNotContains(
    'DROP INDEX `" . self::CONTACT_EMAIL_IDENTITY_INDEX',
    $migration,
    "the forward migration never drops the email security index"
);
$assertNotContains(
    'DROP COLUMN `" . self::CONTACT_EMAIL_IDENTITY_COLUMN',
    $migration,
    "the forward migration never drops the normalized email identity"
);

$assertContains(
    'ADD COLUMN `credentials_ready_at` DATETIME NULL DEFAULT NULL',
    $upgradeSql,
    "manual upgrades add the same nullable readiness timestamp"
);
$assertContains(
    'SECURITY_ERROR_same_cr_contact_email_duplicates',
    $upgradeSql,
    "manual upgrades fail closed when normalized duplicates exist"
);
$assertNotContains(
    'SIGNAL SQLSTATE',
    $upgradeSql,
    "manual upgrades avoid MariaDB's unsupported prepared SIGNAL statement"
);
$assertContains(
    '--abort-source-on-error',
    $upgradeSql,
    "manual CLI upgrades document fail-fast source execution"
);
$assertContains(
    "GROUP BY `vendor_id`, LOWER(TRIM(`email`))",
    $upgradeSql,
    "manual upgrades detect normalized duplicates within each CR"
);
$assertContains(
    "GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') = 'vendor_id,live_email_identity'",
    $upgradeSql,
    "manual upgrades verify the exact unique-index shape"
);
$assertContains(
    'ADD UNIQUE INDEX `uq_vendor_contacts_live_email` (`vendor_id`, `live_email_identity`)',
    $upgradeSql,
    "manual upgrades install the normalized CR/email unique index"
);
$assertNotContains(
    'DROP INDEX `uq_vendor_contacts_live_email`',
    $upgradeSql,
    "manual upgrades never remove the existing unique index"
);
$assertNotContains(
    'DROP COLUMN `live_email_identity`',
    $upgradeSql,
    "manual upgrades never remove the generated identity"
);

// Exercise the same generated-column/index behavior without touching the app
// database. SQLite supports the equivalent stored generated column and unique
// index, making this a fast behavioral contract rather than a source-only test.
if (!extension_loaded("pdo_sqlite")) {
    $fail("pdo_sqlite is required for the contact uniqueness behavior test");
}

$db = new PDO("sqlite::memory:");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec(
    "CREATE TABLE vendor_contacts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        vendor_id INTEGER NOT NULL,
        email TEXT,
        deleted INTEGER NOT NULL DEFAULT 0,
        live_email_identity TEXT GENERATED ALWAYS AS (
            CASE WHEN deleted = 0 THEN NULLIF(LOWER(TRIM(email)), '') ELSE NULL END
        ) STORED
    )"
);
$db->exec(
    "CREATE UNIQUE INDEX uq_vendor_contacts_live_email
     ON vendor_contacts (vendor_id, live_email_identity)"
);

$insert = $db->prepare(
    "INSERT INTO vendor_contacts (vendor_id, email, deleted) VALUES (?, ?, ?)"
);
$insert->execute([101, "  Person@Example.COM  ", 0]);

$sameCrDuplicateBlocked = false;
try {
    $insert->execute([101, "person@example.com", 0]);
} catch (PDOException $exception) {
    $sameCrDuplicateBlocked = true;
}
$assertTrue(
    $sameCrDuplicateBlocked,
    "case/space variants of one live email are blocked inside the same CR"
);

$insert->execute([202, "person@example.com", 0]);
$assertTrue(
    (int) $db->query("SELECT COUNT(*) FROM vendor_contacts WHERE deleted = 0")->fetchColumn() === 2,
    "the same normalized login email remains valid under a different CR"
);

$db->exec("UPDATE vendor_contacts SET deleted = 1 WHERE vendor_id = 101");
$insert->execute([101, "PERSON@example.com", 0]);
$assertTrue(
    (int) $db->query("SELECT COUNT(*) FROM vendor_contacts WHERE vendor_id = 101")->fetchColumn() === 2,
    "soft-deleted history does not prevent a replacement contact in the same CR"
);

echo "Vendor contact security schema contracts passed." . PHP_EOL;
