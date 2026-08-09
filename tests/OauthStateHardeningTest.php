<?php

$root = dirname(__DIR__);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$fail = static function (string $message): void {
    fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
    exit(1);
};
$contains = static function (string $needle, string $source, string $message) use ($fail): void {
    if (strpos($source, $needle) === false) {
        $fail($message . ' Missing: ' . $needle);
    }
};

$guard = $read('app/Libraries/Oauth_state_guard.php');
$googleController = $read('app/Controllers/Google_api.php');
$microsoftController = $read('app/Controllers/Microsoft_api.php');

$contains('random_bytes(32)', $guard, 'OAuth state must be cryptographically random');
$contains("hash('sha256', \$token)", $guard, 'only a state digest should be retained in the session');
$contains('hash_equals', $guard, 'state comparison must be timing safe');
$contains('expires_at', $guard, 'state must expire');
$contains('one-time use', $guard, 'state must be consumed once');
$contains("getGet('state')", $googleController, 'Google callback must read provider state');
$contains('oauth_state->consume', $googleController, 'Google callback must consume a session-bound state');
$contains('access_only_admin_or_settings_admin', $googleController, 'global Google integrations require settings authority');
$contains("getGet('state')", $microsoftController, 'Microsoft callback must read provider state');
$contains('oauth_state->consume', $microsoftController, 'Microsoft callback must consume a session-bound state');

foreach ([
    'app/Libraries/Google.php',
    'app/Libraries/Google_calendar.php',
    'app/Libraries/Google_calendar_events.php',
    'app/Libraries/Google_Trait.php',
] as $path) {
    $contains('setState($state)', $read($path), $path . ' must send issued state to Google');
}
foreach (['app/Libraries/Outlook_imap.php', 'app/Libraries/Outlook_smtp.php'] as $path) {
    $source = $read($path);
    $contains('"state" => $state', $source, $path . ' must send issued state to Microsoft');
    $contains('PHP_QUERY_RFC3986', $source, $path . ' must encode the authorization query safely');
}

echo 'OAuth state hardening checks passed.' . PHP_EOL;
