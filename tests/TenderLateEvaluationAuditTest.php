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

$model = $read("app/Models/Tender_evaluations_model.php");
$technical = $read("app/Controllers/Tender_technical_inbox.php");
$commercial = $read("app/Controllers/Tender_commercial_inbox.php");
$reports = $read("app/Controllers/Tender_reports.php");
$table = $read("app/Views/tender_reports/evaluation_table.php");
$bidsModel = $read("app/Models/Tender_bids_model.php");
$sql = $read("app/Database/SQL/tender_late_evaluation_audit_upgrade_pod.sql");

$assertContains("ensure_late_evaluation_audit_schema", $model, "evaluation model should ensure late/timing audit columns");
$assertContains("review_started_at", $model, "evaluation schema should track when review timing starts");
$assertContains("review_duration_seconds", $model, "evaluation schema should track review duration");
$assertContains("submitted_after_deadline", $model, "evaluation schema should flag late submissions");
$assertContains("late_review_status", $model, "evaluation schema should track procurement late review status");

$assertContains("workflow_stage IN ('technical', 'commercial', 'award_decision')", $bidsModel, "technical users should be able to revisit missed bids after technical stage closes");
$assertContains("_late_review_metadata", $technical, "technical evaluation should calculate late/deadline metadata");
$assertContains("_record_evaluation_history", $technical, "technical evaluation should write audit history");
$assertContains("Late technical evaluation saved", $technical, "technical late save should tell the user it is pending procurement review");
$assertContains("_late_review_metadata", $commercial, "commercial evaluation should calculate late/deadline metadata");
$assertContains("_record_evaluation_history", $commercial, "commercial evaluation should write audit history");

$assertContains("approve_late_evaluation", $reports, "procurement should be able to accept late evaluations");
$assertContains("reject_late_evaluation", $reports, "procurement should be able to reject late evaluations");
$assertContains("late_review_status", $reports, "procurement report queries should include late review status");
$assertContains("Review Duration", $table, "evaluation table should display review duration");
$assertContains("Late Submission", $table, "evaluation table should display late submission status");
$assertContains("Approve Late", $table, "evaluation table should expose late approval action");

$assertContains("pod_tender_evaluations", $sql, "SQL upgrade should target tender evaluations");
$assertContains("review_duration_seconds", $sql, "SQL upgrade should add review duration column");

echo "OK" . PHP_EOL;
