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

if ($db_connect && $user_id > 0) {
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

    if ($g_user_role_upper === 'ADMIN' || $g_user_role_upper === 'STATION_ADMIN' || !$assigned_office) {
        $stmt = $db_connect->query("SELECT office_id, office_code, office_name FROM office WHERE flag_status = 0 ORDER BY office_name ASC");
        if ($stmt) {
            while ($row = $stmt->fetch_assoc()) {
                $offices[] = $row;
            }
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
        #staffBreakdownTable {
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
                                
                                <!-- OFFICE SELECTION -->
                                <div class="col-12 col-md-3">
                                    <label for="filterOfficeId" class="form-label fw-bold small text-muted">Office Location</label>
                                    <select id="filterOfficeId" class="form-select form-select-sm">
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

                                <!-- VISITOR TYPE -->
                                <div class="col-12 col-sm-6 col-md-2">
                                    <label for="filterVisitorType" class="form-label fw-bold small text-muted">Visitor Type</label>
                                    <select id="filterVisitorType" class="form-select form-select-sm">
                                        <option value="">All Types</option>
                                        <option value="STUDENT">Student</option>
                                        <option value="EMPLOYEE">Employee</option>
                                        <option value="GUEST">Guest</option>
                                    </select>
                                </div>

                                <!-- DATE FROM -->
                                <div class="col-6 col-md-2">
                                    <label for="filterDateFrom" class="form-label fw-bold small text-muted">Date From</label>
                                    <input type="date" id="filterDateFrom" class="form-control form-control-sm" value="<?= date('Y-m-d'); ?>">
                                </div>

                                <!-- DATE TO -->
                                <div class="col-6 col-md-2">
                                    <label for="filterDateTo" class="form-label fw-bold small text-muted">Date To</label>
                                    <input type="date" id="filterDateTo" class="form-control form-control-sm" value="<?= date('Y-m-d'); ?>">
                                </div>

                                <!-- ACTION BUTTONS & EXPORT DROPDOWN -->
                                <div class="col-12 col-md-3 d-flex gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold">
                                        <i class="bi bi-filter me-1"></i>Apply
                                    </button>
                                    <button type="button" id="btnResetFilters" class="btn btn-outline-secondary btn-sm" title="Reset Filters">
                                        <i class="bi bi-arrow-counterclockwise"></i>
                                    </button>

                                    <!-- Export Options Dropdown -->
                                    <div class="dropdown">
                                        <button class="btn btn-action btn-sm dropdown-toggle d-inline-flex align-items-center fw-semibold text-nowrap" type="button" id="exportDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="bi bi-download me-1"></i>Export / Download
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="exportDropdown">
                                            <li><a class="dropdown-item text-slate-700" href="javascript:void(0)" id="export-csv"><i class="bi bi-filetype-csv text-success me-2"></i> Export CSV</a></li>
                                            <li><a class="dropdown-item text-slate-700" href="javascript:void(0)" id="export-excel"><i class="bi bi-file-earmark-excel text-success me-2"></i> Export Excel (.xlsx)</a></li>
                                            <li><a class="dropdown-item text-slate-700" href="javascript:void(0)" id="export-json"><i class="bi bi-filetype-json text-warning me-2"></i> Export JSON</a></li>
                                            <li>
                                                <hr class="dropdown-divider">
                                            </li>
                                            <li><a class="dropdown-item text-slate-700" href="javascript:void(0)" id="export-pdf-report"><i class="bi bi-file-earmark-pdf-fill text-danger me-2"></i> PDF Report</a></li>
                                        </ul>
                                    </div>
                                </div>

                            </form>
                        </div>
                    </div>

                    <!-- KPI SUMMARY CARDS ROW -->
                    <div class="row g-3 mb-4">
                        <div class="col-12 col-sm-6 col-xl-3">
                            <div class="kpi-card d-flex align-items-center">
                                <div class="kpi-icon primary flex-shrink-0 me-3">
                                    <i class="bi bi-ticket-perforated-fill"></i>
                                </div>
                                <div>
                                    <span class="fw-semibold text-muted small d-block mb-1">Total Tickets</span>
                                    <h3 class="fw-bold text-dark mb-0 font-monospace" id="kpiTotalTickets">0</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-xl-3">
                            <div class="kpi-card d-flex align-items-center">
                                <div class="kpi-icon success flex-shrink-0 me-3">
                                    <i class="bi bi-check-circle-fill"></i>
                                </div>
                                <div>
                                    <span class="fw-semibold text-muted small d-block mb-1">Completed</span>
                                    <h3 class="fw-bold text-dark mb-0 font-monospace" id="kpiCompletedTickets">0</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-xl-3">
                            <div class="kpi-card d-flex align-items-center">
                                <div class="kpi-icon danger flex-shrink-0 me-3">
                                    <i class="bi bi-x-circle-fill"></i>
                                </div>
                                <div>
                                    <span class="fw-semibold text-muted small d-block mb-1">Skipped / Missed</span>
                                    <h3 class="fw-bold text-dark mb-0 font-monospace" id="kpiSkippedTickets">0</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-xl-3">
                            <div class="kpi-card d-flex align-items-center">
                                <div class="kpi-icon info flex-shrink-0 me-3">
                                    <i class="bi bi-clock-history"></i>
                                </div>
                                <div>
                                    <span class="fw-semibold text-muted small d-block mb-1">Avg. Service Time</span>
                                    <h3 class="fw-bold text-dark mb-0 font-monospace" id="kpiAvgServiceTime">0m</h3>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- CHARTS GRID -->
                    <div class="row g-4 mb-4">
                        <!-- HOURLY / DAILY VOLUME TREND CHART -->
                        <div class="col-12 col-lg-8">
                            <div class="card chart-card p-3 p-sm-4">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <h6 class="fw-bold text-dark mb-0"><i class="bi bi-graph-up-arrow me-2 text-primary"></i>Queue Volume Trend</h6>
                                    <span class="badge bg-light text-muted border small">Time Distribution</span>
                                </div>
                                <div class="chart-container">
                                    <canvas id="volumeTrendChart"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- VISITOR TYPE BREAKDOWN CHART -->
                        <div class="col-12 col-lg-4">
                            <div class="card chart-card p-3 p-sm-4">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <h6 class="fw-bold text-dark mb-0"><i class="bi bi-pie-chart-fill me-2 text-primary"></i>Visitor Distribution</h6>
                                    <span class="badge bg-light text-muted border small">Demographics</span>
                                </div>
                                <div class="chart-container">
                                    <canvas id="visitorTypeChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TOP CONCERNS BAR CHART & TABULATOR STAFF BREAKDOWN TABLE -->
                    <div class="row g-4 mb-4">
                        <div class="col-12 col-lg-6">
                            <div class="card chart-card p-3 p-sm-4">
                                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-bar-chart-line-fill me-2 text-primary"></i>Top Transaction Concerns</h6>
                                <div class="chart-container">
                                    <canvas id="topConcernsChart"></canvas>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-lg-6">
                            <div class="card chart-card p-3 p-sm-4">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <h6 class="fw-bold text-dark mb-0"><i class="bi bi-person-workspace me-2 text-primary"></i>Staff Performance Breakdown</h6>
                                </div>

                                <!-- TABULATOR MOUNT CONTAINER -->
                                <div id="staffBreakdownTable"></div>
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

            let volumeTrendChart = null;
            let visitorTypeChart = null;
            let topConcernsChart  = null;
            let staffTabulator    = null;

            const getTodayDate = () => {
                const today = new Date();
                const year = today.getFullYear();
                const month = String(today.getMonth() + 1).padStart(2, '0');
                const day = String(today.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            };

            function initStaffTable() {
                staffTabulator = new Tabulator("#staffBreakdownTable", {
                    data: [],
                    layout: "fitColumns",
                    responsiveLayout: false,
                    placeholder: "No staff activity recorded for selected range.",
                    downloadConfig: {
                        columnHeaders: true,
                        columnGroups: false,
                        rowGroups: false,
                        formatCells: false
                    },
                    columns: [
                        { 
                            title: "Staff Name", 
                            field: "staff_name",
                            headerSort: true,
                            formatter: (cell) => `<span class="fw-semibold text-wrap">${cell.getValue() || 'Unassigned'}</span>` 
                        },
                        { 
                            title: "Served", 
                            field: "total_served", 
                            hozAlign: "center", 
                            headerHozAlign: "center",
                            sorter: "number" 
                        },
                        { 
                            title: "Completed", 
                            field: "total_completed", 
                            hozAlign: "center", 
                            headerHozAlign: "center",
                            sorter: "number" 
                        },
                        { 
                            title: "Completion Rate", 
                            field: "rate", 
                            hozAlign: "center", 
                            headerHozAlign: "center",
                            sorter: "number",
                            formatter: function(cell) {
                                const rate = cell.getValue();
                                const isHigh = rate >= 80;
                                const bgClass = isHigh ? 'bg-success-subtle text-success border-success-subtle' : 'bg-primary-subtle text-primary';
                                return `<span class="badge ${bgClass} px-2 py-1 rounded-pill font-monospace">${rate}%</span>`;
                            }
                        }
                    ]
                });
            }

            function loadAnalytics() {
                const formData = new FormData();
                formData.append('action', 'fetch_analytics');
                formData.append('office_id', $('#filterOfficeId').val());
                formData.append('visitor_type', $('#filterVisitorType').val());
                formData.append('date_from', $('#filterDateFrom').val());
                formData.append('date_to', $('#filterDateTo').val());

                fetch(HANDLER_URL, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(res => {
                    if (res.success && res.data) {
                        renderKPICards(res.data.kpi);
                        renderVolumeTrendChart(res.data.volume_trend);
                        renderVisitorTypeChart(res.data.visitor_distribution);
                        renderTopConcernsChart(res.data.top_concerns);
                        renderStaffBreakdownTable(res.data.staff_breakdown);
                    } else {
                        console.warn("Analytics response error:", res.message);
                    }
                })
                .catch(err => console.error("Analytics fetch error:", err));
            }

            function renderKPICards(kpi) {
                if (!kpi) return;
                $('#kpiTotalTickets').text(kpi.total_tickets || 0);
                $('#kpiCompletedTickets').text(kpi.completed_tickets || 0);
                $('#kpiSkippedTickets').text(kpi.skipped_tickets || 0);
                $('#kpiAvgServiceTime').text(`${kpi.avg_service_minutes || 0}m`);
            }

            function renderVolumeTrendChart(data) {
                const ctx = document.getElementById('volumeTrendChart').getContext('2d');
                if (volumeTrendChart) volumeTrendChart.destroy();

                const labels = data ? data.map(d => d.label) : [];
                const counts = data ? data.map(d => d.total) : [];

                volumeTrendChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Tickets Issued',
                            data: counts,
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
                        legend: { display: false },
                        scales: {
                            yAxes: [{
                                ticks: { beginAtZero: true, precision: 0 }
                            }]
                        }
                    }
                });
            }

            function renderVisitorTypeChart(data) {
                const ctx = document.getElementById('visitorTypeChart').getContext('2d');
                if (visitorTypeChart) visitorTypeChart.destroy();

                const labels = data ? data.map(d => d.type) : ['Student', 'Employee', 'Guest'];
                const counts = data ? data.map(d => d.total) : [0, 0, 0];

                visitorTypeChart = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: counts,
                            backgroundColor: ['#2563eb', '#0284c7', '#ca8a04'],
                            borderWidth: 2,
                            borderColor: '#ffffff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        legend: { position: 'bottom' }
                    }
                });
            }

            function renderTopConcernsChart(data) {
                const ctx = document.getElementById('topConcernsChart').getContext('2d');
                if (topConcernsChart) topConcernsChart.destroy();

                const labels = data ? data.map(d => d.concern) : [];
                const counts = data ? data.map(d => d.total) : [];

                topConcernsChart = new Chart(ctx, {
                    type: 'horizontalBar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Requests',
                            data: counts,
                            backgroundColor: '#0284c7'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        legend: { display: false },
                        scales: {
                            xAxes: [{
                                ticks: { beginAtZero: true, precision: 0 }
                            }]
                        }
                    }
                });
            }

            function renderStaffBreakdownTable(staffList) {
                if (!staffTabulator) return;

                const formattedData = (staffList || []).map(staff => {
                    const served = parseInt(staff.total_served || 0);
                    const completed = parseInt(staff.total_completed || 0);
                    const rate = served > 0 ? Math.round((completed / served) * 100) : 0;
                    return {
                        staff_name: staff.staff_name || 'Unassigned',
                        total_served: served,
                        total_completed: completed,
                        rate: rate
                    };
                });

                staffTabulator.setData(formattedData);
            }

            $('#analyticsFilterForm').on('submit', function(e) {
                e.preventDefault();
                loadAnalytics();
            });

            $('#btnResetFilters').on('click', function() {
                const todayStr = getTodayDate();
                $('#filterVisitorType').val('');
                $('#filterDateFrom').val(todayStr);
                $('#filterDateTo').val(todayStr);
                loadAnalytics();
            });

            document.getElementById('export-csv')?.addEventListener('click', () => {
                if (staffTabulator) staffTabulator.download("csv", "staff_performance_report.csv");
            });

            document.getElementById('export-excel')?.addEventListener('click', () => {
                if (staffTabulator) staffTabulator.download("xlsx", "staff_performance_report.xlsx", { sheetName: "Staff Performance" });
            });

            document.getElementById('export-json')?.addEventListener('click', () => {
                if (staffTabulator) staffTabulator.download("json", "staff_performance_report.json");
            });

            document.getElementById('export-pdf-report')?.addEventListener('click', function() {
                const officeId    = $('#filterOfficeId').val() || 0;
                const visitorType = $('#filterVisitorType').val() || '';
                const dateFrom    = $('#filterDateFrom').val() || '';
                const dateTo      = $('#filterDateTo').val() || '';

                const printUrl = `<?= BASE_URL; ?>admin/reports-analytics-pdf?office_id=${officeId}&visitor_type=${encodeURIComponent(visitorType)}&date_from=${dateFrom}&date_to=${dateTo}`;

                let printFrame = document.getElementById('silentPrintIframe');
                if (!printFrame) {
                    printFrame = document.createElement('iframe');
                    printFrame.id = 'silentPrintIframe';
                    printFrame.style.position = 'fixed';
                    printFrame.style.right = '0';
                    printFrame.style.bottom = '0';
                    printFrame.style.width = '0';
                    printFrame.style.height = '0';
                    printFrame.style.border = '0';
                    document.body.appendChild(printFrame);
                }

                printFrame.src = printUrl;
            });

            $(document).ready(function() {
                initStaffTable();
                loadAnalytics();
            });
        })();
    </script>
</body>
</html>