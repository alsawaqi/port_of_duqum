<?php

declare(strict_types=1);

// Local schema/SQL diagnostics only. No routes, sessions, migrations or bank calls.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
define('FCPATH', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
chdir(FCPATH);
require FCPATH . 'app/Config/Paths.php';
$paths = new Config\Paths();
require $paths->systemDirectory . '/Boot.php';
final class LocalAccountingReadOnlyBootstrap extends CodeIgniter\Boot
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
try {
    LocalAccountingReadOnlyBootstrap::load($paths);
    if (!defined('CI_DEBUG')) { define('CI_DEBUG', true); }
    $settings = (new Config\Database())->default;
    if (!in_array(strtolower((string) $settings['hostname']), ['localhost', '127.0.0.1', '::1'], true)
        || ($settings['DBDriver'] ?? '') !== 'MySQLi' || !empty($settings['DSN'])) {
        throw new RuntimeException('Local MySQLi configuration required.');
    }
    $db = Config\Database::connect($settings, false);
    $db->query('START TRANSACTION READ ONLY');
    $ledger = new App\Models\Payment_accounting_model($db);
    if (!$ledger->ready()) {
        throw new RuntimeException('Accounting schema is not ready.');
    }
    $paymentTable = $db->prefixTable('eservice_payments');
    $eventTable = $db->prefixTable('eservice_payment_events');
    $count = (int) $db->query("SELECT COUNT(*) AS n FROM {$paymentTable}")->getRow()->n;
    $admin = (object) ['id' => 2147483647, 'is_admin' => 1, 'user_type' => 'staff', 'status' => 'active', 'permissions' => []];
    $staff = clone $admin;
    $staff->is_admin = 0;
    foreach (App\Libraries\Payments\Payment_accounting_policy::MODULES as $module => $_) {
        foreach (App\Libraries\Payments\Payment_accounting_policy::ACTIONS as $action) {
            $staff->permissions['can_' . $action . '_' . $module . '_accounting'] = '1';
        }
    }
    $checks = [];
    foreach (App\Libraries\Payments\Payment_accounting_policy::MODULES as $module => $_) {
        // A future date and impossible reference ensure no business records are returned.
        $filters = ['start_date' => '9998-01-01', 'end_date' => '9998-01-02',
            'vendor' => '__local_schema_diagnostic__', 'payer' => '__local_schema_diagnostic__',
            'reference' => '__local_schema_diagnostic__', 'status' => 'needs_review'];
        $ledger->rows($admin, $module, $filters, 'view', 1);
        $ledger->rows($staff, $module, $filters, 'view', 1);
        $ledger->rows($staff, $module, $filters, 'export', 1);
        foreach (['view', 'responses', 'reconcile'] as $action) {
            if ($ledger->payment($staff, $module, 0, $action) !== null) {
                throw new RuntimeException('Unexpected diagnostic fixture ID.');
            }
        }
        if ($ledger->events($staff, $module, 0) !== []) {
            throw new RuntimeException('Unexpected diagnostic events.');
        }
        $checks[$module] = 'Admin and company-scoped list/filter/export/detail/response/recheck lookup SQL passed.';
    }
    $db->query("SELECT id, provider, provider_event_id, event_type, status, received_at, processed_at,
        payload_sha256, response_json, verification_issues FROM {$eventTable} WHERE payment_id=0 ORDER BY id ASC")->getResult();
    $db->query('ROLLBACK');
    echo json_encode(['ok' => true, 'schema_ready' => true, 'existing_payment_count' => $count,
        'modules' => $checks, 'events' => 'Event field query passed.',
        'bank_recheck_method_exists' => method_exists(App\Libraries\Payments\Eservice_payment_manager::class, 'recheckSmartpayPayment'),
        'safety' => 'Local read-only transaction; no business rows returned, writes, bank calls, sessions or migrations.'], JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, json_encode(['ok' => false, 'error_type' => get_class($error), 'error_code' => $error->getCode(),
        'message' => 'Local accounting SQL validation failed. Check the local schema and accounting model.']) . PHP_EOL);
    exit(1);
} finally {
    if ($db) { $db->close(); }
}
