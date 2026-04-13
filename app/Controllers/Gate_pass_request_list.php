<?php

namespace App\Controllers;

use App\Models\Gate_pass_requests_model;
use App\Models\Gate_pass_companies_model;
use App\Models\Gate_pass_departments_model;
use App\Models\Gate_pass_purposes_model;

class Gate_pass_request_list extends Security_Controller
{
    protected $Gate_pass_requests_model;
    protected $Gate_pass_companies_model;
    protected $Gate_pass_departments_model;
    protected $Gate_pass_purposes_model;

    public function __construct()
    {
        parent::__construct();
        helper('general');
        $this->access_only_team_members();
        $this->Gate_pass_requests_model = new Gate_pass_requests_model();
        $this->Gate_pass_companies_model = new Gate_pass_companies_model();
        $this->Gate_pass_departments_model = new Gate_pass_departments_model();
        $this->Gate_pass_purposes_model = new Gate_pass_purposes_model();
    }

    public function index()
    {
        $this->access_only_gate_pass("request_list", "view");
        $companies = $this->Gate_pass_companies_model->get_details()->getResult();
        $departments = $this->Gate_pass_departments_model->get_details()->getResult();
        $purposes = $this->Gate_pass_purposes_model->get_details()->getResult();

        $view_data = [
            "companies" => $companies,
            "departments" => $departments,
            "purposes" => $purposes,
        ];
        return $this->template->rander("gate_pass_request_list/index", $view_data);
    }

    public function list_data()
    {
        $this->access_only_gate_pass("request_list", "view");
        $company_id = $this->request->getGet("company_id");
        $department_id = $this->request->getGet("department_id");
        $status = $this->request->getGet("status");
        $gate_pass_purpose_id = $this->request->getGet("gate_pass_purpose_id");
        $date_from = $this->request->getGet("date_from");
        $date_to = $this->request->getGet("date_to");

        $options = [];
        if ($company_id !== null && $company_id !== "") {
            $options["company_id"] = (int)$company_id;
        }
        if ($department_id !== null && $department_id !== "") {
            $options["department_id"] = (int)$department_id;
        }
        if ($status !== null && $status !== "") {
            $options["status"] = $status;
        }
        if ($gate_pass_purpose_id !== null && $gate_pass_purpose_id !== "") {
            $options["gate_pass_purpose_id"] = (int)$gate_pass_purpose_id;
        }
        if ($date_from) {
            $options["date_from"] = $date_from;
        }
        if ($date_to) {
            $options["date_to"] = $date_to;
        }

        $list = $this->Gate_pass_requests_model->get_details($options)->getResult();
        $result = [];
        foreach ($list as $row) {
            $result[] = $this->_make_row($row);
        }
        echo json_encode(["data" => $result]);
    }

    private function _make_row($row)
    {
        $view_btn = anchor(
            get_uri("gate_pass_portal/request_details/" . $row->id),
            "<i data-feather='eye' class='icon-16'></i> " . app_lang("view"),
            ["class" => "btn btn-default btn-sm", "title" => app_lang("view"), "target" => "_blank"]
        );
        $requester_name = trim(($row->requester_first_name ?? '') . ' ' . ($row->requester_last_name ?? ''));
        if ($requester_name === '') {
            $requester_name = $row->requester_name ?? '-';
        }
        return [
            $row->reference ?? "-",
            $row->company_name ?? "-",
            $row->department_name ?? "-",
            $requester_name,
            ($row->requester_phone ?? '') ?: '-',
            $row->purpose_name ?? "-",
            $row->visit_from ? format_to_datetime($row->visit_from) : "-",
            $row->visit_to ? format_to_datetime($row->visit_to) : "-",
            gate_pass_request_status_display($row),
            $row->stage ?? "-",
            $view_btn,
        ];
    }

}
