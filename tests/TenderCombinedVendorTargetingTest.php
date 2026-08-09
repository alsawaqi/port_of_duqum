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

$functionBody = function (string $source, string $functionName) use ($assertTrue): string {
    $pattern = '/(?:public|protected|private)\s+function\s+'
        . preg_quote($functionName, '/')
        . '\s*\([^)]*\)[^{]*\{([\s\S]*?)(?=\n\s*(?:public|protected|private)\s+function|\z)/';
    $matched = preg_match($pattern, $source, $matches);
    $assertTrue((bool) $matched, "expected function $functionName to exist");
    return $matches[1] ?? "";
};

$form = $read("app/Views/tender_procurement_inbox/form.php");
$modalForm = $read("app/Views/tender_procurement_inbox/modal_form.php");
$controller = $read("app/Controllers/Tender_procurement_inbox.php");
$manager = $read("app/Controllers/Tender_procurement_manager_inbox.php");
$tendersModel = $read("app/Models/Tenders_model.php");

$assertContains(
    "group_and_specific_vendors",
    $form,
    "procurement form should offer the combined group and specific vendor mode"
);
$assertTrue(
    substr_count($form, "group_and_specific_vendors") >= 3,
    "combined mode should be selectable and should reveal both target controls"
);
$assertContains(
    'name="specific_vendor_ids[]"',
    $form,
    "combined targeting should submit every selected vendor"
);
$assertContains(
    "vendor_group_id",
    $form,
    "combined targeting should submit the selected vendor group"
);
$assertContains(
    "group_and_specific_vendors",
    $modalForm,
    "the legacy procurement editor should preserve and edit combined targeting"
);
$assertContains(
    'name="specific_vendor_ids[]"',
    $modalForm,
    "the legacy procurement editor should submit selected specific vendors"
);

$saveTargetBody = $functionBody($controller, "_save_target_rule");
$assertContains(
    "group_and_specific_vendors",
    $saveTargetBody,
    "procurement persistence should recognize combined targeting"
);
$assertContains(
    "tender_target_specialties",
    $saveTargetBody,
    "combined targeting should persist the vendor group rule"
);
$assertContains(
    "tender_target_vendors",
    $saveTargetBody,
    "combined targeting should persist every specific vendor rule"
);

$procurementUnion = $functionBody($controller, "_sync_invites_by_group_and_specific_vendors");
$assertContains("vendor_group_id", $procurementUnion, "combined invite sync should read the vendor group");
$assertContains("vendor_ids", $procurementUnion, "combined invite sync should read the specific vendor ids");
$assertContains("_replace_invites", $procurementUnion, "combined invite sync should replace invites once with the union");
$assertTrue(
    (bool) preg_match('/array_merge|array_unique|\bUNION\b|combined_vendor_ids|vendor_ids\s*\[/i', $procurementUnion),
    "combined invite sync should union group members with selected vendors"
);

$managerTargetBody = $functionBody($manager, "_sync_target_rule_from_payload");
$assertContains(
    "group_and_specific_vendors",
    $managerTargetBody,
    "manager approval should persist both halves of a combined target"
);
$assertContains("tender_target_specialties", $managerTargetBody, "manager approval should persist the group rule");
$assertContains("tender_target_vendors", $managerTargetBody, "manager approval should persist specific vendor rules");

$managerUnion = $functionBody($manager, "_sync_invites_by_group_and_specific_vendors");
$assertContains("_replace_invites", $managerUnion, "manager approval should replace invites once with the combined union");
$assertTrue(
    (bool) preg_match('/array_merge|array_unique|\bUNION\b|combined_vendor_ids|vendor_ids\s*\[/i', $managerUnion),
    "manager approval should union group members with selected vendors"
);

$assertContains(
    "target_vendor.id IS NOT NULL",
    $tendersModel,
    "vendor eligibility should retain specific vendor targeting"
);
$assertContains(
    "target.vendor_group_id = vendor_profile.vendor_group_id",
    $tendersModel,
    "vendor eligibility should retain vendor group targeting"
);
$assertContains(
    'FROM $ttv any_target_vendor',
    $tendersModel,
    "open tenders with specific vendor targets should not fall back to visibility for every vendor"
);
$assertTrue(
    substr_count($tendersModel, 'FROM $ttv any_target_vendor') >= 4,
    "list/detail eligibility and source labels should all recognize that a specific target exists"
);

echo "OK" . PHP_EOL;
