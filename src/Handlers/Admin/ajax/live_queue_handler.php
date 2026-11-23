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

// 3. Resolve the office(s) an ADMIN_STAFF user is assigned to.
$assigned_office_ids = [];
if ($g_user_role_upper === 'ADMIN_STAFF' && $user_id > 0) {
    $stmtStaff = $db_connect->prepare("
        SELECT DISTINCT office_id
        FROM staff_assignments
        WHERE user_id = ? AND flag_status = 0 AND office_id > 0
    ");
    if ($stmtStaff) {
        $stmtStaff->bind_param("i", $user_id);
        $stmtStaff->execute();
        $resStaff = $stmtStaff->get_result();
        while ($r = $resStaff->fetch_assoc()) {
            $assigned_office_ids[] = (int)$r['office_id'];
        }
        $stmtStaff->close();
    }

    if (empty($assigned_office_ids)) {
        $stmtEmp = $db_connect->prepare("SELECT office_id FROM employee WHERE user_id = ? AND office_id > 0 LIMIT 1");
        if ($stmtEmp) {
            $stmtEmp->bind_param("i", $user_id);
            $stmtEmp->execute();
            $empRow = $stmtEmp->get_result()->fetch_assoc();
            if ($empRow) {
                $assigned_office_ids[] = (int)$empRow['office_id'];
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

            $visitor_filter = "";
            if ($queue_mode === 'student_guest') {
                $visitor_filter = " AND l.visitor_type IN ('STUDENT', 'GUEST') ";
            } elseif ($queue_mode === 'employee') {
                $visitor_filter = " AND l.visitor_type = 'EMPLOYEE' ";
            }

            $office_scope_sql = '';
            if (!empty($assigned_office_ids)) {
                $office_scope_sql = ' AND o.office_id IN (' . implode(',', array_map('intval', $assigned_office_ids)) . ') ';
            }

            // 1. All windows & default Window 1 per office
            $windows = [];
            $counter_names = [];
            $default_counter_by_office = [];
            $resWin = $db_connect->query("
                SELECT c.id AS counter_id, c.counter_name, c.flag_status AS counter_flag,
                       o.office_id, o.office_code, o.office_name, o.office_logo
                FROM counters c
                INNER JOIN office o ON o.office_id = c.office_id
                WHERE o.flag_status = 0 {$office_scope_sql}
                ORDER BY o.office_id ASC, c.id ASC
            ");
            $offices = [];
            while ($w = $resWin->fetch_assoc()) {
                $oid = (int)$w['office_id'];
                $cid = (int)$w['counter_id'];
                $counter_names[$cid] = $w['counter_name'];
                $offices[$oid] = [
                    'office_id'   => $oid,
                    'office_code' => $w['office_code'],
                    'office_name' => $w['office_name'],
                    'office_logo' => $w['office_logo'],
                ];
                if ((int)$w['counter_flag'] === 0) {
                    $windows[] = $w;
                    if (!isset($default_counter_by_office[$oid])) {
                        $default_counter_by_office[$oid] = [
                            'id'   => $cid,
                            'name' => $w['counter_name'] ?: 'Window 1',
                        ];
                    }
                }
            }

            $resOff = $db_connect->query("
                SELECT o.office_id, o.office_code, o.office_name, o.office_logo
                FROM office o
                WHERE o.flag_status = 0 {$office_scope_sql}
            ");
            while ($o = $resOff->fetch_assoc()) {
                $offices[(int)$o['office_id']] = [
                    'office_id'   => (int)$o['office_id'],
                    'office_code' => $o['office_code'],
                    'office_name' => $o['office_name'],
                    'office_logo' => $o['office_logo'],
                ];
            }

            $office_ids_sql = empty($offices) ? '0' : implode(',', array_map('intval', array_keys($offices)));

            // Helper: normalize unassigned counter_id (0) to the office's Window 1
            $resolveCounterId = function (int $oid, int $rawCid) use ($default_counter_by_office): int {
                if ($rawCid > 0) {
                    return $rawCid;
                }
                return $default_counter_by_office[$oid]['id'] ?? 0;
            };

            // 2. ALL currently CALLED / SERVING tickets per window (newest first)
            $now_list_by_key = [];
            $stmtNow = $db_connect->prepare("
                SELECT q.id AS ticket_id, q.office_id, q.counter_id, q.ticket_number, q.status,
                       q.is_priority, q.called_at, q.served_by_user_id, l.visitor_type
                FROM queue_tickets q
                INNER JOIN logbooks l ON q.logbook_id = l.id
                WHERE q.queue_date = ?
                  AND q.status IN ('CALLED', 'SERVING')
                  AND q.office_id IN ({$office_ids_sql})
                  {$visitor_filter}
                ORDER BY q.called_at DESC, q.id DESC
            ");
            $stmtNow->bind_param("s", $today);
            $stmtNow->execute();
            $resNow = $stmtNow->get_result();
            while ($r = $resNow->fetch_assoc()) {
                $oid = (int)$r['office_id'];
                $cid = $resolveCounterId($oid, (int)$r['counter_id']);
                $key = $oid . '_' . $cid;
                $calledTs = !empty($r['called_at']) ? strtotime($r['called_at']) : 0;
                $now_list_by_key[$key][] = [
                    'ticket_id'         => (int)$r['ticket_id'],
                    'ticket_number'     => $r['ticket_number'],
                    'status'            => $r['status'],
                    'is_priority'       => (int)$r['is_priority'],
                    'called_at'         => $r['called_at'],
                    'called_age'        => $calledTs ? max(0, time() - $calledTs) : null,
                    'served_by_user_id' => (int)($r['served_by_user_id'] ?? 0),
                    'visitor_type'      => $r['visitor_type'],
                ];
            }
            $stmtNow->close();

            // 3. Waiting tickets (route counter_id = 0 to Window 1)
            $wait_buckets = [];
            $stmtWait = $db_connect->prepare("
                SELECT q.id, q.office_id, q.counter_id, q.ticket_number, q.is_priority, q.queue_sequence, l.visitor_type, l.created_at AS checkin_at
                FROM queue_tickets q
                INNER JOIN logbooks l ON q.logbook_id = l.id
                WHERE q.queue_date = ?
                  AND q.status = 'WAITING'
                  AND q.office_id IN ({$office_ids_sql})
                  {$visitor_filter}
                ORDER BY q.is_priority DESC, q.queue_sequence ASC
            ");
            $stmtWait->bind_param("s", $today);
            $stmtWait->execute();
            $resWait = $stmtWait->get_result();
            while ($r = $resWait->fetch_assoc()) {
                $oid = (int)$r['office_id'];
                $cid = $resolveCounterId($oid, (int)$r['counter_id']);
                $wait_buckets[$oid][$cid][] = [
                    'ticket_number'  => $r['ticket_number'],
                    'is_priority'    => (int)$r['is_priority'],
                    'visitor_type'   => $r['visitor_type'],
                    'queue_sequence' => (int)$r['queue_sequence'],
                    'checkin_at'     => (string)$r['checkin_at'],
                ];
            }
            $stmtWait->close();

            $build_waiting = function (int $oid, int $cid) use ($wait_buckets) {
                $all = $wait_buckets[$oid][$cid] ?? [];
                usort($all, function ($a, $b) {
                    if ($a['is_priority'] !== $b['is_priority']) {
                        return $b['is_priority'] - $a['is_priority'];
                    }
                    return $a['queue_sequence'] - $b['queue_sequence'];
                });
                $latest_checkin = '';
                foreach ($all as $t) {
                    if ($t['checkin_at'] > $latest_checkin) {
                        $latest_checkin = $t['checkin_at'];
                    }
                }
                $top = [];
                foreach (array_slice($all, 0, 4) as $t) {
                    $top[] = [
                        'ticket_number' => $t['ticket_number'],
                        'is_priority'   => $t['is_priority'],
                        'visitor_type'  => $t['visitor_type'],
                    ];
                }
                return [$top, count($all), $latest_checkin];
            };

            // 4. One pillar per office + window
            $pillars = [];
            $add_pillar = function (array $office, int $cid, string $counter_name) use (&$pillars, $now_list_by_key, $build_waiting) {
                $oid = (int)$office['office_id'];
                $key = $oid . '_' . $cid;
                if (isset($pillars[$key])) {
                    return;
                }
                [$up_next, $waiting_count, $latest_wait] = $build_waiting($oid, $cid);
                $now_list = $now_list_by_key[$key] ?? [];
                $primary_ns = $now_list[0] ?? null;

                $latest_called = '';
                foreach ($now_list as $item) {
                    if ((string)($item['called_at'] ?? '') > $latest_called) {
                        $latest_called = (string)$item['called_at'];
                    }
                }

                $activity_at = max($latest_called, (string)$latest_wait);
                $pillars[$key] = [
                    'pillar_key'       => $key,
                    'office_id'        => $oid,
                    'office_code'      => $office['office_code'],
                    'office_name'      => $office['office_name'],
                    'office_logo'      => $office['office_logo'],
                    'counter_id'       => $cid,
                    'counter_name'     => $counter_name ?: 'Window 1',
                    'now_serving'      => $primary_ns,
                    'now_serving_list' => $now_list,
                    'activity_at'      => $activity_at,
                    'up_next'          => $up_next,
                    'waiting_count'    => $waiting_count,
                ];
            };

            foreach ($windows as $w) {
                $oid = (int)$w['office_id'];
                $cid = (int)$w['counter_id'];
                [, $cnt] = $build_waiting($oid, $cid);
                $hasNow  = !empty($now_list_by_key[$oid . '_' . $cid]);

                if ($hasNow || $cnt > 0) {
                    $add_pillar($offices[$oid], $cid, $w['counter_name']);
                }
            }

            $orphan_keys = array_keys($now_list_by_key);
            foreach ($wait_buckets as $oid => $byCounter) {
                foreach ($byCounter as $cid => $rows) {
                    $orphan_keys[] = $oid . '_' . $cid;
                }
            }
            foreach (array_unique($orphan_keys) as $key) {
                [$oid, $cid] = array_map('intval', explode('_', $key));
                if (isset($pillars[$key]) || !isset($offices[$oid])) {
                    continue;
                }
                $fallbackName = $counter_names[$cid] ?? ($default_counter_by_office[$oid]['name'] ?? 'Window 1');
                $add_pillar($offices[$oid], $cid, $fallbackName);
            }

            $pillars = array_values($pillars);

            usort($pillars, function ($a, $b) {
                $t = strcmp((string)$b['activity_at'], (string)$a['activity_at']);
                if ($t !== 0) {
                    return $t;
                }
                $c = strcasecmp($a['office_name'], $b['office_name']);
                if ($c !== 0) {
                    return $c;
                }
                return strnatcasecmp($a['counter_name'], $b['counter_name']);
            });

            echo json_encode([
                'success' => true,
                'data'    => [
                    'pillars'        => $pillars,
                    'active_offices' => $pillars
                ]
            ]);
            exit();

        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Master queue fetch error: ' . $e->getMessage()]);
            exit();
        }

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action for live queue display.']);
        exit();
}