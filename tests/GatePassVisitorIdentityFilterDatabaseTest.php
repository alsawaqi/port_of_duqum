<?php

// Real local MySQL query regression. Only connection-local temporary tables are used.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
chdir(dirname(__DIR__));
define('FCPATH', getcwd() . DIRECTORY_SEPARATOR);
require FCPATH . 'app/Config/Paths.php';
require FCPATH . 'system/Boot.php';
class VisitorFilterBootstrap extends CodeIgniter\Boot {
    public static function init(): void {
        $paths = new Config\Paths();
        static::definePathConstants($paths);
        static::loadConstants();
        static::loadDotEnv($paths);
        static::defineEnvironment();
        static::loadCommonFunctions();
        static::loadAutoloader();
    }
}
VisitorFilterBootstrap::init();
$config = (new Config\Database())->default;
if (ENVIRONMENT === 'production' || !in_array($config['hostname'], ['localhost', '127.0.0.1'], true)) {
    throw new RuntimeException('This test is restricted to the local development database.');
}
if (!defined('CI_DEBUG')) { define('CI_DEBUG', true); }
helper('general');
$config['DBPrefix'] = 'visitor_filter_' . bin2hex(random_bytes(6)) . '_';
$db = db_connect($config, false);
$tables = [
    'gate_pass_requests' => 'id INT PRIMARY KEY, reference VARCHAR(50), company_id INT, department_id INT, gate_pass_purpose_id INT, requester_id INT, status VARCHAR(30), stage VARCHAR(30), deleted TINYINT DEFAULT 0, visit_from DATETIME, visit_to DATETIME, created_at DATETIME, submitted_at DATETIME',
    'companies' => 'id INT PRIMARY KEY, name VARCHAR(50)',
    'departments' => 'id INT PRIMARY KEY, name VARCHAR(50)',
    'gate_pass_purposes' => 'id INT PRIMARY KEY, name VARCHAR(50)',
    'users' => 'id INT PRIMARY KEY, first_name VARCHAR(50), last_name VARCHAR(50), phone VARCHAR(30), alternative_phone VARCHAR(30)',
    'gate_pass_request_visitors' => 'id INT PRIMARY KEY, gate_pass_request_id INT, id_number VARCHAR(100), id_type VARCHAR(30), nationality VARCHAR(50), deleted TINYINT DEFAULT 0',
];
try {
    foreach ($tables as $table => $columns) {
        $db->query('CREATE TEMPORARY TABLE `' . $db->prefixTable($table) . '` (' . $columns . ') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }
    foreach (range(1, 7) as $id) {
        $db->table('gate_pass_requests')->insert([
            'id' => $id, 'reference' => 'TEST-' . $id,
            'company_id' => $id === 2 ? 2 : 1, 'department_id' => $id === 2 ? 20 : 10,
            'gate_pass_purpose_id' => 4, 'requester_id' => $id === 2 ? 22 : 11,
            'status' => in_array($id, [2, 7], true) ? 'rop_approved' : 'issued',
            'stage' => $id === 7 ? 'issued' : ($id === 2 ? 'rop' : 'completed'),
            'deleted' => $id === 3 ? 1 : 0, 'visit_from' => '2026-09-08 08:00:00',
            'visit_to' => '2026-09-08 16:00:00', 'created_at' => '2026-09-07 08:00:00',
        ]);
    }
    $visitors = [
        [1, '00123456', 'Civil ID', 'Oman', 0],
        [1, '00123456', 'Civil ID', 'Oman', 0], // Request must not be duplicated.
        [1, 'P7654321', 'Passport', 'India', 0],
        [2, '00123456', 'Civil ID', 'Oman', 0],
        [3, '00123456', 'Civil ID', 'Oman', 0], // Deleted request.
        [4, '00123456', 'Civil ID', 'Oman', 1], // Deleted visitor.
        [5, 'AB%_!9', 'Passport', 'Oman', 0],
        [6, "P' OR 1=1 --", 'Passport', 'Oman', 0],
        [7, 'P7654321', 'Passport', 'Oman', 0],
        [7, 'OTHER', 'Passport', 'India', 0], // Nationality must match the same visitor.
    ];
    foreach ($visitors as $i => [$request, $number, $type, $nationality, $deleted]) {
        $db->table('gate_pass_request_visitors')->insert([
            'id' => $i + 1, 'gate_pass_request_id' => $request, 'id_number' => $number,
            'id_type' => $type, 'nationality' => $nationality, 'deleted' => $deleted,
        ]);
    }
    $model = (new ReflectionClass(App\Models\Gate_pass_requests_model::class))->newInstanceWithoutConstructor();
    $property = new ReflectionProperty(App\Models\Crud_model::class, 'db');
    $property->setAccessible(true);
    $property->setValue($model, $db);
    $cases = [
        ['unfiltered list unchanged', [], [1, 2, 4, 5, 6, 7]],
        ['civil ID across multiple requests, no duplicates/deleted rows', ['visitor_identity' => '00123456'], [1, 2]],
        ['partial number', ['visitor_identity' => '1234'], [1, 2]],
        ['leading zero retained', ['visitor_identity' => '001'], [1, 2]],
        ['trim surrounding input spaces', ['visitor_identity' => ' 00123456 '], [1, 2]],
        ['passport case insensitive', ['visitor_identity' => 'p7654321'], [1, 7]],
        ['unknown identity', ['visitor_identity' => 'NO-MATCH'], []],
        ['percent is literal', ['visitor_identity' => '%'], [5]],
        ['underscore is literal', ['visitor_identity' => '_'], [5]],
        ['escape character is literal', ['visitor_identity' => '!'], [5]],
        ['whole punctuation identity', ['visitor_identity' => 'AB%_!9'], [5]],
        ['quoted content stays data', ['visitor_identity' => "P' OR 1=1 --"], [6]],
        ['SQL-like input does not expand results', ['visitor_identity' => "' OR 1=1 #"], []],
        ['company scope retained', ['visitor_identity' => '00123456', 'company_ids' => [2]], [2]],
        ['department scope retained', ['visitor_identity' => '00123456', 'department_ids' => [10]], [1]],
        ['requester scope retained', ['visitor_identity' => '00123456', 'requester_id' => 22], [2]],
        ['status combined', ['visitor_identity' => '00123456', 'status' => 'issued'], [1]],
        ['issued status includes ROP issuance representation', ['visitor_identity' => 'P7654321', 'status' => 'issued'], [1, 7]],
        ['visit date combined', ['visitor_identity' => '00123456', 'date_from' => '2026-09-09'], []],
        ['nationality applies to matching visitor', ['visitor_identity' => 'P7654321', 'nationality' => 'India'], [1]],
        ['existing nationality-only search', ['nationality' => 'India'], [1, 7]],
    ];
    foreach ($cases as [$label, $options, $expected]) {
        $actual = array_map('intval', array_column($model->get_details($options)->getResultArray(), 'id'));
        sort($actual);
        if ($actual !== $expected) {
            throw new RuntimeException($label . ': expected ' . json_encode($expected) . ', got ' . json_encode($actual));
        }
    }
    echo 'OK: ' . count($cases) . ' visitor identity query checks (local temporary tables only).' . PHP_EOL;
} finally {
    $db->close(); // Temporary tables disappear; no application rows were changed.
}
