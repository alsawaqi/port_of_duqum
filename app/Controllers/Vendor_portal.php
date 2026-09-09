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
use App\Models\Vendor_users_model;
use App\Models\Auth_security_model;
use App\Models\Tenders_model;
use App\Models\Tender_documents_model;
use App\Models\Tender_bids_model;
use App\Models\Tender_bid_documents_model;
use App\Models\Tender_bid_item_prices_model;
use App\Models\Tender_bid_requirements_model;
use App\Models\Tender_communications_model;
use App\Models\Tender_evaluations_model;
use App\Models\Tender_rfq_details_model;
use App\Models\Tender_rfq_items_model;
use App\Libraries\Vendor_contact_access;
use App\Libraries\Vendor_portal_authorizer;
use App\Libraries\Payments\Eservice_payment_manager;
use App\Libraries\Payments\Eservice_payment_state;
use App\Libraries\Payments\Vendor_billing_service;
use App\Libraries\Upload_security;
use App\Libraries\UploadSecurityException;


class Vendor_portal extends Security_Controller
{
    protected $db;
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
    protected $Tender_bid_item_prices_model;
    protected $Tender_bid_requirements_model;
    protected $Tender_communications_model;
    protected $Tender_rfq_details_model;
    protected $Tender_rfq_items_model;

    protected $Tender_evaluations_model;

    protected $Vendor_bank_accounts_model;
    protected $Vendor_contact_access;
    protected $Vendor_users_model;
    protected $Auth_security_model;
    protected $Vendor_portal_authorizer;
    private $active_vendor_membership = null;
    private bool $active_vendor_membership_resolved = false;
    // cached per-request (avoid repeating lock checks)
    private array $vendor_module_locked_cache = [];

    function __construct()
    {
        parent::__construct();

        $this->db = db_connect();

        // Vendor Portal is for staff users (vendor logins should be staff)
        if ($this->login_user->user_type !== "staff") {
            log_message("warning", "VENDOR PORTAL ACCESS DENIED: non_staff_user " . json_encode([
                "user_id" => (int) ($this->login_user->id ?? 0),
                "user_type" => (string) ($this->login_user->user_type ?? ""),
                "active_vendor_id" => (int) $this->session->get(Vendor_users_model::SESSION_VENDOR_ID),
            ]));
            app_redirect("forbidden");
        }

        $this->Vendors_model = new Vendors_model();
        $this->Vendor_contacts_model = new Vendor_contacts_model();
        $this->Vendor_contact_access = new Vendor_contact_access($this->db);
        $this->Vendor_users_model = new Vendor_users_model();
        $this->Auth_security_model = new Auth_security_model();
        $this->Vendor_portal_authorizer = new Vendor_portal_authorizer();

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
        $this->Tender_bid_item_prices_model = new Tender_bid_item_prices_model();
        $this->Tender_bid_requirements_model = new Tender_bid_requirements_model();
        $this->Tender_communications_model = new Tender_communications_model();
        $this->Tender_evaluations_model = new Tender_evaluations_model();
        $this->Tender_rfq_details_model = new Tender_rfq_details_model();
        $this->Tender_rfq_items_model = new Tender_rfq_items_model();
    }


    function tenders()
    {
        $this->_require_vendor_tender_access();
        return $this->template->view("vendor_portal/tenders/index");
    }

    function tenders_list_data()
    {
        $vendor_id = $this->_require_vendor_tender_access();

        $list_data = $this->Tenders_model->get_vendor_visible_tenders($vendor_id)->getResult();

        $result = [];
        foreach ($list_data as $row) {
            $result[] = $this->_make_tender_row($row);
        }

        echo json_encode(["data" => $result]);
    }

    function tender($id = 0)
    {
        $vendor_id = $this->_require_vendor_tender_access();
        $tender_id = (int) $id;
        if (!$tender_id) {
            show_404();
        }

        $view_data = $this->_get_vendor_tender_view_data($tender_id, $vendor_id);
        if (!$view_data) {
            app_redirect("forbidden");
        }

        return $this->template->rander("vendor_portal/tenders/details", $view_data);
    }

    function tender_view_modal()
    {
        $this->validate_submitted_data([
            "id" => "required|numeric"
        ]);

        $vendor_id = $this->_require_vendor_tender_access();
        $tender_id = (int) $this->request->getPost("id");

        $view_data = $this->_get_vendor_tender_view_data($tender_id, $vendor_id);
        if (!$view_data) {
            app_redirect("forbidden");
        }

        return $this->template->view("vendor_portal/tenders/view_modal", $view_data);
    }

    private function _get_vendor_tender_view_data(int $tender_id, int $vendor_id): ?array
    {
        $tender = $this->Tenders_model->get_vendor_visible_tender($tender_id, $vendor_id);
        if (!$tender) {
            return null;
        }

        $this->_mark_tender_invite_opened($tender_id, $vendor_id);

        $docs = $this->Tender_documents_model->get_details([
            "tender_id" => $tender_id
        ])->getResult();

        $bid = $this->Tender_bids_model->get_vendor_bid($tender_id, $vendor_id);
        $required_sections = $this->Tender_bid_requirements_model->get_required_codes($tender_id);
        $clarifications = $this->Tender_communications_model->get_clarification_conversation($tender_id, $vendor_id, true);
        $clarification_attachments = $this->Tender_communications_model->get_attachments_map(array_map(fn($item) => (int) $item->id, $clarifications));
        $rfq_items = $this->Tender_rfq_items_model->get_by_tender($tender_id);
        $documents_map = [];
        $bid_item_price_map = [];
        $bid_item_price_rows = [];

        $latest_commercial_evaluation = null;
        $is_awarded_to_vendor = false;
        $is_regretted_vendor = false;

        if ($bid) {
            $latest_commercial_evaluation = $this->Tender_evaluations_model->get_latest_stage_evaluation_for_bid((int) $bid->id, "commercial");
            $documents_map = $this->Tender_bid_documents_model->get_bid_documents_map((int) $bid->id);
            if (!isset($documents_map["commercial_priced"]) && isset($documents_map["commercial"])) {
                $documents_map["commercial_priced"] = $documents_map["commercial"];
            }
            $bid_item_price_map = $this->Tender_bid_item_prices_model->get_price_map((int) $bid->id);
            $bid_item_price_rows = $this->Tender_bid_item_prices_model->get_bid_item_prices((int) $bid->id);
        }

        if (($tender->status ?? "") === "awarded" && $bid) {
            $is_awarded_to_vendor = strtolower((string) ($latest_commercial_evaluation->decision ?? "")) === "accepted";
            $is_regretted_vendor = !$is_awarded_to_vendor;
        }

        return [
            "tender"                       => $tender,
            "docs"                         => $docs,
            "bid"                          => $bid,
            "required_sections"            => $required_sections,
            "documents_map"                 => $documents_map,
            "clarifications"               => $clarifications,
            "clarification_attachments"     => $clarification_attachments,
            "clarification_scope_options"   => Tender_communications_model::clarification_scope_options(),
            "clarification_open"           => $this->_is_vendor_clarification_response_allowed($tender, $vendor_id),
            "latest_commercial_evaluation" => $latest_commercial_evaluation,
            "is_awarded_to_vendor"         => $is_awarded_to_vendor,
            "is_regretted_vendor"          => $is_regretted_vendor,
            "rfq_detail"                   => $this->Tender_rfq_details_model->get_by_tender($tender_id),
            "rfq_items"                    => $rfq_items,
            "bid_item_price_map"           => $bid_item_price_map,
            "bid_item_price_rows"          => $bid_item_price_rows,
            "procurement_approved_for_submission" => $this->_is_tender_procurement_approved($tender),
            "procurement_approval_status"  => $this->_tender_procurement_approval_status($tender),
            "tender_fee_required"          => $this->_is_tender_fee_required($tender),
            "tender_fee_paid"              => $this->_is_tender_fee_paid($tender),
            "tender_fee_payment_status"    => $this->_tender_fee_payment_status($tender),
        ];
    }

    private function _mark_tender_invite_opened(int $tender_id, int $vendor_id): void
    {
        $tiv = $this->db->prefixTable("tender_invited_vendors");
        $this->db->query(
            "UPDATE $tiv
             SET invite_status='opened'
             WHERE tender_id=? AND vendor_id=? AND deleted=0
               AND invite_status IN ('sent','delivered')",
            [$tender_id, $vendor_id]
        );
    }

    public function preview_tender_document($id = 0)
    {
        $context = $this->_get_vendor_tender_document_context((int) $id);
        $this->_audit_tender_document_access($context, "preview");

        return $this->template->view(
            "tender_procurement_manager_inbox/file_preview",
            $this->_make_vendor_tender_document_preview_data(
                $context["doc"],
                $context["full_path"],
                get_uri("vendor_portal/view_tender_document/" . (int) $id)
            )
        );
    }

    public function view_tender_document($id = 0)
    {
        $context = $this->_get_vendor_tender_document_context((int) $id);
        $this->_audit_tender_document_access($context, "view");
        return $this->_serve_vendor_tender_document($context["doc"], $context["full_path"], false);
    }

    public function download_tender_document($id = 0)
    {
        $context = $this->_get_vendor_tender_document_context((int) $id);
        $this->_audit_tender_document_access($context, "download");
        return $this->_serve_vendor_tender_document($context["doc"], $context["full_path"], true);
    }

    private function _get_vendor_tender_document_context(int $id): array
    {
        $vendor_id = $this->_require_vendor_tender_access();
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

        // Paid tender documents are available only after a provider-verified
        // settlement and for 72 hours from that settlement.
        if ($this->_is_tender_fee_required($tender)) {
            $paidAt = trim((string) ($tender->fee_paid_at ?? ""));
            if (!$this->_is_tender_fee_paid($tender) || $paidAt === "") {
                $this->_audit_tender_document_denial($vendor_id, (int) $doc->tender_id, $id, "payment_required");
                app_redirect("forbidden");
            }

            try {
                $paid = new \DateTimeImmutable($paidAt, new \DateTimeZone("UTC"));
                $downloadExpires = $paid->modify("+72 hours");
            } catch (\Throwable $exception) {
                $this->_audit_tender_document_denial($vendor_id, (int) $doc->tender_id, $id, "invalid_payment_time");
                app_redirect("forbidden");
            }
            if ($downloadExpires <= new \DateTimeImmutable("now", new \DateTimeZone("UTC"))) {
                $this->_audit_tender_document_denial($vendor_id, (int) $doc->tender_id, $id, "download_window_expired");
                app_redirect("forbidden");
            }
        }

        if ((int) ($doc->time_limited ?? 0) === 1 && !empty($doc->expires_in_hours) && !empty($doc->created_at)) {
            $expires_at = strtotime((string) $doc->created_at . " +" . (int) $doc->expires_in_hours . " hours");
            if ($expires_at && $expires_at < time()) {
                app_redirect("forbidden");
            }
        }

        $full_path = $this->_resolve_tender_document_path($doc);
        if (!$full_path || !is_file($full_path)) {
            show_404();
        }

        return ["doc" => $doc, "tender" => $tender, "vendor_id" => $vendor_id, "full_path" => $full_path];
    }

    private function _audit_tender_document_access(array $context, string $mode): void
    {
        $this->Auth_security_model->audit(
            "tender_document_" . $mode,
            "success",
            (int) $this->login_user->id,
            "",
            [
                "vendor_id" => (int) ($context["vendor_id"] ?? 0),
                "tender_id" => (int) ($context["doc"]->tender_id ?? 0),
                "document_id" => (int) ($context["doc"]->id ?? 0),
            ]
        );
    }

    private function _audit_tender_document_denial(
        int $vendorId,
        int $tenderId,
        int $documentId,
        string $reason
    ): void {
        $this->Auth_security_model->audit(
            "tender_document_access",
            "denied",
            (int) $this->login_user->id,
            "",
            [
                "vendor_id" => $vendorId,
                "tender_id" => $tenderId,
                "document_id" => $documentId,
                "reason" => $reason,
            ]
        );
    }

    private function _resolve_tender_document_path($doc): ?string
    {
        $relative = ltrim(str_replace('\\', '/', (string)($doc->path ?? '')), '/');
        return (new Upload_security())->resolveStoredFile($relative, 'tender_documents');
    }

    private function _resolve_protected_upload_path(string $relative, string $allowedPrefix): ?string
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        $allowedPrefix = trim(str_replace('\\', '/', $allowedPrefix), '/') . '/';
        if (!str_starts_with($relative, $allowedPrefix)) {
            return null;
        }

        $root = realpath(WRITEPATH . 'uploads/' . rtrim($allowedPrefix, '/'));
        $candidate = realpath(WRITEPATH . 'uploads/' . $relative);
        if (!$root || !$candidate || !is_file($candidate)) {
            return null;
        }
        $root = strtolower(rtrim(str_replace('\\', '/', $root), '/') . '/');
        $candidateCheck = strtolower(str_replace('\\', '/', $candidate));

        return str_starts_with($candidateCheck, $root) ? $candidate : null;
    }

    private function _make_vendor_tender_document_preview_data($doc, string $full_path, string $file_url): array
    {
        $mime = function_exists("mime_content_type") ? mime_content_type($full_path) : "";
        $mime = strtolower((string) ($mime ?: "application/octet-stream"));
        $image_mimes = ["image/jpeg", "image/png", "image/gif", "image/webp", "image/bmp"];

        return [
            "file_url" => $file_url,
            "is_image_file" => in_array($mime, $image_mimes, true),
            "is_iframe_preview_available" => $mime === "application/pdf",
            "is_google_preview_available" => false,
            "is_viewable_video_file" => false,
            "is_google_drive_file" => false,
        ];
    }

    private function _serve_vendor_tender_document($doc, string $full_path, bool $download)
    {
        $mime = function_exists("mime_content_type") ? mime_content_type($full_path) : "";
        $mime = $mime ?: "application/octet-stream";
        $name = str_replace(["\r", "\n", '"'], "", (string) ($doc->original_name ?: basename($full_path)));
        $inline_mimes = ["application/pdf", "image/jpeg", "image/png", "image/gif", "image/webp", "image/bmp"];
        $inline = !$download && in_array(strtolower($mime), $inline_mimes, true);

        $response = $this->response
            ->download($full_path, null)
            ->setFileName($name)
            ->setContentType($mime, "")
            ->setHeader("X-Content-Type-Options", "nosniff");

        return $inline ? $response->inline() : $response;
    }

    public function pay_tender_fee()
    {
        $this->validate_submitted_data([
            "tender_id" => "required|numeric"
        ]);

        $vendor_id = $this->_require_vendor_tender_participation_access();
        $tender_id = (int) $this->request->getPost("tender_id");

        $tender = $this->Tenders_model->get_vendor_visible_tender($tender_id, $vendor_id);
        if (!$tender) {
            return $this->response->setJSON([
                "success" => false,
                "message" => "Tender not found or not accessible."
            ]);
        }

        if (!$this->_is_tender_fee_required($tender)) {
            return $this->response->setJSON([
                "success" => true,
                "message" => "No tender fee is required for this tender."
            ]);
        }

        if (!$this->_is_tender_submission_open($tender)) {
            return $this->response->setStatusCode(409)->setJSON([
                'success' => false, 'message' => 'This tender is no longer open for payment.'
            ]);
        }

        if ($this->_is_tender_fee_paid($tender)) {
            return $this->response->setJSON([
                "success" => true,
                "message" => "Tender fee is already marked as paid."
            ]);
        }

        if ((new Eservice_payment_state($this->db))->latestPaid('tender_fee', $tender_id, $vendor_id)) {
            return $this->response->setStatusCode(409)->setJSON([
                'success' => false, 'message' => 'A verified payment already exists. Please ask Accounting to reconcile it before paying again.'
            ]);
        }

        $return_url = get_uri("vendor_portal/tender/" . $tender_id);
        $payments = new Eservice_payment_manager($this->db);
        $result = $payments->start(
            Eservice_payment_manager::TENDER_FEE,
            $tender_id,
            $vendor_id,
            (int) $this->login_user->id,
            trim((string) ($tender->tender_fee ?? "0")),
            "Tender fee " . ((string) ($tender->title ?? "#" . $tender_id)),
            $return_url,
            $return_url,
            ['currency' => strtoupper((string) ($tender->currency ?? 'OMR'))]
        );
        $status_code = (int) ($result["status_code"] ?? 500);
        unset($result["status_code"]);

        return $this->response->setStatusCode($status_code)->setJSON($result);
    }

    public function request_tender_approval()
    {
        $this->validate_submitted_data([
            "tender_id" => "required|numeric"
        ]);

        $vendor_id = $this->_require_vendor_tender_participation_access();
        $tender_id = (int) $this->request->getPost("tender_id");

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
                "message" => "This tender is no longer open for participation requests."
            ]);
        }

        if (!$this->_is_tender_fee_paid($tender)) {
            return $this->response->setJSON([
                "success" => false,
                "message" => "Tender fee payment is required before requesting procurement approval."
            ]);
        }

        if ($this->_is_tender_procurement_approved($tender)) {
            return $this->response->setJSON([
                "success" => true,
                "message" => "Procurement approval is already granted for this tender."
            ]);
        }

        if ($this->_tender_procurement_approval_status($tender) === "pending_approval") {
            return $this->response->setJSON([
                "success" => true,
                "message" => "Your participation request is already pending procurement approval."
            ]);
        }

        $tiv = $this->db->prefixTable("tender_invited_vendors");
        $now = date("Y-m-d H:i:s");
        $existing = $this->db->query(
            "SELECT id
             FROM $tiv
             WHERE tender_id=? AND vendor_id=? AND deleted=0
             LIMIT 1",
            [$tender_id, $vendor_id]
        )->getRow();

        if ($existing) {
            $this->db->query(
                "UPDATE $tiv
                 SET invite_status='pending_approval',
                     invited_by=?,
                     invited_at=?,
                     deleted=0
                 WHERE id=?",
                [$this->login_user->id, $now, (int) $existing->id]
            );
        } else {
            $this->db->query(
                "INSERT INTO $tiv (tender_id, vendor_id, invite_status, invited_by, invited_at, deleted)
                 VALUES (?, ?, 'pending_approval', ?, ?, 0)",
                [$tender_id, $vendor_id, $this->login_user->id, $now]
            );
        }

        return $this->response->setJSON([
            "success" => true,
            "message" => "Participation request sent to procurement for approval."
        ]);
    }


    function bid_modal()
    {
        $this->validate_submitted_data([
            "tender_id" => "required|numeric"
        ]);

        $vendor_id = $this->_require_vendor_tender_participation_access();
        $tender_id = (int) $this->request->getPost("tender_id");

        $tender = $this->Tenders_model->get_vendor_visible_tender($tender_id, $vendor_id);
        if (!$tender) {
            app_redirect("forbidden");
        }

        if (!$this->_is_tender_submission_open($tender)) {
            app_redirect("forbidden");
        }

        if (!$this->_is_tender_fee_paid($tender)) {
            app_redirect("forbidden");
        }

        if (!$this->_is_tender_procurement_approved($tender)) {
            app_redirect("forbidden");
        }

        $bid = $this->Tender_bids_model->get_vendor_bid($tender_id, $vendor_id);
        $required_sections = $this->Tender_bid_requirements_model->get_required_codes($tender_id);
        $rfq_items = $this->Tender_rfq_items_model->get_by_tender($tender_id);
        $documents_map = [];
        $bid_item_price_map = [];

        if ($bid) {
            $documents_map = $this->Tender_bid_documents_model->get_bid_documents_map((int) $bid->id);
            if (!isset($documents_map["commercial_priced"]) && isset($documents_map["commercial"])) {
                $documents_map["commercial_priced"] = $documents_map["commercial"];
            }
            $bid_item_price_map = $this->Tender_bid_item_prices_model->get_price_map((int) $bid->id);
        }

        return $this->template->view("vendor_portal/tenders/bid_modal", [
            "tender" => $tender,
            "bid" => $bid,
            "required_sections" => $required_sections,
            "documents_map" => $documents_map,
            "rfq_items" => $rfq_items,
            "bid_item_price_map" => $bid_item_price_map
        ]);
    }

    function save_bid()
    {
        $this->validate_submitted_data([
            "tender_id" => "required|numeric"
        ]);

        $vendor_id = $this->_require_vendor_tender_participation_access();
        $tender_id = (int) $this->request->getPost("tender_id");

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

        if (!$this->_is_tender_fee_paid($tender)) {
            return $this->response->setJSON([
                "success" => false,
                "message" => "Tender fee payment is required before submitting a bid."
            ]);
        }

        if (!$this->_is_tender_procurement_approved($tender)) {
            return $this->response->setJSON([
                "success" => false,
                "message" => "Procurement approval is required before submitting a bid."
            ]);
        }

        $existing_bid = $this->Tender_bids_model->get_vendor_bid($tender_id, $vendor_id);
        $required_sections = $this->Tender_bid_requirements_model->get_required_codes($tender_id);
        $upload_fields = [
            "technical" => "technical_file",
            "commercial_priced" => "commercial_priced_file",
            "commercial_unpriced" => "commercial_unpriced_file",
            "bank_guarantee" => "bank_guarantee_file",
        ];
        $section_labels = $this->Tender_bid_requirements_model->get_default_labels();
        $uploaded_files = [];
        $existing_documents = [];

        foreach ($upload_fields as $section => $field_name) {
            $file = $this->request->getFile($field_name);
            $uploaded_files[$section] = ($file && $file->isValid() && !$file->hasMoved()) ? $file : null;
        }

        try {
            $uploadSecurity = new Upload_security();
            foreach ($uploaded_files as $uploadedFile) {
                if ($uploadedFile) {
                    $uploadSecurity->validateUploadedFile(
                        $uploadedFile,
                        Upload_security::CONTEXT_SECURITY_DOCUMENT
                    );
                }
            }
        } catch (UploadSecurityException $e) {
            log_message('notice', 'Vendor bid document upload rejected.');
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => app_lang('invalid_file_type'),
            ]);
        }

        if ($existing_bid) {
            $existing_documents = $this->Tender_bid_documents_model->get_bid_documents_map((int) $existing_bid->id);
            if (!isset($existing_documents["commercial_priced"]) && isset($existing_documents["commercial"])) {
                $existing_documents["commercial_priced"] = $existing_documents["commercial"];
            }
        }

        foreach ($required_sections as $section) {
            $has_existing = !empty($existing_documents[$section]);
            $has_new_upload = !empty($uploaded_files[$section]);
            if (!$has_existing && !$has_new_upload) {
                return $this->response->setJSON([
                    "success" => false,
                    "message" => ($section_labels[$section] ?? "Required document") . " is required."
                ]);
            }
        }

        $rfq_items = $this->Tender_rfq_items_model->get_by_tender($tender_id);
        $item_price_summary = $this->Tender_bid_item_prices_model->prepare_submitted_item_prices(
            $rfq_items,
            $this->request->getPost("rfq_item_unit_price")
        );

        if (empty($item_price_summary["success"])) {
            return $this->response->setJSON([
                "success" => false,
                "message" => $item_price_summary["message"] ?? "Please complete the tender item prices."
            ]);
        }

        $currency = trim((string) $this->request->getPost("currency"));
        if (!$currency) {
            $currency = "OMR";
        }

        $manual_total_amount = $this->request->getPost("total_amount");
        $total_amount = !empty($item_price_summary["has_items"])
            ? ($item_price_summary["total_amount"] ?? null)
            : ($manual_total_amount !== "" ? $manual_total_amount : null);

        $bid_data = [
            "tender_id" => $tender_id,
            "vendor_id" => $vendor_id,
            "status" => "submitted",
            "submitted_at" => date("Y-m-d H:i:s"),
            "total_amount" => $total_amount,
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

        try {
            foreach ($uploaded_files as $section => $file) {
                if ($file) {
                    $this->_replace_bid_document((int) $bid_id, $section, $file, $upload_dir, $tender_id, $vendor_id);
                }
            }
        } catch (\Throwable $e) {
            log_message('error', 'Vendor bid document storage failed.');
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => app_lang('error_occurred'),
            ]);
        }

        $this->Tender_bid_item_prices_model->sync_bid_item_prices(
            (int) $bid_id,
            $tender_id,
            $vendor_id,
            $item_price_summary["rows"] ?? []
        );

        return $this->response->setJSON([
            "success" => true,
            "message" => "Bid submitted successfully."
        ]);
    }

    public function save_clarification()
    {
        $this->validate_submitted_data([
            "tender_id" => "required|numeric",
            "message" => "required",
        ]);

        $vendor_id = $this->_require_vendor_tender_participation_access();
        $tender_id = (int) $this->request->getPost("tender_id");
        $message = trim((string) $this->request->getPost("message"));
        $clarification_scope = Tender_communications_model::normalize_clarification_scope($this->request->getPost("clarification_scope"));

        $user_id = (int) ($this->login_user->id ?? 0);
        $ip_hash = hash("sha256", (string) $this->request->getIPAddress());
        $throttle_key = "vendor_clarification_post_{$user_id}_{$ip_hash}";
        $throttler = service("throttler");
        if (!$throttler->check($throttle_key, 10, 60)) {
            return $this->response
                ->setStatusCode(429)
                ->setHeader("Retry-After", (string) max(1, $throttler->getTokenTime()))
                ->setJSON([
                    "success" => false,
                    "message" => "Too many clarification submissions. Please wait and try again.",
                ]);
        }

        $tender = $this->Tenders_model->get_vendor_visible_tender($tender_id, $vendor_id);
        if (!$tender) {
            return $this->response->setJSON([
                "success" => false,
                "message" => "Tender not found or not accessible."
            ]);
        }

        $normal_clarification_open = $this->_is_tender_clarification_open($tender);
        if (!$this->_is_vendor_clarification_response_allowed($tender, $vendor_id)) {
            return $this->response->setJSON([
                "success" => false,
                "message" => "Clarification submissions are closed for this tender."
            ]);
        }

        $parent_request = null;
        if (!$normal_clarification_open) {
            $parent_request = $this->Tender_communications_model->get_latest_vendor_visible_evaluator_request($tender_id, $vendor_id);
            if ($parent_request) {
                $clarification_scope = Tender_communications_model::normalize_clarification_scope($parent_request->clarification_scope ?? $clarification_scope);
            }
        }

        $saved = $this->Tender_communications_model->ci_save(clean_data([
            "tender_id" => $tender_id,
            "vendor_id" => $vendor_id,
            "tender_bid_id" => !empty($parent_request->tender_bid_id) ? (int) $parent_request->tender_bid_id : null,
            "type" => "clarification",
            "clarification_scope" => $clarification_scope,
            "internal_audience" => !empty($parent_request->internal_audience) ? $parent_request->internal_audience : null,
            "subject" => null,
            "message" => $message,
            "parent_id" => !empty($parent_request->id) ? (int) $parent_request->id : null,
            "status" => "open",
            "is_vendor_visible" => 1,
            "created_by" => $this->login_user->id,
            "created_at" => date("Y-m-d H:i:s"),
            "published_at" => date("Y-m-d H:i:s"),
            "deleted" => 0,
        ]));

        if (!$saved) {
            return $this->response->setJSON([
                "success" => false,
                "message" => app_lang("error_occurred")
            ]);
        }

        try {
            $this->_save_clarification_files((int) $saved, $tender_id, $vendor_id);
        } catch (UploadSecurityException $e) {
            log_message('notice', 'Vendor clarification attachment rejected.');
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => app_lang('invalid_file_type'),
            ]);
        }

        return $this->response->setJSON([
            "success" => true,
            "message" => "Clarification submitted successfully."
        ]);
    }

    public function download_clarification_attachment($id = 0)
    {
        $vendor_id = $this->_require_vendor_tender_access();
        $id = (int) $id;
        if (!$id) {
            show_404();
        }

        $attachment = $this->Tender_communications_model->get_attachment($id);
        if (!$attachment || (int) ($attachment->is_vendor_visible ?? 0) !== 1) {
            show_404();
        }

        $tender = $this->Tenders_model->get_vendor_visible_tender((int) $attachment->tender_id, $vendor_id);
        if (!$tender) {
            app_redirect("forbidden");
        }

        $attachment_vendor_id = (int) ($attachment->vendor_id ?? 0);
        if ($attachment_vendor_id > 0 && $attachment_vendor_id !== (int) $vendor_id) {
            app_redirect("forbidden");
        }

        $full_path = $this->_resolve_protected_upload_path(
            (string)$attachment->path,
            'tender_clarifications'
        );
        if (!$full_path) {
            show_404();
        }

        $download_name = $attachment->original_name ?: basename($full_path);
        return $this->response->download($full_path, null)->setFileName($download_name);
    }

    public function download_bid_document($id = 0)
    {
        $vendor_id = $this->_require_vendor_tender_access();
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

        $full_path = $this->_resolve_protected_upload_path(
            (string)$doc->path,
            'tender_bids'
        );
        if (!$full_path) {
            show_404();
        }

        $download_name = $doc->original_name ?: basename($full_path);

        return $this->response->download($full_path, null)->setFileName($download_name);
    }

    private function _replace_bid_document(int $tender_bid_id, string $section, $file, string $upload_dir, int $tender_id, int $vendor_id): void
    {
        $stored = (new Upload_security())->storeUploadedFile(
            $file,
            $upload_dir,
            Upload_security::CONTEXT_SECURITY_DOCUMENT,
            'tb_' . $section . '_'
        );
        $new_name = $stored['stored_name'];

        $existing = $this->Tender_bid_documents_model->get_bid_document_by_section($tender_bid_id, $section);
        if ($existing) {
            $this->Tender_bid_documents_model->ci_save(["deleted" => 1], (int) $existing->id);
        }

        $doc_data = [
            "tender_bid_id" => $tender_bid_id,
            "section" => $section,
            "disk" => "local",
            "path" => "tender_bids/tender_" . $tender_id . "/vendor_" . $vendor_id . "/" . $new_name,
            "original_name" => $stored['original_name'],
            "mime_type" => $stored['detected_mime'],
            "size_bytes" => $stored['size_bytes'],
            "submitted_at" => date("Y-m-d H:i:s"),
        ];

        $this->Tender_bid_documents_model->ci_save(clean_data($doc_data));
    }

    private function _save_clarification_files(int $communication_id, int $tender_id, int $vendor_id): void
    {
        $files = method_exists($this->request, "getFileMultiple")
            ? ($this->request->getFileMultiple("clarification_files") ?: [])
            : (($this->request->getFiles()["clarification_files"] ?? []) ?: []);

        if (!$files) {
            return;
        }

        if (!is_array($files)) {
            $files = [$files];
        }

        $upload_dir = WRITEPATH . "uploads/tender_clarifications/tender_" . $tender_id . "/communication_" . $communication_id . "/";

        $saved_files = [];
        $security = new Upload_security();
        foreach ($files as $file) {
            if (!$file || !$file->isValid() || $file->hasMoved()) {
                continue;
            }
            $stored = $security->storeUploadedFile(
                $file,
                $upload_dir,
                Upload_security::CONTEXT_SECURITY_DOCUMENT,
                'tc_'
            );
            $new_name = $stored['stored_name'];

            $saved_files[] = [
                "disk" => "local",
                "path" => "tender_clarifications/tender_" . $tender_id . "/communication_" . $communication_id . "/" . $new_name,
                "original_name" => $stored['original_name'],
                "mime_type" => $stored['detected_mime'],
                "size_bytes" => $stored['size_bytes'],
            ];
        }

        $this->Tender_communications_model->save_attachments($communication_id, $tender_id, $vendor_id, $saved_files, (int) $this->login_user->id);
    }

    private function _is_tender_submission_open($tender): bool
    {
        return $this->Tenders_model->is_vendor_submission_open($tender);
    }

    private function _tender_fee_amount($tender): float
    {
        return round((float) ($tender->tender_fee ?? 0), 3);
    }

    private function _is_tender_fee_required($tender): bool
    {
        return $this->_tender_fee_amount($tender) > 0;
    }

    private function _is_tender_fee_paid($tender): bool
    {
        if (!$this->_is_tender_fee_required($tender)) {
            return true;
        }

        return (new Eservice_payment_state($this->db))->hasVerifiedPayment(
            'tender_fee', (int) $tender->id, $this->_my_vendor_id(),
            (string) $tender->tender_fee, strtoupper((string) ($tender->currency ?? 'OMR'))
        );
    }

    private function _tender_fee_payment_status($tender): string
    {
        if (!$this->_is_tender_fee_required($tender)) {
            return "not_required";
        }

        return $this->_is_tender_fee_paid($tender) ? "paid" : "unpaid";
    }

    private function _is_tender_procurement_approved($tender): bool
    {
        if (!$tender) {
            return false;
        }

        if ((int) ($tender->procurement_approved_for_submission ?? 0) === 1) {
            return true;
        }

        if (!empty($tender->specific_target_id)) {
            return true;
        }

        $invite_status = strtolower((string) ($tender->invite_status ?? ""));
        return in_array($invite_status, ["sent", "delivered", "opened", "approved"], true);
    }

    private function _tender_procurement_approval_status($tender): string
    {
        if ($this->_is_tender_procurement_approved($tender)) {
            return "approved";
        }

        $invite_status = strtolower((string) ($tender->invite_status ?? ""));
        if (in_array($invite_status, ["pending_approval", "rejected", "declined"], true)) {
            return $invite_status;
        }

        return "required";
    }

    private function _is_tender_clarification_open($tender): bool
    {
        if (($tender->status ?? "") !== "published") {
            return false;
        }

        if (!empty($tender->clarification_deadline) && strtotime((string) $tender->clarification_deadline) <= time()) {
            return false;
        }

        if (!empty($tender->closing_at) && strtotime((string) $tender->closing_at) <= time()) {
            return false;
        }

        return true;
    }

    private function _is_vendor_clarification_response_allowed($tender, int $vendor_id): bool
    {
        if ($this->_is_tender_clarification_open($tender)) {
            return true;
        }

        if (
            in_array(strtolower((string) ($tender->status ?? "")), ["closed"], true)
            && $this->_vendor_participated_in_tender((int) ($tender->id ?? 0), $vendor_id)
        ) {
            return true;
        }

        return $this->Tender_communications_model->has_vendor_visible_evaluator_clarification_request((int) ($tender->id ?? 0), $vendor_id);
    }

    private function _vendor_participated_in_tender(int $tender_id, int $vendor_id): bool
    {
        $tb = $this->db->prefixTable("tender_bids");

        $row = $this->db->query(
            "SELECT id
             FROM $tb
             WHERE deleted = 0
               AND tender_id = ?
               AND vendor_id = ?
               AND status <> 'draft'
             LIMIT 1",
            [$tender_id, $vendor_id]
        )->getRow();

        return (bool) $row;
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

        $participation_status = $this->_tender_procurement_approval_status($row);
        $participation_badges = [
            "approved"         => "success",
            "pending_approval" => "warning",
            "required"         => "secondary",
            "rejected"         => "danger",
            "declined"         => "danger",
        ];
        $participation_labels = [
            "approved"         => "Approved",
            "pending_approval" => "Pending approval",
            "required"         => "Approval required",
            "rejected"         => "Rejected",
            "declined"         => "Declined",
        ];
        $invite_class = $participation_badges[$participation_status] ?? "secondary";
        $invite_label = $participation_labels[$participation_status] ?? ucwords(str_replace("_", " ", $participation_status));
        $invite_badge = "<span class='badge bg-" . $invite_class . "'>" . esc($invite_label) . "</span>";

        $target = $row->vendor_category_name ?: "";
        if (!empty($row->vendor_sub_category_name)) {
            $target .= " / " . $row->vendor_sub_category_name;
        }
        if (!empty($row->vendor_group_name)) {
            $target = "Group: " . $row->vendor_group_name . (!empty($row->vendor_group_code) ? " (" . $row->vendor_group_code . ")" : "");
        }
        if (!empty($row->vendor_grade_name) || !empty($row->vendor_grade_code)) {
            $target = "Grade: " . trim(($row->vendor_grade_code ? $row->vendor_grade_code . " - " : "") . ($row->vendor_grade_name ?? ""));
        }
        if (!$target) {
            $target = "Open to eligible vendors";
        }

        $eligibility_labels = [
            "participated" => "Participated",
            "specific_vendor" => "Selected vendor",
            "invited" => "Invited",
            "vendor_group" => "Vendor group",
            "vendor_grade" => "Vendor grade",
            "specialty" => "Specialty",
            "open" => "Open tender",
        ];
        $eligibility_source = strtolower((string) ($row->eligibility_source ?? "eligible"));
        $eligibility_label = $eligibility_labels[$eligibility_source] ?? "Eligible";

        $actions = anchor(
            get_uri("vendor_portal/tender/" . (int) $row->id),
            "<i data-feather='arrow-up-right' class='icon-16'></i>",
            [
                "class" => "btn btn-default btn-sm vpt-open-tender",
                "title" => "Open Tender Details",
            ]
        );

        return [
            esc($row->reference ?? "-"),
            esc($row->title ?? "-"),
            $type_badge,
            $status_badge,
            esc($target),
            "<span class='badge bg-light text-dark'>" . esc($eligibility_label) . "</span>",
            !empty($row->published_at) ? format_to_datetime($row->published_at) : "-",
            !empty($row->closing_at) ? format_to_datetime($row->closing_at) : "-",
            $invite_badge,
            $actions
        ];
    }

    private function _my_vendor_id(): int
    {
        $membership = $this->_active_vendor_membership();
        if ($membership) {
            return (int) $membership->vendor_id;
        }

        $user_id = (int) ($this->login_user->id ?? 0);
        if (!$user_id) {
            return 0;
        }

        if (count($this->Vendor_users_model->get_accessible_memberships($user_id)) > 1) {
            app_redirect("signin/vendor_selection");
        }

        return 0;
    }

    private function _active_vendor_membership(): ?object
    {
        if ($this->active_vendor_membership_resolved) {
            return $this->active_vendor_membership;
        }

        $this->active_vendor_membership_resolved = true;
        $user_id = (int) ($this->login_user->id ?? 0);
        if ($user_id < 1) {
            return null;
        }

        $membership = $this->Vendor_users_model->resolve_context($user_id);
        if ($membership) {
            $this->Vendor_users_model->set_active_vendor_context($user_id, (int) $membership->vendor_id);
            $this->active_vendor_membership = $membership;
        }

        return $this->active_vendor_membership;
    }

    private function _require_vendor_capability(string $capability, bool $tender_portal = false): int
    {
        $membership = $this->_active_vendor_membership();
        if (!$membership) {
            $user_id = (int) ($this->login_user->id ?? 0);
            $memberships = $user_id ? $this->Vendor_users_model->get_accessible_memberships($user_id) : [];
            log_message("warning", "VENDOR PORTAL ACCESS DENIED: missing_vendor_context " . json_encode([
                "user_id" => $user_id,
                "active_vendor_id" => (int) $this->session->get(Vendor_users_model::SESSION_VENDOR_ID),
                "accessible_memberships" => count($memberships),
                "capability" => $capability,
            ]));

            if (count($memberships) > 1) {
                app_redirect("signin/vendor_selection");
            }

            app_redirect("forbidden");
        }

        $vendor_id = (int) $membership->vendor_id;
        $status = $this->_vendor_status($vendor_id);
        $statusAllowed = $tender_portal
            ? vendor_can_access_tender_portal($status)
            : vendor_can_access_profile_portal($status);

        $capabilityAllowed = $this->Vendor_portal_authorizer->can($membership, $capability);
        if (!$statusAllowed || !$capabilityAllowed) {
            log_message("warning", "VENDOR PORTAL ACCESS DENIED: capability_or_status_denied " . json_encode([
                "user_id" => (int) ($this->login_user->id ?? 0),
                "vendor_id" => $vendor_id,
                "vendor_status" => $status,
                "status_allowed" => $statusAllowed,
                "membership_status" => (string) ($membership->membership_status ?? ""),
                "vendor_role_code" => (string) ($membership->vendor_role_code ?? ""),
                "is_owner" => (int) ($membership->is_owner ?? 0),
                "capability" => $capability,
                "capability_allowed" => $capabilityAllowed,
            ]));
            app_redirect("forbidden");
        }

        return $vendor_id;
    }

    private function _require_vendor_access(): int
    {
        return $this->_require_vendor_capability(Vendor_portal_authorizer::PROFILE_VIEW);
    }

    private function _can_edit_vendor_profile(): bool
    {
        return $this->Vendor_portal_authorizer->can(
            $this->_active_vendor_membership(),
            Vendor_portal_authorizer::PROFILE_EDIT
        );
    }

    private function _require_vendor_profile_write_access(): int
    {
        return $this->_require_vendor_capability(Vendor_portal_authorizer::PROFILE_EDIT);
    }

    /**
     * Contact administration is reserved for the owner of the active CR.
     * Ordinary contacts may view the CR contact list, but cannot create,
     * change, remove, or provision another contact account.
     */
    private function _can_manage_vendor_contacts(int $vendor_id): bool
    {
        $user_id = (int) ($this->login_user->id ?? 0);
        if ($vendor_id < 1 || $user_id < 1) {
            return false;
        }

        $membership = $this->Vendor_users_model->get_accessible_membership($user_id, $vendor_id);
        return $this->Vendor_portal_authorizer->can(
            $membership,
            Vendor_portal_authorizer::CONTACTS_MANAGE
        );
    }

    private function _require_vendor_contact_owner(): int
    {
        $vendor_id = $this->_require_vendor_access();
        if (!$this->_can_manage_vendor_contacts($vendor_id)) {
            app_redirect("forbidden");
        }

        return $vendor_id;
    }

    private function _require_vendor_tender_access(): int
    {
        return $this->_require_vendor_capability(Vendor_portal_authorizer::TENDER_VIEW, true);
    }

    private function _require_vendor_tender_participation_access(): int
    {
        return $this->_require_vendor_capability(Vendor_portal_authorizer::TENDER_PARTICIPATE, true);
    }

    private function _vendor_status(int $vendor_id): string
    {
        if (!$vendor_id) {
            return "";
        }

        $vendors_table = $this->db->prefixTable("vendors");
        $row = $this->db->query(
            "SELECT status FROM $vendors_table WHERE id=? AND deleted=0 LIMIT 1",
            [$vendor_id]
        )->getRow();

        return (string)($row->status ?? "");
    }

    private function _record_vendor_status_history(int $vendor_id, string $from_status, string $to_status, string $reason = ""): void
    {
        if ($from_status === $to_status) {
            return;
        }

        $action_map = [
            "submitted" => "submit",
            "approved" => "approve",
            "rejected" => "reject",
            "revise" => "revise",
        ];

        $this->db->table($this->db->prefixTable("vendor_status_histories"))->insert(clean_data([
            "vendor_id" => $vendor_id,
            "from_status" => $from_status ?: null,
            "to_status" => $to_status,
            "action" => $action_map[$to_status] ?? null,
            "reason" => $reason ?: null,
            "action_by" => $this->login_user->id ?? null,
            "action_at" => get_current_utc_time(),
            "created_at" => get_current_utc_time(),
            "updated_at" => get_current_utc_time(),
            "deleted" => 0,
        ]));
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
        $view_data["can_edit_profile"] = $this->_can_edit_vendor_profile();

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

        $vendor_id = $this->_require_vendor_profile_write_access();

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

        $vendor_id = $this->_require_vendor_profile_write_access();


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
            try {
                $stored = (new Upload_security())->storeUploadedFile(
                    $file,
                    $upload_dir,
                    Upload_security::CONTEXT_SECURITY_DOCUMENT,
                    'vh_'
                );
            } catch (UploadSecurityException $e) {
                log_message('notice', 'Vendor bank document upload rejected.');
                return $this->response->setStatusCode(422)->setJSON([
                    'success' => false,
                    'message' => app_lang('invalid_file_type'),
                ]);
            }
            $new_name = $stored['stored_name'];

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

        $vendor_id = $this->_require_vendor_profile_write_access();
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

        $full_path = $this->_resolve_protected_upload_path(
            (string)$row->letter_head_path,
            'vendor_bank_accounts'
        );

        if (!$full_path) {
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
        if (!$is_locked && $this->_can_edit_vendor_profile()) {
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

    /**
     * Passwords belong to the signed-in identity, not to an individual CR.
     * Keep this page independent of the active vendor context so changing the
     * password never changes or clears the selected CR.
     */
    public function change_password()
    {
        return $this->template->rander("vendor_portal/change_password");
    }

    public function save_password()
    {
        if (strtolower($this->request->getMethod()) !== "post") {
            return $this->response
                ->setStatusCode(405)
                ->setHeader("Allow", "POST")
                ->setJSON([
                    "success" => false,
                    "message" => "This action requires a POST request.",
                ]);
        }

        $this->validate_submitted_data([
            "current_password" => "required",
            "new_password" => "required|min_length[10]|max_length[72]",
            "new_password_confirm" => "required|matches[new_password]",
        ]);

        $user_id = (int) ($this->login_user->id ?? 0);
        if (!$user_id) {
            return $this->response->setStatusCode(403)->setJSON([
                "success" => false,
                "message" => app_lang("authentication_failed"),
            ]);
        }

        $current_password = (string) $this->request->getPost("current_password");
        $new_password = (string) $this->request->getPost("new_password");

        $policyErrors = $this->Users_model->password_policy_errors($new_password);
        if ($policyErrors) {
            $this->Auth_security_model->audit(
                "password_change",
                "denied",
                $user_id,
                "",
                ["reason" => "policy"]
            );
            return $this->response->setJSON([
                "success" => false,
                "message" => implode(" ", $policyErrors),
            ]);
        }

        $throttler = service("throttler");
        $ip_hash = hash("sha256", (string) $this->request->getIPAddress());
        $throttle_key = "password_change_{$user_id}_{$ip_hash}";

        // Consume the attempt before verifying the secret. Otherwise a correct
        // password could still succeed after the failure bucket is exhausted.
        if (!$throttler->check($throttle_key, 5, 600)) {
            $this->Auth_security_model->audit(
                "password_change",
                "rate_limited",
                $user_id,
                "",
                ["reason" => "current_password"]
            );

            return $this->response
                ->setStatusCode(429)
                ->setHeader(
                    "Retry-After",
                    (string) max(1, $throttler->getTokenTime())
                )
                ->setJSON([
                    "success" => false,
                    "message" => "Too many password attempts. Please wait and try again.",
                ]);
        }

        if (!$this->Users_model->verify_user_password($user_id, $current_password)) {
            $this->Auth_security_model->audit(
                "password_change",
                "denied",
                $user_id,
                "",
                ["reason" => "current_password"]
            );
            return $this->response->setJSON([
                "success" => false,
                "message" => "The current password is incorrect.",
            ]);
        }

        if (hash_equals($current_password, $new_password)) {
            return $this->response->setJSON([
                "success" => false,
                "message" => "The new password must be different from the current password.",
            ]);
        }

        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        if (!$password_hash || !$this->Users_model->ci_save(["password" => $password_hash], $user_id)) {
            return $this->response->setStatusCode(500)->setJSON([
                "success" => false,
                "message" => app_lang("error_occurred"),
            ]);
        }

        $throttler->remove($throttle_key);

        $has_active_vendor = $this->session->has("active_vendor_id");
        $active_vendor_id = $has_active_vendor ? $this->session->get("active_vendor_id") : null;
        $this->session->regenerate(true);
        if ($has_active_vendor) {
            $this->session->set("active_vendor_id", $active_vendor_id);
        }

        $this->Auth_security_model->audit("password_change", "success", $user_id);

        return $this->response->setJSON([
            "success" => true,
            "message" => "Your password has been changed successfully.",
        ]);
    }

    function index($tab = "")
    {
        return $this->view($tab);
    }

    function view($tab = "")
    {
        $vendor_id = $this->_require_vendor_access();

        $view_data["vendor_info"] = $this->Vendors_model->get_one($vendor_id);
        $view_data["vendor_memberships"] = $this->Vendor_users_model->get_accessible_memberships((int) $this->login_user->id);
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
        $view_data["profile_checklist"] = $this->_get_vendor_profile_checklist($vendor_id);
        $view_data["billing"] = (new Vendor_billing_service($this->db))->summary($view_data["vendor_info"]);
        $view_data["can_pay_vendor_fee"] = $this->Vendor_portal_authorizer->can(
            $this->_active_vendor_membership(), Vendor_portal_authorizer::PROFILE_EDIT
        );

        return $this->template->view("vendor_portal/overview/index", $view_data);
    }

    function submit_for_review()
    {
        $vendor_id = $this->_require_vendor_profile_write_access();
        $vendor = $this->Vendors_model->get_one($vendor_id);
        if (!$vendor || (int)($vendor->deleted ?? 0) === 1) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("invalid_request")]);
        }

        $status = strtolower((string)($vendor->status ?? ""));
        if (!in_array($status, ["new", "pending_payment", "submitted", "revise"], true)) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("vendor_profile_already_submitted")]);
        }

        $checklist = $this->_get_vendor_profile_checklist($vendor_id);
        if ((int)($checklist["completed"] ?? 0) < (int)($checklist["total"] ?? 0)) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("vendor_profile_incomplete")]);
        }

        $billing = new Vendor_billing_service($this->db);
        $open = $billing->latestOpen($vendor_id);
        return $this->_start_vendor_fee($vendor, $open->fee_type ?? 'registration');
    }

    public function renew_registration()
    {
        $vendor_id = $this->_require_vendor_profile_write_access();
        $vendor = $this->Vendors_model->get_one($vendor_id);
        if (!$vendor || empty($vendor->id) || (int) $vendor->deleted === 1) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Vendor not found.']);
        }
        $checklist = $this->_get_vendor_profile_checklist($vendor_id);
        if ((int) $checklist['completed'] < (int) $checklist['total']) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => app_lang('vendor_profile_incomplete')]);
        }
        return $this->_start_vendor_fee($vendor, 'renewal');
    }

    private function _start_vendor_fee(object $vendor, string $type)
    {
        try {
            $billing = new Vendor_billing_service($this->db);
            $request = $billing->prepare((int) $vendor->id, (int) $this->login_user->id, $type);
            if ($billing->isSettled($request) || Vendor_billing_service::normalizedFee((string) $request->amount) === '0.000') {
                $billing->submitSettled((int) $request->id, (int) $this->login_user->id);
                return $this->response->setJSON(['success' => true, 'message' => 'Submitted to Procurement for review.']);
            }
            $return = get_uri('vendor_portal');
            $result = (new Eservice_payment_manager($this->db))->start(
                'vendor_' . $type, (int) $vendor->id, (int) $vendor->id, (int) $this->login_user->id,
                (string) $request->amount, 'Vendor ' . $type . ' fee', $return, $return, $billing->metadata($request)
            );
            $code = (int) ($result['status_code'] ?? 500);
            unset($result['status_code']);
            return $this->response->setStatusCode($code)->setJSON($result);
        } catch (\DomainException $e) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            log_message('error', 'Vendor fee initiation failed: {class}', ['class' => get_class($e)]);
            return $this->response->setStatusCode(503)->setJSON(['success' => false,
                'message' => 'Unable to prepare vendor payment. Please contact the administrator.']);
        }
    }

    private function _get_vendor_profile_checklist(int $vendor_id): array
    {
        $db = db_connect();
        $vendors = $db->prefixTable("vendors");
        $contacts = $db->prefixTable("vendor_contacts");
        $bank = $db->prefixTable("vendor_bank_accounts");
        $specialties = $db->prefixTable("vendor_specialties");
        $documents = $db->prefixTable("vendor_documents");
        $doc_types = $db->prefixTable("vendor_document_types");

        $vendor = $db->query("SELECT * FROM $vendors WHERE id=? AND deleted=0 LIMIT 1", [$vendor_id])->getRow();
        $vendor_group_id = (int) ($vendor->vendor_group_id ?? 0);

        $profile_complete = $vendor
            && trim((string) ($vendor->vendor_name ?? "")) !== ""
            && trim((string) ($vendor->email ?? "")) !== ""
            && trim((string) ($vendor->cr_number ?? "")) !== ""
            && trim((string) ($vendor->phone ?? "")) !== "";

        $contact_count = (int) ($db->query("SELECT COUNT(*) AS total FROM $contacts WHERE vendor_id=? AND deleted=0", [$vendor_id])->getRow()->total ?? 0);
        $bank_count = (int) ($db->query("SELECT COUNT(*) AS total FROM $bank WHERE vendor_id=? AND deleted=0 AND status!='rejected'", [$vendor_id])->getRow()->total ?? 0);
        $specialty_count = (int) ($db->query("SELECT COUNT(*) AS total FROM $specialties WHERE vendor_id=? AND deleted=0 AND status!='rejected'", [$vendor_id])->getRow()->total ?? 0);
        $submitted_doc_count = (int) ($db->query("SELECT COUNT(*) AS total FROM $documents WHERE vendor_id=? AND deleted=0 AND status!='rejected'", [$vendor_id])->getRow()->total ?? 0);

        $required_rows = $db->query(
            "SELECT id, name
             FROM $doc_types
             WHERE deleted=0
               AND is_active=1
               AND is_required=1
               AND (vendor_group_id IS NULL OR vendor_group_id=0 OR vendor_group_id=?)
             ORDER BY name ASC",
            [$vendor_group_id]
        )->getResult();

        $required_doc_total = count($required_rows);
        $submitted_required_docs = 0;
        foreach ($required_rows as $row) {
            $has_doc = $db->query(
                "SELECT id
                 FROM $documents
                 WHERE vendor_id=?
                   AND vendor_document_type_id=?
                   AND deleted=0
                   AND status!='rejected'
                 LIMIT 1",
                [$vendor_id, (int) $row->id]
            )->getRow();
            if ($has_doc) {
                $submitted_required_docs++;
            }
        }

        $required_docs_complete = $required_doc_total === 0 ? $submitted_doc_count > 0 : $submitted_required_docs >= $required_doc_total;

        $expiry_rows = $db->query(
            "SELECT vd.*, vdt.name AS document_type_name
             FROM $documents vd
             LEFT JOIN $doc_types vdt ON vdt.id=vd.vendor_document_type_id
             WHERE vd.vendor_id=?
               AND vd.deleted=0
               AND vd.status!='rejected'
               AND vd.expires_at IS NOT NULL
               AND vd.expires_at <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
             ORDER BY vd.expires_at ASC
             LIMIT 8",
            [$vendor_id]
        )->getResult();

        $items = [
            ["label" => "Company profile", "done" => (bool) $profile_complete, "hint" => "Name, email, CR number, and phone"],
            ["label" => "Primary contacts", "done" => $contact_count > 0, "hint" => $contact_count . " contact(s) recorded"],
            ["label" => "Bank account", "done" => $bank_count > 0, "hint" => $bank_count . " account(s) submitted"],
            ["label" => "Specialties", "done" => $specialty_count > 0, "hint" => $specialty_count . " specialty record(s) submitted"],
            ["label" => "Required documents", "done" => (bool) $required_docs_complete, "hint" => $submitted_required_docs . "/" . max(1, $required_doc_total) . " submitted"],
        ];

        $completed = 0;
        foreach ($items as $item) {
            if (!empty($item["done"])) {
                $completed++;
            }
        }

        return [
            "items" => $items,
            "completed" => $completed,
            "total" => count($items),
            "percent" => count($items) ? (int) round(($completed / count($items)) * 100) : 0,
            "expiring_documents" => $expiry_rows,
        ];
    }

    function contacts()
    {
        $vendor_id = $this->_require_vendor_access();

        $view_data["is_locked"] = $this->_is_vendor_module_locked($vendor_id, "contacts");
        $view_data["can_manage_contacts"] = $this->_can_manage_vendor_contacts($vendor_id);

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
        $view_data["can_edit_profile"] = $this->_can_edit_vendor_profile();

        // show latest review comment (if admin marked request as review)
        $view_data["review_request"] = $this->_get_vendor_latest_review_request($vendor_id, "branches");

        // IMPORTANT for tabs: use view() not rander() to avoid JS redeclare issues
        return $this->template->view("vendor_portal/branches/index", $view_data);
    }

    function credentials()
    {
        $vendor_id = $this->_require_vendor_access();

        $view_data["is_locked"] = $this->_is_vendor_module_locked($vendor_id, "credentials");
        $view_data["can_edit_profile"] = $this->_can_edit_vendor_profile();

        // ✅ show latest "review" request comment for this module
        $view_data["review_request"] = $this->_get_vendor_latest_review_request($vendor_id, "credentials");

        return $this->template->view("vendor_portal/credentials/index", $view_data);
    }


    function specialties()
    {
        $vendor_id = $this->_require_vendor_access();

        $view_data["is_locked"] = $this->_is_vendor_module_locked($vendor_id, "specialties");
        $view_data["can_edit_profile"] = $this->_can_edit_vendor_profile();

        // ✅ show latest "review" request comment for this module
        $view_data["review_request"] = $this->_get_vendor_latest_review_request($vendor_id, "specialties");

        return $this->template->view("vendor_portal/specialties/index", $view_data);
    }


    function documents()
    {
        $vendor_id = $this->_require_vendor_access();

        $view_data["is_locked"] = $this->_is_vendor_module_locked($vendor_id, "documents");
        $view_data["can_edit_profile"] = $this->_can_edit_vendor_profile();

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

        $vendor_id = $this->_require_vendor_profile_write_access();
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

        $vendor_id = $this->_require_vendor_profile_write_access();


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

        $vendor_id = $this->_require_vendor_profile_write_access();
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
        if (!$is_locked && $this->_can_edit_vendor_profile()) {
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




        $vendor_id = $this->_require_vendor_profile_write_access();





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

            $vendor_id = $this->_require_vendor_profile_write_access();

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

        $vendor_id = $this->_require_vendor_profile_write_access();


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
        if (!$is_locked && $this->_can_edit_vendor_profile()) {
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

        $vendor_id = $this->_require_vendor_profile_write_access();



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

        $vendor_id = $this->_require_vendor_profile_write_access();


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
            try {
                $stored = (new Upload_security())->storeUploadedFile(
                    $file,
                    $upload_dir,
                    Upload_security::CONTEXT_SECURITY_DOCUMENT,
                    'vd_'
                );
            } catch (UploadSecurityException $e) {
                log_message('notice', 'Vendor document upload rejected.');
                return $this->response->setStatusCode(422)->setJSON([
                    'success' => false,
                    'message' => app_lang('invalid_file_type'),
                ]);
            }
            $new_name = $stored['stored_name'];

            $data["disk"] = "local";
            $data["path"] = "vendor_documents/vendor_" . $vendor_id . "/" . $new_name;
            $data["original_name"] = $stored['original_name'];
            $data["mime_type"] = $stored['detected_mime'];
            $data["size_bytes"] = $stored['size_bytes'];
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

        $full_path = $this->_resolve_protected_upload_path(
            (string)$doc->path,
            'vendor_documents'
        );

        if (!$full_path) {
            show_404();
        }

        $download_name = $doc->original_name ?: basename($full_path);

        return $this->response->download($full_path, null)->setFileName($download_name);
    }

    public function delete_document()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);

        $vendor_id = $this->_require_vendor_profile_write_access();

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

        $issued  = format_to_date($data->issued_at ?? null, false) ?: "-";
        $expires = $this->_document_expiry_badge($data->expires_at ?? null);

        $approval = $this->_approval_badge($data->status ?? "pending");

        $size = $data->size_bytes
            ? number_format($data->size_bytes / 1024, 2) . " KB"
            : "-";

        $uploaded_by = $data->uploaded_by_name ?? "-";

        $actions = "";
        if (!$is_locked && $this->_can_edit_vendor_profile()) {
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

    private function _document_expiry_badge($expires_at): string
    {
        $date = format_to_date($expires_at, false);
        if ($date === "") {
            return "-";
        }

        $expiry_ts = strtotime((string) $expires_at);
        if (!$expiry_ts) {
            return esc($date);
        }

        $today = strtotime(date("Y-m-d"));
        $days = (int) floor(($expiry_ts - $today) / 86400);

        if ($days < 0) {
            return "<span class='badge bg-danger'>" . esc($date) . " (Expired)</span>";
        }

        if ($days <= 30) {
            return "<span class='badge bg-warning text-dark'>" . esc($date) . " (" . $days . "d)</span>";
        }

        return "<span class='badge bg-success'>" . esc($date) . "</span>";
    }



    // -------------------------
    // Credentials CRUD endpoints
    // -------------------------

    function credential_modal_form()
    {
        $this->validate_submitted_data(["id" => "numeric"]);

        $vendor_id = $this->_require_vendor_profile_write_access();
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

        $vendor_id = $this->_require_vendor_profile_write_access();
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

        $vendor_id = $this->_require_vendor_profile_write_access();
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
        $issue  = format_to_date($data->issue_date ?? null, false) ?: "-";
        $expiry = format_to_date($data->expiry_date ?? null, false) ?: "-";

        $approval = $this->_approval_badge($data->status ?? "pending");

        $actions = "";

        if (!$is_locked && $this->_can_edit_vendor_profile()) {
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

        $vendor_id = $this->_require_vendor_contact_owner();
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
        $view_data["portal_access_roles"] = $this->Vendor_users_model->get_assignable_roles();
        $view_data["portal_access_role"] = Vendor_users_model::ROLE_VIEWER;
        return $this->template->view("vendor_portal/contacts/modal_form", $view_data);
    }


    function contacts_list_data()
    {
        $vendor_id = $this->_require_vendor_access();

        $is_locked = $this->_is_vendor_module_locked($vendor_id, "contacts");
        $can_manage_contacts = $this->_can_manage_vendor_contacts($vendor_id);

        $list_data = $this->Vendor_contacts_model->get_details(array(
            "vendor_id" => $vendor_id
        ))->getResult();

        $result = array();
        foreach ($list_data as $data) {
            $result[] = $this->_make_contact_row($data, $is_locked, $can_manage_contacts);
        }

        echo json_encode(array("data" => $result));
    }

    function save_contact()
    {
        if (strtolower($this->request->getMethod()) !== "post") {
            return $this->response
                ->setStatusCode(405)
                ->setHeader("Allow", "POST")
                ->setJSON([
                    "success" => false,
                    "message" => "This action requires a POST request.",
                ]);
        }

        $this->validate_submitted_data([
            "id" => "numeric",
            "contacts_name" => "required",
            "email" => "required|valid_email|max_length[255]",
            "email_2" => "permit_empty|valid_email|max_length[255]",
            "access_role" => "required|in_list[VIEWER,BIDDER,EDITOR]"
        ]);

        $vendor_id = $this->_require_vendor_contact_owner();


        $id = (int) $this->request->getPost("id");
        $email = Vendor_contact_access::canonicalEmail($this->request->getPost("email"));
        $accessRoleCode = strtoupper(trim((string) $this->request->getPost("access_role")));
        $accessRole = $this->Vendor_users_model->get_assignable_role($accessRoleCode);
        if (!$accessRole) {
            return $this->response->setStatusCode(422)->setJSON([
                "success" => false,
                "message" => "Select a valid least-privilege portal access role.",
            ]);
        }

        // Keep raw secrets out of clean_data(), contact rows, approval JSON,
        // logs, and responses.
        $initial_password = (string) $this->request->getPost("initial_password");
        $initial_password_confirm = (string) $this->request->getPost("initial_password_confirm");

        if ($id) {
            $this->_deny_if_vendor_module_locked($vendor_id, "contacts");
        }

        // capture "before" ONLY if editing
        $before = null;
        if ($id) {
            $row = $this->Vendor_contacts_model->get_one($id);
            if (empty($row->id) || (int) $row->vendor_id !== (int) $vendor_id || (int) $row->deleted) {
                app_redirect("forbidden");
            }
            $before = $row;
            if (!empty($row->user_id)
                && Vendor_contact_access::canonicalEmail($row->email) !== $email
            ) {
                echo json_encode([
                    "success" => false,
                    "message" => "The login email cannot be changed after this contact is linked to an account. Add a new contact instead."
                ]);
                return;
            }

            if (!empty($row->user_id)
                && ($initial_password !== "" || $initial_password_confirm !== "")
            ) {
                echo json_encode([
                    "success" => false,
                    "message" => "A contact manager cannot replace an existing account password. The user can change it after signing in."
                ]);
                return;
            }
        }

        $transactionStarted = false;
        try {
            if ($this->Vendor_contact_access->duplicateContactExists($vendor_id, $email, $id)) {
                echo json_encode([
                    "success" => false,
                    "message" => "This email is already a contact for the selected CR. The same email may only be reused under a different CR."
                ]);
                return;
            }

            $existingUser = $this->Vendor_contact_access->findUserByEmail($email);
            if ($existingUser && (string) $existingUser->user_type !== "staff") {
                echo json_encode([
                    "success" => false,
                    "message" => "This email belongs to a non-staff account and cannot be linked to the vendor portal."
                ]);
                return;
            }

            if ($before && !empty($before->user_id)
                && (!$existingUser || (int) $existingUser->id !== (int) $before->user_id)
            ) {
                echo json_encode([
                    "success" => false,
                    "message" => "The linked login account does not match this contact email. Ask an administrator to resolve it."
                ]);
                return;
            }

            $needsAccessPreparation = !$before || empty($before->user_id);
            if ($needsAccessPreparation && !$existingUser) {
                $passwordLength = mb_strlen($initial_password, "UTF-8");
                $passwordBytes = strlen($initial_password);
                if ($passwordLength < 10 || $passwordBytes > 72) {
                    echo json_encode([
                        "success" => false,
                        "message" => "Set an initial password of at least 10 characters and no more than 72 UTF-8 bytes."
                    ]);
                    return;
                }
                if (!hash_equals($initial_password, $initial_password_confirm)) {
                    echo json_encode([
                        "success" => false,
                        "message" => "The password confirmation does not match."
                    ]);
                    return;
                }
            }

            $data = clean_data([
                "vendor_id"     => $vendor_id,
                "user_id"       => $before && !empty($before->user_id)
                    ? (int) $before->user_id
                    : ($existingUser ? (int) $existingUser->id : null),
                "contacts_name" => trim((string) $this->request->getPost("contacts_name")),
                "phone"         => trim((string) $this->request->getPost("phone")),
                "fax"           => trim((string) $this->request->getPost("fax")),
                "designation"   => trim((string) $this->request->getPost("designation")),
                "email"         => $email,
                "email_2"       => Vendor_contact_access::canonicalEmail($this->request->getPost("email_2")),
                "mobile"        => trim((string) $this->request->getPost("mobile")),
                "role"          => trim((string) $this->request->getPost("role")),
                "is_primary"    => $this->request->getPost("is_primary") ? 1 : 0,
                "is_active"     => $this->request->getPost("is_active") ? 1 : 0,
                "status"        => "pending",
                "updated_at"    => get_current_utc_time(),
            ]);
            // clean_data() converts null to an empty string; restore SQL NULL
            // so the optional user foreign key remains valid until approval.
            if (empty($data["user_id"])) {
                $data["user_id"] = null;
            }
            if (!$id) {
                $data["created_at"] = get_current_utc_time();
            }

            $this->db->transBegin();
            $transactionStarted = true;
            $saveResult = $this->Vendor_contacts_model->ci_save($data, $id);
            if (!$saveResult) {
                throw new \RuntimeException("Unable to save the contact.");
            }
            $save_id = $id ?: (int) $saveResult;

            $access = null;
            if ($needsAccessPreparation) {
                $access = $this->Vendor_contact_access->prepareContactAccess(
                    $save_id,
                    $initial_password,
                    (int) $this->login_user->id
                );
                // Keep the approval snapshot aligned with the linked row while
                // keeping the password/hash outside that snapshot.
                $data["user_id"] = (int) $access["user_id"];
                $preparedMembership = $this->Vendor_users_model->find_membership(
                    $vendor_id,
                    (int) $access["user_id"]
                );
                $membershipData = [
                    "status" => (string) ($preparedMembership->status ?? "invited"),
                    "invited_by" => (int) $this->login_user->id,
                ];
                // Never replace or downgrade the registration owner's role.
                if (!(int) ($preparedMembership->is_owner ?? 0)) {
                    $membershipData["vendor_role_id"] = (int) $accessRole->id;
                }
                $membershipId = $this->Vendor_users_model->upsert_membership(
                    $vendor_id,
                    (int) $access["user_id"],
                    $membershipData
                );
                if (!$membershipId) {
                    throw new \RuntimeException("Unable to assign the contact portal role.");
                }
            }

            // create approval request
            $changes = [
                "module"    => "contacts",
                "table"     => "vendor_contacts",
                "action"    => $id ? "update" : "create",
                "record_id" => (int)$save_id,
                "before"    => $before,
                "after"     => $data,
                "portal_access_role" => $accessRoleCode,
            ];


            // create (or re-submit) approval request
            $vurTable = $this->db->prefixTable("vendor_update_requests");

            $existing_review = $this->_get_vendor_review_request_for_record($vendor_id, "contacts", (int)$save_id);

            if ($existing_review) {
                // ✅ If admin previously marked it as "review", re-submit SAME request as pending
                $ok = $this->db->table($vurTable)
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
                    throw new \RuntimeException("Unable to re-submit the contact approval request.");
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

                if (!$this->Vendor_update_requests_model->ci_save($req)) {
                    throw new \RuntimeException("Unable to create the contact approval request.");
                }
            }

            if ($this->db->transStatus() === false) {
                throw new \RuntimeException("The contact transaction failed.");
            }
            $this->db->transCommit();
            $transactionStarted = false;

            $message = app_lang("record_saved");
            if ($access) {
                $message = !empty($access["uses_existing_password"])
                    ? "Contact submitted for approval. This person's existing password was retained."
                    : "Contact submitted for approval. The initial password will work after approval.";
            }

            echo json_encode([
                "success" => true,
                "data" => $this->_contact_row_data($save_id),
                "id" => $save_id,
                "message" => $message
            ]);
            return;
        } catch (\Throwable $e) {
            if ($transactionStarted) {
                $this->db->transRollback();
            }
            log_message("error", "VENDOR CONTACT SAVE FAILED: " . $e->getMessage());

            $isDuplicate = false;
            try {
                $isDuplicate = $this->Vendor_contact_access->duplicateContactExists($vendor_id, $email, $id);
            } catch (\Throwable $ignored) {
                // Preserve the original error and avoid exposing database details.
            }

            if ($isDuplicate) {
                $message = "This email is already a contact for the selected CR. The same email may only be reused under a different CR.";
            } elseif ($e instanceof \CodeIgniter\Database\Exceptions\DatabaseException) {
                $message = app_lang("error_occurred");
            } elseif ($e instanceof \RuntimeException) {
                $message = $e->getMessage();
            } else {
                $message = app_lang("error_occurred");
            }

            echo json_encode(["success" => false, "message" => $message]);
            return;
        }
    }




    function delete_contact()
    {
        if (strtolower($this->request->getMethod()) !== "post") {
            return $this->response
                ->setStatusCode(405)
                ->setHeader("Allow", "POST")
                ->setJSON([
                    "success" => false,
                    "message" => "This action requires a POST request.",
                ]);
        }

        $this->validate_submitted_data(["id" => "required|numeric"]);

        $vendor_id = $this->_require_vendor_contact_owner();

        // ✅ Block delete while pending
        $this->_deny_if_vendor_module_locked($vendor_id, "contacts");

        $id = (int) $this->request->getPost("id");

        $row = $this->Vendor_contacts_model->get_one($id);
        if (empty($row->id) || (int) $row->vendor_id !== (int) $vendor_id) {
            app_redirect("forbidden");
        }

        $this->db->transBegin();
        try {
            if ($this->request->getPost("undo")) {
                if (!$this->Vendor_contacts_model->delete($id, true)) {
                    throw new \RuntimeException(app_lang("error_occurred"));
                }

                $restored = $this->Vendor_contacts_model->get_one($id);
                if ((string) $restored->status === "approved" && (int) $restored->is_active) {
                    $this->Vendor_contact_access->approveContact($id, (int) $this->login_user->id);
                }

                if ($this->db->transStatus() === false) {
                    throw new \RuntimeException("Unable to restore the contact access.");
                }
                $this->db->transCommit();
                echo json_encode([
                    "success" => true,
                    "data" => $this->_contact_row_data($id),
                    "message" => app_lang("record_undone")
                ]);
                return;
            }

            if (!$this->Vendor_contacts_model->delete($id)) {
                throw new \RuntimeException(app_lang("record_cannot_be_deleted"));
            }
            if (!$this->Vendor_contact_access->suspendContactMembership($id)) {
                throw new \RuntimeException("The contact was deleted, but its vendor access could not be suspended.");
            }
            if ($this->db->transStatus() === false) {
                throw new \RuntimeException("Unable to delete the contact.");
            }
            $this->db->transCommit();
            echo json_encode(["success" => true, "message" => app_lang("record_deleted")]);
            return;
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message("error", "VENDOR CONTACT DELETE FAILED: " . $e->getMessage());
            echo json_encode(["success" => false, "message" => app_lang("error_occurred")]);
            return;
        }
    }




    function contact_password_modal_form()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);

        $vendor_id = $this->_require_vendor_contact_owner();
        $id = (int) $this->request->getPost("id");
        $contact = $this->Vendor_contacts_model->get_details([
            "id" => $id,
            "vendor_id" => $vendor_id,
        ])->getRow();

        if (!$this->_can_set_legacy_contact_password($contact)) {
            app_redirect("forbidden");
        }

        return $this->template->view(
            "vendor_portal/contacts/password_modal_form",
            ["model_info" => $contact]
        );
    }

    function save_contact_password()
    {
        if (strtolower($this->request->getMethod()) !== "post") {
            return $this->response
                ->setStatusCode(405)
                ->setHeader("Allow", "POST")
                ->setJSON([
                    "success" => false,
                    "message" => "This action requires a POST request.",
                ]);
        }

        $this->validate_submitted_data([
            "id" => "required|numeric",
            "initial_password" => "required|min_length[10]|max_length[72]",
            "initial_password_confirm" => "required|matches[initial_password]",
        ]);

        $vendor_id = $this->_require_vendor_contact_owner();
        $this->_deny_if_vendor_module_locked($vendor_id, "contacts");

        $id = (int) $this->request->getPost("id");
        $contact = $this->Vendor_contacts_model->get_details([
            "id" => $id,
            "vendor_id" => $vendor_id,
        ])->getRow();
        if (!$this->_can_set_legacy_contact_password($contact)) {
            app_redirect("forbidden");
        }

        $transactionStarted = false;
        try {
            $this->db->transBegin();
            $transactionStarted = true;

            $this->Vendor_contact_access->setInitialPasswordForApprovedContact(
                $id,
                (string) $this->request->getPost("initial_password"),
                (int) $this->login_user->id
            );

            if ($this->db->transStatus() === false) {
                throw new \RuntimeException("Unable to activate the contact login.");
            }

            $this->db->transCommit();
            $transactionStarted = false;

            echo json_encode([
                "success" => true,
                "data" => $this->_contact_row_data($id),
                "id" => $id,
                "message" => "The initial password was saved and this contact can now sign in.",
            ]);
            return;
        } catch (\Throwable $e) {
            if ($transactionStarted) {
                $this->db->transRollback();
            }

            log_message("error", "VENDOR CONTACT PASSWORD SETUP FAILED: " . $e->getMessage());
            $message = $e instanceof \CodeIgniter\Database\Exceptions\DatabaseException
                ? app_lang("error_occurred")
                : ($e instanceof \RuntimeException ? $e->getMessage() : app_lang("error_occurred"));

            echo json_encode(["success" => false, "message" => $message]);
            return;
        }
    }

    private function _can_set_legacy_contact_password($contact): bool
    {
        return $contact
            && !empty($contact->id)
            && (string) ($contact->status ?? "") === "approved"
            && (int) ($contact->is_active ?? 0) === 1
            && (string) ($contact->portal_access_status ?? "") === "invited"
            && !empty($contact->portal_invited_at)
            && empty($contact->portal_credentials_ready_at)
            && ((string) ($contact->account_status ?? "") !== "active"
                || (int) ($contact->account_login_disabled ?? 0) === 1);
    }

    private function _contact_row_data($id)
    {
        $vendor_id = $this->_require_vendor_access();
        $is_locked = $this->_is_vendor_module_locked($vendor_id, "contacts");
        $can_manage_contacts = $this->_can_manage_vendor_contacts($vendor_id);

        $data = $this->Vendor_contacts_model->get_details(array(
            "id" => $id,
            "vendor_id" => $vendor_id
        ))->getRow();

        return $this->_make_contact_row($data, $is_locked, $can_manage_contacts);
    }

    private function _make_contact_row($data, bool $is_locked = false, bool $can_manage_contacts = false)
    {
        $active = $data->is_active
            ? "<span class='badge bg-success'>" . app_lang("active") . "</span>"
            : "<span class='badge bg-secondary'>" . app_lang("inactive") . "</span>";

        $primary = $data->is_primary
            ? "<span class='badge bg-primary'>" . app_lang("primary") . "</span>"
            : "";

        $approval = $this->_approval_badge($data->status ?? "pending");

        $accessStatus = strtolower((string) ($data->portal_access_status ?? ""));
        if ($accessStatus === "active") {
            $access = "<span class='badge bg-success'>Portal active</span>";
            if (!empty($data->portal_is_owner)) {
                $access .= " <span class='badge bg-primary'>Owner</span>";
            }
        } elseif ($accessStatus === "invited") {
            if ((string) ($data->status ?? "") !== "approved") {
                $access = "<span class='badge bg-light text-dark'>Awaiting approval</span>";
            } elseif ((string) ($data->account_status ?? "") === "active"
                && !(int) ($data->account_login_disabled ?? 0)
            ) {
                $access = "<span class='badge bg-warning text-dark'>Access activation required</span>";
            } else {
                $access = "<span class='badge bg-warning text-dark'>Password setup required</span>";
            }
        } elseif ($accessStatus === "suspended") {
            $access = "<span class='badge bg-secondary'>Portal suspended</span>";
        } elseif ((string) ($data->status ?? "") === "approved") {
            $access = "<span class='badge bg-secondary'>Not provisioned</span>";
        } else {
            $access = "<span class='badge bg-light text-dark'>Awaiting approval</span>";
        }

        $actions = "";
        if ($can_manage_contacts && !$is_locked) {
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

        if ($can_manage_contacts && !$is_locked && $this->_can_set_legacy_contact_password($data)) {
            $actions .= " " . modal_anchor(
                get_uri("vendor_portal/contact_password_modal_form"),
                "<i data-feather='key' class='icon-16'></i>",
                [
                    "class" => "text-primary",
                    "title" => "Set initial password",
                    "data-post-id" => (int) $data->id,
                ]
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
            $access,
            $actions
        );
    }
}
