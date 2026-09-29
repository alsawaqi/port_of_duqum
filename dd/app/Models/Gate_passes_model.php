<?php

namespace App\Models;

class Gate_passes_model extends Crud_model
{
    protected $table = null;

    public function __construct()
    {
        $this->table = "gate_passes";
        parent::__construct($this->table);
    }

    /**
     * Get gate pass by request id (for portal details / QR).
     */
    public function get_by_request_id($gate_pass_request_id)
    {
        $t = $this->db->prefixTable("gate_passes");
        $row = $this->db->query(
            "SELECT * FROM $t WHERE gate_pass_request_id=? AND deleted=0 ORDER BY id ASC LIMIT 1",
            [(int)$gate_pass_request_id]
        )->getRow();
        return $row;
    }

    /**
     * Get every issued pass for a request, including the assigned visitor when available.
     */
    public function get_all_by_request_id($gate_pass_request_id)
    {
        $t = $this->db->prefixTable("gate_passes");
        $visitors = $this->db->prefixTable("gate_pass_request_visitors");

        return $this->db->query(
            "SELECT $t.*,
                    $visitors.full_name AS visitor_full_name,
                    $visitors.id_number AS visitor_id_number,
                    $visitors.nationality AS visitor_nationality
             FROM $t
             LEFT JOIN $visitors ON $visitors.id = $t.gate_pass_request_visitor_id AND $visitors.deleted=0
             WHERE $t.gate_pass_request_id=? AND $t.deleted=0
             ORDER BY COALESCE($visitors.is_primary, 0) DESC, $visitors.full_name ASC, $t.id ASC",
            [(int)$gate_pass_request_id]
        );
    }

    public function get_by_request_and_visitor(int $gate_pass_request_id, ?int $gate_pass_request_visitor_id)
    {
        $t = $this->db->prefixTable("gate_passes");
        if ($gate_pass_request_visitor_id) {
            return $this->db->query(
                "SELECT * FROM $t WHERE gate_pass_request_id=? AND gate_pass_request_visitor_id=? AND deleted=0 LIMIT 1",
                [$gate_pass_request_id, $gate_pass_request_visitor_id]
            )->getRow();
        }

        return $this->db->query(
            "SELECT * FROM $t WHERE gate_pass_request_id=? AND gate_pass_request_visitor_id IS NULL AND deleted=0 LIMIT 1",
            [$gate_pass_request_id]
        )->getRow();
    }

    public function get_by_id_for_request(int $id, int $gate_pass_request_id)
    {
        $t = $this->db->prefixTable("gate_passes");

        return $this->db->query(
            "SELECT * FROM $t WHERE id=? AND gate_pass_request_id=? AND deleted=0 LIMIT 1",
            [$id, $gate_pass_request_id]
        )->getRow();
    }



    // app/Models/Gate_passes_model.php

 




    /**
     * Generate a unique qr_token for a new gate pass.
     */
    public static function generate_qr_token(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Generate gate_pass_no (e.g. GP-2025-000123 for request id 123).
     */
    public function generate_gate_pass_no($request_id, ?int $visitor_id = null): string
    {
        $base = "GP-" . date("Y") . "-" . str_pad((string)(int)$request_id, 6, "0", STR_PAD_LEFT);
        if ($visitor_id) {
            return $base . "-V" . str_pad((string)$visitor_id, 6, "0", STR_PAD_LEFT);
        }

        return $base;
    }


    public function get_by_qr_token(string $qr_token)
{
    $t = $this->db->prefixTable("gate_passes");
    return $this->db->query(
        "SELECT * FROM $t WHERE qr_token=? AND deleted=0 LIMIT 1",
        [$qr_token]
    )->getRow();
}



public function update_meta(int $id, array $patch): bool
{
    $row = $this->get_one($id);
    if (!$row) {
        return false;
    }

    $meta = [];
    if (!empty($row->meta)) {
        $decoded = json_decode($row->meta, true);
        if (is_array($decoded)) {
            $meta = $decoded;
        }
    }

    // shallow merge (enough for our usage)
    foreach ($patch as $k => $v) {
        $meta[$k] = $v;
    }

    $data = [
        "meta" => json_encode($meta),
        "updated_at" => get_current_utc_time(),
    ];

    return (bool) $this->ci_save($data, $id);
}

    

    
}
