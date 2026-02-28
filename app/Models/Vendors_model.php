<?php

namespace App\Models;

class Vendors_model extends Crud_model
{
    protected $table = null;

    function __construct()
    {
        $this->table = "vendors";
        parent::__construct($this->table);
    }

    function get_details($options = array())
    {
        $vendors_table = $this->db->prefixTable("vendors");
        $groups_table  = $this->db->prefixTable("vendor_groups");
        $country_table = $this->db->prefixTable("country");
        $regions_table = $this->db->prefixTable("regions");
        $cities_table  = $this->db->prefixTable("cities");

        $where = "WHERE $vendors_table.deleted=0";

        $id = get_array_value($options, "id");
        if ($id) {
            $where .= " AND $vendors_table.id=" . (int)$id;
        }

        $vendor_group_id = get_array_value($options, "vendor_group_id");
        if ($vendor_group_id) {
            $where .= " AND $vendors_table.vendor_group_id=" . (int)$vendor_group_id;
        }

        return $this->db->query("
            SELECT
                $vendors_table.*,
                $groups_table.name AS vendor_group_name,
                $groups_table.code AS vendor_group_code,
                $country_table.name AS country_name,
                $regions_table.name AS region_name,
                $cities_table.name  AS city_name
            FROM $vendors_table
            LEFT JOIN $groups_table  ON $groups_table.id  = $vendors_table.vendor_group_id
            LEFT JOIN $country_table ON $country_table.id = $vendors_table.country_id
            LEFT JOIN $regions_table ON $regions_table.id = $vendors_table.region_id
            LEFT JOIN $cities_table  ON $cities_table.id  = $vendors_table.city_id
            $where
            ORDER BY $vendors_table.id DESC
        ");
    }
}
