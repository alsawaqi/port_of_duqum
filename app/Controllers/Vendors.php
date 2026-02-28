<?php

namespace App\Controllers;

use App\Models\Vendors_model;
use App\Models\Vendor_groups_model;

class Vendors extends Security_Controller
{

    protected $Vendors_model;
    protected $Vendor_groups_model;
    protected $db;

    function __construct()
    {
        parent::__construct();

        $this->access_only_team_members();

        $this->Vendors_model = new Vendors_model();
        $this->Vendor_groups_model = new Vendor_groups_model();
        $this->db = db_connect();
    }


    private function _pickTable(array $candidates): string
    {
        foreach ($candidates as $t) {
            $full = $this->db->prefixTable($t);
            $q = $this->db->query("SHOW TABLES LIKE " . $this->db->escape($full));
            if ($q && $q->getNumRows() > 0) {
                return $full;
            }
        }
        throw new \RuntimeException("Table not found. Tried: " . implode(", ", $candidates));
    }

    function index()
    {
        $this->access_only_vendors_view();

        $view_data = [
            "can_view_vendors" => $this->can_view_vendors(),
            "can_create_vendors" => $this->can_create_vendors(),
            "can_update_vendors" => $this->can_update_vendors(),
            "can_delete_vendors" => $this->can_delete_vendors()
        ];

        return $this->template->rander("vendors/index", $view_data);
    }

    function modal_form()
    {
        $this->validate_submitted_data(array("id" => "numeric"));

        $id = $this->request->getPost("id");
        if ($id) {
            $this->access_only_vendors_update();
        } else {
            $this->access_only_vendors_create();
        }
        $view_data["model_info"] = $this->Vendors_model->get_one($id);

        // vendor groups dropdown
        $groups_dropdown = array("" => "- " . app_lang("select_vendor_group") . " -");
        $groups = $this->Vendor_groups_model->get_details()->getResult();
        foreach ($groups as $g) {
            $groups_dropdown[$g->id] = $g->name . " (" . $g->code . ")";
        }
        $view_data["vendor_groups_dropdown"] = $groups_dropdown;

        // Countries dropdown (use your existing Country_model if you have it)
        // If you already have Country_model, replace this with: $this->Country_model->get_dropdown_list(...)
        $country_table = $this->db->prefixTable('country');
        $countries = $this->db->query("SELECT id, name FROM $country_table WHERE deleted=0 AND is_active=1 ORDER BY name ASC")->getResult();
        $countries_dropdown = array("" => "- " . app_lang("select_country") . " -");
        foreach ($countries as $c) {
            $countries_dropdown[$c->id] = $c->name;
        }
        $view_data["countries_dropdown"] = $countries_dropdown;

        // Regions dropdown (empty initially)
        $view_data["regions_dropdown"] = array("" => "- " . app_lang("select_region") . " -");
        $view_data["cities_dropdown"]  = array("" => "- " . app_lang("select_city") . " -");



        $currency_dropdown = ["" => "- " . app_lang("select_currency") . " -"];

        try {
            $currency_table = $this->db->prefixTable("currencies"); // adjust if your table name differs
            $currencies = $this->db->query("SELECT id, code, name FROM $currency_table WHERE deleted=0 ORDER BY code ASC")->getResult();

            if ($currencies) {
                foreach ($currencies as $c) {
                    // store code (recommended) OR store id depending on your pod_vendors.currency column
                    $currency_dropdown[$c->code] = $c->code . " - " . $c->name;
                }
            }
        } catch (\Throwable $e) {
            // fallback hardcoded if no table
            $currency_dropdown = [
                "" => "- " . app_lang("select_currency") . " -",
                "OMR" => "OMR",
                "AED" => "AED",
                "USD" => "USD",
                "EUR" => "EUR"
            ];
        }

        $view_data["currency_dropdown"] = $currency_dropdown;

        // ✅ Payment terms dropdown (days)
        $view_data["payment_terms_dropdown"] = [
            ""   => "- " . app_lang("select_payment_terms") . " -",
            "45" => "45",
            "90" => "90",
            "180" => "180"
        ];

        return $this->template->view("vendors/modal_form", $view_data);
    }

    public function save()
    {
        $db = $this->db; // use the same connection everywhere

        try {
            $this->validate_submitted_data([
                "id"             => "numeric",
                "vendor_group_id" => "required|numeric",
                "vendor_name"    => "required",
                "email"          => "required|valid_email",


                "address"     => "permit_empty",
                "po_box"      => "permit_empty",
                "postal_code" => "permit_empty",

                // optional
                "country_id"     => "numeric",
                "region_id"      => "numeric",
                "city_id"        => "numeric",

                // login user fields (required on create)
                "user_name"      => "required",
                "user_email"     => "required|valid_email",
                "currency"       => "required",
                "payment_terms"  => "required|in_list[45,90,180]",
            ]);

            $id = $this->request->getPost("id");
            $is_create = !$id;
            if ($is_create) {
                $this->access_only_vendors_create();
            } else {
                $this->access_only_vendors_update();
            }

            // vendor email must be unique (excluding current record on edit)
            $vendor_email = strtolower(trim((string) $this->request->getPost("email")));
            $vendors_table = $db->prefixTable("vendors");
            $existing_vendor = $db->table($vendors_table)
                ->select("id")
                ->where("email", $vendor_email)
                ->where("deleted", 0);
            if (!$is_create) {
                $existing_vendor->where("id !=", (int) $id);
            }
            $existing_vendor = $existing_vendor->get()->getRow();
            if ($existing_vendor) {
                echo json_encode([
                    "success" => false,
                    "message" => app_lang("email_already_exists"),
                    "field" => "email",
                    "errors" => ["email" => app_lang("email_already_exists")]
                ]);
                return;
            }

            if ($is_create) {
                $this->validate_submitted_data([
                    "password" => "required"
                ]);
            }

            $currency = trim((string)$this->request->getPost("currency"));
            $payment_terms = $this->request->getPost("payment_terms");

            $vendor_data["currency"] = $currency !== "" ? $currency : null;
            $vendor_data["payment_terms"] = ($payment_terms !== "" && $payment_terms !== null) ? (int)$payment_terms : null;

            // ✅ optional ids: store NULL instead of 0 (0 can break FK logic)
            $country_id = $this->request->getPost("country_id");
            $region_id  = $this->request->getPost("region_id");
            $city_id    = $this->request->getPost("city_id");

            $vendor_data = [
                "vendor_group_id" => (int) $this->request->getPost("vendor_group_id"),
                "vendor_name"     => $this->request->getPost("vendor_name"),
                "email"           => $vendor_email,

                "country_id"      => $country_id ? (int)$country_id : null,
                "region_id"       => $region_id  ? (int)$region_id  : null,
                "city_id"         => $city_id    ? (int)$city_id    : null,


                // 
                "address"         => $this->request->getPost("address"),
                "po_box"          => $this->request->getPost("po_box"),
                "postal_code"     => $this->request->getPost("postal_code"),


                "currency"        => $currency !== "" ? $currency : null,
                "payment_terms"   => ($payment_terms !== "" && $payment_terms !== null) ? (int)$payment_terms : null,

                // pod_vendors.status has default 'new', but we can still set it
                "status"          => $is_create ? "new" : ($this->request->getPost("status") ?: "new"),
            ];

            if ($is_create) {
                $vendor_data["created_by"] = $this->login_user->id;
            } else {
                $vendor_data["updated_by"] = $this->login_user->id; // exists in pod_vendors
            }

            $vendor_data = clean_data($vendor_data);

            // ---------- TRANSACTION ----------
            $db->transBegin();

            // 1) Save vendor
            $save_vendor_id = $this->Vendors_model->ci_save($vendor_data, $id);
            if (!$save_vendor_id) {
                $err = $db->error();
                throw new \RuntimeException($err["message"] ?: "Vendor save failed.");
            }

            // 2) Create user + pivot (only on create)
            if ($is_create) {
                $user_email = strtolower(trim($this->request->getPost("user_email")));

                // check existing user email (pod_users has deleted=0)
                $existing = $db->table("users")
                    ->select("id")
                    ->where("email", $user_email)
                    ->where("deleted", 0)
                    ->get()
                    ->getRow();

                if ($existing) {
                    echo json_encode([
                        "success" => false,
                        "message" => app_lang("email_already_exists"),
                        "field" => "user_email",
                        "errors" => ["user_email" => app_lang("email_already_exists")]
                    ]);
                    return;
                }

                $password = $this->request->getPost("password");

                // ✅ pod_users required fields: email (NOT NULL), user_type (enum), status (enum), language (NOT NULL)
                $user_data = [
                    "first_name" => $this->request->getPost("user_name"),
                    "last_name"  => "",

                    "email"      => $user_email,
                    "password"   => password_hash($password, PASSWORD_DEFAULT),

                    "user_type"  => "staff",   // enum('staff','client','lead')
                    "is_admin"   => 0,
                    "role_id"    => 0,

                    "status"     => "active",  // enum('active','inactive')
                    "language"   => "",        // NOT NULL in your table
                    "deleted"    => 0,
                ];

                $ok = $db->table("users")->insert(clean_data($user_data));
                if (!$ok) {
                    $err = $db->error();
                    throw new \RuntimeException("User insert error: " . ($err["message"] ?: "unknown"));
                }

                $user_id = $db->insertID();

                // ✅ pod_vendor_users columns: vendor_id, user_id, invited_by, vendor_role_id, is_owner, status, deleted
                $pivot = [
                    "vendor_id"      => (int) $save_vendor_id,
                    "user_id"        => (int) $user_id,
                    "invited_by"     => (int) $this->login_user->id,
                    "vendor_role_id" => 1,     // Owner (seeded)
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

            if ($db->transStatus() === false) {
                $err = $db->error();
                throw new \RuntimeException("Transaction failed: " . ($err["message"] ?: "unknown"));
            }

            $db->transCommit();

            echo json_encode([
                "success" => true,
                "data"    => $this->_row_data($save_vendor_id),
                "id"      => $save_vendor_id,
                "message" => app_lang("record_saved")
            ]);
            return;
        } catch (\Throwable $e) {

            if ($db && $db->transStatus() !== false) {
                // If a transaction is open, roll it back safely
                try {
                    $db->transRollback();
                } catch (\Throwable $t) {
                }
            }

            log_message("error", "Vendor save error: " . $e->getMessage());

            echo json_encode([
                "success" => false,
                "message" => $e->getMessage()
            ]);
            return;
        }
    }


    function list_data()
    {
        $this->access_only_vendors_view();

        $list_data = $this->Vendors_model->get_details()->getResult();
        $result = array();

        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data);
        }

        echo json_encode(array("data" => $result));
    }






    public function update_status()
    {
        $this->access_only_vendors_update();

        $this->validate_submitted_data([
            "id" => "required|numeric",
            "status" => "required"
        ]);

        $id = (int) $this->request->getPost("id");
        $status = strtolower(trim((string)$this->request->getPost("status")));

        $allowed = ["new", "pending_payment", "submitted", "approved", "rejected", "inactive", "active"];
        if (!in_array($status, $allowed, true)) {
            echo json_encode(["success" => false, "message" => "Invalid status: " . $status]);
            return;
        }

        $vendor = $this->Vendors_model->get_one($id);
        if (!$vendor || (int)$vendor->deleted === 1) {
            echo json_encode(["success" => false, "message" => "Vendor not found"]);
            return;
        }

        // ✅ MUST be a variable because ci_save expects reference
        $data = [
            "status" => $status,
            "updated_by" => $this->login_user->id
        ];

        $data = clean_data($data);

        $ok = $this->Vendors_model->ci_save($data, $id);

        if (!$ok) {
            $err = $this->db->error();
            echo json_encode(["success" => false, "message" => $err["message"] ?? "Failed to update"]);
            return;
        }

        echo json_encode(["success" => true, "message" => app_lang("record_saved")]);
    }





    function delete()
    {
        $this->access_only_vendors_delete();

        $this->validate_submitted_data(array("id" => "required|numeric"));
        $id = $this->request->getPost("id");

        if ($this->request->getPost("undo")) {
            if ($this->Vendors_model->delete($id, true)) {
                echo json_encode(array("success" => true, "data" => $this->_row_data($id), "message" => app_lang("record_undone")));
            } else {
                echo json_encode(array("success" => false, "message" => app_lang("error_occurred")));
            }
        } else {
            if ($this->Vendors_model->delete($id)) {
                echo json_encode(array("success" => true, "message" => app_lang("record_deleted")));
            } else {
                echo json_encode(array("success" => false, "message" => app_lang("record_cannot_be_deleted")));
            }
        }
    }

    // AJAX: regions by country
    function get_regions_dropdown_by_country($country_id = 0)
    {
        validate_numeric_value($country_id);

        $regions_table = $this->db->prefixTable('regions');
        $regions = $this->db->query("SELECT id, name FROM $regions_table WHERE deleted=0 AND is_active=1 AND country_id=$country_id ORDER BY name ASC")->getResult();

        $options = "<option value=''>- " . app_lang("select_region") . " -</option>";
        foreach ($regions as $r) {
            $options .= "<option value='{$r->id}'>{$r->name}</option>";
        }
        echo $options;
    }

    // AJAX: cities by region
    function get_cities_dropdown_by_region($region_id = 0)
    {
        validate_numeric_value($region_id);

        $cities_table = $this->db->prefixTable('cities');
        $cities = $this->db->query("SELECT id, name FROM $cities_table WHERE deleted=0 AND is_active=1 AND regions_id=$region_id ORDER BY name ASC")->getResult();

        $options = "<option value=''>- " . app_lang("select_city") . " -</option>";
        foreach ($cities as $c) {
            $options .= "<option value='{$c->id}'>{$c->name}</option>";
        }
        echo $options;
    }

    private function _row_data($id)
    {
        $data = $this->Vendors_model->get_details(array("id" => $id))->getRow();
        return $this->_make_row($data);
    }


    public function details($vendor_id)
    {
        $this->access_only_vendors_view();

        $vendor_id = (int)$vendor_id;
        $vendor = $this->Vendors_model->get_one($vendor_id);

        if (!$vendor || (int)$vendor->deleted === 1) {
            app_redirect("vendors");
        }

        $view_data = [
            "vendor" => $vendor
        ];

        return $this->template->rander("vendors/details", $view_data);
    }


    public function vendor_documents_list_data($vendor_id)
    {
        $this->access_only_vendors_view();
        $vendor_id = (int)$vendor_id;

        // change table name if your prefix differs
        $table = $this->db->prefixTable("vendor_documents");

        $rows = $this->db->table($table)
            ->where("vendor_id", $vendor_id)
            ->where("deleted", 0)
            ->orderBy("id", "DESC")
            ->get()
            ->getResult();

        $result = [];
        foreach ($rows as $r) {
            $fileName = $r->original_name ?: basename($r->path);

            $viewBtn = anchor(
                get_uri("vendors/vendor_document_preview/" . $r->id),
                app_lang("view"),
                ["class" => "btn btn-default btn-sm", "target" => "_blank"]
            );

            $downloadBtn = anchor(
                get_uri("vendors/vendor_document_preview/" . $r->id . "?download=1"),
                app_lang("download"),
                ["class" => "btn btn-default btn-sm", "target" => "_blank"]
            );

            $size = $r->size_bytes ? number_format($r->size_bytes / 1024, 1) . " KB" : "-";

            $result[] = [
                (string)($r->vendor_document_type_id ?? "-"),
                esc($fileName),
                esc($r->issued_at ?? "-"),
                esc($r->expires_at ?? "-"),
                esc($size),
                $viewBtn . " " . $downloadBtn
            ];
        }

        echo json_encode(["data" => $result]);
    }



    public function vendor_document_preview($doc_id)
    {
        $this->access_only_vendors_view();
        $doc_id = (int)$doc_id;

        $table = $this->db->prefixTable("vendor_documents");
        $doc = $this->db->table($table)->where("id", $doc_id)->where("deleted", 0)->get()->getRow();

        if (!$doc) {
            show_404();
        }

        // sanitize
        $relPath = str_replace("\\", "/", (string)$doc->path);
        $relPath = preg_replace("#\.\.+#", "", $relPath);
        $relPath = ltrim($relPath, "/");

        // adjust base folder to match your upload location
        $fullPath = WRITEPATH . "uploads/" . $relPath;
        if (!is_file($fullPath)) {
            // if your files are under public/uploads instead, switch to: FCPATH . "uploads/" . $relPath
            show_404();
        }

        $mime = $doc->mime_type ?: (function_exists("mime_content_type") ? mime_content_type($fullPath) : "application/octet-stream");
        $name = $doc->original_name ?: basename($fullPath);

        $download = (int)($this->request->getGet("download") ?? 0) === 1;
        $inline = !$download && (str_starts_with($mime, "image/") || $mime === "application/pdf");

        return $this->response
            ->setHeader("Content-Type", $mime)
            ->setHeader("Content-Disposition", ($inline ? "inline" : "attachment") . '; filename="' . addslashes($name) . '"')
            ->setBody(file_get_contents($fullPath));
    }


    public function vendor_contacts_list_data($vendor_id)
    {
        $this->access_only_vendors_view();
        $vendor_id = (int)$vendor_id;

        $table = $this->db->prefixTable("vendor_contacts"); // -> pod_vendor_contacts

        $rows = $this->db->table($table)
            ->where("vendor_id", $vendor_id)
            ->where("deleted", 0)
            ->orderBy("is_primary", "DESC")
            ->orderBy("id", "DESC")
            ->get()->getResult();

        $data = [];
        foreach ($rows as $r) {
            $phone = $r->mobile ?: ($r->phone ?: "-");
            $data[] = [
                esc($r->contacts_name ?? "-"),
                esc($r->email ?? "-"),
                esc($phone),
                esc($r->designation ?? "-"),
                esc($r->role ?? "-"),
                $r->is_primary ? "<span class='badge bg-success'>Yes</span>" : "<span class='badge bg-secondary'>No</span>",
                $r->is_active ? "<span class='badge bg-success'>Yes</span>" : "<span class='badge bg-danger'>No</span>",
            ];
        }

        return $this->response->setJSON(["data" => $data]);
    }

    public function vendor_bank_list_data($vendor_id)
    {
        $this->access_only_vendors_view();
        $vendor_id = (int)$vendor_id;

        $table = $this->db->prefixTable("vendor_bank_accounts"); // -> pod_vendor_bank_accounts

        $rows = $this->db->table($table)
            ->where("vendor_id", $vendor_id)
            ->where("deleted", 0)
            ->orderBy("id", "DESC")
            ->get()->getResult();

        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                esc($r->bank_name ?? "-"),
                esc($r->bank_account_no ?? "-"),
                esc($r->iban ?? "-"),
                esc($r->bank_swift_code ?? "-"),
                esc($r->bank_branch ?? "-"),
            ];
        }

        return $this->response->setJSON(["data" => $data]);
    }

    public function vendor_branches_list_data($vendor_id)
    {
        $this->access_only_vendors_view();
        $vendor_id = (int)$vendor_id;

        $table = $this->db->prefixTable("vendor_branches"); // -> pod_vendor_branches

        $rows = $this->db->table($table)
            ->where("vendor_id", $vendor_id)
            ->where("deleted", 0)
            ->orderBy("id", "DESC")
            ->get()->getResult();

        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                esc($r->name ?? "-"),
                esc($r->address ?? "-"),
                esc($r->phone ?? "-"),
                esc($r->email ?? "-"),
            ];
        }

        return $this->response->setJSON(["data" => $data]);
    }

    public function vendor_credentials_list_data($vendor_id)
    {
        $this->access_only_vendors_view();
        $vendor_id = (int)$vendor_id;

        $table = $this->db->prefixTable("vendor_credentials"); // -> pod_vendor_credentials

        $rows = $this->db->table($table)
            ->where("vendor_id", $vendor_id)
            ->where("deleted", 0)
            ->orderBy("id", "DESC")
            ->get()->getResult();

        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                esc($r->type ?? "-"),
                esc($r->number ?? "-"),
                esc($r->issue_date ?? "-"),
                esc($r->expiry_date ?? "-"),
                esc($r->notes ?? "-"),
            ];
        }

        return $this->response->setJSON(["data" => $data]);
    }

    public function vendor_specialties_list_data($vendor_id)
    {
        $this->access_only_vendors_view();
        $this->access_only_vendor_specialties_view();
        $vendor_id = (int)$vendor_id;

        // base tables (with prefix -> pod_vendor_* )
        $specTable = $this->db->prefixTable("vendor_specialties");      // pod_vendor_specialties
        $catTable  = $this->db->prefixTable("vendor_categories");       // pod_vendor_categories
        $subTable  = $this->db->prefixTable("vendor_sub_categories");   // pod_vendor_sub_categories

        $rows = $this->db->table($specTable . " AS s")
            ->select("
            s.*,
            c.name     AS category_name,
            sc.name    AS sub_category_name
        ")
            ->join($catTable . " AS c", "c.id = s.vendor_category_id", "left")
            ->join($subTable . " AS sc", "sc.id = s.vendor_sub_category_id", "left")
            ->where("s.vendor_id", $vendor_id)
            ->where("s.deleted", 0)
            ->orderBy("s.id", "DESC")
            ->get()
            ->getResult();

        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                esc($r->category_name        ?? "-"),   // Category
                esc($r->sub_category_name    ?? "-"),   // Sub-category
                esc($r->specialty_description ?? "-"),  // Description (from specialties table)
            ];
        }

        return $this->response->setJSON(["data" => $data]);
    }




    private function _not_implemented($name)
    {
        echo json_encode(["data" => [], "debug" => "Missing implementation: {$name}"]);
    }



    public function specialties_filter()
    {
        $this->access_only_vendor_specialties_view();

        // Load all categories
        $catsTable = $this->db->prefixTable("vendor_categories"); // pod_vendor_categories
        $rows = $this->db->table($catsTable)
            ->where("deleted", 0)
            ->orderBy("name", "ASC")
            ->get()
            ->getResult();

        $categories_dropdown = ["" => "- " . app_lang("select_category") . " -"];
        foreach ($rows as $row) {
            $categories_dropdown[$row->id] = $row->name;
        }

        $view_data = [
            "categories_dropdown" => $categories_dropdown,
            "can_filter_vendor_specialties" => $this->can_filter_vendor_specialties(),
        ];

        return $this->template->rander("vendors/specialties_filter", $view_data);
    }



    public function get_vendor_sub_categories($category_id = 0)
    {
        $this->access_only_vendor_specialties_filter();
        validate_numeric_value($category_id);

        $subTable = $this->db->prefixTable("vendor_sub_categories"); // pod_vendor_sub_categories

        $rows = $this->db->table($subTable)
            ->where("deleted", 0)
            ->where("vendor_category_id", (int)$category_id)
            ->orderBy("name", "ASC")
            ->get()
            ->getResult();

        $options = "<option value=''>- " . app_lang("select_sub_category") . " -</option>";
        foreach ($rows as $r) {
            $options .= "<option value='" . (int)$r->id . "'>" . esc($r->name) . "</option>";
        }

        echo $options;
    }



    public function vendor_specialties_filter_list_data()
    {
        $this->access_only_vendor_specialties_view();

        $can_view_vendors = $this->can_view_vendors();
        $can_filter = $this->can_filter_vendor_specialties();

        // Accept both POST (DataTables) and GET (if needed)
        $category_id     = (int) ($this->request->getPost("category_id")     ?? $this->request->getGet("category_id")     ?? 0);
        $sub_category_id = (int) ($this->request->getPost("sub_category_id") ?? $this->request->getGet("sub_category_id") ?? 0);

        $vsTable  = $this->db->prefixTable("vendor_specialties");    // pod_vendor_specialties
        $vTable   = $this->db->prefixTable("vendors");               // pod_vendors
        $catTable = $this->db->prefixTable("vendor_categories");     // pod_vendor_categories
        $subTable = $this->db->prefixTable("vendor_sub_categories"); // pod_vendor_sub_categories

        $builder = $this->db->table("$vsTable AS vs")
            ->select("
            vs.id,
            vs.vendor_id,
            vs.specialty_type,
            vs.specialty_name,
            vs.specialty_description,
            v.vendor_name,
            v.email,
            c.name  AS category_name,
            sc.name AS sub_category_name
        ")
            ->join("$vTable   AS v",  "v.id  = vs.vendor_id",              "left")
            ->join("$catTable AS c",  "c.id  = vs.vendor_category_id",     "left")
            ->join("$subTable AS sc", "sc.id = vs.vendor_sub_category_id", "left")
            ->where("vs.deleted", 0)
            ->where("v.deleted", 0);

        if ($can_filter && $category_id) {
            $builder->where("vs.vendor_category_id", $category_id);
        }

        if ($can_filter && $sub_category_id) {
            $builder->where("vs.vendor_sub_category_id", $sub_category_id);
        }

        $rows = $builder->orderBy("v.vendor_name", "ASC")->get()->getResult();

        $data = [];
        foreach ($rows as $r) {

            // Link to vendor details
            $vendorLabel = esc($r->vendor_name ?? "-");
            $vendorLink = $vendorLabel;
            if ($can_view_vendors) {
                $vendorLink = anchor(
                    get_uri("vendors/details/" . (int)$r->vendor_id),
                    $vendorLabel,
                    ["title" => app_lang("vendor_details"), "target" => "_blank"]
                );
            }

            $data[] = [
                $vendorLink,
                esc($r->email ?? "-"),
                esc($r->category_name ?? "-"),
                esc($r->sub_category_name ?? "-"),
                esc($r->specialty_name ?? "-"),
                esc($r->specialty_type ?? "-"),
                esc($r->specialty_description ?? "-"),
            ];
        }

        return $this->response->setJSON(["data" => $data]);
    }





    private function _make_row($data)
    {
        $groupLabel = ($data->vendor_group_name ?? "-");
        if (!empty($data->vendor_group_code)) {
            $groupLabel .= " (" . $data->vendor_group_code . ")";
        }

        // ✅ build location as spans/badges
        $chips = [];

        if (!empty($data->country_name)) {
            $chips[] = "<span class='badge bg-light text-dark mr5'>" . esc($data->country_name) . "</span>";
        }
        if (!empty($data->region_name)) {
            $chips[] = "<span class='badge bg-light text-dark mr5'>" . esc($data->region_name) . "</span>";
        }
        if (!empty($data->city_name)) {
            $chips[] = "<span class='badge bg-light text-dark mr5'>" . esc($data->city_name) . "</span>";
        }

        $locationCell = count($chips)
            ? "<div class='mt5'>" . implode("", $chips) . "</div>"
            : "<span class='text-off'>-</span>";

        $can_view = $this->can_view_vendors();
        $can_update = $this->can_update_vendors();
        $can_delete = $this->can_delete_vendors();

        // status dropdown (same as yours)
        $allowedStatuses = ["new", "pending_payment", "submitted", "approved", "rejected", "inactive", "active"];
        if ($can_update) {
            $statusSelect = "<select class='form-select form-select-sm js-vendor-status' data-id='{$data->id}'>";
            foreach ($allowedStatuses as $st) {
                $selected = ($data->status === $st) ? "selected" : "";
                $statusSelect .= "<option value='{$st}' {$selected}>{$st}</option>";
            }
            $statusSelect .= "</select>";
        } else {
            $statusSelect = "<span class='badge bg-secondary'>" . esc($data->status ?? "-") . "</span>";
        }

        // ✅ FIX: your details route should match your controller: vendors/details/{id}
        $details = "";
        if ($can_view) {
            $details = anchor(
                get_uri("vendors/details/" . $data->id),
                "<i data-feather='eye' class='icon-16'></i>",
                ["title" => "Vendor details", "class" => "mr10"]
            );
        }

        $actions = $details;
        if ($can_update) {
            $actions .= modal_anchor(get_uri("vendors/modal_form"), "<i data-feather='edit' class='icon-16'></i>", [
                "class" => "edit",
                "title" => app_lang("edit"),
                "data-post-id" => $data->id
            ]);
        }
        if ($can_delete) {
            $actions .= js_anchor("<i data-feather='x' class='icon-16'></i>", [
                "title" => app_lang("delete"),
                "class" => "delete",
                "data-id" => $data->id,
                "data-action-url" => get_uri("vendors/delete"),
                "data-action" => "delete"
            ]);
        }

        return [
            $groupLabel,
            esc($data->vendor_name ?? "-"),
            esc($data->email ?? "-"),
            $locationCell,
            $statusSelect,
            $actions
        ];
    }
}
