<?php
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

    // Priority 2: employee table (same fallback the handler uses)
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

        /* Tabulator Custom Layout Styling */
        .report-table-box {
            border-radius: 0.5rem;
            border: 1px solid #e2e8f0;
            width: 100%;
        }
        .tabulator .tabulator-header {
            background-color: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
            font-weight: 600;
        }
        .tabulator .tabulator-col-title {
            white-space: normal !important;
            word-wrap: break-word !important;
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
                            <form id="analyticsFilterForm" class="row g-3 align-items-end">

                                <div class="col-12 col-md-3">
                                    <label for="filterOfficeId" class="form-label fw-bold small text-muted">
                                        Office Location <?php if ($lock_office): ?><i class="bi bi-lock-fill ms-1" title="Locked to your assigned office"></i><?php endif; ?>
                                    </label>
                                    <select id="filterOfficeId" class="form-select form-select-sm" <?= $lock_office ? 'disabled style="pointer-events:none; background-color:#f1f5f9;"' : ''; ?>>
                                        <?php if ($g_user_role_upper === 'ADMIN' || $g_user_role_upper === 'STATION_ADMIN'): ?>
                                            <option value="0">-- All Offices --</option>
                                            <?php foreach ($offices as $off): ?>
                                                <option value="<?= $off['office_id']; ?>"><?= htmlspecialchars($off['office_name']); ?> (<?= htmlspecialchars($off['office_code']); ?>)</option>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <?php if ($assigned_office): ?>
                                                <option value="<?= $assigned_office['office_id']; ?>" selected><?= htmlspecialchars($assigned_office['office_name']); ?> (<?= htmlspecialchars($assigned_office['office_code']); ?>)</option>
                                            <?php else: ?>
                                                <option value="0">-- All Offices --</option>
                                                <?php foreach ($offices as $off): ?>
                                                    <option value="<?= $off['office_id']; ?>"><?= htmlspecialchars($off['office_name']); ?></option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>

                                <div class="col-12 col-sm-6 col-md-2">
                                    <label for="filterCounterId" class="form-label fw-bold small text-muted">Window / Counter</label>
                                    <select id="filterCounterId" class="form-select form-select-sm">
                                        <option value="0">All Windows</option>
                                        <?php foreach ($counters as $c): ?>
                                            <option value="<?= (int)$c['id']; ?>"><?= htmlspecialchars($c['counter_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-12 col-sm-6 col-md-2">
                                    <label for="filterVisitorType" class="form-label fw-bold small text-muted">Visitor Type</label>
                                    <select id="filterVisitorType" class="form-select form-select-sm">
                                        <option value="">All Types</option>
                                        <option value="STUDENT">Student</option>
                                        <option value="EMPLOYEE">Employee</option>
                                        <option value="GUEST">Guest</option>
                                    </select>
                                </div>

                                <div class="col-6 col-md-2">
                                    <label for="filterDateFrom" class="form-label fw-bold small text-muted">Date From</label>
                                    <input type="date" id="filterDateFrom" class="form-control form-control-sm" value="<?= date('Y-m-d'); ?>">
                                </div>

                                <div class="col-6 col-md-3">
                                    <label for="filterDateTo" class="form-label fw-bold small text-muted">Date To</label>
                                    <input type="date" id="filterDateTo" class="form-control form-control-sm" value="<?= date('Y-m-d'); ?>">
                                </div>

                                <div class="col-12 d-flex flex-wrap gap-2 justify-content-end">
                                    <button type="submit" class="btn btn-primary btn-sm fw-bold px-4">
                                        <i class="bi bi-filter me-1"></i>Apply
                                    </button>
                                    <button type="button" id="btnResetFilters" class="btn btn-outline-secondary btn-sm" title="Reset Filters">
                                        <i class="bi bi-arrow-counterclockwise"></i>
                                    </button>

                                    <div class="dropdown">
                                        <button class="btn btn-action btn-sm dropdown-toggle d-inline-flex align-items-center fw-semibold text-nowrap" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="bi bi-download me-1"></i>Export / Download
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                            <?php foreach (['staff' => 'Staff Performance', 'counter' => 'Window Performance', 'sub' => 'Services & Sub-Concerns'] as $key => $label): ?>
                                                <li class="px-3 py-1">
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
                            </form>
                        </div>
                    </div>

                    <!-- KPI CARDS -->
                    <div class="row g-3 mb-4">
                        <?php
                        $kpi_cards = [
                            ['kpiTotalTickets',     'Total Tickets',      'primary', 'bi-ticket-perforated-fill', '0'],
                            ['kpiCompletedTickets', 'Completed',          'success', 'bi-check-circle-fill',      '0'],
                            ['kpiSkippedTickets',   'Skipped / Missed',   'danger',  'bi-x-circle-fill',          '0'],
                            ['kpiPriorityTickets',  'Priority Tickets',   'warning', 'bi-star-fill',              '0'],
                            ['kpiAvgWaitTime',      'Avg. Waiting Time',  'info',    'bi-hourglass-split',        '0m'],
                            ['kpiAvgServiceTime',   'Avg. Service Time',  'info',    'bi-clock-history',          '0m'],
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

                    <!-- ROW 1: TREND + VISITORS -->
                    <div class="row g-4 mb-4">
                        <div class="col-12 col-lg-8">
                            <div class="card chart-card p-3 p-sm-4">
                                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-graph-up-arrow me-2 text-primary"></i>Queue Volume Trend</h6>
                                <div class="chart-container"><canvas id="volumeTrendChart"></canvas></div>
                            </div>
                        </div>
                        <div class="col-12 col-lg-4">
                            <div class="card chart-card p-3 p-sm-4">
                                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-pie-chart-fill me-2 text-primary"></i>Visitor Distribution</h6>
                                <div class="chart-container"><canvas id="visitorTypeChart"></canvas></div>
                            </div>
                        </div>
                    </div>

                    <!-- ROW 2: STATUS + STAGE TIMES + TOP SERVICES -->
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
                                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-card-checklist me-2 text-primary"></i>Top Services</h6>
                                <div class="chart-container"><canvas id="topConcernsChart"></canvas></div>
                            </div>
                        </div>
                    </div>

                    <!-- ROW 3: WINDOWS + STAFF -->
                    <div class="row g-4 mb-4">
                        <div class="col-12 col-lg-6">
                            <div class="card chart-card p-3 p-sm-4">
                                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-window-stack me-2 text-primary"></i>Window Performance</h6>
                                <div id="counterTable" class="report-table-box"></div>
                            </div>
                        </div>
                        <div class="col-12 col-lg-6">
                            <div class="card chart-card p-3 p-sm-4">
                                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-person-workspace me-2 text-primary"></i>Staff Performance</h6>
                                <div id="staffBreakdownTable" class="report-table-box"></div>
                            </div>
                        </div>
                    </div>

                    <!-- ROW 4: SUB-CONCERNS -->
                    <div class="row g-4 mb-4">
                        <div class="col-12">
                            <div class="card chart-card p-3 p-sm-4">
                                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-list-nested me-2 text-primary"></i>Top Services & Sub-Concerns</h6>
                                <div id="subConcernTable" class="report-table-box"></div>
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
            const STATUS_COLORS = {
                COMPLETED: '#16a34a', SKIPPED: '#dc2626', SERVING: '#2563eb',
                CALLED: '#ca8a04', WAITING: '#94a3b8', CANCELLED: '#6b7280'
            };

            const getTodayDate = () => {
                const t = new Date();
                return `${t.getFullYear()}-${String(t.getMonth() + 1).padStart(2, '0')}-${String(t.getDate()).padStart(2, '0')}`;
            };

            const escapeHtml = (s) => String(s ?? '')
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#039;');

            const fmtMin = (v) => `${(parseFloat(v) || 0).toFixed(1)}m`;

            function drawChart(key, canvasId, config) {
                if (charts[key]) charts[key].destroy();
                charts[key] = new Chart(document.getElementById(canvasId).getContext('2d'), config);
            }

            const downloadConfig = { columnHeaders: true, columnGroups: false, rowGroups: false, formatCells: false };

            function initTables() {
                tables.counter = new Tabulator("#counterTable", {
                    data: [], layout: "fitColumns", downloadConfig,
                    placeholder: "No window activity for the selected filters.",
                    columns: [
                        { title: "Window", field: "counter_label", formatter: c => `<span class="fw-semibold">${escapeHtml(c.getValue())}</span>` },
                        { title: "Tickets", field: "total_tickets", hozAlign: "center", headerHozAlign: "center", sorter: "number" },
                        { title: "Completed", field: "total_completed", hozAlign: "center", headerHozAlign: "center", sorter: "number" },
                        { title: "Skipped", field: "total_skipped", hozAlign: "center", headerHozAlign: "center", sorter: "number" },
                        { title: "Avg. Service", field: "avg_service_minutes", hozAlign: "center", headerHozAlign: "center", sorter: "number", formatter: c => fmtMin(c.getValue()) }
                    ]
                });

                tables.staff = new Tabulator("#staffBreakdownTable", {
                    data: [], layout: "fitColumns", downloadConfig,
                    placeholder: "No staff activity recorded for selected range.",
                    columns: [
                        { title: "Staff Name", field: "staff_name", formatter: c => `<span class="fw-semibold text-wrap">${escapeHtml(c.getValue() || 'Unassigned')}</span>` },
                        { title: "Window(s)", field: "windows", formatter: c => escapeHtml(c.getValue() || '---') },
                        { title: "Served", field: "total_served", hozAlign: "center", headerHozAlign: "center", sorter: "number" },
                        { title: "Completed", field: "total_completed", hozAlign: "center", headerHozAlign: "center", sorter: "number" },
                        {
                            title: "Completion", field: "rate", hozAlign: "center", headerHozAlign: "center", sorter: "number",
                            formatter: function(cell) {
                                const rate = cell.getValue();
                                const cls = rate >= 80 ? 'bg-success-subtle text-success border-success-subtle' : 'bg-primary-subtle text-primary';
                                return `<span class="badge ${cls} px-2 py-1 rounded-pill font-monospace">${rate}%</span>`;
                            }
                        }
                    ]
                });

                tables.sub = new Tabulator("#subConcernTable", {
                    data: [], layout: "fitColumns", downloadConfig,
                    placeholder: "No sub-concerns recorded for selected range.",
                    columns: [
                        { title: "Service / Transaction Type", field: "transaction_name", formatter: c => escapeHtml(c.getValue()) },
                        { title: "Sub-Concern", field: "sub_concern_name", formatter: c => `<span class="fw-semibold">${escapeHtml(c.getValue())}</span>` },
                        { title: "Requests", field: "total", width: 120, hozAlign: "center", headerHozAlign: "center", sorter: "number" }
                    ]
                });
            }

            function loadCounters() {
                const fd = new FormData();
                fd.append('action', 'fetch_counters');
                fd.append('office_id', $('#filterOfficeId').val() || 0);

                fetch(HANDLER_URL, { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(res => {
                        const $sel = $('#filterCounterId').empty().append('<option value="0">All Windows</option>');
                        (res.data || []).forEach(c => $sel.append($('<option>').val(c.id).text(c.counter_name)));
                    })
                    .catch(err => console.error("Counter fetch error:", err));
            }

            function loadAnalytics() {
                const fd = new FormData();
                fd.append('action', 'fetch_analytics');
                fd.append('office_id', $('#filterOfficeId').val());
                fd.append('counter_id', $('#filterCounterId').val());
                fd.append('visitor_type', $('#filterVisitorType').val());
                fd.append('date_from', $('#filterDateFrom').val());
                fd.append('date_to', $('#filterDateTo').val());

                fetch(HANDLER_URL, { method: 'POST', body: fd })
                    .then(res => res.json())
                    .then(res => {
                        if (!(res.success && res.data)) {
                            console.warn("Analytics response error:", res.message);
                            return;
                        }
                        const d = res.data;
                        renderKPIs(d.kpi);
                        renderVolumeTrend(d.volume_trend);
                        renderVisitorType(d.visitor_distribution);
                        renderStatus(d.status_distribution);
                        renderStageTimes(d.kpi);
                        renderTopConcerns(d.top_concerns);
                        tables.counter.setData(d.counter_breakdown || []);
                        tables.sub.setData(d.sub_concerns || []);
                        tables.staff.setData((d.staff_breakdown || []).map(s => {
                            const served = parseInt(s.total_served || 0), completed = parseInt(s.total_completed || 0);
                            return { ...s, rate: served > 0 ? Math.round((completed / served) * 100) : 0 };
                        }));
                    })
                    .catch(err => console.error("Analytics fetch error:", err));
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

            function renderVolumeTrend(data) {
                drawChart('volume', 'volumeTrendChart', {
                    type: 'line',
                    data: {
                        labels: (data || []).map(d => d.label),
                        datasets: [{
                            label: 'Tickets Issued', data: (data || []).map(d => d.total),
                            borderColor: '#2563eb', backgroundColor: 'rgba(37, 99, 235, 0.08)',
                            fill: true, lineTension: 0.35, borderWidth: 2, pointBackgroundColor: '#2563eb', pointRadius: 4
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, legend: { display: false },
                        scales: { yAxes: [{ ticks: { beginAtZero: true, precision: 0 } }] } }
                });
            }

            function renderVisitorType(data) {
                drawChart('visitor', 'visitorTypeChart', {
                    type: 'doughnut',
                    data: {
                        labels: (data || []).map(d => d.type),
                        datasets: [{ data: (data || []).map(d => d.total), backgroundColor: ['#2563eb', '#0284c7', '#ca8a04'], borderWidth: 2, borderColor: '#ffffff' }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, legend: { position: 'bottom' } }
                });
            }

            function renderStatus(data) {
                drawChart('status', 'statusChart', {
                    type: 'doughnut',
                    data: {
                        labels: (data || []).map(d => d.status.charAt(0) + d.status.slice(1).toLowerCase()),
                        datasets: [{ data: (data || []).map(d => d.total), backgroundColor: (data || []).map(d => STATUS_COLORS[d.status] || '#94a3b8'), borderWidth: 2, borderColor: '#ffffff' }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, legend: { position: 'bottom' } }
                });
            }

            function renderStageTimes(k) {
                k = k || {};
                drawChart('stage', 'stageTimeChart', {
                    type: 'bar',
                    data: {
                        labels: ['Waiting', 'Calling', 'Processing'],
                        datasets: [{ label: 'Minutes', data: [k.avg_wait_minutes || 0, k.avg_call_minutes || 0, k.avg_service_minutes || 0], backgroundColor: ['#94a3b8', '#ca8a04', '#2563eb'] }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, legend: { display: false },
                        scales: { yAxes: [{ ticks: { beginAtZero: true } }] } }
                });
            }

            function renderTopConcerns(data) {
                drawChart('concerns', 'topConcernsChart', {
                    type: 'horizontalBar',
                    data: {
                        labels: (data || []).map(d => d.concern),
                        datasets: [{ label: 'Requests', data: (data || []).map(d => d.total), backgroundColor: '#0284c7' }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, legend: { display: false },
                        scales: { xAxes: [{ ticks: { beginAtZero: true, precision: 0 } }] } }
                });
            }

            // Events
            $('#filterOfficeId').on('change', loadCounters);

            $('#analyticsFilterForm').on('submit', function(e) {
                e.preventDefault();
                loadAnalytics();
            });

            $('#btnResetFilters').on('click', function() {
                const todayStr = getTodayDate();
                $('#filterCounterId').val('0');
                $('#filterVisitorType').val('');
                $('#filterDateFrom').val(todayStr);
                $('#filterDateTo').val(todayStr);
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

            $(document).ready(function() {
                initTables();
                loadAnalytics();
            });
        })();
    </script>
</body>
</html>