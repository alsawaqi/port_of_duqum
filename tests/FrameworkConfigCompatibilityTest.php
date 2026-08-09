<?php

$root = dirname(__DIR__);
$requirements = [
    "app/Config/Cache.php" => ['public array $cacheStatusCodes = [200];'],
    "app/Config/ContentSecurityPolicy.php" => [
        'public ?string $reportTo = null;',
        'public array|string $scriptSrcElem = [];',
        'public array|string $scriptSrcAttr = [];',
        'public array|string $styleSrcElem = [];',
        'public array|string $styleSrcAttr = [];',
        'public array|string $workerSrc = [];',
    ],
    "app/Config/CURLRequest.php" => ['public array $shareConnectionOptions = ['],
    "app/Config/Email.php" => ["public string \$SMTPAuthMethod = 'login';"],
    "app/Config/Encryption.php" => ['public array|string $previousKeys = [];'],
    "app/Config/Format.php" => ['public int $jsonEncodeDepth = 512;'],
    "app/Config/Migrations.php" => ['public bool $lock = true;'],
    "app/Config/Paths.php" => ['public string $envDirectory ='],
    "app/Config/Routing.php" => ['public bool $useControllerAttributes = false;'],
    "app/Config/Toolbar.php" => ['public array $disableOnHeaders = ['],
    "app/Config/View.php" => ["public string \$appOverridesFolder = 'overrides';"],
];

foreach ($requirements as $relativePath => $needles) {
    $source = file_get_contents($root . "/" . $relativePath);
    foreach ($needles as $needle) {
        if (!is_string($source) || !str_contains($source, $needle)) {
            fwrite(STDERR, "Assertion failed: {$relativePath} is missing {$needle}" . PHP_EOL);
            exit(1);
        }
    }
}

$app = (string) file_get_contents($root . "/app/Config/App.php");
$rise = (string) file_get_contents($root . "/app/Config/Rise.php");
if (!str_contains($app, "PHP_SAPI === 'cli'")
    || !str_contains($app, "\$this->baseURL = 'http://localhost/';")
    || !str_contains($app, "public \$encryption_key = '';")) {
    fwrite(STDERR, "Assertion failed: App base URL discovery is not CLI-safe." . PHP_EOL);
    exit(1);
}
if (!str_contains($rise, "function_exists('app_hooks')")
    || !str_contains($rise, "method_exists(\$hooks, 'apply_filters')")) {
    fwrite(STDERR, "Assertion failed: Rise config is not safe before plugin-hook bootstrap." . PHP_EOL);
    exit(1);
}

echo "CodeIgniter 4.7 application-config compatibility passed." . PHP_EOL;
