<?php

namespace App\Controllers;

use App\Libraries\Google;
use App\Libraries\Google_calendar;
use App\Libraries\Google_calendar_events;
use App\Libraries\Gmail_imap;
use App\Libraries\Gmail_smtp;
use App\Libraries\Oauth_state_guard;

class Google_api extends Security_Controller {

    private $google;
    private $Google_calendar;
    private $Google_calendar_events;
    private $Gmail_imap;
    private $Gmail_smtp;
    private Oauth_state_guard $oauth_state;

    function __construct() {
        parent::__construct();
        $this->google = new Google();
        $this->Google_calendar = new Google_calendar();
        $this->Google_calendar_events = new Google_calendar_events();
        $this->Gmail_imap = new Gmail_imap();
        $this->Gmail_smtp = new Gmail_smtp();
        $this->oauth_state = new Oauth_state_guard();
    }

    function index() {
        app_redirect("google_api/authorize");
    }

    //authorize google drive
    function authorize() {
        $this->access_only_admin_or_settings_admin();
        $this->google->authorize($this->oauth_state->issue('google_drive', (int) $this->login_user->id));
    }

    //get access token of drive and save
    function save_access_token() {
        $this->access_only_admin_or_settings_admin();
        $this->_require_oauth_callback('google_drive');
        $this->google->save_access_token($this->_oauth_code());
        app_redirect("settings/integration/google_drive");
    }

    //authorize google calendar
    function authorize_calendar() {
        $this->access_only_admin_or_settings_admin();
        $this->Google_calendar->authorize($this->oauth_state->issue('google_calendar_admin', (int) $this->login_user->id));
    }

    //get access code and save
    function save_access_token_of_calendar() {
        $this->access_only_admin_or_settings_admin();
        $this->_require_oauth_callback('google_calendar_admin');
        $this->Google_calendar->save_access_token($this->_oauth_code());
        app_redirect("settings/events");
    }

    //authorize google calendar
    function authorize_own_calendar() {
        $this->Google_calendar_events->authorize(
            $this->login_user->id,
            $this->oauth_state->issue('google_calendar_user', (int) $this->login_user->id)
        );
    }

    //get access code and save
    function save_access_token_of_own_calendar() {
        $this->_require_oauth_callback('google_calendar_user');
        $this->Google_calendar_events->save_access_token($this->_oauth_code(), $this->login_user->id);
        app_redirect("events");
    }

    //authorize gmail imap
    function authorize_gmail_imap() {
        $this->access_only_admin_or_settings_admin();
        $this->Gmail_imap->authorize($this->oauth_state->issue('google_gmail_imap', (int) $this->login_user->id));
    }

    //get access code and save
    function save_gmail_imap_access_token() {
        $this->access_only_admin_or_settings_admin();
        $this->_require_oauth_callback('google_gmail_imap');
        $this->Gmail_imap->save_access_token($this->_oauth_code());
        app_redirect("ticket_types/index/imap");
    }

    //authorize gmail smtp
    function authorize_gmail_smtp() {
        $this->access_only_admin_or_settings_admin();
        $this->Gmail_smtp->authorize($this->oauth_state->issue('google_gmail_smtp', (int) $this->login_user->id));
    }

    //get access code and save
    function save_gmail_smtp_access_token() {
        $this->access_only_admin_or_settings_admin();
        $this->_require_oauth_callback('google_gmail_smtp');
        $this->Gmail_smtp->save_access_token($this->_oauth_code());
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

/* End of file Google_api.php */
/* Location: ./app/controllers/Google_api.php */
