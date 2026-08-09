<?php

$controller = file_get_contents(__DIR__ . "/../app/Controllers/Vendor_portal.php");
$contactIndex = file_get_contents(__DIR__ . "/../app/Views/vendor_portal/contacts/index.php");

$fail = static function (string $message): void {
    fwrite(STDERR, "Assertion failed: {$message}" . PHP_EOL);
    exit(1);
};

$assertContains = static function (string $needle, string $haystack, string $message) use ($fail): void {
    if (strpos($haystack, $needle) === false) {
        $fail($message . " Missing: " . $needle);
    }
};

$assertNotContains = static function (string $needle, string $haystack, string $message) use ($fail): void {
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

$ownerGuard = $sliceBetween(
    $controller,
    'private function _can_manage_vendor_contacts',
    'private function _require_vendor_tender_access',
    "the CR-owner contact guard should be inspectable"
);
$assertContains(
    'get_accessible_membership($user_id, $vendor_id)',
    $ownerGuard,
    "contact administration is checked against the selected CR membership"
);
$assertContains(
    '$this->Vendor_portal_authorizer->can(',
    $ownerGuard,
    "contact administration uses the central role matrix"
);
$assertContains(
    'Vendor_portal_authorizer::CONTACTS_MANAGE',
    $ownerGuard,
    "only the contacts-manage capability may administer contacts"
);
$assertContains('app_redirect("forbidden")', $ownerGuard, "nonowners are denied server-side");

$methodRanges = [
    ['function contact_modal_form()', 'function contacts_list_data()', "open the contact form"],
    ['function save_contact()', 'function delete_contact()', "save a contact"],
    ['function delete_contact()', 'function contact_password_modal_form()', "delete or restore a contact"],
    ['function contact_password_modal_form()', 'function save_contact_password()', "open legacy password setup"],
    ['function save_contact_password()', 'private function _can_set_legacy_contact_password', "save a legacy password"],
];
foreach ($methodRanges as [$start, $end, $action]) {
    $method = $sliceBetween($controller, $start, $end, "the {$action} endpoint should be inspectable");
    $assertContains(
        '$vendor_id = $this->_require_vendor_contact_owner();',
        $method,
        "only the active CR owner may {$action}"
    );
}

$contactsPage = $sliceBetween(
    $controller,
    'function contacts()',
    'function branches()',
    "the contacts page should be inspectable"
);
$assertContains('_require_vendor_access()', $contactsPage, "ordinary active contacts may view the list");
$assertContains(
    '$view_data["can_manage_contacts"] = $this->_can_manage_vendor_contacts($vendor_id);',
    $contactsPage,
    "the contacts view receives an explicit management flag"
);

$rowBuilder = $sliceBetween(
    $controller,
    'private function _make_contact_row',
    "\n}",
    "the contact action builder should be inspectable"
);
$assertContains(
    'if ($can_manage_contacts && !$is_locked)',
    $rowBuilder,
    "edit and delete actions are hidden from nonowners"
);
$assertContains(
    'if ($can_manage_contacts && !$is_locked && $this->_can_set_legacy_contact_password($data))',
    $rowBuilder,
    "legacy password actions are hidden from nonowners"
);

$legacyGuard = $sliceBetween(
    $controller,
    'private function _can_set_legacy_contact_password',
    'private function _contact_row_data',
    "the legacy password eligibility guard should be inspectable"
);
$assertContains('!empty($contact->portal_invited_at)', $legacyGuard, "legacy setup requires invitation provenance");
$assertContains('empty($contact->portal_credentials_ready_at)', $legacyGuard, "prepared credentials cannot be overwritten");

$deleteMethod = $sliceBetween($controller, 'function delete_contact()', 'function contact_password_modal_form()', "delete should be inspectable");
$assertContains('"message" => app_lang("error_occurred")', $deleteMethod, "delete failures return a generic message");
$assertNotContains('"message" => $e->getMessage()', $deleteMethod, "delete failures do not expose exception details");

$assertContains('$can_manage_contacts = !empty($can_manage_contacts);', $contactIndex, "the view defaults to read-only");
$assertContains('Only the owner of this CR can add, edit, delete contacts or set initial passwords.', $contactIndex, "nonowners see a clear read-only message");
$assertContains('<?php if ($can_manage_contacts) { ?>', $contactIndex, "owner-only controls are conditionally rendered");

echo "Vendor contact owner authorization contracts passed." . PHP_EOL;
