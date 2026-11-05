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
    ['label' => 'Office Management', 'url' => 'javascript:void(0)'],
    ['label' => 'Office Prefix Codes', 'url' => '']
];
$page_header = render_page_header((string) $title_page, $active_links) ?? '';

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

// Fetch offices list
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

    <style>
        .prefix-card {
            border: 1px solid #e2e8f0;
            border-radius: 0.85rem;
            background: #ffffff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
            overflow: hidden;
        }

        .kiosk-header-banner {
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
            color: #ffffff;
            padding: 1.25rem 1.5rem;
        }

        .prefix-badge {
            font-size: 0.875rem;
            letter-spacing: 0.05em;
            padding: 0.35em 0.75em;
        }

        /* Prevent header text from truncating on small width columns */
        .tabulator .tabulator-header .tabulator-col .tabulator-col-content {
            padding: 8px 4px;
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
                    <div class="kiosk-page-header mb-3">
                        <?php echo is_array($page_header) ? '' : $page_header; ?>
                    </div>

                    <!-- OFFICE PREFIX MANAGEMENT CARD -->
                    <div class="card prefix-card border-0">
                        <!-- KIOSK-STYLE HEADER BANNER WITH SUBTITLE -->
                        <div class="kiosk-header-banner d-flex align-items-center justify-content-between gap-3 flex-wrap">
                            <div class="d-flex align-items-center gap-2 gap-sm-3 min-w-0">
                                <div class="p-2 rounded-3 text-white d-flex align-items-center justify-content-center flex-shrink-0" style="background-color: rgba(255, 255, 255, 0.2); width: 42px; height: 42px;">
                                    <i class="bi bi-building-gear fs-4"></i>
                                </div>
                                <div class="min-w-0">
                                    <h4 class="fw-bold text-white mb-0 tracking-wide text-truncate fs-6 fs-sm-5">ASSIGNED OFFICE PREFIXES</h4>
                                    <small class="text-white-50 d-block text-truncate">Manage ticket generation prefix codes for each active office location</small>
                                </div>
                            </div>

                            <div class="d-flex align-items-center flex-shrink-0 ms-auto">
                                <input type="text" id="searchOfficeInput" class="form-control form-control-sm bg-white text-dark shadow-sm" placeholder="Search office or code..." style="max-width: 240px;">
                            </div>
                        </div>

                        <div class="card-body p-3">
                            <div id="officePrefixTable"></div>
                        </div>
                    </div>

                </div>
            </div>

            <?php include_once ADMIN_FOOTER_PATH; ?>
        </div>
    </div>

    <!-- EDIT PREFIX CODE MODAL -->
    <div class="modal fade" id="modalEditPrefix" tabindex="-1" aria-labelledby="modalEditPrefixLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="formUpdatePrefix">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="modalEditPrefixLabel">
                            <i class="bi bi-tag-fill text-primary me-2"></i>Assign Prefix Code
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="editOfficeId" name="office_id">
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted mb-1">Office Name</label>
                            <input type="text" id="editOfficeName" class="form-control bg-light" readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted mb-1">Office Code</label>
                            <input type="text" id="editOfficeCode" class="form-control bg-light" readonly>
                        </div>

                        <div class="mb-3">
                            <label for="editPrefixCode" class="form-label small fw-bold text-muted mb-1">
                                Prefix Code <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   id="editPrefixCode" 
                                   name="prefix_code" 
                                   class="form-control text-uppercase font-monospace fw-bold" 
                                   maxlength="10" 
                                   placeholder="e.g. REG, MISD, CASH" 
                                   required>
                            <div class="form-text small">Used for prefixing queue ticket numbers generated by this office (Max 10 characters).</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm fw-bold px-3" id="btnSavePrefix">
                            <i class="bi bi-save me-1"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include_once ADMIN_SCRIPT_PATH; ?>

    <script>
        (function() {
            const AJAX_HANDLER = '<?= BASE_URL; ?>admin/ajax/office-prefix';
            
            // 1. Pass PHP dataset to JS safely
            const initialData = <?= json_encode($offices ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

            function escapeHtml(str) {
                return String(str || '')
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/"/g, "&quot;")
                    .replace(/'/g, "&#039;");
            }

            // 2. Custom Formatters
            function officeCodeFormatter(cell) {
                const val = cell.getValue() || '';
                return `<span class="badge bg-light text-dark border font-monospace">${escapeHtml(val)}</span>`;
            }

            function prefixCodeFormatter(cell) {
                const val = cell.getValue();
                if (val && String(val).trim() !== '') {
                    return `<span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace fw-bold prefix-badge">
                                ${escapeHtml(val)}
                            </span>`;
                }
                return `<span class="badge bg-warning-subtle text-warning border border-warning-subtle font-monospace prefix-badge">
                            <i class="bi bi-exclamation-triangle me-1"></i>Unassigned
                        </span>`;
            }

            function actionFormatter(cell) {
                const rowData = cell.getRow().getData();
                return `<button type="button" 
                                class="btn btn-sm btn-outline-primary btn-edit-prefix"
                                data-id="${rowData.office_id}">
                            <i class="bi bi-pencil-square me-1"></i>Edit
                        </button>`;
            }

            // 3. Tabulator Instance Setup
            const table = new Tabulator("#officePrefixTable", {
                data: initialData,
                index: "office_id",
                layout: "fitColumns",
                responsiveLayout: "collapse",
                pagination: "local",
                paginationSize: 10,
                paginationSizeSelector: [10, 25, 50, 100],
                placeholder: "No active offices found.",
                columns: [
                    { 
                        title: "No.", 
                        formatter: "rownum", 
                        width: 85, 
                        minWidth: 75,
                        widthShrink: 0,
                        responsive: 0,
                        hozAlign: "center", 
                        headerHozAlign: "center", 
                        headerSort: false,
                        resizable: false
                    },
                    { title: "Office Name", field: "office_name", sorter: "string" },
                    { title: "Office Code", field: "office_code", formatter: officeCodeFormatter, width: 140, minWidth: 120, widthShrink: 0 },
                    { title: "Prefix Code", field: "prefix_code", formatter: prefixCodeFormatter, hozAlign: "center", width: 160, minWidth: 140, widthShrink: 0 },
                    { 
                        title: "Last Modified", 
                        field: "date_modify", 
                        width: 180, 
                        minWidth: 150,
                        formatter: function(cell) {
                            const val = cell.getValue();
                            return val ? escapeHtml(val) : '—';
                        }
                    },
                    { 
                        title: "Action", 
                        formatter: actionFormatter, 
                        hozAlign: "right", 
                        headerHozAlign: "right", 
                        width: 110, 
                        minWidth: 100,
                        widthShrink: 0,
                        responsive: 0,
                        headerSort: false 
                    }
                ]
            });

            // 4. Client-side Search Filter
            $('#searchOfficeInput').on('keyup', function() {
                const keyword = $(this).val().toLowerCase();
                table.setFilter(function(data) {
                    const name = (data.office_name || '').toLowerCase();
                    const code = (data.office_code || '').toLowerCase();
                    const prefix = (data.prefix_code || '').toLowerCase();
                    return name.includes(keyword) || code.includes(keyword) || prefix.includes(keyword);
                });
            });

            // 5. Open Modal & Pull Live Data from Tabulator Model
            $(document).on('click', '.btn-edit-prefix', function() {
                const officeId = $(this).attr('data-id');
                const row = table.getRow(officeId);

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

            // Auto-format prefix code input
            $('#editPrefixCode').on('input', function() {
                $(this).val($(this).val().toUpperCase().replace(/[^A-Z0-9_-]/g, ''));
            });

            // 6. Form Submission Handler
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
                        console.error('Server returned non-JSON response:', text);
                        throw new Error('Server returned invalid response format.');
                    }
                })
                .then(res => {
                    btn.prop('disabled', false).html(originalHtml);

                    if (res.success) {
                        // Update Tabulator internal model directly
                        const targetRow = table.getRow(officeId);
                        if (targetRow) {
                            targetRow.update({
                                prefix_code: newPrefix,
                                date_modify: 'Just now'
                            });
                        }

                        const modalEl = document.getElementById('modalEditPrefix');
                        const modalInstance = bootstrap.Modal.getInstance(modalEl);
                        if (modalInstance) modalInstance.hide();

                        if (typeof Swal !== 'undefined') {
                            Swal.fire({ icon: 'success', title: 'Updated!', text: res.message || 'Prefix code updated successfully.', timer: 1500, showConfirmButton: false });
                        } else {
                            alert(res.message || 'Prefix code updated successfully.');
                        }
                    } else {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({ icon: 'error', title: 'Failed', text: res.message || 'Unable to update prefix code.' });
                        } else {
                            alert(res.message || 'Unable to update prefix code.');
                        }
                    }
                })
                .catch(err => {
                    btn.prop('disabled', false).html(originalHtml);
                    console.error('Update error:', err);
                    alert(err.message || 'An unexpected server error occurred.');
                });
            });
        })();
    </script>
</body>
</html>