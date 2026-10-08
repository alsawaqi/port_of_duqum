<?php

namespace App\Controllers;

use App\Libraries\Tender_clarification_files;
use App\Libraries\Upload_security;
use App\Libraries\UploadSecurityException;
use App\Models\Tender_communications_model;

/** Team correspondence is available before bid opening without exposing sealed bids. */
class Tender_team_clarifications extends Security_Controller
{
    protected $db;
    private Tender_communications_model $messages;

    public function __construct()
    {
        parent::__construct();
        $this->access_only_team_members();
        $this->db = db_connect();
        $this->messages = new Tender_communications_model();
    }

    private function audience(string $audience, string $action = 'view'): string
    {
        if (!in_array($audience, ['technical', 'commercial'], true)) { show_404(); }
        $this->access_only_tender($audience . '_eval', $action);
        return $audience;
    }

    private function authorize(object $message, string $action = 'view'): void
    {
        $audience = $this->audience((string) $message->internal_audience, $action);
        if ((int) $message->is_vendor_visible !== 0 || !in_array($message->type, [$audience . '_clarification_request', $audience . '_clarification_response'], true)) {
            show_404();
        }
        $this->require_tender_scope((int) $message->tender_id, $audience . '_eval');
        $team = $this->db->table('tender_team_members')->where('tender_id', $message->tender_id)
            ->where('user_id', $this->login_user->id)->where('team_role', $audience . '_evaluator')
            ->where('is_active', 1)->where('deleted', 0)->get(1)->getRow();
        if (!$team && !$this->login_user->is_admin) { app_redirect('forbidden'); exit; }
    }

    public function index($audience = 'technical')
    {
        $audience = $this->audience((string) $audience);
        $c = $this->db->prefixTable('tender_communications');
        $t = $this->db->prefixTable('tenders');
        $team = $this->db->prefixTable('tender_team_members');
        $page = max(1, min(100000, (int) $this->request->getGet('page')));
        $offset = ($page - 1) * 30;
        $params = [$audience];
        $company = $this->tender_company_scope_sql('t.company_id', $audience . '_eval', $params);
        $assigned = '';
        if (!$this->login_user->is_admin) {
            $assigned = "AND EXISTS (SELECT 1 FROM $team tm WHERE tm.tender_id=t.id AND tm.user_id=? AND tm.team_role=? AND tm.deleted=0 AND tm.is_active=1)";
            $params[] = (int) $this->login_user->id; $params[] = $audience . '_evaluator';
        }
        $rows = $this->db->query("SELECT c.*,t.reference AS tender_reference,t.title AS tender_title,t.status AS tender_status FROM $c c
            JOIN $t t ON t.id=c.tender_id AND t.deleted=0
            WHERE c.deleted=0 AND c.internal_audience=? AND c.is_vendor_visible=0
              AND c.type IN ('technical_clarification_request','technical_clarification_response','commercial_clarification_request','commercial_clarification_response')
              AND $company $assigned ORDER BY c.id DESC LIMIT 31 OFFSET $offset", $params)->getResult();
        $hasMore = count($rows) > 30;
        $rows = array_slice($rows, 0, 30);
        return $this->template->rander('tender_team_clarifications/index', [
            'rows' => $rows, 'audience' => $audience,
            'attachments' => $this->messages->get_attachments_map(array_map(static fn($row) => (int) $row->id, $rows)),
            'can_reply' => $this->can_tender($audience . '_eval', 'update'),
            'page' => $page, 'has_more' => $hasMore,
        ]);
    }

    public function reply()
    {
        $this->validate_submitted_data(['communication_id' => 'required|numeric', 'message' => 'required']);
        $original = $this->messages->get_details(['id' => (int) $this->request->getPost('communication_id')])->getRow();
        if (!$original) { show_404(); }
        $this->authorize($original, 'update');
        $tender = $this->require_tender_scope((int) $original->tender_id, $original->internal_audience . '_eval');
        if (in_array($tender->status, ['awarded', 'cancelled'], true)) {
            return $this->response->setJSON(['success' => false, 'message' => 'This tender is finalized.']);
        }
        $key = 'tender_team_reply_' . (int) $this->login_user->id;
        if (!service('throttler')->check($key, 10, 60)) {
            return $this->response->setStatusCode(429)->setJSON(['success' => false, 'message' => 'Please wait before sending more replies.']);
        }
        $parent = (int) ($original->parent_id ?: $original->id);
        $now = date('Y-m-d H:i:s');
        $this->db->transBegin();
        try {
            $id = $this->messages->ci_save([
                'tender_id' => (int) $original->tender_id, 'vendor_id' => $original->vendor_id,
                'tender_bid_id' => $original->tender_bid_id, 'parent_id' => $parent,
                'type' => $original->internal_audience . '_clarification_response',
                'clarification_scope' => $original->internal_audience, 'internal_audience' => $original->internal_audience,
                'message' => trim((string) $this->request->getPost('message')), 'sent_to_all' => 0,
                'is_vendor_visible' => 0, 'status' => 'pending_procurement',
                'created_by' => (int) $this->login_user->id, 'created_at' => $now, 'published_at' => $now, 'deleted' => 0,
            ]);
            if (!$id) { throw new \RuntimeException('Reply failed.'); }
            Tender_clarification_files::save($this->request, (int) $id, (int) $original->tender_id, $original->vendor_id ? (int) $original->vendor_id : null, (int) $this->login_user->id);
            $this->db->table('tender_communications')->where('id', $parent)->update(['status' => 'pending_procurement', 'updated_at' => $now]);
            if (!$this->db->transStatus()) { throw new \RuntimeException('Reply failed.'); }
            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            return $this->response->setStatusCode($e instanceof UploadSecurityException ? 422 : 500)
                ->setJSON(['success' => false, 'message' => $e instanceof UploadSecurityException ? app_lang('invalid_file_type') : app_lang('error_occurred')]);
        }
        return $this->response->setJSON(['success' => true, 'message' => 'Reply sent to procurement. Procurement will decide what to share with vendors.']);
    }

    public function download_attachment($id = 0)
    {
        $attachment = $this->messages->get_attachment((int) $id);
        if (!$attachment) { show_404(); }
        $message = $this->messages->get_details(['id' => (int) $attachment->communication_id])->getRow();
        if (!$message) { show_404(); }
        $this->authorize($message);
        $prefix = 'tender_clarifications/tender_' . (int) $message->tender_id . '/communication_' . (int) $message->id . '/';
        if (!str_starts_with(str_replace('\\', '/', $attachment->path), $prefix)) { show_404(); }
        $path = (new Upload_security())->resolveStoredFile($attachment->path, 'tender_clarifications');
        if (!$path) { show_404(); }
        return $this->response->download($path, null)->setFileName($attachment->original_name ?: basename($path));
    }
}
