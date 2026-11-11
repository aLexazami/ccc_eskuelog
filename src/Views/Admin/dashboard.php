<?php
# Core Config & Session Setup
$db_connect  = $db_connect ?? null;
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
        // Fallback for unauthorized roles
        http_response_code(403);
        header("Location: " . BASE_URL . "admin/sign-out");
        exit();
}