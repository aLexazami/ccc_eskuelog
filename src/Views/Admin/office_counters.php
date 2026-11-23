<?php
// src/Views/Admin/office_counters.php

# Core Config Dependencies
$db_connect  = $db_connect ?? null;
$g_user_role = $g_user_role ?? '';

require_once HELPER;
require_once ISLOGIN;
require_once API_CONNECT;

# Page Helpers
require_once ADMIN_PAGE_HEADER_PATH;

# Page Header Initialization
$title_page   = "Office Service Counters";
$active_links = [
    ['label' => $title_page, 'url' => '']
];
$page_header  = render_page_header((string) $title_page, $active_links) ?? [];
# ===================================================================================

# Validate Access
$system_auth_login = $session_class->getValue(SYSTEM_ACCESS[SYSTEM_ACCESS_NAME]['auth']);
$allowed_roles = ['ADMIN', 'ADMIN_STAFF'];
if (!in_array($g_user_role, $allowed_roles, true) && !($system_auth_login == $g_public_key)) {
    header("location: " . SYSTEM_ACCESS[SYSTEM_ACCESS_NAME]['link']['main']);
    exit();
}

$is_admin_staff     = ($g_user_role === 'ADMIN_STAFF');
$assigned_office_id = 0;

if ($is_admin_staff && $db_connect) {
    $current_user_id = (int) ($g_user_id ?? $session_class->getValue('user_id') ?? 0);
    if ($current_user_id > 0) {
        $stmtAssign = $db_connect->prepare("
            SELECT office_id FROM staff_assignments
            WHERE user_id = ? AND flag_status = 0
            LIMIT 1
        ");
        if ($stmtAssign) {
            $stmtAssign->bind_param("i", $current_user_id);
            $stmtAssign->execute();
            $resAssign = $stmtAssign->get_result()->fetch_assoc();
            $assigned_office_id = (int) ($resAssign['office_id'] ?? 0);
            $stmtAssign->close();
        }

        // Fallback to employee.office_id if not yet in staff_assignments
        if ($assigned_office_id === 0) {
            $stmtEmp = $db_connect->prepare("SELECT office_id FROM employee WHERE user_id = ? LIMIT 1");
            if ($stmtEmp) {
                $stmtEmp->bind_param("i", $current_user_id);
                $stmtEmp->execute();
                $resEmp = $stmtEmp->get_result()->fetch_assoc();
                $assigned_office_id = (int) ($resEmp['office_id'] ?? 0);
                $stmtEmp->close();
            }
        }
    }
}

// Fetch active offices and eligible employee staff for dropdowns
$offices = [];
$users   = [];

if ($db_connect) {
    if ($is_admin_staff) {
        if ($assigned_office_id > 0) {
            $stmtOffices = $db_connect->prepare("
                SELECT office_id, office_code, office_name
                FROM office
                WHERE flag_status = 0 AND office_id = ?
                ORDER BY office_name ASC
            ");
            $stmtOffices->bind_param("i", $assigned_office_id);
            $stmtOffices->execute();
            $resOffices = $stmtOffices->get_result();
            while ($row = $resOffices->fetch_assoc()) {
                $offices[] = $row;
            }
            $stmtOffices->close();

            // Fetch only active employees assigned to this specific office
            $stmtUsers = $db_connect->prepare("
                SELECT DISTINCT
                    u.id,
                    u.first_name,
                    u.last_name,
                    u.email,
                    COALESCE(NULLIF(sa.office_id, 0), e.office_id) AS staff_office_id,
                    o.office_code AS staff_office_code
                FROM users u
                INNER JOIN employee e ON e.user_id = u.id AND e.service_status = 0 AND e.office_id > 0
                LEFT JOIN staff_assignments sa ON sa.user_id = u.id AND sa.flag_status = 0
                INNER JOIN office o ON o.office_id = COALESCE(NULLIF(sa.office_id, 0), e.office_id)
                WHERE u.flag_status = 0
                  AND (sa.office_id = ? OR e.office_id = ?)
                ORDER BY u.last_name ASC, u.first_name ASC
            ");
            $stmtUsers->bind_param("ii", $assigned_office_id, $assigned_office_id);
            $stmtUsers->execute();
            $resUsers = $stmtUsers->get_result();
            while ($row = $resUsers->fetch_assoc()) {
                $users[] = $row;
            }
            $stmtUsers->close();
        }
    } else {
        $stmtOffices = $db_connect->query("
            SELECT office_id, office_code, office_name 
            FROM office 
            WHERE flag_status = 0 
            ORDER BY office_name ASC
        ");
        if ($stmtOffices) {
            while ($row = $stmtOffices->fetch_assoc()) {
                $offices[] = $row;
            }
        }

        // ADMIN: Fetch all active employees strictly tied to their assigned office_id
        $stmtUsers = $db_connect->query("
            SELECT DISTINCT
                u.id,
                u.first_name,
                u.last_name,
                u.email,
                COALESCE(NULLIF(sa.office_id, 0), e.office_id) AS staff_office_id,
                o.office_code AS staff_office_code
            FROM users u
            INNER JOIN employee e ON e.user_id = u.id AND e.service_status = 0 AND e.office_id > 0
            LEFT JOIN staff_assignments sa ON sa.user_id = u.id AND sa.flag_status = 0
            INNER JOIN office o ON o.office_id = COALESCE(NULLIF(sa.office_id, 0), e.office_id)
            WHERE u.flag_status = 0
            ORDER BY o.office_code ASC, u.last_name ASC, u.first_name ASC
        ");
        if ($stmtUsers) {
            while ($row = $stmtUsers->fetch_assoc()) {
                $users[] = $row;
            }
        }
    }
}

$action_buttons = [
    [
        'label' => 'Add Service Counter',
        'icon'  => 'bi bi-plus-circle',
        'class' => 'add-counter-btn',
        'type'  => 'button'
    ]
];
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

        /* Clean Flat Tabulator Styling (Preserves Tabulator row/cell layout) */
        #tabulator-container.tabulator {
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            background-color: #ffffff;
            box-shadow: none !important;
        }
        #tabulator-container .tabulator-header,
        #tabulator-container .tabulator-header .tabulator-col {
            background-color: #ffffff !important;
            border-bottom: 1px solid #e2e8f0;
            box-shadow: none !important;
            font-weight: 600;
            color: #475569;
        }
        #tabulator-container .tabulator-header-filter input,
        #tabulator-container .tabulator-header-filter select {
            height: 32px !important;
            min-height: 32px !important;
            padding: 0.25rem 0.5rem !important;
            font-size: 0.8125rem !important;
            box-sizing: border-box;
        }
        #tabulator-container .tabulator-row,
        #tabulator-container .tabulator-row.tabulator-row-even,
        #tabulator-container .tabulator-row.tabulator-row-odd,
        #tabulator-container .tabulator-row:hover {
            background-color: #ffffff !important;
            box-shadow: none !important;
        }
        #tabulator-container .tabulator-cell {
            background-color: #ffffff !important;
            border-right: 1px solid #f1f5f9;
            box-shadow: none !important;
            white-space: normal;
        }
        #tabulator-container .tabulator-cell:last-child {
            border-right: none;
        }
        #tabulator-container .tabulator-footer {
            background-color: #ffffff !important;
            box-shadow: none !important;
            border-top: 1px solid #e2e8f0;
        }

        /* Enforce strict horizontal alignment inside Tabulator cells */
        #tabulator-container .tabulator-cell[tabulator-field="office_label"],
        #tabulator-container .tabulator-cell[tabulator-field="staff_label"] {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: flex-start !important;
            text-align: left !important;
        }

        #tabulator-container .tabulator-cell[tabulator-field="counter_name"],
        #tabulator-container .tabulator-cell[tabulator-field="status_label"],
        #tabulator-container .tabulator-cell[tabulator-field="updated_at_label"],
        #tabulator-container .tabulator-cell:first-child {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            text-align: center !important;
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
                    <?php echo $page_header; ?>

                    <!-- SEPARATE OFFICE FILTER CONTAINER -->
                    <div class="card filter-card mb-4">
                        <div class="card-body p-3 p-sm-4">
                            <form id="counterFilterForm">
                                <div class="row g-2 align-items-end">
                                    <div class="col-12 col-sm-9 col-md-7 col-xl-6">
                                        <label for="filterOffice" class="form-label fw-bold small text-muted">Campus Office</label>
                                        <select id="filterOffice" class="form-select form-select-sm py-2 px-3" <?= $is_admin_staff ? 'disabled style="pointer-events:none; background-color:#f1f5f9;"' : ''; ?>>
                                            <?php if (!$is_admin_staff): ?>
                                                <option value="0">-- All Campus Offices --</option>
                                            <?php endif; ?>
                                            <?php foreach ($offices as $off): ?>
                                                <option value="<?= $off['office_id']; ?>" <?= ($is_admin_staff && (int)$off['office_id'] === $assigned_office_id) ? 'selected' : ''; ?>>
                                                    <?= htmlspecialchars($off['office_name']); ?> (<?= htmlspecialchars($off['office_code']); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <?php if (!$is_admin_staff): ?>
                                        <div class="col-auto">
                                            <button type="button" id="btnReset" class="btn btn-outline-secondary btn-sm py-2 px-3 text-nowrap" title="Reset Office Filter">
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- OFFICE SERVICE COUNTERS TABLE CONTAINER -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">

                                <div class="card-header d-flex align-items-center justify-content-between">
                                    <div class="d-flex justify-content-between align-items-start align-items-md-center gap-3 w-100">

                                        <div class="d-flex align-items-center gap-3">
                                            <div class="bg-primary-subtle text-primary rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                                                <i class="bi bi-window-stack fs-4"></i>
                                            </div>

                                            <div>
                                                <div class="text-uppercase text-muted fw-bold tracking-wide small" style="font-size: 0.95rem;">
                                                    Office Service Counters & Windows
                                                </div>
                                                <div class="d-flex align-items-center text-muted small mt-0.5" style="font-size: 0.75rem;">
                                                    <i class="bi bi-info-circle me-1"></i> Configure service counters; assign many staff per window and one staff to many windows
                                                </div>
                                            </div>
                                        </div>

                                        <div class="d-flex align-items-start align-items-md-end gap-2 ms-md-auto">
                                            <?php render_header_button($action_buttons); ?>
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
                                                    <li>
                                                        <hr class="dropdown-divider">
                                                    </li>
                                                    <li><a class="dropdown-item" href="javascript:void(0)" id="export-print"><i class="bi bi-printer text-primary me-2"></i> Print Table</a></li>
                                                </ul>
                                            </div>

                                            <button type="button" id="btn-refresh" class="btn btn-action btn-sm d-inline-flex align-items-center">
                                                <i class="bi bi-arrow-clockwise me-1"></i>Refresh Data
                                            </button>
                                        </div>
                                    </div>

                                    <div id="tabulator-container" style="min-height: 600px;"></div>
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- CREATE COUNTER MODAL -->
                    <div class="modal fade" id="add_counter_modal" aria-hidden="true" data-bs-backdrop="static" aria-labelledby="addCounterModalLabel">
                        <div class="modal-dialog modal-dialog-centered modal-lg">
                            <div class="modal-content">
                                <div class="modal-header bg-light border-bottom py-3 px-4">
                                    <h5 class="modal-title" id="addCounterModalLabel">Create Service Counter</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form id="add_counter_form">
                                    <div class="modal-body">
                                        <div class="row p-3">
                                            <div class="col-12 border p-3 mb-3">
                                                <div class="row">
                                                    <h5><strong>Counter Details</strong></h5>
                                                    <hr>

                                                    <!-- Row 1: Office Location & Status -->
                                                    <div class="col-lg-6 mb-3">
                                                        <label class="form-label" for="add_office_id"><b>Office Location</b></label>
                                                        <select name="office_id" id="add_office_id" class="form-select" required <?= $is_admin_staff ? 'disabled' : ''; ?>>
                                                            <?php if (!$is_admin_staff): ?>
                                                                <option value="" selected disabled>Select Office</option>
                                                            <?php endif; ?>
                                                            <?php foreach ($offices as $off): ?>
                                                                <option value="<?= $off['office_id']; ?>" <?= ($is_admin_staff && (int)$off['office_id'] === $assigned_office_id) ? 'selected' : ''; ?>>
                                                                    <?= htmlspecialchars($off['office_name']); ?> (<?= htmlspecialchars($off['office_code']); ?>)
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <?php if ($is_admin_staff): ?>
                                                            <input type="hidden" name="office_id" id="add_hidden_office_id" value="<?= $assigned_office_id; ?>">
                                                        <?php endif; ?>
                                                    </div>

                                                    <div class="col-lg-6 mb-3">
                                                        <label class="form-label" for="add_flag_status"><b>Status</b></label>
                                                        <select name="flag_status" id="add_flag_status" class="form-select">
                                                            <option value="0" selected>Active</option>
                                                            <option value="1">Disabled</option>
                                                        </select>
                                                    </div>

                                                    <!-- Row 2: Counter / Window Name (Solo Column) -->
                                                    <div class="col-12 mb-3">
                                                        <label class="form-label" for="add_counter_name"><b>Counter / Window Name</b></label>
                                                        <input type="text" name="counter_name" class="form-control" id="add_counter_name" placeholder="e.g. Window 1, Counter A, Desk 2" required>
                                                    </div>

                                                    <!-- Row 3: Assigned Staff Members (Solo Column) -->
                                                    <div class="col-12 mb-2">
                                                        <label class="form-label" for="add_assigned_user_ids"><b>Assigned Staff Members</b> <small class="text-muted">(Optional — Multiple allowed)</small></label>
                                                        <select name="assigned_user_ids[]" id="add_assigned_user_ids" class="form-select" multiple="multiple" style="width: 100%;"></select>
                                                        <small class="text-muted" id="add_staff_helper">Multiple staff can share this window, and one staff member can serve multiple windows.</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-danger rounded-3" data-bs-dismiss="modal">Close</button>
                                        <button type="submit" class="btn btn-secondary rounded-3" id="btn_submit_add" name="actionSubmit" value="submitCounter">Submit</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- UPDATE COUNTER MODAL -->
                    <div class="modal fade" id="update_counter_modal" aria-hidden="true" data-bs-backdrop="static" aria-labelledby="updateCounterModalLabel">
                        <div class="modal-dialog modal-dialog-centered modal-lg">
                            <div class="modal-content">
                                <div class="modal-header bg-light border-bottom py-3 px-4">
                                    <h6 class="modal-title" id="updateCounterModalLabel">Update Service Counter</h6>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form id="update_counter_form" method="post" action="/">
                                    <div class="modal-body">
                                        <div class="row p-3">
                                            <div class="col-12 border p-3 mb-3">
                                                <div class="row">
                                                    <h5><strong>Counter Details</strong></h5>
                                                    <hr>

                                                    <input type="hidden" name="counter_id" id="u_counter_id">

                                                    <!-- Row 1: Office Location & Status -->
                                                    <div class="col-lg-6 mb-3">
                                                        <label class="form-label" for="u_office_id"><b>Office Location</b></label>
                                                        <select name="office_id" id="u_office_id" class="form-select" required <?= $is_admin_staff ? 'disabled' : ''; ?>>
                                                            <?php if (!$is_admin_staff): ?>
                                                                <option value="" selected disabled>Select Office</option>
                                                            <?php endif; ?>
                                                            <?php foreach ($offices as $off): ?>
                                                                <option value="<?= $off['office_id']; ?>" <?= ($is_admin_staff && (int)$off['office_id'] === $assigned_office_id) ? 'selected' : ''; ?>>
                                                                    <?= htmlspecialchars($off['office_name']); ?> (<?= htmlspecialchars($off['office_code']); ?>)
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <?php if ($is_admin_staff): ?>
                                                            <input type="hidden" name="office_id" id="u_hidden_office_id" value="<?= $assigned_office_id; ?>">
                                                        <?php endif; ?>
                                                    </div>

                                                    <div class="col-lg-6 mb-3">
                                                        <label class="form-label" for="u_flag_status"><b>Status</b></label>
                                                        <select name="flag_status" id="u_flag_status" class="form-select">
                                                            <option value="0">Active</option>
                                                            <option value="1">Disabled</option>
                                                        </select>
                                                    </div>

                                                    <!-- Row 2: Counter / Window Name (Solo Column) -->
                                                    <div class="col-12 mb-3">
                                                        <label class="form-label" for="u_counter_name"><b>Counter / Window Name</b></label>
                                                        <input type="text" name="counter_name" class="form-control" id="u_counter_name" placeholder="e.g. Window 1, Counter A, Desk 2" required>
                                                    </div>

                                                    <!-- Row 3: Assigned Staff Members (Solo Column) -->
                                                    <div class="col-12 mb-2">
                                                        <label class="form-label" for="u_assigned_user_ids"><b>Assigned Staff Members</b> <small class="text-muted">(Optional — Multiple allowed)</small></label>
                                                        <select name="assigned_user_ids[]" id="u_assigned_user_ids" class="form-select" multiple="multiple" style="width: 100%;"></select>
                                                        <small class="text-muted">Showing eligible staff of this office. A staff member can serve multiple windows.</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-danger rounded-3" data-bs-dismiss="modal">Close</button>
                                        <button type="submit" class="btn btn-secondary rounded-3" id="btn_submit_update" name="actionInfo" value="updateCounter">Update Information</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <?php include_once ADMIN_FOOTER_PATH; ?>
        </div>
    </div>
</body>

<?php include_once ADMIN_SCRIPT_PATH; ?>

<script>
    (function() {
        const AJAX_HANDLER       = "<?php echo BASE_URL; ?>admin/ajax/office-counters";
        const IS_ADMIN_STAFF     = <?php echo $is_admin_staff ? 'true' : 'false'; ?>;
        const ASSIGNED_OFFICE_ID = <?php echo (int) $assigned_office_id; ?>;
        const ALL_STAFF_USERS    = <?php echo json_encode($users, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        let tabulator = null;

        function escapeHtml(str) {
            return String(str || '')
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        function formatTimestampDisplay(rawTs) {
            if (!rawTs) return '—';
            const normalized = String(rawTs).trim().replace(' ', 'T');
            const dt = new Date(normalized);
            if (Number.isNaN(dt.getTime())) {
                return String(rawTs);
            }
            return dt.toLocaleString('en-US', {
                month: 'short',
                day: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function initStaffMultiSelect() {
            if ($.fn && typeof $.fn.select2 === 'function') {
                $('#add_assigned_user_ids').select2({
                    dropdownParent: $('#add_counter_modal'),
                    placeholder: '-- Select Staff Members (Optional) --',
                    allowClear: true,
                    width: '100%'
                });
                $('#u_assigned_user_ids').select2({
                    dropdownParent: $('#update_counter_modal'),
                    placeholder: '-- Select Staff Members (Optional) --',
                    allowClear: true,
                    width: '100%'
                });
            }
        }

        /* Formatters */
        function counterNameFormatter(cell) {
            const val = cell.getValue() || '';
            return `<div class="w-100 text-center"><span class="badge bg-light text-dark border font-monospace">${escapeHtml(val)}</span></div>`;
        }

        // Plain left-aligned list (normal text color, no badge/background)
        function staffFormatter(cell) {
            const val = cell.getValue();
            if (!val || String(val).trim() === '' || val === 'Unassigned') {
                return '<div class="w-100 text-start">Unassigned</div>';
            }
            const names = String(val).split(',').map(s => s.trim()).filter(Boolean);
            return `<div class="w-100 text-start">${names.map(name => `<div>${escapeHtml(name)}</div>`).join('')}</div>`;
        }

        function statusFormatter(cell) {
            return `<div class="w-100 text-center">${escapeHtml(cell.getValue())}</div>`;
        }

        function updatedAtFormatter(cell) {
            const rowData = cell.getRow().getData();
            const titleAttr = rowData.created_at ? ` title="Created: ${escapeHtml(rowData.created_at)}"` : '';
            return `<div class="w-100 text-center text-muted small"${titleAttr}>${escapeHtml(cell.getValue())}</div>`;
        }

        const colFilter = {
            headerFilter: "input",
            headerFilterFunc: "like",
            headerFilterParams: { allowEmpty: true }
        };

        function applyGlobalSearch(tableInstance) {
            const keyword = $('#global-search').val().trim().toLowerCase();
            if (!keyword) {
                tableInstance.clearFilter();
                return;
            }
            tableInstance.setFilter(function(data) {
                const office  = (data.office_name || '').toLowerCase();
                const code    = (data.office_code || '').toLowerCase();
                const counter = (data.counter_name || '').toLowerCase();
                const staff   = (data.assigned_staff_names || '').toLowerCase();
                const status  = (data.status_label || '').toLowerCase();
                const updated = (data.updated_at || '').toLowerCase();
                return office.includes(keyword) || code.includes(keyword) || counter.includes(keyword) || staff.includes(keyword) || status.includes(keyword) || updated.includes(keyword);
            });
        }

        /* Tabulator Table Setup */
        function initTabulatorTable() {
            tabulator = new Tabulator("#tabulator-container", {
                ajaxURL: AJAX_HANDLER,
                ajaxLoader: true,
                ajaxLoaderLoading: 'Fetching data from Database..',
                ajaxConfig: "POST",
                ajaxContentType: "form",
                ajaxParams: function() {
                    return {
                        action: 'fetch_counters',
                        office_id: IS_ADMIN_STAFF ? ASSIGNED_OFFICE_ID : $('#filterOffice').val()
                    };
                },
                ajaxResponse: function(url, params, response) {
                    return (response.data || []).map(r => Object.assign({}, r, {
                        office_label: `${r.office_name || ''} (${r.office_code || ''})`,
                        staff_label: r.assigned_staff_names && String(r.assigned_staff_names).trim() !== '' ? r.assigned_staff_names : 'Unassigned',
                        status_label: Number(r.flag_status) === 0 ? 'Active' : 'Disabled',
                        updated_at_label: formatTimestampDisplay(r.updated_at || r.created_at)
                    }));
                },
                index: "counter_id",
                initialSort: [
                    { column: "updated_at", dir: "desc" }
                ],
                layout: "fitColumns",
                resizableColumnFit: true,
                headerFilterPlaceholder: "Search",
                pagination: "local",
                paginationSize: 10,
                paginationSizeSelector: [10, 25, 50, 100, true],
                placeholder: `
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-inbox fs-2 d-block mb-2 opacity-50"></i>
                        <div class="fw-semibold">No service counters found</div>
                        <div class="small">Try adjusting your search keywords or office filter.</div>
                    </div>
                `,
                height: "600px",
                headerHozAlign: 'center',
                printAsHtml: true,
                printFormatter: false,
                printConfig: {
                    columnGroups: false,
                    rowGroups: false,
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
                        width: 180, 
                        headerSort: false,
                        download: false,
                        print: false,
                        vertAlign: 'middle',
                        hozAlign: 'center',
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const classBtnEdit = "btn btn-action d-inline-flex align-items-center justify-content-center px-2 py-1 me-1 gap-1 text-nowrap btn-update-row";
                            const classBtnDelete = "btn btn-action d-inline-flex align-items-center justify-content-center px-2 py-1 gap-1 text-nowrap btn-delete-row";

                            return `<div class="d-inline-flex align-items-center justify-content-center text-nowrap">
                                        <button type="button" class="${classBtnEdit}" data-id="${rowData.counter_id}" title="Update Information">
                                            <i class="bi bi-pencil-square"></i>
                                            <span style="font-size: 0.75rem;">Update</span>
                                        </button>
                                        <button type="button" class="${classBtnDelete}" data-id="${rowData.counter_id}" title="Delete Counter">
                                            <i class="bi bi-trash text-danger"></i>
                                            <span style="font-size: 0.75rem;">Delete</span>
                                        </button>
                                    </div>`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.closest('.btn-update-row')) {
                                openUpdateModal(cell.getRow().getData());
                            } else if (e.target.closest('.btn-delete-row')) {
                                const btn = e.target.closest('.btn-delete-row');
                                const counterId = parseInt(btn.getAttribute('data-id'), 10);
                                deleteCounter(counterId);
                            }
                        }
                    },
                    Object.assign({ title: "Office Location", field: "office_label", sorter: "string", minWidth: 250, vertAlign: 'middle', hozAlign: 'left',
                        formatter: (cell) => escapeHtml(cell.getValue()) }, colFilter),
                    Object.assign({ title: "Counter Name", field: "counter_name", formatter: counterNameFormatter, minWidth: 150, vertAlign: 'middle', hozAlign: 'center' }, colFilter),
                    Object.assign({ title: "Assigned Staff", field: "staff_label", formatter: staffFormatter, minWidth: 240, vertAlign: 'middle', hozAlign: 'left' }, colFilter),
                    {
                        title: "Status",
                        field: "status_label",
                        formatter: statusFormatter,
                        minWidth: 120,
                        vertAlign: 'middle',
                        hozAlign: 'center',
                        headerFilter: "select",
                        headerFilterLiveFilter: false,
                        headerFilterParams: {
                            values: {
                                "": "All",
                                "Active": "Active",
                                "Disabled": "Disabled"
                            }
                        }
                    },
                    Object.assign({
                        title: "Last Updated",
                        field: "updated_at_label",
                        sorter: function(a, b, aRow, bRow) {
                            const tA = Date.parse(String(aRow.getData().updated_at || aRow.getData().created_at || '').replace(' ', 'T')) || 0;
                            const tB = Date.parse(String(bRow.getData().updated_at || bRow.getData().created_at || '').replace(' ', 'T')) || 0;
                            return tA - tB;
                        },
                        formatter: updatedAtFormatter,
                        minWidth: 175,
                        vertAlign: 'middle',
                        hozAlign: 'center'
                    }, colFilter)
                ]
            });

            function resetAndReload() {
                $('#global-search').val('');
                $('#filterOffice').val(IS_ADMIN_STAFF ? String(ASSIGNED_OFFICE_ID) : '0');
                tabulator.clearFilter();
                tabulator.clearHeaderFilter();
                tabulator.setData();
            }

            // Re-apply global search text whenever AJAX data finishes loading
            tabulator.on("dataProcessed", function() {
                applyGlobalSearch(tabulator);
            });

            // Export Actions
            document.getElementById('export-csv')?.addEventListener('click', () => {
                tabulator.download("csv", "office_counters.csv");
            });

            document.getElementById('export-excel')?.addEventListener('click', () => {
                tabulator.download("xlsx", "office_counters.xlsx", { sheetName: "Counters" });
            });

            document.getElementById('export-json')?.addEventListener('click', () => {
                tabulator.download("json", "office_counters.json");
            });

            document.getElementById("export-print")?.addEventListener("click", function() {
                const tableTitle = 'Office Service Counters Report';
                const tableHtml = tabulator.getHtml("active", "print");
                const printWindow = window.open("", "_blank");

                printWindow.document.write(`<!DOCTYPE html><html><head><title>${tableTitle}</title><style>body {font-family: Arial, sans-serif;padding: 20px;color: #333;}table {width: 100%;border-collapse: collapse;margin-top: 10px;}th, td {border: 1px solid #ddd;padding: 8px 12px;text-align: left;font-size: 12px;}th {background-color: #f2f2f2;font-weight: bold;}tr:nth-child(even) {background-color: #f9f9f9;}@page {size: auto;margin: 15mm;}</style></head><body><h2>${tableTitle}</h2>${tableHtml}</body></html>`);

                printWindow.document.close();
                printWindow.focus();

                setTimeout(() => {
                    printWindow.print();
                    printWindow.close();
                }, 250);
            });

            // Filter Form & Office Handler
            $('#counterFilterForm').on('submit', function(e) {
                e.preventDefault();
                if (tabulator) tabulator.setData();
            });

            $('#filterOffice').on('change', function() {
                if (tabulator) tabulator.setData();
            });

            // Global Search Handler
            $('#global-search').on('input', function() {
                applyGlobalSearch(tabulator);
            });

            // Reset & Refresh Data Handlers
            $('#btnReset').on('click', resetAndReload);
            $('#btn-refresh').on('click', resetAndReload);
        }

        /**
         * Rebuild Select2 options based on the target office:
         * - When targetOfficeId > 0: strictly shows employees assigned to targetOfficeId
         * - When targetOfficeId === 0 (ADMIN with "-- All Campus Offices --"): shows all eligible employees across offices
         * - Staff already serving other windows stay selectable
         */
        function populateStaffDropdown(selector, targetOfficeId = 0, excludeCounterId = 0, preselectedIds = null) {
            const $select = $(selector);
            const currentSelected = Array.isArray(preselectedIds)
                ? preselectedIds.map(String)
                : ($select.val() || []).map(String);

            $select.empty();
            const validSelected = [];

            ALL_STAFF_USERS.forEach(usr => {
                const uid = Number(usr.id || 0);
                const staffOfficeId = Number(usr.staff_office_id || 0);
                const isCurrentlyAssignedHere = currentSelected.includes(String(uid));

                // Strictly filter by office when a specific office is selected
                if (targetOfficeId > 0 && staffOfficeId !== Number(targetOfficeId) && !isCurrentlyAssignedHere) {
                    return;
                }

                const fullName = `${usr.first_name || ''} ${usr.last_name || ''}`.trim();
                const officeTag = (targetOfficeId === 0 && usr.staff_office_code) ? ` [${usr.staff_office_code}]` : '';
                const label = `${fullName} (${usr.email || ''})${officeTag}`;
                if (isCurrentlyAssignedHere) {
                    validSelected.push(String(uid));
                }

                $select.append(new Option(label, String(uid), isCurrentlyAssignedHere, isCurrentlyAssignedHere));
            });

            $select.val(validSelected).trigger('change');
        }

        /* Initialize Select2 & Tabulator Table */
        initStaffMultiSelect();
        initTabulatorTable();

        // Re-filter eligible staff when Office changes inside Add/Update modals
        $('#add_office_id').on('change', function() {
            populateStaffDropdown('#add_assigned_user_ids', Number($(this).val() || 0), 0);
        });

        $('#u_office_id').on('change', function() {
            const counterId = Number($('#u_counter_id').val() || 0);
            populateStaffDropdown('#u_assigned_user_ids', Number($(this).val() || 0), counterId);
        });

        /*** add counter modal ***/
        $('.add-counter-btn').on('click', function() {
            $('#add_counter_modal').modal('show');
            $('#add_counter_modal').find('form').trigger('reset');

            const filterOfficeId = IS_ADMIN_STAFF
                ? ASSIGNED_OFFICE_ID
                : Number($('#filterOffice').val() || 0);

            if (IS_ADMIN_STAFF && ASSIGNED_OFFICE_ID > 0) {
                $('#add_office_id').val(String(ASSIGNED_OFFICE_ID));
                $('#add_hidden_office_id').val(String(ASSIGNED_OFFICE_ID));
            } else if (filterOfficeId > 0) {
                // Pre-select the office currently chosen in the top filter dropdown
                $('#add_office_id').val(String(filterOfficeId));
            } else {
                $('#add_office_id').val('');
            }

            populateStaffDropdown('#add_assigned_user_ids', filterOfficeId, 0, []);
        });

        /*** submit add counter form ***/
        $("#add_counter_form").on('submit', async function(e) {
            e.preventDefault();

            const isConfirmed = await notifyConfirm(
                'Confirm Submission',
                'Are you sure you want to save this service counter?',
                'Yes, save it!',
                'question'
            );
            if (!isConfirmed) return;

            const $form = $(this);
            const $submitBtn = $form.find(':submit');
            const $inputs = $form.find(':input, :button');

            let formData = $form.serializeArray();
            formData.push({
                name: "actionSubmit",
                value: "submitCounter"
            });

            $.ajax({
                url: AJAX_HANDLER,
                method: "POST",
                data: formData,
                dataType: "json",
                beforeSend: function() {
                    showLoader('Saving information...');
                    $submitBtn.data('orig-text', $submitBtn.html());
                    $submitBtn.html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Loading..');
                    $inputs.prop("disabled", true);
                },
                complete: function() {
                    hideLoader();
                    $submitBtn.html($submitBtn.data('orig-text') || 'Submit');
                    $inputs.prop("disabled", false);
                    if (IS_ADMIN_STAFF) {
                        $('#add_office_id').prop('disabled', true);
                    }
                },
                success: function(output) {
                    if (output.msg_status === true) {
                        if (tabulator && typeof tabulator.setData === 'function') tabulator.setData();
                        $("#add_counter_modal").modal('hide');
                        showSuccess(output.msg_response);
                    } else if (output.msg_status === false) {
                        showError(output.msg_response);
                    } else {
                        showError("Request error, please try again");
                    }
                },
                error: function(xhr, status, error) {
                    showError(status + "::" + error);
                }
            });
        });

        /*** open update modal ***/
        function openUpdateModal(data) {
            const targetOfficeId = IS_ADMIN_STAFF ? ASSIGNED_OFFICE_ID : Number(data.office_id || 0);
            const assignedIds = Array.isArray(data.assigned_user_ids)
                ? data.assigned_user_ids.map(String)
                : [];

            document.getElementById("update_counter_modal").querySelector("#u_counter_id").value = data.counter_id;
            document.getElementById("update_counter_modal").querySelector("#u_office_id").value = targetOfficeId;
            if (IS_ADMIN_STAFF && document.getElementById("u_hidden_office_id")) {
                document.getElementById("u_hidden_office_id").value = ASSIGNED_OFFICE_ID;
            }
            document.getElementById("update_counter_modal").querySelector("#u_counter_name").value = data.counter_name;
            document.getElementById("update_counter_modal").querySelector("#u_flag_status").value = data.flag_status;

            // Populate staff strictly for this row's specific office_id
            populateStaffDropdown('#u_assigned_user_ids', targetOfficeId, data.counter_id, assignedIds);

            $("#update_counter_modal").modal("show");
        }

        /*** submit update counter form ***/
        $('#update_counter_form').on('submit', async function(e) {
            e.preventDefault();

            const isConfirmed = await notifyConfirm(
                'Confirm Submission',
                'Are you sure you want to update this service counter?',
                'Yes, update it!',
                'question'
            );
            if (!isConfirmed) return;

            const $form = $(this);
            const $submitBtn = $form.find(':submit');
            const $inputs = $form.find(':input, :button');

            let formData = $form.serializeArray();
            formData.push({
                name: "actionUpdate",
                value: "submitUpdateCounter"
            });

            $.ajax({
                url: AJAX_HANDLER,
                type: 'POST',
                data: formData,
                dataType: "json",
                beforeSend: function() {
                    showLoader('Updating information...');
                    $submitBtn.data('orig-text', $submitBtn.html());
                    $submitBtn.html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Loading..');
                    $inputs.prop("disabled", true);
                },
                complete: function() {
                    hideLoader();
                    $submitBtn.html($submitBtn.data('orig-text') || 'Update Information');
                    $inputs.prop("disabled", false);
                    if (IS_ADMIN_STAFF) {
                        $('#u_office_id').prop('disabled', true);
                    }
                },
                success: function(output) {
                    if (output && output.msg_status === true) {
                        if (tabulator && typeof tabulator.setData === 'function') tabulator.setData();
                        $("#update_counter_modal").modal('hide');
                        showSuccess(output.msg_response);
                    } else if (output && output.msg_status === false) {
                        showError(output.msg_response);
                    } else {
                        showError("Request error, please try again");
                    }
                },
                error: function(xhr, status, error) {
                    showError(status + "::" + error);
                }
            });
        });

        /*** delete counter function ***/
        async function deleteCounter(counterId) {
            if (!counterId) return;

            const isConfirmed = await notifyConfirm(
                'Confirm Deletion',
                'Are you sure you want to permanently delete this service counter? This action cannot be undone.',
                'Yes, delete it!',
                'warning'
            );
            if (!isConfirmed) return;

            $.ajax({
                url: AJAX_HANDLER,
                type: 'POST',
                data: {
                    action: 'delete_counter',
                    counter_id: counterId
                },
                dataType: "json",
                beforeSend: function() {
                    showLoader('Deleting service counter...');
                },
                complete: function() {
                    hideLoader();
                },
                success: function(output) {
                    if (output && output.msg_status === true) {
                        if (tabulator && typeof tabulator.setData === 'function') tabulator.setData();
                        showSuccess(output.msg_response);
                    } else if (output && output.msg_status === false) {
                        showError(output.msg_response);
                    } else {
                        showError("Request error, please try again");
                    }
                },
                error: function(xhr, status, error) {
                    showError(status + "::" + error);
                }
            });
        }

    })();
</script>

</html>