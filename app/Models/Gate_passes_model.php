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
            "SELECT * FROM $t WHERE gate_pass_request_id=? AND deleted=0 LIMIT 1",
            [(int)$gate_pass_request_id]
        )->getRow();
        return $row;
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
    public function generate_gate_pass_no($request_id): string
    {
        return "GP-" . date("Y") . "-" . str_pad((string)(int)$request_id, 6, "0", STR_PAD_LEFT);
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
