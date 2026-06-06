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

$controller = $read("app/Controllers/Tender_reports.php");
$view = $read("app/Views/tender_reports/details.php");
$model = $read("app/Models/Tender_communications_model.php");

$assertContains("form_open_multipart(get_uri(\"tender_reports/save_update\")", $view, "group update form should allow file uploads");
$assertContains("name=\"update_files[]\"", $view, "group update form should expose an attachment input");
$assertContains("communication_attachments", $view, "tender report communications table should render saved attachments");
$assertContains("tender_reports/download_update_attachment", $view, "report attachments should have a procurement download route");

$assertContains("_save_update_files", $controller, "group update save should persist uploaded files");
$assertContains("getFileMultiple(\"update_files\")", $controller, "group update save should read the uploaded files input");
$assertContains("save_attachments((int) $", $controller, "group update save should store attachments through the communications attachment table");
$assertContains("get_attachments_map", $controller, "tender report details should load communication attachments");
$assertContains("download_update_attachment", $controller, "procurement report should allow secure group update attachment downloads");

$assertContains("save_attachments", $model, "communications model should provide shared attachment persistence");
$assertContains("get_attachment", $model, "communications model should provide shared attachment lookup");

echo "OK" . PHP_EOL;
