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

$controller = $read("app/Controllers/Tender_procurement_inbox.php");
$manager = $read("app/Controllers/Tender_procurement_manager_inbox.php");
$form = $read("app/Views/tender_procurement_inbox/form.php");
$sql = $read("app/Database/SQL/tender_specific_vendor_targets_upgrade_pod.sql");

$assertContains("specific_vendors", $controller, "procurement save should accept a specific vendor target mode");
$assertContains("grade", $controller, "procurement save should accept a grade target mode");
$assertContains("_sync_invites_from_specific_vendors", $controller, "specific vendor targets should sync tender invites");
$assertContains("_sync_invites_by_vendor_grade", $controller, "grade targets should sync tender invites by vendor grade");
$assertContains("specific_vendor_ids", $controller, "selected vendor ids should be read from the form");
$assertContains("vendor_grade_id", $controller, "selected vendor grade should be read from the form");
$assertContains("specific_vendors", $manager, "manager approval should preserve/apply specific vendor pending targets");
$assertContains("_sync_invites_from_specific_vendors", $manager, "manager approval should invite selected specific vendors");
$assertContains("_sync_invites_by_vendor_grade", $manager, "manager approval should invite vendors in selected grade");
$assertContains("name=\"specific_vendor_ids[]\"", $form, "tender form should post multiple selected vendor ids");
$assertContains("id='vendor_picker_modal'", $form, "tender form should include a vendor search picker modal");
$assertContains("vendor_grade_id", $form, "tender form should allow selecting a vendor grade target");
$assertContains("pod_tender_target_vendors", $sql, "SQL upgrade should create selected tender vendor target table");
$assertContains("vendor_grade_id", $sql, "SQL upgrade should add grade target support");

echo "OK" . PHP_EOL;
