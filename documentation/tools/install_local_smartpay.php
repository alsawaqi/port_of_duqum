<?php

declare(strict_types=1);

// Targeted, additive XAMPP upgrade. Never runs unrelated pending migrations.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('FCPATH', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
chdir(FCPATH);
require FCPATH . 'app/Config/Paths.php';
$paths = new Config\Paths();
require $paths->systemDirectory . '/Boot.php';
final class LocalPaymentBootstrap extends CodeIgniter\Boot
{
    public static function load(Config\Paths $paths): void
    {
        static::definePathConstants($paths);
        static::loadConstants();
        static::loadDotEnv($paths);
        static::defineEnvironment();
        static::loadCommonFunctions();
        static::loadAutoloader();
    }
}
$db = null;
$locked = false;
try {
    LocalPaymentBootstrap::load($paths);
    $settings = (new Config\Database())->default;
    if (!in_array(strtolower((string) $settings['hostname']), ['localhost', '127.0.0.1', '::1'], true)
        || ($settings['DBDriver'] ?? '') !== 'MySQLi' || !empty($settings['DSN'])
        || (int) $settings['port'] !== 3306 || $settings['database'] !== 'bedotscpanel_poderp'
        || $settings['DBPrefix'] !== 'pod_' || ENVIRONMENT === 'production') {
        throw new RuntimeException('This installer is restricted to the local XAMPP application database.');
    }
    $db = Config\Database::connect($settings, false);
    $migrations = [
        ['2026_08_03_070000', 'eservice_payment_integrity', 'Eservice_payment_integrity'],
        ['2026_09_05_100000', 'bank_muscat_payment_accounting', 'Bank_muscat_payment_accounting'],
        ['2026_09_05_110000', 'vendor_fee_requests', 'Vendor_fee_requests'],
    ];
    $apply = in_array('--apply', $argv, true);
    echo json_encode(['mode' => $apply ? 'apply' : 'preview', 'host' => $settings['hostname'],
        'port' => 3306, 'database' => $settings['database'], 'prefix' => $settings['DBPrefix'],
        'migrations' => array_column($migrations, 1)], JSON_PRETTY_PRINT) . PHP_EOL;
    if (!$apply) { exit(0); }
    $locked = (int) $db->query("SELECT GET_LOCK('pod_local_smartpay_upgrade', 0) AS acquired")->getRow()->acquired === 1;
    if (!$locked) { throw new RuntimeException('Another local payment upgrade is running.'); }
    $runner = new CodeIgniter\Database\MigrationRunner(config('Migrations'), $db);
    $runner->ensureTable();
    $batch = (int) ($db->table('migrations')->selectMax('batch')->get()->getRow()->batch ?? 0) + 1;
    foreach ($migrations as [$version, $file, $shortClass]) {
        $class = 'App\\Database\\Migrations\\' . $shortClass;
        $exists = $db->table('migrations')->where(['version' => $version, 'class' => $class, 'namespace' => 'App', 'group' => 'default'])->countAllResults();
        if ($exists) { echo $file . ': already recorded; skipped.' . PHP_EOL; continue; }
        require_once APPPATH . 'Database/Migrations/' . $version . '_' . $file . '.php';
        (new $class(Config\Database::forge($db)))->up();
        if (!$db->table('migrations')->insert(['version' => $version, 'class' => $class,
            'namespace' => 'App', 'group' => 'default', 'time' => time(), 'batch' => $batch])) {
            throw new RuntimeException('The migration history could not be recorded.');
        }
        echo $file . ': applied and recorded.' . PHP_EOL;
    }
    foreach (['eservice_payments', 'eservice_payment_events', 'vendor_fee_requests'] as $table) {
        echo $db->prefixTable($table) . ': ' . $db->table($table)->countAllResults() . ' rows.' . PHP_EOL;
    }
    echo 'Payment schema installed. No fees, users or historical paid flags were changed.' . PHP_EOL;
} catch (Throwable $error) {
    // Do not print driver messages, configuration or connection credentials.
    fwrite(STDERR, 'Payment schema installation stopped: ' . get_class($error) . ' (code ' . $error->getCode() . '). Check the local configuration and migration schema.' . PHP_EOL);
    exit(1);
} finally {
    if ($db && $locked) { $db->query("SELECT RELEASE_LOCK('pod_local_smartpay_upgrade')"); }
}
