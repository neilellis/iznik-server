<?php

define('IZNIK_BASE', dirname(__FILE__) . '/..');
require_once(IZNIK_BASE . '/composer/vendor/autoload.php');

define('DUPLICATE_POST_PROTECTION', 10); # Set to 0 to disable
define('API_RETRIES', 5);
define('BROWSERTRACKING', TRUE);
define('INCLUDE_TEMPLATE_NAME', TRUE);
define('SQLLOG', TRUE);

define('COOKIE_NAME', 'session');

# Our servers run on UTC
date_default_timezone_set('UTC');

# Per-machine config or overrides
require_once('/etc/iznik.conf');

if (!defined('MINIFY')) {
    define('MINIFY', FALSE);
}