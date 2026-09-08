<?php

/*
 |--------------------------------------------------------------------------
 | ERROR DISPLAY
 |--------------------------------------------------------------------------
 | Keep all errors reportable and available to the configured logger, but do
 | not render diagnostic details into HTTP responses. This keeps local logs
 | useful without exposing paths, queries, or secrets through the browser.
 */
error_reporting(-1);
ini_set('log_errors', '1');
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

/*
 |--------------------------------------------------------------------------
 | DEBUG BACKTRACES
 |--------------------------------------------------------------------------
 | If true, this constant will tell the error screens to display debug
 | backtraces along with the other error information. If you would
 | prefer to not see this, set this value to false.
 */
defined('SHOW_DEBUG_BACKTRACE') || define('SHOW_DEBUG_BACKTRACE', false);

/*
 |--------------------------------------------------------------------------
 | DEBUG MODE
 |--------------------------------------------------------------------------
 | Debug mode is an experimental flag that can allow changes throughout
 | the system. This will control whether Kint is loaded, and a few other
 | items. It can always be used within your own application too.
 */
defined('CI_DEBUG') || define('CI_DEBUG', true);
