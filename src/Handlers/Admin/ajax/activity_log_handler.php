<?php
// src/Handlers/Admin/ajax/activity_log_handler.php

/** @var \mysqli $db_connect */
/** @var \Src\Common\DataHelper $helper */
global $db_connect, $session_class, $g_user_role, $g_user_id, $helper;
$db_connect  = $db_connect ?? null;
$g_user_role = $g_user_role ?? '';

require_once HELPER;
require_once ISLOGIN;
require_once API_CONNECT;

header('Content-Type: application/json');

if (!$db_connect) {
    echo json_encode(['success' => false, 'message' => 'Database connection unavailable.']);
    exit();
}

$user_id = (int)($g_user_id ?? 0);

if ($user_id <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized session.']);
    exit();
}

// Validate active role against SYSTEM_ACCESS and system_access table
$role_map         = SYSTEM_ACCESS[SYSTEM_ACCESS_NAME]['role'] ?? [];
$reverse_role_map = array_map('intval', array_flip($role_map));
$role_int         = $reverse_role_map[$g_user_role] ?? 0;

if ($role_int <= 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid user role.']);
    exit();
}

$hasAccess = $helper->selectDataExists('system_access', [
    'user_id'     => $user_id,
    'system_type' => SYSTEM_ACCESS_NAME,
    'system_role' => $role_int,
    'flag_access' => 0
], ['id']);

if (!$hasAccess) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied for active role.']);
    exit();
}

// Resolve assigned office for ADMIN_STAFF using global $helper
$assigned_office_id = 0;
if ($g_user_role === 'ADMIN_STAFF') {
    $assignRows = $helper->getTableData(
        'staff_assignments',
        ['user_id' => $user_id, 'flag_status' => 0],
        'office_id',
        ['id' => 'DESC'],
        1
    );
    $assigned_office_id = (int)($assignRows[0]['office_id'] ?? 0);

    if ($assigned_office_id <= 0) {
        $empRows = $helper->getTableData('employee', ['user_id' => $user_id], 'office_id', null, 1);
        $assigned_office_id = (int)($empRows[0]['office_id'] ?? 0);
    }
}

$action = $helper->clean($_REQUEST['action'] ?? 'fetch');

if ($action === 'fetch') {
    try {
        $date_from = $helper->clean($_REQUEST['date_from'] ?? '');
        $date_to   = $helper->clean($_REQUEST['date_to'] ?? '');
        $search    = $helper->clean($_REQUEST['search'] ?? '');

        $where  = ["1=1"];
        $params = [];

        if ($helper->validateDate($date_from, 'Y-m-d')) {
            $where[]  = "DATE(al.date_log) >= ?";
            $params[] = $date_from;
        }
        if ($helper->validateDate($date_to, 'Y-m-d')) {
            $where[]  = "DATE(al.date_log) <= ?";
            $params[] = $date_to;
        }

        // Role & user_level scoping
        if ($g_user_role === 'ADMIN') {
            // Unrestricted for ADMIN
        } elseif ($g_user_role === 'ADMIN_STAFF') {
            if ($assigned_office_id > 0) {
                $where[]  = "UPPER(COALESCE(al.user_level, '')) IN ('ADMIN_STAFF', '2') AND (
                    al.user_id = ?
                    OR al.user_id IN (
                        SELECT user_id FROM staff_assignments WHERE office_id = ? AND flag_status = 0 AND user_id > 0
                        UNION
                        SELECT user_id FROM employee WHERE office_id = ? AND user_id > 0
                    )
                )";
                $params[] = $user_id;
                $params[] = $assigned_office_id;
                $params[] = $assigned_office_id;
            } else {
                $where[]  = "al.user_id = ? AND UPPER(COALESCE(al.user_level, '')) IN ('ADMIN_STAFF', '2')";
                $params[] = $user_id;
            }
        } elseif ($g_user_role === 'STATION_ADMIN') {
            $where[]  = "al.user_id = ? AND UPPER(COALESCE(al.user_level, '')) IN ('STATION_ADMIN', '3')";
            $params[] = $user_id;
        } else {
            $where[]  = "al.user_id = ? AND UPPER(COALESCE(al.user_level, '')) IN ('USER', '4')";
            $params[] = $user_id;
        }

        if ($search !== '') {
            $like     = "%{$search}%";
            $where[]  = "(al.action LIKE ? OR al.user_level LIKE ? OR al.session_id LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ? OR sk.system_type LIKE ?)";
            array_push($params, $like, $like, $like, $like, $like, $like);
        }

        $whereSql = implode(" AND ", $where);

        $sql = "
            SELECT 
                al.activity_log_id,
                al.user_id,
                al.date_log,
                al.action,
                al.session_id,
                al.user_level,
                al.system_id,
                COALESCE(NULLIF(TRIM(CONCAT(u.first_name, ' ', u.last_name)), ''), CONCAT('User #', al.user_id)) AS full_name,
                COALESCE(u.email, '') AS email,
                COALESCE(NULLIF(sk.system_type, ''), ?) AS system_type
            FROM activity_log al
            LEFT JOIN users u ON u.id = al.user_id
            LEFT JOIN system_key sk ON sk.system_id = al.system_id
            WHERE {$whereSql}
            ORDER BY al.date_log DESC, al.activity_log_id DESC
            LIMIT 1000
        ";

        array_unshift($params, SYSTEM_ACCESS_NAME);
        $rows = $helper->selectData($sql, $params);

        echo json_encode(['success' => true, 'data' => $rows]);
        exit();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to load activity logs: ' . $e->getMessage()]);
        exit();
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid action.']);