<?php

// Run real entry points in separate processes; no database/provider connections.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
$temp = sys_get_temp_dir() . '/pod_startup_' . bin2hex(random_bytes(8));
mkdir($temp);
register_shutdown_function(static function () use ($temp): void {
    foreach (['app/Config/Paths.php', 'index.php', 'php-error.log'] as $file) {
        if (is_file($temp . '/' . $file)) { unlink($temp . '/' . $file); }
    }
    if (is_dir($temp . '/app/Config')) { rmdir($temp . '/app/Config'); }
    if (is_dir($temp . '/app')) { rmdir($temp . '/app'); }
    rmdir($temp);
});
$checks = 0;
$check = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) { throw new RuntimeException($label); }
    $checks++;
};
$run = static function (array $arguments) use ($root): array {
    $process = proc_open(array_merge([PHP_BINARY], $arguments), [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
    if (!is_resource($process)) { throw new RuntimeException('Cannot start PHP check'); }
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]); fclose($pipes[1]);
    $err = stream_get_contents($pipes[2]); fclose($pipes[2]);
    return [proc_close($process), $out, $err];
};

[$exit, $output] = $run(['-n', $root . '/server-check.php']);
$check($exit === 1, 'Missing PHP extensions fail the preflight.');
$check(str_contains($output, '[FAIL] Extension: intl'), 'The missing extension is named.');
$check(str_contains($output, 'Fix the requirements above'), 'Requirements are checked before framework boot.');
$check(!str_contains($output, 'Database configuration loads'), 'No database configuration loads when core requirements are absent.');

copy($root . '/index.php', $temp . '/index.php');
[$exit, $output, $errors] = $run(['-d', 'display_errors=1', '-d', 'error_log=' . $temp . '/php-error.log', $temp . '/index.php']);
$check($exit === 1, 'Missing application files stop startup.');
$check(str_contains($output, 'php server-check.php'), 'The visitor gets a short support instruction.');
$check(!str_contains($output, $temp) && !str_contains($output, 'Stack trace'), 'Public output hides paths and stack traces.');
$check(str_contains(file_get_contents($temp . '/php-error.log'), 'POD startup failed:'), 'Early errors reach the PHP log before the framework logger exists.');

mkdir($temp . '/app/Config', 0777, true);
file_put_contents($temp . '/app/Config/Paths.php', '<?php throw new RuntimeException("synthetic-password-never-expose");');
[$exit, $output, $errors] = $run(['-d', 'display_errors=1', '-d', 'error_log=' . $temp . '/php-error.log', $temp . '/index.php']);
$check($exit === 1, 'Configuration exceptions stop startup.');
$check(!str_contains($output . $errors . file_get_contents($temp . '/php-error.log'), 'synthetic-password-never-expose'), 'Exception messages and credentials do not leak through the fallback.');
$check(str_contains(file_get_contents($temp . '/php-error.log'), 'RuntimeException at'), 'The private log identifies the exception type and source.');

echo "Server startup diagnostics: {$checks} checks passed.\n";
