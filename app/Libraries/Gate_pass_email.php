<?php

namespace App\Libraries;

/** A complete HTML snapshot can use the existing notification outbox without schema changes. */
final class Gate_pass_email
{
    private const REVIEW_MARKER = '<!-- PODC_GATE_PASS_DEPARTMENT_REVIEW_V1 -->';

    public static function departmentReview(object $request): string
    {
        return self::REVIEW_MARKER . "\n" . view('emails/gate_pass_department_review', [
            'request' => $request,
            'dashboard_url' => get_uri('gate_pass_department_requests'),
            'logo_url' => base_url('assets/images/port-duqum-email-logo.png'),
            'visit_from_label' => self::visitDate($request->visit_from ?? null),
            'visit_to_label' => self::visitDate($request->visit_to ?? null),
        ], ['saveData' => false, 'debug' => false]);
    }

    public static function isDepartmentReview(string $message): bool
    {
        return str_starts_with($message, self::REVIEW_MARKER . "\n<!DOCTYPE html>");
    }

    private static function visitDate($value): string
    {
        if (!$value) { return 'Not specified'; }
        // Visit dates are local calendar dates, not UTC audit timestamps.
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', substr((string) $value, 0, 10));
        return $date ? $date->format('d M Y') : 'Not specified';
    }
}
