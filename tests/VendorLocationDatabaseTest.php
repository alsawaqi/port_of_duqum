<?php

// Actual local database/controller regression. All fixtures roll back; no delivery.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
chdir(dirname(__DIR__));
define('FCPATH', getcwd() . DIRECTORY_SEPARATOR);
require FCPATH . 'app/Config/Paths.php';
require FCPATH . 'system/Boot.php';
class VendorLocationBootstrap extends CodeIgniter\Boot {
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
VendorLocationBootstrap::init();
$database = (new Config\Database())->default;
if (ENVIRONMENT === 'production' || $database['database'] !== 'bedotscpanel_poderp'
    || !in_array($database['hostname'], ['localhost', '127.0.0.1'], true)) {
    throw new RuntimeException('Restricted to the local development database.');
}
if (!defined('CI_DEBUG')) { define('CI_DEBUG', true); }
helper(['general', 'plugin', 'date_time', 'safe_serialization', 'url', 'language', 'email', 'form']);
require_once APPPATH . 'ThirdParty/PHP-Hooks/php-hooks.php';
config('Rise')->app_settings_array = ['language' => 'english', 'sms_notifications_enabled' => '0'];
$source = db_connect($database, false);
$test_database = 'codex_vendor_location_' . bin2hex(random_bytes(6));
$source->query('CREATE DATABASE `' . $test_database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
register_shutdown_function(static function () use ($source, $test_database): void {
    // The name is generated above, never taken from application configuration.
    if (!preg_match('/^codex_vendor_location_[a-f0-9]{12}$/D', $test_database)) { return; }
    $source->query('DROP DATABASE `' . $test_database . '`');
});
config('Database')->default['database'] = $test_database;
$db = db_connect('default');
foreach (['country', 'regions', 'cities', 'vendor_groups', 'vendor_grades', 'vendors',
    'vendor_users', 'users', 'currencies', 'activity_logs', 'vendor_fee_requests'] as $table) {
    $full = $db->prefixTable($table);
    if ($table === 'currencies' && !$source->tableExists($table)) {
        $db->query('CREATE TABLE `pod_currencies` (`id` INT PRIMARY KEY, `code` VARCHAR(3), `name` VARCHAR(100), `deleted` TINYINT DEFAULT 0)');
        continue;
    }
    $db->query('CREATE TABLE `' . $full . '` LIKE `' . $database['database'] . '`.`' . $full . '`');
    if (in_array($table, ['vendor_groups', 'vendor_grades', 'currencies'], true)) {
        $db->query('INSERT INTO `' . $full . '` SELECT * FROM `' . $database['database'] . '`.`' . $full . '`');
    }
}
foreach (['country_id' => 'country', 'region_id' => 'regions', 'city_id' => 'cities', 'vendor_grade_id' => 'vendor_grades'] as $field => $table) {
    $db->query('ALTER TABLE `pod_vendors` ADD FOREIGN KEY (`' . $field . '`) REFERENCES `' . $db->prefixTable($table) . '` (`id`)');
}
$db->transException(true);
$checks = 0;
$check = static function ($condition, string $label) use (&$checks): void {
    $checks++;
    if (!$condition) { throw new RuntimeException($label); }
};
$clone = static function (string $table, array $changes) use ($db, $source): int {
    $row = $source->table($table)->get(1)->getRowArray();
    if (!$row) { throw new RuntimeException('Missing local fixture source: ' . $table); }
    unset($row['id']);
    if (in_array($table, ['country', 'regions', 'cities'], true)) {
        $row['code'] = 'T' . (1 + $db->table($table)->countAllResults());
        unset($changes['code']);
    }
    foreach ($db->query('SHOW COLUMNS FROM `' . $db->prefixTable($table) . '`')->getResult() as $field) {
        if (str_contains($field->Extra, 'GENERATED')) { unset($row[$field->Field]); }
    }
    $db->table($table)->insert(array_replace($row, $changes));
    return (int) $db->insertID();
};
$controller = static function (array $post) use ($db) {
    $_POST = $_REQUEST = $post;
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $app = config('App');
    $request = new CodeIgniter\HTTP\IncomingRequest(
        $app, new CodeIgniter\HTTP\SiteURI($app), null, new CodeIgniter\HTTP\UserAgent()
    );
    $request->setMethod('POST');
    $request->setHeader('X-Requested-With', 'XMLHttpRequest');
    $request->setGlobal('post', $post);
    $request->setGlobal('request', $post);
    $request->setLocale('english');
    Config\Services::injectMock('request', $request);
    Config\Services::resetSingle('validation');
    $instance = (new ReflectionClass(App\Controllers\Vendors::class))->newInstanceWithoutConstructor();
    $instance->initController($request, new CodeIgniter\HTTP\Response($app), service('logger'));
    $instance->login_user = (object) ['id' => 1, 'is_admin' => 1, 'user_type' => 'staff', 'permissions' => []];
    foreach (['db' => $db, 'Vendors_model' => new App\Models\Vendors_model(),
        'Vendor_groups_model' => new App\Models\Vendor_groups_model(),
        'Vendor_grades_model' => new App\Models\Vendor_grades_model()] as $key => $value) {
        $property = new ReflectionProperty($instance, $key);
        $property->setAccessible(true);
        $property->setValue($instance, $value);
    }
    $instance->Users_model = new App\Models\Users_model();
    $template = new class {
        public function view($name, $data) { return $data; }
    };
    $property = new ReflectionProperty($instance, 'template');
    $property->setAccessible(true);
    $property->setValue($instance, $template);
    return $instance;
};
$save = static function (array $post) use ($controller): array {
    ob_start();
    try {
        $response = $controller($post)->save();
        $output = ob_get_contents();
    } finally { ob_end_clean(); }
    return json_decode($response ? $response->getBody() : $output, true, 512, JSON_THROW_ON_ERROR);
};
$stamp = strtoupper(bin2hex(random_bytes(5)));
$db->transBegin();
$vendor = 0;
try {
    $check((int) $db->query('SELECT @@foreign_key_checks AS enabled')->getRow()->enabled === 1, 'Foreign keys stay enabled');
    $check($db->query("SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pod_vendors' AND COLUMN_NAME = 'region_id' AND REFERENCED_TABLE_NAME = 'pod_regions'")->getNumRows() === 1, 'Real vendor region FK exists');
    $country = $clone('country', ['name' => 'LOCATION TEST ' . $stamp, 'code' => substr($stamp, 0, 2), 'is_active' => 1, 'deleted' => 0]);
    $otherCountry = $clone('country', ['name' => 'OTHER LOCATION ' . $stamp, 'code' => substr($stamp, 2, 2), 'is_active' => 1, 'deleted' => 0]);
    $region = $clone('regions', ['country_id' => $country, 'name' => 'LOCATION REGION ' . $stamp, 'is_active' => 1, 'deleted' => 0]);
    $otherRegion = $clone('regions', ['country_id' => $otherCountry, 'name' => 'OTHER REGION ' . $stamp, 'is_active' => 1, 'deleted' => 0]);
    $city = $clone('cities', ['regions_id' => $region, 'name' => 'LOCATION CITY ' . $stamp, 'is_active' => 1, 'deleted' => 0]);
    $otherCity = $clone('cities', ['regions_id' => $otherRegion, 'name' => 'OTHER CITY ' . $stamp, 'is_active' => 1, 'deleted' => 0]);
    $vendor = $clone('vendors', ['vendor_name' => 'LOCATION TEST ' . $stamp, 'cr_number' => 'LOC' . $stamp,
        'email' => strtolower($stamp) . '@example.invalid', 'status' => 'new', 'deleted' => 0,
        'country_id' => $country, 'region_id' => $region, 'city_id' => $city]);
    $original = $db->table('vendors')->where('id', $vendor)->get()->getRowArray();
    $post = array_intersect_key($original, array_flip(['id', 'vendor_group_id', 'vendor_grade_id', 'vendor_name',
        'email', 'cr_number', 'address', 'po_box', 'postal_code', 'country_id', 'region_id', 'city_id']));
    $post = array_merge($post, ['currency' => 'OMR', 'payment_terms' => '45', 'vendor_grade_id' => '']);
    $modal = $controller(['id' => $vendor])->modal_form();
    $check(isset($modal['regions_dropdown'][$region]) && !isset($modal['regions_dropdown'][$otherRegion]), 'Edit loads only regions belonging to saved country');
    $check(isset($modal['cities_dropdown'][$city]) && !isset($modal['cities_dropdown'][$otherCity]), 'Edit loads only cities belonging to saved region');
    $check(str_contains(form_dropdown('region_id', $modal['regions_dropdown'], $modal['model_info']->region_id), 'value="' . $region . '" selected="selected"'), 'Saved region is selected in rendered dropdown');
    $check(str_contains(form_dropdown('city_id', $modal['cities_dropdown'], $modal['model_info']->city_id), 'value="' . $city . '" selected="selected"'), 'Saved city is selected in rendered dropdown');

    $getRow = static fn () => $db->table('vendors')->where('id', $vendor)->get()->getRowArray();
    $result = $save($post);
    $check($result['success'] ?? false, 'Save existing complete location: ' . ($result['message'] ?? ''));
    $row = $getRow();
    $check((int) $row['region_id'] === $region && (int) $row['city_id'] === $city, 'Location persists on ordinary edit');
    $check($row['vendor_grade_id'] === null, 'Empty vendor grade saves SQL NULL');
    foreach ([['country_id' => '', 'region_id' => '', 'city_id' => ''],
        ['country_id' => $country, 'region_id' => '', 'city_id' => ''],
        ['country_id' => $country, 'region_id' => $region, 'city_id' => '']] as $location) {
        $result = $save(array_replace($post, $location));
        $check($result['success'] ?? false, 'Optional location save succeeds: ' . ($result['message'] ?? ''));
        $row = $getRow();
        foreach ($location as $field => $value) {
            $check($value === '' ? $row[$field] === null : (int) $row[$field] === $value, $field . ' retains correct NULL/ID type');
        }
    }
    $result = $save($post);
    $check($result['success'] ?? false, 'Can restore complete valid location');
    foreach ([
        ['payment_terms', '', 'Payment Terms'], ['payment_terms', '30', 'Payment Terms'],
        ['currency', '', 'Currency'], ['vendor_name', '', 'Vendor Name'],
        ['email', 'not-an-email', 'Email'], ['vendor_group_id', '', 'Vendor Group'],
    ] as [$field, $value, $label]) {
        $before = $getRow();
        $result = $save(array_replace($post, [$field => $value]));
        $check(!($result['success'] ?? true) && ($result['field'] ?? '') === $field, 'Validation identifies ' . $field);
        $check(!empty($result['errors'][$field]) && stripos($result['message'], $label) !== false, 'Validation uses readable label for ' . $field);
        $check($getRow() === $before, 'Invalid ' . $field . ' leaves the complete vendor row unchanged');
    }
    $refuse = static function ($changes, $field) use ($save, $post, $getRow, $check): void {
        $before = $getRow();
        $result = $save(array_replace($post, $changes));
        $check(!($result['success'] ?? true) && ($result['field'] ?? '') === $field, 'Reject invalid ' . $field);
        $check(str_contains($result['message'] ?? '', 'Please select a valid'), 'Location error is readable, without SQL');
        $check($getRow() === $before, 'Refused location leaves complete vendor row unchanged');
    };
    foreach (['country_id', 'region_id', 'city_id'] as $field) {
        foreach (['0', '-1', '1.5', '2147483647'] as $invalid) { $refuse([$field => $invalid], $field); }
    }
    $refuse(['country_id' => ''], 'region_id');
    $refuse(['region_id' => ''], 'city_id');
    $refuse(['region_id' => $otherRegion], 'region_id');
    $refuse(['city_id' => $otherCity], 'city_id');
    foreach (['country' => $country, 'regions' => $region, 'cities' => $city] as $table => $id) {
        $field = ['country' => 'country_id', 'regions' => 'region_id', 'cities' => 'city_id'][$table];
        foreach (['is_active' => 0, 'deleted' => 1] as $flag => $value) {
            $db->table($table)->where('id', $id)->update([$flag => $value]);
            $refuse([], $field);
            $db->table($table)->where('id', $id)->update([$flag => 1 - $value]);
        }
    }
    // Admin create uses the same location handling and a real existing identity.
    $identity = $clone('users', ['email' => strtolower($stamp) . '-owner@example.invalid',
        'user_type' => 'staff', 'status' => 'active', 'disable_login' => 0, 'deleted' => 0]);
    $create = array_replace($post, ['id' => '', 'cr_number' => 'NEW' . $stamp,
        'country_id' => $country, 'region_id' => '', 'city_id' => '',
        'user_email' => strtolower($stamp) . '-owner@example.invalid']);
    $result = $save($create);
    $check($result['success'] ?? false, 'Admin create with no region/city: ' . ($result['message'] ?? ''));
    $created = $db->table('vendors')->where('id', $result['id'])->get()->getRowArray();
    $check($created['region_id'] === null && $created['city_id'] === null && $created['vendor_grade_id'] === null, 'Created optional FK fields are SQL NULL');
    $check($db->table('vendor_users')->where(['vendor_id' => $created['id'], 'user_id' => $identity])->countAllResults() === 1, 'Admin create still links existing contact');
    $beforeCount = $db->table('vendors')->countAllResults();
    $result = $save(array_replace($create, ['cr_number' => 'EMAIL' . $stamp, 'user_email' => '']));
    $check(!($result['success'] ?? true) && ($result['field'] ?? '') === 'user_email', 'Missing login email has field validation');
    $check($db->table('vendors')->countAllResults() === $beforeCount, 'Missing login email adds no vendor');
    $result = $save(array_replace($create, ['cr_number' => 'BAD' . $stamp, 'region_id' => $otherRegion]));
    $check(!($result['success'] ?? true) && $result['field'] === 'region_id', 'Admin create rejects mismatched location');
    $check($db->table('vendors')->countAllResults() === $beforeCount, 'Invalid create adds no vendor');
    $db->transRollback();
    $check($db->table('vendors')->where('id', $vendor)->countAllResults() === 0, 'Fixtures rolled back');
    echo "Vendor location integration: {$checks} checks passed; fixtures rolled back; no SMS sent.\n";
} finally {
    $db->transRollback();
}
