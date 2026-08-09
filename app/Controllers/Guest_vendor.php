<?php

namespace App\Controllers;

use App\Models\Vendors_model;
use App\Models\Vendor_groups_model;
use App\Models\Vendor_documents_model;
use App\Models\Vendor_update_requests_model;
use App\Libraries\Upload_security;
use App\Libraries\UploadSecurityException;
use App\Libraries\ReCAPTCHA;
use App\Libraries\Runtime_schema_guard;

class Guest_vendor extends App_Controller
{
    protected $Vendors_model;
    protected $Vendor_groups_model;
    protected $Vendor_documents_model;
    protected $Vendor_update_requests_model;
    protected $db;

    public function __construct()
    {
        parent::__construct();

         
        $this->Vendors_model             = new Vendors_model();
        $this->Vendor_groups_model       = new Vendor_groups_model();
        $this->Vendor_documents_model    = new Vendor_documents_model();
        $this->Vendor_update_requests_model = new Vendor_update_requests_model();
        $this->db = db_connect();
        $this->_ensure_vendor_onboarding_columns();
    }


    public function index()
    {
        $view_data = [];

        // Public layout (same pattern as Request_estimate)
        $view_data["topbar"] = "includes/public/topbar";
        $view_data["left_menu"] = false;

        // Vendor groups dropdown
        $groups_dropdown = ["" => "- " . app_lang("select_vendor_group") . " -"];
        $groups = $this->Vendor_groups_model->get_details()->getResult();
        foreach ($groups as $g) {
            $groups_dropdown[$g->id] = $g->name . " (" . $g->code . ")";
        }
        $view_data["vendor_groups_dropdown"] = $groups_dropdown;

        // Countries dropdown
        $country_table = $this->db->prefixTable('country');
        $countries = $this->db->query("SELECT id, name FROM $country_table WHERE deleted=0 AND is_active=1 ORDER BY name ASC")->getResult();
        $countries_dropdown = ["" => "- " . app_lang("select_country") . " -"];
        foreach ($countries as $c) {
            $countries_dropdown[$c->id] = $c->name;
        }
        $view_data["countries_dropdown"] = $countries_dropdown;

        // Empty dropdowns initially
        $view_data["regions_dropdown"] = ["" => "- " . app_lang("select_region") . " -"];
        $view_data["cities_dropdown"] = ["" => "- " . app_lang("select_city") . " -"];
        $view_data["intl_dial_codes"] = require APPPATH . "Config/intl_phone_dial_codes.php";


        $doc_types_table = $this->db->prefixTable('vendor_document_types');
        $doc_types = $this->db->query("
                                SELECT id, name, code
                                FROM $doc_types_table
                                WHERE deleted = 0 AND is_active = 1
                                ORDER BY name ASC
                            ")->getResult();

        $doc_types_dropdown = ["" => "- " . app_lang("select_document_type") . " -"];
        foreach ($doc_types as $dt) {
            $label = $dt->name;
            if (!empty($dt->code)) {
                $label .= " (" . $dt->code . ")";
            }
            $doc_types_dropdown[$dt->id] = $label;
        }
        $view_data["vendor_document_types_dropdown"] = $doc_types_dropdown;

        return $this->template->rander("guest_vendor/index", $view_data);
    }

    public function save()
    {
        $db = $this->db; // use the same connection everywhere
        $uploadedPaths = [];
        $ipHash = hash('sha256', (string)$this->request->getIPAddress());
        $throttler = service('throttler');
        if (!$throttler->check('guest_vendor_save_' . $ipHash, 10, 3600)) {
            return $this->response
                ->setStatusCode(429)
                ->setHeader('Retry-After', (string)max(1, $throttler->getTokenTime()))
                ->setJSON([
                    'success' => false,
                    'message' => 'Too many registration attempts. Please wait and try again.',
                ]);
        }

        try {
            (new ReCAPTCHA())->validate_recaptcha(true);

            // ✅ EXACT same validation style as your save()
            $this->validate_submitted_data([
                "vendor_group_id" => "required|numeric",
                "vendor_name"     => "required",
                "email"           => "required|valid_email",
                "cr_number"       => "required",
                "phone_country_code" => "required|max_length[12]",
                "phone_local"     => "required|regex_match[/^\d{4,15}$/]",
                "contact_person"  => "required",
                "contact_designation" => "permit_empty",

                // optional numeric (if empty, CI may fail numeric, so handle below)
                "country_id"      => "permit_empty|numeric",
                "region_id"       => "permit_empty|numeric",
                "city_id"         => "permit_empty|numeric",

                // login user fields
                "user_name"       => "required",
                "user_email"      => "required|valid_email",
                "password"        => "required",
                "password_confirm" => "permit_empty",
                // optional vendor address fields


                "vendor_document_type_id" => "required",
                "issued_at"               => "permit_empty",
                "expires_at"              => "permit_empty",
                "address"     => "permit_empty",
                "po_box"      => "permit_empty",
                "postal_code" => "permit_empty",
            ]);

            // ✅ Guest page is CREATE ONLY
            $vendor_email = strtolower(trim((string) $this->request->getPost("email")));
            $cr_number = trim((string) $this->request->getPost("cr_number"));
            $password = (string) $this->request->getPost("password");
            $password_confirm = (string) $this->request->getPost("password_confirm");
            $phone_country_code = $this->_normalize_dial_code($this->request->getPost("phone_country_code"));
            $phone = $this->_merge_e164($phone_country_code, (string) $this->request->getPost("phone_local"));

            if (!$this->_is_allowed_dial($phone_country_code)) {
                echo json_encode([
                    "success" => false,
                    "message" => app_lang("vendor_invalid_country_code"),
                    "field"   => "phone_country_code",
                    "errors"  => ["phone_country_code" => app_lang("vendor_invalid_country_code")]
                ]);
                return;
            }

            if ($phone === "") {
                echo json_encode([
                    "success" => false,
                    "message" => app_lang("vendor_invalid_phone_number"),
                    "field"   => "phone_local",
                    "errors"  => ["phone_local" => app_lang("vendor_invalid_phone_number")]
                ]);
                return;
            }

            // Company email is descriptive vendor data and may be shared by
            // different CR records. The CR remains the vendor identifier.
            $vendors_table = $db->prefixTable("vendors");
            $existing_cr = $db->table($vendors_table)
                ->select("id")
                ->where("cr_number", $cr_number)
                ->where("deleted", 0)
                ->get()
                ->getRow();

            if ($existing_cr) {
                echo json_encode([
                    "success" => false,
                    "message" => app_lang("cr_number_already_exists"),
                    "field"   => "cr_number",
                    "errors"  => ["cr_number" => app_lang("cr_number_already_exists")]
                ]);
                return;
            }

            $user_email = strtolower(trim((string) $this->request->getPost("user_email")));
            $existing_user = $db->table("users")
                ->select("id, user_type, status, disable_login, role_id, is_admin, password, deleted")
                ->where("email", $user_email)
                ->orderBy("deleted", "ASC")
                ->orderBy("id", "ASC")
                ->get()
                ->getRow();

            if ($existing_user) {
                // A public registration must never reactivate an identity that
                // an administrator deliberately deprovisioned.
                if ((int)($existing_user->deleted ?? 0) === 1) {
                    echo json_encode([
                        "success" => false,
                        "message" => app_lang("authentication_failed"),
                        "field"   => "password",
                        "errors"  => ["password" => app_lang("authentication_failed")]
                    ]);
                    return;
                }

                if ((string)($existing_user->status ?? "") !== "active"
                    || (int)($existing_user->disable_login ?? 0) === 1
                ) {
                    echo json_encode([
                        "success" => false,
                        "message" => app_lang("authentication_failed"),
                        "field"   => "password",
                        "errors"  => ["password" => app_lang("authentication_failed")]
                    ]);
                    return;
                }

                if (($existing_user->user_type ?? "") !== "staff") {
                    echo json_encode([
                        "success" => false,
                        "message" => app_lang("vendor_email_belongs_to_non_staff_user"),
                        "field"   => "user_email",
                        "errors"  => ["user_email" => app_lang("vendor_email_belongs_to_non_staff_user")]
                    ]);
                    return;
                }

                // An email is a single global identity. Reusing it for another
                // CR is allowed only after the applicant proves ownership of
                // the existing account with its current password.
                if (!$this->Users_model->verify_user_password((int) $existing_user->id, $password)) {
                    echo json_encode([
                        "success" => false,
                        "message" => app_lang("authentication_failed"),
                        "field"   => "password",
                        "errors"  => ["password" => app_lang("authentication_failed")]
                    ]);
                    return;
                }
            } else {
                if ($password_confirm === "") {
                    echo json_encode([
                        "success" => false,
                        "message" => app_lang("password_confirm_required"),
                        "field"   => "password_confirm",
                        "errors"  => ["password_confirm" => app_lang("password_confirm_required")]
                    ]);
                    return;
                }

                if ($password !== $password_confirm) {
                    echo json_encode([
                        "success" => false,
                        "message" => app_lang("passwords_do_not_match"),
                        "field"   => "password_confirm",
                        "errors"  => ["password_confirm" => app_lang("passwords_do_not_match")]
                    ]);
                    return;
                }

                $policyErrors = $this->Users_model->password_policy_errors($password);
                if ($policyErrors) {
                    echo json_encode([
                        "success" => false,
                        "message" => implode(" ", $policyErrors),
                        "field" => "password",
                        "errors" => ["password" => implode(" ", $policyErrors)],
                    ]);
                    return;
                }
            }

            // ✅ optional ids: store NULL instead of 0
            $country_id = $this->request->getPost("country_id");
            $region_id  = $this->request->getPost("region_id");
            $city_id    = $this->request->getPost("city_id");

            $vendor_data = [
                "vendor_group_id" => (int) $this->request->getPost("vendor_group_id"),
                "vendor_name"     => $this->request->getPost("vendor_name"),
                "email"           => $vendor_email,
                "cr_number"       => $cr_number,
                "phone"           => $phone,
                "phone_country_code" => $phone_country_code,
                "contact_person"  => $this->request->getPost("contact_person"),
                "contact_designation" => $this->request->getPost("contact_designation"),

                "country_id"      => $country_id ? (int) $country_id : null,
                "region_id"       => $region_id  ? (int) $region_id  : null,
                "city_id"         => $city_id    ? (int) $city_id    : null,



                "address"     => $this->request->getPost("address"),
                "po_box"      => $this->request->getPost("po_box"),
                "postal_code" => $this->request->getPost("postal_code"),

                // ✅ public submission always new
                "status"          => vendor_initial_registration_status(),

                // ✅ public: no login user
                "created_by"      => 0
            ];

            $vendor_data = clean_data($vendor_data);

            // ---------- TRANSACTION ----------
            $db->transBegin();

            // 1) Save vendor
            $save_vendor_id = $this->Vendors_model->ci_save($vendor_data);
            if (!$save_vendor_id) {
                $err = $db->error();
                throw new \RuntimeException($err["message"] ?: "Vendor save failed.");
            }

            // 2) Reuse existing user by email, or create new, then link pivot
            $user_id = 0;

            if ($existing_user) {
                $user_id = (int) $existing_user->id;
            } else {
                $password = (string) $this->request->getPost("password");

                // ✅ pod_users required fields
                $user_data = [
                    "first_name" => $this->request->getPost("user_name"),
                    "last_name"  => "",

                    "email"      => $user_email,
                    "password"   => password_hash($password, PASSWORD_DEFAULT),

                    "user_type"  => "staff",     // keep same as your save()
                    "is_admin"   => 0,
                    "role_id"    => 0,

                    "status"     => "active",
                    "language"   => "",
                    "deleted"    => 0
                ];

                $ok = $db->table("users")->insert(clean_data($user_data));
                if (!$ok) {
                    $err = $db->error();
                    throw new \RuntimeException("User insert error: " . ($err["message"] ?: "unknown"));
                }

                $user_id = (int) $db->insertID();
            }

            // ✅ pod_vendor_users pivot
            $existing_pivot = $db->table("vendor_users")
                ->select("id")
                ->where("vendor_id", (int) $save_vendor_id)
                ->where("user_id", (int) $user_id)
                ->get()
                ->getRow();

            if ($existing_pivot) {
                $ok = $db->table("vendor_users")
                    ->where("id", (int)$existing_pivot->id)
                    ->update(clean_data([
                        "invited_by" => 0,
                        "vendor_role_id" => 1,
                        "is_owner" => 1,
                        "status" => "invited",
                        "deleted" => 0
                    ]));
                if (!$ok) {
                    $err = $db->error();
                    throw new \RuntimeException("Vendor user pivot update error: " . ($err["message"] ?: "unknown"));
                }
            } else {
                $pivot = [
                    "vendor_id"      => (int) $save_vendor_id,
                    "user_id"        => (int) $user_id,

                    // public page: no inviter
                    "invited_by"     => 0,

                    "vendor_role_id" => 1,     // Owner
                    "is_owner"       => 1,
                    "status"         => "invited",
                    "deleted"        => 0
                ];

                $ok = $db->table("vendor_users")->insert(clean_data($pivot));
                if (!$ok) {
                    $err = $db->error();
                    throw new \RuntimeException("Vendor user pivot insert error: " . ($err["message"] ?: "unknown"));
                }
            }

            // The applicant is also the primary contact for this CR. Keep one
            // contact row per vendor/user membership and submit it through the
            // same approval queue as contacts added later in the portal.
            $contacts_table = $db->prefixTable("vendor_contacts");
            $contact_data = clean_data([
                "vendor_id"     => (int)$save_vendor_id,
                "user_id"       => (int)$user_id,
                "contacts_name" => trim((string)$this->request->getPost("contact_person")),
                "phone"         => $phone,
                "fax"           => "",
                "designation"   => trim((string)$this->request->getPost("contact_designation")),
                "email"         => $user_email,
                "email_2"       => "",
                "mobile"        => $phone,
                "role"          => "Owner",
                "is_primary"    => 1,
                "is_active"     => 1,
                "status"        => "pending",
                "deleted"       => 0,
                "created_at"    => date("Y-m-d H:i:s"),
                "updated_at"    => date("Y-m-d H:i:s"),
            ]);

            $existing_contact = $db->table($contacts_table)
                ->select("id")
                ->where("vendor_id", (int)$save_vendor_id)
                ->where("user_id", (int)$user_id)
                ->get()
                ->getRow();

            if ($existing_contact) {
                $contact_id = (int)$existing_contact->id;
                $ok = $db->table($contacts_table)
                    ->where("id", $contact_id)
                    ->update($contact_data);
            } else {
                $ok = $db->table($contacts_table)->insert($contact_data);
                $contact_id = (int)$db->insertID();
            }

            if (!$ok || !$contact_id) {
                $err = $db->error();
                throw new \RuntimeException("Vendor contact save error: " . ($err["message"] ?: "unknown"));
            }

            $contact_changes = [
                "module"    => "contacts",
                "table"     => "vendor_contacts",
                "action"    => "create",
                "record_id" => $contact_id,
                "before"    => [],
                "after"     => $contact_data,
            ];

            $contact_request_id = $this->Vendor_update_requests_model->ci_save([
                "vendor_id"    => (int)$save_vendor_id,
                "requested_by" => (int)$user_id,
                "changes"      => json_encode($contact_changes, JSON_UNESCAPED_UNICODE),
                "status"       => "pending",
                "deleted"      => 0,
                "created_at"   => date("Y-m-d H:i:s"),
                "updated_at"   => date("Y-m-d H:i:s"),
            ]);

            if (!$contact_request_id) {
                $err = $db->error();
                throw new \RuntimeException("Vendor contact approval request error: " . ($err["message"] ?: "unknown"));
            }


            // 3) Save initial vendor documents + create VUR rows (pending)
            $doc_type_ids = $this->request->getPost("vendor_document_type_id");
            $issued_ats = $this->request->getPost("issued_at");
            $expires_ats = $this->request->getPost("expires_at");
            $files = $this->request->getFileMultiple("file");

            if (!is_array($doc_type_ids)) {
                $doc_type_ids = [$doc_type_ids];
            }
            if (!is_array($issued_ats)) {
                $issued_ats = [$issued_ats];
            }
            if (!is_array($expires_ats)) {
                $expires_ats = [$expires_ats];
            }
            if (!$files) {
                $single_file = $this->request->getFile("file");
                $files = $single_file ? [$single_file] : [];
            }

            $max_documents = max(count($doc_type_ids), count($files), count($issued_ats), count($expires_ats));
            $document_rows = [];
            for ($i = 0; $i < $max_documents; $i++) {
                $doc_type_id = (int)($doc_type_ids[$i] ?? 0);
                $file = $files[$i] ?? null;
                $has_file = $file && $file->isValid() && !$file->hasMoved();
                $has_dates = !empty($issued_ats[$i]) || !empty($expires_ats[$i]);

                if (!$doc_type_id && !$has_file && !$has_dates) {
                    continue;
                }

                if (!$doc_type_id || !$has_file) {
                    $db->transRollback();

                    echo json_encode([
                        "success" => false,
                        "message" => app_lang("vendor_document_row_required"),
                        "errors"  => [
                            "vendor_document_type_id" => !$doc_type_id ? app_lang("field_required") : null,
                            "file"                    => !$has_file ? app_lang("file_is_required") : null,
                        ],
                    ]);
                    return;
                }

                $document_rows[] = [
                    "vendor_document_type_id" => $doc_type_id,
                    "issued_at" => $issued_ats[$i] ?? null,
                    "expires_at" => $expires_ats[$i] ?? null,
                    "file" => $file,
                ];
            }

            if (!$document_rows) {
                $db->transRollback();

                echo json_encode([
                    "success" => false,
                    "message" => app_lang("file_is_required"),
                    "errors"  => [
                        "vendor_document_type_id" => !$doc_type_id ? app_lang("field_required") : null,
                        "file"                    => !$has_file ? app_lang("file_is_required") : null,
                    ],
                ]);
                return;
            }

            // Upload to same structure as vendor portal
            $upload_dir = WRITEPATH . "uploads/vendor_documents/vendor_" . $save_vendor_id . "/";
            $uploadSecurity = new Upload_security();

            foreach ($document_rows as $document_row) {
                $file = $document_row["file"];
                $stored = $uploadSecurity->storeUploadedFile(
                    $file,
                    $upload_dir,
                    Upload_security::CONTEXT_SECURITY_DOCUMENT,
                    'vd_'
                );
                $new_name = $stored['stored_name'];
                $uploadedPaths[] = $stored['path'];

                // Prepare document data (same style as Vendor_portal::save_document)
                $doc_data = [
                    "vendor_id"              => (int)$save_vendor_id,
                    "vendor_document_type_id" => (int)$document_row["vendor_document_type_id"],
                    "disk"                   => "local",
                    "path"                   => "vendor_documents/vendor_" . $save_vendor_id . "/" . $new_name,
                    "original_name"          => $stored['original_name'],
                    "mime_type"              => $stored['detected_mime'],
                    "size_bytes"             => $stored['size_bytes'],
                    "issued_at"              => $document_row["issued_at"] ?: null,
                    "expires_at"             => $document_row["expires_at"] ?: null,
                    "uploaded_by"            => $user_id,
                    "status"                 => "pending",
                    "deleted"                => 0,
                    "created_at"             => date("Y-m-d H:i:s"),
                    "updated_at"             => date("Y-m-d H:i:s"),
                ];

                $doc_clean = clean_data($doc_data);
                $doc_id    = $this->Vendor_documents_model->ci_save($doc_clean);

                if (!$doc_id) {
                    $err = $db->error();
                    throw new \RuntimeException("Vendor document insert error: " . ($err["message"] ?? "unknown"));
                }

                // Build vendor_update_requests payload (same structure as vendor portal)
                $changes = [
                    "module"    => "documents",
                    "table"     => "vendor_documents",
                    "action"    => "create",
                    "record_id" => (int)$doc_id,
                    "before"    => [],
                    "after"     => $doc_clean,
                ];

                $vur_data = [
                    "vendor_id"    => (int)$save_vendor_id,
                    "requested_by" => (int)$user_id,
                    "changes"      => json_encode($changes, JSON_UNESCAPED_UNICODE),
                    "status"       => "pending",
                    "deleted"      => 0,
                    "created_at"   => date("Y-m-d H:i:s"),
                    "updated_at"   => date("Y-m-d H:i:s"),
                ];

                $this->Vendor_update_requests_model->ci_save($vur_data);
            }


            if ($db->transStatus() === false) {
                $err = $db->error();
                throw new \RuntimeException("Transaction failed: " . ($err["message"] ?: "unknown"));
            }

            $db->transCommit();

            echo json_encode([
                "success" => true,
                "message" => app_lang("guest_vendor_application_saved")
            ]);
            return;
        } catch (\Throwable $e) {

            // rollback if needed
            try {
                $db->transRollback();
            } catch (\Throwable $t) {
            }

            // ✅ map DB unique constraint (race-condition) to field errors
            foreach ($uploadedPaths as $uploadedPath) {
                if (is_file($uploadedPath)) {
                    @unlink($uploadedPath);
                }
            }

            if ($e instanceof UploadSecurityException) {
                log_message('notice', 'Guest vendor document upload rejected.');
                return $this->response->setStatusCode(422)->setJSON([
                    'success' => false,
                    'message' => app_lang('invalid_file_type'),
                ]);
            }

            $msg = $e->getMessage();
            if (stripos($msg, "Duplicate entry") !== false
                && (stripos($msg, "cr_number") !== false || stripos($msg, "cr identity") !== false)
            ) {
                echo json_encode([
                    "success" => false,
                    "message" => app_lang("cr_number_already_exists"),
                    "field"   => "cr_number",
                    "errors"  => ["cr_number" => app_lang("cr_number_already_exists")]
                ]);
                return;
            }

            if (stripos($msg, "Duplicate entry") !== false && stripos($msg, "email") !== false) {
                // Company email is intentionally non-unique; this can only be
                // the global login email constraint.
                $field = "user_email";

                echo json_encode([
                    "success" => false,
                    "message" => app_lang("email_already_exists"),
                    "field"   => $field,
                    "errors"  => [$field => app_lang("email_already_exists")]
                ]);
                return;
            }

            log_message("error", "Guest vendor save error: " . $e->getMessage());

            echo json_encode([
                "success" => false,
                "message" => $e->getMessage()
            ]);
            return;
        }
    }

    private function _ensure_vendor_onboarding_columns(): void
    {
        Runtime_schema_guard::requireTablesAndColumns($this->db, [
            "vendors" => [
                "cr_number", "phone", "phone_country_code", "contact_person", "contact_designation",
            ],
        ], "vendor onboarding");
    }

    /** @var list<string>|null */
    private static $allowedDialCache = null;

    private function _dial_code_whitelist(): array
    {
        if (self::$allowedDialCache === null) {
            $list = require APPPATH . "Config/intl_phone_dial_codes.php";
            self::$allowedDialCache = array_values(array_unique(array_column($list, "code")));
        }

        return self::$allowedDialCache;
    }

    private function _normalize_dial_code(?string $dial): string
    {
        $dial = trim((string) $dial);
        if ($dial === "") {
            return "";
        }

        if ($dial[0] !== "+") {
            $dial = "+" . ltrim($dial, "+");
        }

        return $dial;
    }

    private function _is_allowed_dial(string $dial): bool
    {
        return in_array($dial, $this->_dial_code_whitelist(), true);
    }

    private function _merge_e164(string $dial, string $localDigits): string
    {
        $localDigits = preg_replace('/\D+/', "", $localDigits) ?? "";
        if ($dial === "" || $localDigits === "") {
            return "";
        }

        return $dial . $localDigits;
    }

    // AJAX: regions by country (public)
    public function get_regions_dropdown_by_country($country_id = 0)
    {
        validate_numeric_value($country_id);

        $regions_table = $this->db->prefixTable("regions");
        $regions = $this->db->query("SELECT id, name FROM $regions_table WHERE deleted=0 AND is_active=1 AND country_id=$country_id ORDER BY name ASC")->getResult();

        $options = "<option value=''>- " . app_lang("select_region") . " -</option>";
        foreach ($regions as $r) {
            $options .= "<option value='{$r->id}'>{$r->name}</option>";
        }

        echo $options;
    }

    // AJAX: cities by region (public)
    public function get_cities_dropdown_by_region($region_id = 0)
    {
        validate_numeric_value($region_id);

        $cities_table = $this->db->prefixTable("cities");
        $cities = $this->db->query("SELECT id, name FROM $cities_table WHERE deleted=0 AND is_active=1 AND regions_id=$region_id ORDER BY name ASC")->getResult();

        $options = "<option value=''>- " . app_lang("select_city") . " -</option>";
        foreach ($cities as $c) {
            $options .= "<option value='{$c->id}'>{$c->name}</option>";
        }

        echo $options;
    }
}
