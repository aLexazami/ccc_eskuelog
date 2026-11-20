<?php
// src/Views/Admin/transaction_types.php

global $db_connect;
$db_connect  = $db_connect ?? null;
$g_user_role = $g_user_role ?? '';

require_once HELPER;
require_once ISLOGIN;
require_once API_CONNECT;
require_once ADMIN_PAGE_HEADER_PATH;

$title_page   = "Services & Sub-Transactions Management";
$active_links = [['label' => $title_page, 'url' => '']];
$page_header  = render_page_header((string) $title_page, $active_links) ?? [];

if (!function_exists('tt_resolve_user_id')) {
    /**
     * Resolve the logged-in user's ID. The session wrapper does not always
     * expose it under the same key, so every known key is tried before the
     * user_log fallback (same strategy as queue_handler.php).
     */
    function tt_resolve_user_id($session_class, $db_connect, $hint = 0): int
    {
        $userId = (int)$hint;
        $keys = ['user_id', 'id', 'sys_user_id'];

        if ($userId <= 0 && is_object($session_class) && method_exists($session_class, 'getValue')) {
            foreach ($keys as $key) {
                try {
                    $userId = (int)$session_class->getValue($key);
                } catch (Throwable $e) {
                    $userId = 0;
                }
                if ($userId > 0) {
                    break;
                }
            }
        }

        if ($userId <= 0) {
            foreach ($keys as $key) {
                $userId = (int)($_SESSION[$key] ?? 0);
                if ($userId > 0) {
                    break;
                }
            }
        }

        if ($userId <= 0 && $db_connect instanceof mysqli && session_id() !== '') {
            try {
                $sessionId = session_id();
                $like = '%' . $sessionId . '%';
                $stmt = $db_connect->prepare("
                    SELECT user_id FROM user_log
                    WHERE session_id = ? OR token_id LIKE ?
                    ORDER BY user_log_id DESC LIMIT 1
                ");
                $stmt->bind_param('ss', $sessionId, $like);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                $userId = (int)($row['user_id'] ?? 0);
            } catch (Throwable $e) {
                $userId = 0;
            }
        }

        return max(0, $userId);
    }
}

if (!function_exists('tt_normalize_role')) {
    /**
     * Role names may arrive as text in any case, or as the numeric code.
     */
    function tt_normalize_role($role): string
    {
        $map  = ['1' => 'ADMIN', '2' => 'ADMIN_STAFF', '3' => 'STATION_ADMIN', '4' => 'USER'];
        $role = strtoupper(trim((string)$role));

        return $map[$role] ?? $role;
    }
}

$system_auth_login = $session_class->getValue(SYSTEM_ACCESS[SYSTEM_ACCESS_NAME]['auth']);
$current_role  = tt_normalize_role($g_user_role);
$allowed_roles = ['ADMIN', 'ADMIN_STAFF'];
if (!in_array($current_role, $allowed_roles, true) && !($system_auth_login == $g_public_key)) {
    header("location: " . SYSTEM_ACCESS[SYSTEM_ACCESS_NAME]['link']['main']);
    exit();
}

$is_admin_staff     = ($current_role === 'ADMIN_STAFF');
$assigned_office_id = 0;

if ($is_admin_staff && $db_connect) {
    $current_user_id = tt_resolve_user_id($session_class, $db_connect, $g_user_id ?? 0);
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

$offices = [];
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
        }
    } else {
        $stmtOffices = $db_connect->query("SELECT office_id, office_code, office_name FROM office WHERE flag_status = 0 ORDER BY office_name ASC");
        if ($stmtOffices) {
            while ($row = $stmtOffices->fetch_assoc()) {
                $offices[] = $row;
            }
        }
    }
}

$action_buttons = [
    [
        'label' => 'Add Transaction Service',
        'icon'  => 'bi bi-plus-circle',
        'class' => 'add-type-btn',
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

        .sub-concern-card {
            border: 1px solid #dee2e6;
            border-left: 4px solid #1572e8;
            border-radius: 0.5rem;
            background-color: #f8fafc;
        }
        .detail-builder-box {
            background-color: #ffffff;
            border: 1px dashed #94a3b8;
            border-radius: 0.5rem;
        }
        /* Individual detail field card */
        .detail-item-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 0.75rem 0.875rem;
        }
        .detail-field-col-label {
            display: block;
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 0.25rem;
        }
        /* Visually distinct Dropdown Choices container */
        .detail-choices-wrapper {
            background-color: #ffffff;
            border: 1px solid #cbd5e1;
            border-left: 3px solid #1572e8;
            border-radius: 0.45rem;
            padding: 0.75rem;
            margin-top: 0.75rem;
        }
        .choice-number-label {
            min-width: 36px;
            justify-content: center;
            font-weight: 700;
            background-color: #eef2ff;
            color: #1d4ed8;
        }
        .stepper-group .btn { width: 36px; font-weight: 700; }
        .stepper-group input { text-align: center; font-weight: 600; }
        .prefix-badge {
            min-width: 38px;
            justify-content: center;
            font-weight: 700;
            background-color: #eef2ff;
            color: #1d4ed8;
        }
        /* Fix modal scrolling and height constraints for dynamic elements */
        #unified_service_modal .modal-body {
            max-height: calc(100vh - 200px);
            overflow-y: auto;
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
        #tabulator-container .tabulator-row.svc-group-start {
            border-top: 2px solid #cbd5e1;
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

        .detail-cell-list {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
            padding: 0.15rem 0;
        }
        .detail-cell-block {
            line-height: 1.35;
        }
        .detail-cell-title {
            font-weight: 600;
            color: #1e293b;
        }
        .detail-cell-choices {
            margin-top: 0.15rem;
            padding-left: 0.95rem;
            color: #475569;
            font-size: 0.78rem;
        }
        .detail-cell-choice-item {
            line-height: 1.3;
        }
        .window-cell-name { font-weight: 600; color: #1e293b; }
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include_once ADMIN_SIDEBAR_PATH; ?>
        <div class="main-panel">
            <?php include_once ADMIN_HEADER_PATH; ?>

            <div class="container">
                <div class="page-inner px-2 px-sm-3">
                    <?php echo $page_header; ?>

                    <!-- SEPARATE OFFICE FILTER CONTAINER -->
                    <div class="card filter-card mb-4">
                        <div class="card-body p-3 p-sm-4">
                            <form id="serviceFilterForm">
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

                    <!-- TRANSACTION SERVICES TABLE CONTAINER -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header d-flex align-items-center justify-content-between">
                                    <div class="d-flex justify-content-between align-items-start align-items-md-center gap-3 w-100">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="bg-primary-subtle text-primary rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                                                <i class="bi bi-diagram-3-fill fs-4"></i>
                                            </div>
                                            <div>
                                                <div class="text-uppercase text-muted fw-bold tracking-wide small" style="font-size: 0.95rem;">
                                                    Transaction Services, Sub-Transactions & Form Details
                                                </div>
                                                <div class="d-flex align-items-center text-muted small mt-0.5" style="font-size: 0.75rem;">
                                                    <i class="bi bi-info-circle me-1"></i> Configure primary office services, optional sub-transactions, and dynamic detail fields
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
                                        <!-- Global Search (No label, placeholder="Search", slightly taller) -->
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

                                    <div id="tabulator-container" style="min-height: 600px;"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- UNIFIED CREATE / UPDATE MODAL -->
                    <div class="modal fade" id="unified_service_modal" aria-hidden="true" data-bs-backdrop="static" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
                            <div class="modal-content">
                                <div class="modal-header bg-light border-bottom py-3 px-4">
                                    <h5 class="modal-title fw-bold" id="unifiedModalTitle">
                                        <i class="bi bi-plus-circle text-primary me-2"></i>Create Transaction Service
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>

                                <form id="unified_service_form">
                                    <input type="hidden" name="type_id" id="form_type_id" value="0">

                                    <div class="modal-body p-4">
                                        <!-- STEP 1: PRIMARY TRANSACTION SERVICE DETAILS -->
                                        <div class="border rounded-3 p-3 mb-4 bg-white shadow-sm">
                                            <h6 class="fw-bold text-primary mb-3">
                                                <i class="bi bi-1-circle-fill me-1"></i> Primary Transaction Service Details
                                            </h6>
                                            <div class="row g-3">
                                                <!-- Row 1: Selection of Office | Service Status -->
                                                <div class="col-lg-6">
                                                    <label class="form-label fw-bold" for="form_office_id">Selection of Office</label>
                                                    <select name="office_id" id="form_office_id" class="form-select" required <?= $is_admin_staff ? 'disabled' : ''; ?>>
                                                        <?php if (!$is_admin_staff): ?>
                                                            <option value="" selected disabled>-- Select Campus Office --</option>
                                                        <?php endif; ?>
                                                        <?php foreach ($offices as $off): ?>
                                                            <option value="<?= $off['office_id']; ?>" <?= ($is_admin_staff && (int)$off['office_id'] === $assigned_office_id) ? 'selected' : ''; ?>>
                                                                <?= htmlspecialchars($off['office_name']); ?> (<?= htmlspecialchars($off['office_code']); ?>)
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <?php if ($is_admin_staff): ?>
                                                        <input type="hidden" name="office_id" id="hidden_assigned_office_id" value="<?= $assigned_office_id; ?>">
                                                    <?php endif; ?>
                                                </div>

                                                <div class="col-lg-6">
                                                    <label class="form-label fw-bold" for="form_flag_status">Service Status</label>
                                                    <select name="flag_status" id="form_flag_status" class="form-select">
                                                        <option value="0" selected>Active</option>
                                                        <option value="1">Disabled</option>
                                                    </select>
                                                </div>

                                                <!-- Row 2: Transaction Service Name (full width) -->
                                                <div class="col-12">
                                                    <label class="form-label fw-bold" for="form_transaction_name">Transaction Service Name</label>
                                                    <input type="text" name="transaction_name" class="form-control" id="form_transaction_name" placeholder="e.g., Transcript Request, ID Validation" required>
                                                </div>

                                                <!-- Row 3: Add Sub-Transactions (Optional) (full width) -->
                                                <div class="col-12">
                                                    <label class="form-label fw-bold" for="form_has_sub_concerns">Add Sub-Transactions? (Optional)</label>
                                                    <select name="has_sub_concerns" id="form_has_sub_concerns" class="form-select border-primary">
                                                        <option value="no" selected>No — Standalone Service Only</option>
                                                        <option value="yes">Yes — Configure Sub-Transactions</option>
                                                    </select>
                                                </div>

                                                <!-- Row 4: Estimated Duration | Assigned Window (primary service only) -->
                                                <div class="col-lg-6" id="primary_duration_wrapper">
                                                    <label class="form-label fw-bold" for="form_estimated_minutes">Estimated Duration (Minutes)</label>
                                                    <div class="input-group stepper-group">
                                                        <button type="button" class="btn btn-outline-secondary" id="btn_duration_dec">-</button>
                                                        <input type="number" name="estimated_minutes" class="form-control" id="form_estimated_minutes" value="5" min="1" max="180">
                                                        <button type="button" class="btn btn-outline-primary" id="btn_duration_inc">+</button>
                                                    </div>
                                                </div>

                                                <div class="col-lg-6" id="primary_window_wrapper">
                                                    <label class="form-label fw-bold" for="form_counter_id">Assigned Window <span class="text-danger">*</span></label>
                                                    <select name="counter_id" id="form_counter_id" class="form-select counter-select">
                                                        <option value="">-- Select Office First --</option>
                                                    </select>
                                                    <small class="text-muted" id="primary_window_hint">Windows come from Office Service Counters.</small>
                                                </div>
                                            </div>

                                            <!-- DIRECT DETAILS SECTION (Shown when has_sub_concerns === 'no') -->
                                            <div id="direct_details_wrapper" class="mt-3 pt-3 border-top">
                                                <div class="d-flex align-items-center justify-content-between">
                                                    <span class="small text-muted">
                                                        <i class="bi bi-ui-checks-grid me-1"></i> Need extra form fields directly for this primary service?
                                                    </span>
                                                    <button type="button" class="btn btn-sm btn-outline-primary" id="btn_toggle_direct_details">
                                                        <i class="bi bi-plus-square me-1"></i> Add Details
                                                    </button>
                                                </div>
                                                <div id="direct_details_mount" class="mt-2"></div>
                                            </div>
                                        </div>

                                        <!-- STEP 2: DYNAMIC SUB-TRANSACTIONS SECTION (Shown when has_sub_concerns === 'yes') -->
                                        <div id="sub_concerns_section" class="border rounded-3 p-3 bg-white shadow-sm d-none">
                                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 border-bottom pb-3 mb-3">
                                                <div>
                                                    <h6 class="fw-bold text-primary mb-0">
                                                        <i class="bi bi-2-circle-fill me-1"></i> Sub-Transactions Configuration
                                                    </h6>
                                                    <small class="text-muted">Set the number of sub-transactions, name each one, set their estimated time, assign their own Window, and optionally add details.</small>
                                                </div>

                                                <div class="d-flex align-items-center gap-2">
                                                    <label class="fw-bold small mb-0 text-nowrap">How many sub-transactions?</label>
                                                    <div class="input-group input-group-sm stepper-group">
                                                        <button type="button" class="btn btn-outline-secondary" id="btn_sub_dec">-</button>
                                                        <input type="number" id="sub_concern_count" class="form-control" value="1" min="1" max="30">
                                                        <button type="button" class="btn btn-outline-primary" id="btn_sub_inc">+</button>
                                                    </div>
                                                </div>
                                            </div>

                                            <div id="dynamic_sub_concerns_list" class="d-flex flex-column gap-3"></div>
                                        </div>
                                    </div>

                                    <div class="modal-footer bg-light">
                                        <button type="button" class="btn btn-danger rounded-3" data-bs-dismiss="modal">Close</button>
                                        <button type="submit" class="btn btn-primary rounded-3 px-4" id="btn_submit_unified">
                                            <i class="bi bi-check-circle me-1"></i> Save Service Configuration
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- PREVIEW STRUCTURE MODAL -->
                    <div class="modal fade" id="preview_structure_modal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-lg">
                            <div class="modal-content">
                                <div class="modal-header bg-light">
                                    <h5 class="modal-title fw-bold" id="previewModalTitle">Service Structure Preview</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body" id="previewModalBody"></div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Close</button>
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
    (function () {
        const AJAX_HANDLER       = "<?php echo BASE_URL; ?>admin/ajax/transaction-types";
        const IS_ADMIN_STAFF     = <?php echo $is_admin_staff ? 'true' : 'false'; ?>;
        const ASSIGNED_OFFICE_ID = <?php echo (int) $assigned_office_id; ?>;
        let tabulator = null;
        let isEditMode = false;

        function escapeHtml(str) {
            return String(str ?? '')
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // =====================================================================
        // WINDOWS (COUNTERS) — fetched per selected office
        // =====================================================================
        let officeCounters = [];
        let counterRequestSeq = 0;

        function counterLabel(c) {
            return c.counter_name || '';
        }

        function counterOptionsHtml(selectedId) {
            const sel = parseInt(selectedId, 10) || 0;
            const effectiveOfficeId = IS_ADMIN_STAFF ? ASSIGNED_OFFICE_ID : (parseInt($('#form_office_id').val(), 10) || 0);
            if (!effectiveOfficeId) {
                return '<option value="">-- Select Office First --</option>';
            }
            if (!officeCounters.length) {
                return '<option value="">-- No active Window for this office --</option>';
            }
            return '<option value="">-- Select Window --</option>' + officeCounters.map(c =>
                `<option value="${c.id}" ${c.id === sel ? 'selected' : ''}>${escapeHtml(counterLabel(c))}</option>`
            ).join('');
        }

        // Rebuild every Window dropdown; keep a selection only if it is still valid for the office
        function refreshCounterSelects() {
            $('#unified_service_modal .counter-select').each(function () {
                $(this).html(counterOptionsHtml($(this).val()));
            });
            const effectiveOfficeId = IS_ADMIN_STAFF ? ASSIGNED_OFFICE_ID : (parseInt($('#form_office_id').val(), 10) || 0);
            const noWin = effectiveOfficeId && !officeCounters.length;
            $('#primary_window_hint').text(noWin
                ? 'This office has no active Window yet. Create one in Office Service Counters first.'
                : 'Windows come from Office Service Counters.')
                .toggleClass('text-danger', !!noWin).toggleClass('text-muted', !noWin);
        }

        function loadOfficeCounters(officeId) {
            const seq = ++counterRequestSeq;
            officeId = IS_ADMIN_STAFF ? ASSIGNED_OFFICE_ID : (parseInt(officeId, 10) || 0);
            if (!officeId) {
                officeCounters = [];
                refreshCounterSelects();
                return $.Deferred().resolve().promise();
            }
            return $.ajax({
                url: AJAX_HANDLER,
                method: 'POST',
                data: { action: 'fetch_office_counters', office_id: officeId },
                dataType: 'json'
            }).then(res => {
                if (seq !== counterRequestSeq) return; // a newer office selection won
                officeCounters = (res && res.success && Array.isArray(res.data)) ? res.data : [];
                refreshCounterSelects();
            }, () => {
                if (seq !== counterRequestSeq) return;
                officeCounters = [];
                refreshCounterSelects();
                showError('Unable to load the Windows for this office.');
            });
        }

        $('#form_office_id').on('change', function () {
            // Old selections belong to the previous office; clear them
            $('#unified_service_modal .counter-select').val('');
            officeCounters = [];
            refreshCounterSelects();
            loadOfficeCounters($(this).val());
        });

        // =====================================================================
        // ESTIMATED DURATION STEPPER HANDLERS (PRIMARY SERVICE)
        // =====================================================================
        $('#btn_duration_inc').on('click', () => {
            let val = parseInt($('#form_estimated_minutes').val(), 10) || 1;
            $('#form_estimated_minutes').val(Math.min(180, val + 1));
        });
        $('#btn_duration_dec').on('click', () => {
            let val = parseInt($('#form_estimated_minutes').val(), 10) || 1;
            $('#form_estimated_minutes').val(Math.max(1, val - 1));
        });

        // =====================================================================
        // REUSABLE DETAIL BUILDER COMPONENT (Works for Direct & Sub-Transactions)
        // =====================================================================
        const DETAIL_FORMATS = {
            INPUT: [
                ['CHAR', 'CHAR (Text / Characters)'],
                ['INT',  'INT (Numeric / Integer)'],
                ['VAR',  'VAR (Alphanumeric / Variable)']
            ],
            SELECT: [
                ['numeric',     'Numeric (1., 2., 3.)'],
                ['lower_alpha', 'Lowercase Alpha (a., b., c.)'],
                ['upper_alpha', 'Uppercase Alpha (A., B., C.)']
            ]
        };
        const DETAIL_MAX = 20;

        function detailFormatOptions(mode, selected) {
            return (DETAIL_FORMATS[mode] || DETAIL_FORMATS.INPUT).map(([v, l]) =>
                `<option value="${v}" ${v === selected ? 'selected' : ''}>${l}</option>`
            ).join('');
        }

        const DETAIL_CHOICE_MIN = 2;
        const DETAIL_CHOICE_MAX = 50;

        function getChoicePrefix(format, index) {
            if (format === 'lower_alpha') {
                return String.fromCharCode(97 + (index % 26)) + '.';
            }
            if (format === 'upper_alpha') {
                return String.fromCharCode(65 + (index % 26)) + '.';
            }
            return (index + 1) + '.';
        }

        function createChoiceRow(prefix, fieldIndex, choiceIndex, value = '', format = 'numeric', choiceId = 0) {
            const safeChoiceId = parseInt(choiceId, 10) || 0;
            return $(`
                <div class="input-group input-group-sm mb-2 detail-choice-row">
                    <span class="input-group-text choice-number-label">
                        ${getChoicePrefix(format, choiceIndex)}
                    </span>

                    <input type="hidden"
                           name="${prefix}[fields][${fieldIndex}][choices][${choiceIndex}][id]"
                           class="detail-choice-id"
                           value="${safeChoiceId}">

                    <input type="text"
                        name="${prefix}[fields][${fieldIndex}][choices][${choiceIndex}][value]"
                        class="form-control detail-choice-input"
                        maxlength="100"
                        placeholder="Enter option ${choiceIndex + 1}"
                        value="${escapeHtml(value)}"
                        required>

                    <button type="button"
                            class="btn btn-outline-danger remove-detail-choice-btn"
                            title="Remove Choice"
                            ${choiceIndex < 2 ? 'disabled' : ''}>
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            `);
        }

        function renderChoiceRows($row, choices = [], choiceIds = []) {
            const prefix = $row.closest('.detail-builder-box').attr('data-name-prefix');
            const fieldIndex = $row.index();
            const $list = $row.find('.detail-choice-list');

            if (!Array.isArray(choices) || choices.length < 2) {
                choices = ['', ''];
                choiceIds = [0, 0];
            }

            choices = choices.slice(0, DETAIL_CHOICE_MAX);
            $list.empty();

            const format = $row.find('.detail-format-select').val() || 'numeric';
            choices.forEach((choice, index) => {
                const cId = Array.isArray(choiceIds) ? (parseInt(choiceIds[index], 10) || 0) : 0;
                $list.append(
                    createChoiceRow(prefix, fieldIndex, index, choice, format, cId)
                );
            });

            reindexChoiceRows($row);
        }

        function reindexChoiceRows($row) {
            const prefix = $row.closest('.detail-builder-box').attr('data-name-prefix');
            const fieldIndex = $row.index();
            const format = $row.find('.detail-format-select').val();

            const total = $row.find('.detail-choice-row').length;
            $row.find('.detail-choice-row').each(function (index) {
                $(this).find('.choice-number-label')
                    .text(getChoicePrefix(format, index));

                $(this).find('.detail-choice-id')
                    .attr('name', `${prefix}[fields][${fieldIndex}][choices][${index}][id]`);

                $(this).find('.detail-choice-input')
                    .attr('name', `${prefix}[fields][${fieldIndex}][choices][${index}][value]`)
                    .attr('placeholder', `Enter option ${index + 1}`)
                    .prop('required', true)
                    .removeClass('is-invalid');

                $(this).find('.remove-detail-choice-btn')
                    .prop('disabled', total <= DETAIL_CHOICE_MIN);
            });

            $row.find('.choice-count-input').val(total);
            $row.find('.choice-count-badge').text(`${total} choices`);
            $row.find('.btn-choice-dec').prop('disabled', total <= DETAIL_CHOICE_MIN);
            $row.find('.btn-choice-inc').prop('disabled', total >= DETAIL_CHOICE_MAX);
        }

        function createDetailRow(prefix, i, f) {
            const fieldId   = (f && f.id) ? (parseInt(f.id, 10) || 0) : 0;
            const mode      = f && f.mode === 'SELECT' ? 'SELECT' : 'INPUT';
            const format    = f && f.format ? f.format : (mode === 'SELECT' ? 'numeric' : 'CHAR');
            const title     = f && f.title ? f.title : '';
            const choices   = Array.isArray(f && f.choices) && f.choices.length >= 2
                ? f.choices.slice(0, DETAIL_CHOICE_MAX)
                : ['', ''];
            const choiceIds = Array.isArray(f && f.choice_ids)
                ? f.choice_ids.slice(0, DETAIL_CHOICE_MAX)
                : [];
            const initialChoiceCount = Math.max(2, choices.length);

            const $row = $(`
                <div class="detail-item-card detail-item-row mb-2">
                    <input type="hidden"
                           name="${prefix}[fields][${i}][id]"
                           class="detail-item-id"
                           value="${fieldId}">

                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="badge bg-primary-subtle text-primary border detail-field-badge">
                            Detail Field ${i + 1}
                        </span>
                        <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 remove-detail-row-btn" title="Remove Detail Field">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>

                    <div class="row g-2 align-items-start">
                        <div class="col-12 col-md-4">
                            <label class="detail-field-col-label">Data Type Option</label>
                            <select name="${prefix}[fields][${i}][mode]" class="form-select form-select-sm detail-mode-select">
                                <option value="INPUT" ${mode === 'INPUT' ? 'selected' : ''}>1. Input Data Type</option>
                                <option value="SELECT" ${mode === 'SELECT' ? 'selected' : ''}>2. Select Data Type</option>
                            </select>
                        </div>

                        <div class="col-12 col-md-3">
                            <label class="detail-field-col-label">Input Format Type</label>
                            <select name="${prefix}[fields][${i}][format]" class="form-select form-select-sm detail-format-select">
                                ${detailFormatOptions(mode, format)}
                            </select>
                        </div>

                        <div class="col-12 col-md-5">
                            <label class="detail-field-col-label">Question / Field Title <span class="text-danger">*</span></label>
                            <input type="text"
                                   name="${prefix}[fields][${i}][title]"
                                   class="form-control form-control-sm detail-title-field"
                                   maxlength="100"
                                   placeholder="e.g., Type of Document, Applicant's Full Name"
                                   value="${escapeHtml(title)}">
                        </div>
                    </div>

                    <div class="detail-choices-wrapper ${mode === 'SELECT' ? '' : 'd-none'}">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                            <div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="small fw-bold text-dark">
                                        <i class="bi bi-list-ul text-primary me-1"></i>Dropdown Choices <span class="text-danger">*</span>
                                    </span>
                                    <span class="badge bg-primary-subtle text-primary border choice-count-badge">
                                        ${initialChoiceCount} choices
                                    </span>
                                </div>
                                <small class="text-muted d-block">Enter the available options for this question.</small>
                            </div>

                            <div class="input-group input-group-sm stepper-group" style="width: 125px;">
                                <button type="button" class="btn btn-outline-secondary btn-choice-dec">-</button>
                                <input type="number" class="form-control choice-count-input"
                                       value="${initialChoiceCount}"
                                       min="2" max="50" readonly>
                                <button type="button" class="btn btn-outline-primary btn-choice-inc">+</button>
                            </div>
                        </div>

                        <div class="detail-choice-list"></div>

                        <small class="text-muted">
                            Minimum 2 choices, maximum 50 choices (100 characters each).
                        </small>
                    </div>
                </div>
            `);

            if (mode === 'SELECT') {
                renderChoiceRows($row, choices, choiceIds);
            }
            return $row;
        }

        function createDetailBuilderBox(namePrefix, prefill = null, startVisible = false) {
            const hasDetails = Boolean(prefill && prefill.has_details) || startVisible;

            const $box = $(`
                <div class="detail-builder-box p-3 mt-2 ${hasDetails ? '' : 'd-none'}" data-name-prefix="${namePrefix}">
                    <input type="hidden" name="${namePrefix}[enabled]" class="detail-enabled-flag" value="${hasDetails ? '1' : '0'}">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <label class="form-label small fw-bold mb-0">How many details needed?</label>
                        <div class="input-group input-group-sm stepper-group" style="max-width: 150px;">
                            <button type="button" class="btn btn-outline-secondary btn-detail-dec">-</button>
                            <input type="number" class="form-control detail-count-input" value="1" min="1" max="${DETAIL_MAX}">
                            <button type="button" class="btn btn-outline-primary btn-detail-inc">+</button>
                        </div>
                    </div>
                    <div class="detail-fields-container"></div>
                </div>
            `);

            const fields = (prefill && Array.isArray(prefill.fields) && prefill.fields.length) ? prefill.fields : [{}];
            fields.slice(0, DETAIL_MAX).forEach((f, i) => {
                $box.find('.detail-fields-container').append(createDetailRow(namePrefix, i, f));
            });
            reindexDetailRows($box);
            $box.find('.detail-count-input').val(Math.min(fields.length, DETAIL_MAX));
            return $box;
        }

        function reindexDetailRows($box) {
            const prefix = $box.attr('data-name-prefix');
            $box.find('.detail-item-row').each(function (i) {
                $(this).find('.detail-field-badge').text(`Detail Field ${i + 1}`);
                $(this).find('.detail-item-id').attr('name', `${prefix}[fields][${i}][id]`);
                $(this).find('.detail-mode-select').attr('name', `${prefix}[fields][${i}][mode]`);
                $(this).find('.detail-format-select').attr('name', `${prefix}[fields][${i}][format]`);
                $(this).find('.detail-title-field').attr('name', `${prefix}[fields][${i}][title]`);
                if ($(this).find('.detail-mode-select').val() === 'SELECT') {
                    reindexChoiceRows($(this));
                }
            });
            $box.find('.detail-count-input').val($box.find('.detail-item-row').length);
        }

        function syncDetailRows($box, target) {
            target = Math.max(1, Math.min(DETAIL_MAX, parseInt(target, 10) || 1));
            const prefix = $box.attr('data-name-prefix');
            const $c = $box.find('.detail-fields-container');
            while ($c.children('.detail-item-row').length > target) {
                $c.children('.detail-item-row').last().remove();
            }
            while ($c.children('.detail-item-row').length < target) {
                $c.append(createDetailRow(prefix, $c.children('.detail-item-row').length, null));
            }
            reindexDetailRows($box);
        }

        function ensureDetailRows($box) {
            if (!$box.find('.detail-item-row').length) syncDetailRows($box, 1);
        }

        // =====================================================================
        // DIRECT DETAILS (PRIMARY SERVICE WITHOUT SUB-TRANSACTIONS)
        // =====================================================================
        function resetDirectDetails(prefill = null) {
            const $mount = $('#direct_details_mount');
            const $btn   = $('#btn_toggle_direct_details');
            $mount.empty();

            const hasDet = Boolean(prefill && prefill.has_details);
            $mount.append(createDetailBuilderBox('direct_details', prefill, hasDet));

            if (hasDet) {
                $btn.removeClass('btn-outline-primary').addClass('btn-warning')
                    .html('<i class="bi bi-dash-circle me-1"></i> Remove Details');
            } else {
                $btn.removeClass('btn-warning').addClass('btn-outline-primary')
                    .html('<i class="bi bi-plus-square me-1"></i> Add Details');
            }
        }

        $('#btn_toggle_direct_details').on('click', function () {
            const $box = $('#direct_details_mount .detail-builder-box');
            const $flag = $box.find('.detail-enabled-flag');
            const isHidden = $box.hasClass('d-none');

            if (isHidden) {
                $box.removeClass('d-none');
                $flag.val('1');
                $(this).removeClass('btn-outline-primary').addClass('btn-warning')
                       .html('<i class="bi bi-dash-circle me-1"></i> Remove Details');
                ensureDetailRows($box);
            } else {
                $box.addClass('d-none');
                $flag.val('0');
                $(this).removeClass('btn-warning').addClass('btn-outline-primary')
                       .html('<i class="bi bi-plus-square me-1"></i> Add Details');
            }
        });

        // =====================================================================
        // SUB-TRANSACTION CARDS BUILDER (WITH INDIVIDUAL ESTIMATED DURATION)
        // =====================================================================
        function createSubConcernBlock(idx, prefill = null) {
            const subId      = prefill?.id || 0;
            const subName    = prefill?.sub_concern_name || '';
            const estMinutes = prefill?.estimated_minutes || 5;
            const details    = prefill?.parsed_details || null;
            const hasDetails = Boolean(details && details.has_details);
            const counterId  = prefill?.counter_id || 0;

            const $card = $(`
                <div class="sub-concern-card p-3 position-relative" data-sub-index="${idx}">
                    <input type="hidden" name="sub_concerns[${idx}][id]" value="${subId}">
                    
                    <!-- Remove Card Button -->
                    <button type="button" class="btn btn-sm btn-outline-danger position-absolute top-0 end-0 m-2 px-2 py-0 remove-sub-card-btn" title="Remove Sub-Transaction">
                        <i class="bi bi-x-lg"></i>
                    </button>

                    <div class="row g-2 align-items-center pe-4">
                        <div class="col-md-4">
                            <label class="form-label fw-bold small mb-1 sub-card-title-label">Title ${idx + 1} <span class="text-danger">*</span></label>
                            <input type="text"
                                   name="sub_concerns[${idx}][name]"
                                   class="form-control form-control-sm sub-name-input"
                                   value="${escapeHtml(subName)}"
                                   placeholder="Enter Title ${idx + 1} here"
                                   required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small mb-1">Est. Duration (Mins)</label>
                            <div class="input-group input-group-sm stepper-group">
                                <button type="button" class="btn btn-outline-secondary sub-dur-dec">-</button>
                                <input type="number"
                                       name="sub_concerns[${idx}][estimated_minutes]"
                                       class="form-control sub-dur-input"
                                       value="${estMinutes}"
                                       min="1" max="180" required>
                                <button type="button" class="btn btn-outline-primary sub-dur-inc">+</button>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-bold small mb-1">Assigned Window <span class="text-danger">*</span></label>
                            <select name="sub_concerns[${idx}][counter_id]" class="form-select form-select-sm counter-select sub-counter-select">
                                ${counterOptionsHtml(counterId)}
                            </select>
                        </div>
                    </div>
                    <div class="text-end mt-2">
                        <button type="button" class="btn btn-sm ${hasDetails ? 'btn-warning' : 'btn-outline-primary'} toggle-sub-details-btn">
                            <i class="bi ${hasDetails ? 'bi-dash-circle' : 'bi-plus-square'} me-1"></i>
                            ${hasDetails ? 'Remove Details' : 'Add Details'}
                        </button>
                    </div>
                    <div class="sub-detail-mount"></div>
                </div>
            `);

            $card.find('.sub-detail-mount').append(
                createDetailBuilderBox(`sub_concerns[${idx}][details]`, details, hasDetails)
            );
            return $card;
        }

        function reindexSubConcernBlocks() {
            $('#dynamic_sub_concerns_list .sub-concern-card').each(function (newIdx) {
                $(this).attr('data-sub-index', newIdx);
                $(this).find('.sub-card-title-label').html(`Title ${newIdx + 1} <span class="text-danger">*</span>`);
                $(this).find('.sub-name-input')
                    .attr('name', `sub_concerns[${newIdx}][name]`)
                    .attr('placeholder', `Enter Title ${newIdx + 1} here`);
                $(this).find('.sub-dur-input')
                    .attr('name', `sub_concerns[${newIdx}][estimated_minutes]`);
                $(this).find('.sub-counter-select')
                    .attr('name', `sub_concerns[${newIdx}][counter_id]`);
                $(this).find('input[type="hidden"][name*="[id]"]')
                    .attr('name', `sub_concerns[${newIdx}][id]`);
                
                $(this).find('.detail-builder-box').each(function () {
                    const detailPrefix = `sub_concerns[${newIdx}][details]`;
                    $(this).attr('data-name-prefix', detailPrefix);
                    $(this).find('.detail-enabled-flag').attr('name', `${detailPrefix}[enabled]`);
                    reindexDetailRows($(this));
                });
            });
            const currentTotal = $('#dynamic_sub_concerns_list .sub-concern-card').length;
            $('#sub_concern_count').val(Math.max(1, currentTotal));
        }

        function syncSubConcernBlocks(prefillList = null) {
            const $list = $('#dynamic_sub_concerns_list');
            const targetCount = prefillList
                ? Math.max(1, prefillList.length)
                : Math.max(1, Math.min(30, parseInt($('#sub_concern_count').val(), 10) || 1));

            $('#sub_concern_count').val(targetCount);

            if (prefillList) {
                $list.empty();
                prefillList.forEach((item, idx) => $list.append(createSubConcernBlock(idx, item)));
                return;
            }

            const currentCount = $list.children('.sub-concern-card').length;
            if (targetCount > currentCount) {
                for (let i = currentCount; i < targetCount; i++) {
                    $list.append(createSubConcernBlock(i));
                }
            } else if (targetCount < currentCount) {
                $list.children('.sub-concern-card').slice(targetCount).remove();
            }
            reindexSubConcernBlocks();
        }

        // Switch between Standalone Service vs Sub-Transactions
        $('#form_has_sub_concerns').on('change', function () {
            if ($(this).val() === 'yes') {
                $('#primary_duration_wrapper').addClass('d-none');
                $('#primary_window_wrapper').addClass('d-none');
                $('#form_estimated_minutes').prop('required', false);
                $('#direct_details_wrapper').addClass('d-none');
                $('#sub_concerns_section').removeClass('d-none');
                syncSubConcernBlocks();
            } else {
                $('#primary_duration_wrapper').removeClass('d-none');
                $('#primary_window_wrapper').removeClass('d-none');
                $('#form_estimated_minutes').prop('required', true);
                $('#sub_concerns_section').addClass('d-none');
                $('#direct_details_wrapper').removeClass('d-none');
            }
        });

        $('#btn_sub_inc').on('click', () => {
            $('#sub_concern_count').val(Math.min(30, (parseInt($('#sub_concern_count').val(), 10) || 1) + 1));
            syncSubConcernBlocks();
        });
        $('#btn_sub_dec').on('click', () => {
            $('#sub_concern_count').val(Math.max(1, (parseInt($('#sub_concern_count').val(), 10) || 1) - 1));
            syncSubConcernBlocks();
        });
        $('#sub_concern_count').on('change input', () => syncSubConcernBlocks());

        const $modalBody = $('#unified_service_modal .modal-body');

        $modalBody.on('click', '.remove-sub-card-btn', function () {
            const $card = $(this).closest('.sub-concern-card');
            const totalCards = $('#dynamic_sub_concerns_list .sub-concern-card').length;

            if (totalCards <= 1) {
                showError('You must have at least one sub-transaction, or switch back to Standalone Service.');
                return;
            }

            $card.fadeOut(200, function () {
                $(this).remove();
                reindexSubConcernBlocks();
            });
        });

        $modalBody.on('click', '.remove-detail-row-btn', function () {
            const $box = $(this).closest('.detail-builder-box');
            const $row = $(this).closest('.detail-item-row');

            if ($box.find('.detail-item-row').length <= 1) {
                showError('You must have at least one detail field, or click "Remove Details" to hide this section entirely.');
                return;
            }

            $row.fadeOut(200, function () {
                $(this).remove();
                reindexDetailRows($box);
            });
        });

        $modalBody.on('click', '.sub-dur-inc', function () {
            const $inp = $(this).siblings('.sub-dur-input');
            let val = parseInt($inp.val(), 10) || 1;
            $inp.val(Math.min(180, val + 1));
        });
        $modalBody.on('click', '.sub-dur-dec', function () {
            const $inp = $(this).siblings('.sub-dur-input');
            let val = parseInt($inp.val(), 10) || 1;
            $inp.val(Math.max(1, val - 1));
        });

        $modalBody.on('click', '.toggle-sub-details-btn', function () {
            const $card = $(this).closest('.sub-concern-card');
            const $box  = $card.find('.detail-builder-box');
            const $flag = $box.find('.detail-enabled-flag');
            const isHidden = $box.hasClass('d-none');

            if (isHidden) {
                $box.removeClass('d-none');
                $flag.val('1');
                $(this).removeClass('btn-outline-primary').addClass('btn-warning')
                       .html('<i class="bi bi-dash-circle me-1"></i>Remove Details');
                ensureDetailRows($box);
            } else {
                $box.addClass('d-none');
                $flag.val('0');
                $(this).removeClass('btn-warning').addClass('btn-outline-primary')
                       .html('<i class="bi bi-plus-square me-1"></i>Add Details');
            }
        });

        $modalBody.on('change', '.detail-mode-select', function () {
            const mode = $(this).val() === 'SELECT' ? 'SELECT' : 'INPUT';
            const $row = $(this).closest('.detail-item-row');

            $row.find('.detail-format-select')
                .html(detailFormatOptions(mode, mode === 'SELECT' ? 'numeric' : 'CHAR'));

            $row.find('.detail-choices-wrapper').toggleClass('d-none', mode !== 'SELECT');

            if (mode === 'SELECT') {
                renderChoiceRows($row, ['', '']);
            } else {
                $row.find('.detail-choice-list').empty();
                $row.find('.choice-count-input').val(DETAIL_CHOICE_MIN);
                $row.find('.choice-count-badge').text(`${DETAIL_CHOICE_MIN} choices`);
            }
        });

        $modalBody.on('change', '.detail-format-select', function () {
            const $row = $(this).closest('.detail-item-row');
            if ($row.find('.detail-mode-select').val() === 'SELECT') {
                reindexChoiceRows($row);
            }
        });

        $modalBody.on('click', '.btn-choice-inc', function () {
            const $row = $(this).closest('.detail-item-row');
            const $list = $row.find('.detail-choice-list');
            const count = $list.find('.detail-choice-row').length;

            if (count >= DETAIL_CHOICE_MAX) {
                showError('A maximum of 50 dropdown choices is allowed.');
                return;
            }

            const prefix = $row.closest('.detail-builder-box').attr('data-name-prefix');
            const fieldIndex = $row.index();
            const format = $row.find('.detail-format-select').val() || 'numeric';

            $list.append(createChoiceRow(prefix, fieldIndex, count, '', format, 0));
            reindexChoiceRows($row);
        });

        $modalBody.on('click', '.btn-choice-dec', function () {
            const $row = $(this).closest('.detail-item-row');
            const $list = $row.find('.detail-choice-list');
            const count = $list.find('.detail-choice-row').length;

            if (count <= DETAIL_CHOICE_MIN) {
                showError('At least 2 dropdown choices are required.');
                return;
            }

            $list.children('.detail-choice-row').last().remove();
            reindexChoiceRows($row);
        });

        $modalBody.on('click', '.remove-detail-choice-btn', function () {
            const $row = $(this).closest('.detail-item-row');
            const count = $row.find('.detail-choice-row').length;

            if (count <= DETAIL_CHOICE_MIN) {
                showError('At least 2 dropdown choices are required.');
                return;
            }

            $(this).closest('.detail-choice-row').remove();
            reindexChoiceRows($row);
        });

        $modalBody.on('click', '.btn-detail-inc', function () {
            const $box = $(this).closest('.detail-builder-box');
            syncDetailRows($box, (parseInt($box.find('.detail-count-input').val(), 10) || 1) + 1);
        });

        $modalBody.on('click', '.btn-detail-dec', function () {
            const $box = $(this).closest('.detail-builder-box');
            syncDetailRows($box, (parseInt($box.find('.detail-count-input').val(), 10) || 1) - 1);
        });

        $modalBody.on('change', '.detail-count-input', function () {
            syncDetailRows($(this).closest('.detail-builder-box'), $(this).val());
        });

        // =====================================================================
        // TABULATOR TABLE
        // =====================================================================
        function buildDetailsPlainText(pd) {
            if (!pd || !Array.isArray(pd.formatted_items) || !pd.formatted_items.length) return '';
            return pd.formatted_items.map(it => {
                let lines = [it.title];
                if (it.mode === 'SELECT' && Array.isArray(it.choices) && it.choices.length) {
                    it.choices.forEach((choice, idx) => {
                        const pfx = (it.choice_prefixes && it.choice_prefixes[idx])
                            ? it.choice_prefixes[idx]
                            : getChoicePrefix(it.format || 'numeric', idx) + ' ';
                        lines.push('  ' + pfx + choice);
                    });
                }
                return lines.join('\n');
            }).join('\n');
        }

        function formatDetailsCellHtml(items) {
            if (!Array.isArray(items) || !items.length) {
                return '<span class="text-muted">—</span>';
            }
            const blocks = items.map(it => {
                let choicesHtml = '';
                if (it.mode === 'SELECT' && Array.isArray(it.choices) && it.choices.length) {
                    const choiceLines = it.choices.map((choice, idx) => {
                        const pfx = (it.choice_prefixes && it.choice_prefixes[idx])
                            ? it.choice_prefixes[idx]
                            : getChoicePrefix(it.format || 'numeric', idx) + ' ';
                        return `<div class="detail-cell-choice-item">${escapeHtml(pfx + choice)}</div>`;
                    }).join('');
                    choicesHtml = `<div class="detail-cell-choices">${choiceLines}</div>`;
                }
                return `
                    <div class="detail-cell-block">
                        <div class="detail-cell-title">${escapeHtml(it.title)}</div>
                        ${choicesHtml}
                    </div>`;
            }).join('');

            return `<div class="detail-cell-list">${blocks}</div>`;
        }

        function flattenServices(list) {
            const rows = [];
            (list || []).forEach(svc => {
                const base = {
                    id: svc.id,
                    office_name: svc.office_name,
                    transaction_name: svc.transaction_name,
                    flag_status: svc.flag_status,
                    status_label: String(svc.flag_status) === '0' ? 'Active' : 'Disabled',
                    date_modify: svc.date_modify || '',
                    service: svc
                };
                const win = (src) => ({
                    window_name: src.counter_id ? (src.counter_name || '') : '',
                    window_disabled: src.counter_id && String(src.counter_flag_status) !== '0',
                    window_text: src.counter_id ? (src.counter_name || '') : ''
                });
                if (svc.sub_concerns && svc.sub_concerns.length > 0) {
                    svc.sub_concerns.forEach(sub => rows.push(Object.assign({}, base, {
                        row_key: svc.id + '-s' + sub.id,
                        sub_name: sub.sub_concern_name,
                        process_time: parseInt(sub.estimated_minutes, 10) || 0,
                        ...win(sub),
                        details_items: sub.parsed_details?.formatted_items || [],
                        details_text: buildDetailsPlainText(sub.parsed_details)
                    })));
                } else {
                    rows.push(Object.assign({}, base, {
                        row_key: svc.id + '-p',
                        sub_name: '',
                        process_time: parseInt(svc.estimated_minutes, 10) || 0,
                        ...win(svc),
                        details_items: svc.direct_details?.formatted_items || [],
                        details_text: buildDetailsPlainText(svc.direct_details)
                    }));
                }
            });
            return rows;
        }

        function isContinuationRow(row) {
            const prev = row.getPrevRow();
            return !!(prev && prev.getData().id === row.getData().id);
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
            tableInstance.setFilter(function (data) {
                return [data.office_name, data.transaction_name, data.sub_name, data.details_text, String(data.process_time), data.window_text, data.status_label]
                    .some(v => String(v ?? '').toLowerCase().includes(kw));
            });
        }

        function initTabulatorTable() {
            tabulator = new Tabulator("#tabulator-container", {
                ajaxURL: AJAX_HANDLER,
                ajaxLoader: true,
                ajaxConfig: "POST",
                ajaxContentType: "form",
                ajaxParams: () => ({
                    action: 'fetch_transaction_types',
                    office_id: IS_ADMIN_STAFF ? ASSIGNED_OFFICE_ID : $('#filterOffice').val()
                }),
                ajaxResponse: (url, params, response) => {
                    if (response && response.msg_status === false) {
                        showError(response.msg_response || 'Unable to load transaction services.');
                        return [];
                    }
                    return flattenServices(response && response.data);
                },
                index: "row_key",
                layout: "fitDataFill",
                resizableColumnFit: true,
                headerFilterPlaceholder: "Search",
                pagination: "local",
                paginationSize: 20,
                paginationSizeSelector: [10, 20, 50, 100, true],
                placeholder: `
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-inbox fs-2 d-block mb-2 opacity-50"></i>
                        <div class="fw-semibold">No transaction services found</div>
                        <div class="small">Try adjusting your search keywords or office filter.</div>
                    </div>
                `,
                height: "700px",
                downloadConfig: {
                    columnHeaders: true,
                    columnGroups: false,
                    rowGroups: false,
                    formatCells: false
                },
                rowFormatter: function (row) {
                    row.getElement().classList.toggle('svc-group-start', !isContinuationRow(row));
                },
                columns: [
                    {
                        title: "Action",
                        field: "action",
                        minWidth: 210,
                        headerSort: false,
                        download: false,
                        vertAlign: 'middle',
                        hozAlign: 'center',
                        formatter: function (cell) {
                            if (isContinuationRow(cell.getRow())) return '';
                            const classBtn = "btn btn-action d-inline-flex align-items-center justify-content-center px-2 py-1 me-1 mb-2 gap-2";
                            return `<button type="button" class="${classBtn} btn-update-row" title="Manage">
                                        <i class="bi bi-pencil-square"></i><span style="font-size: 0.75rem;">Manage</span>
                                    </button>
                                    <button type="button" class="${classBtn} btn-delete-row" title="Delete">
                                        <i class="bi bi-trash text-danger"></i><span style="font-size: 0.75rem;">Delete</span>
                                    </button>`;
                        },
                        cellClick: function (e, cell) {
                            const svc = cell.getRow().getData().service;
                            if (e.target.closest('.btn-update-row')) openEditModal(svc);
                            else if (e.target.closest('.btn-delete-row')) deleteTransactionType(svc.id);
                        }
                    },
                    Object.assign({
                        title: "Office",
                        field: "office_name",
                        minWidth: 200,
                        vertAlign: 'middle',
                        hozAlign: 'left',
                        formatter: (cell) => isContinuationRow(cell.getRow()) ? '' : escapeHtml(cell.getValue())
                    }, colFilter),
                    Object.assign({
                        title: "Transaction Service",
                        field: "transaction_name",
                        minWidth: 220,
                        vertAlign: 'middle',
                        hozAlign: 'left',
                        formatter: (cell) => isContinuationRow(cell.getRow())
                            ? ''
                            : `<div style="white-space:normal;">${escapeHtml(cell.getValue())}</div>`
                    }, colFilter),
                    Object.assign({
                        title: "Sub-Transactions Service",
                        field: "sub_name",
                        minWidth: 230,
                        vertAlign: 'middle',
                        hozAlign: 'left',
                        formatter: (cell) => {
                            const v = cell.getValue();
                            return v ? `<div style="white-space:normal;">${escapeHtml(v)}</div>` : '<span class="text-muted">—</span>';
                        }
                    }, colFilter),
                    Object.assign({
                        title: "Details",
                        field: "details_text",
                        minWidth: 300,
                        vertAlign: 'middle',
                        hozAlign: 'left',
                        formatter: (cell) => formatDetailsCellHtml(cell.getRow().getData().details_items)
                    }, colFilter),
                    Object.assign({
                        title: "Process Time",
                        field: "process_time",
                        minWidth: 130,
                        sorter: "number",
                        vertAlign: 'middle',
                        hozAlign: 'center',
                        formatter: (cell) => `${parseInt(cell.getValue(), 10) || 0} min`
                    }, colFilter),
                    Object.assign({
                        title: "Window",
                        field: "window_text",
                        minWidth: 200,
                        vertAlign: 'middle',
                        hozAlign: 'left',
                        formatter: (cell) => {
                            const d = cell.getRow().getData();
                            if (!d.window_name) return '<span class="text-muted">Not assigned</span>';
                            return `<div style="white-space:normal;">
                                        <div class="window-cell-name">${escapeHtml(d.window_name)}${d.window_disabled ? ' <span class="text-danger small">(Disabled)</span>' : ''}</div>
                                    </div>`;
                        }
                    }, colFilter),
                    {
                        title: "Status",
                        field: "status_label",
                        minWidth: 130,
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
                        },
                        formatter: (cell) => isContinuationRow(cell.getRow()) ? '' : escapeHtml(cell.getValue())
                    },
                    Object.assign({
                        title: "Date Added/Modify",
                        field: "date_modify",
                        minWidth: 170,
                        vertAlign: 'middle',
                        hozAlign: 'center',
                        formatter: (cell) => isContinuationRow(cell.getRow()) ? '' : escapeHtml(cell.getValue())
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
            tabulator.on("dataProcessed", function () {
                applyGlobalSearch(tabulator);
            });

            document.getElementById('export-csv')?.addEventListener('click', () => tabulator.download("csv", "unified_services.csv"));
            document.getElementById('export-excel')?.addEventListener('click', () => tabulator.download("xlsx", "unified_services.xlsx", { sheetName: "Services" }));
            document.getElementById('export-json')?.addEventListener('click', () => tabulator.download("json", "unified_services.json"));

            $('#serviceFilterForm').on('submit', function (e) {
                e.preventDefault();
                if (tabulator) tabulator.setData();
            });

            $('#filterOffice').on('change', function () {
                if (tabulator) tabulator.setData();
            });

            $('#global-search').on('input', function () {
                applyGlobalSearch(tabulator);
            });

            $('#btnReset').on('click', resetAndReload);
            $('#btn-refresh').on('click', resetAndReload);
        }

        initTabulatorTable();

        // =====================================================================
        // OPEN CREATE / EDIT / PREVIEW MODALS
        // =====================================================================
        $('.add-type-btn').on('click', async function () {
            isEditMode = false;
            $('#unifiedModalTitle').html('<i class="bi bi-plus-circle text-primary me-2"></i>Create Transaction Service');
            $('#unified_service_form')[0].reset();
            $('#form_type_id').val('0');
            $('#form_estimated_minutes').val('5');
            $('#form_has_sub_concerns').val('no');

            $('#primary_duration_wrapper').removeClass('d-none');
            $('#primary_window_wrapper').removeClass('d-none');
            $('#form_estimated_minutes').prop('required', true);
            $('#direct_details_wrapper').removeClass('d-none');
            resetDirectDetails(null);

            $('#sub_concerns_section').addClass('d-none');
            $('#dynamic_sub_concerns_list').empty();
            $('#sub_concern_count').val('1');

            if (IS_ADMIN_STAFF && ASSIGNED_OFFICE_ID > 0) {
                $('#form_office_id').val(String(ASSIGNED_OFFICE_ID));
                $('#hidden_assigned_office_id').val(String(ASSIGNED_OFFICE_ID));
                await loadOfficeCounters(ASSIGNED_OFFICE_ID);
            } else {
                officeCounters = [];
                counterRequestSeq++;
                refreshCounterSelects();
            }

            $('#unified_service_modal').modal('show');
        });

        async function openEditModal(data) {
            isEditMode = true;
            const effectiveOfficeId = IS_ADMIN_STAFF ? ASSIGNED_OFFICE_ID : data.office_id;
            $('#unifiedModalTitle').html('<i class="bi bi-pencil-square text-primary me-2"></i>Manage Service & Sub-Transactions');
            $('#form_type_id').val(data.id);
            $('#form_office_id').val(effectiveOfficeId);
            if (IS_ADMIN_STAFF) {
                $('#hidden_assigned_office_id').val(effectiveOfficeId);
            }
            $('#form_transaction_name').val(data.transaction_name);
            $('#form_flag_status').val(data.flag_status);

            // Windows must be loaded for this office before any dropdown is built
            showLoader('Loading Windows...');
            try {
                await loadOfficeCounters(effectiveOfficeId);
            } finally {
                hideLoader();
            }

            if (data.sub_concerns && data.sub_concerns.length > 0) {
                $('#form_has_sub_concerns').val('yes');
                $('#primary_duration_wrapper').addClass('d-none');
                $('#primary_window_wrapper').addClass('d-none');
                $('#form_estimated_minutes').prop('required', false);
                $('#direct_details_wrapper').addClass('d-none');
                resetDirectDetails(null);
                $('#sub_concerns_section').removeClass('d-none');
                syncSubConcernBlocks(data.sub_concerns);
            } else {
                $('#form_has_sub_concerns').val('no');
                $('#primary_duration_wrapper').removeClass('d-none');
                $('#primary_window_wrapper').removeClass('d-none');
                $('#form_estimated_minutes').val(data.estimated_minutes || 5);
                $('#form_estimated_minutes').prop('required', true);
                $('#form_counter_id').html(counterOptionsHtml(data.counter_id));
                $('#sub_concerns_section').addClass('d-none');
                $('#dynamic_sub_concerns_list').empty();
                $('#sub_concern_count').val('1');

                $('#direct_details_wrapper').removeClass('d-none');
                resetDirectDetails(data.direct_details || null);
            }

            $('#unified_service_modal').modal('show');
        }

        function renderPreviewDetailCard(pd) {
            if (!pd || !pd.formatted_items) {
                return '<span class="text-muted small">No additional detail fields</span>';
            }
            const modeBadge = pd.detail_mode === 'SELECT'
                ? `<span class="badge bg-info-subtle text-info border">SELECT (${escapeHtml(pd.separator_style)})</span>`
                : `<span class="badge bg-primary-subtle text-primary border">INPUT (${escapeHtml(pd.input_data_type)})</span>`;
            const itemsList = pd.formatted_items.map(it => {
                const choices = Array.isArray(it.choices) && it.choices.length
                    ? `<div class="small text-muted mt-1">Choices: ${it.choices.map((choice, idx) => `${escapeHtml(it.choice_prefixes?.[idx] || '')}${escapeHtml(choice)}`).join(' · ')}</div>`
                    : '';
                return `<li class="small"><strong>${escapeHtml(it.title)}</strong>${choices}</li>`;
            }).join('');
            return `
                <div class="mt-2 p-2 bg-light rounded border">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        ${modeBadge}
                    </div>
                    <ul class="mb-0 ps-3">${itemsList}</ul>
                </div>`;
        }

        function openPreviewModal(data) {
            $('#previewModalTitle').text(`[${data.office_code}] ${data.transaction_name}`);
            if (data.sub_concerns && data.sub_concerns.length > 0) {
                let html = '<div class="list-group">';
                data.sub_concerns.forEach((sub, i) => {
                    html += `
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold">${i + 1}. ${escapeHtml(sub.sub_concern_name)}</span>
                                <span class="badge bg-light text-dark border">${sub.estimated_minutes} mins</span>
                            </div>
                            ${renderPreviewDetailCard(sub.parsed_details)}
                        </div>`;
                });
                html += '</div>';
                $('#previewModalBody').html(html);
            } else if (data.direct_details) {
                $('#previewModalBody').html(`
                    <div class="p-2">
                        <div class="fw-bold mb-1">Primary Service Direct Detail Fields:</div>
                        ${renderPreviewDetailCard(data.direct_details)}
                    </div>
                `);
            } else {
                $('#previewModalBody').html('<p class="text-muted mb-0">Standalone service with no sub-transactions or extra detail fields.</p>');
            }
            $('#preview_structure_modal').modal('show');
        }

        // =====================================================================
        // SUBMIT & DELETE
        // =====================================================================
        $('#unified_service_form').on('submit', async function (e) {
            e.preventDefault();

            let missingTitle = null;
            $('#unified_service_modal .detail-builder-box:not(.d-none) .detail-title-field').each(function () {
                if (!$(this).val().trim()) { missingTitle = $(this); return false; }
            });
            if (missingTitle) {
                missingTitle.addClass('is-invalid').trigger('focus');
                showError('Please enter a title name for every detail field.');
                return;
            }
            $('#unified_service_modal .detail-title-field').removeClass('is-invalid');

            $('#unified_service_modal .counter-select').removeClass('is-invalid');
            const hasSubs = $('#form_has_sub_concerns').val() === 'yes';
            const $windowSelects = hasSubs
                ? $('#dynamic_sub_concerns_list .sub-counter-select')
                : $('#form_counter_id');
            let missingWindow = null;
            $windowSelects.each(function () {
                if (!$(this).val()) { missingWindow = $(this); return false; }
            });
            if (missingWindow) {
                missingWindow.addClass('is-invalid').trigger('focus');
                showError(hasSubs
                    ? 'Please assign a Window to every sub-transaction.'
                    : 'Please assign a Window to this transaction service.');
                return;
            }

            let invalidChoices = null;
            $('#unified_service_modal .detail-builder-box:not(.d-none) .detail-item-row').each(function () {
                const $row = $(this);
                if ($row.find('.detail-mode-select').val() !== 'SELECT') return;

                const $inputs = $row.find('.detail-choice-input');
                const choices = $inputs.map(function () {
                    return String($(this).val() || '').trim();
                }).get();
                const nonEmpty = choices.filter(Boolean);
                const unique = new Set(nonEmpty.map(value => value.toLocaleLowerCase()));

                if (choices.length < DETAIL_CHOICE_MIN ||
                    choices.length > DETAIL_CHOICE_MAX ||
                    choices.some(value => !value || value.length > 100 || value.includes('::')) ||
                    unique.size !== choices.length) {
                    invalidChoices = $inputs.filter(function () {
                        return !String($(this).val() || '').trim();
                    }).first();

                    if (!invalidChoices.length) invalidChoices = $inputs.first();
                    return false;
                }
            });

            if (invalidChoices) {
                invalidChoices.addClass('is-invalid').trigger('focus');
                showError('Each Select field needs 2–50 non-empty, unique choices. Each choice must be 1–100 characters and must not contain "::".');
                return;
            }
            $('#unified_service_modal .detail-choice-input').removeClass('is-invalid');

            const isConfirmed = await notifyConfirm(
                isEditMode ? 'Confirm Update' : 'Confirm Creation',
                'Save this transaction service configuration?',
                'Yes, save it!',
                'question'
            );
            if (!isConfirmed) return;

            const $btn = $('#btn_submit_unified');
            let formData = $(this).serializeArray();
            formData.push({
                name: 'action',
                value: isEditMode ? 'submitUpdateUnifiedType' : 'submitUnifiedType'
            });

            $.ajax({
                url: AJAX_HANDLER,
                method: 'POST',
                data: formData,
                dataType: 'json',
                beforeSend: () => { showLoader('Saving service configuration...'); $btn.prop('disabled', true); },
                complete: () => { hideLoader(); $btn.prop('disabled', false); },
                success: (res) => {
                    if (res && res.msg_status === true) {
                        if (tabulator) tabulator.setData();
                        $('#unified_service_modal').modal('hide');
                        showSuccess(res.msg_response);
                    } else {
                        showError(res?.msg_response || 'Unable to save configuration.');
                    }
                },
                error: (xhr, status, err) => showError(status + ': ' + err)
            });
        });

        async function deleteTransactionType(typeId) {
            if (!typeId) return;
            const isConfirmed = await notifyConfirm('Confirm Deletion', 'Delete this transaction service and all associated sub-transactions/details?', 'Yes, delete it!', 'warning');
            if (!isConfirmed) return;

            $.ajax({
                url: AJAX_HANDLER,
                type: 'POST',
                data: { action: 'delete_transaction_type', id: typeId },
                dataType: 'json',
                beforeSend: () => showLoader('Deleting service...'),
                complete: () => hideLoader(),
                success: (res) => {
                    if (res && res.msg_status === true) {
                        if (tabulator) tabulator.setData();
                        showSuccess(res.msg_response);
                    } else {
                        showError(res?.msg_response || 'Deletion failed.');
                    }
                },
                error: (xhr, status, err) => showError(status + ': ' + err)
            });
        }
    })();
</script>
</body>
</html>