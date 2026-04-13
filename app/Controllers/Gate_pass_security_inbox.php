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
        $view_data["security_nav_active"] = "requests";

        return $this->template->rander("gate_pass_security_inbox/index", $view_data);
    }

    /**
     * KPI dashboard for security (separate from request queue and QR scan).
     */
    public function dashboard()
    {
        $Stats = new \App\Models\Pod_dashboard_stats_model();
        $opts = ["stages" => ["security"]];
        if (!$this->login_user->is_admin) {
            $assignments = $this->Gate_pass_security_users_model->get_user_assignments($this->login_user->id)->getResult();
            $company_ids = [];
            foreach ($assignments as $a) {
                $company_ids[] = (int)$a->company_id;
            }
            if (!empty($company_ids)) {
                $opts["company_ids"] = $company_ids;
            }
        }
        $view_data["kpis"] = $Stats->gate_pass_kpis($opts);
        $view_data["security_nav_active"] = "dashboard";

        return $this->template->rander("gate_pass_security_inbox/dashboard", $view_data);
    }

    public function export_list_csv()
    {
        $options = [
            "stage" => "security",
            "exclude_statuses" => ["returned"],
        ];

        if (!$this->login_user->is_admin) {
            $assignments = $this->Gate_pass_security_users_model->get_user_assignments($this->login_user->id)->getResult();
            $company_ids = [];
            foreach ($assignments as $a) {
                $company_ids[] = (int)$a->company_id;
            }
            if (empty($company_ids)) {
                $this->response->setHeader("Content-Type", "text/csv; charset=UTF-8");
                return $this->response->setBody("");
            }
            $options["company_ids"] = $company_ids;
        }

        $list = $this->Gate_pass_requests_model->get_details($options)->getResult();

        $filename = "gate_pass_security_" . date("Y-m-d") . ".csv";
        $this->response->setHeader("Content-Type", "text/csv; charset=UTF-8");
        $this->response->setHeader("Content-Disposition", "attachment; filename=\"" . $filename . "\"");

        $fh = fopen("php://temp", "r+");
        fputcsv($fh, ["reference", "created_at", "company", "department", "requester", "phone", "status", "stage", "visit_from", "visit_to"]);
        foreach ($list as $r) {
            $requester_name = trim(($r->requester_first_name ?? "") . " " . ($r->requester_last_name ?? ""));
            if ($requester_name === "") {
                $requester_name = $r->requester_name ?? "";
            }
            fputcsv($fh, [
                $r->reference ?? "",
                gate_pass_request_created_at_pick($r) ?? "",
                $r->company_name ?? "",
                $r->department_name ?? "",
                $requester_name,
                ($r->requester_phone ?? "") ?: "",
                $r->status ?? "",
                $r->stage ?? "",
                $r->visit_from ?? "",
                $r->visit_to ?? "",
            ]);
        }
        rewind($fh);
        $body = stream_get_contents($fh);
        fclose($fh);

        return $this->response->setBody($body);
    }

    public function scan()
    {
        $view_data["security_nav_active"] = "scan";

        return $this->template->rander("gate_pass_security_inbox/scan", $view_data);
}


public function lookup_by_qr()
{
    $this->validate_submitted_data([
        "qr_text" => "required"
    ]);

    $raw = trim((string) $this->request->getPost("qr_text"));
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
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
    // Pass is scannable from issuance (ROP approval), not only from scheduled visit_from
    $valid_from = null;
    if (!empty($gate_pass->issued_at)) {
        $valid_from = strtotime($gate_pass->issued_at);
    }
    if (!$valid_from && !empty($gate_pass->valid_from)) {
        $valid_from = strtotime($gate_pass->valid_from);
    }
    $valid_to = $gate_pass->valid_to ? strtotime($gate_pass->valid_to) : null;

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
            "status_label" => gate_pass_request_status_display($request),
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
                    $candidate = strtolower(preg_replace('/\s+/', "", trim((string)$q[$key])));
                    if ($this->_is_valid_qr_token($candidate)) {
                        return $candidate;
                    }
                }
            }
        }

        // last URL segment
        if (!empty($parts["path"])) {
            $seg = explode("/", trim($parts["path"], "/"));
            $last = strtolower(preg_replace('/\s+/', "", trim((string)end($seg))));
            if ($this->_is_valid_qr_token($last)) {
                return $last;
            }
        }

        return "";
    }

    // Plain token (some scanners inject line breaks / spaces)
    $compact = strtolower(preg_replace('/\s+/', "", $raw));
    return $this->_is_valid_qr_token($compact) ? $compact : "";
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

    $mul = $this->_security_vehicle_mulkiyah_cell($row);

    return [
        $row->plate_no ?: "-",
        $row->type ?: "-",
        $mul,
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
        "plate_prefix" => "required",
        "plate_digits" => "required",
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

    $built = gate_pass_plate_merge_from_post_parts(
        (string) $this->request->getPost("plate_prefix"),
        (string) $this->request->getPost("plate_digits")
    );
    if (empty($built["ok"])) {
        return $this->response->setJSON([
            "success" => false,
            "message" => $built["message"] ?? app_lang("gate_pass_plate_invalid_chars"),
        ]);
    }
    $plate_no = $built["plate"];

    $existing = $id ? $this->Gate_pass_request_vehicles_model->get_details(["id" => $id])->getRow() : null;

    $data = [
        "gate_pass_request_id" => $request_id,
        "plate_no" => $plate_no,
        "type" => $vehicle_type,
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

    $mulPath = trim((string)($data["mulkiyah_attachment_path"] ?? ""));
    if ($mulPath === "") {
        return $this->response->setJSON(["success" => false, "message" => app_lang("gate_pass_mulkiyah_required")]);
    }

    $data = clean_data($data);

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

    $allow_visitor_block = $this->_security_allows_visitor_block($request);

    $rows = [];
    foreach ($list as $row) {
        $rows[] = $this->_make_visitor_row($row, $allow_visitor_block);
    }

    return $this->response->setJSON(["data" => $rows]);
}

    /**
     * Visitor row: links to ID / visa / photo / driving license files (security scan + details).
     */
    private function _security_visitor_attachments_cell($row): string
    {
        $vid = (int)($row->id ?? 0);
        if ($vid < 1) {
            return "<span class=\"text-off\">—</span>";
        }

        $fields = [
            "id_attachment_path" => app_lang("id_attachment"),
            "visa_attachment_path" => app_lang("visa_attachment"),
            "photo_attachment_path" => app_lang("photo_attachment"),
            "driving_license_attachment_path" => app_lang("driving_license_attachment"),
        ];

        $parts = [];
        foreach ($fields as $field => $label) {
            if (!empty($row->{$field})) {
                $base = get_uri("gate_pass_security_inbox/visitor_attachment_download/" . $vid . "/" . $field);
                $parts[] = "<a class=\"btn btn-default btn-xs\" href=\"" . esc($base) . "\" target=\"_blank\" rel=\"noopener\">" . esc($label) . "</a>";
            }
        }

        if (empty($parts)) {
            return "<span class=\"text-off\">—</span>";
        }

        return "<div class=\"gp-sec-att-btns\" style=\"display:flex;flex-wrap:wrap;gap:4px;max-width:320px;\">" . implode("", $parts) . "</div>";
    }

    /**
     * Vehicle row: Mulkiyah view / download when on file.
     */
    private function _security_vehicle_mulkiyah_cell($row): string
    {
        $rel = gate_pass_vehicle_mulkiyah_path_value($row);
        if ($rel === "") {
            return "<span class=\"text-off\">—</span>";
        }

        $vid = (int)($row->id ?? 0);
        $base = get_uri("gate_pass_security_inbox/vehicle_attachment_download/" . $vid . "/mulkiyah_attachment_path");

        return "<div class=\"gp-sec-att-btns\" style=\"display:flex;flex-wrap:wrap;gap:4px;justify-content:center;\">"
            . "<a class=\"btn btn-default btn-xs\" href=\"" . esc($base) . "\" target=\"_blank\" rel=\"noopener\">" . esc(app_lang("view")) . "</a>"
            . "<a class=\"btn btn-default btn-xs\" href=\"" . esc($base . "?download=1") . "\">" . esc(app_lang("download")) . "</a>"
            . "</div>";
    }

private function _make_visitor_row($row, ?bool $allow_visitor_block = null)
{
    if ($allow_visitor_block === null) {
        $reqRow = $this->Gate_pass_requests_model->get_details(["id" => (int)($row->gate_pass_request_id ?? 0)])->getRow();
        $allow_visitor_block = $reqRow && $this->_security_allows_visitor_block($reqRow);
    }

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

    $rid = (int)($row->gate_pass_request_id ?? 0);
    $vid = (int)($row->id ?? 0);
    $row_block_btn = "";
    if ($allow_visitor_block && $rid > 0 && $vid > 0) {
        if ($is_blocked) {
            $row_block_btn = modal_anchor(
                get_uri("gate_pass_security_inbox/visitor_block_modal_form"),
                "<i data-feather='unlock' class='icon-16'></i>",
                [
                    "class" => "btn btn-default btn-xs",
                    "title" => "Unblock visitor",
                    "data-post-request_id" => $rid,
                    "data-post-prefill_visitor_id" => $vid,
                    "data-post-prefill_action" => "unblock",
                ]
            );
        } else {
            $row_block_btn = modal_anchor(
                get_uri("gate_pass_security_inbox/visitor_block_modal_form"),
                "<i data-feather='slash' class='icon-16'></i>",
                [
                    "class" => "btn btn-warning btn-xs",
                    "title" => "Block visitor",
                    "data-post-request_id" => $rid,
                    "data-post-prefill_visitor_id" => $vid,
                    "data-post-prefill_action" => "block",
                ]
            );
        }
    }

    return [
        $row->full_name ?: "-",
        $row->id_type ?: "-",
        $row->id_number ?: "-",
        $row->nationality ?: "-",
        $row->phone ?: "-",
        $row->role ?: "-",
        $this->_security_visitor_attachments_cell($row),
        $blocked_badge,
        $block_reason !== "" ? esc($block_reason) : "-",
        '<div class="gp-sec-scan-visitor-opts" style="display:flex;flex-wrap:wrap;gap:4px;align-items:center;justify-content:flex-end;">'
            . $edit . $row_block_btn . $delete
        . '</div>'
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
        // Security queue: exclude rows returned to the requester (they reappear after resubmit).
        $options = [
            "stage" => "security",
            "exclude_statuses" => ["returned"],
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
            "<i data-feather='eye' class='icon-16'></i> " . app_lang("view"),
            ["class" => "btn btn-default btn-sm gp-sec-view-btn", "title" => app_lang("view")]
        );

        $visitor_block_btn = modal_anchor(
            get_uri("gate_pass_security_inbox/visitor_block_modal_form"),
            "<i data-feather='slash' class='icon-16'></i> Block Visitor",
            [
                "class" => "btn btn-warning btn-sm",
                "title" => "Block/Unblock Visitor",
                "data-post-request_id" => $data->id,
            ]
        );

        $requester_name = trim(($data->requester_first_name ?? '') . ' ' . ($data->requester_last_name ?? ''));
        if ($requester_name === '') {
            $requester_name = $data->requester_name ?? '-';
        }

        $created_disp = gate_pass_request_created_display($data);

        return [
            $data->reference ?? "-",
            $created_disp,
            $data->company_name ?? "-",
            $data->department_name ?? "-",
            $requester_name,
            ($data->requester_phone ?? '') ?: '-',
            $data->visit_from ? format_to_datetime($data->visit_from) : "-",
            $data->visit_to ? format_to_datetime($data->visit_to) : "-",
            gate_pass_request_status_display($data),
            '<div class="gp-sec-action-btns">' . $view_btn . $visitor_block_btn . '</div>',
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
        if (($request->status ?? "") === "returned") {
            app_redirect("forbidden");
        }

        $view_data["request"] = $request;
        $view_data["approval_history"] = $this->Gate_pass_request_approvals_model
            ->get_details(["gate_pass_request_id" => $request->id])
            ->getResult();

        $view_data["visitor_rows_for_attachments"] = $this->Gate_pass_request_visitors_model
            ->get_details(["gate_pass_request_id" => $request->id])
            ->getResult();
        $view_data["vehicle_rows_for_attachments"] = $this->Gate_pass_request_vehicles_model
            ->get_details(["gate_pass_request_id" => $request->id])
            ->getResult();

        $view_data["status_label"] = gate_pass_request_status_display($request);
        $view_data["security_nav_active"] = "requests";

        return $this->template->rander("gate_pass_security_inbox/details", $view_data);
    }

    /**
     * Security (or admin): view/download visitor documents for a request they can access.
     */
    public function visitor_attachment_download($visitor_id = 0, $field = "")
    {
        $visitor_id = (int)$visitor_id;
        $allowed_fields = ["id_attachment_path", "visa_attachment_path", "photo_attachment_path", "driving_license_attachment_path"];
        if (!$visitor_id || !in_array($field, $allowed_fields, true)) {
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
        if (!$request || (int)$request->deleted === 1 || !$this->_can_act_on_request($request)) {
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
     * Security (or admin): view/download vehicle Mulkiyah for a request they can access.
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
        if (!$request || (int)$request->deleted === 1 || !$this->_can_act_on_request($request)) {
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
        if (($request->status ?? "") === "returned") {
            return $this->template->view("errors/html/error_general", ["heading" => app_lang("error"), "message" => app_lang("error_occurred")]);
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

        if (($request->status ?? "") === "returned") {
            echo json_encode(["success" => false, "message" => app_lang("error_occurred")]);
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

    public function visitor_block_modal_form()
    {
        $this->validate_submitted_data(["request_id" => "required|numeric"]);
        $request_id = (int)$this->request->getPost("request_id");

        $request = $this->Gate_pass_requests_model->get_details(["id" => $request_id])->getRow();
        if (!$request || $request->deleted) {
            return $this->template->view("errors/html/error_general", ["heading" => "Not found", "message" => app_lang("record_not_found")]);
        }
        if (!$this->_can_act_on_request($request)) {
            app_redirect("forbidden");
        }
        if (!$this->_security_allows_visitor_block($request)) {
            return $this->template->view("errors/html/error_general", [
                "heading" => app_lang("error"),
                "message" => "Visitor block is not available for this request.",
            ]);
        }

        $prefill_visitor_id = (int)$this->request->getPost("prefill_visitor_id");
        $prefill_action = strtolower(trim((string)$this->request->getPost("prefill_action")));
        if (!in_array($prefill_action, ["block", "unblock"], true)) {
            $prefill_action = "";
        }
        if ($prefill_visitor_id > 0) {
            $vcheck = $this->Gate_pass_request_visitors_model->get_details(["id" => $prefill_visitor_id])->getRow();
            if (!$vcheck || (int)$vcheck->gate_pass_request_id !== $request_id) {
                $prefill_visitor_id = 0;
                $prefill_action = "";
            }
        } else {
            $prefill_action = "";
        }

        $view_data["request"] = $request;
        $view_data["visitors"] = $this->Gate_pass_request_visitors_model
            ->get_details(["gate_pass_request_id" => $request_id])
            ->getResult();
        $view_data["prefill_visitor_id"] = $prefill_visitor_id;
        $view_data["prefill_action"] = $prefill_action;

        return $this->template->view("gate_pass_security_inbox/visitor_block_modal_form", $view_data);
    }

    public function save_visitor_block()
    {
        $this->validate_submitted_data([
            "request_id" => "required|numeric",
            "visitor_id" => "required|numeric",
            "block_action" => "required|in_list[block,unblock]",
            "block_reason" => "permit_empty",
        ]);

        $request_id = (int)$this->request->getPost("request_id");
        $visitor_id = (int)$this->request->getPost("visitor_id");
        $block_action = strtolower(trim((string)$this->request->getPost("block_action")));
        $block_reason = trim((string)$this->request->getPost("block_reason"));

        $request = $this->Gate_pass_requests_model->get_details(["id" => $request_id])->getRow();
        if (!$request || $request->deleted) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("record_not_found")]);
        }
        if (!$this->_can_act_on_request($request)) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("forbidden")]);
        }
        if (!$this->_security_allows_visitor_block($request)) {
            return $this->response->setJSON(["success" => false, "message" => "Visitor block is not available for this request."]);
        }

        $visitor = $this->Gate_pass_request_visitors_model->get_details(["id" => $visitor_id])->getRow();
        if (!$visitor || (int)$visitor->gate_pass_request_id !== $request_id) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("record_not_found")]);
        }

        if ($block_action === "block" && $block_reason === "") {
            return $this->response->setJSON(["success" => false, "message" => "Block reason is required."]);
        }

        $data = [];
        if ($block_action === "block") {
            $data = [
                "is_blocked" => 1,
                "block_reason" => $block_reason,
                "blocked_by" => $this->login_user->id,
                "blocked_at" => get_current_utc_time(),
            ];
        } else {
            $data = [
                "is_blocked" => 0,
                "block_reason" => null,
                "blocked_by" => null,
                "blocked_at" => null,
            ];
        }

        $data = clean_data($data);
        $ok = $this->Gate_pass_request_visitors_model->ci_save($data, $visitor_id);
        if (!$ok) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("error_occurred")]);
        }

        gate_pass_audit_log_visitor_block(
            (int)$this->login_user->id,
            $request,
            $visitor,
            $block_action,
            $block_reason,
            "security"
        );

        return $this->response->setJSON(["success" => true, "message" => app_lang("record_saved")]);
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

    /**
     * Security may block/unblock visitors during review (security stage) or at the gate (issued / QR scan).
     */
    private function _security_allows_visitor_block($request): bool
    {
        if (!$request || (int)($request->deleted ?? 0) === 1) {
            return false;
        }
        if (!$this->_can_act_on_request($request)) {
            return false;
        }
        if (($request->status ?? "") === "returned") {
            return false;
        }
        $stage = (string)($request->stage ?? "");
        return in_array($stage, ["security", "issued"], true);
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

}
