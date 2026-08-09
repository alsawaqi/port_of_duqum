<?php

/* Don't change or add any new config in this file */

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Rise extends BaseConfig {

    public const PRODUCTION_CSP_REPORT_ONLY = false;
    public const PRODUCTION_RUNTIME_CODE_MANAGEMENT_ENABLED = false;

    public $app_settings_array = array(
        "app_version" => "3.9.4",
        "app_update_url" => 'https://releases.fairsketch.com/rise/',
        "updates_path" => './updates/',
    );
    public const APP_CSRF_EXCLUDE_URIS = [
        "paypal_redirect", "paypal_redirect/index",
        "paytm_redirect", "paytm_redirect/index", "paytm_redirect.*+",
        "stripe_redirect", "stripe_redirect/index",
        "eservice_payment_webhook/stripe",
        "pay_invoice", "pay_invoice/*",
        "webhooks_listener.*+",
        "external_tickets.*+",
        "collect_leads.*+",
        "request_estimate.*+",
        "cron",
        "event_tracker.*+"
    ];

    public $app_csrf_exclude_uris = self::APP_CSRF_EXCLUDE_URIS;

    public function __construct() {
        parent::__construct();

        // Spark/config tooling can instantiate Rise before the legacy plugin
        // hook bootstrap exists. Keep the fixed baseline in that context.
        if (function_exists('app_hooks')) {
            $hooks = app_hooks();
            if (is_object($hooks) && method_exists($hooks, 'apply_filters')) {
                $this->app_csrf_exclude_uris = $hooks->apply_filters(
                    'app_filter_app_csrf_exclude_uris',
                    $this->app_csrf_exclude_uris
                );
            }
        }
    }

}
