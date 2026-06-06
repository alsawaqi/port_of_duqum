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

$controller = $read("app/Controllers/Tender_procurement_inbox.php");
$form = $read("app/Views/tender_procurement_inbox/form.php");
$modal = $read("app/Views/tender_procurement_inbox/modal_form.php");

$assertContains("_validate_committee_role_selection", $controller, "procurement save should validate committee role conflicts server-side");
$assertContains("Chairman and secretary must be different committee users.", $controller, "backend should reject the same user as chairman and secretary");
$assertContains("Chairman and secretary cannot also be selected as ITC members.", $controller, "backend should reject chairman/secretary inside ITC members");
$assertContains('_validate_committee_role_selection($posted_team_ids)', $controller, "posted committee teams should be checked before saving or approval capture");
$assertContains('_validate_committee_role_selection($team_ids)', $controller, "existing committee teams should be checked before publish/manager submission");

foreach ([$form, $modal] as $view) {
    $assertContains("data-committee-role='chairman'", $view, "committee chairman select should be marked for sequential UI control");
    $assertContains("data-committee-role='secretary'", $view, "committee secretary select should be marked for sequential UI control");
    $assertContains("data-committee-role='itc_member'", $view, "ITC member select should be marked for sequential UI control");
    $assertContains("initTenderCommitteeRoleSelection", $view, "committee selection UI should initialize sequential role behavior");
    $assertContains("Choose a chairman first.", $view, "secretary should explain why it is locked until chairman is selected");
    $assertContains("Choose a secretary before selecting ITC members.", $view, "ITC members should explain why they are locked until secretary is selected");
    $assertContains("syncDisabledSelect2", $view, "disabled select2 controls should visually refresh when dependencies change");
}

echo "OK" . PHP_EOL;
