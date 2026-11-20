<?php
// src/Views/Admin/partials/staff_dashboard.php
global $db_connect, $session_class, $g_user_role, $g_user_id;
$db_connect  = $db_connect ?? null;
$g_user_role = $g_user_role ?? '';

# Page Header Initialization
require_once ADMIN_PAGE_HEADER_PATH;

$current_user_id = (int) ($g_user_id ?? $session_class->getValue('user_id') ?? 0);

# -------------------------------------------------------------------------
# 1. RESOLVE ASSIGNED OFFICE FOR ADMIN_STAFF
# -------------------------------------------------------------------------
$assigned_office_id   = 0;
$assigned_office_name = 'Unassigned Office';
$assigned_office_code = '';
$my_counters          = [];

if ($db_connect && $current_user_id > 0) {
    // Primary lookup from staff_assignments[cite: 13]
    $stmtAssign = $db_connect->prepare("
        SELECT o.office_id, o.office_name, o.office_code
        FROM staff_assignments sa
        INNER JOIN office o ON o.office_id = sa.office_id
        WHERE sa.user_id = ? AND sa.flag_status = 0 AND o.flag_status = 0
        LIMIT 1
    ");
    if ($stmtAssign) {
        $stmtAssign->bind_param("i", $current_user_id);
        $stmtAssign->execute();
        if ($row = $stmtAssign->get_result()->fetch_assoc()) {
            $assigned_office_id   = (int) $row['office_id'];
            $assigned_office_name = $row['office_name'];
            $assigned_office_code = $row['office_code'];
        }
        $stmtAssign->close();
    }

    // Fallback to employee.office_id[cite: 13]
    if ($assigned_office_id === 0) {
        $stmtEmp = $db_connect->prepare("
            SELECT o.office_id, o.office_name, o.office_code
            FROM employee e
            INNER JOIN office o ON o.office_id = e.office_id
            WHERE e.user_id = ? AND o.flag_status = 0
            LIMIT 1
        ");
        if ($stmtEmp) {
            $stmtEmp->bind_param("i", $current_user_id);
            $stmtEmp->execute();
            if ($row = $stmtEmp->get_result()->fetch_assoc()) {
                $assigned_office_id   = (int) $row['office_id'];
                $assigned_office_name = $row['office_name'];
                $assigned_office_code = $row['office_code'];
            }
            $stmtEmp->close();
        }
    }
}

$title_page   = "Office Staff Dashboard";
$active_links = [
    ['label' => $title_page, 'url' => '']
];
$page_header = render_page_header((string) $title_page, $active_links) ?? '';

# -------------------------------------------------------------------------
# 2. FETCH TODAY'S OFFICE QUEUE & SERVICE METRICS
# -------------------------------------------------------------------------
$waiting_count      = 0;
$priority_waiting   = 0;
$serving_count      = 0;
$completed_count    = 0;
$skipped_count      = 0;
$total_today        = 0;
$avg_service_mins   = 0;

$live_queue_list    = [];
$office_counters    = [];
$top_services       = [];

if ($db_connect && $assigned_office_id > 0) {
    // A. Queue Status Counts Today for this Office (queue_tickets.queue_date & status)[cite: 13]
    $stmtMetrics = $db_connect->prepare("
        SELECT 
            UPPER(COALESCE(q.status, 'WAITING')) AS q_status,
            COUNT(*) AS total,
            SUM(CASE WHEN q.is_priority = 1 AND UPPER(COALESCE(q.status, 'WAITING')) = 'WAITING' THEN 1 ELSE 0 END) AS prio_wait,
            AVG(
                CASE 
                    WHEN UPPER(q.status) = 'COMPLETED' AND q.served_at IS NOT NULL AND q.completed_at IS NOT NULL 
                    THEN TIMESTAMPDIFF(MINUTE, q.served_at, q.completed_at)
                    ELSE NULL 
                END
            ) AS avg_mins
        FROM queue_tickets q
        WHERE q.office_id = ?
          AND q.queue_date = CURDATE()
        GROUP BY UPPER(COALESCE(q.status, 'WAITING'))
    ");
    if ($stmtMetrics) {
        $stmtMetrics->bind_param("i", $assigned_office_id);
        $stmtMetrics->execute();
        $resMetrics = $stmtMetrics->get_result();
        while ($m = $resMetrics->fetch_assoc()) {
            $st  = $m['q_status'];
            $cnt = (int) $m['total'];
            $total_today += $cnt;

            if ($st === 'WAITING') {
                $waiting_count    = $cnt;
                $priority_waiting = (int) ($m['prio_wait'] ?? 0);
            } elseif ($st === 'CALLED' || $st === 'SERVING') {
                $serving_count += $cnt;
            } elseif ($st === 'COMPLETED') {
                $completed_count  = $cnt;
                $avg_service_mins = (int) round((float) ($m['avg_mins'] ?? 0));
            } elseif ($st === 'SKIPPED' || $st === 'CANCELLED') {
                $skipped_count += $cnt;
            }
        }
        $stmtMetrics->close();
    }

    // B. Active & Waiting Queue List (Currently Called/Serving first, then Priority, then queue_sequence)[cite: 13]
    $stmtQueue = $db_connect->prepare("
        SELECT 
            q.id AS ticket_id,
            q.ticket_number,
            q.queue_sequence,
            UPPER(COALESCE(q.status, 'WAITING')) AS queue_status,
            q.is_priority,
            l.created_at,
            l.visitor_type,
            l.first_name,
            l.last_name,
            l.priority_type,
            tt.transaction_name,
            c.counter_name,
            (
                SELECT GROUP_CONCAT(DISTINCT tsc.sub_concern_name ORDER BY lti.id ASC SEPARATOR ', ')
                FROM logbook_transaction_items lti
                INNER JOIN transaction_sub_concerns tsc ON tsc.id = lti.sub_concern_id
                WHERE lti.logbook_id = l.id
            ) AS sub_concerns
        FROM queue_tickets q
        INNER JOIN logbooks l ON l.id = q.logbook_id
        LEFT JOIN transaction_types tt ON tt.id = l.transaction_type_id
        LEFT JOIN counters c ON c.id = q.counter_id
        WHERE q.office_id = ?
          AND q.queue_date = CURDATE()
          AND UPPER(COALESCE(q.status, 'WAITING')) IN ('CALLED', 'SERVING', 'WAITING')
        ORDER BY 
            CASE WHEN UPPER(COALESCE(q.status, 'WAITING')) IN ('CALLED', 'SERVING') THEN 0 ELSE 1 END ASC,
            q.is_priority DESC,
            q.queue_sequence ASC
        LIMIT 12
    ");
    if ($stmtQueue) {
        $stmtQueue->bind_param("i", $assigned_office_id);
        $stmtQueue->execute();
        $resQueue = $stmtQueue->get_result();
        while ($row = $resQueue->fetch_assoc()) {
            $live_queue_list[] = $row;
        }
        $stmtQueue->close();
    }

    // C. Office Service Counters & Staff Assignments (counters + staff_assignments)[cite: 13]
    $stmtCounters = $db_connect->prepare("
        SELECT 
            c.id AS counter_id,
            c.counter_name,
            c.flag_status,
            GROUP_CONCAT(DISTINCT CONCAT(u.first_name, ' ', u.last_name) ORDER BY u.last_name SEPARATOR ', ') AS staff_names,
            MAX(CASE WHEN sa.user_id = ? THEN 1 ELSE 0 END) AS is_my_counter
        FROM counters c
        LEFT JOIN staff_assignments sa ON sa.counter_id = c.id AND sa.flag_status = 0
        LEFT JOIN users u ON u.id = sa.user_id AND u.flag_status = 0
        WHERE c.office_id = ? AND c.flag_status = 0
        GROUP BY c.id, c.counter_name, c.flag_status
        ORDER BY c.counter_name ASC
    ");
    if ($stmtCounters) {
        $stmtCounters->bind_param("ii", $current_user_id, $assigned_office_id);
        $stmtCounters->execute();
        $resCounters = $stmtCounters->get_result();
        while ($c = $resCounters->fetch_assoc()) {
            $office_counters[] = $c;
            if ((int) $c['is_my_counter'] === 1) {
                $my_counters[] = $c['counter_name'];
            }
        }
        $stmtCounters->close();
    }

    // D. Top Requested Services Today in this Office[cite: 13]
    $stmtServices = $db_connect->prepare("
        SELECT 
            COALESCE(tt.transaction_name, 'General Inquiry') AS service_name,
            COUNT(q.id) AS request_count
        FROM queue_tickets q
        INNER JOIN logbooks l ON l.id = q.logbook_id
        LEFT JOIN transaction_types tt ON tt.id = l.transaction_type_id
        WHERE q.office_id = ?
          AND q.queue_date = CURDATE()
        GROUP BY tt.id, tt.transaction_name
        ORDER BY request_count DESC
        LIMIT 5
    ");
    if ($stmtServices) {
        $stmtServices->bind_param("i", $assigned_office_id);
        $stmtServices->execute();
        $resServices = $stmtServices->get_result();
        while ($s = $resServices->fetch_assoc()) {
            $top_services[] = $s;
        }
        $stmtServices->close();
    }
}
?>
<!DOCTYPE html>
<html lang="<?= LANG; ?>">

<head>
    <?php include_once ADMIN_META_DATA_PATH; ?>
    <?php include_once ADMIN_LINK_PATH; ?>

    <style>
        /* E-ESKUELOG | Office Staff Dashboard */
        .staff-dashboard {
            --sd-ink: #172b4d;
            --sd-muted: #718096;
            --sd-line: #e8edf4;
            --sd-blue: #2457c5;
            padding-bottom: 2rem;
            color: var(--sd-ink);
        }
        .staff-dashboard .office-banner {
            position: relative; overflow: hidden;
            border: 1px solid #dfe8f5; border-radius: 1.15rem;
            background: radial-gradient(circle at 92% 5%, rgba(93,151,255,.19), transparent 28%),
                        linear-gradient(120deg,#fff 0%,#f5f8ff 68%,#edf4ff 100%);
            box-shadow: 0 8px 24px rgba(28,52,91,.055);
        }
        .staff-dashboard .office-banner::after {
            content: ""; position: absolute; width: 190px; height: 190px;
            right: -75px; bottom: -125px; border: 24px solid rgba(36,87,197,.055);
            border-radius: 50%; pointer-events: none;
        }
        .staff-dashboard .office-banner h3 {
            color: var(--sd-ink) !important; letter-spacing: -.035em;
            font-size: clamp(1.35rem,2vw,1.8rem);
        }
        .staff-dashboard .office-banner p { max-width: 680px; line-height: 1.6; }
        .staff-dashboard .office-heading h3 {
            color: #142b50 !important;
            font-size: clamp(1.55rem, 2.5vw, 2.15rem);
            line-height: 1.15;
            letter-spacing: -.035em;
        }
        .staff-dashboard .office-code-chip {
            display: inline-flex; align-items: center;
            padding: .35rem .7rem; border-radius: 999px;
            background: #e8efff; border: 1px solid #cbd9fb;
            color: #214da8; font-size: .76rem; font-weight: 800;
            letter-spacing: .045em;
        }
        .staff-dashboard .assigned-windows-panel {
            display: inline-block; min-width: min(100%, 360px);
            margin-top: .15rem; padding: .75rem .9rem;
            border: 1px solid #c9d9f6; border-left: 4px solid #2457c5;
            border-radius: .8rem; background: #edf3ff;
        }
        .staff-dashboard .assigned-windows-label {
            color: #234b91; font-size: .72rem; font-weight: 850;
            letter-spacing: .075em;
        }
        .staff-dashboard .assigned-window-chip {
            display: inline-flex; align-items: center;
            padding: .4rem .7rem; border-radius: .55rem;
            background: #fff; border: 1px solid #b9cdf4;
            color: #173e82; font-size: .88rem; font-weight: 750;
            box-shadow: 0 1px 2px rgba(23, 62, 130, .06);
        }
        .staff-dashboard .assigned-window-empty {
            color: #684b12; font-size: .84rem; font-weight: 650;
        }
        .staff-dashboard .office-banner .btn {
            border-radius: .7rem !important; font-size: .86rem;
            padding: .7rem 1rem !important; position: relative; z-index: 1;
        }
        .staff-dashboard .card {
            border: 1px solid var(--sd-line) !important; border-radius: 1rem !important;
            box-shadow: 0 4px 16px rgba(27,46,77,.045) !important;
            transition: box-shadow .18s ease, transform .18s ease;
        }
        .staff-dashboard .card-header {
            background: #fff; border-color: var(--sd-line) !important;
            border-radius: 1rem 1rem 0 0 !important;
        }
        .staff-dashboard .stat-icon-wrapper {
            width: 66px; height: 66px; border-radius: 17px;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 1.9rem; flex: 0 0 66px;
        }
        .staff-dashboard .dashboard-kpis .card > .d-flex {
            justify-content: flex-start !important;
            align-items: center !important;
            gap: 1rem;
        }
        .staff-dashboard .dashboard-kpis .kpi-content {
            min-width: 0; flex: 1 1 auto;
        }
        .staff-dashboard .dashboard-kpis .card {
            min-height: 128px; padding: 1.15rem !important;
            position: relative; overflow: hidden;
        }
        .staff-dashboard .dashboard-kpis .card::before {
            content: ""; position: absolute; inset: 0 auto 0 0;
            width: 4px; background: #d5dfef;
        }
        .staff-dashboard .dashboard-kpis .col-12:nth-child(1) .card::before { background: #e8ae35; }
        .staff-dashboard .dashboard-kpis .col-12:nth-child(2) .card::before { background: #3478db; }
        .staff-dashboard .dashboard-kpis .col-12:nth-child(3) .card::before { background: #25a477; }
        .staff-dashboard .dashboard-kpis .col-12:nth-child(4) .card::before { background: #8492a6; }
        .staff-dashboard .dashboard-kpis h2 {
            font-size: clamp(1.65rem,2.4vw,2rem); letter-spacing: -.04em; line-height: 1.1;
        }
        .staff-dashboard .staff-shortcut-card {
            border: 1px solid var(--sd-line); border-radius: .85rem;
            background: #fff; min-height: 88px;
            transition: border-color .18s ease,box-shadow .18s ease,transform .18s ease;
        }
        .staff-dashboard .staff-shortcut-card:hover {
            border-color: #b8cafa; transform: translateY(-2px);
            box-shadow: 0 9px 20px rgba(36,87,197,.09);
        }
        .staff-dashboard .staff-shortcut-card h6 { font-size: .9rem; letter-spacing: -.01em; }
        .staff-dashboard .table { --bs-table-hover-bg: #f7f9fd; color: #34445d; }
        .staff-dashboard .table thead th {
            background: #f7f9fc; color: #718096; border-bottom: 1px solid var(--sd-line);
            font-size: .7rem; font-weight: 700; letter-spacing: .055em;
            text-transform: uppercase; white-space: nowrap;
            padding-top: .9rem; padding-bottom: .9rem;
        }
        .staff-dashboard .table tbody td {
            border-color: #eef1f6; padding-top: .9rem; padding-bottom: .9rem;
        }
        .staff-dashboard .table tbody tr:last-child td { border-bottom: 0; }
        .staff-dashboard .table .badge,
        .staff-dashboard .office-counters .badge {
            border-radius: 999px; padding: .38rem .62rem;
            font-weight: 650; letter-spacing: .01em;
        }
        .staff-dashboard .office-counter-item {
            border-radius: .8rem !important; border-color: var(--sd-line) !important;
        }
        .staff-dashboard .section-heading {
            color: var(--sd-ink); font-size: .96rem;
            font-weight: 750; letter-spacing: -.015em;
        }
        .staff-dashboard .text-muted { color: var(--sd-muted) !important; }
        @media (max-width: 767.98px) {
            .staff-dashboard .office-banner { padding: 1.15rem !important; }
            .staff-dashboard .dashboard-kpis .card { min-height: 112px; }
            .staff-dashboard .table { min-width: 720px; }
            .staff-dashboard .card-header { padding-left: 1rem !important; padding-right: 1rem !important; }
        }
        @media (prefers-reduced-motion: reduce) {
            .staff-dashboard .card,
            .staff-dashboard .staff-shortcut-card { transition: none; }
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include_once ADMIN_SIDEBAR_PATH; ?>

        <div class="main-panel">
            <?php include_once ADMIN_HEADER_PATH; ?>

            <div class="container">
                <div class="page-inner px-2 px-sm-3 staff-dashboard">

                    <!-- OFFICE WORKSTATION HEADER BANNER -->
                    <div class="office-banner p-3 p-md-4 mb-4 shadow-sm">
                        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                            <div class="office-heading">
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                    <h3 class="fw-bold mb-0"><?= htmlspecialchars($assigned_office_name, ENT_QUOTES, 'UTF-8'); ?></h3>
                                    <?php if ($assigned_office_code !== ''): ?>
                                        <span class="office-code-chip"><?= htmlspecialchars($assigned_office_code, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endif; ?>
                                </div>

                                <div class="assigned-windows-panel">
                                    <span class="assigned-windows-label">
                                        <i class="bi bi-window-dock me-2"></i>YOUR ASSIGNED WINDOW<?= count($my_counters) === 1 ? '' : 'S'; ?>
                                    </span>
                                    <div class="d-flex flex-wrap gap-2 mt-2">
                                        <?php if (!empty($my_counters)): ?>
                                            <?php foreach ($my_counters as $counterName): ?>
                                                <span class="assigned-window-chip">
                                                    <i class="bi bi-check-circle-fill me-1"></i>
                                                    <?= htmlspecialchars($counterName, ENT_QUOTES, 'UTF-8'); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="assigned-window-empty">No service window assigned to your account</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap gap-2">
                                <a href="<?= BASE_URL; ?>admin/live-queue" target="_blank" class="btn btn-outline-primary rounded-3 fw-semibold px-3 py-2">
                                    <i class="bi bi-tv-fill me-1"></i> Live Queue Monitor
                                </a>
                                <a href="<?= BASE_URL; ?>admin/queue-controller" target="_blank" class="btn btn-primary rounded-3 fw-bold px-3 py-2 shadow-sm">
                                    <i class="bi bi-person-workspace me-1"></i> Open Service Counter
                                </a>
                            </div>
                        </div>
                    </div>

                    <?php if ($assigned_office_id === 0): ?>
                        <div class="alert alert-warning border-0 shadow-sm rounded-4 d-flex align-items-center gap-3 mb-4" role="alert">
                            <i class="bi bi-exclamation-triangle-fill fs-4 text-warning"></i>
                            <div>
                                <div class="fw-bold">No Office Assignment Found</div>
                                <div class="small">Your account is not currently linked to an active campus office. Please contact the System Administrator to assign your office.</div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- 1. TODAY'S OFFICE QUEUE KPI CARDS -->
                    <div class="row g-3 mb-4 dashboard-kpis">
                        <!-- Waiting in Queue -->
                        <div class="col-12 col-sm-6 col-xl-3">
                            <div class="card border-0 shadow-sm rounded-4 h-100 p-3">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="stat-icon-wrapper bg-warning-subtle text-warning">
                                        <i class="bi bi-hourglass-split"></i>
                                    </div>
                                    <div class="kpi-content">
                                        <span class="text-muted small fw-semibold d-block">Waiting in Queue</span>
                                        <h2 class="fw-bold text-warning mb-0 mt-1"><?= number_format($waiting_count); ?></h2>
                                        <span class="text-muted" style="font-size: 0.75rem;">
                                            <?= number_format($priority_waiting); ?> priority waiting
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Currently Serving -->
                        <div class="col-12 col-sm-6 col-xl-3">
                            <div class="card border-0 shadow-sm rounded-4 h-100 p-3">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="stat-icon-wrapper bg-primary-subtle text-primary">
                                        <i class="bi bi-megaphone-fill"></i>
                                    </div>
                                    <div class="kpi-content">
                                        <span class="text-muted small fw-semibold d-block">Currently Serving</span>
                                        <h2 class="fw-bold text-primary mb-0 mt-1"><?= number_format($serving_count); ?></h2>
                                        <span class="text-muted" style="font-size: 0.75rem;">
                                            Across <?= count($office_counters); ?> active counter(s)
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Completed Today -->
                        <div class="col-12 col-sm-6 col-xl-3">
                            <div class="card border-0 shadow-sm rounded-4 h-100 p-3">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="stat-icon-wrapper bg-success-subtle text-success">
                                        <i class="bi bi-check-circle-fill"></i>
                                    </div>
                                    <div class="kpi-content">
                                        <span class="text-muted small fw-semibold d-block">Completed Today</span>
                                        <h2 class="fw-bold text-success mb-0 mt-1"><?= number_format($completed_count); ?></h2>
                                        <span class="text-muted" style="font-size: 0.75rem;">
                                            <?= $avg_service_mins > 0 ? "Avg. {$avg_service_mins} min/client" : "Out of {$total_today} total tickets"; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Skipped / Cancelled -->
                        <div class="col-12 col-sm-6 col-xl-3">
                            <div class="card border-0 shadow-sm rounded-4 h-100 p-3">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="stat-icon-wrapper bg-secondary-subtle text-secondary">
                                        <i class="bi bi-skip-forward-circle-fill"></i>
                                    </div>
                                    <div class="kpi-content">
                                        <span class="text-muted small fw-semibold d-block">Skipped / Cancelled</span>
                                        <h2 class="fw-bold text-secondary mb-0 mt-1"><?= number_format($skipped_count); ?></h2>
                                        <span class="text-muted" style="font-size: 0.75rem;">
                                            Total tickets today: <?= number_format($total_today); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. WORKSTATION QUICK ACCESS -->
                    <div class="row g-3 mb-4">
                        <div class="col-12">
                            <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4">
                                <h6 class="fw-bold text-dark mb-3">
                                    <i class="bi bi-grid-fill text-primary me-2"></i>Staff Workstation Quick Access
                                </h6>
                                <div class="row g-3">
                                    <div class="col-12 col-sm-6 col-xl-3">
                                        <a href="<?= BASE_URL; ?>admin/queue-controller" target="_blank" class="text-decoration-none">
                                            <div class="staff-shortcut-card p-3 d-flex align-items-center gap-3">
                                                <div class="stat-icon-wrapper bg-primary text-white">
                                                    <i class="bi bi-person-workspace"></i>
                                                </div>
                                                <div>
                                                    <h6 class="fw-bold text-dark mb-0">Service Counter</h6>
                                                    <span class="text-muted small">Call, serve & complete tickets</span>
                                                </div>
                                            </div>
                                        </a>
                                    </div>

                                    <div class="col-12 col-sm-6 col-xl-3">
                                        <a href="<?= BASE_URL; ?>admin/transaction-records" class="text-decoration-none">
                                            <div class="staff-shortcut-card p-3 d-flex align-items-center gap-3">
                                                <div class="stat-icon-wrapper bg-success text-white">
                                                    <i class="bi bi-folder-check"></i>
                                                </div>
                                                <div>
                                                    <h6 class="fw-bold text-dark mb-0">Transaction Records</h6>
                                                    <span class="text-muted small">View office service logs</span>
                                                </div>
                                            </div>
                                        </a>
                                    </div>

                                    <div class="col-12 col-sm-6 col-xl-3">
                                        <a href="<?= BASE_URL; ?>admin/office-counters" class="text-decoration-none">
                                            <div class="staff-shortcut-card p-3 d-flex align-items-center gap-3">
                                                <div class="stat-icon-wrapper bg-info text-white">
                                                    <i class="bi bi-building-gear"></i>
                                                </div>
                                                <div>
                                                    <h6 class="fw-bold text-dark mb-0">Office Counters</h6>
                                                    <span class="text-muted small">Manage windows & staff</span>
                                                </div>
                                            </div>
                                        </a>
                                    </div>

                                    <div class="col-12 col-sm-6 col-xl-3">
                                        <a href="<?= BASE_URL; ?>admin/reports-analytics" class="text-decoration-none">
                                            <div class="staff-shortcut-card p-3 d-flex align-items-center gap-3">
                                                <div class="stat-icon-wrapper bg-dark text-white">
                                                    <i class="bi bi-bar-chart-line-fill"></i>
                                                </div>
                                                <div>
                                                    <h6 class="fw-bold text-dark mb-0">Reports & Analytics</h6>
                                                    <span class="text-muted small">Office performance insights</span>
                                                </div>
                                            </div>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 3. LIVE OFFICE QUEUE & COUNTER STATUS -->
                    <div class="row g-3">
                        <!-- Live Queue Table -->
                        <div class="col-12 col-lg-8">
                            <div class="card border-0 shadow-sm rounded-4 h-100">
                                <div class="card-header bg-white border-bottom py-3 px-4 d-flex flex-wrap align-items-center justify-content-between gap-2">
                                    <h6 class="section-heading mb-0">
                                        <i class="bi bi-list-ol me-2 text-primary"></i>Active & Waiting Queue Today
                                    </h6>
                                    <a href="<?= BASE_URL; ?>admin/transaction-records" class="btn btn-sm btn-outline-secondary">
                                        View All Records
                                    </a>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0" style="font-size: 0.875rem;">
                                            <thead class="table-light">
                                                <tr>
                                                    <th class="ps-4">Ticket / Client</th>
                                                    <th>Service Requested</th>
                                                    <th class="text-center">Counter</th>
                                                    <th class="text-center">Queued At</th>
                                                    <th class="pe-4 text-end">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (!empty($live_queue_list)): ?>
                                                    <?php foreach ($live_queue_list as $q): ?>
                                                        <?php
                                                            $vType      = strtoupper($q['visitor_type'] ?? 'GUEST');
                                                            $qStatus    = strtoupper($q['queue_status'] ?? 'WAITING');
                                                            $isServing  = in_array($qStatus, ['CALLED', 'SERVING'], true);
                                                            $badgeClass = $isServing ? 'bg-primary' : 'bg-warning text-dark';
                                                            $prioLabel  = !empty($q['priority_type']) ? strtoupper($q['priority_type']) : 'PRIORITY';
                                                        ?>
                                                        <tr>
                                                            <td class="ps-4">
                                                                <div class="fw-bold text-dark">
                                                                    <?= htmlspecialchars($q['ticket_number'] ?? '---'); ?>
                                                                    <?php if (!empty($q['is_priority'])): ?>
                                                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle ms-1" style="font-size: 0.68rem;">
                                                                            <?= htmlspecialchars($prioLabel); ?>
                                                                        </span>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div class="text-muted small">
                                                                    <?= htmlspecialchars(trim(($q['first_name'] ?? '') . ' ' . ($q['last_name'] ?? ''))); ?>
                                                                    <span class="text-secondary">&bull; <?= htmlspecialchars($vType); ?></span>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div class="fw-semibold text-dark"><?= htmlspecialchars($q['transaction_name'] ?? 'General Service'); ?></div>
                                                                <?php if (!empty($q['sub_concerns'])): ?>
                                                                    <div class="text-muted small"><?= htmlspecialchars($q['sub_concerns']); ?></div>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="text-center">
                                                                <?php if (!empty($q['counter_name'])): ?>
                                                                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($q['counter_name']); ?></span>
                                                                <?php else: ?>
                                                                    <span class="text-muted small">—</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="text-center text-muted small">
                                                                <?= !empty($q['created_at']) ? date('h:i A', strtotime($q['created_at'])) : '—'; ?>
                                                            </td>
                                                            <td class="pe-4 text-end">
                                                                <span class="badge <?= $badgeClass; ?> fw-semibold"><?= htmlspecialchars($qStatus); ?></span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="5" class="text-center py-5 text-muted">
                                                            <i class="bi bi-check2-all fs-2 d-block mb-2 opacity-50"></i>
                                                            <div class="fw-semibold">Queue is currently clear</div>
                                                            <div class="small">No waiting or active tickets in your office right now.</div>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Office Windows & Top Services -->
                        <div class="col-12 col-lg-4 d-flex flex-column gap-3">
                            <!-- Office Service Counters -->
                            <div class="card border-0 shadow-sm rounded-4">
                                <div class="card-header bg-white border-bottom py-3 px-4 d-flex align-items-center justify-content-between">
                                    <h6 class="section-heading mb-0">
                                        <i class="bi bi-window-stack me-2 text-primary"></i>Office Windows
                                    </h6>
                                    <a href="<?= BASE_URL; ?>admin/office-counters" class="small text-decoration-none">Manage</a>
                                </div>
                                <div class="card-body p-3">
                                    <?php if (!empty($office_counters)): ?>
                                        <div class="d-flex flex-column gap-2">
                                            <?php foreach ($office_counters as $cnt): ?>
                                                <div class="office-counter-item p-3 rounded-3 border <?= (int)$cnt['is_my_counter'] === 1 ? 'bg-primary-subtle border-primary-subtle' : 'bg-light'; ?>">
                                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                                        <span class="fw-bold text-dark"><?= htmlspecialchars($cnt['counter_name']); ?></span>
                                                        <?php if ((int)$cnt['is_my_counter'] === 1): ?>
                                                            <span class="badge bg-primary">Your Window</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="text-muted small">
                                                        <i class="bi bi-person me-1"></i>
                                                        <?= htmlspecialchars($cnt['staff_names'] ?: 'Unassigned'); ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center py-3 text-muted small">No active service counters configured for this office.</div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Top Requested Services Today -->
                            <div class="card border-0 shadow-sm rounded-4 flex-grow-1">
                                <div class="card-header bg-white border-bottom py-3 px-4 d-flex align-items-center justify-content-between">
                                    <h6 class="section-heading mb-0">
                                        <i class="bi bi-card-checklist me-2 text-primary"></i>Top Services Today
                                    </h6>
                                    <a href="<?= BASE_URL; ?>admin/transaction-types" class="small text-decoration-none">Services</a>
                                </div>
                                <div class="card-body p-3">
                                    <?php if (!empty($top_services)): ?>
                                        <div class="d-flex flex-column gap-2">
                                            <?php foreach ($top_services as $srv): ?>
                                                <div class="d-flex align-items-center justify-content-between p-2 px-3 bg-light rounded-3 border">
                                                    <span class="fw-semibold text-dark small text-truncate me-2"><?= htmlspecialchars($srv['service_name']); ?></span>
                                                    <span class="badge bg-primary rounded-pill"><?= number_format($srv['request_count']); ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center py-3 text-muted small">No service requests recorded today.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <?php include_once ADMIN_FOOTER_PATH; ?>
        </div>
    </div>

    <?php include_once ADMIN_SCRIPT_PATH; ?>
</body>

</html>