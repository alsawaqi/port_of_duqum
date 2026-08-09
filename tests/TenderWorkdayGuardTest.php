<?php

$root = dirname(__DIR__);

$read = function (string $path) use ($root): string {
    $full = $root . "/" . $path;
    return is_file($full) ? file_get_contents($full) : "";
};

$assertTrue = function ($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: " . $message . PHP_EOL);
        exit(1);
    }
};

$assertContains = function (string $needle, string $haystack, string $message) use ($assertTrue): void {
    $assertTrue(strpos($haystack, $needle) !== false, $message);
};

$assertMatches = function (string $pattern, string $haystack, string $message) use ($assertTrue): void {
    $assertTrue((bool) preg_match($pattern, $haystack), $message);
};

$controller = $read("app/Controllers/Tender_procurement_inbox.php");
$form = $read("app/Views/tender_procurement_inbox/form.php");
$modalForm = $read("app/Views/tender_procurement_inbox/modal_form.php");

$assertContains(
    "_validate_tender_workdays",
    $controller,
    "procurement save should use a dedicated server-side workday validator"
);
$assertContains(
    '$this->_validate_tender_workdays($new_milestones)',
    $controller,
    "the server should validate the final milestone values after schedule cascading"
);
$assertContains(
    '$this->_validate_tender_workdays($milestones)',
    $controller,
    "publication validation should also reject imported or pre-existing weekend milestones"
);
$assertMatches(
    '/(?:date|format)\s*\(\s*["\']N["\']/',
    $controller,
    "server-side validation should determine the ISO weekday"
);
$assertMatches(
    '/(?:\[\s*5\s*,\s*6\s*\]|===?\s*5.*===?\s*6)/s',
    $controller,
    "server-side validation should reject Friday and Saturday"
);

$assertContains(
    "validateTenderWorkdays",
    $form,
    "the procurement form should validate workdays before submission"
);
$assertContains(
    "tender-workday-datetime",
    $form,
    "tender schedule controls should be marked for workday validation"
);
$assertMatches(
    '/(?:getDay\(\)|\.day\(\))/',
    $form,
    "client-side validation should inspect the selected weekday"
);
$assertMatches(
    '/(?:\[\s*5\s*,\s*6\s*\].*(?:includes|indexOf)|(?:===?\s*5.*===?\s*6))/s',
    $form,
    "client-side validation should reject both Friday and Saturday"
);
$assertContains("Friday", $form, "the UI should explain that Friday cannot be selected");
$assertContains("Saturday", $form, "the UI should explain that Saturday cannot be selected");
$assertTrue(
    substr_count($form, "validateTenderWorkdays(") >= 2,
    "workday validation should be declared and invoked"
);
$assertContains("validateTenderWorkdays", $modalForm, "the legacy editor should apply the same workday guard");

echo "OK" . PHP_EOL;
