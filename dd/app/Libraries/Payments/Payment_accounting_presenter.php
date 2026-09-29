<?php

namespace App\Libraries\Payments;

/** Turns stored evidence into plain-language accounting information. No raw JSON reaches the view. */
final class Payment_accounting_presenter
{
    private static function label(string $key): string
    {
        return app_lang('payment_accounting_' . $key);
    }

    public static function status(object $payment): string
    {
        $status = in_array($payment->status ?? '', ['pending', 'processing', 'paid', 'failed', 'cancelled', 'expired', 'verification_required'], true)
            ? $payment->status : 'verification_required';
        $text = self::label('status_' . ($status === 'processing' && empty($payment->handed_off_at) ? 'awaiting_checkout' : $status));
        if (($payment->settlement_status ?? '') === 'review_required') {
            $text .= ' · ' . self::label('settlement_review');
        } elseif (!empty($payment->has_verification_issues)) {
            $text .= ' · ' . self::label('bank_attention');
        }
        return $text;
    }

    public static function detail(object $payment, bool $canResponses, array $events = []): array
    {
        $review = ($payment->settlement_status ?? '') === 'review_required';
        $status = (string)($payment->status ?? 'verification_required');
        $next = $review ? 'settlement_review_hint' : (!empty($payment->has_verification_issues) ? 'bank_attention_hint' : 'next_' . $status);
        if ($next === 'next_processing' && empty($payment->handed_off_at)) {
            $next = 'next_pending';
        }
        if (!$review && $status !== 'paid' && self::bankOrderNotFound($payment->status_response_json ?? '')) {
            $next = 'next_bank_not_found';
        }
        if ($next === 'next_paid' && ($payment->settlement_status ?? '') !== 'applied') {
            $next = 'next_paid_pending';
        }
        if (!in_array($next, ['settlement_review_hint', 'bank_attention_hint', 'next_paid', 'next_paid_pending', 'next_bank_not_found', 'next_failed', 'next_cancelled', 'next_expired', 'next_pending', 'next_processing', 'next_verification_required'], true)) {
            $next = 'next_verification_required';
        }
        $fields = [
            'payment_type' => self::label((string)$payment->subject_type),
            'reason' => Bank_muscat_gateway::redactCardText((string)($payment->description ?? '')) ?: self::label((string)$payment->subject_type),
            'amount' => trim(($payment->currency ?? '') . ' ' . ($payment->amount ?? '')),
            'settlement_status' => self::label('settlement_' . (in_array($payment->settlement_status ?? '', ['pending', 'applied', 'review_required'], true) ? $payment->settlement_status : 'pending')),
            'subject_reference' => $payment->subject_reference ?: ('#' . $payment->subject_id),
            'vendor' => trim(($payment->vendor_name ?? '') . ' / ' . ($payment->cr_number ?? ''), ' /'),
            'payer' => trim((string)($payment->payer_name ?? '')) ?: self::label('unavailable'),
            'payer_email' => $payment->payer_email ?? '',
            'company' => $payment->company_name ?? '',
            'provider' => ($payment->provider ?? '') === 'bank_muscat' ? 'Bank Muscat SmartPay' : self::label('other_provider'),
            'gateway_order' => $payment->provider_checkout_id ?? '',
            'gateway_payment' => $payment->provider_payment_id ?? '',
            'bank_reference' => $payment->bank_reference ?? '',
            'initiated_at' => self::date($payment->initiated_at ?? ''),
            'handed_off_at' => self::date($payment->handed_off_at ?? ''),
            'paid_at' => self::date($payment->paid_at ?? ''),
            'verified_at' => self::date($payment->verified_at ?? ''),
            'failed_at' => self::date($payment->failed_at ?? ''),
        ];
        return [
            'status' => self::status($payment),
            'tone' => $review || !empty($payment->has_verification_issues) ? 'warning' : ($status === 'paid' ? 'success' : (in_array($status, ['failed', 'cancelled', 'expired'], true) ? 'warning' : 'info')),
            'next_step' => self::label($next),
            'fields' => self::fields($fields),
            'issues' => $canResponses ? self::responseIssues($payment->status_response_json ?? '', $payment->verification_issues ?? '') : [],
            'bank_return' => $canResponses ? self::bankFields($payment->response_json ?? '') : [],
            'bank_check' => $canResponses ? self::bankFields($payment->status_response_json ?? '') : [],
            'timeline' => $canResponses ? array_map([self::class, 'event'], $events) : [],
        ];
    }

    /** Application timestamps are stored in UTC, explicitly labeled to avoid ambiguous receipts. */
    public static function date(?string $value): string
    {
        if (!$value) {
            return '';
        }
        try {
            $date = new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
            return $date->format('d M Y, H:i:s') . ' UTC';
        } catch (\Throwable $exception) {
            return self::label('unavailable');
        }
    }

    private static function fields(array $values): array
    {
        $fields = [];
        foreach ($values as $label => $value) {
            if ($value !== null && trim((string)$value) !== '') {
                $fields[] = ['label' => self::label($label), 'value' => (string)$value];
            }
        }
        return $fields;
    }

    public static function bankFields(?string $json): array
    {
        $decoded = json_decode((string)$json, true);
        if (!is_array($decoded)) {
            return [];
        }
        $safe = Bank_muscat_gateway::safeResponse($decoded);
        $status = (string)($safe['order_status'] ?? '');
        $bankStatus = $status === '' ? '' : self::label('bank_status_' . Bank_muscat_gateway::statusCategory($status));
        if (in_array(strtolower($status), ['refunded', 'refund', 'partially refunded'], true)) {
            $bankStatus = self::label('bank_status_refunded');
        }
        $bankDate = $safe['order_date_time'] ?? '';
        $fields = [
            'bank_result' => $bankStatus,
            'gateway_order' => $safe['order_no'] ?? $safe['order_id'] ?? '',
            'gateway_payment' => $safe['reference_no'] ?? $safe['tracking_id'] ?? '',
            'bank_reference' => $safe['order_bank_ref_no'] ?? $safe['bank_ref_no'] ?? '',
            'bank_amount' => trim(($safe['order_currency'] ?? $safe['order_currncy'] ?? $safe['currency'] ?? '') . ' ' . ($safe['order_amt'] ?? $safe['amount'] ?? '')),
            'payment_method' => self::paymentMethod($safe['order_option_type'] ?? $safe['payment_mode'] ?? ''),
            'card_brand' => $safe['order_card_name'] ?? $safe['card_name'] ?? $safe['card_type'] ?? '',
            'masked_card' => $safe['masked_card'] ?? '',
            'bank_payment_date' => $bankDate,
            'bank_update_date' => $safe['order_status_date_time'] ?? '',
            'bank_message' => $safe['failure_message'] ?? $safe['status_message'] ?? $safe['error_desc'] ?? '',
        ];
        if (isset($safe['status']) && $safe['status'] !== '0' && $bankStatus === '') {
            $fields = ['bank_result' => self::label('bank_check_unavailable')] + $fields;
        }
        if (self::bankOrderNotFound($json)) {
            $fields['bank_result'] = self::label('bank_order_not_found');
            $fields['bank_message'] = self::label('next_bank_not_found');
        }
        return self::fields($fields);
    }

    private static function bankOrderNotFound(?string $json): bool
    {
        $response = json_decode((string)$json, true);
        return is_array($response) && (string)($response['error_code'] ?? '') === '51313';
    }

    private static function responseIssues(?string $response, ?string $issues): array
    {
        // A missing bank order naturally has no amount/currency/date fields. Do
        // not present those absences as multiple conflicting payment records.
        return self::bankOrderNotFound($response) ? [self::label('next_bank_not_found')] : self::issues($issues);
    }

    private static function paymentMethod(string $value): string
    {
        return match (strtoupper(trim($value))) {
            'OPTCRDC', 'CC', 'CREDIT CARD' => self::label('credit_card'),
            'OPTDBCRD', 'DC', 'DEBIT CARD' => self::label('debit_card'),
            'OPTNBK', 'NB', 'NET BANKING' => self::label('bank_transfer'),
            default => $value,
        };
    }

    public static function issues(?string $json): array
    {
        $issues = json_decode((string)$json, true);
        if (!is_array($issues)) {
            return $json ? [self::label('issue_other')] : [];
        }
        $labels = [];
        foreach ($issues as $issue) {
            if (!is_string($issue)) {
                $labels[] = self::label('issue_other');
                continue;
            }
            $key = match ($issue) {
                'order_id_mismatch', 'outer_order_id_mismatch', 'unknown_order_id' => 'issue_order',
                'amount_mismatch', 'amount_missing_or_invalid' => 'issue_amount',
                'currency_missing_or_mismatch', 'callback_api_currency_mismatch' => 'issue_currency',
                'merchant_mismatch' => 'issue_merchant',
                'order_status_missing', 'callback_api_status_mismatch' => 'issue_status',
                'bank_tracking_reference_missing', 'bank_tracking_reference_conflict', 'callback_api_tracking_id_mismatch', 'callback_api_bank_ref_no_mismatch' => 'issue_reference',
                'order_timestamp_out_of_bounds', 'order_timestamp_missing_or_invalid', 'callback_api_timestamp_mismatch' => 'issue_date',
                'bank_status_query_error', 'bank_status_api_unavailable_or_unverified' => 'issue_bank_unavailable',
                'subject_settlement_failed' => 'settlement_review_hint',
                'checkout_not_handed_to_bank', 'encrypted_response_authentication_failed',
                'encrypted_response_invalid_format', 'encrypted_response_tag_invalid', 'encrypted_response_fields_invalid' => 'issue_authentication',
                default => 'issue_other',
            };
            $labels[] = self::label($key);
        }
        return array_values(array_unique($labels));
    }

    public static function event(object $event): array
    {
        $type = match ($event->event_type ?? '') {
            'checkout.created' => 'event_created',
            'checkout.handed_off' => 'event_handed_off',
            'checkout.superseded' => 'event_superseded',
            'return.received' => 'event_returned',
            'return.rejected', 'return.unknown_order' => 'event_return_rejected',
            'status.requested' => 'event_check_requested',
            'status.received' => 'event_check_received',
            default => 'event_updated',
        };
        $status = match ($event->status ?? '') {
            'rejected' => 'event_needs_review',
            'processed' => 'event_checked',
            default => 'event_recorded',
        };
        return ['title' => self::label($type), 'status' => self::label($status),
            'date' => self::date($event->received_at ?? ''),
            'issues' => self::responseIssues($event->response_json ?? '', $event->verification_issues ?? ''),
            'fields' => self::bankFields($event->response_json ?? '')];
    }
}
