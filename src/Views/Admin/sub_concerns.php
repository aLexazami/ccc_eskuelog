<?php
// src/Views/Admin/sub_concerns.php

# Core Config Dependencies
$db_connect  = $db_connect ?? null;
$g_user_role = $g_user_role ?? '';

require_once HELPER;
require_once ISLOGIN;
require_once API_CONNECT;

# Page Helpers
require_once ADMIN_PAGE_HEADER_PATH;

# Page Header Initialization
$title_page   = "Sub Concerns Management";
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

// Fetch active offices & transaction types for dropdown filters
$offices = [];
$transaction_types = [];

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

    $stmtTypes = $db_connect->query("
        SELECT tt.id, tt.transaction_name, o.office_code 
        FROM transaction_types tt
        INNER JOIN office o ON tt.office_id = o.office_id
        WHERE tt.flag_status = 0 
        ORDER BY o.office_code ASC, tt.transaction_name ASC
    ");
    if ($stmtTypes) {
        while ($row = $stmtTypes->fetch_assoc()) {
            $transaction_types[] = $row;
        }
    }
}

$action_buttons = [
    [
        'label' => 'Add Sub-Concern',
        'icon'  => 'bi bi-plus-circle',
        'class' => 'add-sub-btn',
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
                                                <i class="bi bi-list-nested fs-4"></i>
                                            </div>

                                            <div>
                                                <div class="text-uppercase text-muted fw-bold tracking-wide small" style="font-size: 0.95rem;">
                                                    Sub Concerns Specification Masterlist
                                                </div>
                                                <div class="d-flex align-items-center text-muted small mt-0.5" style="font-size: 0.75rem;">
                                                    <i class="bi bi-info-circle me-1"></i> Manage specific sub-concerns under each main office transaction service
                                                </div>
                                            </div>
                                        </div>

                                        <div class="d-flex align-items-start align-items-md-end gap-2 ms-md-auto">
                                            <?php render_header_button($action_buttons); ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="card-body">
                                    <!-- Toolbar with Search, Filters and Export Buttons -->
                                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                                        <div class="d-flex align-items-center gap-2 flex-grow-1" style="max-width: 700px;">
                                            <select id="filterOffice" class="form-select" style="max-width: 220px;">
                                                <option value="0">-- All Offices --</option>
                                                <?php foreach ($offices as $off): ?>
                                                    <option value="<?= $off['office_id']; ?>"><?= htmlspecialchars($off['office_name']); ?> (<?= htmlspecialchars($off['office_code']); ?>)</option>
                                                <?php endforeach; ?>
                                            </select>

                                            <select id="filterType" class="form-select" style="max-width: 240px;">
                                                <option value="0">-- All Services --</option>
                                                <?php foreach ($transaction_types as $tt): ?>
                                                    <option value="<?= $tt['id']; ?>">[<?= htmlspecialchars($tt['office_code']); ?>] <?= htmlspecialchars($tt['transaction_name']); ?></option>
                                                <?php endforeach; ?>
                                            </select>

                                            <div class="search-input-group flex-grow-1">
                                                <i class="bi bi-search search-icon"></i>
                                                <input type="text" id="global-search" class="form-control" placeholder="Search sub-concern, service...">
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

                    <!-- CREATE SUB CONCERN MODAL -->
                    <div class="modal fade" id="add_sub_modal" aria-hidden="true" data-bs-backdrop="static" aria-labelledby="addSubModalLabel">
                        <div class="modal-dialog modal-dialog-centered modal-lg">
                            <div class="modal-content">
                                <div class="modal-header bg-light border-bottom py-3 px-4">
                                    <h5 class="modal-title" id="addSubModalLabel">Create Sub-Concern</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form id="add_sub_form">
                                    <div class="modal-body">
                                        <div class="row p-3">
                                            <div class="col-12 border p-3 mb-3">
                                                <div class="row">
                                                    <h5><strong>Sub-Concern Details</strong></h5>
                                                    <hr>

                                                    <div class="col-lg-6 mb-2">
                                                        <label class="form-label" for="add_office_id"><b>Office Location</b></label>
                                                        <select id="add_office_id" class="form-select" required>
                                                            <option value="" selected disabled>Select Office</option>
                                                            <?php foreach ($offices as $off): ?>
                                                                <option value="<?= $off['office_id']; ?>"><?= htmlspecialchars($off['office_name']); ?> (<?= htmlspecialchars($off['office_code']); ?>)</option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>

                                                    <div class="col-lg-6 mb-2">
                                                        <label class="form-label" for="add_transaction_type_id"><b>Transaction Type / Service</b></label>
                                                        <select name="transaction_type_id" id="add_transaction_type_id" class="form-select" required>
                                                            <option value="" selected disabled>Select Office First</option>
                                                        </select>
                                                    </div>

                                                    <div class="col-lg-8 mb-2">
                                                        <label class="form-label" for="add_sub_concern_name"><b>Sub-Concern Specification Name</b></label>
                                                        <input type="text" name="sub_concern_name" class="form-control" id="add_sub_concern_name" placeholder="e.g. Official Endorsement Letter, Certificate Verification" required>
                                                    </div>

                                                    <div class="col-lg-4 mb-2">
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
                                        <button type="submit" class="btn btn-secondary rounded-3" id="btn_submit_add" name="actionSubmit" value="submitSubConcern">Submit</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- UPDATE SUB CONCERN MODAL -->
                    <div class="modal fade" id="update_sub_modal" aria-hidden="true" data-bs-backdrop="static" aria-labelledby="updateSubModalLabel">
                        <div class="modal-dialog modal-dialog-centered modal-lg">
                            <div class="modal-content">
                                <div class="modal-header bg-light border-bottom py-3 px-4">
                                    <h6 class="modal-title" id="updateSubModalLabel">Update Sub-Concern</h6>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form id="update_sub_form">
                                    <div class="modal-body">
                                        <div class="row p-3">
                                            <div class="col-12 border p-3 mb-3">
                                                <div class="row">
                                                    <h5><strong>Sub-Concern Details</strong></h5>
                                                    <hr>

                                                    <input type="hidden" name="sub_id" id="u_sub_id">

                                                    <div class="col-lg-6 mb-2">
                                                        <label class="form-label" for="u_office_id"><b>Office Location</b></label>
                                                        <select id="u_office_id" class="form-select" required>
                                                            <option value="" selected disabled>Select Office</option>
                                                            <?php foreach ($offices as $off): ?>
                                                                <option value="<?= $off['office_id']; ?>"><?= htmlspecialchars($off['office_name']); ?> (<?= htmlspecialchars($off['office_code']); ?>)</option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>

                                                    <div class="col-lg-6 mb-2">
                                                        <label class="form-label" for="u_transaction_type_id"><b>Transaction Type / Service</b></label>
                                                        <select name="transaction_type_id" id="u_transaction_type_id" class="form-select" required>
                                                            <option value="" selected disabled>Select Office First</option>
                                                        </select>
                                                    </div>

                                                    <div class="col-lg-8 mb-2">
                                                        <label class="form-label" for="u_sub_concern_name"><b>Sub-Concern Specification Name</b></label>
                                                        <input type="text" name="sub_concern_name" class="form-control" id="u_sub_concern_name" required>
                                                    </div>

                                                    <div class="col-lg-4 mb-2">
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
                                        <button type="submit" class="btn btn-secondary rounded-3" id="btn_submit_update" name="actionInfo" value="submitUpdateSubConcern">Update Information</button>
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
        const AJAX_HANDLER = "<?php echo BASE_URL; ?>admin/ajax/sub-concerns";
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

        function statusFormatter(cell) {
            return cell.getValue() == 0 ? 'Active' : 'Disabled';
        }

        /* Tabulator Table Setup */
        function initTabulatorTable() {
            tabulator = new Tabulator("#tabulator-container", {
                ajaxURL: AJAX_HANDLER,
                ajaxLoader: true,
                ajaxLoaderLoading: 'Fetching sub-concerns from Database..',
                ajaxConfig: "POST",
                ajaxContentType: "form",
                ajaxParams: function() {
                    return {
                        action: 'fetch_sub_concerns',
                        office_id: $('#filterOffice').val(),
                        transaction_type_id: $('#filterType').val()
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
                placeholder: "No active sub-concerns found.",
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
                                        <button type="button" class="${classBtnDelete}" data-id="${rowData.id}" title="Delete Sub Concern">
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
                                const subId = parseInt(btn.getAttribute('data-id'), 10);
                                deleteSubConcern(subId);
                            }
                        }
                    },
                    { title: "Office Location", field: "office_name", formatter: officeFormatter, sorter: "string", minWidth: 240, vertAlign: 'middle', hozAlign: 'left' },
                    { title: "Main Service / Transaction Type", field: "transaction_name", sorter: "string", minWidth: 240, vertAlign: 'middle', hozAlign: 'left' },
                    { title: "Sub-Concern Specification", field: "sub_concern_name", sorter: "string", minWidth: 260, vertAlign: 'middle', hozAlign: 'left' },
                    { title: "Status", field: "flag_status", formatter: statusFormatter, width: 140, vertAlign: 'middle', hozAlign: 'center' }
                ]
            });

            // Export Actions
            document.getElementById('export-csv')?.addEventListener('click', () => {
                tabulator.download("csv", "sub_concerns.csv");
            });

            document.getElementById('export-excel')?.addEventListener('click', () => {
                tabulator.download("xlsx", "sub_concerns.xlsx", { sheetName: "SubConcerns" });
            });

            document.getElementById('export-json')?.addEventListener('click', () => {
                tabulator.download("json", "sub_concerns.json");
            });

            document.getElementById("export-print")?.addEventListener("click", function() {
                const tableTitle = 'Sub Concerns Masterlist Report';
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

            // Filter Handlers
            $('#filterOffice, #filterType').on('change', function() {
                if (tabulator) tabulator.setData();
            });

            // Global Search Handler
            const searchInput = document.getElementById('global-search');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const keyword = this.value.trim().toLowerCase();
                    tabulator.setFilter(function(data) {
                        const office = (data.office_name || '').toLowerCase();
                        const code   = (data.office_code || '').toLowerCase();
                        const type   = (data.transaction_name || '').toLowerCase();
                        const sub    = (data.sub_concern_name || '').toLowerCase();
                        return office.includes(keyword) || code.includes(keyword) || type.includes(keyword) || sub.includes(keyword);
                    });
                });
            }

            // Refresh Data Handler
            const refreshBtn = document.getElementById('btn-refresh');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', function() {
                    if (searchInput) searchInput.value = '';
                    $('#filterOffice').val('0');
                    $('#filterType').val('0');
                    tabulator.clearFilter();
                    tabulator.setData();
                });
            }
        }

        /* Dynamic Loading of Transaction Types by Office in Modals */
        function loadModalTypesByOffice(officeId, targetSelectId, selectedTypeId = 0) {
            const $select = $(`#${targetSelectId}`);
            $select.html('<option value="" disabled selected>Loading services...</option>');

            $.ajax({
                url: AJAX_HANDLER,
                type: 'POST',
                data: {
                    action: 'fetch_types_by_office',
                    office_id: officeId
                },
                dataType: 'json',
                success: function(res) {
                    if (res.success && res.data) {
                        let options = '<option value="" disabled selected>Select Transaction Service</option>';
                        res.data.forEach(item => {
                            const isSel = (parseInt(item.id) === parseInt(selectedTypeId)) ? 'selected' : '';
                            options += `<option value="${item.id}" ${isSel}>${escapeHtml(item.transaction_name)}</option>`;
                        });
                        $select.html(options);
                    } else {
                        $select.html('<option value="" disabled selected>No services found</option>');
                    }
                }
            });
        }

        // Add Modal Office Change Listener
        $('#add_office_id').on('change', function() {
            const officeId = $(this).val();
            loadModalTypesByOffice(officeId, 'add_transaction_type_id');
        });

        // Update Modal Office Change Listener
        $('#u_office_id').on('change', function() {
            const officeId = $(this).val();
            loadModalTypesByOffice(officeId, 'u_transaction_type_id');
        });

        /* Initialize Tabulator Table */
        initTabulatorTable();

        /*** Add Sub-Concern Button ***/
        $('.add-sub-btn').on('click', function() {
            $('#add_sub_modal').modal('show');
            $('#add_sub_modal').find('form').trigger('reset');
            $('#add_transaction_type_id').html('<option value="" disabled selected>Select Office First</option>');
        });

        /*** Submit Add Sub-Concern ***/
        $("#add_sub_form").on('submit', async function(e) {
            e.preventDefault();

            const isConfirmed = await notifyConfirm(
                'Confirm Submission',
                'Are you sure you want to save this sub-concern specification?',
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
                value: "submitSubConcern"
            });

            $.ajax({
                url: AJAX_HANDLER,
                method: "POST",
                data: formData,
                dataType: "json",
                beforeSend: function() {
                    showLoader('Saving sub-concern...');
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
                        $("#add_sub_modal").modal('hide');
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
            document.getElementById("update_sub_modal").querySelector("#u_sub_id").value = data.id;
            document.getElementById("update_sub_modal").querySelector("#u_office_id").value = data.office_id;
            document.getElementById("update_sub_modal").querySelector("#u_sub_concern_name").value = data.sub_concern_name;
            document.getElementById("update_sub_modal").querySelector("#u_flag_status").value = data.flag_status;

            loadModalTypesByOffice(data.office_id, 'u_transaction_type_id', data.transaction_type_id);

            $("#update_sub_modal").modal("show");
        }

        /*** Submit Update Sub-Concern ***/
        $('#update_sub_form').on('submit', async function(e) {
            e.preventDefault();

            const isConfirmed = await notifyConfirm(
                'Confirm Submission',
                'Are you sure you want to update this sub-concern specification?',
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
                value: "submitUpdateSubConcern"
            });

            $.ajax({
                url: AJAX_HANDLER,
                type: 'POST',
                data: formData,
                dataType: "json",
                beforeSend: function() {
                    showLoader('Updating sub-concern...');
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
                        $("#update_sub_modal").modal('hide');
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

        /*** Delete Sub-Concern ***/
        async function deleteSubConcern(subId) {
            if (!subId) return;

            const isConfirmed = await notifyConfirm(
                'Confirm Deletion',
                'Are you sure you want to permanently delete this sub-concern? This action cannot be undone.',
                'Yes, delete it!',
                'warning'
            );
            if (!isConfirmed) return;

            $.ajax({
                url: AJAX_HANDLER,
                type: 'POST',
                data: {
                    action: 'delete_sub_concern',
                    sub_id: subId
                },
                dataType: "json",
                beforeSend: function() {
                    showLoader('Deleting sub-concern...');
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