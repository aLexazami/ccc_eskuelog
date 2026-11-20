<?php
// src/Views/Admin/activity_log.php
$db_connect  = $db_connect ?? null;
$g_user_role = $g_user_role ?? '';

require_once ADMIN_PAGE_HEADER_PATH;

$title_page   = "Activity Log";
$active_links = [
    ['label' => $title_page, 'url' => '']
];
$page_header  = render_page_header((string)$title_page, $active_links) ?? '';
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

        /* Clean Flat Tabulator Styling (Removes header background/shadow effects) */
        #activity-log-table.tabulator {
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            background-color: #ffffff;
            box-shadow: none !important;
        }

        #activity-log-table .tabulator-header,
        #activity-log-table .tabulator-header .tabulator-col {
            background: #ffffff !important;
            background-color: #ffffff !important;
            background-image: none !important;
            border-bottom: 1px solid #e2e8f0;
            box-shadow: none !important;
            font-weight: 600;
            color: #475569;
        }

        #activity-log-table .tabulator-header .tabulator-col:hover {
            background: #ffffff !important;
            background-color: #ffffff !important;
            box-shadow: none !important;
        }

        #activity-log-table .tabulator-header-filter input,
        #activity-log-table .tabulator-header-filter select {
            height: 32px !important;
            min-height: 32px !important;
            padding: 0.25rem 0.5rem !important;
            font-size: 0.8125rem !important;
            box-shadow: none !important;
            box-sizing: border-box;
        }

        #activity-log-table .tabulator-row,
        #activity-log-table .tabulator-row.tabulator-row-even,
        #activity-log-table .tabulator-row.tabulator-row-odd,
        #activity-log-table .tabulator-row:hover {
            background-color: #ffffff !important;
            box-shadow: none !important;
        }

        #activity-log-table .tabulator-cell {
            background-color: #ffffff !important;
            border-right: 1px solid #f1f5f9;
            box-shadow: none !important;
            white-space: normal;
        }

        #activity-log-table .tabulator-cell:last-child {
            border-right: none;
        }

        #activity-log-table .tabulator-footer {
            background-color: #ffffff !important;
            box-shadow: none !important;
            border-top: 1px solid #e2e8f0;
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

                    <!-- SEPARATE DATE FILTER CONTAINER (Mirrors reports_analytics.php) -->
                    <div class="card filter-card mb-4">
                        <div class="card-body p-3 p-sm-4">
                            <form id="activityFilterForm">
                                <div class="row g-2 align-items-end">
                                    <div class="col-6 col-sm-4 col-md-3 col-xl-2">
                                        <label for="filterDateFrom" class="form-label fw-bold small text-muted">Date From</label>
                                        <input type="date" id="filterDateFrom" class="form-control form-control-sm" value="<?= date('Y-m-d'); ?>">
                                    </div>

                                    <div class="col-6 col-sm-4 col-md-3 col-xl-2">
                                        <label for="filterDateTo" class="form-label fw-bold small text-muted">Date To</label>
                                        <input type="date" id="filterDateTo" class="form-control form-control-sm" value="<?= date('Y-m-d'); ?>">
                                    </div>

                                    <div class="col-12 col-sm-4 col-md-6 col-xl-8 d-flex flex-wrap gap-2">
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

                    <!-- ACTIVITY LOG TABLE CONTAINER -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="bg-primary-subtle text-primary rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                                            <i class="bi bi-activity fs-4"></i>
                                        </div>
                                        <div>
                                            <div class="text-uppercase text-muted fw-bold tracking-wide small" style="font-size: 0.95rem;">
                                                System Activity Audit Trail
                                            </div>
                                            <div class="d-flex align-items-center text-muted small mt-0.5" style="font-size: 0.75rem;">
                                                <i class="bi bi-info-circle me-1"></i> Monitor system actions, data changes, and user operations
                                            </div>
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
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item" href="javascript:void(0)" id="export-print"><i class="bi bi-printer text-primary me-2"></i> Print Table</a></li>
                                                </ul>
                                            </div>

                                            <button type="button" id="btn-refresh" class="btn btn-action btn-sm d-inline-flex align-items-center">
                                                <i class="bi bi-arrow-clockwise me-1"></i>Refresh Data
                                            </button>
                                        </div>
                                    </div>

                                    <div id="activity-log-table" style="min-height: 550px;"></div>
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
document.addEventListener("DOMContentLoaded", function () {
    const AJAX_HANDLER = "<?= BASE_URL; ?>admin/ajax/activity-log";

    const getTodayDate = () => {
        const t = new Date();
        return `${t.getFullYear()}-${String(t.getMonth() + 1).padStart(2, '0')}-${String(t.getDate()).padStart(2, '0')}`;
    };

    function applyGlobalSearch(tableInstance) {
        const q = document.getElementById("global-search").value.toLowerCase().trim();
        if (!q) {
            tableInstance.clearFilter();
            return;
        }
        tableInstance.setFilter(function (data) {
            return (
                String(data.activity_log_id).includes(q) ||
                (data.full_name || "").toLowerCase().includes(q) ||
                (data.email || "").toLowerCase().includes(q) ||
                (data.user_level || "").toLowerCase().includes(q) ||
                (data.system_type || "").toLowerCase().includes(q) ||
                (data.action || "").toLowerCase().includes(q) ||
                (data.session_id || "").toLowerCase().includes(q)
            );
        });
    }

    const table = new Tabulator("#activity-log-table", {
        ajaxURL: AJAX_HANDLER,
        ajaxLoader: true,
        ajaxConfig: "GET",
        ajaxParams: () => ({
            action: "fetch",
            date_from: document.getElementById("filterDateFrom").value,
            date_to: document.getElementById("filterDateTo").value
        }),
        ajaxResponse: (url, params, response) => {
            if (response && response.success) {
                return response.data || [];
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
                <div class="fw-semibold">No activity log records found</div>
                <div class="small">Try adjusting your search keywords or date filters.</div>
            </div>
        `,
        pagination: "local",
        paginationSize: 15,
        paginationSizeSelector: [15, 25, 50, 100, true],
        columns: [
            {
                title: "Log ID",
                field: "activity_log_id",
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
                title: "Date & Time",
                field: "date_log",
                minWidth: 165,
                widthGrow: 1,
                headerHozAlign: "center",
                vertAlign: "middle",
                headerFilter: "input"
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
                field: "user_level",
                minWidth: 130,
                widthGrow: 0.9,
                hozAlign: "center",
                headerHozAlign: "center",
                vertAlign: "middle",
                headerFilter: "input",
                formatter: function (cell) {
                    const val = cell.getValue() || "SYSTEM";
                    return `<div class="w-100 text-center">${val}</div>`;
                }
            },
            {
                title: "System",
                field: "system_type",
                minWidth: 135,
                widthGrow: 0.9,
                hozAlign: "center",
                headerHozAlign: "center",
                vertAlign: "middle",
                headerFilter: "input",
                formatter: function (cell) {
                    const val = cell.getValue() || "E-SKUELOG";
                    return `<div class="w-100 text-center">${val}</div>`;
                }
            },
            {
                title: "Action Details",
                field: "action",
                minWidth: 240,
                widthGrow: 2,
                headerHozAlign: "center",
                vertAlign: "middle",
                headerFilter: "input"
            },
            {
                title: "Session / Source",
                field: "session_id",
                minWidth: 145,
                widthGrow: 1,
                hozAlign: "center",
                headerHozAlign: "center",
                vertAlign: "middle",
                headerFilter: "input",
                formatter: function (cell) {
                    return `<code class="small">${cell.getValue() || "-"}</code>`;
                }
            }
        ]
    });

    function resetToTodayAndReload() {
        const todayStr = getTodayDate();
        document.getElementById("filterDateFrom").value = todayStr;
        document.getElementById("filterDateTo").value = todayStr;
        document.getElementById("global-search").value = "";
        table.clearFilter();
        table.clearHeaderFilter();
        table.setData();
    }

    // Re-apply global search text whenever AJAX data finishes loading
    table.on("dataProcessed", function () {
        applyGlobalSearch(table);
    });

    document.getElementById("global-search").addEventListener("input", function () {
        applyGlobalSearch(table);
    });

    document.getElementById("activityFilterForm").addEventListener("submit", function (e) {
        e.preventDefault();
        table.setData();
    });

    document.getElementById("btnReset").addEventListener("click", resetToTodayAndReload);
    document.getElementById("btn-refresh").addEventListener("click", resetToTodayAndReload);

    document.getElementById("export-csv").addEventListener("click", () => table.download("csv", "activity_log.csv"));
    document.getElementById("export-excel").addEventListener("click", () => table.download("xlsx", "activity_log.xlsx", { sheetName: "Activity Log" }));
    document.getElementById("export-print").addEventListener("click", () => table.print(false, true));
});
</script>

</body>

</html>