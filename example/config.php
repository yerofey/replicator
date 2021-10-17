<?php

require __DIR__ . '/vendor/autoload.php';

// load variables from ".env" file
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

if (!isset($_ENV['DB_REPLICATION_IS_ENABLED']) || $_ENV['DB_REPLICATION_IS_ENABLED'] == false) {
  exit();
}

$config_db_map = [
  'primary' => [
    'hostname' => $_ENV['DB_PRIMARY_HOST'],
    'database' => $_ENV['DB_PRIMARY_NAME'],
    'username' => $_ENV['DB_PRIMARY_USER'],
    'password' => $_ENV['DB_PRIMARY_PASS'],
  ],
  'secondary' => [
    'hostname' => $_ENV['DB_SECONDARY_HOST'],
    'database' => $_ENV['DB_SECONDARY_NAME'],
    'username' => $_ENV['DB_SECONDARY_USER'],
    'password' => $_ENV['DB_SECONDARY_PASS'],
  ],
];
