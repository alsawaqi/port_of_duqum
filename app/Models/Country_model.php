<?php

namespace App\Models;

class Country_model extends Crud_model
{

    protected $table = null;

    function __construct()
    {
        $this->table = 'country'; // no prefix
        parent::__construct($this->table);
    }

    function get_details($options = array())
    {

        $country_table = $this->db->prefixTable('country');

        $where = "";
        $id = $this->_get_clean_value($options, "id");
        if ($id) {
            $where .= " AND $country_table.id=$id";
        }

        $sql = "SELECT 
                    $country_table.id,
                    $country_table.name,
                    $country_table.code,
                    $country_table.is_active
                FROM $country_table
                WHERE $country_table.deleted=0 $where
                ORDER BY $country_table.name ASC";

        return $this->db->query($sql);
    }
}
