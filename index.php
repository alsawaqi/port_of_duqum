<?php

/*
 *---------------------------------------------------------------
 * CHECK PHP VERSION
 *---------------------------------------------------------------
 */

$minPhpVersion = '8.2'; // Standard CodeIgniter 4.7.4; validated for the PHP 8.3 server.
if (version_compare(PHP_VERSION, $minPhpVersion, '<')) {
    $message = sprintf(
        'Your PHP version must be %s or higher to run CodeIgniter. Current version: %s',
        $minPhpVersion,
        PHP_VERSION
    );

    header('HTTP/1.1 503 Service Unavailable.', true, 503);
    echo $message;

    exit(1);
}


//set the variable to 'installed' after installation
$app_state = "installed";

// we don't want to access the main project before installation. redirect to installation page
if ($app_state === 'pre_installation') {
    $domain = $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'];

    $domain = preg_replace('/index.php.*/', '', $domain); //remove everything after index.php
    if (!empty($_SERVER['HTTPS'])) {
        $domain = 'https://' . $domain;
    } else {
        $domain = 'http://' . $domain;
    }

    header("Location: $domain./install/index.php");
    exit;
}

/*
 *---------------------------------------------------------------
 * SET THE CURRENT DIRECTORY
 *---------------------------------------------------------------
 */

// Path to the front controller (this file)
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);

// Ensure the current directory is pointing to the front controller's directory
if (getcwd() . DIRECTORY_SEPARATOR !== FCPATH) {
    chdir(FCPATH);
}

/*
 *---------------------------------------------------------------
 * BOOTSTRAP THE APPLICATION
 *---------------------------------------------------------------
 * This process sets up the path constants, loads and registers
 * our autoloader, along with Composer's, loads our constants
 * and fires up an environment-specific bootstrapping.
 */

// A migration can fail before CodeIgniter installs its exception logger
// (missing files, extensions or invalid production configuration). Keep that
// failure visible in the hosting PHP log without exposing secrets to visitors.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('zend.exception_ignore_args', '1');

try {
    require FCPATH . 'app/Config/Paths.php';
    $paths = new Config\Paths();
    require $paths->systemDirectory . '/Boot.php';
    exit(CodeIgniter\Boot::bootWeb($paths));
} catch (\Throwable $error) {
    // Expected HTTP failures retain their status. Never log form data, query
    // strings, or exception messages containing credentials.
    $status = $error instanceof \CodeIgniter\Exceptions\HTTPExceptionInterface ? (int) $error->getCode() : 500;
    if ($status < 400 || $status > 599) { $status = 500; }
    $csrfFailure = $status === 403 && $error instanceof \CodeIgniter\Security\Exceptions\SecurityException;
    $method = preg_replace('/[^A-Z]/', '', substr((string) ($_SERVER['REQUEST_METHOD'] ?? 'CLI'), 0, 12));
    $path = explode('?', (string) ($_SERVER['REQUEST_URI'] ?? '/'), 2)[0];
    $segments = array_values(array_filter(explode('/', $path), 'strlen'));
    if (($segments[0] ?? '') === 'index.php') { array_shift($segments); }
    $route = '/' . implode('/', array_map(static fn($part) => preg_match('/^[A-Za-z_][A-Za-z_-]{0,49}$/D', $part) ? $part : '[redacted]', array_slice($segments, 0, 2)));
    $diagnostic = 'HTTP ' . $status . ' ' . $method . ' ' . $route . ': ' . get_class($error) . ' at ' . $error->getFile() . ':' . $error->getLine();
    // Exception messages and arguments may contain database queries/credentials.
    // The class, source file and line identify the failure without logging them.
    error_log(($status >= 500 ? 'POD startup failed: ' : 'POD request refused: ') . $diagnostic);
    // Late failures (for example PDF rendering) also belong in writable/logs.
    // Keep exception arguments/messages out: they can contain credentials/data.
    if (function_exists('log_message')) {
        try {
            log_message($status >= 500 ? 'error' : 'warning', 'POD request failed: ' . $diagnostic);
        } catch (\Throwable $loggingError) {
            // The hosting PHP log above remains available when app logging fails.
        }
    }
    if (!headers_sent()) {
        http_response_code($status);
        header('Cache-Control: no-store');
    }
    $message = match ($status) {
        404 => 'The requested page could not be found. Please check the link or return to the portal.',
        403 => $csrfFailure ? 'This form has expired or could not be verified. Refresh the page, sign in again if needed, and try again.' : 'You do not have permission to perform this action.',
        default => $status >= 500 ? 'The application could not start. Ask hosting support to check the PHP error log and run php server-check.php in the project directory.' : 'The request could not be completed. Please refresh the page and try again.',
    };
    $ajax = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
        || str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');
    if (!headers_sent()) { header('Content-Type: ' . ($ajax ? 'application/json' : 'text/plain') . '; charset=UTF-8'); }
    echo $ajax ? json_encode(['success' => false, 'message' => $message, 'csrf_expired' => $csrfFailure]) : $message;
    exit(1);
}
