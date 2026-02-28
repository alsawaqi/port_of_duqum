<?php

namespace App\Controllers;

use App\Models\Vendors_model;
use App\Models\Vendor_groups_model;
use App\Models\Vendor_documents_model;
use App\Models\Vendor_update_requests_model;

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

        try {
            // ✅ EXACT same validation style as your save()
            $this->validate_submitted_data([
                "vendor_group_id" => "required|numeric",
                "vendor_name"     => "required",
                "email"           => "required|valid_email",

                // optional numeric (if empty, CI may fail numeric, so handle below)
                "country_id"      => "numeric",
                "region_id"       => "numeric",
                "city_id"         => "numeric",

                // login user fields
                "user_name"       => "required",
                "user_email"      => "required|valid_email",
                "password"        => "permit_empty",
                // optional vendor address fields


                "vendor_document_type_id" => "required|numeric",
                "issued_at"               => "permit_empty",
                "expires_at"              => "permit_empty",
                "address"     => "permit_empty",
                "po_box"      => "permit_empty",
                "postal_code" => "permit_empty",
            ]);

            // ✅ Guest page is CREATE ONLY
            $vendor_email = strtolower(trim((string) $this->request->getPost("email")));

            // vendor email must be unique for active records (deleted=0)
            $vendors_table = $db->prefixTable("vendors");
            $existing_vendor = $db->table($vendors_table)
                ->select("id")
                ->where("email", $vendor_email)
                ->where("deleted", 0)
                ->get()
                ->getRow();

            if ($existing_vendor) {
                echo json_encode([
                    "success" => false,
                    "message" => app_lang("email_already_exists"),
                    "field"   => "email",
                    "errors"  => ["email" => app_lang("email_already_exists")]
                ]);
                return;
            }

            $user_email = strtolower(trim((string) $this->request->getPost("user_email")));
            $existing_user = $db->table("users")
                ->select("id, user_type, deleted")
                ->where("email", $user_email)
                ->get()
                ->getRow();

            if ($existing_user && strtolower((string)($existing_user->user_type ?? "")) !== "staff") {
                echo json_encode([
                    "success" => false,
                    "message" => "This email is linked to a non-staff account and cannot be used for vendor portal access.",
                    "field"   => "user_email",
                    "errors"  => ["user_email" => "This email is linked to a non-staff account."]
                ]);
                return;
            }

            if (!$existing_user) {
                $this->validate_submitted_data([
                    "password" => "required",
                ]);
            }

            // ✅ optional ids: store NULL instead of 0
            $country_id = $this->request->getPost("country_id");
            $region_id  = $this->request->getPost("region_id");
            $city_id    = $this->request->getPost("city_id");

            $vendor_data = [
                "vendor_group_id" => (int) $this->request->getPost("vendor_group_id"),
                "vendor_name"     => $this->request->getPost("vendor_name"),
                "email"           => $vendor_email,

                "country_id"      => $country_id ? (int) $country_id : null,
                "region_id"       => $region_id  ? (int) $region_id  : null,
                "city_id"         => $city_id    ? (int) $city_id    : null,



                "address"     => $this->request->getPost("address"),
                "po_box"      => $this->request->getPost("po_box"),
                "postal_code" => $this->request->getPost("postal_code"),

                // ✅ public submission always new
                "status"          => "pending",

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

                // revive soft-deleted user if needed
                if ((int)($existing_user->deleted ?? 0) === 1) {
                    $ok = $db->table("users")
                        ->where("id", $user_id)
                        ->update(clean_data([
                            "deleted" => 0,
                            "status" => "active",
                            "disable_login" => 0
                        ]));
                    if (!$ok) {
                        $err = $db->error();
                        throw new \RuntimeException("Failed to restore existing user: " . ($err["message"] ?: "unknown"));
                    }
                }
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
                        "status" => "active",
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
                    "status"         => "active",
                    "deleted"        => 0
                ];

                $ok = $db->table("vendor_users")->insert(clean_data($pivot));
                if (!$ok) {
                    $err = $db->error();
                    throw new \RuntimeException("Vendor user pivot insert error: " . ($err["message"] ?: "unknown"));
                }
            }


            // 3) Save initial vendor document + create VUR (pending)
            $doc_type_id = (int)$this->request->getPost("vendor_document_type_id");
            $file        = $this->request->getFile("file");
            $has_file    = $file && $file->isValid() && !$file->hasMoved();

            // File is mandatory for guest vendor
            if (!$doc_type_id || !$has_file) {
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
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0775, true);
            }

            $new_name = uniqid("vd_", true) . "." . $file->getExtension();
            $file->move($upload_dir, $new_name);

            // Prepare document data (same style as Vendor_portal::save_document)
            $doc_data = [
                "vendor_id"              => (int)$save_vendor_id,
                "vendor_document_type_id" => $doc_type_id,
                "disk"                   => "local",
                "path"                   => "vendor_documents/vendor_" . $save_vendor_id . "/" . $new_name,
                "original_name"          => $file->getClientName(),
                "mime_type"              => $file->getClientMimeType(),
                "size_bytes"             => $file->getSize(),
                "issued_at"              => $this->request->getPost("issued_at") ?: null,
                "expires_at"             => $this->request->getPost("expires_at") ?: null,
                "uploaded_by"            => $user_id,   // the user we just created
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








            if ($db->transStatus() === false) {
                $err = $db->error();
                throw new \RuntimeException("Transaction failed: " . ($err["message"] ?: "unknown"));
            }

            $db->transCommit();

            echo json_encode([
                "success" => true,
                "message" => "Thank you. Your vendor application has been submitted successfully."
            ]);
            return;
        } catch (\Throwable $e) {

            // rollback if needed
            try {
                $db->transRollback();
            } catch (\Throwable $t) {
            }

            // ✅ map DB unique constraint (race-condition) to field errors
            $msg = $e->getMessage();
            if (stripos($msg, "Duplicate entry") !== false && stripos($msg, "email") !== false) {
                // Heuristic: if users unique triggered => user_email, else vendor email
                $field = (stripos($msg, "users") !== false) ? "user_email" : "email";

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
