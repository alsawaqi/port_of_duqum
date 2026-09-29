<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;

/** Fixed duration bands, charged once for each person on the request. */
final class Gate_pass_tariff
{
    public function __construct(private BaseConnection $db) {}

    public static function breakdown(object $rule, int $days, int $visitors): array
    {
        if ($days < 1 || $days > 365 || $visitors < 0
            || $days < (int)$rule->min_days || $days > (int)$rule->max_days
            || ($rule->rate_type ?? '') !== 'flat') {
            throw new \DomainException('gate_pass_tariff_not_configured');
        }
        $unit = self::minor($rule->amount);
        $induction = self::minor($rule->induction_amount ?? 0);
        $total = ($unit + $induction) * $visitors;
        if ($total > 999999999999) {
            throw new \DomainException('gate_pass_tariff_not_configured');
        }
        return [
            'version' => 1, 'rule_id' => (int)($rule->id ?? 0),
            'days' => $days, 'min_days' => (int)$rule->min_days, 'max_days' => (int)$rule->max_days,
            'currency' => strtoupper((string)$rule->currency), 'visitor_count' => $visitors,
            'unit_amount' => self::money($unit), 'induction_unit_amount' => self::money($induction),
            'tariff_subtotal' => self::money($unit * $visitors),
            'induction_subtotal' => self::money($induction * $visitors),
            'total' => self::money($total),
        ];
    }

    private static function minor($value): int
    {
        $value = (string)$value;
        if (!preg_match('/^\d{1,9}(?:\.\d{1,3})?$/D', $value)) {
            throw new \DomainException('gate_pass_tariff_not_configured');
        }
        $parts = explode('.', $value);
        return (int)$parts[0] * 1000 + (int)str_pad($parts[1] ?? '', 3, '0');
    }

    private static function money(int $minor): string
    {
        return intdiv($minor, 1000) . '.' . str_pad((string)($minor % 1000), 3, '0', STR_PAD_LEFT);
    }

    public static function snapshot(object $request): ?array
    {
        $quote = json_decode((string)($request->fee_breakdown ?? ''), true);
        return is_array($quote) && ($quote['version'] ?? null) === 1 ? $quote : null;
    }

    public function quote(object $request): array
    {
        if (!$this->db->fieldExists('fee_breakdown', 'gate_pass_requests')
            || !$this->db->fieldExists('induction_amount', 'gate_pass_fee_rules')) {
            throw new \DomainException('gate_pass_tariff_upgrade_required');
        }
        $days = gate_pass_visit_duration_days($request->visit_from ?? null, $request->visit_to ?? null);
        $currency = strtoupper((string)($request->currency ?? 'OMR'));
        $rules = $this->db->prefixTable('gate_pass_fee_rules');
        $matches = $this->db->query("SELECT * FROM {$rules} WHERE deleted=0 AND is_active=1
            AND currency=? AND min_days<=? AND max_days>=?", [$currency, $days, $days])->getResult();
        // Ambiguous, missing or old multiplying rules must never silently charge a fee.
        if (count($matches) !== 1) {
            throw new \DomainException('gate_pass_tariff_not_configured');
        }
        $visitors = $this->visitorCount((int)($request->id ?? 0));
        $quote = self::breakdown($matches[0], $days, $visitors);
        $quote['visit_from'] = (string)$request->visit_from;
        $quote['visit_to'] = (string)$request->visit_to;
        return $quote;
    }

    public function visitorCount(int $requestId): int
    {
        if ($requestId < 1) { return 0; }
        $table = $this->db->prefixTable('gate_pass_request_visitors');
        return (int)$this->db->query("SELECT COUNT(*) AS n FROM {$table} WHERE gate_pass_request_id=? AND deleted=0", [$requestId])->getRow()->n;
    }

    /** Caller must hold a transaction; checkout uses the same parent-row lock. */
    public function lock(int $requestId): object
    {
        $table = $this->db->prefixTable('gate_pass_requests');
        $request = $this->db->query("SELECT * FROM {$table} WHERE id=? AND deleted=0 FOR UPDATE", [$requestId])->getRow();
        if (!$request) { throw new \DomainException('record_not_found'); }
        return $request;
    }

    public function assertEditable(object $request): void
    {
        if (!in_array((string)$request->status, ['draft', 'returned'], true)
            || in_array((string)($request->stage ?? ''), ['security', 'rop', 'issued'], true)) {
            throw new \DomainException('gate_pass_tariff_locked');
        }
        if ($this->hasPayment($request)) {
            throw new \DomainException('gate_pass_tariff_locked');
        }
    }

    public function hasPayment(object $request): bool
    {
        if ($this->db->tableExists('eservice_payments')) {
            $table = $this->db->prefixTable('eservice_payments');
            $payment = $this->db->query("SELECT id FROM {$table} WHERE subject_type='gate_pass_fee'
                AND subject_id=? AND deleted=0 AND status IN ('pending','processing','verification_required','paid') LIMIT 1", [(int)$request->id])->getRow();
            return (bool)$payment;
        }
        return false;
    }

    public function refresh(int $requestId): array
    {
        $request = $this->lock($requestId);
        $quote = $this->quote($request);
        $ok = $this->db->table('gate_pass_requests')->where('id', $requestId)->update(self::fields($quote));
        if (!$ok) { throw new \RuntimeException('Unable to save gate pass tariff.'); }
        return $quote;
    }

    public static function fields(array $quote): array
    {
        return ['fee_amount' => $quote['total'], 'fee_breakdown' => json_encode($quote, JSON_THROW_ON_ERROR)];
    }

    /** Validate the saved quote without repricing an approved or historical request. */
    public function assertPayable(object $request): void
    {
        $quote = self::snapshot($request);
        if (!$quote) {
            if (trim((string)($request->fee_breakdown ?? '')) !== '') {
                throw new \DomainException('gate_pass_tariff_changed');
            }
            return; // Pre-upgrade requests retain their recorded fee.
        }
        if (($quote['visitor_count'] ?? 0) < 1
            || (int)$quote['visitor_count'] !== $this->visitorCount((int)$request->id)
            || ($quote['visit_from'] ?? '') !== (string)$request->visit_from
            || ($quote['visit_to'] ?? '') !== (string)$request->visit_to
            || ($quote['currency'] ?? '') !== strtoupper((string)$request->currency)
            || self::minor($quote['total'] ?? '') !== self::minor($request->fee_amount)) {
            throw new \DomainException('gate_pass_tariff_changed');
        }
    }
}
