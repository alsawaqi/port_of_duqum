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

$reportsController = $read("app/Controllers/Tender_reports.php");
$reportsView = $read("app/Views/tender_reports/details.php");

$assertContains("weighted_evaluation_scores", $reportsController, "tender report should pass weighted evaluation score rows to the view");
$assertContains("_get_weighted_evaluation_scores", $reportsController, "tender report should calculate weighted evaluation scores");
$assertContains("_stage_score_max", $reportsController, "weighted calculation should normalize technical scores against their configured max");
$assertContains("_weighted_score_points", $reportsController, "weighted calculation should convert normalized score into weighted points");
$assertContains("technical_weight", $reportsController, "weighted calculation should use procurement technical weight");
$assertContains("commercial_weight", $reportsController, "weighted calculation should use procurement commercial weight");

$assertContains("Weighted Evaluation Ranking", $reportsView, "report should show weighted ranking section");
$assertContains("Technical Weighted", $reportsView, "report should show technical weighted score");
$assertContains("Commercial Weighted", $reportsView, "report should show commercial weighted score");
$assertContains("Final Weighted Score", $reportsView, "report should show final weighted score");
$assertContains("weighted-score-complete", $reportsView, "report should mark complete weighted scores");

echo "OK" . PHP_EOL;
