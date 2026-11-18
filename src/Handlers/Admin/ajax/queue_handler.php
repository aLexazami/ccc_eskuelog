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
//    A staff member can be assigned to MANY windows (one staff_assignments row per window).
//    The window being controlled is sent by the page as `counter_id` and is ONLY accepted
//    if it is one of the staff member's own active windows.
$assigned_office_id  = 0;
$assigned_counter_id = 0;
$my_windows          = [];
$needs_window_selection = false;

if ($user_id > 0) {
    $stmtStaff = $db_connect->prepare("
        SELECT sa.office_id, sa.counter_id, c.counter_name, o.office_code, o.office_name
        FROM staff_assignments sa
        INNER JOIN counters c ON c.id = sa.counter_id AND c.flag_status = 0
        INNER JOIN office o   ON o.office_id = sa.office_id AND o.flag_status = 0
        WHERE sa.user_id = ?
          AND sa.flag_status = 0
          AND sa.counter_id IS NOT NULL
        ORDER BY o.office_code ASC, c.counter_name ASC
    ");
    if ($stmtStaff) {
        $stmtStaff->bind_param("i", $user_id);
        $stmtStaff->execute();
        $resStaff = $stmtStaff->get_result();
        while ($w = $resStaff->fetch_assoc()) {
            $my_windows[] = [
                'office_id'    => (int)$w['office_id'],
                'counter_id'   => (int)$w['counter_id'],
                'counter_name' => $w['counter_name'],
                'office_code'  => $w['office_code'],
                'office_name'  => $w['office_name'],
            ];
        }
    }

    if (!empty($my_windows)) {
        $requested_counter = (int)($_POST['counter_id'] ?? 0);
        $picked = null;

        foreach ($my_windows as $w) {
            if ($requested_counter > 0 && $w['counter_id'] === $requested_counter) {
                $picked = $w;
                break;
            }
        }
        if ($picked === null && count($my_windows) === 1) {
            $picked = $my_windows[0];
        }

        if ($picked !== null) {
            $assigned_office_id  = $picked['office_id'];
            $assigned_counter_id = $picked['counter_id'];
            $_SESSION['shift_status'] = 'ON_DUTY';
        } else {
            // Several windows and none (valid) chosen: never fall back to "whole office" control
            $needs_window_selection = true;
        }
    } else {
        $stmtEmp = $db_connect->prepare("SELECT office_id FROM employee WHERE user_id = ? AND office_id > 0 LIMIT 1");
        if ($stmtEmp) {
            $stmtEmp->bind_param("i", $user_id);
            $stmtEmp->execute();
            $empRow = $stmtEmp->get_result()->fetch_assoc();
            if ($empRow) {
                $assigned_office_id  = (int)$empRow['office_id'];
                $assigned_counter_id = 0;
                $_SESSION['shift_status'] = 'ON_DUTY';
            }
        }
    }
}

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

if ($office_id > 0) {
    $_SESSION['assigned_office_id']  = $office_id;
    $_SESSION['assigned_counter_id'] = $counter_id;
}

/**
 * Helper: Hydrate full name, sex formatting, multi-concerns list, window numbers, and additional item details
 */
function hydrateTicketDetails(\mysqli $db_connect, array $row): array {
    $m_init = !empty($row['middle_name']) ? ' ' . substr($row['middle_name'], 0, 1) . '.' : '';
    $suf    = !empty($row['suffix']) ? ' ' . $row['suffix'] : '';
    $post   = !empty($row['post_nominal']) ? ', ' . $row['post_nominal'] : '';
    
    $row['full_name'] = trim($row['first_name'] . $m_init . ' ' . $row['last_name'] . $suf . $post);
    
    if (!empty($row['sex'])) {
        $row['sex'] = ucfirst(strtolower($row['sex']));
    }

    // 1. Fetch Transaction Items (Concerns, Sub-Concerns & Assigned Window/Counter)
    $concerns_list = [];
    $window_names  = [];

    if (!empty($row['ticket_window_name'])) {
        $window_names[] = trim((string)$row['ticket_window_name']);
    }

    $stmtItems = $db_connect->prepare("
        SELECT 
            lti.id AS logbook_item_id,
            tt.transaction_name,
            tsc.sub_concern_name,
            COALESCE(c_sub.counter_name, c_trans.counter_name) AS item_window_name
        FROM logbook_transaction_items lti
        LEFT JOIN transaction_types tt ON lti.transaction_type_id = tt.id
        LEFT JOIN transaction_sub_concerns tsc ON lti.sub_concern_id = tsc.id
        LEFT JOIN counters c_sub ON tsc.counter_id = c_sub.id
        LEFT JOIN counters c_trans ON tt.counter_id = c_trans.id
        WHERE lti.logbook_id = ?
        ORDER BY lti.id ASC
    ");
    if ($stmtItems) {
        $stmtItems->bind_param("i", $row['logbook_id']);
        $stmtItems->execute();
        $resItems = $stmtItems->get_result();
        while ($itemRow = $resItems->fetch_assoc()) {
            $concerns_list[] = $itemRow;
            if (!empty($itemRow['item_window_name'])) {
                $wName = trim((string)$itemRow['item_window_name']);
                if (!in_array($wName, $window_names, true)) {
                    $window_names[] = $wName;
                }
            }
        }
    }

    if (empty($window_names) && !empty($row['primary_trans_window_name'])) {
        $window_names[] = trim((string)$row['primary_trans_window_name']);
    }

    $row['concerns_list'] = $concerns_list;
    $row['window_number'] = !empty($window_names) ? implode(', ', $window_names) : '';

    // 2. Fetch Additional Details from logbook_transaction_item_details
    $additional_details = [];
    try {
        $stmtDetails = $db_connect->prepare("
            SELECT 
                ltid.id,
                ltid.logbook_item_id,
                ltid.field_title,
                ltid.detail_mode,
                ltid.answer_value,
                tt.transaction_name,
                tsc.sub_concern_name
            FROM logbook_transaction_item_details ltid
            INNER JOIN logbook_transaction_items lti ON ltid.logbook_item_id = lti.id
            LEFT JOIN transaction_types tt ON lti.transaction_type_id = tt.id
            LEFT JOIN transaction_sub_concerns tsc ON lti.sub_concern_id = tsc.id
            WHERE lti.logbook_id = ?
              AND TRIM(ltid.answer_value) != ''
            ORDER BY lti.id ASC, ltid.id ASC
        ");
        if ($stmtDetails) {
            $stmtDetails->bind_param("i", $row['logbook_id']);
            $stmtDetails->execute();
            $resDetails = $stmtDetails->get_result();
            while ($detRow = $resDetails->fetch_assoc()) {
                $additional_details[] = $detRow;
            }
        }
    } catch (\Throwable $e) {
        $additional_details = [];
    }
    $row['additional_details'] = $additional_details;

    return $row;
}

switch ($action) {

    case 'fetch_my_windows':
        echo json_encode([
            'success' => true,
            'data'    => [
                'windows'          => $my_windows,
                'current_counter'  => $counter_id,
                'needs_selection'  => $needs_window_selection
            ]
        ]);
        exit();

    case 'fetch_counter_status':
        if ($office_id <= 0) {
            echo json_encode([
                'success' => false,
                'needs_window_selection' => $needs_window_selection,
                'message' => $needs_window_selection
                    ? 'Please select the window you want to control.'
                    : 'No active workstation assignment detected for this staff account.'
            ]);
            exit();
        }

        try {
            $shift_status = $_SESSION['shift_status'] ?? 'ON_DUTY';

            // Base SELECT for full visitor & ticket details including counter/window names
            $baseTicketSelect = "
                SELECT 
                    q.id,
                    q.id AS queue_id,
                    q.ticket_number,
                    q.queue_sequence,
                    q.status,
                    q.is_priority,
                    q.counter_id,
                    c_q.counter_name AS ticket_window_name,
                    c_tt.counter_name AS primary_trans_window_name,
                    l.id AS logbook_id,
                    l.created_at AS queue_entry_time,
                    q.called_at,
                    q.served_at,
                    l.visitor_type,
                    l.reference_id,
                    COALESCE(l.email, u.email) AS email,
                    l.first_name,
                    l.middle_name,
                    l.last_name,
                    l.suffix,
                    l.post_nominal,
                    l.sex,
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
                LEFT JOIN counters c_q ON q.counter_id = c_q.id
                LEFT JOIN counters c_tt ON tt.counter_id = c_tt.id
            ";

            // 1. Active CALLED or SERVING ticket for assigned office & window
            $stmtServing = $db_connect->prepare("
                {$baseTicketSelect}
                WHERE q.office_id = ? 
                  AND (? = 0 OR q.counter_id = ?)
                  AND q.queue_date = ? 
                  AND q.status IN ('CALLED', 'SERVING')
                ORDER BY q.called_at DESC, q.id DESC
                LIMIT 1
            ");
            $stmtServing->bind_param("iiis", $office_id, $counter_id, $counter_id, $today);
            $stmtServing->execute();
            $now_serving = $stmtServing->get_result()->fetch_assoc();

            if ($now_serving) {
                $now_serving = hydrateTicketDetails($db_connect, $now_serving);
            }

            // 2. Waiting tickets lineup (Strictly First-Come, First-Served via queue_sequence ASC)
            $up_next = [];
            $stmtNext = $db_connect->prepare("
                {$baseTicketSelect}
                WHERE q.office_id = ? AND q.queue_date = ? AND q.status = 'WAITING'
                  AND (? = 0 OR q.counter_id IS NULL OR q.counter_id = ?)
                ORDER BY q.queue_sequence ASC
                LIMIT 50
            ");
            // Only this window's tickets (+ "Any Window" tickets with no designated window)
            $stmtNext->bind_param("isii", $office_id, $today, $counter_id, $counter_id);
            $stmtNext->execute();
            $resNext = $stmtNext->get_result();
            while ($row = $resNext->fetch_assoc()) {
                $up_next[] = hydrateTicketDetails($db_connect, $row);
            }

            // 3. Total waiting count
            $stmtCount = $db_connect->prepare("
                SELECT COUNT(*) as cnt 
                FROM queue_tickets 
                WHERE office_id = ? AND queue_date = ? AND status = 'WAITING'
                  AND (? = 0 OR counter_id IS NULL OR counter_id = ?)
            ");
            $stmtCount->bind_param("isii", $office_id, $today, $counter_id, $counter_id);
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

    case 'fetch_window_services':
        if ($office_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'No active office assignment detected.']);
            exit();
        }

        try {
            $stmtTrans = $db_connect->prepare("
                SELECT 
                    tt.id,
                    tt.transaction_name,
                    tt.estimated_minutes,
                    tt.counter_id
                FROM transaction_types tt
                WHERE tt.office_id = ?
                  AND tt.flag_status = 0
                ORDER BY tt.id ASC
            ");
            $stmtTrans->bind_param("i", $office_id);
            $stmtTrans->execute();
            $resTrans = $stmtTrans->get_result();

            $stmtSub = $db_connect->prepare("
                SELECT 
                    tsc.id,
                    tsc.sub_concern_name,
                    tsc.estimated_minutes,
                    tsc.counter_id
                FROM transaction_sub_concerns tsc
                WHERE tsc.transaction_type_id = ?
                  AND tsc.flag_status = 0
                ORDER BY tsc.id ASC
            ");

            $services = [];
            while ($tRow = $resTrans->fetch_assoc()) {
                $transId        = (int)$tRow['id'];
                $transCounterId = (int)($tRow['counter_id'] ?? 0);
                $transHandlesWindow = ($counter_id <= 0 || $transCounterId === 0 || $transCounterId === $counter_id);

                $stmtSub->bind_param("i", $transId);
                $stmtSub->execute();
                $resSub = $stmtSub->get_result();

                $allSubs     = [];
                $matchedSubs = [];

                while ($sRow = $resSub->fetch_assoc()) {
                    $allSubs[] = $sRow;
                    $subCounterId = (int)($sRow['counter_id'] ?? 0);

                    if ($counter_id <= 0) {
                        $matchedSubs[] = $sRow;
                    } elseif ($subCounterId === $counter_id) {
                        $matchedSubs[] = $sRow;
                    } elseif ($subCounterId === 0 && $transHandlesWindow) {
                        $matchedSubs[] = $sRow;
                    }
                }

                // Include transaction if it has matched sub-transactions OR if it has no sub-transactions and handles this window
                if (!empty($matchedSubs) || (empty($allSubs) && $transHandlesWindow)) {
                    $tRow['sub_concerns'] = $matchedSubs;
                    $services[] = $tRow;
                }
            }

            echo json_encode([
                'success' => true,
                'data'    => $services
            ]);
            exit();

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to load window services: ' . $e->getMessage()]);
            exit();
        }

    case 'call_next_category':
        if ($office_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'No active office assignment detected.']);
            exit();
        }

        $category = strtoupper(trim((string)($_POST['category'] ?? '')));
        $allowed_categories = ['PRIORITY', 'REGULAR', 'EMPLOYEE'];

        if (!in_array($category, $allowed_categories, true)) {
            echo json_encode(['success' => false, 'message' => 'Invalid queue category selected.']);
            exit();
        }

        try {
            // Auto-complete any currently active ticket at this window before calling the next one
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

            // Select next FIFO ticket in the chosen category (PRIORITY, REGULAR [STUDENT/GUEST], EMPLOYEE)
            if ($category === 'PRIORITY') {
                $stmtNextTicket = $db_connect->prepare("
                    SELECT q.id, q.ticket_number 
                    FROM queue_tickets q
                    INNER JOIN logbooks l ON q.logbook_id = l.id
                    WHERE q.office_id = ? 
                      AND q.queue_date = ? 
                      AND q.status = 'WAITING'
                      AND (? = 0 OR q.counter_id IS NULL OR q.counter_id = ?)
                      AND q.is_priority = 1
                    ORDER BY q.queue_sequence ASC 
                    LIMIT 1
                ");
                $stmtNextTicket->bind_param("isii", $office_id, $today, $counter_id, $counter_id);
            } elseif ($category === 'REGULAR') {
                $stmtNextTicket = $db_connect->prepare("
                    SELECT q.id, q.ticket_number 
                    FROM queue_tickets q
                    INNER JOIN logbooks l ON q.logbook_id = l.id
                    WHERE q.office_id = ? 
                      AND q.queue_date = ? 
                      AND q.status = 'WAITING'
                      AND (? = 0 OR q.counter_id IS NULL OR q.counter_id = ?)
                      AND q.is_priority = 0
                      AND UPPER(TRIM(l.visitor_type)) IN ('STUDENT', 'GUEST')
                    ORDER BY q.queue_sequence ASC 
                    LIMIT 1
                ");
                $stmtNextTicket->bind_param("isii", $office_id, $today, $counter_id, $counter_id);
            } else {
                $stmtNextTicket = $db_connect->prepare("
                    SELECT q.id, q.ticket_number 
                    FROM queue_tickets q
                    INNER JOIN logbooks l ON q.logbook_id = l.id
                    WHERE q.office_id = ? 
                      AND q.queue_date = ? 
                      AND q.status = 'WAITING'
                      AND (? = 0 OR q.counter_id IS NULL OR q.counter_id = ?)
                      AND q.is_priority = 0
                      AND UPPER(TRIM(l.visitor_type)) = 'EMPLOYEE'
                    ORDER BY q.queue_sequence ASC 
                    LIMIT 1
                ");
                $stmtNextTicket->bind_param("isii", $office_id, $today, $counter_id, $counter_id);
            }

            $stmtNextTicket->execute();
            $nextRes = $stmtNextTicket->get_result();

            if ($ticket = $nextRes->fetch_assoc()) {
                $ticket_id = (int)$ticket['id'];
                $now       = date('Y-m-d H:i:s');

                // Atomic claim: only succeeds if the ticket is still WAITING and still callable by this window,
                // so two windows pressing "Call Next" at the same time can never take the same ticket.
                if ($counter_id > 0) {
                    $stmtUpdate = $db_connect->prepare("
                        UPDATE queue_tickets
                        SET status = 'CALLED',
                            called_at = ?,
                            counter_id = ?,
                            served_by_user_id = ?
                        WHERE id = ?
                          AND status = 'WAITING'
                          AND (counter_id IS NULL OR counter_id = ?)
                    ");
                    $stmtUpdate->bind_param("siiii", $now, $counter_id, $user_id, $ticket_id, $counter_id);
                } else {
                    $stmtUpdate = $db_connect->prepare("
                        UPDATE queue_tickets
                        SET status = 'CALLED',
                            called_at = ?,
                            served_by_user_id = ?
                        WHERE id = ?
                          AND status = 'WAITING'
                    ");
                    $stmtUpdate->bind_param("sii", $now, $user_id, $ticket_id);
                }
                $stmtUpdate->execute();

                if ($stmtUpdate->affected_rows < 1) {
                    echo json_encode(['success' => false, 'message' => 'That ticket was just taken. Please press Call Next again.']);
                    exit();
                }

                echo json_encode(['success' => true, 'message' => "Ticket {$ticket['ticket_number']} called."]);
            } else {
                echo json_encode(['success' => false, 'message' => "No waiting tickets in the {$category} lane."]);
            }
            exit();

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Call next failed: ' . $e->getMessage()]);
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

        // Preserve original called_at timestamp so the elapsed clock timer does not reset on recall
        if ($counter_id > 0) {
            $stmtRecall = $db_connect->prepare("
                SELECT id, ticket_number 
                FROM queue_tickets 
                WHERE office_id = ? AND counter_id = ? AND queue_date = ? AND status IN ('CALLED', 'SERVING') 
                LIMIT 1
            ");
            $stmtRecall->bind_param("iis", $office_id, $counter_id, $today);
        } else {
            $stmtRecall = $db_connect->prepare("
                SELECT id, ticket_number 
                FROM queue_tickets 
                WHERE office_id = ? AND queue_date = ? AND status IN ('CALLED', 'SERVING') 
                LIMIT 1
            ");
            $stmtRecall->bind_param("is", $office_id, $today);
        }
        $stmtRecall->execute();
        $recallRow = $stmtRecall->get_result()->fetch_assoc();

        if ($recallRow) {
            $stmtTouch = $db_connect->prepare("
                UPDATE queue_tickets 
                SET called_at = COALESCE(called_at, NOW()) 
                WHERE id = ?
            ");
            $stmtTouch->bind_param("i", $recallRow['id']);
            $stmtTouch->execute();

            echo json_encode(['success' => true, 'message' => 'Ticket callout repeated.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'No active ticket to recall.']);
        }
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