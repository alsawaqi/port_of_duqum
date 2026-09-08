<?php

define('FCPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('ENVIRONMENT', 'testing');
chdir(FCPATH);
require FCPATH . 'app/Config/Paths.php';
require FCPATH . 'system/Boot.php';

class CronRoutingBootstrap extends \CodeIgniter\Boot
{
    public static function init(): void
    {
        static::definePathConstants(new \Config\Paths());
        static::loadConstants();
        static::loadCommonFunctions();
        static::loadAutoloader();
    }
}
CronRoutingBootstrap::init();
$routes = service('routes', false);
require APPPATH . 'Config/Routes.php';
if (($routes->getRoutes('POST')['cron'] ?? null) !== '\\App\\Controllers\\Cron::index') {
    throw new RuntimeException('POST /cron must reach the authenticated scheduler.');
}
$filters = new \Config\Filters();
$exceptions = $filters->globals['before']['csrf']['except'];
if (!in_array('cron', $exceptions, true) || in_array('cron/*', $exceptions, true)) {
    throw new RuntimeException('Only the exact signed scheduler path is CSRF exempt.');
}
if (($routes->getRoutes('POST')['cron/(.*)'] ?? null) !== '\\App\\Controllers\\Cron::$1') {
    throw new RuntimeException('Other controller routes retain their existing protection.');
}
echo "Cron route and exact CSRF scope passed.\n";
