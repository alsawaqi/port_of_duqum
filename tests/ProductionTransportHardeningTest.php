<?php

$root = dirname(__DIR__);

$fail = static function (string $message): void {
    fwrite(STDERR, "Assertion failed: {$message}" . PHP_EOL);
    exit(1);
};

$assertSame = static function ($expected, $actual, string $message) use ($fail): void {
    if ($actual !== $expected) {
        $fail($message . " Expected " . var_export($expected, true)
            . ", got " . var_export($actual, true) . ".");
    }
};

$runPhp = static function (string $code) use ($root, $fail): string {
    $descriptors = [
        0 => ["pipe", "r"],
        1 => ["pipe", "w"],
        2 => ["pipe", "w"],
    ];

    $process = proc_open([PHP_BINARY, "-r", $code], $descriptors, $pipes, $root);
    if (!is_resource($process)) {
        $fail("Unable to start the isolated PHP configuration smoke process.");
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    if ($exitCode !== 0) {
        $fail("Isolated PHP configuration smoke failed: " . trim($stderr));
    }

    return trim($stdout);
};

$readDefault = static function (
    string $environment,
    string $configFile,
    string $className,
    string $property
) use ($root, $runPhp, $fail) {
    $code = "define('ENVIRONMENT', " . var_export($environment, true) . ");"
        . "require " . var_export($root . "/system/Config/BaseConfig.php", true) . ";"
        . "require " . var_export($root . "/" . $configFile, true) . ";"
        . '$defaults=(new ReflectionClass(' . var_export($className, true) . '))->getDefaultProperties();'
        . 'echo json_encode($defaults[' . var_export($property, true) . ']);';

    $decoded = json_decode($runPhp($code), true);
    if (!is_bool($decoded)) {
        $fail("The {$className}::\${$property} smoke result was not boolean.");
    }

    return $decoded;
};

$assertSame(
    false,
    $readDefault("development", "app/Config/App.php", "Config\\App", "forceGlobalSecureRequests"),
    "Development keeps localhost HTTP available"
);
$assertSame(
    true,
    $readDefault("production", "app/Config/App.php", "Config\\App", "forceGlobalSecureRequests"),
    "Production forces secure requests"
);
$assertSame(
    false,
    $readDefault("development", "app/Config/Cookie.php", "Config\\Cookie", "secure"),
    "Development cookies remain usable over localhost HTTP"
);
$assertSame(
    true,
    $readDefault("production", "app/Config/Cookie.php", "Config\\Cookie", "secure"),
    "Production cookies require HTTPS"
);

$developmentBoot = $root . "/app/Config/Boot/development.php";
$bootCode = "require " . var_export($developmentBoot, true) . ";"
    . 'echo json_encode(['
    . '"display_errors"=>ini_get("display_errors"),'
    . '"display_startup_errors"=>ini_get("display_startup_errors"),'
    . '"log_errors"=>ini_get("log_errors"),'
    . '"show_debug_backtrace"=>SHOW_DEBUG_BACKTRACE,'
    . '"error_reporting"=>error_reporting()]);';
$bootState = json_decode($runPhp($bootCode), true);

$assertSame("0", $bootState["display_errors"] ?? null, "Development hides runtime errors");
$assertSame("0", $bootState["display_startup_errors"] ?? null, "Development hides startup errors");
$assertSame("1", $bootState["log_errors"] ?? null, "Development logs PHP errors");
$assertSame(false, $bootState["show_debug_backtrace"] ?? null, "Development hides debug backtraces");
$assertSame(-1, $bootState["error_reporting"] ?? null, "Development reports all errors to the logger");

$appSource = (string) file_get_contents($root . "/app/Config/App.php");
if (!str_contains($appSource, "version_compare(CodeIgniter::CI_VERSION, '4.7.4', '<')")) {
    $fail("Production must reject a framework baseline missing the July 2026 security fixes.");
}

echo "Production transport and error-display hardening passed." . PHP_EOL;
