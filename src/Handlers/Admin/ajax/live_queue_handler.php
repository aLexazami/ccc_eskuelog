<?php
// src/Handlers/Admin/ajax/live_queue_handler.php

/** @var \mysqli $db_connect */
global $db_connect;
$db_connect = $db_connect ?? null;

header('Content-Type: application/json');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!$db_connect) {
    echo json_encode(['success' => false, 'message' => 'Database connection unavailable.']);
    exit();
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

            // Fetch all active offices
            $officesRes = $db_connect->query("SELECT office_id, office_code, office_name, office_logo FROM office WHERE flag_status = 0 ORDER BY office_name ASC");
            $active_offices = [];

            while ($off = $officesRes->fetch_assoc()) {
                $cur_office_id = (int)$off['office_id'];

                // 1. Fetch currently CALLED or SERVING ticket
                $stmtNow = $db_connect->prepare("
                    SELECT q.ticket_number, q.status, q.is_priority, q.called_at, l.visitor_type
                    FROM queue_tickets q
                    INNER JOIN logbooks l ON q.logbook_id = l.id
                    WHERE q.office_id = ? AND q.queue_date = ? AND q.status IN ('CALLED', 'SERVING') {$visitor_filter}
                    ORDER BY q.called_at DESC, q.id DESC
                    LIMIT 1
                ");
                $stmtNow->bind_param("is", $cur_office_id, $today);
                $stmtNow->execute();
                $now_serving = $stmtNow->get_result()->fetch_assoc();

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

                // Only include offices that have active tickets or queued visitors
                if ($now_serving || count($up_next) > 0 || $waiting_count > 0) {
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