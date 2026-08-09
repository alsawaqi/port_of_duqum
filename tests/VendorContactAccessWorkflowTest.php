<?php

require_once __DIR__ . "/../app/Libraries/Vendor_contact_access.php";

use App\Libraries\Vendor_contact_access;

$fail = static function (string $message): void {
    fwrite(STDERR, "Assertion failed: {$message}" . PHP_EOL);
    exit(1);
};

$assertTrue = static function ($condition, string $message) use ($fail): void {
    if ($condition !== true) {
        $fail($message);
    }
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

$assertTrue(
    Vendor_contact_access::canonicalEmail("  Person@Example.COM ") === "person@example.com",
    "contact login emails are canonicalized"
);

$portal = file_get_contents(__DIR__ . "/../app/Controllers/Vendor_portal.php");
$requests = file_get_contents(__DIR__ . "/../app/Controllers/Vendor_update_requests.php");
$access = file_get_contents(__DIR__ . "/../app/Libraries/Vendor_contact_access.php");
$activation = file_get_contents(__DIR__ . "/../app/Controllers/Vendor_contact_invitation.php");
$vendors = file_get_contents(__DIR__ . "/../app/Controllers/Vendors.php");
$vendorDetails = file_get_contents(__DIR__ . "/../app/Views/vendors/details.php");
$contactsModel = file_get_contents(__DIR__ . "/../app/Models/Vendor_contacts_model.php");
$contactForm = file_get_contents(__DIR__ . "/../app/Views/vendor_portal/contacts/modal_form.php");
$contactIndex = file_get_contents(__DIR__ . "/../app/Views/vendor_portal/contacts/index.php");
$legacyPasswordForm = file_get_contents(__DIR__ . "/../app/Views/vendor_portal/contacts/password_modal_form.php");

$contactSave = $sliceBetween(
    $portal,
    'function save_contact()',
    'function delete_contact()',
    "the vendor contact save workflow should be inspectable"
);
$prepareMethod = $sliceBetween(
    $access,
    'public function prepareContactAccess',
    'public function approveContact',
    "the pre-approval credential workflow should be inspectable"
);
$existingAccountBranch = $sliceBetween(
    $prepareMethod,
    'if ($user) {',
    '} else {',
    "the existing-account branch should be inspectable"
);
$approvalMethod = $sliceBetween(
    $access,
    'public function approveContact',
    'public function setInitialPasswordForApprovedContact',
    "the approval activation workflow should be inspectable"
);
$legacyPasswordMethod = $sliceBetween(
    $access,
    'public function setInitialPasswordForApprovedContact',
    'public function suspendContactMembership',
    "the legacy password conversion workflow should be inspectable"
);
$suspendMethod = $sliceBetween(
    $access,
    'public function suspendContactMembership',
    'public function revokeOutstandingInvitations',
    "the CR-scoped suspension workflow should be inspectable"
);
$upsertMembershipMethod = $sliceBetween(
    $access,
    'private function upsertMembership',
    'private function contactRoleId',
    "the CR membership upsert should be inspectable"
);
$contactDataBlock = $sliceBetween(
    $contactSave,
    '$data = clean_data([',
    ']);',
    "the contact persistence payload should be inspectable"
);
$approvalSnapshot = $sliceBetween(
    $contactSave,
    '$changes = [',
    '];',
    "the contact approval snapshot should be inspectable"
);

// Contact identity and same-CR uniqueness.
$assertContains(
    '"email" => "required|valid_email|max_length[255]"',
    $contactSave,
    "a contact email is mandatory"
);
$assertContains(
    'duplicateContactExists($vendor_id, $email, $id)',
    $contactSave,
    "duplicates are checked inside the selected vendor/CR"
);
$assertContains(
    "The login email cannot be changed after this contact is linked to an account.",
    $contactSave,
    "linked contact email changes are blocked"
);
$assertContains(
    '->where("vendor_id", $vendorId)',
    $upsertMembershipMethod,
    "membership reuse is scoped to one CR"
);
$assertContains(
    '->where("user_id", $userId)',
    $upsertMembershipMethod,
    "membership reuse is scoped to the linked global user"
);
$assertContains(
    '"vendor_id" => $vendorId',
    $upsertMembershipMethod,
    "new membership data records the selected CR"
);
$assertContains(
    '"user_id" => $userId',
    $upsertMembershipMethod,
    "new membership data records the linked user"
);
$assertContains(
    '$data["is_owner"] = 0;',
    $upsertMembershipMethod,
    "contact memberships remain non-owner memberships"
);
$assertContains(
    '$vendor_users_table.vendor_id=$vendor_contacts_table.vendor_id',
    $contactsModel,
    "contact access status is joined through the same CR"
);
$assertContains(
    '$vendor_users_table.user_id=$vendor_contacts_table.user_id',
    $contactsModel,
    "contact access status is joined through the same user"
);

// The creator supplies credentials, but raw secrets never enter persisted
// contact data, approval JSON, cleaning helpers, logs, or responses.
$assertContains(
    '"name" => "initial_password"',
    $contactForm,
    "new contacts expose an initial-password field"
);
$assertContains(
    '"name" => "initial_password_confirm"',
    $contactForm,
    "new contacts require password confirmation"
);
$assertContains(
    '"autocomplete" => "new-password"',
    $contactForm,
    "the browser treats creator-set credentials as a new password"
);
$assertContains(
    'if (empty($model_info->user_id))',
    $contactForm,
    "initial-password inputs are shown only before an account is linked"
);
$assertContains(
    '$initial_password = (string) $this->request->getPost("initial_password");',
    $contactSave,
    "the password is read as a raw secret"
);
$assertContains(
    '$initial_password_confirm = (string) $this->request->getPost("initial_password_confirm");',
    $contactSave,
    "the password confirmation is read separately"
);
$assertContains(
    'hash_equals($initial_password, $initial_password_confirm)',
    $contactSave,
    "creator-set password confirmation is compared safely"
);
$assertNotContains(
    'clean_data($initial_password',
    $contactSave,
    "password text is never passed through HTML/data cleaning"
);
$assertNotContains(
    '$initial_password',
    $contactDataBlock,
    "the raw password is absent from the vendor_contacts payload"
);
$assertNotContains(
    '$initial_password',
    $approvalSnapshot,
    "the raw password is absent from the approval audit JSON"
);
$assertNotContains(
    'password_hash(',
    $contactSave,
    "the controller does not copy a password hash into contact or approval data"
);
$assertContains(
    '$this->Vendor_contact_access->prepareContactAccess(',
    $contactSave,
    "contact saving delegates credential preparation to the identity service"
);
$assertContains(
    '$data["user_id"] = (int) $access["user_id"]',
    $contactSave,
    "the approval snapshot records only the linked user id"
);

// New global accounts get a one-way hash but stay blocked until approval.
$assertContains(
    'public function prepareContactAccess',
    $access,
    "pre-approval identity preparation is centralized"
);
$assertContains(
    '"password" => password_hash($initialPassword, PASSWORD_DEFAULT)',
    $prepareMethod,
    "the creator-set initial password is stored only as a modern hash"
);
$assertContains(
    '"status" => "inactive"',
    $prepareMethod,
    "new contact accounts start inactive"
);
$assertContains(
    '"disable_login" => 1',
    $prepareMethod,
    "new contact accounts cannot sign in before approval"
);
$assertContains(
    '"invited",',
    $prepareMethod,
    "pre-approval membership remains inaccessible"
);

// credentials_ready_at is explicit provenance proving that this inactive,
// vendor-only identity received a real creator-set password.
$assertContains(
    '$credentialsReadyAt = get_current_utc_time();',
    $prepareMethod,
    "new credentials receive a trusted preparation timestamp"
);
$assertContains(
    '"credentials_ready_at" => $credentialsReadyAt',
    $upsertMembershipMethod,
    "the trusted credential timestamp is persisted on the membership"
);
$assertContains(
    'private function preparedCredentialsAt',
    $access,
    "credential readiness has one lookup path"
);
$assertContains(
    '->where("credentials_ready_at IS NOT NULL", null, false)',
    $access,
    "only an explicit non-null readiness marker proves prepared credentials"
);
$assertContains(
    'private function isPreparedVendorIdentity',
    $access,
    "activation requires both safe vendor identity and readiness provenance"
);

// An existing global identity owns one password across every CR. Adding it to
// another CR must retain that password and never hash the creator's input.
$assertContains(
    '$usesExistingPassword = true;',
    $existingAccountBranch,
    "existing accounts explicitly retain their global password"
);
$assertContains(
    'preparedCredentialsAt((int) $user->id)',
    $existingAccountBranch,
    "inactive existing accounts require prior creator-set provenance"
);
$assertNotContains(
    'password_hash(',
    $existingAccountBranch,
    "one CR cannot overwrite an existing user's password"
);
$assertContains(
    '"uses_existing_password" => $usesExistingPassword',
    $prepareMethod,
    "the controller can tell the creator that the existing password was retained"
);
$assertContains(
    "A contact manager cannot replace an existing account password.",
    $contactSave,
    "editing a linked contact cannot become a password-reset path"
);
$assertContains(
    "This person's existing password was retained.",
    $contactSave,
    "the saved response accurately explains existing-account reuse"
);

// Approval activates the already-prepared identity and this exact CR only; it
// never receives, hashes, or replaces a password.
$assertContains(
    'public function approveContact',
    $access,
    "contact approval has one controlled activation method"
);
$assertContains(
    'if (!$credentialsReadyAt || !$this->isPreparedVendorIdentity($user))',
    $approvalMethod,
    "an inactive user cannot be activated without creator-set provenance"
);
$assertContains(
    '"status" => "active"',
    $approvalMethod,
    "approval activates a prepared global account"
);
$assertContains(
    '"disable_login" => 0',
    $approvalMethod,
    "approval enables login for a prepared account"
);
$assertContains(
    '(int) $contact->vendor_id',
    $approvalMethod,
    "approval updates the membership belonging to the contact's CR"
);
$assertContains(
    '"active",',
    $approvalMethod,
    "approval activates the CR membership"
);
$assertContains(
    '$this->clearPreparedCredentials($userId);',
    $approvalMethod,
    "activation consumes credential provenance so it cannot reactivate a later-disabled account"
);
$assertNotContains(
    'password_hash(',
    $approvalMethod,
    "approval never changes the prepared or existing password"
);
$assertContains(
    '$this->Vendor_contact_access->approveContact(',
    $requests,
    "single and bulk approval use the centralized activation workflow"
);
$assertNotContains(
    '$invitation = $this->Vendor_contact_access->approveContact(',
    $requests,
    "approval does not expect an invitation result"
);

// Rejection/deletion suspension is CR-scoped and cannot disable a shared user.
$assertContains(
    'suspendContactMembership($record_id)',
    $requests,
    "rejected contact changes keep the CR membership inaccessible"
);
$assertContains(
    '"status" => "suspended"',
    $suspendMethod,
    "contact access can be suspended per CR"
);
$assertContains(
    '->where("vendor_id", (int) $contact->vendor_id)',
    $suspendMethod,
    "suspension targets only the contact's CR"
);
$assertContains(
    '->where("user_id", (int) $contact->user_id)',
    $suspendMethod,
    "suspension targets only the linked membership"
);
$assertNotContains(
    'prefixTable("users")',
    $suspendMethod,
    "suspension never disables the shared global user account"
);

// Approved legacy invitation-era accounts can be converted once, but only
// while inactive, vendor-only, and still in their pending membership state.
$assertContains(
    'public function setInitialPasswordForApprovedContact',
    $access,
    "legacy pending contacts have a controlled creator-password conversion"
);
$assertContains(
    '(string) $contact->status !== "approved"',
    $legacyPasswordMethod,
    "legacy password conversion requires an approved contact"
);
$assertContains(
    '(int) $contact->is_active !== 1',
    $legacyPasswordMethod,
    "legacy password conversion requires an active contact"
);
$assertContains(
    '$this->canExistingUserSignIn($user)',
    $legacyPasswordMethod,
    "a contact manager cannot reset an already-active account"
);
$assertContains(
    '(string) $membership->status !== "invited"',
    $legacyPasswordMethod,
    "legacy conversion is limited to the old pending membership state"
);
$assertContains(
    '$this->isSafeVendorOnlyInactiveIdentity($user)',
    $legacyPasswordMethod,
    "legacy conversion cannot reset a mixed or internal identity"
);
$assertContains(
    'password_hash($initialPassword, PASSWORD_DEFAULT)',
    $legacyPasswordMethod,
    "legacy conversion hashes the vendor-set password"
);
$assertContains(
    '"password" => $passwordHash',
    $legacyPasswordMethod,
    "legacy conversion stores the vendor-set password as a one-way hash"
);
$assertContains(
    'LIMIT 1 FOR UPDATE',
    $legacyPasswordMethod,
    "legacy password claims lock the global identity"
);
$assertContains(
    'ORDER BY id ASC FOR UPDATE',
    $legacyPasswordMethod,
    "all CR memberships are locked in a stable order"
);
$assertContains(
    '$candidate->credentials_ready_at',
    $legacyPasswordMethod,
    "credentials prepared by any CR block legacy password replacement"
);
$assertContains(
    '$this->db->affectedRows() !== 1',
    $legacyPasswordMethod,
    "legacy password setup is a conditional one-time claim"
);
$assertContains(
    '$this->clearPreparedCredentials($userId);',
    $legacyPasswordMethod,
    "legacy conversion consumes any readiness marker"
);
$assertContains(
    '$this->revokeOutstandingInvitations($userId);',
    $legacyPasswordMethod,
    "legacy conversion invalidates every previously issued link"
);
$assertContains(
    '$effectiveStatus = $status;',
    $upsertMembershipMethod,
    "membership synchronization computes a non-downgrading status"
);
$assertContains(
    '(string) $existing->status === "active"',
    $upsertMembershipMethod,
    "an existing active CR membership cannot be downgraded while synchronizing a contact"
);
$assertContains(
    'function save_contact_password()',
    $portal,
    "the vendor portal exposes a dedicated legacy-password POST action"
);
$assertContains(
    '$this->Vendor_contact_access->setInitialPasswordForApprovedContact(',
    $portal,
    "the legacy UI uses the controlled conversion service"
);
$assertContains(
    '"initial_password" => "required|min_length[10]|max_length[72]"',
    $portal,
    "legacy creator-set passwords receive server-side length validation"
);
$assertContains(
    '"name" => "initial_password"',
    $legacyPasswordForm,
    "legacy contacts receive an explicit initial-password form"
);

// No new invitation can be created, sent, or resent. The only remaining token
// helper revokes old links, and the old public endpoint is permanently inert.
$assertNotContains(
    'function createInvitation',
    $access,
    "the identity service cannot create contact invitations"
);
$assertNotContains(
    'function sendInvitation',
    $access,
    "the identity service cannot email contact invitations"
);
$assertNotContains(
    'function resend_contact_invitation',
    $portal,
    "the vendor portal has no resend-invitation endpoint"
);
$assertNotContains(
    'createInvitation(',
    $portal,
    "the vendor portal cannot create an invitation token"
);
$assertNotContains(
    'sendInvitation(',
    $portal,
    "the vendor portal cannot send an invitation email"
);
$assertNotContains(
    '_send_pending_contact_invitations',
    $requests,
    "approval does not queue invitation emails"
);
$assertNotContains(
    'sendInvitation(',
    $requests,
    "single and bulk approval never send contact invitations"
);
$assertNotContains(
    'sendInvitation(',
    $vendors,
    "the admin provisioning action never sends an invitation"
);
$assertNotContains(
    'Resend portal invitation',
    $vendors,
    "the admin contact list has no resend-invitation action"
);
$assertNotContains(
    'Invitation pending',
    $contactIndex,
    "the vendor contact page no longer advertises invitation delivery"
);
$assertContains(
    'setStatusCode(410)',
    $activation,
    "legacy contact invitation endpoints return Gone"
);
$assertContains(
    'invitation links are no longer accepted',
    $activation,
    "old invitation links explain that the flow is retired"
);
$assertNotContains(
    'password_hash(',
    $activation,
    "an old invitation endpoint cannot replace a contact password"
);
$assertNotContains(
    'validInvitation(',
    $activation,
    "the retired endpoint no longer validates or consumes invitation tokens"
);
$assertNotContains(
    'prefixTable("users")',
    $activation,
    "the retired endpoint cannot update the global user account"
);

// Administrative surfaces synchronize access without email side effects.
$assertContains(
    'function provision_vendor_contact_access',
    $vendors,
    "administrators retain an explicit access-synchronization action"
);
$assertContains(
    '$this->Vendor_contact_access->approveContact(',
    $vendors,
    "admin access synchronization uses the same controlled workflow"
);
$assertContains(
    'Access activation pending',
    $vendors,
    "the admin contact list uses activation wording instead of invitation wording"
);
$assertContains(
    'Activate portal access',
    $vendors,
    "administrators can synchronize approved pending access without email"
);
$assertContains(
    'title: "Portal access"',
    $vendorDetails,
    "the admin contact list exposes CR-specific provisioning state"
);

// The contact list exposes the CR-specific membership state used by the UI.
$assertContains(
    '$vendor_users_table.status AS portal_access_status',
    $contactsModel,
    "the contact list exposes CR-specific portal access state"
);

echo "Vendor contact access workflow passed." . PHP_EOL;