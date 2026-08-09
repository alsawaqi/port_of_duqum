<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\CodeIgniter;
use CodeIgniter\Session\Handlers\FileHandler;

class App extends BaseConfig {

    public function __construct() {
        parent::__construct();
        $this->load_security_environment();
        $this->set_base_url();

        if (ENVIRONMENT === 'production') {
            $this->validate_production_security_config();
        }

        $this->set_supported_languages();
    }

    private function load_security_environment() {
        $base_url = $this->read_environment_value(array('PODC_BASE_URL', 'APP_BASE_URL'));
        if ($base_url !== null) {
            $this->baseURL = $base_url;
        }

        $encryption_key = $this->read_environment_value(array('PODC_APP_ENCRYPTION_KEY', 'APP_ENCRYPTION_KEY'));
        if ($encryption_key !== null) {
            $this->encryption_key = $encryption_key;
        }
    }

    private function read_environment_value(array $names) {
        foreach ($names as $name) {
            $value = getenv($name);
            if ($value === false && array_key_exists($name, $_ENV)) {
                $value = $_ENV[$name];
            }
            if ($value === false && array_key_exists($name, $_SERVER)) {
                $value = $_SERVER[$name];
            }

            if ($value !== false && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }

    private function validate_production_security_config() {
        $this->forceGlobalSecureRequests = true;
        $this->CSPEnabled = true;

        // 4.7.4 contains security fixes for trusted-proxy HTTPS detection,
        // batch deletion, uploaded-file paths, and extension/MIME validation.
        // Refuse production startup on a known-vulnerable framework baseline.
        if (version_compare(CodeIgniter::CI_VERSION, '4.7.4', '<')) {
            throw new \RuntimeException(
                'Production requires CodeIgniter 4.7.4 or a later security-supported release.'
            );
        }

        if ((string) $this->encryption_key === '') {
            throw new \RuntimeException(
                'Production requires app.encryption_key or PODC_APP_ENCRYPTION_KEY.'
            );
        }

        // Current-release compatibility: existing deployments may still use
        // the legacy RISE encryption key length. Do not rotate it silently;
        // changing this key can invalidate encoded IDs and saved encrypted
        // values. Plan a proper key migration before enforcing 32+ bytes.

        $parts = parse_url($this->baseURL);
        if (
            !is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || preg_match('/[\r\n]/', $this->baseURL)
        ) {
            throw new \RuntimeException(
                'Production requires an HTTPS app.baseURL, PODC_BASE_URL, or HTTPS request origin.'
            );
        }

        $this->baseURL = rtrim($this->baseURL, '/') . '/';
    }

    private function set_base_url() {
        if (!$this->baseURL) {

            // CLI launchers do not have a trustworthy HTTP_HOST/SCRIPT_NAME.
            // Use a stable non-production origin instead of producing values
            // such as http://localhostspark or an invalid SiteURI.
            if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
                $this->baseURL = 'http://localhost/';
                return;
            }

            $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
            if (!preg_match('/\A(?:\[[0-9a-f:]+\]|[a-z0-9.-]+)(?::[0-9]{1,5})?\z/i', $host)) {
                $host = 'localhost';
            }

            $domain = $host . (string) ($_SERVER['SCRIPT_NAME'] ?? '/');

            $domain = preg_replace('/index.php.*/', '', $domain);
            $domain = strtolower($domain);
            if (!empty($_SERVER['HTTPS'])) {
                $this->baseURL = 'https://' . $domain;
            } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
                $this->baseURL = 'https://' . $domain;
            } else {
                $this->baseURL = 'http://' . $domain;
            }
        }
    }

    private function set_supported_languages() {
        if(count($this->supportedLocales)) return ;
        $language_dropdown = array();
        $dir = "./app/Language/";
        if (is_dir($dir)) {
            if ($dh = opendir($dir)) {
                while (($file = readdir($dh)) !== false) {
                    if ($file && $file != "." && $file != ".." && $file != "index.html" && $file != ".gitkeep" && $file != ".DS_Store") {
                        array_push($language_dropdown, $file);
                    }
                }
                closedir($dh);
            }
        }

        $this->supportedLocales = $language_dropdown;
    }

    /**
     * --------------------------------------------------------------------------
     * Base Site URL
     * --------------------------------------------------------------------------
     *
     * URL to your CodeIgniter root. Typically this will be your base URL,
     * WITH a trailing slash:
     *
     *    http://example.com/
     *
     * If this is not set then CodeIgniter will try guess the protocol, domain
     * and path to your installation. However, you should always configure this
     * explicitly and never rely on auto-guessing, especially in production
     * environments.
     *
     * @var string
     */
    public $baseURL = '';
    
    /**
     * Allowed Hostnames in the Site URL other than the hostname in the baseURL.
     * If you want to accept multiple Hostnames, set this.
     *
     * E.g. When your site URL ($baseURL) is 'http://example.com/', and your site
     *      also accepts 'http://media.example.com/' and
     *      'http://accounts.example.com/':
     *          ['media.example.com', 'accounts.example.com']
     *
     * @var string[]
     * @phpstan-var list<string>
     */
    public array $allowedHostnames = [];
    
    /**
     * --------------------------------------------------------------------------
     * Index File
     * --------------------------------------------------------------------------
     *
     * Typically this will be your index.php file, unless you've renamed it to
     * something else. If you are using mod_rewrite to remove the page set this
     * variable so that it is blank.
     *
     * @var string
     */
    
    public $indexPage = 'index.php';

    /**
     * --------------------------------------------------------------------------
     * URI PROTOCOL
     * --------------------------------------------------------------------------
     *
     * This item determines which getServer global should be used to retrieve the
     * URI string.  The default setting of 'REQUEST_URI' works for most servers.
     * If your links do not seem to work, try one of the other delicious flavors:
     *
     * 'REQUEST_URI'    Uses $_SERVER['REQUEST_URI']
     * 'QUERY_STRING'   Uses $_SERVER['QUERY_STRING']
     * 'PATH_INFO'      Uses $_SERVER['PATH_INFO']
     *
     * WARNING: If you set this to 'PATH_INFO', URIs will always be URL-decoded!
     *
     * @var string
     */
    public $uriProtocol = 'REQUEST_URI';

    /*
    |--------------------------------------------------------------------------
    | Allowed URL Characters
    |--------------------------------------------------------------------------
    |
    | This lets you specify which characters are permitted within your URLs.
    | When someone tries to submit a URL with disallowed characters they will
    | get a warning message.
    |
    | As a security measure you are STRONGLY encouraged to restrict URLs to
    | as few characters as possible.
    |
    | By default, only these are allowed: `a-z 0-9~%.:_-`
    |
    | Set an empty string to allow all characters -- but only if you are insane.
    |
    | The configured value is actually a regular expression character group
    | and it will be used as: '/\A[<permittedURIChars>]+\z/iu'
    |
    | DO NOT CHANGE THIS UNLESS YOU FULLY UNDERSTAND THE REPERCUSSIONS!!
    |
    */
    public string $permittedURIChars = 'a-z 0-9~%.:_\-';

    /**
     * --------------------------------------------------------------------------
     * Default Locale
     * --------------------------------------------------------------------------
     *
     * The Locale roughly represents the language and location that your visitor
     * is viewing the site from. It affects the language strings and other
     * strings (like currency markers, numbers, etc), that your program
     * should run under for this request.
     *
     * @var string
     */
    public $defaultLocale = 'english';

    /**
     * --------------------------------------------------------------------------
     * Negotiate Locale
     * --------------------------------------------------------------------------
     *
     * If true, the current Request object will automatically determine the
     * language to use based on the value of the Accept-Language header.
     *
     * If false, no automatic detection will be performed.
     *
     * @var bool
     */
    public $negotiateLocale = false;

    /**
     * --------------------------------------------------------------------------
     * Supported Locales
     * --------------------------------------------------------------------------
     *
     * If $negotiateLocale is true, this array lists the locales supported
     * by the application in descending order of priority. If no match is
     * found, the first locale will be used.
     *
     * @var string[]
     */
    public $supportedLocales = [];

    /**
     * --------------------------------------------------------------------------
     * Application Timezone
     * --------------------------------------------------------------------------
     *
     * The default timezone that will be used in your application to display
     * dates with the date helper, and can be retrieved through app_timezone()
     *
     * @var string
     */
    public $appTimezone = 'UTC';

    /**
     * --------------------------------------------------------------------------
     * Default Character Set
     * --------------------------------------------------------------------------
     *
     * This determines which character set is used by default in various methods
     * that require a character set to be provided.
     *
     * @see http://php.net/htmlspecialchars for a list of supported charsets.
     *
     * @var string
     */
    public $charset = 'UTF-8';

    /**
     * --------------------------------------------------------------------------
     * URI PROTOCOL
     * --------------------------------------------------------------------------
     *
     * If true, this will force every request made to this application to be
     * made via a secure connection (HTTPS). If the incoming request is not
     * secure, the user will be redirected to a secure version of the page
     * and the HTTP Strict Transport Security header will be set.
     *
     * @var bool
     */
    public $forceGlobalSecureRequests = ENVIRONMENT === 'production';

    /**
     * --------------------------------------------------------------------------
     * Reverse Proxy IPs
     * --------------------------------------------------------------------------
     *
     * If your server is behind a reverse proxy, you must whitelist the proxy
     * IP addresses from which CodeIgniter should trust headers such as
     * HTTP_X_FORWARDED_FOR and HTTP_CLIENT_IP in order to properly identify
     * the visitor's IP address.
     *
     * You can use both an array or a comma-separated list of proxy addresses,
     * as well as specifying whole subnets. Here are a few examples:
     *
     * Comma-separated:	'10.0.1.200,192.168.5.0/24'
     * Array: ['10.0.1.200', '192.168.5.0/24']
     *
     * @var string|string[]
     */
    public $proxyIPs = [];

    /**
     * --------------------------------------------------------------------------
     * Content Security Policy
     * --------------------------------------------------------------------------
     *
     * Enables the Response's Content Secure Policy to restrict the sources that
     * can be used for images, scripts, CSS files, audio, video, etc. If enabled,
     * the Response object will populate default values for the policy from the
     * `ContentSecurityPolicy.php` file. Controllers can always add to those
     * restrictions at run time.
     *
     * For a better understanding of CSP, see these documents:
     *
     * @see http://www.html5rocks.com/en/tutorials/security/content-security-policy/
     * @see http://www.w3.org/TR/CSP/
     *
     * @var bool
     */
    public $CSPEnabled = ENVIRONMENT === 'production';

    /* User configs */
    // Keep the current value unchanged in an environment secret before
    // deployment. Rotating it without a data migration invalidates encoded IDs.
    public $encryption_key = '';
    public $csrf_protection = true;
    public $temp_file_path = 'files/temp/';
    public $profile_image_path = 'files/profile_images/';
    public $timeline_file_path = 'files/timeline_files/';
    public $project_file_path = 'files/project_files/';
    public $system_file_path = 'files/system/';
    public $check_notification_after_every = "60"; //Check notification after every 60 seconds. Recommanded: don't set this value less than 20.

}
