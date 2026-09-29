<?php

namespace App\Controllers;

use App\Models\Gate_pass_request_audit_log_model;

/**
 * Gate pass request audit trail across all requests.
 */
class Gate_pass_activity_logs extends Security_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->access_only_team_members();
        $this->access_only_gate_pass("activity_logs", "view");
    }

    public function index()
    {
        return $this->template->rander("gate_pass_activity_logs/index", []);
    }

    public function list_data()
    {
        $model = new Gate_pass_request_audit_log_model();
        $rows = $model->get_admin_feed(["limit" => 2000])->getResult();

        $out = [];
        foreach ($rows as $a) {
            $ref = trim((string)($a->request_reference ?? ""));
            if ($ref === "") {
                $ref = app_lang("gate_pass_audit_unknown_reference");
            }
            $company = trim((string)($a->request_company ?? ""));
            if ($company === "") {
                $company = "-";
            }

            $out[] = [
                $a->created_at ? format_to_datetime($a->created_at) : "-",
                $ref,
                $company,
                trim((string)($a->actor_name ?? "")) ?: ("—"),
                gate_pass_audit_action_display_label((string)($a->action ?? "")),
                $a->details ? htmlspecialchars((string)$a->details, ENT_QUOTES, "UTF-8") : "—",
            ];
        }

        echo json_encode(["data" => $out]);
    }
}
