<?php

namespace App\Controllers;

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
        $responses = [];
        if ($app) {
            foreach ($this->Ptw_requirement_responses_model->get_by_application($app->id)->getResult() as $r) {
                $responses[(int) $r->ptw_requirement_definition_id] = $r;
            }
        }

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

        $id = (int) $this->request->getPost("id");
        $submit_mode = $this->request->getPost("submit_mode") === "submit" ? "submit" : "draft";

        $existing = $id ? $this->Ptw_applications_model->get_details(["id" => $id])->getRow() : null;
        if ($id && !$existing) {
            app_redirect("forbidden");
        }
        if ($existing && !$this->_can_edit_application($existing)) {
            app_redirect("forbidden");
        }

        $defs = $this->Ptw_requirement_definitions_model->get_active_definitions()->getResult();
        $defs_index = [];
        foreach ($defs as $d) {
            $defs_index[(int) $d->id] = $d;
        }

        [$errors, $field_errors] = $this->_validate_ptw_submission($existing, $defs_index, $submit_mode);
        if (count($errors)) {
            $session = \Config\Services::session();
            $session->setFlashdata('ptw_errors', $errors);
            $session->setFlashdata('ptw_field_errors', $field_errors);
            $session->setFlashdata('ptw_old_input', $this->request->getPost());
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
    $data["stage"] = "draft";
    $data["status"] = "draft";
    $data["completed_at"] = null;
    $target_stage = "draft";
} else {
    // Default first submission goes to HSSE
    $target_stage = "hsse";

    // If contractor is re-submitting after revise/rejected,
    // keep it in the SAME current stage (hsse/hmo/terminal)
    if ($existing) {
        $existing_stage = strtolower(trim((string)($existing->stage ?? "")));
        $existing_status = strtolower(trim((string)($existing->status ?? "")));

        if (
            in_array($existing_stage, ["hsse", "hmo", "terminal"], true) &&
            in_array($existing_status, ["revise", "rejected"], true)
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
        
            $was_revision_cycle = $existing && in_array(strtolower((string)($existing->status ?? "")), ["revise", "rejected"], true);
        
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
        $responses = [];
        foreach ($this->Ptw_requirement_responses_model->get_by_application($app->id)->getResult() as $r) {
            $responses[(int) $r->ptw_requirement_definition_id] = $r;
        }

        $attachments = $this->Ptw_attachments_model->get_by_application($app->id)->getResult();
        $audit_logs = $this->Ptw_audit_logs_model->get_by_application($app->id)->getResult();

        $view_data = [
            "app" => $app,
            "definitions_grouped" => $this->_group_definitions($defs),
            "responses_index" => $responses,
            "attachments" => $attachments,
            "audit_logs" => $audit_logs,
            "can_edit" => $this->_can_edit_application($app),
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
        if ((int)$app->applicant_user_id !== (int)$this->login_user->id && !$this->login_user->is_admin) {
            return false;
        }
    
        // Admin can always edit (optional, keep your current behavior)
        if ($this->login_user->is_admin) {
            return true;
        }
    
        $stage = strtolower(trim((string)($app->stage ?? "")));
        $status = strtolower(trim((string)($app->status ?? "")));
    
        // Draft is editable
        if ($status === "draft") {
            return true;
        }
    
        // Contractor can edit ONLY when reviewer requested changes (revise/rejected),
        // and only while request is still in a review stage (not completed)
        if (in_array($status, ["revise", "rejected"], true) && in_array($stage, ["hsse", "hmo", "terminal"], true)) {
            return true;
        }
    
        // submitted / in_review / approved / completed => no edit
        return false;
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

            $sig              = $this->request->getFile("signature_file");
            $has_existing_sig = $existing && !empty($existing->signature_file_path);
            if ((!$sig || !$sig->isValid() || $sig->hasMoved()) && !$has_existing_sig) {
                $addError("Signature file is required", "signature_file");
            }

            foreach ($defs_index as $def_id => $def) {
                $checked  = $this->request->getPost("req_{$def_id}_checked") ? 1 : 0;
                $text     = trim((string) $this->request->getPost("req_{$def_id}_text"));
                $file     = $this->request->getFile("req_{$def_id}_file");
                $existing_response      = $existing ? $this->Ptw_requirement_responses_model->get_one_by_app_and_def((int)$existing->id, $def_id) : null;
                $has_existing_attachment = $existing_response && !empty($existing_response->attachment_path);

                if ((int)$def->is_mandatory === 1 && $checked !== 1) {
                    $addError($def->label . " must be checked", "req_{$def_id}_checked");
                }

                if ((int)$def->has_text_input === 1 && $checked === 1 && $text === "") {
                    $addError($def->label . " requires text input", "req_{$def_id}_text");
                }

                if ((int)$def->requires_attachment === 1 && $checked === 1) {
                    $has_new_upload = $file && $file->isValid() && !$file->hasMoved();
                    if (!$has_new_upload && !$has_existing_attachment) {
                        $addError($def->label . " requires an attachment", "req_{$def_id}_file");
                    }
                }

                if ($file && $file->isValid() && !$file->hasMoved()) {
                    if (!$this->_is_allowed_file_for_definition($file->getClientExtension(), $def)) {
                        $addError($def->label . " has invalid file type", "req_{$def_id}_file");
                    }
                }
            }
        }

        return [$errors, $field_errors];
    }

    private function _save_requirement_responses(int $application_id, array $defs_index)
    {
        foreach ($defs_index as $def_id => $def) {
            $checked = $this->request->getPost("req_{$def_id}_checked") ? 1 : 0;
            $text = trim((string) $this->request->getPost("req_{$def_id}_text"));

            $existing_response = $this->Ptw_requirement_responses_model->get_one_by_app_and_def($application_id, $def_id);

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
            }
        }
    }

    private function _save_signature_file(int $application_id)
    {
        $file = $this->request->getFile("signature_file");
        if (!$file || !$file->isValid() || $file->hasMoved()) {
            return;
        }

        $allowed = ["pdf", "docx", "jpg", "jpeg", "png", "webp"];
        $ext = strtolower((string) $file->getClientExtension());
        if (!in_array($ext, $allowed, true)) {
            return;
        }

        $rel_dir = "ptw/app_{$application_id}/signature/";
        $dir = WRITEPATH . "uploads/" . $rel_dir;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $new_name = "signature_" . uniqid("", true) . "." . $ext;
        $file->move($dir, $new_name);

        $sig_data = [
            "signature_file_name" => $file->getClientName(),
            "signature_file_path" => $rel_dir . $new_name,
            "signature_file_type" => (string) $file->getClientMimeType(),
            "signature_file_size" => (int) $file->getSize(),
        ];
        $this->Ptw_applications_model->ci_save($sig_data, $application_id);
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
        $grouped = [
            "hazard_document" => [],
            "ppe" => [],
            "preparation" => [],
            "other" => [],
        ];

        foreach ($defs as $d) {
            $cat = (string) $d->category;
            if (!isset($grouped[$cat])) {
                $grouped[$cat] = [];
            }
            $grouped[$cat][] = $d;
        }

        return $grouped;
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
            "<span class='" . $statusClass . "'>" . ucwords(str_replace("_", " ", $status)) . "</span>",
            $actions,
        ];
    }
}