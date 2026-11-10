<?php
// src/Handlers/Admin/ajax/queue_handler.php

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

if ($user_id <= 0 && session_id()) {
    $current_sess_id = session_id();
    $stmtSess = $db_connect->prepare("SELECT user_id FROM user_log WHERE session_id = ? OR token_id LIKE ? ORDER BY user_log_id DESC LIMIT 1");
    if ($stmtSess) {
        $sess_like = '%' . $current_sess_id . '%';
        $stmtSess->bind_param("ss", $current_sess_id, $sess_like);
        $stmtSess->execute();
        $sessRes = $stmtSess->get_result()->fetch_assoc();
        if ($sessRes) {
            $user_id = (int)$sessRes['user_id'];
        }
    }
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

if (empty($g_user_role) && $db_connect && $user_id > 0) {
    $stmtSysRole = $db_connect->prepare("SELECT system_role FROM system_access WHERE user_id = ? AND system_type = 'E-SKUELOG' ORDER BY system_role ASC LIMIT 1");
    if ($stmtSysRole) {
        $stmtSysRole->bind_param("i", $user_id);
        $stmtSysRole->execute();
        $roleRes = $stmtSysRole->get_result()->fetch_assoc();
        if ($roleRes && isset($role_map[(string)$roleRes['system_role']])) {
            $g_user_role = $role_map[(string)$roleRes['system_role']];
        }
    }
}

$g_user_role_upper = strtoupper(trim((string)$g_user_role));

$action = $_POST['action'] ?? '';
$today  = date('Y-m-d');

// 3. STRICT WORKSTATION ASSIGNMENT AUTHORIZATION
$assigned_office_id  = 0;
$assigned_counter_id = 0;

if ($user_id > 0) {
    // Priority 1: Direct Workstation Assignment in staff_assignments
    $stmtStaff = $db_connect->prepare("
        SELECT office_id, counter_id, shift_status 
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
            $assigned_office_id  = (int)$staffRow['office_id'];
            $assigned_counter_id = (int)$staffRow['counter_id'];
            $_SESSION['shift_status'] = $staffRow['shift_status'] ?? 'ON_DUTY';
        }
    }

    // Priority 2: Fallback check on employee table
    if ($assigned_office_id <= 0) {
        $stmtEmp = $db_connect->prepare("SELECT office_id FROM employee WHERE user_id = ? LIMIT 1");
        if ($stmtEmp) {
            $stmtEmp->bind_param("i", $user_id);
            $stmtEmp->execute();
            $empRow = $stmtEmp->get_result()->fetch_assoc();
            if ($empRow) {
                $assigned_office_id  = (int)$empRow['office_id'];
                $assigned_counter_id = 0;
            }
        }
    }
}

// Security Enforcement: ADMIN can select any office, but ADMIN_STAFF MUST use their database assignment
if ($g_user_role_upper === 'ADMIN') {
    $office_id  = (int)($_POST['office_id'] ?? $assigned_office_id ?? $_SESSION['assigned_office_id'] ?? 0);
    $counter_id = (int)($_POST['counter_id'] ?? $assigned_counter_id ?? $_SESSION['assigned_counter_id'] ?? 0);
} else {
    if ($assigned_office_id > 0) {
        $office_id  = $assigned_office_id;
        $counter_id = $assigned_counter_id;
    } else {
        $office_id  = 0;
        $counter_id = 0;
        unset($_SESSION['assigned_office_id'], $_SESSION['assigned_counter_id']);
    }
}

// Update Active Session Cache if valid
if ($office_id > 0) {
    $_SESSION['assigned_office_id']  = $office_id;
    $_SESSION['assigned_counter_id'] = $counter_id;
}

switch ($action) {

    case 'fetch_counter_status':
        if ($office_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'No active workstation assignment detected for this staff account.']);
            exit();
        }

        try {
            $shift_status = $_SESSION['shift_status'] ?? 'ON_DUTY';

            // Active CALLED or SERVING ticket for assigned office
            $stmtServing = $db_connect->prepare("
                SELECT 
                    q.id AS queue_id,
                    q.ticket_number,
                    q.status,
                    q.is_priority,
                    l.id AS logbook_id,
                    l.created_at AS queue_entry_time,
                    q.called_at,
                    q.served_at,
                    l.visitor_type,
                    l.reference_id,
                    u.email,
                    l.first_name,
                    l.middle_name,
                    l.last_name,
                    l.suffix,
                    l.post_nominal,
                    l.sex,
                    l.contact_no,
                    COALESCE(p.program_code, l.program_code) AS program_code,
                    COALESCE(p.program_name, '') AS program_name,
                    l.year_level,
                    d.department_code,
                    d.department_name,
                    o.office_name,
                    tt.transaction_name,
                    l.other_concern_text
                FROM queue_tickets q
                INNER JOIN logbooks l ON q.logbook_id = l.id
                LEFT JOIN users u ON l.user_id = u.id
                LEFT JOIN programs p ON (l.program_id > 0 AND l.program_id = p.program_id) OR (l.program_code IS NOT NULL AND TRIM(l.program_code) = TRIM(p.program_code))
                LEFT JOIN departments d ON l.department_id = d.department_id
                LEFT JOIN office o ON q.office_id = o.office_id
                LEFT JOIN transaction_types tt ON l.transaction_type_id = tt.id
                WHERE q.office_id = ? 
                  AND (q.counter_id = ? OR q.served_by_user_id = ? OR ? = 0)
                  AND q.queue_date = ? 
                  AND q.status IN ('CALLED', 'SERVING')
                ORDER BY q.called_at DESC, q.id DESC
                LIMIT 1
            ");
            $stmtServing->bind_param("iiiis", $office_id, $counter_id, $user_id, $counter_id, $today);
            $stmtServing->execute();
            $now_serving = $stmtServing->get_result()->fetch_assoc();

            if ($now_serving) {
                $m_init = !empty($now_serving['middle_name']) ? ' ' . substr($now_serving['middle_name'], 0, 1) . '.' : '';
                $suf    = !empty($now_serving['suffix']) ? ' ' . $now_serving['suffix'] : '';
                $post   = !empty($now_serving['post_nominal']) ? ', ' . $now_serving['post_nominal'] : '';
                
                $now_serving['full_name'] = trim($now_serving['first_name'] . $m_init . ' ' . $now_serving['last_name'] . $suf . $post);
                
                if (!empty($now_serving['sex'])) {
                    $now_serving['sex'] = ucfirst(strtolower($now_serving['sex']));
                }

                // Fetch multi-concerns from logbook_transaction_items table (including item-level other_concern_text if available)
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
                    $stmtItems->bind_param("i", $now_serving['logbook_id']);
                    $stmtItems->execute();
                    $resItems = $stmtItems->get_result();
                    while ($itemRow = $resItems->fetch_assoc()) {
                        $concerns_list[] = $itemRow;
                    }
                }
                $now_serving['concerns_list'] = $concerns_list;
            }

            // Waiting tickets lineup
            $up_next = [];
            $stmtNext = $db_connect->prepare("
                SELECT q.id, q.ticket_number, q.is_priority, l.visitor_type 
                FROM queue_tickets q
                INNER JOIN logbooks l ON q.logbook_id = l.id
                WHERE q.office_id = ? AND q.queue_date = ? AND q.status = 'WAITING' 
                ORDER BY q.is_priority DESC, q.queue_sequence ASC LIMIT 20
            ");
            $stmtNext->bind_param("is", $office_id, $today);
            $stmtNext->execute();
            $resNext = $stmtNext->get_result();
            while ($row = $resNext->fetch_assoc()) {
                $up_next[] = $row;
            }

            // Total waiting count
            $stmtCount = $db_connect->prepare("
                SELECT COUNT(*) as cnt 
                FROM queue_tickets 
                WHERE office_id = ? AND queue_date = ? AND status = 'WAITING'
            ");
            $stmtCount->bind_param("is", $office_id, $today);
            $stmtCount->execute();
            $waiting_count = (int)($stmtCount->get_result()->fetch_assoc()['cnt'] ?? 0);

            echo json_encode([
                'success' => true,
                'data' => [
                    'now_serving'   => $now_serving,
                    'up_next'       => $up_next,
                    'waiting_count' => $waiting_count,
                    'shift_status'  => $shift_status
                ]
            ]);
            exit();

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Status fetch error: ' . $e->getMessage()]);
            exit();
        }

    case 'call_next':
        if ($office_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'No active office assignment detected.']);
            exit();
        }

        try {
            if ($counter_id > 0) {
                $stmtCompletePrev = $db_connect->prepare("
                    UPDATE queue_tickets 
                    SET status = 'COMPLETED', completed_at = NOW() 
                    WHERE office_id = ? AND counter_id = ? AND queue_date = ? AND status IN ('CALLED', 'SERVING')
                ");
                $stmtCompletePrev->bind_param("iis", $office_id, $counter_id, $today);
            } else {
                $stmtCompletePrev = $db_connect->prepare("
                    UPDATE queue_tickets 
                    SET status = 'COMPLETED', completed_at = NOW() 
                    WHERE office_id = ? AND queue_date = ? AND status IN ('CALLED', 'SERVING')
                ");
                $stmtCompletePrev->bind_param("is", $office_id, $today);
            }
            $stmtCompletePrev->execute();

            $stmtNextTicket = $db_connect->prepare("
                SELECT q.id, q.ticket_number 
                FROM queue_tickets q
                INNER JOIN logbooks l ON q.logbook_id = l.id
                WHERE q.office_id = ? AND q.queue_date = ? AND q.status = 'WAITING' AND UPPER(TRIM(l.visitor_type)) != 'EMPLOYEE'
                ORDER BY q.is_priority DESC, q.queue_sequence ASC LIMIT 1
            ");
            $stmtNextTicket->bind_param("is", $office_id, $today);
            $stmtNextTicket->execute();
            $nextRes = $stmtNextTicket->get_result();

            if ($ticket = $nextRes->fetch_assoc()) {
                $ticket_id = (int)$ticket['id'];
                $now       = date('Y-m-d H:i:s');

                if ($counter_id > 0) {
                    $stmtUpdate = $db_connect->prepare("
                        UPDATE queue_tickets 
                        SET status = 'CALLED', 
                            called_at = ?, 
                            counter_id = ?, 
                            served_by_user_id = ? 
                        WHERE id = ?
                    ");
                    $stmtUpdate->bind_param("siii", $now, $counter_id, $user_id, $ticket_id);
                } else {
                    $stmtUpdate = $db_connect->prepare("
                        UPDATE queue_tickets 
                        SET status = 'CALLED', 
                            called_at = ?, 
                            counter_id = NULL, 
                            served_by_user_id = ? 
                        WHERE id = ?
                    ");
                    $stmtUpdate->bind_param("sii", $now, $user_id, $ticket_id);
                }
                $stmtUpdate->execute();

                echo json_encode(['success' => true, 'message' => "Ticket {$ticket['ticket_number']} called."]);
            } else {
                echo json_encode(['success' => false, 'message' => 'No waiting students or guests in queue.']);
            }
            exit();

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Call next failed: ' . $e->getMessage()]);
            exit();
        }

    case 'call_specific_ticket':
        if ($office_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'No active office assignment detected.']);
            exit();
        }

        $target_ticket_id = (int)($_POST['ticket_id'] ?? 0);
        if ($target_ticket_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid ticket selection.']);
            exit();
        }

        try {
            if ($counter_id > 0) {
                $stmtCompletePrev = $db_connect->prepare("
                    UPDATE queue_tickets 
                    SET status = 'COMPLETED', completed_at = NOW() 
                    WHERE office_id = ? AND counter_id = ? AND queue_date = ? AND status IN ('CALLED', 'SERVING')
                ");
                $stmtCompletePrev->bind_param("iis", $office_id, $counter_id, $today);
            } else {
                $stmtCompletePrev = $db_connect->prepare("
                    UPDATE queue_tickets 
                    SET status = 'COMPLETED', completed_at = NOW() 
                    WHERE office_id = ? AND queue_date = ? AND status IN ('CALLED', 'SERVING')
                ");
                $stmtCompletePrev->bind_param("is", $office_id, $today);
            }
            $stmtCompletePrev->execute();

            $now = date('Y-m-d H:i:s');
            if ($counter_id > 0) {
                $stmtUpdate = $db_connect->prepare("
                    UPDATE queue_tickets 
                    SET status = 'CALLED', 
                        called_at = ?, 
                        counter_id = ?, 
                        served_by_user_id = ? 
                    WHERE id = ? AND office_id = ? AND status = 'WAITING'
                ");
                $stmtUpdate->bind_param("siiii", $now, $counter_id, $user_id, $target_ticket_id, $office_id);
            } else {
                $stmtUpdate = $db_connect->prepare("
                    UPDATE queue_tickets 
                    SET status = 'CALLED', 
                        called_at = ?, 
                        counter_id = NULL, 
                        served_by_user_id = ? 
                    WHERE id = ? AND office_id = ? AND status = 'WAITING'
                ");
                $stmtUpdate->bind_param("siii", $now, $user_id, $target_ticket_id, $office_id);
            }
            $stmtUpdate->execute();

            if ($stmtUpdate->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Selected queue ticket called immediately.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Selected ticket is no longer waiting or invalid.']);
            }
            exit();

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Direct call failed: ' . $e->getMessage()]);
            exit();
        }

    case 'serve_ticket':
        if ($office_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'No active office assignment detected.']);
            exit();
        }

        try {
            if ($counter_id > 0) {
                $stmtServe = $db_connect->prepare("
                    UPDATE queue_tickets 
                    SET status = 'SERVING', served_at = NOW() 
                    WHERE office_id = ? AND counter_id = ? AND queue_date = ? AND status = 'CALLED'
                ");
                $stmtServe->bind_param("iis", $office_id, $counter_id, $today);
            } else {
                $stmtServe = $db_connect->prepare("
                    UPDATE queue_tickets 
                    SET status = 'SERVING', served_at = NOW() 
                    WHERE office_id = ? AND queue_date = ? AND status = 'CALLED'
                ");
                $stmtServe->bind_param("is", $office_id, $today);
            }
            $stmtServe->execute();

            if ($stmtServe->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Started serving.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'No CALLED ticket available to serve.']);
            }
            exit();

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Serve failed: ' . $e->getMessage()]);
            exit();
        }

    case 'recall_ticket':
        if ($office_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'No active office assignment detected.']);
            exit();
        }

        $now = date('Y-m-d H:i:s');
        if ($counter_id > 0) {
            $stmtRecall = $db_connect->prepare("UPDATE queue_tickets SET called_at = ? WHERE office_id = ? AND counter_id = ? AND queue_date = ? AND status IN ('CALLED', 'SERVING')");
            $stmtRecall->bind_param("siis", $now, $office_id, $counter_id, $today);
        } else {
            $stmtRecall = $db_connect->prepare("UPDATE queue_tickets SET called_at = ? WHERE office_id = ? AND queue_date = ? AND status IN ('CALLED', 'SERVING')");
            $stmtRecall->bind_param("sis", $now, $office_id, $today);
        }
        $stmtRecall->execute();

        echo json_encode(['success' => true, 'message' => 'Ticket callout repeated.']);
        exit();

    case 'complete_ticket':
        if ($office_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'No active office assignment detected.']);
            exit();
        }

        if ($counter_id > 0) {
            $stmtComplete = $db_connect->prepare("UPDATE queue_tickets SET status = 'COMPLETED', completed_at = NOW() WHERE office_id = ? AND counter_id = ? AND queue_date = ? AND status IN ('CALLED', 'SERVING')");
            $stmtComplete->bind_param("iis", $office_id, $counter_id, $today);
        } else {
            $stmtComplete = $db_connect->prepare("UPDATE queue_tickets SET status = 'COMPLETED', completed_at = NOW() WHERE office_id = ? AND queue_date = ? AND status IN ('CALLED', 'SERVING')");
            $stmtComplete->bind_param("is", $office_id, $today);
        }
        $stmtComplete->execute();

        echo json_encode(['success' => true, 'message' => 'Ticket marked completed.']);
        exit();

    case 'skip_ticket':
        if ($office_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'No active office assignment detected.']);
            exit();
        }

        if ($counter_id > 0) {
            $stmtSkip = $db_connect->prepare("UPDATE queue_tickets SET status = 'SKIPPED', completed_at = NOW() WHERE office_id = ? AND counter_id = ? AND queue_date = ? AND status IN ('CALLED', 'SERVING')");
            $stmtSkip->bind_param("iis", $office_id, $counter_id, $today);
        } else {
            $stmtSkip = $db_connect->prepare("UPDATE queue_tickets SET status = 'SKIPPED', completed_at = NOW() WHERE office_id = ? AND queue_date = ? AND status IN ('CALLED', 'SERVING')");
            $stmtSkip->bind_param("is", $office_id, $today);
        }
        $stmtSkip->execute();

        echo json_encode(['success' => true, 'message' => 'Ticket skipped.']);
        exit();

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid queue action.']);
        exit();
}