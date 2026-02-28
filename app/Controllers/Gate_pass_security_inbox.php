<?php

namespace App\Controllers;

use App\Models\Gate_pass_requests_model;
use App\Models\Gate_pass_security_users_model;
use App\Models\Gate_pass_request_approvals_model;
use App\Models\Gate_pass_reasons_model;


// ✅ missing
use App\Models\Gate_passes_model;
use App\Models\Gate_pass_request_visitors_model;
use App\Models\Gate_pass_request_vehicles_model;
use App\Models\Gate_pass_scan_log_model;

class Gate_pass_security_inbox extends Security_Controller
{
    protected $Gate_pass_requests_model;
    protected $Gate_pass_security_users_model;
    protected $Gate_pass_request_approvals_model;
    protected $Gate_pass_reasons_model;
    protected $Gate_passes_model;
    protected $Gate_pass_request_visitors_model;
    protected $Gate_pass_request_vehicles_model;
    protected $Gate_pass_scan_log_model;

    public function __construct()
    {
        parent::__construct();
        $this->access_only_team_members();

        $this->Gate_pass_requests_model = new Gate_pass_requests_model();
        $this->Gate_pass_security_users_model = new Gate_pass_security_users_model();
        $this->Gate_pass_request_approvals_model = new Gate_pass_request_approvals_model();
        $this->Gate_pass_reasons_model = new Gate_pass_reasons_model();
        $this->Gate_passes_model = new Gate_passes_model();
        $this->Gate_pass_request_visitors_model = new Gate_pass_request_visitors_model();
        $this->Gate_pass_request_vehicles_model = new Gate_pass_request_vehicles_model();
        $this->Gate_pass_scan_log_model = new Gate_pass_scan_log_model();

        if (!$this->login_user->is_admin && !$this->Gate_pass_security_users_model->is_security_user($this->login_user->id)) {
            app_redirect("forbidden");
        }
    }

    public function index()
    {
        return $this->template->rander("gate_pass_security_inbox/index");
    }

    public function scan()
{
    return $this->template->rander("gate_pass_security_inbox/scan");
}


public function lookup_by_qr()
{
    $this->validate_submitted_data([
        "qr_text" => "required"
    ]);

    $raw = trim((string) $this->request->getPost("qr_text"));
    $token = $this->_extract_qr_token($raw);

    if (!$token) {
        return $this->response->setJSON(["success" => false, "message" => "Invalid QR data."]);
    }

    $gate_pass = $this->Gate_passes_model->get_by_qr_token($token);
    if (!$gate_pass) {
        return $this->response->setJSON(["success" => false, "message" => "Gate pass not found."]);
    }

    $request = $this->Gate_pass_requests_model
        ->get_details(["id" => (int) $gate_pass->gate_pass_request_id])
        ->getRow();

    if (!$request || (int)$request->deleted === 1) {
        return $this->response->setJSON(["success" => false, "message" => "Request not found."]);
    }

    if (!$this->_can_act_on_request($request)) {
        return $this->response->setJSON(["success" => false, "message" => app_lang("forbidden")]);
    }

    // Recommended constraints: only issued/rop approved passes + active + within validity
    if (($request->stage ?? "") !== "issued") {
        return $this->response->setJSON(["success" => false, "message" => "This request is not issued yet."]);
    }

    if (($gate_pass->status ?? "") !== "active") {
        return $this->response->setJSON(["success" => false, "message" => "Gate pass is not active."]);
    }

    $now = time();
    $valid_from = $gate_pass->valid_from ? strtotime($gate_pass->valid_from) : null;
    $valid_to   = $gate_pass->valid_to ? strtotime($gate_pass->valid_to) : null;

    if ($valid_from && $now < $valid_from) {
        return $this->response->setJSON(["success" => false, "message" => "Gate pass is not valid yet."]);
    }
    if ($valid_to && $now > $valid_to) {
        return $this->response->setJSON(["success" => false, "message" => "Gate pass has expired."]);
    }

    $visitor_rows = $this->Gate_pass_request_visitors_model
        ->get_details(["gate_pass_request_id" => (int)$request->id])
        ->getResult();
    $blocked_visitors = [];
    $blocked_reasons = [];
    foreach ($visitor_rows as $vr) {
        if ((int)($vr->is_blocked ?? 0) === 1) {
            $blocked_visitors[] = $vr;
            $reason = trim((string)($vr->block_reason ?? ""));
            if ($reason !== "") {
                $blocked_reasons[] = $reason;
            }
        }
    }
    $blocked_visitors_count = count($blocked_visitors);
    $blocked_reasons = array_values(array_unique($blocked_reasons));

    return $this->response->setJSON([
        "success" => true,
        "data" => [
            "gate_pass_id" => (int)$gate_pass->id,
            "request_id" => (int)$request->id,
            "gate_pass_no" => $gate_pass->gate_pass_no,
            "reference" => $request->reference,

            "company" => $request->company_name ?? "-",
            "department" => $request->department_name ?? "-",
            "purpose" => $request->purpose_title ?? "-",

            "visit_from" => $request->visit_from ? format_to_datetime($request->visit_from) : "-",
            "visit_to" => $request->visit_to ? format_to_datetime($request->visit_to) : "-",

            "fee_amount" => (string)$request->fee_amount,
            "currency" => $request->currency,
            "fee_is_waived" => (int)$request->fee_is_waived,
            "fee_waived_reason" => $request->fee_waived_reason,

            "status" => $request->status,
            "status_label" => $this->_format_gate_pass_status($request->status),
            "valid_from" => $gate_pass->valid_from ? format_to_datetime($gate_pass->valid_from) : "-",
            "valid_to" => $gate_pass->valid_to ? format_to_datetime($gate_pass->valid_to) : "-",
            "blocked_visitors_count" => $blocked_visitors_count,
            "blocked_reasons" => $blocked_reasons,
        ]
    ]);
}

private function _extract_qr_token(string $raw): string
{
    $raw = trim($raw);
    if ($raw === "") {
        return "";
    }

    // If scanner returns a URL
    if (filter_var($raw, FILTER_VALIDATE_URL)) {
        $parts = parse_url($raw);

        if (!empty($parts["query"])) {
            parse_str($parts["query"], $q);

            foreach (["qr_token", "token", "data"] as $key) {
                if (!empty($q[$key])) {
                    $candidate = trim((string)$q[$key]);
                    if ($this->_is_valid_qr_token($candidate)) {
                        return strtolower($candidate);
                    }
                }
            }
        }

        // last URL segment
        if (!empty($parts["path"])) {
            $seg = explode("/", trim($parts["path"], "/"));
            $last = trim((string)end($seg));
            if ($this->_is_valid_qr_token($last)) {
                return strtolower($last);
            }
        }

        return "";
    }

    // plain token
    return $this->_is_valid_qr_token($raw) ? strtolower($raw) : "";
}

private function _is_valid_qr_token(string $token): bool
{
    // your tokens are 64-hex (sha256-like)
    return (bool) preg_match('/^[a-f0-9]{64}$/i', $token);
}



public function vehicles_list_data($request_id = 0)
{
    $request_id = (int)$request_id;
    $request = $this->Gate_pass_requests_model->get_details(["id" => $request_id])->getRow();

    if (!$request || (int)$request->deleted === 1 || !$this->_can_act_on_request($request)) {
        return $this->response->setJSON(["data" => []]);
    }

    $list = $this->Gate_pass_request_vehicles_model
        ->get_details(["gate_pass_request_id" => $request_id])
        ->getResult();

    $rows = [];
    foreach ($list as $row) {
        $rows[] = $this->_make_vehicle_row($row);
    }

    return $this->response->setJSON(["data" => $rows]);
}

private function _make_vehicle_row($row)
{
    $edit = modal_anchor(
        get_uri("gate_pass_security_inbox/vehicle_modal_form"),
        "<i data-feather='edit' class='icon-16'></i>",
        [
            "class" => "edit",
            "title" => app_lang("edit"),
            "data-post-id" => $row->id,
            "data-post-gate_pass_request_id" => $row->gate_pass_request_id
        ]
    );

    $delete = js_anchor(
        "<i data-feather='x' class='icon-16'></i>",
        [
            "title" => app_lang("delete"),
            "class" => "delete",
            "data-id" => $row->id,
            "data-action-url" => get_uri("gate_pass_security_inbox/delete_vehicle"),
            "data-action" => "delete-confirmation"
        ]
    );

    return [
        $row->plate_no ?: "-",
        $row->type ?: "-",
        $row->make ?: "-",
        $row->model ?: "-",
        $row->color ?: "-",
        $edit . " " . $delete
    ];
}

public function vehicle_modal_form()
{
    $this->validate_submitted_data([
        "gate_pass_request_id" => "required|numeric",
        "id" => "numeric"
    ]);

    $request_id = (int)$this->request->getPost("gate_pass_request_id");
    $id = (int)$this->request->getPost("id");

    $request = $this->Gate_pass_requests_model->get_details(["id" => $request_id])->getRow();
    if (!$request || !$this->_can_act_on_request($request)) {
        app_redirect("forbidden");
    }

    $model_info = $id
        ? $this->Gate_pass_request_vehicles_model->get_details(["id" => $id])->getRow()
        : null;

    return $this->template->view("gate_pass_security_inbox/vehicle_modal_form", [
        "model_info" => $model_info,
        "gate_pass_request_id" => $request_id
    ]);
}

public function save_vehicle()
{
    $this->validate_submitted_data([
        "id" => "numeric",
        "gate_pass_request_id" => "required|numeric",
        "plate_no" => "required"
    ]);

    $id = (int)$this->request->getPost("id");
    $request_id = (int)$this->request->getPost("gate_pass_request_id");

    $request = $this->Gate_pass_requests_model->get_details(["id" => $request_id])->getRow();
    if (!$request || !$this->_can_act_on_request($request)) {
        return $this->response->setJSON(["success" => false, "message" => app_lang("forbidden")]);
    }

    $allowed_vehicle_types = ["private", "commercial", "truck", "bus", "other"];
    $vehicle_type = strtolower(trim((string)$this->request->getPost("type")));
    if (!in_array($vehicle_type, $allowed_vehicle_types, true)) {
        $vehicle_type = "private";
    }

    $data = clean_data([
        "gate_pass_request_id" => $request_id,
        "plate_no" => strtoupper(trim((string)$this->request->getPost("plate_no"))),
        "type" => $vehicle_type,
        "make" => $this->request->getPost("make"),
        "model" => $this->request->getPost("model"),
        "color" => $this->request->getPost("color"),
    ]);

    $save_id = $this->Gate_pass_request_vehicles_model->ci_save($data, $id);

    if ($save_id) {
        $row = $this->Gate_pass_request_vehicles_model->get_details(["id" => $save_id])->getRow();
        return $this->response->setJSON([
            "success" => true,
            "data" => $this->_make_vehicle_row($row),
            "id" => $save_id,
            "message" => app_lang("record_saved")
        ]);
    }

    return $this->response->setJSON(["success" => false, "message" => app_lang("error_occurred")]);
}

public function delete_vehicle()
{
    $this->validate_submitted_data(["id" => "required|numeric"]);
    $id = (int)$this->request->getPost("id");

    $row = $this->Gate_pass_request_vehicles_model->get_details(["id" => $id])->getRow();
    if (!$row) {
        return $this->response->setJSON(["success" => false, "message" => app_lang("record_not_found")]);
    }

    $request = $this->Gate_pass_requests_model->get_details(["id" => (int)$row->gate_pass_request_id])->getRow();
    if (!$request || !$this->_can_act_on_request($request)) {
        return $this->response->setJSON(["success" => false, "message" => app_lang("forbidden")]);
    }

    $this->Gate_pass_request_vehicles_model->delete($id);
    return $this->response->setJSON(["success" => true, "message" => app_lang("record_deleted")]);
}



public function save_scan_action()
{
    $this->validate_submitted_data([
        "gate_pass_id" => "required|numeric",
        "action" => "required"
    ]);

    $gate_pass_id = (int)$this->request->getPost("gate_pass_id");
    $action = strtolower(trim((string)$this->request->getPost("action")));
    if (!in_array($action, ["entry", "exit", "check"], true)) {
        $action = "check";
    }
    $note = trim((string)$this->request->getPost("note"));

    $gate_pass = $this->Gate_passes_model->get_one($gate_pass_id);
    if (!$gate_pass || (int)$gate_pass->deleted === 1) {
        return $this->response->setJSON(["success" => false, "message" => "Gate pass not found."]);
    }

    $request = $this->Gate_pass_requests_model->get_details(["id" => (int)$gate_pass->gate_pass_request_id])->getRow();
    if (!$request || (int)$request->deleted === 1 || !$this->_can_act_on_request($request)) {
        return $this->response->setJSON(["success" => false, "message" => app_lang("forbidden")]);
    }

    $request_id = (int)$request->id;
    $recorded_at = get_current_utc_time();

    // Resolve security_user_id (id from pod_gate_pass_security_users for current user)
    $security_user_id = null;
    $assignments = $this->Gate_pass_security_users_model->get_user_assignments($this->login_user->id)->getResult();
    if (!empty($assignments)) {
        foreach ($assignments as $a) {
            if ((int)$a->company_id === (int)$request->company_id) {
                $security_user_id = (int)$a->id;
                break;
            }
        }
        if ($security_user_id === null) {
            $security_user_id = (int)$assignments[0]->id;
        }
    }

    $scan_log_data = [
        "gate_pass_request_id" => $request_id,
        "gate_pass_id" => $gate_pass_id,
        "security_user_id" => $security_user_id,
        "action" => $action,
        "note" => $note !== "" ? $note : null,
        "recorded_at" => $recorded_at,
        "performed_by" => (int)$this->login_user->id,
        "ip_address" => $this->request->getIPAddress(),
        "user_agent" => substr($this->request->getUserAgent()->getAgentString(), 0, 500),
        "created_at" => $recorded_at,
    ];

    $log_saved = $this->Gate_pass_scan_log_model->ci_save($scan_log_data);

    $meta = [];
    if (!empty($gate_pass->meta)) {
        $decoded = json_decode($gate_pass->meta, true);
        if (is_array($decoded)) $meta = $decoded;
    }

    $meta["security"] = $meta["security"] ?? [];
    $meta["security"]["scan_count"] = (int)($meta["security"]["scan_count"] ?? 0) + 1;
    $meta["security"]["last_scan_at"] = $recorded_at;
    $meta["security"]["last_scan_by"] = (int)$this->login_user->id;

    $meta["security"]["logs"] = $meta["security"]["logs"] ?? [];
    $meta["security"]["logs"][] = [
        "action" => $action,
        "note" => $note,
        "by" => (int)$this->login_user->id,
        "at" => $recorded_at,
        "ip" => $this->request->getIPAddress()
    ];

    $gate_pass_update = [
        "meta" => json_encode($meta),
        "updated_at" => $recorded_at,
    ];
    $ok = $this->Gate_passes_model->ci_save($gate_pass_update, $gate_pass_id);

    return $this->response->setJSON([
        "success" => (bool)($log_saved && $ok),
        "message" => ($log_saved && $ok) ? app_lang("record_saved") : app_lang("error_occurred")
    ]);
}



public function request_edit_modal_form()
{
    $this->validate_submitted_data([
        "request_id" => "required|numeric"
    ]);

    $request_id = (int)$this->request->getPost("request_id");
    $request = $this->Gate_pass_requests_model->get_details(["id" => $request_id])->getRow();

    if (!$request || (int)$request->deleted === 1 || !$this->_can_act_on_request($request)) {
        app_redirect("forbidden");
    }

    return $this->template->view("gate_pass_security_inbox/request_edit_modal_form", [
        "model_info" => $request
    ]);
}



public function save_request_patch()
{
    $this->validate_submitted_data([
        "request_id" => "required|numeric",
        "visit_from" => "required",
        "visit_to" => "required",
    ]);

    $request_id = (int)$this->request->getPost("request_id");
    $request = $this->Gate_pass_requests_model->get_details(["id" => $request_id])->getRow();

    if (!$request || (int)$request->deleted === 1 || !$this->_can_act_on_request($request)) {
        return $this->response->setJSON(["success" => false, "message" => app_lang("forbidden")]);
    }

    $visit_from = trim((string)$this->request->getPost("visit_from")); // YYYY-MM-DD
    $visit_to   = trim((string)$this->request->getPost("visit_to"));   // YYYY-MM-DD
    $notes      = $this->request->getPost("purpose_notes");

    $from_dt = $visit_from . " 00:00:00";
    $to_dt   = $visit_to . " 23:59:59";

    $this->Gate_pass_requests_model->ci_save([
        "visit_from" => $from_dt,
        "visit_to" => $to_dt,
        "purpose_notes" => $notes,
        "updated_at" => get_current_utc_time()
    ], $request_id);

    // Keep gate_pass validity in sync (if exists)
    $gp = $this->Gate_passes_model->get_by_request_id($request_id);
    if ($gp) {
        $this->Gate_passes_model->ci_save([
            "valid_from" => $from_dt,
            "valid_to" => $to_dt,
            "updated_at" => get_current_utc_time()
        ], (int)$gp->id);
    }

    return $this->response->setJSON([
        "success" => true,
        "message" => app_lang("record_saved")
    ]);
}



public function visitors_list_data($request_id = 0)
{
    $request_id = (int)$request_id;
    $request = $this->Gate_pass_requests_model->get_details(["id" => $request_id])->getRow();

    if (!$request || (int)$request->deleted === 1 || !$this->_can_act_on_request($request)) {
        return $this->response->setJSON(["data" => []]);
    }

    $list = $this->Gate_pass_request_visitors_model
        ->get_details(["gate_pass_request_id" => $request_id])
        ->getResult();

    $rows = [];
    foreach ($list as $row) {
        $rows[] = $this->_make_visitor_row($row);
    }

    return $this->response->setJSON(["data" => $rows]);
}

private function _make_visitor_row($row)
{
    $is_blocked = (int)($row->is_blocked ?? 0) === 1;
    $block_reason = trim((string)($row->block_reason ?? ""));
    $blocked_badge = $is_blocked
        ? "<span class='badge bg-danger'>Blocked</span>"
        : "<span class='badge bg-success'>Clear</span>";

    $edit = modal_anchor(
        get_uri("gate_pass_security_inbox/visitor_modal_form"),
        "<i data-feather='edit' class='icon-16'></i>",
        [
            "class" => "edit",
            "title" => app_lang("edit"),
            "data-post-id" => $row->id,
            "data-post-gate_pass_request_id" => $row->gate_pass_request_id
        ]
    );

    $delete = js_anchor(
        "<i data-feather='x' class='icon-16'></i>",
        [
            "title" => app_lang("delete"),
            "class" => "delete",
            "data-id" => $row->id,
            "data-action-url" => get_uri("gate_pass_security_inbox/delete_visitor"),
            "data-action" => "delete-confirmation"
        ]
    );

    return [
        $row->full_name ?: "-",
        $row->id_type ?: "-",
        $row->id_number ?: "-",
        $row->nationality ?: "-",
        $row->phone ?: "-",
        $row->role ?: "-",
        $blocked_badge,
        $block_reason !== "" ? esc($block_reason) : "-",
        $edit . " " . $delete
    ];
}


public function visitor_modal_form()
{
    $this->validate_submitted_data([
        "gate_pass_request_id" => "required|numeric",
        "id" => "numeric"
    ]);

    $request_id = (int)$this->request->getPost("gate_pass_request_id");
    $id = (int)$this->request->getPost("id");

    $request = $this->Gate_pass_requests_model->get_details(["id" => $request_id])->getRow();
    if (!$request || !$this->_can_act_on_request($request)) {
        app_redirect("forbidden");
    }

    $model_info = $id
        ? $this->Gate_pass_request_visitors_model->get_details(["id" => $id])->getRow()
        : null;

    return $this->template->view("gate_pass_security_inbox/visitor_modal_form", [
        "model_info" => $model_info,
        "gate_pass_request_id" => $request_id
    ]);
}

public function save_visitor()
{
    $this->validate_submitted_data([
        "id" => "numeric",
        "gate_pass_request_id" => "required|numeric",
        "full_name" => "required"
    ]);

    $id = (int)$this->request->getPost("id");
    $request_id = (int)$this->request->getPost("gate_pass_request_id");

    $request = $this->Gate_pass_requests_model->get_details(["id" => $request_id])->getRow();
    if (!$request || !$this->_can_act_on_request($request)) {
        return $this->response->setJSON(["success" => false, "message" => app_lang("forbidden")]);
    }

    $is_primary = $this->request->getPost("is_primary") ? 1 : 0;

    $data = clean_data([
        "gate_pass_request_id" => $request_id,
        "full_name" => $this->request->getPost("full_name"),
        "id_type" => $this->request->getPost("id_type"),
        "id_number" => $this->request->getPost("id_number"),
        "nationality" => $this->request->getPost("nationality"),
        "phone" => $this->request->getPost("phone"),
        "visitor_company" => $this->request->getPost("visitor_company"),
        "role" => $this->request->getPost("role") ?: "visitor",
        "is_primary" => $is_primary,
    ]);

    if ($is_primary === 1) {
        $db = db_connect();
        $db->table("gate_pass_request_visitors")
            ->where("gate_pass_request_id", $request_id)
            ->update(["is_primary" => 0]);
    }

    $save_id = $this->Gate_pass_request_visitors_model->ci_save($data, $id);

    if ($save_id) {
        $row = $this->Gate_pass_request_visitors_model->get_details(["id" => $save_id])->getRow();
        return $this->response->setJSON([
            "success" => true,
            "data" => $this->_make_visitor_row($row),
            "id" => $save_id,
            "message" => app_lang("record_saved")
        ]);
    }

    return $this->response->setJSON(["success" => false, "message" => app_lang("error_occurred")]);
}

public function delete_visitor()
{
    $this->validate_submitted_data(["id" => "required|numeric"]);
    $id = (int)$this->request->getPost("id");

    $row = $this->Gate_pass_request_visitors_model->get_details(["id" => $id])->getRow();
    if (!$row) {
        return $this->response->setJSON(["success" => false, "message" => app_lang("record_not_found")]);
    }

    $request = $this->Gate_pass_requests_model->get_details(["id" => (int)$row->gate_pass_request_id])->getRow();
    if (!$request || !$this->_can_act_on_request($request)) {
        return $this->response->setJSON(["success" => false, "message" => app_lang("forbidden")]);
    }

    $this->Gate_pass_request_visitors_model->delete($id);
    return $this->response->setJSON(["success" => true, "message" => app_lang("record_deleted")]);
}




    public function list_data()
    {
        // Show all requests in security stage regardless of status
        $options = [
            "stage" => "security",
        ];

        if (!$this->login_user->is_admin) {
            $assignments = $this->Gate_pass_security_users_model->get_user_assignments($this->login_user->id)->getResult();
            $company_ids = [];
            foreach ($assignments as $a) {
                $company_ids[] = (int)$a->company_id;
            }
            if (empty($company_ids)) {
                echo json_encode(["data" => []]);
                return;
            }
            $options["company_ids"] = $company_ids;
        }

        $list = $this->Gate_pass_requests_model->get_details($options)->getResult();
        $result = [];
        foreach ($list as $data) {
            $result[] = $this->_make_row($data);
        }
        echo json_encode(["data" => $result]);
    }

    private function _make_row($data)
    {
        $view_btn = anchor(
            get_uri("gate_pass_security_inbox/details/" . $data->id),
            "<i data-feather='eye' class='icon-16'></i>",
            ["class" => "btn btn-default btn-sm", "title" => app_lang("view")]
        );

        $review_btn = modal_anchor(
            get_uri("gate_pass_security_inbox/approval_modal_form"),
            "<i data-feather='check-square' class='icon-16'></i> " . app_lang("review"),
            [
                "class" => "btn btn-primary btn-sm",
                "title" => app_lang("review"),
                "data-post-id" => $data->id,
            ]
        );

        $requester_name = trim(($data->requester_first_name ?? '') . ' ' . ($data->requester_last_name ?? ''));
        if ($requester_name === '') {
            $requester_name = $data->requester_name ?? '-';
        }

        return [
            $data->reference ?? "-",
            $data->company_name ?? "-",
            $data->department_name ?? "-",
            $requester_name,
            ($data->requester_phone ?? '') ?: '-',
            $data->visit_from ? format_to_datetime($data->visit_from) : "-",
            $data->visit_to ? format_to_datetime($data->visit_to) : "-",
            $this->_format_gate_pass_status($data->status ?? ""),
            $view_btn . " " . $review_btn,
        ];
    }

    public function details($id = 0)
    {
        $id = (int)$id;
        if (!$id) app_redirect("forbidden");

        $request = $this->Gate_pass_requests_model->get_details(["id" => $id])->getRow();
        if (!$request || $request->deleted) {
            app_redirect("forbidden");
        }
        if (!$this->_can_act_on_request($request)) {
            app_redirect("forbidden");
        }
        if ($request->stage !== "security") {
            app_redirect("forbidden");
        }

        $view_data["request"] = $request;
        $view_data["approval_history"] = $this->Gate_pass_request_approvals_model
            ->get_details(["gate_pass_request_id" => $request->id])
            ->getResult();

        $view_data["status_label"] = $this->_format_gate_pass_status($request->status ?? "");

        return $this->template->rander("gate_pass_security_inbox/details", $view_data);
    }

    public function approval_history_modal()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);
        $id = (int)$this->request->getPost("id");

        $request = $this->Gate_pass_requests_model->get_details(["id" => $id])->getRow();
        if (!$request || $request->deleted) {
            return $this->template->view("errors/html/error_general", ["heading" => "Not found", "message" => app_lang("record_not_found")]);
        }
        if (!$this->_can_act_on_request($request)) {
            app_redirect("forbidden");
        }

        $view_data["request"] = $request;
        $view_data["approval_history"] = $this->Gate_pass_request_approvals_model
            ->get_details(["gate_pass_request_id" => $request->id])
            ->getResult();
        return $this->template->view("gate_pass_security_inbox/approval_history_modal", $view_data);
    }

    public function approval_modal_form()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);
        $id = (int)$this->request->getPost("id");

        $request = $this->Gate_pass_requests_model->get_details(["id" => $id])->getRow();
        if (!$request || $request->deleted) {
            return $this->template->view("errors/html/error_general", ["heading" => "Not found", "message" => app_lang("record_not_found")]);
        }

        if (!$this->_can_act_on_request($request)) {
            app_redirect("forbidden");
        }

        if ($request->stage !== "security") {
            return $this->template->view("errors/html/error_general", ["heading" => app_lang("error"), "message" => "Request is not in security stage."]);
        }

        $view_data["request"] = $request;
        $view_data["reason_options"] = $this->_get_rejection_reason_options();
        return $this->template->view("gate_pass_security_inbox/approval_modal_form", $view_data);
    }

    public function save_approval()
    {
        $this->validate_submitted_data([
            "gate_pass_request_id" => "required|numeric",
            "decision" => "required|in_list[approved,rejected,returned]",
        ]);

        $request_id = (int)$this->request->getPost("gate_pass_request_id");
        $decision = $this->request->getPost("decision"); // approved|rejected|returned
        $comment = trim((string)$this->request->getPost("comment"));
        $reason_id = (int)$this->request->getPost("reason_id");

        if ($decision === "returned" && !$comment) {
            echo json_encode(["success" => false, "message" => "Comment is required for Return."]);
            return;
        }

        $selected_reason = null;
        if ($decision === "rejected") {
            if ($reason_id < 1) {
                echo json_encode(["success" => false, "message" => app_lang("reject_reason_required")]);
                return;
            }

            $selected_reason = $this->Gate_pass_reasons_model
                ->get_details(["id" => $reason_id, "only_active" => 1])
                ->getRow();
            if (!$selected_reason) {
                echo json_encode(["success" => false, "message" => app_lang("invalid_request")]);
                return;
            }

            // Keep history readable even where reason_id is not rendered.
            if ($comment === "") {
                $comment = (string)$selected_reason->title;
            }
        } else {
            $reason_id = 0;
        }

        $request = $this->Gate_pass_requests_model->get_one($request_id);
        if (!$request || $request->deleted) {
            echo json_encode(["success" => false, "message" => app_lang("record_not_found")]);
            return;
        }

        if (!$this->_can_act_on_request($request)) {
            echo json_encode(["success" => false, "message" => app_lang("forbidden")]);
            return;
        }

        if ($request->stage !== "security") {
            echo json_encode(["success" => false, "message" => "Request is not in security stage."]);
            return;
        }

        $approval_data = [
            "gate_pass_request_id" => $request_id,
            "stage" => "security",
            "decision" => $decision,
            "reason_id" => $reason_id > 0 ? $reason_id : null,
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

        if ($decision === "approved") {
            $update = ["status" => "security_approved", "stage" => "rop"];
        } else {
            $update = ["status" => $decision];
            // stage stays "security" so request remains in security inbox for re-review
        }
        $this->Gate_pass_requests_model->ci_save($update, $request_id);

        echo json_encode(["success" => true, "message" => app_lang("record_saved"), "id" => $request_id]);
    }

    private function _get_rejection_reason_options(): array
    {
        $options = ["0" => "- " . app_lang("select") . " -"];
        $reasons = $this->Gate_pass_reasons_model->get_details(["only_active" => 1])->getResult();
        foreach ($reasons as $reason) {
            $options[(string)$reason->id] = $reason->title;
        }
        return $options;
    }

    private function _can_act_on_request($request): bool
    {
        if ($this->login_user->is_admin) {
            return true;
        }
        $assignments = $this->Gate_pass_security_users_model->get_user_assignments($this->login_user->id)->getResult();
        foreach ($assignments as $a) {
            if ((int)$a->company_id === (int)$request->company_id) {
                return true;
            }
        }
        return false;
    }

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
