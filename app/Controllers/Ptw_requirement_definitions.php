<?php

namespace App\Controllers;

use App\Models\Ptw_requirement_definitions_model;

class Ptw_requirement_definitions extends Security_Controller
{
    protected $Ptw_requirement_definitions_model;

    public function __construct()
    {
        parent::__construct();
        $this->access_only_team_members();
        $this->Ptw_requirement_definitions_model = new Ptw_requirement_definitions_model();
    }

    private function _allowed_categories()
    {
        return [
            "hazard_document",
            "ppe",
            "preparation",
            "other",
        ];
    }

    private function _normalize_category($category)
    {
        $category = strtolower(trim((string)$category));
        return in_array($category, $this->_allowed_categories(), true) ? $category : "hazard_document";
    }

    private function _category_label($category)
    {
        $map = [
            "hazard_document" => "Hazard Documents",
            "ppe" => "PPE",
            "preparation" => "Work Area Preparation",
            "other" => "Other",
        ];

        return $map[$category] ?? ucwords(str_replace("_", " ", $category));
    }

    public function index()
    {
        $this->access_only_ptw("hazard_documents", "view");
        app_redirect("ptw_requirement_definitions/hazard_documents");
    }

    public function hazard_documents()
    {
        $this->access_only_ptw("hazard_documents", "view");
        return $this->_render_category_page("hazard_document");
    }

    public function ppe()
    {
        $this->access_only_ptw("ppe", "view");
        return $this->_render_category_page("ppe");
    }

    public function preparation()
    {
        $this->access_only_ptw("preparation", "view");
        return $this->_render_category_page("preparation");
    }

    private function _render_category_page(string $category)
    {
        $category = $this->_normalize_category($category);

        $add_button_map = [
            "hazard_document" => "Add Hazard Document",
            "ppe"             => "Add PPE",
            "preparation"     => "Add Preparation",
            "other"           => "Add Item",
        ];

        return $this->template->rander("ptw_requirement_definitions/_category_page", [
            "category"        => $category,
            "category_label"  => $this->_category_label($category),
            "add_button_text" => $add_button_map[$category] ?? "Add Item",
        ]);
    }

    public function modal_form()
    {
        $this->validate_submitted_data(["id" => "numeric"]);
        $id = (int)$this->request->getPost("id");
        $category = $this->_normalize_category($this->request->getPost("category"));
        $this->access_only_ptw($category === "hazard_document" ? "hazard_documents" : $category, $id ? "update" : "create");

        $model_info = null;
        if ($id) {
            $model_info = $this->Ptw_requirement_definitions_model->get_details(["id" => $id])->getRow();
            if ($model_info) {
                $category = $this->_normalize_category($model_info->category);
            }
        }

        return $this->template->view("ptw_requirement_definitions/modal_form", [
            "model_info" => $model_info,
            "category" => $category,
            "category_label" => $this->_category_label($category),
        ]);
    }

    public function save()
    {
        $this->validate_submitted_data([
            "id" => "numeric",
            "category" => "required",
            "label" => "required|max_length[255]",
            "sort_order" => "permit_empty|numeric",
        ]);

        $id = (int)$this->request->getPost("id");
        $category = $this->_normalize_category($this->request->getPost("category"));
        $this->access_only_ptw($category === "hazard_document" ? "hazard_documents" : $category, $id ? "update" : "create");

        $data = [
            "category" => $category,
            "group_key" => trim((string)$this->request->getPost("group_key")) ?: null,
            "code" => trim((string)$this->request->getPost("code")) ?: null,
            "label" => trim((string)$this->request->getPost("label")),
            "requires_attachment" => $this->request->getPost("requires_attachment") ? 1 : 0,
            "is_mandatory" => $this->request->getPost("is_mandatory") ? 1 : 0,
            "has_text_input" => $this->request->getPost("has_text_input") ? 1 : 0,
            "text_label" => trim((string)$this->request->getPost("text_label")) ?: null,
            "allowed_extensions" => trim((string)$this->request->getPost("allowed_extensions")) ?: null,
            "help_text" => trim((string)$this->request->getPost("help_text")) ?: null,
            "sort_order" => (int)$this->request->getPost("sort_order"),
            "is_active" => $this->request->getPost("is_active") ? 1 : 0,
        ];

        $data = clean_data($data);
        $save_id = $this->Ptw_requirement_definitions_model->ci_save($data, $id);

        if ($save_id) {
            return $this->response->setJSON([
                "success" => true,
                "data" => $this->_row_data($save_id),
                "id" => $save_id,
                "message" => app_lang("record_saved"),
            ]);
        }

        return $this->response->setJSON([
            "success" => false,
            "message" => app_lang("error_occurred"),
        ]);
    }

    public function list_data()
    {
        $category = $this->_normalize_category($this->request->getGet("category"));
        $this->access_only_ptw($category === "hazard_document" ? "hazard_documents" : $category, "view");

        $list = $this->Ptw_requirement_definitions_model
            ->get_details(["category" => $category])
            ->getResult();

        $result = [];
        foreach ($list as $row) {
            $result[] = $this->_make_row($row);
        }

        return $this->response->setJSON(["data" => $result]);
    }

    public function delete()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);
        $id = (int)$this->request->getPost("id");

        $existing = $this->Ptw_requirement_definitions_model->get_details(["id" => $id])->getRow();
        if ($existing) {
            $cat = $existing->category ?? "hazard_document";
            $this->access_only_ptw($cat === "hazard_document" ? "hazard_documents" : $cat, "delete");
        }

        if ($this->Ptw_requirement_definitions_model->delete($id)) {
            return $this->response->setJSON([
                "success" => true,
                "message" => app_lang("record_deleted"),
            ]);
        }

        return $this->response->setJSON([
            "success" => false,
            "message" => app_lang("record_cannot_be_deleted"),
        ]);
    }

    private function _row_data($id)
    {
        $row = $this->Ptw_requirement_definitions_model->get_details(["id" => $id])->getRow();
        return $this->_make_row($row);
    }

    private function _flag_badge($value, $trueText)
    {
        return $value
            ? "<span class='badge bg-success'>{$trueText}</span>"
            : "<span class='badge bg-light text-dark border'>No</span>";
    }

    private function _make_row($data)
    {
        $status = !empty($data->is_active)
            ? "<span class='badge bg-success'>" . app_lang("active") . "</span>"
            : "<span class='badge bg-secondary'>" . app_lang("inactive") . "</span>";

        $flags = [];
        $flags[] = $this->_flag_badge((int)$data->is_mandatory === 1, "Mandatory");
        $flags[] = $this->_flag_badge((int)$data->requires_attachment === 1, "Attachment");
        $flags[] = $this->_flag_badge((int)$data->has_text_input === 1, "Text Input");

        $options = modal_anchor(
            get_uri("ptw_requirement_definitions/modal_form"),
            "<i data-feather='edit' class='icon-16'></i>",
            [
                "class" => "edit",
                "title" => app_lang("edit"),
                "data-post-id" => $data->id,
                "data-post-category" => $data->category,
            ]
        );

        $options .= js_anchor(
            "<i data-feather='x' class='icon-16'></i>",
            [
                "class" => "delete",
                "title" => app_lang("delete"),
                "data-id" => $data->id,
                "data-action-url" => get_uri("ptw_requirement_definitions/delete"),
                "data-action" => "delete-confirmation",
            ]
        );

        return [
            esc($data->label ?? "-"),
            esc($data->code ?? "-"),
            esc($data->group_key ?? "-"),
            implode(" ", $flags),
            esc($data->allowed_extensions ?? "-"),
            (int)($data->sort_order ?? 0),
            $status,
            $options,
        ];
    }
}