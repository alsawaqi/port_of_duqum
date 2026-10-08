<?php

namespace App\Libraries;

class Tender_team_validation
{
    public static function error($db, int $companyId, array $teams): ?string
    {
        $users = $db->prefixTable('users');
        foreach (['technical' => 'technical', 'commercial' => 'commercial', 'chairman' => 'committee', 'secretary' => 'committee', 'itc_member' => 'committee'] as $key => $role) {
            $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($teams[$key] ?? [])))));
            if (!$ids) { continue; }
            $pivot = $db->prefixTable('tender_' . $role . '_users');
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $row = $db->query("SELECT COUNT(DISTINCT u.id) AS n FROM $users u
                JOIN $pivot p ON p.user_id=u.id AND p.deleted=0 AND p.status='active'
                WHERE u.deleted=0 AND u.status='active' AND u.disable_login=0
                  AND p.company_id=? AND u.id IN ($marks)", array_merge([$companyId], $ids))->getRow();
            if ((int) $row->n !== count($ids)) {
                return 'Select active ' . str_replace('_', ' ', $key) . ' users assigned to this company.';
            }
        }
        return null;
    }
}
