<?php
// src/Views/Admin/user_log.php
global $db_connect, $session_class, $g_user_role, $g_user_id;
$db_connect  = $db_connect ?? null;
$g_user_role = $g_user_role ?? '';

require_once HELPER;
require_once ISLOGIN;
require_once API_CONNECT;
require_once ADMIN_PAGE_HEADER_PATH;

$title_page   = "User Log";
$active_links = [
    ['label' => $title_page, 'url' => '']
];
$page_header  = render_page_header((string)$title_page, $active_links) ?? '';

# Validate Access using SYSTEM_ACCESS from config.php
$system_auth_login = $session_class->getValue(SYSTEM_ACCESS[SYSTEM_ACCESS_NAME]['auth']);
$role_map          = SYSTEM_ACCESS[SYSTEM_ACCESS_NAME]['role'] ?? [];
$normalized_role   = $role_map[(string)$g_user_role] ?? strtoupper(trim((string)$g_user_role));
$allowed_roles     = array_values($role_map);

if (!in_array($normalized_role, $allowed_roles, true) && !($system_auth_login == $g_public_key)) {
    header("location: " . SYSTEM_ACCESS[SYSTEM_ACCESS_NAME]['link']['main']);
    exit();
}
?>
<!DOCTYPE html>
<html lang="<?= LANG; ?>" class="h-100">

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

        /* Clean Flat Tabulator Styling (Removes header background/shadow effects) */
        #user-log-table.tabulator {
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            background-color: #ffffff;
            box-shadow: none !important;
        }

        #user-log-table .tabulator-header,
        #user-log-table .tabulator-header .tabulator-col {
            background: #ffffff !important;
            background-color: #ffffff !important;
            background-image: none !important;
            border-bottom: 1px solid #e2e8f0;
            box-shadow: none !important;
            font-weight: 600;
            color: #475569;
        }

        #user-log-table .tabulator-header .tabulator-col:hover {
            background: #ffffff !important;
            background-color: #ffffff !important;
            box-shadow: none !important;
        }

        #user-log-table .tabulator-header-filter input,
        #user-log-table .tabulator-header-filter select {
            height: 32px !important;
            min-height: 32px !important;
            padding: 0.25rem 0.5rem !important;
            font-size: 0.8125rem !important;
            box-shadow: none !important;
            box-sizing: border-box;
        }

        #user-log-table .tabulator-row,
        #user-log-table .tabulator-row.tabulator-row-even,
        #user-log-table .tabulator-row.tabulator-row-odd,
        #user-log-table .tabulator-row:hover {
            background-color: #ffffff !important;
            box-shadow: none !important;
        }

        #user-log-table .tabulator-cell {
            background-color: #ffffff !important;
            border-right: 1px solid #f1f5f9;
            box-shadow: none !important;
            white-space: normal;
        }

        #user-log-table .tabulator-cell:last-child {
            border-right: none;
        }

        #user-log-table .tabulator-footer {
            background-color: #ffffff !important;
            box-shadow: none !important;
            border-top: 1px solid #e2e8f0;
        }

        #user-log-table .tabulator-cell:first-child {
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
                    <?php echo $page_header; ?>

                    <!-- SEPARATE FILTER CONTAINER -->
                    <div class="card filter-card mb-4">
                        <div class="card-body p-3 p-sm-4">
                            <form id="userLogFilterForm">
                                <div class="row g-2 align-items-end">
                                    <div class="col-6 col-sm-4 col-md-3 col-xl-2">
                                        <label for="filterDateFrom" class="form-label fw-bold small text-muted">Date From</label>
                                        <input type="date" id="filterDateFrom" class="form-control form-control-sm" value="<?= DATE_NOW; ?>">
                                    </div>

                                    <div class="col-6 col-sm-4 col-md-3 col-xl-2">
                                        <label for="filterDateTo" class="form-label fw-bold small text-muted">Date To</label>
                                        <input type="date" id="filterDateTo" class="form-control form-control-sm" value="<?= DATE_NOW; ?>">
                                    </div>

                                    <div class="col-12 col-sm-4 col-md-3 col-xl-2">
                                        <label for="filterStatus" class="form-label fw-bold small text-muted">Session Status</label>
                                        <select id="filterStatus" class="form-select form-select-sm">
                                            <option value="">All Sessions</option>
                                            <option value="1">Active / Logged In</option>
                                            <option value="0">Logged Out</option>
                                        </select>
                                    </div>

                                    <div class="col-12 col-md-3 col-xl-6 d-flex flex-wrap gap-2">
                                        <button type="submit" id="btnFilter" class="btn btn-primary btn-sm fw-bold px-3 text-nowrap">
                                            <i class="bi bi-filter me-1"></i>Apply
                                        </button>
                                        <button type="button" id="btnReset" class="btn btn-outline-secondary btn-sm text-nowrap" title="Reset Filters">
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- USER LOG TABLE CONTAINER -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="bg-primary-subtle text-primary rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                                            <i class="bi bi-person-lines-fill fs-4"></i>
                                        </div>
                                        <div>
                                            <div class="text-uppercase text-muted fw-bold tracking-wide small" style="font-size: 0.95rem;">
                                                User Authentication & Session Logs
                                            </div>
                                            <div class="d-flex align-items-center text-muted small mt-0.5" style="font-size: 0.75rem;">
                                                <i class="bi bi-info-circle me-1"></i> Track user login/logout timestamps, IP addresses, and active sessions
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="card-body">
                                    <!-- Unified Top Toolbar: Global Search + Export/Download + Refresh Data -->
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                                        <div class="flex-grow-1" style="min-width: 240px; max-width: 420px;">
                                            <div class="search-input-group">
                                                <i class="bi bi-search search-icon"></i>
                                                <input type="text" id="global-search" class="form-control form-control-sm" placeholder="Search" aria-label="Search">
                                            </div>
                                        </div>

                                        <div class="d-flex flex-wrap align-items-center gap-2 ms-auto">
                                            <div class="dropdown">
                                                <button class="btn btn-action btn-sm dropdown-toggle d-inline-flex align-items-center" type="button" id="exportDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                                    <i class="bi bi-download me-1"></i>Export / Download
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="exportDropdown">
                                                    <li><a class="dropdown-item" href="javascript:void(0)" id="export-csv"><i class="bi bi-filetype-csv text-success me-2"></i> Export CSV</a></li>
                                                    <li><a class="dropdown-item" href="javascript:void(0)" id="export-excel"><i class="bi bi-file-earmark-excel text-success me-2"></i> Export Excel (.xlsx)</a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item" href="javascript:void(0)" id="export-print"><i class="bi bi-printer text-primary me-2"></i> Print Table</a></li>
                                                </ul>
                                            </div>

                                            <button type="button" id="btn-refresh" class="btn btn-action btn-sm d-inline-flex align-items-center">
                                                <i class="bi bi-arrow-clockwise me-1"></i>Refresh Data
                                            </button>
                                        </div>
                                    </div>

                                    <div id="user-log-table" style="min-height: 550px;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php include_once ADMIN_FOOTER_PATH; ?>
        </div>
    </div>

    <!-- SESSION TIMELINE MODAL -->
    <div class="modal fade" id="sessionHistoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold" id="sessionModalTitle">Session Event Timeline</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center" style="width: 60px;">#</th>
                                    <th>Timestamp</th>
                                    <th class="text-center">Event</th>
                                    <th class="text-center">IP Address</th>
                                </tr>
                            </thead>
                            <tbody id="sessionEventsBody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <?php include_once ADMIN_SCRIPT_PATH; ?>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const AJAX_HANDLER  = <?= json_encode(BASE_URL . "admin/ajax/user-log"); ?>;
    const SERVER_TODAY  = <?= json_encode(DATE_NOW); ?>;
    const EXPORT_PREFIX = <?= json_encode(strtolower(SCHOOL_ACRONYM) . "_user_log_" . DATE_NOW); ?>;
    const ROLE_MAP      = <?= json_encode($role_map); ?>;
    const sessionModal  = new bootstrap.Modal(document.getElementById("sessionHistoryModal"));

    function resolveUserLevelName(row) {
        const levelKey = String(row.user_level ?? "").trim();
        if (levelKey && Object.prototype.hasOwnProperty.call(ROLE_MAP, levelKey)) {
            return ROLE_MAP[levelKey];
        }
        return row.role_label || "Unknown";
    }

    function openSessionModal(row) {
        document.getElementById("sessionModalTitle").textContent = `Session History — ${row.full_name} (Log #${row.user_log_id})`;
        const tbody = document.getElementById("sessionEventsBody");
        tbody.innerHTML = "";

        if (!row.session_events || row.session_events.length === 0) {
            tbody.innerHTML = `<tr><td colspan="4" class="text-center text-muted py-3">No detailed session events recorded.</td></tr>`;
        } else {
            row.session_events.forEach((ev, idx) => {
                const ts  = ev[0] || "-";
                const act = ev[1] || "-";
                const ip  = ev[2] || "-";

                tbody.insertAdjacentHTML("beforeend", `
                    <tr>
                        <td class="text-center">${idx + 1}</td>
                        <td>${ts}</td>
                        <td class="text-center">${act}</td>
                        <td class="text-center">${ip}</td>
                    </tr>
                `);
            });
        }
        sessionModal.show();
    }

    function applyGlobalSearch(tableInstance) {
        const q = document.getElementById("global-search").value.toLowerCase().trim();
        if (!q) {
            tableInstance.clearFilter();
            return;
        }
        tableInstance.setFilter(function (data) {
            return (
                String(data.user_log_id).includes(q) ||
                (data.full_name || "").toLowerCase().includes(q) ||
                (data.email || "").toLowerCase().includes(q) ||
                (data.user_level_label || "").toLowerCase().includes(q) ||
                (data.login_date || "").toLowerCase().includes(q) ||
                (data.logout_date || "").toLowerCase().includes(q) ||
                (data.ip_address || "").toLowerCase().includes(q) ||
                (data.status_label || "").toLowerCase().includes(q)
            );
        });
    }

    const table = new Tabulator("#user-log-table", {
        ajaxURL: AJAX_HANDLER,
        ajaxLoader: true,
        ajaxConfig: "GET",
        ajaxParams: () => ({
            action: "fetch",
            date_from: document.getElementById("filterDateFrom").value,
            date_to: document.getElementById("filterDateTo").value,
            login_flag: document.getElementById("filterStatus").value
        }),
        ajaxResponse: (url, params, response) => {
            if (response && response.success && Array.isArray(response.data)) {
                return response.data.map(row => ({
                    ...row,
                    user_level_label: resolveUserLevelName(row),
                    status_label: Number(row.login_flag) === 1 ? "Active" : "Logged Out"
                }));
            }
            return [];
        },
        layout: "fitColumns",
        resizableColumnFit: true,
        headerFilterPlaceholder: "Search",
        height: "550px",
        placeholder: `
            <div class="text-center text-muted py-4">
                <i class="bi bi-inbox fs-2 d-block mb-2 opacity-50"></i>
                <div class="fw-semibold">No user log records found</div>
                <div class="small">Try adjusting your search keywords or date filters.</div>
            </div>
        `,
        pagination: "local",
        paginationSize: 15,
        paginationSizeSelector: [15, 25, 50, 100, true],
        columns: [
            {
                title: "Action",
                width: 115,
                minWidth: 115,
                headerSort: false,
                download: false,
                print: false,
                hozAlign: "center",
                headerHozAlign: "center",
                vertAlign: "middle",
                formatter: function (cell) {
                    const rowData = cell.getRow().getData();
                    const count = Number(rowData.event_count ?? (Array.isArray(rowData.session_events) ? rowData.session_events.length : 0));
                    const classBtnView = "btn btn-action d-inline-flex align-items-center justify-content-center px-2 py-1 gap-1 text-nowrap btn-view-session";

                    return `<div class="d-inline-flex align-items-center justify-content-center text-nowrap">
                                <button type="button" class="${classBtnView}" data-id="${rowData.user_log_id}" title="View Session History (${count} Events)">
                                    <i class="bi bi-eye"></i>
                                    <span style="font-size: 0.75rem;">View</span>
                                </button>
                            </div>`;
                },
                cellClick: function (e, cell) {
                    if (e.target.closest(".btn-view-session")) {
                        openSessionModal(cell.getRow().getData());
                    }
                }
            },
            {
                title: "Log ID",
                field: "user_log_id",
                width: 105,
                minWidth: 105,
                headerSort: false,
                hozAlign: "center",
                headerHozAlign: "center",
                vertAlign: "middle",
                headerFilter: "input",
                headerFilterFunc: "like"
            },
            {
                title: "User",
                field: "full_name",
                minWidth: 170,
                widthGrow: 1.2,
                headerHozAlign: "center",
                vertAlign: "middle",
                headerFilter: "input",
                formatter: function (cell) {
                    const val = cell.getValue() || "Unknown User";
                    return `<span class="fw-semibold">${val}</span>`;
                }
            },
            {
                title: "Email",
                field: "email",
                minWidth: 185,
                widthGrow: 1.2,
                hozAlign: "center",
                headerHozAlign: "center",
                vertAlign: "middle",
                headerFilter: "input",
                formatter: function (cell) {
                    const val = cell.getValue() || "-";
                    return `<div class="w-100 text-center">${val}</div>`;
                }
            },
            {
                title: "User Level",
                field: "user_level_label",
                minWidth: 135,
                widthGrow: 1,
                hozAlign: "center",
                headerHozAlign: "center",
                vertAlign: "middle",
                headerFilter: "input",
                formatter: function (cell) {
                    const val = cell.getValue() || "Unknown";
                    return `<div class="w-100 text-center">${val}</div>`;
                }
            },
            {
                title: "Login Date",
                field: "login_date",
                minWidth: 165,
                widthGrow: 1.1,
                hozAlign: "center",
                headerHozAlign: "center",
                vertAlign: "middle",
                headerFilter: "input",
                formatter: function (cell) {
                    return `<div class="w-100 text-center">${cell.getValue() || "-"}</div>`;
                }
            },
            {
                title: "Logout Date",
                field: "logout_date",
                minWidth: 165,
                widthGrow: 1.1,
                hozAlign: "center",
                headerHozAlign: "center",
                vertAlign: "middle",
                headerFilter: "input",
                formatter: function (cell) {
                    return `<div class="w-100 text-center">${cell.getValue() || "-"}</div>`;
                }
            },
            {
                title: "IP Address",
                field: "ip_address",
                minWidth: 130,
                widthGrow: 0.9,
                hozAlign: "center",
                headerHozAlign: "center",
                vertAlign: "middle",
                headerFilter: "input",
                formatter: function (cell) {
                    return `<div class="w-100 text-center">${cell.getValue() || "-"}</div>`;
                }
            },
            {
                title: "Status",
                field: "status_label",
                minWidth: 130,
                widthGrow: 0.9,
                hozAlign: "center",
                headerHozAlign: "center",
                vertAlign: "middle",
                headerFilter: "input",
                formatter: function (cell) {
                    return `<div class="w-100 text-center">${cell.getValue() || "-"}</div>`;
                }
            }
        ]
    });

    function resetToTodayAndReload() {
        document.getElementById("filterDateFrom").value = SERVER_TODAY;
        document.getElementById("filterDateTo").value = SERVER_TODAY;
        document.getElementById("filterStatus").value = "";
        document.getElementById("global-search").value = "";
        table.clearFilter();
        table.clearHeaderFilter();
        table.setData();
    }

    table.on("dataProcessed", function () {
        applyGlobalSearch(table);
    });

    document.getElementById("global-search").addEventListener("input", function () {
        applyGlobalSearch(table);
    });

    document.getElementById("userLogFilterForm").addEventListener("submit", function (e) {
        e.preventDefault();
        table.setData();
    });

    document.getElementById("btnReset").addEventListener("click", resetToTodayAndReload);
    document.getElementById("btn-refresh").addEventListener("click", resetToTodayAndReload);

    document.getElementById("export-csv").addEventListener("click", () => table.download("csv", `${EXPORT_PREFIX}.csv`));
    document.getElementById("export-excel").addEventListener("click", () => table.download("xlsx", `${EXPORT_PREFIX}.xlsx`, { sheetName: "User Log" }));
    document.getElementById("export-print").addEventListener("click", () => table.print(false, true));
});
</script>

</body>

</html>