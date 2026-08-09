<?php

$source = file_get_contents(__DIR__ . "/../app/Controllers/Vendor_update_requests.php");
$vendorView = file_get_contents(__DIR__ . "/../app/Views/vendor_update_requests/vendor.php");

$fail = static function (string $message): void {
    fwrite(STDERR, "Assertion failed: {$message}" . PHP_EOL);
    exit(1);
};

$assertContains = static function (
    string $needle,
    string $haystack,
    string $message
) use ($fail): void {
    if (strpos($haystack, $needle) === false) {
        $fail($message . " Missing: " . $needle);
    }
};

$assertNotContains = static function (
    string $needle,
    string $haystack,
    string $message
) use ($fail): void {
    if (strpos($haystack, $needle) !== false) {
        $fail($message . " Unexpected: " . $needle);
    }
};

$sliceBetween = static function (
    string $haystack,
    string $start,
    string $end,
    string $message
) use ($fail): string {
    $startPosition = strpos($haystack, $start);
    if ($startPosition === false) {
        $fail($message . " Missing start marker: " . $start);
    }

    $endPosition = strpos($haystack, $end, $startPosition + strlen($start));
    if ($endPosition === false) {
        $fail($message . " Missing end marker: " . $end);
    }

    return substr($haystack, $startPosition, $endPosition - $startPosition);
};

$assertBefore = static function (
    string $first,
    string $second,
    string $haystack,
    string $message
) use ($fail): void {
    $firstPosition = strpos($haystack, $first);
    $secondPosition = strpos($haystack, $second);
    if ($firstPosition === false || $secondPosition === false || $firstPosition >= $secondPosition) {
        $fail($message);
    }
};

$bulkApprove = $sliceBetween(
    $source,
    "function bulk_approve()",
    "function bulk_reject()",
    "bulk approval should be inspectable"
);
$bulkReject = $sliceBetween(
    $source,
    "function bulk_reject()",
    "public function list_data()",
    "bulk rejection should be inspectable"
);
$approve = $sliceBetween(
    $source,
    "function approve()",
    "function reject()",
    "single approval should be inspectable"
);
$reject = $sliceBetween(
    $source,
    "function reject()",
    "public function review_modal()",
    "single rejection should be inspectable"
);
$review = $sliceBetween(
    $source,
    "public function review()",
    "public function view_document",
    "review-state transition should be inspectable"
);
$singleApproveRequest = $sliceBetween(
    $vendorView,
    "// APPROVE single (row)",
    "// BULK approve",
    "single approval request wiring should be inspectable"
);
$lock = $sliceBetween(
    $source,
    "private function lockPendingRequest",
    "private function finalizePendingRequest",
    "request locking helper should be inspectable"
);
$finalize = $sliceBetween(
    $source,
    "private function finalizePendingRequest",
    "private function _filter_payload_for_table",
    "request finalization helper should be inspectable"
);
$applyChanges = $sliceBetween(
    $source,
    "private function _apply_changes",
    "\n}",
    "change application policy should be inspectable"
);

foreach ([
    "bulk approval" => $bulkApprove,
    "bulk rejection" => $bulkReject,
    "single approval" => $approve,
    "single rejection" => $reject,
    "review transition" => $review,
] as $label => $section) {
    $assertContains(
        'strtolower($this->request->getMethod()) !== "post"',
        $section,
        "{$label} rejects non-POST requests"
    );
    $assertContains(
        '->setStatusCode(405)',
        $section,
        "{$label} returns Method Not Allowed for non-POST requests"
    );
    $assertContains(
        '->setHeader("Allow", "POST")',
        $section,
        "{$label} advertises POST as the only allowed method"
    );
    $assertBefore(
        'strtolower($this->request->getMethod()) !== "post"',
        '$this->access_only_vendor_update_requests',
        $section,
        "{$label} enforces the HTTP method before processing authorization or input"
    );
}

$assertContains(
    "int \$expectedVendorId",
    $applyChanges,
    "change application requires the authoritative CR id from the locked request"
);
$assertContains(
    '"vendor_bank_accounts"',
    $applyChanges,
    "bank changes use an explicit target-table policy"
);
$assertContains(
    '"vendor_contacts"',
    $applyChanges,
    "contact changes use an explicit target-table policy"
);
$assertContains(
    'hash_equals($policy["table"], $tableRaw)',
    $applyChanges,
    "the supplied module and table must match the server allowlist"
);
$assertContains(
    "SELECT id FROM {\$table} WHERE id = ? AND vendor_id = ? FOR UPDATE",
    $applyChanges,
    "the CR-owned target row is locked before changes are applied"
);
$assertContains(
    '[$record_id, $expectedVendorId]',
    $applyChanges,
    "record and CR identifiers are parameter bound"
);
$assertContains(
    'array_intersect_key($after, $allowedFields)',
    $applyChanges,
    "unapproved fields are discarded from the requested payload"
);
$assertContains(
    '->where("vendor_id", $expectedVendorId)->update($payload)',
    $applyChanges,
    "every target update is constrained to the request CR"
);
$assertNotContains(
    '$this->db->prefixTable($tableRaw);\n\n        // normalize action names',
    $applyChanges,
    "a free-form table cannot reach the update builder without policy checks"
);

$assertContains(
    "function approve()",
    $approve,
    "single approval no longer accepts a URI id parameter"
);
$assertNotContains(
    'function approve($id',
    $source,
    "approval cannot receive a request id through the URI signature"
);
$assertContains(
    '$id = (int) $this->request->getPost("id");',
    $approve,
    "single approval reads its id exclusively from the POST body"
);
$assertNotContains(
    '$id = $id ?? $this->request->getPost("id");',
    $approve,
    "single approval cannot prefer a URI id over the POST body"
);

$assertContains(
    'url: "<?php echo get_uri(\'vendor_update_requests/approve\'); ?>",',
    $singleApproveRequest,
    "the vendor review page posts to the fixed approval endpoint"
);
$assertNotContains(
    "get_uri('vendor_update_requests/approve/')",
    $singleApproveRequest,
    "the vendor review page does not append the id to the approval URL"
);
$assertContains(
    "type: \"POST\"",
    $singleApproveRequest,
    "the vendor review page uses POST for single approval"
);
$assertContains(
    "data: {",
    $singleApproveRequest,
    "the vendor review page sends an approval request body"
);
$assertContains(
    "id: id",
    $singleApproveRequest,
    "the vendor review page sends the selected id in the POST body"
);

$assertContains("FOR UPDATE", $lock, "decision reads use an exclusive database row lock");
$assertContains("[\$id]", $lock, "the locking query binds the request id");
$assertContains("deleted = 0", $lock, "deleted requests cannot be locked for a decision");

$assertContains(
    '->where("status", "pending")',
    $finalize,
    "the final decision is conditional on the request still being pending"
);
$assertContains(
    '$this->db->affectedRows() === 1',
    $finalize,
    "exactly one pending request must be finalized"
);
$assertContains(
    "VUR DECISION FINALIZE FAILED",
    $finalize,
    "failed conditional finalization is logged server-side"
);
$assertContains(
    '($error["message"] ??',
    $finalize,
    "database details remain available in the server log"
);

foreach ([
    "bulk approval" => $bulkApprove,
    "bulk rejection" => $bulkReject,
    "single approval" => $approve,
    "single rejection" => $reject,
] as $label => $section) {
    $assertBefore(
        '$this->db->transBegin();',
        '$this->lockPendingRequest(',
        $section,
        "{$label} starts its transaction before its authoritative request read"
    );
    $assertContains(
        '$this->finalizePendingRequest(',
        $section,
        "{$label} uses conditional pending-row finalization"
    );
}

foreach ([
    "bulk approval" => $bulkApprove,
    "bulk rejection" => $bulkReject,
] as $label => $section) {
    $assertContains("array_unique(", $section, "{$label} deduplicates selected ids");
    $assertContains(
        'sort($ids, SORT_NUMERIC);',
        $section,
        "{$label} locks selected ids in deterministic numeric order"
    );
    $assertNotContains(
        'Vendor_update_requests_model->get_one($id)',
        $section,
        "{$label} no longer uses an unlocked model read"
    );
}

$assertContains(
    '(string) ($request->status ?? "") !== "pending"',
    $approve,
    "single approval rechecks pending status after the lock"
);
$assertContains(
    '(string) ($row->status ?? "") !== "pending"',
    $reject,
    "single rejection rechecks pending status after the lock"
);
$assertContains(
    '(string) ($row->status ?? "") !== "pending"',
    $bulkApprove,
    "bulk approval rechecks pending status after each lock"
);
$assertContains(
    '(string) ($row->status ?? "") !== "pending"',
    $bulkReject,
    "bulk rejection rechecks pending status after each lock"
);

$assertContains(
    "Unable to approve the selected requests. Please try again.",
    $bulkApprove,
    "bulk approval returns a generic client error"
);
$assertContains(
    "Unable to reject the selected requests. Please try again.",
    $bulkReject,
    "bulk rejection returns a generic client error"
);
$assertContains(
    "Unable to approve this request. Please try again.",
    $approve,
    "single approval returns a generic client error"
);
$assertContains(
    "Unable to reject this request. Please try again.",
    $reject,
    "single rejection returns a generic client error"
);

foreach ([
    "bulk approval" => $bulkApprove,
    "bulk rejection" => $bulkReject,
    "single approval" => $approve,
    "single rejection" => $reject,
] as $label => $section) {
    $assertContains(
        '$e->getMessage()',
        $section,
        "{$label} records detailed exceptions in the server log"
    );
}

$assertNotContains(
    '"message" => "Bulk approve failed: " . $e->getMessage()',
    $bulkApprove,
    "bulk approval does not expose exception details"
);
$assertNotContains(
    '"message" => "Bulk reject failed: " . $e->getMessage()',
    $bulkReject,
    "bulk rejection does not expose exception details"
);
$assertNotContains(
    '"message" => "Approve failed: " . $e->getMessage()',
    $approve,
    "single approval does not expose exception details"
);
$assertNotContains(
    '"message" => $e->getMessage()',
    $reject,
    "single rejection does not expose exception details"
);

echo "Vendor update request decision locking contracts passed." . PHP_EOL;
