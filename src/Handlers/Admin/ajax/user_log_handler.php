<?php
// src/Handlers/Admin/ajax/user_log_handler.php

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

$role_labels = [
    1 => 'Administrator',
    2 => 'Admin Staff',
    3 => 'Station Admin',
    4 => 'User'
];

$action = $helper->clean($_REQUEST['action'] ?? 'fetch');

if ($action === 'fetch') {
    try {
        $date_from  = $helper->clean($_REQUEST['date_from'] ?? '');
        $date_to    = $helper->clean($_REQUEST['date_to'] ?? '');
        $login_flag = $helper->clean($_REQUEST['login_flag'] ?? '');
        $search     = $helper->clean($_REQUEST['search'] ?? '');

        $where  = ["1=1"];
        $params = [];

        if ($helper->validateDate($date_from, 'Y-m-d')) {
            $where[]  = "DATE(ul.login_date) >= ?";
            $params[] = $date_from;
        }
        if ($helper->validateDate($date_to, 'Y-m-d')) {
            $where[]  = "DATE(ul.login_date) <= ?";
            $params[] = $date_to;
        }
        if ($login_flag !== '' && in_array($login_flag, ['0', '1'], true)) {
            $where[]  = "ul.login_flag = ?";
            $params[] = (int)$login_flag;
        }

        // Role & user_level scoping (aligned with activity_log_handler.php)
        if ($g_user_role === 'ADMIN') {
            // Unrestricted for ADMIN
        } elseif ($g_user_role === 'ADMIN_STAFF') {
            if ($assigned_office_id > 0) {
                $where[]  = "CAST(ul.user_level AS UNSIGNED) = 2 AND (
                    CAST(ul.user_id AS UNSIGNED) = ?
                    OR CAST(ul.user_id AS UNSIGNED) IN (
                        SELECT user_id FROM staff_assignments WHERE office_id = ? AND flag_status = 0 AND user_id > 0
                        UNION
                        SELECT user_id FROM employee WHERE office_id = ? AND user_id > 0
                    )
                )";
                $params[] = $user_id;
                $params[] = $assigned_office_id;
                $params[] = $assigned_office_id;
            } else {
                $where[]  = "CAST(ul.user_id AS UNSIGNED) = ? AND CAST(ul.user_level AS UNSIGNED) = 2";
                $params[] = $user_id;
            }
        } elseif ($g_user_role === 'STATION_ADMIN') {
            $where[]  = "CAST(ul.user_id AS UNSIGNED) = ? AND CAST(ul.user_level AS UNSIGNED) = 3";
            $params[] = $user_id;
        } else {
            $where[]  = "CAST(ul.user_id AS UNSIGNED) = ? AND CAST(ul.user_level AS UNSIGNED) = 4";
            $params[] = $user_id;
        }

        if ($search !== '') {
            $like     = "%{$search}%";
            $where[]  = "(ul.ip_address LIKE ? OR ul.action LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ?)";
            array_push($params, $like, $like, $like, $like);
        }

        $whereSql = implode(" AND ", $where);

        $sql = "
            SELECT 
                ul.user_log_id,
                ul.user_id,
                ul.login_date,
                ul.logout_date,
                ul.action,
                ul.session_id,
                ul.ip_address,
                ul.login_flag,
                ul.user_level,
                COALESCE(NULLIF(TRIM(CONCAT(u.first_name, ' ', u.last_name)), ''), CONCAT('User #', ul.user_id)) AS full_name,
                COALESCE(u.email, '') AS email
            FROM user_log ul
            LEFT JOIN users u ON u.id = CAST(ul.user_id AS UNSIGNED)
            WHERE {$whereSql}
            ORDER BY ul.login_date DESC, ul.user_log_id DESC
            LIMIT 1000
        ";

        $rawRows = $helper->selectData($sql, $params);

        $rows = [];
        foreach ($rawRows as $r) {
            $events = json_decode($r['session_id'] ?? '[]', true);
            if (!is_array($events)) {
                $events = [];
            }

            $levelInt            = (int)($r['user_level'] ?? 0);
            $r['role_label']     = $role_labels[$levelInt] ?? 'Unknown';
            $r['session_events'] = $events;
            $r['event_count']    = count($events);
            unset($r['session_id']);

            $rows[] = $r;
        }

        echo json_encode(['success' => true, 'data' => $rows]);
        exit();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to load user logs: ' . $e->getMessage()]);
        exit();
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid action.']);