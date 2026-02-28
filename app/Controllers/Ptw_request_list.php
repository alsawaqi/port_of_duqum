<?php

namespace App\Controllers;

use App\Models\Ptw_applications_model;
use App\Models\Gate_pass_companies_model;
use App\Models\Ptw_requirement_definitions_model;
use App\Models\Ptw_requirement_responses_model;
use App\Models\Ptw_attachments_model;
use App\Models\Ptw_reviews_model;
use App\Models\Ptw_audit_logs_model;

class Ptw_request_list extends Security_Controller
{
    protected $Ptw_applications_model;
    protected $Gate_pass_companies_model;

    protected $Ptw_requirement_definitions_model;
    protected $Ptw_requirement_responses_model;
    protected $Ptw_attachments_model;
    protected $Ptw_reviews_model;
    protected $Ptw_audit_logs_model;

    public function __construct()
    {
        parent::__construct();
        helper('general');
        $this->access_only_team_members();

        $this->Ptw_applications_model = new Ptw_applications_model();
        $this->Gate_pass_companies_model = new Gate_pass_companies_model();

        $this->Ptw_requirement_definitions_model = new Ptw_requirement_definitions_model();
        $this->Ptw_requirement_responses_model = new Ptw_requirement_responses_model();
        $this->Ptw_attachments_model = new Ptw_attachments_model();
        $this->Ptw_reviews_model = new Ptw_reviews_model();
        $this->Ptw_audit_logs_model = new Ptw_audit_logs_model();
    }

    public function index()
    {
        $this->access_only_ptw("request_list", "view");

        $companies = $this->Gate_pass_companies_model->get_details()->getResult();

        return $this->template->rander("ptw_request_list/index", [
            "companies" => $companies
        ]);
    }

    public function list_data()
    {
        $this->access_only_ptw("request_list", "view");

        $db = db_connect();
        $apps = $db->prefixTable("ptw_applications");

        $company_name = trim((string)$this->request->getGet("company_name"));
        $stage = trim((string)$this->request->getGet("stage"));
        $status = trim((string)$this->request->getGet("status"));
        $date_from = trim((string)$this->request->getGet("date_from"));
        $date_to = trim((string)$this->request->getGet("date_to"));
        $search = trim((string)$this->request->getGet("search"));

        $where = " WHERE $apps.deleted=0 ";

        if ($company_name !== "") {
            $where .= " AND $apps.company_name=" . $db->escape($company_name);
        }

        if ($stage !== "") {
            $where .= " AND $apps.stage=" . $db->escape($stage);
        }

        if ($status !== "") {
            $where .= " AND $apps.status=" . $db->escape($status);
        }

        // Date filter uses work_from date (same idea as Gate Pass visit_from)
        if ($date_from !== "") {
            $where .= " AND DATE($apps.work_from)>=" . $db->escape($date_from);
        }
        if ($date_to !== "") {
            $where .= " AND DATE($apps.work_from)<=" . $db->escape($date_to);
        }

        // Search across reference/company/applicant/email/location
        if ($search !== "") {
            $s = $db->escapeLikeString($search);
            $where .= " AND (
                $apps.reference LIKE '%$s%' ESCAPE '!'
                OR $apps.company_name LIKE '%$s%' ESCAPE '!'
                OR $apps.applicant_name LIKE '%$s%' ESCAPE '!'
                OR $apps.contact_email LIKE '%$s%' ESCAPE '!'
                OR $apps.exact_location LIKE '%$s%' ESCAPE '!'
            )";
        }

        $sql = "SELECT $apps.*
                FROM $apps
                $where
                ORDER BY $apps.id DESC";

        $list = $db->query($sql)->getResult();

        $result = [];
        foreach ($list as $row) {
            $result[] = $this->_make_row($row);
        }

        return $this->response->setJSON(["data" => $result]);
    }

    public function details($id = 0)
    {
        $this->access_only_ptw("request_list", "view");

        $id = (int)$id;
        if (!$id) {
            app_redirect("forbidden");
        }

        $app = $this->Ptw_applications_model->get_details(["id" => $id])->getRow();
        if (!$app) {
            app_redirect("forbidden");
        }

        // Requirements + attachments (same as inbox)
        $defs = $this->Ptw_requirement_definitions_model->get_active_definitions()->getResult();
        $responses_rows = $this->Ptw_requirement_responses_model->get_by_application($app->id)->getResult();
        $attachments_rows = $this->Ptw_attachments_model->get_by_application($app->id)->getResult();

        $responses_by_definition = [];
        foreach ($responses_rows as $r) {
            $responses_by_definition[(int)($r->ptw_requirement_definition_id ?? 0)] = $r;
        }

        $attachments_by_response = [];
        foreach ($attachments_rows as $att) {
            $rid = (int)($att->ptw_requirement_response_id ?? 0);
            if ($rid > 0) {
                $attachments_by_response[$rid] = $att;
            }
        }

        // Reviews by stage
        $hsse_reviews = $this->Ptw_reviews_model->get_details(["ptw_application_id" => $app->id, "stage" => "hsse"])->getResult();
        $hmo_reviews = $this->Ptw_reviews_model->get_details(["ptw_application_id" => $app->id, "stage" => "hmo"])->getResult();
        $terminal_reviews = $this->Ptw_reviews_model->get_details(["ptw_application_id" => $app->id, "stage" => "terminal"])->getResult();

        // Audit logs
        $audit_logs = $this->Ptw_audit_logs_model->get_by_application($app->id)->getResult();

        return $this->template->rander("ptw_request_list/details", [
            "application" => $app,
            "definitions_grouped" => $this->_group_definitions($defs),
            "responses_by_definition" => $responses_by_definition,
            "attachments_by_response" => $attachments_by_response,
            "hsse_reviews" => $hsse_reviews,
            "hmo_reviews" => $hmo_reviews,
            "terminal_reviews" => $terminal_reviews,
            "audit_logs" => $audit_logs,
        ]);
    }

    private function _make_row($row)
    {
        $view_btn = anchor(
            get_uri("ptw_request_list/details/" . (int)$row->id),
            "<i data-feather='eye' class='icon-16'></i> " . app_lang("view"),
            ["class" => "btn btn-default btn-sm", "title" => app_lang("view"), "target" => "_blank"]
        );

        return [
            $row->reference ?? "-",
            $row->company_name ?? "-",
            $row->applicant_name ?? "-",
            $row->work_supervisor_name ?? "-",
            !empty($row->work_from) ? format_to_datetime($row->work_from) : "-",
            !empty($row->work_to) ? format_to_datetime($row->work_to) : "-",
            $this->_format_ptw_status($row->status ?? ""),
            $row->stage ?? "-",
            $view_btn,
        ];
    }

    private function _format_ptw_status($status, $empty = "-")
    {
        $status = strtolower(trim((string)$status));
        if ($status === "") return $empty;

        $class = "badge bg-secondary";
        if ($status === "submitted") $class = "badge bg-primary";
        if ($status === "approved") $class = "badge bg-success";
        if ($status === "rejected") $class = "badge bg-danger";
        if ($status === "revise") $class = "badge bg-warning text-dark";
        if ($status === "draft") $class = "badge bg-light text-dark border";

        return "<span class='{$class}'>" . ucwords(str_replace("_", " ", $status)) . "</span>";
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
            $cat = (string)($d->category ?? "other");
            if (!isset($grouped[$cat])) {
                $grouped[$cat] = [];
            }
            $grouped[$cat][] = $d;
        }

        return $grouped;
    }
}