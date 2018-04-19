<?php
#
# Purge logs. We do this in a script rather than an event because we want to chunk it, otherwise we can hang the
# cluster with an op that's too big.
#
require_once dirname(__FILE__) . '/../../include/config.php';
require_once(IZNIK_BASE . '/include/utils.php');
require_once(IZNIK_BASE . '/include/db.php');
require_once(IZNIK_BASE . '/include/misc/Log.php');

# Bypass our usual DB class as we don't want the overhead nor to log.
$dsn = "mysql:host={$dbconfig['host']};dbname=iznik;charset=utf8";
$dbhm = new PDO($dsn, $dbconfig['user'], $dbconfig['pass'], array(
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => FALSE
));

# Delete logs for messages which no longer exist.  Typically spam.
try {
    error_log("Logs for messages no longer around:");
    $total = 0;
    $logs = $dbhm->query("SELECT logs.id FROM logs LEFT JOIN messages ON messages.id = logs.msgid WHERE logs.type = 'Message' AND logs.msgid IS NOT NULL AND messages.id IS NULL;");
    error_log("Found " . count($logs));

    foreach ($logs as $log) {
        $sql = "DELETE FROM logs WHERE id = {$log['id']};";
        $count = $dbhm->exec($sql);
        $total++;

        if ($total % 1000 == 0) {
            error_log("...$total");
        }
    }
} catch (Exception $e) {
    error_log("Failed to delete bounce emails" . $e->getMessage());
}

# Delete old email bounces.  Any genuinely bouncing emails will result in the user being set as bouncing = 1 fairly
# rapidly.
try {
    error_log("Old bounces:");
    $start = date('Y-m-d', strtotime("midnight 31 days ago"));
    $total = 0;
    do {
        $count = $dbhm->exec("DELETE FROM bounces_emails WHERE `date` < '$start' LIMIT 1000;");
        $total += $count;
        error_log("...$total");
        set_time_limit(600);
    } while ($count > 0);
} catch (Exception $e) {
    error_log("Failed to delete bounce emails" . $e->getMessage());
}

# Delete any old plugin work for non-Yahoo groups
$dbhm->exec("DELETE FROM `plugin` WHERE groupid IN (SELECT id FROM groups WHERE onyahoo = 0)");

# Don't keep user creation logs indefinitely - the reason we created a user is only really relevant for diagnosis,
error_log("Purge user creation logs");

try {
    $start = date('Y-m-d', strtotime("midnight 31 days ago"));
    $total = 0;
    do {
        $sql = "DELETE FROM logs WHERE `type` = '" . Log::TYPE_USER . "' AND `subtype` = '" . Log::SUBTYPE_CREATED . "' AND `timestamp` < '$start' LIMIT 1000;";
        $count = $dbhm->exec($sql);
        $total += $count;
        error_log("...$total");
        set_time_limit(600);
    } while ($count > 0);
} catch (Exception $e) {
    error_log("Failed to delete non-Freegle logs " . $e->getMessage());
}

error_log("Purge email logs");

try {
    $start = date('Y-m-d', strtotime("25 hours ago"));
    $total = 0;
    do {
        $count = $dbhm->exec("DELETE FROM logs_emails WHERE `timestamp` < '$start' LIMIT 1000;");
        $total += $count;
        error_log("...$total");
        set_time_limit(600);
    } while ($count > 0);
} catch (Exception $e) {
    error_log("Failed to delete email logs logs " . $e->getMessage());
}

error_log("Purge main logs");

try {
    # Non-Freegle groups only keep data for 31 days.
    $start = date('Y-m-d', strtotime("midnight 31 days ago"));
    error_log("Non-Freegle logs");
    $groups = $dbhr->preQuery("SELECT id FROM groups WHERE type != 'Freegle';");
    foreach ($groups as $group) {
        $total = 0;
        do {
            $count = $dbhm->exec("DELETE FROM logs WHERE `timestamp` < '$start' AND groupid IS NOT NULL AND groupid = {$group['id']} LIMIT 1000;");
            $total += $count;
            error_log("...$total");
            set_time_limit(600);
        } while ($count > 0);
    }
} catch (Exception $e) {
    error_log("Failed to delete non-Freegle logs " . $e->getMessage());
}

# In the main logs table we might have logs that can be removed once enough time has elapsed for us using them for PD.
$start = date('Y-m-d', strtotime("midnight 7 days ago"));
$keys = [
    'user' => 'users',
    'byuser' => 'users',
    'msgid' => 'messages',
    'groupid' => 'groups',
    'configid' => 'mod_configs',
    'stdmsgid' => 'mod_stdmsgs',
    'bulkopid' => 'mod_bulkops'
];

//foreach ($keys as $att => $table) {
//    error_log("Logs for $att not in $table");
//    $total = 0;
//    do {
//        $count = $dbhm->exec("DELETE FROM logs WHERE timestamp < '$start' AND $att IS NOT NULL AND $att <> 0 AND $att NOT IN (SELECT id FROM $table) LIMIT 1000;");
//        $total += $count;
//        error_log("...$total");
//    } while ($count > 0);
//}

# Src logs.
$start = date('Y-m-d', strtotime("midnight 30 days ago"));
error_log("Purge src logs before $start");

try {
    error_log("Src logs:");
    $total = 0;
    do {
        $count = $dbhm->exec("DELETE FROM logs_src WHERE `date` < '$start' LIMIT 1000;");
        $total += $count;
        error_log("...$total");
        set_time_limit(600);
    } while ($count > 0);
} catch (Exception $e) {
    error_log("Failed to delete src logs " . $e->getMessage());
}

# JS error logs.
$start = date('Y-m-d', strtotime("midnight 30 days ago"));
error_log("Purge JS error logs before $start");

try {
    $total = 0;
    do {
        $count = $dbhm->exec("DELETE FROM logs_errors WHERE `date` < '$start' LIMIT 1000;");
        $total += $count;
        error_log("...$total");
        set_time_limit(600);
    } while ($count > 0);
} catch (Exception $e) {
    error_log("Failed to delete src logs " . $e->getMessage());
}

$start = date('Y-m-d', strtotime("midnight 1 day ago"));
error_log("Purge detailed logs before $start");

try {
    error_log("Plugin logs:");
    $total = 0;
    do {
        $count = $dbhm->exec("DELETE FROM logs WHERE `timestamp` < '$start' AND TYPE = 'Plugin' LIMIT 1000;");
        $total += $count;
        error_log("...$total");
        set_time_limit(600);
    } while ($count > 0);
} catch (Exception $e) {
    error_log("Failed to delete Plugin logs " . $e->getMessage());
}

try {
    error_log("API logs:");
    $total = 0;
    do {
        $count = $dbhm->exec("DELETE FROM logs_api WHERE `date` < '$start' LIMIT 1000;");
        $total += $count;
        error_log("...$total");
        set_time_limit(600);
    } while ($count > 0);
} catch (Exception $e) {
    error_log("Failed to delete API logs " . $e->getMessage());
}

$start = date('Y-m-d', strtotime("4 hours ago"));

try {
    error_log("SQL logs:");
    $total = 0;
    do {
        $count = $dbhm->exec("DELETE FROM logs_sql WHERE `date` < '$start' LIMIT 1000;");
        $total += $count;
        set_time_limit(600);
        error_log("...$total");
    } while ($count > 0);
} catch (Exception $e) {
    error_log("Failed to delete SQL logs " . $e->getMessage());
}

error_log("Completed");