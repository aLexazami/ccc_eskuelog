<?php

/** 
 * Location: /src/Handlers/Admin/login_access.php
 */

# Rely on $g_user_role_upper populated by index.php
$role = $g_user_role_upper ?? '';

switch ($role) {
    case 'SUPER_ADMIN':
        header("Location: " . BASE_URL . "admin/user-information");
        exit();

    case 'ADMIN':
        header("Location: " . BASE_URL . "admin/user-information");
        exit();

    case 'ADMIN_STAFF':
        header("Location: " . BASE_URL . "admin/user-information");
        exit();

    case 'USER':
        header("Location: " . BASE_URL . "admin/user-information");
        exit();

    default:
        header("Location: " . API_URL);
        exit();
}
