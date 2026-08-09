<?php

$_SERVER["HTTP_HOST"] = "localhost";
$_SERVER["REQUEST_URI"] = "/";
$_SERVER["SCRIPT_NAME"] = "/index.php";

require __DIR__ . "/../system/Test/bootstrap.php";

$fail = static function (string $message): void {
    fwrite(STDERR, "Assertion failed: {$message}" . PHP_EOL);
    exit(1);
};

$assertTrue = static function (bool $condition, string $message) use ($fail): void {
    if (!$condition) {
        $fail($message);
    }
};

$filters = config(\Config\Filters::class);
$security = config(\Config\Security::class);
$before = $filters->globals["before"] ?? [];
$csrf = $before["csrf"] ?? null;

$assertTrue(is_array($csrf), "CSRF is enabled as a global before-filter");
$excluded = $csrf["except"] ?? [];
$assertTrue(is_array($excluded), "CSRF callback exclusions are explicit");
$assertTrue(
    in_array("webhooks_listener.*+", $excluded, true),
    "the existing external webhook callback remains exempt"
);
$assertTrue(
    !in_array("vendor_portal/*", $excluded, true)
        && !in_array("vendor_portal/save_contact", $excluded, true)
        && !in_array("vendor_portal/save_password", $excluded, true),
    "vendor contact and password mutations are protected"
);
$assertTrue(
    $security->csrfProtection === "session",
    "CSRF tokens are bound to the authenticated session"
);

$contactForm = file_get_contents(
    __DIR__ . "/../app/Views/vendor_portal/contacts/modal_form.php"
);
$passwordForm = file_get_contents(
    __DIR__ . "/../app/Views/vendor_portal/change_password.php"
);
$assertTrue(
    str_contains($contactForm, "form_open("),
    "contact forms receive the framework CSRF field"
);
$assertTrue(
    str_contains($passwordForm, "form_open("),
    "password forms receive the framework CSRF field"
);

echo "Vendor CSRF hardening contracts passed." . PHP_EOL;
