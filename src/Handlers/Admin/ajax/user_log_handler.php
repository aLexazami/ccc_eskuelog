<?php
// src/Handlers/Admin/ajax/user_log_handler.php

/** @var \mysqli $db_connect */
global $db_connect, $session_class;
$db_connect = $db_connect ?? null;

header('Content-Type: application/json');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!$db_connect) {
    echo json_encode(['success' => false, 'message' => 'Database connection unavailable.']);
    exit();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Resolve current user & role
$user_id = 0;
if (isset($session_class) && method_exists($session_class, 'getValue')) {
    $user_id = (int)($session_class->getValue('user_id') ?? $session_class->getValue('id') ?? $session_class->getValue('sys_user_id') ?? 0);
}
if ($user_id <= 0) {
    $user_id = (int)($_SESSION['user_id'] ?? $_SESSION['id'] ?? $_SESSION['sys_user_id'] ?? 0);
}

$g_user_role = '';
if (isset($session_class) && method_exists($session_class, 'getValue')) {
    $g_user_role = $session_class->getValue('user_role') ?? $session_class->getValue('system_role') ?? '';
}
if (empty($g_user_role)) {
    $g_user_role = $_SESSION['user_role'] ?? $_SESSION['g_user_role'] ?? $_SESSION['role'] ?? '';
}

$role_map = ['1' => 'ADMIN', '2' => 'ADMIN_STAFF', '3' => 'STATION_ADMIN', '4' => 'USER'];
$role_labels = [
    1 => 'Administrator',
    2 => 'Admin Staff',
    3 => 'Station Admin',
    4 => 'User'
];

if (isset($role_map[(string)$g_user_role])) {
    $g_user_role = $role_map[(string)$g_user_role];
}
$g_user_role_upper = strtoupper(trim((string)$g_user_role));

// Resolve assigned office for ADMIN_STAFF scoping
$assigned_office_id = 0;
if ($g_user_role_upper === 'ADMIN_STAFF' && $user_id > 0) {
    $stmtAssign = $db_connect->prepare("SELECT office_id FROM staff_assignments WHERE user_id = ? AND flag_status = 0 ORDER BY id DESC LIMIT 1");
    $stmtAssign->bind_param("i", $user_id);
    $stmtAssign->execute();
    $res = $stmtAssign->get_result()->fetch_assoc();
    $assigned_office_id = (int)($res['office_id'] ?? 0);
    $stmtAssign->close();

    if ($assigned_office_id <= 0) {
        $stmtEmp = $db_connect->prepare("SELECT office_id FROM employee WHERE user_id = ? LIMIT 1");
        $stmtEmp->bind_param("i", $user_id);
        $stmtEmp->execute();
        $resEmp = $stmtEmp->get_result()->fetch_assoc();
        $assigned_office_id = (int)($resEmp['office_id'] ?? 0);
        $stmtEmp->close();
    }
}

$action = $_REQUEST['action'] ?? 'fetch';

if ($action === 'fetch') {
    try {
        $date_from  = trim($_REQUEST['date_from'] ?? '');
        $date_to    = trim($_REQUEST['date_to'] ?? '');
        $login_flag = trim($_REQUEST['login_flag'] ?? '');

        $where  = ["1=1"];
        $params = [];
        $types  = "";

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
            $where[]  = "DATE(ul.login_date) >= ?";
            $params[] = $date_from;
            $types   .= "s";
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
            $where[]  = "DATE(ul.login_date) <= ?";
            $params[] = $date_to;
            $types   .= "s";
        }
        if ($login_flag !== '' && in_array($login_flag, ['0', '1'], true)) {
            $where[]  = "ul.login_flag = ?";
            $params[] = (int)$login_flag;
            $types   .= "i";
        }

        // Role-based scoping:
        // ADMIN sees all logs; ADMIN_STAFF sees their office/own logs; STATION_ADMIN & USER see only their own logs
        if ($g_user_role_upper === 'ADMIN_STAFF') {
            if ($assigned_office_id > 0) {
                $where[]  = "(CAST(ul.user_id AS UNSIGNED) = ? OR CAST(ul.user_id AS UNSIGNED) IN (
                    SELECT user_id FROM staff_assignments WHERE office_id = ? AND flag_status = 0
                    UNION
                    SELECT user_id FROM employee WHERE office_id = ?
                ))";
                $params[] = $user_id;
                $params[] = $assigned_office_id;
                $params[] = $assigned_office_id;
                $types   .= "iii";
            } else {
                $where[]  = "CAST(ul.user_id AS UNSIGNED) = ?";
                $params[] = $user_id;
                $types   .= "i";
            }
        } elseif ($g_user_role_upper !== 'ADMIN') {
            $where[]  = "CAST(ul.user_id AS UNSIGNED) = ?";
            $params[] = $user_id;
            $types   .= "i";
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

        $stmt = $db_connect->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $rawRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $rows = [];
        foreach ($rawRows as $r) {
            $events = json_decode($r['session_id'] ?? '[]', true);
            if (!is_array($events)) {
                $events = [];
            }

            $levelInt = (int)($r['user_level'] ?? 0);
            $r['role_label']     = $role_labels[$levelInt] ?? 'Unknown';
            $r['session_events'] = $events;
            $r['event_count']    = count($events);
            unset($r['session_id']); // avoid sending large raw JSON string twice

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