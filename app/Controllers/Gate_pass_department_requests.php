<?php

namespace App\Controllers;

use App\Models\Gate_pass_department_users_model;
use App\Models\Gate_pass_requests_model;
use App\Models\Gate_pass_request_visitors_model;
use App\Models\Gate_pass_request_vehicles_model;
use App\Models\Gate_pass_request_approvals_model;

class Gate_pass_department_requests extends Security_Controller
{
    protected $Gate_pass_department_users_model;
    protected $Gate_pass_requests_model;
    protected $Gate_pass_request_visitors_model;
    protected $Gate_pass_request_vehicles_model;
    protected $Gate_pass_request_approvals_model;

    private array $my_department_ids = [];

    public function __construct()
    {
        parent::__construct();

        // staff-only (same pattern you use in Gate_pass_portal)
        if ($this->login_user->user_type !== "staff") {
            app_redirect("forbidden");
        }

        $this->Gate_pass_department_users_model = new Gate_pass_department_users_model();
        $this->Gate_pass_requests_model = new Gate_pass_requests_model();
        $this->Gate_pass_request_visitors_model = new Gate_pass_request_visitors_model();
        $this->Gate_pass_request_vehicles_model = new Gate_pass_request_vehicles_model();
        $this->Gate_pass_request_approvals_model = new Gate_pass_request_approvals_model();

        // Allow only users who exist in pivot table (or admin if you want)
        $this->my_department_ids = $this->Gate_pass_department_users_model
            ->get_department_ids_by_user($this->login_user->id);

        if (!$this->login_user->is_admin && !count($this->my_department_ids)) {
            app_redirect("forbidden");
        }
    }

    public function index()
    {
        $view_data = [];
        return $this->template->rander("gate_pass_department_requests/index", $view_data);
    }

    public function list_data()
    {
        // show only items that are currently in Department stage
        // and exclude drafts (recommended)
        $options = [
            "department_ids" => $this->my_department_ids,
            "stage" => "department",
            "statuses" => ["submitted", "returned"] // you can add more if needed
        ];

        $list_data = $this->Gate_pass_requests_model->get_details($options)->getResult();

        $result = [];
        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data);
        }

        echo json_encode(["data" => $result]);
    }

    private function _make_row($row)
    {
        $status_label = $this->_status_label($row->status);

        $view_url = anchor(
            get_uri("gate_pass_department_requests/details/" . $row->id),
            "<i data-feather='eye' class='icon-16'></i>",
            ["class" => "btn btn-default btn-sm", "title" => app_lang("view")]
        );

        $requester_name = trim(($row->requester_first_name ?? '') . ' ' . ($row->requester_last_name ?? ''));
        if ($requester_name === '') {
            $requester_name = $row->requester_name ?? '-';
        }

        return [
            $row->reference,
            $row->company_name,
            $row->department_name,
            $requester_name,
            ($row->requester_phone ?? '') ?: '-',
            $row->purpose_name,
            $row->visit_from,
            $row->visit_to,
            $status_label,
            $view_url
        ];
    }

    private function _status_label($status): string
    {
        $class = "bg-secondary";

        if ($status === "submitted") $class = "bg-warning";
        if ($status === "returned")  $class = "bg-danger";
        if ($status === "approved")  $class = "bg-success";
        if ($status === "rejected")  $class = "bg-danger";
        if ($status === "issued")    $class = "bg-success";

        return "<span class='badge $class'>" . esc($this->_format_gate_pass_status($status)) . "</span>";
    }

    public function details($id = 0)
    {
        $id = (int)$id;
        if (!$id) app_redirect("forbidden");

        $request = $this->Gate_pass_requests_model->get_details(["id" => $id])->getRow();

        if (!$request || $request->deleted) {
            app_redirect("forbidden");
        }

        // Must match department + be in department stage
        if (!$this->login_user->is_admin) {
            if (!in_array((int)$request->department_id, $this->my_department_ids, true)) {
                app_redirect("forbidden");
            }
        }

        if ($request->stage !== "department") {
            app_redirect("forbidden");
        }

        $view_data["request"] = $request;
        $view_data["approval_history"] = $this->Gate_pass_request_approvals_model
            ->get_details(["gate_pass_request_id" => $request->id])
            ->getResult();

        $view_data["status_label"] = $this->_format_gate_pass_status($request->status ?? "");

        return $this->template->rander("gate_pass_department_requests/details", $view_data);
    }

    /**
     * Modal form for Department Review (approve/return/reject).
     */
    public function approval_modal_form()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);
        $id = (int)$this->request->getPost("id");

        $request = $this->Gate_pass_requests_model->get_details(["id" => $id])->getRow();
        if (!$request || $request->deleted) {
            return $this->template->view("errors/html/error_general", ["heading" => "Not found", "message" => app_lang("record_not_found")]);
        }
        if (!$this->login_user->is_admin && !in_array((int)$request->department_id, $this->my_department_ids, true)) {
            app_redirect("forbidden");
        }
        if ($request->stage !== "department") {
            app_redirect("forbidden");
        }
        if ($request->status !== "submitted" && $request->status !== "returned") {
            return $this->template->view("errors/html/error_general", ["heading" => app_lang("error"), "message" => app_lang("request_not_awaiting_department")]);
        }

        $view_data["request"] = $request;
        return $this->template->view("gate_pass_department_requests/approval_modal_form", $view_data);
    }

    /**
     * Modal content for Approval History (for use in details page).
     */
    public function approval_history_modal()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);
        $id = (int)$this->request->getPost("id");

        $request = $this->Gate_pass_requests_model->get_details(["id" => $id])->getRow();
        if (!$request || $request->deleted) {
            return $this->template->view("errors/html/error_general", ["heading" => "Not found", "message" => app_lang("record_not_found")]);
        }
        if (!$this->login_user->is_admin && !in_array((int)$request->department_id, $this->my_department_ids, true)) {
            app_redirect("forbidden");
        }

        $view_data["request"] = $request;
        $view_data["approval_history"] = $this->Gate_pass_request_approvals_model
            ->get_details(["gate_pass_request_id" => $request->id])
            ->getResult();
        return $this->template->view("gate_pass_department_requests/approval_history_modal", $view_data);
    }

    /**
     * Save approval/return/reject from department requests details page.
     */
    public function save_approval()
    {
        $this->validate_submitted_data([
            "gate_pass_request_id" => "required|numeric",
            "decision" => "required"
        ]);

        $request_id = (int)$this->request->getPost("gate_pass_request_id");
        $decision = $this->request->getPost("decision");
        $comment = trim((string)$this->request->getPost("comment"));

        if (in_array($decision, ["rejected", "returned"], true) && !$comment) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("comment_required_for_return_reject")]);
        }

        $request = $this->Gate_pass_requests_model->get_one($request_id);
        if (!$request || $request->deleted) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("record_not_found")]);
        }

        if (!$this->login_user->is_admin && !in_array((int)$request->department_id, $this->my_department_ids, true)) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("forbidden")]);
        }

        if ($request->status !== "submitted" && $request->status !== "returned") {
            return $this->response->setJSON(["success" => false, "message" => app_lang("request_not_awaiting_department")]);
        }


        $fee_amount = (float)($request->fee_amount ?? 0);
$waive_flag = (int)($this->request->getPost("fee_is_waived") ? 1 : 0);
$waive_reason = trim((string)$this->request->getPost("fee_waived_reason"));

// Only enforce waiver rules when approving AND there is a fee
if ($decision === "approved" && $fee_amount > 0 && $waive_flag === 1 && $waive_reason === "") {
    return $this->response->setJSON(["success" => false, "message" => "Waive reason is required when waiving the fee."]);
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
            return $this->response->setJSON(["success" => false, "message" => app_lang("error_occurred")]);
        }

        // Update request: status and, when approved, move stage to commercial
        $new_status = $decision === "approved" ? "department_approved" : $decision;
        $request_data = ["status" => $new_status];



        if ($decision === "approved") {
            $request_data["stage"] = "commercial";
            $request_data["stage_updated_at"] = get_current_utc_time();
        
            // Apply/clear waiver only when approving
            $fee_amount = (float)($request->fee_amount ?? 0);
        
            if ($fee_amount > 0) {
                $waive_flag = (int)($this->request->getPost("fee_is_waived") ? 1 : 0);
                $waive_reason = trim((string)$this->request->getPost("fee_waived_reason"));
        
                if ($waive_flag === 1) {
                    $request_data["fee_is_waived"] = 1;
                    $request_data["fee_waived_by"] = $this->login_user->id;
                    $request_data["fee_waived_reason"] = $waive_reason;
                } else {
                    // Explicitly clear waiver if not waived
                    $request_data["fee_is_waived"] = 0;
                    $request_data["fee_waived_by"] = null;
                    $request_data["fee_waived_reason"] = null;
                }
            } else {
                // No fee => keep waiver clean
                $request_data["fee_is_waived"] = 0;
                $request_data["fee_waived_by"] = null;
                $request_data["fee_waived_reason"] = null;
            }
        }

        
        $this->Gate_pass_requests_model->ci_save($request_data, $request_id);

        return $this->response->setJSON(["success" => true, "message" => app_lang("record_saved"), "id" => $request_id]);
    }

    /**
     * List visitors for a request (read-only for department view).
     */
    public function visitors_list_data($request_id = 0)
    {
        $request_id = (int)$request_id;
        if (!$request_id) {
            return $this->response->setJSON(["data" => []]);
        }

        $request = $this->Gate_pass_requests_model->get_details(["id" => $request_id])->getRow();
        if (!$request || $request->deleted) {
            return $this->response->setJSON(["data" => []]);
        }
        if (!$this->login_user->is_admin && !in_array((int)$request->department_id, $this->my_department_ids, true)) {
            return $this->response->setJSON(["data" => []]);
        }

        $list_data = $this->Gate_pass_request_visitors_model->get_details([
            "gate_pass_request_id" => $request_id
        ])->getResult();

        $result = [];
        foreach ($list_data as $row) {
            $result[] = $this->_make_visitor_row($row);
        }
        return $this->response->setJSON(["data" => $result]);
    }

    /**
     * List vehicles for a request (read-only for department view).
     */
    public function vehicles_list_data($request_id = 0)
    {
        $request_id = (int)$request_id;
        if (!$request_id) {
            return $this->response->setJSON(["data" => []]);
        }

        $request = $this->Gate_pass_requests_model->get_details(["id" => $request_id])->getRow();
        if (!$request || $request->deleted) {
            return $this->response->setJSON(["data" => []]);
        }
        if (!$this->login_user->is_admin && !in_array((int)$request->department_id, $this->my_department_ids, true)) {
            return $this->response->setJSON(["data" => []]);
        }

        $list_data = $this->Gate_pass_request_vehicles_model->get_details([
            "gate_pass_request_id" => $request_id
        ])->getResult();

        $result = [];
        foreach ($list_data as $row) {
            $result[] = $this->_make_vehicle_row($row);
        }
        return $this->response->setJSON(["data" => $result]);
    }

    /**
     * Modal to show visitor attachments (ID, visa, photo, driving license).
     */
    public function visitor_attachments_modal()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);
        $visitor_id = (int)$this->request->getPost("id");

        $visitor = $this->Gate_pass_request_visitors_model->get_details(["id" => $visitor_id])->getRow();
        if (!$visitor || $visitor->deleted) {
            return $this->template->view("errors/html/error_general", ["heading" => "Not found", "message" => app_lang("record_not_found")]);
        }

        $request = $this->Gate_pass_requests_model->get_details(["id" => $visitor->gate_pass_request_id])->getRow();
        if (!$request || $request->deleted) {
            return $this->template->view("errors/html/error_general", ["heading" => "Not found", "message" => app_lang("record_not_found")]);
        }
        if (!$this->login_user->is_admin && !in_array((int)$request->department_id, $this->my_department_ids, true)) {
            app_redirect("forbidden");
        }

        $view_data["visitor"] = $visitor;
        return $this->template->view("gate_pass_department_requests/visitor_attachments_modal", $view_data);
    }

    /**
     * Download/view a visitor attachment file.
     * @param int $visitor_id
     * @param string $field One of: id_attachment_path, visa_attachment_path, photo_attachment_path, driving_license_attachment_path
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
        $relPath = preg_replace("#\.\.+#", "", $relPath);
        $relPath = ltrim($relPath, "/");
        $fullPath = WRITEPATH . "uploads/" . $relPath;
        if (!is_file($fullPath)) {
            show_404();
        }

        $request = $this->Gate_pass_requests_model->get_details(["id" => $visitor->gate_pass_request_id])->getRow();
        if (!$request || $request->deleted) {
            show_404();
        }
        if (!$this->login_user->is_admin && !in_array((int)$request->department_id, $this->my_department_ids, true)) {
            app_redirect("forbidden");
        }

        $mime = function_exists("mime_content_type") ? mime_content_type($fullPath) : "application/octet-stream";
        $name = basename($relPath);
        $download = (int)($this->request->getGet("download") ?? 0) === 1;
        $inline = !$download && (str_starts_with($mime, "image/") || $mime === "application/pdf");

        return $this->response
            ->setHeader("Content-Type", $mime)
            ->setHeader("Content-Disposition", ($inline ? "inline" : "attachment") . '; filename="' . addslashes($name) . '"')
            ->setBody(file_get_contents($fullPath));
    }

    private function _make_visitor_row($row)
    {
        $is_blocked = (int)($row->is_blocked ?? 0) === 1;
        $blocked_badge = $is_blocked
            ? "<span class='badge bg-danger'>" . app_lang("blocked") . "</span>"
            : "<span class='badge bg-success'>Clear</span>";
        $block_reason = trim((string)($row->block_reason ?? ""));

        $primary = !empty($row->is_primary) ? "<span class='badge bg-success'>" . app_lang("primary") . "</span>" : "";
        $attachments_btn = modal_anchor(
            get_uri("gate_pass_department_requests/visitor_attachments_modal"),
            "<i data-feather='paperclip' class='icon-16'></i> " . app_lang("attachments"),
            ["class" => "btn btn-default btn-sm", "title" => app_lang("visitor_attachments"), "data-modal-title" => app_lang("visitor_attachments"), "data-post-id" => $row->id]
        );
        return [
            $row->full_name ?? "-",
            $row->id_type ?? "-",
            $row->id_number ?? "-",
            $row->nationality ?? "-",
            $row->phone ?? "-",
            $row->role ?? "-",
            $blocked_badge,
            $block_reason !== "" ? esc($block_reason) : "-",
            $primary,
            $attachments_btn
        ];
    }

    private function _make_vehicle_row($row)
    {
        return [
            $row->plate_no ?? "-",
            $row->make ?? "-",
            $row->model ?? "-",
            $row->color ?? "-"
        ];
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
