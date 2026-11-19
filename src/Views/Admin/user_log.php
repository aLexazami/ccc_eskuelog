<?php
// src/Views/Admin/user_log.php
$db_connect  = $db_connect ?? null;
$g_user_role = $g_user_role ?? '';

require_once ADMIN_PAGE_HEADER_PATH;

$title_page   = "User Log";
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
                                    <!-- Filters & Actions -->
                                    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
                                        <div class="d-flex flex-wrap align-items-end gap-2">
                                            <div>
                                                <label for="filterDateFrom" class="form-label small fw-semibold mb-1">From Date</label>
                                                <input type="date" id="filterDateFrom" class="form-control form-control-sm">
                                            </div>
                                            <div>
                                                <label for="filterDateTo" class="form-label small fw-semibold mb-1">To Date</label>
                                                <input type="date" id="filterDateTo" class="form-control form-control-sm">
                                            </div>
                                            <div>
                                                <label for="filterStatus" class="form-label small fw-semibold mb-1">Session Status</label>
                                                <select id="filterStatus" class="form-select form-select-sm" style="min-width: 150px;">
                                                    <option value="">All Sessions</option>
                                                    <option value="1">Active / Logged In</option>
                                                    <option value="0">Logged Out</option>
                                                </select>
                                            </div>
                                            <button type="button" id="btnFilter" class="btn btn-primary btn-sm">
                                                <i class="bi bi-funnel me-1"></i>Filter
                                            </button>
                                            <button type="button" id="btnReset" class="btn btn-outline-secondary btn-sm">
                                                Reset
                                            </button>
                                        </div>

                                        <div class="d-flex align-items-center gap-2">
                                            <div class="dropdown">
                                                <button class="btn btn-action dropdown-toggle d-inline-flex align-items-center" type="button" id="exportDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                                    <i class="bi bi-download me-2"></i>Export / Download
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="exportDropdown">
                                                    <li><a class="dropdown-item" href="javascript:void(0)" id="export-csv"><i class="bi bi-filetype-csv text-success me-2"></i> Export CSV</a></li>
                                                    <li><a class="dropdown-item" href="javascript:void(0)" id="export-excel"><i class="bi bi-file-earmark-excel text-success me-2"></i> Export Excel (.xlsx)</a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item" href="javascript:void(0)" id="export-print"><i class="bi bi-printer text-primary me-2"></i> Print Table</a></li>
                                                </ul>
                                            </div>

                                            <button id="btn-refresh" class="btn btn-action d-inline-flex align-items-center">
                                                <i class="bi bi-arrow-clockwise me-2"></i>Refresh Data
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Global Search -->
                                    <div class="d-flex mb-3">
                                        <div class="search-input-group flex-grow-1">
                                            <i class="bi bi-search search-icon"></i>
                                            <input type="text" id="global-search" class="form-control" placeholder="Search by user, email, role, or IP address...">
                                        </div>
                                    </div>

                                    <div id="user-log-table" class="table table-bordered tabulator" style="min-height: 550px;"></div>
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
                    <h5 class="modal-title" id="sessionModalTitle">Session Event Timeline</h5>
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
</body>

<?php include_once ADMIN_SCRIPT_PATH; ?>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const ajaxUrl = "<?= BASE_URL; ?>admin/ajax/user-log";
    const sessionModal = new bootstrap.Modal(document.getElementById("sessionHistoryModal"));

    const table = new Tabulator("#user-log-table", {
        layout: "fitColumns",
        resizableColumnFit: true,
        height: "550px",
        placeholder: "No user log records found.",
        pagination: "local",
        paginationSize: 15,
        paginationSizeSelector: [15, 25, 50, 100, true],
        columns: [
            { title: "ID", field: "user_log_id", width: 80, hozAlign: "center", headerHozAlign: "center", vertAlign: "middle" },
            {
                title: "User",
                field: "full_name",
                minWidth: 190,
                widthGrow: 1.4,
                headerHozAlign: "center",
                vertAlign: "middle",
                headerFilter: "input",
                formatter: function (cell) {
                    const row = cell.getRow().getData();
                    const email = row.email ? `<div class="text-muted small">${row.email}</div>` : '';
                    return `<div class="fw-semibold">${row.full_name}</div>${email}`;
                }
            },
            {
                title: "Role",
                field: "role_label",
                minWidth: 130,
                widthGrow: 1,
                hozAlign: "center",
                headerHozAlign: "center",
                vertAlign: "middle",
                headerFilter: "input"
            },
            {
                title: "Login Date",
                field: "login_date",
                minWidth: 160,
                widthGrow: 1.1,
                hozAlign: "center",
                headerHozAlign: "center",
                vertAlign: "middle",
                headerFilter: "input",
                formatter: cell => cell.getValue() || '<span class="text-muted">—</span>'
            },
            {
                title: "Logout Date",
                field: "logout_date",
                minWidth: 160,
                widthGrow: 1.1,
                hozAlign: "center",
                headerHozAlign: "center",
                vertAlign: "middle",
                headerFilter: "input",
                formatter: cell => cell.getValue() || '<span class="text-muted">—</span>'
            },
            {
                title: "IP Address",
                field: "ip_address",
                minWidth: 120,
                widthGrow: 0.9,
                hozAlign: "center",
                headerHozAlign: "center",
                vertAlign: "middle",
                headerFilter: "input",
                formatter: cell => `<code>${cell.getValue() || '-'}</code>`
            },
            {
                title: "Status",
                field: "login_flag",
                minWidth: 125,
                widthGrow: 0.9,
                hozAlign: "center",
                headerHozAlign: "center",
                vertAlign: "middle",
                formatter: function (cell) {
                    return Number(cell.getValue()) === 1
                        ? '<span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>'
                        : '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Logged Out</span>';
                }
            },
            {
                title: "Session History",
                field: "event_count",
                width: 135,
                hozAlign: "center",
                headerHozAlign: "center",
                vertAlign: "middle",
                formatter: function (cell) {
                    const count = cell.getValue() || 0;
                    return `<button type="button" class="btn btn-outline-primary btn-sm py-0 px-2 btn-view-session">
                                <i class="bi bi-clock-history me-1"></i>${count} Events
                            </button>`;
                },
                cellClick: function (e, cell) {
                    const row = cell.getRow().getData();
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
                            const badgeClass = act === "LOGIN"
                                ? "bg-success-subtle text-success"
                                : "bg-warning-subtle text-warning";

                            tbody.insertAdjacentHTML("beforeend", `
                                <tr>
                                    <td class="text-center">${idx + 1}</td>
                                    <td>${ts}</td>
                                    <td class="text-center"><span class="badge ${badgeClass}">${act}</span></td>
                                    <td class="text-center"><code>${ip}</code></td>
                                </tr>
                            `);
                        });
                    }
                    sessionModal.show();
                }
            }
        ]
    });

    function loadUserLogs() {
        const params = new URLSearchParams({
            action: "fetch",
            date_from: document.getElementById("filterDateFrom").value,
            date_to: document.getElementById("filterDateTo").value,
            login_flag: document.getElementById("filterStatus").value
        });

        fetch(`${ajaxUrl}?${params.toString()}`)
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    table.setData(res.data || []);
                } else {
                    table.setData([]);
                }
            })
            .catch(() => table.setData([]));
    }

    document.getElementById("global-search").addEventListener("input", function () {
        const q = this.value.toLowerCase().trim();
        if (!q) {
            table.clearFilter();
            return;
        }
        table.setFilter(function (data) {
            return (
                String(data.user_log_id).includes(q) ||
                (data.full_name || "").toLowerCase().includes(q) ||
                (data.email || "").toLowerCase().includes(q) ||
                (data.role_label || "").toLowerCase().includes(q) ||
                (data.ip_address || "").toLowerCase().includes(q)
            );
        });
    });

    document.getElementById("btnFilter").addEventListener("click", loadUserLogs);
    document.getElementById("btn-refresh").addEventListener("click", loadUserLogs);
    document.getElementById("btnReset").addEventListener("click", function () {
        document.getElementById("filterDateFrom").value = "";
        document.getElementById("filterDateTo").value = "";
        document.getElementById("filterStatus").value = "";
        document.getElementById("global-search").value = "";
        table.clearFilter();
        loadUserLogs();
    });

    document.getElementById("export-csv").addEventListener("click", () => table.download("csv", "user_log.csv"));
    document.getElementById("export-excel").addEventListener("click", () => table.download("xlsx", "user_log.xlsx", { sheetName: "User Log" }));
    document.getElementById("export-print").addEventListener("click", () => table.print(false, true));

    loadUserLogs();
});
</script>

</html>