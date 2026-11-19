<?php
// src/Handlers/Admin/ajax/activity_log_handler.php

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
        $date_from = trim($_REQUEST['date_from'] ?? '');
        $date_to   = trim($_REQUEST['date_to'] ?? '');
        $search    = trim($_REQUEST['search'] ?? '');

        $where  = ["1=1"];
        $params = [];
        $types  = "";

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
            $where[]  = "DATE(al.date_log) >= ?";
            $params[] = $date_from;
            $types   .= "s";
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
            $where[]  = "DATE(al.date_log) <= ?";
            $params[] = $date_to;
            $types   .= "s";
        }

        // Role-based scoping:
        // ADMIN sees all logs; ADMIN_STAFF sees their office/own logs; STATION_ADMIN & USER see only their own logs
        if ($g_user_role_upper === 'ADMIN_STAFF') {
            if ($assigned_office_id > 0) {
                $where[]  = "(al.user_id = ? OR al.user_id IN (
                    SELECT user_id FROM staff_assignments WHERE office_id = ? AND flag_status = 0
                    UNION
                    SELECT user_id FROM employee WHERE office_id = ?
                ))";
                $params[] = $user_id;
                $params[] = $assigned_office_id;
                $params[] = $assigned_office_id;
                $types   .= "iii";
            } else {
                $where[]  = "al.user_id = ?";
                $params[] = $user_id;
                $types   .= "i";
            }
        } elseif ($g_user_role_upper !== 'ADMIN') {
            $where[]  = "al.user_id = ?";
            $params[] = $user_id;
            $types   .= "i";
        }

        if ($search !== '') {
            $like     = "%{$search}%";
            $where[]  = "(al.action LIKE ? OR al.user_level LIKE ? OR al.session_id LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ? OR sk.system_type LIKE ?)";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $types   .= "ssssss";
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
                COALESCE(NULLIF(sk.system_type, ''), 'E-SKUELOG') AS system_type
            FROM activity_log al
            LEFT JOIN users u ON u.id = al.user_id
            LEFT JOIN system_key sk ON sk.system_id = al.system_id
            WHERE {$whereSql}
            ORDER BY al.date_log DESC, al.activity_log_id DESC
            LIMIT 1000
        ";

        $stmt = $db_connect->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        echo json_encode(['success' => true, 'data' => $rows]);
        exit();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to load activity logs: ' . $e->getMessage()]);
        exit();
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid action.']);