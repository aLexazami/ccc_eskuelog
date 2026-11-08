<?php
// src/Views/Admin/transaction_types.php

# Core Config Dependencies
$db_connect  = $db_connect ?? null;
$g_user_role = $g_user_role ?? '';

require_once HELPER;
require_once ISLOGIN;
require_once API_CONNECT;

# Page Helpers
require_once ADMIN_PAGE_HEADER_PATH;

# Page Header Initialization
$title_page   = "Transaction Types & Services";
$active_links = [
    ['label' => $title_page, 'url' => '']
];
$page_header  = render_page_header((string) $title_page, $active_links) ?? [];
# ===================================================================================

# Validate Access Control
$system_auth_login = $session_class->getValue(SYSTEM_ACCESS[SYSTEM_ACCESS_NAME]['auth']);
if (!($g_user_role == "ADMIN") && !($system_auth_login == $g_public_key)) {
    header("location: " . SYSTEM_ACCESS[SYSTEM_ACCESS_NAME]['link']['main']);
    exit();
}

// Fetch active offices for dropdown filters
$offices = [];
if ($db_connect) {
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
}

$action_buttons = [
    [
        'label' => 'Add Transaction Type',
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
</head>

<body>
    <div class="wrapper">
        <?php include_once ADMIN_SIDEBAR_PATH; ?>
        <div class="main-panel">

            <?php include_once ADMIN_HEADER_PATH; ?>

            <div class="container">
                <div class="page-inner">

                    <!-- Page Header -->
                    <?php echo $page_header; ?>

                    <div class="row">
                        <div class="col-12">
                            <div class="card">

                                <div class="card-header d-flex align-items-center justify-content-between">
                                    <div class="d-flex justify-content-between align-items-start align-items-md-center gap-3 w-100">

                                        <div class="d-flex align-items-center gap-3">
                                            <div class="bg-primary-subtle text-primary rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                                                <i class="bi bi-card-checklist fs-4"></i>
                                            </div>

                                            <div>
                                                <div class="text-uppercase text-muted fw-bold tracking-wide small" style="font-size: 0.95rem;">
                                                    Campus Transaction Services Masterlist
                                                </div>
                                                <div class="d-flex align-items-center text-muted small mt-0.5" style="font-size: 0.75rem;">
                                                    <i class="bi bi-info-circle me-1"></i> Manage main campus office service offerings and estimated duration times
                                                </div>
                                            </div>
                                        </div>

                                        <div class="d-flex align-items-start align-items-md-end gap-2 ms-md-auto">
                                            <?php render_header_button($action_buttons); ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="card-body">
                                    <!-- Toolbar with Search, Filter and Export Buttons -->
                                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                                        <div class="d-flex align-items-center gap-2 flex-grow-1" style="max-width: 600px;">
                                            <select id="filterOffice" class="form-select" style="max-width: 240px;">
                                                <option value="0">-- All Campus Offices --</option>
                                                <?php foreach ($offices as $off): ?>
                                                    <option value="<?= $off['office_id']; ?>"><?= htmlspecialchars($off['office_name']); ?> (<?= htmlspecialchars($off['office_code']); ?>)</option>
                                                <?php endforeach; ?>
                                            </select>

                                            <div class="search-input-group flex-grow-1">
                                                <i class="bi bi-search search-icon"></i>
                                                <input type="text" id="global-search" class="form-control" placeholder="Search service name, office code...">
                                            </div>
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
                                                    <li><a class="dropdown-item text-slate-700" href="javascript:void(0)" id="export-json"><i class="bi bi-filetype-json text-warning me-2"></i> Export JSON</a></li>
                                                    <li>
                                                        <hr class="dropdown-divider">
                                                    </li>
                                                    <li><a class="dropdown-item text-slate-700" href="javascript:void(0)" id="export-print"><i class="bi bi-printer text-primary me-2"></i> Print Table</a></li>
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

                    <!-- CREATE TRANSACTION TYPE MODAL -->
                    <div class="modal fade" id="add_type_modal" aria-hidden="true" data-bs-backdrop="static" aria-labelledby="addTypeModalLabel">
                        <div class="modal-dialog modal-dialog-centered modal-lg">
                            <div class="modal-content">
                                <div class="modal-header bg-light border-bottom py-3 px-4">
                                    <h5 class="modal-title" id="addTypeModalLabel">Create Transaction Type</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form id="add_type_form">
                                    <div class="modal-body">
                                        <div class="row p-3">
                                            <div class="col-12 border p-3 mb-3">
                                                <div class="row">
                                                    <h5><strong>Service Details</strong></h5>
                                                    <hr>

                                                    <div class="col-lg-6 mb-2">
                                                        <label class="form-label" for="add_office_id"><b>Assigned Office Location</b></label>
                                                        <select name="office_id" id="add_office_id" class="form-select" required>
                                                            <option value="" selected disabled>Select Office</option>
                                                            <?php foreach ($offices as $off): ?>
                                                                <option value="<?= $off['office_id']; ?>"><?= htmlspecialchars($off['office_name']); ?> (<?= htmlspecialchars($off['office_code']); ?>)</option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>

                                                    <div class="col-lg-6 mb-2">
                                                        <label class="form-label" for="add_transaction_name"><b>Transaction Service Name</b></label>
                                                        <input type="text" name="transaction_name" class="form-control" id="add_transaction_name" placeholder="e.g. Transcript Request, ID Validation" required>
                                                    </div>

                                                    <div class="col-lg-6 mb-2">
                                                        <label class="form-label" for="add_estimated_minutes"><b>Estimated Processing Time (Minutes)</b></label>
                                                        <input type="number" name="estimated_minutes" class="form-control" id="add_estimated_minutes" value="5" min="1" max="180" required>
                                                    </div>

                                                    <div class="col-lg-6 mb-2">
                                                        <label class="form-label" for="add_flag_status"><b>Status</b></label>
                                                        <select name="flag_status" id="add_flag_status" class="form-select">
                                                            <option value="0" selected>Active</option>
                                                            <option value="1">Disabled</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-danger rounded-3" data-bs-dismiss="modal">Close</button>
                                        <button type="submit" class="btn btn-secondary rounded-3" id="btn_submit_add" name="actionSubmit" value="submitType">Submit</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- UPDATE TRANSACTION TYPE MODAL -->
                    <div class="modal fade" id="update_type_modal" aria-hidden="true" data-bs-backdrop="static" aria-labelledby="updateTypeModalLabel">
                        <div class="modal-dialog modal-dialog-centered modal-lg">
                            <div class="modal-content">
                                <div class="modal-header bg-light border-bottom py-3 px-4">
                                    <h6 class="modal-title" id="updateTypeModalLabel">Update Transaction Type</h6>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form id="update_type_form">
                                    <div class="modal-body">
                                        <div class="row p-3">
                                            <div class="col-12 border p-3 mb-3">
                                                <div class="row">
                                                    <h5><strong>Service Details</strong></h5>
                                                    <hr>

                                                    <input type="hidden" name="type_id" id="u_type_id">

                                                    <div class="col-lg-6 mb-2">
                                                        <label class="form-label" for="u_office_id"><b>Assigned Office Location</b></label>
                                                        <select name="office_id" id="u_office_id" class="form-select" required>
                                                            <option value="" selected disabled>Select Office</option>
                                                            <?php foreach ($offices as $off): ?>
                                                                <option value="<?= $off['office_id']; ?>"><?= htmlspecialchars($off['office_name']); ?> (<?= htmlspecialchars($off['office_code']); ?>)</option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>

                                                    <div class="col-lg-6 mb-2">
                                                        <label class="form-label" for="u_transaction_name"><b>Transaction Service Name</b></label>
                                                        <input type="text" name="transaction_name" class="form-control" id="u_transaction_name" placeholder="e.g. Transcript Request" required>
                                                    </div>

                                                    <div class="col-lg-6 mb-2">
                                                        <label class="form-label" for="u_estimated_minutes"><b>Estimated Processing Time (Minutes)</b></label>
                                                        <input type="number" name="estimated_minutes" class="form-control" id="u_estimated_minutes" min="1" max="180" required>
                                                    </div>

                                                    <div class="col-lg-6 mb-2">
                                                        <label class="form-label" for="u_flag_status"><b>Status</b></label>
                                                        <select name="flag_status" id="u_flag_status" class="form-select">
                                                            <option value="0">Active</option>
                                                            <option value="1">Disabled</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-danger rounded-3" data-bs-dismiss="modal">Close</button>
                                        <button type="submit" class="btn btn-secondary rounded-3" id="btn_submit_update" name="actionInfo" value="updateType">Update Information</button>
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
        const AJAX_HANDLER = "<?php echo BASE_URL; ?>admin/ajax/transaction-types";
        let tabulator = null;

        function escapeHtml(str) {
            return String(str || '')
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        /* Column Formatters */
        function officeFormatter(cell) {
            const row = cell.getRow().getData();
            return `${escapeHtml(cell.getValue())} (${escapeHtml(row.office_code)})`;
        }

        function estTimeFormatter(cell) {
            const mins = parseInt(cell.getValue()) || 5;
            return `<span class="badge bg-light text-dark border font-monospace"><i class="bi bi-clock me-1"></i>${mins} min(s)</span>`;
        }

        function statusFormatter(cell) {
            return cell.getValue() == 0 ? 'Active' : 'Disabled';
        }

        /* Tabulator Table Setup */
        function initTabulatorTable() {
            tabulator = new Tabulator("#tabulator-container", {
                ajaxURL: AJAX_HANDLER,
                ajaxLoader: true,
                ajaxLoaderLoading: 'Fetching transaction types from Database..',
                ajaxConfig: "POST",
                ajaxContentType: "form",
                ajaxParams: function() {
                    return {
                        action: 'fetch_transaction_types',
                        office_id: $('#filterOffice').val()
                    };
                },
                ajaxResponse: function(url, params, response) {
                    return response.data || [];
                },
                index: "id",
                layout: "fitColumns",
                responsiveLayout: "collapse",
                pagination: "local",
                paginationSize: 10,
                paginationSizeSelector: [10, 25, 50, 100, true],
                placeholder: "No active transaction services found.",
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
                                        <button type="button" class="${classBtnEdit}" data-id="${rowData.id}" title="Update Information">
                                            <i class="bi bi-pencil-square"></i>
                                            <span style="font-size: 0.75rem;">Update</span>
                                        </button>
                                        <button type="button" class="${classBtnDelete}" data-id="${rowData.id}" title="Delete Type">
                                            <i class="bi bi-trash"></i>
                                            <span style="font-size: 0.75rem;">Delete</span>
                                        </button>
                                    </div>`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.closest('.btn-update-row')) {
                                openUpdateModal(cell.getRow().getData());
                            } else if (e.target.closest('.btn-delete-row')) {
                                const btn = e.target.closest('.btn-delete-row');
                                const typeId = parseInt(btn.getAttribute('data-id'), 10);
                                deleteTransactionType(typeId);
                            }
                        }
                    },
                    { title: "Office Location", field: "office_name", formatter: officeFormatter, sorter: "string", minWidth: 260, vertAlign: 'middle', hozAlign: 'left' },
                    { title: "Transaction Service Name", field: "transaction_name", sorter: "string", minWidth: 240, vertAlign: 'middle', hozAlign: 'left' },
                    { title: "Est. Duration", field: "estimated_minutes", formatter: estTimeFormatter, width: 140, vertAlign: 'middle', hozAlign: 'center' },
                    { title: "Status", field: "flag_status", formatter: statusFormatter, width: 140, vertAlign: 'middle', hozAlign: 'center' }
                ]
            });

            // Export Actions
            document.getElementById('export-csv')?.addEventListener('click', () => {
                tabulator.download("csv", "transaction_types.csv");
            });

            document.getElementById('export-excel')?.addEventListener('click', () => {
                tabulator.download("xlsx", "transaction_types.xlsx", { sheetName: "Services" });
            });

            document.getElementById('export-json')?.addEventListener('click', () => {
                tabulator.download("json", "transaction_types.json");
            });

            document.getElementById("export-print")?.addEventListener("click", function() {
                const tableTitle = 'Campus Transaction Services & Types Report';
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

            // Filter by Office Handler
            $('#filterOffice').on('change', function() {
                if (tabulator) tabulator.setData();
            });

            // Global Search Handler
            const searchInput = document.getElementById('global-search');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const keyword = this.value.trim().toLowerCase();
                    tabulator.setFilter(function(data) {
                        const office  = (data.office_name || '').toLowerCase();
                        const code    = (data.office_code || '').toLowerCase();
                        const service = (data.transaction_name || '').toLowerCase();
                        return office.includes(keyword) || code.includes(keyword) || service.includes(keyword);
                    });
                });
            }

            // Refresh Data Handler
            const refreshBtn = document.getElementById('btn-refresh');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', function() {
                    if (searchInput) searchInput.value = '';
                    $('#filterOffice').val('0');
                    tabulator.clearFilter();
                    tabulator.setData();
                });
            }
        }

        /* Initialize Tabulator Table */
        initTabulatorTable();

        /*** Add type modal ***/
        $('.add-type-btn').on('click', function() {
            $('#add_type_modal').modal('show');
            $('#add_type_modal').find('form').trigger('reset');
            $('#add_estimated_minutes').val(5);
        });

        /*** Submit Add Transaction Type ***/
        $("#add_type_form").on('submit', async function(e) {
            e.preventDefault();

            const isConfirmed = await notifyConfirm(
                'Confirm Submission',
                'Are you sure you want to save this transaction service?',
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
                value: "submitType"
            });

            $.ajax({
                url: AJAX_HANDLER,
                method: "POST",
                data: formData,
                dataType: "json",
                beforeSend: function() {
                    showLoader('Saving transaction type...');
                    $submitBtn.data('orig-text', $submitBtn.html());
                    $submitBtn.html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Loading..');
                    $inputs.prop("disabled", true);
                },
                complete: function() {
                    hideLoader();
                    $submitBtn.html($submitBtn.data('orig-text') || 'Submit');
                    $inputs.prop("disabled", false);
                },
                success: function(output) {
                    if (output.msg_status === true) {
                        if (tabulator && typeof tabulator.setData === 'function') tabulator.setData();
                        $("#add_type_modal").modal('hide');
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

        /*** Open Update Modal ***/
        function openUpdateModal(data) {
            document.getElementById("update_type_modal").querySelector("#u_type_id").value = data.id;
            document.getElementById("update_type_modal").querySelector("#u_office_id").value = data.office_id;
            document.getElementById("update_type_modal").querySelector("#u_transaction_name").value = data.transaction_name;
            document.getElementById("update_type_modal").querySelector("#u_estimated_minutes").value = data.estimated_minutes;
            document.getElementById("update_type_modal").querySelector("#u_flag_status").value = data.flag_status;

            $("#update_type_modal").modal("show");
        }

        /*** Submit Update Transaction Type ***/
        $('#update_type_form').on('submit', async function(e) {
            e.preventDefault();

            const isConfirmed = await notifyConfirm(
                'Confirm Submission',
                'Are you sure you want to update this transaction service?',
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
                value: "submitUpdateType"
            });

            $.ajax({
                url: AJAX_HANDLER,
                type: 'POST',
                data: formData,
                dataType: "json",
                beforeSend: function() {
                    showLoader('Updating transaction type...');
                    $submitBtn.data('orig-text', $submitBtn.html());
                    $submitBtn.html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Loading..');
                    $inputs.prop("disabled", true);
                },
                complete: function() {
                    hideLoader();
                    $submitBtn.html($submitBtn.data('orig-text') || 'Update Information');
                    $inputs.prop("disabled", false);
                },
                success: function(output) {
                    if (output && output.msg_status === true) {
                        if (tabulator && typeof tabulator.setData === 'function') tabulator.setData();
                        $("#update_type_modal").modal('hide');
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

        /*** Delete Transaction Type ***/
        async function deleteTransactionType(typeId) {
            if (!typeId) return;

            const isConfirmed = await notifyConfirm(
                'Confirm Deletion',
                'Are you sure you want to permanently delete this transaction service? All associated sub-concerns will also be deleted.',
                'Yes, delete it!',
                'warning'
            );
            if (!isConfirmed) return;

            $.ajax({
                url: AJAX_HANDLER,
                type: 'POST',
                data: {
                    action: 'delete_transaction_type',
                    id: typeId
                },
                dataType: "json",
                beforeSend: function() {
                    showLoader('Deleting transaction service...');
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