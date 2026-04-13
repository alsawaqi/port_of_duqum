<?php

namespace App\Controllers;

use App\Models\Gate_pass_requests_model;
use App\Models\Gate_pass_request_visitors_model;
use App\Models\Gate_pass_request_vehicles_model;
use App\Models\Gate_pass_request_approvals_model;
use App\Models\Gate_pass_department_users_model;
use App\Models\Gate_pass_commercial_users_model;
use App\Models\Gate_pass_security_users_model;
use App\Models\Gate_pass_rop_users_model;
use App\Models\Gate_pass_fee_rules_model;
use App\Models\Gate_passes_model;
use App\Models\Gate_pass_scan_log_model;
use App\Libraries\Pdf;

class Gate_pass_portal extends Security_Controller
{
    protected $Gate_pass_requests_model;
    protected $Gate_pass_request_visitors_model;
    protected $Gate_pass_request_vehicles_model;
    protected $Gate_pass_request_approvals_model;
    protected $Gate_pass_department_users_model;
    protected $Gate_pass_commercial_users_model;
    protected $Gate_pass_security_users_model;
    protected $Gate_pass_rop_users_model;
    protected $Gate_passes_model;
    protected $Gate_pass_fee_rules_model;
    protected $Gate_pass_scan_log_model;

    function __construct()
    {
        parent::__construct();

        // Gate pass visitors are staff users (per your decision)
        if ($this->login_user->user_type !== "staff") {
            app_redirect("forbidden");
        }

        // Must exist in pivot table gate_pass_users
        $this->_require_gate_pass_access();

        $this->Gate_pass_requests_model = new Gate_pass_requests_model();
        $this->Gate_pass_request_visitors_model = new Gate_pass_request_visitors_model();
        $this->Gate_pass_request_vehicles_model = new Gate_pass_request_vehicles_model();
        $this->Gate_pass_request_approvals_model = new Gate_pass_request_approvals_model();
        $this->Gate_pass_department_users_model = new Gate_pass_department_users_model();
        $this->Gate_pass_commercial_users_model = new Gate_pass_commercial_users_model();
        $this->Gate_pass_security_users_model = new Gate_pass_security_users_model();
        $this->Gate_pass_rop_users_model = new Gate_pass_rop_users_model();
        $this->Gate_pass_fee_rules_model = new Gate_pass_fee_rules_model();

        $this->Gate_passes_model = new Gate_passes_model();
        $this->Gate_pass_scan_log_model = new Gate_pass_scan_log_model();
    }

    private function _require_gate_pass_access()
    {
        if ($this->login_user->is_admin) {
            return;
        }

        $db = db_connect();
        $user_id = (int)$this->login_user->id;

        // Portal access: gate_pass_users (requesters), or department/commercial/security reviewers
        $gp_users = $db->prefixTable("gate_pass_users");
        if ($db->query("SELECT id FROM $gp_users WHERE user_id=? AND deleted=0 AND status='active' LIMIT 1", [$user_id])->getRow()) {
            return;
        }

        $gp_dept = $db->prefixTable("gate_pass_department_users");
        if ($db->query("SELECT id FROM $gp_dept WHERE user_id=? AND deleted=0 AND status='active' LIMIT 1", [$user_id])->getRow()) {
            return;
        }

        try {
            $gp_commercial = $db->prefixTable("gate_pass_commercial_users");
            if ($db->query("SELECT id FROM $gp_commercial WHERE user_id=? AND deleted=0 AND status='active' LIMIT 1", [$user_id])->getRow()) {
                return;
            }
        } catch (\Throwable $e) {
            // Table may not exist
        }

        try {
            $gp_security = $db->prefixTable("gate_pass_security_users");
            if ($db->query("SELECT id FROM $gp_security WHERE user_id=? AND deleted=0 AND status='active' LIMIT 1", [$user_id])->getRow()) {
                return;
            }
        } catch (\Throwable $e) {
            // Table may not exist
        }

        try {
            $gp_rop = $db->prefixTable("gate_pass_rop_users");
            if ($db->query("SELECT id FROM $gp_rop WHERE user_id=? AND deleted=0 AND status='active' LIMIT 1", [$user_id])->getRow()) {
                return;
            }
        } catch (\Throwable $e) {
            // Table may not exist
        }

        app_redirect("forbidden");
    }

    // wrapper like vendor portal
    function index($tab = "")
    {
        return $this->view($tab);
    }

    function view($tab = "")
    {
        $view_data["tab"] = $tab;
        $view_data["page_title"] = app_lang("gate_pass_portal_browser_title");
        return $this->template->rander("gate_pass_portal/view", $view_data);
    }

    // ---------- TAB: Requests ----------
    function requests()
    {
        return $this->template->view("gate_pass_portal/requests/index");
    }

    function dashboard()
    {
        $Stats = new \App\Models\Pod_dashboard_stats_model();
        $view_data["kpis"] = $Stats->gate_pass_kpis(["requester_id" => (int)$this->login_user->id]);
        return $this->template->view("gate_pass_portal/dashboard_tab", $view_data);
    }

    function requests_list_data()
    {
        $list_data = $this->Gate_pass_requests_model->get_details([
            "requester_id" => $this->login_user->id
        ])->getResult();

        $result = [];
        foreach ($list_data as $row) {
            $result[] = $this->_make_request_row($row);
        }

        echo json_encode(["data" => $result]);
    }

    function request_modal_form()
    {
        $this->validate_submitted_data(["id" => "numeric"]);
        $id = $this->request->getPost("id");

        $model_info = $id ? $this->Gate_pass_requests_model->get_details(["id" => $id])->getRow() : null;

        if ($model_info && !$this->_can_view_request_details($model_info)) {
            app_redirect("forbidden");
        }

        if ($model_info && !$this->_can_edit_request_core_fields($model_info)) {
            return $this->template->view("errors/html/error_general", [
                "heading" => app_lang("error"),
                "message" => app_lang("gate_pass_request_not_editable"),
            ]);
        }

        if (!$id && !$this->_user_is_gate_pass_requester_pivot() && empty($this->login_user->is_admin)) {
            return $this->template->view("errors/html/error_general", [
                "heading" => app_lang("error"),
                "message" => app_lang("forbidden"),
            ]);
        }

        // dropdowns
        $db = db_connect();

        $companies = $db->query("SELECT id, name, code FROM " . $db->prefixTable("companies") . " WHERE deleted=0 AND is_active=1 ORDER BY name ASC")->getResult();
        $purposes  = $db->query("SELECT id, name FROM " . $db->prefixTable("gate_pass_purposes") . " WHERE deleted=0 AND is_active=1 ORDER BY name ASC")->getResult();

        $company_dropdown = ["" => "- " . app_lang("select") . " -"];
        foreach ($companies as $c) $company_dropdown[$c->id] = $c->name . " (" . $c->code . ")";

        $purpose_dropdown = ["" => "- " . app_lang("select") . " -"];
        foreach ($purposes as $p) $purpose_dropdown[$p->id] = $p->name;

        $view_data["model_info"] = $model_info;
        $view_data["company_dropdown"] = $company_dropdown;
        $view_data["purpose_dropdown"] = $purpose_dropdown;


        $rules_tbl = $db->prefixTable("gate_pass_fee_rules");
$curr_rows = $db->query("SELECT DISTINCT currency FROM $rules_tbl WHERE deleted=0 AND is_active=1 ORDER BY currency ASC")->getResult();

$currency_dropdown = [];
if (!$curr_rows) {
    $currency_dropdown["OMR"] = "OMR";
} else {
    foreach ($curr_rows as $r) {
        $c = strtoupper(trim((string)$r->currency));
        if ($c) $currency_dropdown[$c] = $c;
    }
    if (!isset($currency_dropdown["OMR"])) $currency_dropdown = ["OMR" => "OMR"] + $currency_dropdown;
}
$view_data["currency_dropdown"] = $currency_dropdown;

        return $this->template->view("gate_pass_portal/requests/modal_form", $view_data);
    }

    // AJAX: load active departments for a company (for request modal)
    function departments_by_company($company_id = 0)
    {
        validate_numeric_value($company_id);

        $db = db_connect();
        $departments = $db->prefixTable("departments");

        $rows = $db->query(
            "SELECT id, name FROM $departments
             WHERE deleted=0 AND is_active=1 AND company_id=?
             ORDER BY name ASC",
            [(int)$company_id]
        )->getResult();

        $out = [];
        foreach ($rows as $r) {
            $out[] = ["id" => (int)$r->id, "text" => $r->name];
        }

        return $this->response->setJSON($out);
    }

    function save_request()
    {
        $this->validate_submitted_data([
            "id" => "numeric",
            "company_id" => "required|numeric",
            "department_id" => "permit_empty|numeric",
            "gate_pass_purpose_id" => "required|numeric",
            "visit_from" => "required",
            "visit_to" => "required"
        ]);
    
        $id = $this->request->getPost("id");
        $existing = null;

        // If editing, verify ownership
        if ($id) {
            $existing = $this->Gate_pass_requests_model->get_details(["id" => $id])->getRow();
            if (!$existing) {
                echo json_encode(["success" => false, "message" => app_lang("record_not_found")]);
                return;
            }
            if (!$this->_can_edit_request_core_fields($existing)) {
                echo json_encode(["success" => false, "message" => app_lang("forbidden")]);
                return;
            }
        } elseif (!$this->_user_is_gate_pass_requester_pivot() && empty($this->login_user->is_admin)) {
            echo json_encode(["success" => false, "message" => app_lang("forbidden")]);
            return;
        }

        $status_for_save = "draft";
        $submitted_at_for_save = null;
        $stage_for_save = "department";
        if ($existing) {
            $status_for_save = (string)($existing->status ?? "draft");
            $submitted_at_for_save = $existing->submitted_at ?? null;
            $stage_for_save = (string)($existing->stage ?? "department");
        }
    
        $visit_from = $this->_normalize_gate_pass_datetime($this->request->getPost("visit_from"), "start");
        $visit_to   = $this->_normalize_gate_pass_datetime($this->request->getPost("visit_to"), "end");
    
        if ($visit_from && $visit_to && strtotime($visit_to) < strtotime($visit_from)) {
            echo json_encode(["success" => false, "message" => "Invalid date range: Visit To must be after Visit From."]);
            return;
        }
    
        $currency = strtoupper(trim((string)($this->request->getPost("currency") ?: "OMR")));
        if (!$currency) $currency = "OMR";
    
        $days = $this->_calculate_visit_days($visit_from, $visit_to);
        if ($days < 1) {
            echo json_encode(["success" => false, "message" => "Invalid visit dates."]);
            return;
        }

        // BRD §7.4 / §7.7 — maximum visit window one year (inclusive day count).
        if ($days > 365) {
            echo json_encode(["success" => false, "message" => app_lang("gate_pass_visit_max_one_year")]);
            return;
        }
    
        // Find rule (by days + currency only, as you decided)
        $rule = $this->Gate_pass_fee_rules_model->find_rule_by_days($days, $currency);
        if (!$rule) {
            echo json_encode([
                "success" => false,
                "message" => "No fee rule found for {$days} day(s) with currency {$currency}."
            ]);
            return;
        }
    
        $fee_amount = $this->Gate_pass_fee_rules_model->calculate_fee($rule, $days);
    
        // Handle optional department_id - convert empty/0 to NULL
        $department_id = $this->request->getPost("department_id");
        $department_id = ($department_id && (int)$department_id > 0) ? (int)$department_id : null;

        $request_type = strtolower(trim((string) ($this->request->getPost("request_type") ?: "both")));
        if (!in_array($request_type, ["both", "person"], true)) {
            $request_type = "both";
        }
        $vehicle_type = strtolower(trim((string) ($this->request->getPost("vehicle_type") ?: "none")));
        if ($request_type === "person") {
            $vehicle_type = "none";
        }
        
        $requester_id_for_save = $existing ? (int)$existing->requester_id : (int)$this->login_user->id;

        $data = [
            "requester_id" => $requester_id_for_save,
            "company_id" => (int)$this->request->getPost("company_id"),
            "department_id" => $department_id,
            "gate_pass_purpose_id" => (int)$this->request->getPost("gate_pass_purpose_id"),
            "visit_from" => $visit_from,
            "visit_to" => $visit_to,
            "purpose_notes" => $this->request->getPost("purpose_notes"),
            "visit_type" => $this->request->getPost("visit_type") ?: "visitor",
            "request_type" => $request_type,
            "vehicle_type" => $vehicle_type,
            "currency" => $currency,
            "fee_amount" => $fee_amount,
            "status" => $status_for_save,
            "submitted_at" => $submitted_at_for_save,
            "stage" => $stage_for_save,
        ];

        $dbConn = db_connect();
        $gpReqTable = $dbConn->prefixTable("gate_pass_requests");
        if (!$id && in_array("created_at", $dbConn->getFieldNames($gpReqTable), true)) {
            $data["created_at"] = get_current_utc_time();
        }
    
        $data = clean_data($data);
    
        $db = db_connect();
        $db->transBegin();
    
        try {
            $save_id = $this->Gate_pass_requests_model->ci_save($data, $id);
            
            if (!$save_id) {
                throw new \RuntimeException("Failed to save gate pass request. Database insert/update returned false.");
            }
    
            // Generate reference after insert (if new)
            if ($save_id && !$id) {
                $ref = "GP-" . date("Y") . "-" . str_pad($save_id, 6, "0", STR_PAD_LEFT);
                $ref_data = ["reference" => $ref];
                $this->Gate_pass_requests_model->ci_save($ref_data, $save_id);
            }

            // Visitors/vehicles (with required documents) are added only from the request details page.
            if ($id) {
                $chk = $this->_gate_pass_attachments_complete_if_has_parties((int) $save_id);
                if (!$chk["ok"]) {
                    throw new \RuntimeException($chk["message"]);
                }
            }
    
            if ($db->transStatus() === false) {
                throw new \RuntimeException("Transaction failed");
            }
    
            $db->transCommit();

            gate_pass_audit_log(
                (int)$this->login_user->id,
                (int)$save_id,
                $id ? "request_updated" : "request_created",
                $id ? app_lang("gate_pass_audit_detail_request_updated") : app_lang("gate_pass_audit_detail_request_created")
            );
    
            echo json_encode([
                "success" => true,
                "data" => $this->_request_row_data($save_id),
                "id" => $save_id,
                "message" => app_lang("record_saved")
            ]);
    
        } catch (\Throwable $e) {
            $db->transRollback();
            
            // Log the actual error for debugging
            log_message('error', 'Gate pass save_request error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
            log_message('error', 'Stack trace: ' . $e->getTraceAsString());
            
            // Return detailed error in development, generic in production
            $error_message = app_lang("error_occurred");
            if (ENVIRONMENT !== 'production') {
                $error_message .= " (" . $e->getMessage() . ")";
            }
            
            echo json_encode(["success" => false, "message" => $error_message]);
        }
    }

    /**
     * Requester submits a draft to department, or resubmits after any stage returned the request
     * (stage is preserved; status is restored so the same reviewer queue receives it again).
     */
    function submit_to_department()
    {
        $this->validate_submitted_data(["gate_pass_request_id" => "required|numeric"]);
        $request_id = (int)$this->request->getPost("gate_pass_request_id");

        $request = $this->Gate_pass_requests_model->get_details(["id" => $request_id])->getRow();
        if (!$request || $request->deleted) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("record_not_found")]);
        }
        if ((int)$request->requester_id !== (int)$this->login_user->id) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("forbidden")]);
        }

        $st = strtolower(trim((string)($request->status ?? "")));
        $stage = strtolower(trim((string)($request->stage ?? "")));

        if ($st === "draft") {
            if ($stage !== "department") {
                return $this->response->setJSON(["success" => false, "message" => app_lang("error_occurred")]);
            }
        } elseif ($st === "returned") {
            $next = gate_pass_status_after_requester_resubmit($stage);
            if ($next === null) {
                return $this->response->setJSON(["success" => false, "message" => app_lang("error_occurred")]);
            }
        } else {
            return $this->response->setJSON(["success" => false, "message" => app_lang("error_occurred")]);
        }

        $check = $this->_gate_pass_visitor_attachments_complete($request_id);
        if (!$check["ok"]) {
            return $this->response->setJSON(["success" => false, "message" => $check["message"]]);
        }

        $now = get_current_utc_time();
        if ($st === "draft") {
            $this->Gate_pass_requests_model->ci_save([
                "status" => "submitted",
                "submitted_at" => $now,
            ], $request_id);
            gate_pass_audit_log((int)$this->login_user->id, $request_id, "submitted_to_department", app_lang("gate_pass_audit_detail_submitted_to_department"));
        } else {
            $nextStatus = gate_pass_status_after_requester_resubmit($stage);
            $this->Gate_pass_requests_model->ci_save([
                "status" => $nextStatus,
                "submitted_at" => $request->submitted_at ?: $now,
                "stage_updated_at" => $now,
            ], $request_id);
            gate_pass_audit_log(
                (int)$this->login_user->id,
                $request_id,
                "resubmitted_after_return",
                sprintf(app_lang("gate_pass_audit_detail_resubmitted"), gate_pass_audit_stage_label_for_log($stage))
            );
        }

        return $this->response->setJSON(["success" => true, "message" => app_lang("record_saved"), "id" => $request_id]);
    }

    /**
     * CSV export of the current user's gate pass requests (portal report).
     */
    function export_my_requests_csv()
    {
        $list = $this->Gate_pass_requests_model->get_details([
            "requester_id" => (int)$this->login_user->id,
        ])->getResult();

        $filename = "gate_pass_requests_" . date("Y-m-d") . ".csv";
        $this->response->setHeader("Content-Type", "text/csv; charset=UTF-8");
        $this->response->setHeader("Content-Disposition", "attachment; filename=\"" . $filename . "\"");

        $fh = fopen("php://temp", "r+");
        fputcsv($fh, ["reference", "created_at", "company", "department", "purpose", "status", "stage", "visit_from", "visit_to", "fee_amount", "currency"]);
        foreach ($list as $r) {
            fputcsv($fh, [
                $r->reference ?? "",
                gate_pass_request_created_at_pick($r) ?? "",
                $r->company_name ?? "",
                $r->department_name ?? "",
                $r->purpose_name ?? "",
                $r->status ?? "",
                $r->stage ?? "",
                $r->visit_from ?? "",
                $r->visit_to ?? "",
                (string)($r->fee_amount ?? ""),
                (string)($r->currency ?? ""),
            ]);
        }
        rewind($fh);
        $body = stream_get_contents($fh);
        fclose($fh);

        return $this->response->setBody($body);
    }

    /**
     * Duplicate an existing request into a new draft (same visitors/vehicles and attachment paths).
     */
    function duplicate_request()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);
        $id = (int)$this->request->getPost("id");

        $src = $this->Gate_pass_requests_model->get_details(["id" => $id])->getRow();
        if (!$src || $src->deleted) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("record_not_found")]);
        }
        if ((int)$src->requester_id !== (int)$this->login_user->id) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("forbidden")]);
        }

        $db = db_connect();
        $db->transBegin();
        try {
            $dupRequestType = strtolower(trim((string)($src->request_type ?? "both")));
            if ($dupRequestType === "vehicle") {
                $dupRequestType = "both";
            }
            if (!in_array($dupRequestType, ["both", "person"], true)) {
                $dupRequestType = "both";
            }
            $dupVehicleType = strtolower(trim((string)($src->vehicle_type ?? "none")));
            if ($dupRequestType === "person") {
                $dupVehicleType = "none";
            }
            $newRow = [
                "requester_id" => (int)$this->login_user->id,
                "company_id" => (int)$src->company_id,
                "department_id" => $src->department_id ? (int)$src->department_id : null,
                "gate_pass_purpose_id" => (int)$src->gate_pass_purpose_id,
                "visit_from" => $src->visit_from,
                "visit_to" => $src->visit_to,
                "purpose_notes" => $src->purpose_notes,
                "visit_type" => $src->visit_type ?? "visitor",
                "request_type" => $dupRequestType,
                "vehicle_type" => $dupVehicleType,
                "currency" => $src->currency ?? "OMR",
                "fee_amount" => $src->fee_amount,
                "status" => "draft",
                "stage" => "department",
                "submitted_at" => null,
                "fee_is_waived" => 0,
                "fee_waived_by" => null,
                "fee_waived_reason" => null,
                "fee_waived_at" => null,
            ];
            $dbConn = db_connect();
            $gpReqTable = $dbConn->prefixTable("gate_pass_requests");
            if (in_array("created_at", $dbConn->getFieldNames($gpReqTable), true)) {
                $newRow["created_at"] = get_current_utc_time();
            }
            $new_id = (int)$this->Gate_pass_requests_model->ci_save(clean_data($newRow));
            if ($new_id < 1) {
                throw new \RuntimeException("save request");
            }
            $ref = "GP-" . date("Y") . "-" . str_pad((string)$new_id, 6, "0", STR_PAD_LEFT);
            $this->Gate_pass_requests_model->ci_save(["reference" => $ref], $new_id);

            $visitors = $this->Gate_pass_request_visitors_model->get_details(["gate_pass_request_id" => $id])->getResult();
            $i = 0;
            foreach ($visitors as $v) {
                $vd = [
                    "gate_pass_request_id" => $new_id,
                    "full_name" => $v->full_name,
                    "id_type" => $v->id_type,
                    "id_number" => $v->id_number,
                    "nationality" => $v->nationality,
                    "phone" => $v->phone,
                    "visitor_company" => $v->visitor_company ?? null,
                    "role" => $v->role ?? "visitor",
                    "is_primary" => $i === 0 ? 1 : 0,
                    "id_attachment_path" => $v->id_attachment_path ?? null,
                    "visa_attachment_path" => $v->visa_attachment_path ?? null,
                    "photo_attachment_path" => $v->photo_attachment_path ?? null,
                    "driving_license_attachment_path" => $v->driving_license_attachment_path ?? null,
                ];
                $this->Gate_pass_request_visitors_model->ci_save(clean_data($vd));
                $i++;
            }

            $vehicles = $this->Gate_pass_request_vehicles_model->get_details(["gate_pass_request_id" => $id])->getResult();
            foreach ($vehicles as $veh) {
                $vehData = [
                    "gate_pass_request_id" => $new_id,
                    "plate_no" => $veh->plate_no,
                    "type" => $veh->type ?? "private",
                    "make" => null,
                    "model" => null,
                    "color" => null,
                    "mulkiyah_attachment_path" => $veh->mulkiyah_attachment_path ?? null,
                ];
                $this->Gate_pass_request_vehicles_model->ci_save(clean_data($vehData));
            }

            if ($db->transStatus() === false) {
                throw new \RuntimeException("transaction");
            }
            $db->transCommit();

            $old_req = $this->Gate_pass_requests_model->get_details(["id" => $id])->getRow();
            $new_req = $this->Gate_pass_requests_model->get_details(["id" => $new_id])->getRow();
            $old_ref = $old_req && trim((string)($old_req->reference ?? "")) !== ""
                ? trim((string)$old_req->reference)
                : app_lang("gate_pass_audit_unknown_reference");
            $new_ref = $new_req && trim((string)($new_req->reference ?? "")) !== ""
                ? trim((string)$new_req->reference)
                : app_lang("gate_pass_audit_unknown_reference");
            gate_pass_audit_log(
                (int)$this->login_user->id,
                $new_id,
                "request_duplicated",
                sprintf(app_lang("gate_pass_audit_detail_request_duplicated"), $old_ref, $new_ref)
            );

            return $this->response->setJSON([
                "success" => true,
                "message" => app_lang("record_saved"),
                "id" => $new_id,
                "redirect" => get_uri("gate_pass_portal/request_details/" . $new_id),
            ]);
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message("error", "duplicate_request: " . $e->getMessage());
            return $this->response->setJSON(["success" => false, "message" => app_lang("error_occurred")]);
        }
    }

    function request_audit_list_data($request_id = 0)
    {
        validate_numeric_value($request_id);
        $request = $this->Gate_pass_requests_model->get_details(["id" => $request_id])->getRow();
        if (!$request || $request->deleted) {
            return $this->response->setJSON(["data" => []]);
        }
        $is_requester = (int)$request->requester_id === (int)$this->login_user->id;
        $is_admin = !empty($this->login_user->is_admin);
        if (!$is_requester && !$is_admin) {
            return $this->response->setJSON(["data" => []]);
        }

        try {
            $model = new \App\Models\Gate_pass_request_audit_log_model();
            $rows = $model->get_details(["gate_pass_request_id" => $request_id])->getResult();
        } catch (\Throwable $e) {
            return $this->response->setJSON(["data" => []]);
        }

        $out = [];
        foreach ($rows as $a) {
            $out[] = [
                $a->created_at ? format_to_datetime($a->created_at) : "-",
                trim((string)($a->actor_name ?? "")) ?: ("#" . (int)($a->actor_user_id ?? 0)),
                gate_pass_audit_action_display_label((string)($a->action ?? "")),
                $a->details ? htmlspecialchars((string)$a->details, ENT_QUOTES, "UTF-8") : "-",
            ];
        }

        return $this->response->setJSON(["data" => $out]);
    }

    /**
     * @return array{ok:bool,message:string}
     */
    private function _gate_pass_visitor_attachments_complete(int $request_id): array
    {
        $request = $this->Gate_pass_requests_model->get_details(["id" => $request_id])->getRow();
        if (!$request || !empty($request->deleted)) {
            return ["ok" => false, "message" => app_lang("record_not_found")];
        }

        $visitors = $this->Gate_pass_request_visitors_model->get_details(["gate_pass_request_id" => $request_id])->getResult();
        $vehicles = $this->Gate_pass_request_vehicles_model->get_details(["gate_pass_request_id" => $request_id])->getResult();

        $request_type = strtolower(trim((string) ($request->request_type ?? "both")));
        if (!in_array($request_type, ["both", "person", "vehicle"], true)) {
            $request_type = "both";
        }

        if ($request_type === "both") {
            if (count($visitors) < 1) {
                return ["ok" => false, "message" => app_lang("gate_pass_both_requires_visitor")];
            }
            if (count($vehicles) < 1) {
                return ["ok" => false, "message" => app_lang("gate_pass_both_requires_vehicle")];
            }
        } elseif ($request_type === "person") {
            if (count($visitors) < 1) {
                return ["ok" => false, "message" => app_lang("gate_pass_person_requires_visitor")];
            }
        } elseif ($request_type === "vehicle") {
            if (count($vehicles) < 1) {
                return ["ok" => false, "message" => app_lang("gate_pass_vehicle_requires_vehicle")];
            }
        }

        foreach ($visitors as $v) {
            $idPath = trim((string) ($v->id_attachment_path ?? ""));
            if ($idPath === "") {
                return ["ok" => false, "message" => app_lang("gate_pass_attachments_required_submit")];
            }
            $vrole = strtolower(trim((string) ($v->role ?? "visitor")));
            if ($vrole === "driver") {
                $dlPath = trim((string) ($v->driving_license_attachment_path ?? ""));
                if ($dlPath === "") {
                    return ["ok" => false, "message" => app_lang("gate_pass_driving_license_required_driver")];
                }
            }
        }

        foreach ($vehicles as $veh) {
            $plate = strtoupper(trim((string)($veh->plate_no ?? "")));
            if ($plate !== "" && !gate_pass_plate_no_is_valid($plate)) {
                return ["ok" => false, "message" => app_lang("gate_pass_plate_invalid_chars")];
            }
            $mulkiyah = gate_pass_vehicle_mulkiyah_path_value($veh);
            if ($mulkiyah === "") {
                return ["ok" => false, "message" => app_lang("gate_pass_mulkiyah_required")];
            }
        }

        return ["ok" => true, "message" => ""];
    }

    /**
     * Same checks as submit, but allows an empty request (no visitors/vehicles yet).
     *
     * @return array{ok:bool,message:string}
     */
    private function _gate_pass_attachments_complete_if_has_parties(int $request_id): array
    {
        $visitors = $this->Gate_pass_request_visitors_model->get_details(["gate_pass_request_id" => $request_id])->getResult();
        $vehicles = $this->Gate_pass_request_vehicles_model->get_details(["gate_pass_request_id" => $request_id])->getResult();
        if (!count($visitors) && !count($vehicles)) {
            return ["ok" => true, "message" => ""];
        }

        return $this->_gate_pass_visitor_attachments_complete($request_id);
    }


    private function _calculate_visit_days(?string $visit_from, ?string $visit_to): int
{
    if (!$visit_from || !$visit_to) return 0;

    // Use date-only for day counting (inclusive)
    $start = new \DateTime(substr($visit_from, 0, 10));
    $end   = new \DateTime(substr($visit_to, 0, 10));

    $diff = $start->diff($end);
    if ($diff->invert) return 0;

    return (int)$diff->days + 1; // inclusive
}


function calc_fee_preview()
{
    $visit_from = $this->_normalize_gate_pass_datetime($this->request->getGet("visit_from"), "start");
    $visit_to   = $this->_normalize_gate_pass_datetime($this->request->getGet("visit_to"), "end");
    $currency   = strtoupper(trim((string)($this->request->getGet("currency") ?: "OMR")));
    if (!$currency) $currency = "OMR";

    if (!$visit_from || !$visit_to || strtotime($visit_to) < strtotime($visit_from)) {
        return $this->response->setJSON(["success" => false, "message" => "Invalid dates"]);
    }

    $days = $this->_calculate_visit_days($visit_from, $visit_to);
    if ($days > 365) {
        return $this->response->setJSON(["success" => false, "message" => app_lang("gate_pass_visit_max_one_year")]);
    }
    $rule = $this->Gate_pass_fee_rules_model->find_rule_by_days($days, $currency);

    if (!$rule) {
        return $this->response->setJSON(["success" => false, "message" => "No rule found"]);
    }

    $fee_amount = $this->Gate_pass_fee_rules_model->calculate_fee($rule, $days);

    return $this->response->setJSON([
        "success" => true,
        "days" => $days,
        "currency" => $currency,
        "rate_type" => $rule->rate_type,
        "unit_amount" => (float)$rule->amount,
        "fee_amount" => (float)$fee_amount,
        "fee_amount_formatted" => number_format((float)$fee_amount, 3)
    ]);
}


    
    function request_details($id = 0)
    {
        validate_numeric_value($id);

        $request = $this->Gate_pass_requests_model->get_details(["id" => $id])->getRow();
        if (!$request || $request->deleted) {
            app_redirect("forbidden");
        }

        if (!$this->_can_view_request_details($request)) {
            app_redirect("forbidden");
        }

        $view_data["request"] = $request;
        $view_data["is_requester"] = (int)$request->requester_id === (int)$this->login_user->id;
        $view_data["requester_can_edit"] = $view_data["is_requester"] && gate_pass_requester_can_edit_request($request);
        $view_data["can_edit_request_core"] = $this->_can_edit_request_core_fields($request);
   
        $view_data["approval_history"] = $this->Gate_pass_request_approvals_model
            ->get_details(["gate_pass_request_id" => $request->id])
            ->getResult();

        $view_data["gate_pass"] = null;
        if ($request->status === "rop_approved") {
            $view_data["gate_pass"] = $this->Gate_passes_model->get_by_request_id($request->id);
        }

        $view_data["status_label"] = gate_pass_request_status_display($request);
        $view_data["can_approve"] = $this->_can_act_on_request($request);

        $view_data["visitor_rows_for_attachments"] = [];
        $view_data["vehicle_rows_for_attachments"] = [];
        $is_owner = (int)$request->requester_id === (int)$this->login_user->id;
        $is_admin = !empty($this->login_user->is_admin);
        $view_data["visitor_rows_for_attachments"] = $this->Gate_pass_request_visitors_model
            ->get_details(["gate_pass_request_id" => $request->id])
            ->getResult();
        $view_data["vehicle_rows_for_attachments"] = $this->Gate_pass_request_vehicles_model
            ->get_details(["gate_pass_request_id" => $request->id])
            ->getResult();

        $view_data["show_gate_scan_log"] = true;
        $view_data["scan_log_rows"] = [];
        if ($view_data["show_gate_scan_log"]) {
            $view_data["scan_log_rows"] = $this->Gate_pass_scan_log_model
                ->get_details([
                    "gate_pass_request_id" => (int)$request->id,
                    "order" => "asc",
                ])
                ->getResult();
        }
        $view_data["portal_is_admin"] = $is_admin;
        $view_data["page_title"] = app_lang("gate_pass_portal_browser_title");

        return $this->template->rander("gate_pass_portal/requests/details", $view_data);
    }

    /**
     * Requester: view/download visitor documents stored for this gate pass request.
     */
    public function visitor_attachment_download($visitor_id = 0, $field = "")
    {
        $visitor_id = (int)$visitor_id;
        $allowed = ["id_attachment_path", "visa_attachment_path", "photo_attachment_path", "driving_license_attachment_path"];
        if (!$visitor_id || !in_array($field, $allowed, true)) {
            show_404();
        }

        $visitor = $this->Gate_pass_request_visitors_model->get_details(["id" => $visitor_id])->getRow();
        if (!$visitor || empty($visitor->{$field})) {
            show_404();
        }

        $relPath = $visitor->{$field};
        $relPath = preg_replace("#\.\.+#", "", (string)$relPath);
        $relPath = ltrim($relPath, "/");
        $fullPath = WRITEPATH . "uploads/" . $relPath;
        if (!is_file($fullPath)) {
            show_404();
        }

        $request = $this->Gate_pass_requests_model->get_details(["id" => $visitor->gate_pass_request_id])->getRow();
        if (!$request || !$this->_can_view_request_details($request)) {
            app_redirect("forbidden");
        }

        $mime = function_exists("mime_content_type") ? mime_content_type($fullPath) : "application/octet-stream";
        $name = basename($relPath);
        $download = (int)($this->request->getGet("download") ?? 0) === 1;
        $inline = !$download && ((strpos($mime, "image/") === 0) || $mime === "application/pdf");

        return $this->response
            ->setHeader("Content-Type", $mime)
            ->setHeader("Content-Disposition", ($inline ? "inline" : "attachment") . '; filename="' . addslashes($name) . '"')
            ->setBody(file_get_contents($fullPath));
    }

    /**
     * Requester: view/download vehicle Mulkiyah file.
     */
    public function vehicle_attachment_download($vehicle_id = 0, $field = "")
    {
        $vehicle_id = (int)$vehicle_id;
        if (!$vehicle_id || $field !== "mulkiyah_attachment_path") {
            show_404();
        }

        $veh = $this->Gate_pass_request_vehicles_model->get_details(["id" => $vehicle_id])->getRow();
        $relPath = gate_pass_vehicle_mulkiyah_path_value($veh);
        if (!$veh || $relPath === "") {
            show_404();
        }
        $relPath = preg_replace("#\.\.+#", "", (string)$relPath);
        $relPath = ltrim($relPath, "/");
        $fullPath = WRITEPATH . "uploads/" . $relPath;
        if (!is_file($fullPath)) {
            show_404();
        }

        $request = $this->Gate_pass_requests_model->get_details(["id" => $veh->gate_pass_request_id])->getRow();
        if (!$request || !$this->_can_view_request_details($request)) {
            app_redirect("forbidden");
        }

        $mime = function_exists("mime_content_type") ? mime_content_type($fullPath) : "application/octet-stream";
        $name = basename($relPath);
        $download = (int)($this->request->getGet("download") ?? 0) === 1;
        $inline = !$download && ((strpos($mime, "image/") === 0) || $mime === "application/pdf");

        return $this->response
            ->setHeader("Content-Type", $mime)
            ->setHeader("Content-Disposition", ($inline ? "inline" : "attachment") . '; filename="' . addslashes($name) . '"')
            ->setBody(file_get_contents($fullPath));
    }

    /**
     * Append print audit to gate_pass.meta and set printed_at (pod_gate_passes).
     */
    private function _audit_gate_pass_print(int $gate_pass_id, string $source): void
    {
        $row = $this->Gate_passes_model->get_one($gate_pass_id);
        if (!$row || (int)$row->deleted === 1) {
            return;
        }

        $now = get_current_utc_time();
        $meta = [];
        if (!empty($row->meta)) {
            $decoded = json_decode($row->meta, true);
            if (is_array($decoded)) {
                $meta = $decoded;
            }
        }

        $meta["printing"] = $meta["printing"] ?? ["count" => 0, "logs" => []];
        $meta["printing"]["count"] = (int)($meta["printing"]["count"] ?? 0) + 1;
        $meta["printing"]["last_at"] = $now;
        $meta["printing"]["last_source"] = $source;
        $meta["printing"]["last_by_user_id"] = (int)$this->login_user->id;

        $logs = $meta["printing"]["logs"] ?? [];
        if (!is_array($logs)) {
            $logs = [];
        }
        $logs[] = [
            "at" => $now,
            "by" => (int)$this->login_user->id,
            "source" => $source,
            "ip" => $this->request->getIPAddress(),
        ];
        if (count($logs) > 100) {
            $logs = array_slice($logs, -100);
        }
        $meta["printing"]["logs"] = $logs;

        $this->Gate_passes_model->ci_save([
            "printed_at" => $now,
            "meta" => json_encode($meta, JSON_UNESCAPED_UNICODE),
            "updated_at" => $now,
        ], $gate_pass_id);
    }

    /**
     * Log a browser print of the request details page (QR card); optional client hook.
     */
    function record_qr_print()
    {
        $this->validate_submitted_data([
            "gate_pass_request_id" => "required|numeric",
        ]);
        $request_id = (int)$this->request->getPost("gate_pass_request_id");

        $request = $this->Gate_pass_requests_model->get_details(["id" => $request_id])->getRow();
        if (!$request || $request->deleted) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("forbidden")]);
        }
        if ($request->status !== "rop_approved") {
            return $this->response->setJSON(["success" => false, "message" => app_lang("forbidden")]);
        }
        $gate_pass = $this->Gate_passes_model->get_by_request_id($request_id);
        if (!$gate_pass || empty($gate_pass->qr_token)) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("forbidden")]);
        }

        $this->_audit_gate_pass_print((int)$gate_pass->id, "browser_print");

        return $this->response->setJSON(["success" => true]);
    }

    /**
     * Output QR code image for download (only when request status is rop_approved and user has access).
     */
    function download_qr($request_id = 0)
    {
        validate_numeric_value($request_id);
        $request = $this->Gate_pass_requests_model->get_details(["id" => $request_id])->getRow();
        if (!$request || $request->deleted) {
            app_redirect("forbidden");
        }
        if (!$this->_can_view_request_details($request)) {
            app_redirect("forbidden");
        }
        if ($request->status !== "rop_approved") {
            app_redirect("forbidden");
        }
        $gate_pass = $this->Gate_passes_model->get_by_request_id($request_id);
        if (!$gate_pass || empty($gate_pass->qr_token)) {
            app_redirect("forbidden");
        }

        $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($gate_pass->qr_token);
        $img = @file_get_contents($qr_url);
        if ($img === false) {
            app_redirect("forbidden");
        }

        $this->_audit_gate_pass_print((int)$gate_pass->id, "download_png");
        $this->response->setHeader("Content-Type", "image/png");
        $this->response->setHeader("Content-Disposition", "attachment; filename=\"gate-pass-qr-" . (int)$request_id . ".png\"");
        return $this->response->setBody($img);
    }

    /**
     * Printable PDF gate pass (reference, visit window, visitors/vehicles, QR) — only when ROP-approved.
     */
    public function download_gate_pass_pdf($request_id = 0)
    {
        validate_numeric_value($request_id);
        $request = $this->Gate_pass_requests_model->get_details(["id" => $request_id])->getRow();
        if (!$request || $request->deleted) {
            app_redirect("forbidden");
        }
        if (!$this->_can_view_request_details($request)) {
            app_redirect("forbidden");
        }
        if ($request->status !== "rop_approved") {
            app_redirect("forbidden");
        }
        $gate_pass = $this->Gate_passes_model->get_by_request_id($request_id);
        if (!$gate_pass || empty($gate_pass->qr_token)) {
            app_redirect("forbidden");
        }

        $visitors = $this->Gate_pass_request_visitors_model
            ->get_details(["gate_pass_request_id" => $request_id])
            ->getResult();
        $vehicles = $this->Gate_pass_request_vehicles_model
            ->get_details(["gate_pass_request_id" => $request_id])
            ->getResult();

        $html = $this->_gate_pass_pdf_html($request, $gate_pass, $visitors, $vehicles);

        $pdf = new Pdf("");
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(12, 12, 12);
        $pdf->SetAutoPageBreak(true, 14);
        $pdf->AddPage();
        $pdf->writeHTML($html, true, false, true, false, "");

        $this->_audit_gate_pass_print((int)$gate_pass->id, "download_pdf");

        $safeRef = preg_replace("/[^A-Za-z0-9_-]+/", "_", (string)($request->reference ?? "request")) ?: "gate-pass";
        $fileName = "gate-pass-" . $safeRef . ".pdf";
        $binary = $pdf->Output($fileName, "S");

        return $this->response
            ->setHeader("Content-Type", "application/pdf")
            ->setHeader("Content-Disposition", 'inline; filename="' . addslashes($fileName) . '"')
            ->setBody($binary);
    }

    /**
     * @param list<object> $visitors
     * @param list<object> $vehicles
     */
    private function _gate_pass_pdf_html($request, $gate_pass, array $visitors, array $vehicles): string
    {
        $h = static function ($v): string {
            return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
        };

        $visitFrom = !empty($request->visit_from) ? format_to_date($request->visit_from) : "—";
        $visitTo = !empty($request->visit_to) ? format_to_date($request->visit_to) : "—";
        $reqType = strtolower(trim((string)($request->request_type ?? "both")));
        $reqTypeLabel = $reqType === "person"
            ? $h(app_lang("gate_pass_request_type_display_person"))
            : $h(app_lang("gate_pass_request_type_display_both"));
        $feeDisp = "—";
        if (property_exists($request, "fee_amount") && $request->fee_amount !== null && $request->fee_amount !== "" && is_numeric($request->fee_amount)) {
            $cur = trim((string)($request->currency ?? ""));
            $feeDisp = ($cur !== "" ? $h($cur) . " " : "") . $h(number_format((float)$request->fee_amount, 2));
        }
        if (!empty($request->fee_is_waived)) {
            $feeDisp = $h(app_lang("waived"));
        }

        $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode((string)$gate_pass->qr_token);
        $qrImg = @file_get_contents($qrUrl);
        $qrTag = "";
        if ($qrImg !== false && $qrImg !== "") {
            $qrTag = '<img src="data:image/png;base64,' . base64_encode($qrImg) . '" width="120" height="120" alt="QR" />';
        }

        $visitorRows = "";
        foreach ($visitors as $v) {
            $visitorRows .= "<tr>"
                . "<td>" . $h($v->full_name ?? "") . "</td>"
                . "<td>" . $h($v->id_number ?? "") . "</td>"
                . "<td>" . $h($v->nationality ?? "") . "</td>"
                . "<td>" . $h($v->phone ?? "") . "</td>"
                . "</tr>";
        }
        if ($visitorRows === "") {
            $visitorRows = "<tr><td colspan=\"4\">—</td></tr>";
        }

        $vehicleRows = "";
        if ($reqType !== "person") {
            foreach ($vehicles as $veh) {
                $vehicleRows .= "<tr><td>" . $h($veh->plate_no ?? "") . "</td></tr>";
            }
            if ($vehicleRows === "") {
                $vehicleRows = "<tr><td>—</td></tr>";
            }
        } else {
            $vehicleRows = "<tr><td>" . $h(app_lang("gate_pass_pdf_vehicles_na_person")) . "</td></tr>";
        }

        $title = $h(app_lang("gate_pass_pdf_document_title"));
        $lblRef = $h(app_lang("gate_pass_request_details"));
        $lblGpNo = $h(app_lang("gate_pass_pdf_gate_pass_no"));
        $lblCompany = $h(app_lang("company"));
        $lblDept = $h(app_lang("department"));
        $lblPurpose = $h(app_lang("purpose"));
        $lblVisit = $h(app_lang("visit"));
        $lblFrom = $h(app_lang("visit_from"));
        $lblTo = $h(app_lang("visit_to"));
        $lblReqType = $h(app_lang("gate_pass_request_type_label"));
        $lblFee = $h(app_lang("fee_amount"));
        $lblVisitors = $h(app_lang("visitors"));
        $lblVehicles = $h(app_lang("vehicles"));
        $lblName = $h(app_lang("full_name"));
        $lblId = $h(app_lang("id_number"));
        $lblNat = $h(app_lang("nationality"));
        $lblPhone = $h(app_lang("phone"));
        $lblPlate = $h(app_lang("plate_no"));
        $lblQr = $h(app_lang("gate_pass_qr_code"));

        $ref = $h($request->reference ?? "");
        $gpNo = $h($gate_pass->gate_pass_no ?? "");
        $co = $h($request->company_name ?? "");
        $dept = $h($request->department_name ?? "");
        $purpose = $h($request->purpose_name ?? "");

        return <<<HTML
<style>
  h1 { font-size: 18px; margin: 0 0 8px 0; }
  .meta { font-size: 10px; margin-bottom: 12px; }
  table.info { width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size: 10px; }
  table.info td { border: 1px solid #ccc; padding: 5px; }
  table.info td.k { width: 28%; background: #f5f5f5; font-weight: bold; }
  table.grid { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 9px; }
  table.grid th, table.grid td { border: 1px solid #ccc; padding: 4px; text-align: left; }
  table.grid th { background: #eee; }
  .qr { text-align: center; margin-top: 12px; }
  .muted { font-size: 8px; color: #666; margin-top: 10px; }
</style>
<h1>{$title}</h1>
<div class="meta">{$lblRef}: <strong>{$ref}</strong> &nbsp;|&nbsp; {$lblGpNo}: <strong>{$gpNo}</strong></div>
<table class="info" cellspacing="0">
  <tr><td class="k">{$lblCompany}</td><td>{$co}</td></tr>
  <tr><td class="k">{$lblDept}</td><td>{$dept}</td></tr>
  <tr><td class="k">{$lblPurpose}</td><td>{$purpose}</td></tr>
  <tr><td class="k">{$lblFrom}</td><td>{$visitFrom}</td></tr>
  <tr><td class="k">{$lblTo}</td><td>{$visitTo}</td></tr>
  <tr><td class="k">{$lblReqType}</td><td>{$reqTypeLabel}</td></tr>
  <tr><td class="k">{$lblFee}</td><td>{$feeDisp}</td></tr>
</table>
<p style="font-size:11px;font-weight:bold;margin:10px 0 4px 0;">{$lblVisitors}</p>
<table class="grid" cellspacing="0">
  <thead><tr><th>{$lblName}</th><th>{$lblId}</th><th>{$lblNat}</th><th>{$lblPhone}</th></tr></thead>
  <tbody>{$visitorRows}</tbody>
</table>
<p style="font-size:11px;font-weight:bold;margin:10px 0 4px 0;">{$lblVehicles}</p>
<table class="grid" cellspacing="0">
  <thead><tr><th>{$lblPlate}</th></tr></thead>
  <tbody>{$vehicleRows}</tbody>
</table>
<div class="qr"><div style="font-size:10px;font-weight:bold;margin-bottom:4px;">{$lblQr}</div>{$qrTag}</div>
<p class="muted">{$h(app_lang("gate_pass_pdf_footer_note"))}</p>
HTML;
    }

    /**
     * Whether the current user (department user or admin) can approve/return/reject this request.
     */
    private function _can_act_on_request($request): bool
    {
        if ($this->login_user->is_admin) {
            return true;
        }
        $assignments = $this->Gate_pass_department_users_model
            ->get_user_assignments($this->login_user->id)
            ->getResult();
        foreach ($assignments as $a) {
            if ((int)$a->company_id === (int)$request->company_id && (int)$a->department_id === (int)$request->department_id) {
                return true;
            }
        }
        return false;
    }

    private function _user_is_gate_pass_requester_pivot(): bool
    {
        $db = db_connect();
        $t = $db->prefixTable("gate_pass_users");
        $row = $db->query(
            "SELECT id FROM {$t} WHERE user_id=? AND deleted=0 AND status='active' LIMIT 1",
            [(int)$this->login_user->id]
        )->getRow();

        return $row !== null;
    }

    private function _company_matches_pivot(int $user_id, int $company_id, string $table): bool
    {
        if ($company_id < 1) {
            return false;
        }
        $db = db_connect();
        $t = $db->prefixTable($table);
        $row = $db->query(
            "SELECT id FROM {$t} WHERE user_id=? AND deleted=0 AND status='active' AND company_id=? LIMIT 1",
            [$user_id, $company_id]
        )->getRow();

        return $row !== null;
    }

    /**
     * Requester, admin, or a reviewer assigned to this request's company (and department where relevant).
     */
    private function _can_view_request_details($request): bool
    {
        if (!$request || !empty($request->deleted)) {
            return false;
        }
        if (!empty($this->login_user->is_admin)) {
            return true;
        }
        $uid = (int)$this->login_user->id;
        if ((int)$request->requester_id === $uid) {
            return true;
        }

        $cid = (int)$request->company_id;
        $reqDeptId = isset($request->department_id) && $request->department_id !== null && $request->department_id !== ""
            ? (int)$request->department_id
            : null;

        foreach ($this->Gate_pass_department_users_model->get_user_assignments($uid)->getResult() as $a) {
            if ((int)$a->company_id !== $cid) {
                continue;
            }
            if ($reqDeptId === null || $reqDeptId === 0) {
                return true;
            }
            if ((int)$a->department_id === $reqDeptId) {
                return true;
            }
        }

        if ($this->Gate_pass_commercial_users_model->is_commercial_user($uid)
            && $this->_company_matches_pivot($uid, $cid, "gate_pass_commercial_users")) {
            return true;
        }

        if ($this->Gate_pass_security_users_model->is_security_user($uid)
            && $this->_company_matches_pivot($uid, $cid, "gate_pass_security_users")) {
            return true;
        }

        if ($this->Gate_pass_rop_users_model->is_rop_user($uid)
            && $this->_company_matches_pivot($uid, $cid, "gate_pass_rop_users")) {
            return true;
        }

        return false;
    }

    /**
     * Who may change company, department, purpose, visit window, request type, etc.
     */
    private function _can_edit_request_core_fields($request): bool
    {
        if (!$request || !empty($request->deleted)) {
            return false;
        }
        if (!empty($this->login_user->is_admin)) {
            return true;
        }
        $uid = (int)$this->login_user->id;
        if ((int)$request->requester_id === $uid && gate_pass_requester_can_edit_request($request)) {
            return true;
        }

        $status = strtolower(trim((string)($request->status ?? "")));
        if ($status !== "returned") {
            return false;
        }

        $stage = strtolower(trim((string)($request->stage ?? "")));
        $cid = (int)$request->company_id;

        if ($stage === "department" && $this->_can_act_on_request($request)) {
            return true;
        }
        if ($stage === "commercial"
            && $this->Gate_pass_commercial_users_model->is_commercial_user($uid)
            && $this->_company_matches_pivot($uid, $cid, "gate_pass_commercial_users")) {
            return true;
        }
        if ($stage === "security"
            && $this->Gate_pass_security_users_model->is_security_user($uid)
            && $this->_company_matches_pivot($uid, $cid, "gate_pass_security_users")) {
            return true;
        }
        if ($stage === "rop"
            && $this->Gate_pass_rop_users_model->is_rop_user($uid)
            && $this->_company_matches_pivot($uid, $cid, "gate_pass_rop_users")) {
            return true;
        }

        return false;
    }

    /**
     * Save approval/return/reject decision from portal request details (department user).
     */
    function save_approval()
    {
        $this->validate_submitted_data([
            "gate_pass_request_id" => "required|numeric",
            "decision" => "required"
        ]);

        $request_id = (int)$this->request->getPost("gate_pass_request_id");
        $decision = $this->request->getPost("decision"); // approved|rejected|returned
        $comment = trim((string)$this->request->getPost("comment"));

        if (in_array($decision, ["rejected", "returned"], true) && !$comment) {
            echo json_encode(["success" => false, "message" => app_lang("comment_required_for_return_reject")]);
            return;
        }

        $request = $this->Gate_pass_requests_model->get_one($request_id);
        if (!$request || $request->deleted) {
            echo json_encode(["success" => false, "message" => app_lang("record_not_found")]);
            return;
        }

        if (($request->status ?? "") === "draft") {
            echo json_encode(["success" => false, "message" => app_lang("request_not_awaiting_department")]);
            return;
        }

        if (($request->stage ?? "") !== "department") {
            echo json_encode(["success" => false, "message" => app_lang("request_not_awaiting_department")]);
            return;
        }

        if (!$this->_can_act_on_request($request)) {
            echo json_encode(["success" => false, "message" => app_lang("forbidden")]);
            return;
        }

        if ($request->status !== "submitted") {
            echo json_encode(["success" => false, "message" => app_lang("request_not_awaiting_department")]);
            return;
        }

        $approval_data = [
            "gate_pass_request_id" => $request_id,
            "stage" => "department",
            "decision" => $decision,
            "comment" => $comment,
            "decided_by" => $this->login_user->id,
            "decided_at" => get_current_utc_time(),
            "ip_address" => $this->request->getIPAddress(),
            "user_agent" => substr($this->request->getUserAgent()->getAgentString(), 0, 500),
        ];

        $save_id = $this->Gate_pass_request_approvals_model->ci_save($approval_data);
        if (!$save_id) {
            echo json_encode(["success" => false, "message" => app_lang("error_occurred")]);
            return;
        }

        $new_status = $decision === "approved" ? "department_approved" : $decision;
        $status_update = ["status" => $new_status];
        if ($decision === "approved") {
            $status_update["stage"] = "commercial";
            $status_update["stage_updated_at"] = get_current_utc_time();
        }
        $this->Gate_pass_requests_model->ci_save($status_update, $request_id);

        echo json_encode(["success" => true, "message" => app_lang("record_saved"), "id" => $request_id]);
    }

    /**
     * Placeholder for payment: marks request as commercial_approved.
     * To be replaced by payment gateway integration.
     */
    function save_payment()
    {
        $this->validate_submitted_data([
            "gate_pass_request_id" => "required|numeric"
        ]);

        $request_id = (int)$this->request->getPost("gate_pass_request_id");
        $request = $this->Gate_pass_requests_model->get_details(["id" => $request_id])->getRow();

        if (!$request || $request->deleted) {
            echo json_encode(["success" => false, "message" => app_lang("record_not_found")]);
            return;
        }

        if ((int)$request->requester_id !== (int)$this->login_user->id) {
            echo json_encode(["success" => false, "message" => app_lang("forbidden")]);
            return;
        }

        if ($request->status !== "department_approved") {
            echo json_encode(["success" => false, "message" => app_lang("request_not_awaiting_payment")]);
            return;
        }

        if (gate_pass_fee_waiver_pending($request)) {
            echo json_encode(["success" => false, "message" => app_lang("gate_pass_payment_blocked_waiver_pending")]);
            return;
        }

        if (!gate_pass_portal_can_pay_fee($request)) {
            echo json_encode(["success" => false, "message" => app_lang("request_not_awaiting_payment")]);
            return;
        }

        $update = ["status" => "commercial_approved", "stage" => "security"];
        $this->Gate_pass_requests_model->ci_save($update, $request_id);

        $approval_data = [
            "gate_pass_request_id" => $request_id,
            "stage" => "commercial",
            "decision" => "approved",
            "comment" => "Payment recorded (portal).",
            "decided_by" => $this->login_user->id,
            "decided_at" => get_current_utc_time(),
            "ip_address" => $this->request->getIPAddress(),
            "user_agent" => substr($this->request->getUserAgent()->getAgentString(), 0, 500),
        ];
        $this->Gate_pass_request_approvals_model->ci_save(gate_pass_clean_approval_data_for_save($approval_data));

        gate_pass_audit_log((int)$this->login_user->id, $request_id, "payment_recorded", app_lang("gate_pass_audit_detail_payment_recorded"));

        echo json_encode(["success" => true, "message" => app_lang("payment_recorded"), "id" => $request_id]);
    }

    // ---------- Visitors under request ----------
    function visitors_list_data($request_id = 0)
    {
        validate_numeric_value($request_id);

        $request = $this->Gate_pass_requests_model->get_details(["id" => $request_id])->getRow();
        if (!$request || $request->deleted) {
            echo json_encode(["data" => []]);
            return;
        }

        $is_owner = (int)$request->requester_id === (int)$this->login_user->id;
        $editable = $is_owner && gate_pass_requester_can_edit_request($request);

        $list_data = $this->Gate_pass_request_visitors_model->get_details([
            "gate_pass_request_id" => $request_id
        ])->getResult();

        $result = [];
        foreach ($list_data as $row) {
            $result[] = $this->_make_visitor_row($row, $editable);
        }

        echo json_encode(["data" => $result]);
    }

    function visitor_modal_form()
    {
        $this->validate_submitted_data([
            "id" => "numeric",
            "gate_pass_request_id" => "required|numeric"
        ]);

        $id = $this->request->getPost("id");
        $request_id = (int)$this->request->getPost("gate_pass_request_id");

        $request = $this->Gate_pass_requests_model->get_details(["id" => $request_id])->getRow();
        if (!$request || (int)$request->requester_id !== (int)$this->login_user->id) {
            app_redirect("forbidden");
        }

        if (!gate_pass_requester_can_edit_request($request)) {
            return $this->template->view("errors/html/error_general", [
                "heading" => app_lang("error"),
                "message" => app_lang("gate_pass_request_not_editable"),
            ]);
        }

        $model_info = $id ? $this->Gate_pass_request_visitors_model->get_details(["id" => $id])->getRow() : null;

        $view_data["model_info"] = $model_info;
        $view_data["gate_pass_request_id"] = $request_id;

        return $this->template->view("gate_pass_portal/requests/visitor_modal_form", $view_data);
    }

    function save_visitor()
    {
        $this->validate_submitted_data([
            "id" => "numeric",
            "gate_pass_request_id" => "required|numeric",
            "full_name" => "required",
            "phone" => "required",
            "nationality" => "required",
            "visitor_company" => "required",
            "role" => "required|in_list[visitor,driver,passenger]",
        ]);

        $id = $this->request->getPost("id");
        $request_id = (int)$this->request->getPost("gate_pass_request_id");

        $request = $this->Gate_pass_requests_model->get_details(["id" => $request_id])->getRow();
        if (!$request || (int)$request->requester_id !== (int)$this->login_user->id) {
            echo json_encode(["success" => false, "message" => "Forbidden"]);
            return;
        }

        if (!gate_pass_requester_can_edit_request($request)) {
            echo json_encode(["success" => false, "message" => app_lang("gate_pass_request_not_editable")]);
            return;
        }

        $existing = $id ? $this->Gate_pass_request_visitors_model->get_details(["id" => $id])->getRow() : null;

        $nationality = trim((string) $this->request->getPost("nationality"));
        $phone = trim((string) $this->request->getPost("phone"));
        $visitor_company = trim((string) $this->request->getPost("visitor_company"));
        $role = trim((string) $this->request->getPost("role"));

        if ($nationality === "" || $phone === "" || $visitor_company === "" || $role === "") {
            echo json_encode(["success" => false, "message" => app_lang("field_required")]);
            return;
        }

        if (!preg_match('/^\d+$/', $phone) || strlen($phone) < 6 || strlen($phone) > 20) {
            echo json_encode(["success" => false, "message" => app_lang("gate_pass_phone_digits_only")]);
            return;
        }

        $data = [
            "gate_pass_request_id" => $request_id,
            "full_name" => $this->request->getPost("full_name"),
            "id_type" => $this->request->getPost("id_type"),
            "id_number" => $this->request->getPost("id_number"),
            "nationality" => $nationality,
            "phone" => $phone,
            "visitor_company" => $visitor_company,
            "role" => $role,
            "is_primary" => $this->request->getPost("is_primary") ? 1 : 0
        ];

        $upload_dir_rel = "gate_pass_visitors/request_" . $request_id . "/";
        $upload_dir = WRITEPATH . "uploads/" . $upload_dir_rel;
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0775, true);
        }

        $attachment_fields = [
            "id_attachment_path",
            "visa_attachment_path",
            "photo_attachment_path",
            "driving_license_attachment_path"
        ];
        foreach ($attachment_fields as $field) {
            $file = $this->request->getFile($field);
            if ($file && $file->isValid() && !$file->hasMoved()) {
                $new_name = $field . "_" . uniqid("", true) . "." . $file->getExtension();
                $file->move($upload_dir, $new_name);
                $data[$field] = $upload_dir_rel . $new_name;
            } elseif ($existing && !empty($existing->{$field})) {
                $data[$field] = $existing->{$field};
            }
        }

        $idPath = trim((string) ($data["id_attachment_path"] ?? ""));
        if ($idPath === "") {
            echo json_encode(["success" => false, "message" => app_lang("gate_pass_attachments_required_submit")]);
            return;
        }

        $dlPath = trim((string) ($data["driving_license_attachment_path"] ?? ""));
        if ($role === "driver" && $dlPath === "") {
            echo json_encode(["success" => false, "message" => app_lang("gate_pass_driving_license_required_driver")]);
            return;
        }

        if ($role !== "driver") {
            $data["driving_license_attachment_path"] = "";
        }

        $data = clean_data($data);

        // if primary is set, clear other primaries for same request
        if ((int)$data["is_primary"] === 1) {
            $db = db_connect();
            $db->table("gate_pass_request_visitors")
               ->where("gate_pass_request_id", $request_id)
               ->update(["is_primary" => 0]);
        }

        $save_id = $this->Gate_pass_request_visitors_model->ci_save($data, $id);

        if ($save_id) {
            $saved_v = $this->Gate_pass_request_visitors_model->get_details(["id" => (int)$save_id])->getRow();
            $v_label = $saved_v ? trim((string)($saved_v->full_name ?? "")) : "";
            if ($v_label === "") {
                $v_label = app_lang("gate_pass_audit_unnamed_visitor");
            }
            gate_pass_audit_log(
                (int)$this->login_user->id,
                $request_id,
                "visitor_saved",
                sprintf(app_lang("gate_pass_audit_detail_visitor_saved"), $v_label)
            );
            echo json_encode([
                "success" => true,
                "data" => $this->_visitor_row_data($save_id),
                "id" => $save_id,
                "message" => app_lang("record_saved")
            ]);
        } else {
            echo json_encode(["success" => false, "message" => app_lang("error_occurred")]);
        }
    }

    function delete_visitor()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);
        $id = (int)$this->request->getPost("id");

        $row = $this->Gate_pass_request_visitors_model->get_details(["id" => $id])->getRow();
        if (!$row) {
            echo json_encode(["success" => false, "message" => "Not found"]);
            return;
        }

        $request = $this->Gate_pass_requests_model->get_details(["id" => $row->gate_pass_request_id])->getRow();
        if (!$request || (int)$request->requester_id !== (int)$this->login_user->id) {
            echo json_encode(["success" => false, "message" => "Forbidden"]);
            return;
        }

        if (!gate_pass_requester_can_edit_request($request)) {
            echo json_encode(["success" => false, "message" => app_lang("gate_pass_request_not_editable")]);
            return;
        }

        $this->Gate_pass_request_visitors_model->delete($id);
        echo json_encode(["success" => true, "message" => app_lang("record_deleted")]);
    }

    // ----- rows -----
    private function _request_row_data($id)
    {
        $row = $this->Gate_pass_requests_model->get_details(["id" => $id])->getRow();
        return $this->_make_request_row($row);
    }

    private function _visitor_row_data($id)
    {
        $row = $this->Gate_pass_request_visitors_model->get_details(["id" => $id])->getRow();
        return $this->_make_visitor_row($row);
    }

    private function _make_request_row($row)
    {
        $details_btn = "<a class='btn btn-sm gp-portal-btn-details' href='" . get_uri("gate_pass_portal/request_details/" . $row->id) . "' target='_blank'>"
            . "<i data-feather='eye' class='icon-14'></i> " . app_lang("details") . "</a>";

        $can_edit = gate_pass_requester_can_edit_request($row);
        $edit_btn = $can_edit
            ? modal_anchor(
                get_uri("gate_pass_portal/request_modal_form"),
                "<i data-feather='edit-2' class='icon-14'></i>",
                [
                    "class" => "btn btn-sm gp-portal-btn-edit edit",
                    "title" => app_lang("edit"),
                    "data-post-id" => $row->id,
                    "data-modal-title" => app_lang("gate_pass_portal_browser_title"),
                ]
            )
            : "";

        $status_badge = "<span class='gp-portal-status-badge gp-portal-status-" . preg_replace('/[^a-z0-9_]/', '_', strtolower($row->status ?? '')) . "'>"
            . esc(gate_pass_request_status_display($row)) . "</span>";

        $actions = "<div class='gp-portal-row-actions'>" . $details_btn . ($edit_btn ? " " . $edit_btn : "") . "</div>";

        $created_disp = gate_pass_request_created_display($row);

        return [
            $row->reference,
            $created_disp,
            $row->company_name ?: "-",
            $row->department_name ?: "-",
            $row->purpose_name ?: "-",
            $row->visit_from,
            $row->visit_to,
            $status_badge,
            $actions
        ];
    }

    private function _make_visitor_row($row, bool $editable = true)
    {
        $is_blocked = (int)($row->is_blocked ?? 0) === 1;
        $blocked_badge = $is_blocked
            ? "<span class='badge bg-danger'>" . app_lang("blocked") . "</span>"
            : "<span class='badge bg-success'>Clear</span>";
        $block_reason = trim((string)($row->block_reason ?? ""));

        $actions_cell = "-";
        if ($editable) {
            $edit = modal_anchor(
                get_uri("gate_pass_portal/visitor_modal_form"),
                "<i data-feather='edit' class='icon-16'></i>",
                ["class" => "edit", "title" => app_lang("edit"), "data-post-id" => $row->id, "data-post-gate_pass_request_id" => $row->gate_pass_request_id]
            );

            $delete = js_anchor(
                "<i data-feather='x' class='icon-16'></i>",
                [
                    "title" => app_lang("delete"),
                    "class" => "delete",
                    "data-id" => $row->id,
                    "data-action-url" => get_uri("gate_pass_portal/delete_visitor"),
                    "data-action" => "delete-confirmation"
                ]
            );

            $actions_cell = $edit . " " . $delete;
        }

        $primary = $row->is_primary ? "<span class='badge bg-success'>Primary</span>" : "";

        return [
            $row->full_name,
            $row->id_type ?: "-",
            $row->id_number ?: "-",
            $row->nationality ?: "-",
            $row->phone ?: "-",
            $row->role,
            $blocked_badge,
            $block_reason !== "" ? esc($block_reason) : "-",
            $primary,
            $actions_cell
        ];
    }


    function vehicles_list_data($request_id = 0)
    {
        validate_numeric_value($request_id);

        $request = $this->Gate_pass_requests_model->get_details(["id" => $request_id])->getRow();
        if (!$request || $request->deleted) {
            return $this->response->setJSON(["data" => []]);
        }

        $is_owner = (int)$request->requester_id === (int)$this->login_user->id;
        $reqType = strtolower(trim((string)($request->request_type ?? "both")));
        $vehiclesAllowed = ($reqType !== "person");
        $editable = $is_owner && gate_pass_requester_can_edit_request($request) && $vehiclesAllowed;

        $list_data = $this->Gate_pass_request_vehicles_model->get_details([
            "gate_pass_request_id" => $request_id
        ])->getResult();

        $result = [];
        foreach ($list_data as $row) {
            $result[] = $this->_make_vehicle_row($row, $editable);
        }

        return $this->response->setJSON(["data" => $result]);
    }


 


    function vehicle_modal_form()
    {
        $this->validate_submitted_data([
            "id" => "numeric",
            "gate_pass_request_id" => "required|numeric"
        ]);

        $id = $this->request->getPost("id");
        $request_id = (int)$this->request->getPost("gate_pass_request_id");

        $request = $this->Gate_pass_requests_model->get_details(["id" => $request_id])->getRow();
        if (!$request || (int)$request->requester_id !== (int)$this->login_user->id) {
            app_redirect("forbidden");
        }

        if (!gate_pass_requester_can_edit_request($request)) {
            return $this->template->view("errors/html/error_general", [
                "heading" => app_lang("error"),
                "message" => app_lang("gate_pass_request_not_editable"),
            ]);
        }

        $reqType = strtolower(trim((string)($request->request_type ?? "both")));
        if ($reqType === "person") {
            return $this->template->view("errors/html/error_general", [
                "heading" => app_lang("error"),
                "message" => app_lang("gate_pass_person_only_no_vehicles"),
            ]);
        }

        $model_info = $id ? $this->Gate_pass_request_vehicles_model->get_details(["id" => $id])->getRow() : null;

        $view_data["model_info"] = $model_info;
        $view_data["gate_pass_request_id"] = $request_id;

        return $this->template->view("gate_pass_portal/requests/vehicle_modal_form", $view_data);
    }


    function save_vehicle()
    {
        $this->validate_submitted_data([
            "id" => "numeric",
            "gate_pass_request_id" => "required|numeric",
            "plate_prefix" => "required",
            "plate_digits" => "required",
        ]);

        $id = $this->request->getPost("id");
        $request_id = (int)$this->request->getPost("gate_pass_request_id");

        $request = $this->Gate_pass_requests_model->get_details(["id" => $request_id])->getRow();
        if (!$request || (int)$request->requester_id !== (int)$this->login_user->id) {
            echo json_encode(["success" => false, "message" => "Forbidden"]);
            return;
        }

        if (!gate_pass_requester_can_edit_request($request)) {
            echo json_encode(["success" => false, "message" => app_lang("gate_pass_request_not_editable")]);
            return;
        }

        $reqType = strtolower(trim((string)($request->request_type ?? "both")));
        if ($reqType === "person") {
            echo json_encode(["success" => false, "message" => app_lang("gate_pass_person_only_no_vehicles")]);
            return;
        }

        $built = gate_pass_plate_merge_from_post_parts(
            (string) $this->request->getPost("plate_prefix"),
            (string) $this->request->getPost("plate_digits")
        );
        if (empty($built["ok"])) {
            echo json_encode(["success" => false, "message" => $built["message"] ?? app_lang("gate_pass_plate_invalid_chars")]);
            return;
        }
        $plate_in = $built["plate"];

        $existing = $id ? $this->Gate_pass_request_vehicles_model->get_details(["id" => $id])->getRow() : null;

        $data = [
            "gate_pass_request_id" => $request_id,
            "plate_no" => $plate_in,
            "make" => null,
            "model" => null,
            "color" => null,
        ];

        $upload_dir_rel = "gate_pass_vehicles/request_" . $request_id . "/";
        $upload_dir = WRITEPATH . "uploads/" . $upload_dir_rel;
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0775, true);
        }
        $mulFile = $this->request->getFile("mulkiyah_attachment_path");
        if ($mulFile && $mulFile->isValid() && !$mulFile->hasMoved()) {
            $new_name = "mulkiyah_" . uniqid("", true) . "." . $mulFile->getExtension();
            $mulFile->move($upload_dir, $new_name);
            $data["mulkiyah_attachment_path"] = $upload_dir_rel . $new_name;
        } elseif ($existing && !empty($existing->mulkiyah_attachment_path)) {
            $data["mulkiyah_attachment_path"] = $existing->mulkiyah_attachment_path;
        }

        $mergedForCheck = (object)array_merge(
            $existing ? (array)$existing : [],
            $data
        );
        $mulPath = gate_pass_vehicle_mulkiyah_path_value($mergedForCheck);
        if ($mulPath === "") {
            echo json_encode(["success" => false, "message" => app_lang("gate_pass_mulkiyah_required")]);
            return;
        }

        $data = clean_data($data);

        $save_id = $this->Gate_pass_request_vehicles_model->ci_save($data, $id);

        if ($save_id) {
            $saved_veh = $this->Gate_pass_request_vehicles_model->get_details(["id" => (int)$save_id])->getRow();
            $plate_disp = $saved_veh ? strtoupper(trim((string)($saved_veh->plate_no ?? ""))) : "";
            if ($plate_disp === "") {
                $plate_disp = app_lang("gate_pass_audit_unspecified_plate");
            }
            gate_pass_audit_log(
                (int)$this->login_user->id,
                $request_id,
                "vehicle_saved",
                sprintf(app_lang("gate_pass_audit_detail_vehicle_saved"), $plate_disp)
            );
            echo json_encode([
                "success" => true,
                "data" => $this->_vehicle_row_data($save_id),
                "id" => $save_id,
                "message" => app_lang("record_saved")
            ]);
        } else {
            echo json_encode(["success" => false, "message" => app_lang("error_occurred")]);
        }
    }



    function delete_vehicle()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);
        $id = (int)$this->request->getPost("id");

        $row = $this->Gate_pass_request_vehicles_model->get_details(["id" => $id])->getRow();
        if (!$row) {
            echo json_encode(["success" => false, "message" => "Not found"]);
            return;
        }

        $request = $this->Gate_pass_requests_model->get_details(["id" => $row->gate_pass_request_id])->getRow();
        if (!$request || (int)$request->requester_id !== (int)$this->login_user->id) {
            echo json_encode(["success" => false, "message" => "Forbidden"]);
            return;
        }

        if (!gate_pass_requester_can_edit_request($request)) {
            echo json_encode(["success" => false, "message" => app_lang("gate_pass_request_not_editable")]);
            return;
        }

        $this->Gate_pass_request_vehicles_model->delete($id);
        echo json_encode(["success" => true, "message" => app_lang("record_deleted")]);
    }

    private function _vehicle_row_data($id)
    {
        $row = $this->Gate_pass_request_vehicles_model->get_details(["id" => $id])->getRow();
        return $this->_make_vehicle_row($row);
    }

    private function _make_vehicle_row($row, bool $editable = true)
    {
        $actions_cell = "-";
        if ($editable) {
            $edit = modal_anchor(
                get_uri("gate_pass_portal/vehicle_modal_form"),
                "<i data-feather='edit' class='icon-16'></i>",
                ["class" => "edit", "title" => app_lang("edit"), "data-post-id" => $row->id, "data-post-gate_pass_request_id" => $row->gate_pass_request_id]
            );

            $delete = js_anchor(
                "<i data-feather='x' class='icon-16'></i>",
                [
                    "title" => app_lang("delete"),
                    "class" => "delete",
                    "data-id" => $row->id,
                    "data-action-url" => get_uri("gate_pass_portal/delete_vehicle"),
                    "data-action" => "delete-confirmation"
                ]
            );

            $actions_cell = $edit . " " . $delete;
        }

        $mulkiyah = !empty($row->mulkiyah_attachment_path)
            ? "<span class='badge bg-success'>" . app_lang("yes") . "</span>"
            : "<span class='badge bg-secondary'>" . app_lang("no") . "</span>";

        return [
            $row->plate_no ?: "-",
            $mulkiyah,
            $actions_cell
        ];
    }



    /**
     * Normalize gate pass visit_from / visit_to inputs.
     * Supports: YYYY-MM-DD (from datepicker), YYYY-MM-DDTHH:MM (datetime-local), and MySQL datetime.
     */
    private function _normalize_gate_pass_datetime($value, $edge = "start")
    {
        $value = trim((string) $value);
        if ($value === "") {
            return null;
        }

        // HTML datetime-local: 2026-02-08T13:30
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $value)) {
            $value = str_replace("T", " ", $value);
            if (strlen($value) === 16) { // YYYY-MM-DD HH:MM
                return $value . ":00";
            }
            return $value;
        }

        // MySQL datetime: 2026-02-08 13:30:00
        if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}$/', $value)) {
            return $value;
        }

        // Date only: 2026-02-08
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value . ($edge === "end" ? " 23:59:59" : " 00:00:00");
        }

        // Fallback
        $ts = strtotime($value);
        if ($ts === false) {
            return null;
        }
        return date("Y-m-d H:i:s", $ts);
    }

}
