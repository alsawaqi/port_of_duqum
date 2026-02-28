<?php

namespace App\Controllers;

use App\Models\Legal_types_model;

class Legal_types extends Security_Controller
{

    protected $Legal_types_model;

    function __construct()
    {
        parent::__construct();


        $this->access_only_team_members();

        $this->Legal_types_model = new Legal_types_model();
    }

    // load list page
    function index()
    {
        $this->access_only_legal_types_view();

        $view_data = [
            "can_create_legal_types" => $this->can_create_legal_types(),
            "can_update_legal_types" => $this->can_update_legal_types(),
            "can_delete_legal_types" => $this->can_delete_legal_types()
        ];

        return $this->template->rander("legal_types/index", $view_data);
    }

    // modal for add/edit
    function modal_form()
    {
        $this->validate_submitted_data(array(
            "id" => "numeric"
        ));

        $id = $this->request->getPost('id');
        if ($id) {
            $this->access_only_legal_types_update();
        } else {
            $this->access_only_legal_types_create();
        }
        $view_data["model_info"] = $this->Legal_types_model->get_one($id);

        return $this->template->view("legal_types/modal_form", $view_data);
    }

    // save (insert/update)
    function save()
    {
        $this->validate_submitted_data(array(
            "id" => "numeric",
            "name" => "required",
            "code" => "required"
        ));

        $id = $this->request->getPost('id');
        if ($id) {
            $this->access_only_legal_types_update();
        } else {
            $this->access_only_legal_types_create();
        }

        $data = array(
            "name" => $this->request->getPost('name'),
            "code" => strtoupper(trim($this->request->getPost('code'))),
            "is_active" => $this->request->getPost('is_active') ? 1 : 0
        );

        $data = clean_data($data);

        $save_id = $this->Legal_types_model->ci_save($data, $id);

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

    // list data for datatable
    function list_data()
    {
        $this->access_only_legal_types_view();

        $list_data = $this->Legal_types_model->get_details()->getResult();
        $result = array();

        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data);
        }

        echo json_encode(array("data" => $result));
    }

    // delete / undo
    function delete()
    {
        $this->access_only_legal_types_delete();

        $this->validate_submitted_data(array(
            "id" => "required|numeric"
        ));

        $id = $this->request->getPost('id');

        if ($this->request->getPost('undo')) {
            if ($this->Legal_types_model->delete($id, true)) {
                echo json_encode(array("success" => true, "data" => $this->_row_data($id), "message" => app_lang('record_undone')));
            } else {
                echo json_encode(array("success" => false, "message" => app_lang('error_occurred')));
            }
        } else {
            if ($this->Legal_types_model->delete($id)) {
                echo json_encode(array("success" => true, "message" => app_lang('record_deleted')));
            } else {
                echo json_encode(array("success" => false, "message" => app_lang('record_cannot_be_deleted')));
            }
        }
    }

    private function _row_data($id)
    {
        $options = array("id" => $id);
        $data = $this->Legal_types_model->get_details($options)->getRow();
        return $this->_make_row($data);
    }

    private function _make_row($data)
    {
        $status = $data->is_active ? "<span class='badge bg-success'>" . app_lang("active") . "</span>"
            : "<span class='badge bg-secondary'>" . app_lang("inactive") . "</span>";

        $can_update = $this->can_update_legal_types();
        $can_delete = $this->can_delete_legal_types();
        $actions = "";
        if ($can_update || $can_delete) {
            $edit_action = "";
            $delete_action = "";

            if ($can_update) {
                $edit_action = modal_anchor(get_uri("legal_types/modal_form"), "<i data-feather='edit' class='icon-16'></i>",
                    array("class" => "edit", "title" => app_lang('edit'), "data-post-id" => $data->id));
            }

            if ($can_delete) {
                $delete_action = js_anchor("<i data-feather='x' class='icon-16'></i>",
                    array("title" => app_lang('delete'), "class" => "delete", "data-id" => $data->id, "data-action-url" => get_uri("legal_types/delete"), "data-action" => "delete"));
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
