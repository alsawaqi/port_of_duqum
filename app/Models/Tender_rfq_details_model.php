<?php

namespace App\Models;

use App\Libraries\Runtime_schema_guard;

class Tender_rfq_details_model extends Crud_model
{
    protected $table = null;
    private static bool $schema_checked = false;

    public function __construct()
    {
        $this->table = "tender_rfq_details";
        parent::__construct($this->table);
        $this->ensure_schema();
    }

    public function ensure_schema(): void
    {
        if (self::$schema_checked) {
            return;
        }

        Runtime_schema_guard::requireTablesAndColumns($this->db, [
            "tender_rfq_details" => [
                "id", "tender_id", "rfq_no", "rfq_date", "pr_no", "delivery_location",
                "incoterm", "material_required_on", "terms_reference", "notes", "enclosures",
                "created_at", "updated_at", "deleted",
            ],
        ], "tender RFQ details");

        self::$schema_checked = true;
    }

    public function get_by_tender(int $tender_id)
    {
        $table = $this->db->prefixTable("tender_rfq_details");

        return $this->db->query(
            "SELECT *
             FROM $table
             WHERE deleted = 0
               AND tender_id = ?
             LIMIT 1",
            [$tender_id]
        )->getRow();
    }

    public function sync_detail(int $tender_id, array $data): void
    {
        $existing = $this->get_by_tender($tender_id);
        $now = date("Y-m-d H:i:s");

        $payload = [
            "tender_id" => $tender_id,
            // These legacy fields are no longer collected by the tender form.
            // Preserve historical values when a tender is edited.
            "rfq_no" => array_key_exists("rfq_no", $data) ? $data["rfq_no"] : ($existing->rfq_no ?? null),
            "rfq_date" => array_key_exists("rfq_date", $data) ? $data["rfq_date"] : ($existing->rfq_date ?? null),
            "pr_no" => $data["pr_no"] ?? null,
            "delivery_location" => $data["delivery_location"] ?? null,
            "incoterm" => $data["incoterm"] ?? null,
            "material_required_on" => $data["material_required_on"] ?? null,
            "terms_reference" => $data["terms_reference"] ?? null,
            "notes" => $data["notes"] ?? null,
            "enclosures" => $data["enclosures"] ?? null,
            "updated_at" => $now,
            "deleted" => 0,
        ];

        if (empty($existing->id)) {
            $payload["created_at"] = $now;
        }

        $this->ci_save(clean_data($payload), (int) ($existing->id ?? 0));
    }
}
