<?php
require_once API_SYSTEM_HELPER;

## Ensure database connection exists safely
if (!isset($db_connect)) $db_connect = $db_connect ?? null;

## Initialize function
$api_controller = new \Src\Api\ApiSystemHelper($db_connect);
