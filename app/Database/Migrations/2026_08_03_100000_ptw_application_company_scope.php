<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Persist PTW company identity independently from its display-name snapshot. */
class Ptw_application_company_scope extends Migration
{
    private const COMPANY_INDEX = 'idx_ptw_applications_company_id';

    public function up()
    {
        if (!$this->db->tableExists('ptw_applications') || !$this->db->tableExists('companies')) {
            return;
        }

        if (!$this->db->fieldExists('company_id', 'ptw_applications')) {
            $this->forge->addColumn('ptw_applications', [
                'company_id' => [
                    'type' => 'BIGINT',
                    'constraint' => 20,
                    'unsigned' => true,
                    'null' => true,
                    'after' => 'company_name',
                ],
            ]);
        }

        $applications = $this->db->prefixTable('ptw_applications');
        if (!$this->indexExists($applications, self::COMPANY_INDEX)) {
            $this->db->query(
                "ALTER TABLE `{$applications}` ADD INDEX `" . self::COMPANY_INDEX . "` (`company_id`)"
            );
        }

        $this->backfillExactUniqueCompanyNames($applications);
    }

    public function down()
    {
        if (!$this->db->tableExists('ptw_applications')
            || !$this->db->fieldExists('company_id', 'ptw_applications')
        ) {
            return;
        }

        $applications = $this->db->prefixTable('ptw_applications');
        if ($this->indexExists($applications, self::COMPANY_INDEX)) {
            $this->db->query(
                "ALTER TABLE `{$applications}` DROP INDEX `" . self::COMPANY_INDEX . "`"
            );
        }
        $this->forge->dropColumn('ptw_applications', 'company_id');
    }

    private function backfillExactUniqueCompanyNames(string $applications): void
    {
        $companies = $this->db->prefixTable('companies');
        $this->db->query(
            "UPDATE `{$applications}` AS applications
             INNER JOIN (
                 SELECT MIN(id) AS company_id, MIN(name) AS company_name
                 FROM `{$companies}`
                 WHERE deleted=0 AND name IS NOT NULL AND name <> ''
                 GROUP BY BINARY name
                 HAVING COUNT(*) = 1
             ) AS unique_companies
                 ON BINARY unique_companies.company_name = BINARY applications.company_name
             SET applications.company_id = unique_companies.company_id
             WHERE applications.company_id IS NULL
               AND applications.company_name IS NOT NULL
               AND applications.company_name <> ''"
        );
    }

    private function indexExists(string $table, string $index): bool
    {
        return (bool)$this->db->query(
            'SELECT 1 FROM information_schema.STATISTICS'
            . ' WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND INDEX_NAME=? LIMIT 1',
            [$this->db->database, $table, $index]
        )->getRow();
    }
}
