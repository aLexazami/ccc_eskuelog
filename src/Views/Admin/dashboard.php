<?php
# Core Config & Session Setup
global $db_connect;
if (!$db_connect && defined('CONNECT_PATH')) {
    require_once CONNECT_PATH;
}

$g_user_role = strtoupper(trim($g_user_role ?? ''));

# Dispatch to role-specific partial views
switch ($g_user_role) {
    case 'ADMIN':
        require_once __DIR__ . '/partials/admin_dashboard.php';
        break;

    case 'ADMIN_STAFF':
        require_once __DIR__ . '/partials/staff_dashboard.php';
        break;

    case 'STATION_ADMIN':
        require_once __DIR__ . '/partials/station_dashboard.php';
        break;

    default:
        http_response_code(403);
        header("Location: " . BASE_URL . "admin/sign-out");
        exit();
}