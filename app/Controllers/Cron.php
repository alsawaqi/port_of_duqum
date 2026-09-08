<?php

namespace App\Controllers;

use App\Libraries\Cron_job;
use App\Models\Tender_bid_openings_model;
use App\Models\Tenders_model;

class Cron extends App_Controller {

    private $cron_job;

    function __construct() {
        parent::__construct();
        $this->cron_job = new Cron_job();
    }

    function index() {
        $authorizationFailure = $this->_authorize_invocation();
        if ($authorizationFailure) {
            return $authorizationFailure;
        }

        ini_set('max_execution_time', 300); //execute maximum 300 seconds 

        $last_cron_job_time = get_setting('last_cron_job_time');

        $minimum_cron_interval_seconds = get_setting('minimum_cron_interval_seconds');
        if (!$minimum_cron_interval_seconds) {
            $minimum_cron_interval_seconds = 300; //5 minutes
        }

        $current_time = strtotime(get_current_utc_time());

        if ($last_cron_job_time == "" || ($current_time > ($last_cron_job_time * 1 + $minimum_cron_interval_seconds))) {
            $this->cron_job->run();
            // Time-driven tender transitions are mutations and therefore run
            // only from this authenticated scheduler, never from page reads.
            (new Tenders_model())->auto_progress_workflow(true);
            (new Tender_bid_openings_model())->expire_old_sessions();
            (new \App\Libraries\Sms\WorkflowSmsOutbox())->process(20);
            app_hooks()->do_action("app_hook_after_cron_run");
            $this->Settings_model->save_setting("last_cron_job_time", $current_time);
            echo "Cron job executed.";
        } else {
            $start = new \DateTime(date("Y-m-d H:i:s", $last_cron_job_time * 1 + $minimum_cron_interval_seconds));
            $end = new \DateTime();
            $diff = $end->diff($start);
            $format = "%i minutes, %s seconds.";

            if ($diff->i <= 0) {
                $format = "%s seconds.";
            }
            echo "Please try after " . $end->diff($start)->format($format);
        }
    }

    private function _authorize_invocation()
    {
        if (PHP_SAPI === "cli") {
            return null;
        }

        if (strtoupper((string)$this->request->getMethod()) !== "POST") {
            return $this->response
                ->setStatusCode(405)
                ->setHeader("Allow", "POST")
                ->setBody("Cron invocation rejected.");
        }

        $ipHash = hash("sha256", (string)$this->request->getIPAddress());
        if (!service("throttler")->check("cron_" . $ipHash, 5, 60)) {
            return $this->response->setStatusCode(429)->setBody("Cron invocation rejected.");
        }

        $expected = trim((string)(getenv("PODC_CRON_KEY") ?: ""));
        $provided = trim((string)$this->request->getHeaderLine("X-PODC-Cron-Key"));
        $authorization = trim((string)$this->request->getHeaderLine("Authorization"));
        if ($provided === "" && preg_match('/^Bearer\s+(.+)$/i', $authorization, $match)) {
            $provided = trim((string)$match[1]);
        }

        if (strlen($expected) < 32 || $provided === "" || !hash_equals($expected, $provided)) {
            log_message("notice", "Rejected unauthorized HTTP cron invocation.");
            return $this->response->setStatusCode(403)->setBody("Cron invocation rejected.");
        }

        return null;
    }
}

/* End of file Cron.php */
/* Location: ./app/controllers/Cron.php */
