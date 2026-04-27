<?php

namespace App\Controllers;

use App\Models\Gate_pass_department_users_model;
use App\Models\Gate_pass_companies_model;
use App\Models\Gate_pass_departments_model;

class Gate_pass_department_users extends Security_Controller
{
    protected $Gate_pass_department_users_model;
    protected $Gate_pass_companies_model;
    protected $Gate_pass_departments_model;

    public function __construct()
    {
        parent::__construct();
        $this->access_only_team_members();
        $this->Gate_pass_department_users_model = new Gate_pass_department_users_model();
        $this->Gate_pass_companies_model = new Gate_pass_companies_model();
        $this->Gate_pass_departments_model = new Gate_pass_departments_model();

        $this->db = db_connect();
    }

    public function index()
    {
        $this->access_only_gate_pass("department_users", "view");
        return $this->template->rander("gate_pass_department_users/index");
    }

    public function modal_form()
    {
        $this->validate_submitted_data(["id" => "numeric"]);
        $id = $this->request->getPost("id");
        $this->access_only_gate_pass("department_users", $id ? "update" : "create");

        $model_info = null;
        if ($id) {
            $result = $this->Gate_pass_department_users_model->get_details(["id" => $id])->getRow();
            $model_info = $result;
        }

        $company_dropdown = ["0" => "- " . app_lang("select_company") . " -"];
        $companies = $this->Gate_pass_companies_model->get_details()->getResult();
        foreach ($companies as $c) {
            $company_dropdown[$c->id] = $c->name;
        }

        $department_dropdown = ["0" => "- " . app_lang("select_department") . " -"];
        if ($model_info && (int) $model_info->company_id) {
            $departments = $this->Gate_pass_departments_model->get_details(["company_id" => $model_info->company_id])->getResult();
            foreach ($departments as $d) {
                $department_dropdown[$d->id] = $d->name;
            }
        }

        $view_data = [
            "model_info" => $model_info,
            "company_dropdown" => $company_dropdown,
            "department_dropdown" => $department_dropdown
        ];

        return $this->template->view("gate_pass_department_users/modal_form", $view_data);
    }

    public function departments_by_company($company_id = 0)
    {
        $this->access_only_gate_pass("department_users", "view");
        $company_id = (int) $company_id;

        $options = ["results" => []];
        if (!$company_id) {
            return $this->response->setJSON($options);
        }

        $departments = $this->Gate_pass_departments_model->get_details(["company_id" => $company_id])->getResult();
        foreach ($departments as $d) {
            $options["results"][] = ["id" => $d->id, "text" => $d->name];
        }

        return $this->response->setJSON($options);
    }

    public function list_data()
    {
        $this->access_only_gate_pass("department_users", "view");
        $query_result = $this->Gate_pass_department_users_model->get_details();
        
        if (!$query_result) {
            return $this->response->setJSON(["data" => []]);
        }
        
        $list_data = $query_result->getResult();
        $result = [];

        foreach ($list_data as $data) {
            // Skip rows without id - this is critical for DataTables
            if (empty($data->id) || !isset($data->id)) {
                continue;
            }

            try {
                $row = $this->_make_row($data);

                // Ensure row is valid array with exactly 7 elements
                if (!is_array($row) || count($row) !== 7) {
                    continue;
                }

                // Ensure all elements are strings (not null, not empty array, etc.)
                $row = array_map(function($item) {
                    if ($item === null || $item === false) {
                        return "-";
                    }
                    if (is_array($item) || is_object($item)) {
                        return "-";
                    }
                    $str = (string)$item;
                    return $str === "" ? "-" : $str;
                }, $row);

                // Double-check we have exactly 7 elements
                if (count($row) !== 7) {
                    continue;
                }

                $result[] = array_values($row); // force numeric indexes 0..6
            } catch (\Exception $e) {
                // Skip any row that causes an error
                continue;
            }
        }

        return $this->response->setJSON(["data" => $result]);
    }

    private function _make_row($data)
    {
        // Helper function to safely get object property
        $getProp = function($obj, $prop, $default = "-") {
            return (isset($obj->$prop) && $obj->$prop !== null && trim((string)$obj->$prop) !== "") ? trim((string)$obj->$prop) : $default;
        };

        // Validate id exists and is numeric
        if (empty($data->id) || !isset($data->id) || !is_numeric($data->id)) {
            return ["-", "-", "-", "-", "-", "-", "-"];
        }

        $first_name = $getProp($data, 'first_name', '');
        $last_name = $getProp($data, 'last_name', '');
        $name = trim($first_name . " " . $last_name);
        if (empty($name)) {
            $name = "-";
        }

        $options = "";
        if (!empty($data->id) && is_numeric($data->id)) {
            try {
                $edit_link = modal_anchor(
                    get_uri("gate_pass_department_users/modal_form"),
                    "<i data-feather='edit' class='icon-16'></i>",
                    ["class" => "edit", "title" => app_lang('edit'), "data-post-id" => $data->id]
                );
                if ($edit_link) {
                    $options .= $edit_link;
                }

                $delete_link = js_anchor(
                    "<i data-feather='x' class='icon-16'></i>",
                    ["title" => app_lang("delete"), "class" => "delete", "data-id" => $data->id, "data-action-url" => get_uri("gate_pass_department_users/delete"), "data-action" => "delete-confirmation"]
                );
                if ($delete_link) {
                    $options .= $delete_link;
                }
            } catch (\Exception $e) {
                $options = "-";
            }
        }

        if (empty($options) || trim($options) === "") {
            $options = "-";
        }

        // Get values with proper fallbacks
        $company_name = $getProp($data, 'company_name', '-');
        $department_name = $getProp($data, 'department_name', '-');
        $email = $getProp($data, 'email', '-');
        $phone = $getProp($data, 'phone', '-');
        $status_val = $getProp($data, 'status', 'inactive');
        $status = $status_val !== '-' ? ucfirst($status_val) : 'Inactive';

        // Return exactly 7 elements, all as strings, ensuring no null/empty values
        $row = [
            (string)$company_name,
            (string)$department_name,
            (string)$name,
            (string)$email,
            (string)$phone,
            (string)$status,
            (string)$options
        ];

        // Final validation - ensure no element is empty or null
        return array_map(function($item) {
            $str = (string)$item;
            return $str === "" || $str === null ? "-" : $str;
        }, $row);
    }


    public function save()
    {
        $this->validate_submitted_data([
            "id" => "numeric",
            "company_id" => "required|numeric",
            "department_id" => "required|numeric",
            "email" => "required|valid_email",
            "status" => "required"
        ]);

        $id = (int) $this->request->getPost("id");
        $this->access_only_gate_pass("department_users", $id ? "update" : "create");
        $company_id = (int) $this->request->getPost("company_id");
        $department_id = (int) $this->request->getPost("department_id");

        // Validate department belongs to company
        $dept = $this->Gate_pass_departments_model->get_one($department_id);
        if (!$dept || (int) $dept->company_id !== $company_id) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("invalid_department")]);
        }

        return $this->save_operational_user_assignment(
            $this->Gate_pass_department_users_model,
            "gate_pass_department_users",
            ["company_id" => $company_id, "department_id" => $department_id],
            ["job_title" => "Gate Pass Department User"]
        );
    }

    public function delete()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);
        $this->access_only_gate_pass("department_users", "delete");
        $id = (int) $this->request->getPost("id");

        $pivot = $this->Gate_pass_department_users_model->get_one($id);
        if (!$pivot || (int) $pivot->deleted) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("record_not_found")]);
        }

        $this->db->transStart();

        $this->Gate_pass_department_users_model->delete($id);

        $this->db->transComplete();

        return $this->response->setJSON(["success" => true, "message" => app_lang("record_deleted")]);
    }
}
