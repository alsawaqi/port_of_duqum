<?php

$root = dirname(__DIR__);
$database = (string) file_get_contents($root . '/app/Config/Database.php');
$crud = (string) file_get_contents($root . '/app/Models/Crud_model.php');

$fail = static function (string $message): void {
    fwrite(STDERR, 'Assertion failed: ' . $message . PHP_EOL);
    exit(1);
};

if (!str_contains($database, "'strictOn' => true")) {
    $fail('the default database connection must enable strict mode');
}
if (!str_contains($database, "if (ENVIRONMENT === 'production')")) {
    $fail('production database prerequisites must be validated');
}
if (!str_contains($database, "username === 'root'") || !str_contains($database, 'empty($this->default')) {
    $fail('production must reject root/blank credentials and unencrypted remote connections');
}
if (preg_match('/SET\s+(?:SESSION\s+)?sql_mode\s*=\s*[\'\"]?\s*[\'\"]?/i', $crud)) {
    $fail('Crud_model must not weaken SQL mode at runtime');
}

echo 'Database strict-mode contracts passed.' . PHP_EOL;
