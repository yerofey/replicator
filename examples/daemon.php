<?php

ini_set('memory_limit', '256M');
set_time_limit(0);

// your app root location
$app_dir = __DIR__ . '/..';

// log actions in "/replicator_log.txt"
$debug = true;
$log_file = $app_dir . '/replicator_log.txt';

// master table config key from "config.php"
$primary_db_key = 'main';
// slave table config key from "config.php"
$secondary_db_key = 'test';

// tables to watch
$watch_tables = [
    'test_table',
];

// watch interval
$interval_seconds = 10;

// load Composer
require $app_dir . '/vendor/autoload.php';
// load DB config map
require __DIR__ . '/config.php';

use yerofey\Replicator,
    yerofey\ReplicatorException,
    yerofey\ReplicatorHelpers;


define('REPLICATOR_APP_ROOT', $app_dir);
define('REPLICATOR_DEBUG', $debug);
define('REPLICATOR_LOGFILE', $log_file);


if (!isset($config_db_map)) {
    Replicator::saveLog('Error: $config_db_map is not defined!');
    exit();
}


try {
    $databases = [
        'primary'   => ReplicatorHelpers::createConnection($config_db_map[$primary_db_key] ?? []),
        'secondary' => ReplicatorHelpers::createConnection($config_db_map[$secondary_db_key] ?? []),
    ];
} catch (ReplicatorException $e) {
    Replicator::saveLog($e->getMessage());
    exit();
}


while (true) {
    $time_start = microtime(true);

    try {
        Replicator::run($databases, $watch_tables);
    } catch (ReplicatorException $e) {
        Replicator::saveLog($e->getMessage());
    }

    $sleep = 0;
    $runtime = microtime(true) - $time_start;
    if ($runtime < $interval_seconds) {
        $sleep = $interval_seconds - $runtime;
    }

    sleep($sleep);
}
