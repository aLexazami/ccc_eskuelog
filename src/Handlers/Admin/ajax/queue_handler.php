<?php
// src/Handlers/Admin/ajax/queue_handler.php

/** @var \mysqli $db_connect */
global $db_connect, $session_class, $g_user_id, $g_user_role;
$db_connect = $db_connect ?? null;

header('Content-Type: application/json');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!$db_connect) {
    echo json_encode(['success' => false, 'message' => 'Database connection unavailable.']);
    exit();
}

require_once ACTIVITY_LOGGER;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Resolve User ID
$user_id = (int)($g_user_id ?? 0);
if ($user_id <= 0 && isset($session_class) && method_exists($session_class, 'getValue')) {
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
        $stmtSess->close();
    }
}

// 2. Resolve User Role
$resolved_role = (string)($g_user_role ?? '');
if ($resolved_role === '' && isset($session_class) && method_exists($session_class, 'getValue')) {
    $resolved_role = (string)($session_class->getValue('user_role') ?? $session_class->getValue('system_role') ?? '');
}
if ($resolved_role === '') {
    $resolved_role = (string)($_SESSION['user_role'] ?? $_SESSION['g_user_role'] ?? $_SESSION['role'] ?? '');
}

$role_map = SYSTEM_ACCESS[SYSTEM_ACCESS_NAME]['role'] ?? [];
if (isset($role_map[$resolved_role])) {
    $resolved_role = $role_map[$resolved_role];
}

if ($resolved_role === '' && $user_id > 0) {
    $sysName = SYSTEM_ACCESS_NAME;
    $stmtSysRole = $db_connect->prepare("
        SELECT system_role
        FROM system_access
        WHERE user_id = ? AND system_type = ? AND flag_access = 0
        ORDER BY system_role ASC
        LIMIT 1
    ");
    if ($stmtSysRole) {
        $stmtSysRole->bind_param("is", $user_id, $sysName);
        $stmtSysRole->execute();
        $roleRes = $stmtSysRole->get_result()->fetch_assoc();
        if ($roleRes && isset($role_map[(string)$roleRes['system_role']])) {
            $resolved_role = $role_map[(string)$roleRes['system_role']];
        }
        $stmtSysRole->close();
    }
}

$g_user_role_upper = strtoupper(trim($resolved_role)) ?: 'USER';

$action = $_POST['action'] ?? '';
$today  = date('Y-m-d');

// 3. STRICT WORKSTATION ASSIGNMENT AUTHORIZATION
$assigned_office_id     = 0;
$assigned_counter_id    = 0;
$my_windows             = [];
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
        $stmtStaff->close();
    }

    if (!empty($my_windows)) {
        $requested_counter = (int)($_POST['counter_id'] ?? $_SESSION['assigned_counter_id'] ?? 0);
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
            $needs_window_selection = true;
        }
    }
}

// Enforce staff_assignments consistently for all users (including ADMIN)
$is_default_window_1 = false;
if ($assigned_office_id > 0 && $assigned_counter_id > 0) {
    $office_id  = $assigned_office_id;
    $counter_id = $assigned_counter_id;
    $_SESSION['assigned_office_id']  = $office_id;
    $_SESSION['assigned_counter_id'] = $counter_id;

    // Check if this counter is Window 1 (first active counter of this office)
    $stmtFirstWin = $db_connect->prepare("
        SELECT id FROM counters
        WHERE office_id = ? AND flag_status = 0
        ORDER BY id ASC
        LIMIT 1
    ");
    if ($stmtFirstWin) {
        $stmtFirstWin->bind_param("i", $office_id);
        $stmtFirstWin->execute();
        $firstWinRow = $stmtFirstWin->get_result()->fetch_assoc();
        $stmtFirstWin->close();
        $is_default_window_1 = ($firstWinRow && (int)$firstWinRow['id'] === $counter_id);
    }
} else {
    $office_id  = 0;
    $counter_id = 0;
    unset($_SESSION['assigned_office_id'], $_SESSION['assigned_counter_id']);
}

$counter_match_sql = $is_default_window_1
    ? "(q.counter_id = ? OR q.counter_id = 0 OR q.counter_id IS NULL)"
    : "q.counter_id = ?";

$counter_match_unaliased_sql = $is_default_window_1
    ? "(counter_id = ? OR counter_id = 0 OR counter_id IS NULL)"
    : "counter_id = ?";

/**
 * Helper: Resolve a human-readable workstation label e.g. "DCI (Window 1)"
 */
function resolveWorkstationLabel(\mysqli $db_connect, int $office_id, int $counter_id, array $my_windows = []): string {
    foreach ($my_windows as $w) {
        if ((int)$w['office_id'] === $office_id && (int)$w['counter_id'] === $counter_id) {
            return "{$w['office_code']} ({$w['counter_name']})";
        }
    }

    $office_label  = "Office #{$office_id}";
    $counter_label = $counter_id > 0 ? "Counter #{$counter_id}" : "All Counters";

    try {
        $stmtOff = $db_connect->prepare("SELECT office_code FROM office WHERE office_id = ? LIMIT 1");
        if ($stmtOff) {
            $stmtOff->bind_param("i", $office_id);
            $stmtOff->execute();
            if ($row = $stmtOff->get_result()->fetch_assoc()) {
                $office_label = $row['office_code'];
            }
            $stmtOff->close();
        }

        if ($counter_id > 0) {
            $stmtCnt = $db_connect->prepare("SELECT counter_name FROM counters WHERE id = ? LIMIT 1");
            if ($stmtCnt) {
                $stmtCnt->bind_param("i", $counter_id);
                $stmtCnt->execute();
                if ($row = $stmtCnt->get_result()->fetch_assoc()) {
                    $counter_label = $row['counter_name'];
                }
                $stmtCnt->close();
            }
        }
    } catch (\Throwable $e) {
        // Fallback to numeric IDs if lookup fails
    }

    return "{$office_label} ({$counter_label})";
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
        $stmtItems->close();
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
            $stmtDetails->close();
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
                'windows'         => $my_windows,
                'current_counter' => $counter_id,
                'needs_selection' => $needs_window_selection
            ]
        ]);
        exit();

    case 'fetch_counter_status':
        if ($office_id <= 0 || $counter_id <= 0) {
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

            // Base SELECT for full visitor & ticket details including counter/window names and lifecycle timestamps
            $baseTicketSelect = "
                SELECT 
                    q.id,
                    q.id AS queue_id,
                    q.ticket_number,
                    q.queue_sequence,
                    q.status,
                    q.is_priority,
                    q.counter_id,
                    q.served_by_user_id,
                    c_q.counter_name AS ticket_window_name,
                    c_tt.counter_name AS primary_trans_window_name,
                    l.id AS logbook_id,
                    l.created_at AS queue_entry_time,
                    q.called_at,
                    q.served_at,
                    q.completed_at,
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
                    l.other_concern_text,
                    CONCAT(staff_u.first_name, ' ', staff_u.last_name) AS called_by_name
                FROM queue_tickets q
                INNER JOIN logbooks l ON q.logbook_id = l.id
                LEFT JOIN users u ON l.user_id = u.id
                LEFT JOIN users staff_u ON q.served_by_user_id = staff_u.id
                LEFT JOIN programs p ON (l.program_id > 0 AND l.program_id = p.program_id) OR (l.program_code IS NOT NULL AND TRIM(l.program_code) = TRIM(p.program_code))
                LEFT JOIN departments d ON l.department_id = d.department_id
                LEFT JOIN office o ON q.office_id = o.office_id
                LEFT JOIN transaction_types tt ON l.transaction_type_id = tt.id
                LEFT JOIN counters c_q ON q.counter_id = c_q.id
                LEFT JOIN counters c_tt ON tt.counter_id = c_tt.id
            ";

            // 1. Hero Display Ticket for THIS staff member:
            // Priority A: Ticket they are actively SERVING
            // Priority B: Most recent CALLED ticket they called (if not serving anyone yet)
            $stmtServing = $db_connect->prepare("
                {$baseTicketSelect}
                WHERE q.office_id = ? 
                  AND q.counter_id = ?
                  AND q.served_by_user_id = ?
                  AND q.queue_date = ? 
                  AND q.status IN ('SERVING', 'CALLED')
                ORDER BY (q.status = 'SERVING') DESC, q.called_at DESC, q.id DESC
                LIMIT 1
            ");
            $stmtServing->bind_param("iiis", $office_id, $counter_id, $user_id, $today);
            $stmtServing->execute();
            $now_serving = $stmtServing->get_result()->fetch_assoc();
            $stmtServing->close();

            if ($now_serving) {
                $now_serving = hydrateTicketDetails($db_connect, $now_serving);
            }

            // 2. Shared Calling Line:
            // Include CALLED tickets at this Office & Window ONLY from staff who have
            // MORE THAN ONE (> 1) active CALLED ticket, excluding each caller's
            // latest CALLED ticket (which is shown on that caller's own Main Display).
            $calling_line = [];
            $stmtCalling = $db_connect->prepare("
                {$baseTicketSelect}
                INNER JOIN (
                    SELECT served_by_user_id
                    FROM queue_tickets
                    WHERE office_id = ?
                      AND counter_id = ?
                      AND queue_date = ?
                      AND status = 'CALLED'
                      AND served_by_user_id IS NOT NULL
                      AND served_by_user_id > 0
                    GROUP BY served_by_user_id
                    HAVING COUNT(*) > 1
                ) multi_caller ON q.served_by_user_id = multi_caller.served_by_user_id
                WHERE q.office_id = ?
                  AND q.counter_id = ?
                  AND q.queue_date = ?
                  AND q.status = 'CALLED'
                  AND q.id NOT IN (
                      SELECT latest_id FROM (
                          SELECT MAX(q2.id) AS latest_id
                          FROM queue_tickets q2
                          INNER JOIN (
                              SELECT served_by_user_id, MAX(called_at) AS max_called_at
                              FROM queue_tickets
                              WHERE office_id = ?
                                AND counter_id = ?
                                AND queue_date = ?
                                AND status = 'CALLED'
                                AND served_by_user_id IS NOT NULL
                                AND served_by_user_id > 0
                              GROUP BY served_by_user_id
                              HAVING COUNT(*) > 1
                          ) latest_per_staff
                            ON q2.served_by_user_id = latest_per_staff.served_by_user_id
                           AND q2.called_at = latest_per_staff.max_called_at
                          WHERE q2.office_id = ?
                            AND q2.counter_id = ?
                            AND q2.queue_date = ?
                            AND q2.status = 'CALLED'
                          GROUP BY q2.served_by_user_id
                      ) latest_sub
                  )
                ORDER BY q.called_at ASC, q.id ASC
            ");
            $stmtCalling->bind_param(
                "iisiisiisiis",
                $office_id, $counter_id, $today,
                $office_id, $counter_id, $today,
                $office_id, $counter_id, $today,
                $office_id, $counter_id, $today
            );
            $stmtCalling->execute();
            $resCalling = $stmtCalling->get_result();
            while ($cRow = $resCalling->fetch_assoc()) {
                $calling_line[] = hydrateTicketDetails($db_connect, $cRow);
            }
            $stmtCalling->close();

            // 3. Waiting tickets lineup (Window 1 also includes legacy counter_id = 0/NULL tickets)
            $up_next = [];
            $stmtNext = $db_connect->prepare("
                {$baseTicketSelect}
                WHERE q.office_id = ?
                  AND q.queue_date = ?
                  AND q.status = 'WAITING'
                  AND {$counter_match_sql}
                ORDER BY q.queue_sequence ASC
                LIMIT 50
            ");
            $stmtNext->bind_param("isi", $office_id, $today, $counter_id);
            $stmtNext->execute();
            $resNext = $stmtNext->get_result();
            while ($row = $resNext->fetch_assoc()) {
                $up_next[] = hydrateTicketDetails($db_connect, $row);
            }
            $stmtNext->close();

            // 4. Total waiting count (Window 1 also includes legacy counter_id = 0/NULL tickets)
            $stmtCount = $db_connect->prepare("
                SELECT COUNT(*) as cnt 
                FROM queue_tickets 
                WHERE office_id = ?
                  AND queue_date = ?
                  AND status = 'WAITING'
                  AND {$counter_match_unaliased_sql}
            ");
            $stmtCount->bind_param("isi", $office_id, $today, $counter_id);
            $stmtCount->execute();
            $waiting_count = (int)($stmtCount->get_result()->fetch_assoc()['cnt'] ?? 0);
            $stmtCount->close();

            echo json_encode([
                'success' => true,
                'data' => [
                    'now_serving'   => $now_serving,
                    'calling_line'  => $calling_line,
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
        if ($office_id <= 0 || $counter_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'No active workstation assignment detected.']);
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
                $transId            = (int)$tRow['id'];
                $transCounterId     = (int)($tRow['counter_id'] ?? 0);
                $transHandlesWindow = ($transCounterId === $counter_id) || ($transCounterId === 0 && $is_default_window_1);

                $stmtSub->bind_param("i", $transId);
                $stmtSub->execute();
                $resSub = $stmtSub->get_result();

                $allSubs     = [];
                $matchedSubs = [];

                while ($sRow = $resSub->fetch_assoc()) {
                    $allSubs[] = $sRow;
                    $subCounterId = (int)($sRow['counter_id'] ?? 0);

                    if ($subCounterId === $counter_id) {
                        $matchedSubs[] = $sRow;
                    } elseif ($subCounterId === 0 && $transHandlesWindow) {
                        $matchedSubs[] = $sRow;
                    }
                }

                if (!empty($matchedSubs) || (empty($allSubs) && $transHandlesWindow)) {
                    $tRow['sub_concerns'] = $matchedSubs;
                    $services[] = $tRow;
                }
            }
            $stmtSub->close();
            $stmtTrans->close();

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
        if ($office_id <= 0 || $counter_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'No active workstation assignment detected.']);
            exit();
        }

        $category = strtoupper(trim((string)($_POST['category'] ?? '')));
        $allowed_categories = ['PRIORITY', 'REGULAR', 'EMPLOYEE'];

        if (!in_array($category, $allowed_categories, true)) {
            echo json_encode(['success' => false, 'message' => 'Invalid queue category selected.']);
            exit();
        }

        // Lock per office + counter + lane so different lanes/windows do not block each other
        $lock_name     = "eq_call_{$office_id}_{$counter_id}_{$category}";
        $lock_held     = false;
        $in_tx         = false;
        $guard_seconds = 2;
        $response      = ['success' => false, 'message' => 'Call next failed.'];

        $lane_filters = [
            'PRIORITY' => "AND q.is_priority = 1",
            'REGULAR'  => "AND q.is_priority = 0 AND UPPER(TRIM(l.visitor_type)) IN ('STUDENT', 'GUEST')",
            'EMPLOYEE' => "AND q.is_priority = 0 AND UPPER(TRIM(l.visitor_type)) = 'EMPLOYEE'",
        ];
        $lane_sql = $lane_filters[$category];

        try {
            $stmtLock = $db_connect->prepare("SELECT GET_LOCK(?, 5) AS got");
            $stmtLock->bind_param("s", $lock_name);
            $stmtLock->execute();
            $lock_held = ((int)($stmtLock->get_result()->fetch_assoc()['got'] ?? 0) === 1);
            $stmtLock->close();

            if (!$lock_held) {
                $response = ['success' => false, 'message' => 'This lane is busy handling another Call Next. Please try again.'];
            } else {
                $db_connect->begin_transaction();
                $in_tx = true;

                // 1. Guard: only block if THIS staff member just called a ticket at this window
                $stmtGuard = $db_connect->prepare("
                    SELECT ticket_number, called_at
                    FROM queue_tickets
                    WHERE office_id = ?
                      AND queue_date = ?
                      AND status = 'CALLED'
                      AND counter_id = ?
                      AND served_by_user_id = ?
                    ORDER BY called_at DESC, id DESC
                    LIMIT 1
                ");
                $stmtGuard->bind_param("isii", $office_id, $today, $counter_id, $user_id);
                $stmtGuard->execute();
                $justCalled = $stmtGuard->get_result()->fetch_assoc();
                $stmtGuard->close();

                if ($justCalled && !empty($justCalled['called_at'])
                    && (time() - strtotime($justCalled['called_at'])) < $guard_seconds) {
                    $db_connect->rollback();
                    $in_tx = false;
                    $response = [
                        'success' => false,
                        'message' => "You just called ticket {$justCalled['ticket_number']}. Please wait a moment before calling the next one."
                    ];
                } else {
                    // Existing CALLED/SERVING tickets are NOT auto-completed so they remain tracked in the Calling Line.

                    // 2. Pick + atomically claim the next FIFO ticket (Window 1 also claims counter_id = 0/NULL tickets)
                    $claimed_number = null;
                    $lane_empty     = false;

                    for ($attempt = 0; $attempt < 3 && $claimed_number === null; $attempt++) {
                        $stmtNextTicket = $db_connect->prepare("
                            SELECT q.id, q.ticket_number
                            FROM queue_tickets q
                            INNER JOIN logbooks l ON q.logbook_id = l.id
                            WHERE q.office_id = ?
                              AND q.queue_date = ?
                              AND q.status = 'WAITING'
                              AND {$counter_match_sql}
                              {$lane_sql}
                            ORDER BY q.queue_sequence ASC
                            LIMIT 1
                        ");
                        $stmtNextTicket->bind_param("isi", $office_id, $today, $counter_id);
                        $stmtNextTicket->execute();
                        $ticket = $stmtNextTicket->get_result()->fetch_assoc();
                        $stmtNextTicket->close();

                        if (!$ticket) {
                            $lane_empty = true;
                            break;
                        }

                        $ticket_id = (int)$ticket['id'];
                        $now       = date('Y-m-d H:i:s');

                        $stmtUpdate = $db_connect->prepare("
                            UPDATE queue_tickets
                            SET status = 'CALLED', called_at = ?, served_by_user_id = ?, counter_id = ?
                            WHERE id = ?
                              AND status = 'WAITING'
                              AND {$counter_match_unaliased_sql}
                        ");
                        $stmtUpdate->bind_param("siiii", $now, $user_id, $counter_id, $ticket_id, $counter_id);
                        $stmtUpdate->execute();
                        if ($stmtUpdate->affected_rows > 0) {
                            $claimed_number = $ticket['ticket_number'];
                        }
                        $stmtUpdate->close();
                    }

                    if ($claimed_number !== null) {
                        $db_connect->commit();
                        $in_tx = false;

                        $wsLabel = resolveWorkstationLabel($db_connect, $office_id, $counter_id, $my_windows);
                        record_activity_log(
                            $db_connect,
                            $user_id,
                            "QUEUE_CALL_NEXT: Added ticket {$claimed_number} ({$category} lane) to Calling Line at {$wsLabel}",
                            $g_user_role_upper
                        );

                        $response = ['success' => true, 'message' => "Ticket {$claimed_number} added to Calling Line."];
                    } else {
                        $db_connect->rollback();
                        $in_tx = false;
                        $response = [
                            'success' => false,
                            'message' => $lane_empty
                                ? "No waiting tickets in the {$category} lane."
                                : 'That ticket was just taken. Please press Call Next again.'
                        ];
                    }
                }
            }
        } catch (Throwable $e) {
            if ($in_tx) {
                try { $db_connect->rollback(); } catch (Throwable $ignore) {}
            }
            $response = ['success' => false, 'message' => 'Call next failed: ' . $e->getMessage()];
        }

        if ($lock_held) {
            try {
                $stmtRel = $db_connect->prepare("SELECT RELEASE_LOCK(?)");
                $stmtRel->bind_param("s", $lock_name);
                $stmtRel->execute();
                $stmtRel->close();
            } catch (Throwable $ignore) {}
        }

        echo json_encode($response);
        exit();

    case 'serve_ticket':
        if ($office_id <= 0 || $counter_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'No active workstation assignment detected.']);
            exit();
        }

        $target_ticket_id = (int)($_POST['ticket_id'] ?? 0);

        try {
            // 1. Prevent a staff member from serving multiple tickets simultaneously
            $stmtCheckBusy = $db_connect->prepare("
                SELECT ticket_number
                FROM queue_tickets
                WHERE office_id = ?
                  AND counter_id = ?
                  AND served_by_user_id = ?
                  AND queue_date = ?
                  AND status = 'SERVING'
                LIMIT 1
            ");
            $stmtCheckBusy->bind_param("iiis", $office_id, $counter_id, $user_id, $today);
            $stmtCheckBusy->execute();
            $alreadyServing = $stmtCheckBusy->get_result()->fetch_assoc();
            $stmtCheckBusy->close();

            if ($alreadyServing) {
                echo json_encode([
                    'success' => false,
                    'message' => "You are currently serving {$alreadyServing['ticket_number']}. Complete or skip it first before serving another ticket."
                ]);
                exit();
            }

            // 2. Locate target CALLED ticket at this office & window (specific ticket_id from Calling Line or staff's latest CALLED)
            if ($target_ticket_id > 0) {
                $stmtFind = $db_connect->prepare("
                    SELECT id, ticket_number
                    FROM queue_tickets
                    WHERE id = ?
                      AND office_id = ?
                      AND counter_id = ?
                      AND queue_date = ?
                      AND status = 'CALLED'
                    LIMIT 1
                ");
                $stmtFind->bind_param("iiis", $target_ticket_id, $office_id, $counter_id, $today);
            } else {
                $stmtFind = $db_connect->prepare("
                    SELECT id, ticket_number
                    FROM queue_tickets
                    WHERE office_id = ?
                      AND counter_id = ?
                      AND served_by_user_id = ?
                      AND queue_date = ?
                      AND status = 'CALLED'
                    ORDER BY called_at DESC, id DESC
                    LIMIT 1
                ");
                $stmtFind->bind_param("iiis", $office_id, $counter_id, $user_id, $today);
            }
            $stmtFind->execute();
            $targetTicket = $stmtFind->get_result()->fetch_assoc();
            $stmtFind->close();

            if ($targetTicket) {
                $targetId = (int)$targetTicket['id'];
                // Claim ownership (served_by_user_id = ?) so whichever staff clicks Serve gets it on their display
                $stmtServe = $db_connect->prepare("
                    UPDATE queue_tickets 
                    SET status = 'SERVING', served_at = NOW(), served_by_user_id = ? 
                    WHERE id = ?
                      AND office_id = ?
                      AND counter_id = ?
                      AND status = 'CALLED'
                ");
                $stmtServe->bind_param("iiii", $user_id, $targetId, $office_id, $counter_id);
                $stmtServe->execute();
                $affected = $stmtServe->affected_rows;
                $stmtServe->close();

                if ($affected > 0) {
                    $wsLabel = resolveWorkstationLabel($db_connect, $office_id, $counter_id, $my_windows);
                    record_activity_log(
                        $db_connect,
                        $user_id,
                        "QUEUE_SERVE: Started serving ticket {$targetTicket['ticket_number']} at {$wsLabel}",
                        $g_user_role_upper
                    );

                    echo json_encode(['success' => true, 'message' => "Now serving {$targetTicket['ticket_number']}."]);
                    exit();
                }
            }

            echo json_encode(['success' => false, 'message' => 'Ticket is no longer available in the Calling Line (may have been served by another staff).']);
            exit();

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Serve failed: ' . $e->getMessage()]);
            exit();
        }

    case 'recall_ticket':
        if ($office_id <= 0 || $counter_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'No active workstation assignment detected.']);
            exit();
        }

        $target_ticket_id = (int)($_POST['ticket_id'] ?? 0);

        if ($target_ticket_id > 0) {
            $stmtRecall = $db_connect->prepare("
                SELECT id, ticket_number 
                FROM queue_tickets 
                WHERE id = ?
                  AND office_id = ?
                  AND counter_id = ?
                  AND queue_date = ?
                  AND status IN ('CALLED', 'SERVING')
                LIMIT 1
            ");
            $stmtRecall->bind_param("iiis", $target_ticket_id, $office_id, $counter_id, $today);
        } else {
            $stmtRecall = $db_connect->prepare("
                SELECT id, ticket_number 
                FROM queue_tickets 
                WHERE office_id = ?
                  AND counter_id = ?
                  AND served_by_user_id = ?
                  AND queue_date = ?
                  AND status IN ('SERVING', 'CALLED') 
                ORDER BY (status = 'SERVING') DESC, called_at DESC, id DESC
                LIMIT 1
            ");
            $stmtRecall->bind_param("iiis", $office_id, $counter_id, $user_id, $today);
        }
        $stmtRecall->execute();
        $recallRow = $stmtRecall->get_result()->fetch_assoc();
        $stmtRecall->close();

        if ($recallRow) {
            $stmtTouch = $db_connect->prepare("
                UPDATE queue_tickets 
                SET called_at = COALESCE(called_at, NOW()) 
                WHERE id = ?
                  AND office_id = ?
                  AND counter_id = ?
            ");
            $stmtTouch->bind_param("iii", $recallRow['id'], $office_id, $counter_id);
            $stmtTouch->execute();
            $stmtTouch->close();

            $wsLabel = resolveWorkstationLabel($db_connect, $office_id, $counter_id, $my_windows);
            record_activity_log(
                $db_connect,
                $user_id,
                "QUEUE_RECALL: Recalled ticket {$recallRow['ticket_number']} at {$wsLabel}",
                $g_user_role_upper
            );

            echo json_encode(['success' => true, 'message' => 'Ticket callout repeated.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'No active ticket to recall.']);
        }
        exit();

    case 'complete_ticket':
        if ($office_id <= 0 || $counter_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'No active workstation assignment detected.']);
            exit();
        }

        $target_ticket_id = (int)($_POST['ticket_id'] ?? 0);

        if ($target_ticket_id > 0) {
            $stmtFind = $db_connect->prepare("
                SELECT id, ticket_number
                FROM queue_tickets
                WHERE id = ?
                  AND office_id = ?
                  AND counter_id = ?
                  AND served_by_user_id = ?
                  AND queue_date = ?
                  AND status IN ('CALLED', 'SERVING')
                LIMIT 1
            ");
            $stmtFind->bind_param("iiiis", $target_ticket_id, $office_id, $counter_id, $user_id, $today);
        } else {
            $stmtFind = $db_connect->prepare("
                SELECT id, ticket_number
                FROM queue_tickets
                WHERE office_id = ?
                  AND counter_id = ?
                  AND served_by_user_id = ?
                  AND queue_date = ?
                  AND status IN ('SERVING', 'CALLED')
                ORDER BY (status = 'SERVING') DESC, called_at DESC, id DESC
                LIMIT 1
            ");
            $stmtFind->bind_param("iiis", $office_id, $counter_id, $user_id, $today);
        }
        $stmtFind->execute();
        $activeRow = $stmtFind->get_result()->fetch_assoc();
        $stmtFind->close();

        if ($activeRow) {
            $activeId = (int)$activeRow['id'];
            $stmtComplete = $db_connect->prepare("
                UPDATE queue_tickets 
                SET status = 'COMPLETED', completed_at = NOW() 
                WHERE id = ?
                  AND counter_id = ?
                  AND served_by_user_id = ?
            ");
            $stmtComplete->bind_param("iii", $activeId, $counter_id, $user_id);
            $stmtComplete->execute();
            $stmtComplete->close();

            $wsLabel = resolveWorkstationLabel($db_connect, $office_id, $counter_id, $my_windows);
            record_activity_log(
                $db_connect,
                $user_id,
                "QUEUE_COMPLETE: Completed ticket {$activeRow['ticket_number']} at {$wsLabel}",
                $g_user_role_upper
            );
        }

        echo json_encode(['success' => true, 'message' => 'Ticket marked completed.']);
        exit();

    case 'skip_ticket':
        if ($office_id <= 0 || $counter_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'No active workstation assignment detected.']);
            exit();
        }

        $target_ticket_id = (int)($_POST['ticket_id'] ?? 0);

        if ($target_ticket_id > 0) {
            $stmtFind = $db_connect->prepare("
                SELECT id, ticket_number
                FROM queue_tickets
                WHERE id = ?
                  AND office_id = ?
                  AND counter_id = ?
                  AND queue_date = ?
                  AND status IN ('CALLED', 'SERVING')
                LIMIT 1
            ");
            $stmtFind->bind_param("iiis", $target_ticket_id, $office_id, $counter_id, $today);
        } else {
            $stmtFind = $db_connect->prepare("
                SELECT id, ticket_number
                FROM queue_tickets
                WHERE office_id = ?
                  AND counter_id = ?
                  AND served_by_user_id = ?
                  AND queue_date = ?
                  AND status IN ('SERVING', 'CALLED')
                ORDER BY (status = 'SERVING') DESC, called_at DESC, id DESC
                LIMIT 1
            ");
            $stmtFind->bind_param("iiis", $office_id, $counter_id, $user_id, $today);
        }
        $stmtFind->execute();
        $activeRow = $stmtFind->get_result()->fetch_assoc();
        $stmtFind->close();

        if ($activeRow) {
            $activeId = (int)$activeRow['id'];
            $stmtSkip = $db_connect->prepare("
                UPDATE queue_tickets 
                SET status = 'SKIPPED', completed_at = NOW() 
                WHERE id = ?
                  AND office_id = ?
                  AND counter_id = ?
            ");
            $stmtSkip->bind_param("iii", $activeId, $office_id, $counter_id);
            $stmtSkip->execute();
            $stmtSkip->close();

            $wsLabel = resolveWorkstationLabel($db_connect, $office_id, $counter_id, $my_windows);
            record_activity_log(
                $db_connect,
                $user_id,
                "QUEUE_SKIP: Skipped ticket {$activeRow['ticket_number']} at {$wsLabel}",
                $g_user_role_upper
            );
        }

        echo json_encode(['success' => true, 'message' => 'Ticket skipped.']);
        exit();

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid queue action.']);
        exit();
}