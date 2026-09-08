<?php

namespace App\Controllers;

use App\Libraries\Vendor_contact_access;
use App\Libraries\Payments\Vendor_billing_service;
use App\Models\Vendors_model;
use App\Models\Vendor_groups_model;
use App\Models\Vendor_grades_model;

class Vendors extends Security_Controller
{

    protected $Vendors_model;
    protected $Vendor_groups_model;
    protected $Vendor_grades_model;
    protected $Vendor_contact_access;
    protected $db;
    private $vendor_grades_dropdown_cache = null;

    function __construct()
    {
        parent::__construct();

        $this->access_only_team_members();

        $this->Vendors_model = new Vendors_model();
        $this->Vendor_groups_model = new Vendor_groups_model();
        $this->Vendor_grades_model = new Vendor_grades_model();
        $this->db = db_connect();
        $this->Vendor_contact_access = new Vendor_contact_access($this->db);
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
        $this->_access_only_vendors_review_view();

        $view_data = [
            "can_view_vendors" => $this->_can_view_vendors_for_review(),
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

        $view_data["vendor_grades_dropdown"] = $this->_get_vendor_grades_dropdown();

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

    private function _get_vendor_grades_dropdown(): array
    {
        if ($this->vendor_grades_dropdown_cache !== null) {
            return $this->vendor_grades_dropdown_cache;
        }

        $dropdown = ["" => "- " . app_lang("select_vendor_grade") . " -"];
        $grades = $this->Vendor_grades_model->get_details()->getResult();
        foreach ($grades as $grade) {
            if ((int)($grade->is_active ?? 0) !== 1) {
                continue;
            }

            $dropdown[$grade->id] = vendor_grade_label($grade->name ?? "", $grade->code ?? "");
        }

        $this->vendor_grades_dropdown_cache = $dropdown;
        return $dropdown;
    }

    public function save()
    {
        $db = $this->db; // use the same connection everywhere

        try {
            $id = $this->request->getPost("id");
            $is_create = !$id;

            $this->validate_submitted_data([
                "id"             => "numeric",
                "vendor_group_id" => "required|numeric",
                "vendor_grade_id" => "permit_empty|numeric",
                "vendor_name"    => "required",
                "email"          => "required|valid_email",
                "cr_number"      => $is_create ? "required" : "permit_empty",


                "address"     => "permit_empty",
                "po_box"      => "permit_empty",
                "postal_code" => "permit_empty",

                // optional
                "country_id"     => "permit_empty|numeric",
                "region_id"      => "permit_empty|numeric",
                "city_id"        => "permit_empty|numeric",

                "currency"       => "required",
                "payment_terms"  => "required|in_list[45,90,180]",
            ]);

            if ($is_create) {
                $this->access_only_vendors_create();
            } else {
                $this->access_only_vendors_update();
            }

            // Company email may be shared by multiple CR records.
            $vendor_email = strtolower(trim((string) $this->request->getPost("email")));
            $cr_number = trim((string)$this->request->getPost("cr_number"));
            $vendors_table = $db->prefixTable("vendors");

            if ($cr_number !== "") {
                $existing_cr_builder = $db->table($vendors_table)
                    ->select("id")
                    ->where("cr_number", $cr_number)
                    ->where("deleted", 0);
                if (!$is_create) {
                    $existing_cr_builder->where("id !=", (int)$id);
                }

                if ($existing_cr_builder->get()->getRow()) {
                    echo json_encode([
                        "success" => false,
                        "message" => app_lang("cr_number_already_exists"),
                        "field"   => "cr_number",
                        "errors"  => ["cr_number" => app_lang("cr_number_already_exists")]
                    ]);
                    return;
                }
            }

            $user_email = "";
            $existing_user = null;
            if ($is_create) {
                $this->validate_submitted_data([
                    "user_email" => "required|valid_email",
                ]);

                $user_email = strtolower(trim((string)$this->request->getPost("user_email")));
                $existing_user = $db->table("users")
                    ->select("id, user_type, status, disable_login, deleted")
                    ->where("email", $user_email)
                    ->orderBy("deleted", "ASC")
                    ->orderBy("id", "ASC")
                    ->get()
                    ->getRow();

                if ($existing_user && ($existing_user->user_type ?? "") !== "staff") {
                    echo json_encode([
                        "success" => false,
                        "message" => app_lang("vendor_email_belongs_to_non_staff_user"),
                        "field"   => "user_email",
                        "errors"  => ["user_email" => app_lang("vendor_email_belongs_to_non_staff_user")]
                    ]);
                    return;
                }

                if ($existing_user
                    && ((int)($existing_user->deleted ?? 0) === 1
                        || (string)($existing_user->status ?? "") !== "active"
                        || (int)($existing_user->disable_login ?? 0) === 1)
                ) {
                    echo json_encode([
                        "success" => false,
                        "message" => "This staff account is inactive. Restore it before linking it to a vendor CR.",
                        "field"   => "user_email",
                        "errors"  => ["user_email" => "This staff account is inactive. Restore it before linking it to a vendor CR."]
                    ]);
                    return;
                }

                if (!$existing_user) {
                    $this->validate_submitted_data([
                        "user_name" => "required",
                        "password"  => "required",
                    ]);

                    $policyErrors = $this->Users_model->password_policy_errors(
                        (string) $this->request->getPost("password")
                    );
                    if ($policyErrors) {
                        return $this->response->setStatusCode(422)->setJSON([
                            "success" => false,
                            "message" => implode(" ", $policyErrors),
                            "field" => "password",
                            "errors" => ["password" => implode(" ", $policyErrors)],
                        ]);
                    }
                }
            }

            $currency = trim((string)$this->request->getPost("currency"));
            $payment_terms = $this->request->getPost("payment_terms");
            $current_vendor = $is_create ? null : $this->Vendors_model->get_one((int)$id);
            if (!$is_create) {
                $postedStatus = trim((string) $this->request->getPost('status'));
                if ($postedStatus !== '' && $postedStatus !== (string) $current_vendor->status) {
                    return $this->response->setStatusCode(422)->setJSON(['success' => false,
                        'message' => 'Use the vendor status review action to change status. Payment must be verified before approval.']);
                }
                $feeRequest = (new Vendor_billing_service($db))->latestOpen((int) $id);
                if ($feeRequest && (int) $this->request->getPost('vendor_group_id') !== (int) $feeRequest->vendor_group_id) {
                    return $this->response->setStatusCode(409)->setJSON(['success' => false,
                        'message' => 'The vendor group is fixed while its fee request is open. Please reconcile the fee request first.']);
                }
            }

            $vendor_data["currency"] = $currency !== "" ? $currency : null;
            $vendor_data["payment_terms"] = ($payment_terms !== "" && $payment_terms !== null) ? (int)$payment_terms : null;

            // ✅ optional ids: store NULL instead of 0 (0 can break FK logic)
            $country_id = $this->request->getPost("country_id");
            $region_id  = $this->request->getPost("region_id");
            $city_id    = $this->request->getPost("city_id");

            $vendor_data = [
                "vendor_group_id" => (int) $this->request->getPost("vendor_group_id"),
                "vendor_grade_id" => $this->request->getPost("vendor_grade_id") ? (int) $this->request->getPost("vendor_grade_id") : null,
                "vendor_name"     => $this->request->getPost("vendor_name"),
                "email"           => $vendor_email,
                "cr_number"       => $cr_number !== "" ? $cr_number : null,

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
                "status"          => $is_create
                    ? "new"
                    : ($this->request->getPost("status") ?: ($current_vendor->status ?? "new")),
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

            // 2) Reuse/create the login identity and link it to this CR.
            if ($is_create) {
                if ($existing_user) {
                    $user_id = (int)$existing_user->id;
                } else {
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

                    $user_id = (int)$db->insertID();
                }

                // ✅ pod_vendor_users columns: vendor_id, user_id, invited_by, vendor_role_id, is_owner, status, deleted
                $existing_pivot = $db->table("vendor_users")
                    ->select("id")
                    ->where("vendor_id", (int)$save_vendor_id)
                    ->where("user_id", (int)$user_id)
                    ->get()
                    ->getRow();

                $pivot_data = clean_data([
                    "vendor_id"      => (int) $save_vendor_id,
                    "user_id"        => (int) $user_id,
                    "invited_by"     => (int) $this->login_user->id,
                    "vendor_role_id" => 1,     // Owner (seeded)
                    "is_owner"       => 1,
                    "status"         => "active",
                    "deleted"        => 0
                ]);

                if ($existing_pivot) {
                    $ok = $db->table("vendor_users")
                        ->where("id", (int)$existing_pivot->id)
                        ->update($pivot_data);
                } else {
                    $ok = $db->table("vendor_users")->insert($pivot_data);
                }

                if (!$ok) {
                    $err = $db->error();
                    throw new \RuntimeException("Vendor user pivot save error: " . ($err["message"] ?: "unknown"));
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

            $message = $e->getMessage();
            if (stripos($message, "Duplicate entry") !== false
                && (stripos($message, "cr_number") !== false || stripos($message, "cr identity") !== false)
            ) {
                echo json_encode([
                    "success" => false,
                    "message" => app_lang("cr_number_already_exists"),
                    "field"   => "cr_number",
                    "errors"  => ["cr_number" => app_lang("cr_number_already_exists")]
                ]);
                return;
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
        $this->_access_only_vendors_review_view();

        $list_data = $this->Vendors_model->get_details()->getResult();
        $result = array();

        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data);
        }

        echo json_encode(array("data" => $result));
    }






    public function update_status()
    {
        $this->_access_only_vendor_status_update();

        $this->validate_submitted_data([
            "id" => "required|numeric",
            "status" => "required"
        ]);

        $id = (int) $this->request->getPost("id");
        $status = strtolower(trim((string)$this->request->getPost("status")));

        $allowed = vendor_status_options();
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
        $from_status = (string)($vendor->status ?? "");
        if (!$this->can_update_vendors() && !$this->_is_procurement_vendor_reviewer_status_allowed($status, $from_status)) {
            echo json_encode(["success" => false, "message" => app_lang("forbidden")]);
            return;
        }

        $this->db->transBegin();
        try {
            $vendorsTable = $this->db->prefixTable('vendors');
            $vendor = $this->db->query("SELECT * FROM {$vendorsTable} WHERE id=? AND deleted=0 FOR UPDATE", [$id])->getRow();
            if (!$vendor) {
                throw new \DomainException('Vendor not found.');
            }
            $from_status = (string) $vendor->status;
            if ($from_status === $status) {
                $this->db->transCommit();
                return $this->response->setJSON(['success' => true, 'message' => app_lang('record_saved')]);
            }
            $dates = (new Vendor_billing_service($this->db))->review($vendor, $status, (int) $this->login_user->id);
            $data = ['status' => $status, 'updated_by' => $this->login_user->id] + $dates;

        if ($status === vendor_blocked_status()) {
            $data["blocked_reason"] = $data["blocked_reason"] ?? null;
            $data["blocked_by"] = $this->login_user->id;
            $data["blocked_at"] = get_current_utc_time();
        } elseif ($from_status === vendor_blocked_status()) {
            $data["blocked_reason"] = null;
            $data["blocked_by"] = null;
            $data["blocked_at"] = null;
        }

        $data = clean_data($data);

        $ok = $this->Vendors_model->ci_save($data, $id);

            if (!$ok || $this->db->transStatus() === false) {
                throw new \RuntimeException('Vendor status update failed.');
            }
            $this->_record_vendor_status_history($id, $from_status, $status);
            if ($this->db->transStatus() === false) {
                throw new \RuntimeException('Vendor status audit failed.');
            }
            $this->db->transCommit();
            return $this->response->setJSON(['success' => true, 'message' => app_lang('record_saved')]);
        } catch (\DomainException $e) {
            $this->db->transRollback();
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Vendor status/payment review failed: {class}', ['class' => get_class($e)]);
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => app_lang('error_occurred')]);
        }
    }

    public function update_grade()
    {
        $this->access_only_vendors_update();

        $this->validate_submitted_data([
            "id" => "required|numeric",
            "vendor_grade_id" => "permit_empty|numeric"
        ]);

        $id = (int)$this->request->getPost("id");
        $vendor = $this->Vendors_model->get_one($id);
        if (!$vendor || (int)$vendor->deleted === 1) {
            echo json_encode(["success" => false, "message" => app_lang("record_not_found")]);
            return;
        }

        $grade_id = $this->request->getPost("vendor_grade_id");
        $grade_id = $grade_id ? (int)$grade_id : null;

        if ($grade_id) {
            $grade = $this->Vendor_grades_model->get_one($grade_id);
            if (!$grade || (int)$grade->deleted === 1 || (int)$grade->is_active !== 1) {
                echo json_encode(["success" => false, "message" => app_lang("vendor_grade_not_available")]);
                return;
            }
        }

        $data = clean_data([
            "vendor_grade_id" => $grade_id,
            "updated_by" => $this->login_user->id
        ]);

        if ($this->Vendors_model->ci_save($data, $id)) {
            echo json_encode([
                "success" => true,
                "data" => $this->_row_data($id),
                "id" => $id,
                "message" => app_lang("record_saved")
            ]);
        } else {
            echo json_encode(["success" => false, "message" => app_lang("error_occurred")]);
        }
    }

    public function block_modal_form()
    {
        $this->access_only_vendors_update();
        $this->validate_submitted_data(["id" => "required|numeric"]);

        $id = (int)$this->request->getPost("id");
        $vendor = $this->Vendors_model->get_one($id);
        if (!$vendor || (int)$vendor->deleted === 1) {
            show_404();
        }

        return $this->template->view("vendors/block_modal_form", ["vendor" => $vendor]);
    }

    public function block()
    {
        $this->access_only_vendors_update();

        $this->validate_submitted_data([
            "id" => "required|numeric",
            "reason" => "required"
        ]);

        $id = (int)$this->request->getPost("id");
        $reason = trim((string)$this->request->getPost("reason"));
        $vendor = $this->Vendors_model->get_one($id);
        if (!$vendor || (int)$vendor->deleted === 1) {
            echo json_encode(["success" => false, "message" => app_lang("record_not_found")]);
            return;
        }

        $from_status = (string)($vendor->status ?? "");
        $to_status = vendor_blocked_status();
        $data = clean_data([
            "status" => $to_status,
            "blocked_reason" => $reason,
            "blocked_by" => $this->login_user->id,
            "blocked_at" => get_current_utc_time(),
            "updated_by" => $this->login_user->id
        ]);

        if (!$this->Vendors_model->ci_save($data, $id)) {
            echo json_encode(["success" => false, "message" => app_lang("error_occurred")]);
            return;
        }

        $this->_record_vendor_status_history($id, $from_status, $to_status, $reason);

        echo json_encode([
            "success" => true,
            "data" => $this->_row_data($id),
            "id" => $id,
            "message" => app_lang("vendor_blocked_successfully")
        ]);
    }

    public function unblock()
    {
        $this->access_only_vendors_update();
        $this->validate_submitted_data(["id" => "required|numeric"]);

        $id = (int)$this->request->getPost("id");
        $vendor = $this->Vendors_model->get_one($id);
        if (!$vendor || (int)$vendor->deleted === 1) {
            echo json_encode(["success" => false, "message" => app_lang("record_not_found")]);
            return;
        }

        $from_status = (string)($vendor->status ?? "");
        if ($from_status !== vendor_blocked_status()) {
            echo json_encode(["success" => false, "message" => app_lang("vendor_is_not_blocked")]);
            return;
        }

        $to_status = $this->_last_status_before_block($id);
        $data = clean_data([
            "status" => $to_status,
            "blocked_reason" => null,
            "blocked_by" => null,
            "blocked_at" => null,
            "updated_by" => $this->login_user->id
        ]);

        // Unblocking restores recorded access only. Registration dates are
        // established exclusively by the paid registration/renewal review.

        if (!$this->Vendors_model->ci_save($data, $id)) {
            echo json_encode(["success" => false, "message" => app_lang("error_occurred")]);
            return;
        }

        $this->_record_vendor_status_history($id, $from_status, $to_status, app_lang("vendor_unblocked"));

        echo json_encode([
            "success" => true,
            "data" => $this->_row_data($id),
            "id" => $id,
            "message" => app_lang("vendor_unblocked_successfully")
        ]);
    }

    private function _last_status_before_block(int $vendor_id): string
    {
        $history_table = $this->db->prefixTable("vendor_status_histories");
        $row = $this->db->query(
            "SELECT from_status
             FROM $history_table
             WHERE vendor_id=? AND deleted=0 AND to_status=?
             ORDER BY COALESCE(action_at, created_at) DESC, id DESC
             LIMIT 1",
            [$vendor_id, vendor_blocked_status()]
        )->getRow();

        $previous = strtolower(trim((string)($row->from_status ?? "")));
        if ($previous && in_array($previous, vendor_status_options(), true) && $previous !== vendor_blocked_status()) {
            return $previous;
        }

        // Missing block history cannot establish that registration was approved.
        return "new";
    }


    private function _calculate_vendor_valid_to($vendor): ?string
    {
        $group_id = (int)($vendor->vendor_group_id ?? 0);
        if (!$group_id) {
            return null;
        }

        $group = $this->Vendor_groups_model->get_one($group_id);
        $days = (int)($group->default_validity_days ?? 0);
        if ($days <= 0) {
            return null;
        }

        return date("Y-m-d", strtotime("+" . $days . " days"));
    }

    private function _record_vendor_status_history(int $vendor_id, string $from_status, string $to_status, string $reason = ""): void
    {
        if ($from_status === $to_status) {
            return;
        }

        $action_map = [
            "submitted" => "submit",
            "approved" => "approve",
            "rejected" => "reject",
            "revise" => "revise",
            vendor_blocked_status() => "block",
        ];

        $action = $action_map[$to_status] ?? null;
        if ($from_status === vendor_blocked_status() && $to_status !== vendor_blocked_status()) {
            $action = "unblock";
        }

        $history = [
            "vendor_id" => $vendor_id,
            "from_status" => $from_status ?: null,
            "to_status" => $to_status,
            "action" => $action,
            "reason" => $reason ?: null,
            "action_by" => $this->login_user->id ?? null,
            "action_at" => get_current_utc_time(),
            "created_at" => get_current_utc_time(),
            "updated_at" => get_current_utc_time(),
            "deleted" => 0,
        ];

        $this->db->table($this->db->prefixTable("vendor_status_histories"))->insert(clean_data($history));
    }

    private function _is_procurement_vendor_reviewer(): bool
    {
        return $this->has_active_tender_assignment("tender_procurement_users");
    }

    private function _can_view_vendors_for_review(): bool
    {
        return $this->can_view_vendors() || $this->_is_procurement_vendor_reviewer();
    }

    private function _can_update_vendor_review_status(): bool
    {
        return $this->can_update_vendors() || $this->_is_procurement_vendor_reviewer();
    }

    private function _access_only_vendors_review_view(): void
    {
        if (!$this->_can_view_vendors_for_review()) {
            app_redirect("forbidden");
            exit;
        }
    }

    private function _access_only_vendor_status_update(): void
    {
        if (!$this->_can_update_vendor_review_status()) {
            app_redirect("forbidden");
            exit;
        }
    }

    private function _access_only_vendor_specialties_review_view(): void
    {
        if ($this->_is_procurement_vendor_reviewer()) {
            return;
        }

        $this->access_only_vendor_specialties_view();
    }

    private function _is_procurement_vendor_reviewer_status_allowed(string $status, string $from_status): bool
    {
        if ($status === $from_status) {
            return true;
        }

        return in_array($status, ["approved", "rejected", "revise"], true);
    }

    private function _vendor_status_dropdown_options(string $current_status, bool $can_full_update): array
    {
        $statuses = $can_full_update ? vendor_status_options() : ["approved", "rejected", "revise"];
        $current_status = strtolower(trim($current_status));

        if ($current_status && !in_array($current_status, $statuses, true)) {
            array_unshift($statuses, $current_status);
        }

        return array_values(array_unique($statuses));
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
        $this->_access_only_vendors_review_view();

        $vendor_id = (int)$vendor_id;
        $vendor = $this->Vendors_model->get_details(["id" => $vendor_id])->getRow();

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
        $this->_access_only_vendors_review_view();
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
        $this->_access_only_vendors_review_view();
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
        $this->_access_only_vendors_review_view();
        $vendor_id = (int)$vendor_id;

        $table = $this->db->prefixTable("vendor_contacts"); // -> pod_vendor_contacts
        $vendorUsers = $this->db->prefixTable("vendor_users");
        $users = $this->db->prefixTable("users");

        $rows = $this->db->table($table . " AS contacts")
            ->select(
                "contacts.*, vendor_memberships.status AS portal_access_status,"
                . " vendor_memberships.is_owner AS portal_is_owner,"
                . " users.status AS account_status, users.disable_login"
            )
            ->join(
                $vendorUsers . " AS vendor_memberships",
                "vendor_memberships.vendor_id = contacts.vendor_id"
                    . " AND vendor_memberships.user_id = contacts.user_id"
                    . " AND vendor_memberships.deleted = 0",
                "left"
            )
            ->join($users . " AS users", "users.id = contacts.user_id AND users.deleted = 0", "left")
            ->where("contacts.vendor_id", $vendor_id)
            ->where("contacts.deleted", 0)
            ->orderBy("contacts.is_primary", "DESC")
            ->orderBy("contacts.id", "DESC")
            ->get()->getResult();

        $data = [];
        foreach ($rows as $r) {
            $phone = $r->mobile ?: ($r->phone ?: "-");
            $accessStatus = strtolower((string) ($r->portal_access_status ?? ""));
            if ($accessStatus === "active") {
                $portalAccess = "<span class='badge bg-success'>Portal active</span>";
            } elseif ($accessStatus === "invited"
                && ((string) ($r->account_status ?? "") !== "active" || (int) ($r->disable_login ?? 0) === 1)
            ) {
                $portalAccess = "<span class='badge bg-warning text-dark'>Vendor password setup required</span>";
            } elseif ($accessStatus === "invited") {
                $portalAccess = "<span class='badge bg-warning text-dark'>Access activation pending</span>";
            } elseif ($accessStatus === "suspended") {
                $portalAccess = "<span class='badge bg-secondary'>Portal suspended</span>";
            } elseif ((string) $r->status === "approved") {
                $portalAccess = "<span class='badge bg-secondary'>Not provisioned</span>";
            } else {
                $portalAccess = "<span class='badge bg-light text-dark'>Awaiting approval</span>";
            }

            $actions = "";
            if ($this->can_approve_vendor_update_requests()
                && (string) $r->status === "approved"
                && (int) $r->is_active === 1
                && $accessStatus !== "active"
                && !empty($r->user_id)
                && (string) ($r->account_status ?? "") === "active"
                && (int) ($r->disable_login ?? 0) === 0
            ) {
                $title = $accessStatus === "invited"
                    ? "Activate portal access"
                    : "Reactivate portal access";
                $actions = ajax_anchor(
                    get_uri("vendors/provision_vendor_contact_access"),
                    "<i data-feather='user-check' class='icon-16'></i>",
                    [
                        "class" => "btn btn-default btn-sm spinning-btn",
                        "title" => $title,
                        "data-post-id" => (int) $r->id,
                        "data-reload-on-success" => true,
                        "data-show-response" => true,
                    ]
                );
            }

            $data[] = [
                esc($r->contacts_name ?? "-"),
                esc($r->email ?? "-"),
                esc($phone),
                esc($r->designation ?? "-"),
                esc($r->role ?? "-"),
                $r->is_primary ? "<span class='badge bg-success'>Yes</span>" : "<span class='badge bg-secondary'>No</span>",
                $r->is_active ? "<span class='badge bg-success'>Yes</span>" : "<span class='badge bg-danger'>No</span>",
                $portalAccess,
                $actions,
            ];
        }

        return $this->response->setJSON(["data" => $data]);
    }

    public function provision_vendor_contact_access()
    {
        if (strtolower($this->request->getMethod()) !== "post") {
            return $this->response
                ->setStatusCode(405)
                ->setHeader("Allow", "POST")
                ->setJSON([
                    "success" => false,
                    "message" => "This action requires a POST request.",
                ]);
        }

        $this->access_only_vendor_update_requests_approve();
        $this->validate_submitted_data(["id" => "required|numeric"]);

        $contactId = (int) $this->request->getPost("id");
        $contact = $this->db->table($this->db->prefixTable("vendor_contacts") . " AS contacts")
            ->select("contacts.*")
            ->join(
                $this->db->prefixTable("vendors") . " AS vendors",
                "vendors.id = contacts.vendor_id AND vendors.deleted = 0",
                "inner"
            )
            ->where("contacts.id", $contactId)
            ->where("contacts.deleted", 0)
            ->get(1)
            ->getRow();

        if (!$contact || (string) $contact->status !== "approved" || !(int) $contact->is_active) {
            return $this->response->setJSON([
                "success" => false,
                "message" => "Only an approved, active vendor contact can be provisioned.",
            ]);
        }

        $transactionStarted = false;
        try {
            $this->db->transBegin();
            $transactionStarted = true;
            $this->Vendor_contact_access->approveContact(
                $contactId,
                (int) ($this->login_user->id ?? 0)
            );
            if ($this->db->transStatus() === false) {
                throw new \RuntimeException("Unable to provision the vendor contact access.");
            }
            $this->db->transCommit();
            $transactionStarted = false;

            return $this->response->setJSON(["success" => true, "message" => "Vendor contact portal access has been synchronized."]);
        } catch (\Throwable $e) {
            if ($transactionStarted) {
                $this->db->transRollback();
            }
            log_message("error", "VENDOR CONTACT PROVISIONING FAILED: " . $e->getMessage());
            $message = $e instanceof \CodeIgniter\Database\Exceptions\DatabaseException
                ? app_lang("error_occurred")
                : ($e instanceof \RuntimeException ? $e->getMessage() : app_lang("error_occurred"));

            return $this->response->setJSON([
                "success" => false,
                "message" => $message,
            ]);
        }
    }

    public function vendor_bank_list_data($vendor_id)
    {
        $this->_access_only_vendors_review_view();
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
        $this->_access_only_vendors_review_view();
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
        $this->_access_only_vendors_review_view();
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
        $this->_access_only_vendors_review_view();
        $this->_access_only_vendor_specialties_review_view();
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

        $can_view = $this->_can_view_vendors_for_review();
        $can_update = $this->can_update_vendors();
        $can_update_status = $this->_can_update_vendor_review_status();
        $can_delete = $this->can_delete_vendors();

        if ($can_update) {
            $gradeSelect = "<select class='form-select form-select-sm js-vendor-grade' data-id='{$data->id}' aria-label='" . esc(app_lang("vendor_grade")) . "'>";
            foreach ($this->_get_vendor_grades_dropdown() as $grade_id => $label) {
                $selected = ((string)($data->vendor_grade_id ?? "") === (string)$grade_id) ? "selected" : "";
                $gradeSelect .= "<option value='" . esc($grade_id) . "' {$selected}>" . esc($label) . "</option>";
            }
            $gradeSelect .= "</select>";
        } else {
            $gradeSelect = "<span class='badge bg-light text-dark'>" . esc(vendor_grade_label($data->vendor_grade_name ?? "", $data->vendor_grade_code ?? "")) . "</span>";
        }

        // status dropdown (same as yours)
        $allowedStatuses = $this->_vendor_status_dropdown_options((string)($data->status ?? ""), $can_update);
        if ($can_update_status) {
            $statusSelect = "<select class='form-select form-select-sm js-vendor-status' data-id='{$data->id}' aria-label='" . esc(app_lang("status")) . "'>";
            foreach ($allowedStatuses as $st) {
                $selected = ($data->status === $st) ? "selected" : "";
                $statusLabel = ucwords(str_replace("_", " ", (string)$st));
                $statusSelect .= "<option value='" . esc($st) . "' {$selected}>" . esc($statusLabel) . "</option>";
            }
            $statusSelect .= "</select>";
        } else {
            $statusLabel = ucwords(str_replace("_", " ", (string)($data->status ?? "-")));
            $statusSelect = "<span class='badge bg-secondary pod-vendor-status-badge'>" . esc($statusLabel) . "</span>";
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

            if (($data->status ?? "") === vendor_blocked_status()) {
                $actions .= js_anchor("<i data-feather='unlock' class='icon-16'></i>", [
                    "title" => app_lang("unblock_vendor"),
                    "class" => "js-vendor-unblock",
                    "data-id" => $data->id
                ]);
            } else {
                $actions .= modal_anchor(get_uri("vendors/block_modal_form"), "<i data-feather='slash' class='icon-16'></i>", [
                    "class" => "edit",
                    "title" => app_lang("block_vendor"),
                    "data-post-id" => $data->id
                ]);
            }
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
            $gradeSelect,
            esc($data->vendor_name ?? "-"),
            esc($data->email ?? "-"),
            $locationCell,
            $statusSelect,
            $actions
        ];
    }
}
