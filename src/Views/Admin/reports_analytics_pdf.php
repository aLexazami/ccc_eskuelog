<?php
# Core Config & DB Initialization
global $db_connect, $session_class;
$db_connect = $db_connect ?? null;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Resolve Current User Info (for Prepared By block)
$user_id = 0;
if (isset($session_class) && method_exists($session_class, 'getValue')) {
    $user_id = (int)($session_class->getValue('user_id') ?? $session_class->getValue('id') ?? 0);
}
if ($user_id <= 0) {
    $user_id = (int)($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
}

$generated_by_name = "System Administrator";
if ($db_connect && $user_id > 0) {
    $stmtUser = $db_connect->prepare("SELECT CONCAT(first_name, ' ', last_name) AS full_name FROM users WHERE id = ? LIMIT 1");
    if ($stmtUser) {
        $stmtUser->bind_param("i", $user_id);
        $stmtUser->execute();
        $userData = $stmtUser->get_result()->fetch_assoc();
        if (!empty($userData['full_name'])) {
            $generated_by_name = $userData['full_name'];
        }
    }
}

// 2. Resolve URL Query Parameters
$office_id    = (int)($_GET['office_id'] ?? 0);
$visitor_type = trim($_GET['visitor_type'] ?? '');
$date_from    = trim($_GET['date_from'] ?? date('Y-m-d'));
$date_to      = trim($_GET['date_to'] ?? date('Y-m-d'));

// 3. Resolve Office Name
$office_name = "All Campus Offices";
if ($db_connect && $office_id > 0) {
    $stmtOffice = $db_connect->prepare("SELECT office_name, office_code FROM office WHERE office_id = ? LIMIT 1");
    if ($stmtOffice) {
        $stmtOffice->bind_param("i", $office_id);
        $stmtOffice->execute();
        $resOffice = $stmtOffice->get_result()->fetch_assoc();
        if ($resOffice) {
            $office_name = $resOffice['office_name'] . " (" . $resOffice['office_code'] . ")";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics_Report_<?= preg_replace('/[^A-Za-z0-9_\-]/', '_', $office_name); ?>_<?= htmlspecialchars($date_from); ?></title>
    
    <link rel="stylesheet" href="<?= BASE_ADMIN_ASSETS_PATH; ?>css/bootstrap.min.css">
    
    <style>
        :root {
            --primary-color: #1e3a8a;
            --text-dark: #0f172a;
            --border-color: #cbd5e1;
        }

        body {
            font-family: 'Public Sans', system-ui, -apple-system, sans-serif;
            background-color: #ffffff;
            color: var(--text-dark);
            padding: 24px;
            font-size: 13px;
            line-height: 1.5;
        }

        /* Action bar for screen view */
        .action-bar {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 10px 16px;
            border-radius: 8px;
            margin-bottom: 24px;
        }

        /* Official Document Header */
        .report-header {
            border-bottom: 2.5px solid var(--primary-color);
            padding-bottom: 16px;
            margin-bottom: 24px;
        }

        .report-title {
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 0.5px;
            color: var(--primary-color);
            text-transform: uppercase;
        }

        .meta-pill {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 8px 14px;
            font-size: 12px;
        }

        /* Metric KPI Boxes */
        .kpi-box {
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 12px;
            text-align: center;
            background: #ffffff;
        }

        .kpi-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 0.5px;
        }

        .kpi-value {
            font-size: 22px;
            font-weight: 800;
            margin-top: 4px;
            font-family: monospace;
        }

        /* Tables Styling */
        .report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        .report-table th {
            background-color: #f8fafc !important;
            color: #334155;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
            border: 1px solid var(--border-color);
            padding: 8px 12px;
        }

        .report-table td {
            border: 1px solid var(--border-color);
            padding: 8px 12px;
        }

        .section-heading {
            font-size: 14px;
            font-weight: 700;
            color: var(--primary-color);
            border-bottom: 1.5px solid #e2e8f0;
            padding-bottom: 6px;
            margin-top: 24px;
            margin-bottom: 12px;
        }

        /* Signature block */
        .signature-block {
            margin-top: 48px;
            page-break-inside: avoid;
        }

        .signature-line {
            border-bottom: 1px solid #000;
            width: 220px;
            display: inline-block;
            margin-top: 40px;
        }

        /* PRINT MEDIA OVERRIDES */
        @media print {
            @page {
                size: A4 portrait;
                margin: 12mm 10mm;
            }

            body {
                padding: 0 !important;
                background: #ffffff !important;
                font-size: 12px !important;
            }

            .no-print {
                display: none !important;
            }

            .card, .kpi-box, .report-table {
                page-break-inside: avoid;
            }

            .report-header {
                border-bottom-color: #000 !important;
            }

            .section-heading {
                color: #000 !important;
            }
        }
    </style>
</head>
<body>

    <!-- SCREEN-ONLY ACTION BAR -->
    <div class="d-flex justify-content-between align-items-center action-bar no-print" id="previewActionBar">
        <a href="javascript:window.close();" class="btn btn-outline-secondary btn-sm fw-semibold">
            &larr; Close Preview
        </a>
        <div class="d-flex gap-2">
            <button onclick="window.print();" class="btn btn-primary btn-sm fw-bold">
                <i class="bi bi-printer-fill me-1"></i>Print / Save as PDF
            </button>
        </div>
    </div>

    <!-- OFFICIAL REPORT HEADER -->
    <div class="report-header text-center">
        <h5 class="fw-bold mb-1 text-uppercase">City College of Calamba</h5>
        <h6 class="text-muted mb-2">Management Information Systems Division (MISD)</h6>
        <div class="report-title my-2">Queue Management & Analytics Comprehensive Report</div>
        
        <div class="meta-pill d-inline-block mt-2">
            <strong>Office:</strong> <?= htmlspecialchars($office_name); ?> &nbsp;|&nbsp; 
            <strong>Date Range:</strong> <?= htmlspecialchars($date_from); ?> to <?= htmlspecialchars($date_to); ?> &nbsp;|&nbsp; 
            <strong>Visitor Type:</strong> <?= $visitor_type ? htmlspecialchars(ucfirst(strtolower($visitor_type))) : 'All Demographics'; ?>
        </div>
    </div>

    <!-- SECTION I: KPI METRICS -->
    <div class="section-heading">I. Key Performance Indicators (KPIs)</div>
    <div class="row g-3 mb-3">
        <div class="col-3">
            <div class="kpi-box">
                <div class="kpi-title">Total Tickets</div>
                <div class="kpi-value text-dark" id="kpiTotal">0</div>
            </div>
        </div>
        <div class="col-3">
            <div class="kpi-box">
                <div class="kpi-title">Completed</div>
                <div class="kpi-value text-success" id="kpiCompleted">0</div>
            </div>
        </div>
        <div class="col-3">
            <div class="kpi-box">
                <div class="kpi-title">Skipped / Missed</div>
                <div class="kpi-value text-danger" id="kpiSkipped">0</div>
            </div>
        </div>
        <div class="col-3">
            <div class="kpi-box">
                <div class="kpi-title">Avg. Service Time</div>
                <div class="kpi-value text-primary" id="kpiAvgTime">0m</div>
            </div>
        </div>
    </div>

    <!-- SECTION II: DEMOGRAPHICS & TOP CONCERNS GRID -->
    <div class="row g-4 mb-3">
        <!-- Visitor Demographics Table -->
        <div class="col-6">
            <div class="section-heading">II. Visitor Demographics</div>
            <table class="report-table" id="visitorDistributionTable">
                <thead>
                    <tr>
                        <th>Visitor Category</th>
                        <th class="text-center">Ticket Count</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="2" class="text-center py-2 text-muted">Loading data...</td></tr>
                </tbody>
            </table>
        </div>

        <!-- Top Transaction Concerns Table -->
        <div class="col-6">
            <div class="section-heading">III. Top Transaction Concerns</div>
            <table class="report-table" id="topConcernsTable">
                <thead>
                    <tr>
                        <th>Transaction / Purpose</th>
                        <th class="text-center">Requests</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="2" class="text-center py-2 text-muted">Loading data...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- SECTION III: STAFF PERFORMANCE BREAKDOWN TABLE -->
    <div class="section-heading">IV. Staff Performance Breakdown</div>
    <table class="report-table align-middle mb-4" id="pdfStaffTable">
        <thead>
            <tr>
                <th>Staff Name</th>
                <th class="text-center" style="width: 120px;">Total Served</th>
                <th class="text-center" style="width: 140px;">Total Completed</th>
                <th class="text-center" style="width: 140px;">Completion Rate</th>
            </tr>
        </thead>
        <tbody>
            <tr><td colspan="4" class="text-center py-3 text-muted">Loading staff activity data...</td></tr>
        </tbody>
    </table>

    <!-- ADMINISTRATIVE SIGNATURE BLOCK -->
    <div class="signature-block">
        <div class="row">
            <div class="col-6">
                <p class="small text-muted mb-0">Prepared By:</p>
                <div class="signature-line"></div>
                <p class="fw-bold mb-0 mt-1"><?= htmlspecialchars($generated_by_name); ?></p>
                <p class="small text-muted mb-0">Staff / Administrator</p>
            </div>
            <div class="col-6 text-end">
                <p class="small text-muted mb-0">Report Generated On:</p>
                <p class="fw-bold mb-0 mt-1"><?= date('F d, Y - h:i A'); ?></p>
                <p class="small text-muted mb-0">Eskuelog Analytics System</p>
            </div>
        </div>
    </div>

    <script src="<?= BASE_MAIN_ASSETS_PATH; ?>js/jquery.min.js"></script>
    <script>
        $(document).ready(function() {
            // Hide preview action bar if running inside an iframe
            if (window.self !== window.top) {
                $('#previewActionBar').hide();
            }

            // Helper for HTML escaping
            const escapeHtml = (str) => {
                return String(str || '').replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
            };

            // Safely retrieve parameters injected from PHP
            const officeId    = <?= json_encode($office_id); ?>;
            const visitorType = <?= json_encode($visitor_type); ?>;
            const dateFrom    = <?= json_encode($date_from); ?>;
            const dateTo      = <?= json_encode($date_to); ?>;

            // Fetch data via system AJAX Handler
            $.ajax({
                url: '<?= BASE_URL; ?>admin/ajax/reports-analytics',
                type: 'POST',
                data: {
                    action: 'fetch_analytics',
                    office_id: officeId,
                    visitor_type: visitorType,
                    date_from: dateFrom,
                    date_to: dateTo
                },
                dataType: 'json',
                success: function(res) {
                    if (res.success && res.data) {
                        const data = res.data;

                        // 1. Render KPIs
                        if (data.kpi) {
                            $('#kpiTotal').text(data.kpi.total_tickets || 0);
                            $('#kpiCompleted').text(data.kpi.completed_tickets || 0);
                            $('#kpiSkipped').text(data.kpi.skipped_tickets || 0);
                            $('#kpiAvgTime').text((data.kpi.avg_service_minutes || 0) + 'm');
                        }

                        // 2. Render Visitor Distribution
                        const tbodyVisitor = $('#visitorDistributionTable tbody').empty();
                        if (data.visitor_distribution && data.visitor_distribution.length > 0) {
                            data.visitor_distribution.forEach(item => {
                                tbodyVisitor.append(`
                                    <tr>
                                        <td class="fw-semibold">${escapeHtml(item.type)}</td>
                                        <td class="text-center font-monospace">${parseInt(item.total || 0)}</td>
                                    </tr>
                                `);
                            });
                        } else {
                            tbodyVisitor.html('<tr><td colspan="2" class="text-center py-2 text-muted">No records.</td></tr>');
                        }

                        // 3. Render Top Concerns
                        const tbodyConcerns = $('#topConcernsTable tbody').empty();
                        if (data.top_concerns && data.top_concerns.length > 0) {
                            data.top_concerns.forEach(item => {
                                tbodyConcerns.append(`
                                    <tr>
                                        <td class="fw-semibold">${escapeHtml(item.concern)}</td>
                                        <td class="text-center font-monospace">${parseInt(item.total || 0)}</td>
                                    </tr>
                                `);
                            });
                        } else {
                            tbodyConcerns.html('<tr><td colspan="2" class="text-center py-2 text-muted">No records.</td></tr>');
                        }

                        // 4. Render Staff Performance Table
                        const tbodyStaff = $('#pdfStaffTable tbody').empty();
                        if (data.staff_breakdown && data.staff_breakdown.length > 0) {
                            data.staff_breakdown.forEach(staff => {
                                const served = parseInt(staff.total_served || 0);
                                const completed = parseInt(staff.total_completed || 0);
                                const rate = served > 0 ? Math.round((completed / served) * 100) : 0;

                                tbodyStaff.append(`
                                    <tr>
                                        <td class="fw-semibold">${escapeHtml(staff.staff_name || 'Unassigned')}</td>
                                        <td class="text-center font-monospace">${served}</td>
                                        <td class="text-center font-monospace">${completed}</td>
                                        <td class="text-center font-monospace fw-bold ${rate >= 80 ? 'text-success' : 'text-primary'}">${rate}%</td>
                                    </tr>
                                `);
                            });
                        } else {
                            tbodyStaff.html('<tr><td colspan="4" class="text-center py-3 text-muted">No staff activity recorded for selected range.</td></tr>');
                        }

                        // Focus window and trigger print preview dialog
                        setTimeout(() => {
                            window.focus();
                            window.print();
                        }, 400);
                    } else {
                        alert("Error loading report data: " + (res.message || "Unknown error"));
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX Report Fetch Error:", error);
                    alert("Failed to fetch report data from server.");
                }
            });
        });
    </script>
</body>
</html>