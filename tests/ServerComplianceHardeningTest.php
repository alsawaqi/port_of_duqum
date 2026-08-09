<?php

$root = dirname(__DIR__);

$fail = static function (string $message): void {
    fwrite(STDERR, 'Assertion failed: ' . $message . PHP_EOL);
    exit(1);
};

$assertContains = static function (
    string $needle,
    string $haystack,
    string $message
) use ($fail): void {
    if (!str_contains($haystack, $needle)) {
        $fail($message . ' Missing: ' . $needle);
    }
};

$assertNotContains = static function (
    string $needle,
    string $haystack,
    string $message
) use ($fail): void {
    if (str_contains($haystack, $needle)) {
        $fail($message . ' Unexpected: ' . $needle);
    }
};

$read = static function (string $path) use ($root, $fail): string {
    $absolute = $root . '/' . $path;
    if (!is_file($absolute)) {
        $fail($path . ' must exist');
    }

    return (string) file_get_contents($absolute);
};

$htaccess = $read('.htaccess');
$assertContains('LimitRequestBody 67108864', $htaccess, 'request bodies are bounded');
$assertContains('<LimitExcept GET POST HEAD OPTIONS>', $htaccess, 'unused HTTP methods are denied');
$assertContains('%{REQUEST_URI} =~', $htaccess, 'sensitive path denial does not depend on rewrite');
$assertContains('RewriteOptions InheritDownBefore', $htaccess, 'parent denials precede child rewrites');
$assertContains('Header always set Permissions-Policy', $htaccess, 'browser capabilities are constrained');
$assertContains('Header always set Strict-Transport-Security', $htaccess, 'HTTPS responses receive HSTS');
$assertContains('Header always set Cross-Origin-Opener-Policy', $htaccess, 'cross-origin opener isolation is declared');

foreach (
    array(
        'app', 'tests', 'updates', 'documentation',
        '.vscode', '.vs', '.qodo', '.sql'
    ) as $directory
) {
    $deny = $read($directory . '/.htaccess');
    $assertContains('Require all denied', $deny, $directory . ' is denied independently of rewrite');
}
foreach (array('files', 'plugins') as $directory) {
    $policy = $read($directory . '/.htaccess');
    $assertContains('Options -Indexes', $policy, $directory . ' cannot be listed');
    $assertContains('php[0-9]*', $policy, $directory . ' blocks executable uploads');
    $assertContains('Require all denied', $policy, $directory . ' protects dangerous artifacts');
}

$filters = $read('app/Config/Filters.php');
$globals = substr($filters, strpos($filters, 'public array $globals'));
$safePosition = strpos($globals, "'safehttp'");
$csrfPosition = strpos($globals, "'csrf'");
if ($safePosition === false || $csrfPosition === false || $safePosition >= $csrfPosition) {
    $fail('SafeHttpMethods must run globally before CSRF');
}
$assertContains("'csrf' => ['except' => []]", $globals, 'global CSRF remains enabled');
$assertContains("['before']['csrf']['except']", $filters, 'approved CSRF exclusions are preserved');

$safeMethods = $read('app/Filters/SafeHttpMethods.php');
$assertContains("['GET', 'HEAD', 'POST', 'OPTIONS']", $safeMethods, 'the application method allowlist is explicit');
$assertContains("->setStatusCode(405)", $safeMethods, 'unsupported and mutating safe-verb calls fail closed');

$app = $read('app/Config/App.php');
$assertContains('parent::__construct();', $app, 'framework environment overrides are loaded');
$assertContains("public \$CSPEnabled = ENVIRONMENT === 'production';", $app, 'production CSP is enabled');
$assertContains("public \$encryption_key = '';", $app, 'the encryption key is not committed');
$assertContains('PODC_APP_ENCRYPTION_KEY', $app, 'the deployment encryption secret is supported');
$assertContains('PODC_BASE_URL', $app, 'the canonical production URL is supported');
$assertContains('validate_production_security_config', $app, 'production security configuration fails closed');
$assertContains('$this->set_base_url();', $app, 'production URL validation runs after request/env URL resolution');
$assertContains('Current-release compatibility', $app, 'legacy encryption key length is allowed until planned key migration');
$assertNotContains('with at least 32 random bytes', $app, 'legacy encryption key length must not block current deployment');

$cookie = $read('app/Config/Cookie.php');
$assertContains('$this->secure = true;', $cookie, 'production cookies require TLS');
$assertContains('$this->httponly = true;', $cookie, 'production cookies are inaccessible to scripts');
$assertContains("['lax', 'strict']", $cookie, 'production SameSite cannot be weakened');

$session = $read('app/Config/Session.php');
$assertContains('$this->regenerateDestroy = true;', $session, 'rotated session IDs are destroyed');
$assertContains('min($this->expiration, 7200)', $session, 'production sessions have a maximum lifetime');
$assertContains('min($this->timeToUpdate, 300)', $session, 'session IDs rotate regularly');

$logger = $read('app/Config/Logger.php');
$assertContains('SecurityLogHandler::class', $logger, 'logs pass through the redacting handler');
$assertContains("[1, 2, 3, 4]", $logger, 'production excludes verbose log levels');
$assertContains("'fileExtension' => 'php'", $logger, 'log files have direct-access protection');
$assertContains("'filePermissions' => 0640", $logger, 'new log files are not world-readable');

require_once $root . '/system/Log/Handlers/HandlerInterface.php';
require_once $root . '/system/Log/Handlers/BaseHandler.php';
require_once $root . '/system/Log/Handlers/FileHandler.php';
require_once $root . '/app/Config/SecurityLogHandler.php';

$sample = 'Authorization: Bearer token-example' . PHP_EOL
    . 'Cookie: ci_session=session-example' . PHP_EOL
    . 'password=pass-example&api_key=key-example '
    . 'https://user-example:pwd-example@example.invalid/path '
    . '{"authorization":"Basic basic-example","token":"plain-example"}';
$redacted = \Config\SecurityLogHandler::redactSensitiveData($sample);
foreach (
    array(
        'token-example', 'session-example', 'pass-example', 'key-example',
        'pwd-example', 'basic-example', 'plain-example'
    ) as $secret
) {
    if (str_contains($redacted, $secret)) {
        $fail('log redaction leaked a test credential');
    }
}
$assertContains('[REDACTED]', $redacted, 'credentials are replaced before logging');

$events = $read('app/Config/Events.php');
$assertContains("\$csp->clearDirective(\$directive);", $events, 'enforcing defaults are cleared before CSP bootstrap');
$assertContains('Rise::PRODUCTION_CSP_REPORT_ONLY', $events, 'CSP enforcement mode is explicit');
$assertContains("'unsafe-inline', 'unsafe-eval'", $events, 'legacy allowances are explicitly marked for removal');
$assertContains('PODC_CSP_REPORT_URI', $events, 'a CSP report collector can be configured');
$assertContains('https://js.stripe.com', $events, 'required payment assets are represented');
$assertNotContains('https://fonts.googleapis.com', $events, 'site fonts are self-hosted');

$rise = $read('app/Config/Rise.php');
$assertContains('PRODUCTION_CSP_REPORT_ONLY = false', $rise, 'production CSP is enforced');
$assertContains('PRODUCTION_RUNTIME_CODE_MANAGEMENT_ENABLED = false', $rise, 'runtime code management is off in production');
$assertContains(
    '"eservice_payment_webhook/stripe"',
    $rise,
    'the signature-verified Stripe callback has an exact CSRF exclusion'
);
if (substr_count($rise, '"eservice_payment_webhook/stripe"') !== 1) {
    $fail('the Stripe callback CSRF exclusion must be exact and unique');
}
foreach (array('app/Controllers/Rise_plugins.php', 'app/Controllers/Updates.php') as $controllerPath) {
    $controller = $read($controllerPath);
    $assertContains('PageNotFoundException::forPageNotFound()', $controller, 'production hides ' . $controllerPath);
    $assertContains('PRODUCTION_RUNTIME_CODE_MANAGEMENT_ENABLED', $controller, 'production blocks ' . $controllerPath);
}

echo 'Server compliance hardening contracts passed.' . PHP_EOL;
