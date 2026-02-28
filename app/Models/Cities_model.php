<?php

namespace App\Models;

class Cities_model extends Crud_model
{

    protected $table = null;

    function __construct()
    {
        $this->table = 'cities';
        parent::__construct($this->table);
    }

    function get_details($options = array())
    {

        $cities_table  = $this->db->prefixTable('cities');
        $regions_table = $this->db->prefixTable('regions');
        $country_table = $this->db->prefixTable('country');

        $where = "";
        $id = $this->_get_clean_value($options, "id");
        if ($id) {
            $where .= " AND $cities_table.id=$id";
        }

        $sql = "SELECT 
                    $cities_table.id,
                    $cities_table.regions_id,
                    $cities_table.name,
                    $cities_table.code,
                    $cities_table.is_active,

                    $regions_table.name AS region_name,
                    $regions_table.country_id,

                    $country_table.name AS country_name

                FROM $cities_table
                LEFT JOIN $regions_table ON $regions_table.id = $cities_table.regions_id
                LEFT JOIN $country_table ON $country_table.id = $regions_table.country_id
                WHERE $cities_table.deleted=0 $where
                ORDER BY $country_table.name ASC, $regions_table.name ASC, $cities_table.name ASC";

        return $this->db->query($sql);
    }
}
