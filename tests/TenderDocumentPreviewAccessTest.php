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


$functionBody = function (string $source, string $functionName) use ($assertTrue): string {
    $pattern = '/(?:public|protected|private)\s+function\s+'
        . preg_quote($functionName, '/')
        . '\s*\([^)]*\)[^{]*\{([\s\S]*?)(?=\n\s*(?:public|protected|private)\s+function|\z)/';
    $matched = preg_match($pattern, $source, $matches);
    $assertTrue((bool) $matched, "expected function $functionName to exist");
    return $matches[1] ?? "";
};

$procurement = $read("app/Controllers/Tender_procurement_inbox.php");
$procurementForm = $read("app/Views/tender_procurement_inbox/form.php");
$vendorPortal = $read("app/Controllers/Vendor_portal.php");
$vendorDetails = $read("app/Views/vendor_portal/tenders/details.php");

$procurementPreview = $functionBody($procurement, "preview_tender_document");
$assertContains(
    "_get_accessible_tender_document_context",
    $procurementPreview,
    "procurement preview should resolve an authorized tender document"
);
$assertMatches(
    '/_make_[a-z_]*preview_data/',
    $procurementPreview,
    "procurement preview should use the common file preview contract"
);

$procurementView = $functionBody($procurement, "view_tender_document");
$assertContains(
    "_get_accessible_tender_document_context",
    $procurementView,
    "procurement inline view should repeat the document authorization check"
);
$assertMatches(
    '/_serve_[a-z_]*document(?:_file)?/',
    $procurementView,
    "procurement inline view should serve only the authorized file"
);

$procurementDownload = $functionBody($procurement, "download_tender_document");
$assertContains(
    "_get_accessible_tender_document_context",
    $procurementDownload,
    "procurement download should repeat the document authorization check"
);

$procurementContext = $functionBody($procurement, "_get_accessible_tender_document_context");
$assertContains(
    'access_only_tender("procurement", "view")',
    $procurementContext,
    "procurement document access should require procurement view permission"
);
$assertContains("Tender_documents_model->get_one", $procurementContext, "procurement preview should load a real document row");
$assertContains("deleted", $procurementContext, "procurement preview should reject deleted documents");
$assertContains("_get_tender_by_id", $procurementContext, "procurement preview should reject orphaned or deleted parent tenders");
$assertContains("is_file", $procurementContext, "procurement preview should reject missing files");

$procurementPreviewData = $functionBody($procurement, "_make_tender_document_preview_data");
$assertContains("mime_content_type", $procurementPreviewData, "preview selection should use detected MIME rather than a filename extension");
$procurementServe = $functionBody($procurement, "_serve_tender_document_file");
$assertContains("->download", $procurementServe, "large tender documents should use a streamed file-path response");
$assertTrue(
    strpos($procurementServe, "file_get_contents") === false,
    "tender documents should not be loaded entirely into PHP memory"
);

$assertContains(
    "tender_procurement_inbox/preview_tender_document/",
    $procurementForm,
    "saved procurement documents should expose a Preview action"
);
$assertContains(
    "tender_procurement_inbox/download_tender_document/",
    $procurementForm,
    "saved procurement documents should retain a Download action"
);

$vendorPreview = $functionBody($vendorPortal, "preview_tender_document");
$assertContains(
    "_get_vendor_tender_document_context",
    $vendorPreview,
    "vendor preview should resolve an authorized and unexpired document"
);
$assertMatches(
    '/_make_[a-z_]*preview_data/',
    $vendorPreview,
    "vendor preview should use the common file preview contract"
);

$vendorView = $functionBody($vendorPortal, "view_tender_document");
$assertContains(
    "_get_vendor_tender_document_context",
    $vendorView,
    "vendor inline view should repeat eligibility and expiry checks"
);
$assertMatches(
    '/_serve_[a-z_]*document(?:_file)?/',
    $vendorView,
    "vendor inline view should serve only the authorized file"
);

$vendorDownload = $functionBody($vendorPortal, "download_tender_document");
$assertContains(
    "_get_vendor_tender_document_context",
    $vendorDownload,
    "vendor download should share the same eligibility and expiry guard as preview"
);

$vendorContext = $functionBody($vendorPortal, "_get_vendor_tender_document_context");
$assertContains("_require_vendor_tender_access", $vendorContext, "vendor document access should require a vendor session");
$assertContains("get_vendor_visible_tender", $vendorContext, "vendor document access should enforce tender eligibility");
$assertContains("time_limited", $vendorContext, "vendor document access should enforce time-limited files");
$assertContains("expires_in_hours", $vendorContext, "vendor document access should calculate document expiry");
$assertContains("deleted", $vendorContext, "vendor document access should reject deleted documents");
$assertContains("is_file", $vendorContext, "vendor document access should reject missing files");

$vendorPreviewData = $functionBody($vendorPortal, "_make_vendor_tender_document_preview_data");
$assertContains("mime_content_type", $vendorPreviewData, "vendor preview selection should use detected MIME");
$vendorServe = $functionBody($vendorPortal, "_serve_vendor_tender_document");
$assertContains("->download", $vendorServe, "vendor tender documents should be streamed");
$assertTrue(strpos($vendorServe, "file_get_contents") === false, "vendor preview should remain memory-safe for large files");

$assertContains(
    "vendor_portal/preview_tender_document/",
    $vendorDetails,
    "vendor tender documents should expose a guarded Preview action"
);
$assertContains(
    "vendor_portal/download_tender_document/",
    $vendorDetails,
    "vendor tender documents should retain a guarded Download action"
);

echo "OK" . PHP_EOL;
