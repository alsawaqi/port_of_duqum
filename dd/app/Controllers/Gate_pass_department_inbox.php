<?php

namespace App\Controllers;

/**
 * Legacy/alias route: department users use {@see Gate_pass_department_requests}.
 * This controller exists so `gate_pass_department_inbox` URLs and language keys resolve safely.
 */
class Gate_pass_department_inbox extends Security_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->access_only_team_members();
    }

    public function index()
    {
        app_redirect("gate_pass_department_requests");
    }
}
