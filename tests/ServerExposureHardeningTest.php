<?php

$projectRoot = dirname(__DIR__);
$htaccessPath = $projectRoot . "/.htaccess";
$developmentBootPath = $projectRoot . "/app/Config/Boot/development.php";
$productionBootPath = $projectRoot . "/app/Config/Boot/production.php";

$fail = static function (string $message): void {
    fwrite(STDERR, "Assertion failed: {$message}" . PHP_EOL);
    exit(1);
};

$assertContains = static function (
    string $needle,
    string $haystack,
    string $message
) use ($fail): void {
    if (!str_contains($haystack, $needle)) {
        $fail($message . " Missing: " . $needle);
    }
};

$assertNotContains = static function (
    string $needle,
    string $haystack,
    string $message
) use ($fail): void {
    if (str_contains($haystack, $needle)) {
        $fail($message . " Unexpected: " . $needle);
    }
};

if (!is_file($htaccessPath)) {
    $fail("the project-root .htaccess hardening file must exist");
}

$htaccess = (string) file_get_contents($htaccessPath);
$developmentBoot = (string) file_get_contents($developmentBootPath);
$productionBoot = (string) file_get_contents($productionBootPath);

$assertContains("Options -Indexes", $htaccess, "Apache directory listings are disabled");
$assertContains(
    '<FilesMatch "(?i)^(?:\.env(?:\..*)?|\.htaccess|\.htpasswd|phpunit\.xml(?:\.dist)?|composer\.(?:json|lock)|spark|preload\.php)$">',
    $htaccess,
    "configuration and dependency metadata are denied before routing"
);
$assertContains(
    '<FilesMatch "(?i)\.(?:bak|conf|dist|ini|log|sql|sqlite|sqlite3|yml|yaml)$">',
    $htaccess,
    "backup, log, database, and configuration artifacts are denied"
);
if (substr_count($htaccess, "Require all denied") < 2) {
    $fail("Apache 2.4 denial rules must cover named and extension-based secrets");
}
if (substr_count($htaccess, "Deny from all") < 2) {
    $fail("legacy Apache denial rules must cover named and extension-based secrets");
}

$assertContains(
    'RewriteCond %{REQUEST_METHOD} ^TRACE$ [NC]',
    $htaccess,
    "HTTP TRACE is blocked"
);
$assertContains(
    'RewriteRule (^|/)\.(?!well-known(?:/|$)) - [F,L,NC]',
    $htaccess,
    "dotfiles and dot-directories are denied except for .well-known"
);
$assertContains(
    'RewriteCond %{REQUEST_URI} ^/(?:app|system|tests|writable|updates|documentation|vendor|node_modules|\.git|\.svn)(?:/|$) [NC]',
    $htaccess,
    "source, dependencies, tests, runtime data, and repository metadata are not web-accessible"
);

$assertContains('<IfModule php_module>', $htaccess, "mod_php hardening is guarded by its loaded module");
$assertContains('php_flag expose_php Off', $htaccess, "mod_php is instructed not to advertise its version");
$assertContains('Header unset X-Powered-By', $htaccess, "the normal PHP technology header is removed");
$assertContains('Header always unset X-Powered-By', $htaccess, "the PHP technology header is removed from every response");
$assertContains('Header unset X-Content-Type-Options', $htaccess, "the application MIME header is de-duplicated before the canonical header is set");
$assertContains('Header unset X-Frame-Options', $htaccess, "the application framing header is de-duplicated before the canonical header is set");
$assertContains('Header unset Referrer-Policy', $htaccess, "the application referrer header is de-duplicated before the canonical header is set");
$assertContains(
    'Header always set X-Content-Type-Options "nosniff"',
    $htaccess,
    "MIME sniffing is disabled"
);
$assertContains(
    'Header always set X-Frame-Options "SAMEORIGIN"',
    $htaccess,
    "cross-origin framing is denied"
);
$assertContains(
    'Header always set Referrer-Policy "strict-origin-when-cross-origin"',
    $htaccess,
    "referrer information is limited"
);

$debugOff = "defined('CI_DEBUG') || define('CI_DEBUG', false);";
$assertContains($debugOff, $developmentBoot, "development mode does not expose the debug toolbar");
$assertContains($debugOff, $productionBoot, "production mode keeps debugging disabled");
$assertNotContains("define('CI_DEBUG', true)", $developmentBoot, "development must not enable CI_DEBUG");
$assertNotContains("define('CI_DEBUG', true)", $productionBoot, "production must not enable CI_DEBUG");

echo "Server exposure hardening contracts passed." . PHP_EOL;
