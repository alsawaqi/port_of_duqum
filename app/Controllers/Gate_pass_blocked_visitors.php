<?php

namespace App\Controllers;

use App\Models\Gate_pass_blocked_visitors_model;
use App\Models\Gate_pass_rop_users_model;

class Gate_pass_blocked_visitors extends Security_Controller
{
    protected Gate_pass_blocked_visitors_model $Gate_pass_blocked_visitors_model;
    protected Gate_pass_rop_users_model $Gate_pass_rop_users_model;

    public function __construct()
    {
        parent::__construct();
        $this->access_only_team_members();

        $this->Gate_pass_blocked_visitors_model = new Gate_pass_blocked_visitors_model();
        $this->Gate_pass_rop_users_model = new Gate_pass_rop_users_model();

        if (!$this->Gate_pass_rop_users_model->is_rop_user((int) $this->login_user->id)) {
            $this->access_only_gate_pass("blocked_visitors", "view");
        }
    }

    public function index()
    {
        return $this->template->rander("gate_pass_blocked_visitors/index", [
            "can_create_blocked_visitors" => $this->_can_manage_blocked_visitors("create"),
        ]);
    }

    public function list_data()
    {
        $rows = $this->Gate_pass_blocked_visitors_model->get_details()->getResult();
        $result = [];

        foreach ($rows as $row) {
            $result[] = $this->_make_row($row);
        }

        echo json_encode(["data" => $result]);
    }

    public function modal_form()
    {
        $this->validate_submitted_data(["id" => "numeric"]);
        $id = (int) $this->request->getPost("id");
        $this->_access_blocked_visitors($id ? "update" : "create");

        $view_data["model_info"] = $id ? $this->Gate_pass_blocked_visitors_model->get_details(["id" => $id])->getRow() : null;
        $view_data["id_type_options"] = $this->_id_type_options();
        $view_data["nationality_options"] = $this->_nationality_options();

        return $this->template->view("gate_pass_blocked_visitors/modal_form", $view_data);
    }

    public function save()
    {
        $this->validate_submitted_data([
            "id" => "numeric",
            "id_number" => "required",
            "reason" => "required",
        ]);

        $id = (int) $this->request->getPost("id");
        $this->_access_blocked_visitors($id ? "update" : "create");

        $existing = $id ? $this->Gate_pass_blocked_visitors_model->get_details(["id" => $id])->getRow() : null;
        if ($id && !$existing) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("record_not_found")]);
        }

        $id_number = $existing ? (string) $existing->id_number : (string) $this->request->getPost("id_number");

        $save_id = $this->Gate_pass_blocked_visitors_model->block_visitor([
            "id_number" => $id_number,
            "id_type" => $this->request->getPost("id_type"),
            "visitor_name" => $this->request->getPost("visitor_name"),
            "nationality" => $this->request->getPost("nationality"),
            "visitor_company" => $this->request->getPost("visitor_company"),
            "reason" => $this->request->getPost("reason"),
        ], (int) $this->login_user->id, $this->request->getIPAddress(), $this->request->getUserAgent()->getAgentString());

        if (!$save_id) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("error_occurred")]);
        }

        return $this->response->setJSON([
            "success" => true,
            "data" => $this->_row_data($save_id),
            "id" => $save_id,
            "message" => app_lang("record_saved"),
        ]);
    }

    public function unblock_modal_form()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);
        $this->_access_blocked_visitors("update");

        $id = (int) $this->request->getPost("id");
        $model_info = $this->Gate_pass_blocked_visitors_model->get_details(["id" => $id])->getRow();
        if (!$model_info) {
            return $this->template->view("errors/html/error_general", [
                "heading" => app_lang("error"),
                "message" => app_lang("record_not_found"),
            ]);
        }

        return $this->template->view("gate_pass_blocked_visitors/unblock_modal_form", ["model_info" => $model_info]);
    }

    public function unblock()
    {
        $this->validate_submitted_data([
            "id" => "required|numeric",
            "unblock_reason" => "permit_empty",
        ]);
        $this->_access_blocked_visitors("update");

        $id = (int) $this->request->getPost("id");
        $ok = $this->Gate_pass_blocked_visitors_model->unblock_visitor(
            $id,
            (int) $this->login_user->id,
            (string) $this->request->getPost("unblock_reason"),
            $this->request->getIPAddress(),
            $this->request->getUserAgent()->getAgentString()
        );

        if (!$ok) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("error_occurred")]);
        }

        return $this->response->setJSON([
            "success" => true,
            "data" => $this->_row_data($id),
            "id" => $id,
            "message" => app_lang("record_saved"),
        ]);
    }

    public function history_modal()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);
        $id = (int) $this->request->getPost("id");
        $model_info = $this->Gate_pass_blocked_visitors_model->get_details(["id" => $id])->getRow();
        if (!$model_info) {
            return $this->template->view("errors/html/error_general", [
                "heading" => app_lang("error"),
                "message" => app_lang("record_not_found"),
            ]);
        }

        $view_data["model_info"] = $model_info;
        $view_data["history"] = $this->Gate_pass_blocked_visitors_model->get_logs($id)->getResult();

        return $this->template->view("gate_pass_blocked_visitors/history_modal", $view_data);
    }

    private function _row_data(int $id): array
    {
        $row = $this->Gate_pass_blocked_visitors_model->get_details(["id" => $id])->getRow();
        return $this->_make_row($row);
    }

    private function _make_row($row): array
    {
        $status = strtolower((string) ($row->status ?? ""));
        $status_badge = $status === "blocked"
            ? "<span class='badge bg-danger'>" . app_lang("blocked") . "</span>"
            : "<span class='badge bg-secondary'>" . app_lang("gate_pass_unblocked") . "</span>";

        $blocked_by = trim((string) ($row->blocked_by_name ?? ""));
        if ($blocked_by === "" && !empty($row->blocked_by)) {
            $blocked_by = "#" . (int) $row->blocked_by;
        }

        $unblocked_by = trim((string) ($row->unblocked_by_name ?? ""));
        if ($unblocked_by === "" && !empty($row->unblocked_by)) {
            $unblocked_by = "#" . (int) $row->unblocked_by;
        }

        $history = modal_anchor(
            get_uri("gate_pass_blocked_visitors/history_modal"),
            "<i data-feather='clock' class='icon-16'></i>",
            [
                "class" => "btn btn-default btn-sm",
                "title" => app_lang("history"),
                "data-post-id" => $row->id,
                "data-modal-title" => app_lang("gate_pass_block_history"),
            ]
        );

        $can_update = $this->_can_manage_blocked_visitors("update");
        if ($status === "blocked") {
            $toggle = $can_update ? modal_anchor(
                get_uri("gate_pass_blocked_visitors/unblock_modal_form"),
                "<i data-feather='unlock' class='icon-16'></i> " . app_lang("gate_pass_unblock"),
                [
                    "class" => "btn btn-success btn-sm",
                    "title" => app_lang("gate_pass_unblock"),
                    "data-post-id" => $row->id,
                ]
            ) : "";
        } else {
            $toggle = $can_update ? modal_anchor(
                get_uri("gate_pass_blocked_visitors/modal_form"),
                "<i data-feather='slash' class='icon-16'></i> " . app_lang("gate_pass_block_again"),
                [
                    "class" => "btn btn-warning btn-sm",
                    "title" => app_lang("gate_pass_block_again"),
                    "data-post-id" => $row->id,
                ]
            ) : "";
        }

        $edit = $can_update ? modal_anchor(
            get_uri("gate_pass_blocked_visitors/modal_form"),
            "<i data-feather='edit' class='icon-16'></i>",
            [
                "class" => "btn btn-default btn-sm",
                "title" => app_lang("edit"),
                "data-post-id" => $row->id,
            ]
        ) : "";

        $actions = "<div class='gp-blocked-actions'>" . $history . $edit . $toggle . "</div>";

        return [
            $status_badge,
            esc($row->id_number ?? "-"),
            esc($row->id_type ?: "-"),
            esc($row->visitor_name ?: "-"),
            esc($row->nationality ?: "-"),
            $row->reason ? nl2br(esc($row->reason)) : "-",
            $row->blocked_at ? format_to_datetime($row->blocked_at) : "-",
            esc($blocked_by ?: "-"),
            $row->unblocked_at ? format_to_datetime($row->unblocked_at) : "-",
            esc($unblocked_by ?: "-"),
            $actions,
        ];
    }

    private function _can_manage_blocked_visitors(string $action): bool
    {
        return $this->login_user->is_admin
            || $this->Gate_pass_rop_users_model->is_rop_user((int) $this->login_user->id)
            || $this->can_gate_pass("blocked_visitors", $action);
    }

    private function _access_blocked_visitors(string $action): void
    {
        if (!$this->_can_manage_blocked_visitors($action)) {
            app_redirect("forbidden");
            exit;
        }
    }

    private function _id_type_options(): array
    {
        return [
            "" => "- " . app_lang("id_type") . " -",
            "Passport" => app_lang("gate_pass_id_type_passport"),
            "National ID" => app_lang("gate_pass_id_type_national_id"),
            "Civil ID" => app_lang("gate_pass_id_type_civil_id"),
            "Residence Permit" => app_lang("gate_pass_id_type_residence_permit"),
            "Residence Card" => app_lang("gate_pass_id_type_residence_card"),
            "Driving License" => app_lang("gate_pass_id_type_driving_license"),
            "Visa" => app_lang("gate_pass_id_type_visa"),
            "Seafarer ID" => app_lang("gate_pass_id_type_seafarer_id"),
            "Military ID" => app_lang("gate_pass_id_type_military_id"),
            "Other" => app_lang("other"),
        ];
    }

    private function _nationality_options(): array
    {
        $locale = (string) service("request")->getLocale();
        $label_locale = strtolower(substr($locale, 0, 2)) === "ar" ? "ar" : "en";
        $english_countries = $this->_icu_country_names("en");
        $localized_countries = $this->_icu_country_names($label_locale);
        $options = [];

        foreach ($english_countries as $code => $english_name) {
            $options[$english_name] = $localized_countries[$code] ?? $english_name;
        }

        if (!$options) {
            $countries = require APPPATH . "Config/intl_phone_dial_codes.php";
            foreach ($countries as $country) {
                $name = trim((string) ($country["country"] ?? ""));
                if ($name !== "") {
                    $options[$name] = $name;
                }
            }
        }

        natcasesort($options);

        return ["" => "- " . app_lang("nationality") . " -"] + $options;
    }

    private function _icu_country_names(string $locale): array
    {
        if (!class_exists("\\ResourceBundle")) {
            return [];
        }

        $bundle = \ResourceBundle::create($locale, "ICUDATA-region");
        if (!$bundle) {
            return [];
        }

        $countries = $bundle->get("Countries");
        if (!$countries) {
            return [];
        }

        $result = [];
        foreach ($countries as $code => $name) {
            if (preg_match("/^[A-Z]{2}$/", (string) $code)) {
                $result[(string) $code] = (string) $name;
            }
        }

        return $result;
    }
}
