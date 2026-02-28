<?php

namespace App\Models;

class Vendor_documents_model extends Crud_model
{
    protected $table = null;


     protected $allowedFields = [
        "vendor_id",
        "vendor_document_type_id",
        "disk",
        "path",
        "original_name",
        "mime_type",
        "size_bytes",
        "issued_at",
        "expires_at",
        "uploaded_by",
        "deleted",
        "created_at",
        "updated_at",
    ];

    function __construct()
    {
        $this->table = "vendor_documents"; // no "pod_" here
        parent::__construct($this->table);
    }

    function get_details($options = array())
    {
        // IMPORTANT: Crud_model already prefixed $this->table
        $docs_table  = $this->table; // <- already like "pod_vendor_documents"
        $types_table = $this->db->prefixTable("vendor_document_types");
        $users_table = $this->db->prefixTable("users");

        $where = "WHERE $docs_table.deleted=0";

        $id = get_array_value($options, "id");
        $vendor_id = get_array_value($options, "vendor_id");

        if ($id) {
            $where .= " AND $docs_table.id=" . (int)$id;
        }
        if ($vendor_id) {
            $where .= " AND $docs_table.vendor_id=" . (int)$vendor_id;
        }

        return $this->db->query("
            SELECT
                $docs_table.*,
                $types_table.name AS document_type_name,
                TRIM(CONCAT(COALESCE($users_table.first_name,''),' ',COALESCE($users_table.last_name,''))) AS uploaded_by_name
            FROM $docs_table
            LEFT JOIN $types_table ON $types_table.id = $docs_table.vendor_document_type_id
            LEFT JOIN $users_table ON $users_table.id = $docs_table.uploaded_by
            $where
            ORDER BY $docs_table.id DESC
        ");
    }
}
