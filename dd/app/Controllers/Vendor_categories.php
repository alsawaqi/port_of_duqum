<?php

namespace App\Controllers;

use App\Models\Vendor_categories_model;

class Vendor_categories extends Security_Controller
{
    protected $Vendor_categories_model;

    function __construct()
    {
        parent::__construct();

        $this->access_only_team_members();

        $this->Vendor_categories_model = new Vendor_categories_model();
    }

    function index()
    {
        $this->access_only_vendor_categories_view();

        $view_data = [
            "can_create_vendor_categories" => $this->can_create_vendor_categories(),
            "can_update_vendor_categories" => $this->can_update_vendor_categories(),
            "can_delete_vendor_categories" => $this->can_delete_vendor_categories()
        ];

        return $this->template->rander("vendor_categories/index", $view_data);
    }

    function modal_form()
    {
        $this->validate_submitted_data(array(
            "id" => "numeric"
        ));

        $id = $this->request->getPost("id");
        if ($id) {
            $this->access_only_vendor_categories_update();
        } else {
            $this->access_only_vendor_categories_create();
        }
        $view_data["model_info"] = $this->Vendor_categories_model->get_one($id);

        return $this->template->view("vendor_categories/modal_form", $view_data);
    }

    function save()
    {
        $this->validate_submitted_data(array(
            "id" => "numeric",
            "name" => "required",
            "code" => "permit_empty",
            "is_active" => "numeric"
        ));

        $id = $this->request->getPost("id");
        if ($id) {
            $this->access_only_vendor_categories_update();
        } else {
            $this->access_only_vendor_categories_create();
        }

        $data = array(
            "name" => $this->request->getPost("name"),
            "code" => $this->request->getPost("code"),
            "is_active" => $this->request->getPost("is_active") ? 1 : 0,
            "updated_at" => get_current_utc_time()
        );

        if (!$id) {
            $data["created_at"] = get_current_utc_time();
        }

        $data = clean_data($data);

        $save_id = $this->Vendor_categories_model->ci_save($data, $id);

        if ($save_id) {
            echo json_encode(array(
                "success" => true,
                "data" => $this->_row_data($save_id),
                "id" => $save_id,
                "message" => app_lang("record_saved")
            ));
        } else {
            echo json_encode(array(
                "success" => false,
                "message" => app_lang("error_occurred")
            ));
        }
    }

    function delete()
    {
        $this->access_only_vendor_categories_delete();

        $this->validate_submitted_data(array(
            "id" => "required|numeric"
        ));

        $id = $this->request->getPost("id");

        if ($this->request->getPost("undo")) {
            if ($this->Vendor_categories_model->delete($id, true)) {
                echo json_encode(array(
                    "success" => true,
                    "data" => $this->_row_data($id),
                    "message" => app_lang("record_undone")
                ));
            } else {
                echo json_encode(array("success" => false, "message" => app_lang("error_occurred")));
            }
        } else {
            if ($this->Vendor_categories_model->delete($id)) {
                echo json_encode(array("success" => true, "message" => app_lang("record_deleted")));
            } else {
                echo json_encode(array("success" => false, "message" => app_lang("record_cannot_be_deleted")));
            }
        }
    }

    function list_data()
    {
        $this->access_only_vendor_categories_view();

        $list_data = $this->Vendor_categories_model->get_details()->getResult();
        $result = array();

        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data);
        }

        echo json_encode(array("data" => $result));
    }

    private function _row_data($id)
    {
        $data = $this->Vendor_categories_model->get_details(array("id" => $id))->getRow();
        return $this->_make_row($data);
    }

    private function _make_row($data)
    {
        $status = $data->is_active
            ? "<span class='badge bg-success'>" . app_lang("active") . "</span>"
            : "<span class='badge bg-danger'>" . app_lang("inactive") . "</span>";

        $can_update = $this->can_update_vendor_categories();
        $can_delete = $this->can_delete_vendor_categories();
        $actions = "";
        if ($can_update || $can_delete) {
            $edit_action = "";
            $delete_action = "";

            if ($can_update) {
                $edit_action = modal_anchor(
                    get_uri("vendor_categories/modal_form"),
                    "<i data-feather='edit' class='icon-16'></i>",
                    array(
                        "class" => "edit",
                        "title" => app_lang("edit_vendor_category"),
                        "data-post-id" => $data->id
                    )
                );
            }

            if ($can_delete) {
                $delete_action = js_anchor(
                    "<i data-feather='x' class='icon-16'></i>",
                    array(
                        "title" => app_lang("delete"),
                        "class" => "delete",
                        "data-id" => $data->id,
                        "data-action-url" => get_uri("vendor_categories/delete"),
                        "data-action" => "delete"
                    )
                );
            }

            $actions = $edit_action . $delete_action;
        }

        return array(
            $data->name,
            $data->code,
            $status,
            $actions
        );
    }
}
