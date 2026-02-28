<?php

namespace App\Controllers;

use Config\Database;
use App\Models\Cities_model;
use App\Models\Country_model;
use App\Models\Regions_model;

class Cities extends Security_Controller
{

    protected $Cities_model;
    protected $Country_model;
    protected $Regions_model;

    function __construct()
    {
        parent::__construct();

        $this->access_only_team_members();

        $this->Cities_model  = new Cities_model();
        $this->Country_model = new Country_model();
        $this->Regions_model = new Regions_model();
    }

    function index()
    {
        $this->access_only_cities_view();

        $view_data = [
            "can_create_cities" => $this->can_create_cities(),
            "can_update_cities" => $this->can_update_cities(),
            "can_delete_cities" => $this->can_delete_cities()
        ];

        return $this->template->rander("cities/index", $view_data);
    }

    function modal_form()
    {
        $this->validate_submitted_data(array("id" => "numeric"));

        $id = $this->request->getPost('id');
        if ($id) {
            $this->access_only_cities_update();
        } else {
            $this->access_only_cities_create();
        }
        $view_data["model_info"] = $this->Cities_model->get_one($id);

        // Countries dropdown (full list)
        $view_data["countries_dropdown"] = $this->Country_model->get_dropdown_list(
            array("name"),
            "id",
            array("deleted" => 0)
        );

        // Regions dropdown: if editing existing city, load the regions for that city’s country
        $regions_dropdown = array("" => "- " . app_lang("select_region") . " -");

        $selected_country_id = "";

        if ($view_data["model_info"]->regions_id) {
            $region_info = $this->Regions_model->get_one($view_data["model_info"]->regions_id);
            if ($region_info && $region_info->country_id) {
                $selected_country_id = $region_info->country_id;
            }
        }


        $view_data["selected_country_id"] = $selected_country_id;

        $view_data["regions_dropdown"] = $regions_dropdown;

        return $this->template->view("cities/modal_form", $view_data);
    }

    function save()
    {
        $this->validate_submitted_data(array(
            "id" => "numeric",
            "country_id" => "required|numeric",
            "regions_id" => "required|numeric",
            "name" => "required",
            "code" => "required"
        ));

        $id = $this->request->getPost('id');
        if ($id) {
            $this->access_only_cities_update();
        } else {
            $this->access_only_cities_create();
        }

        $data = array(
            "regions_id" => $this->request->getPost('regions_id'),
            "name" => $this->request->getPost('name'),
            "code" => strtoupper(trim($this->request->getPost('code'))),
            "is_active" => $this->request->getPost('is_active') ? 1 : 0
        );


        $country_id = (int) $this->request->getPost("country_id");
        $region_id  = (int) $this->request->getPost("regions_id");

        $region_info = $this->Regions_model->get_one($region_id);

        if (!$region_info || (int)$region_info->country_id !== $country_id) {
            echo json_encode(["success" => false, "message" => app_lang("invalid_region")]);
            return;
        }

        $data = clean_data($data);
        $save_id = $this->Cities_model->ci_save($data, $id);

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
        $this->access_only_cities_view();

        $list_data = $this->Cities_model->get_details()->getResult();
        $result = array();

        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data);
        }

        echo json_encode(array("data" => $result));
    }

    function delete()
    {
        $this->access_only_cities_delete();

        $this->validate_submitted_data(array("id" => "required|numeric"));

        $id = $this->request->getPost('id');

        if ($this->request->getPost("undo")) {
            if ($this->Cities_model->delete($id, true)) {
                echo json_encode(array("success" => true, "data" => $this->_row_data($id), "message" => app_lang("record_undone")));
            } else {
                echo json_encode(array("success" => false, "message" => app_lang("error_occurred")));
            }
        } else {
            if ($this->Cities_model->delete($id)) {
                echo json_encode(array("success" => true, "message" => app_lang("record_deleted")));
            } else {
                echo json_encode(array("success" => false, "message" => app_lang("record_cannot_be_deleted")));
            }
        }
    }

    // ✅ AJAX endpoint: returns <option> list (HTML) or JSON
    function get_regions_dropdown_by_country($country_id = 0)
    {
        validate_numeric_value($country_id);

        $regions_dropdown = $this->_get_regions_dropdown($country_id);

        // Return HTML options (easiest for Select2 + <select>)
        $options = "";
        foreach ($regions_dropdown as $key => $value) {
            $options .= "<option value='$key'>$value</option>";
        }

        echo $options;
    }

    private function _get_regions_dropdown($country_id)
    {
        $db = Database::connect();
        $regions_table = $db->prefixTable('regions');

        $sql = "SELECT id, name 
                FROM $regions_table 
                WHERE deleted=0 AND is_active=1 AND country_id=$country_id
                ORDER BY name ASC";

        $regions = $db->query($sql)->getResult();

        $dropdown = array("" => "- " . app_lang("select_region") . " -");
        foreach ($regions as $r) {
            $dropdown[$r->id] = $r->name;
        }
        return $dropdown;
    }

    private function _row_data($id)
    {
        $data = $this->Cities_model->get_details(array("id" => $id))->getRow();
        return $this->_make_row($data);
    }

    private function _make_row($data)
    {
        $status = $data->is_active
            ? "<span class='badge bg-success'>" . app_lang("active") . "</span>"
            : "<span class='badge bg-secondary'>" . app_lang("inactive") . "</span>";

        $can_update = $this->can_update_cities();
        $can_delete = $this->can_delete_cities();
        $actions = "";
        if ($can_update || $can_delete) {
            $edit_action = "";
            $delete_action = "";

            if ($can_update) {
                $edit_action = modal_anchor(get_uri("cities/modal_form"), "<i data-feather='edit' class='icon-16'></i>",
                    array("class" => "edit", "title" => app_lang("edit"), "data-post-id" => $data->id));
            }

            if ($can_delete) {
                $delete_action = js_anchor("<i data-feather='x' class='icon-16'></i>",
                    array("title" => app_lang("delete"), "class" => "delete", "data-id" => $data->id, "data-action-url" => get_uri("cities/delete"), "data-action" => "delete"));
            }

            $actions = $edit_action . $delete_action;
        }

        return array(
            $data->country_name,
            $data->region_name,
            $data->name,
            $data->code,
            $status,
            $actions
        );
    }
}
