<?php
// src/Handlers/Admin/ajax/live_queue_handler.php

/** @var \mysqli $db_connect */
global $db_connect, $session_class;
$db_connect = $db_connect ?? null;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!$db_connect) {
    echo json_encode(['success' => false, 'message' => 'Database connection unavailable.']);
    exit();
}

// 1. Resolve User ID
$user_id = 0;
if (isset($session_class) && method_exists($session_class, 'getValue')) {
    $user_id = (int)(
        $session_class->getValue('user_id') ?? 
        $session_class->getValue('id') ?? 
        $session_class->getValue('sys_user_id') ?? 
        0
    );
}
if ($user_id <= 0) {
    $user_id = (int)($_SESSION['user_id'] ?? $_SESSION['id'] ?? $_SESSION['sys_user_id'] ?? 0);
}

// 2. Resolve User Role
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

// 3. Resolve Assigned Office ID for ADMIN_STAFF
$assigned_office_id = 0;
if ($g_user_role_upper === 'ADMIN_STAFF' && $user_id > 0) {
    // Priority 1: Check staff_assignments table
    $stmtStaff = $db_connect->prepare("
        SELECT office_id 
        FROM staff_assignments 
        WHERE user_id = ? AND flag_status = 0 
        ORDER BY (shift_status = 'ON_DUTY') DESC, id DESC 
        LIMIT 1
    ");
    if ($stmtStaff) {
        $stmtStaff->bind_param("i", $user_id);
        $stmtStaff->execute();
        $staffRow = $stmtStaff->get_result()->fetch_assoc();
        if ($staffRow) {
            $assigned_office_id = (int)$staffRow['office_id'];
        }
        $stmtStaff->close();
    }

    // Priority 2: Fallback check on employee table
    if ($assigned_office_id <= 0) {
        $stmtEmp = $db_connect->prepare("SELECT office_id FROM employee WHERE user_id = ? LIMIT 1");
        if ($stmtEmp) {
            $stmtEmp->bind_param("i", $user_id);
            $stmtEmp->execute();
            $empRow = $stmtEmp->get_result()->fetch_assoc();
            if ($empRow) {
                $assigned_office_id = (int)$empRow['office_id'];
            }
            $stmtEmp->close();
        }
    }
}

$action = $_POST['action'] ?? '';
$today  = date('Y-m-d');

switch ($action) {

    case 'fetch_master_queue':
        try {
            $queue_mode = $_POST['queue_mode'] ?? 'all';
            
            // Filter by visitor type based on screen mode
            $visitor_filter = "";
            if ($queue_mode === 'student_guest') {
                $visitor_filter = " AND l.visitor_type IN ('STUDENT', 'GUEST') ";
            } else if ($queue_mode === 'employee') {
                $visitor_filter = " AND l.visitor_type = 'EMPLOYEE' ";
            }

            // Fetch active offices (Restricted for ADMIN_STAFF, All offices for ADMIN/STATION_ADMIN)
            if ($g_user_role_upper === 'ADMIN_STAFF' && $assigned_office_id > 0) {
                $stmtOffices = $db_connect->prepare("SELECT office_id, office_code, office_name, office_logo FROM office WHERE flag_status = 0 AND office_id = ? ORDER BY office_name ASC");
                $stmtOffices->bind_param("i", $assigned_office_id);
                $stmtOffices->execute();
                $officesRes = $stmtOffices->get_result();
            } else {
                $officesRes = $db_connect->query("SELECT office_id, office_code, office_name, office_logo FROM office WHERE flag_status = 0 ORDER BY office_name ASC");
            }

            $active_offices = [];

            while ($off = $officesRes->fetch_assoc()) {
                $cur_office_id = (int)$off['office_id'];

                // 1. Fetch currently CALLED or SERVING ticket + counter name
                $stmtNow = $db_connect->prepare("
                    SELECT 
                        q.ticket_number, 
                        q.status, 
                        q.is_priority, 
                        q.called_at, 
                        l.visitor_type,
                        q.counter_id,
                        COALESCE(c.counter_name, '') AS counter_name
                    FROM queue_tickets q
                    INNER JOIN logbooks l ON q.logbook_id = l.id
                    LEFT JOIN counters c ON q.counter_id = c.id
                    WHERE q.office_id = ? AND q.queue_date = ? AND q.status IN ('CALLED', 'SERVING') {$visitor_filter}
                    ORDER BY q.called_at DESC, q.id DESC
                    LIMIT 1
                ");
                $stmtNow->bind_param("is", $cur_office_id, $today);
                $stmtNow->execute();
                $now_serving = $stmtNow->get_result()->fetch_assoc();
                $stmtNow->close();

                // 2. Fetch up next waiting tickets (Limit 4 for pillar display)
                $stmtNext = $db_connect->prepare("
                    SELECT q.ticket_number, q.is_priority, l.visitor_type
                    FROM queue_tickets q
                    INNER JOIN logbooks l ON q.logbook_id = l.id
                    WHERE q.office_id = ? AND q.queue_date = ? AND q.status = 'WAITING' {$visitor_filter}
                    ORDER BY q.is_priority DESC, q.queue_sequence ASC
                    LIMIT 4
                ");
                $stmtNext->bind_param("is", $cur_office_id, $today);
                $stmtNext->execute();
                $resNext = $stmtNext->get_result();
                $up_next = [];
                while ($nextRow = $resNext->fetch_assoc()) {
                    $up_next[] = $nextRow;
                }
                $stmtNext->close();

                // 3. Count total waiting tickets
                $stmtCnt = $db_connect->prepare("
                    SELECT COUNT(*) as cnt
                    FROM queue_tickets q
                    INNER JOIN logbooks l ON q.logbook_id = l.id
                    WHERE q.office_id = ? AND q.queue_date = ? AND q.status = 'WAITING' {$visitor_filter}
                ");
                $stmtCnt->bind_param("is", $cur_office_id, $today);
                $stmtCnt->execute();
                $waiting_count = (int)($stmtCnt->get_result()->fetch_assoc()['cnt'] ?? 0);
                $stmtCnt->close();

                // Include offices that have active tickets or queued visitors (or force display for single assigned office)
                if ($now_serving || count($up_next) > 0 || $waiting_count > 0 || ($g_user_role_upper === 'ADMIN_STAFF' && $assigned_office_id > 0)) {
                    $active_offices[] = [
                        'office_id'     => $off['office_id'],
                        'office_code'   => $off['office_code'],
                        'office_name'   => $off['office_name'],
                        'office_logo'   => $off['office_logo'],
                        'now_serving'   => $now_serving,
                        'up_next'       => $up_next,
                        'waiting_count' => $waiting_count
                    ];
                }
            }

            if (isset($stmtOffices) && is_object($stmtOffices)) {
                $stmtOffices->close();
            }

            echo json_encode([
                'success' => true,
                'data'    => [
                    'active_offices' => $active_offices
                ]
            ]);
            exit();

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Master queue fetch error: ' . $e->getMessage()]);
            exit();
        }

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action for live queue display.']);
        exit();
}