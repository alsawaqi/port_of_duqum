<?php

namespace App\Controllers;

use App\Models\Regions_model;
use App\Models\Country_model;

class Regions extends Security_Controller
{
    protected $Regions_model;
    protected $Country_model;

    function __construct()
    {
        parent::__construct();

        // staff/admin area
        $this->access_only_team_members();

        $this->Regions_model = new Regions_model();
        $this->Country_model = new Country_model();
    }

    function index()
    {
        $this->access_only_regions_view();

        $view_data = [
            "can_create_regions" => $this->can_create_regions(),
            "can_update_regions" => $this->can_update_regions(),
            "can_delete_regions" => $this->can_delete_regions()
        ];

        return $this->template->rander("regions/index", $view_data);
    }

    function list_data()
    {
        $this->access_only_regions_view();

        $list_data = $this->Regions_model->get_details()->getResult();
        $result = array();

        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data);
        }

        echo json_encode(array("data" => $result));
    }

    function modal_form()
    {
        $this->validate_submitted_data(array("id" => "numeric"));

        $id = $this->request->getPost('id');
        if ($id) {
            $this->access_only_regions_update();
        } else {
            $this->access_only_regions_create();
        }
        $view_data["model_info"] = $this->Regions_model->get_one($id);

        // dropdown data for <select>
        $view_data["countries_dropdown"] = $this->Country_model->get_dropdown_list(
            array("name"),
            "id",
            array("deleted" => 0)
        );

        return $this->template->view("regions/modal_form", $view_data);
    }

    function save()
    {
        $this->validate_submitted_data(array(
            "id" => "numeric",
            "country_id" => "required|numeric",
            "name" => "required",
            "code" => "required"
        ));

        $id = $this->request->getPost('id');
        if ($id) {
            $this->access_only_regions_update();
        } else {
            $this->access_only_regions_create();
        }

        $data = array(
            "country_id" => $this->request->getPost('country_id'),
            "name" => $this->request->getPost('name'),
            "code" => strtoupper(trim($this->request->getPost('code'))),
            "is_active" => $this->request->getPost('is_active') ? 1 : 0
        );

        $data = clean_data($data);
        $save_id = $this->Regions_model->ci_save($data, $id);

        if ($save_id) {
            echo json_encode(array(
                "success" => true,
                "data" => $this->_row_data($save_id),
                "id" => $save_id,
                "message" => app_lang('record_saved')
            ));
        } else {
            echo json_encode(array("success" => false, "message" => app_lang('error_occurred')));
        }
    }

    function delete()
    {
        $this->access_only_regions_delete();

        $this->validate_submitted_data(array(
            "id" => "required|numeric"
        ));

        $id = $this->request->getPost('id');

        if ($this->request->getPost('undo')) {
            if ($this->Regions_model->delete($id, true)) {
                echo json_encode(array("success" => true, "data" => $this->_row_data($id), "message" => app_lang('record_undone')));
            } else {
                echo json_encode(array("success" => false, "message" => app_lang('error_occurred')));
            }
        } else {
            if ($this->Regions_model->delete($id)) {
                echo json_encode(array("success" => true, "message" => app_lang('record_deleted')));
            } else {
                echo json_encode(array("success" => false, "message" => app_lang('record_cannot_be_deleted')));
            }
        }
    }

    private function _row_data($id)
    {
        $options = array("id" => $id);
        $data = $this->Regions_model->get_details($options)->getRow();
        return $this->_make_row($data);
    }

    private function _make_row($data)
    {
        $status = $data->is_active
            ? "<span class='badge bg-success'>" . app_lang("active") . "</span>"
            : "<span class='badge bg-secondary'>" . app_lang("inactive") . "</span>";

        $actions = "";

        $can_update = $this->can_update_regions();
        $can_delete = $this->can_delete_regions();

        if ($can_update || $can_delete) {
            $edit_action = "";
            $delete_action = "";

            if ($can_update) {
                $edit_action = modal_anchor(get_uri("regions/modal_form"), "<i data-feather='edit' class='icon-16'></i>",
                    array("class" => "edit", "title" => app_lang('edit'), "data-post-id" => $data->id));
            }

            if ($can_delete) {
                $delete_action = js_anchor("<i data-feather='x' class='icon-16'></i>",
                    array("title" => app_lang('delete'), "class" => "delete", "data-id" => $data->id, "data-action-url" => get_uri("regions/delete"), "data-action" => "delete"));
            }

            $actions = $edit_action . $delete_action;
        }

        return array(
            $data->country_name,
            $data->name,
            $data->code,
            $status,
            $actions
        );
    }
}
