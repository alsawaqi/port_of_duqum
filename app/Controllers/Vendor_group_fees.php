<?php

namespace App\Controllers;

use App\Models\Vendor_group_fees_model;
use App\Models\Vendor_groups_model;

class Vendor_group_fees extends Security_Controller
{

    protected $Vendor_group_fees_model;
    protected $Vendor_groups_model;

    function __construct()
    {
        parent::__construct();
        $this->access_only_team_members();

        $this->Vendor_group_fees_model = new Vendor_group_fees_model();
        $this->Vendor_groups_model = new Vendor_groups_model();
    }

    function index()
    {
        $this->access_only_vendor_group_fees_view();

        $view_data = [
            "can_create_vendor_group_fees" => $this->can_create_vendor_group_fees(),
            "can_update_vendor_group_fees" => $this->can_update_vendor_group_fees(),
            "can_delete_vendor_group_fees" => $this->can_delete_vendor_group_fees()
        ];

        return $this->template->rander('vendor_group_fees/index', $view_data);
    }

    function modal_form()
    {
        $this->validate_submitted_data(array("id" => "numeric"));

        $id = $this->request->getPost('id');
        if ($id) {
            $this->access_only_vendor_group_fees_update();
        } else {
            $this->access_only_vendor_group_fees_create();
        }
        $view_data["model_info"] = $this->Vendor_group_fees_model->get_one($id);

        $groups_dropdown = array("" => "- " . app_lang("select_vendor_group") . " -");
        $groups = $this->Vendor_groups_model->get_details()->getResult();
        foreach ($groups as $g) {
            $groups_dropdown[$g->id] = $g->name . " (" . $g->code . ")";
        }
        $view_data["vendor_groups_dropdown"] = $groups_dropdown;

        $view_data["fee_types_dropdown"] = array(
            "registration" => app_lang('registration_fee'),
            "renewal"      => app_lang('renewal_fee')
        );

        return $this->template->view('vendor_group_fees/modal_form', $view_data);
    }

    function save()
    {
        $this->validate_submitted_data(array(
            "id" => "numeric",
            "vendor_group_id" => "required|numeric",
            "fee_type" => "required",
            "currency" => "required",
            "amount" => "required|numeric"
        ));

        $id = $this->request->getPost('id');
        if ($id) {
            $this->access_only_vendor_group_fees_update();
        } else {
            $this->access_only_vendor_group_fees_create();
        }

        $active_from = $this->request->getPost('active_from');
        $active_to   = $this->request->getPost('active_to');

        $active_from = $active_from ? $this->_check_valid_date($active_from) : null;
        $active_to   = $active_to ? $this->_check_valid_date($active_to) : null;

        if ($active_from === false || $active_to === false) {
            echo json_encode(array("success" => false, "message" => app_lang("invalid_date")));
            return;
        }

        // optional: enforce order if both set
        if ($active_from && $active_to && strtotime($active_to) < strtotime($active_from)) {
            echo json_encode(array("success" => false, "message" => app_lang("date_range_invalid")));
            return;
        }

        $data = array(
            "vendor_group_id" => (int) $this->request->getPost('vendor_group_id'),
            "fee_type"        => $this->request->getPost('fee_type'),
            "currency"        => strtoupper(trim($this->request->getPost('currency'))),
            "amount"          => $this->request->getPost('amount'),
            "active_from"     => $active_from,
            "active_to"       => $active_to,
            "is_active"       => $this->request->getPost('is_active') ? 1 : 0
        );

        if (!$id) {
            $data["created_by"] = $this->login_user->id;
        }

        $data = clean_data($data);
        $save_id = $this->Vendor_group_fees_model->ci_save($data, $id);

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
        $this->access_only_vendor_group_fees_view();

        $list_data = $this->Vendor_group_fees_model->get_details()->getResult();
        $result = array();

        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data);
        }

        echo json_encode(array("data" => $result));
    }

    function delete()
    {
        $this->access_only_vendor_group_fees_delete();

        $this->validate_submitted_data(array("id" => "required|numeric"));
        $id = $this->request->getPost('id');

        if ($this->request->getPost("undo")) {
            if ($this->Vendor_group_fees_model->delete($id, true)) {
                echo json_encode(array("success" => true, "data" => $this->_row_data($id), "message" => app_lang("record_undone")));
            } else {
                echo json_encode(array("success" => false, "message" => app_lang("error_occurred")));
            }
        } else {
            if ($this->Vendor_group_fees_model->delete($id)) {
                echo json_encode(array("success" => true, "message" => app_lang("record_deleted")));
            } else {
                echo json_encode(array("success" => false, "message" => app_lang("record_cannot_be_deleted")));
            }
        }
    }

    private function _row_data($id)
    {
        $data = $this->Vendor_group_fees_model->get_details(array("id" => $id))->getRow();
        return $this->_make_row($data);
    }

    private function _make_row($data)
    {
        $status = $data->is_active
            ? "<span class='badge bg-success'>" . app_lang("active") . "</span>"
            : "<span class='badge bg-secondary'>" . app_lang("inactive") . "</span>";

        $fee_type_label = ($data->fee_type === "renewal")
            ? app_lang("renewal_fee")
            : app_lang("registration_fee");

        $amount = to_currency($data->amount, $data->currency);

        $period = "-";
        if ($data->active_from || $data->active_to) {
            $from = $data->active_from ? format_to_date($data->active_from, false) : "-";
            $to   = $data->active_to ? format_to_date($data->active_to, false) : "-";
            $period = $from . " → " . $to;
        }

        $can_update = $this->can_update_vendor_group_fees();
        $can_delete = $this->can_delete_vendor_group_fees();
        $actions = "";
        if ($can_update || $can_delete) {
            $edit_action = "";
            $delete_action = "";

            if ($can_update) {
                $edit_action = modal_anchor(get_uri("vendor_group_fees/modal_form"), "<i data-feather='edit' class='icon-16'></i>", array("class" => "edit", "title" => app_lang("edit"), "data-post-id" => $data->id));
            }

            if ($can_delete) {
                $delete_action = js_anchor("<i data-feather='x' class='icon-16'></i>", array("title" => app_lang("delete"), "class" => "delete", "data-id" => $data->id, "data-action-url" => get_uri("vendor_group_fees/delete"), "data-action" => "delete"));
            }

            $actions = $edit_action . $delete_action;
        }

        return array(
            $data->vendor_group_name,
            $fee_type_label,
            $amount,
            $period,
            $status,
            $actions
        );
    }
}
