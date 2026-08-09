<?php

namespace App\Models;

class Users_model extends Crud_model {

    protected $table = null;
    private ?bool $supports_auth_session_version = null;

    function __construct() {
        $this->table = 'users';
        parent::__construct($this->table);
    }

    /**
     * Any password mutation increments the account's session version. The
     * caller's current session is advanced only when it belongs to the target;
     * every other live session becomes invalid on its next protected request.
     */
    function ci_save($data = array(), $id = 0) {
        $changesPassword = (int) $id > 0 && array_key_exists("password", $data);
        if (!$changesPassword) {
            return parent::ci_save($data, $id);
        }

        if (!$this->_supports_auth_session_version()) {
            log_message("critical", "Password mutation denied because session-version storage is unavailable.");
            return false;
        }

        $userId = (int) $id;
        $usersTable = $this->db->prefixTable("users");
        $nextVersion = 0;

        if (!$this->db->transBegin()) {
            return false;
        }

        try {
            $row = $this->db->query(
                "SELECT auth_session_version
                 FROM {$usersTable}
                 WHERE id = ?
                 LIMIT 1 FOR UPDATE",
                [$userId]
            )->getRow();

            if (!$row) {
                $this->db->transRollback();
                return false;
            }

            $nextVersion = max(1, (int) ($row->auth_session_version ?? 0)) + 1;
            $data["auth_session_version"] = $nextVersion;
            $result = parent::ci_save($data, $userId);

            if (!$result || !$this->db->transStatus()) {
                $this->db->transRollback();
                return false;
            }

            if (!$this->db->transCommit()) {
                $this->db->transRollback();
                return false;
            }
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message("error", "Atomic password session invalidation failed for user ID {$userId}.");
            return false;
        }

        $session = \Config\Services::session();
        if ((int) $session->get("user_id") === $userId) {
            $session->set("auth_session_version", $nextVersion);
        }

        return $result;
    }

    function authenticate($email, $password) {
        $user_info = $this->authenticate_credentials($email, $password);
        if (!$user_info || !$this->_vendor_user_can_login((int) $user_info->id)) {
            return false;
        }

        return $this->start_user_session((int) $user_info->id);
    }

    /**
     * Validate an email/password pair without creating a login session.
     * CR numbers are deliberately not authentication credentials.
     */
    function authenticate_credentials($email, $password) {
        $email = strtolower(trim((string) $this->_get_clean_value(array("email" => $email), "email")));
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $users_table = $this->db->prefixTable("users");
        $rows = $this->db->query(
            "SELECT id, user_type, client_id, is_admin, role_id, email, phone, password
             FROM $users_table
             WHERE LOWER(email) = ?
               AND status = 'active'
               AND deleted = 0
               AND disable_login = 0",
            [$email]
        )->getResult();

        foreach ($rows as $user_info) {
            if ($this->_password_matches($user_info, $password) && $this->_client_can_login($user_info) !== false) {
                $this->_rehash_verified_password_if_needed($user_info, (string) $password);
                $isActiveVendorPortalIdentity = $this->is_vendor_only_identity(
                    (int) $user_info->id,
                    $user_info
                ) && $this->has_active_vendor_portal_membership((int) $user_info->id);
                $user_info->mfa_user_type = $isActiveVendorPortalIdentity ? 'vendor' : strtolower((string) $user_info->user_type);
                return $user_info;
            }
        }

        return false;
    }

    function verify_user_password(int $user_id, $password): bool
    {
        if (!$user_id) {
            return false;
        }

        $user_info = $this->db_builder
            ->select("id, password")
            ->getWhere(["id" => $user_id, "deleted" => 0])
            ->getRow();

        if (!$user_info || !$this->_password_matches($user_info, $password)) {
            return false;
        }

        $this->_rehash_verified_password_if_needed($user_info, (string) $password);
        return true;
    }

    /**
     * @return string[]
     */
    function password_policy_errors(string $password): array
    {
        return (new Auth_security_model())->password_errors($password);
    }

    /**
     * Resolve exactly one login identity for password recovery. Ambiguous
     * legacy duplicate-email rows deliberately produce no reset message.
     */
    function find_password_reset_user(string $email): ?object
    {
        $normalized = strtolower(trim($email));
        if (!filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $usersTable = $this->db->prefixTable("users");
        $rows = $this->db->query(
            "SELECT id, user_type, email, first_name, last_name, language
             FROM {$usersTable}
             WHERE LOWER(email) = ?
               AND status = 'active'
               AND deleted = 0
               AND disable_login = 0",
            [$normalized]
        )->getResult();

        $rows = array_values(array_filter($rows, static function ($user): bool {
            if ((string) ($user->user_type ?? "") === "staff") {
                return true;
            }

            return (string) ($user->user_type ?? "") === "client"
                && get_setting("disable_client_login") != "1";
        }));

        return count($rows) === 1 ? $rows[0] : null;
    }

    /**
     * External portal accounts use role-less staff rows for compatibility. A
     * user with an internal role or privileged workflow assignment is mixed
     * staff; memberships in another external portal remain portal-only.
     */
    function is_vendor_only_identity(int $user_id, $user_info = null): bool
    {
        if (!$user_id) {
            return false;
        }

        $user_info = $user_info ?: $this->get_one($user_id);
        if (!$user_info
            || (string) ($user_info->user_type ?? "") !== "staff"
            || !empty($user_info->is_admin)
            || (int) ($user_info->role_id ?? 0) !== 0) {
            return false;
        }

        $vendorUsers = $this->db->prefixTable("vendor_users");
        $membership = $this->db->query(
            "SELECT memberships.id
             FROM {$vendorUsers} memberships
             WHERE memberships.user_id = ?
               AND memberships.deleted = 0
             LIMIT 1",
            [$user_id]
        )->getRow();
        if (!$membership) {
            return false;
        }

        $assignmentTables = [
            "gate_pass_commercial_users",
            "gate_pass_department_users",
            "gate_pass_rop_users",
            "gate_pass_security_users",
            "ptw_hmo_users",
            "ptw_hsse_users",
            "ptw_terminal_users",
            "tender_commercial_users",
            "tender_committee_users",
            "tender_department_manager_users",
            "tender_department_users",
            "tender_finance_users",
            "tender_procurement_manager_users",
            "tender_procurement_users",
            "tender_technical_users",
        ];

        $assignmentQueries = [];
        foreach ($assignmentTables as $table) {
            if (!$this->db->tableExists($table)) {
                continue;
            }
            $prefixed = $this->db->prefixTable($table);
            $assignmentQueries[] = "SELECT user_id FROM {$prefixed}"
                . " WHERE user_id = {$user_id} AND deleted = 0 AND status = 'active'";
        }

        $operational = $assignmentQueries
            ? $this->db->query(
                "SELECT user_id FROM (" . implode(" UNION ALL ", $assignmentQueries) . ") assignments LIMIT 1"
            )->getRow()
            : null;

        return !$operational;
    }

    /**
     * A self-registered gate-pass requester is a portal identity, not an
     * internal employee, even though the legacy schema stores it as staff.
     */
    function is_gate_pass_only_identity(int $user_id, $user_info = null): bool
    {
        if (!$user_id) {
            return false;
        }

        $user_info = $user_info ?: $this->get_one($user_id);
        if (!$user_info
            || (string) ($user_info->user_type ?? "") !== "staff"
            || !empty($user_info->is_admin)
            || (int) ($user_info->role_id ?? 0) !== 0) {
            return false;
        }

        if (!$this->db->tableExists("gate_pass_users")) {
            return false;
        }

        $requesters = $this->db->prefixTable("gate_pass_users");
        $membership = $this->db->query(
            "SELECT id FROM {$requesters}
             WHERE user_id = ? AND deleted = 0
             LIMIT 1",
            [$user_id]
        )->getRow();
        if (!$membership) {
            return false;
        }

        // Reviewer/approver assignments are internal operational identities.
        // A plain requester has none of these privileged assignments.
        $assignmentTables = [
            "gate_pass_commercial_users",
            "gate_pass_department_users",
            "gate_pass_rop_users",
            "gate_pass_security_users",
            "ptw_hmo_users",
            "ptw_hsse_users",
            "ptw_terminal_users",
            "tender_commercial_users",
            "tender_committee_users",
            "tender_department_manager_users",
            "tender_department_users",
            "tender_finance_users",
            "tender_procurement_manager_users",
            "tender_procurement_users",
            "tender_technical_users",
        ];

        $queries = [];
        foreach ($assignmentTables as $table) {
            if (!$this->db->tableExists($table)) {
                continue;
            }
            $prefixed = $this->db->prefixTable($table);
            $queries[] = "SELECT user_id FROM {$prefixed}"
                . " WHERE user_id = {$user_id} AND deleted = 0 AND status = 'active'";
        }

        $operational = $queries
            ? $this->db->query(
                "SELECT user_id FROM (" . implode(" UNION ALL ", $queries) . ") assignments LIMIT 1"
            )->getRow()
            : null;

        return !$operational;
    }

    function is_ptw_applicant_only_identity(int $user_id, $user_info = null): bool
    {
        if (!$user_id) {
            return false;
        }

        $user_info = $user_info ?: $this->get_one($user_id);
        if (!$user_info
            || (string) ($user_info->user_type ?? "") !== "staff"
            || !empty($user_info->is_admin)
            || (int) ($user_info->role_id ?? 0) !== 0) {
            return false;
        }

        if (!$this->db->tableExists("ptw_applicant_users")) {
            return false;
        }

        $applicants = $this->db->prefixTable("ptw_applicant_users");
        if (!$this->db->query(
            "SELECT id FROM {$applicants}
             WHERE user_id=? AND deleted=0 LIMIT 1",
            [$user_id]
        )->getRow()) {
            return false;
        }

        $privilegedTables = [
            "gate_pass_commercial_users",
            "gate_pass_department_users",
            "gate_pass_rop_users",
            "gate_pass_security_users",
            "ptw_hmo_users",
            "ptw_hsse_users",
            "ptw_terminal_users",
            "tender_commercial_users",
            "tender_committee_users",
            "tender_department_manager_users",
            "tender_department_users",
            "tender_finance_users",
            "tender_procurement_manager_users",
            "tender_procurement_users",
            "tender_technical_users",
        ];

        $queries = [];
        foreach ($privilegedTables as $table) {
            if (!$this->db->tableExists($table)) {
                continue;
            }
            $prefixed = $this->db->prefixTable($table);
            $queries[] = "SELECT user_id FROM {$prefixed}"
                . " WHERE user_id = {$user_id} AND deleted = 0 AND status = 'active'";
        }

        return !$queries || !$this->db->query(
            "SELECT user_id FROM (" . implode(" UNION ALL ", $queries) . ") assignments LIMIT 1"
        )->getRow();
    }

    /**
     * Identity-history classification keeps role-less external accounts out of
     * internal modules. These methods separately determine which portal is
     * currently usable, so an inactive CR cannot mask a valid Gate Pass/PTW
     * membership (or vice versa).
     */
    function has_active_vendor_portal_membership(int $user_id): bool
    {
        return $user_id > 0
            && count((new Vendor_users_model())->get_accessible_memberships($user_id)) > 0;
    }

    function has_active_gate_pass_portal_membership(int $user_id): bool
    {
        if ($user_id < 1 || !$this->db->tableExists("gate_pass_users")) {
            return false;
        }

        $users = $this->db->prefixTable("gate_pass_users");
        return (bool) $this->db->query(
            "SELECT id FROM {$users}
             WHERE user_id=? AND deleted=0 AND status='active' LIMIT 1",
            [$user_id]
        )->getRow();
    }

    function has_active_ptw_applicant_portal_membership(int $user_id): bool
    {
        if ($user_id < 1 || !$this->db->tableExists("ptw_applicant_users")) {
            return false;
        }

        $assignments = $this->db->prefixTable("ptw_applicant_users");
        $companies = $this->db->prefixTable("companies");
        return (bool) $this->db->query(
            "SELECT assignments.id
             FROM {$assignments} assignments
             INNER JOIN {$companies} companies
                ON companies.id=assignments.company_id AND companies.deleted=0
             WHERE assignments.user_id=?
               AND assignments.deleted=0
               AND assignments.status='active'
             LIMIT 1",
            [$user_id]
        )->getRow();
    }

    function has_any_active_external_portal_membership(int $user_id): bool
    {
        return $this->has_active_vendor_portal_membership($user_id)
            || $this->has_active_gate_pass_portal_membership($user_id)
            || $this->has_active_ptw_applicant_portal_membership($user_id);
    }

    private function _vendor_user_can_login(int $user_id): bool
    {
        if (!$user_id) {
            return false;
        }

        // Legacy callers of authenticate() enforce the same active-membership
        // boundary as Signin for every role-less external identity.
        $isExternalPortalIdentity = $this->is_vendor_only_identity($user_id)
            || $this->is_gate_pass_only_identity($user_id)
            || $this->is_ptw_applicant_only_identity($user_id);
        if (!$isExternalPortalIdentity) {
            return true;
        }

        $hasActiveVendor = count((new Vendor_users_model())->get_accessible_memberships($user_id)) > 0;
        return $hasActiveVendor
            || $this->has_active_gate_pass_portal_membership($user_id)
            || $this->has_active_ptw_applicant_portal_membership($user_id);
    }

    private function _password_matches($user_info, $password): bool {
        $stored_password = (string) ($user_info->password ?? "");
        if ($stored_password === "") {
            return false;
        }

        $hash_info = password_get_info($stored_password);
        return (!empty($hash_info["algo"]) && password_verify((string) $password, $stored_password))
            || (strlen($stored_password) === 32 && hash_equals($stored_password, md5((string) $password)));
    }

    /**
     * Keep the legacy MD5 verifier only as a one-login migration bridge. The
     * successfully verified value is immediately replaced with PASSWORD_DEFAULT
     * using a compare-and-update so concurrent password changes are preserved.
     */
    private function _rehash_verified_password_if_needed(object $userInfo, string $password): void
    {
        $storedPassword = (string) ($userInfo->password ?? "");
        $hashInfo = password_get_info($storedPassword);
        $isLegacyMd5 = preg_match('/^[a-f0-9]{32}$/iD', $storedPassword) === 1;
        $needsRehash = !empty($hashInfo["algo"])
            && password_needs_rehash($storedPassword, PASSWORD_DEFAULT);

        if (!$isLegacyMd5 && !$needsRehash) {
            return;
        }

        $replacement = password_hash($password, PASSWORD_DEFAULT);
        if (!$replacement) {
            return;
        }

        $this->db_builder
            ->where("id", (int) $userInfo->id)
            ->where("password", $storedPassword)
            ->update(["password" => $replacement]);
        $userInfo->password = $replacement;
    }

    function start_user_session(int $user_id, int $active_vendor_id = 0): bool {
        if (!$user_id
            || !$this->_supports_auth_session_version()
            || !$this->is_login_enabled($user_id)
        ) {
            return false;
        }

        $session = \Config\Services::session();
        $session->set('user_id', $user_id);
        $session->remove([
            'pending_vendor_user_id',
            'pending_vendor_redirect_url',
            'pending_vendor_authenticated_at',
            'pending_mfa_challenge_id',
            'pending_mfa_user_id',
            'pending_mfa_redirect_url',
            'pending_mfa_started_at'
        ]);

        $session->set(
            "auth_session_version",
            $this->_get_auth_session_version($user_id)
        );

        if ($active_vendor_id) {
            $session->set('active_vendor_id', $active_vendor_id);
        } else {
            $session->remove('active_vendor_id');
        }

        try {
            app_hooks()->do_action('app_hook_after_signin');
        } catch (\Exception $ex) {
            log_message('error', '[ERROR] {exception}', ['exception' => $ex]);
        }

        return true;
    }

    function is_login_enabled(int $user_id): bool
    {
        if ($user_id < 1) {
            return false;
        }

        $supportsSessionVersion = $this->_supports_auth_session_version();
        $session = \Config\Services::session();
        $isAuthenticatedSession = (int) $session->get("user_id") === $user_id;
        if ($isAuthenticatedSession && !$supportsSessionVersion) {
            return false;
        }

        $select = $supportsSessionVersion
            ? "id, auth_session_version"
            : "id";
        $user = $this->db_builder
            ->select($select)
            ->getWhere([
                "id" => $user_id,
                "status" => "active",
                "deleted" => 0,
                "disable_login" => 0,
            ])
            ->getRow();

        if (!$user) {
            return false;
        }

        if ($supportsSessionVersion && $isAuthenticatedSession) {
            if (!$session->has("auth_session_version")) {
                return false;
            }

            $sessionVersion = $session->get("auth_session_version");
            if ((!is_int($sessionVersion) && !(is_string($sessionVersion) && ctype_digit($sessionVersion)))
                || (int) $sessionVersion < 1
            ) {
                return false;
            }

            $currentVersion = max(1, (int) ($user->auth_session_version ?? 0));
            if ((int) $sessionVersion !== $currentVersion) {
                return false;
            }
        }

        return true;
    }

    private function _supports_auth_session_version(): bool
    {
        if ($this->supports_auth_session_version === null) {
            $this->supports_auth_session_version = $this->db->fieldExists(
                "auth_session_version",
                $this->db->prefixTable("users")
            );
        }

        return $this->supports_auth_session_version;
    }

    private function _get_auth_session_version(int $userId): int
    {
        if (!$this->_supports_auth_session_version()) {
            return 1;
        }

        $row = $this->db_builder
            ->select("auth_session_version")
            ->getWhere(["id" => $userId], 1)
            ->getRow();

        return max(1, (int) ($row->auth_session_version ?? 1));
    }

    private function _client_can_login($user_info) {
        //check client login settings
        if ($user_info->user_type === "client" && get_setting("disable_client_login")) {
            return false;
        } else if ($user_info->user_type === "client") {
            //user can't be loged in if client has deleted
            $clients_table = $this->db->prefixTable('clients');

            $sql = "SELECT $clients_table.id
                    FROM $clients_table
                    WHERE $clients_table.id = $user_info->client_id AND $clients_table.deleted=0";
            $client_result = $this->db->query($sql);

            if ($client_result->resultID->num_rows !== 1) {
                return false;
            }
        }
    }

    function login_user_id() {
        $session = \Config\Services::session();
        return $session->has("user_id") ? $session->get("user_id") : "";
    }

    function sign_out() {
        try {
            app_hooks()->do_action('app_hook_before_signout');
        } catch (\Exception $ex) {
            log_message('error', '[ERROR] {exception}', ['exception' => $ex]);
        }

        $session = \Config\Services::session();
        $session->destroy();
        app_redirect('signin');
    }

    function get_details($options = array()) {
        $users_table = $this->db->prefixTable('users');
        $team_member_job_info_table = $this->db->prefixTable('team_member_job_info');
        $clients_table = $this->db->prefixTable('clients');
        $roles_table = $this->db->prefixTable('roles');

        $where = "";
        $id = $this->_get_clean_value($options, "id");
        $status = $this->_get_clean_value($options, "status");
        $user_type = $this->_get_clean_value($options, "user_type");
        $client_id = $this->_get_clean_value($options, "client_id");
        $exclude_user_id = $this->_get_clean_value($options, "exclude_user_id");
        $first_name = $this->_get_clean_value($options, "first_name");
        $last_name = $this->_get_clean_value($options, "last_name");

        if ($id) {
            $where .= " AND $users_table.id=$id";
        }
        if ($status === "active") {
            $where .= " AND $users_table.status='active'";
        } else if ($status === "inactive") {
            $where .= " AND $users_table.status='inactive'";
        }

        if ($user_type) {
            $where .= " AND $users_table.user_type='$user_type'";
        }

        if ($user_type == 'client') {
            $where .= " AND $clients_table.deleted=0";
        }

        if ($first_name) {
            $where .= " AND $users_table.first_name='$first_name'";
        }

        if ($last_name) {
            $where .= " AND $users_table.last_name='$last_name'";
        }

        if ($client_id) {
            $where .= " AND $users_table.client_id=$client_id";
        }

        if ($exclude_user_id) {
            $where .= " AND $users_table.id!=$exclude_user_id";
        }

        $non_admin_users_only = $this->_get_clean_value($options, "non_admin_users_only");
        if ($non_admin_users_only) {
            $where .= " AND $users_table.is_admin=0";
        }

        $show_own_clients_only_user_id = $this->_get_clean_value($options, "show_own_clients_only_user_id");
        if ($user_type == "client" && $show_own_clients_only_user_id) {
            $where .= " AND $users_table.client_id IN(SELECT $clients_table.id FROM $clients_table WHERE $clients_table.deleted=0 AND ($clients_table.created_by=$show_own_clients_only_user_id OR $clients_table.owner_id=$show_own_clients_only_user_id OR FIND_IN_SET('$show_own_clients_only_user_id', $clients_table.managers)))";
        }

        $quick_filter = $this->_get_clean_value($options, "quick_filter");
        if ($quick_filter) {
            $where .= $this->make_quick_filter_query($quick_filter, $users_table);
        }

        $client_groups = $this->_get_clean_value($options, "client_groups");
        if ($client_groups) {
            $client_groups_where = $this->prepare_allowed_client_groups_query($clients_table, $client_groups);
            if ($client_groups_where) {
                $where .= " AND $users_table.client_id IN(SELECT $clients_table.id FROM $clients_table WHERE $clients_table.deleted=0 $client_groups_where)";
            }
        }

        $custom_field_type = "team_members";
        if ($user_type === "client") {
            $custom_field_type = "client_contacts";
        } else if ($user_type === "lead") {
            $custom_field_type = "lead_contacts";
        }

        $limit_offset = "";
        $limit = $this->_get_clean_value($options, "limit");
        if ($limit) {
            $skip = $this->_get_clean_value($options, "skip");
            $offset = $skip ? $skip : 0;
            $limit_offset = " LIMIT $limit OFFSET $offset ";
        }

        $available_order_by_list = array(
            "first_name" => $users_table . ".first_name",
            "company_name" => $clients_table . ".company_name",
            "job_title" => $users_table . ".job_title",
            "email" => $users_table . ".email",
            "phone" => $users_table . ".phone",
            "skype" => $users_table . ".skype",
        );

        $order_by = get_array_value($available_order_by_list, $this->_get_clean_value($options, "order_by"));

        $order = "ORDER BY $users_table.first_name";

        if ($order_by) {
            $order_dir = $this->_get_clean_value($options, "order_dir");
            $order = " ORDER BY $order_by $order_dir ";
        }

        $search_by = $this->_get_clean_value($options, "search_by");
        if ($search_by) {
            $search_by = $this->db->escapeLikeString($search_by);

            $where .= " AND (";
            $where .= " $users_table.job_title LIKE '%$search_by%' ESCAPE '!' ";
            $where .= " OR $users_table.email LIKE '%$search_by%' ESCAPE '!' ";
            $where .= " OR $users_table.phone LIKE '%$search_by%' ESCAPE '!' ";
            $where .= " OR $users_table.skype LIKE '%$search_by%' ESCAPE '!' ";
            $where .= " OR $clients_table.company_name LIKE '%$search_by%' ESCAPE '!' ";
            $where .= " OR CONCAT($users_table.first_name, ' ', $users_table.last_name) LIKE '%$search_by%' ESCAPE '!' ";
            $where .= $this->get_custom_field_search_query($users_table, "client_contacts", $search_by);
            $where .= " )";
        }

        //prepare custom fild binding query
        $custom_fields = get_array_value($options, "custom_fields");
        $custom_field_filter = get_array_value($options, "custom_field_filter");
        $custom_field_query_info = $this->prepare_custom_field_query_string($custom_field_type, $custom_fields, $users_table, $custom_field_filter);
        $select_custom_fieds = get_array_value($custom_field_query_info, "select_string");
        $join_custom_fieds = get_array_value($custom_field_query_info, "join_string");
        $custom_fields_where = get_array_value($custom_field_query_info, "where_string");

        //prepare full query string
        $sql = "SELECT SQL_CALC_FOUND_ROWS $users_table.*, $roles_table.title AS role_title,
            $team_member_job_info_table.date_of_hire, $team_member_job_info_table.salary, $team_member_job_info_table.salary_term $select_custom_fieds
        FROM $users_table
        LEFT JOIN $team_member_job_info_table ON $team_member_job_info_table.user_id=$users_table.id
        LEFT JOIN $clients_table ON $clients_table.id=$users_table.client_id
        LEFT JOIN $roles_table ON $roles_table.id=$users_table.role_id
        $join_custom_fieds    
        WHERE $users_table.deleted=0 $where $custom_fields_where
        $order $limit_offset";

        $raw_query = $this->db->query($sql);

        $total_rows = $this->db->query("SELECT FOUND_ROWS() as found_rows")->getRow();

        if ($limit) {
            return array(
                "data" => $raw_query->getResult(),
                "recordsTotal" => $total_rows->found_rows,
                "recordsFiltered" => $total_rows->found_rows,
            );
        } else {
            return $raw_query;
        }
    }

    function is_email_exists($email, $target_client_id = 0) {
        $users_table = $this->db->prefixTable('users');

        $email = $this->_get_clean_value($email);

        $email = strtolower(trim($email));

        $sql = "SELECT $users_table.id, $users_table.user_type, $users_table.client_id
                FROM $users_table   
                WHERE $users_table.deleted=0 AND $users_table.email='$email' AND $users_table.user_type IN ('staff', 'client')";

        $result = $this->db->query($sql);

        if ($target_client_id && $result->resultID->num_rows) {
            //Same email can be used for different clients. Ensure the email doesn't exist on any staff user.
            //One client can't have the same email for different users.
            $email_exists_on_staff = false;
            $email_exists_on_taget_client = false;
            foreach ($result->getResult() as $user) {
                if ($user->user_type == "staff") {
                    $email_exists_on_staff = true;
                }
                if ($user->client_id == $target_client_id) {
                    $email_exists_on_taget_client = true;
                }
            }

            if ($email_exists_on_staff || $email_exists_on_taget_client) {
                return true; //Email exists
            } else {
                return false; //Not exists. (Exists on non staff user or non target client)
            }
        }

        if ($result->resultID->num_rows > 0) {
            return true; //Email exists.
        }

        return false; //Not exists. 
    }

    function get_job_info($user_id) {
        parent::use_table("team_member_job_info");
        return parent::get_one_where(array("user_id" => $user_id));
    }

    function save_job_info($data) {
        parent::use_table("team_member_job_info");

        //check if job info already exists
        $where = array("user_id" => $this->_get_clean_value($data, "user_id"));
        $exists = parent::get_one_where($where);
        if ($exists->user_id) {
            //job info found. update the record
            return parent::update_where($data, $where);
        } else {
            //insert new one
            return parent::ci_save($data);
        }
    }

    function get_team_members($member_ids) {

        $member_ids = $this->_get_clean_value($member_ids);
        if (!$member_ids) {
            return null;
        }

        $users_table = $this->db->prefixTable('users');
        $sql = "SELECT $users_table.*
        FROM $users_table
        WHERE $users_table.deleted=0 AND $users_table.user_type='staff' AND FIND_IN_SET($users_table.id, '$member_ids')
        ORDER BY $users_table.first_name";
        return $this->db->query($sql);
    }

    function get_access_info($user_id = 0) {
        $users_table = $this->db->prefixTable('users');
        $roles_table = $this->db->prefixTable('roles');
        $team_table = $this->db->prefixTable('team');

        $user_id = $this->_get_clean_value($user_id);

        if (!$user_id) {
            $user_id = 0;
        }

        $sql = "SELECT $users_table.id, $users_table.user_type, $users_table.is_admin, $users_table.role_id, $users_table.email,
            $users_table.first_name, $users_table.last_name, $users_table.image, $users_table.message_checked_at, $users_table.notification_checked_at, $users_table.client_id, $users_table.enable_web_notification,
            $users_table.is_primary_contact, $users_table.sticky_note, $users_table.language, $users_table.client_permissions,
            $roles_table.title as role_title, $roles_table.permissions,
            (SELECT GROUP_CONCAT(id) team_ids FROM $team_table WHERE FIND_IN_SET('$user_id', `members`)) as team_ids
        FROM $users_table
        LEFT JOIN $roles_table ON $roles_table.id = $users_table.role_id AND $roles_table.deleted = 0
        WHERE $users_table.deleted=0 AND $users_table.id=$user_id";
        return $this->db->query($sql)->getRow();
    }

    /* return comma separated list of user names */

    function user_group_names($user_ids = "") {
        $users_table = $this->db->prefixTable('users');
        $user_ids = $this->_get_clean_value($user_ids);

        $sql = "SELECT GROUP_CONCAT(' ', $users_table.first_name, ' ', $users_table.last_name) AS user_group_name
        FROM $users_table
        WHERE FIND_IN_SET($users_table.id, '$user_ids')";
        return $this->db->query($sql)->getRow();
    }

    /* return list of ids of the online users */

    function get_online_user_ids() {
        $users_table = $this->db->prefixTable('users');
        $now = get_current_utc_time();

        $sql = "SELECT $users_table.id 
        FROM $users_table
        WHERE TIMESTAMPDIFF(MINUTE, users.last_online, '$now')<=0";
        return $this->db->query($sql)->getResult();
    }

    function get_active_members_and_clients($options = array()) {
        $users_table = $this->db->prefixTable('users');
        $clients_table = $this->db->prefixTable('clients');

        $where = "";

        $user_type = $this->_get_clean_value($options, "user_type");
        if ($user_type) {
            $where .= " AND $users_table.user_type='$user_type'";
        }

        $exclude_user_id = $this->_get_clean_value($options, "exclude_user_id");
        if ($exclude_user_id) {
            $where .= " AND $users_table.id!=$exclude_user_id";
        }

        $show_own_clients_only_user_id = $this->_get_clean_value($options, "show_own_clients_only_user_id");
        if ($user_type == "client" && $show_own_clients_only_user_id) {
            $where .= " AND $users_table.client_id IN(SELECT $clients_table.id FROM $clients_table WHERE $clients_table.deleted=0 AND ($clients_table.created_by=$show_own_clients_only_user_id OR $clients_table.owner_id=$show_own_clients_only_user_id OR FIND_IN_SET('$show_own_clients_only_user_id', $clients_table.managers)))";
        }

        $client_groups = $this->_get_clean_value($options, "client_groups");
        if ($client_groups) {
            $client_groups_where = $this->prepare_allowed_client_groups_query($clients_table, $client_groups);
            if ($client_groups_where) {
                $where .= " AND $users_table.client_id IN(SELECT $clients_table.id FROM $clients_table WHERE $clients_table.deleted=0 $client_groups_where)";
            }
        }

        $sql = "SELECT CONCAT($users_table.first_name, ' ',$users_table.last_name) AS member_name, $users_table.last_online, $users_table.id, $users_table.image, $users_table.job_title, $users_table.user_type, $clients_table.company_name
        FROM $users_table
        LEFT JOIN $clients_table ON $clients_table.id = $users_table.client_id AND $clients_table.deleted=0
        WHERE $users_table.deleted=0 AND $users_table.status='active' $where
        ORDER BY $users_table.last_online DESC";
        return $this->db->query($sql);
    }

    function count_total_contacts($options = array()) {
        $users_table = $this->db->prefixTable('users');
        $clients_table = $this->db->prefixTable('clients');

        $where = "";
        $show_own_clients_only_user_id = $this->_get_clean_value($options, "show_own_clients_only_user_id");
        if ($show_own_clients_only_user_id) {
            $where .= " AND $users_table.client_id IN(SELECT $clients_table.id FROM $clients_table WHERE $clients_table.deleted=0 AND ($clients_table.created_by=$show_own_clients_only_user_id OR $clients_table.owner_id=$show_own_clients_only_user_id OR FIND_IN_SET('$show_own_clients_only_user_id', $clients_table.managers)))";
        }

        $last_online = $this->_get_clean_value($options, "last_online");
        if ($last_online) {
            $where .= " AND DATE($users_table.last_online)>='$last_online'";
        }

        $client_groups = $this->_get_clean_value($options, "client_groups");
        if ($client_groups) {
            $client_groups_where = $this->prepare_allowed_client_groups_query($clients_table, $client_groups);
            if ($client_groups_where) {
                $where .= " AND $users_table.client_id IN(SELECT $clients_table.id FROM $clients_table WHERE $clients_table.deleted=0 $client_groups_where)";
            }
        }

        $sql = "SELECT COUNT($users_table.id) AS total
        FROM $users_table 
        WHERE $users_table.deleted=0 AND $users_table.user_type='client' $where";
        return $this->db->query($sql)->getRow()->total;
    }

    private function make_quick_filter_query($filter, $users_table) {
        $query = "";

        if ($filter == "logged_in_today" || $filter == "logged_in_seven_days") {
            $last_online = get_today_date();
            if ($filter == "logged_in_seven_days") {
                $last_online = subtract_period_from_date(get_today_date(), 7, "days");
            }

            $query = " AND $users_table.id IN(SELECT $users_table.id FROM $users_table WHERE $users_table.deleted=0 AND $users_table.user_type='client' AND DATE($users_table.last_online)>='$last_online') ";
        }

        return $query;
    }

    function get_user_from_full_name($user_full_name = "", $user_type = "") {
        $users_table = $this->db->prefixTable('users');
        $user_full_name = $this->_get_clean_value($user_full_name);

        $where = "";
        if ($user_type === "staff") {
            $where .= " AND $users_table.user_type='staff' ";
        } else if ($user_type === "client") {
            $where .= " AND $users_table.user_type='client' ";
        }

        $sql = "SELECT $users_table.id 
        FROM $users_table
        WHERE $users_table.deleted=0 AND $users_table.status='active' AND CONCAT(TRIM($users_table.first_name), ' ', TRIM($users_table.last_name))='$user_full_name' $where
        LIMIT 1";

        return $this->db->query($sql)->getRow();
    }

    function get_other_clients_of_this_client_contact($email, $id) {
        $users_table = $this->db->prefixTable('users');
        $clients_table = $this->db->prefixTable('clients');

        $id = $this->_get_clean_value($id);
        $email = $this->_get_clean_value($email);

        $sql = "SELECT $users_table.id AS user_id, $clients_table.company_name 
        FROM $users_table   
        LEFT JOIN $clients_table ON $clients_table.id = $users_table.client_id AND $clients_table.deleted=0
        WHERE $users_table.deleted=0 AND $users_table.email='$email' AND $users_table.status='active' AND $users_table.disable_login=0 AND $users_table.user_type='client' AND $users_table.id!=$id ";

        return $this->db->query($sql);
    }

    function update_password($email, $password) {
        $users_table = $this->db->prefixTable('users');
        $email = strtolower(trim((string) $email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $matches = $this->db->query(
            "SELECT id FROM {$users_table}
             WHERE deleted = 0 AND LOWER(email) = ?",
            [$email]
        )->getResult();

        // Never change multiple identities because a legacy email is shared.
        if (count($matches) !== 1) {
            return false;
        }

        return (bool) $this->ci_save(
            ["password" => (string) $password],
            (int) $matches[0]->id
        );
    }

    function count_total_users() {
        $users_table = $this->db->prefixTable('users');

        $sql = "SELECT COUNT($users_table.id) AS total
        FROM $users_table 
        WHERE $users_table.deleted=0 AND $users_table.user_type='staff' AND $users_table.status='active'";
        return $this->db->query($sql)->getRow()->total;
    }

    function get_team_members_id_and_name($options = array()) {
        $users_table = $this->db->prefixTable('users');

        $where = "";
        $exclude_admins = $this->_get_clean_value($options, "exclude_admins");
        if ($exclude_admins) {
            $where .= " AND $users_table.is_admin!=1";
        }


        $sql = "SELECT $users_table.id, CONCAT($users_table.first_name, ' ',$users_table.last_name) AS user_name
        FROM $users_table 
        WHERE $users_table.deleted=0 AND $users_table.user_type='staff' AND $users_table.status='active' $where";
        return $this->db->query($sql);
    }
}
