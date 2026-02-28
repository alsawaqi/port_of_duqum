<?php

namespace App\Models;

class Regions_model extends Crud_model
{

    protected $table = null;

    function __construct()
    {
        $this->table = 'regions';
        parent::__construct($this->table);
    }

    function get_details($options = array())
    {

        $regions_table = $this->db->prefixTable('regions');
        $country_table = $this->db->prefixTable('country');

        $where = "";
        $id = $this->_get_clean_value($options, "id");
        if ($id) {
            $where .= " AND $regions_table.id=$id";
        }

        $sql = "SELECT 
                    $regions_table.id,
                    $regions_table.country_id,
                    $regions_table.name,
                    $regions_table.code,
                    $regions_table.is_active,
                    $country_table.name AS country_name
                FROM $regions_table
                LEFT JOIN $country_table ON $country_table.id = $regions_table.country_id
                WHERE $regions_table.deleted=0 $where
                ORDER BY $country_table.name ASC, $regions_table.name ASC";

        return $this->db->query($sql);
    }
}
