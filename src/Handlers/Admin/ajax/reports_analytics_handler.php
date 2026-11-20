<?php
// src/Handlers/Admin/ajax/reports_analytics_handler.php

ob_start();

/** @var \mysqli $db_connect */
global $db_connect, $session_class;
$db_connect = $db_connect ?? null;

header('Content-Type: application/json; charset=utf-8');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$sendJson = static function (array $payload): void {
    if (ob_get_length() > 0) {
        ob_clean();
    }
    echo json_encode($payload);
    exit();
};

if (!$db_connect) {
    $sendJson(['success' => false, 'message' => 'Database connection unavailable.']);
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

$action = $_REQUEST['action'] ?? '';
$today  = defined('DATE_NOW') ? DATE_NOW : date('Y-m-d');

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

        $sendJson(['success' => true, 'data' => $counters]);
        break;

    case 'fetch_analytics':
        try {
            $office_id    = (int)($_REQUEST['office_id'] ?? 0);
            $counter_id   = (int)($_REQUEST['counter_id'] ?? 0);
            $visitor_type = strtoupper(trim($_REQUEST['visitor_type'] ?? ''));
            $date_from    = trim($_REQUEST['date_from'] ?? '');
            $date_to      = trim($_REQUEST['date_to'] ?? '');

            // Sanitize filters
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) $date_from = $today;
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to))   $date_to   = $today;
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

            // Counter filter is only valid when scoped to a specific office
            if ($office_id === 0) {
                $counter_id = 0;
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

            // A. KPI METRICS + AVERAGE STAGE TIMES
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

            // A2. OFFICE PERFORMANCE BREAKDOWN (When viewing All Offices: office_id === 0)
            $officeBreakdown = [];
            if ($office_id === 0) {
                foreach ($fetchAll("
                    SELECT
                        o.office_id,
                        o.office_code,
                        o.office_name,
                        COUNT(q.id) AS total_tickets,
                        SUM(q.status = 'COMPLETED') AS total_completed,
                        SUM(q.status = 'SKIPPED') AS total_skipped,
                        ROUND(AVG(CASE WHEN q.called_at IS NOT NULL
                            THEN TIMESTAMPDIFF(SECOND, l.created_at, q.called_at) END) / 60, 1) AS avg_wait_minutes,
                        ROUND(AVG(CASE WHEN q.served_at IS NOT NULL AND q.completed_at IS NOT NULL
                            THEN TIMESTAMPDIFF(SECOND, q.served_at, q.completed_at) END) / 60, 1) AS avg_service_minutes
                    {$base}
                    INNER JOIN office o ON o.office_id = q.office_id
                    WHERE {$whereSql}
                    GROUP BY o.office_id, o.office_code, o.office_name
                    ORDER BY total_tickets DESC, o.office_name ASC
                ") as $r) {
                    $officeBreakdown[] = [
                        'office_id'           => (int)$r['office_id'],
                        'office_code'         => $r['office_code'],
                        'office_name'         => $r['office_name'],
                        'total_tickets'       => (int)$r['total_tickets'],
                        'total_completed'     => (int)$r['total_completed'],
                        'total_skipped'       => (int)$r['total_skipped'],
                        'avg_wait_minutes'    => (float)($r['avg_wait_minutes'] ?? 0),
                        'avg_service_minutes' => (float)($r['avg_service_minutes'] ?? 0)
                    ];
                }
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

            // C. VISITOR DISTRIBUTION (Always include Student, Employee, Guest)
            $visitorCounts = ['STUDENT' => 0, 'EMPLOYEE' => 0, 'GUEST' => 0];
            foreach ($fetchAll("SELECT UPPER(TRIM(l.visitor_type)) AS type, COUNT(q.id) AS total {$base} WHERE {$whereSql} GROUP BY UPPER(TRIM(l.visitor_type))") as $r) {
                $vType = strtoupper(trim((string)($r['type'] ?? '')));
                if (isset($visitorCounts[$vType])) {
                    $visitorCounts[$vType] = (int)$r['total'];
                }
            }
            $visitorDistribution = [
                ['type' => 'Student',  'total' => $visitorCounts['STUDENT']],
                ['type' => 'Employee', 'total' => $visitorCounts['EMPLOYEE']],
                ['type' => 'Guest',    'total' => $visitorCounts['GUEST']]
            ];

            // D. QUEUE STATUS DISTRIBUTION (Completed and Skipped)
            $statusCounts = ['COMPLETED' => 0, 'SKIPPED' => 0];
            foreach ($fetchAll("SELECT UPPER(TRIM(q.status)) AS status, COUNT(q.id) AS total {$base} WHERE {$whereSql} AND q.status IN ('COMPLETED', 'SKIPPED') GROUP BY q.status") as $r) {
                $st = strtoupper((string)$r['status']);
                if (isset($statusCounts[$st])) {
                    $statusCounts[$st] = (int)$r['total'];
                }
            }
            $statusDistribution = [
                ['status' => 'COMPLETED', 'total' => $statusCounts['COMPLETED']],
                ['status' => 'SKIPPED',   'total' => $statusCounts['SKIPPED']]
            ];

            // E. TOP TRANSACTIONS (Includes office_code when All Offices is selected)
            $topConcerns = [];
            $concernGroupBy = ($office_id === 0) ? "o.office_id, o.office_code, concern" : "concern";
            foreach ($fetchAll("
                SELECT
                    o.office_code,
                    CASE
                        WHEN tsc.id IS NOT NULL AND TRIM(tsc.sub_concern_name) <> ''
                            THEN CONCAT(COALESCE(tt.transaction_name, 'Others / General Concern'), ' / ', tsc.sub_concern_name)
                        ELSE COALESCE(tt.transaction_name, 'Others / General Concern')
                    END AS concern,
                    COUNT(DISTINCT q.id) AS total
                {$base}
                INNER JOIN office o ON o.office_id = q.office_id
                LEFT JOIN logbook_transaction_items lti ON lti.logbook_id = l.id
                LEFT JOIN transaction_sub_concerns tsc ON tsc.id = lti.sub_concern_id
                LEFT JOIN transaction_types tt ON tt.id = COALESCE(lti.transaction_type_id, tsc.transaction_type_id, l.transaction_type_id)
                WHERE {$whereSql}
                GROUP BY {$concernGroupBy}
                ORDER BY total DESC, concern ASC
                LIMIT 10
            ") as $r) {
                $topConcerns[] = [
                    'office_code' => $r['office_code'] ?? '',
                    'concern'     => $r['concern'],
                    'total'       => (int)$r['total']
                ];
            }

            // F. WINDOW (COUNTER) PERFORMANCE
            $counterBreakdown = [];
            foreach ($fetchAll("
                SELECT o.office_code,
                       o.office_name,
                       c.counter_name AS counter_label,
                       COUNT(q.id) AS total_tickets,
                       SUM(q.status = 'COMPLETED') AS total_completed,
                       SUM(q.status = 'SKIPPED') AS total_skipped,
                       ROUND(AVG(CASE WHEN q.called_at IS NOT NULL
                           THEN TIMESTAMPDIFF(SECOND, l.created_at, q.called_at) END) / 60, 1) AS avg_wait_minutes,
                       ROUND(AVG(CASE WHEN q.called_at IS NOT NULL AND q.served_at IS NOT NULL
                           THEN TIMESTAMPDIFF(SECOND, q.called_at, q.served_at) END) / 60, 1) AS avg_call_minutes,
                       ROUND(AVG(CASE WHEN q.served_at IS NOT NULL AND q.completed_at IS NOT NULL
                           THEN TIMESTAMPDIFF(SECOND, q.served_at, q.completed_at) END) / 60, 1) AS avg_service_minutes
                {$base}
                INNER JOIN counters c ON c.id = q.counter_id
                INNER JOIN office o ON o.office_id = c.office_id
                WHERE {$whereSql}
                GROUP BY c.id, o.office_code, o.office_name, c.counter_name
                ORDER BY total_tickets DESC, o.office_code ASC, counter_label ASC
            ") as $r) {
                $counterBreakdown[] = [
                    'office_code'         => $r['office_code'],
                    'office_name'         => $r['office_name'],
                    'counter_label'       => $r['counter_label'],
                    'total_tickets'       => (int)$r['total_tickets'],
                    'total_completed'     => (int)$r['total_completed'],
                    'total_skipped'       => (int)$r['total_skipped'],
                    'avg_wait_minutes'    => (float)($r['avg_wait_minutes'] ?? 0),
                    'avg_call_minutes'    => (float)($r['avg_call_minutes'] ?? 0),
                    'avg_service_minutes' => (float)($r['avg_service_minutes'] ?? 0)
                ];
            }

            // G. STAFF PERFORMANCE (Includes office_code & office_name for All Offices view)
            $staffBreakdown = [];
            foreach ($fetchAll("
                SELECT o.office_code,
                       o.office_name,
                       CONCAT(su.first_name, ' ', su.last_name) AS staff_name,
                       COALESCE(c.counter_name, 'Unassigned Window') AS window_label,
                       COUNT(q.id) AS total_served,
                       SUM(q.status = 'COMPLETED') AS total_completed,
                       SUM(q.status = 'SKIPPED') AS total_skipped,
                       ROUND(AVG(CASE WHEN q.served_at IS NOT NULL AND q.completed_at IS NOT NULL
                           THEN TIMESTAMPDIFF(SECOND, q.served_at, q.completed_at) END) / 60, 1) AS avg_service_minutes
                {$base}
                INNER JOIN office o ON o.office_id = q.office_id
                INNER JOIN users su ON q.served_by_user_id = su.id
                LEFT JOIN counters c ON c.id = q.counter_id
                WHERE {$whereSql}
                GROUP BY o.office_id, o.office_code, o.office_name, su.id, su.first_name, su.last_name, c.id, c.counter_name
                ORDER BY o.office_code ASC, staff_name ASC, window_label ASC
            ") as $r) {
                $staffBreakdown[] = [
                    'office_code'         => $r['office_code'],
                    'office_name'         => $r['office_name'],
                    'staff_name'          => $r['staff_name'],
                    'window_label'        => $r['window_label'],
                    'total_served'        => (int)$r['total_served'],
                    'total_completed'     => (int)$r['total_completed'],
                    'total_skipped'       => (int)$r['total_skipped'],
                    'avg_service_minutes' => (float)($r['avg_service_minutes'] ?? 0)
                ];
            }

            $sendJson([
                'success' => true,
                'data'    => [
                    'kpi'                  => $kpi,
                    'office_breakdown'     => $officeBreakdown,
                    'volume_trend'         => $volumeTrend,
                    'visitor_distribution' => $visitorDistribution,
                    'status_distribution'  => $statusDistribution,
                    'top_concerns'         => $topConcerns,
                    'counter_breakdown'    => $counterBreakdown,
                    'staff_breakdown'      => $staffBreakdown
                ]
            ]);

        } catch (Exception $e) {
            $sendJson(['success' => false, 'message' => 'Analytics fetch error: ' . $e->getMessage()]);
        }
        break;

    default:
        $sendJson(['success' => false, 'message' => 'Invalid action.']);
        break;
}