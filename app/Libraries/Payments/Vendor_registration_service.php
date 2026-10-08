<?php

namespace App\Libraries\Payments;

use CodeIgniter\Database\BaseConnection;
use DomainException;
use RuntimeException;

/** Payment-first onboarding only. Rows absent from this table retain the legacy workflow. */
final class Vendor_registration_service
{
    public function __construct(private BaseConnection $db) {}

    public function application(int $vendorId): ?object
    {
        if (!$this->db->tableExists('vendor_registration_applications')) {
            return null;
        }
        $apps = $this->db->prefixTable('vendor_registration_applications');
        $fees = $this->db->prefixTable('vendor_fee_requests');
        return $this->db->query("SELECT a.*, f.amount, f.currency, f.status AS payment_status,
            f.requested_by, f.vendor_group_id, f.validity_days, f.payment_id
            FROM {$apps} a JOIN {$fees} f ON f.id=a.fee_request_id WHERE a.vendor_id=?", [$vendorId])->getRow() ?: null;
    }

    public function quote(int $groupId): array
    {
        if (!$this->db->tableExists('vendor_registration_applications')) {
            throw new DomainException('Vendor registration setup is not available. Please contact the administrator.');
        }
        try {
            $quote = (new Vendor_billing_service($this->db))->quote((object) ['vendor_group_id' => $groupId], 'registration');
        } catch (\InvalidArgumentException $e) {
            throw new DomainException('The registration fee configuration is invalid. Please contact Procurement.');
        }
        $groups = $this->db->prefixTable('vendor_groups');
        $group = $this->db->query("SELECT requires_riyada FROM {$groups} WHERE id=? AND deleted=0 AND is_active=1", [$groupId])->getRow();
        $quote['waiver'] = $quote['amount'] === '0.000';
        $quote['requires_riyada'] = $quote['waiver'] || !empty($group->requires_riyada);
        $quote['riyada_type_id'] = $quote['requires_riyada'] ? $this->riyadaTypeId($groupId) : null;
        return $quote;
    }

    public function riyadaTypeId(int $groupId): int
    {
        $types = $this->db->prefixTable('vendor_document_types');
        $type = $this->db->query("SELECT id FROM {$types} WHERE deleted=0 AND is_active=1
            AND (vendor_group_id IS NULL OR vendor_group_id=?)
            AND UPPER(code) IN ('RIYADHA','RIYADA') ORDER BY vendor_group_id DESC, id LIMIT 1", [$groupId])->getRow();
        if (!$type) {
            throw new DomainException('The Riyadha document type is not configured for this vendor group. Please contact the administrator.');
        }
        return (int) $type->id;
    }

    /** Caller owns the guest registration transaction, including files, identity and membership. */
    public function create(object $vendor, int $userId, int $contactId, ?int $riyadaDocumentId): object
    {
        $quote = $this->quote((int) $vendor->vendor_group_id);
        if ($quote['requires_riyada']) {
            $this->assertRiyada((int) $vendor->id, (int) $riyadaDocumentId);
        }
        $now = get_current_utc_time();
        $fee = array_intersect_key($quote, array_flip(['fee_id','vendor_group_id','fee_type','amount','currency','validity_days']));
        $fee += ['vendor_id' => (int) $vendor->id,
            'period_key' => Vendor_billing_service::periodKey((int) $vendor->id, 'registration', null),
            'prior_valid_until' => null, 'status' => $quote['waiver'] ? 'not_required' : 'pending',
            'review_status' => $quote['waiver'] ? 'submitted' : 'pending',
            'requested_by' => $userId, 'created_at' => $now, 'updated_at' => $now];
        if (!$this->db->table('vendor_fee_requests')->insert($fee)) {
            throw new RuntimeException('Unable to create the registration fee request.');
        }
        $feeId = (int) $this->db->insertID();
        if (!$this->db->table('vendor_registration_applications')->insert([
            'vendor_id' => (int) $vendor->id, 'fee_request_id' => $feeId, 'owner_contact_id' => $contactId,
            'riyada_document_id' => $riyadaDocumentId, 'initial_amount' => $quote['amount'],
            'status' => $quote['waiver'] ? 'pending_review' : 'pending_payment',
            'created_at' => $now, 'updated_at' => $now,
        ])) {
            throw new RuntimeException('Unable to create the registration application.');
        }
        $this->changeStatus($vendor, $quote['waiver'] ? 'submitted' : 'pending_payment', $userId,
            $quote['waiver'] ? 'Riyadha submitted for registration fee waiver review.' : 'Registration payment required: ' . $quote['currency'] . ' ' . $quote['amount']);
        return $this->application((int) $vendor->id);
    }

    public function startPayment(int $vendorId, int $userId): array
    {
        $application = $this->application($vendorId);
        if (!$application || $application->status !== 'pending_payment') {
            throw new DomainException('This registration is not awaiting payment.');
        }
        $fees = $this->db->prefixTable('vendor_fee_requests');
        $fee = $this->db->query("SELECT * FROM {$fees} WHERE id=?", [(int) $application->fee_request_id])->getRow();
        $vendors = $this->db->prefixTable('vendors');
        $vendor = $this->db->query("SELECT * FROM {$vendors} WHERE id=? AND deleted=0", [$vendorId])->getRow();
        if (!$vendor || !in_array($vendor->status, ['new','pending_payment','submitted','revise'], true)
            || !empty($vendor->registration_valid_to) || (int) $vendor->vendor_group_id !== (int) $fee->vendor_group_id
            || $fee->status !== 'pending' || $fee->review_status !== 'pending') {
            throw new DomainException('This registration is no longer eligible for payment. Please contact Procurement.');
        }
        $returnUrl = get_uri('vendor_portal/registration_status');
        return (new Eservice_payment_manager($this->db))->start(
            Eservice_payment_manager::VENDOR_REGISTRATION, $vendorId, $vendorId, $userId,
            (string) $fee->amount, 'Vendor registration fee', $returnUrl, $returnUrl,
            (new Vendor_billing_service($this->db))->metadata($fee)
        );
    }

    /** Staff may accept the documented waiver, or replace it once with an explicit payable fee. */
    public function review(int $vendorId, string $decision, string $amount, string $note, int $reviewerId): void
    {
        if (!in_array($decision, ['approve_waiver','require_payment'], true)) {
            throw new DomainException('Choose Approve waiver or Require payment.');
        }
        $note = trim($note);
        if (mb_strlen($note) > 2000) { throw new DomainException('Keep the review note within 2,000 characters.'); }
        if ($decision === 'require_payment') {
            if ($note === '') { throw new DomainException('Explain why payment is required.'); }
            try { $amount = Vendor_billing_service::normalizedFee($amount); }
            catch (\InvalidArgumentException $e) { throw new DomainException('Enter a valid payment amount with up to three decimal places.'); }
            if ($amount === '0.000') { throw new DomainException('The requested payment must be greater than zero.'); }
        }
        $this->db->transBegin();
        try {
            $vendor = $this->lockVendor($vendorId);
            $application = $this->application($vendorId);
            if (!$application || $application->status !== 'pending_review' || $application->initial_amount != 0
                || $application->payment_status !== 'not_required' || !empty($application->payment_id)) {
                throw new DomainException('Only a pending waiver application can be reviewed. Refresh the page.');
            }
            if ((int) $vendor->vendor_group_id !== (int) $application->vendor_group_id) {
                throw new DomainException('The vendor group changed. Please reconcile the registration first.');
            }
            $now = get_current_utc_time();
            $this->db->table('vendor_registration_applications')->where('vendor_id', $vendorId)->update([
                'review_note' => $note ?: 'Riyadha waiver approved.', 'reviewed_by' => $reviewerId,
                'reviewed_at' => $now, 'updated_at' => $now,
            ]);
            if ($decision === 'approve_waiver') {
                $this->assertRiyada($vendorId, (int) $application->riyada_document_id);
                $this->db->table('vendor_documents')->where('id', (int) $application->riyada_document_id)->where('vendor_id', $vendorId)
                    ->update(['status' => 'approved', 'updated_at' => $now]);
                $this->db->table('vendor_fee_requests')->where('id', (int) $application->fee_request_id)->update([
                    'reviewed_by' => $reviewerId, 'reviewed_at' => $now,
                ]);
                $this->activate($vendor, $application, $reviewerId, 'Registration approved after Riyadha waiver review.');
            } else {
                $this->db->table('vendor_fee_requests')->where('id', (int) $application->fee_request_id)->update([
                    'amount' => $amount, 'status' => 'pending', 'review_status' => 'pending',
                    'reviewed_by' => $reviewerId, 'reviewed_at' => $now, 'updated_at' => $now,
                ]);
                $this->db->table('vendor_registration_applications')->where('vendor_id', $vendorId)->update(['status' => 'pending_payment']);
                $this->changeStatus($vendor, 'pending_payment', $reviewerId,
                    'Waiver declined; payment required: ' . $application->currency . ' ' . $amount . '. ' . $note);
            }
            if ($this->db->transStatus() === false) { throw new RuntimeException('Registration review failed.'); }
            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /** Called exclusively after the gateway has independently verified payment, in its transaction. */
    public function completePaid(object $vendor, object $request, object $payment): bool
    {
        $application = $this->application((int) $vendor->id);
        if (!$application) { return false; }
        if ((int) $application->fee_request_id !== (int) $request->id || $application->status !== 'pending_payment') {
            throw new DomainException('Registration changed while payment was in progress; accounting reconciliation is required.');
        }
        $this->activate($vendor, $application, (int) $payment->user_id,
            'Registration completed by verified Bank Muscat payment #' . (int) $payment->id . '.');
        return true;
    }

    private function activate(object $vendor, object $application, int $actorId, string $reason): void
    {
        if (!empty($vendor->registration_valid_to)) { throw new DomainException('This registration is already active.'); }
        $contacts = $this->db->prefixTable('vendor_contacts');
        $owner = $this->db->query("SELECT id FROM {$contacts} WHERE id=? AND vendor_id=? AND deleted=0
            AND status IN ('pending','approved') FOR UPDATE", [(int) $application->owner_contact_id, (int) $vendor->id])->getRow();
        if (!$owner) { throw new DomainException('The registration contact has changed. Procurement must reconcile this application.'); }
        $now = get_current_utc_time();
        $start = gmdate('Y-m-d');
        $this->db->table('vendors')->where('id', (int) $vendor->id)->update([
            'registration_valid_from' => $start,
            'registration_valid_to' => (new \DateTimeImmutable($start))->modify('+' . (int) $application->validity_days . ' days')->format('Y-m-d'),
        ]);
        $this->db->table('vendor_fee_requests')->where('id', (int) $application->fee_request_id)->update([
            'review_status' => 'approved', 'updated_at' => $now,
        ]);
        $this->db->table('vendor_registration_applications')->where('id', (int) $application->id)->update([
            'status' => 'approved', 'updated_at' => $now,
        ]);
        // Only the registration owner is activated here. Later contacts retain their own approval flow.
        $this->db->table('vendor_contacts')->where('id', (int) $application->owner_contact_id)->where('vendor_id', (int) $vendor->id)
            ->update(['status' => 'approved', 'updated_at' => $now]);
        $updates = $this->db->prefixTable('vendor_update_requests');
        foreach ($this->db->query("SELECT id, changes FROM {$updates} WHERE vendor_id=? AND status='pending' AND deleted=0", [(int) $vendor->id])->getResult() as $update) {
            $change = json_decode((string) $update->changes, true) ?: [];
            $owner = ($change['module'] ?? '') === 'contacts'
                && (int) ($change['record_id'] ?? 0) === (int) $application->owner_contact_id;
            $waiverDocument = $application->status === 'pending_review' && ($change['module'] ?? '') === 'documents'
                && (int) ($change['record_id'] ?? 0) === (int) $application->riyada_document_id;
            if (($change['action'] ?? '') === 'create' && ($owner || $waiverDocument)) {
                $this->db->table($updates)->where('id', (int) $update->id)->update(['status' => 'approved', 'updated_at' => $now]);
            }
        }
        $this->changeStatus($vendor, 'approved', $actorId, $reason);
    }

    private function assertRiyada(int $vendorId, int $documentId): void
    {
        $docs = $this->db->prefixTable('vendor_documents');
        $vendors = $this->db->prefixTable('vendors');
        $vendor = $this->db->query("SELECT vendor_group_id FROM {$vendors} WHERE id=? AND deleted=0", [$vendorId])->getRow();
        $doc = $this->db->query("SELECT id FROM {$docs} WHERE id=? AND vendor_id=? AND deleted=0
            AND vendor_document_type_id=? AND status<>'rejected' AND path<>''", [$documentId, $vendorId, $this->riyadaTypeId((int) ($vendor->vendor_group_id ?? 0))])->getRow();
        if (!$doc) { throw new DomainException('A valid Riyadha document is required for this registration.'); }
    }

    private function lockVendor(int $vendorId): object
    {
        $vendors = $this->db->prefixTable('vendors');
        $vendor = $this->db->query("SELECT * FROM {$vendors} WHERE id=? AND deleted=0 FOR UPDATE", [$vendorId])->getRow();
        if (!$vendor || in_array($vendor->status, ['suspended','rejected','approved','expired'], true)) {
            throw new DomainException('This vendor is not awaiting registration review.');
        }
        return $vendor;
    }

    private function changeStatus(object $vendor, string $status, int $actorId, string $reason): void
    {
        (new Vendor_billing_service($this->db))->setVendorStatus($vendor, $status, $actorId, $reason);
        if (get_setting('sms_notifications_enabled')) {
            (new \App\Libraries\Sms\WorkflowSmsOutbox($this->db))->capture('vendors', (array) $vendor,
                array_merge((array) $vendor, ['status' => $status]));
        }
    }
}
