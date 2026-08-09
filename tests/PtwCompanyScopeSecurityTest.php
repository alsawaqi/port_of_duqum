<?php

$root = dirname(__DIR__);

$fail = static function (string $message): void {
    fwrite(STDERR, 'Assertion failed: ' . $message . PHP_EOL);
    exit(1);
};
$assertTrue = static function ($condition, string $message) use ($fail): void {
    if (!$condition) {
        $fail($message);
    }
};
$assertContains = static function (string $needle, string $haystack, string $message) use ($fail): void {
    if (!str_contains($haystack, $needle)) {
        $fail($message . ' Missing: ' . $needle);
    }
};

$read = static function (string $path) use ($root, $fail): string {
    $absolute = $root . '/' . $path;
    if (!is_file($absolute)) {
        $fail($path . ' must exist');
    }
    return (string)file_get_contents($absolute);
};

$migration = $read('app/Database/Migrations/2026_08_03_100000_ptw_application_company_scope.php');
$manualSql = $read('app/Database/SQL/ptw_company_scope_upgrade_pod.sql');
foreach ([$migration, $manualSql] as $schema) {
    $assertContains('company_id', $schema, 'PTW company identity is persisted');
    $assertContains('GROUP BY BINARY', $schema, 'backfill groups exact case-sensitive names');
    $assertContains('HAVING COUNT(*) = 1', $schema, 'ambiguous company names are not backfilled');
    $assertContains('deleted', $schema, 'only active company rows participate in backfill');
    $assertContains('IS NULL', $schema, 'backfill does not overwrite an established relation');
}
$assertContains("'null' => true", $migration, 'legacy ambiguous rows remain nullable and fail closed');

$portal = $read('app/Controllers/Ptw_portal.php');
$form = $read('app/Views/ptw_portal/applications/form.php');
$assertContains('getPost("company_id")', $portal, 'the portal receives a company identifier');
$assertContains('->get_details(["id" => $postedCompanyId])', $portal, 'the selected company is resolved server-side');
$assertContains('"company_id" => (int)$selected_company->id', $portal, 'the resolved company ID is persisted');
$assertContains('"company_name" => trim((string)$selected_company->name)', $portal, 'the display name snapshot comes from the server');
$assertTrue(!str_contains($portal, 'getPost("company_name")'), 'posted company text is never trusted');
$assertContains('if ((int) $app->applicant_user_id === (int) $this->login_user->id)', $portal, 'applicant ownership access remains intact');
$assertContains('if ((int)$app->applicant_user_id !== (int)$this->login_user->id', $portal, 'applicant edit ownership remains intact');
$assertContains('$company_id = (int)($app->company_id ?? 0);', $portal, 'legacy null company rows fail closed for reviewers');
$assertContains('AND $assignments.company_id=?', $portal, 'portal reviewer access compares the persisted company ID');

$assertContains('name="company_id"', $form, 'the company select posts an ID');
$assertContains('value="<?php echo (int)$co->id; ?>"', $form, 'company option values are IDs');
$assertContains('<?php echo esc($co->name); ?>', $form, 'company names remain display labels');
$assertTrue(!str_contains($form, 'name="company_name"'), 'the form does not post free-text company authority');
$assertContains("setRev('company_name', 'company_id');", $form, 'the review step displays the selected company label');

$applicationsModel = $read('app/Models/Ptw_applications_model.php');
$assertContains('$options["company_ids"]', $applicationsModel, 'review queues can be SQL-scoped to assigned companies');
$assertContains('AND 1=0', $applicationsModel, 'an empty company scope fails closed');

$roles = ['hsse', 'hmo', 'terminal'];
foreach ($roles as $role) {
    $inbox = $read('app/Controllers/Ptw_' . $role . '_inbox.php');
    $assignment = $read('app/Models/Ptw_' . $role . '_users_model.php');

    $assertContains('get_active_company_ids', $inbox, strtoupper($role) . ' list is company-scoped');
    $assertContains('$application->company_id ?? 0', $inbox, strtoupper($role) . ' reads persisted company identity');
    $assertContains('has_active_company_assignment', $inbox, strtoupper($role) . ' object authorization compares IDs');
    $assertTrue(!str_contains($inbox, '$application->company_name ??'), strtoupper($role) . ' no longer authorizes by company text');

    $assertContains('INNER JOIN $companies', $assignment, strtoupper($role) . ' assignments require an active company');
    $assertContains('AND $ptw.company_id=?', $assignment, strtoupper($role) . ' assignment lookup compares company IDs');
    $assertContains('if ($user_id < 1 || $company_id < 1)', $assignment, strtoupper($role) . ' null or invalid IDs fail closed');
}

echo 'PTW company-scope security contracts passed.' . PHP_EOL;
