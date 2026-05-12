<?php

$root = dirname(__DIR__);

$read = function (string $path) use ($root): string {
    $full = $root . "/" . $path;
    return is_file($full) ? file_get_contents($full) : "";
};

$assertContains = function (string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "FAIL: " . $message . PHP_EOL);
        exit(1);
    }
};

$controller = $read("app/Controllers/Gate_pass_rop_inbox.php");
$index = $read("app/Views/gate_pass_rop_inbox/index.php");
$requestInfo = $read("app/Views/gate_pass_includes/request_information_card.php");
$english = $read("app/Language/english/custom_lang.php");

$assertContains("Gate_pass_companies_model", $controller, "ROP inbox should load companies for the requested filter");
$assertContains("Gate_pass_departments_model", $controller, "ROP inbox should load departments for the requested filter");
$assertContains("Gate_pass_purposes_model", $controller, "ROP inbox should load purposes for the requested filter");
$assertContains("get_distinct_nationalities", $controller, "ROP inbox should load nationality options");
$assertContains("_get_filter_options", $controller, "ROP list and export should share filter option parsing");
$assertContains('$this->request->getGet("company_id")', $controller, "ROP filter should accept company");
$assertContains('$this->request->getGet("department_id")', $controller, "ROP filter should accept department");
$assertContains('$this->request->getGet("nationality")', $controller, "ROP filter should accept nationality");
$assertContains('$this->request->getGet("gate_pass_purpose_id")', $controller, "ROP filter should accept purpose");
$assertContains('$this->request->getGet("date_from")', $controller, "ROP filter should accept visit date from");
$assertContains('$this->request->getGet("date_to")', $controller, "ROP filter should accept visit date to");

$assertContains("gp-rop-filter-form", $index, "ROP inbox should render a filter form");
$assertContains("filter_company_id", $index, "ROP filter should include company");
$assertContains("filter_department_id", $index, "ROP filter should include department");
$assertContains("filter_nationality", $index, "ROP filter should include nationality");
$assertContains("filter_purpose_id", $index, "ROP filter should include purpose");
$assertContains("filter_date_from", $index, "ROP filter should include visit date from");
$assertContains("filter_date_to", $index, "ROP filter should include visit date to");
$assertContains("DataTable().ajax.url(url).load", $index, "ROP filter should reload the existing DataTable with filtered URL");
$assertContains("gp-rop-export-btn", $index, "ROP export should preserve active filters");

$assertContains("gate_pass_date_count", $requestInfo, "request information card should show a date count field");
$assertContains("gate_pass_visit_duration_label", $requestInfo, "date count should use inclusive visit duration helper");
$assertContains('$lang["gate_pass_date_count"]', $english, "English language file should define Date count label");

foreach ([
    "app/Views/gate_pass_portal/requests/details.php",
    "app/Views/gate_pass_department_requests/details.php",
    "app/Views/gate_pass_commercial_inbox/details.php",
    "app/Views/gate_pass_security_inbox/details.php",
    "app/Views/gate_pass_rop_inbox/details.php",
] as $path) {
    $assertContains("request_information_card", $read($path), "$path should include the shared request information card");
}

echo "OK" . PHP_EOL;
