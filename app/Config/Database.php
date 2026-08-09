<?php

namespace Config;

use CodeIgniter\Database\Config;

/**
 * Database Configuration.
 */
class Database extends Config
{
    /**
     * The directory that holds the Migrations
     * and Seeds directories.
     *
     * @var string
     */
    public $filesPath = APPPATH.'Database'.DIRECTORY_SEPARATOR;

    /**
     * Lets you choose which connection group to
     * use if no other is specified.
     *
     * @var string
     */
    public $defaultGroup = 'default';

    /**
     * The default database connection.
     *
     * @var array
     */
    public $default = [
        'DSN' => '',
        'hostname' => 'localhost',
        'username' => 'root',
        'password' => '',
        'database' => 'bedotscpanel_poderp',
        'DBDriver' => 'MySQLi',
        'DBPrefix' => 'pod_',
        'pConnect' => false,
        'DBDebug' => (ENVIRONMENT !== 'production'),
        'charset' => 'utf8mb4',
        'DBCollat' => 'utf8mb4_unicode_ci',
        'swapPre' => '',
        'encrypt' => false,
        'compress' => false,
        'strictOn' => true,
        'failover' => [],
        'port' => 3306,
    ];

    /**
     * This database connection is used when
     * running PHPUnit database tests.
     *
     * @var array
     */
    public $tests = [
        'DSN' => '',
        'hostname' => '127.0.0.1',
        'username' => '',
        'password' => '',
        'database' => ':memory:',
        'DBDriver' => 'SQLite3',
        'DBPrefix' => 'db_',  // Needed to ensure we're working correctly with prefixes live. DO NOT REMOVE FOR CI DEVS
        'pConnect' => false,
        'DBDebug' => (ENVIRONMENT !== 'production'),
        'charset' => 'utf8',
        'DBCollat' => 'utf8_general_ci',
        'swapPre' => '',
        'encrypt' => false,
        'compress' => false,
        'strictOn' => false,
        'failover' => [],
        'port' => 3306,
    ];

    // --------------------------------------------------------------------

    public function __construct()
    {
        parent::__construct();

        // Ensure that we always set the database group to 'tests' if
        // we are currently running an automated test suite, so that
        // we don't overwrite live data on accident.
        if (ENVIRONMENT === 'testing') {
            $this->defaultGroup = 'tests';
        }

        if (ENVIRONMENT === 'production') {
            $hostname = strtolower(trim((string) ($this->default['hostname'] ?? '')));
            $username = strtolower(trim((string) ($this->default['username'] ?? '')));
            $password = (string) ($this->default['password'] ?? '');
            $database = trim((string) ($this->default['database'] ?? ''));

            if ($hostname === '' || $database === '' || $username === '' || $username === 'root' || $password === '') {
                throw new \RuntimeException(
                    'Production requires a dedicated, password-protected least-privilege database account.'
                );
            }

            $localHosts = ['localhost', '127.0.0.1', '::1'];
            if (!in_array($hostname, $localHosts, true) && empty($this->default['encrypt'])) {
                throw new \RuntimeException(
                    'Production requires TLS encryption for a remote database connection.'
                );
            }

            $this->default['DBDebug'] = false;
            $this->default['pConnect'] = false;
            $this->default['strictOn'] = true;
            $this->default['charset'] = 'utf8mb4';
            $this->default['DBCollat'] = 'utf8mb4_unicode_ci';
        }
    }

    // --------------------------------------------------------------------
}
