<?php

namespace App\Libraries;

use App\Models\Users_model;
use App\Models\Vendor_users_model;
use CodeIgniter\Database\BaseConnection;

/**
 * Keeps a vendor contact and its portal membership in sync.
 *
 * A user is global (one canonical email), while vendor_users is the
 * vendor/CR-specific authorization boundary. New contacts receive a
 * creator-defined password but remain unable to sign in until approval.
 */
class Vendor_contact_access
{
    private BaseConnection $db;
    private Users_model $users;
    private Vendor_users_model $vendorUsers;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?: db_connect();
        $this->users = new Users_model();
        $this->vendorUsers = new Vendor_users_model();
    }

    public static function canonicalEmail(?string $email): string
    {
        return strtolower(trim((string) $email));
    }

    public function findUserByEmail(string $email)
    {
        $email = self::canonicalEmail($email);
        if ($email === "") {
            return null;
        }

        $matches = $this->db->table($this->db->prefixTable("users"))
            ->where("deleted", 0)
            ->where("LOWER(TRIM(email))", $email)
            ->orderBy("id", "ASC")
            ->get(2)
            ->getResult();

        if (count($matches) > 1) {
            throw new \RuntimeException(
                "More than one account uses this email. An administrator must resolve the duplicate accounts first."
            );
        }

        return $matches[0] ?? null;
    }

    public function duplicateContactExists(int $vendorId, string $email, int $excludeId = 0): bool
    {
        $builder = $this->db->table($this->db->prefixTable("vendor_contacts"))
            ->where("vendor_id", $vendorId)
            ->where("deleted", 0)
            ->where("LOWER(TRIM(email))", self::canonicalEmail($email));

        if ($excludeId) {
            $builder->where("id !=", $excludeId);
        }

        return (bool) $builder->get(1)->getRow();
    }

    /**
     * Link a newly submitted contact to a login identity before approval.
     *
     * Passwords belong to the global user, never to a CR. A creator-provided
     * password is therefore used only when the canonical email has no account.
     * Existing accounts retain their current password across every linked CR.
     */
    public function prepareContactAccess(int $contactId, string $initialPassword, int $createdBy): array
    {
        $contactsTable = $this->db->prefixTable("vendor_contacts");
        $contact = $this->db->table($contactsTable)
            ->where("id", $contactId)
            ->where("deleted", 0)
            ->get(1)
            ->getRow();

        if (!$contact) {
            throw new \RuntimeException("The vendor contact could not be found.");
        }

        $email = self::canonicalEmail($contact->email ?? "");
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException("The vendor contact must have a valid email address.");
        }

        $user = null;
        if (!empty($contact->user_id)) {
            $user = $this->users->get_one((int) $contact->user_id);
            if (!$user || empty($user->id) || (int) $user->deleted) {
                throw new \RuntimeException("The account linked to this vendor contact no longer exists.");
            }
            if (self::canonicalEmail($user->email ?? "") !== $email) {
                throw new \RuntimeException("The contact email does not match its linked account.");
            }
        } else {
            $user = $this->findUserByEmail($email);
        }

        $createdUser = false;
        $usesExistingPassword = false;
        $credentialsReadyAt = null;

        if ($user) {
            if ((string) $user->user_type !== "staff") {
                throw new \RuntimeException(
                    "This email is already assigned to a non-staff account and cannot be used for vendor access."
                );
            }

            // Never let one CR reset a password shared by another CR.
            $usesExistingPassword = true;
            if (!$this->canExistingUserSignIn($user)) {
                $credentialsReadyAt = $this->preparedCredentialsAt((int) $user->id);
                if (!$credentialsReadyAt || !$this->isPreparedVendorIdentity($user)) {
                    throw new \RuntimeException(
                        "This email belongs to an inactive account. Its existing password cannot be replaced here; "
                        . "ask an administrator to restore or resolve that account."
                    );
                }
            }
        } else {
            $this->assertInitialPassword($initialPassword);
            $name = $this->splitContactName((string) $contact->contacts_name);
            $credentialsReadyAt = get_current_utc_time();
            $userId = $this->users->ci_save([
                "first_name" => $name["first_name"],
                "last_name" => $name["last_name"],
                "user_type" => "staff",
                "is_admin" => 0,
                "role_id" => 0,
                "email" => $email,
                "password" => password_hash($initialPassword, PASSWORD_DEFAULT),
                "status" => "inactive",
                "disable_login" => 1,
                "job_title" => mb_substr(trim((string) ($contact->designation ?: "Vendor contact")), 0, 100),
                "phone" => mb_substr(trim((string) ($contact->mobile ?: $contact->phone)), 0, 20),
                "language" => get_setting("language") ?: "english",
                "created_at" => $credentialsReadyAt,
                "deleted" => 0,
            ]);

            if (!$userId) {
                throw new \RuntimeException("Unable to create the vendor contact login.");
            }

            $user = $this->users->get_one((int) $userId);
            $createdUser = true;
        }

        $userId = (int) $user->id;
        $linked = $this->db->table($contactsTable)
            ->where("id", $contactId)
            ->update([
                "user_id" => $userId,
                "email" => $email,
                "updated_at" => get_current_utc_time(),
            ]);
        if (!$linked) {
            throw new \RuntimeException("Unable to link the contact to its login account.");
        }

        $this->upsertMembership(
            (int) $contact->vendor_id,
            $userId,
            $createdBy,
            "invited",
            $credentialsReadyAt
        );

        return [
            "user_id" => $userId,
            "created_user" => $createdUser,
            "uses_existing_password" => $usesExistingPassword,
        ];
    }

    /**
     * Activate the CR membership after an administrator approves the contact.
     * The password was prepared at contact creation and is never changed here.
     */
    public function approveContact(int $contactId, int $approvedBy): bool
    {
        $contactsTable = $this->db->prefixTable("vendor_contacts");
        $contact = $this->db->table($contactsTable)
            ->where("id", $contactId)
            ->where("deleted", 0)
            ->get(1)
            ->getRow();

        if (!$contact || (string) $contact->status !== "approved") {
            throw new \RuntimeException("The approved vendor contact could not be found.");
        }

        $email = self::canonicalEmail($contact->email ?? "");
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException("The vendor contact must have a valid email address.");
        }

        $user = null;
        if (!empty($contact->user_id)) {
            $user = $this->users->get_one((int) $contact->user_id);
            if (!$user || empty($user->id) || (int) $user->deleted) {
                throw new \RuntimeException("The account linked to this vendor contact no longer exists.");
            }
            if (self::canonicalEmail($user->email ?? "") !== $email) {
                throw new \RuntimeException("The approved contact email no longer matches its linked account.");
            }
        } else {
            // Compatibility for an older pending contact that already points
            // to an active global identity by email.
            $user = $this->findUserByEmail($email);
        }

        if (!$user) {
            throw new \RuntimeException(
                "This contact has no prepared login. Ask the vendor to recreate it with an initial password before approval."
            );
        }
        if ((string) $user->user_type !== "staff") {
            throw new \RuntimeException(
                "This email is already assigned to a non-staff account and cannot be used for vendor access."
            );
        }

        $userId = (int) $user->id;
        if ((int) ($contact->user_id ?? 0) !== $userId) {
            $linked = $this->db->table($contactsTable)
                ->where("id", $contactId)
                ->update([
                    "user_id" => $userId,
                    "email" => $email,
                    "updated_at" => get_current_utc_time(),
                ]);
            if (!$linked) {
                throw new \RuntimeException("Unable to link the approved contact to its login account.");
            }
        }

        $credentialsReadyAt = $this->preparedCredentialsAt($userId);
        if ((int) $contact->is_active !== 1) {
            $this->upsertMembership(
                (int) $contact->vendor_id,
                $userId,
                $approvedBy,
                "suspended",
                $credentialsReadyAt
            );
            return true;
        }

        if (!$this->canExistingUserSignIn($user)) {
            if (!$credentialsReadyAt || !$this->isPreparedVendorIdentity($user)) {
                throw new \RuntimeException(
                    "This contact does not have a verified creator-set password. "
                    . "Ask the vendor to set an initial password before enabling portal access."
                );
            }

            if (!$this->users->ci_save([
                "status" => "active",
                "disable_login" => 0,
            ], $userId)) {
                throw new \RuntimeException("Unable to activate the vendor contact login.");
            }
        }

        $this->upsertMembership(
            (int) $contact->vendor_id,
            $userId,
            $approvedBy,
            "active",
            null
        );
        $this->clearPreparedCredentials($userId);
        $this->revokeOutstandingInvitations($userId);

        return true;
    }

    /**
     * Convert an approved legacy invitation account to creator-set credentials.
     */
    public function setInitialPasswordForApprovedContact(
        int $contactId,
        string $initialPassword,
        int $setBy
    ): bool {
        $contactsTable = $this->db->prefixTable("vendor_contacts");
        $usersTable = $this->db->prefixTable("users");
        $vendorUsersTable = $this->db->prefixTable("vendor_users");
        $contact = $this->db->query(
            "SELECT * FROM {$contactsTable}
             WHERE id = ? AND deleted = 0
             LIMIT 1 FOR UPDATE",
            [$contactId]
        )->getRow();

        if (!$contact
            || (string) $contact->status !== "approved"
            || (int) $contact->is_active !== 1
            || empty($contact->user_id)
        ) {
            throw new \RuntimeException("Only an approved, active contact can receive an initial password.");
        }

        $userId = (int) $contact->user_id;
        // The password is global across CRs. Lock the identity before checking
        // eligibility so two CRs cannot race and replace one another's choice.
        $user = $this->db->query(
            "SELECT * FROM {$usersTable}
             WHERE id = ? AND deleted = 0
             LIMIT 1 FOR UPDATE",
            [$userId]
        )->getRow();
        if (!$user || empty($user->id) || (int) $user->deleted) {
            throw new \RuntimeException("The account linked to this contact no longer exists.");
        }
        if (self::canonicalEmail($user->email ?? "") !== self::canonicalEmail($contact->email ?? "")) {
            throw new \RuntimeException("The contact email no longer matches its linked account.");
        }
        if ($this->canExistingUserSignIn($user)) {
            throw new \RuntimeException(
                "This account already has an active password. Its password cannot be replaced by a contact manager."
            );
        }

        $memberships = $this->db->query(
            "SELECT * FROM {$vendorUsersTable}
             WHERE user_id = ? AND deleted = 0
             ORDER BY id ASC FOR UPDATE",
            [$userId]
        )->getResult();
        $membership = null;
        foreach ($memberships as $candidate) {
            if (trim((string) ($candidate->credentials_ready_at ?? "")) !== "") {
                throw new \RuntimeException(
                    "This account already has creator-prepared credentials and cannot be reset by another CR."
                );
            }
            if ((int) $candidate->vendor_id === (int) $contact->vendor_id) {
                $membership = $candidate;
            }
        }

        if (!$membership
            || (string) $membership->status !== "invited"
            || trim((string) ($membership->invited_at ?? "")) === ""
        ) {
            throw new \RuntimeException("This contact is not awaiting legacy password setup.");
        }
        if (!$this->isSafeVendorOnlyInactiveIdentity($user)) {
            throw new \RuntimeException(
                "This inactive account cannot be reset by a vendor. Ask an administrator to resolve it."
            );
        }

        $this->assertInitialPassword($initialPassword);
        $passwordHash = password_hash($initialPassword, PASSWORD_DEFAULT);
        if (!is_string($passwordHash) || $passwordHash === "") {
            throw new \RuntimeException("Unable to secure the contact password.");
        }

        $updated = $this->db->table($usersTable)
            ->set([
                "password" => $passwordHash,
                "status" => "active",
                "disable_login" => 0,
            ])
            ->set("auth_session_version", "auth_session_version + 1", false)
            ->where("id", $userId)
            ->where("deleted", 0)
            ->where("status", "inactive")
            ->where("disable_login", 1)
            ->update();
        if (!$updated || $this->db->affectedRows() !== 1) {
            throw new \RuntimeException(
                "This password setup has already been completed or the account state changed. Please refresh and try again."
            );
        }

        $this->upsertMembership(
            (int) $contact->vendor_id,
            $userId,
            $setBy,
            "active",
            null
        );
        $this->clearPreparedCredentials($userId);
        $this->revokeOutstandingInvitations($userId);

        return true;
    }

    /**
     * Suspend access for this CR only; never disable the shared user account.
     */
    public function suspendContactMembership(int $contactId): bool
    {
        $contact = $this->db->table($this->db->prefixTable("vendor_contacts"))
            ->where("id", $contactId)
            ->get(1)
            ->getRow();

        if (!$contact || empty($contact->user_id)) {
            return true;
        }

        return (bool) $this->db->table($this->db->prefixTable("vendor_users"))
            ->where("vendor_id", (int) $contact->vendor_id)
            ->where("user_id", (int) $contact->user_id)
            ->update([
                "status" => "suspended",
                "updated_at" => get_current_utc_time(),
            ]);
    }


    /**
     * Revoke unused vendor-contact invitation tokens. With no contact id this
     * clears every outstanding token for the account (used after activation).
     */
    public function revokeOutstandingInvitations(int $userId, int $contactId = 0): void
    {
        if ($userId < 1) {
            return;
        }

        $table = $this->db->prefixTable("verification");
        $rows = $this->db->query(
            "SELECT id, params FROM {$table}
             WHERE type = 'invitation' AND deleted = 0
             FOR UPDATE"
        )->getResult();
        $ids = [];

        foreach ($rows as $row) {
            $params = safe_unserialize((string) $row->params);
            if (!is_array($params)
                || ($params["type"] ?? "") !== "vendor_contact"
                || (int) ($params["user_id"] ?? 0) !== $userId
                || ($contactId > 0 && (int) ($params["contact_id"] ?? 0) !== $contactId)
            ) {
                continue;
            }
            $ids[] = (int) $row->id;
        }

        if ($ids && !$this->db->table($table)->whereIn("id", $ids)->delete()) {
            throw new \RuntimeException("Unable to revoke the previous vendor contact invitation.");
        }
    }


    private function canExistingUserSignIn($user): bool
    {
        return (string) ($user->status ?? "") === "active" && !(int) ($user->disable_login ?? 0);
    }

    private function assertInitialPassword(string $password): void
    {
        $policyErrors = $this->users->password_policy_errors($password);
        if ($policyErrors) {
            throw new \RuntimeException(implode(" ", $policyErrors));
        }
    }

    private function preparedCredentialsAt(int $userId): ?string
    {
        $membership = $this->db->table($this->db->prefixTable("vendor_users"))
            ->select("credentials_ready_at")
            ->where("user_id", $userId)
            ->where("deleted", 0)
            ->where("credentials_ready_at IS NOT NULL", null, false)
            ->orderBy("credentials_ready_at", "DESC")
            ->get(1)
            ->getRow();

        $preparedAt = trim((string) ($membership->credentials_ready_at ?? ""));
        return $preparedAt !== "" ? $preparedAt : null;
    }

    private function clearPreparedCredentials(int $userId): void
    {
        $ok = $this->db->table($this->db->prefixTable("vendor_users"))
            ->where("user_id", $userId)
            ->where("deleted", 0)
            ->update([
                "credentials_ready_at" => null,
                "updated_at" => get_current_utc_time(),
            ]);

        if (!$ok) {
            throw new \RuntimeException("Unable to finalize the vendor contact credentials.");
        }
    }

    private function isSafeVendorOnlyInactiveIdentity($user): bool
    {
        $userId = (int) ($user->id ?? 0);
        if ($userId < 1
            || (string) ($user->user_type ?? "") !== "staff"
            || !empty($user->is_admin)
            || (int) ($user->role_id ?? 0) !== 0
            || (string) ($user->status ?? "") !== "inactive"
            || (int) ($user->disable_login ?? 0) !== 1
            || !$this->users->is_vendor_only_identity($userId, $user)
        ) {
            return false;
        }

        $activeMembership = $this->db->table($this->db->prefixTable("vendor_users"))
            ->where("user_id", $userId)
            ->where("status", "active")
            ->where("deleted", 0)
            ->get(1)
            ->getRow();

        return !$activeMembership;
    }

    private function isPreparedVendorIdentity($user): bool
    {
        $userId = (int) ($user->id ?? 0);
        return $this->isSafeVendorOnlyInactiveIdentity($user)
            && $this->preparedCredentialsAt($userId) !== null;
    }

    private function upsertMembership(
        int $vendorId,
        int $userId,
        int $invitedBy,
        string $status,
        ?string $credentialsReadyAt
    ): void {
        $table = $this->db->prefixTable("vendor_users");
        $existing = $this->db->table($table)
            ->where("vendor_id", $vendorId)
            ->where("user_id", $userId)
            ->get(1)
            ->getRow();

        $roleId = $this->contactRoleId();
        $now = get_current_utc_time();
        $effectiveStatus = $status;
        if ($existing
            && (string) $existing->status === "active"
            && $status === "invited"
        ) {
            // Synchronizing a contact record must never downgrade an already
            // active membership, especially the registration owner.
            $effectiveStatus = "active";
            $credentialsReadyAt = null;
        }
        $data = [
            "vendor_id" => $vendorId,
            "user_id" => $userId,
            "invited_by" => $invitedBy ?: null,
            "invited_at" => $effectiveStatus === "invited"
                ? ($existing->invited_at ?? $now)
                : null,
            "credentials_ready_at" => $credentialsReadyAt,
            "status" => $effectiveStatus,
            "updated_at" => $now,
            "deleted" => 0,
        ];

        // Never downgrade the registration owner when their owner contact is
        // synchronized into the contact list.
        if (!$existing || !(int) $existing->is_owner) {
            $data["is_owner"] = 0;
            if (!$existing || (int) ($existing->vendor_role_id ?? 0) <= 0) {
                $data["vendor_role_id"] = $roleId;
            }
        }

        if ($existing) {
            $ok = $this->db->table($table)->where("id", (int) $existing->id)->update($data);
        } else {
            $data["vendor_role_id"] = $roleId;
            $data["is_owner"] = 0;
            $data["created_at"] = $now;
            $ok = $this->db->table($table)->insert($data);
        }

        if (!$ok) {
            $error = $this->db->error();
            log_message(
                "error",
                "VENDOR CONTACT MEMBERSHIP SAVE FAILED: " . ($error["message"] ?? "database error")
            );
            throw new \RuntimeException("Unable to link the contact to this vendor.");
        }
    }

    private function contactRoleId(): int
    {
        if (method_exists($this->vendorUsers, "ensure_contact_role")) {
            $role = $this->vendorUsers->ensure_contact_role();
            if (is_object($role) && !empty($role->id)) {
                return (int) $role->id;
            }
            if (is_numeric($role)) {
                return (int) $role;
            }
        }

        $table = $this->db->prefixTable("vendor_roles");
        $role = $this->db->table($table)
            ->where("code", "CONTACT")
            ->where("deleted", 0)
            ->get(1)
            ->getRow();
        if ($role) {
            return (int) $role->id;
        }

        $ok = $this->db->table($table)->insert([
            "name" => "Contact",
            "code" => "CONTACT",
            "description" => "Vendor contact with full vendor portal authority",
            "is_active" => 1,
            "created_at" => get_current_utc_time(),
            "deleted" => 0,
        ]);
        if (!$ok) {
            throw new \RuntimeException("The vendor Contact role is not configured.");
        }

        return (int) $this->db->insertID();
    }


    private function splitContactName(string $fullName): array
    {
        $parts = preg_split('/\s+/u', trim($fullName), 2, PREG_SPLIT_NO_EMPTY) ?: [];
        $first = $parts[0] ?? "Vendor";
        $last = $parts[1] ?? "Contact";

        return [
            "first_name" => mb_substr($first, 0, 50),
            "last_name" => mb_substr($last, 0, 50),
        ];
    }
}
