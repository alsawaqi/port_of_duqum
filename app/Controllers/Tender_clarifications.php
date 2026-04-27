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
        $this->access_only_tender("procurement", "view");
        $tbl = $this->db->prefixTable("tender_communications");
        $t = $this->db->prefixTable("tenders");

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
               AND root.type = 'clarification'
               AND (root.parent_id IS NULL OR root.parent_id = 0)
             GROUP BY
                root.tender_id,
                $t.reference,
                $t.title
             ORDER BY latest_question_at DESC, root.tender_id DESC"
        )->getResult();

        return $this->template->rander("tender_clarifications/index", [
            "rows" => $rows,
        ]);
    }

    public function tender($tender_id = 0)
    {
        $this->access_only_tender("procurement", "view");
        $tender_id = (int) $tender_id;
        if (!$tender_id) {
            show_404();
        }

        $tender = $this->_get_tender($tender_id);
        if (!$tender) {
            show_404();
        }

        $tbl = $this->db->prefixTable("tender_communications");
        $v = $this->db->prefixTable("vendors");

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
               AND root.type = 'clarification'
               AND (root.parent_id IS NULL OR root.parent_id = 0)
               AND root.tender_id = ?
             GROUP BY
                root.vendor_id,
                $v.vendor_name
             ORDER BY latest_question_at DESC, root.vendor_id DESC",
            [$tender_id]
        )->getResult();

        return $this->template->rander("tender_clarifications/tender", [
            "tender" => $tender,
            "rows" => $rows,
        ]);
    }

    public function vendor($tender_id = 0, $vendor_id = 0)
    {
        $this->access_only_tender("procurement", "view");
        $tender_id = (int) $tender_id;
        $vendor_id = (int) $vendor_id;
        if (!$tender_id || !$vendor_id) {
            show_404();
        }

        $tender = $this->_get_tender($tender_id);
        $vendor = $this->_get_vendor($vendor_id);
        if (!$tender || !$vendor) {
            show_404();
        }

        $messages = $this->Tender_communications_model->get_clarification_conversation($tender_id, $vendor_id);
        $question_count = 0;
        $latest_message_at = null;

        foreach ($messages as $message) {
            $is_root_question = strtolower((string) ($message->type ?? "")) === "clarification"
                && (empty($message->parent_id) || (int) $message->parent_id === 0);

            if ($is_root_question) {
                $question_count++;
            }

            $message_at = $message->published_at ?: $message->created_at;
            if ($message_at && (!$latest_message_at || strtotime((string) $message_at) > strtotime((string) $latest_message_at))) {
                $latest_message_at = $message_at;
            }
        }

        return $this->template->rander("tender_clarifications/vendor", [
            "tender" => $tender,
            "vendor" => $vendor,
            "messages" => $messages,
            "question_count" => $question_count,
            "latest_message_at" => $latest_message_at,
        ]);
    }

    public function thread($id = 0)
    {
        $this->access_only_tender("procurement", "view");
        $id = (int) $id;
        if (!$id) {
            show_404();
        }

        $clarification = $this->Tender_communications_model->get_details([
            "id" => $id,
            "type" => "clarification",
        ])->getRow();

        if (!$clarification || !in_array((int) ($clarification->parent_id ?? 0), [0], true) && $clarification->parent_id !== null) {
            show_404();
        }

        app_redirect("tender_clarifications/vendor/" . (int) $clarification->tender_id . "/" . (int) ($clarification->vendor_id ?? 0));
    }

    public function save_reply()
    {
        $this->validate_submitted_data([
            "message" => "required",
        ]);
        $this->access_only_tender("procurement", "update");

        $communication_id = (int) $this->request->getPost("communication_id");
        $tender_id = (int) $this->request->getPost("tender_id");
        $vendor_id = (int) $this->request->getPost("vendor_id");
        $message = trim((string) $this->request->getPost("message"));
        $visibility = trim((string) $this->request->getPost("visibility"));
        if (!in_array($visibility, ["vendor", "all"], true)) {
            $visibility = "vendor";
        }

        $clarification = null;
        if ($communication_id > 0) {
            $clarification = $this->Tender_communications_model->get_details([
                "id" => $communication_id,
                "type" => "clarification",
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

        $now = date("Y-m-d H:i:s");
        $reply_type = $visibility === "all" ? "circular" : "response";
        $reply_vendor_id = $visibility === "all" ? null : ((int) $clarification->vendor_id ?: null);

        $saved = $this->Tender_communications_model->ci_save(clean_data([
            "tender_id" => (int) $clarification->tender_id,
            "vendor_id" => $reply_vendor_id,
            "type" => $reply_type,
            "subject" => null,
            "message" => $message,
            "parent_id" => (int) $clarification->id,
            "sent_to_all" => $visibility === "all" ? 1 : 0,
            "is_vendor_visible" => 1,
            "status" => "published",
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

        $tbl = $this->db->prefixTable("tender_communications");
        $this->db->query(
            "UPDATE $tbl
             SET status=?,
                 updated_at=?
             WHERE deleted=0
               AND tender_id=?
               AND vendor_id=?
               AND type='clarification'
               AND (parent_id IS NULL OR parent_id=0)
               AND status IN ('open', '', 'pending')",
            [
                $visibility === "all" ? "public_answered" : "answered",
                $now,
                (int) $clarification->tender_id,
                (int) $clarification->vendor_id,
            ]
        );

        return $this->response->setJSON([
            "success" => true,
            "message" => $visibility === "all"
                ? "Clarification message published successfully."
                : "Clarification reply sent successfully.",
            "redirect_url" => get_uri("tender_clarifications/vendor/" . (int) $clarification->tender_id . "/" . (int) ($clarification->vendor_id ?? 0)),
        ]);
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
}
