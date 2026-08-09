<?php

$root = dirname(__DIR__);
$spark = file_get_contents($root . "/spark");
$index = file_get_contents($root . "/index.php");

if (!is_string($spark)
    || !str_contains($spark, 'Boot::bootSpark($paths)')
    || !str_contains($spark, "app/Config/Paths.php")
) {
    fwrite(STDERR, "Assertion failed: the reviewed CodeIgniter CLI migration entry point is missing." . PHP_EOL);
    exit(1);
}

foreach (["spark" => $spark, "index.php" => $index] as $name => $source) {
    if (!is_string($source) || !str_contains($source, '$minPhpVersion = \'8.2\';')) {
        fwrite(STDERR, "Assertion failed: {$name} does not enforce the CodeIgniter 4.7 PHP minimum." . PHP_EOL);
        exit(1);
    }
}

echo "CLI migration entry point and PHP runtime baseline passed." . PHP_EOL;
