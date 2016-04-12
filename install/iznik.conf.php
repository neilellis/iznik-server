<?php
# This file should be suitably modified, then go into /etc/iznik.conf
define('SQLDB', 'iznik');
define('SQLUSER', 'zzzz');
define('SQLPASSWORD', 'zzzz');
define('PASSWORD_SALT', 'zzzz');
define('MODERATOR_EMAIL', 'zzzz');

# We can query Trash Nothing to get real email addresses for their users.
define('TNKEY', 'zzzzz');

# We can use push notifications
define('GOOGLE_PROJECT', 'zzz');
define('GOOGLE_PUSH_KEY', 'zzzz');

# Other Google keys
define('GOOGLE_VISION_KEY', 'zzz');
define('GOOGLE_CLIENT_ID', 'zzz');
define('GOOGLE_CLIENT_SECRET', 'zzz');
define('GOOGLE_APP_NAME', 'zzz');

# We support Facebook login, but you have to create your own app
define('FBAPP_ID', 'zzz');
define('FBAPP_SECRET', 'zzz');

# We use beanstalk for backgrounding.
define('PHEANSTALK_SERVER', '127.0.0.1');

$host = $_SERVER && array_key_exists('HTTP_HOST', $_SERVER) ? $_SERVER['HTTP_HOST'] : 'iznik.modtools.org';

switch($host) {
    case 'iznik.modtools.org':
        define('SITE_NAME', 'Iznik');
        define('SITE_DESC', 'Making moderating easier');
        define('MANIFEST', FALSE);
        define('MANIFEST_STARTURL', 'modtools');
        define('FAVICON_HOME', 'modtools');
        define('CHAT_HOST', 'iznik.modtools.org');
        break;
    case 'dev.modtools.org':
    case 'modtools.org':
        define('SITE_NAME', 'Iznik');
        define('SITE_DESC', 'Making moderating easier');
        define('MANIFEST', TRUE);
        define('MANIFEST_STARTURL', 'modtools');
        define('FAVICON_HOME', 'modtools');
        define('CHAT_HOST', 'modtools.org');
        break;
    case 'iznik.ilovefreegle.org':
        define('SITE_NAME', 'Freegle');
        define('SITE_DESC', 'Online dating for stuff');
        define('MANIFEST', FALSE);
        define('MANIFEST_STARTURL', '');
        define('FAVICON_HOME', 'user');
        define('CHAT_HOST', 'chat.ilovefreegle.org');
        break;
}

# Image host domain
define('IMAGE_DOMAIN', 'zzzz');

# Domain for email addresses for our users
define('USER_DOMAIN', 'zzzz');

# Contact emails
define('SUPPORT_ADDR', 'support@zzz');
define('INFO_ADDR', 'info@zzz');
define('NOREPLY_ADDR', 'noreply@zzz');

# This speeds up load time
define('MINIFY', TRUE);
