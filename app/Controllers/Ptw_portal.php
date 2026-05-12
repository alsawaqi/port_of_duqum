<?php

namespace App\Controllers;

use App\Libraries\Pdf;

class Ptw_portal extends Security_Controller
{
    protected $Ptw_applications_model;
    protected $Ptw_requirement_definitions_model;
    protected $Ptw_requirement_responses_model;
    protected $Ptw_attachments_model;
    protected $Ptw_reviews_model;
    protected $Ptw_audit_logs_model;
    protected $Gate_pass_companies_model;

    public function __construct()
    {
        parent::__construct();

        $this->init_permission_checker("client");

        $this->Ptw_applications_model = model("App\\Models\\Ptw_applications_model");
        $this->Ptw_requirement_definitions_model = model("App\\Models\\Ptw_requirement_definitions_model");
        $this->Ptw_requirement_responses_model = model("App\\Models\\Ptw_requirement_responses_model");
        $this->Ptw_attachments_model = model("App\\Models\\Ptw_attachments_model");
        $this->Ptw_reviews_model = model("App\\Models\\Ptw_reviews_model");
        $this->Ptw_audit_logs_model = model("App\\Models\\Ptw_audit_logs_model");
        $this->Gate_pass_companies_model = model("App\\Models\\Gate_pass_companies_model");
    }

    public function index()
    {
        return $this->view();
    }

    function view($tab = "")
    {
        $view_data["tab"] = $tab;
        return $this->template->rander("ptw_portal/view", $view_data);
    }
    public function applications()
    {
        $this->_require_ptw_access();
        return $this->template->view("ptw_portal/applications/index");
    }

    public function applications_list_data()
    {
        $this->_require_ptw_access();

        $rows = $this->Ptw_applications_model->get_details([
            "applicant_user_id" => $this->login_user->id
        ])->getResult();

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->_make_application_row($row);
        }

        echo json_encode(["data" => $result]);
    }

    public function application_form($id = 0)
    {
        $this->_require_ptw_access();
        $this->_cleanup_stale_ptw_pending_uploads();

        $id = (int) $id;
        $app = $id ? $this->Ptw_applications_model->get_details(["id" => $id])->getRow() : null;
        if ($id && !$app) {
            app_redirect("forbidden");
        }
        if ($app && !$this->_can_access_application($app)) {
            app_redirect("forbidden");
        }

        if ($app && !$this->_can_edit_application($app)) {
            app_redirect("ptw_portal/application_details/" . $app->id);
        }

        $defs = $this->Ptw_requirement_definitions_model->get_active_definitions()->getResult();
        $response_rows = [];
        if ($app) {
            foreach ($this->Ptw_requirement_responses_model->get_by_application($app->id)->getResult() as $r) {
                $response_rows[] = $r;
            }
        }
        $responses = ptw_index_requirement_responses_with_default_others($response_rows, $defs);

        $session = \Config\Services::session();

        $companies_list = $this->Gate_pass_companies_model->get_details()->getResult();

        $view_data = [
            "model_info"          => $app,
            "definitions_grouped" => $this->_group_definitions($defs),
            "responses_index"     => $responses,
            "duration_days"       => $this->_calculate_duration_days($app ? $app->work_from : null, $app ? $app->work_to : null),
            "login_user"          => $this->login_user,
            "companies_list"      => $companies_list,
            "ptw_errors"          => $session->getFlashdata('ptw_errors') ?? [],
            "ptw_field_errors"    => $session->getFlashdata('ptw_field_errors') ?? [],
            "ptw_old_input"       => $session->getFlashdata('ptw_old_input') ?? [],
            "submit_stage_label"  => $this->_get_submit_stage_label($app),
        ];

        return $this->template->rander("ptw_portal/applications/form", $view_data);
    }


    private function _ensure_stage_review_row(int $application_id, string $stage)
{
    $stage = strtolower(trim($stage));
    if (!in_array($stage, ["hsse", "hmo", "terminal"], true)) {
        return;
    }

    // If there is already an open review row for this stage, keep it
    $open = $this->Ptw_reviews_model->get_open_review($application_id, $stage)->getRow();
    if ($open) {
        if (empty($open->received_at)) {
            $this->Ptw_reviews_model->ci_save(["received_at" => get_current_utc_time()], (int)$open->id);
        }
        return;
    }

    // Create a new review row for this revision cycle
    $review_data = [
        "ptw_application_id" => $application_id,
        "stage"              => $stage,
        "revision_no"        => $this->Ptw_reviews_model->get_next_revision_no($application_id, $stage),
        "received_at"        => get_current_utc_time(),
    ];

    $this->Ptw_reviews_model->ci_save($review_data);
}

    public function save_application()
    {
        $this->_require_ptw_access();
        $this->_cleanup_stale_ptw_pending_uploads();

        $id = (int) $this->request->getPost("id");
        $submit_mode = $this->request->getPost("submit_mode") === "submit" ? "submit" : "draft";

        $existing = $id ? $this->Ptw_applications_model->get_details(["id" => $id])->getRow() : null;
        if ($id && !$existing) {
            app_redirect("forbidden");
        }
        if ($existing && !$this->_can_edit_application($existing)) {
            app_redirect("forbidden");
        }

        $existing_stage = strtolower(trim((string)($existing->stage ?? "")));
        $existing_status = strtolower(trim((string)($existing->status ?? "")));
        if ($existing && $submit_mode === "submit" && $existing_status === "rejected") {
            app_redirect("forbidden");
        }

        $defs = $this->Ptw_requirement_definitions_model->get_active_definitions()->getResult();
        $defs_index = [];
        foreach ($defs as $d) {
            $defs_index[(int) $d->id] = $d;
        }
        foreach (["hazard_document", "ppe", "preparation"] as $category) {
            $virtual_other = ptw_virtual_other_requirement_definition($category);
            $defs_index[(int)$virtual_other->id] = $virtual_other;
        }

        [$errors, $field_errors] = $this->_validate_ptw_submission($existing, $defs_index, $submit_mode);
        if (count($errors)) {
            $session = \Config\Services::session();
            $session->setFlashdata('ptw_errors', $errors);
            $session->setFlashdata('ptw_field_errors', $field_errors);
            $old_input = $this->request->getPost();
            // Signature pad data URL can be large; avoid storing it in session flash.
            unset($old_input["signature_data"]);
            $old_input = $this->_remember_ptw_pending_uploads($old_input, $defs_index);
            $session->setFlashdata('ptw_old_input', $old_input);
            app_redirect("ptw_portal/application_form/" . ($id ?: ""));
            return;
        }

        $db = db_connect();
        $db->transStart();

        $now = get_current_utc_time();

        $data = [
            "reference" => $existing->reference ?? ("PTW-TMP-" . time() . "-" . rand(100, 999)),
            "applicant_user_id" => $existing->applicant_user_id ?? $this->login_user->id,
            "company_name" => trim((string) $this->request->getPost("company_name")),
            "applicant_name" => trim((string) $this->request->getPost("applicant_name")),
            "applicant_position" => trim((string) $this->request->getPost("applicant_position")),
            "contact_phone" => trim((string) $this->request->getPost("contact_phone")),
            "contact_email" => trim((string) $this->request->getPost("contact_email")),
            "work_description" => trim((string) $this->request->getPost("work_description")),
            "exact_location" => trim((string) $this->request->getPost("exact_location")),
            "work_supervisor_name" => trim((string) $this->request->getPost("work_supervisor_name")),
            "supervisor_contact_details" => trim((string) $this->request->getPost("supervisor_contact_details")),
            "total_workers" => (int) $this->request->getPost("total_workers"),
            "location_lat" => $this->_nullable_decimal($this->request->getPost("location_lat")),
            "location_lng" => $this->_nullable_decimal($this->request->getPost("location_lng")),
            "location_sector_name" => trim((string) $this->request->getPost("location_sector_name")),
            "location_description" => trim((string) $this->request->getPost("location_description")),
            "work_from" => $this->_normalize_datetime($this->request->getPost("work_from")),
            "work_to" => $this->_normalize_datetime($this->request->getPost("work_to")),
            "declaration_agreed" => $this->request->getPost("declaration_agreed") ? 1 : 0,
            "declaration_responsible_name" => trim((string) $this->request->getPost("declaration_responsible_name")),
            "declaration_function" => trim((string) $this->request->getPost("declaration_function")),
            "declaration_date" => $submit_mode === "submit" ? $now : ($existing->declaration_date ?? null),
        ];

        $target_stage = "draft";

if ($submit_mode === "draft") {
    // Keep revise requests in their current reviewer stage while contractor is editing.
    if (
        $existing &&
        $existing_status === "revise" &&
        in_array($existing_stage, ["hsse", "hmo", "terminal"], true)
    ) {
        $data["stage"] = $existing_stage;
        $data["status"] = "revise";
        $target_stage = $existing_stage;
    } else {
        $data["stage"] = "draft";
        $data["status"] = "draft";
        $target_stage = "draft";
    }
    $data["completed_at"] = null;
} else {
    // Default first submission goes to HSSE
    $target_stage = "hsse";

    // If contractor is re-submitting after revise, keep it in the same stage.
    if ($existing) {
        if (
            in_array($existing_stage, ["hsse", "hmo", "terminal"], true) &&
            $existing_status === "revise"
        ) {
            $target_stage = $existing_stage;
        }
    }

    $data["stage"] = $target_stage;
    $data["status"] = "submitted";
    $data["submitted_at"] = $existing->submitted_at ?? $now;
    $data["completed_at"] = null;
}

        $clean_data = clean_data($data);
        $save_id = $this->Ptw_applications_model->ci_save($clean_data, $id);
        if (!$save_id) {
            $db->transRollback();
            app_redirect("ptw_portal/application_form/" . ($id ?: ""));
            return;
        }

        $application_id = (int) $save_id;
        $application = $this->Ptw_applications_model->get_details(["id" => $application_id])->getRow();

        if (!$id) {
            $final_ref = $this->_generate_ptw_reference($application_id);
            $ref_data = ["reference" => $final_ref];
            $this->Ptw_applications_model->ci_save($ref_data, $application_id);
            $application->reference = $final_ref;
        }

        $this->_save_requirement_responses($application_id, $defs_index);
        $this->_save_signature_file($application_id);



        if ($submit_mode === "submit") {
            $this->_ensure_stage_review_row($application_id, $target_stage);
        
            $was_revision_cycle = $existing && strtolower((string)($existing->status ?? "")) === "revise";
        
            $this->_ptw_audit(
                $application_id,
                $was_revision_cycle
                    ? "applicant_resubmitted"
                    : ($id ? "applicant_updated_and_submitted" : "applicant_submitted"),
                [
                    "stage" => $target_stage,
                    "status" => "submitted",
                    "previous_status" => $existing->status ?? null,
                ]
            );
        } else {
            $this->_ptw_audit($application_id, $id ? "applicant_updated_draft" : "applicant_created_draft");
        }
       

        $db->transComplete();

        if ($db->transStatus() === false) {
            $session = \Config\Services::session();
            $session->setFlashdata('ptw_errors', ["Unable to save the PTW application. Please try again."]);
            app_redirect("ptw_portal/application_form/" . ($id ?: ""));
            return;
        }

        app_redirect("ptw_portal/application_details/" . $application_id);
    }

    public function application_details($id = 0)
    {
        $this->_require_ptw_access();

        $id = (int) $id;
        $app = $this->Ptw_applications_model->get_details(["id" => $id])->getRow();
        if (!$app || !$this->_can_access_application($app)) {
            app_redirect("forbidden");
        }

        $defs = $this->Ptw_requirement_definitions_model->get_active_definitions()->getResult();
        $response_rows = [];
        foreach ($this->Ptw_requirement_responses_model->get_by_application($app->id)->getResult() as $r) {
            $response_rows[] = $r;
        }
        $responses = ptw_index_requirement_responses_with_default_others($response_rows, $defs);

        $attachments = $this->Ptw_attachments_model->get_by_application($app->id)->getResult();
        $attachments_by_response = [];
        foreach ($attachments as $att) {
            $response_id = (int)($att->ptw_requirement_response_id ?? 0);
            if ($response_id > 0) {
                $attachments_by_response[$response_id] = $att;
            }
        }
        $hsse_reviews = $this->Ptw_reviews_model->get_details(["ptw_application_id" => $app->id, "stage" => "hsse"])->getResult();
        $hmo_reviews = $this->Ptw_reviews_model->get_details(["ptw_application_id" => $app->id, "stage" => "hmo"])->getResult();
        $terminal_reviews = $this->Ptw_reviews_model->get_details(["ptw_application_id" => $app->id, "stage" => "terminal"])->getResult();
        $audit_logs = $this->Ptw_audit_logs_model->get_by_application($app->id)->getResult();

        $view_data = [
            "app" => $app,
            "definitions_grouped" => $this->_group_definitions($defs),
            "responses_index" => $responses,
            "responses_by_definition" => $responses,
            "attachments" => $attachments,
            "attachments_by_response" => $attachments_by_response,
            "review_groups" => [
                "hsse" => $hsse_reviews,
                "hmo" => $hmo_reviews,
                "terminal" => $terminal_reviews,
            ],
            "audit_logs" => $audit_logs,
            "can_edit" => $this->_can_edit_application($app),
            "can_download_final_permit" => $this->_is_final_permit_available($app),
            "duration_days" => $this->_calculate_duration_days($app->work_from, $app->work_to),
        ];

        return $this->template->rander("ptw_portal/applications/details", $view_data);
    }

    public function download_attachment($id = 0)
    {
        $this->_require_ptw_access();

        $id = (int) $id;
        $row = $this->Ptw_attachments_model->get_one($id);
        if (!$row || empty($row->id) || (int)($row->deleted ?? 0) === 1) {
            // convenience: if response id was passed, resolve latest attachment by response id
            $db = db_connect();
            $att_table = $db->prefixTable("ptw_attachments");
            $row = $db->query("SELECT * FROM $att_table WHERE ptw_requirement_response_id=? AND deleted=0 ORDER BY id DESC LIMIT 1", [(int)$id])->getRow();
        }
        if (!$row) {
            app_redirect("forbidden");
        }

        $app = $this->Ptw_applications_model->get_details(["id" => $row->ptw_application_id])->getRow();
        if (!$app || !$this->_can_access_application($app)) {
            app_redirect("forbidden");
        }

        $full = WRITEPATH . "uploads/" . ltrim((string) $row->file_path, "/");
        if (!is_file($full)) {
            app_redirect("forbidden");
        }

        return $this->response->download($full, null)->setFileName($row->file_name);
    }

    public function download_signature($application_id = 0)
    {
        $this->_require_ptw_access();

        $app = $this->Ptw_applications_model->get_details(["id" => (int)$application_id])->getRow();
        if (!$app || !$this->_can_access_application($app) || empty($app->signature_file_path)) {
            app_redirect("forbidden");
        }

        $full = WRITEPATH . "uploads/" . ltrim((string) $app->signature_file_path, "/");
        if (!is_file($full)) {
            app_redirect("forbidden");
        }

        return $this->response->download($full, null)->setFileName($app->signature_file_name ?: basename($full));
    }

    public function download_final_permit($application_id = 0)
    {
        $this->_require_ptw_access();

        $app = $this->Ptw_applications_model->get_details(["id" => (int) $application_id])->getRow();
        if (!$app || !$this->_can_access_application($app) || !$this->_is_final_permit_available($app)) {
            app_redirect("forbidden");
        }

        $defs = $this->Ptw_requirement_definitions_model->get_active_definitions()->getResult();
        $response_rows = [];
        foreach ($this->Ptw_requirement_responses_model->get_by_application($app->id)->getResult() as $row) {
            $response_rows[] = $row;
        }
        $responses = ptw_index_requirement_responses_with_default_others($response_rows, $defs);

        $reviews = $this->Ptw_reviews_model->get_details(["ptw_application_id" => $app->id])->getResult();
        $html = $this->_ptw_final_permit_html($app, $this->_group_definitions($defs), $responses, $reviews);

        $pdf = new Pdf("");
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(12, 12, 12);
        $pdf->SetAutoPageBreak(true, 14);
        $pdf->AddPage();
        $pdf->writeHTML($html, true, false, true, false, "");

        $safe_ref = preg_replace("/[^A-Za-z0-9_-]+/", "_", (string) ($app->reference ?? "ptw")) ?: "ptw";
        $file_name = "issued-permit-" . $safe_ref . ".pdf";

        return $this->response
            ->setHeader("Content-Type", "application/pdf")
            ->setHeader("Content-Disposition", 'inline; filename="' . addslashes($file_name) . '"')
            ->setBody($pdf->Output($file_name, "S"));
    }

    // ---------------- helpers ----------------

    private function _require_ptw_access()
    {
        if ($this->login_user->is_admin) {
            return;
        }

        // Allow portal users. Reviewer restrictions are enforced later in inbox modules.
        return;
    }

    private function _can_access_application($app)
    {
        if ($this->login_user->is_admin) {
            return true;
        }

        if ((int) $app->applicant_user_id === (int) $this->login_user->id) {
            return true;
        }

        // PTW stage reviewers can also access
        $db = db_connect();
        $uid = (int) $this->login_user->id;
        $tables = ["ptw_hsse_users", "ptw_hmo_users", "ptw_terminal_users"];
        foreach ($tables as $t) {
            $table = $db->prefixTable($t);
            $row = $db->query("SELECT id FROM $table WHERE user_id=? AND deleted=0 AND status='active' LIMIT 1", [$uid])->getRow();
            if ($row) {
                return true;
            }
        }

        return false;
    }

    private function _can_edit_application($app)
    {
        $stage = strtolower(trim((string)($app->stage ?? "")));
        $status = strtolower(trim((string)($app->status ?? "")));

        if ((int)$app->applicant_user_id !== (int)$this->login_user->id && !$this->login_user->is_admin) {
            return false;
        }

        if ($status === "draft" && $stage === "draft") {
            return true;
        }

        return $status === "revise" && in_array($stage, ["hsse", "hmo", "terminal"], true);
    }

    private function _is_final_permit_available($app): bool
    {
        return strtolower((string) ($app->status ?? "")) === "approved"
            && strtolower((string) ($app->stage ?? "")) === "completed";
    }

    private function _ptw_final_permit_html($app, array $definitions_grouped, array $responses, array $reviews): string
    {
        $h = static function ($value): string {
            return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
        };

        $row = static function (string $label, $value) use ($h): string {
            $value = ($value === null || $value === "") ? "-" : $value;
            return "<tr><td class=\"k\">" . $h($label) . "</td><td>" . $h($value) . "</td></tr>";
        };

        $review_rows = "";
        foreach ($reviews as $review) {
            $reviewer = trim((string) (($review->first_name ?? "") . " " . ($review->last_name ?? "")));
            if ($reviewer === "") {
                $reviewer = $review->email ?? "-";
            }
            $review_rows .= "<tr>"
                . "<td>" . $h(ptw_stage_display_label($review->stage ?? "")) . "</td>"
                . "<td>" . $h(ptw_decision_display_label($review->decision ?? "")) . "</td>"
                . "<td>" . $h($reviewer) . "</td>"
                . "<td>" . $h(!empty($review->completed_at) ? format_to_datetime($review->completed_at) : "-") . "</td>"
                . "<td>" . $h(ptw_duration_between($review->received_at ?? null, $review->completed_at ?? null)) . "</td>"
                . "<td>" . nl2br($h($review->remarks ?? "-")) . "</td>"
                . "</tr>";
        }
        if ($review_rows === "") {
            $review_rows = "<tr><td colspan=\"6\">No approval records.</td></tr>";
        }

        $checklist_html = "";
        $section_labels = [
            "hazard_document" => "Hazards & Attachments",
            "ppe" => "Proposed PPE",
            "preparation" => "Work Area Preparations",
            "other" => "Other Requirements",
        ];
        foreach ($section_labels as $category => $label) {
            $rows = "";
            foreach (($definitions_grouped[$category] ?? []) as $def) {
                $response = $responses[(int) $def->id] ?? null;
                if (ptw_is_other_requirement_definition($def)) {
                    $other_items = ptw_decode_other_requirement_items($response);
                    if (!$other_items) {
                        $rows .= "<tr>"
                            . "<td>" . $h($def->label ?? "-") . "</td>"
                            . "<td>No</td>"
                            . "<td>-</td>"
                            . "</tr>";
                        continue;
                    }

                    foreach ($other_items as $item) {
                        $note = trim((string)($item["label"] ?? ""));
                        $attachment = trim((string)($item["attachment_name"] ?? ""));
                        if ($attachment !== "") {
                            $note .= ($note !== "" ? " - " : "") . $attachment;
                        }
                        $rows .= "<tr>"
                            . "<td>" . $h($def->label ?? "Other") . "</td>"
                            . "<td>Yes</td>"
                            . "<td>" . nl2br($h($note !== "" ? $note : "-")) . "</td>"
                            . "</tr>";
                    }
                    continue;
                }

                $rows .= "<tr>"
                    . "<td>" . $h($def->label ?? "-") . "</td>"
                    . "<td>" . (!empty($response) && (int) ($response->is_checked ?? 0) === 1 ? "Yes" : "No") . "</td>"
                    . "<td>" . nl2br($h($response->value_text ?? "-")) . "</td>"
                    . "</tr>";
            }
            if ($rows === "") {
                continue;
            }

            $checklist_html .= "<h3>" . $h($label) . "</h3>"
                . "<table class=\"grid\"><thead><tr><th>Requirement</th><th>Checked</th><th>Notes</th></tr></thead><tbody>"
                . $rows
                . "</tbody></table>";
        }

        $issued_at = !empty($app->completed_at) ? format_to_datetime($app->completed_at) : format_to_datetime(get_current_utc_time());
        $valid_from = !empty($app->work_from) ? format_to_datetime($app->work_from) : "-";
        $valid_to = !empty($app->work_to) ? format_to_datetime($app->work_to) : "-";
        $issued_label = ptw_terminal_approval_is_required($app)
            ? "PERMIT ISSUED - Approved by Terminal"
            : "PERMIT ISSUED - Terminal approval not required";

        $info_rows = ""
            . $row("Permit Reference", $app->reference ?? "-")
            . $row("Issued At", $issued_at)
            . $row("Company", $app->company_name ?? "-")
            . $row("Applicant", $app->applicant_name ?? "-")
            . $row("Contact", trim((string) (($app->contact_phone ?? "") . " " . ($app->contact_email ?? ""))))
            . $row("Supervisor", $app->work_supervisor_name ?? "-")
            . $row("Workers", $app->total_workers ?? "-")
            . $row("Work Location", $app->exact_location ?? "-")
            . $row("Valid From", $valid_from)
            . $row("Valid To", $valid_to)
            . $row("Terminal Approval", ptw_terminal_approval_is_required($app) ? "Required" : "Not required")
            . $row("Final Status", ptw_status_display_label($app->status ?? "approved"));

        $description = nl2br($h($app->work_description ?? "-"));

        return <<<HTML
<style>
  h1 { font-size: 18px; margin: 0 0 5px 0; color: #1f2a44; }
  h2 { font-size: 13px; margin: 12px 0 6px 0; color: #1f2a44; }
  h3 { font-size: 11px; margin: 10px 0 4px 0; color: #1f2a44; }
  .muted { font-size: 9px; color: #666; margin-bottom: 10px; }
  table.info, table.grid { width: 100%; border-collapse: collapse; font-size: 9px; }
  table.info td, table.grid th, table.grid td { border: 1px solid #cfd6e4; padding: 5px; vertical-align: top; }
  table.info td.k, table.grid th { background: #f3f6fb; font-weight: bold; }
  .issued { border: 1px solid #8cc6a4; background: #f0fbf5; color: #166534; padding: 7px; font-size: 10px; font-weight: bold; margin: 8px 0; }
  .desc { border: 1px solid #cfd6e4; padding: 7px; font-size: 9px; margin-bottom: 8px; }
</style>
<h1>Final Issued Permit to Work</h1>
<div class="muted">System-generated issued permit view. Manual signatures can be attached separately where required by PODC procedure.</div>
<div class="issued">{$issued_label}</div>
<table class="info"><tbody>{$info_rows}</tbody></table>
<h2>Work Description</h2>
<div class="desc">{$description}</div>
{$checklist_html}
<h2>Approval Trail</h2>
<table class="grid"><thead><tr><th>Stage</th><th>Decision</th><th>Reviewer</th><th>Reviewed At</th><th>Duration</th><th>Remarks</th></tr></thead><tbody>{$review_rows}</tbody></table>
HTML;
    }

    private function _get_submit_stage_label($app): string
    {
        if (!$app) {
            return "HSSE";
        }

        $stage = strtolower(trim((string)($app->stage ?? "")));
        $status = strtolower(trim((string)($app->status ?? "")));
        if ($status === "revise" && in_array($stage, ["hsse", "hmo", "terminal"], true)) {
            return strtoupper($stage);
        }

        return "HSSE";
    }

    private function _validate_ptw_submission($existing, array $defs_index, string $submit_mode): array
    {
        $errors       = [];
        $field_errors = [];

        $addError = function (string $message, string $field = '') use (&$errors, &$field_errors) {
            $errors[] = $message;
            if ($field !== '') {
                $field_errors[$field] = $message;
            }
        };

        if ($submit_mode === "submit") {
            $required = [
                "company_name"                 => "Company Name",
                "applicant_name"               => "Applicant Name",
                "applicant_position"           => "Applicant Position",
                "contact_phone"                => "Contact Number",
                "contact_email"                => "Email",
                "work_description"             => "Work Description",
                "work_from"                    => "Starting Date/Time",
                "work_to"                      => "Completion Date/Time",
                "exact_location"               => "Work Location",
                "work_supervisor_name"         => "Work Supervisor Name",
                "total_workers"                => "Total Number of Workers",
                "declaration_responsible_name" => "Responsible Party Name",
                "declaration_function"         => "Declaration Function",
            ];
            foreach ($required as $field => $label) {
                if (trim((string) $this->request->getPost($field)) === "") {
                    $addError($label . " is required", $field);
                }
            }
        }

        $email = trim((string) $this->request->getPost("contact_email"));
        if ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $addError("Email format is invalid", "contact_email");
        }

        $phone = trim((string) $this->request->getPost("contact_phone"));
        if ($phone !== "" && !preg_match('/^[0-9+\-\s]{6,20}$/', $phone)) {
            $addError("Contact Number should be numeric (digits, +, -, spaces allowed)", "contact_phone");
        }

        $workers = trim((string) $this->request->getPost("total_workers"));
        if ($workers !== "" && (!ctype_digit($workers) || (int)$workers <= 0)) {
            $addError("Total Number of Workers must be greater than 0", "total_workers");
        }

        $work_from = $this->_normalize_datetime($this->request->getPost("work_from"));
        $work_to   = $this->_normalize_datetime($this->request->getPost("work_to"));
        if ($work_from && $work_to && strtotime($work_to) < strtotime($work_from)) {
            $addError("Completion Date/Time must be after or equal to Starting Date/Time", "work_to");
        }

        if ($submit_mode === "submit") {
            if (!$this->request->getPost("declaration_agreed")) {
                $addError("Declaration agreement checkbox is required", "declaration_agreed");
            }

            $signature_data   = trim((string) $this->request->getPost("signature_data"));
            $has_new_signature = $this->_parse_signature_data($signature_data) !== null;
            $has_existing_sig = $existing && !empty($existing->signature_file_path);
            if ($signature_data !== "" && !$has_new_signature) {
                $addError("Signature format is invalid. Please sign again.", "signature_file");
            } elseif (!$has_new_signature && !$has_existing_sig) {
                $addError("Signature is required", "signature_file");
            }

            foreach ($defs_index as $def_id => $def) {
                if (ptw_is_other_requirement_definition($def)) {
                    continue;
                }

                $checked  = $this->request->getPost("req_{$def_id}_checked") ? 1 : 0;
                $text     = trim((string) $this->request->getPost("req_{$def_id}_text"));
                $file     = $this->request->getFile("req_{$def_id}_file");
                $existing_response      = $existing ? $this->Ptw_requirement_responses_model->get_one_by_app_and_def((int)$existing->id, $def_id) : null;
                $has_existing_attachment = $existing_response && !empty($existing_response->attachment_path);
                $has_pending_attachment = $this->_ptw_pending_upload_exists((string)$this->request->getPost("req_{$def_id}_pending_token"));

                if ((int)$def->is_mandatory === 1 && $checked !== 1) {
                    $addError($def->label . " must be checked", "req_{$def_id}_checked");
                }

                if ((int)$def->has_text_input === 1 && $checked === 1 && $text === "") {
                    $addError($def->label . " requires text input", "req_{$def_id}_text");
                }

                if ((int)$def->requires_attachment === 1 && $checked === 1) {
                    $has_new_upload = $file && $file->isValid() && !$file->hasMoved();
                    if (!$has_new_upload && !$has_existing_attachment && !$has_pending_attachment) {
                        $addError($def->label . " requires an attachment", "req_{$def_id}_file");
                    }
                }

                if ($file && $file->isValid() && !$file->hasMoved()) {
                    if (!$this->_is_allowed_file_for_definition($file->getClientExtension(), $def)) {
                        $addError($def->label . " has invalid file type", "req_{$def_id}_file");
                    }
                }
            }

            $this->_validate_ptw_other_items($existing, $defs_index, $addError);
        }

        return [$errors, $field_errors];
    }

    private function _save_requirement_responses(int $application_id, array $defs_index)
    {
        foreach ($defs_index as $def_id => $def) {
            if (ptw_is_other_requirement_definition($def)) {
                continue;
            }

            $checked = $this->request->getPost("req_{$def_id}_checked") ? 1 : 0;
            $text = trim((string) $this->request->getPost("req_{$def_id}_text"));

            $existing_response = $this->Ptw_requirement_responses_model->get_one_by_app_and_def($application_id, $def_id);
            $pending_token = (string)$this->request->getPost("req_{$def_id}_pending_token");

            $data = [
                "ptw_application_id" => $application_id,
                "ptw_requirement_definition_id" => $def_id,
                "is_checked" => $checked,
                "value_text" => $text,
                "attachment_path" => $existing_response->attachment_path ?? null,
            ];

            $clean_response = clean_data($data);
            $response_id = $this->Ptw_requirement_responses_model->ci_save($clean_response, $existing_response->id ?? 0);

            $file = $this->request->getFile("req_{$def_id}_file");
            if ($file && $file->isValid() && !$file->hasMoved()) {
                $rel_dir = "ptw/app_{$application_id}/requirements/";
                $dir = WRITEPATH . "uploads/" . $rel_dir;
                if (!is_dir($dir)) {
                    mkdir($dir, 0775, true);
                }

                $safe_ext = strtolower((string) $file->getClientExtension());
                $new_name = "req_{$def_id}_" . uniqid("", true) . "." . $safe_ext;
                $file->move($dir, $new_name);
                $rel_path = $rel_dir . $new_name;

                $att_path_data = ["attachment_path" => $rel_path];
                $this->Ptw_requirement_responses_model->ci_save($att_path_data, (int)$response_id);

                $db = db_connect();
                $att_table = $db->prefixTable("ptw_attachments");
                $db->query("UPDATE $att_table SET deleted=1 WHERE ptw_requirement_response_id=?", [(int)$response_id]);

                $att_data = [
                    "ptw_requirement_id"          => $def_id,
                    "ptw_application_id"          => $application_id,
                    "ptw_requirement_response_id" => (int)$response_id,
                    "file_name"                   => $file->getClientName(),
                    "file_path"                   => $rel_path,
                    "file_type"                   => (string) $file->getClientMimeType(),
                    "file_size"                   => (int) $file->getSize(),
                    "uploaded_by"                 => (int) $this->login_user->id,
                ];
                $this->Ptw_attachments_model->ci_save($att_data);
                $this->_delete_ptw_pending_upload($pending_token);
            } elseif ($this->_ptw_pending_upload_exists($pending_token)) {
                $consumed = $this->_consume_ptw_pending_upload($pending_token, $application_id, "req_{$def_id}_");
                if ($consumed) {
                    $att_path_data = ["attachment_path" => $consumed["file_path"]];
                    $this->Ptw_requirement_responses_model->ci_save($att_path_data, (int)$response_id);

                    $db = db_connect();
                    $att_table = $db->prefixTable("ptw_attachments");
                    $db->query("UPDATE $att_table SET deleted=1 WHERE ptw_requirement_response_id=?", [(int)$response_id]);

                    $att_data = [
                        "ptw_requirement_id"          => $def_id,
                        "ptw_application_id"          => $application_id,
                        "ptw_requirement_response_id" => (int)$response_id,
                        "file_name"                   => $consumed["file_name"],
                        "file_path"                   => $consumed["file_path"],
                        "file_type"                   => $consumed["file_type"],
                        "file_size"                   => (int)$consumed["file_size"],
                        "uploaded_by"                 => (int) $this->login_user->id,
                    ];
                    $this->Ptw_attachments_model->ci_save($att_data);
                }
            }
        }

        foreach (["hazard_document", "ppe", "preparation"] as $category) {
            $other_def = $this->_get_ptw_other_definition($defs_index, $category);
            if ($other_def) {
                $this->_save_ptw_other_items($application_id, $other_def);
            }
        }
    }

    private function _validate_ptw_other_items($existing, array $defs_index, callable $addError): void
    {
        foreach (["hazard_document", "ppe", "preparation"] as $category) {
            $def = $this->_get_ptw_other_definition($defs_index, $category);
            if (!$def) {
                continue;
            }

            $prefix = $this->_ptw_other_post_prefix($category);
            $existing_response = $existing ? $this->_get_existing_ptw_other_response((int)$existing->id, $category, $def) : null;
            foreach ($this->_get_ptw_other_post_rows($category) as $index => $row) {
                $label = trim((string)$row["label"]);
                $has_existing_attachment = false;
                if ($existing && $existing_response && trim((string)$row["existing_path"]) !== "") {
                    $has_existing_attachment = (bool)$this->_get_existing_ptw_other_attachment_item(
                        (int)$existing->id,
                        (int)$existing_response->id,
                        (int)$row["existing_id"],
                        (string)$row["existing_path"]
                    );
                }
                $file = $row["file"];
                $has_new_upload = $this->_ptw_file_has_upload($file);
                $has_pending_attachment = $this->_ptw_pending_upload_exists((string)$row["pending_token"]);

                if ($label === "" && !$has_new_upload && !$has_existing_attachment && !$has_pending_attachment) {
                    continue;
                }

                if ($label === "") {
                    $addError("Other " . $this->_ptw_other_category_label($category) . " requires a description", "{$prefix}_label_{$index}");
                }

                if ($category === "hazard_document" && !$has_new_upload && !$has_existing_attachment && !$has_pending_attachment) {
                    $addError("Other hazard/document requires an attachment", "{$prefix}_file_{$index}");
                }

                if ($file && $file->getError() !== UPLOAD_ERR_NO_FILE) {
                    if (!$file->isValid() || $file->hasMoved()) {
                        $addError("Other " . $this->_ptw_other_category_label($category) . " attachment is invalid", "{$prefix}_file_{$index}");
                    } elseif (!$this->_is_allowed_file_for_definition($file->getClientExtension(), $def)) {
                        $addError("Other " . $this->_ptw_other_category_label($category) . " has invalid file type", "{$prefix}_file_{$index}");
                    }
                }
            }
        }
    }

    private function _get_ptw_other_definition(array $defs_index, string $category)
    {
        foreach ($defs_index as $def) {
            if ((string)($def->category ?? "") === $category && ptw_is_other_requirement_definition($def)) {
                return $def;
            }
        }

        return null;
    }

    private function _ptw_other_post_prefix(string $category): string
    {
        return "other_" . preg_replace('/[^a-z0-9_]/', "", strtolower($category));
    }

    private function _ptw_other_category_label(string $category): string
    {
        $labels = [
            "hazard_document" => "hazard/document",
            "ppe" => "PPE",
            "preparation" => "preparation",
        ];

        return $labels[$category] ?? $category;
    }

    private function _get_ptw_post_array(string $key): array
    {
        $value = $this->request->getPost($key);
        if ($value === null) {
            return [];
        }

        return is_array($value) ? array_values($value) : [$value];
    }

    private function _get_ptw_file_array(string $key): array
    {
        $files = $this->request->getFileMultiple($key);
        if (!$files) {
            return [];
        }

        return is_array($files) ? array_values($files) : [$files];
    }

    private function _ptw_file_has_upload($file): bool
    {
        return $file && $file->getError() !== UPLOAD_ERR_NO_FILE && $file->isValid() && !$file->hasMoved();
    }

    private function _remember_ptw_pending_uploads(array $old_input, array $defs_index): array
    {
        foreach ($defs_index as $def_id => $def) {
            if (ptw_is_other_requirement_definition($def)) {
                continue;
            }

            $token_key = "req_{$def_id}_pending_token";
            $name_key = "req_{$def_id}_pending_name";
            $file = $this->request->getFile("req_{$def_id}_file");
            $old_token = trim((string)($old_input[$token_key] ?? ""));

            if ($this->_ptw_file_has_upload($file) && $this->_is_allowed_file_for_definition($file->getClientExtension(), $def)) {
                $this->_delete_ptw_pending_upload($old_token);
                $pending = $this->_store_ptw_pending_upload($file, "req_{$def_id}");
                if ($pending) {
                    $old_input[$token_key] = $pending["token"];
                    $old_input[$name_key] = $pending["file_name"];
                }
            } elseif ($this->_ptw_pending_upload_exists($old_token)) {
                $pending = $this->_ptw_pending_upload_info($old_token);
                $old_input[$token_key] = $old_token;
                $old_input[$name_key] = $pending["file_name"] ?? ($old_input[$name_key] ?? "");
            } else {
                unset($old_input[$token_key], $old_input[$name_key]);
            }
        }

        foreach (["hazard_document", "ppe", "preparation"] as $category) {
            $def = $this->_get_ptw_other_definition($defs_index, $category);
            if (!$def) {
                continue;
            }

            $prefix = $this->_ptw_other_post_prefix($category);
            $labels = $this->_get_ptw_post_array("{$prefix}_label");
            $existing_paths = $this->_get_ptw_post_array("{$prefix}_existing_path");
            $existing_names = $this->_get_ptw_post_array("{$prefix}_existing_name");
            $existing_ids = $this->_get_ptw_post_array("{$prefix}_existing_id");
            $pending_tokens = $this->_get_ptw_post_array("{$prefix}_pending_token");
            $pending_names = $this->_get_ptw_post_array("{$prefix}_pending_name");
            $files = $this->_get_ptw_file_array("{$prefix}_file");
            $count = max(count($labels), count($existing_paths), count($existing_names), count($existing_ids), count($pending_tokens), count($pending_names), count($files), 1);

            $new_pending_tokens = [];
            $new_pending_names = [];
            for ($i = 0; $i < $count; $i++) {
                $file = $files[$i] ?? null;
                $old_token = trim((string)($pending_tokens[$i] ?? ""));
                if ($this->_ptw_file_has_upload($file) && $this->_is_allowed_file_for_definition($file->getClientExtension(), $def)) {
                    $this->_delete_ptw_pending_upload($old_token);
                    $pending = $this->_store_ptw_pending_upload($file, "{$prefix}_{$i}");
                    $new_pending_tokens[$i] = $pending["token"] ?? "";
                    $new_pending_names[$i] = $pending["file_name"] ?? "";
                } elseif ($this->_ptw_pending_upload_exists($old_token)) {
                    $pending = $this->_ptw_pending_upload_info($old_token);
                    $new_pending_tokens[$i] = $old_token;
                    $new_pending_names[$i] = $pending["file_name"] ?? ($pending_names[$i] ?? "");
                } else {
                    $new_pending_tokens[$i] = "";
                    $new_pending_names[$i] = "";
                }
            }

            $old_input["{$prefix}_pending_token"] = $new_pending_tokens;
            $old_input["{$prefix}_pending_name"] = $new_pending_names;
        }

        return $old_input;
    }

    private function _ptw_pending_upload_dir(): string
    {
        $user_id = (int)($this->login_user->id ?? 0);
        return WRITEPATH . "uploads/ptw/pending/user_{$user_id}/";
    }

    private function _ptw_pending_upload_meta_path(string $token): ?string
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }

        return $this->_ptw_pending_upload_dir() . $token . ".json";
    }

    private function _store_ptw_pending_upload($file, string $context): ?array
    {
        if (!$this->_ptw_file_has_upload($file)) {
            return null;
        }

        $dir = $this->_ptw_pending_upload_dir();
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $ext = strtolower((string)$file->getClientExtension());
        $token = bin2hex(random_bytes(16));
        $stored_name = $token . ($ext ? "." . $ext : "");
        $file->move($dir, $stored_name);

        $meta = [
            "token" => $token,
            "context" => preg_replace('/[^a-zA-Z0-9_-]/', "_", $context),
            "file_name" => (string)$file->getClientName(),
            "file_type" => (string)$file->getClientMimeType(),
            "file_size" => (int)$file->getSize(),
            "extension" => $ext,
            "stored_name" => $stored_name,
            "created_at" => time(),
        ];

        file_put_contents($dir . $token . ".json", json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $meta;
    }

    private function _ptw_pending_upload_info(string $token): ?array
    {
        $meta_path = $this->_ptw_pending_upload_meta_path($token);
        if (!$meta_path || !is_file($meta_path)) {
            return null;
        }

        $meta = json_decode((string)file_get_contents($meta_path), true);
        if (!is_array($meta) || empty($meta["stored_name"])) {
            return null;
        }

        $file_path = $this->_ptw_pending_upload_dir() . basename((string)$meta["stored_name"]);
        if (!is_file($file_path)) {
            return null;
        }

        $meta["path"] = $file_path;
        return $meta;
    }

    private function _ptw_pending_upload_exists(string $token): bool
    {
        return (bool)$this->_ptw_pending_upload_info($token);
    }

    private function _consume_ptw_pending_upload(string $token, int $application_id, string $name_prefix): ?array
    {
        $meta = $this->_ptw_pending_upload_info($token);
        if (!$meta) {
            return null;
        }

        $rel_dir = "ptw/app_{$application_id}/requirements/";
        $dir = WRITEPATH . "uploads/" . $rel_dir;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $ext = (string)($meta["extension"] ?? pathinfo((string)$meta["file_name"], PATHINFO_EXTENSION));
        $new_name = preg_replace('/[^a-zA-Z0-9_-]/', "_", $name_prefix) . uniqid("", true) . ($ext ? "." . $ext : "");
        $target = $dir . $new_name;
        if (!@rename((string)$meta["path"], $target)) {
            if (!@copy((string)$meta["path"], $target)) {
                return null;
            }
            @unlink((string)$meta["path"]);
        }

        $this->_delete_ptw_pending_upload($token, false);

        return [
            "file_name" => (string)($meta["file_name"] ?? $new_name),
            "file_path" => $rel_dir . $new_name,
            "file_type" => (string)($meta["file_type"] ?? ""),
            "file_size" => is_file($target) ? filesize($target) : (int)($meta["file_size"] ?? 0),
        ];
    }

    private function _delete_ptw_pending_upload(string $token, bool $delete_file = true): void
    {
        $meta_path = $this->_ptw_pending_upload_meta_path($token);
        if (!$meta_path) {
            return;
        }

        if ($delete_file) {
            $meta = $this->_ptw_pending_upload_info($token);
            if ($meta && !empty($meta["path"])) {
                @unlink((string)$meta["path"]);
            }
        }

        if (is_file($meta_path)) {
            @unlink($meta_path);
        }
    }

    private function _cleanup_stale_ptw_pending_uploads(): void
    {
        $dir = $this->_ptw_pending_upload_dir();
        if (!is_dir($dir)) {
            return;
        }

        $cutoff = time() - 86400;
        foreach (glob($dir . "*.json") ?: [] as $meta_path) {
            $meta = json_decode((string)file_get_contents($meta_path), true);
            $created_at = (int)($meta["created_at"] ?? filemtime($meta_path));
            if ($created_at >= $cutoff) {
                continue;
            }

            $token = pathinfo($meta_path, PATHINFO_FILENAME);
            $this->_delete_ptw_pending_upload($token);
        }
    }

    private function _get_ptw_other_post_rows(string $category): array
    {
        $prefix = $this->_ptw_other_post_prefix($category);
        $labels = $this->_get_ptw_post_array("{$prefix}_label");
        $existing_paths = $this->_get_ptw_post_array("{$prefix}_existing_path");
        $existing_names = $this->_get_ptw_post_array("{$prefix}_existing_name");
        $existing_ids = $this->_get_ptw_post_array("{$prefix}_existing_id");
        $pending_tokens = $this->_get_ptw_post_array("{$prefix}_pending_token");
        $pending_names = $this->_get_ptw_post_array("{$prefix}_pending_name");
        $files = $this->_get_ptw_file_array("{$prefix}_file");

        $count = max(count($labels), count($existing_paths), count($existing_names), count($existing_ids), count($pending_tokens), count($pending_names), count($files));
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $rows[$i] = [
                "label" => trim((string)($labels[$i] ?? "")),
                "existing_path" => trim((string)($existing_paths[$i] ?? "")),
                "existing_name" => trim((string)($existing_names[$i] ?? "")),
                "existing_id" => (int)($existing_ids[$i] ?? 0),
                "pending_token" => trim((string)($pending_tokens[$i] ?? "")),
                "pending_name" => trim((string)($pending_names[$i] ?? "")),
                "file" => $files[$i] ?? null,
            ];
        }

        return $rows;
    }

    private function _save_ptw_other_items(int $application_id, $def): void
    {
        $def_id = (int)$def->id;
        $category = (string)$def->category;
        $existing_response = $this->_get_existing_ptw_other_response($application_id, $category, $def);
        $stored_definition_id = $def_id > 0 ? $def_id : null;
        $post_rows = $this->_get_ptw_other_post_rows($category);

        $has_any_other_data = false;
        foreach ($post_rows as $row) {
            if (
                trim((string)$row["label"]) !== ""
                || trim((string)$row["existing_path"]) !== ""
                || $this->_ptw_pending_upload_exists((string)$row["pending_token"])
                || $this->_ptw_file_has_upload($row["file"])
            ) {
                $has_any_other_data = true;
                break;
            }
        }

        if (!$has_any_other_data) {
            if ($existing_response) {
                $this->Ptw_requirement_responses_model->ci_save([
                    "is_checked" => 0,
                    "value_text" => null,
                    "attachment_path" => null,
                ], (int)$existing_response->id);
                $this->_delete_removed_ptw_other_attachments((int)$existing_response->id, []);
            }
            return;
        }

        $response_data = [
            "ptw_application_id" => $application_id,
            "ptw_requirement_definition_id" => $stored_definition_id,
            "is_checked" => 0,
            "value_text" => null,
            "attachment_path" => null,
        ];
        if ($existing_response) {
            unset($response_data["ptw_requirement_definition_id"]);
            $this->Ptw_requirement_responses_model->ci_save(clean_data($response_data), (int)$existing_response->id);
            $response_id = (int)$existing_response->id;
        } else {
            $response_id = $this->_insert_ptw_virtual_other_response($response_data);
        }

        if (!$response_id) {
            return;
        }

        $items = [];
        $kept_attachment_ids = [];
        foreach ($post_rows as $index => $row) {
            $label = trim((string)$row["label"]);
            $file = $row["file"];
            $has_new_upload = $this->_ptw_file_has_upload($file);
            $has_existing_attachment = trim((string)$row["existing_path"]) !== "";
            $pending_token = (string)$row["pending_token"];
            $has_pending_attachment = $this->_ptw_pending_upload_exists($pending_token);

            if ($label === "" && !$has_new_upload && !$has_existing_attachment && !$has_pending_attachment) {
                continue;
            }

            $item = [
                "label" => $label,
                "attachment_id" => 0,
                "attachment_path" => "",
                "attachment_name" => "",
            ];

            if ($has_new_upload) {
                $rel_dir = "ptw/app_{$application_id}/requirements/";
                $dir = WRITEPATH . "uploads/" . $rel_dir;
                if (!is_dir($dir)) {
                    mkdir($dir, 0775, true);
                }

                $safe_ext = strtolower((string)$file->getClientExtension());
                $new_name = "other_{$def_id}_{$index}_" . uniqid("", true) . "." . $safe_ext;
                $file->move($dir, $new_name);
                $rel_path = $rel_dir . $new_name;

                $att_data = [
                    "ptw_requirement_id" => $stored_definition_id,
                    "ptw_application_id" => $application_id,
                    "ptw_requirement_response_id" => $response_id,
                    "file_name" => $file->getClientName(),
                    "file_path" => $rel_path,
                    "file_type" => (string)$file->getClientMimeType(),
                    "file_size" => (int)$file->getSize(),
                    "uploaded_by" => (int)$this->login_user->id,
                ];
                $attachment_id = (int)$this->Ptw_attachments_model->ci_save($att_data);

                $item["attachment_id"] = $attachment_id;
                $item["attachment_path"] = $rel_path;
                $item["attachment_name"] = (string)$file->getClientName();
                $kept_attachment_ids[] = $attachment_id;
                $this->_delete_ptw_pending_upload($pending_token);
            } elseif ($has_pending_attachment) {
                $consumed = $this->_consume_ptw_pending_upload($pending_token, $application_id, "other_{$def_id}_{$index}_");
                if ($consumed) {
                    $att_data = [
                        "ptw_requirement_id" => $stored_definition_id,
                        "ptw_application_id" => $application_id,
                        "ptw_requirement_response_id" => $response_id,
                        "file_name" => $consumed["file_name"],
                        "file_path" => $consumed["file_path"],
                        "file_type" => $consumed["file_type"],
                        "file_size" => (int)$consumed["file_size"],
                        "uploaded_by" => (int)$this->login_user->id,
                    ];
                    $attachment_id = (int)$this->Ptw_attachments_model->ci_save($att_data);

                    $item["attachment_id"] = $attachment_id;
                    $item["attachment_path"] = $consumed["file_path"];
                    $item["attachment_name"] = $consumed["file_name"];
                    $kept_attachment_ids[] = $attachment_id;
                }
            } elseif ($has_existing_attachment) {
                $existing_item = $this->_get_existing_ptw_other_attachment_item(
                    $application_id,
                    $response_id,
                    (int)$row["existing_id"],
                    (string)$row["existing_path"]
                );
                if ($existing_item) {
                    $item["attachment_id"] = (int)$existing_item["attachment_id"];
                    $item["attachment_path"] = (string)$existing_item["attachment_path"];
                    $item["attachment_name"] = (string)$existing_item["attachment_name"];
                    if ($item["attachment_id"]) {
                        $kept_attachment_ids[] = (int)$item["attachment_id"];
                    }
                }
            }

            if ($item["label"] !== "" || $item["attachment_path"] !== "") {
                $items[] = $item;
            }
        }

        $first_attachment_path = null;
        foreach ($items as $item) {
            if (!empty($item["attachment_path"])) {
                $first_attachment_path = $item["attachment_path"];
                break;
            }
        }

        $payload = $items ? json_encode([
            "virtual_type" => "other_requirement",
            "category" => $category,
            "items" => $items,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        $this->Ptw_requirement_responses_model->ci_save([
            "is_checked" => $items ? 1 : 0,
            "value_text" => $payload,
            "attachment_path" => $first_attachment_path,
        ], $response_id);

        $this->_delete_removed_ptw_other_attachments($response_id, $kept_attachment_ids);
    }

    private function _insert_ptw_virtual_other_response(array $response_data): int
    {
        $db = db_connect();
        $table = $db->prefixTable("ptw_requirement_responses");
        $clean_data = clean_data($response_data);
        $clean_data["ptw_requirement_definition_id"] = null;
        $db->table($table)->insert($clean_data);

        return (int)$db->insertID();
    }

    private function _get_existing_ptw_other_response(int $application_id, string $category, $def = null)
    {
        $legacy_definition_id = (int)($def->id ?? 0);
        if ($legacy_definition_id > 0) {
            $legacy = $this->Ptw_requirement_responses_model->get_one_by_app_and_def($application_id, $legacy_definition_id);
            if ($legacy) {
                return $legacy;
            }
        }

        foreach ($this->Ptw_requirement_responses_model->get_by_application($application_id)->getResult() as $row) {
            $definition_id = (int)($row->ptw_requirement_definition_id ?? 0);
            if ($definition_id > 0) {
                continue;
            }

            $text = trim((string)($row->value_text ?? ""));
            if ($text === "" || ($text[0] ?? "") !== "{") {
                continue;
            }

            $decoded = json_decode($text, true);
            if (
                is_array($decoded)
                && (string)($decoded["virtual_type"] ?? "") === "other_requirement"
                && (string)($decoded["category"] ?? "") === $category
            ) {
                return $row;
            }
        }

        return null;
    }

    private function _get_existing_ptw_other_attachment_item(int $application_id, int $response_id, int $attachment_id, string $path): ?array
    {
        $db = db_connect();
        $att_table = $db->prefixTable("ptw_attachments");
        $params = [$application_id, $response_id];
        $where = "ptw_application_id=? AND ptw_requirement_response_id=? AND deleted=0";

        if ($attachment_id > 0) {
            $where .= " AND id=?";
            $params[] = $attachment_id;
        } else {
            $where .= " AND file_path=?";
            $params[] = $path;
        }

        $row = $db->query("SELECT id, file_name, file_path FROM $att_table WHERE $where LIMIT 1", $params)->getRow();
        if (!$row) {
            return null;
        }

        return [
            "attachment_id" => (int)$row->id,
            "attachment_path" => (string)$row->file_path,
            "attachment_name" => (string)$row->file_name,
        ];
    }

    private function _delete_removed_ptw_other_attachments(int $response_id, array $kept_attachment_ids): void
    {
        $db = db_connect();
        $att_table = $db->prefixTable("ptw_attachments");
        $kept_attachment_ids = array_values(array_unique(array_filter(array_map("intval", $kept_attachment_ids))));

        if ($kept_attachment_ids) {
            $placeholders = implode(",", array_fill(0, count($kept_attachment_ids), "?"));
            $params = array_merge([$response_id], $kept_attachment_ids);
            $db->query("UPDATE $att_table SET deleted=1 WHERE ptw_requirement_response_id=? AND deleted=0 AND id NOT IN ($placeholders)", $params);
        } else {
            $db->query("UPDATE $att_table SET deleted=1 WHERE ptw_requirement_response_id=? AND deleted=0", [$response_id]);
        }
    }

    private function _save_signature_file(int $application_id)
    {
        $signature_data = trim((string) $this->request->getPost("signature_data"));
        $parsed = $this->_parse_signature_data($signature_data);
        if (!$parsed) {
            return;
        }

        $ext = (string) $parsed["ext"];
        $mime = (string) $parsed["mime"];
        $binary = (string) $parsed["binary"];

        $rel_dir = "ptw/app_{$application_id}/signature/";
        $dir = WRITEPATH . "uploads/" . $rel_dir;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $new_name = "signature_" . uniqid("", true) . "." . $ext;
        $full_path = $dir . $new_name;
        if (@file_put_contents($full_path, $binary) === false) {
            return;
        }

        $sig_data = [
            "signature_file_name" => "signature." . $ext,
            "signature_file_path" => $rel_dir . $new_name,
            "signature_file_type" => $mime,
            "signature_file_size" => strlen($binary),
        ];
        $this->Ptw_applications_model->ci_save($sig_data, $application_id);
    }

    private function _parse_signature_data(string $signature_data): ?array
    {
        if ($signature_data === "") {
            return null;
        }

        if (!preg_match('/^data:image\/(png|jpe?g|webp);base64,(.+)$/i', $signature_data, $m)) {
            return null;
        }

        $type = strtolower((string) $m[1]);
        if ($type === "jpg") {
            $type = "jpeg";
        }

        $raw_base64 = str_replace(" ", "+", (string) $m[2]);
        $binary = base64_decode($raw_base64, true);
        if ($binary === false || strlen($binary) < 32 || strlen($binary) > (5 * 1024 * 1024)) {
            return null;
        }

        $mime = "image/" . $type;
        if (function_exists("getimagesizefromstring")) {
            $info = @getimagesizefromstring($binary);
            $allowed_mimes = ["image/png", "image/jpeg", "image/webp"];
            $detected = strtolower((string)($info["mime"] ?? ""));
            if ($detected === "" || !in_array($detected, $allowed_mimes, true)) {
                return null;
            }
            $mime = $detected;
        }

        $ext_map = [
            "image/png" => "png",
            "image/jpeg" => "jpg",
            "image/webp" => "webp",
        ];
        $ext = $ext_map[$mime] ?? null;
        if (!$ext) {
            return null;
        }

        return [
            "binary" => $binary,
            "mime"   => $mime,
            "ext"    => $ext,
        ];
    }

    private function _ensure_hsse_review_row(int $application_id)
    {
        $latest = $this->Ptw_reviews_model->get_open_or_latest_stage_row($application_id, "hsse");
        if ($latest && (int)$latest->revision_no >= 1 && empty($latest->decision)) {
            if (empty($latest->received_at)) {
                $received_data = ["received_at" => get_current_utc_time()];
                $this->Ptw_reviews_model->ci_save($received_data, $latest->id);
            }
            return;
        }

        $next_revision = $latest ? ((int)$latest->revision_no + 1) : 1;
        $review_data = [
            "ptw_application_id" => $application_id,
            "stage"              => "hsse",
            "revision_no"        => $next_revision,
            "received_at"        => get_current_utc_time(),
        ];
        $this->Ptw_reviews_model->ci_save($review_data);
    }

    private function _ptw_audit(int $application_id, string $action, array $meta = [])
    {
        $payload = $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        $audit_data = [
            "ptw_application_id" => $application_id,
            "user_id"            => (int) $this->login_user->id,
            "action"             => $action,
            "meta"               => $payload,
            "ip_address"         => (string) $this->request->getIPAddress(),
            "user_agent"         => substr((string) ($this->request->getUserAgent() ? $this->request->getUserAgent()->__toString() : ""), 0, 512),
        ];
        $this->Ptw_audit_logs_model->ci_save($audit_data);
    }

    private function _group_definitions(array $defs): array
    {
        return ptw_group_definitions_with_default_others($defs);
    }

    private function _generate_ptw_reference(int $id): string
    {
        return "PTW-" . date("Y") . "-" . str_pad((string)$id, 6, "0", STR_PAD_LEFT);
    }

    private function _normalize_datetime($value)
    {
        $value = trim((string) $value);
        if ($value === "") {
            return null;
        }

        $value = str_replace("T", " ", $value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}$/', $value)) {
            $value .= ":00";
        }

        return $value;
    }

    private function _nullable_decimal($value)
    {
        $v = trim((string) $value);
        if ($v === "") {
            return null;
        }
        return is_numeric($v) ? $v : null;
    }

    private function _calculate_duration_days($from, $to): ?int
    {
        if (!$from || !$to) {
            return null;
        }
        $a = strtotime((string) $from);
        $b = strtotime((string) $to);
        if (!$a || !$b) {
            return null;
        }
        if ($b < $a) {
            return 0;
        }
        return (int) ceil(($b - $a) / 86400);
    }

    private function _is_allowed_file_for_definition($extension, $def): bool
    {
        $extension = strtolower((string) $extension);
        $allowed = trim((string) ($def->allowed_extensions ?? ""));
        if ($allowed === "") {
            return true;
        }
        $allowed_list = array_filter(array_map(function ($v) {
            return strtolower(trim((string)$v));
        }, explode(",", $allowed)));
        return in_array($extension, $allowed_list, true);
    }

    private function _make_application_row($row)
    {
        $statusClass = "badge bg-secondary";
        $status = (string) $row->status;
        if ($status === "submitted") $statusClass = "badge bg-primary";
        if ($status === "approved") $statusClass = "badge bg-success";
        if ($status === "rejected") $statusClass = "badge bg-danger";
        if ($status === "revise") $statusClass = "badge bg-warning text-dark";

        $stage = strtolower(trim((string)($row->stage ?? "")));
        $stageClass = "badge bg-secondary";
        if ($stage === "draft") $stageClass = "badge bg-light text-dark border";
        if ($stage === "hsse") $stageClass = "badge bg-info";
        if ($stage === "hmo") $stageClass = "badge bg-primary";
        if ($stage === "terminal") $stageClass = "badge bg-dark";
        if ($stage === "completed") $stageClass = "badge bg-success";

        $statusBadge = "<span class='" . $statusClass . "'>" . ptw_status_display_label($status) . "</span>";
        $stageLabel = ptw_stage_display_label($stage);
        $stageBadge = "<span class='" . $stageClass . "'>" . esc($stageLabel) . "</span>";
        $statusStage = "<div class='d-flex justify-content-center align-items-center gap-1 flex-wrap'>" . $statusBadge . $stageBadge . "</div>";

        $actions = anchor(get_uri("ptw_portal/application_details/" . $row->id), "<i data-feather='eye' class='icon-14'></i>", [
            "class" => "btn btn-default btn-sm",
            "title" => "View"
        ]);

        if ($this->_can_edit_application($row)) {
            $actions .= " " . anchor(get_uri("ptw_portal/application_form/" . $row->id), "<i data-feather='edit' class='icon-14'></i>", [
                "class" => "btn btn-default btn-sm",
                "title" => "Edit"
            ]);
        }

        return [
            $row->reference,
            esc($row->company_name),
            esc($row->applicant_name),
            esc($row->work_supervisor_name),
            esc(substr((string) $row->work_from, 0, 16)),
            esc(substr((string) $row->work_to, 0, 16)),
            $statusStage,
            $actions,
        ];
    }
}
