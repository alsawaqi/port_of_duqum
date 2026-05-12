<?php

namespace App\Controllers;

use App\Models\Tender_communications_model;

class Tender_clarifications extends Security_Controller
{
    protected $Tender_communications_model;

    public function __construct()
    {
        parent::__construct();
        $this->access_only_team_members();
        $this->Tender_communications_model = new Tender_communications_model();
        $this->db = db_connect();
    }

    public function index()
    {
        $this->_access_clarifications_view();
        $tbl = $this->db->prefixTable("tender_communications");
        $t = $this->db->prefixTable("tenders");
        $params = [];
        $scope_sql = $this->_scope_filter_sql("root", $params);
        $assignment_sql = $this->_tender_assignment_sql($t, $params);
        $root_types = Tender_communications_model::get_clarification_root_types();
        $root_type_placeholders = implode(",", array_fill(0, count($root_types), "?"));
        $params = array_merge($root_types, $params);

        $rows = $this->db->query(
            "SELECT
                root.tender_id,
                $t.reference AS tender_reference,
                $t.title AS tender_title,
                COUNT(root.id) AS total_questions,
                COUNT(DISTINCT root.vendor_id) AS vendor_count,
                MAX(COALESCE(root.published_at, root.created_at)) AS latest_question_at
             FROM $tbl root
             INNER JOIN $t
                ON $t.id = root.tender_id
             WHERE root.deleted = 0
               AND root.type IN ($root_type_placeholders)
               $scope_sql
               $assignment_sql
               AND (root.parent_id IS NULL OR root.parent_id = 0)
             GROUP BY
                root.tender_id,
                $t.reference,
                $t.title
             ORDER BY latest_question_at DESC, root.tender_id DESC",
            $params
        )->getResult();

        return $this->template->rander("tender_clarifications/index", [
            "rows" => $rows,
        ]);
    }

    public function tender($tender_id = 0)
    {
        $tender_id = (int) $tender_id;
        if (!$tender_id) {
            show_404();
        }
        $this->_access_clarifications_view($tender_id);

        $tender = $this->_get_tender($tender_id);
        if (!$tender) {
            show_404();
        }

        $tbl = $this->db->prefixTable("tender_communications");
        $v = $this->db->prefixTable("vendors");
        $params = [];
        $scope_sql = $this->_scope_filter_sql("root", $params);
        $root_types = Tender_communications_model::get_clarification_root_types();
        $root_type_placeholders = implode(",", array_fill(0, count($root_types), "?"));

        $rows = $this->db->query(
            "SELECT
                root.vendor_id,
                $v.vendor_name,
                COUNT(root.id) AS total_questions,
                SUM(CASE WHEN root.status IN ('answered', 'public_answered') THEN 1 ELSE 0 END) AS answered_questions,
                MAX(COALESCE(root.published_at, root.created_at)) AS latest_question_at
             FROM $tbl root
             INNER JOIN $v
                ON $v.id = root.vendor_id
             WHERE root.deleted = 0
               AND root.type IN ($root_type_placeholders)
               $scope_sql
               AND (root.parent_id IS NULL OR root.parent_id = 0)
               AND root.tender_id = ?
             GROUP BY
                root.vendor_id,
                $v.vendor_name
             ORDER BY latest_question_at DESC, root.vendor_id DESC",
            array_merge($root_types, $params, [$tender_id])
        )->getResult();

        $internal_rows = $this->db->query(
            "SELECT
                root.id,
                root.type,
                root.subject,
                root.message,
                root.status,
                root.internal_audience,
                root.tender_bid_id,
                root.created_at,
                root.published_at,
                TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))) AS created_by_name
             FROM $tbl root
             LEFT JOIN " . $this->db->prefixTable("users") . " u
                ON u.id = root.created_by
             WHERE root.deleted = 0
               AND root.type IN ('technical_clarification_request', 'commercial_clarification_request')
               AND (root.vendor_id IS NULL OR root.vendor_id = 0)
               AND (root.parent_id IS NULL OR root.parent_id = 0)
               AND root.tender_id = ?
             ORDER BY COALESCE(root.published_at, root.created_at) DESC, root.id DESC",
            [$tender_id]
        )->getResult();

        return $this->template->rander("tender_clarifications/tender", [
            "tender" => $tender,
            "rows" => $rows,
            "internal_rows" => $internal_rows,
        ]);
    }

    public function vendor($tender_id = 0, $vendor_id = 0)
    {
        $tender_id = (int) $tender_id;
        $vendor_id = (int) $vendor_id;
        if (!$tender_id || !$vendor_id) {
            show_404();
        }
        $this->_access_clarifications_view($tender_id);

        $tender = $this->_get_tender($tender_id);
        $vendor = $this->_get_vendor($vendor_id);
        if (!$tender || !$vendor) {
            show_404();
        }

        $messages = $this->Tender_communications_model->get_clarification_conversation($tender_id, $vendor_id, false, $this->_current_user_clarification_scopes());
        $attachments = $this->Tender_communications_model->get_attachments_map(array_map(fn($message) => (int) $message->id, $messages));
        $question_count = 0;
        $latest_message_at = null;

        $root_types = Tender_communications_model::get_clarification_root_types();
        $reply_threads = [];

        foreach ($messages as $message) {
            $type = strtolower((string) ($message->type ?? ""));
            $is_root_question = in_array($type, $root_types, true)
                && (empty($message->parent_id) || (int) $message->parent_id === 0);

            if ($is_root_question) {
                $question_count++;
                $reply_threads[] = $message;
            }

            $message_at = $message->published_at ?: $message->created_at;
            if ($message_at && (!$latest_message_at || strtotime((string) $message_at) > strtotime((string) $latest_message_at))) {
                $latest_message_at = $message_at;
            }
        }

        usort($reply_threads, function ($a, $b) {
            $a_time = strtotime((string) (($a->published_at ?? null) ?: ($a->created_at ?? null))) ?: 0;
            $b_time = strtotime((string) (($b->published_at ?? null) ?: ($b->created_at ?? null))) ?: 0;
            return $b_time <=> $a_time;
        });

        return $this->template->rander("tender_clarifications/vendor", [
            "tender" => $tender,
            "vendor" => $vendor,
            "messages" => $messages,
            "attachments" => $attachments,
            "clarification_scope_options" => Tender_communications_model::clarification_scope_options(),
            "reply_threads" => $reply_threads,
            "question_count" => $question_count,
            "latest_message_at" => $latest_message_at,
        ]);
    }

    public function thread($id = 0)
    {
        $this->_access_clarifications_view();
        $id = (int) $id;
        if (!$id) {
            show_404();
        }

        $clarification = $this->Tender_communications_model->get_details([
            "id" => $id,
        ])->getRow();

        if (
            !$clarification
            || !in_array(strtolower((string) ($clarification->type ?? "")), Tender_communications_model::get_clarification_root_types(), true)
            || (!in_array((int) ($clarification->parent_id ?? 0), [0], true) && $clarification->parent_id !== null)
        ) {
            show_404();
        }

        if ((int) ($clarification->vendor_id ?? 0) > 0) {
            app_redirect("tender_clarifications/vendor/" . (int) $clarification->tender_id . "/" . (int) $clarification->vendor_id);
        }

        $tender = $this->_get_tender((int) $clarification->tender_id);
        if (!$tender) {
            show_404();
        }

        $replies = $this->Tender_communications_model->get_details([
            "parent_id" => (int) $clarification->id,
        ])->getResult();
        $message_ids = array_merge([(int) $clarification->id], array_map(fn($reply) => (int) $reply->id, $replies));

        return $this->template->rander("tender_clarifications/thread", [
            "tender" => $tender,
            "vendor" => null,
            "clarification" => $clarification,
            "replies" => $replies,
            "attachments" => $this->Tender_communications_model->get_attachments_map($message_ids),
            "vendors" => $this->_get_tender_vendors((int) $clarification->tender_id),
        ]);
    }

    public function save_reply()
    {
        $this->validate_submitted_data([
            "message" => "required",
        ]);

        $communication_id = (int) $this->request->getPost("communication_id");
        $tender_id = (int) $this->request->getPost("tender_id");
        $vendor_id = (int) $this->request->getPost("vendor_id");
        $message = trim((string) $this->request->getPost("message"));
        $visibility = trim((string) $this->request->getPost("visibility"));
        $visibility_options = [
            "vendor" => "Reply to this vendor",
            "all" => "Publish to all vendors",
            "technical" => "Forward internally to technical team",
            "commercial" => "Forward internally to commercial team",
        ];
        if (!array_key_exists($visibility, $visibility_options)) {
            $visibility = "vendor";
        }

        $clarification = null;
        if ($communication_id > 0) {
            $clarification = $this->Tender_communications_model->get_details([
                "id" => $communication_id,
            ])->getRow();
        } elseif ($tender_id > 0 && $vendor_id > 0) {
            $clarification = $this->Tender_communications_model->get_latest_vendor_root_clarification($tender_id, $vendor_id);
        }

        if (!$clarification) {
            return $this->response->setJSON([
                "success" => false,
                "message" => "No vendor clarification thread was found for this tender."
            ]);
        }
        $this->_access_clarifications_reply((int) $clarification->tender_id, (string) ($clarification->clarification_scope ?? "general"));

        $now = date("Y-m-d H:i:s");
        $is_technical_request = strtolower((string) ($clarification->type ?? "")) === "technical_clarification_request";
        $is_commercial_request = strtolower((string) ($clarification->type ?? "")) === "commercial_clarification_request";
        $reply_type = $visibility === "all"
            ? "circular"
            : (in_array($visibility, ["technical", "commercial"], true) ? $visibility . "_clarification_response" : "response");
        $selected_vendor_id = (int) $this->request->getPost("reply_vendor_id");
        $reply_vendor_id = $visibility === "all" ? null : ((int) $clarification->vendor_id ?: ($selected_vendor_id ?: null));
        $clarification_scope = Tender_communications_model::normalize_clarification_scope($this->request->getPost("clarification_scope") ?: ($clarification->clarification_scope ?? "general"));
        if ($visibility === "technical" || $is_technical_request) {
            $clarification_scope = "technical";
        } elseif ($visibility === "commercial" || $is_commercial_request) {
            $clarification_scope = "commercial";
        }
        $internal_audience = in_array($clarification_scope, ["technical", "commercial"], true) ? $clarification_scope : null;

        $saved = $this->Tender_communications_model->ci_save(clean_data([
            "tender_id" => (int) $clarification->tender_id,
            "vendor_id" => $reply_vendor_id,
            "tender_bid_id" => !empty($clarification->tender_bid_id) ? (int) $clarification->tender_bid_id : null,
            "type" => $reply_type,
            "clarification_scope" => $clarification_scope,
            "internal_audience" => $internal_audience,
            "subject" => null,
            "message" => $message,
            "parent_id" => (int) $clarification->id,
            "sent_to_all" => $visibility === "all" ? 1 : 0,
            "is_vendor_visible" => in_array($visibility, ["technical", "commercial"], true) ? 0 : 1,
            "status" => in_array($visibility, ["technical", "commercial"], true) ? "forwarded_to_" . $visibility : "published",
            "created_by" => $this->login_user->id,
            "created_at" => $now,
            "published_at" => $now,
            "deleted" => 0,
        ]));

        if (!$saved) {
            return $this->response->setJSON([
                "success" => false,
                "message" => app_lang("error_occurred")
            ]);
        }

        $this->_save_clarification_files((int) $saved, (int) $clarification->tender_id, $reply_vendor_id);

        $tbl = $this->db->prefixTable("tender_communications");
        $root_types = Tender_communications_model::get_clarification_root_types();
        $root_type_placeholders = implode(",", array_fill(0, count($root_types), "?"));
        $root_status = $visibility === "all"
            ? "public_answered"
            : (in_array($visibility, ["technical", "commercial"], true) ? "forwarded_to_" . $visibility : "answered");
        if ($communication_id > 0) {
            $this->db->query(
                "UPDATE $tbl
                 SET status=?,
                     updated_at=?
                 WHERE deleted=0
                   AND id=?",
                [$root_status, $now, (int) $clarification->id]
            );
        } else {
            $this->db->query(
                "UPDATE $tbl
                 SET status=?,
                     updated_at=?
                 WHERE deleted=0
                   AND tender_id=?
                   AND vendor_id=?
                   AND type IN ($root_type_placeholders)
                   AND (parent_id IS NULL OR parent_id=0)
                   AND status IN ('open', '', 'pending', 'pending_procurement', 'sent_to_vendor')",
                array_merge([
                    $root_status,
                    $now,
                    (int) $clarification->tender_id,
                    (int) $clarification->vendor_id,
                ], $root_types)
            );
        }

        return $this->response->setJSON([
            "success" => true,
            "message" => in_array($visibility, ["technical", "commercial"], true)
                ? "Clarification forwarded internally to the " . $visibility . " team."
                : ($visibility === "all"
                    ? "Clarification message published successfully."
                    : "Clarification reply sent successfully."),
            "redirect_url" => (int) ($clarification->vendor_id ?? 0) > 0
                ? get_uri("tender_clarifications/vendor/" . (int) $clarification->tender_id . "/" . (int) $clarification->vendor_id)
                : get_uri("tender_clarifications/thread/" . (int) $clarification->id),
        ]);
    }

    public function download_attachment($id = 0)
    {
        $id = (int) $id;
        if (!$id) {
            show_404();
        }

        $attachment = $this->Tender_communications_model->get_attachment($id);
        if (!$attachment) {
            show_404();
        }

        $this->_access_clarifications_view((int) $attachment->tender_id);
        if (!$this->_can_access_clarification_scope((int) $attachment->tender_id, (string) ($attachment->clarification_scope ?? "general"))) {
            app_redirect("forbidden");
        }

        $full_path = WRITEPATH . "uploads/" . ltrim((string) $attachment->path, "/");
        if (!is_file($full_path)) {
            show_404();
        }

        $download_name = $attachment->original_name ?: basename($full_path);
        return $this->response->download($full_path, null)->setFileName($download_name);
    }

    private function _get_tender(int $tender_id)
    {
        $t = $this->db->prefixTable("tenders");
        return $this->db->query(
            "SELECT *
             FROM $t
             WHERE id = ?
               AND deleted = 0
             LIMIT 1",
            [$tender_id]
        )->getRow();
    }

    private function _get_vendor(int $vendor_id)
    {
        $v = $this->db->prefixTable("vendors");
        return $this->db->query(
            "SELECT *
             FROM $v
             WHERE id = ?
               AND deleted = 0
             LIMIT 1",
            [$vendor_id]
        )->getRow();
    }

    private function _get_tender_vendors(int $tender_id): array
    {
        $tiv = $this->db->prefixTable("tender_invited_vendors");
        $tb = $this->db->prefixTable("tender_bids");
        $v = $this->db->prefixTable("vendors");

        return $this->db->query(
            "SELECT DISTINCT $v.id, $v.vendor_name
             FROM (
                SELECT vendor_id FROM $tiv WHERE deleted=0 AND tender_id=?
                UNION
                SELECT vendor_id FROM $tb WHERE deleted=0 AND tender_id=?
             ) src
             INNER JOIN $v ON $v.id=src.vendor_id AND $v.deleted=0
             ORDER BY $v.vendor_name ASC",
            [$tender_id, $tender_id]
        )->getResult();
    }

    private function _access_clarifications_view(int $tender_id = 0): void
    {
        if (!$this->can_tender("procurement", "view")) {
            app_redirect("forbidden");
            exit;
        }
    }

    private function _access_clarifications_reply(int $tender_id, string $scope): void
    {
        if (!$this->can_tender("procurement", "update")) {
            app_redirect("forbidden");
            exit;
        }
    }

    private function _can_access_clarification_scope(int $tender_id = 0, string $scope = "general"): bool
    {
        return $this->can_tender("procurement", "view");
    }

    private function _current_user_clarification_scopes(): array
    {
        if ($this->can_tender("procurement", "view")) {
            return [];
        }

        $scopes = [];
        if ($this->can_tender("technical_eval", "view")) {
            $scopes = array_merge($scopes, ["general", "tender", "technical"]);
        }
        if ($this->can_tender("commercial_eval", "view")) {
            $scopes = array_merge($scopes, ["general", "tender", "commercial"]);
        }

        return array_values(array_unique($scopes));
    }

    private function _scope_filter_sql(string $alias, array &$params): string
    {
        $scopes = $this->_current_user_clarification_scopes();
        if (!$scopes) {
            return "";
        }

        $params = array_merge($params, $scopes);
        return " AND $alias.clarification_scope IN (" . implode(",", array_fill(0, count($scopes), "?")) . ")";
    }

    private function _tender_assignment_sql(string $tender_alias, array &$params): string
    {
        if ($this->can_tender("procurement", "view")) {
            return "";
        }

        $roles = $this->_current_user_tender_team_roles();
        if (!$roles) {
            return " AND 1=0";
        }

        $team = $this->db->prefixTable("tender_team_members");
        $params = array_merge($params, $roles, [(int) $this->login_user->id]);

        return " AND EXISTS (
            SELECT 1
            FROM $team clarification_team
            WHERE clarification_team.deleted=0
              AND clarification_team.is_active=1
              AND clarification_team.tender_id=$tender_alias.id
              AND clarification_team.team_role IN (" . implode(",", array_fill(0, count($roles), "?")) . ")
              AND clarification_team.user_id=?
        )";
    }

    private function _current_user_tender_team_roles(): array
    {
        $roles = [];
        if ($this->can_tender("technical_eval", "view")) {
            $roles[] = "technical_evaluator";
        }
        if ($this->can_tender("commercial_eval", "view")) {
            $roles[] = "commercial_evaluator";
        }

        return array_values(array_unique($roles));
    }

    private function _is_current_user_on_tender_team(int $tender_id): bool
    {
        $roles = $this->_current_user_tender_team_roles();
        if (!$roles) {
            return false;
        }

        $team = $this->db->prefixTable("tender_team_members");
        $params = array_merge([$tender_id], $roles, [(int) $this->login_user->id]);
        $row = $this->db->query(
            "SELECT id
             FROM $team
             WHERE deleted=0
               AND is_active=1
               AND tender_id=?
               AND team_role IN (" . implode(",", array_fill(0, count($roles), "?")) . ")
               AND user_id=?
             LIMIT 1",
            $params
        )->getRow();

        return (bool) $row;
    }

    private function _save_clarification_files(int $communication_id, int $tender_id, ?int $vendor_id): void
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
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0775, true);
        }

        $saved_files = [];
        foreach ($files as $file) {
            if (!$file || !$file->isValid() || $file->hasMoved()) {
                continue;
            }

            $original_name = $file->getClientName();
            if (function_exists("is_valid_file_to_upload") && !is_valid_file_to_upload($original_name)) {
                continue;
            }

            $extension = $file->getExtension() ?: pathinfo($original_name, PATHINFO_EXTENSION);
            $new_name = uniqid("tc_", true) . ($extension ? "." . $extension : "");
            $file->move($upload_dir, $new_name);

            $saved_files[] = [
                "disk" => "local",
                "path" => "tender_clarifications/tender_" . $tender_id . "/communication_" . $communication_id . "/" . $new_name,
                "original_name" => $original_name,
                "mime_type" => $file->getClientMimeType(),
                "size_bytes" => $file->getSize(),
            ];
        }

        $this->Tender_communications_model->save_attachments($communication_id, $tender_id, $vendor_id, $saved_files, (int) $this->login_user->id);
    }
}
