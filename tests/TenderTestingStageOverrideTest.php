<?php

$root = dirname(__DIR__);

$controller = file_get_contents($root . "/app/Controllers/Tender_procurement_inbox.php");
$form = file_get_contents($root . "/app/Views/tender_procurement_inbox/form.php");
$modal = file_get_contents($root . "/app/Views/tender_procurement_inbox/modal_form.php");
$index = file_get_contents($root . "/app/Views/tender_procurement_inbox/index.php");
$libraryPath = $root . "/app/Libraries/Tender_testing_stage.php";

$assertTrue = function ($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: " . $message . PHP_EOL);
        exit(1);
    }
};

$assertContains = function (string $needle, string $haystack, string $message) use ($assertTrue): void {
    $assertTrue(strpos($haystack, $needle) !== false, $message);
};

$assertTrue(is_file($libraryPath), "testing stage helper library should exist");

require_once $libraryPath;

$stageHelper = new \App\Libraries\Tender_testing_stage();
$options = $stageHelper->options(false);
$expectedOrder = [
    "published_bidding",
    "clarification",
    "site_visit",
    "technical_3key",
    "technical",
    "commercial",
];

$assertTrue(array_keys($options) === $expectedOrder, "testing stage options should be ordered from published bidding through commercial evaluation");
$assertTrue($stageHelper->normalize(" clarification ") === "clarification", "testing stage normalize should accept valid trimmed stages");
$assertTrue($stageHelper->normalize("award_decision") === null, "temporary testing selector should stop at commercial evaluation");

$clarificationPayload = $stageHelper->build_payload("clarification", (object) ["published_at" => null], "2026-05-10 10:00:00");
$assertTrue(($clarificationPayload["status"] ?? null) === "published", "clarification testing stage should publish the tender");
$assertTrue(($clarificationPayload["workflow_stage"] ?? null) === "bidding", "clarification testing stage should keep vendor bidding open");
$assertTrue(strtotime($clarificationPayload["clarification_deadline"]) > strtotime("2026-05-10 10:00:00"), "clarification testing stage should move clarification deadline into the future");

$technicalPayload = $stageHelper->build_payload("technical", (object) ["published_at" => "2026-05-09 09:00:00"], "2026-05-10 10:00:00");
$assertTrue(($technicalPayload["status"] ?? null) === "closed", "technical testing stage should close the tender");
$assertTrue(($technicalPayload["workflow_stage"] ?? null) === "technical", "technical testing stage should open technical evaluation");
$assertTrue(strtotime($technicalPayload["closing_at"]) < strtotime("2026-05-10 10:00:00"), "technical testing stage should make submission deadline past");
$assertTrue(strtotime($technicalPayload["technical_end_at"]) > strtotime("2026-05-10 10:00:00"), "technical testing stage should keep technical evaluation open");

$commercialPayload = $stageHelper->build_payload("commercial", (object) ["published_at" => "2026-05-09 09:00:00"], "2026-05-10 10:00:00");
$assertTrue(($commercialPayload["workflow_stage"] ?? null) === "commercial", "commercial testing stage should open commercial evaluation");
$assertTrue(strtotime($commercialPayload["commercial_end_at"]) > strtotime("2026-05-10 10:00:00"), "commercial testing stage should keep commercial evaluation open");

$assertContains("Tender_testing_stage", $controller, "procurement controller should use testing stage helper");
$assertContains("testing_workflow_stage", $controller, "procurement save should read testing stage field");
$assertContains('$validation_rules["closing_at"] = "required";', $controller, "normal saves should still require submission deadline");
$assertContains("if (!\$testing_workflow_stage)", $controller, "testing stage saves should bypass normal closing-date requirement and manager submission path");
$assertContains("_apply_testing_stage_override", $controller, "procurement save should apply testing stage override");
$assertContains("testing_stage_override", $controller, "testing stage override should be logged distinctly");

$assertContains("Temporary Testing Stage", $form, "full procurement form should show temporary testing stage selector");
$assertContains("testing_workflow_stage", $form, "full procurement form should submit testing stage");
$assertContains("skipClosingForTesting", $form, "full procurement form should not force submission deadline when testing stage is selected");
$assertContains("Temporary Testing Stage", $modal, "modal procurement form should show temporary testing stage selector");
$assertContains("testing_workflow_stage", $modal, "modal procurement form should submit testing stage");
$assertContains("Workflow Stage", $index, "procurement inbox should show current workflow stage");

echo "OK" . PHP_EOL;
