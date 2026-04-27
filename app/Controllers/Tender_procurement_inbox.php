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
                    COALESCE(t.title, req.subject) AS subject,
                    COALESCE(t.company_id, req.company_id) AS company_id,
                    company.name AS company_name,
                    COALESCE(t.department_id, req.department_id) AS department_id,
                    department.name AS department_name,
                    COALESCE(t.tender_type, req.tender_type, 'open') AS tender_type,
                    COALESCE(req.status, 'direct') AS request_status,
                    t.status AS tender_status,
                    t.workflow_stage AS tender_workflow_stage,
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
                    req.subject,
                    req.company_id,
                    company.name AS company_name,
                    req.department_id,
                    department.name AS department_name,
                    req.tender_type,
                    req.status AS request_status,
                    '' AS tender_status,
                    '' AS tender_workflow_stage,
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
                ORDER BY tender_id DESC, tender_request_id DESC";

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

        if ($tender && !empty($tender->id)) {
            $target = $this->_get_latest_target_rule((int) $tender->id);
            if ($target) {
                if ((int) ($target->vendor_group_id ?? 0) > 0) {
                    $selected_target_mode = "group";
                    $selected_vendor_group_id = (int) $target->vendor_group_id;
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
            "company_dropdown" => $this->_get_company_dropdown(),
            "department_dropdown" => $this->_get_department_dropdown(),
            "target" => $target,
            "target_cat" => $target_cat,
            "target_sub" => $target_sub,
            "selected_target_mode" => $selected_target_mode,
            "selected_vendor_group_id" => $selected_vendor_group_id,
            "request_selected_vendors" => $request_selected_vendors,
            "technical_users_dropdown" => $this->_get_role_users_dropdown("technical", $company_id),
            "commercial_users_dropdown" => $this->_get_role_users_dropdown("commercial", $company_id),
            "committee_users_dropdown" => $this->_get_role_users_dropdown("committee", $company_id),
            "existing_team_ids" => $existing_team_ids,
            "existing_required_codes" => $existing_required_codes,
            "bid_requirement_labels" => $this->Tender_bid_requirements_model->get_default_labels(),
            "rfq_detail" => $rfq_detail,
            "rfq_items" => $rfq_items,
            "company_id" => $company_id,
            "department_id" => $department_id,
        ];
    }

    public function save()
    {
        $this->validate_submitted_data([
            "tender_id" => "numeric",
            "tender_request_id" => "numeric",
            "reference" => "required",
            "title" => "required",
            "closing_at" => "required",
        ]);

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

        if (!$closing_at) {
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
        $schedule_error = $this->_validate_tender_schedule($new_milestones);
        if ($schedule_error) {
            return $this->response->setJSON(["success" => false, "message" => $schedule_error]);
        }

        $target_mode = strtolower(trim((string) $this->request->getPost("target_mode")));
        if (!in_array($target_mode, ["specialty", "group"], true)) {
            $target_mode = "specialty";
        }

        $vendor_category_id = (int) $this->request->getPost("vendor_category_id");
        $vendor_sub_category_id = (int) $this->request->getPost("vendor_sub_category_id");
        $vendor_group_id = (int) $this->request->getPost("vendor_group_id");

        $required_sections = (array) $this->request->getPost("required_sections");

        $data = [
            "tender_request_id" => $tender_request_id ?: null,
            "reference" => $reference,
            "title" => $title,
            "company_id" => $company_id,
            "department_id" => $department_id,
            "brief_description" => $brief_description ?: ($request->brief_description ?? null),
            "tender_type" => $tender_type,
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

        $this->_save_target_rule($tender_id, $target_mode, $vendor_category_id, $vendor_sub_category_id, $vendor_group_id);
        $this->Tender_bid_requirements_model->sync_requirements($tender_id, $required_sections);
        $this->_sync_rfq_data($tender_id);
        $this->_save_tender_documents($tender_id);

        $posted_team_ids = $this->_get_posted_team_ids();
        if ($this->_has_any_posted_team_selection($posted_team_ids)) {
            $this->_sync_tender_teams_from_post($tender_id, $posted_team_ids);
        } elseif ($request) {
            $this->_sync_teams_from_request($tender_id, (int) $request->id);
        }

        $request_selected_vendors = $request ? $this->Tender_request_vendors_model->get_selected_vendors((int) $request->id) : [];
        $has_request_selected_vendors = count($request_selected_vendors) > 0;
        $has_specialty_target = $target_mode === "specialty" && $vendor_category_id > 0;
        $has_group_target = $target_mode === "group" && $vendor_group_id > 0;

        if ($tender_type === "close" && !$has_request_selected_vendors && !$has_specialty_target && !$has_group_target) {
            $this->db->transComplete();
            return $this->response->setJSON([
                "success" => false,
                "message" => "For close tenders, select request vendors or choose a vendor specialty/group target."
            ]);
        }

        if ($has_request_selected_vendors) {
            $invited_count = $this->_sync_invites_from_request($tender_id, (int) $request->id);
        } elseif ($has_specialty_target) {
            $invited_count = $this->_sync_invites_by_specialty($tender_id, $vendor_category_id, $vendor_sub_category_id);
        } elseif ($has_group_target) {
            $invited_count = $this->_sync_invites_by_vendor_group($tender_id, $vendor_group_id);
        } else {
            $this->_clear_invites($tender_id);
            $invited_count = 0;
        }

        $publish_now = (int) $this->request->getPost("publish_now") === 1;
        if ($publish_now) {
            $publish_error = $this->_validate_tender_can_publish($tender_id, $tender_type);
            if ($publish_error) {
                $this->db->transComplete();
                return $this->response->setJSON(["success" => false, "message" => $publish_error]);
            }

            $fresh_tender = $this->_get_tender_by_id($tender_id);
            $this->Tenders_model->ci_save($this->_build_publish_payload($fresh_tender), $tender_id);
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

        return $this->response->setJSON([
            "success" => true,
            "message" => ($publish_now ? "Tender published" : "Tender saved") . ". Vendors matched: " . (int) $invited_count,
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
            "tender_type" => (string) ($source->tender_type ?? "open"),
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

    private function _save_target_rule(int $tender_id, string $target_mode, int $vendor_category_id, int $vendor_sub_category_id, int $vendor_group_id): void
    {
        $tts = $this->db->prefixTable("tender_target_specialties");
        $this->db->query("UPDATE $tts SET deleted=1 WHERE tender_id=?", [$tender_id]);

        if ($target_mode === "specialty" && $vendor_category_id <= 0) {
            return;
        }

        if ($target_mode === "group" && $vendor_group_id <= 0) {
            return;
        }

        $this->db->query(
            "INSERT INTO $tts
             (tender_id, vendor_category_id, vendor_sub_category_id, vendor_group_id, created_by, created_at, deleted)
             VALUES (?, ?, ?, ?, ?, ?, 0)",
            [
                $tender_id,
                $target_mode === "specialty" ? $vendor_category_id : 0,
                $target_mode === "specialty" && $vendor_sub_category_id > 0 ? $vendor_sub_category_id : null,
                $target_mode === "group" && $vendor_group_id > 0 ? $vendor_group_id : null,
                $this->login_user->id,
                date("Y-m-d H:i:s"),
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

    private function _get_posted_team_ids(): array
    {
        return [
            "technical" => (array) $this->request->getPost("technical_user_ids"),
            "commercial" => (array) $this->request->getPost("commercial_user_ids"),
            "chairman" => (array) $this->request->getPost("chairman_user_id"),
            "secretary" => (array) $this->request->getPost("secretary_user_id"),
            "itc_member" => (array) $this->request->getPost("itc_member_user_ids"),
        ];
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

    private function _validate_tender_can_publish(int $tender_id, string $tender_type): ?string
    {
        $tender = $this->_get_tender_by_id($tender_id);
        if (!$tender) {
            return "Create the tender first.";
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

    private function _sync_invites_from_request(int $tender_id, int $tender_request_id): int
    {
        $trv = $this->db->prefixTable("tender_request_vendors");
        $tiv = $this->db->prefixTable("tender_invited_vendors");
        $vendors = $this->db->prefixTable("vendors");

        $this->_clear_invites($tender_id);

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

        $count = 0;
        $now = date("Y-m-d H:i:s");
        foreach ($rows as $row) {
            $vendor_id = (int) ($row->vendor_id ?? 0);
            if (!$vendor_id) {
                continue;
            }

            $this->db->query(
                "INSERT INTO $tiv (tender_id, vendor_id, invite_status, invited_by, invited_at, deleted)
                 VALUES (?, ?, 'sent', ?, ?, 0)",
                [$tender_id, $vendor_id, $this->login_user->id, $now]
            );
            $count++;
        }

        return $count;
    }

    private function _sync_invites_by_specialty(int $tender_id, int $vendor_category_id, int $vendor_sub_category_id): int
    {
        $this->_clear_invites($tender_id);
        $tiv = $this->db->prefixTable("tender_invited_vendors");
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
        $count = 0;
        $now = date("Y-m-d H:i:s");
        foreach ($rows as $row) {
            $vendor_id = (int) ($row->vendor_id ?? 0);
            if (!$vendor_id) {
                continue;
            }

            $this->db->query(
                "INSERT INTO $tiv (tender_id, vendor_id, invite_status, invited_by, invited_at, deleted)
                 VALUES (?, ?, 'sent', ?, ?, 0)",
                [$tender_id, $vendor_id, $this->login_user->id, $now]
            );
            $count++;
        }

        return $count;
    }

    private function _sync_invites_by_vendor_group(int $tender_id, int $vendor_group_id): int
    {
        $this->_clear_invites($tender_id);
        $tiv = $this->db->prefixTable("tender_invited_vendors");
        $vendors = $this->db->prefixTable("vendors");
        $rows = $this->db->query(
            "SELECT DISTINCT id AS vendor_id
             FROM $vendors
             WHERE deleted=0
               AND status='approved'
               AND vendor_group_id=?",
            [$vendor_group_id]
        )->getResult();

        $count = 0;
        $now = date("Y-m-d H:i:s");
        foreach ($rows as $row) {
            $vendor_id = (int) ($row->vendor_id ?? 0);
            if (!$vendor_id) {
                continue;
            }

            $this->db->query(
                "INSERT INTO $tiv (tender_id, vendor_id, invite_status, invited_by, invited_at, deleted)
                 VALUES (?, ?, 'sent', ?, ?, 0)",
                [$tender_id, $vendor_id, $this->login_user->id, $now]
            );
            $count++;
        }

        return $count;
    }

    private function _clear_invites(int $tender_id): void
    {
        $tiv = $this->db->prefixTable("tender_invited_vendors");
        $this->db->query("UPDATE $tiv SET deleted=1 WHERE tender_id=?", [$tender_id]);
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
            "INSERT INTO $target (tender_id, vendor_category_id, vendor_sub_category_id, vendor_group_id, created_by, created_at, deleted)
             SELECT ?, vendor_category_id, vendor_sub_category_id, vendor_group_id, ?, ?, 0
             FROM $target
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

        $setup_url = !empty($row->tender_id)
            ? get_uri("tender_procurement_inbox/form?tender_id=" . (int) $row->tender_id)
            : get_uri("tender_procurement_inbox/form?id=" . (int) ($row->tender_request_id ?? 0));

        $setup = anchor(
            $setup_url,
            "<i data-feather='settings' class='icon-16'></i>",
            ["title" => !empty($row->tender_id) ? "Edit Tender" : "Setup Tender"]
        );

        $publish = "";
        if (!empty($row->tender_id) && in_array($tender_status_value, ["draft", ""], true)) {
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
        if (!empty($row->tender_id) && $tender_status_value === "closed" && ($row->tender_workflow_stage ?? "") === "award_decision") {
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
                "<i data-feather='x-circle' class='icon-16'></i>",
                [
                    "title" => "Cancel",
                    "class" => "cancel-tender",
                    "data-tender-id" => (int) $row->tender_id,
                    "data-action-url" => get_uri("tender_procurement_inbox/cancel_tender"),
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
            esc($row->subject ?? "-"),
            esc($row->company_name ?? "-"),
            esc($row->department_name ?? "-"),
            esc($row->tender_type ?? "open"),
            $req_status,
            $tender_status,
            !empty($row->closing_at) ? esc($row->closing_at) : "-",
            trim($setup . " " . $publish . " " . $report . " " . $final_actions),
        ];
    }
}
