<?php
require_once DATA_HELPER;

$db_connect = $db_connect ?? null;

## Initialize
$helper = new \Src\Common\dataHelper($db_connect);