<?php

declare(strict_types=1);

// Local fixture helper. Read the new password from stdin, never a command-line
// argument or saved file. A newly created account stays disabled until bound to
// an explicitly restricted accounting-only role using --enable-role=<role ID>.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('FCPATH', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
chdir(FCPATH);
require FCPATH . 'app/Config/Paths.php';
$paths = new Config\Paths();
require $paths->systemDirectory . '/Boot.php';
final class LocalAccountingSmokeUserBootstrap extends CodeIgniter\Boot
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
    LocalAccountingSmokeUserBootstrap::load($paths);
    if (!defined('CI_DEBUG')) { define('CI_DEBUG', true); }
    $settings = (new Config\Database())->default;
    if (!in_array(strtolower((string) $settings['hostname']), ['localhost', '127.0.0.1', '::1'], true)
        || ($settings['DBDriver'] ?? '') !== 'MySQLi' || !empty($settings['DSN'])
        || (int) $settings['port'] !== 3306 || $settings['database'] !== 'bedotscpanel_poderp'
        || $settings['DBPrefix'] !== 'pod_' || ENVIRONMENT === 'production') {
        throw new RuntimeException('This helper requires the local XAMPP development database.');
    }
    $db = Config\Database::connect($settings, false);
    $email = 'accounting-smoke-20260905@example.invalid';
    $existing = $db->table('users')->where('email', $email)->get()->getRow();
    $enableRole = null;
    foreach (array_slice($argv, 1) as $argument) {
        if (!preg_match('/^--enable-role=([1-9][0-9]*)$/D', $argument, $matches)) {
            throw new RuntimeException('Unsupported fixture option.');
        }
        $enableRole = (int) $matches[1];
    }
    if ($enableRole !== null) {
        if (!$existing || !empty($existing->is_admin) || (int) $existing->role_id !== 0 || (int) $existing->disable_login !== 1) {
            throw new RuntimeException('Only the newly created disabled fixture may be enabled.');
        }
        $role = $db->table('roles')->where('id', $enableRole)->where('deleted', 0)->get()->getRow();
        $permissions = $role ? unserialize((string) $role->permissions, ['allowed_classes' => false]) : [];
        $actor = (object) ['id' => (int) $existing->id, 'user_type' => 'staff', 'status' => 'active', 'is_admin' => 0,
            'permissions' => is_array($permissions) ? $permissions : []];
        if (!App\Libraries\Payments\Payment_accounting_policy::isAccountingOnly($actor)
            || App\Libraries\Payments\Payment_accounting_policy::home($actor) === 'forbidden') {
            throw new RuntimeException('The selected role must restrict this user to accounting and allow at least one ledger.');
        }
        $db->table('users')->where('id', (int) $existing->id)->update(['role_id' => $enableRole, 'disable_login' => 0]);
        echo json_encode(['ok' => true, 'user_id' => (int) $existing->id, 'role_id' => $enableRole, 'enabled' => true]) . PHP_EOL;
    } else {
        if ($existing) { throw new RuntimeException('The fixture email already exists; no account or password was changed.'); }
        $password = rtrim(stream_get_contents(STDIN), "\r\n");
        if (strlen($password) < 10 || strlen($password) > 72) {
            throw new RuntimeException('Supply a fixture password of 10 to 72 characters through stdin.');
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        unset($password);
        if (!$hash) { throw new RuntimeException('Could not hash the fixture password.'); }
        $data = [];
        foreach ($db->query('SHOW COLUMNS FROM `' . $db->prefixTable('users') . '`')->getResult() as $column) {
            if (str_contains($column->Extra, 'auto_increment') || str_contains($column->Extra, 'GENERATED')
                || $column->Null === 'YES' || $column->Default !== null) { continue; }
            $type = strtolower($column->Type);
            if (preg_match('/^enum\(\x27([^\x27]+)\x27/', $type, $matches)) {
                $data[$column->Field] = $matches[1];
            } elseif (preg_match('/int|decimal|float|double|bit/', $type)) {
                $data[$column->Field] = 0;
            } elseif (str_contains($type, 'datetime') || str_contains($type, 'timestamp')) {
                $data[$column->Field] = date('Y-m-d H:i:s');
            } elseif ($type === 'date') {
                $data[$column->Field] = date('Y-m-d');
            } else {
                $data[$column->Field] = '';
            }
        }
        $data = array_merge($data, ['first_name' => 'Accounting', 'last_name' => 'Smoke Test', 'email' => $email,
            'user_type' => 'staff', 'status' => 'active', 'is_admin' => 0, 'role_id' => 0, 'deleted' => 0,
            'disable_login' => 1, 'password' => $hash]);
        if (!$db->table('users')->insert($data)) { throw new RuntimeException('Could not insert fixture user.'); }
        echo json_encode(['ok' => true, 'user_id' => (int) $db->insertID(), 'email' => $email,
            'enabled' => false, 'operational_assignments_added' => 0]) . PHP_EOL;
    }
} catch (Throwable $error) {
    fwrite(STDERR, json_encode(['ok' => false, 'error_type' => get_class($error), 'error_code' => $error->getCode(),
        'message' => $error instanceof RuntimeException && !$error instanceof CodeIgniter\Database\Exceptions\DatabaseException
            ? $error->getMessage() : 'Local accounting fixture operation failed.']) . PHP_EOL);
    exit(1);
} finally {
    if ($db) { $db->close(); }
}
