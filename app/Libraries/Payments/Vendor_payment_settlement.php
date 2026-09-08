<?php

namespace App\Libraries\Payments;

use CodeIgniter\Database\BaseConnection;
use DomainException;

final class Vendor_payment_settlement
{
    /** Invoked only by verified gateway settlement inside the ledger transaction. */
    public static function apply(BaseConnection $db, object $payment, string $providerReference): void
    {
        $meta = json_decode((string) ($payment->metadata ?? ''), true) ?: [];
        $requestId = (int) ($meta['vendor_fee_request_id'] ?? 0);
        $table = $db->prefixTable('vendor_fee_requests');
        $request = $db->query("SELECT * FROM {$table} WHERE id=? FOR UPDATE", [$requestId])->getRow();
        if (!$request || (int) $request->vendor_id !== (int) $payment->vendor_id
            || (int) $payment->subject_id !== (int) $request->vendor_id
            || (string) $payment->subject_type !== 'vendor_' . $request->fee_type
            || (string) $request->period_key !== (string) ($meta['period_key'] ?? '')
            || Vendor_billing_service::normalizedFee((string) $request->amount) !== Vendor_billing_service::normalizedFee((string) $payment->amount)
            || strtoupper((string) $request->currency) !== strtoupper((string) $payment->currency)) {
            throw new DomainException('Vendor fee request does not match this payment.');
        }
        if ($request->status === 'paid') {
            if ((int) $request->payment_id !== (int) $payment->id) {
                throw new DomainException('This vendor billing period has already been paid by another transaction.');
            }
            return;
        }
        if (!in_array((string) $request->review_status, ['pending','submitted','revise'], true)) {
            throw new DomainException('Vendor fee request was already closed by Procurement.');
        }
        $vendors = $db->prefixTable('vendors');
        $vendor = $db->query("SELECT * FROM {$vendors} WHERE id=? AND deleted=0 FOR UPDATE", [(int) $request->vendor_id])->getRow();
        if (!$vendor || (int) $vendor->vendor_group_id !== (int) $request->vendor_group_id
            || (string) ($vendor->registration_valid_to ?? '') !== (string) ($request->prior_valid_until ?? '')
            || in_array((string) $vendor->status, ['suspended','rejected'], true)) {
            throw new DomainException('Vendor changed while payment was in progress; accounting reconciliation is required.');
        }
        $db->table($table)->where('id', $requestId)->update(['status' => 'paid',
            'payment_id' => (int) $payment->id, 'review_status' => 'submitted', 'updated_at' => get_current_utc_time()]);
        (new Vendor_billing_service($db))->setVendorStatus($vendor, 'submitted', (int) $payment->user_id,
            ucfirst($request->fee_type) . ' paid through Bank Muscat; reference ' . mb_substr($providerReference, 0, 100));
    }
}
