<?php

declare(strict_types=1);

// Read-only local diagnostics. Never expose connection credentials or table rows.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('FCPATH', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
chdir(FCPATH);
require FCPATH . 'app/Config/Paths.php';
$paths = new Config\Paths();
require $paths->systemDirectory . '/Boot.php';

// Load configuration without running routes, controllers, plugins, or migrations.
final class LocalReadOnlyBootstrap extends CodeIgniter\Boot
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

$connection = null;
try {
    LocalReadOnlyBootstrap::load($paths);
    $database = new Config\Database();
    $settings = $database->default;
    if (!in_array(strtolower((string) $settings['hostname']), ['localhost', '127.0.0.1', '::1'], true)) {
        throw new RuntimeException('The configured database is not on localhost; local diagnostics stopped.');
    }
    if (($settings['DBDriver'] ?? '') !== 'MySQLi' || !empty($settings['DSN'])) {
        throw new RuntimeException('Local diagnostics require the configured MySQLi connection without a DSN.');
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $connection = new mysqli(
        $settings['hostname'],
        $settings['username'],
        $settings['password'],
        $settings['database'],
        (int) $settings['port']
    );
    $connection->set_charset('utf8mb4');
    $connection->query('START TRANSACTION READ ONLY');
    $server = $connection->query('SELECT VERSION() AS server_version, DATABASE() AS database_name')->fetch_assoc();
    $tables = $connection->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME')->fetch_all(MYSQLI_ASSOC);
    $moduleTables = [];
    foreach ($tables as $table) {
        $name = $table['TABLE_NAME'];
        if (preg_match('/(?:eservice.*payment|vendor.*payment|vendor.*fee|gate_pass.*(?:payment|fee)|tender.*payment|migration)/i', $name)) {
            $moduleTables[] = $name;
        }
    }
    $details = [];
    if (in_array('--schema', $argv, true)) {
        foreach ($moduleTables as $name) {
            $quotedName = '`' . str_replace('`', '``', $name) . '`';
            $columns = $connection->query('SHOW COLUMNS FROM ' . $quotedName)->fetch_all(MYSQLI_ASSOC);
            $columnNames = array_column($columns, 'Field');
            $details[$name] = [
                'row_count' => (int) $connection->query('SELECT COUNT(*) AS n FROM ' . $quotedName)->fetch_assoc()['n'],
                'columns' => array_map(static fn (array $column): array => [
                    'name' => $column['Field'],
                    'type' => $column['Type'],
                    'nullable' => $column['Null'] === 'YES',
                ], $columns),
            ];
            if (in_array('currency', $columnNames, true)) {
                $details[$name]['currency_counts'] = $connection->query('SELECT currency, COUNT(*) AS n FROM ' . $quotedName . ' GROUP BY currency')->fetch_all(MYSQLI_ASSOC);
            }
            if (preg_match('/(?:^|_)migrations$/', $name)) {
                $safeColumns = array_values(array_intersect(['version', 'migration', 'class', 'namespace', 'group', 'batch'], $columnNames));
                if ($safeColumns) {
                    $columnList = implode(', ', array_map(static fn (string $column): string => '`' . $column . '`', $safeColumns));
                    $order = in_array('version', $columnNames, true) ? ' ORDER BY version DESC' : '';
                    $details[$name]['ledger'] = $connection->query('SELECT ' . $columnList . ' FROM ' . $quotedName . $order . ' LIMIT 60')->fetch_all(MYSQLI_ASSOC);
                }
            }
        }
    }
    $connection->rollback();

    $result = [
        'ok' => true,
        'php_version' => PHP_VERSION,
        'environment' => ENVIRONMENT,
        'extensions' => array_combine(
            ['curl', 'openssl', 'mysqli', 'intl', 'mbstring', 'fileinfo'],
            array_map('extension_loaded', ['curl', 'openssl', 'mysqli', 'intl', 'mbstring', 'fileinfo'])
        ),
        'database_host' => $settings['hostname'],
        'database_port' => (int) $settings['port'],
        'database_name' => $server['database_name'],
        'database_prefix' => $settings['DBPrefix'],
        'database_version' => $server['server_version'],
        'table_count' => count($tables),
        'payment_and_migration_tables' => $moduleTables,
        'queries' => 'Read-only metadata queries; no migrations or business records changed.',
    ];
    if ($details) {
        $result['schema_details'] = $details;
    }
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $error) {
    // Driver errors may contain account details; report only the error type/code.
    $message = $error instanceof mysqli_sql_exception
        ? 'Local database connection/query failed. Check the local database settings and XAMPP MySQL.'
        : 'Local configuration could not be loaded. Check the PHP runtime and local database configuration.';
    fwrite(STDERR, json_encode([
        'ok' => false,
        'message' => $message,
        'error_type' => get_class($error),
        'error_code' => $error->getCode(),
    ], JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(1);
} finally {
    if ($connection instanceof mysqli) {
        $connection->close();
    }
}
