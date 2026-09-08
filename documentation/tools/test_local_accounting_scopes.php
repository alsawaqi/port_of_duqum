<?php

declare(strict_types=1);

// CLI-only transaction fixtures. Every inserted/updated row is rolled back;
// no sessions, bank calls, DDL, fees, or real user roles are changed.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('FCPATH', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
chdir(FCPATH);
require FCPATH . 'app/Config/Paths.php';
$paths = new Config\Paths();
require $paths->systemDirectory . '/Boot.php';
final class LocalAccountingScopeTestBootstrap extends CodeIgniter\Boot
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
$transaction = false;
$checks = 0;
$assert = static function (bool $value, string $message) use (&$checks): void {
    if (!$value) { throw new RuntimeException($message); }
    $checks++;
};
try {
    LocalAccountingScopeTestBootstrap::load($paths);
    if (!defined('CI_DEBUG')) { define('CI_DEBUG', true); }
    $settings = (new Config\Database())->default;
    if (!in_array(strtolower((string) $settings['hostname']), ['localhost', '127.0.0.1', '::1'], true)
        || ($settings['DBDriver'] ?? '') !== 'MySQLi' || !empty($settings['DSN'])
        || (int) $settings['port'] !== 3306 || $settings['database'] !== 'bedotscpanel_poderp'
        || $settings['DBPrefix'] !== 'pod_' || ENVIRONMENT === 'production') {
        throw new RuntimeException('This test requires the local XAMPP development database.');
    }
    $db = Config\Database::connect($settings, false);
    $tables = ['companies', 'users', 'vendors', 'gate_pass_requests', 'tenders', 'tender_requests',
        'ptw_applications', 'eservice_payments', 'eservice_payment_events'];
    foreach ($tables as $table) {
        $engine = $db->query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?',
            [$settings['database'], $db->prefixTable($table)])->getRow();
        $assert($engine && strcasecmp((string) $engine->ENGINE, 'InnoDB') === 0, 'Transactional tables required.');
    }
    $db->transBegin();
    $transaction = true;
    $tag = 'accounting-scope-' . bin2hex(random_bytes(6));
    $fixture = static function (string $table, array $overrides) use ($db): int {
        $data = [];
        foreach ($db->query('SHOW COLUMNS FROM `' . $db->prefixTable($table) . '`')->getResult() as $column) {
            if (str_contains($column->Extra, 'auto_increment') || str_contains($column->Extra, 'GENERATED')
                || $column->Null === 'YES' || $column->Default !== null) { continue; }
            $type = strtolower($column->Type);
            if (preg_match('/^enum\(\x27([^\x27]+)\x27/', $type, $matches)) {
                $data[$column->Field] = $matches[1];
            } elseif (preg_match('/int|decimal|float|double|bit/', $type)) {
                $data[$column->Field] = 0;
            } elseif (str_contains($type, 'datetime') || str_contains($type, 'timestamp')) {
                $data[$column->Field] = '2026-09-05 12:00:00';
            } elseif ($type === 'date') {
                $data[$column->Field] = '2026-09-05';
            } elseif ($type === 'time') {
                $data[$column->Field] = '12:00:00';
            } else {
                $data[$column->Field] = '';
            }
        }
        if (!$db->table($table)->insert(array_merge($data, $overrides))) {
            throw new RuntimeException('Could not create transactional fixture.');
        }
        return (int) $db->insertID();
    };
    $companyA = $fixture('companies', ['name' => $tag . '-A', 'code' => substr($tag, -12) . 'A', 'is_active' => 1, 'deleted' => 0]);
    $companyB = $fixture('companies', ['name' => $tag . '-B', 'code' => substr($tag, -12) . 'B', 'is_active' => 1, 'deleted' => 0]);
    $userId = $fixture('users', ['first_name' => 'Accounting', 'last_name' => 'Scope test', 'email' => $tag . '@example.invalid',
        'user_type' => 'staff', 'status' => 'active', 'is_admin' => 0, 'role_id' => 0, 'deleted' => 0,
        'password' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT)]);
    $vendorId = $fixture('vendors', ['vendor_name' => $tag, 'cr_number' => substr($tag, -12), 'deleted' => 0]);
    $actor = (object) ['id' => $userId, 'is_admin' => 0, 'user_type' => 'staff', 'status' => 'active',
        'permissions' => ['accounting_only' => '1']];
    $ledger = new App\Models\Payment_accounting_model($db);
    $allPayments = [];
    foreach (['gate_pass', 'tender', 'ptw'] as $module) {
        foreach (App\Libraries\Payments\Payment_accounting_policy::ACTIONS as $action) {
            $actor->permissions['can_' . $action . '_' . $module . '_accounting'] = '1';
        }
        $actor->permissions[$module . '_accounting_company_ids'] = [$companyA];
        $paymentIds = [];
        foreach ([$companyA, $companyB] as $companyId) {
            $reference = substr($tag, -12) . '-' . $module . '-' . $companyId;
            if ($module === 'tender') {
                $requestId = $fixture('tender_requests', ['company_id' => $companyId]);
                $subjectId = $fixture('tenders', ['company_id' => $companyId, 'tender_request_id' => $requestId, 'reference' => $reference]);
            } else {
                $subjectId = $fixture($module === 'ptw' ? 'ptw_applications' : 'gate_pass_requests',
                    ['company_id' => $companyId, 'reference' => $reference]);
            }
            $paymentId = $fixture('eservice_payments', ['public_id' => bin2hex(random_bytes(16)), 'subject_type' => $module . '_fee',
                'subject_id' => $subjectId, 'vendor_id' => $vendorId, 'user_id' => $userId, 'amount' => '1.123',
                'amount_minor' => 1123, 'currency' => 'OMR', 'provider' => 'bank_muscat', 'status' => 'pending',
                'idempotency_key' => bin2hex(random_bytes(32)), 'initiated_at' => '2026-09-05 12:00:00',
                'metadata' => json_encode(['description' => 'Rollback-only accounting scope test']), 'deleted' => 0]);
            $fixture('eservice_payment_events', ['payment_id' => $paymentId, 'provider' => 'bank_muscat',
                'provider_event_id' => $tag . '-' . $paymentId, 'event_type' => 'scope_test', 'payload_sha256' => hash('sha256', $tag),
                'status' => 'received', 'received_at' => '2026-09-05 12:00:00', 'response_json' => '{}']);
            $paymentIds[] = $paymentId;
            $allPayments[$module][] = $paymentId;
        }
        [$allowedId, $deniedId] = $paymentIds;
        foreach (App\Libraries\Payments\Payment_accounting_policy::ACTIONS as $action) {
            $rows = $ledger->rows($actor, $module, [], $action);
            $assert(array_map(static fn($row) => (int) $row->id, $rows) === [$allowedId], 'Company-scoped row list/export mismatch.');
            $assert($ledger->payment($actor, $module, $allowedId, $action) !== null, 'Allowed company detail missing.');
            $assert($ledger->payment($actor, $module, $deniedId, $action) === null, 'Cross-company action leaked.');
        }
        $assert(count($ledger->events($actor, $module, $allowedId)) === 1, 'Allowed bank event missing.');
        $assert($ledger->events($actor, $module, $deniedId) === [], 'Cross-company bank event leaked.');
        $actor->permissions[$module . '_accounting_company_ids'] = [];
        $assert($ledger->rows($actor, $module) === [], 'Empty selection did not deny records.');
        $actor->permissions[$module . '_accounting_company_ids'] = [$companyA];
        $assert($ledger->payment($actor, $module, $allowedId)->description === 'Rollback-only accounting scope test', 'Reason projection failed.');
    }
    foreach ($allPayments as $module => $ids) {
        foreach ($allPayments as $otherModule => $otherIds) {
            if ($module !== $otherModule) {
                $assert($ledger->payment($actor, $module, $otherIds[0]) === null, 'Cross-module detail leaked.');
                $assert($ledger->payment($actor, $module, $otherIds[0], 'reconcile') === null, 'Cross-module bank recheck lookup leaked.');
            }
        }
        $actor->permissions['can_responses_' . $module . '_accounting'] = '0';
        $assert(!property_exists($ledger->payment($actor, $module, $ids[0]), 'response_json'), 'View-only role exposed raw bank response.');
    }
    $db->table('companies')->where('id', $companyA)->update(['is_active' => 0]);
    foreach (array_keys($allPayments) as $module) {
        $assert($ledger->rows($actor, $module) === [], 'Inactive company remained visible.');
    }
    $db->transRollback();
    $transaction = false;
    $assert($db->table('users')->where('email', $tag . '@example.invalid')->countAllResults() === 0, 'Fixture rollback failed.');
    echo json_encode(['ok' => true, 'checks' => $checks, 'modules' => array_keys($allPayments),
        'safety' => 'All fixture rows rolled back. No bank calls or existing business/user records changed.'], JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, json_encode(['ok' => false, 'checks' => $checks, 'error_type' => get_class($error),
        'error_code' => $error->getCode(), 'message' => $error instanceof RuntimeException && !$error instanceof CodeIgniter\Database\Exceptions\DatabaseException
            ? $error->getMessage() : 'Accounting scope fixture/schema check failed.']) . PHP_EOL);
    exit(1);
} finally {
    if ($db && $transaction) { $db->transRollback(); }
    if ($db) { $db->close(); }
}
