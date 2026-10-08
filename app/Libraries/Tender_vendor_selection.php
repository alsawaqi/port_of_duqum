<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use DomainException;

/** Closed tender audience: all chosen filters, plus explicitly selected vendors. */
class Tender_vendor_selection
{
    public function __construct(private BaseConnection $db)
    {
    }

    public function validate(array $input): array
    {
        $selection = [];
        foreach (['vendor_group_id', 'vendor_grade_id', 'vendor_category_id', 'vendor_sub_category_id'] as $field) {
            $selection[$field] = $this->id($input[$field] ?? null);
        }
        $ids = $input['specific_vendor_ids'] ?? [];
        if (!is_array($ids) || count($ids) > 1000) {
            throw new DomainException(app_lang('tender_audience_invalid'));
        }
        $selection['specific_vendor_ids'] = array_values(array_unique(array_filter(array_map($this->id(...), $ids))));
        foreach (['vendor_group_id' => 'vendor_groups', 'vendor_grade_id' => 'vendor_grades', 'vendor_category_id' => 'vendor_categories', 'vendor_sub_category_id' => 'vendor_sub_categories'] as $field => $table) {
            if (!$selection[$field]) {
                continue;
            }
            $row = $this->db->table($table)->where(['id' => $selection[$field], 'deleted' => 0])->get()->getRow();
            if (!$row || (isset($row->is_active) && !(int) $row->is_active)
                || ($field === 'vendor_sub_category_id' && (int) $row->vendor_category_id !== $selection['vendor_category_id'])) {
                throw new DomainException(app_lang('tender_audience_invalid'));
            }
        }
        if ($selection['specific_vendor_ids']) {
            $count = $this->db->table('vendors')->where(['deleted' => 0, 'status' => 'approved'])
                ->whereIn('id', $selection['specific_vendor_ids'])->countAllResults();
            if ($count !== count($selection['specific_vendor_ids'])) {
                throw new DomainException(app_lang('tender_audience_vendor_unavailable'));
            }
        }
        return $selection;
    }

    private function id($value): int
    {
        if ($value === null || $value === '' || $value === 0 || $value === '0') {
            return 0;
        }
        if ((!is_string($value) && !is_int($value)) || !preg_match('/^[1-9][0-9]{0,9}$/D', (string) $value)) {
            throw new DomainException(app_lang('tender_audience_invalid'));
        }
        return (int) $value;
    }

    public static function hasFilters(array $selection): bool
    {
        return !empty($selection['vendor_group_id']) || !empty($selection['vendor_grade_id']) || !empty($selection['vendor_category_id']);
    }

    public function withRequestFallback(array $selection, int $requestId): array
    {
        if ($requestId && !self::hasFilters($selection) && !$selection['specific_vendor_ids']) {
            $rows = $this->db->table('tender_request_vendors')->where(['tender_request_id' => $requestId, 'deleted' => 0])->get()->getResultArray();
            $selection['specific_vendor_ids'] = array_values(array_unique(array_map('intval', array_column($rows, 'vendor_id'))));
        }
        return $selection;
    }

    /** SQL aliases are internal constants, never request input. Zero means no category in legacy rows. */
    public static function matchingRuleSql(string $specialties, string $vendor = 'vendor_profile', string $rule = 'target'): string
    {
        return "($rule.id IS NOT NULL
            AND (COALESCE($rule.vendor_group_id, 0)>0 OR COALESCE($rule.vendor_grade_id, 0)>0 OR COALESCE($rule.vendor_category_id, 0)>0)
            AND (COALESCE($rule.vendor_group_id, 0)=0 OR $rule.vendor_group_id = $vendor.vendor_group_id)
            AND (COALESCE($rule.vendor_grade_id, 0)=0 OR $rule.vendor_grade_id = $vendor.vendor_grade_id)
            AND (COALESCE($rule.vendor_category_id, 0)=0 OR EXISTS (
                SELECT 1 FROM $specialties audience_specialty
                WHERE audience_specialty.vendor_id=$vendor.id AND audience_specialty.deleted=0
                  AND audience_specialty.status='approved'
                  AND audience_specialty.vendor_category_id=$rule.vendor_category_id
                  AND (COALESCE($rule.vendor_sub_category_id, 0)=0 OR audience_specialty.vendor_sub_category_id=$rule.vendor_sub_category_id)
            )))";
    }

    public function recipients(array $selection): array
    {
        $vendors = $this->db->prefixTable('vendors');
        $specialties = $this->db->prefixTable('vendor_specialties');
        $groups = $this->db->prefixTable('vendor_groups');
        $grades = $this->db->prefixTable('vendor_grades');
        $conditions = [];
        $params = [];
        foreach (['vendor_group_id', 'vendor_grade_id'] as $field) {
            if (!empty($selection[$field])) {
                $conditions[] = "v.$field=?";
                $params[] = $selection[$field];
            }
        }
        if (!empty($selection['vendor_category_id'])) {
            $specialty = "EXISTS (SELECT 1 FROM $specialties s WHERE s.vendor_id=v.id AND s.deleted=0 AND s.status='approved' AND s.vendor_category_id=?";
            $params[] = $selection['vendor_category_id'];
            if (!empty($selection['vendor_sub_category_id'])) {
                $specialty .= ' AND s.vendor_sub_category_id=?';
                $params[] = $selection['vendor_sub_category_id'];
            }
            $conditions[] = $specialty . ')';
        }
        // No filters means nobody, never all vendors. Explicit selections still apply.
        $filter = $conditions ? '(' . implode(' AND ', $conditions) . ')' : '0=1';
        $explicit = $selection['specific_vendor_ids'] ?? [];
        $where = $filter;
        if ($explicit) {
            $where .= ' OR v.id IN (' . implode(',', array_fill(0, count($explicit), '?')) . ')';
            $params = array_merge($params, $explicit);
        }
        $rows = $this->db->query("SELECT v.id, v.vendor_name AS name, v.cr_number,
                g.name AS vendor_group, gr.name AS grade
            FROM $vendors v
            LEFT JOIN $groups g ON g.id=v.vendor_group_id AND g.deleted=0
            LEFT JOIN $grades gr ON gr.id=v.vendor_grade_id AND gr.deleted=0
            WHERE v.deleted=0 AND v.status='approved' AND ($where)
            ORDER BY v.vendor_name, v.id", $params)->getResultArray();
        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
            $row['explicit'] = in_array($row['id'], $explicit, true);
        }
        return $rows;
    }

    /** Caller owns the transaction; used by procurement and approved manager changes. */
    public function save(int $tenderId, array $selection, int $userId): void
    {
        foreach (['tender_target_specialties', 'tender_target_vendors'] as $table) {
            $this->db->table($table)->where('tender_id', $tenderId)->update(['deleted' => 1]);
        }
        $base = ['tender_id' => $tenderId, 'created_by' => $userId, 'created_at' => date('Y-m-d H:i:s'), 'deleted' => 0];
        if (self::hasFilters($selection)) {
            $this->db->table('tender_target_specialties')->insert($base + [
                'vendor_category_id' => $selection['vendor_category_id'],
                'vendor_sub_category_id' => $selection['vendor_sub_category_id'] ?: null,
                'vendor_group_id' => $selection['vendor_group_id'] ?: null,
                'vendor_grade_id' => $selection['vendor_grade_id'] ?: null,
            ]);
        }
        foreach ($selection['specific_vendor_ids'] as $id) {
            $this->db->table('tender_target_vendors')->insert($base + ['vendor_id' => $id]);
        }
    }
}
