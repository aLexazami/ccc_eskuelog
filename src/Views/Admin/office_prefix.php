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
$title_page   = "Office Prefix Codes";
$active_links = [
    ['label' => $title_page, 'url' => '']
];
$page_header  = render_page_header((string) $title_page, $active_links) ?? [];

// Resolve User Role
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

// Fetch initial offices list
$offices = [];
if ($db_connect) {
    $stmt = $db_connect->query("
        SELECT office_id, office_code, prefix_code, office_name, date_modify 
        FROM office 
        WHERE flag_status = 0 
        ORDER BY office_name ASC
    ");
    if ($stmt) {
        while ($row = $stmt->fetch_assoc()) {
            $offices[] = $row;
        }
    }
}
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

                    <!-- Page Header -->
                    <?php echo is_array($page_header) ? '' : $page_header; ?>

                    <div class="row">
                        <div class="col-12">
                            <div class="card">

                                <div class="card-header d-flex align-items-center justify-content-between">
                                    <div class="d-flex justify-content-between align-items-start align-items-md-center gap-3 w-100">

                                        <div class="d-flex align-items-center gap-3">
                                            <div class="bg-primary-subtle text-primary rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                                                <i class="bi bi-building-gear fs-4"></i>
                                            </div>

                                            <div>
                                                <div class="text-uppercase text-muted fw-bold tracking-wide small" style="font-size: 0.95rem;">
                                                    Assigned Office Prefixes
                                                </div>
                                                <div class="d-flex align-items-center text-muted small mt-0.5" style="font-size: 0.75rem;">
                                                    <i class="bi bi-info-circle me-1"></i> Manage ticket generation prefix codes for each active office location
                                                </div>
                                            </div>
                                        </div>

                                    </div>
                                </div>

                                <div class="card-body">
                                    <!-- Toolbar with Search and Export Buttons -->
                                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                                        <div class="search-input-group flex-grow-1">
                                            <i class="bi bi-search search-icon"></i>
                                            <input type="text" id="global-search" class="form-control" placeholder="Search office or code...">
                                        </div>

                                        <div class="d-flex align-items-center gap-2">
                                            <!-- Export Options Dropdown -->
                                            <div class="dropdown">
                                                <button class="btn btn-action dropdown-toggle d-inline-flex align-items-center" type="button" id="exportDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                                    <i class="bi bi-download me-2"></i>Export / Download
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="exportDropdown">
                                                    <li><a class="dropdown-item text-slate-700" href="javascript:void(0)" id="export-csv"><i class="bi bi-filetype-csv text-success me-2"></i> Export CSV</a></li>
                                                    <li><a class="dropdown-item text-slate-700" href="javascript:void(0)" id="export-excel"><i class="bi bi-file-earmark-excel text-success me-2"></i> Export Excel (.xlsx)</a></li>
                                                    <li><a class="dropdown-item text-slate-700" href="javascript:void(0)" id="export-json"><i class="bi bi-filetype-json text-warning me-2"></i>Export JSON</a></li>
                                                    <li>
                                                        <hr class="dropdown-divider">
                                                    </li>
                                                    <li><a class="dropdown-item text-slate-700" href="javascript:void(0)" id="export-print"><i class="bi bi-printer text-primary me-2"></i>Print Table</a></li>
                                                </ul>
                                            </div>

                                            <button id="btn-refresh" class="btn btn-action d-inline-flex align-items-center">
                                                <i class="bi bi-arrow-clockwise me-2"></i>Refresh Data
                                            </button>
                                        </div>
                                    </div>

                                    <div id="tabulator-container" class="table table-bordered tabulator" style="min-height: 600px;"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- UPDATE OFFICE PREFIX MODAL -->
                    <div class="modal fade" id="modalEditPrefix" aria-hidden="true" data-bs-backdrop="static" aria-labelledby="exampleModalToggleLabel">
                        <div class="modal-dialog modal-dialog-centered modal-xl">
                            <div class="modal-content">
                                <div class="modal-header bg-light border-bottom py-3 px-4">
                                    <h6 class="modal-title" id="exampleModalToggleLabel">UPDATE OFFICE PREFIX CODE</h6>
                                    <button type="button" id="close" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form id="formUpdatePrefix" name="formUpdatePrefix" method="post" action="/">
                                    <div class="modal-body">
                                        <div class="row">
                                            <div class="col-12">
                                                <div class="card shadow-none border">
                                                    <div class="card-body p-4">
                                                        <div class="row g-3">
                                                            <input type="hidden" id="editOfficeId" name="office_id">
                                                            
                                                            <div class="col-lg-4 mb-2">
                                                                <label class="form-label" for="editOfficeName"><b>Office Name</b></label>
                                                                <input type="text" id="editOfficeName" class="form-control bg-light" readonly>
                                                            </div>
                                                            
                                                            <div class="col-lg-4 mb-2">
                                                                <label class="form-label" for="editOfficeCode"><b>Office Code</b></label>
                                                                <input type="text" id="editOfficeCode" class="form-control bg-light" readonly>
                                                            </div>

                                                            <div class="col-lg-4 mb-2">
                                                                <label class="form-label" for="editPrefixCode"><b>Prefix Code</b></label>
                                                                <input type="text" 
                                                                       id="editPrefixCode" 
                                                                       name="prefix_code" 
                                                                       class="form-control text-uppercase font-monospace fw-bold" 
                                                                       maxlength="10" 
                                                                       placeholder="e.g. REG, MISD, CASH" 
                                                                       required>
                                                                <small class="text-muted d-block mt-1">Used for ticket prefix (Max 10 chars)</small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-danger rounded-3" data-bs-dismiss="modal">Close</button>
                                        <button type="submit" class="btn btn-secondary rounded-3" id="btnSavePrefix" name="actionInfo" value="updateInfo">Update</button>
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

    <?php include_once ADMIN_SCRIPT_PATH; ?>

    <script>
        (function() {
            const AJAX_HANDLER = '<?= BASE_URL; ?>admin/ajax/office-prefix';
            const initialData = <?= json_encode($offices ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

            function escapeHtml(str) {
                return String(str || '')
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/"/g, "&quot;")
                    .replace(/'/g, "&#039;");
            }

            // Custom Formatters
            function officeCodeFormatter(cell) {
                const val = cell.getValue() || '';
                return `<span class="badge bg-light text-dark border font-monospace">${escapeHtml(val)}</span>`;
            }

            function prefixCodeFormatter(cell) {
                const val = cell.getValue();
                if (val && String(val).trim() !== '') {
                    return `<span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace fw-bold">
                                ${escapeHtml(val)}
                            </span>`;
                }
                return `<span class="badge bg-warning-subtle text-warning border border-warning-subtle font-monospace">
                            <i class="bi bi-exclamation-triangle me-1"></i>Unassigned
                        </span>`;
            }

            let tabulator = new Tabulator("#tabulator-container", {
                ajaxURL: AJAX_HANDLER,
                ajaxLoader: true,
                ajaxLoaderLoading: 'Fetching data from Database..',
                ajaxConfig: {
                    method: "GET",
                    headers: {
                        "Content-type": 'application/json; charset=utf-8',
                    },
                },
                ajaxParams: {
                    action: 'fetch_offices'
                },
                ajaxResponse: function(url, params, response) {
                    return response.data;
                },
                data: initialData,
                index: "office_id",
                layout: "fitColumns",
                responsiveLayout: "collapse",
                pagination: "local",
                paginationSize: 10,
                paginationSizeSelector: [10, 25, 50, 100, true],
                placeholder: "No active offices found.",
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
                        width: 120, 
                        headerSort: false,
                        download: false,
                        print: false,
                        vertAlign: 'middle',
                        hozAlign: 'center',
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const classBtn = "btn btn-action d-inline-flex align-items-center justify-content-center px-2 py-1 gap-1 btn-edit-prefix";
                            return `<button type="button" class="${classBtn}" data-id="${rowData.office_id}">
                                        <i class="bi bi-pencil-square"></i>
                                        <span style="font-size: 0.75rem;">Update</span>
                                    </button>`;
                        }
                    },
                    { title: "Office Name", field: "office_name", sorter: "string", vertAlign: 'middle', hozAlign: 'left' },
                    { title: "Office Code", field: "office_code", formatter: officeCodeFormatter, width: 140, vertAlign: 'middle', hozAlign: 'center' },
                    { title: "Prefix Code", field: "prefix_code", formatter: prefixCodeFormatter, hozAlign: "center", vertAlign: 'middle', width: 160 },
                    { 
                        title: "Last Modified", 
                        field: "date_modify", 
                        width: 180, 
                        vertAlign: 'middle',
                        hozAlign: 'center',
                        formatter: function(cell) {
                            const val = cell.getValue();
                            return val ? escapeHtml(val) : '—';
                        }
                    }
                ]
            });

            // Export Actions
            document.getElementById('export-csv')?.addEventListener('click', () => {
                tabulator.download("csv", "office_prefixes.csv");
            });

            document.getElementById('export-excel')?.addEventListener('click', () => {
                tabulator.download("xlsx", "office_prefixes.xlsx", { sheetName: "Offices" });
            });

            document.getElementById('export-json')?.addEventListener('click', () => {
                tabulator.download("json", "office_prefixes.json");
            });

            document.getElementById("export-print")?.addEventListener("click", function() {
                const tableTitle = 'Office Prefix Codes Report';
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

            // Global Search Handler
            const searchInput = document.getElementById('global-search');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const keyword = this.value.trim().toLowerCase();
                    tabulator.setFilter(function(data) {
                        const name = (data.office_name || '').toLowerCase();
                        const code = (data.office_code || '').toLowerCase();
                        const prefix = (data.prefix_code || '').toLowerCase();
                        return name.includes(keyword) || code.includes(keyword) || prefix.includes(keyword);
                    });
                });
            }

            // Refresh Data Handler (Triggers Tabulator's Built-in Gray Loader Modal)
            const refreshBtn = document.getElementById('btn-refresh');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', function() {
                    if (searchInput) searchInput.value = '';
                    tabulator.clearFilter();
                    tabulator.setData(); // Automatically displays "Fetching data from Database.." gray overlay
                });
            }

            // Open Modal Handler
            $(document).on('click', '.btn-edit-prefix', function() {
                const officeId = $(this).attr('data-id');
                const row = tabulator.getRow(officeId);

                if (row) {
                    const rowData = row.getData();
                    $('#editOfficeId').val(rowData.office_id);
                    $('#editOfficeName').val(rowData.office_name);
                    $('#editOfficeCode').val(rowData.office_code);
                    $('#editPrefixCode').val(rowData.prefix_code || '');
                }

                const modal = new bootstrap.Modal(document.getElementById('modalEditPrefix'));
                modal.show();
            });

            // Input Sanitization (Uppercase Alphanumeric + Hyphens/Underscores)
            $('#editPrefixCode').on('input', function() {
                $(this).val($(this).val().toUpperCase().replace(/[^A-Z0-9_-]/g, ''));
            });

            // Form Submit Handler
            $('#formUpdatePrefix').on('submit', function(e) {
                e.preventDefault();

                const btn = $('#btnSavePrefix');
                const originalHtml = btn.html();
                btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Saving...');

                const officeId = $('#editOfficeId').val();
                const newPrefix = $('#editPrefixCode').val();

                const formData = new FormData();
                formData.append('action', 'update_prefix_code');
                formData.append('office_id', officeId);
                formData.append('prefix_code', newPrefix);

                fetch(AJAX_HANDLER, { method: 'POST', body: formData })
                .then(async res => {
                    const text = await res.text();
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        throw new Error('Server returned invalid response format.');
                    }
                })
                .then(res => {
                    btn.prop('disabled', false).html(originalHtml);

                    if (res.success) {
                        tabulator.setData(); // Refresh table data dynamically with loader

                        const modalEl = document.getElementById('modalEditPrefix');
                        const modalInstance = bootstrap.Modal.getInstance(modalEl);
                        if (modalInstance) modalInstance.hide();

                        if (typeof showSuccess === 'function') {
                            showSuccess(res.message || 'Prefix code updated successfully.');
                        } else if (typeof Swal !== 'undefined') {
                            Swal.fire({ icon: 'success', title: 'Updated!', text: res.message || 'Prefix code updated successfully.', timer: 1500, showConfirmButton: false });
                        } else {
                            alert(res.message || 'Prefix code updated successfully.');
                        }
                    } else {
                        if (typeof showError === 'function') {
                            showError(res.message || 'Unable to update prefix code.');
                        } else if (typeof Swal !== 'undefined') {
                            Swal.fire({ icon: 'error', title: 'Failed', text: res.message || 'Unable to update prefix code.' });
                        } else {
                            alert(res.message || 'Unable to update prefix code.');
                        }
                    }
                })
                .catch(err => {
                    btn.prop('disabled', false).html(originalHtml);
                    if (typeof showError === 'function') {
                        showError(err.message || 'An unexpected server error occurred.');
                    } else {
                        alert(err.message || 'An unexpected server error occurred.');
                    }
                });
            });
        })();
    </script>
</body>
</html>