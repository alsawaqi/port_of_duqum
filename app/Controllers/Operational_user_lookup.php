<?php

namespace App\Controllers;

class Operational_user_lookup extends Security_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->access_only_team_members();
        $this->db = db_connect();
    }

    public function check_email()
    {
        $email = (string) ($this->request->getPost("email") ?: $this->request->getGet("email"));
        $user = $this->get_operational_user_by_email($email);

        if (!$user) {
            return $this->response->setJSON(["exists" => false]);
        }

        return $this->response->setJSON([
            "exists" => true,
            "user" => [
                "id" => (int) $user->id,
                "first_name" => (string) $user->first_name,
                "last_name" => (string) $user->last_name,
                "email" => (string) $user->email,
                "phone" => (string) $user->phone,
                "user_type" => (string) $user->user_type,
                "status" => (string) $user->status,
                "can_assign" => (string) $user->user_type === "staff",
            ],
        ]);
    }
}
