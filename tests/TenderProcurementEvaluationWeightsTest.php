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

$tendersModel = $read("app/Models/Tenders_model.php");
$criteriaModel = $read("app/Models/Tender_criteria_model.php");
$procurementController = $read("app/Controllers/Tender_procurement_inbox.php");
$managerController = $read("app/Controllers/Tender_procurement_manager_inbox.php");
$reportsController = $read("app/Controllers/Tender_reports.php");
$procurementForm = $read("app/Views/tender_procurement_inbox/form.php");
$procurementModal = $read("app/Views/tender_procurement_inbox/modal_form.php");
$managerDetails = $read("app/Views/tender_procurement_manager_inbox/details.php");
$upgradeSql = $read("app/Database/SQL/tender_evaluation_weights_upgrade_pod.sql");
$baseSql = $read("app/Database/SQL/tender.sql");

$assertContains("ensure_tender_evaluation_weight_columns", $tendersModel, "tenders model should ensure tender evaluation weight columns");
$assertContains("ADD COLUMN `evaluation_method`", $tendersModel . $upgradeSql . $baseSql, "SQL/runtime schema should add evaluation method to tenders");
$assertContains("ADD COLUMN `technical_weight`", $tendersModel . $upgradeSql . $baseSql, "SQL/runtime schema should add technical weight to tenders");
$assertContains("ADD COLUMN `commercial_weight`", $tendersModel . $upgradeSql . $baseSql, "SQL/runtime schema should add commercial weight to tenders");
$assertContains("req.`technical_weight`", $tendersModel . $upgradeSql . $baseSql, "linked tenders should inherit request weights when columns are added");

$assertContains("getPost(\"evaluation_method\")", $procurementController, "procurement save should read evaluation method from the form");
$assertContains("getPost(\"technical_weight\")", $procurementController, "procurement save should read technical weight from the form");
$assertContains("getPost(\"commercial_weight\")", $procurementController, "procurement save should read commercial weight from the form");
$assertContains("Technical and Commercial weights must total 100.", $procurementController, "procurement save should validate total evaluation weights");
$assertContains('"evaluation_method" => $evaluation_method', $procurementController, "procurement save should persist evaluation method");
$assertContains('"technical_weight" => $technical_weight', $procurementController, "procurement save should persist technical weight");
$assertContains('"commercial_weight" => $commercial_weight', $procurementController, "procurement save should persist commercial weight");

$assertContains('"name" => "technical_weight"', $procurementForm, "full procurement form should include technical weight input");
$assertContains('"name" => "commercial_weight"', $procurementForm, "full procurement form should include commercial weight input");
$assertContains("data-preview=\"technical_weight\"", $procurementForm, "full procurement preview should show technical weight");
$assertContains("data-preview=\"commercial_weight\"", $procurementForm, "full procurement preview should show commercial weight");
$assertContains('"name" => "technical_weight"', $procurementModal, "procurement modal should include technical weight input");
$assertContains('"name" => "commercial_weight"', $procurementModal, "procurement modal should include commercial weight input");

$assertContains("COALESCE(t.technical_weight, req.technical_weight) AS technical_weight", $reportsController, "reports should prefer tender technical weight and fall back to request weight");
$assertContains("COALESCE(t.commercial_weight, req.commercial_weight) AS commercial_weight", $reportsController, "reports should prefer tender commercial weight and fall back to request weight");
$assertContains('COALESCE($t.technical_weight, $tr.technical_weight) AS technical_weight', $criteriaModel, "default technical criteria should use tender-level weights");
$assertContains("Evaluation Weights", $managerDetails, "manager review should display evaluation weights");
$assertContains('"technical_weight" => "Technical Weight"', $managerController, "manager change summary should label technical weight changes");
$assertContains('"commercial_weight" => "Commercial Weight"', $managerController, "manager change summary should label commercial weight changes");

echo "OK" . PHP_EOL;
