<?php
// src/Handlers/Admin/ajax/queue_handler.php

/** @var \mysqli $db_connect */
global $db_connect;
$db_connect = $db_connect ?? null;

header('Content-Type: application/json');

if (!$db_connect) {
    echo json_encode(['success' => false, 'message' => 'Database connection unavailable.']);
    exit();
}

$action = $_POST['action'] ?? '';

switch ($action) {

    // -------------------------------------------------------------------------
    // 1. FETCH MASTER LIVE QUEUE (For live_queue.php monitor screen)
    // -------------------------------------------------------------------------
    case 'fetch_master_queue':
        $today = date('Y-m-d');

        // Fetch all active offices
        $offices_query = "SELECT office_id, office_code, office_name, office_logo FROM office WHERE flag_status = 0 ORDER BY office_name ASC";
        $offices_res   = $db_connect->query($offices_query);

        $active_offices = [];
        $idle_offices   = [];

        if ($offices_res) {
            while ($off = $offices_res->fetch_assoc()) {
                $office_id = (int)$off['office_id'];

                // 1. Fetch current 'CALLED' or 'SERVING' ticket joining counters table
                $stmtServing = $db_connect->prepare("
                    SELECT 
                        q.ticket_number, 
                        q.status, 
                        q.is_priority, 
                        q.counter_id,
                        COALESCE(c.counter_name, CONCAT('Counter ', q.counter_id)) AS counter_label
                    FROM queue_tickets q
                    LEFT JOIN counters c ON q.counter_id = c.id
                    WHERE q.office_id = ? 
                      AND q.queue_date = ? 
                      AND q.status IN ('CALLED', 'SERVING')
                    ORDER BY q.called_at DESC, q.id DESC
                    LIMIT 1
                ");
                $stmtServing->bind_param("is", $office_id, $today);
                $stmtServing->execute();
                $servingTicket = $stmtServing->get_result()->fetch_assoc();

                // 2. Fetch top 4 'WAITING' tickets for this office ordered by priority and queue sequence
                $stmtNext = $db_connect->prepare("
                    SELECT q.ticket_number, q.is_priority
                    FROM queue_tickets q
                    WHERE q.office_id = ? 
                      AND q.queue_date = ? 
                      AND q.status = 'WAITING'
                    ORDER BY q.is_priority DESC, q.queue_sequence ASC
                    LIMIT 4
                ");
                $stmtNext->bind_param("is", $office_id, $today);
                $stmtNext->execute();
                $nextRes = $stmtNext->get_result();
                
                $upNextList = [];
                while ($nextRow = $nextRes->fetch_assoc()) {
                    $upNextList[] = $nextRow;
                }

                // 3. Count total 'WAITING' tickets for this office
                $stmtCount = $db_connect->prepare("
                    SELECT COUNT(*) as total_waiting 
                    FROM queue_tickets 
                    WHERE office_id = ? AND queue_date = ? AND status = 'WAITING'
                ");
                $stmtCount->bind_param("is", $office_id, $today);
                $stmtCount->execute();
                $waitingCount = (int)($stmtCount->get_result()->fetch_assoc()['total_waiting'] ?? 0);

                // Group as active or idle
                if ($servingTicket || $waitingCount > 0) {
                    $active_offices[] = [
                        'office_id'      => $office_id,
                        'office_code'    => $off['office_code'],
                        'office_name'    => $off['office_name'],
                        'office_logo'    => $off['office_logo'],
                        'counter_number' => $servingTicket['counter_label'] ?? 'Counter 1',
                        'now_serving'    => $servingTicket ?: null,
                        'up_next'        => $upNextList,
                        'waiting_count'  => $waitingCount
                    ];
                } else {
                    $idle_offices[] = [
                        'office_id'   => $office_id,
                        'office_code' => $off['office_code'],
                        'office_name' => $off['office_name']
                    ];
                }
            }
        }

        echo json_encode([
            'success' => true,
            'data'    => [
                'active_offices' => $active_offices,
                'idle_offices'   => $idle_offices
            ]
        ]);
        exit();

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid or missing queue action.']);
        exit();
}