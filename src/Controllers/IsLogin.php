<?php
require_once APP_CONTEXT;

$db_connect    = $db_connect ?? null;
$session_class = $session_class ?? null;

# Initialize the unified AppContext container
$app = new \Src\Common\AppContext($db_connect, $session_class);

# Validate authentication session
$app->requireLogin();

defined('LOGIN_AUTH') || define('LOGIN_AUTH', true);

# Extract global scope user variables
$g_token_id            = $app->get_token_id();
$g_device              = $app->get_device();
$g_ip                  = $app->get_ip();
$g_user_id             = $app->get_user_id();
$g_system_role         = $app->get_system_role();
$g_user_role           = $app->get_user_role();
$g_user_role_id        = $app->get_user_role_id();
$g_fullname            = $app->get_full_name();
$g_username            = $app->get_username();
$g_email_address       = $app->get_email_address();
$g_recovery_email      = $app->get_recovery_email();
$g_photo               = $app->get_photo();
$g_initials            = $app->get_initials();
$g_browser_fingerprint = $app->get_fingerprint();