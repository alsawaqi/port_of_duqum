<?php

namespace App\Libraries\Payments;

use InvalidArgumentException;

/** Shared, fail-closed permissions and filter validation for payment ledgers. */
final class Payment_accounting_policy
{
    public const MODULES = [
        'vendor' => ['vendor_registration', 'vendor_renewal'],
        'gate_pass' => ['gate_pass_fee'],
        'tender' => ['tender_fee'],
        'ptw' => ['ptw_fee'],
    ];
    public const ACTIONS = ['view', 'responses', 'export', 'reconcile'];
    public const STATUSES = ['pending', 'processing', 'paid', 'failed', 'cancelled', 'expired', 'verification_required', 'needs_review'];

    public const COMPANY_MODULES = ['gate_pass', 'tender', 'ptw'];

    public static function isAccountingOnly(object $actor): bool
    {
        $permissions = (array) ($actor->permissions ?? []);
        return !empty($actor->id) && ($actor->user_type ?? '') === 'staff'
            && empty($actor->is_admin) && (string) ($permissions['accounting_only'] ?? '') === '1';
    }

    public static function home(object $actor): string
    {
        foreach (self::MODULES as $module => $_types) {
            if (self::allows($actor, $module)) {
                return 'payment_accounting/index/' . $module;
            }
        }
        return 'forbidden';
    }

    /** An accounting-only role never inherits operational access from assignments. */
    public static function accountingRouteAllowed(string $controller, string $method): bool
    {
        $controller = strtolower($controller);
        $method = strtolower($method);
        return $controller === 'payment_accounting'
            || ($controller === 'portal_account' && in_array($method, ['index', 'change_password', 'save_password'], true))
            || ($controller === 'team_members' && $method === 'save_personal_language');
    }

    /** Null preserves legacy operational scopes; an explicit empty list denies all companies. */
    public static function companyIds(object $actor, string $module): ?array
    {
        if (!in_array($module, self::COMPANY_MODULES, true)) {
            return null;
        }
        $permissions = (array) ($actor->permissions ?? []);
        $key = $module . '_accounting_company_ids';
        if (!array_key_exists($key, $permissions)) {
            return $module === 'ptw' ? [] : null;
        }
        try {
            return self::normalizeCompanyIds($permissions[$key]);
        } catch (InvalidArgumentException $exception) {
            return [];
        }
    }

    public static function normalizeCompanyIds($values): array
    {
        if (!is_array($values) || count($values) > 1000) {
            throw new InvalidArgumentException('Invalid accounting company selection.');
        }
        $ids = [];
        foreach ($values as $value) {
            if ((!is_int($value) && !is_string($value)) || !preg_match('/^[1-9][0-9]{0,9}$/D', (string) $value)
                || (int) $value > 2147483647) {
                throw new InvalidArgumentException('Invalid accounting company selection.');
            }
            $ids[(int) $value] = (int) $value;
        }
        return array_values($ids);
    }

    public static function allows(object $actor, string $module, string $action = 'view'): bool
    {
        if (!isset(self::MODULES[$module]) || !in_array($action, self::ACTIONS, true)
            || empty($actor->id) || ($actor->user_type ?? '') !== 'staff'
            || !empty($actor->is_vendor_only_identity) || !empty($actor->is_gate_pass_only_identity)
            || !empty($actor->is_ptw_applicant_only_identity)
            || (isset($actor->status) && $actor->status !== 'active') || !empty($actor->deleted)) {
            return false;
        }
        if (!empty($actor->is_admin)) {
            return true;
        }
        $permissions = (array) ($actor->permissions ?? []);
        return (string) ($permissions['can_view_' . $module . '_accounting'] ?? '') === '1'
            && (string) ($permissions['can_' . $action . '_' . $module . '_accounting'] ?? '') === '1';
    }

    public static function filters(array $input): array
    {
        $filters = [];
        foreach (['start_date', 'end_date'] as $key) {
            $date = trim(is_scalar($input[$key] ?? '') ? (string) ($input[$key] ?? '') : 'invalid');
            if ($date === '') {
                continue;
            }
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if (!$parsed || $parsed->format('Y-m-d') !== $date) {
                throw new InvalidArgumentException('Dates must use YYYY-MM-DD.');
            }
            $filters[$key] = $date;
        }
        if (isset($filters['start_date'], $filters['end_date']) && $filters['start_date'] > $filters['end_date']) {
            throw new InvalidArgumentException('The end date must be on or after the start date.');
        }
        $status = $input['status'] ?? '';
        if ($status !== '') {
            if (!is_string($status) || !in_array($status, self::STATUSES, true)) {
                throw new InvalidArgumentException('Invalid payment status.');
            }
            $filters['status'] = $status;
        }
        foreach (['vendor', 'payer', 'reference'] as $key) {
            if (isset($input[$key]) && !is_scalar($input[$key])) {
                throw new InvalidArgumentException('Invalid search filter.');
            }
            $value = trim((string) ($input[$key] ?? ''));
            if (strlen($value) > 200) {
                throw new InvalidArgumentException('Search filters must be 200 characters or fewer.');
            }
            if ($value !== '') {
                $filters[$key] = $value;
            }
        }
        return $filters;
    }
}
