<?php

namespace App\Controllers;

use App\Models\Gate_pass_requests_model;
use App\Models\Gate_pass_request_visitors_model;
use App\Models\Gate_pass_request_vehicles_model;
use App\Models\Gate_pass_request_approvals_model;
use App\Models\Gate_pass_department_users_model;
use App\Models\Gate_pass_fee_rules_model;
use App\Models\Gate_passes_model;

class Gate_pass_portal extends Security_Controller
{
    protected $Gate_pass_requests_model;
    protected $Gate_pass_request_visitors_model;
    protected $Gate_pass_request_vehicles_model;
    protected $Gate_pass_request_approvals_model;
    protected $Gate_pass_department_users_model;
    protected $Gate_passes_model;
    protected $Gate_pass_fee_rules_model;

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
        $this->Gate_pass_fee_rules_model = new Gate_pass_fee_rules_model();

        $this->Gate_passes_model = new Gate_passes_model();
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
        return $this->template->rander("gate_pass_portal/view", $view_data);
    }

    // ---------- TAB: Requests ----------
    function requests()
    {
        return $this->template->view("gate_pass_portal/requests/index");
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

        // Security: only owner can edit
        if ($model_info && (int)$model_info->requester_id !== (int)$this->login_user->id) {
            app_redirect("forbidden");
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
    
        // If editing, verify ownership
        if ($id) {
            $existing = $this->Gate_pass_requests_model->get_details(["id" => $id])->getRow();
            if (!$existing || (int)$existing->requester_id !== (int)$this->login_user->id) {
                echo json_encode(["success" => false, "message" => "Forbidden"]);
                return;
            }
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
        
        $data = [
            "requester_id" => $this->login_user->id,
            "company_id" => (int)$this->request->getPost("company_id"),
            "department_id" => $department_id,
            "gate_pass_purpose_id" => (int)$this->request->getPost("gate_pass_purpose_id"),
            "visit_from" => $visit_from,
            "visit_to" => $visit_to,
            "purpose_notes" => $this->request->getPost("purpose_notes"),
            "visit_type" => $this->request->getPost("visit_type") ?: "visitor",
            "request_type" => $this->request->getPost("request_type") ?: "both",
            "vehicle_type" => $this->request->getPost("vehicle_type") ?: "none",
            "currency" => $currency,
            "fee_amount" => $fee_amount,
            "status" => "submitted",
            "submitted_at" => get_current_utc_time(),
            "stage" => "department"
        ];
    
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
    
            // On CREATE only: save visitors + vehicles from same form
            if ($save_id && !$id) {
                $saved_visitors = 0;
                $saved_vehicles = 0;
    
                $visitors = $this->request->getPost("visitors");
                if (is_array($visitors)) {
                    foreach ($visitors as $idx => $v) {
                        $full = trim((string)($v["full_name"] ?? ""));
                        if ($full === "") continue;
    
                        $vdata = [
                            "gate_pass_request_id" => $save_id,
                            "full_name" => $full,
                            "id_type" => $v["id_type"] ?? null,
                            "id_number" => $v["id_number"] ?? null,
                            "nationality" => $v["nationality"] ?? null,
                            "phone" => $v["phone"] ?? null,
                            "visitor_company" => $v["visitor_company"] ?? null,
                            "role" => $v["role"] ?? "visitor",
                            "is_primary" => ($saved_visitors === 0 ? 1 : 0),
                        ];
                        $vdata = clean_data($vdata);
                        $visitor_save_id = $this->Gate_pass_request_visitors_model->ci_save($vdata);
                        if (!$visitor_save_id) {
                            throw new \RuntimeException("Failed to save visitor: " . $full);
                        }
                        $saved_visitors++;
                    }
                }
    
                $vehicles = $this->request->getPost("vehicles");
                if (is_array($vehicles)) {
                    foreach ($vehicles as $c) {
                        $plate = strtoupper(trim((string)($c["plate_no"] ?? "")));
                        if ($plate === "") continue;
                        $type = strtolower(trim((string)($c["type"] ?? "")));
                        if ($type === "" || $type === "none") {
                            $type = strtolower(trim((string)($this->request->getPost("vehicle_type") ?: "private")));
                        }
                        if ($type === "" || $type === "none") {
                            $type = "private";
                        }
    
                        $cdata = [
                            "gate_pass_request_id" => $save_id,
                            "plate_no" => $plate,
                            "type" => $type,
                            "make" => $c["make"] ?? null,
                            "model" => $c["model"] ?? null,
                            "color" => $c["color"] ?? null
                        ];
                        $cdata = clean_data($cdata);
                        $vehicle_save_id = $this->Gate_pass_request_vehicles_model->ci_save($cdata);
                        if (!$vehicle_save_id) {
                            throw new \RuntimeException("Failed to save vehicle: " . $plate);
                        }
                        $saved_vehicles++;
                    }
                }
    
                // Normalize request_type / vehicle_type based on what was entered
                $new_request_type = "person";
                if ($saved_visitors > 0 && $saved_vehicles > 0) $new_request_type = "both";
                elseif ($saved_vehicles > 0) $new_request_type = "vehicle";
    
                $posted_vehicle_type = strtolower(trim((string)$this->request->getPost("vehicle_type")));
                if ($saved_vehicles > 0) {
                    $new_vehicle_type = ($posted_vehicle_type !== "" && $posted_vehicle_type !== "none") ? $posted_vehicle_type : "private";
                } else {
                    $new_vehicle_type = "none";
                }
    
                $update_request_data = [
                    "request_type" => $new_request_type,
                    "vehicle_type" => $new_vehicle_type
                ];
                $this->Gate_pass_requests_model->ci_save($update_request_data, $save_id);
            }
    
            if ($db->transStatus() === false) {
                throw new \RuntimeException("Transaction failed");
            }
    
            $db->transCommit();
    
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

        $view_data["request"] = $request;
        $view_data["is_requester"] = (int)$request->requester_id === (int)$this->login_user->id;
   
        $view_data["approval_history"] = $this->Gate_pass_request_approvals_model
            ->get_details(["gate_pass_request_id" => $request->id])
            ->getResult();

        $view_data["gate_pass"] = null;
        if ($request->status === "rop_approved") {
            $view_data["gate_pass"] = $this->Gate_passes_model->get_by_request_id($request->id);
        }

        $view_data["status_label"] = $this->_format_gate_pass_status($request->status ?? "");

        return $this->template->rander("gate_pass_portal/requests/details", $view_data);
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
        $this->response->setHeader("Content-Type", "image/png");
        $this->response->setHeader("Content-Disposition", "attachment; filename=\"gate-pass-qr-" . (int)$request_id . ".png\"");
        return $this->response->setBody($img);
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

        // Open: any gate pass user can submit approval (no role check)
        if ($request->status !== "submitted" && $request->status !== "returned") {
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

        $update = ["status" => "commercial_approved", "stage" => "security"];
        $this->Gate_pass_requests_model->ci_save($update, $request_id);

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
        // Open: any gate pass user can see visitors (no role check)

        $list_data = $this->Gate_pass_request_visitors_model->get_details([
            "gate_pass_request_id" => $request_id
        ])->getResult();

        $result = [];
        foreach ($list_data as $row) {
            $result[] = $this->_make_visitor_row($row);
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
            "full_name" => "required"
        ]);

        $id = $this->request->getPost("id");
        $request_id = (int)$this->request->getPost("gate_pass_request_id");

        $request = $this->Gate_pass_requests_model->get_details(["id" => $request_id])->getRow();
        if (!$request || (int)$request->requester_id !== (int)$this->login_user->id) {
            echo json_encode(["success" => false, "message" => "Forbidden"]);
            return;
        }

        $existing = $id ? $this->Gate_pass_request_visitors_model->get_details(["id" => $id])->getRow() : null;

        $data = [
            "gate_pass_request_id" => $request_id,
            "full_name" => $this->request->getPost("full_name"),
            "id_type" => $this->request->getPost("id_type"),
            "id_number" => $this->request->getPost("id_number"),
            "nationality" => $this->request->getPost("nationality"),
            "phone" => $this->request->getPost("phone"),
            "visitor_company" => $this->request->getPost("visitor_company"),
            "role" => $this->request->getPost("role") ?: "visitor",
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

        $edit_btn = modal_anchor(
            get_uri("gate_pass_portal/request_modal_form"),
            "<i data-feather='edit-2' class='icon-14'></i>",
            ["class" => "btn btn-sm gp-portal-btn-edit edit", "title" => app_lang("edit"), "data-post-id" => $row->id]
        );

        $status_badge = "<span class='gp-portal-status-badge gp-portal-status-" . preg_replace('/[^a-z0-9_]/', '_', strtolower($row->status ?? '')) . "'>"
            . esc($this->_format_gate_pass_status($row->status ?? "")) . "</span>";

        $actions = "<div class='gp-portal-row-actions'>" . $details_btn . $edit_btn . "</div>";

        return [
            $row->reference,
            $row->company_name ?: "-",
            $row->department_name ?: "-",
            $row->purpose_name ?: "-",
            $row->visit_from,
            $row->visit_to,
            $status_badge,
            $actions
        ];
    }

    private function _make_visitor_row($row)
    {
        $is_blocked = (int)($row->is_blocked ?? 0) === 1;
        $blocked_badge = $is_blocked
            ? "<span class='badge bg-danger'>" . app_lang("blocked") . "</span>"
            : "<span class='badge bg-success'>Clear</span>";
        $block_reason = trim((string)($row->block_reason ?? ""));

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
            $edit . " " . $delete
        ];
    }


    function vehicles_list_data($request_id = 0)
    {
        validate_numeric_value($request_id);

        $request = $this->Gate_pass_requests_model->get_details(["id" => $request_id])->getRow();
        if (!$request || $request->deleted) {
            return $this->response->setJSON(["data" => []]);
        }
        // Open: any gate pass user can see vehicles (no role check)

        $list_data = $this->Gate_pass_request_vehicles_model->get_details([
            "gate_pass_request_id" => $request_id
        ])->getResult();

        $result = [];
        foreach ($list_data as $row) {
            $result[] = $this->_make_vehicle_row($row);
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
            "plate_no" => "required"
        ]);

        $id = $this->request->getPost("id");
        $request_id = (int)$this->request->getPost("gate_pass_request_id");

        $request = $this->Gate_pass_requests_model->get_details(["id" => $request_id])->getRow();
        if (!$request || (int)$request->requester_id !== (int)$this->login_user->id) {
            echo json_encode(["success" => false, "message" => "Forbidden"]);
            return;
        }

        $data = [
            "gate_pass_request_id" => $request_id,
            "plate_no" => strtoupper(trim((string)$this->request->getPost("plate_no"))),
            "make" => $this->request->getPost("make"),
            "model" => $this->request->getPost("model"),
            "color" => $this->request->getPost("color")
        ];

        $data = clean_data($data);

        $save_id = $this->Gate_pass_request_vehicles_model->ci_save($data, $id);

        if ($save_id) {
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

        $this->Gate_pass_request_vehicles_model->delete($id);
        echo json_encode(["success" => true, "message" => app_lang("record_deleted")]);
    }

    private function _vehicle_row_data($id)
    {
        $row = $this->Gate_pass_request_vehicles_model->get_details(["id" => $id])->getRow();
        return $this->_make_vehicle_row($row);
    }

    private function _make_vehicle_row($row)
    {
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

        return [
            $row->plate_no ?: "-",
            $row->make ?: "-",
            $row->model ?: "-",
            $row->color ?: "-",
            $edit . " " . $delete
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

    /**
     * Format gate pass status for display (avoids dependency on general_helper on production).
     */
    private function _format_gate_pass_status($status, $empty_value = "-")
    {
        $status = strtolower(trim((string) $status));
        if ($status === "" || $status === "-") {
            return $empty_value;
        }
        $lang_key = "gate_pass_status_" . $status;
        $translated = app_lang($lang_key);
        if ($translated && $translated !== $lang_key) {
            return $translated;
        }
        if ($status === "rop_approved") {
            return "ROP Approved";
        }
        return ucwords(str_replace("_", " ", $status));
    }

}
