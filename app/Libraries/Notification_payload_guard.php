<?php

namespace App\Libraries;

/** Restricts notification processor input to the fields its model consumes. */
final class Notification_payload_guard
{
    private const ID_FIELDS = [
        'user_id', 'project_id', 'task_id', 'project_comment_id', 'ticket_id',
        'ticket_comment_id', 'project_file_id', 'leave_id', 'post_id',
        'to_user_id', 'activity_log_id', 'client_id', 'invoice_payment_id',
        'invoice_id', 'estimate_id', 'order_id', 'estimate_request_id',
        'actual_message_id', 'parent_message_id', 'event_id', 'announcement_id',
        'contract_id', 'lead_id', 'proposal_id', 'estimate_comment_id',
        'subscription_id', 'expense_id', 'proposal_comment_id', 'reminder_log_id',
    ];

    private const BOOLEAN_FIELDS = [
        'exclude_ticket_creator',
        'notification_multiple_tasks',
    ];

    /**
     * @param list<string> $allowedPluginFields Plugin fields must be registered
     *        explicitly through the notification processor hook.
     */
    public static function sanitize(array $payload, array $allowedPluginFields = []): ?array
    {
        $allowedPluginFields = array_values(array_unique(array_filter(
            $allowedPluginFields,
            static fn($field): bool => is_string($field)
                && preg_match('/^plugin_[a-z0-9_]{1,64}$/D', $field) === 1
        )));

        $allowed = array_merge(['event'], self::ID_FIELDS, self::BOOLEAN_FIELDS, $allowedPluginFields);
        foreach (array_keys($payload) as $field) {
            if (!is_string($field) || !in_array($field, $allowed, true)) {
                return null;
            }
        }

        $event = $payload['event'] ?? null;
        if (!is_string($event) || $event === '' || strlen($event) > 1024) {
            return null;
        }

        $clean = ['event' => $event];
        foreach (self::ID_FIELDS as $field) {
            if (!array_key_exists($field, $payload)) {
                continue;
            }
            $value = self::unsignedInteger($payload[$field]);
            if ($value === null) {
                return null;
            }
            $clean[$field] = $value;
        }

        foreach (self::BOOLEAN_FIELDS as $field) {
            if (!array_key_exists($field, $payload)) {
                continue;
            }
            $value = filter_var($payload[$field], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($value === null) {
                return null;
            }
            $clean[$field] = $value ? 1 : 0;
        }

        foreach ($allowedPluginFields as $field) {
            if (!array_key_exists($field, $payload)) {
                continue;
            }
            $value = $payload[$field];
            if ($value === null) {
                $clean[$field] = '';
                continue;
            }
            if (!is_scalar($value) || strlen((string) $value) > 4096) {
                return null;
            }
            $clean[$field] = (string) $value;
        }

        return $clean;
    }

    /** @return int|string|null */
    private static function unsignedInteger($value)
    {
        if ($value === '' || $value === null) {
            return '';
        }
        if (is_int($value)) {
            return $value >= 0 ? $value : null;
        }
        if (!is_string($value) || preg_match('/^(?:0|[1-9]\d*)$/D', $value) !== 1) {
            return null;
        }

        // Preserve large numeric identifiers as strings instead of truncating
        // them on platforms whose integer width is smaller than the database.
        return strlen($value) < 19 ? (int) $value : $value;
    }
}
