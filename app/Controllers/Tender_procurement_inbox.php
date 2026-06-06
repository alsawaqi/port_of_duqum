<?php

namespace App\Controllers;

use App\Models\Gate_pass_companies_model;
use App\Models\Gate_pass_departments_model;
use App\Models\Tender_bid_requirements_model;
use App\Models\Tender_committee_users_model;
use App\Models\Tender_commercial_users_model;
use App\Models\Tender_communications_model;
use App\Models\Tender_documents_model;
use App\Models\Tender_evaluations_model;
use App\Models\Tender_extensions_model;
use App\Models\Tender_invited_vendors_model;
use App\Models\Tender_requests_model;
use App\Models\Tender_request_team_members_model;
use App\Models\Tender_request_vendors_model;
use App\Models\Tender_rfq_details_model;
use App\Models\Tender_rfq_items_model;
use App\Models\Tender_target_specialties_model;
use App\Models\Tender_team_members_model;
use App\Models\Tender_technical_users_model;
use App\Models\Tenders_model;
use App\Libraries\Tender_testing_stage;
use CodeIgniter\I18n\Time;

class Tender_procurement_inbox extends Security_Controller
{
    private const TENDER_EMAILS_ENABLED = false;

    protected $db;
    protected $Tender_requests_model;
    protected $Tenders_model;
    protected $Tender_documents_model;
    protected $Tender_target_specialties_model;
    protected $Tender_invited_vendors_model;
    protected $Tender_request_vendors_model;
    protected $Tender_request_team_members_model;
    protected $Tender_team_members_model;
    protected $Tender_evaluations_model;
    protected $Tender_bid_requirements_model;
    protected $Tender_communications_model;
    protected $Tender_extensions_model;
    protected $Tender_rfq_details_model;
    protected $Tender_rfq_items_model;
    protected $Tender_technical_users_model;
    protected $Tender_commercial_users_model;
    protected $Tender_committee_users_model;
    protected $Tender_testing_stage;
    protected $Gate_pass_companies_model;
    protected $Gate_pass_departments_model;

    public function __construct()
    {
        parent::__construct();
        $this->access_only_team_members();

        $this->Tender_requests_model = new Tender_requests_model();
        $this->Tenders_model = new Tenders_model();
        $this->Tender_documents_model = new Tender_documents_model();
        $this->Tender_target_specialties_model = new Tender_target_specialties_model();
        $this->Tender_invited_vendors_model = new Tender_invited_vendors_model();
        $this->Tender_request_vendors_model = new Tender_request_vendors_model();
        $this->Tender_request_team_members_model = new Tender_request_team_members_model();
        $this->Tender_team_members_model = new Tender_team_members_model();
        $this->Tender_evaluations_model = new Tender_evaluations_model();
        $this->Tender_bid_requirements_model = new Tender_bid_requirements_model();
        $this->Tender_communications_model = new Tender_communications_model();
        $this->Tender_extensions_model = new Tender_extensions_model();
        $this->Tender_rfq_details_model = new Tender_rfq_details_model();
        $this->Tender_rfq_items_model = new Tender_rfq_items_model();
        $this->Tender_technical_users_model = new Tender_technical_users_model();
        $this->Tender_commercial_users_model = new Tender_commercial_users_model();
        $this->Tender_committee_users_model = new Tender_committee_users_model();
        $this->Tender_testing_stage = new Tender_testing_stage();
        $this->Gate_pass_companies_model = new Gate_pass_companies_model();
        $this->Gate_pass_departments_model = new Gate_pass_departments_model();
        $this->db = db_connect();
        $this->_ensure_tender_site_visit_columns();
    }

    public function index()
    {
        $this->access_only_tender("procurement", "view");
        return $this->template->rander("tender_procurement_inbox/index");
    }

    public function list_data()
    {
        $this->access_only_tender("procurement", "view");
        $this->Tenders_model->auto_progress_workflow();

        $req = $this->db->prefixTable("tender_requests");
        $t = $this->db->prefixTable("tenders");
        $companies = $this->db->prefixTable("companies");
        $departments = $this->db->prefixTable("departments");

        $sql = "SELECT
                    t.id AS tender_id,
                    t.tender_request_id,
                    t.reference,
                    COALESCE(t.created_at, req.created_at) AS created_at,
                    COALESCE(t.title, req.subject) AS subject,
                    COALESCE(t.company_id, req.company_id) AS company_id,
                    company.name AS company_name,
                    COALESCE(t.department_id, req.department_id) AS department_id,
                    department.name AS department_name,
                    COALESCE(t.tender_type, req.tender_type, 'open') AS tender_type,
                    COALESCE(req.status, 'direct') AS request_status,
                    t.status AS tender_status,
                    t.workflow_stage AS tender_workflow_stage,
                    t.procurement_manager_status,
                    t.procurement_manager_submitted_at,
                    t.procurement_manager_reviewed_at,
                    t.release_at,
                    t.published_at,
                    t.closing_at
                FROM $t t
                LEFT JOIN $req req
                    ON req.id = t.tender_request_id
                   AND req.deleted = 0
                LEFT JOIN $companies company
                    ON company.id = COALESCE(t.company_id, req.company_id)
                LEFT JOIN $departments department
                    ON department.id = COALESCE(t.department_id, req.department_id)
                WHERE t.deleted = 0

                UNION ALL

                SELECT
                    NULL AS tender_id,
                    req.id AS tender_request_id,
                    req.reference,
                    req.created_at,
                    req.subject,
                    req.company_id,
                    company.name AS company_name,
                    req.department_id,
                    department.name AS department_name,
                    req.tender_type,
                    req.status AS request_status,
                    '' AS tender_status,
                    '' AS tender_workflow_stage,
                    'draft' AS procurement_manager_status,
                    NULL AS procurement_manager_submitted_at,
                    NULL AS procurement_manager_reviewed_at,
                    NULL AS release_at,
                    NULL AS published_at,
                    NULL AS closing_at
                FROM $req req
                LEFT JOIN $companies company ON company.id = req.company_id
                LEFT JOIN $departments department ON department.id = req.department_id
                WHERE req.deleted = 0
                  AND req.status = 'committee_approved'
                  AND NOT EXISTS (
                        SELECT 1
                        FROM $t
                        WHERE $t.deleted = 0
                          AND $t.tender_request_id = req.id
                  )
                ORDER BY created_at DESC, tender_id DESC, tender_request_id DESC";

        $list = $this->db->query($sql)->getResult();
        $result = [];

        foreach ($list as $row) {
            $result[] = $this->_make_row($row);
        }

        return $this->response->setJSON(["data" => $result]);
    }

    public function vendor_categories_suggestion()
    {
        $this->access_only_tender("procurement", "view");

        $q = trim((string) $this->request->getPost("q"));
        $cats = $this->db->prefixTable("vendor_categories");
        $where = "WHERE deleted=0";

        if ($q !== "") {
            $like = $this->db->escapeLikeString($q);
            $where .= " AND name LIKE '%$like%'";
        }

        $rows = $this->db->query("SELECT id, name FROM $cats $where ORDER BY name ASC LIMIT 30")->getResult();
        $out = [];

        foreach ($rows as $row) {
            $out[] = ["id" => (int) $row->id, "text" => (string) $row->name];
        }

        return $this->response->setJSON($out);
    }

    public function vendor_subcategories_suggestion()
    {
        $this->access_only_tender("procurement", "view");

        $category_id = (int) $this->request->getPost("category_id");
        $q = trim((string) $this->request->getPost("q"));
        if (!$category_id) {
            return $this->response->setJSON([]);
        }

        $subs = $this->db->prefixTable("vendor_sub_categories");
        $where = "WHERE deleted=0 AND vendor_category_id=" . $category_id;

        if ($q !== "") {
            $like = $this->db->escapeLikeString($q);
            $where .= " AND name LIKE '%$like%'";
        }

        $rows = $this->db->query("SELECT id, name FROM $subs $where ORDER BY name ASC LIMIT 30")->getResult();
        $out = [];
        foreach ($rows as $row) {
            $out[] = ["id" => (int) $row->id, "text" => (string) $row->name];
        }

        return $this->response->setJSON($out);
    }

    public function get_vendor_sub_categories_dropdown()
    {
        $this->access_only_tender("procurement", "view");

        $vendor_category_id = (int) $this->request->getGet("vendor_category_id");
        if (!$vendor_category_id) {
            return $this->response->setBody("<option value=''>- " . app_lang("select") . " -</option>");
        }

        $sub = $this->db->prefixTable("vendor_sub_categories");
        $rows = $this->db->query(
            "SELECT id, name
             FROM $sub
             WHERE deleted=0
               AND vendor_category_id=?
             ORDER BY name ASC",
            [$vendor_category_id]
        )->getResult();

        $options = "<option value=''>- " . app_lang("select") . " -</option>";
        foreach ($rows as $row) {
            $options .= "<option value='" . (int) $row->id . "'>" . esc($row->name) . "</option>";
        }

        return $this->response->setBody($options);
    }

    public function search_vendors()
    {
        $this->access_only_tender("procurement", "view");

        $q = trim((string) ($this->request->getGet("q") ?: $this->request->getPost("q")));
        $vendors = $this->db->prefixTable("vendors");
        $groups = $this->db->prefixTable("vendor_groups");
        $grades = $this->db->prefixTable("vendor_grades");
        $where = "$vendors.deleted=0 AND $vendors.status='approved'";
        $params = [];

        if ($q !== "") {
            $like = "%" . $this->db->escapeLikeString($q) . "%";
            $where .= " AND (
                $vendors.vendor_name LIKE ?
                OR $vendors.email LIKE ?
                OR $vendors.cr_number LIKE ?
                OR $vendors.phone LIKE ?
            )";
            $params = [$like, $like, $like, $like];
        }

        $rows = $this->db->query(
            "SELECT
                $vendors.id,
                COALESCE(NULLIF($vendors.vendor_name, ''), $vendors.email) AS vendor_name,
                $vendors.email,
                $vendors.cr_number,
                $groups.name AS group_name,
                $groups.code AS group_code,
                $grades.name AS grade_name,
                $grades.code AS grade_code
             FROM $vendors
             LEFT JOIN $groups ON $groups.id=$vendors.vendor_group_id AND $groups.deleted=0
             LEFT JOIN $grades ON $grades.id=$vendors.vendor_grade_id AND $grades.deleted=0
             WHERE $where
             ORDER BY vendor_name ASC
             LIMIT 30",
            $params
        )->getResult();

        $out = [];
        foreach ($rows as $row) {
            $grade = function_exists("vendor_grade_label") ? vendor_grade_label($row->grade_name ?? "", $row->grade_code ?? "") : trim((string) ($row->grade_code ?? ""));
            $group = trim((string) ($row->group_name ?? ""));
            if ($group !== "" && !empty($row->group_code)) {
                $group .= " (" . $row->group_code . ")";
            }

            $out[] = [
                "id" => (int) $row->id,
                "name" => trim((string) ($row->vendor_name ?? "")) ?: ("Vendor #" . (int) $row->id),
                "email" => (string) ($row->email ?? ""),
                "cr_number" => (string) ($row->cr_number ?? ""),
                "group" => $group,
                "grade" => $grade,
            ];
        }

        return $this->response->setJSON(["vendors" => $out]);
    }

    public function modal_form()
    {
        $this->validate_submitted_data([
            "id" => "numeric",
            "tender_id" => "numeric",
        ]);
        $this->access_only_tender("procurement", "view");

        $request_id = (int) $this->request->getPost("id");
        $tender_id = (int) $this->request->getPost("tender_id");

        return $this->template->view("tender_procurement_inbox/modal_form", $this->_get_tender_form_data($request_id, $tender_id));
    }

    public function form()
    {
        $this->access_only_tender("procurement", "view");

        $request_id = (int) $this->request->getGet("id");
        $tender_id = (int) $this->request->getGet("tender_id");

        return $this->template->rander("tender_procurement_inbox/form", $this->_get_tender_form_data($request_id, $tender_id));
    }

    private function _get_tender_form_data(int $request_id = 0, int $tender_id = 0): array
    {
        $request = $request_id ? $this->Tender_requests_model->get_details(["id" => $request_id])->getRow() : null;
        $tender = $tender_id ? $this->_get_tender_by_id($tender_id) : null;
        if (!$tender && $request) {
            $tender = $this->Tenders_model->get_by_request_id((int) $request->id);
        }

        $company_id = (int) ($tender->company_id ?? $request->company_id ?? 0);
        $department_id = (int) ($tender->department_id ?? $request->department_id ?? 0);

        $docs = [];
        $invited_vendors = [];
        $existing_team_ids = [
            "technical" => [],
            "commercial" => [],
            "chairman" => 0,
            "secretary" => 0,
            "itc_member" => [],
        ];

        if ($tender && !empty($tender->id)) {
            $docs = $this->Tender_documents_model->get_details(["tender_id" => (int) $tender->id])->getResult();
            $invited_vendors = $this->Tender_invited_vendors_model->get_invited_vendors((int) $tender->id);
            $existing_team_ids = $this->_get_existing_tender_team_ids((int) $tender->id);
        } elseif ($request) {
            $existing_team_ids = $this->_get_existing_request_team_ids((int) $request->id);
        }

        $request_selected_vendors = [];
        if ($request && ($request->tender_type ?? "open") === "close") {
            $request_selected_vendors = $this->Tender_request_vendors_model->get_selected_vendors((int) $request->id);
        }

        $target = null;
        $target_cat = null;
        $target_sub = null;
        $selected_target_mode = "specialty";
        $selected_vendor_group_id = 0;
        $selected_vendor_grade_id = 0;
        $selected_specific_vendors = [];

        if ($tender && !empty($tender->id)) {
            $selected_specific_vendors = $this->_get_selected_target_vendors((int) $tender->id);
            $target = $this->_get_latest_target_rule((int) $tender->id);
            if (!empty($selected_specific_vendors)) {
                $selected_target_mode = "specific_vendors";
            } elseif ($target) {
                if ((int) ($target->vendor_group_id ?? 0) > 0) {
                    $selected_target_mode = "group";
                    $selected_vendor_group_id = (int) $target->vendor_group_id;
                }
                if ((int) ($target->vendor_grade_id ?? 0) > 0) {
                    $selected_target_mode = "grade";
                    $selected_vendor_grade_id = (int) $target->vendor_grade_id;
                }
                if ((int) ($target->vendor_category_id ?? 0) > 0) {
                    $selected_target_mode = "specialty";
                    $target_cat = $this->db->query(
                        "SELECT id, name FROM " . $this->db->prefixTable("vendor_categories") . " WHERE id=? AND deleted=0 LIMIT 1",
                        [(int) $target->vendor_category_id]
                    )->getRow();
                }
                if ((int) ($target->vendor_sub_category_id ?? 0) > 0) {
                    $target_sub = $this->db->query(
                        "SELECT id, name FROM " . $this->db->prefixTable("vendor_sub_categories") . " WHERE id=? AND deleted=0 LIMIT 1",
                        [(int) $target->vendor_sub_category_id]
                    )->getRow();
                }
            }
        }

        $existing_required_codes = $tender && !empty($tender->id)
            ? $this->Tender_bid_requirements_model->get_required_codes((int) $tender->id)
            : Tender_bid_requirements_model::DEFAULT_CODES;

        $rfq_detail = null;
        $rfq_items = [];
        if ($tender && !empty($tender->id)) {
            $rfq_detail = $this->Tender_rfq_details_model->get_by_tender((int) $tender->id);
            $rfq_items = $this->Tender_rfq_items_model->get_by_tender((int) $tender->id);
        }

        return [
            "request" => $request,
            "tender" => $tender,
            "docs" => $docs,
            "invited_vendors" => $invited_vendors,
            "vendor_categories_dropdown" => $this->_get_vendor_category_dropdown(),
            "vendor_groups_dropdown" => $this->_get_vendor_group_dropdown(),
            "vendor_grades_dropdown" => $this->_get_vendor_grade_dropdown(),
            "company_dropdown" => $this->_get_company_dropdown(),
            "department_dropdown" => $this->_get_department_dropdown(),
            "target" => $target,
            "target_cat" => $target_cat,
            "target_sub" => $target_sub,
            "selected_target_mode" => $selected_target_mode,
            "selected_vendor_group_id" => $selected_vendor_group_id,
            "selected_vendor_grade_id" => $selected_vendor_grade_id,
            "selected_specific_vendors" => $selected_specific_vendors,
            "request_selected_vendors" => $request_selected_vendors,
            "technical_users_dropdown" => $this->_get_role_users_dropdown("technical", $company_id),
            "commercial_users_dropdown" => $this->_get_role_users_dropdown("commercial", $company_id),
            "committee_users_dropdown" => $this->_get_role_users_dropdown("committee", $company_id),
            "existing_team_ids" => $existing_team_ids,
            "existing_required_codes" => $existing_required_codes,
            "bid_requirement_labels" => $this->Tender_bid_requirements_model->get_default_labels(),
            "rfq_detail" => $rfq_detail,
            "rfq_items" => $rfq_items,
            "testing_stage_options" => $this->Tender_testing_stage->options(),
            "company_id" => $company_id,
            "department_id" => $department_id,
        ];
    }

    public function save()
    {
        $testing_workflow_stage = $this->Tender_testing_stage->normalize($this->request->getPost("testing_workflow_stage"));
        $validation_rules = [
            "tender_id" => "numeric",
            "tender_request_id" => "numeric",
            "reference" => "required",
            "title" => "required",
            "evaluation_method" => "required",
            "technical_weight" => "required|numeric",
            "commercial_weight" => "required|numeric",
        ];
        if (!$testing_workflow_stage) {
            $validation_rules["closing_at"] = "required";
        }

        $this->validate_submitted_data($validation_rules);

        $tender_id = (int) $this->request->getPost("tender_id");
        $tender_request_id = (int) $this->request->getPost("tender_request_id");
        $request = $tender_request_id ? $this->Tender_requests_model->get_details(["id" => $tender_request_id])->getRow() : null;
        if ($request && ($request->status ?? "") !== "committee_approved") {
            return $this->response->setJSON(["success" => false, "message" => "Only committee approved requests can be processed."]);
        }

        $existing = $tender_id ? $this->_get_tender_by_id($tender_id) : null;
        if (!$existing && $tender_request_id) {
            $existing = $this->Tenders_model->get_by_request_id($tender_request_id);
        }
        if ($existing && !$tender_request_id && !empty($existing->tender_request_id)) {
            $tender_request_id = (int) $existing->tender_request_id;
            $request = $this->Tender_requests_model->get_details(["id" => $tender_request_id])->getRow();
        }

        if ($existing && !empty($existing->id)) {
            $this->access_only_tender("procurement", "update");
            $tender_id = (int) $existing->id;
        } else {
            $this->access_only_tender("procurement", "create");
        }
        $old_site_visit_at = $existing ? $this->_normalize_tender_datetime($existing->site_visit_at ?? "", "start") : null;
        $old_site_visit_location = $existing ? trim((string) ($existing->site_visit_location ?? "")) : "";
        $old_site_visit_instructions = $existing ? trim((string) ($existing->site_visit_instructions ?? "")) : "";
        $old_site_visit_mandatory = $existing ? (int) ($existing->site_visit_mandatory ?? 0) : 0;
        $old_milestones = $this->_get_tender_milestone_values($existing);

        $reference = trim((string) $this->request->getPost("reference"));
        $title = trim((string) $this->request->getPost("title"));
        $brief_description = trim((string) $this->request->getPost("brief_description"));
        $tender_type = strtolower(trim((string) $this->request->getPost("tender_type")));
        if (!in_array($tender_type, ["open", "close"], true)) {
            $tender_type = ($request->tender_type ?? "open") === "close" ? "close" : "open";
        }
        $tender_fee_input = trim((string) $this->request->getPost("tender_fee"));
        if ($tender_fee_input === "" && isset($request->tender_fee) && $request->tender_fee !== null && $request->tender_fee !== "") {
            $tender_fee_input = (string) $request->tender_fee;
        }
        if ($tender_fee_input !== "" && (!is_numeric($tender_fee_input) || (float) $tender_fee_input < 0)) {
            return $this->response->setJSON(["success" => false, "message" => "Tender fee cannot be negative and must be a valid amount."]);
        }
        $tender_fee = $tender_fee_input === "" ? null : round((float) $tender_fee_input, 3);

        $evaluation_method = strtolower(trim((string) $this->request->getPost("evaluation_method")));
        if ($evaluation_method === "" && isset($request->evaluation_method)) {
            $evaluation_method = (string) $request->evaluation_method;
        }
        if (!in_array($evaluation_method, ["separate", "combined"], true)) {
            return $this->response->setJSON(["success" => false, "message" => "Please select a valid evaluation method."]);
        }

        $technical_weight = (int) $this->request->getPost("technical_weight");
        $commercial_weight = (int) $this->request->getPost("commercial_weight");
        if (!$this->_weights_are_valid($technical_weight, $commercial_weight)) {
            return $this->response->setJSON(["success" => false, "message" => "Technical and Commercial weights must total 100."]);
        }

        $company_id = (int) ($this->request->getPost("company_id") ?: ($request->company_id ?? 0));
        $department_id = (int) ($this->request->getPost("department_id") ?: ($request->department_id ?? 0));
        if (!$company_id || !$department_id) {
            return $this->response->setJSON(["success" => false, "message" => "Company and department are required."]);
        }

        $release_at = $this->_normalize_tender_datetime($this->request->getPost("release_at"), "start");
        $document_purchase_deadline = $this->_normalize_tender_datetime($this->request->getPost("document_purchase_deadline"), "end");
        $site_visit_at = $this->_normalize_tender_datetime($this->request->getPost("site_visit_at"), "start");
        $site_visit_location = trim((string) $this->request->getPost("site_visit_location"));
        $site_visit_instructions = trim((string) $this->request->getPost("site_visit_instructions"));
        $site_visit_mandatory = (int) $this->request->getPost("site_visit_mandatory") === 1 ? 1 : 0;
        $clarification_deadline = $this->_normalize_tender_datetime($this->request->getPost("clarification_deadline"), "end");
        $closing_at = $this->_normalize_tender_datetime($this->request->getPost("closing_at"), "end");
        $bid_opening_at = $this->_normalize_tender_datetime($this->request->getPost("bid_opening_at"), "start");
        $technical_eval_deadline = $this->_normalize_tender_datetime($this->request->getPost("technical_eval_deadline"), "end");
        $commercial_eval_deadline = $this->_normalize_tender_datetime($this->request->getPost("commercial_eval_deadline"), "end");

        if (!$closing_at && !$testing_workflow_stage) {
            return $this->response->setJSON(["success" => false, "message" => "Invalid submission deadline."]);
        }

        $new_milestones = [
            "release_at" => $release_at,
            "document_purchase_deadline" => $document_purchase_deadline,
            "site_visit_at" => $site_visit_at,
            "clarification_deadline" => $clarification_deadline,
            "closing_at" => $closing_at,
            "bid_opening_at" => $bid_opening_at,
            "technical_eval_deadline" => $technical_eval_deadline,
            "commercial_eval_deadline" => $commercial_eval_deadline,
        ];

        $new_milestones = $this->_cascade_tender_milestones($old_milestones, $new_milestones);
        $release_at = $new_milestones["release_at"];
        $document_purchase_deadline = $new_milestones["document_purchase_deadline"];
        $site_visit_at = $new_milestones["site_visit_at"];
        $clarification_deadline = $new_milestones["clarification_deadline"];
        $closing_at = $new_milestones["closing_at"];
        $bid_opening_at = $new_milestones["bid_opening_at"];
        $technical_eval_deadline = $new_milestones["technical_eval_deadline"];
        $commercial_eval_deadline = $new_milestones["commercial_eval_deadline"];

        $schedule_error = $testing_workflow_stage ? null : $this->_validate_tender_schedule($new_milestones);
        if ($schedule_error) {
            return $this->response->setJSON(["success" => false, "message" => $schedule_error]);
        }

        $target_mode = strtolower(trim((string) $this->request->getPost("target_mode")));
        if (!in_array($target_mode, ["specialty", "group", "specific_vendors", "grade"], true)) {
            $target_mode = "specialty";
        }

        $vendor_category_id = (int) $this->request->getPost("vendor_category_id");
        $vendor_sub_category_id = (int) $this->request->getPost("vendor_sub_category_id");
        $vendor_group_id = (int) $this->request->getPost("vendor_group_id");
        $vendor_grade_id = (int) $this->request->getPost("vendor_grade_id");
        $specific_vendor_ids = $this->_clean_vendor_ids((array) $this->request->getPost("specific_vendor_ids"));

        $required_sections = (array) $this->request->getPost("required_sections");
        $posted_team_ids = $this->_get_posted_team_ids();
        if ($this->_has_any_posted_team_selection($posted_team_ids)) {
            $committee_error = $this->_validate_committee_role_selection($posted_team_ids);
            if ($committee_error) {
                return $this->response->setJSON(["success" => false, "message" => $committee_error]);
            }
        }

        $data = [
            "tender_request_id" => $tender_request_id ?: null,
            "reference" => $reference,
            "title" => $title,
            "company_id" => $company_id,
            "department_id" => $department_id,
            "brief_description" => $brief_description ?: ($request->brief_description ?? null),
            "tender_fee" => $tender_fee,
            "tender_type" => $tender_type,
            "evaluation_method" => $evaluation_method,
            "technical_weight" => $technical_weight,
            "commercial_weight" => $commercial_weight,
            "release_at" => $release_at,
            "document_purchase_deadline" => $document_purchase_deadline,
            "site_visit_at" => $site_visit_at,
            "site_visit_location" => $site_visit_location ?: null,
            "site_visit_instructions" => $site_visit_instructions ?: null,
            "site_visit_mandatory" => $site_visit_mandatory,
            "clarification_deadline" => $clarification_deadline,
            "closing_at" => $closing_at,
            "bid_opening_at" => $bid_opening_at,
            "technical_eval_deadline" => $technical_eval_deadline,
            "commercial_eval_deadline" => $commercial_eval_deadline,
        ];

        $request_selected_vendors = $request ? $this->Tender_request_vendors_model->get_selected_vendors((int) $request->id) : [];
        $has_request_selected_vendors = count($request_selected_vendors) > 0;
        $has_specialty_target = $target_mode === "specialty" && $vendor_category_id > 0;
        $has_group_target = $target_mode === "group" && $vendor_group_id > 0;
        $has_specific_vendor_target = $target_mode === "specific_vendors" && !empty($specific_vendor_ids);
        $has_grade_target = $target_mode === "grade" && $vendor_grade_id > 0;
        $use_request_selected_vendors = $has_request_selected_vendors && !$has_specialty_target && !$has_group_target && !$has_specific_vendor_target && !$has_grade_target;

        if ($target_mode === "group" && !$has_group_target) {
            return $this->response->setJSON(["success" => false, "message" => "Please select a vendor group."]);
        }
        if ($target_mode === "specific_vendors" && !$has_specific_vendor_target) {
            return $this->response->setJSON(["success" => false, "message" => "Please add at least one specific vendor."]);
        }
        if ($target_mode === "grade" && !$has_grade_target) {
            return $this->response->setJSON(["success" => false, "message" => "Please select a vendor grade."]);
        }

        if ($tender_type === "close" && !$has_request_selected_vendors && !$has_specialty_target && !$has_group_target && !$has_specific_vendor_target && !$has_grade_target) {
            return $this->response->setJSON([
                "success" => false,
                "message" => "For close tenders, select request vendors or choose a vendor specialty, group, grade, or specific vendor target."
            ]);
        }

        if ($this->_requires_manager_approval_for_tender_change($existing)) {
            if (in_array((string) ($existing->status ?? ""), ["awarded", "cancelled"], true)) {
                return $this->response->setJSON(["success" => false, "message" => "Finalized tenders cannot be changed."]);
            }

            $change_payload = $this->_capture_manager_change_payload(
                $data,
                $target_mode,
                $vendor_category_id,
                $vendor_sub_category_id,
                $vendor_group_id,
                $vendor_grade_id,
                $specific_vendor_ids,
                $required_sections,
                $posted_team_ids,
                $testing_workflow_stage
            );

            $this->Tenders_model->ci_save($this->_build_manager_approval_payload("update", $change_payload), $tender_id);

            return $this->response->setJSON([
                "success" => true,
                "message" => "Tender change submitted for procurement manager approval. Existing tender remains unchanged until approval.",
                "tender_id" => $tender_id,
                "redirect_url" => get_uri("tender_procurement_inbox"),
            ]);
        }

        $this->db->transStart();

        if ($tender_id) {
            $this->Tenders_model->ci_save($data, $tender_id);
        } else {
            $data["status"] = "draft";
            $data["workflow_stage"] = "bidding";
            $data["created_by"] = $this->login_user->id;
            $data["created_at"] = date("Y-m-d H:i:s");
            $tender_id = (int) $this->Tenders_model->ci_save($data);
        }

        if (!$tender_id) {
            $this->db->transComplete();
            return $this->response->setJSON(["success" => false, "message" => "Failed to save tender."]);
        }

        $this->_save_target_rule($tender_id, $target_mode, $vendor_category_id, $vendor_sub_category_id, $vendor_group_id, $vendor_grade_id, $specific_vendor_ids);
        $this->Tender_bid_requirements_model->sync_requirements($tender_id, $required_sections);
        $this->_sync_rfq_data($tender_id);
        $this->_save_tender_documents($tender_id);

        if ($this->_has_any_posted_team_selection($posted_team_ids)) {
            $this->_sync_tender_teams_from_post($tender_id, $posted_team_ids);
        } elseif ($request) {
            $this->_sync_teams_from_request($tender_id, (int) $request->id);
        }

        if ($use_request_selected_vendors) {
            $invited_count = $this->_sync_invites_from_request($tender_id, (int) $request->id);
        } elseif ($has_specialty_target) {
            $invited_count = $this->_sync_invites_by_specialty($tender_id, $vendor_category_id, $vendor_sub_category_id);
        } elseif ($has_group_target) {
            $invited_count = $this->_sync_invites_by_vendor_group($tender_id, $vendor_group_id);
        } elseif ($has_specific_vendor_target) {
            $invited_count = $this->_sync_invites_from_specific_vendors($tender_id, $specific_vendor_ids);
        } elseif ($has_grade_target) {
            $invited_count = $this->_sync_invites_by_vendor_grade($tender_id, $vendor_grade_id);
        } else {
            $this->_clear_invites($tender_id);
            $invited_count = 0;
        }

        $publish_now = (int) $this->request->getPost("publish_now") === 1;
        $submit_for_approval = (int) $this->request->getPost("submit_for_approval") === 1;
        if ($testing_workflow_stage) {
            $publish_now = false;
            $submit_for_approval = false;
        }
        if ($publish_now) {
            $publish_error = $this->_validate_tender_can_publish($tender_id, $tender_type);
            if ($publish_error) {
                $this->db->transComplete();
                return $this->response->setJSON(["success" => false, "message" => $publish_error]);
            }

            $fresh_tender = $this->_get_tender_by_id($tender_id);
            $this->Tenders_model->ci_save($this->_build_publish_payload($fresh_tender), $tender_id);
        } elseif ($submit_for_approval) {
            $approval_error = $this->_validate_tender_can_submit_for_manager_approval($tender_id, $tender_type);
            if ($approval_error) {
                $this->db->transComplete();
                return $this->response->setJSON(["success" => false, "message" => $approval_error]);
            }

            $this->Tenders_model->ci_save($this->_build_manager_approval_payload(), $tender_id);
        }

        if ($testing_workflow_stage) {
            $this->_apply_testing_stage_override($tender_id, $testing_workflow_stage, $reference);
        }

        $this->_record_site_visit_notice($tender_id, $old_site_visit_at, $site_visit_at, $reference, $title, $site_visit_location, $site_visit_mandatory, $site_visit_instructions, $old_site_visit_location, $old_site_visit_mandatory, $old_site_visit_instructions);
        $this->_record_milestone_changes($tender_id, $old_milestones, $new_milestones, $reference, (string) ($existing->status ?? ""));

        $this->db->transComplete();
        if ($this->db->transStatus() === false) {
            return $this->response->setJSON(["success" => false, "message" => "Database error."]);
        }

        if ($publish_now && $invited_count > 0) {
            $this->_send_invitation_notifications($tender_id);
        }

        $message = "Tender saved";
        if ($publish_now) {
            $message = "Tender published";
        } elseif ($submit_for_approval) {
            $message = "Tender submitted for procurement manager approval";
        }
        if ($testing_workflow_stage) {
            $message .= ". Testing stage opened: " . $this->Tender_testing_stage->label($testing_workflow_stage);
        }

        return $this->response->setJSON([
            "success" => true,
            "message" => $message . ". Vendors matched: " . (int) $invited_count,
            "tender_id" => $tender_id,
            "redirect_url" => get_uri("tender_procurement_inbox"),
        ]);
    }

    public function publish()
    {
        $this->validate_submitted_data([
            "tender_id" => "numeric",
            "tender_request_id" => "numeric",
        ]);
        $this->access_only_tender("procurement", "update");

        $tender_id = (int) $this->request->getPost("tender_id");
        $tender_request_id = (int) $this->request->getPost("tender_request_id");
        $tender = $tender_id ? $this->_get_tender_by_id($tender_id) : null;
        if (!$tender && $tender_request_id) {
            $tender = $this->Tenders_model->get_by_request_id($tender_request_id);
        }

        if (!$tender || empty($tender->id)) {
            return $this->response->setJSON(["success" => false, "message" => "Create the tender first."]);
        }

        $publish_error = $this->_validate_tender_can_publish((int) $tender->id, (string) ($tender->tender_type ?? "open"));
        if ($publish_error) {
            return $this->response->setJSON(["success" => false, "message" => $publish_error]);
        }

        $this->Tenders_model->ci_save($this->_build_publish_payload($tender), (int) $tender->id);
        $invited_count = $this->_count_active_invites((int) $tender->id);
        if ($invited_count > 0) {
            $this->_send_invitation_notifications((int) $tender->id);
        }

        return $this->response->setJSON([
            "success" => true,
            "message" => "Tender published. Vendors invited: " . (int) $invited_count,
        ]);
    }

    public function delete_document()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);
        $this->access_only_tender("procurement", "update");
        $this->Tender_documents_model->ci_save(["deleted" => 1], (int) $this->request->getPost("id"));
        return $this->response->setJSON(["success" => true, "message" => "Document deleted."]);
    }

    public function award()
    {
        $this->validate_submitted_data(["tender_id" => "required|numeric"]);
        $this->access_only_tender("procurement", "update");

        $tender_id = (int) $this->request->getPost("tender_id");
        $this->Tenders_model->auto_progress_workflow();
        $tender = $this->_get_tender_by_id($tender_id);
        if (!$tender) {
            return $this->response->setJSON(["success" => false, "message" => "Tender not found."]);
        }

        if (($tender->status ?? "") !== "closed" || ($tender->workflow_stage ?? "") !== "award_decision") {
            return $this->response->setJSON(["success" => false, "message" => "Tender must be in Award Decision stage before final award."]);
        }

        $summary = $this->_get_commercial_decision_summary($tender_id);
        if ((int) ($summary["approved_count"] ?? 0) !== 1) {
            return $this->response->setJSON(["success" => false, "message" => "Exactly one commercially approved bid is required before final award."]);
        }

        $winner_vendor_id = (int) ($summary["winner_vendor_id"] ?? 0);
        if (!$winner_vendor_id) {
            return $this->response->setJSON(["success" => false, "message" => "Unable to identify winner vendor."]);
        }

        $now = $this->_get_tender_business_now();
        $this->Tenders_model->ci_save([
            "status" => "awarded",
            "workflow_stage" => "award_decision",
            "award_ready_at" => $tender->award_ready_at ?: $now,
            "award_vendor_id" => $winner_vendor_id,
            "loa_reference" => $tender->loa_reference ?: ("LOA-" . (string) ($tender->reference ?? "TENDER") . "-" . date("YmdHis")),
            "loa_issued_at" => $now,
            "updated_at" => $now,
        ], $tender_id);

        $this->_send_award_and_regret_notifications($tender_id, $winner_vendor_id);

        return $this->response->setJSON(["success" => true, "message" => "Tender awarded successfully."]);
    }

    public function cancel_tender()
    {
        $this->validate_submitted_data(["tender_id" => "required|numeric"]);
        $this->access_only_tender("procurement", "update");

        $tender_id = (int) $this->request->getPost("tender_id");
        $tender = $this->_get_tender_by_id($tender_id);
        if (!$tender) {
            return $this->response->setJSON(["success" => false, "message" => "Tender not found."]);
        }

        if (($tender->status ?? "") === "awarded") {
            return $this->response->setJSON(["success" => false, "message" => "Awarded tender cannot be cancelled."]);
        }

        if ($this->_requires_manager_approval_for_tender_change($tender)) {
            $payload = [
                "from_status" => (string) ($tender->status ?? ""),
                "from_stage" => (string) ($tender->workflow_stage ?? ""),
                "requested_at" => $this->_get_tender_business_now(),
            ];

            $this->Tenders_model->ci_save($this->_build_manager_approval_payload("cancel", $payload), $tender_id);

            return $this->response->setJSON([
                "success" => true,
                "message" => "Tender cancellation submitted for procurement manager approval."
            ]);
        }

        $this->Tenders_model->ci_save([
            "status" => "cancelled",
            "updated_at" => $this->_get_tender_business_now(),
        ], $tender_id);

        $this->_send_cancellation_notifications($tender_id);
        return $this->response->setJSON(["success" => true, "message" => "Tender cancelled."]);
    }

    public function retender()
    {
        $this->validate_submitted_data(["tender_id" => "required|numeric"]);
        $this->access_only_tender("procurement", "create");

        $source_tender_id = (int) $this->request->getPost("tender_id");
        $source = $this->_get_tender_by_id($source_tender_id);
        if (!$source) {
            return $this->response->setJSON(["success" => false, "message" => "Source tender not found."]);
        }

        $now = $this->_get_tender_business_now();
        $new_reference = trim((string) ($source->reference ?? "TENDER")) . "-RT-" . date("YmdHis");

        $new_tender_id = 0;
        $this->db->transStart();

        $new_tender_id = (int) $this->Tenders_model->ci_save([
            "tender_request_id" => (int) ($source->tender_request_id ?? 0) ?: null,
            "reference" => $new_reference,
            "title" => (string) ($source->title ?? ""),
            "company_id" => (int) ($source->company_id ?? 0) ?: null,
            "department_id" => (int) ($source->department_id ?? 0) ?: null,
            "brief_description" => $source->brief_description ?? null,
            "tender_fee" => $source->tender_fee ?? null,
            "tender_type" => (string) ($source->tender_type ?? "open"),
            "evaluation_method" => (string) ($source->evaluation_method ?? "separate"),
            "technical_weight" => (int) ($source->technical_weight ?? 70),
            "commercial_weight" => (int) ($source->commercial_weight ?? 30),
            "status" => "draft",
            "workflow_stage" => "bidding",
            "release_at" => null,
            "document_purchase_deadline" => null,
            "site_visit_at" => null,
            "site_visit_location" => null,
            "site_visit_instructions" => null,
            "site_visit_mandatory" => 0,
            "clarification_deadline" => null,
            "closing_at" => !empty($source->closing_at) ? date("Y-m-d H:i:s", strtotime($source->closing_at . " +7 days")) : null,
            "bid_opening_at" => null,
            "technical_eval_deadline" => null,
            "commercial_eval_deadline" => null,
            "created_by" => $this->login_user->id,
            "created_at" => $now,
            "updated_at" => $now,
        ]);

        if ($new_tender_id) {
            $this->_clone_tender_related_rows($source_tender_id, $new_tender_id);
        }

        $this->db->transComplete();
        if ($this->db->transStatus() === false || !$new_tender_id) {
            return $this->response->setJSON(["success" => false, "message" => "Unable to create retender draft."]);
        }

        return $this->response->setJSON(["success" => true, "message" => "Retender draft created successfully."]);
    }

    private function _get_tender_by_id(int $tender_id)
    {
        $t = $this->db->prefixTable("tenders");
        return $this->db->query(
            "SELECT * FROM $t WHERE id=? AND deleted=0 LIMIT 1",
            [$tender_id]
        )->getRow();
    }

    private function _weights_are_valid(int $technical_weight, int $commercial_weight): bool
    {
        return $technical_weight >= 0
            && $technical_weight <= 100
            && $commercial_weight >= 0
            && $commercial_weight <= 100
            && ($technical_weight + $commercial_weight) === 100;
    }

    private function _ensure_tender_site_visit_columns(): void
    {
        $table = $this->db->prefixTable("tenders");
        $columns = [
            "site_visit_location" => "ALTER TABLE `$table` ADD COLUMN `site_visit_location` VARCHAR(255) DEFAULT NULL AFTER `site_visit_at`",
            "site_visit_instructions" => "ALTER TABLE `$table` ADD COLUMN `site_visit_instructions` TEXT DEFAULT NULL AFTER `site_visit_location`",
            "site_visit_mandatory" => "ALTER TABLE `$table` ADD COLUMN `site_visit_mandatory` TINYINT(1) NOT NULL DEFAULT 0 AFTER `site_visit_instructions`",
        ];

        foreach ($columns as $column => $sql) {
            if (!$this->_column_exists($table, $column)) {
                $this->db->query($sql);
            }
        }
    }

    private function _column_exists(string $table, string $column): bool
    {
        $row = $this->db->query(
            "SHOW COLUMNS FROM `$table` LIKE " . $this->db->escape($column)
        )->getRow();

        return (bool) $row;
    }

    private function _sync_rfq_data(int $tender_id): void
    {
        $detail = [
            "rfq_no" => trim((string) $this->request->getPost("rfq_no")) ?: null,
            "rfq_date" => $this->_date_or_null($this->request->getPost("rfq_date")),
            "pr_no" => trim((string) $this->request->getPost("pr_no")) ?: null,
            "delivery_location" => trim((string) $this->request->getPost("delivery_location")) ?: null,
            "incoterm" => trim((string) $this->request->getPost("incoterm")) ?: null,
            "material_required_on" => $this->_date_or_null($this->request->getPost("material_required_on")),
            "terms_reference" => trim((string) $this->request->getPost("terms_reference")) ?: null,
            "notes" => trim((string) $this->request->getPost("rfq_notes")) ?: null,
            "enclosures" => trim((string) $this->request->getPost("rfq_enclosures")) ?: null,
        ];

        $this->Tender_rfq_details_model->sync_detail($tender_id, $detail);

        $rows = [];
        $sr = (array) $this->request->getPost("rfq_item_sr_no");
        $descriptions = (array) $this->request->getPost("rfq_item_description");
        $uoms = (array) $this->request->getPost("rfq_item_uom");
        $qtys = (array) $this->request->getPost("rfq_item_qty");
        $prices = (array) $this->request->getPost("rfq_item_unit_price");
        $brands = (array) $this->request->getPost("rfq_item_brand");
        $max = max(count($sr), count($descriptions), count($uoms), count($qtys), count($prices), count($brands));

        for ($i = 0; $i < $max; $i++) {
            $rows[] = [
                "sr_no" => $sr[$i] ?? "",
                "description" => $descriptions[$i] ?? "",
                "uom" => $uoms[$i] ?? "",
                "qty" => $qtys[$i] ?? "",
                "unit_price" => $prices[$i] ?? "",
                "brand" => $brands[$i] ?? "",
            ];
        }

        $this->Tender_rfq_items_model->sync_items($tender_id, $rows);
    }

    private function _date_or_null($value): ?string
    {
        $value = trim((string) $value);
        if ($value === "") {
            return null;
        }

        $ts = strtotime($value);
        return $ts ? date("Y-m-d", $ts) : null;
    }

    private function _save_tender_documents(int $tender_id): void
    {
        $target_path = getcwd() . "/files/tender_files/" . $tender_id . "/";
        if (!is_dir($target_path)) {
            @mkdir($target_path, 0755, true);
        }

        $files = $this->request->getPost("files");
        if (!$files || !is_array($files) || !get_array_value($files, 0)) {
            return;
        }

        foreach ($files as $serial) {
            $serial = (int) $serial;
            if (!$serial) {
                continue;
            }

            $original_name = $this->request->getPost("file_name_" . $serial);
            $file_size = (int) $this->request->getPost("file_size_" . $serial);
            $title_input = $this->request->getPost("description_" . $serial);
            $doc_type = trim((string) $this->request->getPost("doc_type_" . $serial));
            $time_limited = $this->request->getPost("time_limited_" . $serial) ? 1 : 0;
            $expires_in_hours = (int) ($this->request->getPost("expires_in_hours_" . $serial) ?: 72);

            if (!in_array($doc_type, ["RFP", "BOQ", "DRAWING", "SUPPORTING", "OTHER"], true)) {
                $doc_type = trim((string) $this->request->getPost("doc_type")) ?: "RFP";
            }

            if ($expires_in_hours < 1) {
                $expires_in_hours = 72;
            }

            if (!$original_name) {
                continue;
            }

            $file_info = move_temp_file($original_name, $target_path, "tender_doc", null, "", "", false, $file_size, true);
            if (!$file_info || !get_array_value($file_info, "file_name")) {
                continue;
            }

            $stored_name = get_array_value($file_info, "file_name");
            $this->Tender_documents_model->ci_save([
                "tender_id" => $tender_id,
                "doc_type" => $doc_type,
                "title" => $title_input ?: null,
                "disk" => "local",
                "path" => "files/tender_files/" . $tender_id . "/" . $stored_name,
                "original_name" => $original_name,
                "size_bytes" => $file_size ?: null,
                "time_limited" => $time_limited,
                "expires_in_hours" => $time_limited ? $expires_in_hours : null,
                "uploaded_by" => $this->login_user->id,
                "created_at" => date("Y-m-d H:i:s"),
                "deleted" => 0,
            ]);
        }
    }

    private function _tender_milestone_labels(): array
    {
        return [
            "release_at" => "Tender Release",
            "document_purchase_deadline" => "Document Purchase Deadline",
            "site_visit_at" => "Site Visit",
            "clarification_deadline" => "Clarification Deadline",
            "closing_at" => "Submission Deadline",
            "bid_opening_at" => "Bid Opening",
            "technical_eval_deadline" => "Technical Evaluation Deadline",
            "commercial_eval_deadline" => "Commercial Evaluation Deadline",
        ];
    }

    private function _milestone_normalize_mode(string $field): string
    {
        return in_array($field, ["release_at", "site_visit_at", "bid_opening_at"], true) ? "start" : "end";
    }

    private function _get_tender_milestone_values($tender): array
    {
        if (!$tender) {
            return [];
        }

        $values = [];
        foreach (array_keys($this->_tender_milestone_labels()) as $field) {
            $values[$field] = $this->_normalize_tender_datetime($tender->{$field} ?? "", $this->_milestone_normalize_mode($field));
        }

        return $values;
    }

    private function _tender_milestone_order(): array
    {
        return array_keys($this->_tender_milestone_labels());
    }

    private function _cascade_tender_milestones(array $old_milestones, array $new_milestones): array
    {
        if (!$old_milestones) {
            return $new_milestones;
        }

        $order = $this->_tender_milestone_order();
        foreach ($order as $index => $field) {
            $old = $old_milestones[$field] ?? null;
            $new = $new_milestones[$field] ?? null;

            if (!$old || !$new || $old === $new) {
                continue;
            }

            $old_time = strtotime((string) $old);
            $new_time = strtotime((string) $new);
            if ($old_time === false || $new_time === false || $old_time === $new_time) {
                continue;
            }

            return $this->_cascade_downstream_milestones($order, $old_milestones, $new_milestones, (int) $index, $new_time - $old_time);
        }

        return $new_milestones;
    }

    private function _cascade_downstream_milestones(array $order, array $old_milestones, array $new_milestones, int $changed_index, int $delta_seconds): array
    {
        $total = count($order);
        for ($i = $changed_index + 1; $i < $total; $i++) {
            $field = $order[$i];
            $current = $new_milestones[$field] ?? null;
            if (!$current) {
                continue;
            }

            $old_downstream = $old_milestones[$field] ?? null;
            if ($old_downstream && $current !== $old_downstream) {
                continue;
            }

            $current_time = strtotime((string) $current);
            if ($current_time === false) {
                continue;
            }

            $new_milestones[$field] = date("Y-m-d H:i:s", $current_time + $delta_seconds);
        }

        return $new_milestones;
    }

    private function _validate_tender_schedule(array $dates): ?string
    {
        $on_or_before = [
            ["release_at", "document_purchase_deadline", "Tender release date must be on or before the document purchase deadline."],
            ["release_at", "site_visit_at", "Tender release date must be on or before the site visit date."],
            ["release_at", "clarification_deadline", "Tender release date must be on or before the clarification deadline."],
            ["release_at", "closing_at", "Tender release date must be on or before the submission deadline."],
            ["document_purchase_deadline", "closing_at", "Document purchase deadline must be on or before the submission deadline."],
            ["site_visit_at", "closing_at", "Site visit date must be on or before the submission deadline."],
            ["clarification_deadline", "closing_at", "Clarification deadline must be on or before the submission deadline."],
        ];

        foreach ($on_or_before as $rule) {
            [$first, $second, $message] = $rule;
            if (!empty($dates[$first]) && !empty($dates[$second]) && strtotime((string) $dates[$first]) > strtotime((string) $dates[$second])) {
                return $message;
            }
        }

        $strictly_after = [
            ["closing_at", "bid_opening_at", "Bid opening date must be after the submission deadline."],
            ["closing_at", "technical_eval_deadline", "Technical evaluation deadline must be after the submission deadline."],
            ["bid_opening_at", "technical_eval_deadline", "Technical evaluation deadline must be after the bid opening date."],
            ["technical_eval_deadline", "commercial_eval_deadline", "Commercial evaluation deadline must be after the technical evaluation deadline."],
        ];

        foreach ($strictly_after as $rule) {
            [$first, $second, $message] = $rule;
            if (!empty($dates[$first]) && !empty($dates[$second]) && strtotime((string) $dates[$first]) >= strtotime((string) $dates[$second])) {
                return $message;
            }
        }

        if (!empty($dates["commercial_eval_deadline"]) && empty($dates["technical_eval_deadline"])) {
            return "Technical evaluation deadline is required before setting the commercial evaluation deadline.";
        }

        return null;
    }

    private function _record_milestone_changes(int $tender_id, array $old_milestones, array $new_milestones, string $reference, string $existing_status): void
    {
        if (!$old_milestones) {
            return;
        }

        $labels = $this->_tender_milestone_labels();
        $vendor_visible_changes = [];
        $now = date("Y-m-d H:i:s");

        foreach ($labels as $field => $label) {
            $old = $old_milestones[$field] ?? null;
            $new = $new_milestones[$field] ?? null;

            if (!$old || !$new || $old === $new) {
                continue;
            }

            $is_extension = strtotime((string) $new) > strtotime((string) $old);
            $this->Tender_extensions_model->ci_save(clean_data([
                "tender_id" => $tender_id,
                "milestone_code" => $field,
                "old_close_at" => $old,
                "new_close_at" => $new,
                "reason" => ($is_extension ? "Extended" : "Updated") . " by procurement schedule update.",
                "created_by" => $this->login_user->id,
                "approved_by" => $this->login_user->id,
                "approved_at" => $now,
                "status" => "approved",
                "created_at" => $now,
                "deleted" => 0,
            ]));

            if ($field !== "site_visit_at") {
                $vendor_visible_changes[] = $label . ": " . date("Y-m-d H:i", strtotime((string) $old)) . " to " . date("Y-m-d H:i", strtotime((string) $new));
            }
        }

        if (!$vendor_visible_changes || !in_array($existing_status, ["published", "closed"], true)) {
            return;
        }

        $this->Tender_communications_model->ci_save(clean_data([
            "tender_id" => $tender_id,
            "vendor_id" => null,
            "type" => "circular",
            "subject" => "Schedule Update - " . ($reference ?: "Tender"),
            "message" => "Tender schedule updated.\n" . implode("\n", $vendor_visible_changes),
            "parent_id" => null,
            "sent_to_all" => 1,
            "is_vendor_visible" => 1,
            "status" => "published",
            "created_by" => $this->login_user->id,
            "created_at" => $now,
            "published_at" => $now,
            "deleted" => 0,
        ]));
    }

    private function _record_site_visit_notice(int $tender_id, ?string $old_site_visit_at, ?string $new_site_visit_at, string $reference, string $title, string $location = "", int $mandatory = 0, string $instructions = "", string $old_location = "", int $old_mandatory = 0, string $old_instructions = ""): void
    {
        $changed = $new_site_visit_at !== $old_site_visit_at
            || trim($location) !== trim($old_location)
            || (int) $mandatory !== (int) $old_mandatory
            || trim($instructions) !== trim($old_instructions);

        if (!$new_site_visit_at || !$changed) {
            return;
        }

        $message = "Site visit scheduled for " . date("Y-m-d H:i", strtotime($new_site_visit_at)) . ".";
        $message .= "\nAttendance: " . ($mandatory ? "Mandatory" : "Optional");
        if ($location !== "") {
            $message .= "\nLocation: " . $location;
        }
        if ($instructions !== "") {
            $message .= "\nInstructions: " . $instructions;
        }
        if ($title !== "") {
            $message .= "\nTender: " . $title;
        }

        if ($old_site_visit_at) {
            $message .= "\nPrevious site visit date was " . date("Y-m-d H:i", strtotime($old_site_visit_at)) . ".";
        }

        $now = date("Y-m-d H:i:s");
        $this->Tender_communications_model->ci_save(clean_data([
            "tender_id" => $tender_id,
            "vendor_id" => null,
            "type" => "site_visit_notice",
            "subject" => "Site Visit Notice - " . ($reference ?: "Tender"),
            "message" => $message,
            "parent_id" => null,
            "sent_to_all" => 1,
            "is_vendor_visible" => 1,
            "status" => "published",
            "created_by" => $this->login_user->id,
            "created_at" => $now,
            "published_at" => $now,
            "deleted" => 0,
        ]));
    }

    private function _save_target_rule(int $tender_id, string $target_mode, int $vendor_category_id, int $vendor_sub_category_id, int $vendor_group_id, int $vendor_grade_id, array $specific_vendor_ids): void
    {
        $tts = $this->db->prefixTable("tender_target_specialties");
        $target_vendors = $this->db->prefixTable("tender_target_vendors");
        $this->db->query("UPDATE $tts SET deleted=1 WHERE tender_id=?", [$tender_id]);
        $this->db->query("UPDATE $target_vendors SET deleted=1 WHERE tender_id=?", [$tender_id]);

        if ($target_mode === "specialty" && $vendor_category_id <= 0) {
            return;
        }

        if ($target_mode === "group" && $vendor_group_id <= 0) {
            return;
        }

        if ($target_mode === "grade" && $vendor_grade_id <= 0) {
            return;
        }

        $now = date("Y-m-d H:i:s");
        if ($target_mode === "specific_vendors") {
            foreach ($specific_vendor_ids as $vendor_id) {
                $this->db->query(
                    "INSERT INTO $target_vendors (tender_id, vendor_id, created_by, created_at, deleted)
                     VALUES (?, ?, ?, ?, 0)",
                    [$tender_id, (int) $vendor_id, $this->login_user->id, $now]
                );
            }

            return;
        }

        $this->db->query(
            "INSERT INTO $tts
             (tender_id, vendor_category_id, vendor_sub_category_id, vendor_group_id, vendor_grade_id, created_by, created_at, deleted)
             VALUES (?, ?, ?, ?, ?, ?, ?, 0)",
            [
                $tender_id,
                $target_mode === "specialty" ? $vendor_category_id : 0,
                $target_mode === "specialty" && $vendor_sub_category_id > 0 ? $vendor_sub_category_id : null,
                $target_mode === "group" && $vendor_group_id > 0 ? $vendor_group_id : null,
                $target_mode === "grade" && $vendor_grade_id > 0 ? $vendor_grade_id : null,
                $this->login_user->id,
                $now,
            ]
        );
    }

    private function _get_latest_target_rule(int $tender_id)
    {
        $tts = $this->db->prefixTable("tender_target_specialties");
        return $this->db->query(
            "SELECT *
             FROM $tts
             WHERE tender_id=?
               AND deleted=0
             ORDER BY id DESC
             LIMIT 1",
            [$tender_id]
        )->getRow();
    }

    private function _get_selected_target_vendor_ids(int $tender_id): array
    {
        $target_vendors = $this->db->prefixTable("tender_target_vendors");
        $rows = $this->db->query(
            "SELECT vendor_id
             FROM $target_vendors
             WHERE tender_id=?
               AND deleted=0
             ORDER BY id ASC",
            [$tender_id]
        )->getResult();

        return array_values(array_unique(array_filter(array_map(fn($row) => (int) $row->vendor_id, $rows))));
    }

    private function _get_selected_target_vendors(int $tender_id): array
    {
        $target_vendors = $this->db->prefixTable("tender_target_vendors");
        $vendors = $this->db->prefixTable("vendors");
        $groups = $this->db->prefixTable("vendor_groups");
        $grades = $this->db->prefixTable("vendor_grades");

        return $this->db->query(
            "SELECT
                $vendors.id,
                $vendors.vendor_name,
                $vendors.email,
                $vendors.cr_number,
                $groups.name AS group_name,
                $groups.code AS group_code,
                $grades.name AS grade_name,
                $grades.code AS grade_code
             FROM $target_vendors
             JOIN $vendors ON $vendors.id=$target_vendors.vendor_id AND $vendors.deleted=0
             LEFT JOIN $groups ON $groups.id=$vendors.vendor_group_id AND $groups.deleted=0
             LEFT JOIN $grades ON $grades.id=$vendors.vendor_grade_id AND $grades.deleted=0
             WHERE $target_vendors.tender_id=?
               AND $target_vendors.deleted=0
             ORDER BY $vendors.vendor_name ASC",
            [$tender_id]
        )->getResult();
    }

    private function _get_posted_team_ids(): array
    {
        return [
            "technical" => (array) $this->request->getPost("technical_user_ids"),
            "commercial" => (array) $this->request->getPost("commercial_user_ids"),
            "chairman" => (int) $this->request->getPost("chairman_user_id"),
            "secretary" => (int) $this->request->getPost("secretary_user_id"),
            "itc_member" => $this->_clean_user_ids((array) $this->request->getPost("itc_member_user_ids")),
        ];
    }

    private function _clean_user_ids(array $ids): array
    {
        $clean = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $clean[$id] = $id;
            }
        }

        return array_values($clean);
    }

    private function _clean_vendor_ids(array $ids): array
    {
        $clean = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $clean[$id] = $id;
            }
        }

        return array_values($clean);
    }

    private function _has_any_posted_team_selection(array $team_ids): bool
    {
        foreach ($team_ids as $group) {
            foreach ((array) $group as $id) {
                if ((int) $id > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function _validate_committee_role_selection(array $team_ids): ?string
    {
        $chairman_id = (int) ($team_ids["chairman"] ?? 0);
        $secretary_id = (int) ($team_ids["secretary"] ?? 0);
        $itc_member_ids = $this->_clean_user_ids((array) ($team_ids["itc_member"] ?? []));

        if ($chairman_id > 0 && $secretary_id > 0 && $chairman_id === $secretary_id) {
            return "Chairman and secretary must be different committee users.";
        }

        $reserved_ids = array_filter([$chairman_id, $secretary_id]);
        if ($reserved_ids && array_intersect($reserved_ids, $itc_member_ids)) {
            return "Chairman and secretary cannot also be selected as ITC members.";
        }

        return null;
    }

    private function _sync_tender_teams_from_post(int $tender_id, array $team_ids): void
    {
        $this->Tender_team_members_model->sync_members($tender_id, "technical_evaluator", (array) $team_ids["technical"]);
        $this->Tender_team_members_model->sync_members($tender_id, "commercial_evaluator", (array) $team_ids["commercial"]);
        $this->Tender_team_members_model->sync_members($tender_id, "chairman", (array) $team_ids["chairman"]);
        $this->Tender_team_members_model->sync_members($tender_id, "secretary", (array) $team_ids["secretary"]);
        $this->Tender_team_members_model->sync_members($tender_id, "itc_member", (array) $team_ids["itc_member"]);
    }

    private function _sync_teams_from_request(int $tender_id, int $tender_request_id): void
    {
        $grouped = $this->Tender_request_team_members_model->get_grouped_members($tender_request_id);

        $this->Tender_team_members_model->sync_members(
            $tender_id,
            "technical_evaluator",
            array_map(fn($u) => (int) $u->id, $grouped["technical_evaluator"] ?? [])
        );
        $this->Tender_team_members_model->sync_members(
            $tender_id,
            "commercial_evaluator",
            array_map(fn($u) => (int) $u->id, $grouped["commercial_evaluator"] ?? [])
        );
        $this->Tender_team_members_model->sync_members(
            $tender_id,
            "chairman",
            array_map(fn($u) => (int) $u->id, $grouped["chairman"] ?? [])
        );
        $this->Tender_team_members_model->sync_members(
            $tender_id,
            "secretary",
            array_map(fn($u) => (int) $u->id, $grouped["secretary"] ?? [])
        );
        $this->Tender_team_members_model->sync_members(
            $tender_id,
            "itc_member",
            array_map(fn($u) => (int) $u->id, $grouped["itc_member"] ?? [])
        );
    }

    private function _get_existing_request_team_ids(int $tender_request_id): array
    {
        $grouped = $this->Tender_request_team_members_model->get_grouped_members($tender_request_id);

        return [
            "technical" => array_map(fn($u) => (int) $u->id, $grouped["technical_evaluator"] ?? []),
            "commercial" => array_map(fn($u) => (int) $u->id, $grouped["commercial_evaluator"] ?? []),
            "chairman" => (int) (($grouped["chairman"][0]->id ?? 0)),
            "secretary" => (int) (($grouped["secretary"][0]->id ?? 0)),
            "itc_member" => array_map(fn($u) => (int) $u->id, $grouped["itc_member"] ?? []),
        ];
    }

    private function _get_existing_tender_team_ids(int $tender_id): array
    {
        return [
            "technical" => array_map(fn($u) => (int) $u->id, $this->Tender_team_members_model->get_members($tender_id, "technical_evaluator")),
            "commercial" => array_map(fn($u) => (int) $u->id, $this->Tender_team_members_model->get_members($tender_id, "commercial_evaluator")),
            "chairman" => (int) ($this->Tender_team_members_model->get_members($tender_id, "chairman")[0]->id ?? 0),
            "secretary" => (int) ($this->Tender_team_members_model->get_members($tender_id, "secretary")[0]->id ?? 0),
            "itc_member" => array_map(fn($u) => (int) $u->id, $this->Tender_team_members_model->get_members($tender_id, "itc_member")),
        ];
    }

    private function _get_company_dropdown(): array
    {
        $dropdown = ["" => "- " . app_lang("select_company") . " -"];
        foreach ($this->Gate_pass_companies_model->get_details()->getResult() as $company) {
            $dropdown[(int) $company->id] = $company->name;
        }
        return $dropdown;
    }

    private function _get_department_dropdown(): array
    {
        $dropdown = ["" => "- " . app_lang("select") . " -"];
        foreach ($this->Gate_pass_departments_model->get_details()->getResult() as $department) {
            $company_name = trim((string) ($department->company_name ?? ""));
            $label = $department->name;
            if ($company_name !== "") {
                $label = $company_name . " / " . $label;
            }
            $dropdown[(int) $department->id] = $label;
        }
        return $dropdown;
    }

    private function _get_vendor_category_dropdown(): array
    {
        $dropdown = ["" => "- " . app_lang("select") . " -"];
        $cats = $this->db->prefixTable("vendor_categories");
        $rows = $this->db->query("SELECT id, name, is_active FROM $cats WHERE deleted=0 ORDER BY name ASC")->getResult();
        foreach ($rows as $row) {
            $label = $row->name . (((int) $row->is_active === 0) ? " (inactive)" : "");
            $dropdown[(int) $row->id] = $label;
        }
        return $dropdown;
    }

    private function _get_vendor_group_dropdown(): array
    {
        $dropdown = ["" => "- " . app_lang("select_vendor_group") . " -"];
        $vg = $this->db->prefixTable("vendor_groups");
        $rows = $this->db->query(
            "SELECT id, name, code
             FROM $vg
             WHERE deleted=0
             ORDER BY name ASC"
        )->getResult();

        foreach ($rows as $row) {
            $label = $row->name;
            if (!empty($row->code)) {
                $label .= " (" . $row->code . ")";
            }
            $dropdown[(int) $row->id] = $label;
        }

        return $dropdown;
    }

    private function _get_vendor_grade_dropdown(): array
    {
        $dropdown = ["" => "- Select vendor grade -"];
        $grades = $this->db->prefixTable("vendor_grades");
        $rows = $this->db->query(
            "SELECT id, name, code
             FROM $grades
             WHERE deleted=0
               AND is_active=1
             ORDER BY sort ASC, code ASC, name ASC"
        )->getResult();

        foreach ($rows as $row) {
            $dropdown[(int) $row->id] = function_exists("vendor_grade_label")
                ? vendor_grade_label($row->name ?? "", $row->code ?? "")
                : trim((string) ($row->code ?? $row->name ?? ""));
        }

        return $dropdown;
    }

    private function _get_role_users_dropdown(string $role, int $company_id): array
    {
        $dropdown = [];
        $table = match ($role) {
            "technical" => $this->db->prefixTable("tender_technical_users"),
            "commercial" => $this->db->prefixTable("tender_commercial_users"),
            default => $this->db->prefixTable("tender_committee_users"),
        };
        $users = $this->db->prefixTable("users");
        $where = "WHERE pivot.deleted=0 AND pivot.status='active' AND $users.deleted=0";

        if ($company_id > 0) {
            $where .= " AND pivot.company_id=" . $company_id;
        }

        $rows = $this->db->query(
            "SELECT
                $users.id,
                TRIM(CONCAT(COALESCE($users.first_name,''), ' ', COALESCE($users.last_name,''))) AS full_name,
                $users.email
             FROM $table pivot
             INNER JOIN $users ON $users.id = pivot.user_id
             $where
             ORDER BY $users.first_name ASC, $users.last_name ASC"
        )->getResult();

        foreach ($rows as $row) {
            $label = trim((string) $row->full_name);
            if ($label === "") {
                $label = (string) $row->email;
            } elseif (!empty($row->email)) {
                $label .= " (" . $row->email . ")";
            }
            $dropdown[(int) $row->id] = $label;
        }

        return $dropdown;
    }

    private function _validate_tender_can_submit_for_manager_approval(int $tender_id, string $tender_type): ?string
    {
        return $this->_validate_tender_can_publish($tender_id, $tender_type, false);
    }

    private function _validate_tender_can_publish(int $tender_id, string $tender_type, bool $require_manager_approval = true): ?string
    {
        $tender = $this->_get_tender_by_id($tender_id);
        if (!$tender) {
            return "Create the tender first.";
        }

        if ($require_manager_approval && (string) ($tender->procurement_manager_status ?? "draft") !== "approved") {
            return "Procurement manager approval is required before publishing.";
        }

        $closing_at = $this->_normalize_tender_datetime($tender->closing_at, "end");
        if (!$closing_at) {
            return "Submission deadline is required.";
        }

        $closing = Time::parse($closing_at, "Asia/Muscat");
        $now = Time::now("Asia/Muscat");
        if ($closing->getTimestamp() <= $now->getTimestamp()) {
            return "Submission deadline must be in the future.";
        }

        $schedule_error = $this->_validate_tender_schedule($this->_get_tender_milestone_values($tender));
        if ($schedule_error) {
            return $schedule_error;
        }

        $team_ids = $this->_get_existing_tender_team_ids($tender_id);
        if (count($team_ids["technical"]) < 1) {
            return "At least one technical evaluator is required.";
        }
        if (count($team_ids["commercial"]) < 1) {
            return "At least one commercial evaluator is required.";
        }
        if ((int) $team_ids["chairman"] <= 0) {
            return "A chairman is required.";
        }
        if ((int) $team_ids["secretary"] <= 0) {
            return "A secretary is required.";
        }
        if (count($team_ids["itc_member"]) < 1) {
            return "At least one ITC member is required.";
        }
        $committee_error = $this->_validate_committee_role_selection($team_ids);
        if ($committee_error) {
            return $committee_error;
        }

        if ($tender_type === "close" && $this->_count_active_invites($tender_id) < 1) {
            return "Close tenders must have invited vendors before publishing.";
        }

        if (in_array((string) ($tender->status ?? ""), ["awarded", "cancelled"], true)) {
            return "Finalized tenders cannot be published again.";
        }

        return null;
    }

    private function _build_publish_payload($tender): array
    {
        $now = $this->_get_tender_business_now();
        return [
            "status" => "published",
            "workflow_stage" => "bidding",
            "published_at" => $now,
            "updated_at" => $now,
        ];
    }

    private function _requires_manager_approval_for_tender_change($existing): bool
    {
        if (!$existing || empty($existing->id)) {
            return false;
        }

        $status = (string) ($existing->status ?? "");
        if (in_array($status, ["published", "closed"], true)) {
            return true;
        }

        return $status === "draft" && (string) ($existing->procurement_manager_status ?? "") === "approved";
    }

    private function _capture_manager_change_payload(
        array $tender_fields,
        string $target_mode,
        int $vendor_category_id,
        int $vendor_sub_category_id,
        int $vendor_group_id,
        int $vendor_grade_id,
        array $specific_vendor_ids,
        array $required_sections,
        array $posted_team_ids,
        ?string $testing_workflow_stage = null
    ): array {
        $tender_fields = $this->_prepare_tender_fields_for_manager_payload($tender_fields);

        return [
            "tender_fields" => $tender_fields,
            "target" => [
                "target_mode" => $target_mode,
                "vendor_category_id" => $vendor_category_id,
                "vendor_sub_category_id" => $vendor_sub_category_id,
                "vendor_group_id" => $vendor_group_id,
                "vendor_grade_id" => $vendor_grade_id,
                "specific_vendor_ids" => $this->_clean_vendor_ids($specific_vendor_ids),
            ],
            "required_sections" => array_values($required_sections),
            "team_ids" => $posted_team_ids,
            "rfq" => $this->_capture_rfq_payload(),
            "testing_workflow_stage" => $testing_workflow_stage,
            "requested_at" => $this->_get_tender_business_now(),
        ];
    }

    private function _prepare_tender_fields_for_manager_payload(array $tender_fields): array
    {
        if (!array_key_exists("tender_request_id", $tender_fields)) {
            return $tender_fields;
        }

        $request_id = (int) ($tender_fields["tender_request_id"] ?? 0);
        if ($request_id > 0) {
            $tender_fields["tender_request_id"] = $request_id;
        } else {
            unset($tender_fields["tender_request_id"]);
        }

        return $tender_fields;
    }

    private function _capture_rfq_payload(): array
    {
        $sr = (array) $this->request->getPost("rfq_item_sr_no");
        $descriptions = (array) $this->request->getPost("rfq_item_description");
        $uoms = (array) $this->request->getPost("rfq_item_uom");
        $qtys = (array) $this->request->getPost("rfq_item_qty");
        $prices = (array) $this->request->getPost("rfq_item_unit_price");
        $brands = (array) $this->request->getPost("rfq_item_brand");
        $max = max(count($sr), count($descriptions), count($uoms), count($qtys), count($prices), count($brands));
        $items = [];

        for ($i = 0; $i < $max; $i++) {
            $items[] = [
                "sr_no" => $sr[$i] ?? "",
                "description" => $descriptions[$i] ?? "",
                "uom" => $uoms[$i] ?? "",
                "qty" => $qtys[$i] ?? "",
                "unit_price" => $prices[$i] ?? "",
                "brand" => $brands[$i] ?? "",
            ];
        }

        return [
            "detail" => [
                "rfq_no" => trim((string) $this->request->getPost("rfq_no")) ?: null,
                "rfq_date" => $this->_date_or_null($this->request->getPost("rfq_date")),
                "pr_no" => trim((string) $this->request->getPost("pr_no")) ?: null,
                "delivery_location" => trim((string) $this->request->getPost("delivery_location")) ?: null,
                "incoterm" => trim((string) $this->request->getPost("incoterm")) ?: null,
                "material_required_on" => $this->_date_or_null($this->request->getPost("material_required_on")),
                "terms_reference" => trim((string) $this->request->getPost("terms_reference")) ?: null,
                "notes" => trim((string) $this->request->getPost("rfq_notes")) ?: null,
                "enclosures" => trim((string) $this->request->getPost("rfq_enclosures")) ?: null,
            ],
            "items" => $items,
        ];
    }

    private function _build_manager_approval_payload(string $action = "initial", ?array $payload = null): array
    {
        $now = $this->_get_tender_business_now();
        return [
            "procurement_manager_status" => "pending",
            "procurement_manager_action" => $action,
            "procurement_manager_payload" => $payload ? json_encode($payload) : null,
            "procurement_manager_submitted_by" => (int) $this->login_user->id,
            "procurement_manager_submitted_at" => $now,
            "procurement_manager_reviewed_by" => null,
            "procurement_manager_reviewed_at" => null,
            "procurement_manager_comment" => null,
            "updated_at" => $now,
        ];
    }

    private function _apply_testing_stage_override(int $tender_id, string $testing_stage, string $reference = ""): void
    {
        $tender = $this->_get_tender_by_id($tender_id);
        if (!$tender || in_array((string) ($tender->status ?? ""), ["awarded", "cancelled"], true)) {
            return;
        }

        $now = $this->_get_tender_business_now();
        $payload = $this->Tender_testing_stage->build_payload($testing_stage, $tender, $now);
        if (!$payload) {
            return;
        }

        $actions = $this->Tender_testing_stage->opening_actions($testing_stage);
        foreach (($actions["expire"] ?? []) as $stage) {
            $this->_expire_testing_opening_sessions($tender_id, (string) $stage, $now);
        }

        foreach (($actions["unlock"] ?? []) as $stage) {
            $this->_ensure_testing_override_opening($tender_id, (string) $stage, $now);
        }

        $this->Tenders_model->ci_save($payload, $tender_id);
        $this->_record_testing_stage_override($tender, $testing_stage, $payload, $reference, $now);
    }

    private function _expire_testing_opening_sessions(int $tender_id, string $stage, string $now): void
    {
        $tbo = $this->db->prefixTable("tender_bid_openings");
        $this->db->query(
            "UPDATE $tbo
             SET status='expired',
                 updated_at=?
             WHERE deleted=0
               AND tender_id=?
               AND stage=?
               AND status IN ('codes_generated', 'unlocked')",
            [$now, $tender_id, $stage]
        );
    }

    private function _ensure_testing_override_opening(int $tender_id, string $stage, string $now): void
    {
        $tbo = $this->db->prefixTable("tender_bid_openings");

        $existing = $this->db->query(
            "SELECT id
             FROM $tbo
             WHERE deleted=0
               AND tender_id=?
               AND stage=?
               AND status='unlocked'
             ORDER BY id DESC
             LIMIT 1",
            [$tender_id, $stage]
        )->getRow();

        if ($existing) {
            return;
        }

        $this->_expire_testing_opening_sessions($tender_id, $stage, $now);

        $this->db->query(
            "INSERT INTO $tbo
                (tender_id, stage, status, chairman_code, secretary_code, member_code, generated_by, generated_at, expires_at, unlocked_at, created_at, updated_at, deleted)
             VALUES
                (?, ?, 'unlocked', ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)",
            [
                $tender_id,
                $stage,
                "TEST",
                "TEST",
                "TEST",
                $this->login_user->id,
                $now,
                $now,
                $now,
                $now,
                $now,
            ]
        );
    }

    private function _record_testing_stage_override($tender, string $testing_stage, array $payload, string $reference, string $now): void
    {
        $history = $this->db->prefixTable("tender_workflow_history");
        $label = $this->Tender_testing_stage->label($testing_stage);

        $this->db->table($history)->insert(clean_data([
            "tender_id" => (int) ($tender->id ?? 0),
            "action_type" => "testing_stage_override",
            "from_status" => (string) ($tender->status ?? ""),
            "to_status" => (string) ($payload["status"] ?? ($tender->status ?? "")),
            "from_stage" => (string) ($tender->workflow_stage ?? ""),
            "to_stage" => (string) ($payload["workflow_stage"] ?? ($tender->workflow_stage ?? "")),
            "open_until" => $payload["commercial_end_at"] ?? $payload["technical_end_at"] ?? $payload["closing_at"] ?? null,
            "reason" => "Temporary procurement testing stage selector",
            "details" => $this->_testing_stage_history_details($tender, $payload, $label),
            "created_by" => $this->login_user->id,
            "created_at" => $now,
            "deleted" => 0,
        ]));

        $this->Tender_communications_model->ci_save(clean_data([
            "tender_id" => (int) ($tender->id ?? 0),
            "vendor_id" => null,
            "parent_id" => null,
            "type" => "circular",
            "subject" => "Internal Testing Stage Override - " . ($reference ?: ($tender->reference ?? "Tender")),
            "message" => "Temporary testing stage opened by procurement.\nStage: " . $label,
            "sent_to_all" => 0,
            "is_vendor_visible" => 0,
            "published_at" => $now,
            "created_by" => $this->login_user->id,
            "status" => "internal",
            "created_at" => $now,
            "deleted" => 0,
        ]));
    }

    private function _testing_stage_history_details($tender, array $payload, string $label): string
    {
        $labels = $this->_tender_milestone_labels() + [
            "status" => "Tender Status",
            "workflow_stage" => "Workflow Stage",
            "technical_start_at" => "Technical Evaluation Start",
            "technical_end_at" => "Technical Evaluation End",
            "technical_locked_at" => "Technical Evaluation Lock",
            "commercial_unlocked_at" => "Commercial Unlock",
            "commercial_start_at" => "Commercial Evaluation Start",
            "commercial_end_at" => "Commercial Evaluation End",
            "award_ready_at" => "Award Decision Ready",
        ];

        $lines = ["Temporary testing stage: " . $label];
        foreach ($labels as $field => $fieldLabel) {
            if (!array_key_exists($field, $payload)) {
                continue;
            }

            $old = $tender->{$field} ?? null;
            $new = $payload[$field] ?? null;
            if ((string) $old === (string) $new) {
                continue;
            }

            $lines[] = $fieldLabel . ": " . ($old ?: "-") . " to " . ($new ?: "-");
        }

        return implode("\n", $lines);
    }

    private function _sync_invites_from_request(int $tender_id, int $tender_request_id): int
    {
        $trv = $this->db->prefixTable("tender_request_vendors");
        $vendors = $this->db->prefixTable("vendors");

        $rows = $this->db->query(
            "SELECT DISTINCT $trv.vendor_id
             FROM $trv
             JOIN $vendors ON $vendors.id = $trv.vendor_id
             WHERE $trv.deleted=0
               AND $vendors.deleted=0
               AND $vendors.status='approved'
               AND $trv.tender_request_id=?",
            [$tender_request_id]
        )->getResult();

        return $this->_replace_invites($tender_id, $this->_vendor_ids_from_rows($rows));
    }

    private function _sync_invites_by_specialty(int $tender_id, int $vendor_category_id, int $vendor_sub_category_id): int
    {
        $vendors = $this->db->prefixTable("vendors");
        $spec = $this->db->prefixTable("vendor_specialties");

        $sql = "SELECT DISTINCT $vendors.id AS vendor_id
                FROM $spec
                JOIN $vendors ON $vendors.id = $spec.vendor_id
                WHERE $spec.deleted=0
                  AND $spec.status='approved'
                  AND $vendors.deleted=0
                  AND $vendors.status='approved'
                  AND $spec.vendor_category_id=?";
        $params = [$vendor_category_id];

        if ($vendor_sub_category_id > 0) {
            $sql .= " AND $spec.vendor_sub_category_id=?";
            $params[] = $vendor_sub_category_id;
        }

        $rows = $this->db->query($sql, $params)->getResult();
        return $this->_replace_invites($tender_id, $this->_vendor_ids_from_rows($rows));
    }

    private function _sync_invites_by_vendor_group(int $tender_id, int $vendor_group_id): int
    {
        $vendors = $this->db->prefixTable("vendors");
        $rows = $this->db->query(
            "SELECT DISTINCT id AS vendor_id
             FROM $vendors
             WHERE deleted=0
               AND status='approved'
               AND vendor_group_id=?",
            [$vendor_group_id]
        )->getResult();

        return $this->_replace_invites($tender_id, $this->_vendor_ids_from_rows($rows));
    }

    private function _sync_invites_from_specific_vendors(int $tender_id, array $vendor_ids): int
    {
        $vendor_ids = $this->_clean_vendor_ids($vendor_ids);
        if (!$vendor_ids) {
            $this->_clear_invites($tender_id);
            return 0;
        }

        $vendors = $this->db->prefixTable("vendors");
        $placeholders = implode(",", array_fill(0, count($vendor_ids), "?"));
        $rows = $this->db->query(
            "SELECT DISTINCT id AS vendor_id
             FROM $vendors
             WHERE deleted=0
               AND status='approved'
               AND id IN ($placeholders)",
            $vendor_ids
        )->getResult();

        return $this->_replace_invites($tender_id, $this->_vendor_ids_from_rows($rows));
    }

    private function _sync_invites_by_vendor_grade(int $tender_id, int $vendor_grade_id): int
    {
        $vendors = $this->db->prefixTable("vendors");
        $rows = $this->db->query(
            "SELECT DISTINCT id AS vendor_id
             FROM $vendors
             WHERE deleted=0
               AND status='approved'
               AND vendor_grade_id=?",
            [$vendor_grade_id]
        )->getResult();

        return $this->_replace_invites($tender_id, $this->_vendor_ids_from_rows($rows));
    }

    private function _clear_invites(int $tender_id): void
    {
        $tiv = $this->db->prefixTable("tender_invited_vendors");
        $this->db->query(
            "DELETE stale
             FROM $tiv stale
             INNER JOIN $tiv active
                ON active.tender_id=stale.tender_id
               AND active.vendor_id=stale.vendor_id
               AND active.deleted=0
             WHERE stale.tender_id=?
               AND stale.deleted=1",
            [$tender_id]
        );
        $this->db->query("UPDATE $tiv SET deleted=1 WHERE tender_id=? AND deleted=0", [$tender_id]);
    }

    private function _replace_invites(int $tender_id, array $vendor_ids): int
    {
        $vendor_ids = $this->_clean_vendor_ids($vendor_ids);
        if (!$vendor_ids) {
            $this->_clear_invites($tender_id);
            return 0;
        }

        $tiv = $this->db->prefixTable("tender_invited_vendors");
        $placeholders = implode(",", array_fill(0, count($vendor_ids), "?"));
        $params = array_merge([$tender_id], $vendor_ids);

        $this->db->query(
            "DELETE stale
             FROM $tiv stale
             INNER JOIN $tiv active
                ON active.tender_id=stale.tender_id
               AND active.vendor_id=stale.vendor_id
               AND active.deleted=0
             WHERE stale.tender_id=?
               AND stale.deleted=1
               AND active.vendor_id NOT IN ($placeholders)",
            $params
        );

        $this->db->query(
            "UPDATE $tiv
             SET deleted=1
             WHERE tender_id=?
               AND deleted=0
               AND vendor_id NOT IN ($placeholders)",
            $params
        );

        $now = date("Y-m-d H:i:s");
        foreach ($vendor_ids as $vendor_id) {
            $active = $this->db->query(
                "SELECT id
                 FROM $tiv
                 WHERE tender_id=?
                   AND vendor_id=?
                   AND deleted=0
                 LIMIT 1",
                [$tender_id, $vendor_id]
            )->getRow();

            if ($active) {
                continue;
            }

            $inactive = $this->db->query(
                "SELECT id
                 FROM $tiv
                 WHERE tender_id=?
                   AND vendor_id=?
                   AND deleted=1
                 ORDER BY id DESC
                 LIMIT 1",
                [$tender_id, $vendor_id]
            )->getRow();

            if ($inactive) {
                $this->db->query(
                    "UPDATE $tiv
                     SET invite_status='sent',
                         invited_by=?,
                         invited_at=?,
                         deleted=0
                     WHERE id=?",
                    [(int) $this->login_user->id, $now, (int) $inactive->id]
                );
                continue;
            }

            $this->db->query(
                "INSERT INTO $tiv (tender_id, vendor_id, invite_status, invited_by, invited_at, deleted)
                 VALUES (?, ?, 'sent', ?, ?, 0)",
                [$tender_id, $vendor_id, (int) $this->login_user->id, $now]
            );
        }

        return count($vendor_ids);
    }

    private function _vendor_ids_from_rows(array $rows): array
    {
        $vendor_ids = [];
        foreach ($rows as $row) {
            $vendor_id = (int) ($row->vendor_id ?? 0);
            if ($vendor_id > 0) {
                $vendor_ids[$vendor_id] = $vendor_id;
            }
        }

        return array_values($vendor_ids);
    }

    private function _count_active_invites(int $tender_id): int
    {
        $tiv = $this->db->prefixTable("tender_invited_vendors");
        $row = $this->db->query(
            "SELECT COUNT(*) AS total
             FROM $tiv
             WHERE deleted=0
               AND tender_id=?",
            [$tender_id]
        )->getRow();

        return (int) ($row->total ?? 0);
    }

    private function _get_commercial_decision_summary(int $tender_id): array
    {
        $te = $this->db->prefixTable("tender_evaluations");
        $tb = $this->db->prefixTable("tender_bids");
        $row = $this->db->query(
            "SELECT
                SUM(CASE WHEN te.decision='accepted' THEN 1 ELSE 0 END) AS approved_count,
                MAX(CASE WHEN te.decision='accepted' THEN $tb.vendor_id ELSE NULL END) AS winner_vendor_id
             FROM $tb
             LEFT JOIN (
                 SELECT tender_bid_id, MAX(id) AS max_id
                 FROM $te
                 WHERE deleted=0
                   AND type='commercial'
                 GROUP BY tender_bid_id
             ) latest ON latest.tender_bid_id = $tb.id
             LEFT JOIN $te te ON te.id = latest.max_id
             WHERE $tb.deleted=0
               AND $tb.tender_id=?",
            [$tender_id]
        )->getRowArray();

        return $row ?: ["approved_count" => 0, "winner_vendor_id" => 0];
    }

    private function _clone_tender_related_rows(int $source_tender_id, int $new_tender_id): void
    {
        $docs = $this->db->prefixTable("tender_documents");
        $team = $this->db->prefixTable("tender_team_members");
        $target = $this->db->prefixTable("tender_target_specialties");
        $target_vendors = $this->db->prefixTable("tender_target_vendors");
        $invites = $this->db->prefixTable("tender_invited_vendors");
        $requirements = $this->db->prefixTable("tender_bid_requirements");
        $rfq_details = $this->db->prefixTable("tender_rfq_details");
        $rfq_items = $this->db->prefixTable("tender_rfq_items");

        $now = date("Y-m-d H:i:s");

        $this->db->query(
            "INSERT INTO $docs (tender_id, doc_type, title, disk, path, original_name, mime_type, size_bytes, time_limited, expires_in_hours, uploaded_by, created_at, updated_at, deleted)
             SELECT ?, doc_type, title, disk, path, original_name, mime_type, size_bytes, time_limited, expires_in_hours, uploaded_by, ?, ?, 0
             FROM $docs
             WHERE deleted=0 AND tender_id=?",
            [$new_tender_id, $now, $now, $source_tender_id]
        );

        $this->db->query(
            "INSERT INTO $team (tender_id, user_id, team_role, is_active, created_at, updated_at, deleted)
             SELECT ?, user_id, team_role, is_active, ?, ?, 0
             FROM $team
             WHERE deleted=0 AND tender_id=?",
            [$new_tender_id, $now, $now, $source_tender_id]
        );

        $this->db->query(
            "INSERT INTO $target (tender_id, vendor_category_id, vendor_sub_category_id, vendor_group_id, vendor_grade_id, created_by, created_at, deleted)
             SELECT ?, vendor_category_id, vendor_sub_category_id, vendor_group_id, vendor_grade_id, ?, ?, 0
             FROM $target
             WHERE deleted=0 AND tender_id=?",
            [$new_tender_id, $this->login_user->id, $now, $source_tender_id]
        );

        $this->db->query(
            "INSERT INTO $target_vendors (tender_id, vendor_id, created_by, created_at, deleted)
             SELECT ?, vendor_id, ?, ?, 0
             FROM $target_vendors
             WHERE deleted=0 AND tender_id=?",
            [$new_tender_id, $this->login_user->id, $now, $source_tender_id]
        );

        $this->db->query(
            "INSERT INTO $invites (tender_id, vendor_id, invite_status, invited_by, invited_at, deleted)
             SELECT ?, vendor_id, 'sent', ?, ?, 0
             FROM $invites
             WHERE deleted=0 AND tender_id=?",
            [$new_tender_id, $this->login_user->id, $now, $source_tender_id]
        );

        $this->db->query(
            "INSERT INTO $requirements (tender_id, code, label, is_required, sort_order, created_at, updated_at, deleted)
             SELECT ?, code, label, is_required, sort_order, ?, ?, 0
             FROM $requirements
             WHERE deleted=0 AND tender_id=?",
            [$new_tender_id, $now, $now, $source_tender_id]
        );

        $this->db->query(
            "INSERT INTO $rfq_details (tender_id, rfq_no, rfq_date, pr_no, delivery_location, incoterm, material_required_on, terms_reference, notes, enclosures, created_at, updated_at, deleted)
             SELECT ?, rfq_no, rfq_date, pr_no, delivery_location, incoterm, material_required_on, terms_reference, notes, enclosures, ?, ?, 0
             FROM $rfq_details
             WHERE deleted=0 AND tender_id=?",
            [$new_tender_id, $now, $now, $source_tender_id]
        );

        $this->db->query(
            "INSERT INTO $rfq_items (tender_id, sr_no, description, uom, qty, unit_price, brand, sort_order, created_at, updated_at, deleted)
             SELECT ?, sr_no, description, uom, qty, unit_price, brand, sort_order, ?, ?, 0
             FROM $rfq_items
             WHERE deleted=0 AND tender_id=?",
            [$new_tender_id, $now, $now, $source_tender_id]
        );
    }

    private function _send_invitation_notifications(int $tender_id): void
    {
        $tender = $this->_get_tender_by_id($tender_id);
        if (!$tender) {
            return;
        }

        foreach ($this->_get_tender_recipients($tender_id) as $recipient) {
            $subject = "Tender Invitation - " . ($tender->reference ?? "Tender");
            $message = "Dear " . ($recipient->vendor_name ?? "Vendor") . ",\n\n"
                . "You are invited to participate in tender " . ($tender->reference ?? "-") . " (" . ($tender->title ?? "-") . ").\n"
                . "Submission deadline: " . ($tender->closing_at ?? "-") . ".\n\n"
                . "Please log in to the vendor portal to review the tender and submit your bid.\n";
            $this->_send_tender_email($recipient->email ?? null, $subject, $message);
        }
    }

    private function _send_award_and_regret_notifications(int $tender_id, int $winner_vendor_id): void
    {
        $tender = $this->_get_tender_by_id($tender_id);
        if (!$tender) {
            return;
        }

        foreach ($this->_get_tender_recipients($tender_id) as $recipient) {
            $vendor_id = (int) ($recipient->vendor_id ?? 0);
            if (!$vendor_id) {
                continue;
            }

            if ($vendor_id === $winner_vendor_id) {
                $subject = "Tender Award Notification - " . ($tender->reference ?? "Tender");
                $message = "Dear " . ($recipient->vendor_name ?? "Vendor") . ",\n\n"
                    . "We are pleased to inform you that your bid has been awarded for tender "
                    . ($tender->reference ?? "-") . " (" . ($tender->title ?? "-") . ").\n\n"
                    . "Procurement will contact you with the next steps.\n";
            } else {
                $subject = "Tender Regret Notification - " . ($tender->reference ?? "Tender");
                $message = "Dear " . ($recipient->vendor_name ?? "Vendor") . ",\n\n"
                    . "Thank you for participating in tender " . ($tender->reference ?? "-") . " (" . ($tender->title ?? "-") . ").\n"
                    . "After evaluation, another vendor has been selected.\n";
            }

            $this->_send_tender_email($recipient->email ?? null, $subject, $message);
        }
    }

    private function _send_cancellation_notifications(int $tender_id): void
    {
        $tender = $this->_get_tender_by_id($tender_id);
        if (!$tender) {
            return;
        }

        foreach ($this->_get_tender_recipients($tender_id) as $recipient) {
            $subject = "Tender Cancellation - " . ($tender->reference ?? "Tender");
            $message = "Dear " . ($recipient->vendor_name ?? "Vendor") . ",\n\n"
                . "Please be informed that tender " . ($tender->reference ?? "-") . " (" . ($tender->title ?? "-") . ") has been cancelled.\n";
            $this->_send_tender_email($recipient->email ?? null, $subject, $message);
        }
    }

    private function _get_tender_recipients(int $tender_id): array
    {
        $tiv = $this->db->prefixTable("tender_invited_vendors");
        $vendors = $this->db->prefixTable("vendors");

        return $this->db->query(
            "SELECT DISTINCT
                $vendors.id AS vendor_id,
                $vendors.vendor_name,
                $vendors.email
             FROM $tiv
             INNER JOIN $vendors ON $vendors.id = $tiv.vendor_id
             WHERE $tiv.deleted=0
               AND $vendors.deleted=0
               AND $tiv.tender_id=?",
            [$tender_id]
        )->getResult();
    }

    private function _send_tender_email(?string $to, string $subject, string $message): void
    {
        if (!self::TENDER_EMAILS_ENABLED || !$to) {
            return;
        }

        try {
            send_app_mail($to, $subject, nl2br($message));
        } catch (\Throwable $e) {
        }
    }

    private function _get_tender_business_now(): string
    {
        return Time::now("Asia/Muscat")->toDateTimeString();
    }

    private function _normalize_tender_datetime($value, string $edge = "end")
    {
        $value = trim((string) $value);
        if ($value === "") {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $value)) {
            $value = str_replace("T", " ", $value);
            return strlen($value) === 16 ? $value . ":00" : $value;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}$/', $value)) {
            return $value;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value . ($edge === "end" ? " 23:59:59" : " 00:00:00");
        }

        $ts = strtotime($value);
        return $ts === false ? null : date("Y-m-d H:i:s", $ts);
    }

    private function _make_row($row): array
    {
        $request_status = (string) ($row->request_status ?? "direct");
        $request_badge_class = $request_status === "direct" ? "bg-dark" : "bg-secondary";
        $req_status = "<span class='badge $request_badge_class'>" . esc(str_replace("_", " ", ucfirst($request_status))) . "</span>";

        $tender_status_value = (string) ($row->tender_status ?? "");
        if ($tender_status_value === "") {
            $tender_status = "<span class='badge bg-light text-dark'>not created</span>";
        } else {
            $class = match ($tender_status_value) {
                "draft" => "bg-secondary",
                "published" => "bg-primary",
                "closed" => "bg-dark",
                "awarded" => "bg-success",
                "cancelled" => "bg-danger",
                default => "bg-secondary",
            };
            $tender_status = "<span class='badge $class'>" . esc($tender_status_value) . "</span>";
        }

        $manager_status_value = (string) ($row->procurement_manager_status ?? "draft");
        $manager_class = match ($manager_status_value) {
            "pending" => "bg-warning",
            "approved" => "bg-success",
            "rejected" => "bg-danger",
            "revision_requested" => "bg-info",
            default => "bg-secondary",
        };
        $manager_status = "<span class='badge $manager_class'>" . esc(ucwords(str_replace("_", " ", $manager_status_value ?: "draft"))) . "</span>";
        $workflow_stage_value = (string) ($row->tender_workflow_stage ?? "");
        if ($workflow_stage_value === "") {
            $workflow_stage = "<span class='badge bg-light text-dark'>-</span>";
        } else {
            $stage_class = match ($workflow_stage_value) {
                "bidding" => "bg-primary",
                "technical_3key" => "bg-warning text-dark",
                "technical" => "bg-info text-dark",
                "commercial" => "bg-primary",
                "award_decision" => "bg-success",
                default => "bg-secondary",
            };
            $stage_label = match ($workflow_stage_value) {
                "technical_3key" => "Bid Opening",
                "award_decision" => "Award Decision",
                default => ucwords(str_replace("_", " ", $workflow_stage_value)),
            };
            $workflow_stage = "<span class='badge $stage_class'>" . esc($stage_label) . "</span>";
        }

        $setup_url = !empty($row->tender_id)
            ? get_uri("tender_procurement_inbox/form?tender_id=" . (int) $row->tender_id)
            : get_uri("tender_procurement_inbox/form?id=" . (int) ($row->tender_request_id ?? 0));

        $setup = anchor(
            $setup_url,
            "<i data-feather='settings' class='icon-16'></i>",
            ["title" => !empty($row->tender_id) ? "Edit Tender" : "Setup Tender"]
        );

        $publish = "";
        if (!empty($row->tender_id) && in_array($tender_status_value, ["draft", ""], true) && $manager_status_value === "approved") {
            $publish = js_anchor(
                "<i data-feather='send' class='icon-16'></i>",
                [
                    "title" => "Publish",
                    "class" => "publish",
                    "data-tender-id" => (int) $row->tender_id,
                    "data-action-url" => get_uri("tender_procurement_inbox/publish"),
                ]
            );
        }

        $report = "";
        if (!empty($row->tender_id)) {
            $report = anchor(
                get_uri("tender_reports/details/" . (int) $row->tender_id),
                "<i data-feather='bar-chart-2' class='icon-16'></i>",
                ["title" => "Tender Register Detail"]
            );
        }

        $final_actions = "";
        if (!empty($row->tender_id) && in_array($tender_status_value, ["published", "closed"], true)) {
            $final_actions .= js_anchor(
                "<i data-feather='x-circle' class='icon-16'></i>",
                [
                    "title" => "Request Cancellation Approval",
                    "class" => "cancel-tender",
                    "data-tender-id" => (int) $row->tender_id,
                    "data-action-url" => get_uri("tender_procurement_inbox/cancel_tender"),
                ]
            );
        }

        if (!empty($row->tender_id) && $tender_status_value === "closed" && ($row->tender_workflow_stage ?? "") === "award_decision") {
            if ($final_actions !== "") {
                $final_actions .= " ";
            }
            $final_actions .= js_anchor(
                "<i data-feather='award' class='icon-16'></i>",
                [
                    "title" => "Award",
                    "class" => "award",
                    "data-tender-id" => (int) $row->tender_id,
                    "data-action-url" => get_uri("tender_procurement_inbox/award"),
                ]
            );
            $final_actions .= " " . js_anchor(
                "<i data-feather='copy' class='icon-16'></i>",
                [
                    "title" => "Retender",
                    "class" => "retender",
                    "data-tender-id" => (int) $row->tender_id,
                    "data-action-url" => get_uri("tender_procurement_inbox/retender"),
                ]
            );
        }

        return [
            esc($row->reference ?? "-"),
            !empty($row->created_at) ? esc(format_to_datetime($row->created_at)) : "-",
            esc($row->subject ?? "-"),
            esc($row->company_name ?? "-"),
            esc($row->department_name ?? "-"),
            esc($row->tender_type ?? "open"),
            $req_status,
            $tender_status,
            $manager_status,
            $workflow_stage,
            !empty($row->closing_at) ? esc($row->closing_at) : "-",
            trim($setup . " " . $publish . " " . $report . " " . $final_actions),
        ];
    }
}
