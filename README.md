# Replicator
A library to create MySQL Database replication.

## Features
- [x] Cloning table columns
- [x] Cloning table indexes
- [x] Cloning table data

## Why not use a default built-in replication methods
The default replication has some weak points:
* It creates a big binlog file (if you have a big database) - so you need a large amount of disk space to handle those binlogs
* It does not supports the triggers - triggers will be saved as SQL-query in binlog, but if you want to have a copy of just a few tables - some data will be incorrect.

## How to setup
### First of all you'll need a Composer
If you don't have it yet installed, - checkout [this guide](https://getcomposer.org/download/).
### Then add the library to your project
```bash
composer require yerofey/replicator
```
### If you want to replicate on the same server:  
  1. You can create a worker that will do the job for example every minute (examples/worker.php)
It can be setted up with crontab.  
  2. You can create a daemon that will work always and do the job every n seconds (examples/daemon.php)
### If you want to replicate on another server:  
On secondary server (Linux):
```bash
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf
```
Navigate to the line that begins with the bind-address directive. By default, this value is set to 127.0.0.1, meaning that the server will only look for local connections. You will need to change this directive to reference an external IP address. For the purposes of troubleshooting, you could set this directive to a wildcard IP address, either *, ::, or 0.0.0.0:  
```
bind-address            = 0.0.0.0
```
After changing this line, save and close the file (CTRL + X, Y, then ENTER if you edited it with nano).

Assuming you’ve configured a firewall on your database server, you will also need to open port 3306 — MySQL’s default port — to allow traffic to MySQL.
If you only plan to access the database server from one specific machine, you can grant that machine exclusive permission to connect to the database remotely with the following command. Make sure to replace remote_IP_address with the actual IP address of the machine you plan to connect with:
```bash
sudo ufw allow from REMOTE_IP_ADDRESS to any port 3306
```

Alternatively, you can allow connections to your MySQL database from any IP address with the following command:
```bash
sudo ufw allow 3306
```

Lastly, restart the MySQL service to put the changes you made to mysqld.cnf into effect:
```bash
sudo systemctl restart mysql
```

MySQL setup guide source: https://www.digitalocean.com/community/tutorials/how-to-allow-remote-access-to-mysql
### Then create a folder for a worker scripts
```bash
mkdir db-replicator
cd db-replicator
```
### Then create a DB configuration file
```bash
touch config.php
```
### config.php example
```php
<?php

$config_db_map = [
    'main' => [
        'hostname' => 'localhost',
        'database' => 'main',
        'username' => 'root',
        'password' => '',
    ],
    'test' => [
        'hostname' => 'localhost', // can be remote IP address
        'database' => 'test',
        'username' => 'root',
        'password' => '',
    ],
];
```
### Then create a worker script (one time job)
```php
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

```
### Or create a daemon (that will work always)
```php
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
while (true) {
    $time_start = microtime(true);

    try {
        $replicator->run($watch_tables);
    } catch (ReplicatorException $e) {
        $replicator->saveLog($e->getMessage());
    }

    $sleep = 0;
    $runtime = microtime(true) - $time_start;
    if ($runtime < $interval_seconds) {
        $sleep = $interval_seconds - $runtime;
    }

    sleep($sleep);
}

```
### Then just run your script
If you've done everything right replication should now work.

## Author
[Yerofey S.](https://github.com/yerofey)

## License
This library licensed under [MIT](https://github.com/yerofey/replicator/blob/master/LICENSE).
