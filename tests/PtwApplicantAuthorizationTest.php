<?php

$root = dirname(__DIR__);

$fail = static function (string $message): void {
    fwrite(STDERR, 'Assertion failed: ' . $message . PHP_EOL);
    exit(1);
};
$read = static function (string $path) use ($root, $fail): string {
    $absolute = $root . '/' . $path;
    if (!is_file($absolute)) {
        $fail($path . ' must exist');
    }
    return (string) file_get_contents($absolute);
};
$contains = static function (string $needle, string $haystack, string $message) use ($fail): void {
    if (!str_contains($haystack, $needle)) {
        $fail($message . ' Missing: ' . $needle);
    }
};
$notContains = static function (string $needle, string $haystack, string $message) use ($fail): void {
    if (str_contains($haystack, $needle)) {
        $fail($message . ' Unexpected: ' . $needle);
    }
};

$migration = $read('app/Database/Migrations/2026_08_03_120000_ptw_applicant_company_assignments.php');
$sql = $read('app/Database/SQL/ptw_applicant_company_assignments_upgrade_pod.sql');
foreach ([$migration, $sql] as $schema) {
    $contains('ptw_applicant_users', $schema, 'PTW applicant assignments have deployment schema');
    $contains('user_id', $schema, 'assignment stores the user identity');
    $contains('company_id', $schema, 'assignment stores the company boundary');
    $contains('status', $schema, 'assignment supports suspension');
}

$model = $read('app/Models/Ptw_applicant_users_model.php');
$contains('get_active_company_ids', $model, 'applicant company scope is resolved server-side');
$contains('has_active_company_assignment', $model, 'object authorization rechecks the assignment');
$contains('"status" => "active"', $model, 'only active assignments grant company access');

$admin = $read('app/Controllers/Ptw_applicant_users.php');
$contains('access_only_ptw("applicant_users"', $admin, 'assignment management is permission protected');
$contains('save_operational_user_assignment', $admin, 'assignment creation uses the secured identity workflow');
$contains('"status" => "inactive"', $admin, 'removal suspends the assignment');
$contains('"deleted" => 1', $admin, 'removal archives the assignment and excludes it from active lists');
$contains('delete_error', $admin, 'failed archive returns a readable refusal');

$portal = $read('app/Controllers/Ptw_portal.php');
$contains('_get_allowed_ptw_applicant_companies()', $portal, 'the form receives the configured company choices');
$contains('return $this->Gate_pass_companies_model->get_details()->getResult();', $portal, 'registered applicants can select a non-deleted company');
$notContains('Ptw_applicant_users_model', $portal, 'applicant operations no longer require a company assignment');
$contains('$postedCompanyId !== (int) ($existing->company_id ?? 0)', $portal, 'company identity is immutable after creation');
$contains('app_redirect("forbidden")', $portal, 'authorization failures are denied');
$contains('AND $assignments.company_id=?', $portal, 'reviewers are constrained by persisted company ID');
$notContains('getPost("company_name")', $portal, 'posted company names never establish authority');

$form = $read('app/Views/ptw_portal/applications/form.php');
$contains('name="company_id"', $form, 'company selection posts an immutable identifier');
$notContains('name="company_name"', $form, 'company authority is not accepted as free text');

echo 'PTW applicant-company authorization contracts passed.' . PHP_EOL;
