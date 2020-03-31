<?php

ini_set('memory_limit', '256M');
set_time_limit(300);

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

// load Composer
require $app_dir . '/vendor/autoload.php';
// load DB config map
require __DIR__ . '/config.php';

use Yerofey\Replicator\Replicator;
use Yerofey\Replicator\ReplicatorException;
use Yerofey\Replicator\ReplicatorHelper;

if (!isset($config_db_map)) {
    exit();
}

// init helper
$helper = new ReplicatorHelper();

// init DB connections
try {
    $connections = [
        'primary'   => $helper->createConnection($config_db_map[$primary_db_key] ?? []),
        'secondary' => $helper->createConnection($config_db_map[$secondary_db_key] ?? []),
    ];
} catch (ReplicatorException $e) {
    $replicator->saveLog($e->getMessage());
    exit();
}

// init Replicator
$replicator = new Replicator(
    $connections,
    $helper,
    $debug,
    $log_file
);

// run Replicator
try {
    $replicator->run($watch_tables);
} catch (ReplicatorException $e) {
    $replicator->saveLog($e->getMessage());
}
