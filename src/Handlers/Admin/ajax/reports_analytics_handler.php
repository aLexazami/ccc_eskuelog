<?php
// src/Handlers/Admin/ajax/reports_analytics_handler.php

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

// Support both POST and GET payloads
$action = $_REQUEST['action'] ?? '';

switch ($action) {

        case 'fetch_counters':
        $office_id = (int)($_REQUEST['office_id'] ?? 0);
        if ($g_user_role_upper === 'ADMIN_STAFF' && $assigned_office_id > 0) {
            $office_id = $assigned_office_id;
        }

        $counters = [];
        if ($office_id > 0) {
            $stmt = $db_connect->prepare("SELECT id, counter_name FROM counters WHERE office_id = ? AND flag_status = 0 ORDER BY counter_name ASC");
            $stmt->bind_param("i", $office_id);
            $stmt->execute();
            $counters = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }

        echo json_encode(['success' => true, 'data' => $counters]);
        exit();

    case 'fetch_analytics':
        try {
            $office_id    = (int)($_REQUEST['office_id'] ?? 0);
            $counter_id   = (int)($_REQUEST['counter_id'] ?? 0);
            $visitor_type = strtoupper(trim($_REQUEST['visitor_type'] ?? ''));
            $date_from    = trim($_REQUEST['date_from'] ?? '');
            $date_to      = trim($_REQUEST['date_to'] ?? '');

            // Sanitize filters
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) $date_from = date('Y-m-d');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to))   $date_to   = date('Y-m-d');
            if ($date_from > $date_to) {
                [$date_from, $date_to] = [$date_to, $date_from];
            }
            if (!in_array($visitor_type, ['STUDENT', 'EMPLOYEE', 'GUEST'], true)) {
                $visitor_type = '';
            }

            // Enforce assigned office for ADMIN_STAFF
            if ($g_user_role_upper === 'ADMIN_STAFF' && $assigned_office_id > 0) {
                $office_id = $assigned_office_id;
            }

            $whereClauses = ["q.queue_date BETWEEN ? AND ?"];
            $params       = [$date_from, $date_to];
            $paramTypes   = "ss";

            if ($office_id > 0) {
                $whereClauses[] = "q.office_id = ?";
                $params[]       = $office_id;
                $paramTypes    .= "i";
            }
            if ($counter_id > 0) {
                $whereClauses[] = "q.counter_id = ?";
                $params[]       = $counter_id;
                $paramTypes    .= "i";
            }
            if ($visitor_type !== '') {
                $whereClauses[] = "UPPER(TRIM(l.visitor_type)) = ?";
                $params[]       = $visitor_type;
                $paramTypes    .= "s";
            }

            $whereSql = implode(" AND ", $whereClauses);
            $base     = "FROM queue_tickets q INNER JOIN logbooks l ON q.logbook_id = l.id";

            $fetchAll = function (string $sql) use ($db_connect, $params, $paramTypes): array {
                $stmt = $db_connect->prepare($sql);
                $stmt->bind_param($paramTypes, ...$params);
                $stmt->execute();
                $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
                return $rows;
            };

            // A. KPI METRICS + AVERAGE STAGE TIMES (same stages as the Transaction Records lifecycle)
            $kpi = $fetchAll("
                SELECT
                    COUNT(q.id) AS total_tickets,
                    SUM(q.status = 'COMPLETED') AS completed_tickets,
                    SUM(q.status = 'SKIPPED') AS skipped_tickets,
                    SUM(q.status IN ('WAITING','CALLED','SERVING')) AS pending_tickets,
                    SUM(q.is_priority = 1) AS priority_tickets,
                    ROUND(AVG(CASE WHEN q.called_at IS NOT NULL
                        THEN TIMESTAMPDIFF(SECOND, l.created_at, q.called_at) END) / 60, 1) AS avg_wait_minutes,
                    ROUND(AVG(CASE WHEN q.called_at IS NOT NULL AND q.served_at IS NOT NULL
                        THEN TIMESTAMPDIFF(SECOND, q.called_at, q.served_at) END) / 60, 1) AS avg_call_minutes,
                    ROUND(AVG(CASE WHEN q.served_at IS NOT NULL AND q.completed_at IS NOT NULL
                        THEN TIMESTAMPDIFF(SECOND, q.served_at, q.completed_at) END) / 60, 1) AS avg_service_minutes
                {$base}
                WHERE {$whereSql}
            ")[0] ?? [];

            foreach (['total_tickets', 'completed_tickets', 'skipped_tickets', 'pending_tickets', 'priority_tickets'] as $k) {
                $kpi[$k] = (int)($kpi[$k] ?? 0);
            }
            foreach (['avg_wait_minutes', 'avg_call_minutes', 'avg_service_minutes'] as $k) {
                $kpi[$k] = (float)($kpi[$k] ?? 0);
            }

            // B. VOLUME TREND
            $isSingleDay = ($date_from === $date_to);
            $volumeTrend = [];
            if ($isSingleDay) {
                $rows = $fetchAll("SELECT HOUR(l.created_at) AS hr, COUNT(q.id) AS total {$base} WHERE {$whereSql} GROUP BY HOUR(l.created_at) ORDER BY hr ASC");
                foreach ($rows as $r) {
                    $volumeTrend[] = ['label' => date("g A", strtotime(((int)$r['hr']) . ":00")), 'total' => (int)$r['total']];
                }
            } else {
                $rows = $fetchAll("SELECT q.queue_date AS label_date, COUNT(q.id) AS total {$base} WHERE {$whereSql} GROUP BY q.queue_date ORDER BY q.queue_date ASC");
                foreach ($rows as $r) {
                    $volumeTrend[] = ['label' => $r['label_date'], 'total' => (int)$r['total']];
                }
            }

            // C. VISITOR DISTRIBUTION
            $visitorDistribution = [];
            foreach ($fetchAll("SELECT UPPER(TRIM(l.visitor_type)) AS type, COUNT(q.id) AS total {$base} WHERE {$whereSql} GROUP BY UPPER(TRIM(l.visitor_type))") as $r) {
                $visitorDistribution[] = ['type' => ucfirst(strtolower($r['type'] ?: 'Unknown')), 'total' => (int)$r['total']];
            }

            // D. QUEUE STATUS DISTRIBUTION
            $statusDistribution = [];
            foreach ($fetchAll("SELECT q.status, COUNT(q.id) AS total {$base} WHERE {$whereSql} GROUP BY q.status") as $r) {
                $statusDistribution[] = ['status' => strtoupper((string)$r['status']), 'total' => (int)$r['total']];
            }

            // E. TOP SERVICES (transaction types) - a ticket is counted once per service
            $topConcerns = [];
            foreach ($fetchAll("
                SELECT COALESCE(tt.transaction_name, 'Others / General Concern') AS concern,
                       COUNT(DISTINCT q.id) AS total
                {$base}
                LEFT JOIN logbook_transaction_items lti ON lti.logbook_id = l.id
                LEFT JOIN transaction_types tt ON tt.id = COALESCE(lti.transaction_type_id, l.transaction_type_id)
                WHERE {$whereSql}
                GROUP BY tt.id, concern
                ORDER BY total DESC
                LIMIT 5
            ") as $r) {
                $topConcerns[] = ['concern' => $r['concern'], 'total' => (int)$r['total']];
            }

            // F. TOP SUB-CONCERNS (per service)
            $subConcerns = [];
            foreach ($fetchAll("
                SELECT tt.transaction_name, tsc.sub_concern_name, COUNT(DISTINCT q.id) AS total
                {$base}
                INNER JOIN logbook_transaction_items lti ON lti.logbook_id = l.id AND lti.sub_concern_id IS NOT NULL
                INNER JOIN transaction_sub_concerns tsc ON tsc.id = lti.sub_concern_id
                LEFT JOIN transaction_types tt ON tt.id = tsc.transaction_type_id
                WHERE {$whereSql}
                GROUP BY tsc.id, tt.transaction_name, tsc.sub_concern_name
                ORDER BY total DESC
                LIMIT 10
            ") as $r) {
                $subConcerns[] = [
                    'transaction_name'  => $r['transaction_name'] ?? 'N/A',
                    'sub_concern_name'  => $r['sub_concern_name'],
                    'total'             => (int)$r['total']
                ];
            }

            // G. WINDOW (COUNTER) PERFORMANCE
            $counterBreakdown = [];
            foreach ($fetchAll("
                SELECT CONCAT(o.office_code, ' - ', c.counter_name) AS counter_label,
                       COUNT(q.id) AS total_tickets,
                       SUM(q.status = 'COMPLETED') AS total_completed,
                       SUM(q.status = 'SKIPPED') AS total_skipped,
                       ROUND(AVG(CASE WHEN q.served_at IS NOT NULL AND q.completed_at IS NOT NULL
                           THEN TIMESTAMPDIFF(SECOND, q.served_at, q.completed_at) END) / 60, 1) AS avg_service_minutes
                {$base}
                INNER JOIN counters c ON c.id = q.counter_id
                INNER JOIN office o ON o.office_id = c.office_id
                WHERE {$whereSql}
                GROUP BY c.id, o.office_code, c.counter_name
                ORDER BY total_tickets DESC, counter_label ASC
            ") as $r) {
                $counterBreakdown[] = [
                    'counter_label'       => $r['counter_label'],
                    'total_tickets'       => (int)$r['total_tickets'],
                    'total_completed'     => (int)$r['total_completed'],
                    'total_skipped'       => (int)$r['total_skipped'],
                    'avg_service_minutes' => (float)($r['avg_service_minutes'] ?? 0)
                ];
            }

            // H. STAFF PERFORMANCE
            $staffBreakdown = [];
            foreach ($fetchAll("
                SELECT CONCAT(su.first_name, ' ', su.last_name) AS staff_name,
                       GROUP_CONCAT(DISTINCT c.counter_name ORDER BY c.counter_name SEPARATOR ', ') AS windows,
                       COUNT(q.id) AS total_served,
                       SUM(q.status = 'COMPLETED') AS total_completed,
                       ROUND(AVG(CASE WHEN q.served_at IS NOT NULL AND q.completed_at IS NOT NULL
                           THEN TIMESTAMPDIFF(SECOND, q.served_at, q.completed_at) END) / 60, 1) AS avg_service_minutes
                {$base}
                INNER JOIN users su ON q.served_by_user_id = su.id
                LEFT JOIN counters c ON c.id = q.counter_id
                WHERE {$whereSql}
                GROUP BY su.id, su.first_name, su.last_name
                ORDER BY total_served DESC
            ") as $r) {
                $staffBreakdown[] = [
                    'staff_name'          => $r['staff_name'],
                    'windows'             => $r['windows'] ?? '',
                    'total_served'        => (int)$r['total_served'],
                    'total_completed'     => (int)$r['total_completed'],
                    'avg_service_minutes' => (float)($r['avg_service_minutes'] ?? 0)
                ];
            }

            echo json_encode([
                'success' => true,
                'data'    => [
                    'kpi'                  => $kpi,
                    'volume_trend'         => $volumeTrend,
                    'visitor_distribution' => $visitorDistribution,
                    'status_distribution'  => $statusDistribution,
                    'top_concerns'         => $topConcerns,
                    'sub_concerns'         => $subConcerns,
                    'counter_breakdown'    => $counterBreakdown,
                    'staff_breakdown'      => $staffBreakdown
                ]
            ]);
            exit();

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Analytics fetch error: ' . $e->getMessage()]);
            exit();
        }
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        exit();
}