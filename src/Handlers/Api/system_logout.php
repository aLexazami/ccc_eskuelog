<?php
defined('DOMAIN_PATH') || define('DOMAIN_PATH', dirname(__DIR__, 3));
require_once DOMAIN_PATH . '/config/config.php';
require_once GLOBAL_FUNC;
require_once CL_SESSION_PATH;
require_once CONNECT_PATH;
require_once HELPER;
require_once API_CONNECT;

# Safely target fallback route context
$targerLink = SYSTEM_ACCESS[SYSTEM_FALLBACK]['link']['main'];

