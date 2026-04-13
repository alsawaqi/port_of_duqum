<?php

namespace App\Controllers;

use App\Models\Gate_pass_requests_model;
use App\Models\Gate_pass_commercial_users_model;
use App\Models\Gate_pass_request_approvals_model;
use App\Models\Gate_pass_request_visitors_model;
use App\Models\Gate_pass_request_vehicles_model;
use App\Models\Gate_pass_reasons_model;

class Gate_pass_commercial_inbox extends Security_Controller
{
    protected $Gate_pass_requests_model;
    protected $Gate_pass_commercial_users_model;
    protected $Gate_pass_request_approvals_model;
    protected $Gate_pass_request_visitors_model;
    protected $Gate_pass_request_vehicles_model;
    protected $Gate_pass_reasons_model;

    public function __construct()
    {
        parent::__construct();
        $this->access_only_team_members();

        $this->Gate_pass_requests_model = new Gate_pass_requests_model();
        $this->Gate_pass_commercial_users_model = new Gate_pass_commercial_users_model();
        $this->Gate_pass_request_approvals_model = new Gate_pass_request_approvals_model();
        $this->Gate_pass_request_visitors_model = new Gate_pass_request_visitors_model();
        $this->Gate_pass_request_vehicles_model = new Gate_pass_request_vehicles_model();
        $this->Gate_pass_reasons_model = new Gate_pass_reasons_model();

        if (!$this->login_user->is_admin && !$this->Gate_pass_commercial_users_model->is_commercial_user($this->login_user->id)) {
            app_redirect("forbidden");
        }
    }

    public function index()
    {
        $Stats = new \App\Models\Pod_dashboard_stats_model();
        $opts = ["stages" => ["commercial"]];
        if (!$this->login_user->is_admin) {
            $assignments = $this->Gate_pass_commercial_users_model->get_user_assignments($this->login_user->id)->getResult();
            $company_ids = [];
            foreach ($assignments as $a) {
                $company_ids[] = (int)$a->company_id;
            }
            if (!empty($company_ids)) {
                $opts["company_ids"] = $company_ids;
            }
        }
        $view_data["kpis"] = $Stats->gate_pass_kpis($opts);

        return $this->template->rander("gate_pass_commercial_inbox/index", $view_data);
    }

    /**
     * CSV export for the same scope as the commercial inbox list.
     */
    public function export_list_csv()
    {
        $options = [
            "stage" => "commercial",
            "statuses" => ["department_approved"],
        ];

        if (!$this->login_user->is_admin) {
            $assignments = $this->Gate_pass_commercial_users_model->get_user_assignments($this->login_user->id)->getResult();
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

        $filename = "gate_pass_commercial_" . date("Y-m-d") . ".csv";
        $this->response->setHeader("Content-Type", "text/csv; charset=UTF-8");
        $this->response->setHeader("Content-Disposition", "attachment; filename=\"" . $filename . "\"");

        $fh = fopen("php://temp", "r+");
        fputcsv($fh, ["reference", "created_at", "company", "department", "requester", "phone", "visit_from", "visit_to", "currency", "fee_amount", "fee_waived", "status", "stage"]);
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
                $r->visit_from ? format_to_datetime($r->visit_from) : "",
                $r->visit_to ? format_to_datetime($r->visit_to) : "",
                (string)($r->currency ?? ""),
                $r->fee_amount !== null && $r->fee_amount !== "" ? (string)$r->fee_amount : "",
                !empty($r->fee_is_waived) ? "1" : "0",
                $r->status ?? "",
                $r->stage ?? "",
            ]);
        }
        rewind($fh);
        $body = stream_get_contents($fh);
        fclose($fh);

        return $this->response->setBody($body);
    }

    public function list_data()
    {
        $options = [
            "stage" => "commercial",
            "statuses" => ["department_approved"],
        ];

        if (!$this->login_user->is_admin) {
            $assignments = $this->Gate_pass_commercial_users_model->get_user_assignments($this->login_user->id)->getResult();
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
            get_uri("gate_pass_commercial_inbox/details/" . $data->id),
            "<i data-feather='eye' class='icon-16'></i>",
            ["class" => "btn btn-default btn-sm", "title" => app_lang("view")]
        );

        $fee_btn = modal_anchor(
            get_uri("gate_pass_commercial_inbox/fee_modal_form"),
            "<span class='gp-set-fee-omr'>OMR</span> " . app_lang("set_fee"),
            [
                "class" => "btn btn-primary btn-sm",
                "title" => app_lang("set_fee"),
                "data-post-id" => $data->id,
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
            $data->currency ?? "-",
            $data->fee_amount !== null && $data->fee_amount !== "" ? (string)$data->fee_amount : "-",
            gate_pass_fee_waiver_pending($data)
                ? app_lang("gate_pass_fee_waiver_pending")
                : (!empty($data->fee_is_waived) ? app_lang("yes") : app_lang("no")),
            $view_btn . " " . $fee_btn,
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
        if ($request->stage !== "commercial" || $request->status !== "department_approved") {
            app_redirect("forbidden");
        }

        $view_data["request"] = $request;
        $view_data["approval_history"] = $this->Gate_pass_request_approvals_model
            ->get_details(["gate_pass_request_id" => $request->id])
            ->getResult();

        $view_data["status_label"] = gate_pass_request_status_display($request);

        $view_data["visitor_rows_for_attachments"] = $this->Gate_pass_request_visitors_model
            ->get_details(["gate_pass_request_id" => $request->id])
            ->getResult();
        $view_data["vehicle_rows_for_attachments"] = $this->Gate_pass_request_vehicles_model
            ->get_details(["gate_pass_request_id" => $request->id])
            ->getResult();

        return $this->template->rander("gate_pass_commercial_inbox/details", $view_data);
    }

    /**
     * Commercial (or admin): view/download visitor documents for a request they can access.
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
     * Commercial (or admin): view/download vehicle Mulkiyah for a request they can access.
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
        return $this->template->view("gate_pass_commercial_inbox/approval_history_modal", $view_data);
    }

    public function visitors_list_data($request_id = 0)
    {
        $request_id = (int)$request_id;
        if (!$request_id) {
            return $this->response->setJSON(["data" => []]);
        }
        $request = $this->Gate_pass_requests_model->get_details(["id" => $request_id])->getRow();
        if (!$request || $request->deleted || !$this->_can_act_on_request($request)) {
            return $this->response->setJSON(["data" => []]);
        }
        $list_data = $this->Gate_pass_request_visitors_model->get_details(["gate_pass_request_id" => $request_id])->getResult();
        $result = [];
        foreach ($list_data as $row) {
            $result[] = $this->_make_visitor_row($row);
        }
        return $this->response->setJSON(["data" => $result]);
    }

    public function vehicles_list_data($request_id = 0)
    {
        $request_id = (int)$request_id;
        if (!$request_id) {
            return $this->response->setJSON(["data" => []]);
        }
        $request = $this->Gate_pass_requests_model->get_details(["id" => $request_id])->getRow();
        if (!$request || $request->deleted || !$this->_can_act_on_request($request)) {
            return $this->response->setJSON(["data" => []]);
        }
        $list_data = $this->Gate_pass_request_vehicles_model->get_details(["gate_pass_request_id" => $request_id])->getResult();
        $result = [];
        foreach ($list_data as $row) {
            $result[] = $this->_make_vehicle_row($row);
        }
        return $this->response->setJSON(["data" => $result]);
    }

    private function _make_visitor_row($row)
    {
        $is_blocked = (int)($row->is_blocked ?? 0) === 1;
        $blocked_badge = $is_blocked
            ? "<span class='badge bg-danger'>" . app_lang("blocked") . "</span>"
            : "<span class='badge bg-success'>Clear</span>";
        $block_reason = trim((string)($row->block_reason ?? ""));
        $primary = !empty($row->is_primary) ? "<span class='badge bg-success'>" . app_lang("primary") . "</span>" : "";
        return [
            $row->full_name ?? "-",
            $row->id_type ?? "-",
            $row->id_number ?? "-",
            $row->nationality ?? "-",
            $row->phone ?? "-",
            $row->role ?? "-",
            $blocked_badge,
            $block_reason !== "" ? esc($block_reason) : "-",
            $primary
        ];
    }

    private function _make_vehicle_row($row)
    {
        $mul = !empty($row->mulkiyah_attachment_path)
            ? "<span class='badge bg-success'>" . app_lang("yes") . "</span>"
            : "<span class='badge bg-secondary'>" . app_lang("no") . "</span>";
        return [
            $row->plate_no ?? "-",
            $mul,
        ];
    }

    public function fee_modal_form()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);
        $id = (int)$this->request->getPost("id");

        $request = $this->Gate_pass_requests_model->get_details(["id" => $id])->getRow();
        if (!$request || $request->deleted) {
            return $this->template->view("errors/html/error_general", ["heading" => "Not found", "message" => app_lang("record_not_found")]);
        }
        if ($request->stage !== "commercial" || $request->status !== "department_approved") {
            return $this->template->view("errors/html/error_general", ["heading" => app_lang("error"), "message" => "Request is not in commercial stage."]);
        }
        if (!$this->_can_act_on_request($request)) {
            app_redirect("forbidden");
        }

        $view_data["request"] = $request;
        $view_data["currency_options"] = ["OMR" => "OMR", "USD" => "USD", "EUR" => "EUR", "GBP" => "GBP"];
        $view_data["reason_options"] = $this->_get_rejection_reason_options();
        return $this->template->view("gate_pass_commercial_inbox/fee_modal_form", $view_data);
    }

    public function save_fee()
    {
        $this->validate_submitted_data([
            "gate_pass_request_id" => "required|numeric",
            "currency" => "required|max_length[10]",
            "fee_amount" => "required|numeric",
            "fee_is_waived" => "permit_empty|in_list[0,1]",
            "fee_waived_reason" => "permit_empty",
        ]);

        $request_id = (int)$this->request->getPost("gate_pass_request_id");
        $currency = strtoupper(trim((string)$this->request->getPost("currency")));
        $fee_amount = (float)$this->request->getPost("fee_amount");
        $fee_is_waived = (int)$this->request->getPost("fee_is_waived") === 1 ? 1 : 0;
        $fee_waived_reason = trim((string)$this->request->getPost("fee_waived_reason"));

        $request = $this->Gate_pass_requests_model->get_one($request_id);
        if (!$request || $request->deleted) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("record_not_found")]);
        }
        if ($request->stage !== "commercial" || $request->status !== "department_approved") {
            return $this->response->setJSON(["success" => false, "message" => "Request is not in commercial stage."]);
        }
        if (!$this->_can_act_on_request($request)) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("forbidden")]);
        }

        if (gate_pass_fee_waiver_pending($request) && $fee_is_waived) {
            return $this->response->setJSON([
                "success" => false,
                "message" => app_lang("gate_pass_fee_waiver_use_decision_buttons"),
            ]);
        }

        if (gate_pass_fee_waiver_pending($request)) {
            return $this->response->setJSON([
                "success" => false,
                "message" => app_lang("gate_pass_fee_waiver_resolve_first"),
            ]);
        }

        if ($fee_is_waived && $fee_amount > 0 && $fee_waived_reason === "") {
            return $this->response->setJSON([
                "success" => false,
                "message" => app_lang("gate_pass_commercial_waive_reason_required"),
            ]);
        }

        $prev_waived = (int)($request->fee_is_waived ?? 0) === 1;
        if ($fee_is_waived) {
            $fee_waived_at = (!$prev_waived || empty($request->fee_waived_at))
                ? get_current_utc_time()
                : $request->fee_waived_at;
        } else {
            $fee_waived_at = null;
        }

        $fee_data = [
            "currency" => $currency,
            "fee_amount" => max(0, $fee_amount),
            "fee_is_waived" => $fee_is_waived,
            "fee_waived_by" => $fee_is_waived ? (int)$this->login_user->id : null,
            "fee_waived_reason" => $fee_is_waived ? ($fee_waived_reason ?: null) : null,
            "fee_waived_at" => $fee_waived_at,
            "updated_at" => get_current_utc_time(),
        ];

        // Commercial may waive at any time (including after rejecting a department waiver request).
        if ($fee_is_waived) {
            $fee_data["fee_waiver_requested"] = 0;
            $fee_data["fee_waiver_commercial_status"] = null;
        }

        $this->Gate_pass_requests_model->ci_save($fee_data, $request_id);

        return $this->response->setJSON(["success" => true, "message" => app_lang("record_saved"), "id" => $request_id]);
    }

    /**
     * Commercial approves or rejects a department-initiated fee waiver request.
     * Approve → fee waived, advance to security. Reject → requester must pay in portal.
     */
    public function fee_waiver_decision()
    {
        $this->validate_submitted_data([
            "gate_pass_request_id" => "required|numeric",
            "outcome" => "required|in_list[approve,reject]",
        ]);

        $request_id = (int)$this->request->getPost("gate_pass_request_id");
        $outcome = strtolower(trim((string)$this->request->getPost("outcome")));
        $comment = trim((string)$this->request->getPost("comment"));

        $request = $this->Gate_pass_requests_model->get_details(["id" => $request_id])->getRow();
        if (!$request || $request->deleted) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("record_not_found")]);
        }
        if ($request->stage !== "commercial" || $request->status !== "department_approved") {
            return $this->response->setJSON(["success" => false, "message" => app_lang("error_occurred")]);
        }
        if (!$this->_can_act_on_request($request)) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("forbidden")]);
        }
        if ((int)($request->fee_waiver_requested ?? 0) !== 1) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("error_occurred")]);
        }
        $wst = strtolower(trim((string)($request->fee_waiver_commercial_status ?? "")));
        if ($wst !== "" && $wst !== "pending") {
            return $this->response->setJSON(["success" => false, "message" => app_lang("error_occurred")]);
        }

        if ($outcome === "approve") {
            $this->Gate_pass_requests_model->ci_save(clean_data([
                "fee_is_waived" => 1,
                "fee_waiver_requested" => 0,
                "fee_waiver_commercial_status" => "approved",
                "fee_waived_by" => (int)$this->login_user->id,
                "fee_waived_at" => get_current_utc_time(),
            ]), $request_id);

            $fresh = $this->Gate_pass_requests_model->get_details(["id" => $request_id])->getRow();
            $adv = $this->_advance_commercial_to_security($request_id, $fresh);
            if (!$adv["success"]) {
                return $this->response->setJSON($adv);
            }

            return $this->response->setJSON(["success" => true, "message" => app_lang("record_saved"), "id" => $request_id]);
        }

        $dept_waiver_note = trim((string)($request->fee_waived_reason ?? ""));

        // Reject waiver: keep request at commercial / department_approved so the requester pays (not returned for edits).
        $this->Gate_pass_requests_model->ci_save(clean_data([
            "fee_is_waived" => 0,
            "fee_waiver_requested" => 0,
            "fee_waiver_commercial_status" => "rejected",
            "fee_waived_by" => null,
            "fee_waived_at" => null,
            "fee_waived_reason" => null,
            "updated_at" => get_current_utc_time(),
        ]), $request_id);

        $log_comment = app_lang("gate_pass_fee_waiver_rejected_log");
        if ($dept_waiver_note !== "") {
            $log_comment .= " " . app_lang("reason") . ": " . $dept_waiver_note;
        }
        if ($comment !== "") {
            $log_comment .= " " . $comment;
        }
        $approval_data = [
            "gate_pass_request_id" => $request_id,
            "stage" => "commercial",
            "decision" => "fee_waiver_rejected",
            "reason_id" => null,
            "comment" => $log_comment,
            "decided_by" => $this->login_user->id,
            "decided_at" => get_current_utc_time(),
            "ip_address" => $this->request->getIPAddress(),
            "user_agent" => substr($this->request->getUserAgent()->getAgentString(), 0, 500),
        ];
        $this->Gate_pass_request_approvals_model->ci_save(gate_pass_clean_approval_data_for_save($approval_data));

        return $this->response->setJSON(["success" => true, "message" => app_lang("record_saved"), "id" => $request_id]);
    }

    public function approve_waiver()
    {
        $this->validate_submitted_data([
            "gate_pass_request_id" => "required|numeric",
        ]);

        $request_id = (int)$this->request->getPost("gate_pass_request_id");

        $request = $this->Gate_pass_requests_model->get_details(["id" => $request_id])->getRow();
        if (!$request || $request->deleted) {
            return $this->response->setJSON([
                "success" => false,
                "message" => app_lang("record_not_found"),
            ]);
        }

        if ($request->stage !== "commercial" || $request->status !== "department_approved") {
            return $this->response->setJSON([
                "success" => false,
                "message" => "Request is not in commercial stage.",
            ]);
        }

        if (!$this->_can_act_on_request($request)) {
            return $this->response->setJSON([
                "success" => false,
                "message" => app_lang("forbidden"),
            ]);
        }

        if (gate_pass_fee_waiver_pending($request)) {
            return $this->response->setJSON([
                "success" => false,
                "message" => app_lang("gate_pass_fee_waiver_pending_commercial_action"),
            ]);
        }

        $fee_amount = (float)($request->fee_amount ?? 0);
        $is_waived = (int)($request->fee_is_waived ?? 0) === 1;

        if (!$is_waived && $fee_amount > 0) {
            return $this->response->setJSON([
                "success" => false,
                "message" => app_lang("gate_pass_commercial_payment_required_or_save_fee"),
            ]);
        }

        return $this->response->setJSON($this->_advance_commercial_to_security($request_id, $request));
    }

    /**
     * @return array{success:bool,message:string,id?:int}
     */
    private function _advance_commercial_to_security(int $request_id, $request = null): array
    {
        if (!$request) {
            $request = $this->Gate_pass_requests_model->get_details(["id" => $request_id])->getRow();
        }
        if (!$request || $request->deleted) {
            return ["success" => false, "message" => app_lang("record_not_found")];
        }

        $is_waived = (int)($request->fee_is_waived ?? 0) === 1;

        $visitor_rows = $this->Gate_pass_request_visitors_model->get_details(["gate_pass_request_id" => $request_id])->getResult();
        $id_numbers = [];
        foreach ($visitor_rows as $vr) {
            $idn = trim((string)($vr->id_number ?? ""));
            if ($idn !== "") {
                $id_numbers[] = $idn;
            }
        }
        $vehicle_rows = $this->Gate_pass_request_vehicles_model->get_details(["gate_pass_request_id" => $request_id])->getResult();
        $plates = [];
        foreach ($vehicle_rows as $veh) {
            $p = strtoupper(str_replace("-", "", preg_replace('/\s+/', "", trim((string)($veh->plate_no ?? "")))));
            if ($p !== "") {
                $plates[] = $p;
            }
        }
        if ($this->Gate_pass_requests_model->has_overlapping_active_pass(
            $request_id,
            $request->visit_from ?? null,
            $request->visit_to ?? null,
            (int)($request->company_id ?? 0),
            $id_numbers,
            $plates
        )) {
            return ["success" => false, "message" => app_lang("gate_pass_overlap_active_pass")];
        }

        $this->Gate_pass_requests_model->ci_save([
            "status" => "commercial_approved",
            "stage" => "security",
            "stage_updated_at" => get_current_utc_time(),
        ], $request_id);

        $comment = $is_waived
            ? trim("Fee waived. " . (string)($request->fee_waived_reason ?? ""))
            : "Zero fee (commercial approval).";

        $approval_data = [
            "gate_pass_request_id" => $request_id,
            "stage" => "commercial",
            "decision" => "approved",
            "reason_id" => null,
            "comment" => $comment,
            "decided_by" => $this->login_user->id,
            "decided_at" => get_current_utc_time(),
            "ip_address" => $this->request->getIPAddress(),
            "user_agent" => substr($this->request->getUserAgent()->getAgentString(), 0, 500),
        ];
        $this->Gate_pass_request_approvals_model->ci_save(gate_pass_clean_approval_data_for_save($approval_data));

        return ["success" => true, "message" => app_lang("record_saved"), "id" => $request_id];
    }

    /**
     * Commercial returns the request to the requester for edits (stage stays commercial).
     */
    public function return_request_modal()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);
        $id = (int)$this->request->getPost("id");

        $request = $this->Gate_pass_requests_model->get_details(["id" => $id])->getRow();
        if (!$request || $request->deleted) {
            return $this->template->view("errors/html/error_general", ["heading" => "Not found", "message" => app_lang("record_not_found")]);
        }
        if ($request->stage !== "commercial" || $request->status !== "department_approved") {
            return $this->template->view("errors/html/error_general", ["heading" => app_lang("error"), "message" => app_lang("error_occurred")]);
        }
        if (gate_pass_fee_waiver_pending($request)) {
            return $this->template->view("errors/html/error_general", ["heading" => app_lang("error"), "message" => app_lang("gate_pass_fee_waiver_pending_commercial_action")]);
        }
        if (!$this->_can_act_on_request($request)) {
            app_redirect("forbidden");
        }

        return $this->template->view("gate_pass_commercial_inbox/return_request_modal", ["request" => $request]);
    }

    public function save_return_request()
    {
        $this->validate_submitted_data([
            "gate_pass_request_id" => "required|numeric",
            "comment" => "required",
        ]);

        $request_id = (int)$this->request->getPost("gate_pass_request_id");
        $comment = trim((string)$this->request->getPost("comment"));
        if ($comment === "") {
            return $this->response->setJSON(["success" => false, "message" => app_lang("comment_required_for_return_reject")]);
        }

        $request = $this->Gate_pass_requests_model->get_one($request_id);
        if (!$request || $request->deleted) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("record_not_found")]);
        }
        if ($request->stage !== "commercial" || $request->status !== "department_approved") {
            return $this->response->setJSON(["success" => false, "message" => app_lang("error_occurred")]);
        }
        if (gate_pass_fee_waiver_pending($request)) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("gate_pass_fee_waiver_pending_commercial_action")]);
        }
        if (!$this->_can_act_on_request($request)) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("forbidden")]);
        }

        $approval_data = [
            "gate_pass_request_id" => $request_id,
            "stage" => "commercial",
            "decision" => "returned",
            "reason_id" => null,
            "comment" => $comment,
            "decided_by" => $this->login_user->id,
            "decided_at" => get_current_utc_time(),
            "ip_address" => $this->request->getIPAddress(),
            "user_agent" => substr($this->request->getUserAgent()->getAgentString(), 0, 500),
        ];
        $saved = $this->Gate_pass_request_approvals_model->ci_save(gate_pass_clean_approval_data_for_save($approval_data));
        if (!$saved) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("error_occurred")]);
        }

        $this->Gate_pass_requests_model->ci_save([
            "status" => "returned",
            "stage_updated_at" => get_current_utc_time(),
        ], $request_id);

        return $this->response->setJSON(["success" => true, "message" => app_lang("record_saved"), "id" => $request_id]);
    }

    public function reject_request()
    {
        $this->validate_submitted_data([
            "gate_pass_request_id" => "required|numeric",
            "reason_id" => "required|numeric",
            "comment" => "permit_empty",
        ]);

        $request_id = (int)$this->request->getPost("gate_pass_request_id");
        $reason_id = (int)$this->request->getPost("reason_id");
        $comment = trim((string)$this->request->getPost("comment"));

        if ($reason_id < 1) {
            return $this->response->setJSON([
                "success" => false,
                "message" => app_lang("reject_reason_required"),
            ]);
        }

        $reason = $this->Gate_pass_reasons_model
            ->get_details(["id" => $reason_id, "only_active" => 1])
            ->getRow();
        if (!$reason) {
            return $this->response->setJSON([
                "success" => false,
                "message" => app_lang("invalid_request"),
            ]);
        }

        $request = $this->Gate_pass_requests_model->get_one($request_id);
        if (!$request || $request->deleted) {
            return $this->response->setJSON([
                "success" => false,
                "message" => app_lang("record_not_found"),
            ]);
        }

        if ($request->stage !== "commercial") {
            return $this->response->setJSON([
                "success" => false,
                "message" => "Request is not in commercial stage.",
            ]);
        }

        if (!$this->_can_act_on_request($request)) {
            return $this->response->setJSON([
                "success" => false,
                "message" => app_lang("forbidden"),
            ]);
        }

        $approval_data = [
            "gate_pass_request_id" => $request_id,
            "stage" => "commercial",
            "decision" => "rejected",
            "reason_id" => $reason_id,
            "comment" => $comment !== "" ? $comment : (string)$reason->title,
            "decided_by" => $this->login_user->id,
            "decided_at" => get_current_utc_time(),
            "ip_address" => $this->request->getIPAddress(),
            "user_agent" => substr($this->request->getUserAgent()->getAgentString(), 0, 500),
        ];
        $approval_data = gate_pass_clean_approval_data_for_save($approval_data);
        $saved_id = $this->Gate_pass_request_approvals_model->ci_save($approval_data);
        if (!$saved_id) {
            return $this->response->setJSON([
                "success" => false,
                "message" => app_lang("error_occurred"),
            ]);
        }

        $this->Gate_pass_requests_model->ci_save(["status" => "rejected"], $request_id);

        return $this->response->setJSON([
            "success" => true,
            "message" => app_lang("record_saved"),
            "id" => $request_id,
        ]);
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
        $assignments = $this->Gate_pass_commercial_users_model->get_user_assignments($this->login_user->id)->getResult();
        foreach ($assignments as $a) {
            if ((int)$a->company_id === (int)$request->company_id) {
                return true;
            }
        }
        return false;
    }

}
