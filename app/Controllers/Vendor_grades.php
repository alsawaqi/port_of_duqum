<?php

namespace App\Controllers;

use App\Models\Vendor_grades_model;

class Vendor_grades extends Security_Controller
{
    protected $db;
    protected $Vendor_grades_model;

    function __construct()
    {
        parent::__construct();
        $this->access_only_team_members();
        $this->db = db_connect();
        $this->Vendor_grades_model = new Vendor_grades_model();
    }

    function index()
    {
        $this->access_only_vendor_grades_view();

        $view_data = [
            "can_create_vendor_grades" => $this->can_create_vendor_grades(),
            "can_update_vendor_grades" => $this->can_update_vendor_grades(),
            "can_delete_vendor_grades" => $this->can_delete_vendor_grades()
        ];

        return $this->template->rander("vendor_grades/index", $view_data);
    }

    function modal_form()
    {
        $this->validate_submitted_data(["id" => "numeric"]);
        $id = $this->request->getPost("id");

        if ($id) {
            $this->access_only_vendor_grades_update();
        } else {
            $this->access_only_vendor_grades_create();
        }

        $view_data["model_info"] = $this->Vendor_grades_model->get_one($id);
        return $this->template->view("vendor_grades/modal_form", $view_data);
    }

    function save()
    {
        $this->validate_submitted_data([
            "id" => "numeric",
            "name" => "required",
            "code" => "required"
        ]);

        $id = $this->request->getPost("id");
        if ($id) {
            $this->access_only_vendor_grades_update();
        } else {
            $this->access_only_vendor_grades_create();
        }

        $code = strtoupper(trim((string)$this->request->getPost("code")));
        $existing = $this->db->table($this->db->prefixTable("vendor_grades"))
            ->select("id")
            ->where("deleted", 0)
            ->where("code", $code);

        if ($id) {
            $existing->where("id !=", (int)$id);
        }

        if ($existing->get(1)->getRow()) {
            echo json_encode(["success" => false, "message" => app_lang("vendor_grade_code_exists")]);
            return;
        }

        $data = [
            "name" => $this->request->getPost("name"),
            "code" => $code,
            "description" => $this->request->getPost("description"),
            "sort" => (int)$this->request->getPost("sort"),
            "is_active" => $this->request->getPost("is_active") ? 1 : 0
        ];

        $save_id = $this->Vendor_grades_model->ci_save(clean_data($data), $id);

        if ($save_id) {
            echo json_encode([
                "success" => true,
                "data" => $this->_row_data($save_id),
                "id" => $save_id,
                "message" => app_lang("record_saved")
            ]);
        } else {
            echo json_encode(["success" => false, "message" => app_lang("error_occurred")]);
        }
    }

    function list_data()
    {
        $this->access_only_vendor_grades_view();

        $list_data = $this->Vendor_grades_model->get_details()->getResult();
        $result = [];

        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data);
        }

        echo json_encode(["data" => $result]);
    }

    function delete()
    {
        $this->access_only_vendor_grades_delete();
        $this->validate_submitted_data(["id" => "required|numeric"]);
        $id = $this->request->getPost("id");

        if ($this->request->getPost("undo")) {
            if ($this->Vendor_grades_model->delete($id, true)) {
                echo json_encode(["success" => true, "data" => $this->_row_data($id), "message" => app_lang("record_undone")]);
            } else {
                echo json_encode(["success" => false, "message" => app_lang("error_occurred")]);
            }
        } else {
            if ($this->Vendor_grades_model->delete($id)) {
                echo json_encode(["success" => true, "message" => app_lang("record_deleted")]);
            } else {
                echo json_encode(["success" => false, "message" => app_lang("record_cannot_be_deleted")]);
            }
        }
    }

    private function _row_data($id)
    {
        $data = $this->Vendor_grades_model->get_details(["id" => $id])->getRow();
        return $this->_make_row($data);
    }

    private function _make_row($data)
    {
        $status = $data->is_active
            ? "<span class='badge bg-success'>" . app_lang("active") . "</span>"
            : "<span class='badge bg-secondary'>" . app_lang("inactive") . "</span>";

        $can_update = $this->can_update_vendor_grades();
        $can_delete = $this->can_delete_vendor_grades();

        $actions = "";
        if ($can_update) {
            $actions .= modal_anchor(get_uri("vendor_grades/modal_form"), "<i data-feather='edit' class='icon-16'></i>", [
                "class" => "edit",
                "title" => app_lang("edit"),
                "data-post-id" => $data->id
            ]);
        }

        if ($can_delete) {
            $actions .= js_anchor("<i data-feather='x' class='icon-16'></i>", [
                "title" => app_lang("delete"),
                "class" => "delete",
                "data-id" => $data->id,
                "data-action-url" => get_uri("vendor_grades/delete"),
                "data-action" => "delete"
            ]);
        }

        return [
            esc($data->code ?? "-"),
            esc($data->name ?? "-"),
            esc($data->description ?? "-"),
            (int)($data->sort ?? 0),
            $status,
            $actions
        ];
    }
}
