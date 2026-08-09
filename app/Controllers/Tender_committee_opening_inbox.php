<?php

namespace App\Controllers;

use App\Libraries\TenderOpeningCodeVaultException;
use App\Libraries\Upload_security;
use App\Libraries\UploadSecurityException;
use App\Models\Tender_bids_model;
use App\Models\Tender_bid_documents_model;
use App\Models\Tender_bid_openings_model;
use App\Models\Tenders_model;

class Tender_committee_opening_inbox extends Security_Controller
{
    protected $Tender_bids_model;
    protected $Tender_bid_documents_model;
    protected $Tender_bid_openings_model;
    protected $Tenders_model;

    function __construct()
    {
        parent::__construct();
        $this->access_only_team_members();

        $this->Tender_bids_model = new Tender_bids_model();
        $this->Tender_bid_documents_model = new Tender_bid_documents_model();
        $this->Tender_bid_openings_model = new Tender_bid_openings_model();
        $this->Tenders_model = new Tenders_model();
    }

    function index()
    {
        $this->access_only_tender("committee", "view");
        return $this->template->rander("tender_committee_opening_inbox/index");
    }

    function list_data()
    {
        $this->access_only_tender("committee", "view");

        $rows = $this->Tender_bids_model->get_tenders_ready_for_3key_opening((int) $this->login_user->id);
        $result = [];

        foreach ($rows as $row) {
            $opening_stage = (string) ($row->opening_stage ?? "technical");
            $session = $this->Tender_bid_openings_model->get_active_session((int) $row->id, $opening_stage);
            $status = $session ? $session->status : "pending";
            $stage_label = "Bid Opening";
            $details_url = get_uri("tender_committee_opening_inbox/details/" . (int) $row->id);
            $reference = esc($row->reference);
            $title = esc($row->title);
            $action = modal_anchor(
                get_uri("tender_committee_opening_inbox/modal_form"),
                "<i data-feather='unlock' class='icon-16'></i>",
                [
                    "title" => "3-Key " . $stage_label,
                    "data-post-id" => $row->id,
                    "data-post-stage" => $opening_stage,
                    "class" => "edit"
                ]
            );

            if ($session && in_array((string) $status, ["unlocked", "signed", "manual_accepted"], true)) {
                $reference = anchor($details_url, $reference);
                $title = anchor($details_url, $title);
                $action = anchor($details_url, "<i data-feather='eye' class='icon-16'></i>", ["title" => "Review opened bids", "class" => "edit"]);
            }

            $result[] = [
                $reference,
                $title,
                esc($stage_label),
                (int) $row->bids_count,
                "<span class='badge bg-secondary'>" . esc(ucfirst($status)) . "</span>",
                !empty($row->opening_end_at) ? format_to_datetime($row->opening_end_at) : "-",
                $action
            ];
        }

        return $this->response->setJSON(["data" => $result]);
    }

    function details($id = 0)
    {
        $this->access_only_tender("committee", "view");

        $tender_id = (int) $id;
        if (!$tender_id) {
            show_404();
        }

        $stage = "technical";
        $tender = $this->_get_committee_stage_tender($tender_id, $stage);
        if (!$tender) {
            show_404();
        }

        $session = $this->Tender_bid_openings_model->get_active_session($tender_id, $stage);
        if (!$session || !in_array((string) $session->status, ["unlocked", "signed", "manual_accepted"], true)) {
            app_redirect("tender_committee_opening_inbox");
        }

        return $this->template->rander("tender_committee_opening_inbox/details", [
            "tender" => $tender,
            "session" => $session,
            "signature_map" => $this->Tender_bid_openings_model->get_signature_map((int) $session->id),
            "bid_summary" => $this->Tender_bid_openings_model->get_bid_summary_for_opening($tender_id),
            "signature_rows" => $this->Tender_bid_openings_model->get_signature_rows((int) $session->id),
            "my_role" => $this->_get_current_committee_role($tender_id),
            "opening_stage" => $stage,
            "opening_title" => "Bid Opening",
        ]);
    }

    function modal_form()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);
        $this->access_only_tender("committee", "view");

        $tender_id = (int) $this->request->getPost("id");
        $stage = $this->_normalize_opening_stage($this->request->getPost("stage"));
        $tender = $this->_get_committee_stage_tender($tender_id, $stage);

        if (!$tender) {
            show_404();
        }

        $session = $this->Tender_bid_openings_model->get_active_session($tender_id, $stage);
        $confirm_map = $session ? $this->Tender_bid_openings_model->get_confirmation_map((int) $session->id) : [
            "chairman" => 0,
            "secretary" => 0,
            "itc_member" => 0,
        ];
        $signature_map = $session ? $this->Tender_bid_openings_model->get_signature_map((int) $session->id) : [
            "chairman" => 0,
            "secretary" => 0,
            "itc_member" => 0,
        ];

        $role = $this->_get_current_committee_role($tender_id);
        $my_code = null;
        $code_unavailable = false;
        if (
            $session
            && (string) ($session->status ?? "") === "codes_generated"
            && (!$role || (int) ($confirm_map[$role] ?? 0) < 1)
        ) {
            if (!$role) {
                $code_unavailable = true;
            } else {
                try {
                    $my_code = $this->Tender_bid_openings_model->getRoleCodeForDisplay(
                        (int) $session->id,
                        (int) $this->login_user->id,
                        $role
                    );
                } catch (\Throwable $e) {
                    $code_unavailable = true;
                    log_message("warning", "Assigned tender opening code could not be displayed.");
                }
            }
        }

        $this->response
            ->setHeader("Cache-Control", "private, no-store")
            ->setHeader("Pragma", "no-cache");

        return $this->template->view("tender_committee_opening_inbox/modal_form", [
            "tender" => $tender,
            "session" => $session,
            "confirm_map" => $confirm_map,
            "signature_map" => $signature_map,
            "bid_summary" => $this->Tender_bid_openings_model->get_bid_summary_for_opening($tender_id),
            "signature_rows" => $session ? $this->Tender_bid_openings_model->get_signature_rows((int) $session->id) : [],
            "my_role" => $role,
            "my_code" => $my_code,
            "code_unavailable" => $code_unavailable,
            "opening_stage" => $stage,
            "opening_title" => "Bid Opening",
        ]);
    }

    function generate_codes()
    {
        $this->validate_submitted_data([
            "tender_id" => "required|numeric",
            "opening_stage" => "required",
        ]);
        $this->access_only_tender("committee", "update");

        if (!$this->can_tender_3key_opening()) {
            app_redirect("forbidden");
        }

        $tender_id = (int) $this->request->getPost("tender_id");
        $stage = $this->_normalize_opening_stage($this->request->getPost("opening_stage"));

        if (!$this->_get_current_committee_role($tender_id)) {
            return $this->response->setJSON(["success" => false, "message" => "You are not assigned to this ITC opening."]);
        }

        $tender = $this->_get_committee_stage_tender($tender_id, $stage);
        if (!$tender) {
            return $this->response->setJSON(["success" => false, "message" => "This tender is not currently in the requested 3-key stage."]);
        }

        try {
            $id = $this->Tender_bid_openings_model->create_new_session(
                $tender_id,
                (int) $this->login_user->id,
                $stage
            );
        } catch (\DomainException $e) {
            log_message("notice", "Tender opening code regeneration was refused by session state.");
            return $this->response
                ->setStatusCode(409)
                ->setJSON([
                    "success" => false,
                    "message" => "Opening codes cannot be regenerated after a confirmation attempt or after bids are unlocked.",
                ]);
        } catch (TenderOpeningCodeVaultException $e) {
            log_message("critical", "Tender opening code protection is unavailable.");
            return $this->response
                ->setStatusCode(503)
                ->setJSON([
                    "success" => false,
                    "message" => "Secure bid opening is temporarily unavailable. Contact the system administrator.",
                ]);
        } catch (\Throwable $e) {
            log_message("error", "Tender opening codes could not be generated.");
            return $this->response
                ->setStatusCode(500)
                ->setJSON(["success" => false, "message" => app_lang("error_occurred")]);
        }

        return $this->response->setJSON([
            "success" => true,
            "message" => "3-key bid opening codes generated.",
            "opening_id" => $id
        ]);
    }

    function confirm_codes()
    {
        $this->validate_submitted_data([
            "tender_id" => "required|numeric",
            "opening_stage" => "required",
            "opening_code" => "required|numeric|exact_length[6]",
        ]);
        $this->access_only_tender("committee", "update");

        if (!$this->can_tender_3key_opening()) {
            app_redirect("forbidden");
        }

        $tender_id = (int) $this->request->getPost("tender_id");
        $stage = $this->_normalize_opening_stage($this->request->getPost("opening_stage"));
        $role = $this->_get_current_committee_role($tender_id);

        if (!$role) {
            return $this->response->setJSON(["success" => false, "message" => "You are not assigned to this ITC opening."]);
        }

        $tender = $this->_get_committee_stage_tender($tender_id, $stage);
        if (!$tender) {
            return $this->response->setJSON(["success" => false, "message" => "This tender is not currently in the requested 3-key stage."]);
        }

        $session = $this->Tender_bid_openings_model->get_active_session($tender_id, $stage);
        if (!$session || $session->status !== "codes_generated") {
            return $this->response->setJSON(["success" => false, "message" => "No active 3-key session found."]);
        }

        try {
            $result = $this->Tender_bid_openings_model->confirmRoleCode(
                (int) $session->id,
                (int) $this->login_user->id,
                $role,
                (string) $this->request->getPost("opening_code")
            );
        } catch (TenderOpeningCodeVaultException $e) {
            log_message("critical", "Tender opening code protection is unavailable.");
            return $this->response
                ->setStatusCode(503)
                ->setJSON([
                    "success" => false,
                    "message" => "Secure bid opening is temporarily unavailable. Contact the system administrator.",
                ]);
        } catch (\Throwable $e) {
            log_message("error", "Tender opening confirmation could not be processed.");
            return $this->response
                ->setStatusCode(500)
                ->setJSON(["success" => false, "message" => app_lang("error_occurred")]);
        }

        if (($result["status"] ?? "") === "rate_limited") {
            $retry_after = max(1, (int) ($result["retry_after_seconds"] ?? 900));
            return $this->response
                ->setStatusCode(429)
                ->setHeader("Retry-After", (string) $retry_after)
                ->setJSON([
                    "success" => false,
                    "message" => "Too many unsuccessful confirmations. Try again later.",
                ]);
        }

        if (in_array((string) ($result["status"] ?? ""), ["invalid", "expired", "unavailable"], true)) {
            return $this->response->setJSON([
                "success" => false,
                "message" => "The opening code is invalid or expired.",
            ]);
        }

        if (($result["status"] ?? "") === "already_confirmed") {
            return $this->response->setJSON([
                "success" => false,
                "message" => "You or this committee role already confirmed this opening session.",
            ]);
        }

        if (!empty($result["unlocked"])) {
            return $this->response->setJSON([
                "success" => true,
                "message" => "Bids unlocked successfully. Committee members can now review the full bid package and sign the opening form.",
                "redirect_url" => get_uri("tender_committee_opening_inbox/details/" . $tender_id),
            ]);
        }

        return $this->response->setJSON([
            "success" => true,
            "message" => "Your assigned role code was confirmed. Waiting for the other committee roles."
        ]);
    }

    function sign_opening()
    {
        $this->validate_submitted_data([
            "tender_id" => "required|numeric",
            "opening_stage" => "required",
            "committee_signature_statement" => "required",
            "signature" => "required",
        ]);
        $this->access_only_tender("committee", "update");

        if (!$this->can_tender_3key_opening()) {
            app_redirect("forbidden");
        }

        $tender_id = (int) $this->request->getPost("tender_id");
        $stage = $this->_normalize_opening_stage($this->request->getPost("opening_stage"));
        $role = $this->_get_current_committee_role($tender_id);

        if (!$role) {
            return $this->_sign_opening_response(false, "You are not assigned to this ITC opening.", $tender_id);
        }

        $tender = $this->_get_committee_stage_tender($tender_id, $stage);
        if (!$tender) {
            return $this->_sign_opening_response(false, "This tender is not currently in the bid opening signature stage.", $tender_id);
        }

        $session = $this->Tender_bid_openings_model->get_active_session($tender_id, $stage);
        if (!$session || !in_array((string) $session->status, ["unlocked", "signed"], true)) {
            return $this->_sign_opening_response(false, "The bid package must be unlocked before signing.", $tender_id);
        }

        $statement = trim((string) $this->request->getPost("committee_signature_statement"));
        $signature_name = trim((string) $this->request->getPost("signature_name"));
        if ($signature_name === "") {
            $signature_name = trim((string) ($this->login_user->first_name ?? "") . " " . (string) ($this->login_user->last_name ?? ""));
        }
        if ($signature_name === "") {
            $signature_name = $this->login_user->email ?? ("User #" . (int) $this->login_user->id);
        }
        $previous_signature_path = $this->_get_existing_signature_image_path(
            (int) $session->id,
            (int) $this->login_user->id
        );
        try {
            $signature_image = $this->_save_signature_image(
                (string) $this->request->getPost("signature"),
                (int) $session->id
            );
        } catch (UploadSecurityException $e) {
            log_message("notice", "Tender opening signature image rejected.");
            return $this->_sign_opening_response(false, "The drawn signature could not be accepted.", $tender_id);
        }

        if (!$signature_image) {
            return $this->_sign_opening_response(false, "Draw your digital signature before signing the opening form.", $tender_id);
        }

        try {
            $saved = $this->Tender_bid_openings_model->save_signature(
                (int) $session->id,
                (int) $this->login_user->id,
                $role,
                $statement,
                $signature_name,
                $signature_image["relative_path"]
            );
        } catch (\Throwable $e) {
            $this->_remove_signature_file($signature_image["path"]);
            log_message("error", "Tender opening signature could not be recorded.");
            return $this->_sign_opening_response(false, app_lang("error_occurred"), $tender_id);
        }

        if (!$saved) {
            $this->_remove_signature_file($signature_image["path"]);
            return $this->_sign_opening_response(false, "Confirm the 3-key codes before signing the opening form.", $tender_id);
        }

        if ($previous_signature_path !== "" && $previous_signature_path !== $signature_image["relative_path"]) {
            $previous_full_path = (new Upload_security())->resolveStoredFile(
                $previous_signature_path,
                "tender_opening_signatures/opening_" . (int) $session->id
            );
            if ($previous_full_path) {
                @unlink($previous_full_path);
            }
        }

        $complete = $this->Tender_bid_openings_model->all_required_signatures_completed((int) $session->id);
        $message = $complete
            ? "All committee signatures are complete. Procurement can now download the bid opening form and start technical review."
            : "Your signature was saved. Waiting for the remaining committee signatures.";

        return $this->_sign_opening_response(true, $message, $tender_id, [
            "reload" => true,
            "redirect_url" => get_uri("tender_committee_opening_inbox/details/" . $tender_id),
        ]);
    }

    function bid_opening_form($id = 0)
    {
        $this->access_only_tender("committee", "view");

        $tender_id = (int) $id;
        if (!$tender_id) {
            show_404();
        }

        $stage = "technical";
        $tender = $this->_get_committee_stage_tender($tender_id, $stage);
        if (!$tender) {
            show_404();
        }

        $session = $this->Tender_bid_openings_model->get_active_session($tender_id, $stage);
        if (!$session || !in_array((string) $session->status, ["unlocked", "signed", "manual_accepted"], true)) {
            app_redirect("tender_committee_opening_inbox");
        }

        return $this->template->rander("tender_reports/bid_opening_form", [
            "tender" => $tender,
            "stage" => $stage,
            "vendors" => $this->Tender_bid_openings_model->get_bid_summary_for_opening($tender_id),
            "teams" => [],
            "opening_audit" => [],
            "opening_session" => $session,
            "signature_rows" => $this->Tender_bid_openings_model->get_signature_rows((int) $session->id),
            "signature_image_route" => "tender_committee_opening_inbox/signature_image",
            "manual_form_download_url" => get_uri("tender_committee_opening_inbox/download_manual_bid_opening_form/" . $tender_id),
            "back_url" => get_uri("tender_committee_opening_inbox/details/" . $tender_id),
            "back_label" => "Back to Bid Opening Review",
        ]);
    }

    function signature_image($id = 0)
    {
        $this->access_only_tender("committee", "view");
        $context = $this->_get_signature_image_context((int) $id);
        if (!$context || !$this->_get_committee_stage_tender((int) $context->tender_id, "technical")) {
            show_404();
        }

        $session = $this->Tender_bid_openings_model->get_active_session((int) $context->tender_id, "technical");
        if (!$session || (int) $session->id !== (int) $context->opening_id || !in_array((string) $session->status, ["unlocked", "signed"], true)) {
            app_redirect("forbidden");
        }

        $full_path = (new Upload_security())->resolveStoredFile(
            (string) $context->signature_image_path,
            "tender_opening_signatures/opening_" . (int) $context->opening_id
        );
        $info = $full_path ? @getimagesize($full_path) : false;
        if (!$full_path || !is_array($info) || (int) ($info[2] ?? 0) !== IMAGETYPE_PNG) {
            show_404();
        }

        return $this->response
            ->download($full_path, null)
            ->setFileName("signature.png")
            ->setContentType("image/png")
            ->setHeader("X-Content-Type-Options", "nosniff")
            ->setHeader("Cache-Control", "private, no-store")
            ->inline();
    }

    function download_manual_bid_opening_form($id = 0)
    {
        $this->access_only_tender("committee", "view");
        $tender_id = (int) $id;
        if (!$tender_id || !$this->_get_committee_stage_tender($tender_id, "technical")) {
            show_404();
        }

        $session = $this->Tender_bid_openings_model->get_active_session($tender_id, "technical");
        if (!$session || (string) $session->status !== "manual_accepted" || empty($session->manual_form_path)) {
            show_404();
        }
        $full_path = (new Upload_security())->resolveStoredFile(
            (string) $session->manual_form_path,
            "tender_opening_forms/tender_" . $tender_id
        );
        if (!$full_path) {
            show_404();
        }

        $name = str_replace(["\r", "\n", chr(34)], "", (string) ($session->manual_form_original_name ?: basename($full_path)));
        return $this->response
            ->download($full_path, null)
            ->setFileName($name)
            ->setHeader("X-Content-Type-Options", "nosniff")
            ->setHeader("Cache-Control", "private, no-store");
    }

    private function _save_signature_image(string $signature_data, int $opening_id): ?array
    {
        $data_prefix = "data:image/png;base64,";
        if (strpos($signature_data, $data_prefix) !== 0) {
            return null;
        }

        $encoded = substr($signature_data, strlen($data_prefix));
        $maximum = (new Upload_security())->maximumBytes(Upload_security::CONTEXT_SIGNATURE_IMAGE);
        $maximum_encoded = 4 * (int) ceil($maximum / 3) + 4;
        if ($encoded === "" || strlen($encoded) > $maximum_encoded) {
            return null;
        }

        $binary = base64_decode($encoded, true);
        if (!$binary) {
            return null;
        }

        $relative_dir = "tender_opening_signatures/opening_" . $opening_id . "/";
        $stored = (new Upload_security())->storeUntrustedBytes(
            $binary,
            "signature.png",
            WRITEPATH . "uploads/" . $relative_dir,
            Upload_security::CONTEXT_SIGNATURE_IMAGE,
            "signature_",
            ["png"]
        );

        return $stored + ["relative_path" => $relative_dir . $stored["stored_name"]];
    }

    private function _get_existing_signature_image_path(int $opening_id, int $user_id): string
    {
        $db = db_connect();
        $entries = $db->prefixTable("tender_bid_opening_entries");
        $row = $db->query(
            "SELECT signature_image_path
             FROM $entries
             WHERE tender_bid_opening_id = ?
               AND user_id = ?
               AND deleted = 0
               AND is_valid = 1
             LIMIT 1",
            [$opening_id, $user_id]
        )->getRow();

        return (string) ($row->signature_image_path ?? "");
    }

    private function _get_signature_image_context(int $entry_id)
    {
        if (!$entry_id) {
            return null;
        }

        $db = db_connect();
        $entries = $db->prefixTable("tender_bid_opening_entries");
        $openings = $db->prefixTable("tender_bid_openings");
        return $db->query(
            "SELECT
                $entries.id,
                $entries.signature_image_path,
                $openings.id AS opening_id,
                $openings.tender_id
             FROM $entries
             INNER JOIN $openings
                ON $openings.id = $entries.tender_bid_opening_id
               AND $openings.deleted = 0
             WHERE $entries.id = ?
               AND $entries.deleted = 0
               AND $entries.is_valid = 1
               AND $entries.signed_at IS NOT NULL
               AND $entries.signature_image_path IS NOT NULL
             LIMIT 1",
            [$entry_id]
        )->getRow();
    }

    private function _remove_signature_file($path): void
    {
        if (is_string($path) && is_file($path)) {
            @unlink($path);
        }
    }

    private function _sign_opening_response(bool $success, string $message, int $tender_id, array $extra = [])
    {
        $redirect_url = get_uri($tender_id ? "tender_committee_opening_inbox/details/" . $tender_id : "tender_committee_opening_inbox");
        $payload = array_merge([
            "success" => $success,
            "message" => $message,
            "redirect_url" => $redirect_url,
        ], $extra);

        if ($this->request->isAJAX()) {
            return $this->response->setJSON($payload);
        }

        if ($success) {
            $this->session->setFlashdata("success_message", $message);
        } else {
            $this->session->setFlashdata("error_message", $message);
        }

        return redirect()->to($redirect_url);
    }

    function download_bid_document($id = 0)
    {
        $this->access_only_tender("committee", "view");
        $doc_id = (int) $id;
        if (!$doc_id) {
            show_404();
        }

        $doc = $this->Tender_bid_documents_model->get_one($doc_id);
        if (!$doc || (int) ($doc->deleted ?? 0) === 1) {
            show_404();
        }

        $db = db_connect();
        $tb = $db->prefixTable("tender_bids");
        $t = $db->prefixTable("tenders");
        $ttm = $db->prefixTable("tender_team_members");

        $context = $db->query(
            "SELECT $t.id AS tender_id
             FROM $tb
             INNER JOIN $t
                ON $t.id = $tb.tender_id
               AND $t.deleted = 0
               AND $t.status = 'closed'
               AND $t.workflow_stage = 'technical_3key'
             INNER JOIN $ttm
                ON $ttm.tender_id = $t.id
               AND $ttm.deleted = 0
               AND $ttm.is_active = 1
               AND $ttm.user_id = ?
               AND $ttm.team_role IN ('chairman', 'secretary', 'itc_member')
             WHERE $tb.deleted = 0
               AND $tb.id = ?
             LIMIT 1",
            [(int) $this->login_user->id, (int) $doc->tender_bid_id]
        )->getRow();

        if (!$context) {
            app_redirect("forbidden");
        }

        $session = $this->Tender_bid_openings_model->get_active_session((int) $context->tender_id, "technical");
        if (!$session || !in_array((string) $session->status, ["unlocked", "signed"], true)) {
            app_redirect("forbidden");
        }

        $full_path = WRITEPATH . "uploads/" . ltrim((string) $doc->path, "/");
        if (!is_file($full_path)) {
            show_404();
        }

        return $this->response->download($full_path, null)->setFileName($doc->original_name ?: basename($full_path));
    }

    private function _get_committee_stage_tender(int $tender_id, string $stage)
    {
        $rows = $this->Tender_bids_model->get_tenders_ready_for_3key_opening((int) $this->login_user->id);
        foreach ($rows as $row) {
            if ((int) $row->id === $tender_id && (string) ($row->opening_stage ?? "") === $stage) {
                return $row;
            }
        }

        return null;
    }

    private function _normalize_opening_stage($stage): string
    {
        $stage = strtolower(trim((string) $stage));
        return $stage === "technical" ? "technical" : "technical";
    }

    private function _get_current_committee_role(int $tender_id): ?string
    {
        $db = db_connect();
        $ttm = $db->prefixTable("tender_team_members");

        $row = $db->query(
            "SELECT team_role
             FROM $ttm
             WHERE tender_id=?
               AND user_id=?
               AND deleted=0
               AND is_active=1
               AND team_role IN ('chairman','secretary','itc_member')
             ORDER BY id ASC
             LIMIT 1",
            [$tender_id, (int) $this->login_user->id]
        )->getRow();

        return $row->team_role ?? null;
    }
}
