<?php

// Run from a terminal on the destination server, never through the browser.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (!in_array($argv[1] ?? '', ['--check', '--apply'], true)) {
    fwrite(STDERR, "Usage: php scripts/gate-pass-waiver-schema.php --check|--apply\n");
    exit(2);
}
ini_set('display_errors', '0');
ini_set('zend.exception_ignore_args', '1');
chdir(dirname(__DIR__));
define('FCPATH', getcwd() . DIRECTORY_SEPARATOR);
require FCPATH . 'app/Config/Paths.php';
require FCPATH . 'system/Boot.php';

class GatePassWaiverSchemaBoot extends CodeIgniter\Boot
{
    public static function init(): void
    {
        $paths = new Config\Paths();
        static::definePathConstants($paths);
        static::loadConstants();
        static::loadDotEnv($paths);
        static::defineEnvironment();
        static::loadCommonFunctions();
        static::loadAutoloader();
    }
}

try {
    GatePassWaiverSchemaBoot::init();
    defined('CI_DEBUG') || define('CI_DEBUG', false);
    $db = db_connect();
    if ($argv[1] === '--apply') {
        require APPPATH . 'Database/Migrations/2026_09_13_180000_gate_pass_fee_waiver_decision.php';
        (new App\Database\Migrations\Gate_pass_fee_waiver_decision())->up();
    }
    $table = $db->prefixTable('gate_pass_request_approvals');
    $field = $db->query("SHOW COLUMNS FROM `{$table}` WHERE Field = 'decision'")->getRow();
    $type = (string) ($field->Type ?? '');
    $ready = str_contains($type, "'fee_waiver_rejected'")
        || preg_match('/^(?:var)?char\((\d+)\)/i', $type, $length) && (int) $length[1] >= 19
        || in_array(strtolower($type), ['text', 'mediumtext', 'longtext'], true);
    echo $ready ? "Gate pass fee waiver history schema is ready.\n" : "Gate pass fee waiver history schema needs the supplied migration.\n";
    exit($ready ? 0 : 1);
} catch (Throwable $e) {
    error_log('Gate pass waiver schema command failed: ' . get_class($e) . ' at ' . $e->getFile() . ':' . $e->getLine());
    fwrite(STDERR, "Schema operation failed. Check the application/PHP error log; no database credentials are printed here.\n");
    exit(1);
}
