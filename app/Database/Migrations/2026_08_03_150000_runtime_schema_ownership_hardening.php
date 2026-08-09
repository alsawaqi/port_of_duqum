<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

class Runtime_schema_ownership_hardening extends Migration
{
    public function up()
    {
        $this->addCompatibilityColumns();
        $this->normalizeCompatibilityDefinitions();
        $this->createOwnedTables();
        $this->ensureOwnedIndexes();
        $this->ensureRfqForeignKeys();
        $this->migrateCompatibilityData();
    }

    public function down()
    {
        // These structures may predate this consolidating migration and contain
        // business records. An automatic destructive rollback is intentionally omitted.
    }

    private function addCompatibilityColumns(): void
    {
        $columns = [
            'gate_pass_request_vehicles' => [
                'is_international_plate' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'plate_country' => 'VARCHAR(120) DEFAULT NULL',
                'international_plate_no' => 'VARCHAR(120) DEFAULT NULL',
            ],
            'vendors' => [
                'cr_number' => 'VARCHAR(100) DEFAULT NULL',
                'phone' => 'VARCHAR(50) DEFAULT NULL',
                'phone_country_code' => 'VARCHAR(12) DEFAULT NULL',
                'contact_person' => 'VARCHAR(255) DEFAULT NULL',
                'contact_designation' => 'VARCHAR(255) DEFAULT NULL',
            ],
            'ptw_applications' => [
                'terminal_approval_required' => 'TINYINT(1) NOT NULL DEFAULT 1',
            ],
            'tenders' => [
                'procurement_manager_action' => 'VARCHAR(50) DEFAULT NULL',
                'procurement_manager_payload' => 'LONGTEXT DEFAULT NULL',
                'tender_fee' => 'DECIMAL(15,3) DEFAULT NULL',
                'evaluation_method' => "ENUM('separate','combined') NOT NULL DEFAULT 'separate'",
                'technical_weight' => 'TINYINT(3) UNSIGNED NOT NULL DEFAULT 70',
                'commercial_weight' => 'TINYINT(3) UNSIGNED NOT NULL DEFAULT 30',
                'site_visit_location' => 'VARCHAR(255) DEFAULT NULL',
                'site_visit_instructions' => 'TEXT DEFAULT NULL',
                'site_visit_mandatory' => 'TINYINT(1) NOT NULL DEFAULT 0',
            ],
            'tender_target_specialties' => [
                'vendor_grade_id' => 'BIGINT(20) UNSIGNED DEFAULT NULL',
            ],
            'tender_communications' => [
                'clarification_scope' => "VARCHAR(50) NOT NULL DEFAULT 'general'",
                'tender_bid_id' => 'BIGINT(20) UNSIGNED DEFAULT NULL',
                'internal_audience' => 'VARCHAR(50) DEFAULT NULL',
            ],
            'tender_evaluations' => [
                'review_started_at' => 'DATETIME DEFAULT NULL',
                'review_duration_seconds' => 'INT(11) DEFAULT NULL',
                'deadline_at' => 'DATETIME DEFAULT NULL',
                'submitted_after_deadline' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'late_review_status' => "ENUM('pending','accepted','rejected') DEFAULT NULL",
                'late_reviewed_by' => 'BIGINT(20) UNSIGNED DEFAULT NULL',
                'late_reviewed_at' => 'DATETIME DEFAULT NULL',
                'late_review_comment' => 'TEXT DEFAULT NULL',
            ],
        ];

        foreach ($columns as $table => $definitions) {
            $this->requireTable($table);
            foreach ($definitions as $column => $definition) {
                $this->addColumnIfMissing($table, $column, $definition);
            }
        }
    }

    private function normalizeCompatibilityDefinitions(): void
    {
        $this->requireColumn('ptw_requirement_responses', 'ptw_requirement_definition_id');
        $this->requireColumn('ptw_attachments', 'ptw_requirement_id');
        $this->requireColumn('tender_invited_vendors', 'invite_status');
        $this->requireColumn('tender_communications', 'type');

        $responses = $this->db->prefixTable('ptw_requirement_responses');
        $attachments = $this->db->prefixTable('ptw_attachments');
        $invites = $this->db->prefixTable('tender_invited_vendors');
        $communications = $this->db->prefixTable('tender_communications');
        $tenders = $this->db->prefixTable('tenders');

        $this->db->query(
            "ALTER TABLE `{$responses}` MODIFY `ptw_requirement_definition_id` BIGINT(20) UNSIGNED NULL"
        );
        $this->db->query(
            "ALTER TABLE `{$attachments}` MODIFY `ptw_requirement_id` BIGINT(20) UNSIGNED NULL"
        );
        $this->db->query(
            "ALTER TABLE `{$invites}` MODIFY `invite_status`
             ENUM('sent','delivered','opened','declined','pending_approval','approved','rejected')
             NOT NULL DEFAULT 'sent'"
        );
        $this->db->query(
            "ALTER TABLE `{$communications}` MODIFY `type` VARCHAR(50) NULL DEFAULT NULL"
        );
        $this->db->query(
            "UPDATE `{$tenders}` SET `evaluation_method`='separate'
             WHERE `evaluation_method` IS NULL OR `evaluation_method` NOT IN ('separate','combined')"
        );
        $this->db->query(
            "ALTER TABLE `{$tenders}` MODIFY `evaluation_method`
             ENUM('separate','combined') NOT NULL DEFAULT 'separate'"
        );
    }

    private function createOwnedTables(): void
    {
        $blocked = $this->db->prefixTable('gate_pass_blocked_visitors');
        $blockedLogs = $this->db->prefixTable('gate_pass_blocked_visitor_logs');
        $targetVendors = $this->db->prefixTable('tender_target_vendors');
        $feePayments = $this->db->prefixTable('tender_fee_payments');
        $workflow = $this->db->prefixTable('tender_workflow_history');
        $communicationAttachments = $this->db->prefixTable('tender_communication_attachments');
        $evaluationAttachments = $this->db->prefixTable('tender_evaluation_attachments');
        $bidPrices = $this->db->prefixTable('tender_bid_item_prices');
        $rfqDetails = $this->db->prefixTable('tender_rfq_details');
        $rfqItems = $this->db->prefixTable('tender_rfq_items');

        $statements = [
            "CREATE TABLE IF NOT EXISTS `{$blocked}` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_number` VARCHAR(120) NOT NULL,
                `normalized_id_number` VARCHAR(120) NOT NULL,
                `id_type` VARCHAR(80) DEFAULT NULL,
                `visitor_name` VARCHAR(255) DEFAULT NULL,
                `nationality` VARCHAR(120) DEFAULT NULL,
                `visitor_company` VARCHAR(255) DEFAULT NULL,
                `source_request_id` BIGINT UNSIGNED DEFAULT NULL,
                `source_visitor_id` BIGINT UNSIGNED DEFAULT NULL,
                `reason` TEXT DEFAULT NULL,
                `status` VARCHAR(20) NOT NULL DEFAULT 'blocked',
                `blocked_by` BIGINT UNSIGNED DEFAULT NULL,
                `blocked_at` DATETIME DEFAULT NULL,
                `unblocked_by` BIGINT UNSIGNED DEFAULT NULL,
                `unblocked_at` DATETIME DEFAULT NULL,
                `unblock_reason` TEXT DEFAULT NULL,
                `last_action_by` BIGINT UNSIGNED DEFAULT NULL,
                `last_action_at` DATETIME DEFAULT NULL,
                `created_at` DATETIME DEFAULT NULL,
                `updated_at` DATETIME DEFAULT NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `{$blockedLogs}` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `blocked_visitor_id` BIGINT UNSIGNED NOT NULL,
                `action` VARCHAR(30) NOT NULL,
                `reason` TEXT DEFAULT NULL,
                `action_by` BIGINT UNSIGNED DEFAULT NULL,
                `action_at` DATETIME DEFAULT NULL,
                `ip_address` VARCHAR(80) DEFAULT NULL,
                `user_agent` VARCHAR(500) DEFAULT NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `{$targetVendors}` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `tender_id` BIGINT UNSIGNED NOT NULL,
                `vendor_id` BIGINT UNSIGNED NOT NULL,
                `created_by` BIGINT UNSIGNED DEFAULT NULL,
                `created_at` DATETIME DEFAULT NULL,
                `deleted` INT(11) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `{$feePayments}` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `tender_id` BIGINT UNSIGNED NOT NULL,
                `vendor_id` BIGINT UNSIGNED NOT NULL,
                `amount` DECIMAL(15,3) NOT NULL DEFAULT 0.000,
                `currency` VARCHAR(10) NOT NULL DEFAULT 'OMR',
                `status` VARCHAR(50) NOT NULL DEFAULT 'paid',
                `payment_reference` VARCHAR(100) DEFAULT NULL,
                `paid_at` DATETIME DEFAULT NULL,
                `created_by` BIGINT UNSIGNED DEFAULT NULL,
                `created_at` DATETIME DEFAULT NULL,
                `updated_at` DATETIME DEFAULT NULL,
                `deleted` INT(11) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `{$workflow}` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `tender_id` BIGINT UNSIGNED NOT NULL,
                `action_type` VARCHAR(50) NOT NULL DEFAULT 'stage_override',
                `from_status` VARCHAR(50) DEFAULT NULL,
                `to_status` VARCHAR(50) DEFAULT NULL,
                `from_stage` VARCHAR(50) DEFAULT NULL,
                `to_stage` VARCHAR(50) DEFAULT NULL,
                `open_until` DATETIME DEFAULT NULL,
                `reason` TEXT DEFAULT NULL,
                `details` TEXT DEFAULT NULL,
                `created_by` BIGINT UNSIGNED DEFAULT NULL,
                `created_at` DATETIME DEFAULT NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `{$communicationAttachments}` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `communication_id` BIGINT UNSIGNED NOT NULL,
                `tender_id` BIGINT UNSIGNED NOT NULL,
                `vendor_id` BIGINT UNSIGNED DEFAULT NULL,
                `disk` VARCHAR(50) NOT NULL DEFAULT 'local',
                `path` VARCHAR(500) NOT NULL,
                `original_name` VARCHAR(255) DEFAULT NULL,
                `mime_type` VARCHAR(255) DEFAULT NULL,
                `size_bytes` BIGINT UNSIGNED DEFAULT NULL,
                `uploaded_by` BIGINT UNSIGNED DEFAULT NULL,
                `created_at` DATETIME DEFAULT NULL,
                `deleted` INT(11) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `{$evaluationAttachments}` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `tender_evaluation_id` BIGINT UNSIGNED NOT NULL,
                `tender_id` BIGINT UNSIGNED NOT NULL,
                `tender_bid_id` BIGINT UNSIGNED NOT NULL,
                `disk` VARCHAR(50) NOT NULL DEFAULT 'local',
                `path` VARCHAR(500) NOT NULL,
                `original_name` VARCHAR(255) DEFAULT NULL,
                `mime_type` VARCHAR(255) DEFAULT NULL,
                `size_bytes` BIGINT UNSIGNED DEFAULT NULL,
                `uploaded_by` BIGINT UNSIGNED DEFAULT NULL,
                `created_at` DATETIME DEFAULT NULL,
                `deleted` INT(11) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `{$bidPrices}` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `tender_bid_id` BIGINT UNSIGNED NOT NULL,
                `tender_id` BIGINT UNSIGNED NOT NULL,
                `vendor_id` BIGINT UNSIGNED NOT NULL,
                `tender_rfq_item_id` BIGINT UNSIGNED NOT NULL,
                `qty` DECIMAL(18,3) DEFAULT NULL,
                `unit_price` DECIMAL(18,3) NOT NULL,
                `line_total` DECIMAL(18,3) DEFAULT NULL,
                `created_at` DATETIME DEFAULT NULL,
                `updated_at` DATETIME DEFAULT NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `{$rfqDetails}` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `tender_id` BIGINT UNSIGNED NOT NULL,
                `rfq_no` VARCHAR(100) DEFAULT NULL,
                `rfq_date` DATE DEFAULT NULL,
                `pr_no` VARCHAR(100) DEFAULT NULL,
                `delivery_location` VARCHAR(255) DEFAULT NULL,
                `incoterm` VARCHAR(100) DEFAULT NULL,
                `material_required_on` DATE DEFAULT NULL,
                `terms_reference` VARCHAR(255) DEFAULT NULL,
                `notes` TEXT DEFAULT NULL,
                `enclosures` TEXT DEFAULT NULL,
                `created_at` DATETIME DEFAULT NULL,
                `updated_at` DATETIME DEFAULT NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `{$rfqItems}` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `tender_id` BIGINT UNSIGNED NOT NULL,
                `sr_no` VARCHAR(30) DEFAULT NULL,
                `description` TEXT DEFAULT NULL,
                `uom` VARCHAR(50) DEFAULT NULL,
                `qty` DECIMAL(18,3) DEFAULT NULL,
                `unit_price` DECIMAL(18,3) DEFAULT NULL,
                `brand` VARCHAR(150) DEFAULT NULL,
                `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
                `created_at` DATETIME DEFAULT NULL,
                `updated_at` DATETIME DEFAULT NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];

        foreach ($statements as $statement) {
            $this->db->query($statement);
        }
    }

    private function ensureOwnedIndexes(): void
    {
        $indexes = [
            ['gate_pass_blocked_visitors', 'gp_blocked_visitors_norm_unique', ['normalized_id_number'], true],
            ['gate_pass_blocked_visitors', 'gp_blocked_visitors_status_idx', ['status'], false],
            ['gate_pass_blocked_visitors', 'gp_blocked_visitors_last_action_idx', ['last_action_at'], false],
            ['gate_pass_blocked_visitor_logs', 'gp_blocked_visitor_logs_parent_idx', ['blocked_visitor_id'], false],
            ['gate_pass_blocked_visitor_logs', 'gp_blocked_visitor_logs_action_idx', ['action_at'], false],
            ['tender_target_vendors', 'idx_tender_target_vendors_tender', ['tender_id', 'deleted'], false],
            ['tender_target_vendors', 'idx_tender_target_vendors_vendor', ['vendor_id', 'deleted'], false],
            ['tender_target_specialties', 'idx_tender_target_specialties_vendor_grade', ['vendor_grade_id'], false],
            ['tender_fee_payments', 'idx_tender_fee_payments_tender_vendor', ['tender_id', 'vendor_id', 'deleted'], false],
            ['tender_fee_payments', 'idx_tender_fee_payments_status', ['status', 'deleted'], false],
            ['tender_workflow_history', 'idx_tender_workflow_history_tender', ['tender_id'], false],
            ['tender_workflow_history', 'idx_tender_workflow_history_action', ['action_type'], false],
            ['tender_communication_attachments', 'idx_tender_comm_att_communication', ['communication_id', 'deleted'], false],
            ['tender_communication_attachments', 'idx_tender_comm_att_tender_vendor', ['tender_id', 'vendor_id', 'deleted'], false],
            ['tender_communications', 'idx_tender_communications_scope', ['clarification_scope', 'tender_id', 'deleted'], false],
            ['tender_communications', 'idx_tender_communications_type_scope', ['type', 'clarification_scope', 'tender_id', 'vendor_id', 'deleted'], false],
            ['tender_communications', 'idx_tender_communications_bid_audience', ['tender_bid_id', 'internal_audience', 'deleted'], false],
            ['tender_evaluation_attachments', 'idx_tender_eval_att_eval', ['tender_evaluation_id', 'deleted'], false],
            ['tender_evaluation_attachments', 'idx_tender_eval_att_tender', ['tender_id', 'tender_bid_id', 'deleted'], false],
            ['tender_evaluations', 'idx_tender_eval_late_status', ['tender_id', 'type', 'submitted_after_deadline', 'late_review_status', 'deleted'], false],
            ['tender_bid_item_prices', 'idx_tender_bid_item_prices_bid', ['tender_bid_id'], false],
            ['tender_bid_item_prices', 'idx_tender_bid_item_prices_tender_vendor', ['tender_id', 'vendor_id'], false],
            ['tender_bid_item_prices', 'idx_tender_bid_item_prices_rfq_item', ['tender_rfq_item_id'], false],
            ['tender_rfq_details', 'tender_rfq_details_tender_unique', ['tender_id'], true],
            ['tender_rfq_details', 'tender_rfq_details_tender_id_idx', ['tender_id'], false],
            ['tender_rfq_items', 'tender_rfq_items_tender_id_idx', ['tender_id'], false],
        ];

        foreach ($indexes as [$table, $name, $columns, $unique]) {
            $this->ensureIndex($table, $name, $columns, $unique);
        }
    }

    private function ensureRfqForeignKeys(): void
    {
        $this->ensureForeignKey('tender_rfq_details', 'tender_id', 'tenders', 'id');
        $this->ensureForeignKey('tender_rfq_items', 'tender_id', 'tenders', 'id');
    }

    private function migrateCompatibilityData(): void
    {
        $vendors = $this->db->prefixTable('vendors');
        $tenders = $this->db->prefixTable('tenders');
        $requests = $this->db->prefixTable('tender_requests');
        $communications = $this->db->prefixTable('tender_communications');

        $this->db->query(
            "UPDATE `{$vendors}` SET `status`='new'
             WHERE `deleted`=0 AND (`status`='' OR `status` IS NULL)"
        );

        if ($this->hasColumns('tender_requests', ['evaluation_method', 'technical_weight', 'commercial_weight'])) {
            $this->db->query(
                "UPDATE `{$tenders}` t
                 INNER JOIN `{$requests}` req ON req.id=t.tender_request_id AND req.deleted=0
                 SET t.evaluation_method=COALESCE(req.evaluation_method, t.evaluation_method),
                     t.technical_weight=COALESCE(req.technical_weight, t.technical_weight),
                     t.commercial_weight=COALESCE(req.commercial_weight, t.commercial_weight)
                 WHERE t.deleted=0"
            );
        }

        $this->db->query(
            "UPDATE `{$communications}`
             SET `type`=CONCAT(COALESCE(NULLIF(`internal_audience`, ''), `clarification_scope`), '_clarification_request')
             WHERE `deleted`=0 AND (`type` IS NULL OR `type`='')
               AND COALESCE(NULLIF(`internal_audience`, ''), `clarification_scope`) IN ('technical','commercial')
               AND (`parent_id` IS NULL OR `parent_id`=0)"
        );
        $this->db->query(
            "UPDATE `{$communications}` child
             INNER JOIN `{$communications}` root ON root.id=child.parent_id AND root.deleted=0
             SET child.`type`=CONCAT(COALESCE(NULLIF(root.`internal_audience`, ''), root.`clarification_scope`), '_clarification_response')
             WHERE child.deleted=0 AND (child.`type` IS NULL OR child.`type`='')
               AND COALESCE(NULLIF(root.`internal_audience`, ''), root.`clarification_scope`) IN ('technical','commercial')"
        );
        $this->db->query(
            "UPDATE `{$communications}` SET `type`='clarification' WHERE `type` IS NULL OR `type`=''"
        );
        $this->db->query(
            "ALTER TABLE `{$communications}` MODIFY `type` VARCHAR(50) NOT NULL DEFAULT 'clarification'"
        );
    }

    private function addColumnIfMissing(string $table, string $column, string $definition): void
    {
        $physical = $this->db->prefixTable($table);
        if (!$this->db->fieldExists($column, $physical)) {
            $this->db->query("ALTER TABLE `{$physical}` ADD COLUMN `{$column}` {$definition}");
        }
    }

    private function requireTable(string $table): void
    {
        if (!$this->db->tableExists($table)) {
            throw new RuntimeException('Required base table is missing: ' . $this->db->prefixTable($table));
        }
    }

    private function requireColumn(string $table, string $column): void
    {
        $this->requireTable($table);
        if (!$this->db->fieldExists($column, $this->db->prefixTable($table))) {
            throw new RuntimeException('Required base field is missing: ' . $this->db->prefixTable($table) . '.' . $column);
        }
    }

    private function hasColumns(string $table, array $columns): bool
    {
        if (!$this->db->tableExists($table)) {
            return false;
        }

        $physical = $this->db->prefixTable($table);
        foreach ($columns as $column) {
            if (!$this->db->fieldExists($column, $physical)) {
                return false;
            }
        }

        return true;
    }

    private function ensureIndex(string $table, string $name, array $columns, bool $unique): void
    {
        $physical = $this->db->prefixTable($table);
        $exists = $this->db->query(
            'SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND INDEX_NAME=? LIMIT 1',
            [$this->db->getDatabase(), $physical, $name]
        )->getRow();

        if (!$exists) {
            $quoted = '`' . implode('`,`', $columns) . '`';
            $kind = $unique ? 'UNIQUE INDEX' : 'INDEX';
            $this->db->query("ALTER TABLE `{$physical}` ADD {$kind} `{$name}` ({$quoted})");
        }
    }

    private function ensureForeignKey(
        string $table,
        string $column,
        string $referencedTable,
        string $referencedColumn
    ): void {
        $physical = $this->db->prefixTable($table);
        $referencedPhysical = $this->db->prefixTable($referencedTable);
        $exists = $this->db->query(
            'SELECT 1 FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?
               AND REFERENCED_TABLE_NAME=? AND REFERENCED_COLUMN_NAME=? LIMIT 1',
            [$this->db->getDatabase(), $physical, $column, $referencedPhysical, $referencedColumn]
        )->getRow();

        if (!$exists) {
            $constraint = substr(preg_replace('/[^a-zA-Z0-9_]/', '_', $physical . '_tender_fk'), 0, 64);
            $this->db->query(
                "ALTER TABLE `{$physical}` ADD CONSTRAINT `{$constraint}`
                 FOREIGN KEY (`{$column}`) REFERENCES `{$referencedPhysical}` (`{$referencedColumn}`)
                 ON DELETE CASCADE"
            );
        }
    }
}
