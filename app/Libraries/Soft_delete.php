<?php

namespace App\Libraries;

/** Transactional aggregate deletion. Relationships point to owned rows only. */
final class Soft_delete
{
    public function __construct(private $db) {}

    public static function ownership(): array
    {
        $map = [
            'country' => ['regions' => 'country_id'],
            'regions' => ['cities' => 'regions_id'],
            'companies' => ['departments' => 'company_id'],
            'vendor_categories' => ['vendor_sub_categories' => 'vendor_category_id'],
            'vendor_groups' => ['vendor_group_fees' => 'vendor_group_id'],
            'vendors' => array_fill_keys(['vendor_bank_accounts','vendor_branches','vendor_contacts','vendor_credentials','vendor_documents','vendor_specialties','vendor_status_histories','vendor_update_requests','vendor_users','vendor_performance_scores','tender_target_vendors','tender_request_vendors','tender_invited_vendors','tender_bids','tender_fee_payments','tender_vendor_performance_evaluations'], 'vendor_id'),
            'gate_pass_requests' => array_fill_keys(['gate_pass_request_visitors','gate_pass_request_vehicles','gate_pass_request_approvals','gate_pass_request_audit_log','gate_passes'], 'gate_pass_request_id'),
            'gate_pass_request_visitors' => ['gate_passes' => 'gate_pass_request_visitor_id'],
            'ptw_applications' => array_fill_keys(['ptw_requirement_responses','ptw_attachments','ptw_reviews','ptw_audit_logs'], 'ptw_application_id'),
            'ptw_requirement_responses' => ['ptw_attachments' => 'ptw_requirement_response_id'],
            'tender_requests' => array_fill_keys(['tender_request_approvals','tender_request_team_members','tender_request_vendors','tenders'], 'tender_request_id'),
            'tenders' => array_fill_keys(['tender_bid_requirements','tender_bids','tender_bid_openings','tender_communications','tender_communication_attachments','tender_criteria','tender_documents','tender_evaluations','tender_evaluation_attachments','tender_extensions','tender_fee_payments','tender_invited_vendors','tender_opening_sessions','tender_rfq_details','tender_rfq_items','tender_target_specialties','tender_target_vendors','tender_team_members','tender_vendor_performance_evaluations','tender_workflow_history'], 'tender_id'),
            'tender_bids' => array_fill_keys(['tender_bid_documents','tender_bid_item_prices','tender_evaluations','tender_communications'], 'tender_bid_id'),
            'tender_bid_openings' => ['tender_bid_opening_entries' => 'tender_bid_opening_id'],
            'tender_opening_sessions' => array_fill_keys(['tender_opening_attempts','tender_opening_members'], 'tender_opening_session_id'),
            'tender_evaluations' => array_fill_keys(['tender_evaluation_scores','tender_evaluation_attachments'], 'tender_evaluation_id'),
            'tender_communications' => ['tender_communication_attachments' => 'communication_id'],
            'users' => array_fill_keys(['vendor_users','vendor_contacts','gate_pass_users','gate_pass_department_users','gate_pass_commercial_users','gate_pass_security_users','gate_pass_rop_users','ptw_applicant_users','ptw_hsse_users','ptw_hmo_users','ptw_terminal_users','tender_department_users','tender_department_manager_users','tender_finance_users','tender_committee_users','tender_procurement_users','tender_procurement_manager_users','tender_technical_users','tender_commercial_users'], 'user_id'),
        ];
        // Company/department assignments own access links, never global users.
        foreach (['gate_pass_department_users','gate_pass_commercial_users','gate_pass_security_users','gate_pass_rop_users','ptw_applicant_users','ptw_hsse_users','ptw_hmo_users','ptw_terminal_users','tender_department_users','tender_department_manager_users','tender_finance_users','tender_committee_users','tender_procurement_users','tender_procurement_manager_users','tender_technical_users','tender_commercial_users','user_permissions'] as $child) {
            $map['companies'][$child] = 'company_id';
        }
        $map['departments'] = array_fill_keys(['gate_pass_department_users','tender_department_users','tender_department_manager_users'], 'department_id');
        $map['users']['user_permissions'] = 'user_id';
        $map['vendors'] += array_fill_keys(['user_permissions','tender_communications','tender_communication_attachments','tender_bid_item_prices'], 'vendor_id');
        $map['tenders'] += array_fill_keys(['tender_bid_item_prices','vendor_performance_scores'], 'tender_id');
        $map['tender_bids'] += array_fill_keys(['tender_evaluation_attachments','tender_vendor_performance_evaluations'], 'tender_bid_id');
        return $map;
    }

    /** Shared master records are references, not ownership of a customer's data. */
    private static function references(): array
    {
        return [
            'vendors' => ['vendor_group_id'=>'vendor_groups','vendor_grade_id'=>'vendor_grades','legal_type_id'=>'legal_types','country_id'=>'country','region_id'=>'regions','city_id'=>'cities'],
            'vendor_branches' => ['country_id'=>'country','region_id'=>'regions','city_id'=>'cities'],
            'vendor_documents' => ['vendor_document_type_id'=>'vendor_document_types'],
            'vendor_specialties' => ['vendor_category_id'=>'vendor_categories','vendor_sub_category_id'=>'vendor_sub_categories'],
            'vendor_users' => ['vendor_role_id'=>'vendor_roles'],
            'gate_pass_requests' => ['company_id'=>'companies','department_id'=>'departments','gate_pass_purpose_id'=>'gate_pass_purposes'],
            'ptw_applications' => ['company_id'=>'companies'],
            'ptw_requirement_responses' => ['ptw_requirement_definition_id'=>'ptw_requirement_definitions'],
            'ptw_attachments' => ['ptw_requirement_id'=>'ptw_requirement_definitions'],
            'tender_requests' => ['company_id'=>'companies','department_id'=>'departments'],
            'tenders' => ['company_id'=>'companies','department_id'=>'departments'],
            'tender_target_specialties' => ['vendor_category_id'=>'vendor_categories','vendor_sub_category_id'=>'vendor_sub_categories','vendor_group_id'=>'vendor_groups','vendor_grade_id'=>'vendor_grades'],
            'tender_evaluation_scores' => ['tender_criterion_id'=>'tender_criteria'],
            'contracts' => ['company_id'=>'companies'],
            'invoices' => ['company_id'=>'companies'],
            'orders' => ['company_id'=>'companies'],
            'estimates' => ['company_id'=>'companies'],
            'proposals' => ['company_id'=>'companies'],
            'subscriptions' => ['company_id'=>'companies'],
        ];
    }

    public static function supports(string $table): bool
    {
        return in_array($table, ['users','user_permissions','vendors','country','regions','cities','companies','departments','legal_types'], true)
            || (bool) preg_match('/^(vendor_|gate_pass|ptw_|tender)/', $table);
    }

    private function exists(string $table, string $column = 'deleted'): bool
    {
        return $this->db->tableExists($table) && $this->db->fieldExists($column, $table)
            && $this->db->fieldExists('id', $table);
    }

    private function collect(string $table, int $id, array &$rows): void
    {
        $key = $table . ':' . $id;
        if (isset($rows[$key]) || !$this->exists($table)) { return; }
        $row = $this->db->query('SELECT * FROM `' . $this->db->prefixTable($table) . '` WHERE id=? AND deleted=0 FOR UPDATE', [$id])->getRowArray();
        if (!$row) { return; }
        $rows[$key] = [$table, $row];
        foreach (self::ownership()[$table] ?? [] as $child => $column) {
            if (!$this->exists($child) || !$this->db->fieldExists($column, $child)) { continue; }
            $children = $this->db->table($child)->select('id')->where($column, $id)->where('deleted', 0)->orderBy('id')->get()->getResultArray();
            foreach ($children as $childRow) { $this->collect($child, (int) $childRow['id'], $rows); }
        }
    }

    private function assertNoSharedDependents(array $rows): void
    {
        foreach ($rows as [$parent, $row]) {
            foreach (self::references() as $child => $links) {
                foreach ($links as $column => $target) {
                    if ($target !== $parent || !$this->exists($child) || !$this->db->fieldExists($column, $child)) { continue; }
                    $linked = $this->db->table($child)->select('id')->where($column, $row['id'])->where('deleted', 0)->get()->getResultArray();
                    foreach ($linked as $link) {
                        if (!isset($rows[$child . ':' . $link['id']])) { throw new \DomainException('soft_delete_in_use'); }
                    }
                }
            }
        }
    }

    private function assertParentsActive(array $rows): void
    {
        $parents = self::references();
        foreach (self::ownership() as $parent => $children) {
            foreach ($children as $child => $column) { $parents[$child][$column] = $parent; }
        }
        foreach ($rows as [$table, $row]) {
            foreach ($parents[$table] ?? [] as $column => $parent) {
                $parentId = (int) ($row[$column] ?? 0);
                if (!$parentId || isset($rows[$parent . ':' . $parentId]) || !$this->exists($parent)) { continue; }
                if (!$this->db->table($parent)->where('id', $parentId)->where('deleted', 0)->countAllResults()) {
                    throw new \DomainException('soft_delete_restore_conflict');
                }
            }
        }
    }

    /** Throws on any refusal; the caller returns a validation message. */
    public function change(string $table, int $id, bool $undo, callable $save)
    {
        $this->db->transBegin();
        try {
            $root = $this->db->query('SELECT * FROM `' . $this->db->prefixTable($table) . '` WHERE id=? FOR UPDATE', [$id])->getRowArray();
            if (!$root) { throw new \DomainException('record_not_found'); }
            if ((int) $root['deleted'] === ($undo ? 0 : 1)) {
                $result = $save();
                if (!$result || !$this->db->transStatus()) { throw new \DomainException('error_occurred'); }
                $this->db->transCommit();
                return $result;
            }
            if (!$this->exists('soft_delete_items', 'batch_key')) { throw new \DomainException('soft_delete_setup_required'); }
            if ($undo) {
                $batch = $this->db->table('soft_delete_items')->where('root_table', $table)->where('root_id', $id)->where('restored_at', null)->orderBy('id', 'DESC')->get(1)->getRowArray();
                $rows = [$table . ':' . $id => [$table, $root]];
                if ($batch) {
                    foreach ($this->db->table('soft_delete_items')->where('batch_key', $batch['batch_key'])->where('restored_at', null)->orderBy('id')->get()->getResultArray() as $item) {
                        $child = $item['table_name'];
                        if (!self::supports($child) || !$this->exists($child)) { throw new \DomainException('soft_delete_restore_conflict'); }
                        $latest = $this->db->table('soft_delete_items')->where('table_name', $child)->where('record_id', $item['record_id'])->where('restored_at', null)->orderBy('id','DESC')->get(1)->getRowArray();
                        if (($latest['batch_key'] ?? '') !== $batch['batch_key']) { continue; }
                        $row = $this->db->query('SELECT * FROM `' . $this->db->prefixTable($child) . '` WHERE id=? FOR UPDATE', [$item['record_id']])->getRowArray();
                        if ($row && (int) $row['deleted'] === 1) { $rows[$child . ':' . $row['id']] = [$child, $row]; }
                    }
                }
                $this->assertParentsActive($rows);
                // Refuse predictable unique collisions before any restore writes.
                foreach ($rows as [$child, $row]) {
                    foreach ($this->db->getIndexData($child) as $index) {
                        if ($index->type !== 'UNIQUE') { continue; }
                        $query = $this->db->table($child)->where('id !=', $row['id']);
                        $hasNull = false;
                        foreach ($index->fields as $field) {
                            $value = match ($field) {
                                '_live_row'=>1, 'deleted'=>0,
                                'cr_number_identity'=>trim((string) ($row['cr_number'] ?? ''), ' ') ?: null,
                                'live_email_identity'=>trim((string) ($row['email'] ?? ''), ' ') ?: null,
                                'live_user_identity'=>$row['user_id'] ?? null,
                                default=>$row[$field] ?? null,
                            };
                            if ($value === null) { $hasNull = true; break; }
                            $query->where($field, $value);
                        }
                        if (!$hasNull && $query->countAllResults()) { throw new \DomainException('soft_delete_restore_conflict'); }
                    }
                }
                foreach ($rows as [$child, $row]) {
                    if ($child === $table && (int) $row['id'] === $id) { continue; }
                    if (!$this->db->table($child)->where('id', $row['id'])->update(['deleted'=>0])) { throw new \DomainException('soft_delete_restore_conflict'); }
                }
                $result = $save();
                if ($batch) { $this->db->table('soft_delete_items')->where('batch_key',$batch['batch_key'])->update(['restored_at'=>date('Y-m-d H:i:s')]); }
            } else {
                $rows = [];
                $this->collect($table, $id, $rows);
                $this->assertNoSharedDependents($rows);
                $batch = bin2hex(random_bytes(16));
                foreach ($rows as [$child, $row]) {
                    $this->db->table('soft_delete_items')->insert(['batch_key'=>$batch,'root_table'=>$table,'root_id'=>$id,'table_name'=>$child,'record_id'=>$row['id'],'created_at'=>date('Y-m-d H:i:s')]);
                    if ($child === $table && (int) $row['id'] === $id) { continue; }
                    $this->db->table($child)->where('id',$row['id'])->update(['deleted'=>1]);
                }
                $result = $save();
            }
            if (!$result || !$this->db->transStatus()) { throw new \DomainException($undo ? 'soft_delete_restore_conflict' : 'error_occurred'); }
            $this->db->transCommit();
            return $result;
        } catch (\Throwable $error) {
            $this->db->transRollback();
            if ($error instanceof \DomainException) { throw $error; }
            throw new \DomainException($undo ? 'soft_delete_restore_conflict' : 'error_occurred', 0, $error);
        }
    }
}
