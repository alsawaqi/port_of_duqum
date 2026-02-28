<?php

namespace App\Controllers;

use App\Models\Vendor_groups_model;

class Vendor_groups extends Security_Controller
{

    protected $Vendor_groups_model;

    function __construct()
    {
        parent::__construct();
        $this->access_only_team_members();
        $this->Vendor_groups_model = new Vendor_groups_model();
    }

    function index()
    {
        $this->access_only_vendor_groups_view();

        $view_data = [
            "can_create_vendor_groups" => $this->can_create_vendor_groups(),
            "can_update_vendor_groups" => $this->can_update_vendor_groups(),
            "can_delete_vendor_groups" => $this->can_delete_vendor_groups()
        ];

        return $this->template->rander("vendor_groups/index", $view_data);
    }

    function modal_form()
    {
        $this->validate_submitted_data(array("id" => "numeric"));
        $id = $this->request->getPost("id");
        if ($id) {
            $this->access_only_vendor_groups_update();
        } else {
            $this->access_only_vendor_groups_create();
        }
        $view_data["model_info"] = $this->Vendor_groups_model->get_one($id);
        return $this->template->view("vendor_groups/modal_form", $view_data);
    }

    function save()
    {
        $this->validate_submitted_data(array(
            "id" => "numeric",
            "name" => "required",
            "code" => "required"
        ));

        $id = $this->request->getPost("id");
        if ($id) {
            $this->access_only_vendor_groups_update();
        } else {
            $this->access_only_vendor_groups_create();
        }

        $data = array(
            "name" => $this->request->getPost("name"),
            "code" => strtoupper(trim($this->request->getPost("code"))),
            "requires_riyada" => $this->request->getPost("requires_riyada") ? 1 : 0,
            "default_validity_days" => (int)$this->request->getPost("default_validity_days"),
            "is_active" => $this->request->getPost("is_active") ? 1 : 0
        );

        $data = clean_data($data);
        $save_id = $this->Vendor_groups_model->ci_save($data, $id);

        if ($save_id) {
            echo json_encode(array(
                "success" => true,
                "data" => $this->_row_data($save_id),
                "id" => $save_id,
                "message" => app_lang("record_saved")
            ));
        } else {
            echo json_encode(array("success" => false, "message" => app_lang("error_occurred")));
        }
    }

    function list_data()
    {
        $this->access_only_vendor_groups_view();

        $list_data = $this->Vendor_groups_model->get_details()->getResult();
        $result = array();

        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data);
        }

        echo json_encode(array("data" => $result));
    }

    function delete()
    {
        $this->access_only_vendor_groups_delete();

        $this->validate_submitted_data(array("id" => "required|numeric"));
        $id = $this->request->getPost("id");

        if ($this->request->getPost("undo")) {
            if ($this->Vendor_groups_model->delete($id, true)) {
                echo json_encode(array("success" => true, "data" => $this->_row_data($id), "message" => app_lang("record_undone")));
            } else {
                echo json_encode(array("success" => false, "message" => app_lang("error_occurred")));
            }
        } else {
            if ($this->Vendor_groups_model->delete($id)) {
                echo json_encode(array("success" => true, "message" => app_lang("record_deleted")));
            } else {
                echo json_encode(array("success" => false, "message" => app_lang("record_cannot_be_deleted")));
            }
        }
    }

    private function _row_data($id)
    {
        $data = $this->Vendor_groups_model->get_details(array("id" => $id))->getRow();
        return $this->_make_row($data);
    }

    private function _make_row($data)
    {
        $status = $data->is_active
            ? "<span class='badge bg-success'>" . app_lang("active") . "</span>"
            : "<span class='badge bg-secondary'>" . app_lang("inactive") . "</span>";

        $riyada = $data->requires_riyada
            ? "<span class='badge bg-info'>Yes</span>"
            : "<span class='badge bg-light text-dark'>No</span>";

        $can_update = $this->can_update_vendor_groups();
        $can_delete = $this->can_delete_vendor_groups();
        $actions = "";
        if ($can_update || $can_delete) {
            $edit_action = "";
            $delete_action = "";

            if ($can_update) {
                $edit_action = modal_anchor(get_uri("vendor_groups/modal_form"), "<i data-feather='edit' class='icon-16'></i>", array("class" => "edit", "title" => app_lang("edit"), "data-post-id" => $data->id));
            }

            if ($can_delete) {
                $delete_action = js_anchor("<i data-feather='x' class='icon-16'></i>", array("title" => app_lang("delete"), "class" => "delete", "data-id" => $data->id, "data-action-url" => get_uri("vendor_groups/delete"), "data-action" => "delete"));
            }

            $actions = $edit_action . $delete_action;
        }

        return array(
            $data->name,
            $data->code,
            $riyada,
            $data->default_validity_days,
            $status,
            $actions
        );
    }
}
