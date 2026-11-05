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

// Support both POST and GET payloads
$action = $_REQUEST['action'] ?? '';

switch ($action) {

    case 'fetch_analytics':
        try {
            $office_id    = (int)($_REQUEST['office_id'] ?? 0);
            $visitor_type = trim($_REQUEST['visitor_type'] ?? '');
            $date_from    = trim($_REQUEST['date_from'] ?? date('Y-m-d'));
            $date_to      = trim($_REQUEST['date_to'] ?? date('Y-m-d'));

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

            if (!empty($date_from) && !empty($date_to)) {
                $whereClauses[] = "q.queue_date BETWEEN ? AND ?";
                $params[]       = $date_from;
                $params[]       = $date_to;
                $paramTypes    .= "ss";
            }

            $whereSql = implode(" AND ", $whereClauses);

            // A. KPI METRICS
            $kpiSql = "
                SELECT 
                    COUNT(q.id) AS total_tickets,
                    SUM(CASE WHEN q.status = 'COMPLETED' THEN 1 ELSE 0 END) AS completed_tickets,
                    SUM(CASE WHEN q.status = 'SKIPPED' THEN 1 ELSE 0 END) AS skipped_tickets,
                    ROUND(AVG(CASE WHEN q.served_at IS NOT NULL AND q.completed_at IS NOT NULL 
                                   THEN TIMESTAMPDIFF(MINUTE, q.served_at, q.completed_at) ELSE NULL END), 1) AS avg_service_minutes
                FROM queue_tickets q
                INNER JOIN logbooks l ON q.logbook_id = l.id
                WHERE {$whereSql}
            ";
            $stmtKpi = $db_connect->prepare($kpiSql);
            if (!empty($params)) {
                $stmtKpi->bind_param($paramTypes, ...$params);
            }
            $stmtKpi->execute();
            $kpiData = $stmtKpi->get_result()->fetch_assoc();

            // Sanitize NULL KPI values to 0
            if ($kpiData) {
                $kpiData['total_tickets']       = (int)($kpiData['total_tickets'] ?? 0);
                $kpiData['completed_tickets']   = (int)($kpiData['completed_tickets'] ?? 0);
                $kpiData['skipped_tickets']     = (int)($kpiData['skipped_tickets'] ?? 0);
                $kpiData['avg_service_minutes'] = (float)($kpiData['avg_service_minutes'] ?? 0);
            }

            // B. VOLUME TREND (Grouped by Date if multiple days, or Hour if single day)
            $isSingleDay = ($date_from === $date_to);
            if ($isSingleDay) {
                $trendSql = "
                    SELECT HOUR(l.created_at) AS hr, COUNT(q.id) AS total
                    FROM queue_tickets q
                    INNER JOIN logbooks l ON q.logbook_id = l.id
                    WHERE {$whereSql}
                    GROUP BY HOUR(l.created_at)
                    ORDER BY hr ASC
                ";
            } else {
                $trendSql = "
                    SELECT q.queue_date AS label_date, COUNT(q.id) AS total
                    FROM queue_tickets q
                    INNER JOIN logbooks l ON q.logbook_id = l.id
                    WHERE {$whereSql}
                    GROUP BY q.queue_date
                    ORDER BY q.queue_date ASC
                ";
            }

            $stmtTrend = $db_connect->prepare($trendSql);
            if (!empty($params)) {
                $stmtTrend->bind_param($paramTypes, ...$params);
            }
            $stmtTrend->execute();
            $resTrend = $stmtTrend->get_result();

            $volumeTrend = [];
            while ($row = $resTrend->fetch_assoc()) {
                if ($isSingleDay) {
                    $hr = (int)$row['hr'];
                    $formattedHr = date("g A", strtotime("{$hr}:00"));
                    $volumeTrend[] = ['label' => $formattedHr, 'total' => (int)$row['total']];
                } else {
                    $volumeTrend[] = ['label' => $row['label_date'], 'total' => (int)$row['total']];
                }
            }

            // C. VISITOR DISTRIBUTION
            $visitorSql = "
                SELECT UPPER(TRIM(l.visitor_type)) AS type, COUNT(q.id) AS total
                FROM queue_tickets q
                INNER JOIN logbooks l ON q.logbook_id = l.id
                WHERE {$whereSql}
                GROUP BY UPPER(TRIM(l.visitor_type))
            ";
            $stmtVisitor = $db_connect->prepare($visitorSql);
            if (!empty($params)) {
                $stmtVisitor->bind_param($paramTypes, ...$params);
            }
            $stmtVisitor->execute();
            $resVisitor = $stmtVisitor->get_result();

            $visitorDistribution = [];
            while ($row = $resVisitor->fetch_assoc()) {
                $visitorDistribution[] = [
                    'type'  => ucfirst(strtolower($row['type'] ?: 'Unknown')),
                    'total' => (int)$row['total']
                ];
            }

            // D. TOP TRANSACTION CONCERNS
            $concernsSql = "
                SELECT tt.transaction_name AS concern, COUNT(q.id) AS total
                FROM queue_tickets q
                INNER JOIN logbooks l ON q.logbook_id = l.id
                INNER JOIN transaction_types tt ON l.transaction_type_id = tt.id
                WHERE {$whereSql}
                GROUP BY tt.id, tt.transaction_name
                ORDER BY total DESC LIMIT 5
            ";
            $stmtConcerns = $db_connect->prepare($concernsSql);
            if (!empty($params)) {
                $stmtConcerns->bind_param($paramTypes, ...$params);
            }
            $stmtConcerns->execute();
            $resConcerns = $stmtConcerns->get_result();

            $topConcerns = [];
            while ($row = $resConcerns->fetch_assoc()) {
                $topConcerns[] = [
                    'concern' => $row['concern'],
                    'total'   => (int)$row['total']
                ];
            }

            // E. STAFF PERFORMANCE BREAKDOWN
            $staffSql = "
                SELECT 
                    CONCAT(su.first_name, ' ', su.last_name) AS staff_name,
                    COUNT(q.id) AS total_served,
                    SUM(CASE WHEN q.status = 'COMPLETED' THEN 1 ELSE 0 END) AS total_completed
                FROM queue_tickets q
                INNER JOIN logbooks l ON q.logbook_id = l.id
                INNER JOIN users su ON q.served_by_user_id = su.id
                WHERE {$whereSql}
                GROUP BY su.id
                ORDER BY total_served DESC
            ";
            $stmtStaff = $db_connect->prepare($staffSql);
            if (!empty($params)) {
                $stmtStaff->bind_param($paramTypes, ...$params);
            }
            $stmtStaff->execute();
            $resStaff = $stmtStaff->get_result();

            $staffBreakdown = [];
            while ($row = $resStaff->fetch_assoc()) {
                $row['total_served']    = (int)($row['total_served'] ?? 0);
                $row['total_completed'] = (int)($row['total_completed'] ?? 0);
                $staffBreakdown[]       = $row;
            }

            echo json_encode([
                'success' => true,
                'data'    => [
                    'kpi'                  => $kpiData,
                    'volume_trend'         => $volumeTrend,
                    'visitor_distribution' => $visitorDistribution,
                    'top_concerns'         => $topConcerns,
                    'staff_breakdown'      => $staffBreakdown
                ]
            ]);
            exit();

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Analytics fetch error: ' . $e->getMessage()]);
            exit();
        }

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        exit();
}