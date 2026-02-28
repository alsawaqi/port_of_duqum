<?php

namespace App\Controllers;

use App\Models\Gate_pass_fee_rules_model;

class Gate_pass_fee_rules extends Security_Controller
{
    protected $Gate_pass_fee_rules_model;

    function __construct()
    {
        parent::__construct();

        $this->access_only_team_members();
        $this->Gate_pass_fee_rules_model = new Gate_pass_fee_rules_model();
    }

    function index()
    {
        $this->access_only_gate_pass("fee_rules", "view");
        return $this->template->rander("gate_pass_fee_rules/index");
    }

    function modal_form()
    {
        $id = $this->request->getPost("id");
        $this->access_only_gate_pass("fee_rules", $id ? "update" : "create");

        $view_data["model_info"] = $this->Gate_pass_fee_rules_model->get_one($id);

        $view_data["rate_type_dropdown"] = [
            "flat" => "Flat",
            "daily" => "Daily",
            "weekly" => "Weekly",
            "monthly" => "Monthly",
        ];

        $view_data["currency_options"] = [
            "OMR" => "OMR",
            "USD" => "USD",
            "EUR" => "EUR",
            "GBP" => "GBP"
        ];

        return $this->template->view("gate_pass_fee_rules/modal_form", $view_data);
    }

    function save()
    {
        $this->validate_submitted_data([
            "id" => "numeric",
            "currency" => "required",
            "amount" => "required|numeric",
            "min_days" => "required|numeric"
        ]);

        $id = $this->request->getPost("id");
        $this->access_only_gate_pass("fee_rules", $id ? "update" : "create");

        $min_days = (int)$this->request->getPost("min_days");
        $max_raw  = $this->request->getPost("max_days");
        $max_days = (is_numeric($max_raw) && (int)$max_raw > 0) ? (int)$max_raw : null;

        if ($min_days < 1) {
            echo json_encode(["success" => false, "message" => "Min days must be >= 1"]);
            return;
        }

        if ($max_days !== null && $max_days < $min_days) {
            echo json_encode(["success" => false, "message" => "Max days must be >= Min days"]);
            return;
        }

        $currency = $this->request->getPost("currency");

        // Optional but recommended: block overlapping ranges (prevents ambiguous fee selection)
        if ($this->Gate_pass_fee_rules_model->has_overlap($min_days, $max_days, $currency, $id ?: null)) {
            echo json_encode(["success" => false, "message" => "This range overlaps with an existing rule for the same currency."]);
            return;
        }

        $data = [
            "rate_type" => $this->request->getPost("rate_type") ?: "flat",
            "min_days"  => $min_days,
            "max_days"  => $max_days,
            "currency"  => $currency,
            "amount"    => $this->request->getPost("amount"),
            "is_waivable" => $this->request->getPost("is_waivable") ? 1 : 0,
            "is_active"   => $this->request->getPost("is_active") ? 1 : 0,
        ];

        $data = clean_data($data);

        $save_id = $this->Gate_pass_fee_rules_model->ci_save($data, $id);

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
        $this->access_only_gate_pass("fee_rules", "view");
        $list = $this->Gate_pass_fee_rules_model->get_details()->getResult();
        $result = [];

        foreach ($list as $row) {
            $result[] = $this->_make_row($row);
        }

        echo json_encode(["data" => $result]);
    }

    function delete()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);
        $this->access_only_gate_pass("fee_rules", "delete");
        $id = $this->request->getPost("id");

        if ($this->request->getPost("undo")) {
            if ($this->Gate_pass_fee_rules_model->delete($id, true)) {
                echo json_encode(["success" => true, "data" => $this->_row_data($id), "message" => app_lang("record_undone")]);
            } else {
                echo json_encode(["success" => false, "message" => app_lang("error_occurred")]);
            }
        } else {
            if ($this->Gate_pass_fee_rules_model->delete($id)) {
                echo json_encode(["success" => true, "message" => app_lang("record_deleted")]);
            } else {
                echo json_encode(["success" => false, "message" => app_lang("record_cannot_be_deleted")]);
            }
        }
    }

    private function _row_data($id)
    {
        $row = $this->Gate_pass_fee_rules_model->get_details(["id" => $id])->getRow();
        return $this->_make_row($row);
    }

    private function _make_row($row)
    {
        $duration = (int)$row->min_days . " - " . (($row->max_days !== null && $row->max_days !== "") ? (int)$row->max_days : "+") . " days";
        $rate = ucfirst($row->rate_type ?? "flat");
        $amount = to_currency($row->amount, $row->currency);

        $waivable = $row->is_waivable
            ? "<span class='badge bg-info'>Yes</span>"
            : "<span class='badge bg-secondary'>No</span>";

        $status = $row->is_active
            ? "<span class='badge bg-success'>" . app_lang("active") . "</span>"
            : "<span class='badge bg-secondary'>" . app_lang("inactive") . "</span>";

        $options =
            modal_anchor(
                get_uri("gate_pass_fee_rules/modal_form"),
                "<i data-feather='edit' class='icon-16'></i>",
                ["class" => "edit", "title" => app_lang("edit"), "data-post-id" => $row->id]
            )
            . " " .
            js_anchor(
                "<i data-feather='x' class='icon-16'></i>",
                ["title" => app_lang("delete"), "class" => "delete", "data-id" => $row->id, "data-action-url" => get_uri("gate_pass_fee_rules/delete"), "data-action" => "delete-confirmation"]
            );

        return [$duration, $rate, $amount, $waivable, $status, $options];
    }
}
