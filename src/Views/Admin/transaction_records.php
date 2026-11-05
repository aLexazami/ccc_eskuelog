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
    ['label' => 'Queue Management', 'url' => 'javascript:void(0)'],
    ['label' => 'Transaction Records', 'url' => '']
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
    }

    if ($g_user_role_upper === 'ADMIN' || !$assigned_office) {
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
                    <div class="kiosk-page-header">
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
                                        <?php if ($g_user_role_upper === 'ADMIN'): ?>
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

        // Dynamic helper to calculate local browser date (YYYY-MM-DD) across midnight
        const getTodayDate = () => {
            const today = new Date();
            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0');
            const day = String(today.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        };

        function formatCapitalized(str) {
            if (!str) return 'N/A';
            return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();
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

        function initTabulator() {
            recordsTable = new Tabulator("#transactionRecordsTable", {
                layout: "fitColumns",
                responsiveLayout: "collapse",
                responsiveLayoutCollapseStartOpen: false,
                pagination: "local",
                paginationSize: 10,
                paginationSizeSelector: [10, 25, 50, 100],
                movableColumns: true,
                placeholder: "No transaction records found matching criteria.",
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
                    return response.data || [];
                },
                columns: [
                    { 
                        formatter: "responsiveCollapse", 
                        width: 30, 
                        minWidth: 30, 
                        hozAlign: "center", 
                        resizable: false, 
                        headerSort: false,
                        responsive: 0 
                    },
                    { 
                        title: "Ticket #", 
                        field: "ticket_number", 
                        minWidth: 110,
                        responsive: 0,
                        formatter: cell => `<strong class="text-primary font-monospace fs-6">${cell.getValue() || '---'}</strong>` 
                    },
                    { 
                        title: "Visitor Name", 
                        field: "full_name", 
                        minWidth: 150,
                        responsive: 1,
                        formatter: cell => `<span class="text-dark">${cell.getValue() || 'N/A'}</span>` 
                    },
                    { 
                        title: "Visitor Type", 
                        field: "visitor_type", 
                        minWidth: 120,
                        responsive: 2,
                        formatter: cell => `<span class="text-dark">${formatCapitalized(cell.getValue())}</span>` 
                    },
                    { 
                        title: "Transaction Concern", 
                        field: "transaction_name", 
                        minWidth: 260,
                        responsive: 3,
                        formatter: cell => {
                            const row = cell.getRow().getData();
                            let main = cell.getValue() || 'General Transaction';
                            if (row.sub_concern_name) {
                                return `<div><span class="d-block text-dark">${main}</span><small class="text-muted">&rsaquo; ${row.sub_concern_name}</small></div>`;
                            }
                            return `<span class="text-dark">${main}</span>`;
                        } 
                    },
                    { 
                        title: "Office / Counter", 
                        field: "office_name", 
                        minWidth: 220,
                        responsive: 4,
                        formatter: cell => {
                            const row = cell.getRow().getData();
                            const counter = row.counter_name ? ` (${row.counter_name})` : '';
                            return `<span class="text-dark">${cell.getValue() || '---'}${counter}</span>`;
                        } 
                    },
                    { 
                        title: "Status", 
                        field: "status", 
                        minWidth: 110,
                        responsive: 0,
                        formatter: cell => {
                            const st = (cell.getValue() || '').toUpperCase();
                            let cls = 'badge-status-waiting';
                            if (st === 'COMPLETED') cls = 'badge-status-completed';
                            if (st === 'SKIPPED') cls = 'badge-status-skipped';
                            if (st === 'SERVING') cls = 'badge-status-serving';
                            if (st === 'CALLED') cls = 'badge-status-called';
                            return `<span class="badge ${cls} px-2.5 py-1.5 rounded-pill fw-bold">${st}</span>`;
                        } 
                    },
                    { 
                        title: "Date & Time", 
                        field: "queue_date", 
                        minWidth: 130,
                        responsive: 2,
                        formatter: cell => {
                            const row = cell.getRow().getData();
                            const time = row.created_at ? new Date(row.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : '';
                            return `<div><span class="d-block text-dark">${cell.getValue() || ''}</span><small class="text-muted">${time}</small></div>`;
                        } 
                    },
                    { 
                        title: "Action", 
                        field: "id", 
                        minWidth: 90,
                        responsive: 0,
                        hozAlign: "right", 
                        headerHozAlign: "right", 
                        headerSort: false, 
                        formatter: cell => {
                            return `<button type="button" class="btn btn-sm btn-outline-primary fw-bold" onclick="viewTicketDetails(${cell.getValue()})">
                                        <i class="bi bi-eye-fill me-1"></i>View
                                    </button>`;
                        } 
                    }
                ]
            });
        }

        $('#recordsFilterForm').on('submit', function(e) {
            e.preventDefault();
            if (recordsTable) recordsTable.setData();
        });

        // Dynamic reset action using browser real-time Date
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
                    let mainConcern = t.transaction_name || 'General Concern';
                    let subConcern = t.sub_concern_name ? ` &rsaquo; ${t.sub_concern_name}` : '';
                    let fullConcernDisplay = mainConcern + subConcern;
                    let officeCounterDisplay = `${t.office_name || 'N/A'}${t.counter_name ? ' (' + t.counter_name + ')' : ''}`;

                    modalBody.html(`
                        <!-- HERO TICKET HEADER CARD -->
                        <div class="modal-ticket-hero p-4 text-center mb-4">
                            <span class="text-white-50 text-uppercase fw-bold small tracking-wide">Queue Ticket Number</span>
                            <h1 class="display-4 fw-bold text-info font-monospace my-1 mb-0">${t.ticket_number || '---'}</h1>
                        </div>

                        <!-- DETAILS GRID (WITH QUEUE STATUS & VISITOR TYPE AT THE VERY TOP) -->
                        <div class="row g-3 mb-4">
                            ${renderDetailTile('bi-activity', 'Queue Status', statusBadgeHtml)}
                            ${renderDetailTile('bi-person-vcard-fill', 'Visitor Type', visitorBadgeHtml)}
                            ${renderDetailTile('bi-person-fill', 'Visitor Name', `<strong>${name}</strong>`)}
                            ${renderDetailTile('bi-envelope-fill', 'Email Address', `<strong>${email}</strong>`)}
                            ${renderDetailTile('bi-building', 'Office & Counter', `<strong>${officeCounterDisplay}</strong>`)}
                            ${renderDetailTile('bi-person-badge-fill', 'Served By Staff', `<strong>${t.served_by_name || 'N/A'}</strong>`)}
                            ${renderDetailTile('bi-clipboard-check-fill', 'Transaction Concern', `<strong>${fullConcernDisplay}</strong>`, 'col-12')}
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