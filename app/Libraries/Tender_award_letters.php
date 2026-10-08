<?php

namespace App\Libraries;

use App\Models\Tender_communications_model;

/** Result letters are recorded inside the award transaction, before email delivery. */
class Tender_award_letters
{
    private $db;
    private $mailer;

    public function __construct($db = null, ?callable $mailer = null)
    {
        $this->db = $db ?? db_connect();
        $this->mailer = $mailer ?? static fn($to, $subject, $body, $options) => send_app_mail($to, $subject, $body, $options, false);
    }

    public function record(object $tender, int $winner, int $userId, string $now): void
    {
        $bids = $this->db->prefixTable('tender_bids');
        $vendors = $this->db->prefixTable('vendors');
        $recipients = $this->db->query("SELECT v.*,
            (SELECT MAX(b.submitted_at) FROM $bids b WHERE b.vendor_id=v.id AND b.tender_id=? AND b.deleted=0 AND b.status<>'draft') AS submitted_at
            FROM $vendors v WHERE v.deleted=0 AND EXISTS
            (SELECT 1 FROM $bids b WHERE b.vendor_id=v.id AND b.tender_id=? AND b.deleted=0 AND b.status<>'draft')",
            [$tender->id, $tender->id])->getResult();
        $model = new Tender_communications_model();
        foreach ($recipients as $vendor) {
            $won = (int) $vendor->id === $winner;
            $subject = ($won ? 'Letter of Award' : 'Regret Letter') . ' - ' . $tender->reference;
            $message = 'Dear ' . $vendor->vendor_name . ",\n\n";
            $message .= $won
                ? 'We are pleased to confirm that your bid has been awarded for tender ' . $tender->reference . ' (' . $tender->title . ").\n\nAward reference: " . $tender->loa_reference . ".\nProcurement will contact you with the next steps."
                : 'Thank you for participating in tender ' . $tender->reference . ' (' . $tender->title . "). Following evaluation, your bid was not selected for award.\n\nWe appreciate your interest and participation.";
            $message .= "\n\nProcurement Department\nPort of Duqm";
            if (!$won) {
                $message = Tender_document_pdf::regretMessage($tender, $vendor, $now);
            }
            $saved = $model->ci_save([
                'tender_id' => (int) $tender->id, 'vendor_id' => (int) $vendor->id,
                'type' => $won ? 'award_letter' : 'regret_letter', 'clarification_scope' => 'vendor',
                'subject' => $subject, 'message' => $message, 'sent_to_all' => 0,
                'is_vendor_visible' => 1, 'status' => 'email_pending', 'created_by' => $userId,
                'created_at' => $now, 'published_at' => $now, 'deleted' => 0,
            ]);
            if (!$saved) {
                throw new \RuntimeException('Unable to record tender result letters.');
            }
        }
    }

    public function deliver(int $tenderId): array
    {
        $sent = 0; $failed = 0;
        $table = $this->db->prefixTable('tender_communications');
        $vendors = $this->db->prefixTable('vendors');
        $letters = $this->db->query("SELECT c.*, v.email FROM $table c
            JOIN $vendors v ON v.id=c.vendor_id AND v.deleted=0
            WHERE c.tender_id=? AND c.deleted=0 AND c.type IN ('award_letter','regret_letter')
              AND c.status IN ('email_pending','email_failed')", [$tenderId])->getResult();
        foreach ($letters as $letter) {
            // Claim each delivery once, including when two staff members click retry together.
            $this->db->table('tender_communications')->where('id', $letter->id)
                ->whereIn('status', ['email_pending', 'email_failed'])->update(['status' => 'email_sending']);
            if ($this->db->affectedRows() !== 1) {
                continue;
            }
            $ok = false;
            $attachmentPath = null;
            try {
                if (!filter_var($letter->email, FILTER_VALIDATE_EMAIL)) {
                    throw new \RuntimeException('The bidder email is invalid.');
                }
                $options = [];
                if ($letter->type === 'regret_letter') {
                    $directory = WRITEPATH . 'cache/tender-letters/';
                    if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
                        throw new \RuntimeException('Unable to create the letter attachment directory.');
                    }
                    $pdf = Tender_document_pdf::regret($letter)->Output('', 'S');
                    $attachmentPath = tempnam($directory, 'regret-');
                    if (!$attachmentPath || realpath(dirname($attachmentPath)) !== realpath($directory)) {
                        throw new \RuntimeException('Unable to create the letter attachment.');
                    }
                    // The existing SMTP helper determines MIME type from the physical file extension.
                    if (!rename($attachmentPath, $attachmentPath . '.pdf')) {
                        throw new \RuntimeException('Unable to name the letter attachment.');
                    }
                    $attachmentPath .= '.pdf';
                    @chmod($attachmentPath, 0600);
                    if (file_put_contents($attachmentPath, $pdf) !== strlen($pdf)) {
                        throw new \RuntimeException('Unable to write the letter attachment.');
                    }
                    $options['attachments'] = [[
                        'file_path' => $attachmentPath,
                        'file_name' => Tender_document_pdf::filename('Regret-Letter', (string) $letter->id),
                    ]];
                }
                $ok = (bool) ($this->mailer)($letter->email, $letter->subject, nl2br(esc($letter->message)), $options);
            } catch (\Throwable $e) {
                log_message('error', 'Tender result email delivery failed; the letter remains in the portal.');
            } finally {
                if ($attachmentPath && is_file($attachmentPath)) {
                    @unlink($attachmentPath);
                }
            }
            $this->db->table('tender_communications')->where('id', $letter->id)->update([
                'status' => $ok ? 'email_sent' : 'email_failed', 'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $ok ? $sent++ : $failed++;
        }
        return ['sent' => $sent, 'failed' => $failed];
    }
}
