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
        }

        /* Queue Status Badge Styles */
        .badge-status-completed { background-color: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .badge-status-skipped   { background-color: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .badge-status-serving   { background-color: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }
        .badge-status-called    { background-color: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
        .badge-status-waiting   { background-color: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }

        /* Tabulator Responsive Polish */
        .tabulator {
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            background-color: #ffffff;
        }
        .tabulator .tabulator-header {
            background-color: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
            font-weight: 600;
            color: #475569;
        }
        #transactionRecordsTable .tabulator-cell { white-space: normal; }
        .tabulator-responsive-collapse {
            padding: 0.75rem 1rem !important;
            background-color: #f8fafc !important;
            border-bottom: 1px solid #e2e8f0 !important;
        }
        .tabulator-responsive-collapse table td {
            padding: 0.35rem 0.75rem;
        }

        /* Modern Modal Polish */
        .modal-ticket-hero {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%);
            border-radius: 0.85rem;
            color: #ffffff;
        }

        /* Detail Tile Styling */
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

        .timeline-step-card {
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 0.65rem;
            padding: 0.65rem 0.75rem;
            transition: all 0.2s ease;
        }

        .timeline-step-card:hover {
            background: #ffffff;
            border-color: #cbd5e1;
        }

        /* RECEIPT CONCERN STYLES (MATCHED EXACTLY FROM STUDENT LOGBOOK & QUEUE CONTROLLER) */
        .receipt-concern-item {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-left: 3.5px solid #1d4ed8;
            border-radius: 0.5rem;
            padding: 0.55rem 0.85rem;
            margin-bottom: 0.4rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
        }

        .receipt-concern-title {
            font-weight: 700;
            color: #1e293b;
            font-size: 0.875rem;
            line-height: 1.3;
        }

        .receipt-subconcern-tag {
            background-color: #eff6ff;
            color: #1d4ed8;
            font-weight: 600;
            font-size: 0.75rem;
            padding: 0.2rem 0.55rem;
            border-radius: 0.35rem;
            display: inline-flex;
            align-items: center;
            border: 1px solid #bfdbfe;
            line-height: 1.2;
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
                            <form id="recordsFilterForm" class="row g-3 align-items-end">
                                
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

                                <!-- STATUS -->
                                <div class="col-12 col-sm-6 col-md-2">
                                    <label for="filterStatus" class="form-label fw-bold small text-muted">Queue Status</label>
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
                                <div class="col-6 col-md-2">
                                    <label for="filterDateFrom" class="form-label fw-bold small text-muted">Date From</label>
                                    <input type="date" id="filterDateFrom" class="form-input form-control form-control-sm" value="<?= date('Y-m-d'); ?>">
                                </div>

                                <!-- DATE TO -->
                                <div class="col-6 col-md-2">
                                    <label for="filterDateTo" class="form-label fw-bold small text-muted">Date To</label>
                                    <input type="date" id="filterDateTo" class="form-input form-control form-control-sm" value="<?= date('Y-m-d'); ?>">
                                </div>

                                <!-- ACTION BUTTONS -->
                                <div class="col-12 col-md-1 d-flex gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold">
                                        <i class="bi bi-search"></i>
                                    </button>
                                    <button type="button" id="btnResetFilters" class="btn btn-outline-secondary btn-sm" title="Reset Filters">
                                        <i class="bi bi-arrow-counterclockwise"></i>
                                    </button>
                                </div>

                            </form>
                        </div>
                    </div>

                    <!-- TABULATOR TABLE CONTAINER CARD -->
                    <div class="card border-0 shadow-sm rounded-3">
                        <div class="card-body p-3 p-sm-4">
                            <!-- Row 1: Export + Refresh -->
                            <div class="d-flex justify-content-end align-items-center gap-2 mb-3">
                                <div class="dropdown">
                                    <button class="btn btn-action dropdown-toggle d-inline-flex align-items-center" type="button" data-bs-toggle="dropdown">
                                        <i class="bi bi-download me-2"></i>Export / Download
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                        <li><a class="dropdown-item" href="javascript:void(0)" id="export-csv"><i class="bi bi-filetype-csv text-success me-2"></i> Export CSV</a></li>
                                        <li><a class="dropdown-item" href="javascript:void(0)" id="export-excel"><i class="bi bi-file-earmark-excel text-success me-2"></i> Export Excel (.xlsx)</a></li>
                                        <li><a class="dropdown-item" href="javascript:void(0)" id="export-json"><i class="bi bi-filetype-json text-warning me-2"></i> Export JSON</a></li>
                                    </ul>
                                </div>
                                <button type="button" id="btn-refresh" class="btn btn-action d-inline-flex align-items-center">
                                    <i class="bi bi-arrow-clockwise me-2"></i>Refresh Data
                                </button>
                            </div>

                            <!-- Row 2: Search bar on its own row -->
                            <div class="d-flex mb-3">
                                <div class="search-input-group flex-grow-1">
                                    <i class="bi bi-search search-icon"></i>
                                    <input type="text" id="global-search" class="form-control" placeholder="Search...">
                                </div>
                            </div>

                            <div id="transactionRecordsTable"></div>
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

        // Build exact Student Logbook Receipt Style for Multiple Primary Transactions & Sub Concerns
        function buildLogbookStyleConcerns(ticket) {
            let html = '';
            const otherText = ticket.other_concern_text ? ticket.other_concern_text.trim() : '';

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
                    let subTagsHtml = '';
                    if (subs && subs.length > 0) {
                        const tags = subs.map(sub => `
                            <span class="receipt-subconcern-tag me-1 mb-1">
                                <i class="bi bi-tag-fill me-1"></i>${escapeHtml(sub)}
                            </span>
                        `).join('');
                        subTagsHtml = `<div class="mt-1 d-flex flex-wrap align-items-center">${tags}</div>`;
                    }

                    html += `
                        <div class="receipt-concern-item">
                            <div class="receipt-concern-title">${escapeHtml(mainTitle)}</div>
                            ${subTagsHtml}
                        </div>
                    `;
                });
            } else if (ticket.transaction_name) {
                const mainConcerns = ticket.transaction_name.split(',').map(s => s.trim()).filter(Boolean);
                const subConcerns = (ticket.sub_concern_name || '').split(',').map(s => s.trim()).filter(Boolean);

                mainConcerns.forEach((main, idx) => {
                    if (main.toLowerCase().includes('other') && otherText) return;

                    let subTagsHtml = '';
                    if (idx === 0 && subConcerns.length > 0) {
                        const tags = subConcerns.map(sub => `
                            <span class="receipt-subconcern-tag me-1 mb-1">
                                <i class="bi bi-tag-fill me-1"></i>${escapeHtml(sub)}
                            </span>
                        `).join('');
                        subTagsHtml = `<div class="mt-1 d-flex flex-wrap align-items-center">${tags}</div>`;
                    }

                    html += `
                        <div class="receipt-concern-item">
                            <div class="receipt-concern-title">${escapeHtml(main)}</div>
                            ${subTagsHtml}
                        </div>
                    `;
                });
            }

            if (otherText) {
                html += `
                    <div class="receipt-concern-item">
                        <div class="receipt-concern-title"><i class="bi bi-pen me-1 text-primary"></i>Others / Custom Concern</div>
                        <div class="text-muted small mt-1 fw-semibold" style="word-break: break-word;">${escapeHtml(otherText)}</div>
                    </div>
                `;
            }

            if (!html) {
                html = `
                    <div class="receipt-concern-item">
                        <div class="receipt-concern-title">General Concern</div>
                    </div>
                `;
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
                r.office_counter = (r.office_name || '') + (r.counter_name ? ` (${r.counter_name})` : '');
                const d    = r.created_at ? new Date(r.created_at) : null;
                const time = (d && !isNaN(d)) ? d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '';
                r.time_text     = time;
                r.datetime_text = `${r.queue_date || ''} ${time}`.trim();
                return r;
            });
        }

        const colFilter = {
            headerFilter: "input",
            headerFilterFunc: "like",
            headerFilterParams: { allowEmpty: true }
        };

        // Dropdown header filter factory: options = [[value, label], ...]
        function selectHeaderFilter(options) {
            return function (cell, onRendered, success, cancel) {
                const select = document.createElement('select');
                select.className = 'form-select form-select-sm';
                select.style.width = '100%';
                [['', 'All']].concat(options).forEach(([val, label]) => {
                    const opt = document.createElement('option');
                    opt.value = val;
                    opt.textContent = label;
                    select.appendChild(opt);
                });
                select.addEventListener('change', () => success(select.value));
                return select;
            };
        }

        function initTabulator() {
            recordsTable = new Tabulator("#transactionRecordsTable", {
                layout: "fitColumns",
                resizableColumnFit: true,
                headerFilterPlaceholder: "Search",
                pagination: "local",
                paginationSize: 10,
                paginationSizeSelector: [10, 25, 50, 100],
                movableColumns: true,
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
                        headerFilter: selectHeaderFilter([['STUDENT', 'Student'], ['EMPLOYEE', 'Employee'], ['GUEST', 'Guest']]),
                        headerFilterFunc: "=",
                        formatter: cell => `<span class="text-dark">${formatCapitalized(cell.getValue())}</span>`
                    },
                    Object.assign({
                        title: "Transaction Concerns",
                        field: "concerns_text",
                        minWidth: 300,
                        vertAlign: 'middle',
                        formatter: cell => `<div class="py-1">${buildLogbookStyleConcerns(cell.getRow().getData())}</div>`
                    }, colFilter),
                    Object.assign({
                        title: "Office / Counter",
                        field: "office_counter",
                        minWidth: 220,
                        vertAlign: 'middle',
                        formatter: cell => `<span class="text-dark">${escapeHtml(cell.getValue()) || '---'}</span>`
                    }, colFilter),
                    {
                        title: "Status",
                        field: "status",
                        minWidth: 130,
                        vertAlign: 'middle',
                        hozAlign: 'center',
                        headerFilter: selectHeaderFilter([
                            ['COMPLETED', 'Completed'], ['SKIPPED', 'Skipped'], ['SERVING', 'Serving'],
                            ['CALLED', 'Called'], ['WAITING', 'Waiting']
                        ]),
                        headerFilterFunc: "=",
                        formatter: cell => escapeHtml(formatCapitalized(cell.getValue()))
                    },
                    Object.assign({
                        title: "Date & Time",
                        field: "datetime_text",
                        minWidth: 150,
                        vertAlign: 'middle',
                        sorter: (a, b, aRow, bRow) => String(aRow.getData().created_at || '').localeCompare(String(bRow.getData().created_at || '')),
                        formatter: cell => {
                            const row = cell.getRow().getData();
                            return `<div><span class="d-block text-dark">${escapeHtml(row.queue_date)}</span><small class="text-muted">${escapeHtml(row.time_text)}</small></div>`;
                        }
                    }, colFilter)
                ]
            });

            document.getElementById('export-csv')?.addEventListener('click', () => recordsTable.download("csv", "transaction_records.csv"));
            document.getElementById('export-excel')?.addEventListener('click', () => recordsTable.download("xlsx", "transaction_records.xlsx", { sheetName: "Records" }));
            document.getElementById('export-json')?.addEventListener('click', () => recordsTable.download("json", "transaction_records.json"));

            // --- GLOBAL SEARCH HANDLER (works together with the per-column filters) ---
            $('#global-search').on('input', function() {
                const kw = this.value.trim().toLowerCase();
                if (!kw) { recordsTable.clearFilter(); return; }
                recordsTable.setFilter(function(data) {
                    return [data.ticket_number, data.full_name, data.visitor_type, data.concerns_text, data.office_counter, data.status, data.datetime_text]
                        .some(v => String(v ?? '').toLowerCase().includes(kw));
                });
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
            $('#filterVisitorType').val('');
            $('#filterStatus').val('');
            $('#filterDateFrom').val(todayStr);
            $('#filterDateTo').val(todayStr);
            if (recordsTable) recordsTable.setData();
        });

        window.viewTicketDetails = function(ticketId) {
            if (!ticketId) return;

            const modalElem = document.getElementById('ticketDetailsModal');
            const modal = bootstrap.Modal.getOrCreateInstance(modalElem);
            const modalBody = $('#modalTicketBody');
            
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
                    const visitorTypeFormatted = formatCapitalized(t.visitor_type);
                    const statusUpper = (t.status || 'WAITING').toUpperCase();

                    let statusBadgeCls = 'badge-status-waiting';
                    if (statusUpper === 'COMPLETED') statusBadgeCls = 'badge-status-completed';
                    if (statusUpper === 'SKIPPED')   statusBadgeCls = 'badge-status-skipped';
                    if (statusUpper === 'SERVING')   statusBadgeCls = 'badge-status-serving';
                    if (statusUpper === 'CALLED')    statusBadgeCls = 'badge-status-called';

                    let statusBadgeHtml = `<span class="badge ${statusBadgeCls} px-3 py-1.5 rounded-pill fw-bold">${statusUpper}</span>`;
                    let visitorBadgeHtml = `<span class="badge bg-secondary px-3 py-1.5 rounded-pill fw-bold">${visitorTypeFormatted}</span>`;

                    let name = t.full_name || 'N/A';
                    let email = t.email || 'N/A';
                    let officeCounterDisplay = `${t.office_name || 'N/A'}${t.counter_name ? ' (' + t.counter_name + ')' : ''}`;

                    const concernsHtml = buildLogbookStyleConcerns(t);

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
                            <h1 class="display-4 fw-bold text-info font-monospace my-1 mb-0">${t.ticket_number || '---'}</h1>
                        </div>

                        <!-- DETAILS GRID -->
                        <div class="row g-3 mb-4">
                            ${renderDetailTile('bi-activity', 'Queue Status', statusBadgeHtml)}
                            ${renderDetailTile('bi-person-vcard-fill', 'Visitor Type', visitorBadgeHtml)}
                            ${renderDetailTile('bi-person-fill', 'Visitor Name', `<strong>${name}</strong>`)}
                            ${renderDetailTile('bi-envelope-fill', 'Email Address', `<strong>${email}</strong>`)}
                            ${renderDetailTile('bi-building', 'Office & Counter', `<strong>${officeCounterDisplay}</strong>`)}
                            ${renderDetailTile('bi-person-badge-fill', 'Served By Staff', `<strong>${t.served_by_name || 'N/A'}</strong>`)}
                            
                            <!-- STRUCTURED CONCERNS CARD -->
                            ${concernsBlockHtml}
                        </div>

                        <!-- LIFECYCLE TIMESTAMPS GRID -->
                        <h6 class="fw-bold text-dark mb-2 ms-1"><i class="bi bi-clock-history me-1 text-primary"></i>Queue Lifecycle Timestamps</h6>
                        <div class="row g-2">
                            <div class="col-6 col-md-3">
                                <div class="timeline-step-card text-center">
                                    <small class="text-muted d-block fw-semibold">Entry / Issued</small>
                                    <span class="fw-bold text-dark small">${t.created_at || '---'}</span>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="timeline-step-card text-center">
                                    <small class="text-muted d-block fw-semibold">Called Time</small>
                                    <span class="fw-bold text-dark small">${t.called_at || '---'}</span>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="timeline-step-card text-center">
                                    <small class="text-muted d-block fw-semibold">Served Time</small>
                                    <span class="fw-bold text-dark small">${t.served_at || '---'}</span>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="timeline-step-card text-center">
                                    <small class="text-muted d-block fw-semibold">Completed Time</small>
                                    <span class="fw-bold text-dark small">${t.completed_at || '---'}</span>
                                </div>
                            </div>
                        </div>
                    `);
                } else {
                    modalBody.html(`<div class="alert alert-warning mb-0">${res.message || 'Details unavailable.'}</div>`);
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