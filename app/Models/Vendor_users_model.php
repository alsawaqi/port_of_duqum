<?php

namespace App\Models;

use Config\Services;

/**
 * Central access layer for the many-to-many user <-> vendor (CR) relationship.
 */
class Vendor_users_model extends Crud_model
{
    public const SESSION_VENDOR_ID = "active_vendor_id";
    public const ROLE_OWNER = "OWNER";
    public const ROLE_CONTACT = "CONTACT";
    public const ROLE_EDITOR = "EDITOR";
    public const ROLE_BIDDER = "BIDDER";
    public const ROLE_VIEWER = "VIEWER";

    protected $table = "vendor_users";

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Returns all portal-accessible CR memberships for one login identity.
     *
     * Supported options:
     * - vendor_id: limit to one vendor
     * - membership_statuses: defaults to active
     * - vendor_statuses: defaults to the portal/login-allowed statuses
     */
    public function get_accessible_memberships($user_id, array $options = []): array
    {
        $userId = (int) $user_id;
        if ($userId < 1) {
            return [];
        }

        $vendorUsers = $this->db->prefixTable("vendor_users");
        $vendors = $this->db->prefixTable("vendors");
        $roles = $this->db->prefixTable("vendor_roles");

        $builder = $this->db->table($vendorUsers . " AS vendor_memberships");
        $builder->select(
            "vendor_memberships.id AS membership_id, vendor_memberships.vendor_id,"
            . " vendor_memberships.user_id, vendor_memberships.vendor_role_id,"
            . " vendor_memberships.is_owner, vendor_memberships.status AS membership_status,"
            . " vendor_memberships.invited_by, vendor_memberships.invited_at,"
            . " vendors.vendor_name, vendors.cr_number, vendors.email AS vendor_email,"
            . " vendors.status AS vendor_status, vendors.vendor_group_id, vendors.vendor_grade_id,"
            . " vendor_roles.name AS vendor_role_name, vendor_roles.code AS vendor_role_code"
        );
        $builder->join($vendors . " AS vendors", "vendors.id = vendor_memberships.vendor_id", "inner");
        $builder->join(
            $roles . " AS vendor_roles",
            "vendor_roles.id = vendor_memberships.vendor_role_id AND vendor_roles.deleted = 0",
            "left"
        );
        $builder->where("vendor_memberships.user_id", $userId);
        $builder->where("vendor_memberships.deleted", 0);
        $builder->where("vendors.deleted", 0);

        $membershipStatuses = $options["membership_statuses"] ?? ["active"];
        $membershipStatuses = $this->normalizeStatuses(
            $membershipStatuses,
            ["invited", "active", "suspended"]
        );
        if (!$membershipStatuses) {
            return [];
        }
        $builder->whereIn("vendor_memberships.status", $membershipStatuses);

        $vendorStatuses = $options["vendor_statuses"] ?? $this->defaultAccessibleVendorStatuses();
        $vendorStatuses = $this->normalizeStatuses(
            $vendorStatuses,
            ["new", "pending_payment", "submitted", "approved", "rejected", "revise", "suspended", "expired"]
        );
        if (!$vendorStatuses) {
            return [];
        }
        $builder->whereIn("vendors.status", $vendorStatuses);

        $vendorId = (int) ($options["vendor_id"] ?? 0);
        if ($vendorId > 0) {
            $builder->where("vendor_memberships.vendor_id", $vendorId);
        }

        $builder->orderBy("vendor_memberships.is_owner", "DESC");
        $builder->orderBy("vendors.vendor_name", "ASC");
        $builder->orderBy("vendors.cr_number", "ASC");
        $builder->orderBy("vendor_memberships.id", "ASC");

        return $builder->get()->getResult();
    }

    public function get_accessible_membership($user_id, $vendor_id, array $options = []): ?object
    {
        $options["vendor_id"] = (int) $vendor_id;
        $memberships = $this->get_accessible_memberships($user_id, $options);
        return $memberships[0] ?? null;
    }

    public function has_access($user_id, $vendor_id, array $options = []): bool
    {
        return $this->get_accessible_membership($user_id, $vendor_id, $options) !== null;
    }

    /**
     * Detects whether an identity is vendor-linked even when every linked CR is
     * currently rejected, suspended, or expired and therefore not selectable.
     * Optional statuses refer to membership status, not vendor status.
     */
    public function has_vendor_memberships($user_id, $statuses = null): bool
    {
        $userId = (int) $user_id;
        if ($userId < 1) {
            return false;
        }

        $vendorUsers = $this->db->prefixTable("vendor_users");
        $builder = $this->db->table($vendorUsers . " AS vendor_memberships");
        $builder->where("vendor_memberships.user_id", $userId);
        $builder->where("vendor_memberships.deleted", 0);

        // This is an identity-history check, not an access check. Keep a
        // vendor-only account classified as such after its last CR is deleted;
        // get_accessible_memberships() still excludes deleted CRs.

        if ($statuses !== null) {
            $membershipStatuses = $this->normalizeStatuses(
                $statuses,
                ["invited", "active", "suspended"]
            );
            if (!$membershipStatuses) {
                return false;
            }
            $builder->whereIn("vendor_memberships.status", $membershipStatuses);
        }

        return $builder->countAllResults() > 0;
    }

    /**
     * Resolves an explicit/session CR. A sole accessible CR is auto-resolved;
     * multiple memberships without a valid selection intentionally return null.
     */
    public function resolve_context($user_id, $requested_vendor_id = 0, array $options = []): ?object
    {
        $userId = (int) $user_id;
        $requestedVendorId = (int) $requested_vendor_id;
        if ($userId < 1) {
            return null;
        }

        if ($requestedVendorId > 0) {
            return $this->get_accessible_membership($userId, $requestedVendorId, $options);
        }

        $sessionVendorId = (int) Services::session()->get(self::SESSION_VENDOR_ID);
        if ($sessionVendorId > 0) {
            $membership = $this->get_accessible_membership($userId, $sessionVendorId, $options);
            if ($membership) {
                return $membership;
            }
            Services::session()->remove(self::SESSION_VENDOR_ID);
        }

        $memberships = $this->get_accessible_memberships($userId, $options);
        return count($memberships) === 1 ? $memberships[0] : null;
    }

    public function set_active_vendor_context($user_id, $vendor_id, array $options = []): bool
    {
        $membership = $this->get_accessible_membership($user_id, $vendor_id, $options);
        if (!$membership) {
            return false;
        }

        Services::session()->set(self::SESSION_VENDOR_ID, (int) $membership->vendor_id);
        return true;
    }

    public function clear_active_vendor_context(): void
    {
        Services::session()->remove(self::SESSION_VENDOR_ID);
    }

    /**
     * Finds a vendor/user pair regardless of membership status. Soft-deleted
     * rows can be included so upsert_membership can revive the existing unique
     * pair rather than colliding with (vendor_id, user_id).
     */
    public function find_membership($vendor_id, $user_id, bool $include_deleted = false): ?object
    {
        $vendorId = (int) $vendor_id;
        $userId = (int) $user_id;
        if ($vendorId < 1 || $userId < 1) {
            return null;
        }

        $builder = $this->db->table($this->db->prefixTable("vendor_users"));
        $builder->where("vendor_id", $vendorId)->where("user_id", $userId);
        if (!$include_deleted) {
            $builder->where("deleted", 0);
        }

        return $builder->orderBy("id", "ASC")->get(1)->getRow() ?: null;
    }

    /**
     * Creates, updates, or revives a membership. Returns the membership id or
     * false. New memberships default to the least-privilege VIEWER role.
     */
    public function upsert_membership($vendor_id, $user_id, array $data = [])
    {
        $vendorId = (int) $vendor_id;
        $userId = (int) $user_id;
        if ($vendorId < 1 || $userId < 1) {
            return false;
        }

        $existing = $this->find_membership($vendorId, $userId, true);
        $status = strtolower(trim((string) ($data["status"] ?? ($existing->status ?? "active"))));
        if (!in_array($status, ["invited", "active", "suspended"], true)) {
            return false;
        }

        $roleId = (int) ($data["vendor_role_id"] ?? ($existing->vendor_role_id ?? 0));
        if ($roleId < 1) {
            $viewerRole = $this->ensure_viewer_role();
            $roleId = (int) ($viewerRole->id ?? 0);
        }
        if ($roleId < 1) {
            return false;
        }

        $save = [
            "vendor_id" => $vendorId,
            "user_id" => $userId,
            "vendor_role_id" => $roleId,
            "is_owner" => array_key_exists("is_owner", $data)
                ? (!empty($data["is_owner"]) ? 1 : 0)
                : (int) ($existing->is_owner ?? 0),
            "status" => $status,
            "deleted" => 0,
            "updated_at" => date("Y-m-d H:i:s"),
        ];

        if (array_key_exists("invited_by", $data)) {
            $invitedBy = (int) $data["invited_by"];
            $save["invited_by"] = $invitedBy > 0 ? $invitedBy : null;
        } elseif (!$existing) {
            $save["invited_by"] = null;
        }

        if (array_key_exists("invited_at", $data)) {
            $save["invited_at"] = $data["invited_at"] ?: null;
        } elseif (!$existing && $status === "invited") {
            $save["invited_at"] = date("Y-m-d H:i:s");
        }

        if (array_key_exists("credentials_ready_at", $data)) {
            $save["credentials_ready_at"] = $data["credentials_ready_at"] ?: null;
        }

        if (!$existing) {
            $save["created_at"] = date("Y-m-d H:i:s");
        }

        $membershipId = $existing ? (int) $existing->id : 0;
        $result = $this->ci_save($save, $membershipId);
        if (!$result) {
            return false;
        }

        return $membershipId ?: (int) $result;
    }

    public function find_role_by_code($code, bool $include_deleted = false): ?object
    {
        $normalized = strtoupper(trim((string) $code));
        if ($normalized === "") {
            return null;
        }

        $roles = $this->db->prefixTable("vendor_roles");
        $activeSql = $include_deleted ? "" : " AND `deleted` = 0 AND `is_active` = 1";
        $role = $this->db->query(
            "SELECT * FROM `{$roles}` WHERE UPPER(TRIM(`code`)) = ?{$activeSql} ORDER BY `id` ASC LIMIT 1",
            [$normalized]
        )->getRow();

        return $role ?: null;
    }

    public function get_contact_role(): ?object
    {
        return $this->find_role_by_code(self::ROLE_CONTACT);
    }

    public function ensure_contact_role(): ?object
    {
        $role = $this->find_role_by_code(self::ROLE_CONTACT, true);
        $roles = $this->db->prefixTable("vendor_roles");
        $now = date("Y-m-d H:i:s");

        if ($role) {
            $this->db->table($roles)->where("id", (int) $role->id)->update([
                "name" => "Contact",
                "code" => self::ROLE_CONTACT,
                "description" => "Legacy vendor contact role; enforced as read-only.",
                "is_active" => 1,
                "deleted" => 0,
                "updated_at" => $now,
            ]);
            return $this->find_role_by_code(self::ROLE_CONTACT);
        }

        $inserted = $this->db->table($roles)->insert([
            "name" => "Contact",
            "code" => self::ROLE_CONTACT,
            "description" => "Legacy vendor contact role; enforced as read-only.",
            "is_active" => 1,
            "created_at" => $now,
            "updated_at" => $now,
            "deleted" => 0,
        ]);

        return $inserted ? $this->find_role_by_code(self::ROLE_CONTACT) : null;
    }

    public function ensure_viewer_role(): ?object
    {
        $this->ensure_portal_roles();
        return $this->find_role_by_code(self::ROLE_VIEWER);
    }

    /** @return array<string, string> */
    public function get_assignable_roles(): array
    {
        $this->ensure_portal_roles();
        $roles = [];
        foreach ([self::ROLE_VIEWER, self::ROLE_BIDDER, self::ROLE_EDITOR] as $code) {
            $role = $this->find_role_by_code($code);
            if ($role) {
                $roles[$code] = (string) $role->name;
            }
        }

        return $roles;
    }

    /**
     * Resolve only roles an owner is allowed to assign to another contact.
     * OWNER can never be self-assigned through contact creation.
     */
    public function get_assignable_role(string $code): ?object
    {
        $normalized = strtoupper(trim($code));
        if (!in_array($normalized, [self::ROLE_VIEWER, self::ROLE_BIDDER, self::ROLE_EDITOR], true)) {
            return null;
        }

        $this->ensure_portal_roles();
        return $this->find_role_by_code($normalized);
    }

    private function ensure_portal_roles(): void
    {
        $definitions = [
            self::ROLE_OWNER => ["Owner", "Full authority for the selected vendor CR, including contact administration."],
            self::ROLE_EDITOR => ["Editor", "May maintain the selected CR profile and participate in tenders."],
            self::ROLE_BIDDER => ["Bidder", "May view the selected CR profile and participate in tenders."],
            self::ROLE_VIEWER => ["Viewer", "Read-only access to the selected CR profile and tenders."],
        ];
        $table = $this->db->prefixTable("vendor_roles");
        $now = get_current_utc_time();

        foreach ($definitions as $code => [$name, $description]) {
            $role = $this->find_role_by_code($code, true);
            $data = [
                "name" => $name,
                "code" => $code,
                "description" => $description,
                "is_active" => 1,
                "deleted" => 0,
                "updated_at" => $now,
            ];
            if ($role) {
                $this->db->table($table)->where("id", (int) $role->id)->update($data);
                continue;
            }

            $data["created_at"] = $now;
            $this->db->table($table)->insert($data);
        }
    }

    private function defaultAccessibleVendorStatuses(): array
    {
        if (function_exists("vendor_login_allowed_statuses")) {
            return (array) vendor_login_allowed_statuses();
        }

        return ["new", "pending_payment", "submitted", "approved", "revise", "expired"];
    }

    private function normalizeStatuses($statuses, array $allowed): array
    {
        if (!is_array($statuses)) {
            $statuses = [$statuses];
        }

        $normalized = array_map(static function ($status) {
            return strtolower(trim((string) $status));
        }, $statuses);

        return array_values(array_unique(array_intersect($normalized, $allowed)));
    }
}
