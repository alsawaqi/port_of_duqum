<?php

$root = dirname(__DIR__);
$captcha = (string) file_get_contents($root . '/app/Libraries/ReCAPTCHA.php');
$signin = (string) file_get_contents($root . '/app/Controllers/Signin.php');
$guestVendor = (string) file_get_contents($root . '/app/Controllers/Guest_vendor.php');
$guestGate = (string) file_get_contents($root . '/app/Controllers/Guest_gate_pass.php');
$vendorView = (string) file_get_contents($root . '/app/Views/guest_vendor/index.php');
$externalTickets = (string) file_get_contents($root . '/app/Controllers/External_tickets.php');
$collectLeads = (string) file_get_contents($root . '/app/Controllers/Collect_leads.php');
$requestEstimate = (string) file_get_contents($root . '/app/Controllers/Request_estimate.php');

$fail = static function (string $message): void {
    fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
    exit(1);
};
$contains = static function (string $needle, string $haystack, string $message) use ($fail): void {
    if (strpos($haystack, $needle) === false) {
        $fail($message . ' Missing: ' . $needle);
    }
};

$contains('ENVIRONMENT === "production"', $captcha, 'production CAPTCHA must fail closed when unconfigured');
$contains('PODC_RECAPTCHA_SECRET_KEY', $captcha, 'CAPTCHA secret supports environment provisioning');
$contains('CURLOPT_SSL_VERIFYPEER => true', $captcha, 'provider TLS certificates must be verified');
$contains('CURLOPT_CONNECTTIMEOUT => 3', $captcha, 'provider requests need a bounded connection timeout');
$contains('CURLOPT_FOLLOWLOCATION => false', $captcha, 'CAPTCHA verification must not follow provider redirects');
$contains('PODC_RECAPTCHA_EXPECTED_HOSTNAME', $captcha, 'CAPTCHA responses are bound to the deployed hostname');
$contains('PODC_RECAPTCHA_EXPECTED_ACTION', $captcha, 'v3 CAPTCHA responses are bound to the intended action');
$contains('PODC_RECAPTCHA_MIN_SCORE', $captcha, 'v3 CAPTCHA risk threshold is explicitly configurable');
$contains('$this->has_recaptcha_error();', $signin, 'login always invokes abuse verification');
$contains('(new ReCAPTCHA())->validate_recaptcha(true);', $guestVendor, 'vendor registration invokes abuse verification');
$contains('$ReCAPTCHA->validate_recaptcha(true);', $guestGate, 'gate-pass registration invokes abuse verification');
$contains('view("signin/re_captcha")', $vendorView, 'vendor registration renders the verification widget');
$contains("external_ticket_", $externalTickets, 'public ticket submission is IP rate limited');
$contains('validate_recaptcha()', $externalTickets, 'public ticket submission requires human verification');
$contains("public_lead_", $collectLeads, 'public lead submission is IP rate limited');
$contains('validate_recaptcha()', $collectLeads, 'all public lead modes require human verification');
$contains("public_estimate_", $requestEstimate, 'public estimate submission is IP rate limited');
$contains('validate_recaptcha()', $requestEstimate, 'public estimate submission requires human verification');

echo 'Public abuse protection contracts passed.' . PHP_EOL;
