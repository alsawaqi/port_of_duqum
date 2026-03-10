<?php

namespace App\Controllers;

use Config\Database;

use App\Models\Cities_model;
use App\Models\Country_model;
use App\Models\Regions_model;

use App\Models\Vendors_model;
use App\Models\Vendor_branches_model;
use App\Models\Vendor_contacts_model;
use App\Models\Vendor_credentials_model;
use App\Models\Vendor_specialties_model;
use App\Models\Vendor_documents_model;
use App\Models\Vendor_document_types_model;
use App\Models\Vendor_update_requests_model;
use App\Models\Vendor_bank_accounts_model;
use App\Models\Vendor_categories_model;
use App\Models\Tenders_model;
use App\Models\Tender_documents_model;
use App\Models\Tender_bids_model;
use App\Models\Tender_bid_documents_model;
use App\Models\Tender_evaluations_model;


class Vendor_portal extends Security_Controller
{
    protected $Vendors_model;
    protected $Vendor_contacts_model;
    protected $Tenders_model;
    protected $Tender_documents_model;

    protected $Vendor_categories_model;
    protected $Vendor_branches_model;
    protected $Country_model;
    protected $Regions_model;
    protected $Cities_model;
    protected $Vendor_credentials_model;
    protected $Vendor_specialties_model;
    protected $Vendor_documents_model;
    protected $Vendor_document_types_model;
    protected $Vendor_update_requests_model;
    protected $Tender_bids_model;
    protected $Tender_bid_documents_model;

    protected $Tender_evaluations_model;

    protected $Vendor_bank_accounts_model;
    // cached per-request (avoid repeating lock checks)
    private array $vendor_module_locked_cache = [];

    function __construct()
    {
        parent::__construct();

        // Vendor Portal is for staff users (vendor logins should be staff)
        if ($this->login_user->user_type !== "staff") {
            app_redirect("forbidden");
        }

        $this->Vendors_model = new Vendors_model();
        $this->Vendor_contacts_model = new Vendor_contacts_model();

        $this->Vendor_branches_model = new Vendor_branches_model();
        $this->Country_model = new Country_model();
        $this->Regions_model = new Regions_model();
        $this->Cities_model = new Cities_model();

        $this->Tenders_model = new Tenders_model();
        $this->Tender_documents_model = new Tender_documents_model();


        $this->Vendor_categories_model = new Vendor_categories_model();

        $this->Vendor_credentials_model = new Vendor_credentials_model();
        $this->Vendor_specialties_model = new Vendor_specialties_model();
        $this->Vendor_documents_model = new Vendor_documents_model();
        $this->Vendor_document_types_model = new Vendor_document_types_model();
        $this->Vendor_update_requests_model = new Vendor_update_requests_model();
        $this->Vendor_bank_accounts_model = new Vendor_bank_accounts_model();

        $this->Tender_bids_model = new Tender_bids_model();
        $this->Tender_bid_documents_model = new Tender_bid_documents_model();
        $this->Tender_evaluations_model = new Tender_evaluations_model();
    }


    function tenders()
    {
        $this->_require_vendor_access();
        return $this->template->view("vendor_portal/tenders/index");
    }

    function tenders_list_data()
    {
        $vendor_id = $this->_require_vendor_access();

        $list_data = $this->Tenders_model->get_vendor_visible_tenders($vendor_id)->getResult();

        $result = [];
        foreach ($list_data as $row) {
            $result[] = $this->_make_tender_row($row);
        }

        echo json_encode(["data" => $result]);
    }

    function tender_view_modal()
    {
        $this->validate_submitted_data([
            "id" => "required|numeric"
        ]);

        $vendor_id = $this->_require_vendor_access();
        $tender_id = (int) $this->request->getPost("id");

        $tender = $this->Tenders_model->get_vendor_visible_tender($tender_id, $vendor_id);
        if (!$tender) {
            app_redirect("forbidden");
        }

        $db = db_connect();
        $tiv = $db->prefixTable("tender_invited_vendors");
        $db->query(
            "UPDATE $tiv
             SET invite_status='opened'
             WHERE tender_id=? AND vendor_id=? AND deleted=0
               AND invite_status IN ('sent','delivered')",
            [$tender_id, $vendor_id]
        );

        $docs = $this->Tender_documents_model->get_details([
            "tender_id" => $tender_id
        ])->getResult();

        $bid = $this->Tender_bids_model->get_vendor_bid($tender_id, $vendor_id);

        $latest_commercial_evaluation = null;
        $is_awarded_to_vendor = false;
        $is_regretted_vendor = false;

        if ($bid) {
            $latest_commercial_evaluation = $this->Tender_evaluations_model->get_latest_stage_evaluation_for_bid((int) $bid->id, "commercial");
        }

        if (($tender->status ?? "") === "awarded" && $bid) {
            $is_awarded_to_vendor = strtolower((string) ($latest_commercial_evaluation->decision ?? "")) === "accepted";
            $is_regretted_vendor = !$is_awarded_to_vendor;
        }

        return $this->template->view("vendor_portal/tenders/view_modal", [
            "tender"                       => $tender,
            "docs"                         => $docs,
            "bid"                          => $bid,
            "latest_commercial_evaluation" => $latest_commercial_evaluation,
            "is_awarded_to_vendor"         => $is_awarded_to_vendor,
            "is_regretted_vendor"          => $is_regretted_vendor
        ]);
    }

    public function download_tender_document($id = 0)
    {
        $vendor_id = $this->_require_vendor_access();
        $id = (int) $id;

        if (!$id) {
            show_404();
        }

        $doc = $this->Tender_documents_model->get_one($id);
        if (!$doc || (int) ($doc->deleted ?? 0) === 1) {
            show_404();
        }

        $tender = $this->Tenders_model->get_vendor_visible_tender((int) $doc->tender_id, $vendor_id);
        if (!$tender) {
            app_redirect("forbidden");
        }

        if ((int) ($doc->time_limited ?? 0) === 1 && !empty($doc->expires_in_hours) && !empty($doc->created_at)) {
            $expires_at = strtotime($doc->created_at . " +" . (int) $doc->expires_in_hours . " hours");
            if ($expires_at && $expires_at < time()) {
                app_redirect("forbidden");
            }
        }

        $full_path = getcwd() . "/" . ltrim((string) $doc->path, "/");
        if (!is_file($full_path)) {
            show_404();
        }

        $download_name = $doc->original_name ?: basename($full_path);

        return $this->response->download($full_path, null)->setFileName($download_name);
    }


    function bid_modal()
    {
        $this->validate_submitted_data([
            "tender_id" => "required|numeric"
        ]);

        $vendor_id = $this->_require_vendor_access();
        $tender_id = (int) $this->request->getPost("tender_id");


        $this->Tenders_model->auto_progress_workflow();

        $tender = $this->Tenders_model->get_vendor_visible_tender($tender_id, $vendor_id);
        if (!$tender) {
            app_redirect("forbidden");
        }

        if (!$this->_is_tender_submission_open($tender)) {
            app_redirect("forbidden");
        }

        $bid = $this->Tender_bids_model->get_vendor_bid($tender_id, $vendor_id);

        $technical_doc = null;
        $commercial_doc = null;

        if ($bid) {
            $technical_doc = $this->Tender_bid_documents_model->get_bid_document_by_section((int) $bid->id, "technical");
            $commercial_doc = $this->Tender_bid_documents_model->get_bid_document_by_section((int) $bid->id, "commercial");
        }

        return $this->template->view("vendor_portal/tenders/bid_modal", [
            "tender" => $tender,
            "bid" => $bid,
            "technical_doc" => $technical_doc,
            "commercial_doc" => $commercial_doc
        ]);
    }

    function save_bid()
    {
        $this->validate_submitted_data([
            "tender_id" => "required|numeric"
        ]);

        $vendor_id = $this->_require_vendor_access();
        $tender_id = (int) $this->request->getPost("tender_id");


        $this->Tenders_model->auto_progress_workflow();

        $tender = $this->Tenders_model->get_vendor_visible_tender($tender_id, $vendor_id);
        if (!$tender) {
            return $this->response->setJSON([
                "success" => false,
                "message" => "Tender not found or not accessible."
            ]);
        }

        if (!$this->_is_tender_submission_open($tender)) {
            return $this->response->setJSON([
                "success" => false,
                "message" => "Bid submission is closed for this tender."
            ]);
        }

        $existing_bid = $this->Tender_bids_model->get_vendor_bid($tender_id, $vendor_id);

        $technical_file = $this->request->getFile("technical_file");
        $commercial_file = $this->request->getFile("commercial_file");

        $has_technical_upload = $technical_file && $technical_file->isValid() && !$technical_file->hasMoved();
        $has_commercial_upload = $commercial_file && $commercial_file->isValid() && !$commercial_file->hasMoved();

        $existing_technical = null;
        $existing_commercial = null;

        if ($existing_bid) {
            $existing_technical = $this->Tender_bid_documents_model->get_bid_document_by_section((int) $existing_bid->id, "technical");
            $existing_commercial = $this->Tender_bid_documents_model->get_bid_document_by_section((int) $existing_bid->id, "commercial");
        }

        if (!$existing_bid && (!$has_technical_upload || !$has_commercial_upload)) {
            return $this->response->setJSON([
                "success" => false,
                "message" => "Both Technical Proposal and Commercial Proposal are required."
            ]);
        }

        if ($existing_bid) {
            if (!$existing_technical && !$has_technical_upload) {
                return $this->response->setJSON([
                    "success" => false,
                    "message" => "Technical Proposal is required."
                ]);
            }

            if (!$existing_commercial && !$has_commercial_upload) {
                return $this->response->setJSON([
                    "success" => false,
                    "message" => "Commercial Proposal is required."
                ]);
            }
        }

        $currency = trim((string) $this->request->getPost("currency"));
        if (!$currency) {
            $currency = "OMR";
        }

        $bid_data = [
            "tender_id" => $tender_id,
            "vendor_id" => $vendor_id,
            "status" => "submitted",
            "submitted_at" => date("Y-m-d H:i:s"),
            "total_amount" => $this->request->getPost("total_amount") !== "" ? $this->request->getPost("total_amount") : null,
            "currency" => $currency,
        ];

        $bid_id = $this->Tender_bids_model->ci_save(clean_data($bid_data), $existing_bid->id ?? 0);

        if (!$bid_id) {
            return $this->response->setJSON([
                "success" => false,
                "message" => app_lang("error_occurred")
            ]);
        }

        $upload_dir = WRITEPATH . "uploads/tender_bids/tender_" . $tender_id . "/vendor_" . $vendor_id . "/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0775, true);
        }

        if ($has_technical_upload) {
            $this->_replace_bid_document((int) $bid_id, "technical", $technical_file, $upload_dir, $tender_id, $vendor_id);
        }

        if ($has_commercial_upload) {
            $this->_replace_bid_document((int) $bid_id, "commercial", $commercial_file, $upload_dir, $tender_id, $vendor_id);
        }

        return $this->response->setJSON([
            "success" => true,
            "message" => "Bid submitted successfully."
        ]);
    }

    public function download_bid_document($id = 0)
    {
        $vendor_id = $this->_require_vendor_access();
        $id = (int) $id;

        if (!$id) {
            show_404();
        }

        $doc = $this->Tender_bid_documents_model->get_one($id);
        if (!$doc || (int) ($doc->deleted ?? 0) === 1) {
            show_404();
        }

        $db = db_connect();
        $tb = $db->prefixTable("tender_bids");

        $bid = $db->query(
            "SELECT *
             FROM $tb
             WHERE id=? AND deleted=0
             LIMIT 1",
            [(int) $doc->tender_bid_id]
        )->getRow();

        if (!$bid || (int) $bid->vendor_id !== (int) $vendor_id) {
            app_redirect("forbidden");
        }

        $full_path = WRITEPATH . "uploads/" . ltrim((string) $doc->path, "/");
        if (!is_file($full_path)) {
            show_404();
        }

        $download_name = $doc->original_name ?: basename($full_path);

        return $this->response->download($full_path, null)->setFileName($download_name);
    }

    private function _replace_bid_document(int $tender_bid_id, string $section, $file, string $upload_dir, int $tender_id, int $vendor_id): void
    {
        $existing = $this->Tender_bid_documents_model->get_bid_document_by_section($tender_bid_id, $section);
        if ($existing) {
            $this->Tender_bid_documents_model->ci_save(["deleted" => 1], (int) $existing->id);
        }

        $new_name = uniqid("tb_" . $section . "_", true) . "." . $file->getExtension();
        $file->move($upload_dir, $new_name);

        $doc_data = [
            "tender_bid_id" => $tender_bid_id,
            "section" => $section,
            "disk" => "local",
            "path" => "tender_bids/tender_" . $tender_id . "/vendor_" . $vendor_id . "/" . $new_name,
            "original_name" => $file->getClientName(),
            "mime_type" => $file->getClientMimeType(),
            "size_bytes" => $file->getSize(),
            "submitted_at" => date("Y-m-d H:i:s"),
        ];

        $this->Tender_bid_documents_model->ci_save(clean_data($doc_data));
    }

    private function _is_tender_submission_open($tender): bool
    {
        return $this->Tenders_model->is_vendor_submission_open($tender);
    }

    private function _make_tender_row($row)
    {
        $type_badge = ($row->tender_type ?? "open") === "close"
            ? "<span class='badge bg-warning'>CLOSE</span>"
            : "<span class='badge bg-success'>OPEN</span>";

        $status = strtolower((string) ($row->status ?? "draft"));
        $status_classes = [
            "draft" => "secondary",
            "published" => "primary",
            "closed" => "dark",
            "awarded" => "success",
            "cancelled" => "danger",
        ];
        $status_class = $status_classes[$status] ?? "secondary";
        $status_badge = "<span class='badge bg-" . $status_class . "'>" . esc(ucfirst($status)) . "</span>";

        $invite_status = strtolower((string) ($row->invite_status ?? "sent"));
        $invite_badges = [
            "sent"      => "secondary",
            "delivered" => "info",
            "opened"    => "success",
            "declined"  => "danger",
        ];
        $invite_class = $invite_badges[$invite_status] ?? "secondary";
        $invite_badge = "<span class='badge bg-" . $invite_class . "'>" . esc(ucfirst($invite_status)) . "</span>";

        $target = $row->vendor_category_name ?: "-";
        if (!empty($row->vendor_sub_category_name)) {
            $target .= " / " . $row->vendor_sub_category_name;
        }

        $actions = modal_anchor(
            get_uri("vendor_portal/tender_view_modal"),
            "<i data-feather='eye' class='icon-16'></i>",
            [
                "class" => "edit",
                "title" => "Tender Details",
                "data-post-id" => $row->id
            ]
        );

        return [
            esc($row->reference ?? "-"),
            esc($row->title ?? "-"),
            $type_badge,
            $status_badge,
            esc($target),
            !empty($row->published_at) ? format_to_datetime($row->published_at) : "-",
            !empty($row->closing_at) ? format_to_datetime($row->closing_at) : "-",
            $invite_badge,
            $actions
        ];
    }

    private function _my_vendor_id(): int
    {
        $db = db_connect();
        $vendor_users_table = $db->prefixTable("vendor_users");

        $row = $db->query(
            "SELECT vendor_id 
             FROM $vendor_users_table
             WHERE user_id=? AND deleted=0 AND status='active'
             ORDER BY is_owner DESC, id DESC
             LIMIT 1",
            [$this->login_user->id]
        )->getRow();

        return $row ? (int)$row->vendor_id : 0;
    }

    private function _require_vendor_access(): int
    {
        $vendor_id = $this->_my_vendor_id();
        if (!$vendor_id && !$this->login_user->is_admin) {
            app_redirect("forbidden");
        }
        return $vendor_id;
    }


    private function _is_vendor_module_locked(int $vendor_id, string $module): bool
    {
        if (!$vendor_id) return false;

        $key = $vendor_id . ":" . $module;
        if (isset($this->vendor_module_locked_cache[$key])) {
            return $this->vendor_module_locked_cache[$key];
        }

        $db = db_connect();
        $vur = $db->prefixTable("vendor_update_requests");

        // Prefer JSON_EXTRACT (MySQL 5.7+). Fallback to LIKE.
        try {
            $sql = "SELECT 1
                FROM $vur
                WHERE vendor_id=? AND deleted=0 AND status='pending'
                  AND JSON_UNQUOTE(JSON_EXTRACT(changes,'$.module')) = ?
                LIMIT 1";
            $locked = (bool) $db->query($sql, [$vendor_id, $module])->getRow();
        } catch (\Throwable $e) {
            $sql = "SELECT 1
                FROM $vur
                WHERE vendor_id=? AND deleted=0 AND status='pending'
                  AND changes LIKE ?
                LIMIT 1";
            $locked = (bool) $db->query($sql, [$vendor_id, '%"module":"' . $module . '"%'])->getRow();
        }

        $this->vendor_module_locked_cache[$key] = $locked;
        return $locked;
    }

    private function _deny_if_vendor_module_locked(int $vendor_id, string $module)
    {
        if ($this->_is_vendor_module_locked($vendor_id, $module)) {
            echo json_encode([
                "success" => false,
                "message" => "This section has a pending approval request. You can't make changes until it is reviewed."
            ]);
            exit;
        }
    }






    function bank()
    {
        $vendor_id = $this->_require_vendor_access();

        $view_data["is_locked"] = $this->_is_vendor_module_locked($vendor_id, "bank");

        // ✅ NEW: show latest "review" request comment (if any)
        $view_data["review_request"] = $this->_get_vendor_latest_review_request($vendor_id, "bank");

        return $this->template->view("vendor_portal/bank/index", $view_data);
    }



    function bank_accounts_list_data()
    {
        $vendor_id = $this->_require_vendor_access();
        $is_locked = $this->_is_vendor_module_locked($vendor_id, "bank");

        $list_data = $this->Vendor_bank_accounts_model->get_details([
            "vendor_id" => $vendor_id
        ])->getResult();

        $result = [];
        foreach ($list_data as $row) {
            $result[] = $this->_make_bank_row($row, $is_locked);
        }

        echo json_encode(["data" => $result]);
    }



    function bank_account_modal_form()
    {
        $this->validate_submitted_data(["id" => "numeric"]);

        $vendor_id = $this->_require_vendor_access();

        $id = $this->request->getPost("id");

        if ($id && $this->_is_vendor_module_locked($vendor_id, "bank")) {
            return $this->_locked_modal_view();
        }

        $id = $this->request->getPost("id");
        $model_info = $this->Vendor_bank_accounts_model->get_one($id);

        if ($id && (int)$model_info->vendor_id !== (int)$vendor_id) {
            app_redirect("forbidden");
        }

        $view_data["model_info"] = $model_info;
        return $this->template->view("vendor_portal/bank/modal_form", $view_data);
    }



    function save_bank_account()
    {
        $this->validate_submitted_data([
            "id" => "numeric",
            "bank_name" => "required",
            "bank_account_no" => "required",
        ]);

        $vendor_id = $this->_require_vendor_access();


        $id = $this->request->getPost("id");


        if ($id) {
            $this->_deny_if_vendor_module_locked($vendor_id, "bank");
        }

        $before = null;
        if ($id) {
            $row = $this->Vendor_bank_accounts_model->get_one($id);
            if ((int)$row->vendor_id !== (int)$vendor_id) {
                app_redirect("forbidden");
            }
            $before = $this->_bank_row_to_array($row);
        }

        $data = [
            "vendor_id"        => $vendor_id,
            "bank_name"        => $this->request->getPost("bank_name"),
            "bank_branch"      => $this->request->getPost("bank_branch"),
            "bank_account_no"  => $this->request->getPost("bank_account_no"),
            "bank_swift_code"  => $this->request->getPost("bank_swift_code"),
            "iban"             => $this->request->getPost("iban"),
            "status"           => "pending",
        ];

        // optional file: letter head
        $file = $this->request->getFile("letter_head");
        $has_new_file = $file && $file->isValid() && !$file->hasMoved();

        if ($has_new_file) {
            $upload_dir = WRITEPATH . "uploads/vendor_bank_accounts/vendor_" . $vendor_id . "/";
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0775, true);
            }

            $new_name = uniqid("vh_", true) . "." . $file->getExtension();
            $file->move($upload_dir, $new_name);

            // store RELATIVE path under writable/uploads/
            $data["letter_head_path"] = "vendor_bank_accounts/vendor_" . $vendor_id . "/" . $new_name;
        }

        $data = clean_data($data);

        $save_id = $this->Vendor_bank_accounts_model->ci_save($data, $id);

        if ($save_id) {
            $changes = [
                "module"    => "bank",                 // MUST match your lock check
                "table"     => "vendor_bank_accounts",
                "action"    => $id ? "update" : "create",
                "record_id" => (int)$save_id,
                "before"    => $before,
                "after"     => $data,
            ];

            // ✅ create (or re-submit) approval request
            $db = db_connect();
            $vurTable = $db->prefixTable("vendor_update_requests");

            // if admin previously marked it as "review" for the same record, re-submit it as pending
            $existing_review = $this->_get_vendor_review_request_for_record($vendor_id, "bank", (int)$save_id);

            if ($existing_review) {

                $ok = $db->table($vurTable)
                    ->where("id", (int)$existing_review->id)
                    ->update([
                        "changes"        => json_encode($changes, JSON_UNESCAPED_UNICODE),
                        "status"         => "pending",
                        "reviewed_by"    => null,
                        "reviewed_at"    => null,
                        "review_comment" => null,
                        "updated_at"     => date("Y-m-d H:i:s")
                    ]);

                if (!$ok) {
                    $err = $db->error();
                    echo json_encode([
                        "success" => false,
                        "message" => "Failed to re-submit request: " . ($err["message"] ?? "unknown")
                    ]);
                    exit;
                }
            } else {

                // normal: create new pending request
                $req = [
                    "vendor_id"     => $vendor_id,
                    "requested_by"  => $this->login_user->id,
                    "changes"       => json_encode($changes, JSON_UNESCAPED_UNICODE),
                    "status"        => "pending",
                    "deleted"       => 0,
                    "created_at"    => date("Y-m-d H:i:s"),
                    "updated_at"    => date("Y-m-d H:i:s")
                ];

                $this->Vendor_update_requests_model->ci_save($req);
            }


            echo json_encode([
                "success" => true,
                "data" => $this->_bank_row_data($save_id),
                "id" => $save_id,
                "message" => app_lang("record_saved")
            ]);
            return;
        }

        echo json_encode(["success" => false, "message" => app_lang("error_occurred")]);
    }




    function delete_bank_account()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);

        $vendor_id = $this->_require_vendor_access();
        $this->_deny_if_vendor_module_locked($vendor_id, "bank");

        $id = $this->request->getPost("id");

        $row = $this->Vendor_bank_accounts_model->get_one($id);
        if ((int)$row->vendor_id !== (int)$vendor_id) {
            app_redirect("forbidden");
        }

        if ($this->request->getPost("undo")) {
            if ($this->Vendor_bank_accounts_model->delete($id, true)) {
                echo json_encode([
                    "success" => true,
                    "data" => $this->_bank_row_data($id),
                    "message" => app_lang("record_undone")
                ]);
            } else {
                echo json_encode(["success" => false, "message" => app_lang("error_occurred")]);
            }
        } else {
            if ($this->Vendor_bank_accounts_model->delete($id)) {
                echo json_encode(["success" => true, "message" => app_lang("record_deleted")]);
            } else {
                echo json_encode(["success" => false, "message" => app_lang("record_cannot_be_deleted")]);
            }
        }
    }




    public function download_bank_letter_head($id)
    {
        $vendor_id = $this->_require_vendor_access();

        $row = $this->Vendor_bank_accounts_model->get_one($id);

        if (!$row || (int)$row->deleted === 1 || (int)$row->vendor_id !== (int)$vendor_id) {
            show_404();
        }

        if (!$row->letter_head_path) {
            show_404();
        }

        $full_path = WRITEPATH . "uploads/" . $row->letter_head_path;

        if (!is_file($full_path)) {
            show_404();
        }

        return $this->response->download($full_path, null)->setFileName(basename($full_path));
    }



    private function _bank_row_to_array($row): ?array
    {
        if (!$row) return null;

        return [
            "id"              => (int)($row->id ?? 0),
            "vendor_id"       => (int)($row->vendor_id ?? 0),
            "bank_name"       => $row->bank_name ?? null,
            "bank_branch"     => $row->bank_branch ?? null,
            "bank_account_no" => $row->bank_account_no ?? null,
            "bank_swift_code" => $row->bank_swift_code ?? null,
            "iban"            => $row->iban ?? null,
            "letter_head_path" => $row->letter_head_path ?? null,
            "status"          => $row->status ?? null,
            "deleted"         => (int)($row->deleted ?? 0),
        ];
    }

    private function _bank_row_data($id)
    {
        $vendor_id = $this->_require_vendor_access();
        $is_locked = $this->_is_vendor_module_locked($vendor_id, "bank");

        $data = $this->Vendor_bank_accounts_model->get_details([
            "id" => $id,
            "vendor_id" => $vendor_id
        ])->getRow();

        return $this->_make_bank_row($data, $is_locked);
    }

    private function _make_bank_row($data, bool $is_locked = false)
    {
        $approval = $this->_approval_badge($data->status ?? "pending");

        $letter = "-";
        if (!empty($data->letter_head_path)) {
            $letter = anchor(
                get_uri("vendor_portal/download_bank_letter_head/" . $data->id),
                app_lang("download"),
                ["target" => "_blank"]
            );
        }

        $actions = "";
        if (!$is_locked) {
            $actions = modal_anchor(
                get_uri("vendor_portal/bank_account_modal_form"),
                "<i data-feather='edit' class='icon-16'></i>",
                ["class" => "edit", "title" => app_lang("edit"), "data-post-id" => $data->id]
            ) . js_anchor(
                "<i data-feather='x' class='icon-16'></i>",
                [
                    "title" => app_lang("delete"),
                    "class" => "delete",
                    "data-id" => $data->id,
                    "data-action-url" => get_uri("vendor_portal/delete_bank_account"),
                    "data-action" => "delete"
                ]
            );
        }

        return [
            $data->bank_name,
            $data->bank_branch ?: "-",
            $data->bank_account_no,
            $data->bank_swift_code ?: "-",
            $data->iban ?: "-",
            $letter,
            $approval,
            $actions,
        ];
    }



    private function _get_vendor_latest_review_request(int $vendor_id, string $module)
    {
        $db = db_connect();
        $vurTable = $db->prefixTable("vendor_update_requests");

        // Prefer JSON_EXTRACT (works on MySQL 5.7+/8)
        try {
            $sql = "SELECT *
                FROM $vurTable
                WHERE vendor_id=? AND deleted=0 AND status='review'
                  AND JSON_UNQUOTE(JSON_EXTRACT(changes,'$.module')) = ?
                ORDER BY id DESC
                LIMIT 1";
            return $db->query($sql, [$vendor_id, $module])->getRow();
        } catch (\Throwable $e) {
            // Fallback: fetch latest review rows and filter in PHP
            $rows = $db->query(
                "SELECT * FROM $vurTable WHERE vendor_id=? AND deleted=0 AND status='review' ORDER BY id DESC LIMIT 30",
                [$vendor_id]
            )->getResult();

            foreach ($rows as $r) {
                $ch = json_decode($r->changes, true);
                if (($ch["module"] ?? "") === $module) {
                    return $r;
                }
            }
            return null;
        }
    }

    private function _get_vendor_review_request_for_record(int $vendor_id, string $module, int $record_id)
    {
        $db = db_connect();
        $vurTable = $db->prefixTable("vendor_update_requests");

        // Prefer JSON_EXTRACT
        try {
            $sql = "SELECT *
                FROM $vurTable
                WHERE vendor_id=? AND deleted=0 AND status='review'
                  AND JSON_UNQUOTE(JSON_EXTRACT(changes,'$.module')) = ?
                  AND CAST(JSON_UNQUOTE(JSON_EXTRACT(changes,'$.record_id')) AS UNSIGNED) = ?
                ORDER BY id DESC
                LIMIT 1";
            return $db->query($sql, [$vendor_id, $module, $record_id])->getRow();
        } catch (\Throwable $e) {
            // Fallback: fetch and filter in PHP
            $rows = $db->query(
                "SELECT * FROM $vurTable WHERE vendor_id=? AND deleted=0 AND status='review' ORDER BY id DESC LIMIT 60",
                [$vendor_id]
            )->getResult();

            foreach ($rows as $r) {
                $ch = json_decode($r->changes, true);
                if (($ch["module"] ?? "") === $module && (int)($ch["record_id"] ?? 0) === (int)$record_id) {
                    return $r;
                }
            }
            return null;
        }
    }




    private function _approval_badge($status): string
    {
        $status = $status ?: 'pending';

        switch ($status) {
            case 'approved':
                $class = 'bg-success';
                break;
            case 'rejected':
                $class = 'bg-danger';
                break;
            case 'review':
                $class = 'bg-info';
                break;
            case 'pending':
            default:
                $class = 'bg-warning text-dark';
                $status = 'pending';
                break;
        }

        return "<span class='badge {$class}'>" . app_lang($status) . "</span>";
    }





    /**
     * Vendor can submit ONLY ONE request at a time.
     * When there is ANY pending item (contacts/branches/credentials/specialties/documents/bank)
     * or ANY pending row in vendor_update_requests, the vendor portal becomes read-only.
     */


    private function _deny_if_vendor_locked(int $vendor_id)
    {
        if ($this->_is_vendor_module_locked($vendor_id, "contacts")) {
            echo json_encode([
                "success" => false,
                "message" => "Your profile has a pending approval request. You can't make changes until it is reviewed."
            ]);
            exit;
        }
    }

    private function _locked_modal_view()
    {
        return $this->template->view("vendor_portal/locked_modal");
    }

    function index($tab = "")
    {
        return $this->view($tab);
    }

    function view($tab = "")
    {
        $vendor_id = $this->_require_vendor_access();

        $view_data["vendor_info"] = $this->Vendors_model->get_one($vendor_id);
        $view_data["tab"] = $tab;

        return $this->template->rander("vendor_portal/view", $view_data);
    }

    // -------------------------
    // Tabs (load partial views)
    // -------------------------

    function overview()
    {
        $vendor_id = $this->_require_vendor_access();
        $view_data["vendor_info"] = $this->Vendors_model->get_one($vendor_id);

        return $this->template->view("vendor_portal/overview/index", $view_data);
    }

    function contacts()
    {
        $vendor_id = $this->_require_vendor_access();

        $view_data["is_locked"] = $this->_is_vendor_module_locked($vendor_id, "contacts");

        // show latest "review" request comment (if any)
        $view_data["review_request"] = $this->_get_vendor_latest_review_request($vendor_id, "contacts");

        // ✅ IMPORTANT: tabs should return partial view only (NO full layout/scripts)
        return $this->template->view("vendor_portal/contacts/index", $view_data);
    }





    // (next tabs - we’ll implement after contacts)
    function branches()
    {
        $vendor_id = $this->_require_vendor_access();

        // lock only if there's a pending request for branches
        $view_data["is_locked"] = $this->_is_vendor_module_locked($vendor_id, "branches");

        // show latest review comment (if admin marked request as review)
        $view_data["review_request"] = $this->_get_vendor_latest_review_request($vendor_id, "branches");

        // IMPORTANT for tabs: use view() not rander() to avoid JS redeclare issues
        return $this->template->view("vendor_portal/branches/index", $view_data);
    }

    function credentials()
    {
        $vendor_id = $this->_require_vendor_access();

        $view_data["is_locked"] = $this->_is_vendor_module_locked($vendor_id, "credentials");

        // ✅ show latest "review" request comment for this module
        $view_data["review_request"] = $this->_get_vendor_latest_review_request($vendor_id, "credentials");

        return $this->template->view("vendor_portal/credentials/index", $view_data);
    }


    function specialties()
    {
        $vendor_id = $this->_require_vendor_access();

        $view_data["is_locked"] = $this->_is_vendor_module_locked($vendor_id, "specialties");

        // ✅ show latest "review" request comment for this module
        $view_data["review_request"] = $this->_get_vendor_latest_review_request($vendor_id, "specialties");

        return $this->template->view("vendor_portal/specialties/index", $view_data);
    }


    function documents()
    {
        $vendor_id = $this->_require_vendor_access();

        $view_data["is_locked"] = $this->_is_vendor_module_locked($vendor_id, "documents");

        // ✅ NEW: show latest "review" request comment (if any)
        $view_data["review_request"] = $this->_get_vendor_latest_review_request($vendor_id, "documents");

        return $this->template->view("vendor_portal/documents/index", $view_data);
    }



  

   
   

   

   



    private function _specialty_row_to_array($row): ?array
    {
        if (!$row) return null;

        return [
            "id" => (int)($row->id ?? 0),
            "vendor_id" => (int)($row->vendor_id ?? 0),
            "vendor_category_id" => (int)($row->vendor_category_id ?? 0),
            "vendor_sub_category_id" => (int)($row->vendor_sub_category_id ?? 0), // 👈 NEW
            "specialty_type" => $row->specialty_type ?? null,
            "specialty_name" => $row->specialty_name ?? null,
            "specialty_description" => $row->specialty_description ?? null,
            "status" => $row->status ?? null,
            "deleted" => (int)($row->deleted ?? 0),
        ];
    }






    // -------------------------
    // Specialties CRUD endpoints
    // -------------------------

    function specialty_modal_form()
    {
        $this->validate_submitted_data(["id" => "numeric"]);

        $vendor_id = $this->_require_vendor_access();
        $id = $this->request->getPost("id");

        // ✅ Only block EDIT when pending
        if ($id && $this->_is_vendor_module_locked($vendor_id, "specialties")) {
            return $this->_locked_modal_view();
        }

        $model_info = $this->Vendor_specialties_model->get_one($id);

        if ($id && (int)$model_info->vendor_id !== (int)$vendor_id) {
            app_redirect("forbidden");
        }

        $categories = $this->Vendor_categories_model->get_details()->getResult();

        $categories_dropdown = ["" => "- " . app_lang("select_vendor_category") . " -"];
        foreach ($categories as $c) {
            $label = $c->name . ((int)$c->is_active === 0 ? " (inactive)" : "");
            $categories_dropdown[$c->id] = $label;
        }

        $view_data["model_info"] = $model_info;
        $view_data["categories_dropdown"] = $categories_dropdown;

        return $this->template->view("vendor_portal/specialties/modal_form", $view_data);
    }


    function specialties_list_data()
    {
        $vendor_id = $this->_require_vendor_access();
        $is_locked = $this->_is_vendor_module_locked($vendor_id, "specialties");

        $list_data = $this->Vendor_specialties_model->get_details([
            "vendor_id" => $vendor_id
        ])->getResult();

        $result = [];
        foreach ($list_data as $data) {
            $result[] = $this->_make_specialty_row($data, $is_locked);
        }

        echo json_encode(["data" => $result]);
    }


    public function get_vendor_sub_categories_dropdown()
    {
        $vendor_category_id = (int) $this->request->getGet("vendor_category_id");

        $db = Database::connect();
        $table = $db->prefixTable("vendor_sub_categories");

        $rows = $db->query(
            "SELECT id, name FROM $table
         WHERE deleted=0 AND vendor_category_id=?
         ORDER BY name ASC",
            [$vendor_category_id]
        )->getResult();

        $options = "<option value=''>- " . app_lang("select") . " -</option>";
        foreach ($rows as $r) {
            $options .= "<option value='{$r->id}'>{$r->name}</option>";
        }

        echo $options;
    }

    public function save_specialty()
    {
        $this->validate_submitted_data([
            "id" => "numeric",
            "vendor_category_id" => "required|numeric",
            "vendor_sub_category_id" => "required|numeric", // 👈 NEW
            "specialty_type" => "required",
            "specialty_name" => "required"
        ]);

        $vendor_id = $this->_require_vendor_access();


        $id = $this->request->getPost("id");


        // ✅ Only block UPDATE when pending
        if ($id) {
            $this->_deny_if_vendor_module_locked($vendor_id, "specialties");
        }

        // ✅ capture BEFORE (only if editing)
        $before = null;
        if ($id) {
            $row = $this->Vendor_specialties_model->get_one($id);
            if ((int)$row->vendor_id !== (int)$vendor_id) {
                app_redirect("forbidden");
            }
            $before = $this->_specialty_row_to_array($row);
        }

        $data = [
            "vendor_id" => $vendor_id,
            "vendor_category_id" => (int) $this->request->getPost("vendor_category_id"),
            "vendor_sub_category_id" => (int) $this->request->getPost("vendor_sub_category_id"), // 👈 NEW
            "specialty_type" => $this->request->getPost("specialty_type"),
            "specialty_name" => $this->request->getPost("specialty_name"),
            "specialty_description" => $this->request->getPost("specialty_description"),
            "status" => "pending",
        ];


        $clean_data = clean_data($data);

        $save_id = $this->Vendor_specialties_model->ci_save($clean_data, $id);

        if (!$save_id) {
            echo json_encode(["success" => false, "message" => app_lang("error_occurred")]);
            return;
        }

        $changes = [
            "module"    => "specialties",
            "table"     => "vendor_specialties",
            "action"    => $id ? "update" : "create",
            "record_id" => (int)$save_id,
            "before"    => $before,
            "after"     => $clean_data,
        ];

        // ✅ If admin previously set this request to "review", re-submit SAME request as pending
        $db = db_connect();
        $vurTable = $db->prefixTable("vendor_update_requests");

        $existing_review = $this->_get_vendor_review_request_for_record($vendor_id, "specialties", (int)$save_id);

        if ($existing_review) {
            $ok = $db->table($vurTable)
                ->where("id", (int)$existing_review->id)
                ->update([
                    "changes"        => json_encode($changes, JSON_UNESCAPED_UNICODE),
                    "status"         => "pending",
                    "reviewed_by"    => null,
                    "reviewed_at"    => null,
                    "review_comment" => null,
                    "updated_at"     => date("Y-m-d H:i:s"),
                ]);

            if (!$ok) {
                $err = $db->error();
                echo json_encode([
                    "success" => false,
                    "message" => "Failed to re-submit request: " . ($err["message"] ?? "unknown")
                ]);
                return;
            }
        } else {
            // ✅ Normal: create new pending request
            $req = [
                "vendor_id"     => $vendor_id,
                "requested_by"  => $this->login_user->id,
                "changes"       => json_encode($changes, JSON_UNESCAPED_UNICODE),
                "status"        => "pending",
                "deleted"       => 0,
                "created_at"    => date("Y-m-d H:i:s"),
                "updated_at"    => date("Y-m-d H:i:s"),
            ];

            $this->Vendor_update_requests_model->ci_save($req);
        }

        echo json_encode([
            "success" => true,
            "data" => $this->_specialty_row_data($save_id),
            "id" => $save_id,
            "message" => app_lang("record_saved")
        ]);
    }






    public function delete_specialty()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);

        $vendor_id = $this->_require_vendor_access();
        $this->_deny_if_vendor_module_locked($vendor_id, "specialties");

        $id = $this->request->getPost("id");

        $row = $this->Vendor_specialties_model->get_one($id);
        if ((int)$row->vendor_id !== (int)$vendor_id) {
            app_redirect("forbidden");
        }

        if ($this->request->getPost("undo")) {
            if ($this->Vendor_specialties_model->delete($id, true)) {
                echo json_encode([
                    "success" => true,
                    "data" => $this->_specialty_row_data($id),
                    "message" => app_lang("record_undone")
                ]);
            } else {
                echo json_encode(["success" => false, "message" => app_lang("error_occurred")]);
            }
            return;
        }

        if ($this->Vendor_specialties_model->delete($id)) {
            echo json_encode(["success" => true, "message" => app_lang("record_deleted")]);
            return;
        }

        echo json_encode(["success" => false, "message" => app_lang("record_cannot_be_deleted")]);
    }






    private function _specialty_row_data($id)
    {
        $vendor_id = $this->_require_vendor_access();
        $is_locked = $this->_is_vendor_module_locked($vendor_id, "specialties");

        $data = $this->Vendor_specialties_model->get_details([
            "id" => $id,
            "vendor_id" => $vendor_id
        ])->getRow();

        return $this->_make_specialty_row($data, $is_locked);
    }




    private function _make_specialty_row($data, bool $is_locked = false)
    {
        $approval = $this->_approval_badge($data->status ?? "pending");

        $actions = "";
        if (!$is_locked) {
            $actions =
                modal_anchor(
                    get_uri("vendor_portal/specialty_modal_form"),
                    "<i data-feather='edit' class='icon-16'></i>",
                    ["class" => "edit", "data-post-id" => $data->id]
                )
                .
                js_anchor(
                    "<i data-feather='x' class='icon-16'></i>",
                    [
                        "class" => "delete",
                        "data-id" => $data->id,
                        "data-action-url" => get_uri("vendor_portal/delete_specialty"),
                        "data-action" => "delete"
                    ]
                );
        }

        return [
            ucfirst($data->specialty_type ?? "-"),
            $data->vendor_category_name ?: "-",
            $data->vendor_sub_category_name ?? "-", // 👈 NEW
            $data->specialty_name,
            $data->specialty_description ?: "-",
            $approval,
            $actions
        ];
    }



    // -------------------------
    // branches CRUD endpoints
    // -------------------------

    function get_regions_dropdown_by_country()
    {
        $country_id = (int) $this->request->getGet("country_id");

        $db = Database::connect();
        $regions_table = $db->prefixTable("regions");
        $rows = $db->query(
            "SELECT id, name FROM $regions_table
         WHERE deleted=0 AND is_active=1 AND country_id=?
         ORDER BY name ASC",
            [$country_id]
        )->getResult();

        $options = "<option value=''>- " . app_lang("select_region") . " -</option>";
        foreach ($rows as $r) {
            $options .= "<option value='{$r->id}'>{$r->name}</option>";
        }
        echo $options;
    }

    function get_cities_dropdown_by_region()
    {
        $region_id = (int) $this->request->getGet("region_id");

        $db = Database::connect();
        $cities_table = $db->prefixTable("cities");
        $rows = $db->query(
            "SELECT id, name FROM $cities_table
         WHERE deleted=0 AND is_active=1 AND regions_id=?
         ORDER BY name ASC",
            [$region_id]
        )->getResult();

        $options = "<option value=''>- " . app_lang("select_city") . " -</option>";
        foreach ($rows as $c) {
            $options .= "<option value='{$c->id}'>{$c->name}</option>";
        }
        echo $options;
    }



    function branch_modal_form()
    {
        $this->validate_submitted_data(array("id" => "numeric"));




        $vendor_id = $this->_require_vendor_access();





        $id = $this->request->getPost("id");


        // ✅ Only block EDIT when pending
        if ($id && $this->_is_vendor_module_locked($vendor_id, "branches")) {
            return $this->_locked_modal_view(); // your existing locked modal response
        }

        $model_info = $this->Vendor_branches_model->get_one($id);




        if ($id && (int)$model_info->vendor_id !== (int)$vendor_id) {
            app_redirect("forbidden");
        }

        $view_data["model_info"] = $model_info;

        // countries dropdown (full list)
        $view_data["countries_dropdown"] = $this->Country_model->get_dropdown_list(
            array("name"),
            "id",
            array("deleted" => 0)
        );

        // regions dropdown (preload if editing)
        $regions_dropdown = array("" => "- " . app_lang("select_region") . " -");
        if ($model_info->country_id) {
            $regions_dropdown = $this->_regions_dropdown_array((int)$model_info->country_id);
        }
        $view_data["regions_dropdown"] = $regions_dropdown;

        // cities dropdown (preload if editing)
        $cities_dropdown = array("" => "- " . app_lang("select_city") . " -");
        if ($model_info->region_id) {
            $cities_dropdown = $this->_cities_dropdown_array((int)$model_info->region_id);
        }
        $view_data["cities_dropdown"] = $cities_dropdown;

        return $this->template->view("vendor_portal/branches/modal_form", $view_data);
    }

    private function _regions_dropdown_array($country_id)
    {
        $db = Database::connect();
        $regions_table = $db->prefixTable("regions");
        $rows = $db->query(
            "SELECT id, name FROM $regions_table
         WHERE deleted=0 AND is_active=1 AND country_id=?
         ORDER BY name ASC",
            [$country_id]
        )->getResult();

        $dropdown = array("" => "- " . app_lang("select_region") . " -");
        foreach ($rows as $r) {
            $dropdown[$r->id] = $r->name;
        }
        return $dropdown;
    }

    private function _cities_dropdown_array($region_id)
    {
        $db = Database::connect();
        $cities_table = $db->prefixTable("cities");
        $rows = $db->query(
            "SELECT id, name FROM $cities_table
         WHERE deleted=0 AND is_active=1 AND regions_id=?
         ORDER BY name ASC",
            [$region_id]
        )->getResult();

        $dropdown = array("" => "- " . app_lang("select_city") . " -");
        foreach ($rows as $c) {
            $dropdown[$c->id] = $c->name;
        }
        return $dropdown;
    }



    function branches_list_data()
    {
        $vendor_id = $this->_require_vendor_access();

        $list_data = $this->Vendor_branches_model->get_details(array(
            "vendor_id" => $vendor_id
        ))->getResult();


        $is_locked = $this->_is_vendor_module_locked($vendor_id, "branches");

        $result = array();
        foreach ($list_data as $data) {
            $result[] = $this->_make_branch_row($data, $is_locked);
        }

        echo json_encode(array("data" => $result));
    }



    function save_branch()
    {
        try {
            $this->validate_submitted_data(array(
                "id" => "numeric",
                "name" => "required",
                "country_id" => "required|numeric",
                "region_id"  => "required|numeric",
                "city_id"    => "required|numeric",
                "email" => "permit_empty|valid_email"
            ));

            $vendor_id = $this->_require_vendor_access();

            $id = $this->request->getPost("id");


            // ✅ Only block UPDATE when pending
            if ($id) {
                $this->_deny_if_vendor_module_locked($vendor_id, "branches");
            }

            $before = null;
            if ($id) {
                $row = $this->Vendor_branches_model->get_one($id);
                if ((int)$row->vendor_id !== (int)$vendor_id) {
                    app_redirect("forbidden");
                }
                $before = $row;
            }

            if ($id) {
                $row = $this->Vendor_branches_model->get_one($id);
                if ((int)$row->vendor_id !== (int)$vendor_id) {
                    app_redirect("forbidden");
                }
            }

            $data = array(
                "vendor_id"  => $vendor_id,
                "name"       => $this->request->getPost("name"),
                "address"    => $this->request->getPost("address"),
                "country_id" => (int) $this->request->getPost("country_id"),
                "region_id"  => (int) $this->request->getPost("region_id"),
                "city_id"    => (int) $this->request->getPost("city_id"),
                "phone"      => $this->request->getPost("phone"),
                "email"      => $this->request->getPost("email"),
                "is_main"    => $this->request->getPost("is_main") ? 1 : 0,
                "is_active"  => $this->request->getPost("is_active") ? 1 : 0,

                // approval workflow
                "status"         => "pending",

            );

            $data = clean_data($data);

            $save_id = $this->Vendor_branches_model->ci_save($data, $id);

            if ($save_id) {

                $changes = [
                    "module"    => "branches",
                    "table"     => "vendor_branches",
                    "action"    => $id ? "update" : "create",
                    "record_id" => (int)$save_id,
                    "before"    => $before,
                    "after"     => $data,
                ];

                // create (or re-submit) approval request
                $db = db_connect();
                $vurTable = $db->prefixTable("vendor_update_requests");

                // If admin previously marked it as "review", re-submit SAME request as pending
                $existing_review = $this->_get_vendor_review_request_for_record($vendor_id, "branches", (int)$save_id);

                if ($existing_review) {
                    $ok = $db->table($vurTable)
                        ->where("id", (int)$existing_review->id)
                        ->update([
                            "changes"        => json_encode($changes, JSON_UNESCAPED_UNICODE),
                            "status"         => "pending",
                            "reviewed_by"    => null,
                            "reviewed_at"    => null,
                            "review_comment" => null,
                            "updated_at"     => date("Y-m-d H:i:s")
                        ]);

                    if (!$ok) {
                        $err = $db->error();
                        echo json_encode(["success" => false, "message" => "Failed to re-submit request: " . ($err["message"] ?? "unknown")]);
                        exit;
                    }
                } else {
                    // Normal: create new pending request
                    $req = [
                        "vendor_id"     => $vendor_id,
                        "requested_by"  => $this->login_user->id,
                        "changes"       => json_encode($changes, JSON_UNESCAPED_UNICODE),
                        "status"        => "pending",
                        "deleted"       => 0,
                        "created_at"    => date("Y-m-d H:i:s"),
                        "updated_at"    => date("Y-m-d H:i:s")
                    ];

                    $this->Vendor_update_requests_model->ci_save($req);
                }



                echo json_encode([
                    "success" => true,
                    "data" => $this->_branch_row_data($save_id),
                    "id" => $save_id,
                    "message" => app_lang("record_saved")
                ]);
                return;
            } else {
                echo json_encode(array("success" => false, "message" => app_lang("error_occurred")));
            }
        } catch (\Throwable $e) {
            log_message("error", "save_branch error: " . $e->getMessage());
            echo json_encode(array("success" => false, "message" => $e->getMessage()));
        }
    }



    function delete_branch()
    {
        $this->validate_submitted_data(array("id" => "required|numeric"));

        $vendor_id = $this->_require_vendor_access();


        $id = $this->request->getPost("id");

        $row = $this->Vendor_branches_model->get_one($id);
        if ((int)$row->vendor_id !== (int)$vendor_id) {
            app_redirect("forbidden");
        }

        if ($this->request->getPost("undo")) {
            if ($this->Vendor_branches_model->delete($id, true)) {
                echo json_encode(array("success" => true, "data" => $this->_branch_row_data($id), "message" => app_lang("record_undone")));
            } else {
                echo json_encode(array("success" => false, "message" => app_lang("error_occurred")));
            }
        } else {
            if ($this->Vendor_branches_model->delete($id)) {
                echo json_encode(array("success" => true, "message" => app_lang("record_deleted")));
            } else {
                echo json_encode(array("success" => false, "message" => app_lang("record_cannot_be_deleted")));
            }
        }
    }

    private function _branch_row_data($id)
    {
        $vendor_id = $this->_require_vendor_access();

        $data = $this->Vendor_branches_model->get_details(array(
            "id" => $id,
            "vendor_id" => $vendor_id
        ))->getRow();

        return $this->_make_branch_row($data);
    }

    private function _make_branch_row($data, bool $is_locked = false)
    {
        $active = $data->is_active
            ? "<span class='badge bg-success'>" . app_lang("active") . "</span>"
            : "<span class='badge bg-secondary'>" . app_lang("inactive") . "</span>";

        $main = !empty($data->is_main)
            ? "<span class='badge bg-primary'>" . app_lang("primary") . "</span>"
            : "";

        $approval = $this->_approval_badge($data->status ?? "pending");

        $actions = "";
        if (!$is_locked) {
            $actions =
                modal_anchor(
                    get_uri("vendor_portal/branch_modal_form"),
                    "<i data-feather='edit' class='icon-16'></i>",
                    [
                        "class" => "edit",
                        "title" => app_lang("edit"),
                        "data-post-id" => $data->id
                    ]
                )
                .
                js_anchor(
                    "<i data-feather='x' class='icon-16'></i>",
                    [
                        "title" => app_lang("delete"),
                        "class" => "delete",
                        "data-id" => $data->id,
                        "data-action-url" => get_uri("vendor_portal/delete_branch"),
                        "data-action" => "delete"
                    ]
                );
        }

        return [
            $data->name,                 // branch name
            $data->email ?: "-",
            $data->phone ?: "-",
            $data->country_name ?: "-",
            $data->region_name ?: "-",
            $data->city_name ?: "-",
            $main,
            $approval,
            $active,
            $actions
        ];
    }



    public function documents_list_data()
    {
        $vendor_id = $this->_require_vendor_access();
        $is_locked = $this->_is_vendor_module_locked($vendor_id, "documents");

        $list_data = $this->Vendor_documents_model
            ->get_details(["vendor_id" => $vendor_id])
            ->getResult();

        $result = [];
        foreach ($list_data as $row) {
            $result[] = $this->_make_document_row($row, $is_locked);
        }

        return $this->response->setJSON(["data" => $result]);
    }



    public function document_modal_form()
    {
        $this->validate_submitted_data(["id" => "numeric"]);

        $vendor_id = $this->_require_vendor_access();



        $id = $this->request->getPost("id");


        // ✅ Only block EDIT when pending
        if ($id && $this->_is_vendor_module_locked($vendor_id, "documents")) {
            return $this->_locked_modal_view();
        }


        $model_info = $this->Vendor_documents_model->get_one($id);

        if ($id && (int)$model_info->vendor_id !== (int)$vendor_id) {
            app_redirect("forbidden");
        }

        $types = $this->Vendor_document_types_model
            ->get_all_where(["deleted" => 0])
            ->getResult();

        $types_dropdown = ["" => "-"];
        foreach ($types as $t) {
            $types_dropdown[$t->id] = $t->name;
        }

        $view_data["model_info"] = $model_info;
        $view_data["types_dropdown"] = $types_dropdown;

        return $this->template->view("vendor_portal/documents/modal_form", $view_data);
    }




    public function save_document()
    {
        $this->validate_submitted_data([
            "id" => "numeric",
            "vendor_document_type_id" => "required|numeric",
        ]);

        $vendor_id = $this->_require_vendor_access();


        $id = $this->request->getPost("id");


        // ✅ Only block UPDATE when pending
        if ($id) {
            $this->_deny_if_vendor_module_locked($vendor_id, "documents");
        }

        // ✅ BEFORE snapshot (only if editing)
        $before = null;
        if ($id) {
            $old = $this->Vendor_documents_model->get_one($id);
            if ($old && (int)$old->vendor_id !== (int)$vendor_id) {
                app_redirect("forbidden");
            }
            $before = $old; // you can convert to array if you prefer
        }

        $data = [
            "vendor_id" => $vendor_id,
            "vendor_document_type_id" => (int)$this->request->getPost("vendor_document_type_id"),
            "issued_at" => $this->request->getPost("issued_at") ?: null,
            "expires_at" => $this->request->getPost("expires_at") ?: null,
            "status" => "pending",
        ];

        $file = $this->request->getFile("file");
        $has_new_file = $file && $file->isValid() && !$file->hasMoved();

        if (!$id && !$has_new_file) {
            return $this->response->setJSON([
                "success" => false,
                "message" => app_lang("file_is_required")
            ]);
        }

        if ($has_new_file) {
            $upload_dir = WRITEPATH . "uploads/vendor_documents/vendor_" . $vendor_id . "/";
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0775, true);
            }

            $new_name = uniqid("vd_", true) . "." . $file->getExtension();
            $file->move($upload_dir, $new_name);

            $data["disk"] = "local";
            $data["path"] = "vendor_documents/vendor_" . $vendor_id . "/" . $new_name;
            $data["original_name"] = $file->getClientName();
            $data["mime_type"] = $file->getClientMimeType();
            $data["size_bytes"] = $file->getSize();
            $data["uploaded_by"] = $this->login_user->id;
        }

        $clean_data = clean_data($data);
        $save_id = $this->Vendor_documents_model->ci_save($clean_data, $id);

        if (!$save_id) {
            return $this->response->setJSON([
                "success" => false,
                "message" => app_lang("error_occurred")
            ]);
        }

        // ✅ build request changes
        $changes = [
            "module"    => "documents",
            "table"     => "vendor_documents",
            "action"    => $id ? "update" : "create",
            "record_id" => (int)$save_id,
            "before"    => $before,
            "after"     => $clean_data,
        ];

        // ✅ If admin previously marked it as "review", re-submit SAME request as pending
        $db = db_connect();
        $vurTable = $db->prefixTable("vendor_update_requests");

        $existing_review = $this->_get_vendor_review_request_for_record($vendor_id, "documents", (int)$save_id);

        if ($existing_review) {
            $ok = $db->table($vurTable)
                ->where("id", (int)$existing_review->id)
                ->update([
                    "changes"        => json_encode($changes, JSON_UNESCAPED_UNICODE),
                    "status"         => "pending",
                    "reviewed_by"    => null,
                    "reviewed_at"    => null,
                    "review_comment" => null,
                    "updated_at"     => date("Y-m-d H:i:s")
                ]);

            if (!$ok) {
                $err = $db->error();
                return $this->response->setJSON([
                    "success" => false,
                    "message" => "Failed to re-submit request: " . ($err["message"] ?? "unknown")
                ]);
            }
        } else {
            // ✅ Normal: create new pending request
            $req = [
                "vendor_id"     => $vendor_id,
                "requested_by"  => $this->login_user->id,
                "changes"       => json_encode($changes, JSON_UNESCAPED_UNICODE),
                "status"        => "pending",
                "deleted"       => 0,
                "created_at"    => date("Y-m-d H:i:s"),
                "updated_at"    => date("Y-m-d H:i:s")
            ];

            $this->Vendor_update_requests_model->ci_save($req);
        }

        return $this->response->setJSON([
            "success" => true,
            "data" => $this->_document_row_data($save_id),
            "id" => $save_id,
            "message" => app_lang("record_saved"),
        ]);
    }




    public function download_document($id)
    {
        $doc = $this->Vendor_documents_model->get_one($id);
        $vendor_id = $this->_require_vendor_access();

        if (!$doc || (int)$doc->deleted === 1 || (int)$doc->vendor_id !== (int)$vendor_id) {
            show_404();
        }

        $full_path = WRITEPATH . "uploads/" . $doc->path;

        if (!is_file($full_path)) {
            show_404();
        }

        $download_name = $doc->original_name ?: basename($full_path);

        return $this->response->download($full_path, null)->setFileName($download_name);
    }

    public function delete_document()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);

        $vendor_id = $this->_require_vendor_access();

        // ✅ keep delete locked during pending
        $this->_deny_if_vendor_module_locked($vendor_id, "documents");

        $id = (int)$this->request->getPost("id");

        $doc = $this->Vendor_documents_model->get_one($id);
        if (!$doc || (int)$doc->vendor_id !== (int)$vendor_id) {
            app_redirect("forbidden");
        }

        if ($this->Vendor_documents_model->delete($id)) {
            return $this->response->setJSON([
                "success" => true,
                "message" => app_lang("record_deleted")
            ]);
        }

        return $this->response->setJSON([
            "success" => false,
            "message" => app_lang("error_occurred")
        ]);
    }





    private function _document_row_data($id)
    {
        $vendor_id = $this->_require_vendor_access();
        $is_locked = $this->_is_vendor_module_locked($vendor_id, "documents");

        $data = $this->Vendor_documents_model->get_details([
            "id" => $id,
            "vendor_id" => $vendor_id
        ])->getRow();

        return $this->_make_document_row($data, $is_locked);
    }





    private function _make_document_row($data, bool $is_locked = false)
    {
        $file_link = anchor(
            get_uri("vendor_portal/download_document/" . $data->id),
            esc($data->original_name ?: app_lang("download")),
            ["target" => "_blank"]
        );

        $issued  = $data->issued_at ? format_to_date($data->issued_at, false) : "-";
        $expires = $data->expires_at ? format_to_date($data->expires_at, false) : "-";

        $approval = $this->_approval_badge($data->status ?? "pending");

        $size = $data->size_bytes
            ? number_format($data->size_bytes / 1024, 2) . " KB"
            : "-";

        $uploaded_by = $data->uploaded_by_name ?? "-";

        $actions = "";
        if (!$is_locked) {
            $actions =
                modal_anchor(
                    get_uri("vendor_portal/document_modal_form"),
                    "<i data-feather='edit' class='icon-16'></i>",
                    ["class" => "edit", "data-post-id" => $data->id]
                )
                .
                js_anchor(
                    "<i data-feather='x' class='icon-16'></i>",
                    [
                        "class" => "delete",
                        "data-id" => $data->id,
                        "data-action-url" => get_uri("vendor_portal/delete_document"),
                        "data-action" => "delete",
                    ]
                );
        }

        return [
            $data->document_type_name ?? "-",
            $file_link,
            $issued,
            $expires,
            $approval,
            $size,
            $uploaded_by,
            $actions,
        ];
    }



    // -------------------------
    // Credentials CRUD endpoints
    // -------------------------

    function credential_modal_form()
    {
        $this->validate_submitted_data(["id" => "numeric"]);

        $vendor_id = $this->_require_vendor_access();
        $id = $this->request->getPost("id");

        // ✅ Only block EDIT when pending (id exists)
        if ($id && $this->_is_vendor_module_locked($vendor_id, "credentials")) {
            return $this->_locked_modal_view();
        }

        $model_info = $this->Vendor_credentials_model->get_one($id);

        // prevent opening another vendor’s credential
        if ($id && (int)$model_info->vendor_id !== (int)$vendor_id) {
            app_redirect("forbidden");
        }

        $view_data["model_info"] = $model_info;

        // dropdown options for enum('cr','vat','other')
        $view_data["type_dropdown"] = [
            "cr"    => "CR",
            "vat"   => "VAT",
            "other" => "Other",
        ];

        return $this->template->view("vendor_portal/credentials/modal_form", $view_data);
    }


    function credentials_list_data()
    {
        $vendor_id = $this->_require_vendor_access();
        $is_locked = $this->_is_vendor_module_locked($vendor_id, "credentials");

        $list_data = $this->Vendor_credentials_model->get_details(array(
            "vendor_id" => $vendor_id
        ))->getResult();

        $result = array();
        foreach ($list_data as $data) {
            $result[] = $this->_make_credential_row($data, $is_locked);
        }

        echo json_encode(array("data" => $result));
    }

    function save_credential()
    {
        $this->validate_submitted_data([
            "id" => "numeric",
            "type" => "required",
            "number" => "required"
        ]);

        $vendor_id = $this->_require_vendor_access();
        $id = $this->request->getPost("id");

        // ✅ Only block UPDATE when pending (id exists)
        if ($id) {
            $this->_deny_if_vendor_module_locked($vendor_id, "credentials");
        }

        // ✅ BEFORE snapshot (only if editing)
        $before = null;
        if ($id) {
            $row = $this->Vendor_credentials_model->get_one($id);
            if ((int)$row->vendor_id !== (int)$vendor_id) {
                app_redirect("forbidden");
            }
            $before = $row;
        }

        $data = [
            "vendor_id"   => $vendor_id,
            "type"        => $this->request->getPost("type"),
            "number"      => $this->request->getPost("number"),
            "issue_date"  => $this->request->getPost("issue_date") ?: null,
            "expiry_date" => $this->request->getPost("expiry_date") ?: null,
            "notes"       => $this->request->getPost("notes"),
            "status"      => "pending",
        ];

        $data = clean_data($data);

        $save_id = $this->Vendor_credentials_model->ci_save($data, $id);

        if (!$save_id) {
            echo json_encode(["success" => false, "message" => app_lang("error_occurred")]);
            return;
        }

        // ✅ Approval request payload
        $changes = [
            "module"    => "credentials",
            "table"     => "vendor_credentials",
            "action"    => $id ? "update" : "create",
            "record_id" => (int)$save_id,
            "before"    => $before,
            "after"     => $data,
        ];

        $db = db_connect();
        $vurTable = $db->prefixTable("vendor_update_requests");

        // ✅ If there is an existing REVIEW request for this record, re-submit it as pending
        $existing_review = $this->_get_vendor_review_request_for_record($vendor_id, "credentials", (int)$save_id);

        if ($existing_review) {
            $ok = $db->table($vurTable)
                ->where("id", (int)$existing_review->id)
                ->update([
                    "changes"        => json_encode($changes, JSON_UNESCAPED_UNICODE),
                    "status"         => "pending",
                    "reviewed_by"    => null,
                    "reviewed_at"    => null,
                    "review_comment" => null,
                    "updated_at"     => date("Y-m-d H:i:s")
                ]);

            if (!$ok) {
                $err = $db->error();
                echo json_encode([
                    "success" => false,
                    "message" => "Failed to re-submit request: " . ($err["message"] ?? "unknown")
                ]);
                return;
            }
        } else {
            // ✅ Normal create: new pending request
            $req = [
                "vendor_id"    => $vendor_id,
                "requested_by" => $this->login_user->id,
                "changes"      => json_encode($changes, JSON_UNESCAPED_UNICODE),
                "status"       => "pending",
                "deleted"      => 0,
                "created_at"   => date("Y-m-d H:i:s"),
                "updated_at"   => date("Y-m-d H:i:s"),
            ];

            $this->Vendor_update_requests_model->ci_save($req);
        }

        echo json_encode([
            "success" => true,
            "data"    => $this->_credential_row_data($save_id),
            "id"      => $save_id,
            "message" => app_lang("record_saved")
        ]);
    }


    function delete_credential()
    {
        $this->validate_submitted_data(array("id" => "required|numeric"));

        $vendor_id = $this->_require_vendor_access();
        $id = $this->request->getPost("id");

        $row = $this->Vendor_credentials_model->get_one($id);


        $this->_deny_if_vendor_module_locked($vendor_id, "credentials");

        if ((int)$row->vendor_id !== (int)$vendor_id) {
            app_redirect("forbidden");
        }

        if ($this->request->getPost("undo")) {
            if ($this->Vendor_credentials_model->delete($id, true)) {
                echo json_encode(array(
                    "success" => true,
                    "data" => $this->_credential_row_data($id),
                    "message" => app_lang("record_undone")
                ));
            } else {
                echo json_encode(array("success" => false, "message" => app_lang("error_occurred")));
            }
        } else {
            if ($this->Vendor_credentials_model->delete($id)) {
                echo json_encode(array("success" => true, "message" => app_lang("record_deleted")));
            } else {
                echo json_encode(array("success" => false, "message" => app_lang("record_cannot_be_deleted")));
            }
        }
    }

    private function _credential_row_data($id)
    {
        $vendor_id = $this->_require_vendor_access();
        $is_locked = $this->_is_vendor_module_locked($vendor_id, "credentials");

        $data = $this->Vendor_credentials_model->get_details([
            "id" => $id,
            "vendor_id" => $vendor_id
        ])->getRow();

        return $this->_make_credential_row($data, $is_locked);
    }


    private function _make_credential_row($data, bool $is_locked = false)
    {
        $issue  = $data->issue_date ? format_to_date($data->issue_date, false) : "-";
        $expiry = $data->expiry_date ? format_to_date($data->expiry_date, false) : "-";

        $approval = $this->_approval_badge($data->status ?? "pending");

        $actions = "";

        if (!$is_locked) {
            $actions =
                modal_anchor(
                    get_uri("vendor_portal/credential_modal_form"),
                    "<i data-feather='edit' class='icon-16'></i>",
                    [
                        "class" => "edit",
                        "title" => app_lang("edit"),
                        "data-post-id" => $data->id
                    ]
                )
                .
                js_anchor(
                    "<i data-feather='x' class='icon-16'></i>",
                    [
                        "title" => app_lang("delete"),
                        "class" => "delete",
                        "data-id" => $data->id,
                        "data-action-url" => get_uri("vendor_portal/delete_credential"),
                        "data-action" => "delete"
                    ]
                );
        }

        return [
            strtoupper($data->type),
            $data->number,
            $issue,
            $expiry,
            $data->notes ?: "-",
            $approval,
            $actions
        ];
    }





    // -------------------------
    // Contacts CRUD endpoints
    // -------------------------

    function contact_modal_form()
    {
        $this->validate_submitted_data(["id" => "numeric"]);

        $vendor_id = $this->_require_vendor_access();
        $id = $this->request->getPost("id");

        // ✅ Only block EDIT when pending (not ADD)
        if ($id && $this->_is_vendor_module_locked($vendor_id, "contacts")) {
            return $this->_locked_modal_view();
        }

        $model_info = $this->Vendor_contacts_model->get_one($id);

        // prevent opening another vendor’s contact
        if ($id && (int)$model_info->vendor_id !== (int)$vendor_id) {
            app_redirect("forbidden");
        }

        $view_data["model_info"] = $model_info;
        return $this->template->view("vendor_portal/contacts/modal_form", $view_data);
    }


    function contacts_list_data()
    {
        $vendor_id = $this->_require_vendor_access();

        $is_locked = $this->_is_vendor_module_locked($vendor_id, "contacts");

        $list_data = $this->Vendor_contacts_model->get_details(array(
            "vendor_id" => $vendor_id
        ))->getResult();

        $result = array();
        foreach ($list_data as $data) {
            $result[] = $this->_make_contact_row($data, $is_locked);
        }

        echo json_encode(array("data" => $result));
    }

    function save_contact()
    {
        $this->validate_submitted_data([
            "id" => "numeric",
            "contacts_name" => "required",
            "email" => "permit_empty|valid_email"
        ]);

        $vendor_id = $this->_require_vendor_access();


        $id = $this->request->getPost("id");


        if ($id) {
            $this->_deny_if_vendor_module_locked($vendor_id, "contact");
        }

        // capture "before" ONLY if editing
        $before = null;
        if ($id) {
            $row = $this->Vendor_contacts_model->get_one($id);
            if ((int)$row->vendor_id !== (int)$vendor_id) {
                app_redirect("forbidden");
            }
            $before = $row;
        }

        $data = [
            "vendor_id"     => $vendor_id,
            "contacts_name" => $this->request->getPost("contacts_name"),
            "phone"         => $this->request->getPost("phone"),
            "fax"           => $this->request->getPost("fax"),
            "designation"   => $this->request->getPost("designation"),
            "email"         => $this->request->getPost("email"),
            "email_2"       => $this->request->getPost("email_2"),
            "mobile"        => $this->request->getPost("mobile"),
            "role"          => $this->request->getPost("role"),
            "is_primary"    => $this->request->getPost("is_primary") ? 1 : 0,
            "is_active"     => $this->request->getPost("is_active") ? 1 : 0,
            "status"        => "pending",
        ];

        $data = clean_data($data);

        $save_id = $this->Vendor_contacts_model->ci_save($data, $id);

        if ($save_id) {
            // create approval request
            $changes = [
                "module"    => "contacts",
                "table"     => "vendor_contacts",
                "action"    => $id ? "update" : "create",
                "record_id" => (int)$save_id,
                "before"    => $before,
                "after"     => $data,
            ];


            // create (or re-submit) approval request
            $db = db_connect();
            $vurTable = $db->prefixTable("vendor_update_requests");

            $existing_review = $this->_get_vendor_review_request_for_record($vendor_id, "contacts", (int)$save_id);

            if ($existing_review) {
                // ✅ If admin previously marked it as "review", re-submit SAME request as pending
                $ok = $db->table($vurTable)
                    ->where("id", (int)$existing_review->id)
                    ->update([
                        "changes"        => json_encode($changes, JSON_UNESCAPED_UNICODE),
                        "status"         => "pending",
                        "reviewed_by"    => null,
                        "reviewed_at"    => null,
                        "review_comment" => null,
                        "updated_at"     => date("Y-m-d H:i:s")
                    ]);

                if (!$ok) {
                    $err = $db->error();
                    echo json_encode(["success" => false, "message" => "Failed to re-submit request: " . ($err["message"] ?? "unknown")]);
                    exit;
                }
            } else {
                // ✅ Normal: create new pending request
                $req = [
                    "vendor_id"     => $vendor_id,
                    "requested_by"  => $this->login_user->id,
                    "changes"       => json_encode($changes, JSON_UNESCAPED_UNICODE),
                    "status"        => "pending",
                    "deleted"       => 0,
                    "created_at"    => date("Y-m-d H:i:s"),
                    "updated_at"    => date("Y-m-d H:i:s")
                ];

                $this->Vendor_update_requests_model->ci_save($req);
            }







            echo json_encode([
                "success" => true,
                "data" => $this->_contact_row_data($save_id),
                "id" => $save_id,
                "message" => app_lang("record_saved")
            ]);
            return;
        }

        echo json_encode(["success" => false, "message" => app_lang("error_occurred")]);
    }




    function delete_contact()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);

        $vendor_id = $this->_require_vendor_access();

        // ✅ Block delete while pending
        $this->_deny_if_vendor_module_locked($vendor_id, "contacts");

        $id = $this->request->getPost("id");

        $row = $this->Vendor_contacts_model->get_one($id);
        if ((int)$row->vendor_id !== (int)$vendor_id) {
            app_redirect("forbidden");
        }

        if ($this->request->getPost("undo")) {
            if ($this->Vendor_contacts_model->delete($id, true)) {
                echo json_encode([
                    "success" => true,
                    "data" => $this->_contact_row_data($id),
                    "message" => app_lang("record_undone")
                ]);
            } else {
                echo json_encode(["success" => false, "message" => app_lang("error_occurred")]);
            }
            return;
        }

        if ($this->Vendor_contacts_model->delete($id)) {
            echo json_encode(["success" => true, "message" => app_lang("record_deleted")]);
            return;
        }

        echo json_encode(["success" => false, "message" => app_lang("record_cannot_be_deleted")]);
    }



    private function _contact_row_data($id)
    {
        $vendor_id = $this->_require_vendor_access();
        $is_locked = $this->_is_vendor_module_locked($vendor_id, "contacts");

        $data = $this->Vendor_contacts_model->get_details(array(
            "id" => $id,
            "vendor_id" => $vendor_id
        ))->getRow();

        return $this->_make_contact_row($data, $is_locked);
    }

    private function _make_contact_row($data, bool $is_locked = false)
    {
        $active = $data->is_active
            ? "<span class='badge bg-success'>" . app_lang("active") . "</span>"
            : "<span class='badge bg-secondary'>" . app_lang("inactive") . "</span>";

        $primary = $data->is_primary
            ? "<span class='badge bg-primary'>" . app_lang("primary") . "</span>"
            : "";

        $approval = $this->_approval_badge($data->status ?? "pending");

        $actions = "";
        if (!$is_locked) {
            $actions = modal_anchor(
                get_uri("vendor_portal/contact_modal_form"),
                "<i data-feather='edit' class='icon-16'></i>",
                array("class" => "edit", "title" => app_lang("edit"), "data-post-id" => $data->id)
            ) . js_anchor(
                "<i data-feather='x' class='icon-16'></i>",
                array(
                    "title" => app_lang("delete"),
                    "class" => "delete",
                    "data-id" => $data->id,
                    "data-action-url" => get_uri("vendor_portal/delete_contact"),
                    "data-action" => "delete"
                )
            );
        }

        return array(
            $data->contacts_name,
            $data->designation,
            $data->email,
            $data->mobile,
            $primary,
            $approval,
            $active,
            $actions
        );
    }
}
