<?php
// Real sanitizer + strict SQL regression. Only generated temporary tables are written.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
chdir(dirname(__DIR__));
define('FCPATH', getcwd() . DIRECTORY_SEPARATOR);
require 'app/Config/Paths.php';
require 'system/Boot.php';
class StrictSqlSanitizationBoot extends CodeIgniter\Boot {
    static function init(): void {
        $p = new Config\Paths();
        static::definePathConstants($p); static::loadConstants(); static::loadDotEnv($p);
        static::defineEnvironment(); static::loadCommonFunctions(); static::loadAutoloader();
    }
}
StrictSqlSanitizationBoot::init();
define('CI_DEBUG', true);
$cfg = (new Config\Database())->default;
if (ENVIRONMENT === 'production' || !in_array($cfg['hostname'], ['localhost', '127.0.0.1'], true)) {
    throw new RuntimeException('Local development only');
}
helper(['general', 'url']);
config('Rise')->app_settings_array = ['disable_html_input' => '0'];
$db = db_connect($cfg, false);
$db->query("SET SESSION sql_mode='STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
$table = 'codex_null_regression_' . bin2hex(random_bytes(5));
$db->query('CREATE TEMPORARY TABLE `' . $table . '` (id INT PRIMARY KEY, blocked_by BIGINT NULL, blocked_at DATETIME NULL, amount DECIMAL(12,3) NULL, note TEXT NULL)');
$count = 0;
$check = static function ($condition, $message) use (&$count): void { $count++; if (!$condition) throw new RuntimeException($message); };
try {
    foreach ([0,1] as $escape) {
        config('Rise')->app_settings_array['disable_html_input'] = (string)$escape;
        $input = ['blocked_by'=>null, 'blocked_at'=>null, 'amount'=>null, 'note'=>null];
        $clean = clean_data($input);
        $check($clean === $input, 'Null values must survive both cleaning modes');
        $db->query('INSERT INTO `' . $table . '` (id,blocked_by,blocked_at,amount,note) VALUES (?,?,?,?,?)', [$escape+1, ...array_values($clean)]);
        $row = $db->query('SELECT blocked_by,blocked_at,amount,note FROM `' . $table . '` WHERE id=?', [$escape+1])->getRowArray();
        $check($row === $input, 'Strict SQL inserts preserve absent values');
        $db->query('UPDATE `' . $table . '` SET blocked_by=123, blocked_at=NOW(), amount=10, note=? WHERE id=?', ['blocked',$escape+1]);
        $db->query('UPDATE `' . $table . '` SET blocked_by=?,blocked_at=?,amount=?,note=? WHERE id=?', [...array_values($clean),$escape+1]);
        $check($db->query('SELECT blocked_by FROM `' . $table . '` WHERE id=?',[$escape+1])->getRow()->blocked_by === null, 'Unblock/reset writes NULL');
        $nested = clean_data(['nested'=>['value'=>null,'blank'=>'','zero'=>'0']]);
        $check($nested['nested'] === ['value'=>null,'blank'=>'','zero'=>'0'], 'Nested null, blank and zero remain distinct');
        $malicious = clean_data('<img src=x onerror=alert(1)><script>alert(2)</script>');
        $check(!preg_match('/<script|onerror\s*=/i',$malicious), 'String XSS filtering remains enabled');
        $check(clean_data(null) === null, 'Scalar null survives');
    }
    // Run the real legacy timestamp backfill against a temporary shadow table.
    $prefix = 'codex_migration_' . bin2hex(random_bytes(5)) . '_';
    $db->setPrefix($prefix);
    $migrationTable = $db->prefixTable('gate_pass_requests');
    $db->query('CREATE TEMPORARY TABLE `' . $migrationTable . '` (id INT PRIMARY KEY, submitted_at DATETIME NULL, created_at DATETIME NULL, deleted TINYINT DEFAULT 0)');
    try {
        $db->query("SET SESSION sql_mode=''");
        $db->query('INSERT INTO `' . $migrationTable . '` VALUES (1,?,?,0),(2,NULL,NULL,0),(3,?,?,0)', ['2026-09-01 10:00:00',null,'0000-00-00 00:00:00','0000-00-00 00:00:00']);
        $db->query("SET SESSION sql_mode='STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        require APPPATH . 'Database/Migrations/2026_04_14_120000_add_created_at_to_gate_pass_requests.php';
        $reflection = new ReflectionClass(App\Database\Migrations\Add_created_at_to_gate_pass_requests::class);
        $migration = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('db')->setValue($migration, $db);
        $migration->up();
        $rows = $db->query('SELECT created_at FROM `' . $migrationTable . '` ORDER BY id')->getResultArray();
        $check($rows[0]['created_at'] === '2026-09-01 10:00:00', 'Migration backfills a valid submitted date under strict mode');
        $check($rows[1]['created_at'] === null, 'Migration preserves absent dates');
        $check($rows[2]['created_at'] === '0000-00-00 00:00:00', 'Migration safely skips legacy zero dates');
        $migration->up();
        $check($db->query('SELECT created_at FROM `' . $migrationTable . '` ORDER BY id')->getResultArray() === $rows, 'Migration is idempotent');
    } finally {
        $db->query('DROP TEMPORARY TABLE `' . $migrationTable . '`');
    }
    $waiverTable = $db->prefixTable('gate_pass_request_approvals');
    $db->query("CREATE TEMPORARY TABLE `{$waiverTable}` (id INT PRIMARY KEY, decision ENUM('approved','rejected','returned','legacy') NULL DEFAULT NULL COMMENT 'History decision')");
    try {
        $db->query("INSERT INTO `{$waiverTable}` VALUES (1,'legacy'),(2,NULL)");
        require APPPATH . 'Database/Migrations/2026_09_13_180000_gate_pass_fee_waiver_decision.php';
        $reflection = new ReflectionClass(App\Database\Migrations\Gate_pass_fee_waiver_decision::class);
        $migration = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('db')->setValue($migration, $db);
        $migration->up();
        $field = $db->query("SHOW FULL COLUMNS FROM `{$waiverTable}` WHERE Field='decision'")->getRow();
        $check(str_contains($field->Type, "'legacy'") && str_contains($field->Type, "'fee_waiver_rejected'"), 'Enum expansion preserves existing values');
        $check($field->Null === 'YES' && $field->Default === null && $field->Comment === 'History decision', 'Enum metadata preserved');
        $db->query("INSERT INTO `{$waiverTable}` VALUES (3,'fee_waiver_rejected')");
        $check($db->query("SELECT decision FROM `{$waiverTable}` WHERE id=3")->getRow()->decision === 'fee_waiver_rejected', 'Strict SQL accepts waiver history');
        $migration->up();
        $migration->down();
        $check($db->query("SELECT decision FROM `{$waiverTable}` WHERE id=1")->getRow()->decision === 'legacy', 'Rerun and rollback do not destroy legacy history');
        $check($db->query("SELECT COUNT(*) AS n FROM `{$waiverTable}`")->getRow()->n == 3, 'Audit rows survive repeated migration');
    } finally {
        $db->query('DROP TEMPORARY TABLE `' . $waiverTable . '`');
    }
    echo "StrictSqlSanitizationDatabaseTest: $count checks passed\n";
} finally {
    $db->query('DROP TEMPORARY TABLE `' . $table . '`');
    $db->close();
}
