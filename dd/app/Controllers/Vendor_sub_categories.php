<?php

namespace App\Controllers;

use App\Models\Vendor_sub_categories_model;
use App\Models\Vendor_categories_model;

class Vendor_sub_categories extends Security_Controller
{
    protected $Vendor_sub_categories_model;
    protected $Vendor_categories_model;

    public function __construct()
    {
        parent::__construct();

        $this->access_only_team_members();

        $this->Vendor_sub_categories_model = new Vendor_sub_categories_model();
        $this->Vendor_categories_model     = new Vendor_categories_model();
    }

    public function index()
    {
        $this->access_only_vendor_sub_categories_view();

        $view_data = [
            "can_create_vendor_sub_categories" => $this->can_create_vendor_sub_categories(),
            "can_update_vendor_sub_categories" => $this->can_update_vendor_sub_categories(),
            "can_delete_vendor_sub_categories" => $this->can_delete_vendor_sub_categories()
        ];

        return $this->template->rander("vendor_sub_categories/index", $view_data);
    }

    private function _get_categories_dropdown()
    {
        $list = $this->Vendor_categories_model
            ->get_details(["deleted" => 0])
            ->getResult();

        $dropdown = [" " => "- " . app_lang("select_vendor_category") . " -"];

        foreach ($list as $row) {
            $label = $row->name;
            if (!empty($row->code)) {
                $label .= " ({$row->code})";
            }
            $dropdown[$row->id] = $label;
        }

        return $dropdown;
    }

    public function modal_form()
    {
        $this->validate_submitted_data([
            "id" => "numeric"
        ]);

        $id = $this->request->getPost("id");
        if ($id) {
            $this->access_only_vendor_sub_categories_update();
        } else {
            $this->access_only_vendor_sub_categories_create();
        }

        $model_info = $this->Vendor_sub_categories_model->get_one($id);

        $view_data              = [];
        $view_data["model_info"] = $model_info;
        $view_data["vendor_categories_dropdown"] = $this->_get_categories_dropdown();

        return $this->template->view("vendor_sub_categories/modal_form", $view_data);
    }

    public function save()
    {
        $this->validate_submitted_data([
            "id"                => "numeric",
            "vendor_category_id" => "required|numeric",
            "name"              => "required"
        ]);

        $id = $this->request->getPost("id");
        if ($id) {
            $this->access_only_vendor_sub_categories_update();
        } else {
            $this->access_only_vendor_sub_categories_create();
        }

        $data = [
            "vendor_category_id" => $this->request->getPost("vendor_category_id"),
            "name"               => $this->request->getPost("name"),
            "code"               => $this->request->getPost("code"),
            "is_active"          => $this->request->getPost("is_active") ? 1 : 0
        ];

        $data = clean_data($data);

        $save_id = $this->Vendor_sub_categories_model->ci_save($data, $id);
        if ($save_id) {
            echo json_encode([
                "success" => true,
                "id"      => $save_id,
                "data"    => $this->_row_data($save_id),
                "message" => app_lang("record_saved")
            ]);
        } else {
            echo json_encode([
                "success" => false,
                "message" => app_lang("error_occurred")
            ]);
        }
    }

    public function delete()
    {
        $this->access_only_vendor_sub_categories_delete();

        $this->validate_submitted_data([
            "id" => "required|numeric"
        ]);

        $id = $this->request->getPost("id");

        if ($this->request->getPost("undo")) {
            if ($this->Vendor_sub_categories_model->delete($id, true)) {
                echo json_encode([
                    "success" => true,
                    "data"    => $this->_row_data($id),
                    "message" => app_lang("record_undone")
                ]);
            } else {
                echo json_encode([
                    "success" => false,
                    "message" => app_lang("error_occurred")
                ]);
            }
        } else {
            if ($this->Vendor_sub_categories_model->delete($id)) {
                echo json_encode([
                    "success" => true,
                    "message" => app_lang("record_deleted")
                ]);
            } else {
                echo json_encode([
                    "success" => false,
                    "message" => app_lang("record_cannot_be_deleted")
                ]);
            }
        }
    }

    public function list_data()
    {
        $this->access_only_vendor_sub_categories_view();

        $list = $this->Vendor_sub_categories_model
            ->get_details()
            ->getResult();

        $result = [];
        foreach ($list as $data) {
            $result[] = $this->_make_row($data);
        }

        echo json_encode(["data" => $result]);
    }

    private function _row_data($id)
    {
        $data = $this->Vendor_sub_categories_model->get_details(["id" => $id])->getRow();
        return $this->_make_row($data);
    }

    private function _make_row($data)
    {
        $status = $data->is_active
            ? "<span class='badge bg-success'>" . app_lang("active") . "</span>"
            : "<span class='badge bg-danger'>" . app_lang("inactive") . "</span>";

        $can_update = $this->can_update_vendor_sub_categories();
        $can_delete = $this->can_delete_vendor_sub_categories();
        $actions = "";
        if ($can_update || $can_delete) {
            $edit_action = "";
            $delete_action = "";

            if ($can_update) {
                $edit_action = modal_anchor(
                    get_uri("vendor_sub_categories/modal_form"),
                    "<i data-feather='edit' class='icon-16'></i>",
                    [
                        "title"       => app_lang("edit"),
                        "data-post-id" => $data->id,
                        "class"       => "edit"
                    ]
                );
            }

            if ($can_delete) {
                $delete_action = js_anchor(
                    "<i data-feather='x' class='icon-16'></i>",
                    [
                        "title"          => app_lang("delete"),
                        "class"          => "delete",
                        "data-id"        => $data->id,
                        "data-action-url" => get_uri("vendor_sub_categories/delete"),
                        "data-action"    => "delete"
                    ]
                );
            }

            $actions = $edit_action . $delete_action;
        }

        return [
            esc($data->category_name),      // from join in model
            esc($data->name),
            esc($data->code),
            $status,
            $actions
        ];
    }
}
