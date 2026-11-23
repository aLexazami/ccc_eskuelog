<?php
// src/Views/Admin/transaction_records.php

# Core Config Dependencies
global $session_class, $db_connect;
$db_connect = $db_connect ?? null;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

# Page Helpers
require_once ADMIN_PAGE_HEADER_PATH;

# Page Header Initialization
$title_page   = "Transaction Records";
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
    // Priority 1: Check staff_assignments table
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

    // Priority 2: Fallback check on employee table
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
            overflow: hidden;
        }

        .search-input-group {
            position: relative;
        }
        .search-input-group .search-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            pointer-events: none;
            z-index: 4;
            font-size: 0.875rem;
        }
        .search-input-group .form-control {
            padding-left: 2.15rem !important;
            padding-top: 0.42rem;
            padding-bottom: 0.42rem;
            min-height: 36px;
        }

        /* Queue Status Badge Styles */
        .badge-status-completed { background-color: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .badge-status-skipped   { background-color: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .badge-status-serving   { background-color: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }
        .badge-status-called    { background-color: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
        .badge-status-waiting   { background-color: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }

        /* Clean Flat Tabulator Styling (Preserves Tabulator row/cell layout) */
        #transactionRecordsTable.tabulator {
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            background-color: #ffffff;
            box-shadow: none !important;
        }
        #transactionRecordsTable .tabulator-header,
        #transactionRecordsTable .tabulator-header .tabulator-col {
            background-color: #ffffff !important;
            border-bottom: 1px solid #e2e8f0;
            box-shadow: none !important;
            font-weight: 600;
            color: #475569;
        }
        #transactionRecordsTable .tabulator-header-filter input,
        #transactionRecordsTable .tabulator-header-filter select {
            height: 32px !important;
            min-height: 32px !important;
            padding: 0.25rem 0.5rem !important;
            font-size: 0.8125rem !important;
            box-sizing: border-box;
        }
        #transactionRecordsTable .tabulator-row,
        #transactionRecordsTable .tabulator-row.tabulator-row-even,
        #transactionRecordsTable .tabulator-row.tabulator-row-odd,
        #transactionRecordsTable .tabulator-row:hover {
            background-color: #ffffff !important;
            box-shadow: none !important;
        }
        #transactionRecordsTable .tabulator-cell {
            background-color: #ffffff !important;
            border-right: 1px solid #f1f5f9;
            white-space: normal;
        }
        #transactionRecordsTable .tabulator-cell:last-child {
            border-right: none;
        }
        #transactionRecordsTable .tabulator-footer {
            background-color: #ffffff !important;
            box-shadow: none !important;
            border-top: 1px solid #e2e8f0;
        }
        .tabulator-responsive-collapse {
            padding: 0.75rem 1rem !important;
            background-color: #ffffff !important;
            border-bottom: 1px solid #e2e8f0 !important;
            box-shadow: none !important;
        }
        .tabulator-responsive-collapse table td {
            padding: 0.35rem 0.75rem;
        }

        /* Clean Table Concerns List (Flat, no card box or shadow inside table column) */
        .table-concern-entry {
            padding: 0.25rem 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .table-concern-entry:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        .table-concern-entry:first-child {
            padding-top: 0;
        }

        /* Modern Modal Polish */
        .modal-ticket-hero {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%);
            border-radius: 0.85rem;
            color: #ffffff;
        }

        /* Detail Tile Styling (Modal Only) */
        .detail-tile {
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            transition: all 0.2s ease;
            height: 100%;
            overflow: hidden;
        }

        .detail-tile:hover {
            background: #ffffff;
            border-color: #cbd5e1;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
        }

        .detail-tile .tile-icon {
            width: 42px;
            height: 42px;
            min-width: 42px;
            border-radius: 0.65rem;
            background: #eff6ff;
            color: #2563eb;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.85rem;
            margin-top: 2px;
        }

        /* RECEIPT CONCERN STYLES (MODAL ONLY) */
        .receipt-concern-item {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-left: 3.5px solid #1d4ed8;
            border-radius: 0.5rem;
            padding: 0.55rem 0.85rem;
            margin-bottom: 0.4rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
        }

        /* QUEUE LIFECYCLE TIMESTAMPS CARD (MODAL ONLY) */
        .lifecycle-card {
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            border-radius: 0.75rem;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03);
        }

        .lifecycle-toolbar {
            background: #f8fafc;
            border-bottom: 1.5px solid #e2e8f0;
            padding: 0.65rem 1rem;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .lifecycle-table {
            width: 100%;
            margin-bottom: 0;
            border-collapse: collapse;
            font-size: 0.84rem;
        }

        .lifecycle-table th,
        .lifecycle-table td {
            padding: 0.6rem 0.9rem;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }

        .lifecycle-table tr:last-child td {
            border-bottom: none;
        }

        .lifecycle-stage-header td,
        .lifecycle-summary-header td {
            background: #eff6ff;
            color: #1e40af;
            font-weight: 800;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 0.48rem 0.9rem;
            text-align: center;
        }

        .lifecycle-summary-header td {
            border-top: 1.5px solid #cbd5e1;
        }

        .lifecycle-sub-header th {
            background: #f8fafc;
            color: #64748b;
            font-weight: 700;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            padding: 0.4rem 0.9rem;
            width: 33.33%;
        }

        .lifecycle-total-row td {
            background: #eff6ff;
            color: #1e3a8a;
            font-weight: 800;
            border-top: 1.5px solid #bfdbfe;
        }

        .lifecycle-status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.25rem 0.75rem;
            border-radius: 50rem;
            font-size: 0.75rem;
            font-weight: 800;
            letter-spacing: 0.03em;
            line-height: 1.2;
            border: 1.5px solid transparent;
            vertical-align: middle;
        }

        .lc-status-completed {
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            color: #065f46;
            border-color: #34d399;
        }

        .lc-status-calling {
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
            color: #92400e;
            border-color: #fbbf24;
        }

        .lc-status-skipped {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            color: #991b1b;
            border-color: #f87171;
        }

        .lc-status-serving {
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            color: #1e40af;
            border-color: #60a5fa;
        }

        .lc-status-waiting {
            background: #f1f5f9;
            color: #334155;
            border-color: #cbd5e1;
        }

        .lc-status-cancelled {
            background: #f3f4f6;
            color: #4b5563;
            border-color: #d1d5db;
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
                            <form id="recordsFilterForm">
                                <div class="row g-3 align-items-end">
                                    
                                    <!-- OFFICE SELECTION -->
                                    <?php $lock_office = ($g_user_role_upper === 'ADMIN_STAFF' && !empty($assigned_office)); ?>
                                    <div class="col-12 col-sm-6 col-lg-6 col-xl-3">
                                        <label for="filterOfficeId" class="form-label fw-bold small text-muted mb-1">
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

                                    <!-- VISITOR TYPE -->
                                    <div class="col-6 col-sm-6 col-lg-3 col-xl-2">
                                        <label for="filterVisitorType" class="form-label fw-bold small text-muted mb-1">Visitor Type</label>
                                        <select id="filterVisitorType" class="form-select form-select-sm">
                                            <option value="">All Types</option>
                                            <option value="STUDENT">Student</option>
                                            <option value="EMPLOYEE">Employee</option>
                                            <option value="GUEST">Guest</option>
                                        </select>
                                    </div>

                                    <!-- STATUS -->
                                    <div class="col-6 col-sm-6 col-lg-3 col-xl-2">
                                        <label for="filterStatus" class="form-label fw-bold small text-muted mb-1">Queue Status</label>
                                        <select id="filterStatus" class="form-select form-select-sm">
                                            <option value="">All Statuses</option>
                                            <option value="COMPLETED">Completed</option>
                                            <option value="SKIPPED">Skipped</option>
                                            <option value="SERVING">Serving</option>
                                            <option value="CALLED">Called</option>
                                            <option value="WAITING">Waiting</option>
                                        </select>
                                    </div>

                                    <!-- DATE FROM -->
                                    <div class="col-6 col-sm-6 col-lg-3 col-xl-2">
                                        <label for="filterDateFrom" class="form-label fw-bold small text-muted mb-1">Date From</label>
                                        <input type="date" id="filterDateFrom" class="form-control form-control-sm" value="<?= date('Y-m-d'); ?>">
                                    </div>

                                    <!-- DATE TO -->
                                    <div class="col-6 col-sm-6 col-lg-3 col-xl-2">
                                        <label for="filterDateTo" class="form-label fw-bold small text-muted mb-1">Date To</label>
                                        <input type="date" id="filterDateTo" class="form-control form-control-sm" value="<?= date('Y-m-d'); ?>">
                                    </div>

                                    <!-- ACTION BUTTONS -->
                                    <div class="col-12 col-sm-12 col-lg-6 col-xl-1">
                                        <div class="d-flex flex-row flex-xl-column gap-1">
                                            <button type="submit" class="btn btn-primary btn-sm fw-bold px-2 py-1 text-nowrap flex-grow-1 flex-xl-grow-0">
                                                <i class="bi bi-filter me-1"></i>Apply
                                            </button>
                                            <button type="button" id="btnResetFilters" class="btn btn-outline-secondary btn-sm px-2 py-1 text-nowrap flex-grow-1 flex-xl-grow-0" title="Reset Filters">
                                                <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                                            </button>
                                        </div>
                                    </div>

                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- TABULATOR TABLE CONTAINER CARD -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="bg-primary-subtle text-primary rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                                            <i class="bi bi-receipt-cutoff fs-4"></i>
                                        </div>
                                        <div>
                                            <div class="text-uppercase text-muted fw-bold tracking-wide small" style="font-size: 0.95rem;">
                                                Transaction Records
                                            </div>
                                            <div class="d-flex align-items-center text-muted small mt-0.5" style="font-size: 0.75rem;">
                                                <i class="bi bi-info-circle me-1"></i> View and audit visitor queue tickets, service concerns, and lifecycle timestamps
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="card-body">
                                    <!-- Unified Top Toolbar: Global Search + Export/Download + Refresh Data -->
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                                        <!-- Global Search -->
                                        <div class="flex-grow-1" style="min-width: 240px; max-width: 420px;">
                                            <div class="search-input-group">
                                                <i class="bi bi-search search-icon"></i>
                                                <input type="text" id="global-search" class="form-control form-control-sm" placeholder="Search" aria-label="Search">
                                            </div>
                                        </div>

                                        <!-- Export & Refresh Actions -->
                                        <div class="d-flex flex-wrap align-items-center gap-2 ms-auto">
                                            <div class="dropdown">
                                                <button class="btn btn-action btn-sm dropdown-toggle d-inline-flex align-items-center" type="button" id="exportDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                                    <i class="bi bi-download me-1"></i>Export / Download
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="exportDropdown">
                                                    <li><a class="dropdown-item" href="javascript:void(0)" id="export-csv"><i class="bi bi-filetype-csv text-success me-2"></i> Export CSV</a></li>
                                                    <li><a class="dropdown-item" href="javascript:void(0)" id="export-excel"><i class="bi bi-file-earmark-excel text-success me-2"></i> Export Excel (.xlsx)</a></li>
                                                    <li><a class="dropdown-item" href="javascript:void(0)" id="export-json"><i class="bi bi-filetype-json text-warning me-2"></i> Export JSON</a></li>
                                                </ul>
                                            </div>

                                            <button type="button" id="btn-refresh" class="btn btn-action btn-sm d-inline-flex align-items-center">
                                                <i class="bi bi-arrow-clockwise me-1"></i>Refresh Data
                                            </button>
                                        </div>
                                    </div>

                                    <div id="transactionRecordsTable"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <?php include_once ADMIN_FOOTER_PATH; ?>
        </div>
    </div>

    <!-- MODERN TICKET DETAILS MODAL -->
    <div class="modal fade" id="ticketDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 1.25rem; overflow: hidden;">
                <div class="modal-header bg-dark text-white px-4 py-3">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-ticket-detailed fs-4 text-primary"></i>
                        <h5 class="modal-title fw-bold mb-0" id="modalTicketTitle">Transaction Details</h5>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4" id="modalTicketBody">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 px-4 py-3">
                    <button type="button" class="btn btn-secondary btn-sm px-4 fw-semibold rounded-pill" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <?php include_once ADMIN_SCRIPT_PATH; ?>

<script>
    (function() {
        const HANDLER_URL = '<?= BASE_URL; ?>admin/ajax/transaction-records';
        let recordsTable = null;

        // Live Lifecycle Table State
        let modalLifecycleInterval = null;
        let activeModalTicketRef = null;
        let selectedDurationFormat = 'B'; // Default: B. X hr Y min Z sec

        const DURATION_FORMAT_LABELS = {
            'A': 'HH:MM:SS',
            'B': 'X hr Y min Z sec',
            'C': 'Xh Ym Zs',
            'D': 'Full Words'
        };

        const getTodayDate = () => {
            const today = new Date();
            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0');
            const day = String(today.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        };

        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function formatCapitalized(str) {
            if (!str) return 'N/A';
            return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();
        }

        function parseSqlTimestamp(ts) {
            if (!ts) return null;
            const d = new Date(String(ts).replace(' ', 'T'));
            return isNaN(d.getTime()) ? null : d;
        }

        function formatDateAndTimeParts(ts) {
            const d = parseSqlTimestamp(ts);
            if (!d) return { date: '', time: '', full: '' };
            const yyyy = d.getFullYear();
            const mm = String(d.getMonth() + 1).padStart(2, '0');
            const dd = String(d.getDate()).padStart(2, '0');
            const dateStr = `${yyyy}-${mm}-${dd}`;
            const timeStr = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            return {
                date: dateStr,
                time: timeStr,
                full: `${dateStr} ${timeStr}`.trim()
            };
        }

        // Displays Hour, Minute, and Second in 12-hour format (e.g., 5:00:00 PM)
        function formatClockTime(dateObj) {
            if (!dateObj) return '---';
            return dateObj.toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            });
        }

        function formatLifecycleDuration(totalSeconds, formatKey = 'B') {
            if (totalSeconds === null || totalSeconds === undefined || isNaN(totalSeconds)) {
                return '---';
            }
            const secs = Math.max(0, Math.floor(totalSeconds));
            const h = Math.floor(secs / 3600);
            const m = Math.floor((secs % 3600) / 60);
            const s = secs % 60;

            switch (formatKey) {
                case 'A': {
                    const hh = String(h).padStart(2, '0');
                    const mm = String(m).padStart(2, '0');
                    const ss = String(s).padStart(2, '0');
                    return `${hh}:${mm}:${ss}`;
                }
                case 'C': {
                    return h > 0 ? `${h}h ${m}m ${s}s` : `${m}m ${s}s`;
                }
                case 'D': {
                    const parts = [];
                    if (h > 0) parts.push(`${h} ${h === 1 ? 'hour' : 'hours'}`);
                    parts.push(`${m} ${m === 1 ? 'minute' : 'minutes'}`);
                    parts.push(`and ${s} ${s === 1 ? 'second' : 'seconds'}`);
                    return parts.join(', ');
                }
                case 'B':
                default: {
                    return h > 0 ? `${h} hr ${m} min ${s} sec` : `${m} min ${s} sec`;
                }
            }
        }

        function computeQueueLifecycleMetrics(ticket) {
            const now = new Date();
            const status = (ticket.status || 'WAITING').toUpperCase();

            const entryDate = parseSqlTimestamp(ticket.queue_entry_time || ticket.created_at);
            const calledDate = parseSqlTimestamp(ticket.called_at);
            const servedDate = parseSqlTimestamp(ticket.served_at);
            const completedDate = parseSqlTimestamp(ticket.completed_at);

            // 1. Waiting Time (Entry -> Called)
            let waitStart = formatClockTime(entryDate);
            let waitEnd = '---';
            let waitSecs = null;
            let waitLive = false;

            if (entryDate) {
                if (calledDate) {
                    waitEnd = formatClockTime(calledDate);
                    waitSecs = Math.floor((calledDate - entryDate) / 1000);
                } else if (completedDate) {
                    waitEnd = formatClockTime(completedDate);
                    waitSecs = Math.floor((completedDate - entryDate) / 1000);
                } else if (status === 'WAITING') {
                    waitEnd = 'Ongoing';
                    waitSecs = Math.floor((now - entryDate) / 1000);
                    waitLive = true;
                }
            }

            // 2. Calling Time (Called -> Served, or Completed if skipped while called)
            let callStart = formatClockTime(calledDate);
            let callEnd = '---';
            let callSecs = null;
            let callLive = false;

            if (calledDate) {
                if (servedDate) {
                    callEnd = formatClockTime(servedDate);
                    callSecs = Math.floor((servedDate - calledDate) / 1000);
                } else if (completedDate) {
                    callEnd = formatClockTime(completedDate);
                    callSecs = Math.floor((completedDate - calledDate) / 1000);
                } else if (status === 'CALLED') {
                    callEnd = 'Ongoing';
                    callSecs = Math.floor((now - calledDate) / 1000);
                    callLive = true;
                }
            }

            // 3. Processing Time (Served -> Completed)
            let procStart = formatClockTime(servedDate);
            let procEnd = '---';
            let procSecs = null;
            let procLive = false;

            if (servedDate) {
                if (completedDate) {
                    procEnd = formatClockTime(completedDate);
                    procSecs = Math.floor((completedDate - servedDate) / 1000);
                } else if (status === 'SERVING') {
                    procEnd = 'Ongoing';
                    procSecs = Math.floor((now - servedDate) / 1000);
                    procLive = true;
                }
            }

            const totalSecs = (waitSecs || 0) + (callSecs || 0) + (procSecs || 0);
            const hasAnyTime = (waitSecs !== null || callSecs !== null || procSecs !== null);

            return {
                status,
                wait: { start: waitStart, end: waitEnd, secs: waitSecs, live: waitLive },
                call: { start: callStart, end: callEnd, secs: callSecs, live: callLive },
                proc: { start: procStart, end: procEnd, secs: procSecs, live: procLive },
                totalSecs: hasAnyTime ? totalSecs : null,
                isAnyLive: waitLive || callLive || procLive
            };
        }

        function refreshTableLifecycleDisplay() {
            if (!recordsTable) return;
            const rows = recordsTable.getRows();
            rows.forEach(row => {
                const data = row.getData();
                const m = computeQueueLifecycleMetrics(data);
                row.update({
                    waiting_time_text: formatLifecycleDuration(m.wait.secs, selectedDurationFormat),
                    calling_time_text: formatLifecycleDuration(m.call.secs, selectedDurationFormat),
                    processing_time_text: formatLifecycleDuration(m.proc.secs, selectedDurationFormat)
                });
            });
        }

        function buildLifecycleTableHtml(ticket) {
            const m = computeQueueLifecycleMetrics(ticket);
            const fmt = selectedDurationFormat;

            const formatStatusBadge = (st) => {
                const config = {
                    'WAITING':   { cls: 'lc-status-waiting',   label: 'Waiting' },
                    'CALLED':    { cls: 'lc-status-calling',   label: 'Calling' },
                    'SERVING':   { cls: 'lc-status-serving',   label: 'Serving' },
                    'COMPLETED': { cls: 'lc-status-completed', label: 'Completed' },
                    'SKIPPED':   { cls: 'lc-status-skipped',   label: 'Skipped' },
                    'CANCELLED': { cls: 'lc-status-cancelled', label: 'Cancelled' }
                };
                const item = config[st] || {
                    cls: 'lc-status-waiting',
                    label: st.charAt(0) + st.slice(1).toLowerCase()
                };
                return `
                    <span class="lifecycle-status-badge ${item.cls}">
                        ${escapeHtml(item.label)}
                    </span>
                `;
            };

            return `
                <div class="lifecycle-card">
                    <div class="lifecycle-toolbar">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-primary dropdown-toggle fw-semibold d-inline-flex align-items-center gap-1" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="font-size: 0.76rem; border-radius: 0.5rem;">
                                <i class="bi bi-sliders"></i>
                                <span>Time Format: ${escapeHtml(DURATION_FORMAT_LABELS[fmt])}</span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size: 0.82rem;">
                                <li><a class="dropdown-item ${fmt === 'A' ? 'active' : ''}" href="javascript:void(0)" onclick="setLifecycleTimeFormat('A')">A. HH:MM:SS <small class="opacity-75">(e.g., 01:05:30)</small></a></li>
                                <li><a class="dropdown-item ${fmt === 'B' ? 'active' : ''}" href="javascript:void(0)" onclick="setLifecycleTimeFormat('B')">B. X hr Y min Z sec <small class="opacity-75">(Default)</small></a></li>
                                <li><a class="dropdown-item ${fmt === 'C' ? 'active' : ''}" href="javascript:void(0)" onclick="setLifecycleTimeFormat('C')">C. Xh Ym Zs <small class="opacity-75">(e.g., 2h 45m 45s)</small></a></li>
                                <li><a class="dropdown-item ${fmt === 'D' ? 'active' : ''}" href="javascript:void(0)" onclick="setLifecycleTimeFormat('D')">D. X hours, Y minutes, and Z seconds</a></li>
                            </ul>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="lifecycle-table">
                            <tbody>
                                <!-- WAITING TIME -->
                                <tr class="lifecycle-stage-header">
                                    <td colspan="3">Waiting Time</td>
                                </tr>
                                <tr class="lifecycle-sub-header">
                                    <th>Start Time</th>
                                    <th>End Time</th>
                                    <th>Duration</th>
                                </tr>
                                <tr>
                                    <td class="fw-semibold text-dark">${escapeHtml(m.wait.start)}</td>
                                    <td class="fw-semibold ${m.wait.live ? 'text-primary' : 'text-dark'}">${escapeHtml(m.wait.end)}</td>
                                    <td class="fw-bold ${m.wait.live ? 'text-primary' : 'text-dark'}" id="lcWaitDuration">${escapeHtml(formatLifecycleDuration(m.wait.secs, fmt))}</td>
                                </tr>

                                <!-- CALLING TIME -->
                                <tr class="lifecycle-stage-header">
                                    <td colspan="3">Calling Time</td>
                                </tr>
                                <tr class="lifecycle-sub-header">
                                    <th>Start Time</th>
                                    <th>End Time</th>
                                    <th>Duration</th>
                                </tr>
                                <tr>
                                    <td class="fw-semibold text-dark">${escapeHtml(m.call.start)}</td>
                                    <td class="fw-semibold ${m.call.live ? 'text-primary' : 'text-dark'}">${escapeHtml(m.call.end)}</td>
                                    <td class="fw-bold ${m.call.live ? 'text-primary' : 'text-dark'}" id="lcCallDuration">${escapeHtml(formatLifecycleDuration(m.call.secs, fmt))}</td>
                                </tr>

                                <!-- PROCESSING TIME -->
                                <tr class="lifecycle-stage-header">
                                    <td colspan="3">Processing Time</td>
                                </tr>
                                <tr class="lifecycle-sub-header">
                                    <th>Start Time</th>
                                    <th>End Time</th>
                                    <th>Duration</th>
                                </tr>
                                <tr>
                                    <td class="fw-semibold text-dark">${escapeHtml(m.proc.start)}</td>
                                    <td class="fw-semibold ${m.proc.live ? 'text-primary' : 'text-dark'}">${escapeHtml(m.proc.end)}</td>
                                    <td class="fw-bold ${m.proc.live ? 'text-primary' : 'text-dark'}" id="lcProcDuration">${escapeHtml(formatLifecycleDuration(m.proc.secs, fmt))}</td>
                                </tr>

                                <!-- SUMMARY -->
                                <tr class="lifecycle-summary-header">
                                    <td colspan="3">Summary</td>
                                </tr>
                                <tr>
                                    <td colspan="2" class="text-muted fw-semibold">Waiting Time :</td>
                                    <td class="fw-bold ${m.wait.live ? 'text-primary' : 'text-dark'}" id="lcSumWait">${escapeHtml(formatLifecycleDuration(m.wait.secs, fmt))}</td>
                                </tr>
                                <tr>
                                    <td colspan="2" class="text-muted fw-semibold">Calling Time :</td>
                                    <td class="fw-bold ${m.call.live ? 'text-primary' : 'text-dark'}" id="lcSumCall">${escapeHtml(formatLifecycleDuration(m.call.secs, fmt))}</td>
                                </tr>
                                <tr>
                                    <td colspan="2" class="text-muted fw-semibold">Processing Time :</td>
                                    <td class="fw-bold ${m.proc.live ? 'text-primary' : 'text-dark'}" id="lcSumProc">${escapeHtml(formatLifecycleDuration(m.proc.secs, fmt))}</td>
                                </tr>
                                <tr class="lifecycle-total-row">
                                    <td colspan="2">Total Duration</td>
                                    <td class="${m.isAnyLive ? 'text-primary' : ''}" id="lcSumTotal">${escapeHtml(formatLifecycleDuration(m.totalSecs, fmt))}</td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="text-muted fw-semibold">
                                        <div class="d-inline-flex align-items-center gap-2">
                                            <span>Status:</span>
                                            ${formatStatusBadge(m.status)}
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        }

        function startModalLifecycleTicker() {
            stopModalLifecycleTicker();
            if (!activeModalTicketRef) return;

            const setLiveTextColor = (el, isLive) => {
                if (!el) return;
                el.classList.toggle('text-primary', isLive);
                el.classList.toggle('text-dark', !isLive);
            };

            const updateLiveCells = () => {
                if (!activeModalTicketRef) return;
                const m = computeQueueLifecycleMetrics(activeModalTicketRef);
                const fmt = selectedDurationFormat;

                const elWait = document.getElementById('lcWaitDuration');
                const elCall = document.getElementById('lcCallDuration');
                const elProc = document.getElementById('lcProcDuration');
                const elSumWait = document.getElementById('lcSumWait');
                const elSumCall = document.getElementById('lcSumCall');
                const elSumProc = document.getElementById('lcSumProc');
                const elSumTotal = document.getElementById('lcSumTotal');

                if (elWait) {
                    elWait.textContent = formatLifecycleDuration(m.wait.secs, fmt);
                    setLiveTextColor(elWait, m.wait.live);
                }
                if (elCall) {
                    elCall.textContent = formatLifecycleDuration(m.call.secs, fmt);
                    setLiveTextColor(elCall, m.call.live);
                }
                if (elProc) {
                    elProc.textContent = formatLifecycleDuration(m.proc.secs, fmt);
                    setLiveTextColor(elProc, m.proc.live);
                }
                if (elSumWait) {
                    elSumWait.textContent = formatLifecycleDuration(m.wait.secs, fmt);
                    setLiveTextColor(elSumWait, m.wait.live);
                }
                if (elSumCall) {
                    elSumCall.textContent = formatLifecycleDuration(m.call.secs, fmt);
                    setLiveTextColor(elSumCall, m.call.live);
                }
                if (elSumProc) {
                    elSumProc.textContent = formatLifecycleDuration(m.proc.secs, fmt);
                    setLiveTextColor(elSumProc, m.proc.live);
                }
                if (elSumTotal) {
                    elSumTotal.textContent = formatLifecycleDuration(m.totalSecs, fmt);
                    elSumTotal.classList.toggle('text-primary', m.isAnyLive);
                }
            };

            const initialMetrics = computeQueueLifecycleMetrics(activeModalTicketRef);
            if (initialMetrics.isAnyLive) {
                modalLifecycleInterval = setInterval(updateLiveCells, 1000);
            }
        }

        function stopModalLifecycleTicker() {
            if (modalLifecycleInterval) {
                clearInterval(modalLifecycleInterval);
                modalLifecycleInterval = null;
            }
        }

        window.setLifecycleTimeFormat = function(fmtKey) {
            selectedDurationFormat = fmtKey;
            const container = document.getElementById('queueLifecycleTableContainer');
            if (container && activeModalTicketRef) {
                container.innerHTML = buildLifecycleTableHtml(activeModalTicketRef);
                startModalLifecycleTicker();
            }
            refreshTableLifecycleDisplay();
        };

        document.getElementById('ticketDetailsModal')?.addEventListener('hidden.bs.modal', () => {
            stopModalLifecycleTicker();
            activeModalTicketRef = null;
        });

        // Render concerns cleanly: flat text for table cells, receipt card style for modal
        function buildLogbookStyleConcerns(ticket, isTableCell = false) {
            let html = '';
            const otherText = ticket.other_concern_text ? ticket.other_concern_text.trim() : '';

            const renderConcernCard = (mainTitle, subs = []) => {
                if (isTableCell) {
                    let subHtml = '';
                    if (Array.isArray(subs) && subs.length > 0) {
                        subHtml = subs.map(sub => `
                            <div class="text-muted small ps-2" style="line-height: 1.35;">
                                &bull; ${escapeHtml(sub)}
                            </div>
                        `).join('');
                    }
                    return `
                        <div class="table-concern-entry">
                            <div class="text-dark fw-semibold" style="font-size: 0.85rem; line-height: 1.35;">${escapeHtml(mainTitle)}</div>
                            ${subHtml}
                        </div>
                    `;
                }

                let subSectionHtml = '';
                if (Array.isArray(subs) && subs.length > 0) {
                    const subRows = subs.map(sub => `
                        <div class="text-dark fw-bold" style="font-size: 0.875rem; line-height: 1.35;">
                            ${escapeHtml(sub)}
                        </div>
                    `).join('');

                    subSectionHtml = `
                        <div class="mt-2 pt-2 border-top d-flex flex-column gap-1">
                            <div class="text-muted fw-bold" style="font-size: 0.875rem; line-height: 1.35;">Sub-Transaction</div>
                            ${subRows}
                        </div>
                    `;
                }

                return `
                    <div class="receipt-concern-item">
                        <div class="text-dark fw-bold" style="font-size: 0.875rem; line-height: 1.35;">${escapeHtml(mainTitle)}</div>
                        ${subSectionHtml}
                    </div>
                `;
            };

            if (Array.isArray(ticket.concerns_list) && ticket.concerns_list.length > 0) {
                let grouped = {};

                ticket.concerns_list.forEach(item => {
                    const main = (item.transaction_name || 'General Concern').trim();
                    const sub = item.sub_concern_name ? item.sub_concern_name.trim() : null;

                    if (main.toLowerCase().includes('other') && otherText) {
                        return;
                    }

                    if (!grouped[main]) {
                        grouped[main] = [];
                    }

                    if (sub && !grouped[main].includes(sub)) {
                        grouped[main].push(sub);
                    }
                });

                Object.entries(grouped).forEach(([mainTitle, subs]) => {
                    html += renderConcernCard(mainTitle, subs);
                });
            } else if (ticket.transaction_name) {
                const mainConcerns = ticket.transaction_name.split(',').map(s => s.trim()).filter(Boolean);
                const subConcerns = (ticket.sub_concern_name || '').split(',').map(s => s.trim()).filter(Boolean);

                mainConcerns.forEach((main, idx) => {
                    if (main.toLowerCase().includes('other') && otherText) return;
                    const subs = (idx === 0 && subConcerns.length > 0) ? subConcerns : [];
                    html += renderConcernCard(main, subs);
                });
            }

            if (otherText) {
                if (isTableCell) {
                    html += `
                        <div class="table-concern-entry">
                            <div class="text-dark fw-semibold" style="font-size: 0.85rem; line-height: 1.35;">Others / Custom Concern</div>
                            <div class="text-muted small ps-2" style="line-height: 1.35; word-break: break-word;">&bull; ${escapeHtml(otherText)}</div>
                        </div>
                    `;
                } else {
                    html += `
                        <div class="receipt-concern-item">
                            <div class="text-dark fw-bold" style="font-size: 0.875rem; line-height: 1.35;">
                                <i class="bi bi-pen me-1 text-primary"></i>Others / Custom Concern
                            </div>
                            <div class="text-dark fw-bold mt-1" style="font-size: 0.875rem; line-height: 1.35; word-break: break-word;">
                                ${escapeHtml(otherText)}
                            </div>
                        </div>
                    `;
                }
            }

            if (!html) {
                html = renderConcernCard('General Concern', []);
            }

            return html;
        }

        // Helper to render modular detail tile matched with Service Counter Workstation
        function renderDetailTile(iconClass, label, value, colSize = 'col-12 col-sm-6') {
            return `
                <div class="${colSize}">
                    <div class="detail-tile d-flex align-items-start">
                        <div class="tile-icon flex-shrink-0">
                            <i class="bi ${iconClass} fs-5"></i>
                        </div>
                        <div class="min-w-0 flex-grow-1">
                            <span class="fw-semibold text-muted d-block mb-1" style="font-size: 0.75rem;">${label}</span>
                            <div class="text-dark d-block text-break" style="font-size: 0.875rem; line-height: 1.35; word-break: break-word;">${value}</div>
                        </div>
                    </div>
                </div>
            `;
        }

        // Plain-text version of a ticket's concerns (used for per-column filter, search and export)
        function buildConcernsText(t) {
            const parts = [];
            if (Array.isArray(t.concerns_list) && t.concerns_list.length > 0) {
                t.concerns_list.forEach(i => {
                    const main = (i.transaction_name || '').trim();
                    const sub  = (i.sub_concern_name || '').trim();
                    parts.push(sub ? (main ? `${main}: ${sub}` : sub) : main);
                });
            } else if (t.transaction_name) {
                parts.push(t.transaction_name);
            }
            if (t.other_concern_text) parts.push(t.other_concern_text.trim());
            return parts.filter(Boolean).join('; ');
        }

        // Add flat, filterable/exportable fields to each record
        function normalizeRecords(rows) {
            return (rows || []).map(r => {
                r.status         = (r.status || '').trim().toUpperCase();
                r.visitor_type   = (r.visitor_type || '').trim().toUpperCase();
                r.concerns_text  = buildConcernsText(r);
                r.office_name    = r.office_name || '';
                r.counter_name   = r.counter_name || '';
                r.served_by_name = (r.served_by_name || '').trim();

                const m = computeQueueLifecycleMetrics(r);
                r.waiting_time_text    = formatLifecycleDuration(m.wait.secs, selectedDurationFormat);
                r.calling_time_text    = formatLifecycleDuration(m.call.secs, selectedDurationFormat);
                r.processing_time_text = formatLifecycleDuration(m.proc.secs, selectedDurationFormat);

                const createdParts = formatDateAndTimeParts(r.created_at);
                r.time_text        = createdParts.time;
                r.datetime_text    = `${r.queue_date || createdParts.date} ${createdParts.time}`.trim();

                const updatedTs    = r.latest_activity_at || r.completed_at || r.served_at || r.called_at || r.created_at || '';
                const updatedParts = formatDateAndTimeParts(updatedTs);
                r.latest_activity_at = updatedTs;
                r.updated_date       = updatedParts.date;
                r.updated_time       = updatedParts.time;
                r.updated_text       = updatedParts.full;

                return r;
            });
        }

        const colFilter = {
            headerFilter: "input",
            headerFilterFunc: "like",
            headerFilterParams: { allowEmpty: true }
        };

        function applyGlobalSearch(tableInstance) {
            const kw = $('#global-search').val().trim().toLowerCase();
            if (!kw) {
                tableInstance.clearFilter();
                return;
            }
            tableInstance.setFilter(function(data) {
                return [
                    data.ticket_number, data.full_name, data.visitor_type, data.concerns_text,
                    data.office_name, data.counter_name, data.served_by_name,
                    data.waiting_time_text, data.calling_time_text, data.processing_time_text,
                    data.status, data.datetime_text, data.updated_text
                ].some(v => String(v ?? '').toLowerCase().includes(kw));
            });
        }

        function initTabulator() {
            recordsTable = new Tabulator("#transactionRecordsTable", {
                layout: "fitDataFill",
                resizableColumnFit: true,
                headerFilterPlaceholder: "Search",
                pagination: "local",
                paginationSize: 10,
                paginationSizeSelector: [10, 25, 50, 100],
                movableColumns: true,
                initialSort: [{ column: "updated_text", dir: "desc" }],
                placeholder: "No Record Found",
                ajaxURL: HANDLER_URL,
                ajaxConfig: "POST",
                ajaxContentType: "form",
                ajaxParams: function() {
                    return {
                        action: 'fetch_records',
                        office_id: $('#filterOfficeId').val(),
                        visitor_type: $('#filterVisitorType').val(),
                        status: $('#filterStatus').val(),
                        date_from: $('#filterDateFrom').val(),
                        date_to: $('#filterDateTo').val()
                    };
                },
                ajaxResponse: function(url, params, response) {
                    if (response && response.success === false) {
                        if (typeof showError === 'function') showError(response.message || 'Unable to load transaction records.');
                        return [];
                    }
                    return normalizeRecords(response && response.data);
                },
                downloadConfig: {
                    columnHeaders: true,
                    columnGroups: false,
                    rowGroups: false,
                    formatCells: false
                },
                columns: [
                    {
                        title: "Action",
                        field: "id",
                        minWidth: 120,
                        headerSort: false,
                        download: false,
                        vertAlign: 'middle',
                        hozAlign: 'center',
                        formatter: () => `<button type="button" class="btn btn-action d-inline-flex align-items-center justify-content-center px-2 py-1 gap-2 btn-view-row" title="View">
                                              <i class="bi bi-eye"></i><span style="font-size: 0.75rem;">View</span>
                                          </button>`,
                        cellClick: function(e, cell) {
                            if (e.target.closest('.btn-view-row')) viewTicketDetails(cell.getValue());
                        }
                    },
                    Object.assign({
                        title: "Ticket #",
                        field: "ticket_number",
                        minWidth: 120,
                        vertAlign: 'middle',
                        formatter: cell => `<strong class="text-primary font-monospace fs-6">${escapeHtml(cell.getValue()) || '---'}</strong>`
                    }, colFilter),
                    Object.assign({
                        title: "Visitor Name",
                        field: "full_name",
                        minWidth: 170,
                        vertAlign: 'middle',
                        formatter: cell => `<span class="text-dark fw-semibold">${escapeHtml(cell.getValue()) || 'N/A'}</span>`
                    }, colFilter),
                    {
                        title: "Visitor Type",
                        field: "visitor_type",
                        minWidth: 130,
                        vertAlign: 'middle',
                        hozAlign: 'center',
                        headerFilter: "select",
                        headerFilterLiveFilter: false,
                        headerFilterParams: {
                            values: {
                                "": "All",
                                "STUDENT": "Student",
                                "EMPLOYEE": "Employee",
                                "GUEST": "Guest"
                            }
                        },
                        formatter: cell => `<span class="text-dark">${formatCapitalized(cell.getValue())}</span>`
                    },
                    Object.assign({
                        title: "Transaction Concerns",
                        field: "concerns_text",
                        minWidth: 300,
                        vertAlign: 'middle',
                        formatter: cell => `<div class="py-1">${buildLogbookStyleConcerns(cell.getRow().getData(), true)}</div>`
                    }, colFilter),
                    Object.assign({
                        title: "Office",
                        field: "office_name",
                        minWidth: 200,
                        vertAlign: 'middle',
                        formatter: cell => `<span class="text-dark">${escapeHtml(cell.getValue()) || '---'}</span>`
                    }, colFilter),
                    Object.assign({
                        title: "Window",
                        field: "counter_name",
                        minWidth: 120,
                        vertAlign: 'middle',
                        hozAlign: 'center',
                        formatter: cell => `<span class="text-dark">${escapeHtml(cell.getValue()) || '---'}</span>`
                    }, colFilter),
                    Object.assign({
                        title: "Served By",
                        field: "served_by_name",
                        minWidth: 170,
                        vertAlign: 'middle',
                        formatter: cell => `<span class="text-dark">${escapeHtml(cell.getValue()) || '---'}</span>`
                    }, colFilter),
                    Object.assign({
                        title: "Waiting Time",
                        field: "waiting_time_text",
                        minWidth: 155,
                        vertAlign: 'middle',
                        hozAlign: 'center',
                        formatter: cell => `<span class="text-dark fw-semibold">${escapeHtml(cell.getValue()) || '---'}</span>`
                    }, colFilter),
                    Object.assign({
                        title: "Calling Time",
                        field: "calling_time_text",
                        minWidth: 155,
                        vertAlign: 'middle',
                        hozAlign: 'center',
                        formatter: cell => `<span class="text-dark fw-semibold">${escapeHtml(cell.getValue()) || '---'}</span>`
                    }, colFilter),
                    Object.assign({
                        title: "Processing Time",
                        field: "processing_time_text",
                        minWidth: 165,
                        vertAlign: 'middle',
                        hozAlign: 'center',
                        formatter: cell => `<span class="text-dark fw-semibold">${escapeHtml(cell.getValue()) || '---'}</span>`
                    }, colFilter),
                    {
                        title: "Status",
                        field: "status",
                        minWidth: 130,
                        vertAlign: 'middle',
                        hozAlign: 'center',
                        headerFilter: "select",
                        headerFilterLiveFilter: false,
                        headerFilterParams: {
                            values: {
                                "": "All",
                                "COMPLETED": "Completed",
                                "SKIPPED": "Skipped",
                                "SERVING": "Serving",
                                "CALLED": "Called",
                                "WAITING": "Waiting"
                            }
                        },
                        formatter: cell => escapeHtml(formatCapitalized(cell.getValue()))
                    },
                    Object.assign({
                        title: "Created At",
                        field: "datetime_text",
                        minWidth: 150,
                        vertAlign: 'middle',
                        sorter: (a, b, aRow, bRow) => {
                            const tA = parseSqlTimestamp(aRow.getData().created_at)?.getTime() || 0;
                            const tB = parseSqlTimestamp(bRow.getData().created_at)?.getTime() || 0;
                            return tA - tB || (Number(aRow.getData().id) - Number(bRow.getData().id));
                        },
                        formatter: cell => {
                            const row = cell.getRow().getData();
                            return `<div><span class="d-block text-dark">${escapeHtml(row.queue_date)}</span><small class="text-muted">${escapeHtml(row.time_text)}</small></div>`;
                        }
                    }, colFilter),
                    Object.assign({
                        title: "Last Updated",
                        field: "updated_text",
                        minWidth: 150,
                        vertAlign: 'middle',
                        sorter: (a, b, aRow, bRow) => {
                            const tA = parseSqlTimestamp(aRow.getData().latest_activity_at)?.getTime() || 0;
                            const tB = parseSqlTimestamp(bRow.getData().latest_activity_at)?.getTime() || 0;
                            return tA - tB || (Number(aRow.getData().id) - Number(bRow.getData().id));
                        },
                        formatter: cell => {
                            const row = cell.getRow().getData();
                            return `<div><span class="d-block text-dark">${escapeHtml(row.updated_date || '---')}</span><small class="text-muted">${escapeHtml(row.updated_time || '')}</small></div>`;
                        }
                    }, colFilter)
                ]
            });

            recordsTable.on("dataProcessed", function() {
                applyGlobalSearch(recordsTable);
            });

            document.getElementById('export-csv')?.addEventListener('click', () => recordsTable.download("csv", "transaction_records.csv"));
            document.getElementById('export-excel')?.addEventListener('click', () => recordsTable.download("xlsx", "transaction_records.xlsx", { sheetName: "Records" }));
            document.getElementById('export-json')?.addEventListener('click', () => recordsTable.download("json", "transaction_records.json"));

            // --- GLOBAL SEARCH HANDLER ---
            $('#global-search').on('input', function() {
                applyGlobalSearch(recordsTable);
            });

            // --- REFRESH DATA HANDLER ---
            $('#btn-refresh').on('click', function() {
                $('#global-search').val('');
                recordsTable.clearFilter();
                recordsTable.clearHeaderFilter();
                recordsTable.setData();
            });
        }

        $('#recordsFilterForm').on('submit', function(e) {
            e.preventDefault();
            if (recordsTable) recordsTable.setData();
        });

        $('#btnResetFilters').on('click', function() {
            const todayStr = getTodayDate();
            if (!$('#filterOfficeId').prop('disabled')) {
                $('#filterOfficeId').val('0');
            }
            $('#filterVisitorType').val('');
            $('#filterStatus').val('');
            $('#filterDateFrom').val(todayStr);
            $('#filterDateTo').val(todayStr);
            $('#global-search').val('');
            if (recordsTable) {
                recordsTable.clearFilter();
                recordsTable.clearHeaderFilter();
                recordsTable.setData();
            }
        });

        window.viewTicketDetails = function(ticketId) {
            if (!ticketId) return;

            const modalElem = document.getElementById('ticketDetailsModal');
            const modal = bootstrap.Modal.getOrCreateInstance(modalElem);
            const modalBody = $('#modalTicketBody');
            
            stopModalLifecycleTicker();
            activeModalTicketRef = null;

            modalBody.html(`
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>`);
            modal.show();

            const formData = new FormData();
            formData.append('action', 'fetch_ticket_details');
            formData.append('ticket_id', ticketId);

            fetch(HANDLER_URL, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(res => {
                if (res.success && res.data) {
                    const t = res.data;
                    activeModalTicketRef = t;

                    const visitorTypeFormatted = formatCapitalized(t.visitor_type);
                    const statusUpper = (t.status || 'WAITING').toUpperCase();

                    let statusBadgeCls = 'badge-status-waiting';
                    if (statusUpper === 'COMPLETED') statusBadgeCls = 'badge-status-completed';
                    if (statusUpper === 'SKIPPED')   statusBadgeCls = 'badge-status-skipped';
                    if (statusUpper === 'SERVING')   statusBadgeCls = 'badge-status-serving';
                    if (statusUpper === 'CALLED')    statusBadgeCls = 'badge-status-called';

                    let statusBadgeHtml = `<span class="badge ${statusBadgeCls} px-3 py-1.5 rounded-pill fw-bold">${statusUpper}</span>`;
                    let visitorBadgeHtml = `<span class="badge bg-secondary px-3 py-1.5 rounded-pill fw-bold">${visitorTypeFormatted}</span>`;

                    let name = escapeHtml(t.full_name || 'N/A');
                    let email = escapeHtml(t.email || 'N/A');
                    let officeCounterDisplay = escapeHtml(`${t.office_name || 'N/A'}${t.counter_name ? ' (' + t.counter_name + ')' : ''}`);

                    const concernsHtml = buildLogbookStyleConcerns(t, false);

                    let concernsBlockHtml = `
                        <div class="col-12">
                            <div class="detail-tile border-primary-subtle bg-white">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="tile-icon flex-shrink-0 bg-primary text-white" style="width: 36px; height: 36px; min-width: 36px; margin-top:0;">
                                        <i class="bi bi-clipboard-check-fill fs-6"></i>
                                    </div>
                                    <div>
                                        <span class="fw-bold text-dark d-block" style="font-size: 0.875rem;">Transaction Concerns & Services</span>
                                    </div>
                                </div>
                                <div class="ps-1">
                                    ${concernsHtml}
                                </div>
                            </div>
                        </div>
                    `;

                    modalBody.html(`
                        <!-- HERO TICKET HEADER CARD -->
                        <div class="modal-ticket-hero p-4 text-center mb-4">
                            <span class="text-white-50 text-uppercase fw-bold small tracking-wide">Queue Ticket Number</span>
                            <h1 class="display-4 fw-bold text-info font-monospace my-1 mb-0">${escapeHtml(t.ticket_number) || '---'}</h1>
                        </div>

                        <!-- DETAILS GRID -->
                        <div class="row g-3 mb-4">
                            ${renderDetailTile('bi-activity', 'Queue Status', statusBadgeHtml)}
                            ${renderDetailTile('bi-person-vcard-fill', 'Visitor Type', visitorBadgeHtml)}
                            ${renderDetailTile('bi-person-fill', 'Visitor Name', `<strong>${name}</strong>`)}
                            ${renderDetailTile('bi-envelope-fill', 'Email Address', `<strong>${email}</strong>`)}
                            ${renderDetailTile('bi-building', 'Office & Counter', `<strong>${officeCounterDisplay}</strong>`)}
                            ${renderDetailTile('bi-person-badge-fill', 'Served By Staff', `<strong>${escapeHtml(t.served_by_name || 'N/A')}</strong>`)}
                            
                            <!-- STRUCTURED CONCERNS CARD -->
                            ${concernsBlockHtml}
                        </div>

                        <!-- QUEUE LIFECYCLE TIMESTAMPS -->
                        <h6 class="fw-bold text-dark mb-2 text-center"><i class="bi bi-clock-history me-1 text-primary"></i>Queue Lifecycle Timestamps</h6>
                        <div id="queueLifecycleTableContainer">
                            ${buildLifecycleTableHtml(t)}
                        </div>
                    `);

                    startModalLifecycleTicker();
                } else {
                    modalBody.html(`<div class="alert alert-warning mb-0">${escapeHtml(res.message || 'Details unavailable.')}</div>`);
                }
            })
            .catch(err => {
                console.error("Modal fetch error:", err);
                modalBody.html('<div class="alert alert-danger mb-0">Failed to load ticket details.</div>');
            });
        };

        $(document).ready(function() {
            initTabulator();
        });
    })();
</script>
</body>
</html>