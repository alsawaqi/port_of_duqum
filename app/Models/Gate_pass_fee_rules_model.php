<?php

namespace App\Models;

class Gate_pass_fee_rules_model extends Crud_model
{
    protected $table = null;

    function __construct()
    {
        $this->table = "gate_pass_fee_rules"; // => pod_gate_pass_fee_rules
        parent::__construct($this->table);
    }

    function get_details($options = array())
    {
        $t = $this->db->prefixTable("gate_pass_fee_rules");
        $where = "WHERE $t.deleted=0";

        $id = get_array_value($options, "id");
        if ($id) {
            $where .= " AND $t.id=" . (int)$id;
        }

        $sql = "SELECT $t.*
                FROM $t
                $where
                ORDER BY $t.min_days ASC, $t.max_days ASC, $t.id DESC";

        return $this->db->query($sql);
    }

    /**
     * Finds the best rule for given days.
     * - rule must satisfy: min_days <= days <= max_days (or max_days is NULL)
     * - prefers the narrowest range (exact match wins)
     */
    function find_rule_by_days(int $days, string $currency = "OMR")
    {
        if ($days < 1) {
            return null;
        }

        $t = $this->db->prefixTable("gate_pass_fee_rules");

        $sql = "SELECT $t.*
                FROM $t
                WHERE $t.deleted=0
                  AND $t.is_active=1
                  AND $t.currency=" . $this->db->escape($currency) . "
                  AND $t.min_days <= " . (int)$days . "
                  AND ($t.max_days IS NULL OR $t.max_days >= " . (int)$days . ")
                ORDER BY
                  -- closed ranges first (more precise than open-ended)
                  (CASE WHEN $t.max_days IS NULL THEN 1 ELSE 0 END) ASC,
                  -- narrowest range first
                  (CASE WHEN $t.max_days IS NULL THEN 999999 ELSE ($t.max_days - $t.min_days) END) ASC,
                  -- prefer higher min_days if tie
                  $t.min_days DESC,
                  $t.id DESC
                LIMIT 1";

        return $this->db->query($sql)->getRow();
    }

    function calculate_fee($rule, int $days): float
    {
        if (!$rule || $days < 1) {
            return 0.0;
        }

        $amount = (float)$rule->amount;
        $type   = (string)($rule->rate_type ?? "flat");

        if ($type === "daily") {
            return $amount * $days;
        }

        if ($type === "weekly") {
            return $amount * (int)ceil($days / 7);
        }

        if ($type === "monthly") {
            return $amount * (int)ceil($days / 30);
        }

        return $amount; // flat
    }

    /**
     * Optional: prevent overlaps (same currency).
     * Checks if another active rule overlaps [min_days, max_days].
     */
    function has_overlap($min_days, $max_days, $currency, $exclude_id = null): bool
    {
        $t = $this->db->prefixTable("gate_pass_fee_rules");
        $min_days = (int)$min_days;
        $max_days = $max_days !== null ? (int)$max_days : null;

        $where = "WHERE $t.deleted=0 AND $t.currency=" . $this->db->escape($currency);

        if ($exclude_id) {
            $where .= " AND $t.id!=" . (int)$exclude_id;
        }

        // Overlap condition
        // existing: [min, max or INF]
        // new:      [min_days, max_days or INF]
        if ($max_days === null) {
            // new is open-ended: overlaps if existing max is null OR existing max >= new min
            $where .= " AND ( $t.max_days IS NULL OR $t.max_days >= $min_days )";
        } else {
            // new is closed: overlaps if existing min <= new max AND (existing max is null OR existing max >= new min)
            $where .= " AND ( $t.min_days <= $max_days AND ( $t.max_days IS NULL OR $t.max_days >= $min_days ) )";
        }

        $row = $this->db->query("SELECT $t.id FROM $t $where LIMIT 1")->getRow();
        return (bool)$row;
    }
}
