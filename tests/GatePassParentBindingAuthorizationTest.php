<?php

$root = dirname(__DIR__);
$portal = (string) file_get_contents($root . '/app/Controllers/Gate_pass_portal.php');
$security = (string) file_get_contents($root . '/app/Controllers/Gate_pass_security_inbox.php');
$view = (string) file_get_contents($root . '/app/Views/gate_pass_portal/requests/visitor_modal_form.php');

$fail = static function (string $message): void {
    fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
    exit(1);
};
$contains = static function (string $needle, string $haystack, string $message) use ($fail): void {
    if (strpos($haystack, $needle) === false) {
        $fail($message . ' Missing: ' . $needle);
    }
};
$slice = static function (string $source, string $start, string $end) use ($fail): string {
    $from = strpos($source, $start);
    $to = $from === false ? false : strpos($source, $end, $from + strlen($start));
    if ($from === false || $to === false) {
        $fail('Unable to inspect method between ' . $start . ' and ' . $end);
    }
    return substr($source, $from, $to - $from);
};

$visitorList = $slice($portal, 'function visitors_list_data', 'function check_blocked_visitor');
$vehicleList = $slice($portal, 'function vehicles_list_data', 'function vehicle_modal_form');
$contains('_can_view_request_details($request)', $visitorList, 'visitor list must authorize its parent request');
$contains('_can_view_request_details($request)', $vehicleList, 'vehicle list must authorize its parent request');

$qrPrint = $slice($portal, 'function record_qr_print', 'function download_qr');
$contains('_can_view_request_details($request)', $qrPrint, 'QR print audit must require request visibility');

foreach ([
    [$portal, 'function visitor_modal_form', 'function save_visitor'],
    [$portal, 'function save_visitor', 'function delete_visitor'],
    [$portal, 'function vehicle_modal_form', 'function save_vehicle'],
    [$portal, 'function save_vehicle', 'function delete_vehicle'],
    [$security, 'public function vehicle_modal_form', 'public function save_vehicle'],
    [$security, 'public function save_vehicle', 'public function delete_vehicle'],
    [$security, 'public function visitor_modal_form', 'public function save_visitor'],
    [$security, 'public function save_visitor', 'public function delete_visitor'],
] as [$source, $start, $end]) {
    $method = $slice($source, $start, $end);
    $contains('"gate_pass_request_id" => $request_id', $method, 'child lookup must bind child ID to parent request');
}

$blocked = $slice($portal, 'function check_blocked_visitor', 'function visitor_modal_form');
$contains('getMethod()) !== "post"', $blocked, 'blocked lookup must be POST-only');
$contains('gate_pass_request_id', $blocked, 'blocked lookup must be scoped to an editable request');
$contains('$throttler->check(', $blocked, 'blocked lookup must be throttled');
$contains('type: "POST"', $view, 'blocked lookup browser request must use POST');
$contains('allowSubmit = false', $view, 'blocked lookup errors must fail closed');

$contains('private function _can_review_request', $security, 'security review stage guard must exist');
$contains('(string) ($request->stage ?? "") === "security"', $security, 'review mutations require the security stage');
$contains('private function _can_scan_request', $security, 'issued-pass scan guard must exist');
$contains('(string) ($request->stage ?? "") === "issued"', $security, 'QR scanning requires issued stage');
$contains('_resolve_security_upload_path(', $security, 'security attachments require canonical path containment');

echo 'Gate-pass parent binding and stage authorization passed.' . PHP_EOL;
