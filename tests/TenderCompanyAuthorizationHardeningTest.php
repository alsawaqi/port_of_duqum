<?php

$root = dirname(__DIR__);

$read = static function (string $path) use ($root): string {
    $full = $root . "/" . $path;
    return is_file($full) ? (string) file_get_contents($full) : "";
};

$fail = static function (string $message): void {
    fwrite(STDERR, "FAIL: " . $message . PHP_EOL);
    exit(1);
};

$contains = static function (string $needle, string $source, string $message) use ($fail): void {
    if (strpos($source, $needle) === false) {
        $fail($message . " Missing: " . $needle);
    }
};

$matches = static function (string $pattern, string $source, string $message) use ($fail): void {
    if (!preg_match($pattern, $source)) {
        $fail($message);
    }
};

$security = $read("app/Controllers/Security_Controller.php");
$requests = $read("app/Controllers/Tender_requests.php");
$finance = $read("app/Controllers/Tender_finance_inbox.php");
$committee = $read("app/Controllers/Tender_committee_inbox.php");
$procurement = $read("app/Controllers/Tender_procurement_inbox.php");
$technical = $read("app/Controllers/Tender_technical_inbox.php");
$clarifications = $read("app/Controllers/Tender_clarifications.php");
$reports = $read("app/Controllers/Tender_reports.php");

foreach ([
    "tender_department_users",
    "tender_department_manager_users",
    "tender_finance_users",
    "tender_procurement_users",
    "tender_procurement_manager_users",
    "tender_committee_users",
    "tender_technical_users",
    "tender_commercial_users",
] as $assignmentTable) {
    $contains($assignmentTable, $security, "central tender authorization must recognize every company-bearing assignment table");
}

foreach ([
    "can_access_tender_company",
    "tender_company_scope_sql",
    "require_tender_company_access",
    "can_access_tender_id",
    "require_tender_request_scope",
    "require_tender_scope",
    "require_tender_request_pair",
] as $helper) {
    $contains("function " . $helper, $security, "central tender authorization helper must exist");
}

$contains("status='active'", $security, "company authorization must accept only active assignments");
$contains("deleted=0", $security, "company authorization must reject deleted assignments and resources");
$contains("company_id=?", $security, "company authorization must bind the selected company");
$contains("COALESCE(tender_scope.company_id, request_scope.company_id)", $security, "tender scope must inherit the immutable request company when required");
$matches('/is_admin[\s\S]{0,120}return true/', $security, "administrator bypass must be explicit and narrow");
$contains('"reports" => ["tender_procurement_users", "tender_procurement_manager_users"]', $security, "reports must use only procurement-family company assignments");
$contains('"clarifications" => ["tender_procurement_users"]', $security, "clarifications must not borrow unrelated evaluator company assignments");

$contains("can_access_tender_company", $requests, "request lists must be company filtered");
$contains("require_tender_request_scope", $requests, "request reads and mutations must authorize the parent request");
$contains("require_tender_company_access", $requests, "request creation and company-specific suggestions must enforce the active assignment");

$contains("can_access_tender_company", $finance, "finance lists must be company filtered");
$contains("require_tender_request_scope", $finance, "finance decisions must authorize the parent request");
$contains("can_access_tender_company", $committee, "committee lists must be company filtered");
$contains("require_tender_request_scope", $committee, "committee decisions must authorize the parent request");

$contains("tender_company_scope_sql", $procurement, "procurement tender and request list branches must use the SQL company predicate");
$matches('/tender_company_scope_sql\([\s\S]*?COALESCE\(t\.company_id, req\.company_id\)[\s\S]*?tender_company_scope_sql\([\s\S]*?req\.company_id/', $procurement, "both procurement list branches must be independently company scoped");
$contains("require_tender_request_pair", $procurement, "submitted tender/request IDs must describe the same parent-child relationship");
$contains('(int) ($request->company_id ?? 0) !== $company_id', $procurement, "a tender may not be moved away from its request company");
$contains('(int) ($authorized_existing->authorization_company_id ?? 0) !== $company_id', $procurement, "an existing tender may not be moved across companies");

$contains("can_access_tender_id", $technical, "technical evaluation lists must be company filtered");
$contains('require_tender_scope($tender_id, "technical_eval")', $technical, "technical reads and mutations must enforce tender company scope");
$contains('strtolower((string) ($evaluation->type ?? "")) !== "technical"', $technical, "technical finding downloads must reject non-technical evaluations");
$contains('(int) ($evaluation->tender_id ?? 0) !== (int) ($attachment->tender_id ?? 0)', $technical, "technical finding attachments must match the evaluation tender");
$contains('(int) ($evaluation->tender_bid_id ?? 0) !== (int) ($attachment->tender_bid_id ?? 0)', $technical, "technical finding attachments must match the evaluation bid");
$contains("get_tender_bid_for_technical_user", $technical, "technical child downloads must verify the bid belongs to the authorized tender");
$contains("tender_evaluation_findings/tender_", $technical, "technical finding paths must be bound to their tender and evaluation");
$contains("tender_bids/tender_", $technical, "technical bid document paths must be bound to their tender and vendor");

$contains("tender_company_scope_sql", $clarifications, "clarification registers must be company filtered");
$contains('require_tender_scope($tender_id, "clarifications")', $clarifications, "clarification tender and vendor pages must enforce tender company scope");
$contains("_vendor_participates_in_tender", $clarifications, "clarification replies must validate tender participation");
$contains("tender_invited_vendors", $clarifications, "invited vendors must be recognized as tender participants");
$contains("tender_bids", $clarifications, "bidding vendors must be recognized as tender participants");
$contains("invite_status IN ('sent', 'delivered', 'opened', 'approved')", $clarifications, "rejected or pending invitations must not count as vendor participation");
$contains("bid_scope.status<>'draft'", $clarifications, "draft-only vendors must not count as clarification participants");
$contains('$tender_id !== (int) $clarification->tender_id', $clarifications, "clarification replies must reject a mismatched submitted tender");
$contains('$vendor_id !== (int) $clarification->vendor_id', $clarifications, "clarification replies must reject a mismatched submitted vendor");
$contains('"tender_id" => (int) $clarification->tender_id', $clarifications, "clarification child replies must be queried under their parent tender");
$contains("_clarification_bid_matches_thread", $clarifications, "clarification replies must bind an optional bid to the same tender and vendor");
$contains("AND tender_id=?", $clarifications, "clarification bid lookup must include the parent tender ID");

$contains("tender_company_scope_sql", $reports, "report registers and report lookup must be company scoped");
$contains('require_tender_scope($tender_id, "reports")', $reports, "report pages and mutations must enforce tender company scope");
$contains('require_tender_scope((int) $attachment->tender_id, "reports")', $reports, "report attachment downloads must enforce the attachment tender company");
$contains('$tb.tender_id = $te.tender_id', $reports, "late evaluation review must bind the evaluation bid to the same tender");
$contains('(int) ($evaluation->tender_id ?? 0) !== (int) ($attachment->tender_id ?? 0)', $reports, "report evaluation attachments must match their evaluation tender");
$contains('(int) ($evaluation->tender_bid_id ?? 0) !== (int) ($attachment->tender_bid_id ?? 0)', $reports, "report evaluation attachments must match their evaluation bid");
$contains("_evaluation_bid_matches_tender", $reports, "report evaluation downloads must verify the evaluation bid belongs to the same tender");
$contains("resolveStoredFile", $reports, "report child downloads must use canonical parent-bound storage resolution");

echo "Tender company authorization and parent binding hardening passed." . PHP_EOL;
