<?php

namespace App\Models;

use App\Libraries\Payments\Payment_accounting_policy;

/** Read-only ledger; every entry point reuses the actor/module scope. */
class Payment_accounting_model
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? db_connect();
    }

    public function ready(): bool
    {
        return $this->db->tableExists('eservice_payments')
            && $this->db->tableExists('eservice_payment_events')
            && $this->db->fieldExists('status_response_json', 'eservice_payments')
            && $this->db->fieldExists('settlement_status', 'eservice_payments')
            && $this->db->fieldExists('response_json', 'eservice_payment_events');
    }

    public function rows(object $actor, string $module, array $filters = [], string $action = 'view', int $limit = 2000): array
    {
        [$sql, $params] = $this->ledgerQuery($actor, $module, $filters, $action);
        return $this->db->query($sql . ' ORDER BY p.id DESC LIMIT ' . min(10001, max(1, $limit)), $params)->getResult();
    }

    public function payment(object $actor, string $module, int $id, string $action = 'view'): ?object
    {
        [$sql, $params] = $this->ledgerQuery($actor, $module, [], $action);
        $params[] = $id;
        return $this->db->query($sql . ' AND p.id=? LIMIT 1', $params)->getRow();
    }

    public function events(object $actor, string $module, int $id): array
    {
        if (!$this->payment($actor, $module, $id, 'responses')) {
            return [];
        }
        $events = $this->db->prefixTable('eservice_payment_events');
        return $this->db->query("SELECT id, provider, provider_event_id, event_type, status,
            received_at, processed_at, payload_sha256, response_json, verification_issues
            FROM {$events} WHERE payment_id=? ORDER BY id ASC", [$id])->getResult();
    }

    private function ledgerQuery(object $actor, string $module, array $filters, string $action): array
    {
        if (!Payment_accounting_policy::allows($actor, $module, $action)) {
            throw new \RuntimeException('Payment accounting access denied.');
        }
        $filters = Payment_accounting_policy::filters($filters);
        $payments = $this->db->prefixTable('eservice_payments');
        $vendors = $this->db->prefixTable('vendors');
        $users = $this->db->prefixTable('users');
        $companies = $this->db->prefixTable('companies');
        $params = Payment_accounting_policy::MODULES[$module];
        $where = 'p.deleted=0 AND p.subject_type IN (' . implode(',', array_fill(0, count($params), '?')) . ')';
        $joins = " LEFT JOIN {$vendors} v ON v.id=p.vendor_id
                   LEFT JOIN {$users} u ON u.id=p.user_id";
        $company = 'NULL';
        $reference = 'v.cr_number';
        if ($module === 'gate_pass') {
            $requests = $this->db->prefixTable('gate_pass_requests');
            $joins .= " LEFT JOIN {$requests} r ON r.id=p.subject_id";
            $company = 'r.company_id';
            $reference = 'r.reference';
        } elseif ($module === 'tender') {
            $tenders = $this->db->prefixTable('tenders');
            $requests = $this->db->prefixTable('tender_requests');
            $joins .= " LEFT JOIN {$tenders} t ON t.id=p.subject_id
                        LEFT JOIN {$requests} r ON r.id=t.tender_request_id";
            $company = 'COALESCE(t.company_id, r.company_id)';
            $reference = 't.reference';
        } elseif ($module === 'ptw') {
            $applications = $this->db->prefixTable('ptw_applications');
            $joins .= " LEFT JOIN {$applications} r ON r.id=p.subject_id";
            $company = 'r.company_id';
            $reference = 'r.reference';
        }
        $joins .= " LEFT JOIN {$companies} c ON c.id={$company}";
        // Accounting-only roles select companies without operational assignments.
        // Roles saved before these scopes existed retain their existing assignment limits.
        if (empty($actor->is_admin) && $module !== 'vendor') {
            $companyIds = Payment_accounting_policy::companyIds($actor, $module);
            if ($companyIds !== null) {
                if ($companyIds) {
                    $where .= " AND c.deleted=0 AND c.is_active=1 AND {$company} IN ("
                        . implode(',', array_fill(0, count($companyIds), '?')) . ')';
                    array_push($params, ...$companyIds);
                    $where .= " AND EXISTS (SELECT 1 FROM {$users} au WHERE au.id=? AND au.deleted=0 AND au.status='active')";
                    $params[] = (int) $actor->id;
                } else {
                    $where .= ' AND 1=0';
                }
            } else {
                $assignment = $this->db->prefixTable($module === 'gate_pass' ? 'gate_pass_commercial_users' : 'tender_finance_users');
                $where .= " AND EXISTS (SELECT 1 FROM {$assignment} a
                    INNER JOIN {$users} au ON au.id=a.user_id AND au.deleted=0 AND au.status='active'
                    WHERE a.user_id=? AND a.deleted=0 AND a.status='active' AND a.company_id={$company})";
                $params[] = (int) $actor->id;
            }
        }
        if (isset($filters['start_date'])) {
            $where .= ' AND p.initiated_at>=?';
            $params[] = $filters['start_date'] . ' 00:00:00';
        }
        if (isset($filters['end_date'])) {
            $where .= ' AND p.initiated_at<?';
            $params[] = (new \DateTimeImmutable($filters['end_date']))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
        }
        if (($filters['status'] ?? '') === 'needs_review') {
            $where .= " AND (p.status='verification_required' OR p.settlement_status='review_required'
                OR (p.verification_issues IS NOT NULL AND p.verification_issues<>'' AND p.verification_issues<>'[]'))";
        } elseif (isset($filters['status'])) {
            $where .= ' AND p.status=?';
            $params[] = $filters['status'];
        }
        $searches = [
            'vendor' => ['v.vendor_name', 'v.cr_number'],
            'payer' => ["CONCAT_WS(' ',u.first_name,u.last_name)", 'u.email'],
            'reference' => ['p.public_id', 'p.provider_checkout_id', 'p.provider_payment_id', 'p.bank_reference', $reference],
        ];
        foreach ($searches as $key => $columns) {
            if (!isset($filters[$key])) {
                continue;
            }
            $conditions = [];
            foreach ($columns as $column) {
                $conditions[] = $column . " LIKE ? ESCAPE '!'";
                $params[] = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters[$key]) . '%';
            }
            $where .= ' AND (' . implode(' OR ', $conditions) . ')';
        }
        // Deliberately omit checkout URLs, idempotency keys and arbitrary metadata.
        $responseColumns = Payment_accounting_policy::allows($actor, $module, 'responses')
            ? ', p.response_json, p.status_response_json, p.verification_issues' : '';
        return ["SELECT p.id, p.public_id, p.subject_type, p.subject_id, p.vendor_id, p.user_id,
            p.amount, p.currency, p.provider, p.status, p.provider_checkout_id, p.provider_payment_id,
            JSON_UNQUOTE(JSON_EXTRACT(CASE WHEN JSON_VALID(p.metadata) THEN p.metadata ELSE '{}' END, '$.description')) AS description,
            p.bank_reference, p.gateway_merchant_id, p.initiated_at, p.handed_off_at, p.paid_at, p.failed_at, p.settlement_status,
            p.verified_at, p.failure_code, v.vendor_name, v.cr_number, u.email AS payer_email,
            (p.verification_issues IS NOT NULL AND p.verification_issues<>'' AND p.verification_issues<>'[]') AS has_verification_issues,
            CONCAT_WS(' ',u.first_name,u.last_name) AS payer_name, {$company} AS company_id,
            c.name AS company_name, {$reference} AS subject_reference {$responseColumns}
            FROM {$payments} p {$joins} WHERE {$where}", $params];
    }
}
