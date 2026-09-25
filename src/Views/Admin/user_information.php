<?php
# Core Config Dependencies
$db_connect = $db_connect ?? null;
$g_user_role = $g_user_role ?? '';

# Server Execution Limits [uncomment ONLY for long-running scripts like reports/imports]
// set_time_limit(0);
// ini_set('max_execution_time', '0');
// ini_set('memory_limit', '1024M');

# Page Helpers
require_once ADMIN_PAGE_HEADER_PATH;
# Page Header Initialization
$title_page   = "User Information";
$active_links = [
    ['label' => $title_page, 'url' => '']
];
$page_header  = render_page_header((string) $title_page, $active_links) ?? [];
# ===================================================================================

## uploaded logs
$path = IMPORT_USER_LOG;
$result = tailCustom($path, 100);
$record = array();
if (!empty($result)) {
    # Break into an array and remove any empty lines
    $record = array_filter(explode("\n", $result));

    # Sort descending by the date prefix
    usort($record, function ($a, $b) {
        // Split by the pipe character '|' to isolate the date
        $dateA = explode('|', $a)[0] ?? '';
        $dateB = explode('|', $b)[0] ?? '';

        # Compare strings in reverse for descending order
        return strcmp($dateB, $dateA);
    });
}
$record = array_values($record);

$action_buttons = [
    [
        'label' => 'Add User Information',
        'icon'  => 'bi bi-plus-circle',
        'class' => 'add-user-btn',
        'type'  => 'button'
    ],
    [
        'label' => 'Import Bulk User',
        'icon'  => 'bi bi-arrow-bar-up bulk',
        'class' => 'bulk-user-btn',
        'type'  => 'button'
    ]
];


$my_links = [
    [
        'label'  => 'Download Template CSV File',
        'icon'   => 'bi bi-download',
        'href'   => BASE_URL . 'download?attach=IMP_BLK_USRINF',
        'target' => '_blank',
        'class'  => 'text-decoration-none text-primary'
    ],
    [
        'label'  => 'View Uploaded Logs',
        'icon'   => 'bi bi-eye',
        'href'   => '#',
        'id'     => 'view_log',
        'class'  => 'text-decoration-none text-secondary'
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
                                                <i class="bi bi-people-fill"></i>
                                            </div>

                                            <div>
                                                <div class="text-uppercase text-muted fw-bold tracking-wide small" style="font-size: 0.95rem;">
                                                    User Information
                                                </div>
                                                <div class="d-flex align-items-center text-muted small mt-0.5" style="font-size: 0.75rem;">
                                                    <i class="bi bi-info-circle me-1"></i>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="d-flex align-items-start align-items-md-end gap-2 ms-md-auto">
                                            <?php render_header_button($action_buttons); ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="card-body">
                                    <!-- Toolbar with Search and Export Buttons -->
                                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                                        <div class="search-input-group flex-grow-1">
                                            <i class="bi bi-search search-icon"></i>
                                            <input type="text" id="global-search" class="form-control" placeholder="Search article title, category, author...">
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
                                        <div id="tabulator-container" class="table table-bordered tabulator" style="min-height: 600px;"></div>
                                    </div>
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
    (function() {
        /** set server time **/
        var set_server_time = <?php echo "'" . DATE_TIME . "';\r\n"; ?>
        var serverOffset = moment(set_server_time).diff(new Date());
        var now_server = moment();
        now_server.add(serverOffset, 'milliseconds');
        now_server.subtract(15, 'year');
        var dateLimit = now_server.format('YYYY-MM-DD');

        let tabulator = null;

        /* Tabulator Table Setup */
        function initTabulatorTable() {
            const remoteTableUrl = "<?php echo BASE_URL; ?>admin/table/user-information";

            tabulator = new Tabulator("#tabulator-container", {
                ajaxURL: remoteTableUrl,

                ajaxLoader: true,
                ajaxLoaderLoading: 'Fetching data from Database..',
                ajaxConfig: {
                    method: "GET",
                    headers: {
                        "Content-type": 'application/json; charset=utf-8',
                    },
                },
                ajaxParams: {
                    load_all: 0,
                },
                ajaxResponse: function(url, params, response) {
                    return response.data;
                },

                filterMode: "remote",
                sortMode: "remote",
                headerFilterPlaceholder: "Search",

                pagination: "remote",
                paginationSize: 10,
                paginationSizeSelector: [10, 25, 50, 100, true],

                paginationCounter: function(pageSize, currentRow, currentPage, totalRows, totalPages) {
                    const count = totalRows || 0;
                    if (count === 0) return "Showing data 0 of 0 entries";

                    let effPageSize = (pageSize === true || pageSize >= count) ? count : pageSize;
                    let start = (currentPage - 1) * effPageSize + 1;
                    let end = Math.min(start + effPageSize - 1, count);

                    if (pageSize === true || effPageSize >= count) {
                        return `Showing all data (${count} entries)`;
                    }
                    return `Showing data ${start} to ${end} of ${count} entries`;
                },

                dataReceiveParams: {
                    "last_page": "last_page",
                    "data": "data",
                    "last_row": "last_row"
                },

                downloadRowRange: "all",

                height: "600px",
                headerHozAlign: 'center',
                layout: "fitColumns",
                placeholder: "No Record Found",

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

                columns: [{
                        title: "Name",
                        field: "name",
                        minWidth: 250,
                        vertAlign: 'middle',
                        hozAlign: 'left',
                        headerFilter: "input",
                        headerFilterFunc: "like",
                        headerFilterLiveFilter: false,
                        headerFilterParams: {
                            allowEmpty: true
                        },
                        formatter: 'textarea',
                    },
                    {
                        title: "First Name",
                        field: "first_name",
                        visible: false,
                        print: false,
                        download: true,
                    },
                    {
                        title: "Middle Name",
                        field: "middle_name",
                        visible: false,
                        print: false,
                        download: true,
                    },
                    {
                        title: "Last Name",
                        field: "last_name",
                        visible: false,
                        print: false,
                        download: true,
                    },
                    {
                        title: "Extension Name",
                        field: "suffix",
                        visible: false,
                        print: false,
                        download: true,
                    },
                    {
                        title: "Sex",
                        field: "sex",
                        minWidth: 150,
                        vertAlign: 'middle',
                        hozAlign: 'center',
                        headerFilter: "input",
                        headerFilterFunc: "like",
                        headerFilterLiveFilter: false,
                        headerFilterParams: {
                            allowEmpty: true
                        },
                        formatter: 'textarea',
                    },
                    {
                        title: "Birth Date",
                        field: "birth_date",
                        minWidth: 150,
                        vertAlign: 'middle',
                        hozAlign: 'center',
                        headerFilter: "input",
                        headerFilterFunc: "like",
                        headerFilterLiveFilter: false,
                        headerFilterParams: {
                            allowEmpty: true
                        },
                        formatter: 'textarea',
                    },
                    {
                        title: "Birth Place",
                        field: "birth_place",
                        visible: false,
                        print: false,
                        download: true,
                    },
                    {
                        title: "Civil Status",
                        field: "civil_status",
                        visible: false,
                        print: false,
                        download: true,
                    },
                    {
                        title: "Nationality",
                        field: "nationality",
                        visible: false,
                        print: false,
                        download: true,
                    },
                    {
                        title: "Contact Number",
                        field: "contact_no",
                        visible: false,
                        print: false,
                        download: true,
                    },
                    {
                        title: "CCC Email Address",
                        field: "email",
                        minWidth: 150,
                        vertAlign: 'middle',
                        hozAlign: 'left',
                        headerFilter: "input",
                        headerFilterFunc: "like",
                        headerFilterLiveFilter: false,
                        headerFilterParams: {
                            allowEmpty: true
                        },
                        formatter: 'textarea',
                    },
                    {
                        title: "Personal/Recovery Email",
                        field: "personal_email",
                        minWidth: 170,
                        vertAlign: 'middle',
                        hozAlign: 'left',
                        headerFilter: "input",
                        headerFilterFunc: "like",
                        headerFilterLiveFilter: false,
                        headerFilterParams: {
                            allowEmpty: true
                        },
                        formatter: 'textarea',
                    },
                    {
                        title: "Home Address",
                        field: "home_address",
                        visible: false,
                        print: false,
                        download: true,
                    },
                    {
                        title: "Barangay",
                        field: "brgy",
                        visible: false,
                        print: false,
                        download: true,
                    },
                    {
                        title: "City",
                        field: "city",
                        visible: false,
                        print: false,
                        download: true,
                    },
                    {
                        title: "Province",
                        field: "province",
                        visible: false,
                        print: false,
                        download: true,
                    },
                    {
                        title: "Full Name (ECI)",
                        field: "e_name",
                        visible: false,
                        print: false,
                        download: true,
                    },
                    {
                        title: "Relationship (ECI)",
                        field: "e_relationship",
                        visible: false,
                        print: false,
                        download: true,
                    },
                    {
                        title: "Contact Number (ECI)",
                        field: "e_contact",
                        visible: false,
                        print: false,
                        download: true,
                    },
                    {
                        title: "Address (ECI)",
                        field: "e_address",
                        visible: false,
                        print: false,
                        download: true,
                    },
                    {
                        title: "Date Added/Modify",
                        field: "date_modify",
                        minWidth: 170,
                        vertAlign: 'middle',
                        hozAlign: 'center',
                        print: false,
                        download: false,
                        formatter: 'textarea',
                    }
                ]
            });

            document.getElementById('export-csv')?.addEventListener('click', () => {
                tabulator.download("csv", "user_information.csv");
            });

            document.getElementById('export-excel')?.addEventListener('click', () => {
                tabulator.download("xlsx", "user_information.xlsx", {
                    sheetName: "Users"
                });
            });

            document.getElementById('export-json')?.addEventListener('click', () => {
                tabulator.download("json", "user_information.json");
            });

            document.getElementById("export-print")?.addEventListener("click", function() {
                const tableTitle = 'User Information Report';
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

            // --- GLOBAL SEARCH HANDLER ---
            const searchInput = document.getElementById('global-search');
            if (searchInput) {
                let searchDebounceTimeout;
                searchInput.addEventListener('keyup', function() {
                    clearTimeout(searchDebounceTimeout);
                    const query = this.value.trim();

                    searchDebounceTimeout = setTimeout(() => {
                        tabulator.setData(remoteTableUrl, {
                            load_all: 0,
                            search: query
                        });
                    }, 300);
                });
            }

            // --- REFRESH DATA HANDLER ---
            const refreshBtn = document.getElementById('btn-refresh');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', function() {
                    if (searchInput) searchInput.value = '';
                    tabulator.clearHeaderFilter();

                    tabulator.setData(remoteTableUrl, {
                        load_all: 0,
                    });
                });
            }
        }

        /* Initialize Tabulator Table */
        initTabulatorTable();
    })();
</script>

</html>