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

$assertNotContains = function (string $needle, string $haystack, string $message) use ($assertTrue): void {
    $assertTrue(strpos($haystack, $needle) === false, $message);
};

$openingController = $read("app/Controllers/Tender_committee_opening_inbox.php");
$openingModel = $read("app/Models/Tender_bid_openings_model.php");
$bidsModel = $read("app/Models/Tender_bids_model.php");
$tendersModel = $read("app/Models/Tenders_model.php");
$reportsController = $read("app/Controllers/Tender_reports.php");
$reportsView = $read("app/Views/tender_reports/details.php");
$openingModal = $read("app/Views/tender_committee_opening_inbox/modal_form.php");
$openingForm = $read("app/Views/tender_reports/bid_opening_form.php");
$testingStage = $read("app/Libraries/Tender_testing_stage.php");
$sql = $read("app/Database/SQL/tender_single_3key_opening_upgrade_pod.sql");

$assertContains("ensure_single_opening_signature_schema", $openingModel, "opening model should ensure signature/manual-form schema");
$assertContains("save_signature", $openingModel, "opening model should save committee digital signatures");
$assertContains("mark_all_signatures_completed", $openingModel, "opening model should mark an opening signed after all committee members sign");
$assertContains("manual_accepted", $openingModel, "opening model should support procurement manual form bypass status");
$assertContains("get_bid_summary_for_opening", $openingModel, "opening model should expose technical and commercial bid summary for signing/form output");

$assertContains("sign_opening", $openingController, "committee opening controller should expose a sign action");
$assertContains("download_bid_document", $openingController, "committee members should be able to open bid documents after the bid package is unlocked");
$assertContains("\"Bid Opening\"", $openingController, "committee opening controller should use one generic bid opening label");
$assertContains("'technical' AS opening_stage", $bidsModel, "committee opening list should only return the single technical/bid opening stage");
$assertNotContains("'commercial' AS opening_stage", $bidsModel, "committee opening list should not create a second commercial opening row");
$assertNotContains("is_technical_evaluation_complete", $openingController, "committee opening controller should not wait for technical evaluation before a commercial opening");

$assertContains("save_manual_bid_opening_form", $reportsController, "procurement should be able to upload a manual opening form");
$assertContains("start_technical_review", $reportsController, "procurement should explicitly move signed/manual openings into technical review");
$assertContains("manual_bid_opening_form", $reportsView, "tender report should render the manual opening form upload control");
$assertContains("Start Technical Review", $reportsView, "tender report should show the procurement move-to-technical action");

$assertContains("committee_signature_statement", $openingModal, "committee modal should require a digital signature statement");
$assertContains("tender_committee_opening_inbox/download_bid_document", $openingModal, "committee modal should link the opened technical and commercial bid documents");
$assertContains("Bid Opening Form", $openingForm, "downloadable opening form should be single generic form");
$assertContains("Committee Digital Signatures", $openingForm, "downloadable form should include committee signatures");
$assertContains("Commercial With Price", $openingForm, "downloadable form should include commercial-side bid data");

$assertContains("Technical review completed or deadline reached; commercial evaluation started by the system.", $tendersModel, "workflow should move directly from technical evaluation to commercial evaluation");
$assertNotContains("commercial 3-key opening started by the system", $tendersModel, "workflow should not auto-open a second commercial 3-key stage");
$assertContains("status IN ('signed','manual_accepted')", $tendersModel, "auto/manual progression should require signed or manually accepted bid opening");

$assertNotContains("\"committee_3key\"", $testingStage, "temporary testing stages should not include a separate commercial 3-key stage");
$assertContains("tender_single_3key_opening_upgrade_pod.sql", $sql, "SQL upgrade should identify the single 3-key opening change");
$assertContains("signed_at", $sql, "SQL upgrade should add signature completion fields");
$assertContains("manual_form_path", $sql, "SQL upgrade should add procurement manual form upload fields");

echo "OK" . PHP_EOL;
