<?php
// src/Handlers/Admin/ajax/transaction_records_handler.php

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

// 3. Resolve Staff Office Assignment (if ADMIN_STAFF)
$assigned_office_id = 0;
if ($g_user_role_upper === 'ADMIN_STAFF' && $user_id > 0) {
    // Priority 1: Check staff_assignments table
    $stmtAssign = $db_connect->prepare("
        SELECT office_id 
        FROM staff_assignments 
        WHERE user_id = ? AND flag_status = 0 
        ORDER BY id DESC LIMIT 1
    ");
    if ($stmtAssign) {
        $stmtAssign->bind_param("i", $user_id);
        $stmtAssign->execute();
        $assignRes = $stmtAssign->get_result()->fetch_assoc();
        $assigned_office_id = (int)($assignRes['office_id'] ?? 0);
        $stmtAssign->close();
    }

    // Priority 2: Fallback to employee table
    if ($assigned_office_id <= 0) {
        $stmtEmp = $db_connect->prepare("SELECT office_id FROM employee WHERE user_id = ? LIMIT 1");
        if ($stmtEmp) {
            $stmtEmp->bind_param("i", $user_id);
            $stmtEmp->execute();
            $empRow = $stmtEmp->get_result()->fetch_assoc();
            $assigned_office_id = (int)($empRow['office_id'] ?? 0);
            $stmtEmp->close();
        }
    }
}

$action = $_POST['action'] ?? '';

switch ($action) {

    case 'fetch_records':
        try {
            $office_id    = (int)($_POST['office_id'] ?? 0);
            $visitor_type = trim($_POST['visitor_type'] ?? '');
            $status       = trim($_POST['status'] ?? '');
            $date_from    = trim($_POST['date_from'] ?? date('Y-m-d'));
            $date_to      = trim($_POST['date_to'] ?? date('Y-m-d'));

            // Enforce staff assigned office if user is ADMIN_STAFF
            if ($g_user_role_upper === 'ADMIN_STAFF' && $assigned_office_id > 0) {
                $office_id = $assigned_office_id;
            }

            $whereClauses = ["1=1"];
            $params       = [];
            $paramTypes   = "";

            if ($office_id > 0) {
                $whereClauses[] = "q.office_id = ?";
                $params[]       = $office_id;
                $paramTypes    .= "i";
            }

            if (!empty($visitor_type)) {
                $whereClauses[] = "UPPER(TRIM(l.visitor_type)) = ?";
                $params[]       = strtoupper($visitor_type);
                $paramTypes    .= "s";
            }

            if (!empty($status)) {
                $whereClauses[] = "q.status = ?";
                $params[]       = strtoupper($status);
                $paramTypes    .= "s";
            }

            if (!empty($date_from) && !empty($date_to)) {
                $whereClauses[] = "q.queue_date BETWEEN ? AND ?";
                $params[]       = $date_from;
                $params[]       = $date_to;
                $paramTypes    .= "ss";
            }

            $whereSql = implode(" AND ", $whereClauses);

            // Fetch base tickets with logbook and station metadata
            $dataSql = "
                SELECT 
                    q.id,
                    q.logbook_id,
                    q.ticket_number,
                    q.queue_date,
                    q.status,
                    q.is_priority,
                    q.called_at,
                    q.served_at,
                    q.completed_at,
                    l.created_at,
                    l.visitor_type,
                    l.first_name,
                    l.middle_name,
                    l.last_name,
                    l.suffix,
                    l.post_nominal,
                    l.other_concern_text,
                    o.office_name,
                    c.counter_name,
                    tt_main.transaction_name AS transaction_name
                FROM queue_tickets q
                INNER JOIN logbooks l ON q.logbook_id = l.id
                LEFT JOIN office o ON q.office_id = o.office_id
                LEFT JOIN counters c ON q.counter_id = c.id
                LEFT JOIN transaction_types tt_main ON l.transaction_type_id = tt_main.id
                WHERE {$whereSql}
                ORDER BY q.id DESC
            ";

            $stmtData = $db_connect->prepare($dataSql);
            if (!empty($params)) {
                $stmtData->bind_param($paramTypes, ...$params);
            }
            $stmtData->execute();
            $res = $stmtData->get_result();

            $records = [];
            while ($row = $res->fetch_assoc()) {
                $m_init = !empty($row['middle_name']) ? ' ' . substr($row['middle_name'], 0, 1) . '.' : '';
                $suf    = !empty($row['suffix']) ? ' ' . $row['suffix'] : '';
                $post   = !empty($row['post_nominal']) ? ', ' . $row['post_nominal'] : '';
                $row['full_name'] = trim($row['first_name'] . $m_init . ' ' . $row['last_name'] . $suf . $post);

                // Fetch linked transaction items and sub-concerns per logbook entry
                $concerns_list = [];
                $stmtItems = $db_connect->prepare("
                    SELECT 
                        tt.transaction_name,
                        tsc.sub_concern_name
                    FROM logbook_transaction_items lti
                    LEFT JOIN transaction_types tt ON lti.transaction_type_id = tt.id
                    LEFT JOIN transaction_sub_concerns tsc ON lti.sub_concern_id = tsc.id
                    WHERE lti.logbook_id = ?
                    ORDER BY lti.id ASC
                ");
                if ($stmtItems) {
                    $stmtItems->bind_param("i", $row['logbook_id']);
                    $stmtItems->execute();
                    $resItems = $stmtItems->get_result();
                    while ($item = $resItems->fetch_assoc()) {
                        $concerns_list[] = $item;
                    }
                    $stmtItems->close();
                }
                $row['concerns_list'] = $concerns_list;

                $records[] = $row;
            }
            if (isset($stmtData) && is_object($stmtData)) {
                $stmtData->close();
            }

            echo json_encode([
                'success' => true,
                'data'    => $records
            ]);
            exit();

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Fetch error: ' . $e->getMessage()]);
            exit();
        }

    case 'fetch_ticket_details':
        $ticket_id = (int)($_POST['ticket_id'] ?? 0);
        if ($ticket_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid ticket ID.']);
            exit();
        }

        try {
            $stmtDetails = $db_connect->prepare("
                SELECT 
                    q.id,
                    q.logbook_id,
                    q.ticket_number,
                    q.queue_date,
                    q.status,
                    q.called_at,
                    q.served_at,
                    q.completed_at,
                    l.created_at,
                    l.visitor_type,
                    l.first_name,
                    l.middle_name,
                    l.last_name,
                    l.suffix,
                    l.post_nominal,
                    l.sex,
                    l.other_concern_text,
                    COALESCE(NULLIF(l.email, ''), u.email, 'N/A') AS email,
                    o.office_name,
                    c.counter_name,
                    tt_main.transaction_name AS transaction_name,
                    CONCAT(su.first_name, ' ', su.last_name) AS served_by_name
                FROM queue_tickets q
                INNER JOIN logbooks l ON q.logbook_id = l.id
                LEFT JOIN users u ON l.user_id = u.id
                LEFT JOIN office o ON q.office_id = o.office_id
                LEFT JOIN counters c ON q.counter_id = c.id
                LEFT JOIN transaction_types tt_main ON l.transaction_type_id = tt_main.id
                LEFT JOIN users su ON q.served_by_user_id = su.id
                WHERE q.id = ?
                LIMIT 1
            ");
            $stmtDetails->bind_param("i", $ticket_id);
            $stmtDetails->execute();
            $details = $stmtDetails->get_result()->fetch_assoc();
            $stmtDetails->close();

            if ($details) {
                $m_init = !empty($details['middle_name']) ? ' ' . substr($details['middle_name'], 0, 1) . '.' : '';
                $suf    = !empty($details['suffix']) ? ' ' . $details['suffix'] : '';
                $post   = !empty($details['post_nominal']) ? ', ' . $details['post_nominal'] : '';
                $details['full_name'] = trim($details['first_name'] . $m_init . ' ' . $details['last_name'] . $suf . $post);

                // Fetch linked transaction items and sub-concerns for modal details
                $concerns_list = [];
                $stmtItems = $db_connect->prepare("
                    SELECT 
                        tt.transaction_name,
                        tsc.sub_concern_name
                    FROM logbook_transaction_items lti
                    LEFT JOIN transaction_types tt ON lti.transaction_type_id = tt.id
                    LEFT JOIN transaction_sub_concerns tsc ON lti.sub_concern_id = tsc.id
                    WHERE lti.logbook_id = ?
                    ORDER BY lti.id ASC
                ");
                if ($stmtItems) {
                    $stmtItems->bind_param("i", $details['logbook_id']);
                    $stmtItems->execute();
                    $resItems = $stmtItems->get_result();
                    while ($item = $resItems->fetch_assoc()) {
                        $concerns_list[] = $item;
                    }
                    $stmtItems->close();
                }
                $details['concerns_list'] = $concerns_list;

                echo json_encode(['success' => true, 'data' => $details]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Ticket not found.']);
            }
            exit();

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Details fetch error: ' . $e->getMessage()]);
            exit();
        }

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        exit();
}