<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class Ptw_applicant_company_assignments extends Migration
{
    public function up()
    {
        $assignments = $this->db->prefixTable("ptw_applicant_users");
        $applications = $this->db->prefixTable("ptw_applications");

        $this->db->query(
            "CREATE TABLE IF NOT EXISTS {$assignments} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT NOT NULL,
                company_id BIGINT UNSIGNED NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                deleted TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_ptw_applicant_user_company (user_id, company_id),
                KEY idx_ptw_applicant_active_user (user_id, status, deleted),
                KEY idx_ptw_applicant_company (company_id, status, deleted)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        // Preserve access to existing, already-owned applications while
        // requiring administrators to review the resulting assignments.
        if ($this->db->tableExists("ptw_applications")
            && $this->db->fieldExists("company_id", "ptw_applications")) {
            $this->db->query(
                "INSERT IGNORE INTO {$assignments}
                    (user_id, company_id, status, deleted, created_at)
                 SELECT DISTINCT applicant_user_id, company_id, 'active', 0, UTC_TIMESTAMP()
                 FROM {$applications}
                 WHERE deleted=0
                   AND applicant_user_id IS NOT NULL
                   AND applicant_user_id > 0
                   AND company_id IS NOT NULL
                   AND company_id > 0"
            );
        }
    }

    public function down()
    {
        $this->forge->dropTable("ptw_applicant_users", true);
    }
}
