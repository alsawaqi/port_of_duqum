<?php

namespace App\Models;

class Ptw_requirement_responses_model extends Crud_model
{
    protected $table = null;

    public function __construct()
    {
        $this->table = "ptw_requirement_responses";
        parent::__construct($this->table);
        $this->ensure_virtual_other_support();
    }

    private function ensure_virtual_other_support(): void
    {
        try {
            $table = $this->db->prefixTable($this->table);
            $row = $this->db->query(
                "SELECT IS_NULLABLE
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA=?
                   AND TABLE_NAME=?
                   AND COLUMN_NAME='ptw_requirement_definition_id'
                 LIMIT 1",
                [$this->db->getDatabase(), $table]
            )->getRow();

            if ($row && strtoupper((string)$row->IS_NULLABLE) !== "YES") {
                $this->db->query("ALTER TABLE `$table` MODIFY `ptw_requirement_definition_id` BIGINT(20) UNSIGNED NULL");
            }
        } catch (\Throwable $e) {
            log_message("error", "Failed to ensure PTW virtual other response support: " . $e->getMessage());
        }
    }

    public function get_by_application($ptw_application_id)
    {
        $table = $this->db->prefixTable("ptw_requirement_responses");
        $sql = "SELECT * FROM $table WHERE deleted=0 AND ptw_application_id=" . (int) $ptw_application_id . " ORDER BY id ASC";
        return $this->db->query($sql);
    }

    public function get_one_by_app_and_def($ptw_application_id, $definition_id)
    {
        $table = $this->db->prefixTable("ptw_requirement_responses");
        $sql = "SELECT * FROM $table 
                WHERE deleted=0 
                  AND ptw_application_id=" . (int) $ptw_application_id . "
                  AND ptw_requirement_definition_id=" . (int) $definition_id . "
                LIMIT 1";
        return $this->db->query($sql)->getRow();
    }
}
