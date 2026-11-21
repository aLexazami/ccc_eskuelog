<?php
// src/Views/Admin/reports_analytics.php

# Core Config Dependencies
global $session_class, $db_connect;
$db_connect = $db_connect ?? null;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

# Page Helpers
require_once ADMIN_PAGE_HEADER_PATH;

# Page Header Initialization
$title_page   = "Reports & Analytics";
$active_links = [
    ['label' => $title_page, 'url' => '']
];
$page_header = render_page_header((string) $title_page, $active_links) ?? '';

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

// Fetch assigned office or office list for filters
$assigned_office = null;
$offices         = [];
$counters        = [];
$lock_office     = false;

if ($db_connect && $user_id > 0) {
    // Priority 1: staff_assignments
    $stmtAssign = $db_connect->prepare("
        SELECT sa.office_id, o.office_code, o.office_name 
        FROM staff_assignments sa
        INNER JOIN office o ON sa.office_id = o.office_id
        WHERE sa.user_id = ? AND sa.flag_status = 0
        ORDER BY sa.id DESC LIMIT 1
    ");
    if ($stmtAssign) {
        $stmtAssign->bind_param("i", $user_id);
        $stmtAssign->execute();
        $assigned_office = $stmtAssign->get_result()->fetch_assoc();
        $stmtAssign->close();
    }

    // Priority 2: employee table
    if (!$assigned_office && $g_user_role_upper === 'ADMIN_STAFF') {
        $stmtEmp = $db_connect->prepare("
            SELECT e.office_id, o.office_code, o.office_name 
            FROM employee e
            INNER JOIN office o ON e.office_id = o.office_id
            WHERE e.user_id = ? LIMIT 1
        ");
        if ($stmtEmp) {
            $stmtEmp->bind_param("i", $user_id);
            $stmtEmp->execute();
            $assigned_office = $stmtEmp->get_result()->fetch_assoc();
            $stmtEmp->close();
        }
    }

    $lock_office = ($g_user_role_upper === 'ADMIN_STAFF' && !empty($assigned_office));

    if ($g_user_role_upper === 'ADMIN' || $g_user_role_upper === 'STATION_ADMIN' || !$assigned_office) {
        $stmt = $db_connect->query("SELECT office_id, office_code, office_name FROM office WHERE flag_status = 0 ORDER BY office_name ASC");
        if ($stmt) {
            while ($row = $stmt->fetch_assoc()) {
                $offices[] = $row;
            }
        }
    }

    // Preload windows when the office is fixed (Admin Staff)
    if ($lock_office) {
        $stmtC = $db_connect->prepare("SELECT id, counter_name FROM counters WHERE office_id = ? AND flag_status = 0 ORDER BY counter_name ASC");
        if ($stmtC) {
            $oid = (int)$assigned_office['office_id'];
            $stmtC->bind_param("i", $oid);
            $stmtC->execute();
            $counters = $stmtC->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtC->close();
        }
    }
}

$today_date = defined('DATE_NOW') ? DATE_NOW : date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en" class="h-100">

<head>
    <?php include_once ADMIN_META_DATA_PATH; ?>
    <?php include_once ADMIN_LINK_PATH; ?>

    <style>
        .filter-card {
            border: 1px solid #e2e8f0;
            border-radius: 0.85rem;
            background: #ffffff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
        }

        /* KPI Metric Cards */
        .kpi-card {
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            border-radius: 0.85rem;
            padding: 1.15rem;
            transition: all 0.2s ease;
            height: 100%;
        }

        .kpi-card:hover {
            border-color: #cbd5e1;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .kpi-icon {
            width: 48px;
            height: 48px;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
        }

        .kpi-icon.primary   { background: #eff6ff; color: #2563eb; }
        .kpi-icon.success   { background: #f0fdf4; color: #16a34a; }
        .kpi-icon.warning   { background: #fefce8; color: #ca8a04; }
        .kpi-icon.danger    { background: #fef2f2; color: #dc2626; }
        .kpi-icon.info      { background: #f0f9ff; color: #0284c7; }

        /* Chart Canvas Containers */
        .chart-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 0.85rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
            height: 100%;
        }

        .chart-container {
            position: relative;
            min-height: 280px;
            width: 100%;
        }

        .chart-container-tall {
            position: relative;
            min-height: 360px;
            width: 100%;
        }

        /* Clean Flat Tabulator Styling */
        #officeTable.tabulator,
        #counterTable.tabulator,
        #staffBreakdownTable.tabulator {
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            background-color: #ffffff;
            box-shadow: none !important;
        }

        #officeTable .tabulator-header,
        #officeTable .tabulator-header .tabulator-col,
        #counterTable .tabulator-header,
        #counterTable .tabulator-header .tabulator-col,
        #staffBreakdownTable .tabulator-header,
        #staffBreakdownTable .tabulator-header .tabulator-col {
            background: #ffffff !important;
            background-color: #ffffff !important;
            background-image: none !important;
            border-bottom: 1px solid #e2e8f0;
            box-shadow: none !important;
            font-weight: 600;
            color: #475569;
        }

        #officeTable .tabulator-header .tabulator-col:hover,
        #counterTable .tabulator-header .tabulator-col:hover,
        #staffBreakdownTable .tabulator-header .tabulator-col:hover {
            background: #ffffff !important;
            background-color: #ffffff !important;
            box-shadow: none !important;
        }

        #officeTable .tabulator-header-filter input,
        #officeTable .tabulator-header-filter select,
        #counterTable .tabulator-header-filter input,
        #counterTable .tabulator-header-filter select,
        #staffBreakdownTable .tabulator-header-filter input,
        #staffBreakdownTable .tabulator-header-filter select {
            height: 32px !important;
            min-height: 32px !important;
            padding: 0.25rem 0.5rem !important;
            font-size: 0.8125rem !important;
            box-shadow: none !important;
            box-sizing: border-box;
        }

        #officeTable .tabulator-row,
        #officeTable .tabulator-row.tabulator-row-even,
        #officeTable .tabulator-row.tabulator-row-odd,
        #officeTable .tabulator-row:hover,
        #counterTable .tabulator-row,
        #counterTable .tabulator-row.tabulator-row-even,
        #counterTable .tabulator-row.tabulator-row-odd,
        #counterTable .tabulator-row:hover,
        #staffBreakdownTable .tabulator-row,
        #staffBreakdownTable .tabulator-row.tabulator-row-even,
        #staffBreakdownTable .tabulator-row.tabulator-row-odd,
        #staffBreakdownTable .tabulator-row:hover {
            background-color: #ffffff !important;
            box-shadow: none !important;
        }

        #officeTable .tabulator-cell,
        #counterTable .tabulator-cell,
        #staffBreakdownTable .tabulator-cell {
            background-color: #ffffff !important;
            border-right: 1px solid #f1f5f9;
            box-shadow: none !important;
            white-space: normal;
        }

        #officeTable .tabulator-cell:last-child,
        #counterTable .tabulator-cell:last-child,
        #staffBreakdownTable .tabulator-cell:last-child {
            border-right: none;
        }

        #officeTable .tabulator-footer,
        #counterTable .tabulator-footer,
        #staffBreakdownTable .tabulator-footer {
            background-color: #ffffff !important;
            box-shadow: none !important;
            border-top: 1px solid #e2e8f0;
        }

        .tabulator .tabulator-tableholder {
            overflow-x: auto !important;
            overflow-y: auto !important;
        }

        /* Left-align label/name columns; center Office Code and metric columns */
        #officeTable .tabulator-cell[tabulator-field="office_label"],
        #counterTable .tabulator-cell[tabulator-field="counter_label"],
        #staffBreakdownTable .tabulator-cell[tabulator-field="staff_name"] {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: flex-start !important;
            text-align: left !important;
        }

        #officeTable .tabulator-cell:not([tabulator-field="office_label"]),
        #counterTable .tabulator-cell:not([tabulator-field="counter_label"]),
        #staffBreakdownTable .tabulator-cell:not([tabulator-field="staff_name"]) {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            text-align: center !important;
        }

        /* Empty Fallback Overlay for Chart.js Containers */
        .chart-empty-state {
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

        .chart-empty-state i {
            font-size: 2rem;
            color: #94a3b8;
            margin-bottom: 0.5rem;
        }

        .chart-empty-state .empty-title {
            font-weight: 600;
            font-size: 0.9rem;
            color: #475569;
        }

        .chart-empty-state .empty-sub {
            font-size: 0.78rem;
            color: #94a3b8;
            margin-top: 0.15rem;
        }

        .tabulator .tabulator-tableholder .tabulator-placeholder {
            padding: 2rem 1rem !important;
        }

        .tabulator .tabulator-tableholder .tabulator-placeholder span {
            color: #64748b !important;
            font-size: 0.9rem !important;
            font-weight: 500 !important;
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include_once ADMIN_SIDEBAR_PATH; ?>
        <div class="main-panel">
            <?php include_once ADMIN_HEADER_PATH; ?>

            <div class="container">
                <div class="page-inner px-2 px-sm-3">

                    <!-- Page Header -->
                    <div>
                        <?php echo is_array($page_header) ? '' : $page_header; ?>
                    </div>

                    <!-- FILTERS CARD -->
                    <div class="card filter-card mb-4">
                        <div class="card-body p-3 p-sm-4">
                            <form id="analyticsFilterForm" autocomplete="off">

                                <!-- ROW 1: SOLO OFFICE LOCATION -->
                                <div class="row g-3 mb-3">
                                    <div class="col-12">
                                        <label for="filterOfficeId" class="form-label fw-bold small text-muted">
                                            Office Location
                                        </label>
                                        <select id="filterOfficeId" class="form-select form-select-sm" autocomplete="off" <?= $lock_office ? 'disabled style="pointer-events:none; background-color:#f1f5f9;"' : ''; ?>>
                                            <?php if ($g_user_role_upper === 'ADMIN' || $g_user_role_upper === 'STATION_ADMIN'): ?>
                                                <option value="0" selected>-- All Offices --</option>
                                                <?php foreach ($offices as $off): ?>
                                                    <option value="<?= $off['office_id']; ?>"><?= htmlspecialchars($off['office_name']); ?> (<?= htmlspecialchars($off['office_code']); ?>)</option>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <?php if ($assigned_office): ?>
                                                    <option value="<?= $assigned_office['office_id']; ?>" selected><?= htmlspecialchars($assigned_office['office_name']); ?> (<?= htmlspecialchars($assigned_office['office_code']); ?>)</option>
                                                <?php else: ?>
                                                    <option value="0" selected>-- All Offices --</option>
                                                    <?php foreach ($offices as $off): ?>
                                                        <option value="<?= $off['office_id']; ?>"><?= htmlspecialchars($off['office_name']); ?></option>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                </div>

                                <!-- ROW 2: WINDOW, VISITOR TYPE, DATES & ACTION BUTTONS ALIGNED -->
                                <div class="row g-2 align-items-end">
                                    <div class="col-12 col-sm-6 col-xl-2">
                                        <label for="filterCounterId" class="form-label fw-bold small text-muted">Window / Counter</label>
                                        <select id="filterCounterId" class="form-select form-select-sm" autocomplete="off" <?= !$lock_office ? 'disabled' : ''; ?>>
                                            <option value="0" selected><?= $lock_office ? 'All Windows' : 'Select office first'; ?></option>
                                            <?php foreach ($counters as $c): ?>
                                                <option value="<?= (int)$c['id']; ?>"><?= htmlspecialchars($c['counter_name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-12 col-sm-6 col-xl-2">
                                        <label for="filterVisitorType" class="form-label fw-bold small text-muted">Visitor Type</label>
                                        <select id="filterVisitorType" class="form-select form-select-sm" autocomplete="off">
                                            <option value="" selected>All Types</option>
                                            <option value="STUDENT">Student</option>
                                            <option value="EMPLOYEE">Employee</option>
                                            <option value="GUEST">Guest</option>
                                        </select>
                                    </div>

                                    <div class="col-6 col-sm-6 col-xl-2">
                                        <label for="filterDateFrom" class="form-label fw-bold small text-muted">Date From</label>
                                        <input type="date" id="filterDateFrom" class="form-control form-control-sm" autocomplete="off" value="<?= htmlspecialchars($today_date); ?>">
                                    </div>

                                    <div class="col-6 col-sm-6 col-xl-2">
                                        <label for="filterDateTo" class="form-label fw-bold small text-muted">Date To</label>
                                        <input type="date" id="filterDateTo" class="form-control form-control-sm" autocomplete="off" value="<?= htmlspecialchars($today_date); ?>">
                                    </div>

                                    <div class="col-12 col-xl-4 d-flex flex-wrap flex-xl-nowrap gap-2 justify-content-xl-end">
                                        <button type="submit" class="btn btn-primary btn-sm fw-bold px-3 text-nowrap">
                                            <i class="bi bi-filter me-1"></i>Apply
                                        </button>
                                        <button type="button" id="btnResetFilters" class="btn btn-outline-secondary btn-sm text-nowrap" title="Reset Filters">
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                        </button>

                                        <div class="dropdown">
                                            <button class="btn btn-action btn-sm dropdown-toggle d-inline-flex align-items-center fw-semibold text-nowrap" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="bi bi-download me-1"></i>Export / Download
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                                <?php
                                                $export_groups = [
                                                    'office'  => 'Office Performance',
                                                    'staff'   => 'Staff Performance',
                                                    'counter' => 'Window Performance'
                                                ];
                                                foreach ($export_groups as $key => $label): ?>
                                                    <li class="px-3 py-1" id="exportGroup_<?= $key; ?>">
                                                        <div class="small fw-bold text-muted"><?= $label; ?></div>
                                                        <div class="d-flex gap-3 small">
                                                            <a href="javascript:void(0)" data-export="<?= $key; ?>:csv">CSV</a>
                                                            <a href="javascript:void(0)" data-export="<?= $key; ?>:xlsx">Excel</a>
                                                            <a href="javascript:void(0)" data-export="<?= $key; ?>:json">JSON</a>
                                                        </div>
                                                    </li>
                                                <?php endforeach; ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item" href="javascript:void(0)" id="export-pdf-report"><i class="bi bi-file-earmark-pdf-fill text-danger me-2"></i> PDF Report</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>

                            </form>
                        </div>
                    </div>

                    <!-- KPI CARDS -->
                    <div class="row g-3 mb-4">
                        <?php
                        $kpi_cards = [
                            ['kpiTotalTickets',     'Total Queue',      'primary', 'bi-ticket-perforated-fill', '0'],
                            ['kpiCompletedTickets', 'Completed',          'success', 'bi-check-circle-fill',      '0'],
                            ['kpiSkippedTickets',   'Skipped / Missed',   'danger',  'bi-x-circle-fill',          '0'],
                            ['kpiPriorityTickets',  'Priority Queue',   'warning', 'bi-star-fill',              '0'],
                            ['kpiAvgWaitTime',      'Avg. Waiting Time',  'info',    'bi-hourglass-split',        '0m'],
                            ['kpiAvgServiceTime',   'Avg. Processing Time',  'info',    'bi-clock-history',          '0m'],
                        ];
                        foreach ($kpi_cards as [$id, $label, $tone, $icon, $default]): ?>
                            <div class="col-6 col-lg-4 col-xl-2">
                                <div class="kpi-card d-flex align-items-center">
                                    <div class="kpi-icon <?= $tone; ?> flex-shrink-0 me-3"><i class="bi <?= $icon; ?>"></i></div>
                                    <div class="min-w-0">
                                        <span class="fw-semibold text-muted small d-block mb-1"><?= $label; ?></span>
                                        <h4 class="fw-bold text-dark mb-0 font-monospace" id="<?= $id; ?>"><?= $default; ?></h4>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- ROW 0 (ALL OFFICES ONLY): OFFICE PERFORMANCE COMPARISON -->
                    <div id="allOfficesComparisonSection">
                        <div class="row g-4 mb-4">
                            <div class="col-12">
                                <div class="card chart-card p-3 p-sm-4">
                                    <h6 class="fw-bold text-dark mb-3"><i class="bi bi-building me-2 text-primary"></i>Queue Volume by Office</h6>
                                    <div class="chart-container"><canvas id="officeVolumeChart"></canvas></div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-4 mb-4">
                            <div class="col-12">
                                <div class="card chart-card p-3 p-sm-4">
                                    <h6 class="fw-bold text-dark mb-3"><i class="bi bi-buildings me-2 text-primary"></i>Office Performance Breakdown</h6>
                                    <div id="officeTable" style="min-height: 380px;"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ROW 1: QUEUE VOLUME TREND (1 COLUMN) -->
                    <div class="row g-4 mb-4">
                        <div class="col-12">
                            <div class="card chart-card p-3 p-sm-4">
                                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-graph-up-arrow me-2 text-primary"></i>Queue Volume Trend</h6>
                                <div class="chart-container"><canvas id="volumeTrendChart"></canvas></div>
                            </div>
                        </div>
                    </div>

                    <!-- ROW 2: QUEUE STATUS + AVERAGE TIME PER STAGE + VISITOR DISTRIBUTION (3 COLUMNS) -->
                    <div class="row g-4 mb-4">
                        <div class="col-12 col-lg-4">
                            <div class="card chart-card p-3 p-sm-4">
                                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-activity me-2 text-primary"></i>Queue Status</h6>
                                <div class="chart-container"><canvas id="statusChart"></canvas></div>
                            </div>
                        </div>
                        <div class="col-12 col-lg-4">
                            <div class="card chart-card p-3 p-sm-4">
                                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-stopwatch-fill me-2 text-primary"></i>Average Time per Stage (min)</h6>
                                <div class="chart-container"><canvas id="stageTimeChart"></canvas></div>
                            </div>
                        </div>
                        <div class="col-12 col-lg-4">
                            <div class="card chart-card p-3 p-sm-4">
                                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-pie-chart-fill me-2 text-primary"></i>Visitor Distribution</h6>
                                <div class="chart-container"><canvas id="visitorTypeChart"></canvas></div>
                            </div>
                        </div>
                    </div>

                    <!-- ROW 3: TOP TRANSACTIONS (1 COLUMN, FULL READABLE TITLES) -->
                    <div class="row g-4 mb-4">
                        <div class="col-12">
                            <div class="card chart-card p-3 p-sm-4">
                                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-card-checklist me-2 text-primary"></i>Top Transactions</h6>
                                <div class="chart-container-tall"><canvas id="topConcernsChart"></canvas></div>
                            </div>
                        </div>
                    </div>

                    <!-- ROW 4: WINDOW PERFORMANCE (1 COLUMN) -->
                    <div class="row g-4 mb-4">
                        <div class="col-12">
                            <div class="card chart-card p-3 p-sm-4">
                                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-window-stack me-2 text-primary"></i>Window Performance</h6>
                                <div id="counterTable" style="min-height: 420px;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- ROW 5: STAFF PERFORMANCE (1 COLUMN) -->
                    <div class="row g-4 mb-4">
                        <div class="col-12">
                            <div class="card chart-card p-3 p-sm-4">
                                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-person-workspace me-2 text-primary"></i>Staff Performance</h6>
                                <div id="staffBreakdownTable" style="min-height: 420px;"></div>
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
        const HANDLER_URL = '<?= BASE_URL; ?>admin/ajax/reports-analytics';
        const PDF_URL     = '<?= BASE_URL; ?>admin/reports-analytics-pdf';

        const charts = {};
        const tables = {};
        let isInitialized = false;

        const STATUS_COLORS = {
            COMPLETED: '#16a34a', SKIPPED: '#dc2626'
        };

        const isAllOfficesMode = () => parseInt($('#filterOfficeId').val() || '0', 10) === 0;

        const getTodayDate = () => {
            const t = new Date();
            return `${t.getFullYear()}-${String(t.getMonth() + 1).padStart(2, '0')}-${String(t.getDate()).padStart(2, '0')}`;
        };

        const escapeHtml = (s) => String(s ?? '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');

        const fmtMin = (v) => `${(parseFloat(v) || 0).toFixed(1)}m`;

        function wrapChartLabel(str, maxLineLen = 58) {
            const text = String(str || '');
            if (text.length <= maxLineLen) return text;
            const words = text.split(' ');
            const lines = [];
            let current = '';
            words.forEach(w => {
                if ((current + ' ' + w).trim().length > maxLineLen) {
                    if (current) lines.push(current.trim());
                    current = w;
                } else {
                    current = (current + ' ' + w).trim();
                }
            });
            if (current) lines.push(current.trim());
            return lines;
        }

        function drawChart(key, canvasId, config) {
            if (charts[key]) charts[key].destroy();
            const canvas = document.getElementById(canvasId);
            if (!canvas) return;
            charts[key] = new Chart(canvas.getContext('2d'), config);
        }

        function toggleChartEmptyState(key, canvasId, isEmpty, title = 'No data available', subtitle = 'Try adjusting your office, window, or date filters.') {
            const canvas = document.getElementById(canvasId);
            if (!canvas) return;
            const container = canvas.parentElement;
            let emptyEl = container.querySelector('.chart-empty-state');

            if (isEmpty) {
                if (charts[key]) {
                    charts[key].destroy();
                    delete charts[key];
                }
                canvas.style.display = 'none';
                if (!emptyEl) {
                    emptyEl = document.createElement('div');
                    emptyEl.className = 'chart-empty-state';
                    container.appendChild(emptyEl);
                }
                emptyEl.innerHTML = `
                    <i class="bi bi-bar-chart-line"></i>
                    <div class="empty-title">${escapeHtml(title)}</div>
                    <div class="empty-sub">${escapeHtml(subtitle)}</div>
                `;
            } else {
                if (emptyEl) emptyEl.remove();
                canvas.style.display = 'block';
            }
        }

        const buildTablePlaceholder = (title, subtitle) => `
            <div class="text-center py-4">
                <i class="bi bi-inbox fs-2 text-muted d-block mb-2"></i>
                <div class="fw-semibold text-secondary">${escapeHtml(title)}</div>
                <div class="small text-muted">${escapeHtml(subtitle)}</div>
            </div>
        `;

        const colFilter = {
            headerFilter: "input",
            headerFilterFunc: "like",
            headerFilterParams: { allowEmpty: true }
        };

        const baseTabulatorOptions = {
            data: [],
            layout: "fitColumns",
            resizableColumnFit: true,
            headerFilterPlaceholder: "Search",
            pagination: "local",
            paginationSize: 10,
            paginationSizeSelector: [10, 25, 50, 100, true],
            height: "420px",
            headerHozAlign: "center",
            downloadConfig: {
                columnHeaders: true,
                columnGroups: false,
                rowGroups: false,
                formatCells: false
            }
        };

        function initTables() {
            // 0. Office Performance Breakdown (All Offices Mode)
            tables.office = new Tabulator("#officeTable", Object.assign({}, baseTabulatorOptions, {
                height: "380px",
                placeholder: buildTablePlaceholder("No office activity found", "No tickets were recorded across offices for the selected date range."),
                columns: [
                    Object.assign({ title: "Office", field: "office_label", widthGrow: 2, minWidth: 220, vertAlign: "middle", hozAlign: "left",
                        formatter: c => `<div class="w-100 text-start"><span class="fw-semibold">${escapeHtml(c.getValue())}</span></div>` }, colFilter),
                    Object.assign({ title: "Tickets", field: "total_tickets", widthGrow: 1, minWidth: 110, vertAlign: "middle", hozAlign: "center", sorter: "number" }, colFilter),
                    Object.assign({ title: "Completed", field: "total_completed", widthGrow: 1, minWidth: 110, vertAlign: "middle", hozAlign: "center", sorter: "number" }, colFilter),
                    Object.assign({ title: "Skipped", field: "total_skipped", widthGrow: 1, minWidth: 110, vertAlign: "middle", hozAlign: "center", sorter: "number" }, colFilter),
                    Object.assign({ title: "Avg. Waiting", field: "avg_wait_minutes", widthGrow: 1, minWidth: 120, vertAlign: "middle", hozAlign: "center", sorter: "number", formatter: c => fmtMin(c.getValue()) }, colFilter),
                    Object.assign({ title: "Avg. Processing", field: "avg_service_minutes", widthGrow: 1, minWidth: 130, vertAlign: "middle", hozAlign: "center", sorter: "number", formatter: c => fmtMin(c.getValue()) }, colFilter),
                    Object.assign({ title: "Completion", field: "rate", widthGrow: 1, minWidth: 115, vertAlign: "middle", hozAlign: "center", sorter: "number", formatter: c => `${c.getValue()}%` }, colFilter)
                ]
            }));

            // 1. Window Performance (Permanent Office Code column across All Offices & Single Office)
            tables.counter = new Tabulator("#counterTable", Object.assign({}, baseTabulatorOptions, {
                placeholder: buildTablePlaceholder("No window activity found", "No tickets were routed to any window for the selected filters."),
                columns: [
                    Object.assign({ title: "Office Code", field: "office_code", widthGrow: 1, minWidth: 120, vertAlign: "middle", hozAlign: "center",
                        formatter: c => `<div class="w-100 text-center"><span class="badge bg-light text-dark border font-monospace">${escapeHtml(c.getValue() || '---')}</span></div>` }, colFilter),
                    Object.assign({ title: "Window", field: "counter_label", widthGrow: 1, minWidth: 150, vertAlign: "middle", hozAlign: "left",
                        formatter: c => `<div class="w-100 text-start"><span class="badge bg-light text-dark border font-monospace">${escapeHtml(c.getValue())}</span></div>` }, colFilter),
                    Object.assign({ title: "Tickets", field: "total_tickets", widthGrow: 1, minWidth: 110, vertAlign: "middle", hozAlign: "center", sorter: "number" }, colFilter),
                    Object.assign({ title: "Completed", field: "total_completed", widthGrow: 1, minWidth: 110, vertAlign: "middle", hozAlign: "center", sorter: "number" }, colFilter),
                    Object.assign({ title: "Skipped", field: "total_skipped", widthGrow: 1, minWidth: 110, vertAlign: "middle", hozAlign: "center", sorter: "number" }, colFilter),
                    Object.assign({ title: "Avg. Waiting", field: "avg_wait_minutes", widthGrow: 1, minWidth: 120, vertAlign: "middle", hozAlign: "center", sorter: "number", formatter: c => fmtMin(c.getValue()) }, colFilter),
                    Object.assign({ title: "Avg. Calling", field: "avg_call_minutes", widthGrow: 1, minWidth: 120, vertAlign: "middle", hozAlign: "center", sorter: "number", formatter: c => fmtMin(c.getValue()) }, colFilter),
                    Object.assign({ title: "Avg. Processing", field: "avg_service_minutes", widthGrow: 1, minWidth: 130, vertAlign: "middle", hozAlign: "center", sorter: "number", formatter: c => fmtMin(c.getValue()) }, colFilter)
                ]
            }));

            // 2. Staff Performance (Permanent Office Code column across All Offices & Single Office)
            tables.staff = new Tabulator("#staffBreakdownTable", Object.assign({}, baseTabulatorOptions, {
                placeholder: buildTablePlaceholder("No staff activity recorded", "Staff performance metrics will appear once tickets are served."),
                columns: [
                    Object.assign({ title: "Office Code", field: "office_code", widthGrow: 1, minWidth: 120, vertAlign: "middle", hozAlign: "center",
                        formatter: c => `<div class="w-100 text-center"><span class="badge bg-light text-dark border font-monospace">${escapeHtml(c.getValue() || '---')}</span></div>` }, colFilter),
                    Object.assign({ title: "Staff Name", field: "staff_name", widthGrow: 1, minWidth: 180, vertAlign: "middle", hozAlign: "left",
                        formatter: c => `<div class="w-100 text-start"><span class="fw-semibold text-wrap">${escapeHtml(c.getValue() || 'Unassigned')}</span></div>` }, colFilter),
                    Object.assign({ title: "Window", field: "window_label", widthGrow: 1, minWidth: 140, vertAlign: "middle", hozAlign: "center",
                        formatter: c => `<div class="w-100 text-center"><span class="badge bg-light text-dark border font-monospace">${escapeHtml(c.getValue() || '---')}</span></div>` }, colFilter),
                    Object.assign({ title: "Served", field: "total_served", widthGrow: 1, minWidth: 110, vertAlign: "middle", hozAlign: "center", sorter: "number" }, colFilter),
                    Object.assign({ title: "Completed", field: "total_completed", widthGrow: 1, minWidth: 110, vertAlign: "middle", hozAlign: "center", sorter: "number" }, colFilter),
                    Object.assign({ title: "Skipped", field: "total_skipped", widthGrow: 1, minWidth: 110, vertAlign: "middle", hozAlign: "center", sorter: "number" }, colFilter),
                    Object.assign({
                        title: "Completion", field: "rate", widthGrow: 1, minWidth: 120, vertAlign: "middle", hozAlign: "center", sorter: "number",
                        formatter: c => `${c.getValue()}%`
                    }, colFilter)
                ]
            }));
        }

        function redrawAllTables() {
            requestAnimationFrame(() => {
                if (tables.office) tables.office.redraw(true);
                if (tables.counter) tables.counter.redraw(true);
                if (tables.staff) tables.staff.redraw(true);
            });
        }

        function syncScopeLayout() {
            const allOffices = isAllOfficesMode();

            $('#allOfficesComparisonSection').toggle(allOffices);
            $('#exportGroup_office').toggle(allOffices);

            redrawAllTables();
        }

        function resetAllEmptyStates() {
            renderKPIs({
                total_tickets: 0,
                completed_tickets: 0,
                skipped_tickets: 0,
                priority_tickets: 0,
                avg_wait_minutes: 0,
                avg_service_minutes: 0
            });
            renderOfficeComparison([]);
            renderVolumeTrend([]);
            renderVisitorType([]);
            renderStatus([]);
            renderStageTimes({});
            renderTopConcerns([]);
            if (tables.office) tables.office.setData([]);
            if (tables.counter) tables.counter.setData([]);
            if (tables.staff) tables.staff.setData([]);
        }

        function loadCounters() {
            const officeId = parseInt($('#filterOfficeId').val() || '0', 10);
            const $sel = $('#filterCounterId');

            if (officeId === 0) {
                $sel.empty().append('<option value="0" selected>Select office first</option>').prop('disabled', true);
                return;
            }

            $sel.prop('disabled', false);
            const fd = new FormData();
            fd.append('action', 'fetch_counters');
            fd.append('office_id', officeId);

            fetch(HANDLER_URL, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    $sel.empty().append('<option value="0" selected>All Windows</option>');
                    (res.data || []).forEach(c => $sel.append($('<option>').val(c.id).text(c.counter_name)));
                })
                .catch(err => console.error("Counter fetch error:", err));
        }

        function normalizeDefaultFilters() {
            const todayStr = getTodayDate();
            if (!$('#filterOfficeId').prop('disabled') && !$('#filterOfficeId').val()) {
                $('#filterOfficeId').val('0');
            }
            if (!$('#filterCounterId').val()) {
                $('#filterCounterId').val('0');
            }
            if (!$('#filterDateFrom').val()) {
                $('#filterDateFrom').val(todayStr);
            }
            if (!$('#filterDateTo').val()) {
                $('#filterDateTo').val(todayStr);
            }
        }

        function loadAnalytics() {
            normalizeDefaultFilters();
            syncScopeLayout();

            const fd = new FormData();
            fd.append('action', 'fetch_analytics');
            fd.append('office_id', $('#filterOfficeId').val() || '0');
            fd.append('counter_id', $('#filterCounterId').val() || '0');
            fd.append('visitor_type', $('#filterVisitorType').val() || '');
            fd.append('date_from', $('#filterDateFrom').val());
            fd.append('date_to', $('#filterDateTo').val());

            fetch(HANDLER_URL, { method: 'POST', body: fd })
                .then(res => res.json())
                .then(res => {
                    if (!(res.success && res.data)) {
                        console.warn("Analytics response error:", res.message);
                        resetAllEmptyStates();
                        return;
                    }
                    const d = res.data;
                    renderKPIs(d.kpi);

                    if (isAllOfficesMode()) {
                        const officeRows = (d.office_breakdown || []).map(o => {
                            const total = parseInt(o.total_tickets || 0, 10);
                            const completed = parseInt(o.total_completed || 0, 10);
                            return {
                                ...o,
                                office_label: o.office_code ? `${o.office_code} - ${o.office_name}` : (o.office_name || 'Unknown Office'),
                                rate: total > 0 ? Math.round((completed / total) * 100) : 0
                            };
                        });
                        renderOfficeComparison(officeRows);
                        if (tables.office) tables.office.setData(officeRows);
                    }

                    renderVolumeTrend(d.volume_trend);
                    renderVisitorType(d.visitor_distribution);
                    renderStatus(d.status_distribution);
                    renderStageTimes(d.kpi);
                    renderTopConcerns(d.top_concerns);

                    if (tables.counter) {
                        tables.counter.setData((d.counter_breakdown || []).map(c => ({
                            ...c,
                            office_code: c.office_code || '---'
                        })));
                    }

                    if (tables.staff) {
                        tables.staff.setData((d.staff_breakdown || []).map(s => {
                            const served = parseInt(s.total_served || 0, 10), completed = parseInt(s.total_completed || 0, 10);
                            return {
                                ...s,
                                office_code: s.office_code || '---',
                                rate: served > 0 ? Math.round((completed / served) * 100) : 0
                            };
                        }));
                    }

                    redrawAllTables();
                })
                .catch(err => {
                    console.error("Analytics fetch error:", err);
                    resetAllEmptyStates();
                });
        }

        function renderKPIs(k) {
            if (!k) return;
            $('#kpiTotalTickets').text(k.total_tickets || 0);
            $('#kpiCompletedTickets').text(k.completed_tickets || 0);
            $('#kpiSkippedTickets').text(k.skipped_tickets || 0);
            $('#kpiPriorityTickets').text(k.priority_tickets || 0);
            $('#kpiAvgWaitTime').text(fmtMin(k.avg_wait_minutes));
            $('#kpiAvgServiceTime').text(fmtMin(k.avg_service_minutes));
        }

        function renderOfficeComparison(rows) {
            if (!isAllOfficesMode()) return;
            const items = (rows || []).filter(r => (parseInt(r.total_tickets, 10) || 0) > 0);
            const hasData = items.length > 0;
            toggleChartEmptyState('office', 'officeVolumeChart', !hasData, 'No office volume recorded', 'No tickets were issued across offices for the selected filters.');
            if (!hasData) return;

            drawChart('office', 'officeVolumeChart', {
                type: 'bar',
                data: {
                    labels: items.map(o => o.office_code || o.office_name),
                    datasets: [
                        { label: 'Completed', data: items.map(o => o.total_completed || 0), backgroundColor: '#16a34a' },
                        { label: 'Skipped',   data: items.map(o => o.total_skipped || 0),   backgroundColor: '#dc2626' },
                        { label: 'Total',     data: items.map(o => o.total_tickets || 0),   backgroundColor: '#2563eb' }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { yAxes: [{ ticks: { beginAtZero: true, precision: 0 } }] }
                }
            });
        }

        function renderVolumeTrend(data) {
            const items = data || [];
            const hasData = items.some(d => (parseInt(d.total, 10) || 0) > 0);
            toggleChartEmptyState('volume', 'volumeTrendChart', !hasData, 'No queue volume recorded', 'No tickets were issued for the selected filters.');
            if (!hasData) return;

            drawChart('volume', 'volumeTrendChart', {
                type: 'line',
                data: {
                    labels: items.map(d => d.label),
                    datasets: [{
                        label: 'Tickets Issued', data: items.map(d => d.total),
                        borderColor: '#2563eb', backgroundColor: 'rgba(37, 99, 235, 0.08)',
                        fill: true, lineTension: 0.35, borderWidth: 2, pointBackgroundColor: '#2563eb', pointRadius: 4
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, legend: { display: false },
                    scales: { yAxes: [{ ticks: { beginAtZero: true, precision: 0 } }] } }
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

            const labels = ['Student', 'Employee', 'Guest'];
            const values = [counts.Student, counts.Employee, counts.Guest];
            const hasData = values.some(v => v > 0);

            toggleChartEmptyState('visitor', 'visitorTypeChart', !hasData, 'No visitor records found', 'No student, employee, or guest activity in this range.');
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
                options: { responsive: true, maintainAspectRatio: false, legend: { position: 'bottom' } }
            });
        }

        function renderStatus(data) {
            const allowed = ['COMPLETED', 'SKIPPED'];
            const filtered = (data || []).filter(d => allowed.includes(String(d.status || '').toUpperCase()));
            const hasData = filtered.some(d => (parseInt(d.total, 10) || 0) > 0);

            toggleChartEmptyState('status', 'statusChart', !hasData, 'No completed or skipped tickets', 'Queue status breakdown will appear once tickets are processed.');
            if (!hasData) return;

            drawChart('status', 'statusChart', {
                type: 'doughnut',
                data: {
                    labels: filtered.map(d => d.status.charAt(0) + d.status.slice(1).toLowerCase()),
                    datasets: [{
                        data: filtered.map(d => d.total),
                        backgroundColor: filtered.map(d => STATUS_COLORS[d.status] || '#94a3b8'),
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, legend: { position: 'bottom' } }
            });
        }

        function renderStageTimes(k) {
            k = k || {};
            const values = [
                parseFloat(k.avg_wait_minutes) || 0,
                parseFloat(k.avg_call_minutes) || 0,
                parseFloat(k.avg_service_minutes) || 0
            ];
            const hasData = values.some(v => v > 0);

            toggleChartEmptyState('stage', 'stageTimeChart', !hasData, 'No stage timing data', 'Waiting, calling, and processing averages require served tickets.');
            if (!hasData) return;

            drawChart('stage', 'stageTimeChart', {
                type: 'bar',
                data: {
                    labels: ['Waiting', 'Calling', 'Processing'],
                    datasets: [{ label: 'Minutes', data: values, backgroundColor: ['#94a3b8', '#ca8a04', '#2563eb'] }]
                },
                options: { responsive: true, maintainAspectRatio: false, legend: { display: false },
                    scales: { yAxes: [{ ticks: { beginAtZero: true } }] } }
            });
        }

        function renderTopConcerns(data) {
            const items = (data || []).filter(d => (parseInt(d.total, 10) || 0) > 0);
            const container = document.getElementById('topConcernsChart').parentElement;
            const hasData = items.length > 0;

            if (!hasData) {
                container.style.minHeight = '280px';
                toggleChartEmptyState('concerns', 'topConcernsChart', true, 'No transactions logged', 'Top requested transactions will display here once logged.');
                return;
            }

            toggleChartEmptyState('concerns', 'topConcernsChart', false);

            const rawLabels = items.map(d => (isAllOfficesMode() && d.office_code) ? `[${d.office_code}] ${d.concern}` : d.concern);
            const wrappedLabels = rawLabels.map(lbl => wrapChartLabel(lbl, 60));
            const dynamicHeight = Math.max(360, items.length * 52);
            container.style.minHeight = `${dynamicHeight}px`;

            drawChart('concerns', 'topConcernsChart', {
                type: 'horizontalBar',
                data: {
                    labels: wrappedLabels,
                    datasets: [{ label: 'Requests', data: items.map(d => d.total), backgroundColor: '#0284c7' }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    legend: { display: false },
                    tooltips: {
                        callbacks: {
                            title: function(tooltipItems) {
                                const idx = tooltipItems[0].index;
                                return rawLabels[idx] || '';
                            }
                        }
                    },
                    scales: {
                        xAxes: [{ ticks: { beginAtZero: true, precision: 0 } }],
                        yAxes: [{
                            ticks: {
                                autoSkip: false,
                                fontSize: 12
                            }
                        }]
                    }
                }
            });
        }

        function resetFiltersToDefault() {
            const todayStr = getTodayDate();
            if (!$('#filterOfficeId').prop('disabled')) {
                $('#filterOfficeId').val('0');
                loadCounters();
            }
            $('#filterCounterId').val('0');
            $('#filterVisitorType').val('');
            $('#filterDateFrom').val(todayStr);
            $('#filterDateTo').val(todayStr);
            if (tables.office) tables.office.clearHeaderFilter();
            if (tables.counter) tables.counter.clearHeaderFilter();
            if (tables.staff) tables.staff.clearHeaderFilter();
        }

        function initPageData() {
            if (isInitialized) return;
            isInitialized = true;

            initTables();
            resetFiltersToDefault();
            requestAnimationFrame(() => {
                loadAnalytics();
            });
        }

        // Events
        $('#filterOfficeId').on('change', loadCounters);

        $('#analyticsFilterForm').on('submit', function(e) {
            e.preventDefault();
            loadAnalytics();
        });

        $('#btnResetFilters').on('click', function() {
            resetFiltersToDefault();
            loadAnalytics();
        });

        document.querySelectorAll('[data-export]').forEach(el => {
            el.addEventListener('click', () => {
                const [name, fmt] = el.dataset.export.split(':');
                const table = tables[name];
                if (!table) return;
                const file = `${name}_report`;
                if (fmt === 'xlsx') table.download('xlsx', `${file}.xlsx`, { sheetName: name });
                else table.download(fmt, `${file}.${fmt}`);
            });
        });

        document.getElementById('export-pdf-report')?.addEventListener('click', function() {
            const qs = new URLSearchParams({
                office_id: $('#filterOfficeId').val() || 0,
                counter_id: $('#filterCounterId').val() || 0,
                visitor_type: $('#filterVisitorType').val() || '',
                date_from: $('#filterDateFrom').val() || '',
                date_to: $('#filterDateTo').val() || ''
            });

            let frame = document.getElementById('silentPrintIframe');
            if (!frame) {
                frame = document.createElement('iframe');
                frame.id = 'silentPrintIframe';
                frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;';
                document.body.appendChild(frame);
            }
            frame.src = `${PDF_URL}?${qs.toString()}`;
        });

        if (document.readyState === 'complete') {
            initPageData();
        } else {
            window.addEventListener('load', initPageData);
        }

        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                resetFiltersToDefault();
                loadAnalytics();
            }
        });
    })();
</script>
</body>
</html>