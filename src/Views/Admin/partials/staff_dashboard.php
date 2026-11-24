<?php
// src/Views/Admin/partials/staff_dashboard.php
global $db_connect, $session_class, $g_user_role, $g_user_id;
$db_connect  = $db_connect ?? null;
$g_user_role = $g_user_role ?? '';

# Page Header Initialization
require_once ADMIN_PAGE_HEADER_PATH;

$title_page   = "Office Staff Dashboard";
$active_links = [
    ['label' => $title_page, 'url' => '']
];
$page_header = render_page_header((string) $title_page, $active_links) ?? '';

# -------------------------------------------------------------------------
# 1. RESOLVE LOGGED-IN STAFF ASSIGNED OFFICE
# -------------------------------------------------------------------------
$current_user_id = (int) ($g_user_id ?? 0);
if ($current_user_id <= 0 && isset($session_class) && method_exists($session_class, 'getValue')) {
    $current_user_id = (int) (
        $session_class->getValue('user_id') ??
        $session_class->getValue('id') ??
        $session_class->getValue('sys_user_id') ??
        0
    );
}
if ($current_user_id <= 0) {
    $current_user_id = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? $_SESSION['sys_user_id'] ?? 0);
}

$assigned_office_id = 0;

if ($db_connect && $current_user_id > 0) {
    // Primary lookup from staff_assignments
    $stmtAssign = $db_connect->prepare("
        SELECT o.office_id
        FROM staff_assignments sa
        INNER JOIN office o ON o.office_id = sa.office_id
        WHERE sa.user_id = ? AND sa.flag_status = 0 AND o.flag_status = 0
        ORDER BY sa.id DESC
        LIMIT 1
    ");
    if ($stmtAssign) {
        $stmtAssign->bind_param("i", $current_user_id);
        $stmtAssign->execute();
        if ($row = $stmtAssign->get_result()->fetch_assoc()) {
            $assigned_office_id = (int) $row['office_id'];
        }
        $stmtAssign->close();
    }

    // Fallback to employee.office_id
    if ($assigned_office_id === 0) {
        $stmtEmp = $db_connect->prepare("
            SELECT o.office_id
            FROM employee e
            INNER JOIN office o ON o.office_id = e.office_id
            WHERE e.user_id = ? AND o.flag_status = 0
            LIMIT 1
        ");
        if ($stmtEmp) {
            $stmtEmp->bind_param("i", $current_user_id);
            $stmtEmp->execute();
            if ($row = $stmtEmp->get_result()->fetch_assoc()) {
                $assigned_office_id = (int) $row['office_id'];
            }
            $stmtEmp->close();
        }
    }
}

# -------------------------------------------------------------------------
# 2. FETCH INITIAL OFFICE KPI COUNTS
# -------------------------------------------------------------------------
$total_today     = 0;
$completed_count = 0;
$skipped_count   = 0;
$priority_today  = 0;

if ($db_connect && $assigned_office_id > 0) {
    $stmtMetrics = $db_connect->prepare("
        SELECT 
            COUNT(*) AS total_tickets,
            SUM(CASE WHEN UPPER(COALESCE(q.status, 'WAITING')) = 'COMPLETED' THEN 1 ELSE 0 END) AS completed_tickets,
            SUM(CASE WHEN UPPER(COALESCE(q.status, 'WAITING')) IN ('SKIPPED', 'CANCELLED') THEN 1 ELSE 0 END) AS skipped_tickets,
            SUM(CASE WHEN q.is_priority = 1 THEN 1 ELSE 0 END) AS priority_tickets
        FROM queue_tickets q
        WHERE q.office_id = ?
          AND q.queue_date = CURDATE()
    ");
    if ($stmtMetrics) {
        $stmtMetrics->bind_param("i", $assigned_office_id);
        $stmtMetrics->execute();
        if ($m = $stmtMetrics->get_result()->fetch_assoc()) {
            $total_today     = (int) ($m['total_tickets'] ?? 0);
            $completed_count = (int) ($m['completed_tickets'] ?? 0);
            $skipped_count   = (int) ($m['skipped_tickets'] ?? 0);
            $priority_today  = (int) ($m['priority_tickets'] ?? 0);
        }
        $stmtMetrics->close();
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
        .staff-dashboard .card {
            border: 1px solid var(--sd-line) !important;
            border-radius: 1rem !important;
            box-shadow: 0 4px 16px rgba(27,46,77,.045) !important;
            transition: box-shadow .18s ease, transform .18s ease;
        }
        .staff-dashboard .card-header {
            background: #fff;
            border-color: var(--sd-line) !important;
            border-radius: 1rem 1rem 0 0 !important;
        }

        /* Daily KPI Metric Cards */
        .staff-dashboard .kpi-card {
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            border-radius: 0.95rem;
            padding: 1.15rem;
            box-shadow: 0 4px 14px rgba(27, 46, 77, 0.035);
            transition: all 0.2s ease;
            height: 100%;
        }
        .staff-dashboard .kpi-card:hover {
            border-color: #cbd5e1;
            box-shadow: 0 6px 16px rgba(27, 46, 77, 0.06);
        }
        .staff-dashboard .kpi-icon {
            width: 48px;
            height: 48px;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
        }
        .staff-dashboard .kpi-icon.primary { background: #eff6ff; color: #2563eb; }
        .staff-dashboard .kpi-icon.success { background: #f0fdf4; color: #16a34a; }
        .staff-dashboard .kpi-icon.warning { background: #fefce8; color: #ca8a04; }
        .staff-dashboard .kpi-icon.danger  { background: #fef2f2; color: #dc2626; }

        /* Quick-Navigation Wrapper Background Design */
        .staff-dashboard .quick-nav-wrapper {
            position: relative;
            overflow: hidden;
            background: radial-gradient(circle at 95% 15%, rgba(36, 87, 197, 0.12), transparent 40%),
                        linear-gradient(120deg, #ffffff 0%, #f4f8ff 65%, #eaf1ff 100%);
            border: 1px solid #dbe6f8;
            border-radius: 1.15rem;
            padding: 1.35rem;
            box-shadow: 0 4px 16px rgba(27, 46, 77, 0.045);
        }
        .staff-dashboard .quick-nav-wrapper::after {
            content: "";
            position: absolute;
            width: 140px;
            height: 140px;
            right: -45px;
            bottom: -75px;
            border: 18px solid rgba(36, 87, 197, 0.06);
            border-radius: 50%;
            pointer-events: none;
        }

        /* Spacious Evenly Distributed Shortcut Cards (4x2 Grid) */
        .staff-dashboard .square-shortcut-card {
            width: 100%;
            height: 100%;
            min-height: 92px;
            background: #ffffff;
            border: 1px solid var(--sd-line);
            border-radius: 0.95rem;
            box-shadow: 0 3px 12px rgba(27, 46, 77, 0.04);
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: flex-start;
            text-align: left;
            padding: 1.15rem 1.25rem;
            gap: 1rem;
            transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
        }
        .staff-dashboard .square-shortcut-card:hover {
            border-color: #b8cafa;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(36, 87, 197, 0.1);
        }
        .staff-dashboard .square-shortcut-icon {
            width: 50px;
            height: 50px;
            border-radius: 0.9rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        .staff-dashboard .square-shortcut-title {
            color: var(--sd-ink);
            font-size: 0.94rem;
            font-weight: 700;
            letter-spacing: -0.01em;
            line-height: 1.25;
            margin: 0;
        }
        .staff-dashboard .square-shortcut-sub {
            color: var(--sd-muted);
            font-size: 0.78rem;
            line-height: 1.3;
            margin: 0;
        }

        /* Graph Period Filter Pills */
        .staff-dashboard .graph-period-group .btn {
            font-size: 0.78rem;
            font-weight: 600;
            padding: 0.3rem 0.75rem;
            border-color: #cbd5e1;
            color: #475569;
            background-color: #ffffff;
        }
        .staff-dashboard .graph-period-group .btn:hover {
            background-color: #f1f5f9;
            color: var(--sd-ink);
        }
        .staff-dashboard .graph-period-group .btn.active {
            background-color: var(--sd-blue);
            border-color: var(--sd-blue);
            color: #ffffff;
        }

        /* Chart Containers */
        .staff-dashboard .chart-container {
            position: relative;
            min-height: 300px;
            width: 100%;
        }
        .staff-dashboard .chart-empty-state {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 0.75rem;
            color: #64748b;
            padding: 1.5rem;
            z-index: 2;
        }
        .staff-dashboard .chart-empty-state i {
            font-size: 2rem;
            color: #94a3b8;
            margin-bottom: 0.5rem;
        }
        .staff-dashboard .chart-empty-state .empty-title {
            font-weight: 600;
            font-size: 0.9rem;
            color: #475569;
        }
        .staff-dashboard .chart-empty-state .empty-sub {
            font-size: 0.78rem;
            color: #94a3b8;
            margin-top: 0.15rem;
        }

        .staff-dashboard .section-heading {
            color: var(--sd-ink);
            font-size: .96rem;
            font-weight: 750;
            letter-spacing: -.015em;
        }
        .staff-dashboard .text-muted {
            color: var(--sd-muted) !important;
        }
        @media (max-width: 767.98px) {
            .staff-dashboard .square-shortcut-card {
                flex-direction: column;
                text-align: center;
                justify-content: center;
                padding: 1rem 0.75rem;
                gap: 0.65rem;
            }
            .staff-dashboard .square-shortcut-icon {
                width: 44px;
                height: 44px;
                font-size: 1.35rem;
            }
            .staff-dashboard .square-shortcut-title {
                font-size: 0.85rem;
            }
            .staff-dashboard .card-header {
                padding-left: 1rem !important;
                padding-right: 1rem !important;
            }
        }
        @media (prefers-reduced-motion: reduce) {
            .staff-dashboard .card,
            .staff-dashboard .kpi-card,
            .staff-dashboard .square-shortcut-card {
                transition: none;
            }
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include_once ADMIN_SIDEBAR_PATH; ?>

        <div class="main-panel">
            <?php include_once ADMIN_HEADER_PATH; ?>

            <div class="container">
                <div class="page-inner px-2 px-sm-3 staff-dashboard pt-3">

                    <?php if ($assigned_office_id === 0): ?>
                        <div class="alert alert-warning border-0 shadow-sm rounded-4 d-flex align-items-center gap-3 mb-4" role="alert">
                            <i class="bi bi-exclamation-triangle-fill fs-4 text-warning"></i>
                            <div>
                                <div class="fw-bold">No Office Assignment Found</div>
                                <div class="small">Your account is not currently linked to an active campus office. Please contact the System Administrator to assign your office.</div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- DAILY OFFICE KPI CARDS -->
                    <div class="row g-3 mb-4">
                        <?php
                        $kpi_cards = [
                            ['kpiTotalQueue',    'Total Queue',      'primary', 'bi-ticket-perforated-fill', number_format($total_today)],
                            ['kpiCompleted',     'Completed',        'success', 'bi-check-circle-fill',      number_format($completed_count)],
                            ['kpiSkipped',       'Skipped / Missed', 'danger',  'bi-x-circle-fill',          number_format($skipped_count)],
                            ['kpiPriorityQueue', 'Priority Queue',   'warning', 'bi-star-fill',              number_format($priority_today)],
                        ];
                        foreach ($kpi_cards as [$id, $label, $tone, $icon, $default]): ?>
                            <div class="col-6 col-xl-3">
                                <div class="kpi-card d-flex align-items-center">
                                    <div class="kpi-icon <?= $tone; ?> flex-shrink-0 me-3">
                                        <i class="bi <?= $icon; ?>"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <span class="fw-semibold text-muted small d-block mb-1"><?= $label; ?></span>
                                        <h4 class="fw-bold text-dark mb-0 font-monospace" id="<?= $id; ?>"><?= $default; ?></h4>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- 8 QUICK-NAVIGATION CARDS (Even 4x2 spacious grid) -->
                    <div class="quick-nav-wrapper mb-4">
                        <div class="row row-cols-2 row-cols-md-4 g-3 position-relative" style="z-index: 1;">
                            <div class="col">
                                <a href="<?= BASE_URL; ?>admin/queue-controller" target="_blank" class="text-decoration-none d-block h-100">
                                    <div class="square-shortcut-card">
                                        <div class="square-shortcut-icon bg-primary-subtle text-primary">
                                            <i class="bi bi-person-workspace"></i>
                                        </div>
                                        <div class="min-w-0">
                                            <h5 class="square-shortcut-title">Service Counter</h5>
                                            <p class="square-shortcut-sub mt-1">Call &amp; serve tickets</p>
                                        </div>
                                    </div>
                                </a>
                            </div>

                            <div class="col">
                                <a href="<?= BASE_URL; ?>admin/live-queue" target="_blank" class="text-decoration-none d-block h-100">
                                    <div class="square-shortcut-card">
                                        <div class="square-shortcut-icon bg-info-subtle text-info">
                                            <i class="bi bi-tv-fill"></i>
                                        </div>
                                        <div class="min-w-0">
                                            <h5 class="square-shortcut-title">Live Queue Monitor</h5>
                                            <p class="square-shortcut-sub mt-1">Display queue screen</p>
                                        </div>
                                    </div>
                                </a>
                            </div>

                            <div class="col">
                                <a href="<?= BASE_URL; ?>admin/transaction-records" class="text-decoration-none d-block h-100">
                                    <div class="square-shortcut-card">
                                        <div class="square-shortcut-icon bg-success-subtle text-success">
                                            <i class="bi bi-folder-check"></i>
                                        </div>
                                        <div class="min-w-0">
                                            <h5 class="square-shortcut-title">Transaction Records</h5>
                                            <p class="square-shortcut-sub mt-1">Office service logs</p>
                                        </div>
                                    </div>
                                </a>
                            </div>

                            <div class="col">
                                <a href="<?= BASE_URL; ?>admin/office-counters" class="text-decoration-none d-block h-100">
                                    <div class="square-shortcut-card">
                                        <div class="square-shortcut-icon bg-primary-subtle text-primary">
                                            <i class="bi bi-building-gear"></i>
                                        </div>
                                        <div class="min-w-0">
                                            <h5 class="square-shortcut-title">Office Counters</h5>
                                            <p class="square-shortcut-sub mt-1">Windows &amp; assignments</p>
                                        </div>
                                    </div>
                                </a>
                            </div>

                            <div class="col">
                                <a href="<?= BASE_URL; ?>admin/transaction-types" class="text-decoration-none d-block h-100">
                                    <div class="square-shortcut-card">
                                        <div class="square-shortcut-icon bg-success-subtle text-success">
                                            <i class="bi bi-card-checklist"></i>
                                        </div>
                                        <div class="min-w-0">
                                            <h5 class="square-shortcut-title">Services &amp; Sub-Concerns</h5>
                                            <p class="square-shortcut-sub mt-1">Manage office services</p>
                                        </div>
                                    </div>
                                </a>
                            </div>

                            <div class="col">
                                <a href="<?= BASE_URL; ?>admin/reports-analytics" class="text-decoration-none d-block h-100">
                                    <div class="square-shortcut-card">
                                        <div class="square-shortcut-icon bg-warning-subtle text-warning">
                                            <i class="bi bi-bar-chart-line-fill"></i>
                                        </div>
                                        <div class="min-w-0">
                                            <h5 class="square-shortcut-title">Reports &amp; Analytics</h5>
                                            <p class="square-shortcut-sub mt-1">Office queue insights</p>
                                        </div>
                                    </div>
                                </a>
                            </div>

                            <div class="col">
                                <a href="<?= BASE_URL; ?>admin/activity-log" class="text-decoration-none d-block h-100">
                                    <div class="square-shortcut-card">
                                        <div class="square-shortcut-icon bg-danger-subtle text-danger">
                                            <i class="bi bi-clock-history"></i>
                                        </div>
                                        <div class="min-w-0">
                                            <h5 class="square-shortcut-title">Activity Log</h5>
                                            <p class="square-shortcut-sub mt-1">System audit trail</p>
                                        </div>
                                    </div>
                                </a>
                            </div>

                            <div class="col">
                                <a href="<?= BASE_URL; ?>admin/user-log" class="text-decoration-none d-block h-100">
                                    <div class="square-shortcut-card">
                                        <div class="square-shortcut-icon bg-secondary-subtle text-secondary">
                                            <i class="bi bi-person-lines-fill"></i>
                                        </div>
                                        <div class="min-w-0">
                                            <h5 class="square-shortcut-title">User Log</h5>
                                            <p class="square-shortcut-sub mt-1">Session activity</p>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- GRAPH DATE FILTER BAR -->
                    <div class="card border-0 shadow-sm rounded-4 mb-3">
                        <div class="card-body py-2 px-3 px-sm-4 d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <span class="fw-bold text-dark small">
                                    <i class="bi bi-calendar-range text-primary me-1"></i>Analytics Period:
                                </span>
                                <span class="text-muted small" id="activeGraphDateLabel">Today</span>
                            </div>
                            <div class="btn-group graph-period-group" role="group" aria-label="Graph Date Filter">
                                <button type="button" class="btn active" data-period="today">Today</button>
                                <button type="button" class="btn" data-period="weekly">Weekly</button>
                                <button type="button" class="btn" data-period="monthly">Monthly</button>
                                <button type="button" class="btn" data-period="yearly">Yearly</button>
                            </div>
                        </div>
                    </div>

                    <!-- REPORTS & ANALYTICS SUMMARY (2 COLUMNS: QUEUE VOLUME & VISITOR DISTRIBUTION) -->
                    <div class="row g-4">
                        <!-- Left Column: Queue Volume (Office-Scoped) -->
                        <div class="col-12 col-lg-8">
                            <div class="card border-0 shadow-sm rounded-4 h-100">
                                <div class="card-header bg-white border-bottom py-3 px-4 d-flex flex-wrap align-items-center justify-content-between gap-2">
                                    <h6 class="section-heading mb-0">
                                        <i class="bi bi-graph-up-arrow me-2 text-primary"></i>Queue Volume
                                    </h6>
                                    <a href="<?= BASE_URL; ?>admin/reports-analytics" class="btn btn-sm btn-outline-secondary">
                                        Full Analytics
                                    </a>
                                </div>
                                <div class="card-body p-3 p-sm-4">
                                    <div class="chart-container" id="volumeChartContainer">
                                        <canvas id="volumeTrendChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Visitor Distribution (Office-Scoped) -->
                        <div class="col-12 col-lg-4">
                            <div class="card border-0 shadow-sm rounded-4 h-100">
                                <div class="card-header bg-white border-bottom py-3 px-4 d-flex align-items-center justify-content-between">
                                    <h6 class="section-heading mb-0">
                                        <i class="bi bi-pie-chart-fill me-2 text-primary"></i>Visitor Distribution
                                    </h6>
                                    <span class="badge bg-light text-muted border small" id="visitorPeriodBadge">Today</span>
                                </div>
                                <div class="card-body p-3 p-sm-4">
                                    <div class="chart-container" id="visitorChartContainer">
                                        <canvas id="visitorTypeChart"></canvas>
                                    </div>
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

    <script>
        (function() {
            const HANDLER_URL        = '<?= BASE_URL; ?>admin/ajax/reports-analytics';
            const ASSIGNED_OFFICE_ID = '<?= (int) $assigned_office_id; ?>';
            const POLL_INTERVAL_MS   = 10000; // Auto-refresh via AJAX every 10 seconds

            const PERIOD_LABELS = {
                today:   'Today',
                weekly:  'This Week (Last 7 Days)',
                monthly: 'This Month',
                yearly:  'This Year'
            };

            const PERIOD_BADGE_LABELS = {
                today:   'Today',
                weekly:  'Weekly',
                monthly: 'Monthly',
                yearly:  'Yearly'
            };

            const charts = {};
            let selectedPeriod = 'today';
            let pollTimer = null;
            let isFetching = false;
            let lastPayloadHash = '';

            const escapeHtml = (s) => String(s ?? '')
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#039;');

            const formatYMD = (dateObj) => {
                const y = dateObj.getFullYear();
                const m = String(dateObj.getMonth() + 1).padStart(2, '0');
                const d = String(dateObj.getDate()).padStart(2, '0');
                return `${y}-${m}-${d}`;
            };

            const getDateRangeForPeriod = (period) => {
                const now = new Date();
                const dateTo = formatYMD(now);
                let startObj = new Date(now.getFullYear(), now.getMonth(), now.getDate());

                if (period === 'weekly') {
                    startObj.setDate(startObj.getDate() - 6);
                } else if (period === 'monthly') {
                    startObj = new Date(now.getFullYear(), now.getMonth(), 1);
                } else if (period === 'yearly') {
                    startObj = new Date(now.getFullYear(), 0, 1);
                }

                return {
                    dateFrom: formatYMD(startObj),
                    dateTo: dateTo
                };
            };

            function renderKPIs(k) {
                k = k || {};
                const totalEl     = document.getElementById('kpiTotalQueue');
                const completedEl = document.getElementById('kpiCompleted');
                const skippedEl   = document.getElementById('kpiSkipped');
                const priorityEl  = document.getElementById('kpiPriorityQueue');

                if (totalEl)     totalEl.textContent     = (parseInt(k.total_tickets, 10) || 0).toLocaleString();
                if (completedEl) completedEl.textContent = (parseInt(k.completed_tickets, 10) || 0).toLocaleString();
                if (skippedEl)   skippedEl.textContent   = (parseInt(k.skipped_tickets, 10) || 0).toLocaleString();
                if (priorityEl)  priorityEl.textContent  = (parseInt(k.priority_tickets, 10) || 0).toLocaleString();
            }

            function drawChart(key, canvasId, config) {
                if (charts[key]) charts[key].destroy();
                const canvas = document.getElementById(canvasId);
                if (!canvas) return;
                charts[key] = new Chart(canvas.getContext('2d'), config);
            }

            function toggleChartEmptyState(key, outerContainerId, toggleTargetEl, isEmpty, title = 'No data available', subtitle = 'No records found for the selected period.') {
                const outerContainer = document.getElementById(outerContainerId);
                if (!outerContainer || !toggleTargetEl) return;
                let emptyEl = outerContainer.querySelector('.chart-empty-state');

                if (isEmpty) {
                    if (charts[key]) {
                        charts[key].destroy();
                        delete charts[key];
                    }
                    toggleTargetEl.style.display = 'none';
                    if (!emptyEl) {
                        emptyEl = document.createElement('div');
                        emptyEl.className = 'chart-empty-state';
                        outerContainer.appendChild(emptyEl);
                    }
                    emptyEl.innerHTML = `
                        <i class="bi bi-bar-chart-line"></i>
                        <div class="empty-title">${escapeHtml(title)}</div>
                        <div class="empty-sub">${escapeHtml(subtitle)}</div>
                    `;
                } else {
                    if (emptyEl) emptyEl.remove();
                    toggleTargetEl.style.display = 'block';
                }
            }

            function renderVolumeTrend(data) {
                const items      = data || [];
                const hasData    = items.some(d => (parseInt(d.total, 10) || 0) > 0);
                const canvas     = document.getElementById('volumeTrendChart');
                const periodText = (PERIOD_BADGE_LABELS[selectedPeriod] || 'Today').toLowerCase();

                toggleChartEmptyState(
                    'volume',
                    'volumeChartContainer',
                    canvas,
                    !hasData,
                    'No queue volume recorded',
                    `No tickets were issued for your office for this ${periodText} period.`
                );
                if (!hasData) return;

                drawChart('volume', 'volumeTrendChart', {
                    type: 'line',
                    data: {
                        labels: items.map(d => d.label),
                        datasets: [{
                            label: 'Tickets Issued',
                            data: items.map(d => d.total),
                            borderColor: '#2563eb',
                            backgroundColor: 'rgba(37, 99, 235, 0.08)',
                            fill: true,
                            lineTension: 0.35,
                            borderWidth: 2,
                            pointBackgroundColor: '#2563eb',
                            pointRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: { duration: 250 },
                        legend: { display: false },
                        scales: {
                            yAxes: [{ ticks: { beginAtZero: true, precision: 0 } }]
                        }
                    }
                });
            }

            function renderVisitorType(data) {
                const counts = { Student: 0, Employee: 0, Guest: 0 };
                (data || []).forEach(d => {
                    const key = String(d.type || '').trim().toLowerCase();
                    if (key === 'student') counts.Student = parseInt(d.total || 0, 10);
                    else if (key === 'employee') counts.Employee = parseInt(d.total || 0, 10);
                    else if (key === 'guest') counts.Guest = parseInt(d.total || 0, 10);
                });

                const labels     = ['Student', 'Employee', 'Guest'];
                const values     = [counts.Student, counts.Employee, counts.Guest];
                const hasData    = values.some(v => v > 0);
                const canvas     = document.getElementById('visitorTypeChart');
                const periodText = (PERIOD_BADGE_LABELS[selectedPeriod] || 'Today').toLowerCase();

                toggleChartEmptyState(
                    'visitor',
                    'visitorChartContainer',
                    canvas,
                    !hasData,
                    'No visitor records found',
                    `No student, employee, or guest activity recorded for this ${periodText} period.`
                );
                if (!hasData) return;

                drawChart('visitor', 'visitorTypeChart', {
                    type: 'doughnut',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: values,
                            backgroundColor: ['#2563eb', '#7c3aed', '#16a34a'],
                            borderWidth: 2,
                            borderColor: '#ffffff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: { duration: 250 },
                        legend: { position: 'bottom' }
                    }
                });
            }

            function loadDashboardAnalytics(forceRender = false) {
                if (ASSIGNED_OFFICE_ID === '0') {
                    renderVolumeTrend([]);
                    renderVisitorType([]);
                    return;
                }
                if (isFetching) return;
                isFetching = true;

                const { dateFrom, dateTo } = getDateRangeForPeriod(selectedPeriod);
                const fd = new FormData();
                fd.append('action', 'fetch_analytics');
                fd.append('office_id', ASSIGNED_OFFICE_ID);
                fd.append('counter_id', '0');
                fd.append('visitor_type', '');
                fd.append('date_from', dateFrom);
                fd.append('date_to', dateTo);

                fetch(HANDLER_URL, {
                    method: 'POST',
                    body: fd,
                    cache: 'no-store'
                })
                    .then(res => res.json())
                    .then(res => {
                        if (!(res.success && res.data)) {
                            if (forceRender) {
                                renderKPIs({});
                                renderVolumeTrend([]);
                                renderVisitorType([]);
                            }
                            return;
                        }

                        const payloadHash = JSON.stringify({
                            period: selectedPeriod,
                            kpi: res.data.kpi || {},
                            volume: res.data.volume_trend || [],
                            visitor: res.data.visitor_distribution || []
                        });

                        if (forceRender || payloadHash !== lastPayloadHash) {
                            lastPayloadHash = payloadHash;
                            renderKPIs(res.data.kpi);
                            renderVolumeTrend(res.data.volume_trend || []);
                            renderVisitorType(res.data.visitor_distribution || []);
                        }
                    })
                    .catch(() => {
                        if (forceRender) {
                            renderVolumeTrend([]);
                            renderVisitorType([]);
                        }
                    })
                    .finally(() => {
                        isFetching = false;
                    });
            }

            function bindPeriodFilterButtons() {
                const buttons     = document.querySelectorAll('.graph-period-group .btn[data-period]');
                const dateLabelEl = document.getElementById('activeGraphDateLabel');
                const badgeEl     = document.getElementById('visitorPeriodBadge');

                buttons.forEach(btn => {
                    btn.addEventListener('click', () => {
                        const period = btn.getAttribute('data-period') || 'today';
                        if (period === selectedPeriod) return;

                        selectedPeriod = period;
                        lastPayloadHash = '';

                        buttons.forEach(b => b.classList.remove('active'));
                        btn.classList.add('active');

                        if (dateLabelEl) dateLabelEl.textContent = PERIOD_LABELS[period] || 'Today';
                        if (badgeEl) badgeEl.textContent = PERIOD_BADGE_LABELS[period] || 'Today';

                        loadDashboardAnalytics(true);
                    });
                });
            }

            function startPolling() {
                stopPolling();
                pollTimer = setInterval(() => {
                    if (!document.hidden) {
                        loadDashboardAnalytics(false);
                    }
                }, POLL_INTERVAL_MS);
            }

            function stopPolling() {
                if (pollTimer) {
                    clearInterval(pollTimer);
                    pollTimer = null;
                }
            }

            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    stopPolling();
                } else {
                    loadDashboardAnalytics(false);
                    startPolling();
                }
            });

            const bootDashboard = () => {
                bindPeriodFilterButtons();
                loadDashboardAnalytics(true);
                startPolling();
            };

            if (document.readyState === 'complete') {
                bootDashboard();
            } else {
                window.addEventListener('load', bootDashboard);
            }
        })();
    </script>
</body>

</html>