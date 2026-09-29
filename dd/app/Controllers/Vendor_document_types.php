<?php

namespace App\Controllers;

use App\Models\Vendor_document_types_model;
use App\Models\Vendor_groups_model;

class Vendor_document_types extends Security_Controller
{
    protected $Vendor_document_types_model;
    protected $Vendor_groups_model;

    function __construct()
    {
        parent::__construct();

        $this->access_only_team_members();

        $this->Vendor_document_types_model = new Vendor_document_types_model();
        $this->Vendor_groups_model         = new Vendor_groups_model();
    }

    function index()
    {
        $this->access_only_vendor_document_types_view();

        $view_data = [
            "can_create_vendor_document_types" => $this->can_create_vendor_document_types(),
            "can_update_vendor_document_types" => $this->can_update_vendor_document_types(),
            "can_delete_vendor_document_types" => $this->can_delete_vendor_document_types()
        ];

        return $this->template->rander("vendor_document_types/index", $view_data);
    }

    function modal_form()
    {
        $this->validate_submitted_data(["id" => "numeric"]);

        $id = $this->request->getPost("id");
        if ($id) {
            $this->access_only_vendor_document_types_update();
        } else {
            $this->access_only_vendor_document_types_create();
        }
        $view_data["model_info"] = $this->Vendor_document_types_model->get_one($id);

        // dropdown: allow NULL (all groups)
        $groups = $this->Vendor_groups_model->get_dropdown_list(
            ["name"],
            "id",
            ["deleted" => 0]
        );

        $view_data["vendor_groups_dropdown"] = ["" => "- " . app_lang("all_vendor_groups") . " -"] + $groups;

        return $this->template->view("vendor_document_types/modal_form", $view_data);
    }

    function save()
    {
        $this->validate_submitted_data([
            "id"              => "numeric",
            "name"            => "required",
            "code"            => "required",
            "vendor_group_id" => "numeric"
        ]);

        $id = $this->request->getPost("id");
        if ($id) {
            $this->access_only_vendor_document_types_update();
        } else {
            $this->access_only_vendor_document_types_create();
        }

        $vendor_group_id = $this->request->getPost("vendor_group_id");

        $data = [
            "name"            => $this->request->getPost("name"),
            "code"            => strtoupper(trim($this->request->getPost("code"))),
            "vendor_group_id" => $vendor_group_id ? (int)$vendor_group_id : null,
            "is_required"     => $this->request->getPost("is_required") ? 1 : 0,
            "is_active"       => $this->request->getPost("is_active") ? 1 : 0
        ];

        $data = clean_data($data);

        $save_id = $this->Vendor_document_types_model->ci_save($data, $id);

        if ($save_id) {
            echo json_encode([
                "success" => true,
                "data"    => $this->_row_data($save_id),
                "id"      => $save_id,
                "message" => app_lang("record_saved")
            ]);
        } else {
            echo json_encode([
                "success" => false,
                "message" => app_lang("error_occurred")
            ]);
        }
    }

    function list_data()
    {
        $this->access_only_vendor_document_types_view();

        $list_data = $this->Vendor_document_types_model->get_details()->getResult();
        $result = [];

        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data);
        }

        echo json_encode(["data" => $result]);
    }

    function delete()
    {
        $this->access_only_vendor_document_types_delete();

        $this->validate_submitted_data(["id" => "required|numeric"]);
        $id = $this->request->getPost("id");

        if ($this->request->getPost("undo")) {
            if ($this->Vendor_document_types_model->delete($id, true)) {
                echo json_encode([
                    "success" => true,
                    "data"    => $this->_row_data($id),
                    "message" => app_lang("record_undone")
                ]);
            } else {
                echo json_encode(["success" => false, "message" => app_lang("error_occurred")]);
            }
        } else {
            if ($this->Vendor_document_types_model->delete($id)) {
                echo json_encode(["success" => true, "message" => app_lang("record_deleted")]);
            } else {
                echo json_encode(["success" => false, "message" => app_lang("record_cannot_be_deleted")]);
            }
        }
    }

    private function _row_data($id)
    {
        $data = $this->Vendor_document_types_model->get_details(["id" => $id])->getRow();
        return $this->_make_row($data);
    }

    private function _make_row($data)
    {
        $status = $data->is_active
            ? "<span class='badge bg-success'>" . app_lang("active") . "</span>"
            : "<span class='badge bg-secondary'>" . app_lang("inactive") . "</span>";

        $required = $data->is_required
            ? "<span class='badge bg-danger'>" . app_lang("required") . "</span>"
            : "<span class='badge bg-light text-dark'>" . app_lang("optional") . "</span>";

        $group_name = $data->vendor_group_name ?: app_lang("all_vendor_groups");

        $can_update = $this->can_update_vendor_document_types();
        $can_delete = $this->can_delete_vendor_document_types();
        $actions = "";
        if ($can_update || $can_delete) {
            $edit_action = "";
            $delete_action = "";

            if ($can_update) {
                $edit_action = modal_anchor(get_uri("vendor_document_types/modal_form"), "<i data-feather='edit' class='icon-16'></i>", [
                    "class" => "edit",
                    "title" => app_lang("edit"),
                    "data-post-id" => $data->id
                ]);
            }

            if ($can_delete) {
                $delete_action = js_anchor("<i data-feather='x' class='icon-16'></i>", [
                    "title" => app_lang("delete"),
                    "class" => "delete",
                    "data-id" => $data->id,
                    "data-action-url" => get_uri("vendor_document_types/delete"),
                    "data-action" => "delete"
                ]);
            }

            $actions = $edit_action . $delete_action;
        }

        return [
            $group_name,
            $data->name,
            $data->code,
            $required,
            $status,
            $actions
        ];
    }
}
