<?php

require_once __DIR__ . '/../app/Libraries/Payments/Payment_accounting_policy.php';
require_once __DIR__ . '/../app/Models/Payment_accounting_model.php';
require_once __DIR__ . '/../app/Helpers/csv_security_helper.php';

use App\Libraries\Payments\Payment_accounting_policy as Policy;
use App\Models\Payment_accounting_model;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};
$throws = static function (callable $action) use ($assert): void {
    try {
        $action();
    } catch (\InvalidArgumentException | \RuntimeException $exception) {
        return;
    }
    $assert(false, 'Expected denied access or rejected filter.');
};

// Execute the actual production ledger SQL against an isolated in-memory DB.
// No live application data or connection configuration is loaded.
class AccountingTestDatabase
{
    public PDO $pdo;
    public array $queries = [];
    public function __construct()
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->sqliteCreateFunction('CONCAT_WS', static fn($separator, ...$parts) => implode($separator, array_filter($parts, static fn($value) => $value !== null)));
        $this->pdo->sqliteCreateFunction('JSON_UNQUOTE', static fn($value) => $value);
    }
    public function prefixTable($table) { return 'test_' . $table; }
    public function query($sql, $params = [])
    {
        $this->queries[] = [$sql, $params];
        $statement = $this->pdo->prepare($sql);
        foreach (array_values($params) as $index => $value) {
            $statement->bindValue($index + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $statement->execute();
        return new class($statement) {
            public function __construct(private PDOStatement $statement) {}
            public function getResult() { return $this->statement->fetchAll(PDO::FETCH_OBJ); }
            public function getRow() { return $this->statement->fetch(PDO::FETCH_OBJ) ?: null; }
        };
    }
}

$db = new AccountingTestDatabase();
$db->pdo->exec("CREATE TABLE test_eservice_payments (
    id INTEGER PRIMARY KEY, public_id TEXT, subject_type TEXT, subject_id INTEGER, vendor_id INTEGER,
    user_id INTEGER, amount TEXT, currency TEXT, provider TEXT, status TEXT, settlement_status TEXT,
    provider_checkout_id TEXT, provider_payment_id TEXT, bank_reference TEXT, gateway_merchant_id TEXT,
    initiated_at TEXT, handed_off_at TEXT, paid_at TEXT, failed_at TEXT, verified_at TEXT, failure_code TEXT,
    response_json TEXT, status_response_json TEXT, verification_issues TEXT, metadata TEXT, deleted INTEGER DEFAULT 0)");
$db->pdo->exec('CREATE TABLE test_vendors (id INTEGER PRIMARY KEY, vendor_name TEXT, cr_number TEXT)');
$db->pdo->exec('CREATE TABLE test_users (id INTEGER PRIMARY KEY, first_name TEXT, last_name TEXT, email TEXT, deleted INTEGER, status TEXT)');
$db->pdo->exec('CREATE TABLE test_companies (id INTEGER PRIMARY KEY, name TEXT, deleted INTEGER DEFAULT 0, is_active INTEGER DEFAULT 1)');
$db->pdo->exec('CREATE TABLE test_gate_pass_requests (id INTEGER PRIMARY KEY, company_id INTEGER, reference TEXT)');
$db->pdo->exec('CREATE TABLE test_tender_requests (id INTEGER PRIMARY KEY, company_id INTEGER)');
$db->pdo->exec('CREATE TABLE test_tenders (id INTEGER PRIMARY KEY, company_id INTEGER, tender_request_id INTEGER, reference TEXT)');
$db->pdo->exec('CREATE TABLE test_ptw_applications (id INTEGER PRIMARY KEY, company_id INTEGER, reference TEXT)');
$db->pdo->exec('CREATE TABLE test_gate_pass_commercial_users (id INTEGER PRIMARY KEY, user_id INTEGER, company_id INTEGER, deleted INTEGER, status TEXT)');
$db->pdo->exec('CREATE TABLE test_tender_finance_users (id INTEGER PRIMARY KEY, user_id INTEGER, company_id INTEGER, deleted INTEGER, status TEXT)');
$db->pdo->exec('CREATE TABLE test_eservice_payment_events (id INTEGER PRIMARY KEY, payment_id INTEGER, provider TEXT, provider_event_id TEXT, event_type TEXT, status TEXT, received_at TEXT, processed_at TEXT, payload_sha256 TEXT, response_json TEXT, verification_issues TEXT)');
$db->pdo->exec("INSERT INTO test_users VALUES (20,'Finance','Reader','finance@example.test',0,'active'), (30,'Payer','One','payer@example.test',0,'active')");
$db->pdo->exec("INSERT INTO test_vendors VALUES (1,'Vendor One','CR1'),(2,'100%_Literal','CR2')");
$db->pdo->exec("INSERT INTO test_companies (id,name) VALUES (1,'Company A'),(2,'Company B')");
$db->pdo->exec("INSERT INTO test_gate_pass_requests VALUES (11,1,'GP-A'),(12,2,'GP-B')");
$db->pdo->exec('INSERT INTO test_tender_requests VALUES (101,1),(102,2)');
$db->pdo->exec("INSERT INTO test_tenders VALUES (21,NULL,101,'T-A'),(22,2,101,'T-B')");
$db->pdo->exec("INSERT INTO test_ptw_applications VALUES (31,1,'PTW-A'),(32,2,'PTW-B')");
$db->pdo->exec("INSERT INTO test_gate_pass_commercial_users VALUES (1,20,1,0,'active'),(2,20,2,0,'inactive')");
$db->pdo->exec("INSERT INTO test_tender_finance_users VALUES (1,20,1,0,'active'),(2,20,2,1,'active')");
$insert = $db->pdo->prepare("INSERT INTO test_eservice_payments
    (id,public_id,subject_type,subject_id,vendor_id,user_id,amount,currency,provider,status,settlement_status,
     initiated_at,bank_reference,response_json,status_response_json,verification_issues)
    VALUES (?,?,?,?,?,30,'10.123','OMR','bank_muscat',?,?,?,?,'{}','{}','')");
$fixtures = [
    [1,'a1','gate_pass_fee',11,1,'paid','applied','2026-09-01 00:00:00','bank-one'],
    [2,'a2','gate_pass_fee',12,2,'paid','applied','2026-09-01 10:00:00','bank-two'],
    [3,'a3','tender_fee',21,1,'paid','applied','2026-09-01 23:59:59','bank-three'],
    [4,'a4','tender_fee',22,2,'failed','pending','2026-09-01 10:00:00','bank-four'],
    [5,'a5','vendor_registration',1,1,'paid','applied','2026-09-01 10:00:00','bank-five'],
    [6,'a6','vendor_renewal',2,2,'verification_required','pending','2026-09-01 10:00:00','bank-six'],
    [7,'a7','gate_pass_fee',11,1,'paid','review_required','2026-09-02 00:00:00','bank-seven'],
    [8,'a8','ptw_fee',31,1,'paid','applied','2026-09-01 10:00:00','bank-eight'],
    [9,'a9','ptw_fee',32,2,'paid','applied','2026-09-01 10:00:00','bank-nine'],
];
foreach ($fixtures as $fixture) { $insert->execute($fixture); }
$metadata = $db->pdo->prepare('UPDATE test_eservice_payments SET metadata=? WHERE id=?');
$metadata->execute([json_encode(['description' => 'Gate pass application fee', 'success_url' => 'https://private-return.example.test/token']), 1]);
$db->pdo->exec("INSERT INTO test_eservice_payment_events VALUES (1,1,'bank_muscat','event-a','return','processed','2026-09-01',NULL,'digest','{}',''),(2,2,'bank_muscat','event-b','return','processed','2026-09-01',NULL,'digest','{}','')");
$actor = (object) ['id' => 20, 'user_type' => 'staff', 'status' => 'active', 'is_admin' => 0, 'permissions' => []];
foreach (Policy::MODULES as $module => $_) {
    foreach (Policy::ACTIONS as $action) { $actor->permissions['can_' . $action . '_' . $module . '_accounting'] = '1'; }
}
$ledger = new Payment_accounting_model($db);
$ids = static fn(array $rows) => array_map(static fn($row) => (int) $row->id, $rows);

$assert($ids($ledger->rows($actor, 'gate_pass')) === [7,1], 'Commercial ledger cannot leak another company.');
$assert($ids($ledger->rows($actor, 'tender')) === [3], 'Finance scope uses tender company with request fallback and ignores deleted assignments.');
$assert($ids($ledger->rows($actor, 'vendor')) === [6,5], 'Explicit vendor accounting covers registration and renewal registry.');
$assert($ledger->rows($actor, 'ptw') === [], 'PTW has no legacy finance assignment and needs explicit company selection.');
$purpose = $ledger->payment($actor, 'gate_pass', 1);
$assert($purpose->description === 'Gate pass application fee', 'Ledger displays the saved payment reason.');
$assert(!property_exists($purpose, 'metadata'), 'Ledger projection excludes private return URLs and arbitrary metadata.');
foreach (['view','responses','export','reconcile'] as $action) {
    $assert($ledger->payment($actor, 'gate_pass', 2, $action) === null, 'Details/action deny cross-company payment: ' . $action);
    $assert($ledger->payment($actor, 'gate_pass', 3, $action) === null, 'Details/action deny a different module: ' . $action);
}
$assert($ids($ledger->rows($actor, 'gate_pass', [], 'export')) === [7,1], 'Export retains company scope.');
$assert(count($ledger->events($actor, 'gate_pass', 1)) === 1, 'Authorized bank events are available.');
$assert($ledger->events($actor, 'gate_pass', 2) === [], 'Bank events cannot bypass company scope.');
$assert($ids($ledger->rows($actor, 'gate_pass', ['start_date'=>'2026-09-01','end_date'=>'2026-09-01'])) === [1], 'Date interval includes the full final day and excludes next-day midnight.');
$assert($ids($ledger->rows($actor, 'tender', ['end_date'=>'2026-09-01'])) === [3], 'Final second of end date is included.');
$assert($ids($ledger->rows($actor, 'gate_pass', ['status'=>'needs_review'])) === [7], 'Confirmed bank payment with application failure is visible for review.');
$assert($ids($ledger->rows($actor, 'vendor', ['status'=>'needs_review'])) === [6], 'Unverified payment is visible for review.');
$db->pdo->exec("UPDATE test_eservice_payments SET verification_issues='[\"bank_tracking_reference_conflict\"]' WHERE id=1");
$assert($ids($ledger->rows($actor, 'gate_pass', ['status'=>'needs_review'])) === [7,1], 'Already-paid bank tracking conflict is visible for review without revoking the paid receipt.');
$assert((int) $ledger->payment($actor, 'gate_pass', 1)->has_verification_issues === 1, 'Bank attention flag is available independently of the response permission.');
$assert($ledger->payment($actor, 'gate_pass', 1)->status === 'paid', 'Accounting never demotes a verified paid receipt.');
$assert($ids($ledger->rows($actor, 'vendor', ['vendor'=>'%_'])) === [6], 'Search wildcards are literal.');
$assert($ledger->rows($actor, 'gate_pass', ['reference'=>"' OR 1=1 --"]) === [], 'Untrusted filters cannot alter SQL scope.');
$assert($ids($ledger->rows($actor, 'gate_pass', ['payer'=>'payer@example.test','reference'=>'bank-one'])) === [1], 'Payer and reference filters combine.');
$assert($ledger->rows($actor, 'gate_pass')[0]->amount === '10.123', 'OMR precision is retained.');

$viewOnly = clone $actor;
$viewOnly->permissions = ['can_view_gate_pass_accounting'=>'1'];
$assert(!property_exists($ledger->payment($viewOnly, 'gate_pass', 1), 'response_json'), 'View permission does not select sensitive response JSON.');
$throws(static fn() => $ledger->events($viewOnly, 'gate_pass', 1));
$throws(static fn() => $ledger->rows($viewOnly, 'gate_pass', [], 'export'));
$throws(static fn() => $ledger->payment($viewOnly, 'gate_pass', 1, 'reconcile'));
$withoutView = clone $actor;
unset($withoutView->permissions['can_view_gate_pass_accounting']);
$assert(!Policy::allows($withoutView, 'gate_pass', 'responses'), 'Response permission alone is insufficient.');
$throws(static fn() => $ledger->rows($withoutView, 'gate_pass'));
$admin = clone $actor;
$admin->is_admin = 1;
$admin->permissions = [];
$assert($ids($ledger->rows($admin, 'gate_pass')) === [7,2,1], 'Internal admin can reconcile every company.');
foreach (['is_vendor_only_identity','is_gate_pass_only_identity','is_ptw_applicant_only_identity'] as $flag) {
    $external = clone $admin;
    $external->$flag = true;
    $assert(!Policy::allows($external, 'vendor'), 'Portal-only identity is denied despite staff/admin flags.');
}
foreach (['client','lead',''] as $type) {
    $external = clone $admin;
    $external->user_type = $type;
    $assert(!Policy::allows($external, 'vendor'), 'Non-staff cannot access accounting.');
}
$assert(!Policy::allows($admin, 'unknown'), 'Unsupported modules do not gain billing access.');
$assert($ids($ledger->rows($admin, 'ptw')) === [9,8], 'PTW accounting projects existing records for admin without creating a payment flow.');

// Dedicated accounting scopes work without operational assignment rows. Every
// lookup (including the one used by bank recheck) must enforce the same scope.
$accountingOnly = clone $actor;
$accountingOnly->id = 30;
foreach (['gate_pass' => [2,1], 'tender' => [4,3], 'ptw' => [9,8]] as $module => [$allowedId, $deniedId]) {
    $accountingOnly->permissions[$module . '_accounting_company_ids'] = ['2'];
    $assert($ids($ledger->rows($accountingOnly, $module)) === [$allowedId], 'Accounting-only company list: ' . $module);
    $assert($ids($ledger->rows($accountingOnly, $module, [], 'export')) === [$allowedId], 'Accounting-only export scope: ' . $module);
    foreach (Policy::ACTIONS as $action) {
        $assert($ledger->payment($accountingOnly, $module, $allowedId, $action) !== null, 'Accounting-only permitted lookup: ' . $module . '/' . $action);
        $assert($ledger->payment($accountingOnly, $module, $deniedId, $action) === null, 'Accounting-only cross-company lookup blocked: ' . $module . '/' . $action);
    }
    $assert($ledger->events($accountingOnly, $module, $deniedId) === [], 'Accounting-only events cannot escape scope: ' . $module);
    $accountingOnly->permissions[$module . '_accounting_company_ids'] = [];
    $assert($ledger->rows($accountingOnly, $module) === [], 'Empty explicit accounting companies deny all: ' . $module);
    $accountingOnly->permissions[$module . '_accounting_company_ids'] = '2 OR 1=1';
    $assert($ledger->rows($accountingOnly, $module) === [], 'Malformed saved company scope fails closed: ' . $module);
}
$actor->permissions['gate_pass_accounting_company_ids'] = [];
$assert($ledger->rows($actor, 'gate_pass') === [], 'An explicit empty selection never uses legacy Commercial assignments.');
unset($actor->permissions['gate_pass_accounting_company_ids']);
$accountingOnly->permissions['gate_pass_accounting_company_ids'] = [2];
$assert(count($ledger->events($accountingOnly, 'gate_pass', 2)) === 1, 'Authorized accounting-only reader can inspect bank history.');
$db->pdo->exec('UPDATE test_companies SET is_active=0 WHERE id=2');
$assert($ledger->rows($accountingOnly, 'gate_pass') === [], 'Inactive company selections are denied at read time.');
$db->pdo->exec('UPDATE test_companies SET is_active=1, deleted=1 WHERE id=2');
$assert($ledger->rows($accountingOnly, 'gate_pass') === [], 'Deleted company selections are denied at read time.');
$db->pdo->exec('UPDATE test_companies SET deleted=0 WHERE id=2');
$assert(Policy::normalizeCompanyIds(['2',2,'1']) === [2,1], 'Company selections are normalized and deduplicated.');
foreach (['2', [0], [-1], ['1 OR 1=1'], [[]], ['2147483648'], [true]] as $invalidCompanies) {
    $throws(static fn() => Policy::normalizeCompanyIds($invalidCompanies));
}
$assert(!Policy::allows($admin, 'vendor', 'mark_paid'), 'No manual settlement action exists.');
$db->pdo->exec("UPDATE test_users SET status='inactive' WHERE id=20");
$assert($ledger->rows($actor, 'gate_pass') === [], 'A disabled assignee cannot access company ledger.');
foreach ([['start_date'=>'2026-02-30'],['end_date'=>[]],['status'=>['paid']],['status'=>'unknown'],['start_date'=>'2026-09-03','end_date'=>'2026-09-01'],['reference'=>['x']]] as $invalid) {
    $throws(static fn() => Policy::filters($invalid));
}
$assert(csv_safe_cell('=HYPERLINK("bad")') === "'=HYPERLINK(\"bad\")", 'CSV export neutralizes formulas.');
$controller = file_get_contents(__DIR__ . '/../app/Controllers/Payment_accounting.php');
$assert(str_contains($controller, "strtolower(\$this->request->getMethod()) !== 'post'"), 'Recheck is POST-only.');
$assert(str_contains($controller, "access_only_payment_accounting(\$module, 'reconcile')"), 'Bank recheck has independent action authorization.');
$presenter = file_get_contents(__DIR__ . '/../app/Libraries/Payments/Payment_accounting_presenter.php');
$assert(str_contains($presenter, 'Bank_muscat_gateway::safeResponse'), 'Stored responses are re-sanitized by the human-readable presenter.');
$accountingOnly->permissions['accounting_only'] = '1';
$assert(Policy::isAccountingOnly($accountingOnly), 'A dedicated accounting-only role is explicit.');
$assert(Policy::home($accountingOnly) === 'payment_accounting/index/vendor', 'Accounting-only landing uses a permitted ledger.');
$restricted = clone $accountingOnly;
$restricted->permissions = ['accounting_only' => '1', 'can_view_ptw_accounting' => '1'];
$assert(Policy::home($restricted) === 'payment_accounting/index/ptw', 'PTW-only role lands on PTW accounting.');
$restricted->permissions = ['accounting_only' => '1'];
$assert(Policy::home($restricted) === 'forbidden', 'Accounting-only role with no view permission gets no ledger.');
$admin->permissions['accounting_only'] = '1';
$assert(!Policy::isAccountingOnly($admin), 'Administrator retains administrative access.');
foreach ([['payment_accounting', 'index'], ['payment_accounting', 'details'], ['portal_account', 'change_password'], ['portal_account', 'save_password'], ['team_members', 'save_personal_language']] as [$route, $method]) {
    $assert(Policy::accountingRouteAllowed($route, $method), 'Required accounting/account route remains available: ' . $route . '/' . $method);
}
foreach ([['vendors', 'update_status'], ['gate_pass_commercial_inbox', 'index'], ['tender_finance_inbox', 'index'], ['ptw_hmo_inbox', 'index'], ['roles', 'save_permissions'], ['team_members', 'save_account_settings'], ['projects', 'index'], ['notifications', 'index']] as [$route, $method]) {
    $assert(!Policy::accountingRouteAllowed($route, $method), 'Accounting-only staff cannot enter operational route: ' . $route . '/' . $method);
}
echo "Payment accounting authorization, SQL scope, filters and CSV tests passed.\n";
