<?php

namespace App\Models;

class Tender_target_specialties_model extends Crud_model
{
    protected $table = null;

    public function __construct()
    {
        $this->table = "tender_target_specialties";
        parent::__construct($this->table);
    }
}