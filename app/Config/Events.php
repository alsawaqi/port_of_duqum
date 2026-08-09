<?php

namespace Config;

use CodeIgniter\Events\Events;
use CodeIgniter\Exceptions\FrameworkException;
use CodeIgniter\HotReloader\HotReloader;

/*
 * --------------------------------------------------------------------
 * Application Events
 * --------------------------------------------------------------------
 * Events allow you to tap into the execution of the program without
 * modifying or extending core files. This file provides a central
 * location to define your events, though they can always be added
 * at run-time, also, if needed.
 *
 * You create code that can execute by subscribing to events with
 * the 'on()' method. This accepts any form of callable, including
 * Closures, that will be executed when the event is triggered.
 *
 * Example:
 *      Events::on('create', [$myInstance, 'myMethod']);
 */

Events::on('pre_system', static function (): void {
    if (ENVIRONMENT !== 'testing') {
        if (ini_get('zlib.output_compression')) {
            throw FrameworkException::forEnabledZlibOutputCompression();
        }

        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        ob_start(static fn($buffer) => $buffer);
    }

    /*
     * --------------------------------------------------------------------
     * Debug Toolbar Listeners.
     * --------------------------------------------------------------------
     * If you delete, they will no longer be collected.
     */
    if (CI_DEBUG && ! is_cli()) {
        Events::on('DBQuery', 'CodeIgniter\Debug\Toolbar\Collectors\Database::collect');
        service('toolbar')->respond();
        // Hot Reload route - for framework use on the hot reloader.
        if (ENVIRONMENT === 'development') {
            service('routes')->get('__hot-reload', static function (): void {
                (new HotReloader())->run();
            });
        }
    }

    //load php hooks library
    require_once(APPPATH . "ThirdParty/PHP-Hooks/php-hooks.php");

    helper('plugin');

    define('PLUGINPATH', ROOTPATH . 'plugins/'); //define plugin path
    define('PLUGIN_URL_PATH', 'plugins/'); //define plugin path

    load_plugin_indexes();

    include APPPATH . 'Config/RiseHooks.php';
    include APPPATH . 'Config/RiseCustomHooks.php';

    set_default_csp_directives();
});

function load_plugin_indexes() {
    $plugins = file_get_contents(APPPATH . "Config/activated_plugins.json");
    $plugins = @json_decode($plugins);

    if (!($plugins && is_array($plugins) && count($plugins))) {
        return false;
    }

    foreach ($plugins as $plugin) {
        $index_file = PLUGINPATH . $plugin . '/index.php';

        if (file_exists($index_file)) {
            include $index_file;
        }
    }
}

function set_default_csp_directives() {
    $response = service('response');
    $csp = $response->getCSP();

    if ($csp->enabled()) {
        $App = config('App');
        if (ENVIRONMENT !== 'production' && isset($App->do_not_add_default_csp)) {
            $csp->finalize($response);
            return;
        }

        // Config defaults are replaced with the explicit application policy.
        // Production enforces this policy; non-production remains report-only.
        $directives = array(
            'base-uri', 'child-src', 'connect-src', 'default-src', 'font-src',
            'form-action', 'frame-ancestors', 'frame-src', 'img-src',
            'media-src', 'object-src', 'plugin-types', 'script-src',
            'style-src', 'manifest-src', 'sandbox', 'report-uri'
        );
        foreach ($directives as $directive) {
            $csp->clearDirective($directive);
        }

        $csp->reportOnly(
            ENVIRONMENT === 'production' && Rise::PRODUCTION_CSP_REPORT_ONLY
        );
        $csp->setDefaultSrc('self');
        $csp->addBaseURI('self');
        $csp->addFormAction('self');
        $csp->addFrameAncestor('self');
        $csp->addObjectSrc('none');

        // Inline/eval remain temporarily for the legacy UI. CSP reports should
        // be used to replace them with nonces/hashes before enforcement.
        $csp->addScriptSrc(array(
            'self', 'unsafe-inline', 'unsafe-eval',
            'https://www.google.com', 'https://www.gstatic.com',
            'https://cdn.tiny.cloud', 'https://js.stripe.com'
        ));
        $csp->addStyleSrc(array(
            'self', 'unsafe-inline', 'https://cdn.tiny.cloud'
        ));
        $csp->addFontSrc(array('self', 'data:'));
        $csp->addImageSrc(array(
            'self', 'data:', 'blob:', 'https:',
            'https://sp.tinymce.com', 'https://lh3.googleusercontent.com',
            'https://drive.google.com'
        ));
        $csp->addManifestSrc('self');
        $csp->addMediaSrc(array('self', 'blob:'));
        $csp->addChildSrc(array('self', 'blob:'));
        $csp->addFrameSrc(array(
            'self', 'https://www.google.com', 'https://recaptcha.google.com',
            'https://drive.google.com', 'https://docs.google.com',
            'https://js.stripe.com', 'https://hooks.stripe.com',
            'https://www.youtube.com', 'https://player.vimeo.com'
        ));
        $csp->addConnectSrc(array(
            'self', 'https://www.google.com', 'https://cdn.tiny.cloud',
            'https://hyperlinking.iad.tiny.cloud', 'https://api.stripe.com',
            'https://r.stripe.com', 'https://m.stripe.network',
            'https://*.pushnotifications.pusher.com'
        ));

        $pusher_clusters = array('mt1', 'ap1', 'ap2', 'ap3', 'ap4', 'us2', 'us3', 'eu', 'sa1');
        foreach ($pusher_clusters as $cluster) {
            $csp->addConnectSrc('wss://ws-' . $cluster . '.pusher.com');
            $csp->addConnectSrc('https://sockjs-' . $cluster . '.pusher.com');
        }

        if (ENVIRONMENT !== 'production') {
            $release_url = base64_decode('aHR0cHM6Ly9yZWxlYXNlcy5mYWlyc2tldGNoLmNvbQ==');
            $csp->addImageSrc($release_url);
            $csp->addChildSrc($release_url);
        }

        $report_uri = getenv('PODC_CSP_REPORT_URI');
        if (
            is_string($report_uri)
            && filter_var($report_uri, FILTER_VALIDATE_URL)
            && strtolower((string) parse_url($report_uri, PHP_URL_SCHEME)) === 'https'
            && !preg_match('/[\r\n]/', $report_uri)
        ) {
            $csp->setReportURI($report_uri);
        }

        $csp->finalize($response);
    }
}
