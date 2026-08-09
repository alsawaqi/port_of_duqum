<?php

namespace App\Controllers;

/**
 * Legacy endpoint retained only to reject previously issued invitation links.
 * Vendor contact credentials are now created directly by the vendor account
 * owner, so this controller must never activate an account or change a password.
 */
class Vendor_contact_invitation extends App_Controller
{
    public function index()
    {
        return $this->invitationRetired();
    }

    public function accept($code = "")
    {
        return $this->invitationRetired();
    }

    public function activate()
    {
        return $this->response
            ->setStatusCode(410)
            ->setJSON([
                "success" => false,
                "message" => "Vendor contact invitation links are no longer accepted. Ask the vendor account owner for your login password.",
            ]);
    }

    private function invitationRetired()
    {
        $this->response->setStatusCode(410);

        return $this->template->view("errors/html/error_general", [
            "heading" => "Invitation retired",
            "message" => "Vendor contact invitation links are no longer accepted. Ask the vendor account owner for your login password.",
        ]);
    }
}