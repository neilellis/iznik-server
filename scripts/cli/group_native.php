<?php

require_once dirname(__FILE__) . '/../../include/config.php';
require_once(IZNIK_BASE . '/include/db.php');
require_once(IZNIK_BASE . '/include/utils.php');
require_once(IZNIK_BASE . '/include/group/Group.php');
require_once(IZNIK_BASE . '/include/user/User.php');

$opts = getopt('n:');

if (count($opts) < 1) {
    echo "Usage: hhvm group_native.php -n <shortname of group>\n";
} else {
    $name = $opts['n'];
    $g = Group::get($dbhr, $dbhm);
    $id = $g->findByShortName($name);

    if ($id) {
        $g = Group::get($dbhr, $dbhm, $id);
        $g->moveToNative();
        $g->setPrivate('onyahoo', 0);
    }
}
