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
        @keyframes spin-refresh {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .spin-icon {
            display: inline-block;
            animation: spin-refresh 0.8s linear infinite;
        }
        .search-input-group {
            position: relative;
        }
        .search-input-group .search-icon {
            position: absolute;
            left: 0.65rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            pointer-events: none;
            z-index: 4;
            font-size: 0.85rem;
        }
        .search-input-group .form-control {
            padding-left: 2rem !important;
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include_once ADMIN_SIDEBAR_PATH; ?>

        <div class="main-panel">
            <?php include_once ADMIN_HEADER_PATH; ?>

            <div class="container">
                <div class="page-inner">
                    <?php echo $page_header; ?>

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
                                    <!-- Single-Row Toolbar: Global Search + Date Filter + Export + Refresh -->
                                    <div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-3">
                                        <!-- Global Search -->
                                        <div class="flex-grow-1" style="min-width: 240px;">
                                            <label for="global-search" class="form-label small fw-semibold mb-1">Search</label>
                                            <div class="search-input-group">
                                                <i class="bi bi-search search-icon"></i>
                                                <input type="text" id="global-search" class="form-control form-control-sm" placeholder="Search user, email, system, action, level, or session ID...">
                                            </div>
                                        </div>

                                        <!-- Date Filters & Actions -->
                                        <div class="d-flex flex-wrap align-items-end gap-2">
                                            <div>
                                                <label for="filterDateFrom" class="form-label small fw-semibold mb-1">From Date</label>
                                                <input type="date" id="filterDateFrom" class="form-control form-control-sm">
                                            </div>
                                            <div>
                                                <label for="filterDateTo" class="form-label small fw-semibold mb-1">To Date</label>
                                                <input type="date" id="filterDateTo" class="form-control form-control-sm">
                                            </div>
                                            <button type="button" id="btnFilter" class="btn btn-primary btn-sm">
                                                <i class="bi bi-funnel me-1"></i>Filter
                                            </button>
                                            <button type="button" id="btnReset" class="btn btn-outline-secondary btn-sm">
                                                Reset
                                            </button>

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
                                                <i id="refresh-icon" class="bi bi-arrow-clockwise me-1"></i>
                                                <span id="refresh-text">Refresh Data</span>
                                            </button>
                                        </div>
                                    </div>

                                    <div id="activity-log-table" class="table table-bordered tabulator" style="min-height: 550px;"></div>
                                </div>
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
document.addEventListener("DOMContentLoaded", function () {
    const ajaxUrl     = "<?= BASE_URL; ?>admin/ajax/activity-log";
    const btnRefresh  = document.getElementById("btn-refresh");
    const refreshIcon = document.getElementById("refresh-icon");
    const refreshText = document.getElementById("refresh-text");

    const table = new Tabulator("#activity-log-table", {
        layout: "fitColumns",
        resizableColumnFit: true,
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

    function setRefreshLoading(isLoading) {
        btnRefresh.disabled = isLoading;
        if (isLoading) {
            refreshIcon.classList.add("spin-icon");
            refreshText.textContent = "Refreshing...";
            if (typeof table.showDataLoader === "function") {
                table.showDataLoader("Loading activity logs...");
            }
        } else {
            refreshIcon.classList.remove("spin-icon");
            refreshText.textContent = "Refresh Data";
            if (typeof table.hideDataLoader === "function") {
                table.hideDataLoader();
            }
        }
    }

    function applyGlobalSearch() {
        const q = document.getElementById("global-search").value.toLowerCase().trim();
        if (!q) {
            table.clearFilter();
            return;
        }
        table.setFilter(function (data) {
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

    function loadActivityLogs() {
        setRefreshLoading(true);

        const params = new URLSearchParams({
            action: "fetch",
            date_from: document.getElementById("filterDateFrom").value,
            date_to: document.getElementById("filterDateTo").value
        });

        fetch(`${ajaxUrl}?${params.toString()}`)
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    table.setData(res.data || []).then(() => {
                        applyGlobalSearch();
                    });
                } else {
                    table.setData([]);
                }
            })
            .catch(() => table.setData([]))
            .finally(() => {
                setTimeout(() => setRefreshLoading(false), 300);
            });
    }

    document.getElementById("global-search").addEventListener("input", applyGlobalSearch);
    document.getElementById("btnFilter").addEventListener("click", loadActivityLogs);
    btnRefresh.addEventListener("click", loadActivityLogs);

    document.getElementById("btnReset").addEventListener("click", function () {
        document.getElementById("filterDateFrom").value = "";
        document.getElementById("filterDateTo").value = "";
        document.getElementById("global-search").value = "";
        table.clearFilter(true);
        loadActivityLogs();
    });

    document.getElementById("export-csv").addEventListener("click", () => table.download("csv", "activity_log.csv"));
    document.getElementById("export-excel").addEventListener("click", () => table.download("xlsx", "activity_log.xlsx", { sheetName: "Activity Log" }));
    document.getElementById("export-print").addEventListener("click", () => table.print(false, true));

    loadActivityLogs();
});
</script>

</html>