<?php

namespace App\Models;

class Vendor_branches_model extends Crud_model
{
    protected $table = null;

    function __construct()
    {
        $this->table = 'vendor_branches';
        parent::__construct($this->table);
    }

    public function get_details($options = array())
    {
        $vendor_branches_table = $this->db->prefixTable("vendor_branches");
        $country_table = $this->db->prefixTable("country");
        $regions_table = $this->db->prefixTable("regions");
        $cities_table  = $this->db->prefixTable("cities");

        $where = "";

        $id = $this->_get_clean_value($options, "id");
        if ($id) {
            $where .= " AND $vendor_branches_table.id=$id";
        }

        $vendor_id = $this->_get_clean_value($options, "vendor_id");
        if ($vendor_id) {
            $where .= " AND $vendor_branches_table.vendor_id=$vendor_id";
        }

        $sql = "SELECT
                $vendor_branches_table.*,
                $vendor_branches_table.name AS branch_name,
                $country_table.name AS country_name,
                $regions_table.name AS region_name,
                $cities_table.name AS city_name
            FROM $vendor_branches_table
            LEFT JOIN $country_table ON $country_table.id = $vendor_branches_table.country_id
            LEFT JOIN $regions_table ON $regions_table.id = $vendor_branches_table.region_id
            LEFT JOIN $cities_table  ON $cities_table.id  = $vendor_branches_table.city_id
            WHERE $vendor_branches_table.deleted=0 $where
            ORDER BY $vendor_branches_table.id DESC";

        return $this->db->query($sql);
    }
}
