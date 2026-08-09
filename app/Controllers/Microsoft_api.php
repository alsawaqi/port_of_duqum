<?php

namespace App\Controllers;

use App\Libraries\Outlook_imap;
use App\Libraries\Outlook_smtp;
use App\Libraries\Oauth_state_guard;

class Microsoft_api extends Security_Controller {

    private $Outlook_imap;
    private $Outlook_smtp;
    private Oauth_state_guard $oauth_state;
    
    function __construct() {
        parent::__construct();
        $this->access_only_admin_or_settings_admin();
        $this->Outlook_imap = new Outlook_imap();
        $this->Outlook_smtp = new Outlook_smtp();
        $this->oauth_state = new Oauth_state_guard();
    }

    function index() {
        show_404();
    }

    function authorize_outlook_imap() {
        $this->Outlook_imap->authorize($this->oauth_state->issue('microsoft_outlook_imap', (int) $this->login_user->id));
    }

    function save_outlook_imap_access_token() {
        $this->_require_oauth_callback('microsoft_outlook_imap');
        $this->Outlook_imap->save_access_token($this->_oauth_code());
        app_redirect("ticket_types");
    }

    function authorize_outlook_smtp() {
        $this->Outlook_smtp->authorize($this->oauth_state->issue('microsoft_outlook_smtp', (int) $this->login_user->id));
    }

    function save_outlook_smtp_access_token() {
        $this->_require_oauth_callback('microsoft_outlook_smtp');
        $this->Outlook_smtp->save_access_token($this->_oauth_code());
        app_redirect("settings/email");
    }

    private function _require_oauth_callback(string $flow): void {
        $state = trim((string) $this->request->getGet('state'));
        if (!$this->oauth_state->consume($flow, $state, (int) $this->login_user->id)) {
            app_redirect("forbidden");
        }
    }

    private function _oauth_code(): string {
        $code = trim((string) $this->request->getGet('code'));
        if ($code === '' || strlen($code) > 4096) {
            app_redirect("forbidden");
        }
        return $code;
    }

}

/* End of file Microsoft_api.php */
/* Location: ./app/controllers/Microsoft_api.php */
