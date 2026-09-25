<?php
require_once API_HELPER;

## Ensure database connection exists safely
if (!isset($db_connect)) $db_connect = $db_connect ?? null; 

## Initialize with Main System
$api = new \Src\Api\ApiHelper($db_connect, SYSTEM_ACCESS_NAME);

$g_system_key = $api->get_system_key();
$g_public_key = $api->get_public_key();
$g_secret_key = $api->get_secret_key();

## Switch to a Second System Login on the fly
// $api->setKeys('SECONDARY_SYSTEM_NAME');
// $second_system_key = $api->get_system_key();
// $second_public_key = $api->get_public_key();
// $second_secret_key = $api->get_secret_key();


// $api->setKeys(); ## No arguments reset it right back to your core global setup