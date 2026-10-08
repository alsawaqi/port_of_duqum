<?php

// Standalone template checks; no database connection or live email.
if (PHP_SAPI !== 'cli') { exit; }
chdir(dirname(__DIR__));
define('FCPATH', getcwd() . DIRECTORY_SEPARATOR);
define('ENVIRONMENT', 'development');
define('CI_DEBUG', true);
require 'app/Config/Paths.php';
require 'system/Boot.php';
class GatePassEmailTestBoot extends CodeIgniter\Boot
{
    public static function init(): void
    {
        static::definePathConstants(new Config\Paths());
        static::loadConstants();
        static::loadCommonFunctions();
        static::loadAutoloader();
    }
}
GatePassEmailTestBoot::init();
helper(['general', 'url', 'language']);
config('App')->baseURL = 'https://portal.example.invalid/';
config('Rise')->app_settings_array = ['language' => 'english'];
$checks = 0;
$check = static function ($ok, $label) use (&$checks) {
    if (!$ok) { throw new RuntimeException($label); } $checks++;
};
$request = (object) ['reference' => 'GP-2026-001245', 'requester_name' => 'Maneesh Mohanan',
    'company_name' => 'Port of Duqm Company (PODC)', 'department_name' => 'Information Technology',
    'purpose_name' => 'Scheduled maintenance', 'visit_from' => '2026-10-08 00:00:00', 'visit_to' => '2026-10-09 23:59:59'];
$html = App\Libraries\Gate_pass_email::departmentReview($request);
$check(App\Libraries\Gate_pass_email::isDepartmentReview($html), 'Generated HTML can be distinguished from legacy plain messages.');
$check(str_contains($html, 'Dear Manager of Information Technology Department,'), 'The greeting names the selected department.');
$check(str_contains($html, 'Maneesh Mohanan') && str_contains($html, 'GP-2026-001245'), 'Named requester and gate pass reference are included.');
$check(str_contains($html, '08 Oct 2026') && str_contains($html, '09 Oct 2026'), 'Visit dates keep the local calendar day.');
$check(str_contains(html_entity_decode($html), 'https://portal.example.invalid/index.php/gate_pass_department_requests'), 'Button uses the configured domain and existing department dashboard.');
$check(str_contains(html_entity_decode($html), 'https://portal.example.invalid/assets/images/port-duqum-email-logo.png'), 'Logo uses the current base URL without index.php.');
$check(str_contains($html, 'bgcolor="#ffffff"') && str_contains($html, 'max-width:640px'), 'An explicit white card supports common email clients.');
$check(str_contains($html, '@media only screen and (max-width:600px)') && str_contains($html, 'mso-padding-alt'), 'Responsive and Outlook-safe email markup is included.');
$check(!str_contains($html, '<script') && !str_contains($html, '<form'), 'Email contains no JavaScript or embedded login form.');
$unsafe = clone $request;
$unsafe->requester_name = '<script>alert("name")</script>';
$unsafe->purpose_name = '<img src=x onerror=alert(1)>';
$unsafe->department_name = 'IT & Support Department';
$escaped = App\Libraries\Gate_pass_email::departmentReview($unsafe);
$check(!str_contains($escaped, '<script>') && !str_contains($escaped, '<img src=x'), 'User-supplied content cannot inject HTML.');
$check(str_contains($escaped, '&lt;script&gt;') && str_contains($escaped, 'IT &amp; Support Department,'), 'User content is encoded, with no duplicated Department suffix.');
$check(!App\Libraries\Gate_pass_email::isDepartmentReview('A visitor has been blocked.'), 'Existing block notifications stay plain text.');
$fallback = App\Libraries\Gate_pass_email::departmentReview((object) []);
$check(str_contains($fallback, 'Dear Department Manager,') && str_contains($fallback, 'a registered user'), 'Incomplete legacy names have clear fallbacks.');
$path = getenv('POD_GATE_PASS_EMAIL_PREVIEW');
if ($path) { file_put_contents($path, $html); }
echo "$checks gate pass email template checks passed.\n";
