<?php

declare(strict_types=1);

namespace CodeIgniter\Database { abstract class BaseConnection {} }
namespace App\Libraries\Payments {
    // This test exercises clearance and real ledger predicates without loading
    // the gateway or obtaining any application/database credentials.
    final class Eservice_payment_manager { public const GATE_PASS_FEE = 'gate_pass_fee'; }
}
namespace {
    require_once __DIR__ . '/../app/Libraries/Payments/Payment_amount.php';
    require_once __DIR__ . '/../app/Libraries/Payments/Eservice_payment_state.php';
    require_once __DIR__ . '/../app/Libraries/Payments/Gate_pass_payment_clearance.php';

    final class GatePassClearanceMemoryDb extends \CodeIgniter\Database\BaseConnection
    {
        public \PDO $pdo;
        public function __construct()
        {
            $this->pdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            $this->pdo->exec('CREATE TABLE pod_eservice_payments (id INTEGER PRIMARY KEY, subject_type TEXT, subject_id INTEGER, vendor_id INTEGER, provider TEXT, status TEXT, amount TEXT, currency TEXT, verified_at TEXT, settlement_status TEXT, deleted INTEGER DEFAULT 0)');
            $this->pdo->exec('CREATE TABLE pod_gate_pass_request_approvals (id INTEGER PRIMARY KEY, gate_pass_request_id INTEGER, stage TEXT, decision TEXT, decided_by INTEGER, decided_at TEXT, deleted INTEGER DEFAULT 0)');
        }
        public function prefixTable(string $name): string { return 'pod_' . $name; }
        public function query(string $sql, array $params = []): object
        {
            $statement = $this->pdo->prepare(str_replace('<=>', 'IS', $sql));
            $statement->execute($params);
            return new class($statement) {
                public function __construct(private \PDOStatement $statement) {}
                public function getRow(): ?object { return $this->statement->fetch(\PDO::FETCH_OBJ) ?: null; }
            };
        }
        public function tableExists(string $name): bool
        {
            return (bool) $this->query("SELECT name FROM sqlite_master WHERE type='table' AND name=?", [$this->prefixTable($name)])->getRow();
        }
        public function fieldExists(string $field, string $table): bool
        {
            return in_array($field, array_column($this->pdo->query('PRAGMA table_info(' . $this->prefixTable($table) . ')')->fetchAll(\PDO::FETCH_ASSOC), 'name'), true);
        }
        public function seed(string $table, array $data): void
        {
            $this->query('INSERT INTO ' . $this->prefixTable($table) . ' (' . implode(',', array_keys($data)) . ') VALUES (' . implode(',', array_fill(0, count($data), '?')) . ')', array_values($data));
        }
    }

    function fixture(array $changes = []): array
    {
        $db = new GatePassClearanceMemoryDb();
        $request = (object) array_merge(['id' => 17, 'fee_amount' => '3.125', 'currency' => 'OMR', 'deleted' => 0,
            'fee_is_waived' => 0, 'fee_waiver_requested' => 0, 'fee_waiver_commercial_status' => null,
            'fee_waived_by' => null, 'fee_waived_at' => null, 'fee_waived_reason' => null], $changes);
        return [$db, new \App\Libraries\Payments\Gate_pass_payment_clearance($db), $request];
    }
    function paid(GatePassClearanceMemoryDb $db, array $changes = []): void
    {
        $db->seed('eservice_payments', array_merge(['id' => 81, 'subject_type' => 'gate_pass_fee', 'subject_id' => 17,
            'vendor_id' => null, 'provider' => 'bank_muscat', 'status' => 'paid', 'amount' => '3.125', 'currency' => 'OMR',
            'verified_at' => '2026-09-05 10:00:00', 'settlement_status' => 'applied', 'deleted' => 0], $changes));
    }
    function same(bool $expected, bool $actual, string $message): void
    {
        if ($expected !== $actual) { throw new \RuntimeException($message); }
    }
    $tests = [];
    $tests['positive fee has no legacy stage/status exemption'] = static function (): void {
        [$db, $policy, $request] = fixture(['stage' => 'rop', 'status' => 'security_approved']);
        same(false, $policy->allowsApproval($request), 'Existing bypass status must not authorize new issuance.');
    };
    $tests['only an explicit numeric zero is free'] = static function (): void {
        foreach (['0', '0.0', '0.000'] as $amount) {
            [$db, $policy, $request] = fixture(['fee_amount' => $amount]);
            same(true, $policy->allowsApproval($request), 'Explicit zero should be eligible.');
        }
        foreach ([null, '', '-1', 'not set', '0.0001'] as $amount) {
            [$db, $policy, $request] = fixture(['fee_amount' => $amount]);
            same(false, $policy->allowsApproval($request), 'Invalid/missing amount must not become free.');
        }
    };
    $tests['verified applied Bank Muscat fee permits approval'] = static function (): void {
        [$db, $policy, $request] = fixture(); paid($db);
        same(true, $policy->allowsApproval($request), 'Matching verified payment should clear.');
    };
    $tests['ledger binds request vendor amount currency and bank verification'] = static function (): void {
        foreach ([['subject_id' => 18], ['subject_type' => 'tender_fee'], ['vendor_id' => 1], ['amount' => '3.124'], ['currency' => 'USD'],
            ['status' => 'processing'], ['verified_at' => null], ['provider' => 'stripe'], ['settlement_status' => 'review_required'], ['deleted' => 1]] as $change) {
            [$db, $policy, $request] = fixture(); paid($db, $change);
            same(false, $policy->allowsApproval($request), 'Rejected mismatch: ' . json_encode($change));
        }
    };
    $tests['missing ledger schema blocks chargeable issuance'] = static function (): void {
        [$db, $policy, $request] = fixture();
        $db->pdo->exec('DROP TABLE pod_eservice_payments');
        same(false, $policy->allowsApproval($request), 'Missing ledger must fail closed.');
    };
    $tests['department waiver request is not a completed Commercial waiver'] = static function (): void {
        [$db, $policy, $request] = fixture(['fee_is_waived' => 1, 'fee_waiver_requested' => 1, 'fee_waiver_commercial_status' => 'pending']);
        same(false, $policy->allowsApproval($request), 'Pending department waiver must be resolved.');
    };
    $tests['waiver flag without approval audit does not clear a fee'] = static function (): void {
        [$db, $policy, $request] = fixture(['fee_is_waived' => 1, 'fee_waived_by' => 101, 'fee_waived_at' => '2026-09-05 09:00:00', 'fee_waived_reason' => 'Approved contractor']);
        same(false, $policy->allowsApproval($request), 'Waiver requires an actual Commercial approval entry.');
    };
    $tests['both direct and department-requested Commercial waivers are honored'] = static function (): void {
        foreach ([null, 'approved'] as $status) {
            [$db, $policy, $request] = fixture(['fee_is_waived' => 1, 'fee_waiver_commercial_status' => $status,
                'fee_waived_by' => 101, 'fee_waived_at' => '2026-09-05 09:00:00', 'fee_waived_reason' => 'Approved contractor']);
            $db->seed('gate_pass_request_approvals', ['id' => 8, 'gate_pass_request_id' => 17, 'stage' => 'commercial', 'decision' => 'approved', 'decided_by' => 102, 'decided_at' => '2026-09-05 09:00:01']);
            same(true, $policy->allowsApproval($request), 'Recorded Commercial waiver should clear.');
        }
    };
    $tests['waiver history binds request actor time stage and decision'] = static function (): void {
        foreach ([['gate_pass_request_id' => 18], ['stage' => 'department'], ['decision' => 'fee_waiver_rejected'], ['decided_by' => 0], ['decided_at' => '2026-09-05 08:59:00'], ['deleted' => 1]] as $change) {
            [$db, $policy, $request] = fixture(['fee_is_waived' => 1, 'fee_waived_by' => 101, 'fee_waived_at' => '2026-09-05 09:00:00', 'fee_waived_reason' => 'Approved contractor']);
            $db->seed('gate_pass_request_approvals', array_merge(['id' => 8, 'gate_pass_request_id' => 17, 'stage' => 'commercial', 'decision' => 'approved', 'decided_by' => 101, 'decided_at' => '2026-09-05 09:00:01'], $change));
            same(false, $policy->allowsApproval($request), 'Rejected waiver mismatch: ' . json_encode($change));
        }
    };
    $tests['deleted request cannot be cleared even with a bank payment'] = static function (): void {
        [$db, $policy, $request] = fixture(['deleted' => 1]); paid($db);
        same(false, $policy->allowsApproval($request), 'Deleted request must stay ineligible.');
    };
    $failed = 0;
    foreach ($tests as $name => $test) {
        try { $test(); } catch (\Throwable $error) { $failed++; fwrite(STDERR, 'FAIL ' . $name . ': ' . $error->getMessage() . PHP_EOL); }
    }
    echo sprintf('Gate-pass payment clearance: %d passed, %d failed. SQLite in-memory fixtures only.%s', count($tests) - $failed, $failed, PHP_EOL);
    exit($failed ? 1 : 0);
}
