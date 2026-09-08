<?php

// No database or external services: exercise the actual framework on PHP 8.1+.
define('FCPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('ENVIRONMENT', 'testing');
require FCPATH . 'app/Config/Paths.php';
require FCPATH . 'system/Boot.php';

class Php81CompatibilityBootstrap extends \CodeIgniter\Boot
{
    public static function init(): void
    {
        static::definePathConstants(new \Config\Paths());
        static::loadConstants();
        static::loadCommonFunctions();
        static::loadAutoloader();
    }
}
Php81CompatibilityBootstrap::init();

$checks = 0;
$check = static function (bool $ok, string $label) use (&$checks): void {
    $checks++;
    if (!$ok) {
        throw new RuntimeException($label);
    }
};

$classes = [
    \CodeIgniter\Cache\FactoriesCache::class,
    \CodeIgniter\DataConverter\DataConverter::class,
    \CodeIgniter\HTTP\SiteURIFactory::class,
    \CodeIgniter\Router\DefinedRouteCollector::class,
    \CodeIgniter\Commands\Utilities\Routes\AutoRouteCollector::class,
    \CodeIgniter\Commands\Utilities\Routes\ControllerFinder::class,
    \CodeIgniter\Commands\Utilities\Routes\ControllerMethodReader::class,
    \CodeIgniter\Commands\Utilities\Routes\FilterCollector::class,
    \CodeIgniter\Commands\Utilities\Routes\FilterFinder::class,
    \CodeIgniter\Commands\Utilities\Routes\AutoRouterImproved\AutoRouteCollector::class,
    \CodeIgniter\Commands\Utilities\Routes\AutoRouterImproved\ControllerMethodReader::class,
];
foreach ($classes as $class) {
    $reflection = new ReflectionClass($class);
    $check($reflection->isFinal(), $class . ' remains final');
    foreach ($reflection->getProperties() as $property) {
        $check($property->isReadOnly(), $class . '::$' . $property->name . ' remains readonly');
    }
    $object = $reflection->newInstanceWithoutConstructor();
    $refused = false;
    try {
        $object->unexpected = 'value';
    } catch (Error $e) {
        $refused = str_contains($e->getMessage(), 'Cannot create dynamic property');
    }
    $check($refused, $class . ' rejects dynamic properties');
}

$converter = new \CodeIgniter\DataConverter\DataConverter(['amount' => 'integer']);
$check($converter->fromDataSource(['amount' => '123']) === ['amount' => 123], 'Model casting');
$check($converter->toDataSource(['amount' => 123]) === ['amount' => 123], 'Database casting');
$refused = false;
try {
    $property = (new ReflectionClass($converter))->getProperty('types');
    $property->setAccessible(true);
    $property->setValue($converter, []);
} catch (Error $e) {
    $refused = str_contains($e->getMessage(), 'readonly property');
}
$check($refused, 'Readonly state cannot be reassigned');

$app = new \Config\App();
$app->baseURL = 'https://portal.example/';
$factory = new \CodeIgniter\HTTP\SiteURIFactory($app, new \CodeIgniter\Superglobals());
$uri = $factory->createFromString('https://portal.example/index.php/signin');
$check(str_contains((string) $uri, '/index.php/signin'), 'Sign-in URI construction');

$routes = service('routes', false);
$routes->get('compatibility-check', 'Signin::index');
$defined = iterator_to_array((new \CodeIgniter\Router\DefinedRouteCollector($routes))->collect());
$check(count(array_filter($defined, static fn ($row) => $row['route'] === 'compatibility-check')) === 1, 'Defined route collection');
$check((new \CodeIgniter\Commands\Utilities\Routes\ControllerMethodReader('Missing\\'))->read(stdClass::class) === [], 'Route method reader');
$check((new \CodeIgniter\Commands\Utilities\Routes\AutoRouterImproved\ControllerMethodReader('Missing\\', ['GET']))->read(stdClass::class) === [], 'Improved route method reader');
$check((new \CodeIgniter\Commands\Utilities\Routes\FilterCollector())->get('CLI', 'compatibility-check') === ['before' => [], 'after' => []], 'Filter collection');

$cache = new \CodeIgniter\Test\Mock\MockCache(new \Config\Cache());
$check($cache->save('compatibility', 'value', 60), 'Cache stores data');
$check($cache->clean() === true && $cache->get('compatibility') === null, 'Cache clean preserves true result');
$factoryCache = new \CodeIgniter\Cache\FactoriesCache($cache);
$check($factoryCache->load('missing-compatibility-component') === false, 'Factory cache misses safely');

// PHP 8.1 ignores SensitiveParameter attributes; production omits all arguments.
ini_set('zend.exception_ignore_args', '0');
require FCPATH . 'app/Config/Boot/production.php';
$throwWithSecret = static function (string $secret): void { throw new RuntimeException('test'); };
try {
    $throwWithSecret('synthetic-secret-must-not-appear');
} catch (RuntimeException $e) {
    $check(!str_contains(serialize($e->getTrace()), 'synthetic-secret-must-not-appear'), 'Production exception traces omit arguments');
}

echo "PHP " . PHP_VERSION . " compatibility: {$checks} checks passed.\n";
