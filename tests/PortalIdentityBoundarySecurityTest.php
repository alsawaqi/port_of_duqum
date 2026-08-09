<?php

$root = dirname(__DIR__);

$fail = static function (string $message): void {
    fwrite(STDERR, 'Assertion failed: ' . $message . PHP_EOL);
    exit(1);
};
$read = static function (string $path) use ($root, $fail): string {
    $absolute = $root . '/' . $path;
    if (!is_file($absolute)) {
        $fail($path . ' must exist');
    }
    return (string) file_get_contents($absolute);
};
$contains = static function (string $needle, string $haystack, string $message) use ($fail): void {
    if (!str_contains($haystack, $needle)) {
        $fail($message . ' Missing: ' . $needle);
    }
};
$notContains = static function (string $needle, string $haystack, string $message) use ($fail): void {
    if (str_contains($haystack, $needle)) {
        $fail($message . ' Unexpected: ' . $needle);
    }
};
$method = static function (string $source, string $start, string $end) use ($fail): string {
    $from = strpos($source, $start);
    $to = $from === false ? false : strpos($source, $end, $from + strlen($start));
    if ($from === false || $to === false) {
        $fail('Unable to isolate method ' . $start);
    }
    return substr($source, $from, $to - $from);
};

$guest = $read('app/Controllers/Guest_gate_pass.php');
$contains('SELECT id, user_type, deleted FROM $users_table WHERE email=? LIMIT 1', $guest, 'public registration checks every existing identity');
$contains('if ($existing_user)', $guest, 'an existing email is rejected');
$contains('An account already exists for this email.', $guest, 'duplicate response is generic');
$notContains('SET deleted=0', $guest, 'public registration cannot revive an account');
$notContains('UPDATE $users_table', $guest, 'public registration cannot modify an existing identity');

$security = $read('app/Controllers/Security_Controller.php');
$contains('$this->_confine_vendor_only_identity', $security, 'vendor identities are confined globally');
$contains('$this->_confine_gate_pass_only_identity', $security, 'gate-pass identities are confined globally');
$contains('$this->_confine_ptw_applicant_only_identity', $security, 'PTW applicant identities are confined globally');
$gateConfinement = $method($security, 'private function _confine_gate_pass_only_identity', 'private function _confine_ptw_applicant_only_identity');
$contains('["vendor_portal", "gate_pass_portal", "ptw_portal", "portal_account", "notifications"]', $gateConfinement, 'external-only identities have a deny-by-default portal allowlist');
$ptwConfinement = $method($security, 'private function _confine_ptw_applicant_only_identity', '//initialize the login');
$contains('["vendor_portal", "gate_pass_portal", "ptw_portal", "portal_account", "notifications"]', $ptwConfinement, 'PTW-only identities remain confined to externally authorized portals');

$users = $read('app/Models/Users_model.php');
$contains('function is_gate_pass_only_identity', $users, 'gate-pass identity classification exists');
$contains('function is_ptw_applicant_only_identity', $users, 'PTW identity classification exists');
$contains('if (!$this->db->tableExists("gate_pass_users"))', $users, 'gate-pass classification fails safely before schema rollout');
$contains('"tender_technical_users"', $users, 'privileged workflow identities are not mislabeled as portal-only');

$vur = $read('app/Controllers/Vendor_update_requests.php');
$view = $method($vur, 'public function view_document', 'private function streamVendorRequestDocument');
$contains('access_only_vendor_update_requests_view()', $view, 'vendor-request document access requires module view permission');
$contains('access_only_vendor_update_requests_by_vendor_view()', $view, 'vendor-request document access requires CR-scoped view permission');
$stream = $method($vur, 'private function streamVendorRequestDocument', 'private function lockPendingRequest');
$contains('!== "vendor_documents"', $stream, 'only vendor-document update requests can stream a file');
$contains('->where("vendor_id", (int) $request->vendor_id)', $stream, 'the canonical document is bound to the request CR');
$contains('realpath(WRITEPATH . "uploads/vendor_documents")', $stream, 'documents are resolved from protected storage');
$contains('X-Content-Type-Options', $stream, 'streamed documents disable MIME sniffing');
$contains('Cache-Control', $stream, 'streamed documents are not cached');
$notContains('FCPATH', $stream, 'document streaming has no public-root fallback');
$notContains('$baseCandidates', $stream, 'document streaming has no broad path resolver');

echo 'Portal identity and vendor-document boundary contracts passed.' . PHP_EOL;
