<?php
# Core Config & DB Initialization
global $db_connect, $session_class;
$db_connect = $db_connect ?? null;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Resolve Current User Info & Role
$user_id = 0;
if (isset($session_class) && method_exists($session_class, 'getValue')) {
    $user_id = (int)($session_class->getValue('user_id') ?? $session_class->getValue('id') ?? 0);
}
if ($user_id <= 0) {
    $user_id = (int)($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
}

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

$generated_by_name = "System Administrator";
if ($db_connect && $user_id > 0) {
    $stmtUser = $db_connect->prepare("SELECT CONCAT(first_name, ' ', last_name) AS full_name FROM users WHERE id = ? LIMIT 1");
    if ($stmtUser) {
        $stmtUser->bind_param("i", $user_id);
        $stmtUser->execute();
        $userData = $stmtUser->get_result()->fetch_assoc();
        if (!empty($userData['full_name'])) {
            $generated_by_name = $userData['full_name'];
        }
        $stmtUser->close();
    }
}

// 2. Resolve URL Query Parameters & Enforce Staff Office Scoping
$office_id    = (int)($_GET['office_id'] ?? 0);
$counter_id   = (int)($_GET['counter_id'] ?? 0);
$visitor_type = strtoupper(trim($_GET['visitor_type'] ?? ''));
$date_from    = trim($_GET['date_from'] ?? '');
$date_to      = trim($_GET['date_to'] ?? '');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) $date_from = date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to))   $date_to   = date('Y-m-d');
if ($date_from > $date_to) { [$date_from, $date_to] = [$date_to, $date_from]; }
if (!in_array($visitor_type, ['STUDENT', 'EMPLOYEE', 'GUEST'], true)) $visitor_type = '';

if ($g_user_role_upper === 'ADMIN_STAFF' && $user_id > 0 && $db_connect) {
    $staff_office_id = 0;

    $stmtAssign = $db_connect->prepare("SELECT office_id FROM staff_assignments WHERE user_id = ? AND flag_status = 0 ORDER BY id DESC LIMIT 1");
    if ($stmtAssign) {
        $stmtAssign->bind_param("i", $user_id);
        $stmtAssign->execute();
        $staff_office_id = (int)($stmtAssign->get_result()->fetch_assoc()['office_id'] ?? 0);
        $stmtAssign->close();
    }
    if ($staff_office_id <= 0) {
        $stmtEmp = $db_connect->prepare("SELECT office_id FROM employee WHERE user_id = ? LIMIT 1");
        if ($stmtEmp) {
            $stmtEmp->bind_param("i", $user_id);
            $stmtEmp->execute();
            $staff_office_id = (int)($stmtEmp->get_result()->fetch_assoc()['office_id'] ?? 0);
            $stmtEmp->close();
        }
    }
    if ($staff_office_id > 0) {
        $office_id = $staff_office_id;
    }
}

// 3. Resolve Office & Window names
$office_name  = "All Campus Offices";
$counter_name = "All Windows";
if ($db_connect && $office_id > 0) {
    $stmtOffice = $db_connect->prepare("SELECT office_name, office_code FROM office WHERE office_id = ? LIMIT 1");
    if ($stmtOffice) {
        $stmtOffice->bind_param("i", $office_id);
        $stmtOffice->execute();
        $resOffice = $stmtOffice->get_result()->fetch_assoc();
        if ($resOffice) {
            $office_name = $resOffice['office_name'] . " (" . $resOffice['office_code'] . ")";
        }
        $stmtOffice->close();
    }
}
if ($db_connect && $counter_id > 0) {
    $stmtC = $db_connect->prepare("SELECT counter_name FROM counters WHERE id = ? LIMIT 1");
    if ($stmtC) {
        $stmtC->bind_param("i", $counter_id);
        $stmtC->execute();
        $counter_name = $stmtC->get_result()->fetch_assoc()['counter_name'] ?? $counter_name;
        $stmtC->close();
    }
}

$prepared_by_role = ucwords(strtolower(str_replace('_', ' ', $g_user_role_upper ?: 'Staff')));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics_Report_<?= preg_replace('/[^A-Za-z0-9_\-]/', '_', $office_name); ?>_<?= htmlspecialchars($date_from); ?></title>

    <!-- Bootstrap & Icons -->
    <link rel="stylesheet" href="<?= BASE_URL; ?>assets/vendor/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="<?= BASE_URL; ?>assets/vendor/bootstrap-icons/bootstrap-icons.css">

    <style>
        :root {
            --primary-color: #1e3a8a;
            --text-dark: #0f172a;
            --border-color: #cbd5e1;
        }

        body {
            font-family: 'Public Sans', system-ui, -apple-system, sans-serif;
            background-color: #ffffff;
            color: var(--text-dark);
            padding: 24px;
            font-size: 13px;
            line-height: 1.5;
        }

        /* Action bar for screen view */
        .action-bar {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 10px 16px;
            border-radius: 8px;
            margin-bottom: 24px;
        }

        /* Official Document Header */
        .report-header {
            border-bottom: 2.5px solid var(--primary-color);
            padding-bottom: 16px;
            margin-bottom: 24px;
        }

        .report-title {
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 0.5px;
            color: var(--primary-color);
            text-transform: uppercase;
        }

        .meta-pill {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 8px 14px;
            font-size: 12px;
        }

        /* Metric KPI Boxes */
        .kpi-box {
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 12px;
            text-align: center;
            background: #ffffff;
        }

        .kpi-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 0.5px;
        }

        .kpi-value {
            font-size: 22px;
            font-weight: 800;
            margin-top: 4px;
            font-family: monospace;
        }

        /* Tables Styling */
        .report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        .report-table th {
            background-color: #f8fafc !important;
            color: #334155;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
            border: 1px solid var(--border-color);
            padding: 8px 12px;
        }

        .report-table td {
            border: 1px solid var(--border-color);
            padding: 8px 12px;
        }

        .empty-row-cell {
            background-color: #f8fafc;
            color: #64748b;
            font-style: italic;
            text-align: center;
            padding: 14px 12px !important;
        }

        .section-heading {
            font-size: 14px;
            font-weight: 700;
            color: var(--primary-color);
            border-bottom: 1.5px solid #e2e8f0;
            padding-bottom: 6px;
            margin-top: 24px;
            margin-bottom: 12px;
        }

        /* Signature block */
        .signature-block {
            margin-top: 48px;
            page-break-inside: avoid;
        }

        .signature-line {
            border-bottom: 1px solid #000;
            width: 220px;
            display: inline-block;
            margin-top: 40px;
        }

        /* PRINT MEDIA OVERRIDES */
        @media print {
            @page {
                size: A4 portrait;
                margin: 12mm 10mm;
            }

            body {
                padding: 0 !important;
                background: #ffffff !important;
                font-size: 12px !important;
            }

            .no-print {
                display: none !important;
            }

            .card, .kpi-box, .report-table {
                page-break-inside: avoid;
            }

            .report-header {
                border-bottom-color: #000 !important;
            }

            .section-heading {
                color: #000 !important;
            }
            thead { display: table-header-group; }
            tr { page-break-inside: avoid; }
        }
    </style>
</head>
<body>

    <!-- SCREEN-ONLY ACTION BAR -->
    <div class="d-flex justify-content-between align-items-center action-bar no-print" id="previewActionBar">
        <a href="javascript:window.close();" class="btn btn-outline-secondary btn-sm fw-semibold">
            &larr; Close Preview
        </a>
        <div class="d-flex gap-2">
            <button onclick="window.print();" class="btn btn-primary btn-sm fw-bold">
                <i class="bi bi-printer-fill me-1"></i>Print / Save as PDF
            </button>
        </div>
    </div>

    <!-- INLINE FETCH ERROR ALERT -->
    <div id="reportErrorBanner" class="alert alert-danger d-none mb-3" role="alert"></div>

    <!-- OFFICIAL REPORT HEADER -->
    <div class="report-header text-center">
        <h5 class="fw-bold mb-1 text-uppercase">City College of Calamba</h5>
        <h6 class="text-muted mb-2">Management Information Systems Division (MISD)</h6>
        <div class="report-title my-2">Queue Management & Analytics Comprehensive Report</div>

        <div class="meta-pill d-inline-block mt-2">
            <strong>Office:</strong> <?= htmlspecialchars($office_name); ?> &nbsp;|&nbsp;
            <strong>Window:</strong> <?= htmlspecialchars($counter_name); ?> &nbsp;|&nbsp;
            <strong>Date Range:</strong> <?= htmlspecialchars($date_from); ?> to <?= htmlspecialchars($date_to); ?> &nbsp;|&nbsp;
            <strong>Visitor Type:</strong> <?= $visitor_type ? htmlspecialchars(ucfirst(strtolower($visitor_type))) : 'All Demographics'; ?>
        </div>
    </div>

    <!-- I. KPIs -->
    <div class="section-heading">I. Key Performance Indicators (KPIs)</div>
    <div class="row g-3 mb-2">
        <?php
        $kpis = [
            ['kpiTotal', 'Total Tickets', 'text-dark', '0'],
            ['kpiCompleted', 'Completed', 'text-success', '0'],
            ['kpiSkipped', 'Skipped / Missed', 'text-danger', '0'],
            ['kpiPriority', 'Priority Tickets', 'text-warning', '0'],
            ['kpiWait', 'Avg. Waiting Time', 'text-primary', '0.0m'],
            ['kpiService', 'Avg. Service Time', 'text-primary', '0.0m'],
        ];
        foreach ($kpis as [$id, $label, $cls, $def]): ?>
            <div class="col-4">
                <div class="kpi-box">
                    <div class="kpi-title"><?= $label; ?></div>
                    <div class="kpi-value <?= $cls; ?>" id="<?= $id; ?>"><?= $def; ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <p class="small text-muted mb-0">Average calling time (called to serving): <strong id="kpiCall">0.0m</strong></p>

    <!-- II. DEMOGRAPHICS & STATUS -->
    <div class="row g-4 mb-3">
        <div class="col-6">
            <div class="section-heading">II. Visitor Demographics</div>
            <table class="report-table" id="visitorDistributionTable">
                <thead><tr><th>Visitor Category</th><th class="text-center">Ticket Count</th></tr></thead>
                <tbody><tr><td colspan="2" class="empty-row-cell">Loading visitor demographics...</td></tr></tbody>
            </table>
        </div>
        <div class="col-6">
            <div class="section-heading">III. Queue Status Summary</div>
            <table class="report-table" id="statusTable">
                <thead><tr><th>Status</th><th class="text-center">Ticket Count</th></tr></thead>
                <tbody><tr><td colspan="2" class="empty-row-cell">Loading queue status summary...</td></tr></tbody>
            </table>
        </div>
    </div>

    <!-- IV. TOP TRANSACTIONS -->
    <div class="section-heading">IV. Top Transactions</div>
    <table class="report-table mb-3" id="topConcernsTable">
        <thead><tr><th>Transaction / Service Type</th><th class="text-center" style="width: 140px;">Requests</th></tr></thead>
        <tbody><tr><td colspan="2" class="empty-row-cell">Loading top transactions...</td></tr></tbody>
    </table>

    <!-- V. WINDOW PERFORMANCE -->
    <div class="section-heading">V. Window Performance</div>
    <table class="report-table mb-3" id="counterTable">
        <thead>
            <tr>
                <th>Window</th>
                <th class="text-center" style="width: 90px;">Tickets</th>
                <th class="text-center" style="width: 100px;">Completed</th>
                <th class="text-center" style="width: 90px;">Skipped</th>
                <th class="text-center" style="width: 120px;">Avg. Service</th>
            </tr>
        </thead>
        <tbody><tr><td colspan="5" class="empty-row-cell">Loading window performance...</td></tr></tbody>
    </table>

    <!-- VI. STAFF PERFORMANCE -->
    <div class="section-heading">VI. Staff Performance Breakdown</div>
    <table class="report-table align-middle mb-4" id="pdfStaffTable">
        <thead>
            <tr>
                <th>Staff Name</th>
                <th>Window(s)</th>
                <th class="text-center" style="width: 90px;">Served</th>
                <th class="text-center" style="width: 100px;">Completed</th>
                <th class="text-center" style="width: 110px;">Avg. Service</th>
                <th class="text-center" style="width: 110px;">Completion</th>
            </tr>
        </thead>
        <tbody><tr><td colspan="6" class="empty-row-cell">Loading staff activity data...</td></tr></tbody>
    </table>

    <!-- SIGNATURE BLOCK -->
    <div class="signature-block">
        <div class="row">
            <div class="col-6">
                <p class="small text-muted mb-0">Prepared By:</p>
                <div class="signature-line"></div>
                <p class="fw-bold mb-0 mt-1"><?= htmlspecialchars($generated_by_name); ?></p>
                <p class="small text-muted mb-0"><?= htmlspecialchars($prepared_by_role); ?></p>
            </div>
            <div class="col-6 text-end">
                <p class="small text-muted mb-0">Report Generated On:</p>
                <p class="fw-bold mb-0 mt-1"><?= date('F d, Y - h:i A'); ?></p>
                <p class="small text-muted mb-0">Eskuelog Analytics System</p>
            </div>
        </div>
    </div>

    <script src="<?= BASE_MAIN_ASSETS_PATH; ?>js/jquery.min.js"></script>
    <script>
        $(document).ready(function() {
            if (window.self !== window.top) {
                $('#previewActionBar').hide();
            }

            const esc = (s) => String(s ?? '')
                .replace(/&/g, "&amp;").replace(/</g, "&lt;")
                .replace(/>/g, "&gt;").replace(/"/g, "&quot;");
            const fmtMin = (v) => `${(parseFloat(v) || 0).toFixed(1)}m`;

            // Fill a table body from rows with a custom empty fallback message
            function fillTable(selector, rows, colspan, emptyMessage, renderRow) {
                const $tbody = $(`${selector} tbody`).empty();
                if (!rows || rows.length === 0) {
                    $tbody.html(`<tr><td colspan="${colspan}" class="empty-row-cell">${esc(emptyMessage)}</td></tr>`);
                    return;
                }
                rows.forEach(r => $tbody.append(`<tr>${renderRow(r)}</tr>`));
            }

            function renderAllEmpty(messagePrefix = 'No records found') {
                fillTable('#visitorDistributionTable', [], 2, `${messagePrefix} for visitor demographics.`, () => '');
                fillTable('#statusTable', [], 2, `${messagePrefix} for completed or skipped tickets.`, () => '');
                fillTable('#topConcernsTable', [], 2, `${messagePrefix} for transactions.`, () => '');
                fillTable('#counterTable', [], 5, `${messagePrefix} for window activity.`, () => '');
                fillTable('#pdfStaffTable', [], 6, `${messagePrefix} for staff performance.`, () => '');
            }

            $.ajax({
                url: '<?= BASE_URL; ?>admin/ajax/reports-analytics',
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'fetch_analytics',
                    office_id: <?= json_encode($office_id); ?>,
                    counter_id: <?= json_encode($counter_id); ?>,
                    visitor_type: <?= json_encode($visitor_type); ?>,
                    date_from: <?= json_encode($date_from); ?>,
                    date_to: <?= json_encode($date_to); ?>
                },
                success: function(res) {
                    if (!(res.success && res.data)) {
                        $('#reportErrorBanner')
                            .removeClass('d-none')
                            .text('Unable to load report data: ' + (res.message || 'Unknown error.'));
                        renderAllEmpty('No data available');
                        return;
                    }

                    const d = res.data;
                    const k = d.kpi || {};

                    $('#kpiTotal').text(k.total_tickets || 0);
                    $('#kpiCompleted').text(k.completed_tickets || 0);
                    $('#kpiSkipped').text(k.skipped_tickets || 0);
                    $('#kpiPriority').text(k.priority_tickets || 0);
                    $('#kpiWait').text(fmtMin(k.avg_wait_minutes));
                    $('#kpiService').text(fmtMin(k.avg_service_minutes));
                    $('#kpiCall').text(fmtMin(k.avg_call_minutes));

                    const visitorRows = (d.visitor_distribution || []).filter(r => (parseInt(r.total, 10) || 0) > 0);
                    fillTable('#visitorDistributionTable', visitorRows, 2, 'No visitor records found for the selected filters.', r =>
                        `<td class="fw-semibold">${esc(r.type)}</td><td class="text-center font-monospace">${parseInt(r.total || 0, 10)}</td>`);

                    const allowedStatuses = ['COMPLETED', 'SKIPPED'];
                    const statusRows = (d.status_distribution || []).filter(r =>
                        allowedStatuses.includes(String(r.status || '').toUpperCase()) && (parseInt(r.total, 10) || 0) > 0
                    );
                    fillTable('#statusTable', statusRows, 2, 'No completed or skipped tickets recorded.', r =>
                        `<td class="fw-semibold">${esc(r.status.charAt(0) + r.status.slice(1).toLowerCase())}</td><td class="text-center font-monospace">${parseInt(r.total || 0, 10)}</td>`);

                    const concernRows = (d.top_concerns || []).filter(r => (parseInt(r.total, 10) || 0) > 0);
                    fillTable('#topConcernsTable', concernRows, 2, 'No transaction requests logged for the selected filters.', r =>
                        `<td class="fw-semibold">${esc(r.concern)}</td><td class="text-center font-monospace">${parseInt(r.total || 0, 10)}</td>`);

                    fillTable('#counterTable', d.counter_breakdown, 5, 'No window activity recorded for the selected filters.', r =>
                        `<td class="fw-semibold">${esc(r.counter_label)}</td>
                         <td class="text-center font-monospace">${parseInt(r.total_tickets || 0, 10)}</td>
                         <td class="text-center font-monospace">${parseInt(r.total_completed || 0, 10)}</td>
                         <td class="text-center font-monospace">${parseInt(r.total_skipped || 0, 10)}</td>
                         <td class="text-center font-monospace">${fmtMin(r.avg_service_minutes)}</td>`);

                    fillTable('#pdfStaffTable', d.staff_breakdown, 6, 'No staff activity recorded for the selected filters.', r => {
                        const served = parseInt(r.total_served || 0, 10);
                        const completed = parseInt(r.total_completed || 0, 10);
                        const rate = served > 0 ? Math.round((completed / served) * 100) : 0;
                        const windowLabel = r.window_label || r.windows || '---';
                        return `<td class="fw-semibold">${esc(r.staff_name || 'Unassigned')}</td>
                                <td>${esc(windowLabel)}</td>
                                <td class="text-center font-monospace">${served}</td>
                                <td class="text-center font-monospace">${completed}</td>
                                <td class="text-center font-monospace">${fmtMin(r.avg_service_minutes)}</td>
                                <td class="text-center font-monospace fw-bold ${rate >= 80 ? 'text-success' : 'text-primary'}">${rate}%</td>`;
                    });

                    setTimeout(() => { window.focus(); window.print(); }, 400);
                },
                error: function(xhr, status, error) {
                    console.error("AJAX Report Fetch Error:", error);
                    $('#reportErrorBanner')
                        .removeClass('d-none')
                        .text('Failed to fetch report data from the server. Please try again.');
                    renderAllEmpty('Unable to load data');
                }
            });
        });
    </script>
</body>
</html>