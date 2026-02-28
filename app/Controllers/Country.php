<?php

namespace App\Controllers;

use App\Models\Country_model;

class Country extends Security_Controller
{
    protected $Country_model;

    public function __construct()
    {
        parent::__construct();
        // At least view permission to enter Countries module
        $this->access_only_countries_view();
        $this->Country_model = new Country_model();
    }

    public function index()
    {
        $this->access_only_countries_view();
    
        $view_data = [];
        $view_data["can_create_countries"] = $this->can_create_countries();
        $view_data["can_update_countries"] = $this->can_update_countries();
        $view_data["can_delete_countries"] = $this->can_delete_countries();
    
        return $this->template->rander("country/index", $view_data);
    }
    

    public function modal_form()
    {
        $this->validate_submitted_data([
            "id" => "numeric"
        ]);

        $id = $this->request->getPost("id");

        // If editing -> need update permission, else create permission
        if ($id) {
            $this->access_only_countries_update();
        } else {
            $this->access_only_countries_create();
        }

        $view_data["model_info"] = $this->Country_model->get_one($id);
        return $this->template->view("country/modal_form", $view_data);
    }

    public function save()
    {
        $this->validate_submitted_data([
            "id" => "numeric",
            "name" => "required",
            "code" => "required"
        ]);

        $id = $this->request->getPost("id");

        if ($id) {
            $this->access_only_countries_update();
        } else {
            $this->access_only_countries_create();
        }

        $data = [
            "name" => $this->request->getPost("name"),
            "code" => $this->request->getPost("code"),
            "is_active" => $this->request->getPost("is_active") ? 1 : 0
        ];

        $save_id = $this->Country_model->ci_save($data, $id);

        if ($save_id) {
            echo json_encode([
                "success" => true,
                "data" => $this->_row_data($save_id),
                "id" => $save_id,
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
        $this->access_only_countries_delete();

        $this->validate_submitted_data([
            "id" => "required|numeric"
        ]);

        $id = $this->request->getPost("id");

        if ($this->request->getPost("undo")) {
            if ($this->Country_model->delete($id, true)) {
                echo json_encode([
                    "success" => true,
                    "data" => $this->_row_data($id),
                    "message" => app_lang("record_undone")
                ]);
            } else {
                echo json_encode(["success" => false, "message" => app_lang("error_occurred")]);
            }
        } else {
            if ($this->Country_model->delete($id)) {
                echo json_encode(["success" => true, "message" => app_lang("record_deleted")]);
            } else {
                echo json_encode(["success" => false, "message" => app_lang("record_cannot_be_deleted")]);
            }
        }
    }

    public function list_data()
    {
        $this->access_only_countries_view();

        $list_data = $this->Country_model->get_details()->getResult();
        $result = [];

        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data);
        }

        echo json_encode(["data" => $result]);
    }

    private function _row_data($id)
    {
        $options = ["id" => $id];
        $data = $this->Country_model->get_details($options)->getRow();
        return $this->_make_row($data);
    }

    private function _make_row($data)
    {
        $can_update = $this->can_update_countries();
        $can_delete = $this->can_delete_countries();

        $options = "";

        if ($can_update) {
            $options .= modal_anchor(
                get_uri("country/modal_form"),
                "<i data-feather='edit' class='icon-16'></i>",
                ["class" => "edit", "title" => app_lang("edit_country"), "data-post-id" => $data->id]
            );
        }

        if ($can_delete) {
            $options .= js_anchor(
                "<i data-feather='x' class='icon-16'></i>",
                [
                    "title" => app_lang("delete_country"),
                    "class" => "delete",
                    "data-id" => $data->id,
                    "data-action-url" => get_uri("country/delete"),
                    "data-action" => "delete-confirmation"
                ]
            );
        }

        return [
            $data->name,
            $data->code,
            $data->is_active ? app_lang("active") : app_lang("inactive"),
            $options
        ];
    }
}
