<?php

$root = dirname(__DIR__);
$source = (string) file_get_contents($root . "/app/Models/Notifications_model.php");

$fail = static function (string $message): void {
    fwrite(STDERR, "Assertion failed: {$message}" . PHP_EOL);
    exit(1);
};
$assertContains = static function (string $needle, string $message) use ($source, $fail): void {
    if (!str_contains($source, $needle)) {
        $fail($message . " Missing: " . $needle);
    }
};

$assertContains(
    'private function external_portal_only_identity_condition($users_table)',
    "new all-members notifications need an external-identity exclusion"
);
foreach ([
    'vendor_users',
    'gate_pass_users',
    'ptw_applicant_users',
] as $membershipTable) {
    $assertContains(
        '"' . $membershipTable . '"',
        "all external portal membership types must be classified"
    );
}
$assertContains(
    "COALESCE(\$users_table.role_id, 0)=0",
    "internal role holders must remain valid all-members recipients"
);
$assertContains(
    "operational_assignment.status='active'",
    "active operational staff assignments must remain valid recipients"
);
$assertContains(
    'AND NOT ($external_portal_only)',
    "role-less external identities must be removed from all-members recipients"
);

$assertContains(
    'private function announcement_notification_visibility_condition(',
    "historic notification rows need a retrieval-time boundary"
);
foreach ([
    'is_vendor_only_identity($user_id, $user)',
    'is_gate_pass_only_identity($user_id, $user)',
    'is_ptw_applicant_only_identity($user_id, $user)',
] as $identityCheck) {
    $assertContains($identityCheck, "retrieval must recognize every external portal identity");
}
$assertContains(
    "FIND_IN_SET('all_members', COALESCE(\$announcements_table.share_with, ''))=0",
    "historic all-members announcements must be hidden from external identities"
);
if (substr_count($source, '$this->announcement_notification_visibility_condition(') < 2) {
    $fail("both notification list and unread-count queries must apply the historic-row boundary");
}
$assertContains(
    'LEFT JOIN $announcements_table ON $announcements_table.id=$notifications_table.announcement_id',
    "the unread count must inspect the linked announcement audience"
);
$assertContains(
    'AND timestamp($notifications_table.created_at)>timestamp(?)',
    "the unread-count timestamp must be bound rather than interpolated"
);

echo "Announcement external-identity isolation contracts passed." . PHP_EOL;
