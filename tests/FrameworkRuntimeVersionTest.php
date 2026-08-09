<?php

require_once __DIR__ . "/../system/CodeIgniter.php";

$minimum = "4.7.4";
$actual = \CodeIgniter\CodeIgniter::CI_VERSION;

if (version_compare($actual, $minimum, "<")) {
    fwrite(
        STDERR,
        "Assertion failed: bundled CodeIgniter {$actual} is below required security baseline {$minimum}." . PHP_EOL
    );
    exit(1);
}

$format = file_get_contents(__DIR__ . "/../app/Config/Format.php");
if (!is_string($format) || !str_contains($format, 'public int $jsonEncodeDepth = 512;')) {
    fwrite(STDERR, "Assertion failed: CodeIgniter 4.7 JSON depth config is missing." . PHP_EOL);
    exit(1);
}

echo "Bundled CodeIgniter security baseline passed ({$actual})." . PHP_EOL;
