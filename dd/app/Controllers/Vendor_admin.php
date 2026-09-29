<?php

namespace App\Controllers;

use App\Controllers\Security_Controller;

// reuse the same models vendor portal uses (adjust names if yours differ)
use App\Models\Vendors_model;
use App\Models\Vendor_contacts_model;
use App\Models\Vendor_bank_accounts_model;
use App\Models\Vendor_branches_model;
use App\Models\Vendor_credentials_model;
use App\Models\Vendor_specialties_model;
use App\Models\Vendor_documents_model;

class Vendor_admin extends Security_Controller
{
    protected $db;
    protected $Vendors_model;
    protected $Vendor_contacts_model;
    protected $Vendor_bank_accounts_model;
    protected $Vendor_branches_model;
    protected $Vendor_credentials_model;
    protected $Vendor_specialties_model;
    protected $Vendor_documents_model;

    function __construct()
    {
        parent::__construct();
        $this->access_only_admin_or_settings_admin();

        $this->db = db_connect();

        $this->Vendors_model = new Vendors_model();

        // if any of these model names differ in your project, replace them with the correct ones
        $this->Vendor_contacts_model = new Vendor_contacts_model();
        $this->Vendor_bank_accounts_model = new Vendor_bank_accounts_model();
        $this->Vendor_branches_model = new Vendor_branches_model();
        $this->Vendor_credentials_model = new Vendor_credentials_model();
        $this->Vendor_specialties_model = new Vendor_specialties_model();
        $this->Vendor_documents_model = new Vendor_documents_model();
    }

    public function view($vendor_id)
    {
        $vendor_id = (int)$vendor_id;
        $vendor = $this->Vendors_model->get_one($vendor_id);

        if (!$vendor || (int)$vendor->deleted === 1) {
            app_redirect("vendors");
        }

        return $this->template->rander("vendor_admin/view", [
            "vendor" => $vendor
        ]);
    }

    public function contacts_list_data($vendor_id)
    {
        $vendor_id = (int)$vendor_id;
        $rows = $this->Vendor_contacts_model->get_details(["vendor_id" => $vendor_id])->getResult();

        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                esc($r->name ?? "-"),
                esc($r->email ?? "-"),
                esc($r->phone ?? "-"),
                esc($r->position ?? "-"),
            ];
        }

        echo json_encode(["data" => $data]);
    }

    public function bank_list_data($vendor_id)
    {
        $vendor_id = (int)$vendor_id;
        $rows = $this->Vendor_bank_accounts_model->get_details(["vendor_id" => $vendor_id])->getResult();

        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                esc($r->bank_name ?? "-"),
                esc($r->account_no ?? "-"),
                esc($r->iban ?? "-"),
                esc($r->status ?? "-"),
            ];
        }

        echo json_encode(["data" => $data]);
    }

    public function branches_list_data($vendor_id)
    {
        $vendor_id = (int)$vendor_id;
        $rows = $this->Vendor_branches_model->get_details(["vendor_id" => $vendor_id])->getResult();

        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                esc($r->branch_name ?? "-"),
                esc($r->address ?? "-"),
                esc($r->phone ?? "-"),
                esc($r->status ?? "-"),
            ];
        }

        echo json_encode(["data" => $data]);
    }

    public function credentials_list_data($vendor_id)
    {
        $vendor_id = (int)$vendor_id;
        $rows = $this->Vendor_credentials_model->get_details(["vendor_id" => $vendor_id])->getResult();

        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                esc($r->type ?? "-"),
                esc($r->number ?? "-"),
                esc($r->issue_date ?? "-"),
                esc($r->expiry_date ?? "-"),
            ];
        }

        echo json_encode(["data" => $data]);
    }

    public function specialties_list_data($vendor_id)
    {
        $vendor_id = (int)$vendor_id;
        $rows = $this->Vendor_specialties_model->get_details(["vendor_id" => $vendor_id])->getResult();

        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                esc($r->specialty_name ?? ($r->name ?? "-")),
                esc($r->status ?? "-"),
            ];
        }

        echo json_encode(["data" => $data]);
    }

    public function documents_list_data($vendor_id)
    {
        $vendor_id = (int)$vendor_id;
        $rows = $this->Vendor_documents_model->get_details(["vendor_id" => $vendor_id])->getResult();

        $data = [];
        foreach ($rows as $r) {
            $fileName = $r->original_name ?: basename($r->path);

            $viewBtn = anchor(
                get_uri("vendor_admin/document_preview/" . $r->id),
                app_lang("view"),
                ["class" => "btn btn-default btn-sm", "target" => "_blank"]
            );

            $data[] = [
                esc($r->document_type_name ?? ($r->vendor_document_type_id ?? "-")),
                esc($fileName),
                esc($r->issued_at ?? "-"),
                esc($r->expires_at ?? "-"),
                $viewBtn
            ];
        }

        echo json_encode(["data" => $data]);
    }

    public function document_preview($doc_id)
    {
        $doc_id = (int)$doc_id;
        $doc = $this->Vendor_documents_model->get_one($doc_id);

        if (!$doc || (int)$doc->deleted === 1) {
            show_404();
        }

        $relPath = str_replace("\\", "/", (string)$doc->path);
        $relPath = preg_replace("#\.\.+#", "", $relPath);
        $relPath = ltrim($relPath, "/");

        // adjust to your real upload base:
        $fullPath = WRITEPATH . "uploads/" . $relPath;
        if (!is_file($fullPath)) {
            // if your files are in public/uploads, swap to: FCPATH . "uploads/" . $relPath;
            show_404();
        }

        $mime = $doc->mime_type ?: (function_exists("mime_content_type") ? mime_content_type($fullPath) : "application/octet-stream");
        $name = $doc->original_name ?: basename($fullPath);

        $inline = (str_starts_with($mime, "image/") || $mime === "application/pdf");

        return $this->response
            ->setHeader("Content-Type", $mime)
            ->setHeader("Content-Disposition", ($inline ? "inline" : "attachment") . '; filename="' . addslashes($name) . '"')
            ->setBody(file_get_contents($fullPath));
    }
}
