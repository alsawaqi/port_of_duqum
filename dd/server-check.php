<?php

// Hosting support can run this without starting the website or sending anything.
// Never expose configuration diagnostics over HTTP.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('zend.exception_ignore_args', '1');
error_reporting(E_ALL);
chdir(__DIR__);

$failures = 0;
$report = static function (bool $ok, string $label, string $help = '') use (&$failures): void {
    echo ($ok ? '[OK] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$ok) {
        $failures++;
        if ($help !== '') { echo '       ' . $help . PHP_EOL; }
    }
};

echo 'Port of Duqm server check (CLI only, no SMS or bank requests)' . PHP_EOL;
$report(version_compare(PHP_VERSION, '8.2', '>='), 'PHP ' . PHP_VERSION . '; minimum 8.2, migration target 8.3', 'Select PHP 8.3 for both the website and scheduled jobs.');
echo 'PHP configuration: ' . (php_ini_loaded_file() ?: 'No php.ini loaded') . PHP_EOL;
echo 'The website PHP-FPM/Apache configuration can differ from this CLI configuration.' . PHP_EOL;

foreach (['intl', 'mbstring', 'mysqli', 'mysqlnd', 'curl', 'openssl', 'fileinfo', 'gd', 'zlib', 'zip', 'dom', 'simplexml', 'xml', 'xmlreader', 'xmlwriter', 'iconv', 'ctype', 'bcmath', 'session', 'json'] as $extension) {
    $report(extension_loaded($extension), 'Extension: ' . $extension, 'Enable this extension in the PHP 8.3 configuration used by the website.');
}
foreach (['app/Config/Paths.php', 'app/Config/Database.php', 'app/Config/activated_plugins.json', 'system/Boot.php', 'system/CodeIgniter.php', 'app/Config/Routes.php', 'app/Helpers/safe_serialization_helper.php'] as $file) {
    $report(is_readable(__DIR__ . '/' . $file), 'Required file: ' . $file, 'Upload the complete project, preserving filename case.');
}
foreach (['writable', 'writable/logs', 'writable/cache', 'files', 'files/temp'] as $directory) {
    $report(is_dir(__DIR__ . '/' . $directory) && is_writable(__DIR__ . '/' . $directory), 'Writable directory: ' . $directory, 'Give the website PHP user ownership/write access; do not make the entire project world-writable.');
}
$report(is_readable(__DIR__ . '/.env'), 'Environment file is present', 'Include the hidden .env file, with the new HTTPS base URL and the existing encryption keys.');

if ($failures > 0) {
    echo 'Fix the requirements above before checking application configuration.' . PHP_EOL;
    exit(1);
}

$stage = 'framework bootstrap';
try {
    define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);
    require FCPATH . 'app/Config/Paths.php';
    require FCPATH . 'system/Boot.php';
    class ServerCheckBootstrap extends \CodeIgniter\Boot
    {
        public static function prepare(): void
        {
            $paths = new \Config\Paths();
            static::definePathConstants($paths);
            static::loadConstants();
            static::loadDotEnv($paths);
            static::defineEnvironment();
            static::loadCommonFunctions();
            static::loadAutoloader();
        }
    }
    ServerCheckBootstrap::prepare();
    $report(version_compare(\CodeIgniter\CodeIgniter::CI_VERSION, '4.7.4', '>='), 'Framework security baseline: ' . \CodeIgniter\CodeIgniter::CI_VERSION);
    $report(ENVIRONMENT === 'production', 'Environment: ' . ENVIRONMENT, 'On the destination server set CI_ENVIRONMENT = production in .env. Keep localhost in development.');

    $stage = 'application configuration (HTTPS base URL and existing app encryption key)';
    $app = new \Config\App();
    $report(true, 'Application configuration loads');
    $stage = 'database configuration (dedicated user/password and TLS for remote MySQL)';
    $database = new \Config\Database();
    $report(true, 'Database configuration loads; credentials are not printed');

    if (in_array('--database', $argv, true)) {
        $stage = 'database connection';
        $db = db_connect($database->default, false);
        $db->query('SELECT 1');
        $report(true, 'Read-only database connection');
        foreach (['users', 'settings', 'ci_sessions', 'eservice_payments', 'eservice_payment_events', 'sms_outbox', 'auth_mfa_challenges'] as $table) {
            $report($db->tableExists($table), 'Database table: ' . $db->prefixTable($table), 'Import the complete existing database, including structure and data.');
        }
        $db->close();
    } else {
        echo '[SKIP] Database connection: run php server-check.php --database for read-only checks.' . PHP_EOL;
    }
} catch (\Throwable $error) {
    // Arbitrary exception messages may contain SQL or secrets: do not print them.
    $report(false, 'Could not complete ' . $stage, 'Check the matching server settings. Error type: ' . get_class($error) . '. No credentials are printed.');
}

echo $failures ? 'Server checks need attention.' . PHP_EOL : 'Server checks passed. Confirm the same extensions in the website PHP runtime.' . PHP_EOL;
exit($failures ? 1 : 0);
