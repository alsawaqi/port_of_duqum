<?php

declare(strict_types=1);

// Standalone behavior tests. SQLite :memory: only; no application bootstrap,
// environment credentials, network calls, or real database connection.
namespace CodeIgniter\Database {
    abstract class BaseConnection {}
}

namespace {
    use App\Libraries\Payments\Vendor_billing_service;
    use App\Libraries\Payments\Vendor_payment_settlement;

    require_once __DIR__ . '/../app/Libraries/Payments/Payment_amount.php';
    require_once __DIR__ . '/../app/Libraries/Payments/Vendor_billing_service.php';
    require_once __DIR__ . '/../app/Libraries/Payments/Vendor_payment_settlement.php';

    function config(string $name): object
    {
        if ($name !== 'EservicesPayments') {
            throw new RuntimeException('Unexpected configuration dependency: ' . $name);
        }
        return (object) ['currency' => 'OMR'];
    }

    function get_current_utc_time(): string { return gmdate('Y-m-d H:i:s'); }

    final class VendorBillingResult
    {
        public function __construct(private \PDOStatement $statement) {}
        public function getRow(): ?object
        {
            return $this->statement->fetch(\PDO::FETCH_OBJ) ?: null;
        }
    }

    final class VendorBillingMemoryDb extends \CodeIgniter\Database\BaseConnection
    {
        public \PDO $pdo;
        public function __construct()
        {
            $this->pdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        }
        public function prefixTable(string $name): string { return str_starts_with($name, 'pod_') ? $name : 'pod_' . $name; }
        public function tableExists(string $name): bool
        {
            return (bool) $this->query("SELECT name FROM sqlite_master WHERE type='table' AND name=?", [$this->prefixTable($name)])->getRow();
        }
        public function query(string $sql, array $bindings = []): VendorBillingResult
        {
            // SQLite has no row locks; exercise production predicates and binding
            // values while retaining transactional rollback/unique constraints.
            $statement = $this->pdo->prepare(str_ireplace(' FOR UPDATE', '', $sql));
            $statement->execute($bindings);
            return new VendorBillingResult($statement);
        }
        public function table(string $name): VendorBillingBuilder { return new VendorBillingBuilder($this, $this->prefixTable($name)); }
        public function insertID(): int { return (int) $this->pdo->lastInsertId(); }
        public function transBegin(): void { $this->pdo->beginTransaction(); }
        public function transCommit(): void { $this->pdo->commit(); }
        public function transRollback(): void { if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); } }
        public function transStatus(): bool { return true; }
        public function getFieldNames(string $table): array
        {
            return array_column($this->pdo->query('PRAGMA table_info(' . $this->prefixTable($table) . ')')->fetchAll(\PDO::FETCH_ASSOC), 'name');
        }
    }

    final class VendorBillingBuilder
    {
        private array $conditions = [];
        public function __construct(private VendorBillingMemoryDb $db, private string $table) {}
        public function where(string $column, mixed $value): self { $this->conditions[$column] = $value; return $this; }
        public function insert(array $row): bool
        {
            $this->db->query('INSERT INTO ' . $this->table . ' (' . implode(',', array_keys($row)) . ') VALUES (' . implode(',', array_fill(0, count($row), '?')) . ')', array_values($row));
            return true;
        }
        public function update(array $row): bool
        {
            if (!$this->conditions) { throw new RuntimeException('Unexpected update without a fixture row predicate.'); }
            $assignments = implode(',', array_map(static fn (string $column): string => $column . '=?', array_keys($row)));
            $predicates = implode(' AND ', array_map(static fn (string $column): string => $column . '=?', array_keys($this->conditions)));
            $this->db->query('UPDATE ' . $this->table . ' SET ' . $assignments . ' WHERE ' . $predicates, array_merge(array_values($row), array_values($this->conditions)));
            return true;
        }
    }

    function fixture(array $vendorChanges = [], string $amount = '10.125'): array
    {
        $db = new VendorBillingMemoryDb();
        $schema = [
            'CREATE TABLE pod_vendors (id INTEGER PRIMARY KEY, vendor_group_id INTEGER, status TEXT, registration_valid_from TEXT, registration_valid_to TEXT, deleted INTEGER DEFAULT 0, updated_by INTEGER, updated_at TEXT)',
            'CREATE TABLE pod_vendor_groups (id INTEGER PRIMARY KEY, deleted INTEGER DEFAULT 0, is_active INTEGER DEFAULT 1, default_validity_days INTEGER)',
            'CREATE TABLE pod_vendor_group_fees (id INTEGER PRIMARY KEY, vendor_group_id INTEGER, fee_type TEXT, currency TEXT, amount TEXT, active_from TEXT, active_to TEXT, is_active INTEGER DEFAULT 1, deleted INTEGER DEFAULT 0)',
            'CREATE TABLE pod_vendor_fee_requests (id INTEGER PRIMARY KEY AUTOINCREMENT, vendor_id INTEGER NOT NULL, fee_type TEXT NOT NULL, period_key TEXT NOT NULL, fee_id INTEGER NOT NULL, vendor_group_id INTEGER NOT NULL, amount TEXT NOT NULL, currency TEXT NOT NULL, prior_valid_until TEXT, validity_days INTEGER NOT NULL, status TEXT DEFAULT "pending", payment_id INTEGER, review_status TEXT DEFAULT "pending", requested_by INTEGER NOT NULL, reviewed_by INTEGER, reviewed_at TEXT, created_at TEXT NOT NULL, updated_at TEXT NOT NULL, UNIQUE(vendor_id,period_key))',
            'CREATE TABLE pod_eservice_payments (id INTEGER PRIMARY KEY, vendor_id INTEGER, subject_id INTEGER, subject_type TEXT, amount TEXT, currency TEXT, metadata TEXT, user_id INTEGER, status TEXT, provider TEXT, verified_at TEXT, settlement_status TEXT, deleted INTEGER DEFAULT 0)',
            // These are the actual deployed history column names (no changed_*).
            'CREATE TABLE pod_vendor_status_histories (id INTEGER PRIMARY KEY AUTOINCREMENT, vendor_id INTEGER NOT NULL, from_status TEXT, to_status TEXT NOT NULL, action TEXT, reason TEXT, action_by INTEGER, action_at TEXT, created_at TEXT, updated_at TEXT, deleted INTEGER DEFAULT 0)',
        ];
        foreach ($schema as $sql) { $db->pdo->exec($sql); }
        $vendor = array_merge(['id' => 17, 'vendor_group_id' => 3, 'status' => 'new', 'registration_valid_from' => null, 'registration_valid_to' => null, 'deleted' => 0], $vendorChanges);
        $db->table('vendors')->insert($vendor);
        $db->table('vendor_groups')->insert(['id' => 3, 'default_validity_days' => 365]);
        foreach (['registration', 'renewal'] as $offset => $type) {
            $db->table('vendor_group_fees')->insert(['id' => 41 + $offset, 'vendor_group_id' => 3, 'fee_type' => $type, 'currency' => 'OMR', 'amount' => $amount, 'active_from' => null, 'active_to' => null]);
        }
        return [$db, new Vendor_billing_service($db), (object) $vendor];
    }

    function row(VendorBillingMemoryDb $db, string $table, int $id): object
    {
        return $db->query('SELECT * FROM ' . $db->prefixTable($table) . ' WHERE id=?', [$id])->getRow() ?? throw new RuntimeException('Missing fixture row.');
    }

    function payment(VendorBillingMemoryDb $db, Vendor_billing_service $billing, object $request, array $changes = []): object
    {
        $values = array_merge(['id' => 81, 'vendor_id' => $request->vendor_id, 'subject_id' => $request->vendor_id,
            'subject_type' => 'vendor_' . $request->fee_type, 'amount' => $request->amount, 'currency' => $request->currency,
            'metadata' => json_encode($billing->metadata($request)), 'user_id' => 101,
            'status' => 'paid', 'provider' => 'bank_muscat', 'verified_at' => get_current_utc_time(), 'settlement_status' => 'applied', 'deleted' => 0], $changes);
        $db->table('eservice_payments')->insert($values);
        return (object) $values;
    }

    function settled(VendorBillingMemoryDb $db, Vendor_billing_service $billing, object $request, array $paymentChanges = []): object
    {
        $paid = payment($db, $billing, $request, $paymentChanges);
        $db->table('vendor_fee_requests')->where('id', $request->id)->update(['status' => 'paid', 'payment_id' => $paid->id, 'review_status' => 'submitted']);
        return row($db, 'vendor_fee_requests', (int) $request->id);
    }

    function same(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) { throw new RuntimeException($message . ' Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true)); }
    }
    function rejects(callable $call, string $type = \DomainException::class): void
    {
        try { $call(); } catch (\Throwable $error) {
            if ($error instanceof $type) { return; }
            throw $error;
        }
        throw new RuntimeException('Expected rejection (' . $type . ').');
    }

    $tests = [];
    $tests['OMR amount stays exact to three decimal places'] = static function (): void {
        foreach (['0' => '0.000', '0.000' => '0.000', '1' => '1.000', '10.1' => '10.100', '123456789.123' => '123456789.123'] as $input => $expected) {
            same($expected, Vendor_billing_service::normalizedFee((string) $input));
        }
        foreach (['-1', '1.0001', '1e3', '1,000', '01.00', 'not money'] as $invalid) {
            rejects(static fn () => Vendor_billing_service::normalizedFee($invalid), \InvalidArgumentException::class);
        }
    };
    $tests['registration is one period and renewal binds previous expiry'] = static function (): void {
        same(Vendor_billing_service::periodKey(17, 'registration', null), Vendor_billing_service::periodKey(17, 'registration', '2030-01-01'));
        same(false, Vendor_billing_service::periodKey(17, 'renewal', '2030-01-01') === Vendor_billing_service::periodKey(17, 'renewal', '2031-01-01'));
        same(false, Vendor_billing_service::periodKey(17, 'registration', null) === Vendor_billing_service::periodKey(18, 'registration', null));
        rejects(static fn () => Vendor_billing_service::periodKey(0, 'registration', null));
        rejects(static fn () => Vendor_billing_service::periodKey(17, 'update', null));
    };
    $tests['quote selects current configured group fee and rejects missing rules'] = static function (): void {
        [$db, $billing, $vendor] = fixture();
        $db->table('vendor_group_fees')->insert(['id' => 43, 'vendor_group_id' => 3, 'fee_type' => 'registration', 'currency' => 'OMR', 'amount' => '20.001', 'active_from' => gmdate('Y-m-d', strtotime('-1 day')), 'active_to' => gmdate('Y-m-d', strtotime('+1 day'))]);
        $db->table('vendor_group_fees')->insert(['id' => 44, 'vendor_group_id' => 3, 'fee_type' => 'registration', 'currency' => 'OMR', 'amount' => '999.999', 'active_from' => gmdate('Y-m-d', strtotime('+1 day'))]);
        same('20.001', $billing->quote($vendor, 'registration')['amount']);
        $vendor->vendor_group_id = 99;
        rejects(static fn () => $billing->quote($vendor, 'registration'));
    };
    $tests['quote refuses wrong currency and missing validity'] = static function (): void {
        [$db, $billing, $vendor] = fixture();
        $db->table('vendor_group_fees')->where('id', 41)->update(['currency' => 'USD']);
        rejects(static fn () => $billing->quote($vendor, 'registration'));
        $db->table('vendor_group_fees')->where('id', 41)->update(['currency' => 'OMR']);
        $db->table('vendor_groups')->where('id', 3)->update(['default_validity_days' => 0]);
        rejects(static fn () => $billing->quote($vendor, 'registration'));
    };
    $tests['registration preparation snapshots fee and is idempotent'] = static function (): void {
        [$db, $billing] = fixture();
        $first = $billing->prepare(17, 101, 'registration');
        same('10.125', $first->amount);
        same('pending_payment', row($db, 'vendors', 17)->status);
        $db->table('vendor_group_fees')->where('id', 41)->update(['amount' => '99.999']);
        $second = $billing->prepare(17, 101, 'registration');
        same($first->id, $second->id);
        same('10.125', $second->amount);
        same(1, (int) $db->pdo->query('SELECT COUNT(*) FROM pod_vendor_fee_requests')->fetchColumn());
    };
    $tests['history retains actual deployed actor and time fields'] = static function (): void {
        [$db, $billing] = fixture();
        $billing->prepare(17, 101, 'registration');
        $history = $db->query('SELECT * FROM pod_vendor_status_histories')->getRow();
        same(101, (int) $history->action_by);
        same(true, !empty($history->action_at));
        same('pending_payment', $history->to_status);
    };
    $tests['rejected vendors cannot create a payable request'] = static function (): void {
        [$db, $billing] = fixture(['status' => 'rejected']);
        rejects(static fn () => $billing->prepare(17, 101, 'registration'));
        same(0, (int) $db->pdo->query('SELECT COUNT(*) FROM pod_vendor_fee_requests')->fetchColumn());
    };
    $tests['request snapshot rolls back if vendor status update fails'] = static function (): void {
        [$db, $billing] = fixture();
        $db->pdo->exec("CREATE TRIGGER reject_vendor_update BEFORE UPDATE ON pod_vendors BEGIN SELECT RAISE(ABORT, 'fixture write failure'); END");
        rejects(static fn () => $billing->prepare(17, 101, 'registration'), \PDOException::class);
        same(0, (int) $db->pdo->query('SELECT COUNT(*) FROM pod_vendor_fee_requests')->fetchColumn());
        same('new', row($db, 'vendors', 17)->status);
    };
    $tests['renewal preparation retains existing approved access'] = static function (): void {
        $expiry = gmdate('Y-m-d', strtotime('+60 days'));
        [$db, $billing] = fixture(['status' => 'approved', 'registration_valid_to' => $expiry]);
        $request = $billing->prepare(17, 101, 'renewal');
        same($expiry, $request->prior_valid_until);
        same('approved', row($db, 'vendors', 17)->status);
        rejects(static fn () => $billing->prepare(17, 101, 'registration'));
    };
    $tests['reviewed period cannot be charged twice'] = static function (): void {
        [$db, $billing] = fixture();
        $request = $billing->prepare(17, 101, 'registration');
        $db->table('vendor_fee_requests')->where('id', $request->id)->update(['status' => 'paid', 'review_status' => 'approved']);
        rejects(static fn () => $billing->prepare(17, 101, 'registration'));
        same(1, (int) $db->pdo->query('SELECT COUNT(*) FROM pod_vendor_fee_requests')->fetchColumn());
    };
    $tests['completed renewal creates a different request for the next period'] = static function (): void {
        $expiry = gmdate('Y-m-d', strtotime('+60 days'));
        [$db, $billing] = fixture(['status' => 'approved', 'registration_valid_from' => '2025-01-01', 'registration_valid_to' => $expiry]);
        $first = settled($db, $billing, $billing->prepare(17, 101, 'renewal'));
        $dates = $billing->review(row($db, 'vendors', 17), 'approved', 500);
        $db->table('vendors')->where('id', 17)->update($dates);
        $second = $billing->prepare(17, 101, 'renewal');
        same(false, $first->id === $second->id);
        same(false, $first->period_key === $second->period_key);
        same($dates['registration_valid_to'], $second->prior_valid_until);
    };
    $tests['paid flag alone cannot submit for Procurement review'] = static function (): void {
        [$db, $billing] = fixture();
        $request = $billing->prepare(17, 101, 'registration');
        $db->table('vendor_fee_requests')->where('id', $request->id)->update(['status' => 'paid']);
        rejects(static fn () => $billing->submitSettled((int) $request->id, 101));
        same('pending_payment', row($db, 'vendors', 17)->status);
    };
    $tests['zero fee submits without pretending money was paid'] = static function (): void {
        [$db, $billing] = fixture([], '0.000');
        $request = $billing->prepare(17, 101, 'registration');
        $billing->submitSettled((int) $request->id, 101);
        $submitted = row($db, 'vendor_fee_requests', (int) $request->id);
        same('not_required', $submitted->status);
        same('submitted', row($db, 'vendors', 17)->status);
        same(true, $billing->isSettled($submitted));
        same(0, (int) $db->pdo->query('SELECT COUNT(*) FROM pod_eservice_payments')->fetchColumn());
    };
    $tests['settled fee requires verified and applied Bank Muscat ledger'] = static function (): void {
        foreach ([['status' => 'pending'], ['provider' => 'stripe'], ['deleted' => 1], ['verified_at' => null], ['settlement_status' => 'pending'], ['settlement_status' => 'failed']] as $mismatch) {
            [$db, $billing] = fixture();
            $request = settled($db, $billing, $billing->prepare(17, 101, 'registration'), $mismatch);
            same(false, $billing->isSettled($request), json_encode($mismatch));
        }
    };
    $tests['settled fee binds vendor subject amount currency and period'] = static function (): void {
        foreach ([['vendor_id' => 99], ['subject_id' => 99], ['subject_type' => 'vendor_renewal'], ['amount' => '10.126'], ['currency' => 'USD'], ['metadata' => json_encode(['vendor_fee_request_id' => 1, 'period_key' => 'different-period'])], ['metadata' => json_encode(['vendor_fee_request_id' => 2])]] as $mismatch) {
            [$db, $billing] = fixture();
            $request = settled($db, $billing, $billing->prepare(17, 101, 'registration'), $mismatch);
            same(false, $billing->isSettled($request), json_encode($mismatch));
        }
    };
    $tests['matching bank payment submits and extends initial registration on approval'] = static function (): void {
        [$db, $billing] = fixture();
        $request = settled($db, $billing, $billing->prepare(17, 101, 'registration'));
        same(true, $billing->isSettled($request));
        $billing->submitSettled((int) $request->id, 101);
        $dates = $billing->review(row($db, 'vendors', 17), 'approved', 500);
        same(gmdate('Y-m-d'), $dates['registration_valid_from']);
        same((new \DateTimeImmutable(gmdate('Y-m-d')))->modify('+365 days')->format('Y-m-d'), $dates['registration_valid_to']);
        same('approved', row($db, 'vendor_fee_requests', (int) $request->id)->review_status);
    };
    $tests['future renewal extends previous expiry and preserves original start'] = static function (): void {
        $expiry = gmdate('Y-m-d', strtotime('+60 days'));
        [$db, $billing] = fixture(['status' => 'approved', 'registration_valid_from' => '2025-01-01', 'registration_valid_to' => $expiry]);
        settled($db, $billing, $billing->prepare(17, 101, 'renewal'));
        $dates = $billing->review(row($db, 'vendors', 17), 'approved', 500);
        same('2025-01-01', $dates['registration_valid_from']);
        same((new \DateTimeImmutable($expiry))->modify('+365 days')->format('Y-m-d'), $dates['registration_valid_to']);
    };
    $tests['expired renewal starts extension today rather than losing elapsed days'] = static function (): void {
        [$db, $billing] = fixture(['status' => 'expired', 'registration_valid_from' => '2020-01-01', 'registration_valid_to' => '2021-01-01']);
        settled($db, $billing, $billing->prepare(17, 101, 'renewal'));
        $dates = $billing->review(row($db, 'vendors', 17), 'approved', 500);
        same((new \DateTimeImmutable(gmdate('Y-m-d')))->modify('+365 days')->format('Y-m-d'), $dates['registration_valid_to']);
    };
    $tests['unpaid request cannot be approved'] = static function (): void {
        [$db, $billing] = fixture();
        $billing->prepare(17, 101, 'registration');
        rejects(static fn () => $billing->review(row($db, 'vendors', 17), 'approved', 500));
    };
    $tests['settlement updates request and vendor once for repeated callback'] = static function (): void {
        [$db, $billing] = fixture();
        $request = $billing->prepare(17, 101, 'registration');
        $paid = payment($db, $billing, $request);
        Vendor_payment_settlement::apply($db, $paid, 'BANK-REFERENCE-81');
        $updated = row($db, 'vendor_fee_requests', (int) $request->id);
        same('paid', $updated->status);
        same(81, (int) $updated->payment_id);
        same('submitted', $updated->review_status);
        same('submitted', row($db, 'vendors', 17)->status);
        $historyCount = (int) $db->pdo->query('SELECT COUNT(*) FROM pod_vendor_status_histories')->fetchColumn();
        Vendor_payment_settlement::apply($db, $paid, 'BANK-REFERENCE-81');
        same($historyCount, (int) $db->pdo->query('SELECT COUNT(*) FROM pod_vendor_status_histories')->fetchColumn());
        $paid->id = 82;
        rejects(static fn () => Vendor_payment_settlement::apply($db, $paid, 'BANK-REFERENCE-82'));
    };
    $tests['settlement rejects stale group closed request and mismatched payment'] = static function (): void {
        foreach (['group', 'closed', 'vendor', 'subject', 'amount', 'currency', 'period'] as $mismatch) {
            [$db, $billing] = fixture();
            $request = $billing->prepare(17, 101, 'registration');
            $paid = payment($db, $billing, $request);
            if ($mismatch === 'group') { $db->table('vendors')->where('id', 17)->update(['vendor_group_id' => 99]); }
            if ($mismatch === 'closed') { $db->table('vendor_fee_requests')->where('id', $request->id)->update(['review_status' => 'rejected']); }
            if ($mismatch === 'vendor') { $paid->vendor_id = 99; }
            if ($mismatch === 'subject') { $paid->subject_id = 99; }
            if ($mismatch === 'amount') { $paid->amount = '0.001'; }
            if ($mismatch === 'currency') { $paid->currency = 'USD'; }
            if ($mismatch === 'period') { $paid->metadata = json_encode(['vendor_fee_request_id' => $request->id, 'period_key' => 'other']); }
            rejects(static fn () => Vendor_payment_settlement::apply($db, $paid, 'BANK-REFERENCE-81'));
            same('pending', row($db, 'vendor_fee_requests', (int) $request->id)->status, $mismatch);
        }
    };

    $tests['approved legacy vendor without expiry is offered renewal'] = static function (): void {
        [$db, $billing] = fixture(['status' => 'approved', 'registration_valid_to' => null]);
        same('renewal', $billing->summary(row($db, 'vendors', 17))['type']);
        same('renewal', $billing->prepare(17, 101, 'renewal')->fee_type);
    };
    $tests['changed registration period blocks checkout reuse settlement and review'] = static function (): void {
        foreach (['registration', 'renewal'] as $type) {
            foreach (['prepare', 'settle', 'submit', 'review'] as $action) {
                [$db, $billing] = fixture($type === 'renewal'
                    ? ['status' => 'approved', 'registration_valid_to' => '2027-01-01'] : []);
                $request = $billing->prepare(17, 101, $type);
                $paid = payment($db, $billing, $request);
                $db->table('vendors')->where('id', 17)->update(['registration_valid_to' => '2028-01-01']);
                rejects(static function () use ($action, $db, $billing, $request, $paid, $type): void {
                    if ($action === 'prepare') { $billing->prepare(17, 101, $type); }
                    if ($action === 'settle') { Vendor_payment_settlement::apply($db, $paid, 'BANK-81'); }
                    if ($action === 'submit') { $billing->submitSettled((int) $request->id, 101); }
                    if ($action === 'review') { $billing->review(row($db, 'vendors', 17), 'approved', 500); }
                });
            }
        }
    };

    $failures = [];
    foreach ($tests as $name => $test) {
        try { $test(); } catch (\Throwable $error) { $failures[] = $name . ': ' . $error->getMessage(); }
    }
    foreach ($failures as $failure) { fwrite(STDERR, 'FAIL ' . $failure . PHP_EOL); }
    echo sprintf('Vendor billing behavior: %d passed, %d failed. SQLite in-memory fixtures only.%s', count($tests) - count($failures), count($failures), PHP_EOL);
    exit($failures ? 1 : 0);
}
