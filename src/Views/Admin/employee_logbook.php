<?php
# Core Config Dependencies
$db_connect  = $db_connect ?? null;
$g_user_role = $g_user_role ?? '';

# Page Helpers
require_once ADMIN_PAGE_HEADER_PATH;

# Page Header Initialization
$title_page   = "Employee Logbook";
$active_links = [
    ['label' => $title_page, 'url' => '']
];
$page_header = render_page_header((string) $title_page, $active_links) ?? '';

# Fetch Active Departments
$departments_list = [];
if ($db_connect) {
    $stmtDept = $db_connect->query("SELECT department_id, department_code, department_name FROM departments WHERE flag_status = 0 ORDER BY department_code ASC");
    if ($stmtDept) {
        while ($row = $stmtDept->fetch_assoc()) {
            $departments_list[] = $row;
        }
    }
}

# Fetch Active Facilities / Buildings
$facilities_list = [];
if ($db_connect) {
    $stmtFac = $db_connect->query("SELECT facility_id, facility_code, facility_name, facility_floor FROM facility WHERE flag_status = 0 ORDER BY facility_name ASC");
    if ($stmtFac) {
        while ($row = $stmtFac->fetch_assoc()) {
            $facilities_list[] = $row;
        }
    }
}

# Fetch Active Offices Joined with Room (Driven by Office Table)
$offices_list = [];
if ($db_connect) {
    $queryOffice = "
        SELECT 
            o.office_id, 
            o.office_code, 
            o.office_name, 
            o.office_logo,
            o.room_id,
            COALESCE(r.room_name, 'Main Office') AS room_name,
            COALESCE(r.facility_id, 1) AS facility_id,
            COALESCE(r.facility_floor, 'Ground Floor') AS facility_floor
        FROM office o
        LEFT JOIN room r ON o.room_id = r.room_id AND r.flag_status = 0
        WHERE o.flag_status = 0
        ORDER BY o.office_name ASC
    ";
    $stmtOff = $db_connect->query($queryOffice);
    if ($stmtOff) {
        while ($row = $stmtOff->fetch_assoc()) {
            $offices_list[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-100">

<head>
    <?php include_once ADMIN_META_DATA_PATH; ?>
    <?php include_once ADMIN_LINK_PATH; ?>
    
    <!-- MODERN KIOSK CUSTOM STYLES -->
    <style>
        :root {
            --bg-wrapper: linear-gradient(135deg, rgba(11, 32, 117, 0.25) 0%, rgba(0, 10, 76, 0.45) 100%), url('<?= BASE_URL; ?>public/assets/main/css/bg-template.png');
            --bg-header: rgba(0, 16, 77, 0.88);
            --border-header: 1px solid rgba(255, 255, 255, 0.15);
            --header-title-color: #ffffff;
            
            --kiosk-primary: #1d4ed8;
            --kiosk-primary-hover: #1e40af;
            --kiosk-primary-light: #eff6ff;
            --kiosk-border: #e2e8f0;
            --kiosk-bg-subtle: #f8fafc;
            --kiosk-shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
            --kiosk-shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
            --kiosk-radius: 1rem;
        }

        .kiosk-main-card {
            border: 1px solid var(--kiosk-border);
            border-radius: var(--kiosk-radius);
            box-shadow: var(--kiosk-shadow-lg);
            background: #ffffff;
            overflow: hidden;
            width: 100%;
            margin-left: auto;
            margin-right: auto;
        }

        /* 4-STEP WIZARD PROGRESS BAR */
        .step-progress-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.875rem 1rem;
            background: var(--kiosk-bg-subtle);
            border-bottom: 1px solid var(--kiosk-border);
        }

        .step-item {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.82rem;
            font-weight: 600;
            color: #64748b;
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .step-item .step-number {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #cbd5e1;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 700;
            flex-shrink: 0;
        }

        .step-item.active { color: var(--kiosk-primary); }
        .step-item.active .step-number {
            background: var(--kiosk-primary);
            box-shadow: 0 0 0 4px rgba(29, 78, 216, 0.15);
        }
        .step-item.completed .step-number { background: #10b981; }

        .step-divider {
            flex-grow: 1;
            height: 2px;
            background: #e2e8f0;
            margin: 0 0.35rem;
            min-width: 6px;
        }

        /* Form Controls */
        .form-control-kiosk, .form-select-kiosk {
            border: 1.5px solid #cbd5e1;
            border-radius: 0.75rem;
            padding: 0.85rem 1rem;
            font-size: 1rem;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            width: 100%;
            box-sizing: border-box;
        }

        .form-control-kiosk:focus, .form-select-kiosk:focus {
            border-color: var(--kiosk-primary);
            box-shadow: 0 0 0 4px rgba(29, 78, 216, 0.12);
            outline: none;
        }

        .input-group-kiosk { position: relative; width: 100%; }
        .input-group-kiosk .input-icon {
            position: absolute;
            right: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            pointer-events: none;
        }

        /* Selection Cards */
        .building-card, .office-card, .transaction-card {
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            border: 2px solid #e2e8f0;
            background: #ffffff;
            border-radius: 0.875rem;
            position: relative;
            padding: 1rem 1.15rem;
            width: 100%;
        }

        .building-card:hover, .office-card:hover, .transaction-card:hover {
            border-color: #93c5fd;
            background-color: var(--kiosk-bg-subtle);
            transform: translateY(-2px);
            box-shadow: var(--kiosk-shadow-sm);
        }

        .building-card.active, .office-card.active, .transaction-card.active {
            border-color: var(--kiosk-primary);
            background-color: var(--kiosk-primary-light);
            box-shadow: 0 10px 15px -3px rgba(29, 78, 216, 0.1);
        }

        .office-logo-img {
            width: 44px;
            height: 44px;
            object-fit: contain;
            border-radius: 50%;
            background: #ffffff;
            padding: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.06);
            flex-shrink: 0;
        }

        .office-logo-placeholder {
            width: 44px;
            height: 44px;
            background-color: #e2e8f0;
            color: #475569;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .sub-concern-btn {
            border-radius: 0.65rem;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.2s ease;
        }

        .consent-card-box, .priority-card-box {
            padding: 1rem 1.15rem;
            background-color: var(--kiosk-bg-subtle);
            border: 1px solid var(--kiosk-border);
            border-radius: 0.875rem;
            width: 100%;
        }

        .btn-kiosk-primary {
            background-color: var(--kiosk-primary);
            border-color: var(--kiosk-primary);
            color: #ffffff;
            border-radius: 0.75rem;
            padding: 0.875rem 1.25rem;
            font-weight: 700;
            transition: all 0.2s ease;
        }

        .btn-kiosk-primary:hover {
            background-color: var(--kiosk-primary-hover);
            border-color: var(--kiosk-primary-hover);
            color: #ffffff;
        }

        .receipt-card {
            background: #ffffff;
            border: 2px dashed #cbd5e1;
            border-radius: 1rem;
        }

        .receipt-header {
            background: var(--kiosk-primary);
            color: #ffffff;
            border-radius: 0.85rem 0.85rem 0 0;
            padding: 1.25rem;
        }

        /* RECEIPT CONCERN STYLES */
        .receipt-concern-item {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-left: 3px solid var(--kiosk-primary);
            border-radius: 0.5rem;
            padding: 0.5rem 0.75rem;
            margin-bottom: 0.4rem;
        }
        .receipt-concern-title {
            font-weight: 700;
            color: #1e293b;
            font-size: 0.875rem;
        }
        .receipt-subconcern-tag {
            background-color: #eff6ff;
            color: #1d4ed8;
            font-weight: 600;
            font-size: 0.75rem;
            padding: 0.15rem 0.5rem;
            border-radius: 0.35rem;
            display: inline-block;
            margin-top: 0.2rem;
            border: 1px solid #bfdbfe;
        }

        :fullscreen, :-webkit-full-screen {
            background-color: #000a4c !important;
            background-image: var(--bg-wrapper) !important;
            background-position: center center !important;
            background-repeat: no-repeat !important;
            background-size: cover !important;
        }

        :fullscreen .sidebar, :-webkit-full-screen .sidebar,
        :fullscreen .main-header, :-webkit-full-screen .main-header,
        :fullscreen .kiosk-page-header, :-webkit-full-screen .kiosk-page-header,
        :fullscreen footer, :-webkit-full-screen footer {
            display: none !important;
        }

        :fullscreen .main-panel, :-webkit-full-screen .main-panel {
            width: 100vw !important;
            height: 100vh !important;
            padding: 0 !important;
            margin: 0 !important;
            overflow-y: auto !important;
        }

        :fullscreen .kiosk-main-card, :-webkit-full-screen .kiosk-main-card {
            max-width: 1100px;
            margin: 0.5rem auto 18em auto !important;
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

                    <div class="kiosk-page-header">
                        <?php echo is_array($page_header) ? '' : $page_header; ?>
                    </div>

                    <div class="row justify-content-center w-100 mx-0">
                        <div class="col-12 px-0">
                            <div class="card kiosk-main-card border-0">

                                <!-- HEADER -->
                                <div class="card-header d-flex align-items-center justify-content-between">
                                    <div class="d-flex justify-content-between align-items-start align-items-md-center gap-3 w-100">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="bg-primary-subtle text-primary rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 44px; height: 44px;">
                                                <i class="bi bi-person-badge fs-4"></i>
                                            </div>
                                            <div>
                                                <div class="text-uppercase text-muted fw-bold tracking-wide small">Employee Digital Logbook</div>
                                                <div class="d-flex align-items-center text-muted small mt-0.5" style="font-size: 0.75rem;">
                                                    <i class="bi bi-info-circle me-1"></i> Employee kiosk check-in and queue ticket issuance
                                                </div>
                                            </div>
                                        </div>

                                        <button type="button" class="btn btn-action shadow-sm" id="btnToggleFullscreen" title="Toggle Fullscreen">
                                            <i class="bi bi-fullscreen fs-5" id="fullscreenIcon"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- 4-STEP PROGRESS WIZARD -->
                                <div class="step-progress-bar">
                                    <div class="step-item active" id="wizardStep1">
                                        <span class="step-number">1</span>
                                        <span class="d-none d-sm-inline">Info</span>
                                    </div>
                                    <div class="step-divider"></div>
                                    <div class="step-item" id="wizardStep2">
                                        <span class="step-number">2</span>
                                        <span class="d-none d-sm-inline">Building</span>
                                    </div>
                                    <div class="step-divider"></div>
                                    <div class="step-item" id="wizardStep3">
                                        <span class="step-number">3</span>
                                        <span class="d-none d-sm-inline">Office</span>
                                    </div>
                                    <div class="step-divider"></div>
                                    <div class="step-item" id="wizardStep4">
                                        <span class="step-number">4</span>
                                        <span class="d-none d-sm-inline">Transaction</span>
                                    </div>
                                </div>

                                <div class="card-body card-body-kiosk p-3 p-sm-4 p-md-5">

                                    <form id="kioskForm" novalidate>
                                        <input type="hidden" name="action" value="logbook_checkin">
                                        <input type="hidden" name="visitor_type" value="EMPLOYEE">
                                        <input type="hidden" id="userId" name="user_id" value="">
                                        
                                        <!-- SELECTION STATES -->
                                        <input type="hidden" id="facilitySelect" name="facility_id" value="" required>
                                        <input type="hidden" id="officeSelect" name="office_id" value="" required>
                                        <input type="hidden" id="transactionSelect" name="transaction_type_id" value="" required>
                                        <input type="hidden" id="subConcernSelect" name="sub_concern_id" value="">

                                        <!-- ========================================================== -->
                                        <!-- STEP 1: EMPLOYEE INFORMATION & PRIVACY AGREEMENT          -->
                                        <!-- ========================================================== -->
                                        <div id="step1_employeeInfo">
                                            <div class="mb-3 mb-sm-4">
                                                <h3 class="fw-bold text-dark mb-1 fs-5 fs-sm-4">Employee Identification</h3>
                                                <p class="text-muted small mb-0">Enter your Employee ID to retrieve your record.</p>
                                            </div>

                                            <div class="mb-3 mb-sm-4">
                                                <label for="employeeId" class="form-label fw-bold text-dark small">Employee ID Number</label>
                                                <div class="input-group-kiosk">
                                                    <input type="text" class="form-control form-control-kiosk form-control-lg fw-bold text-primary" id="employeeId" name="reference_id" placeholder="e.g., EMP-2023-001" maxlength="20" autocomplete="off" required autofocus>
                                                    <i class="bi bi-person-badge input-icon fs-4"></i>
                                                </div>
                                                <div id="idHelpBlock" class="form-text text-muted mt-2 small">Enter your Employee ID to verify details.</div>
                                            </div>

                                            <div id="employeeDetailsSection" class="d-none pt-2">
                                                <div class="row g-2 g-sm-3 mb-3">
                                                    <div class="col-12 col-md-3">
                                                        <label for="firstName" class="form-label fw-bold text-dark small">First Name</label>
                                                        <input type="text" class="form-control form-control-kiosk" id="firstName" name="first_name" placeholder="First Name" required>
                                                    </div>
                                                    <div class="col-12 col-sm-6 col-md-2">
                                                        <label for="middleName" class="form-label fw-bold text-dark small">Middle Name</label>
                                                        <input type="text" class="form-control form-control-kiosk" id="middleName" name="middle_name" placeholder="Middle Name">
                                                    </div>
                                                    <div class="col-12 col-sm-6 col-md-3">
                                                        <label for="lastName" class="form-label fw-bold text-dark small">Last Name</label>
                                                        <input type="text" class="form-control form-control-kiosk" id="lastName" name="last_name" placeholder="Last Name" required>
                                                    </div>
                                                    <div class="col-6 col-sm-6 col-md-2">
                                                        <label for="suffix" class="form-label fw-bold text-dark small">Suffix</label>
                                                        <select class="form-select form-select-kiosk" id="suffix" name="suffix">
                                                            <option value="" selected>None</option>
                                                            <option value="Jr.">Jr.</option>
                                                            <option value="Sr.">Sr.</option>
                                                            <option value="I">I</option>
                                                            <option value="II">II</option>
                                                            <option value="III">III</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-6 col-sm-6 col-md-2">
                                                        <label for="postNominal" class="form-label fw-bold text-dark small">Post Nominal</label>
                                                        <input type="text" class="form-control form-control-kiosk" id="postNominal" name="post_nominal" placeholder="PhD, LPT">
                                                    </div>
                                                </div>

                                                <div class="row g-2 g-sm-3 mb-3">
                                                    <div class="col-12 col-md-4">
                                                        <label for="sex" class="form-label fw-bold text-dark small">Sex</label>
                                                        <select class="form-select form-select-kiosk" id="sex" name="sex" required>
                                                            <option value="" selected disabled>Select sex</option>
                                                            <option value="Male">Male</option>
                                                            <option value="Female">Female</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-12 col-sm-6 col-md-4">
                                                        <label for="contactNumber" class="form-label fw-bold text-dark small">Contact Number</label>
                                                        <input type="tel" class="form-control form-control-kiosk" id="contactNumber" name="contact_no" placeholder="09XXXXXXXXX" maxlength="11" required>
                                                    </div>
                                                    <div class="col-12 col-sm-6 col-md-4">
                                                        <label for="email" class="form-label fw-bold text-dark small">Email Address</label>
                                                        <input type="email" class="form-control form-control-kiosk" id="email" name="email" placeholder="employee@ccc.edu.ph" required>
                                                    </div>
                                                </div>

                                                <div class="row g-2 g-sm-3 mb-3">
                                                    <div class="col-12 col-sm-6">
                                                        <label for="departmentId" class="form-label fw-bold text-dark small">Department</label>
                                                        <select class="form-select form-select-kiosk" id="departmentId" name="department_id" required>
                                                            <option value="" selected disabled>Select department</option>
                                                            <?php foreach ($departments_list as $dept): ?>
                                                                <option value="<?= htmlspecialchars($dept['department_id']); ?>">
                                                                    <?= htmlspecialchars($dept['department_code'] . ' - ' . $dept['department_name']); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>

                                                    <div class="col-12 col-sm-6">
                                                        <label for="employeeOfficeId" class="form-label fw-bold text-dark small">
                                                            Office <span class="text-muted fw-normal fs-8">(Optional)</span>
                                                        </label>
                                                        <select class="form-select form-select-kiosk" id="employeeOfficeId" name="employee_office_id">
                                                            <option value="" selected>Select office</option>
                                                            <?php foreach ($offices_list as $off): ?>
                                                                <option value="<?= htmlspecialchars($off['office_id']); ?>">
                                                                    <?= htmlspecialchars($off['office_code'] . ' - ' . $off['office_name']); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>

                                                <!-- PRIORITY TOGGLE -->
                                                <div class="priority-card-box mb-3">
                                                    <div class="d-flex align-items-center justify-content-between gap-3">
                                                        <label class="form-check-label fw-bold text-dark mb-0 fs-6 cursor-pointer" for="isPrioritySwitch">
                                                            <i class="bi bi-person-wheelchair text-primary me-1 fs-5"></i> Priority / Special Assistance Needed?
                                                        </label>
                                                        <div class="form-check form-switch p-0 m-0">
                                                            <input class="form-check-input fs-4 m-0" type="checkbox" id="isPrioritySwitch" name="is_priority" value="1">
                                                        </div>
                                                    </div>

                                                    <div id="priorityOptionsSection" class="d-none mt-3 pt-3 border-top">
                                                        <label class="form-label fw-bold text-dark small mb-2 d-block">Select Priority Classification:</label>
                                                        <select class="form-select form-select-kiosk" id="priorityType" name="priority_type">
                                                            <option value="PWD">Person with Disability (PWD)</option>
                                                            <option value="PREGNANT">Pregnant Woman</option>
                                                            <option value="SENIOR">Senior Citizen</option>
                                                            <option value="OTHER">Other Special Assistance / Express Service</option>
                                                        </select>
                                                    </div>
                                                </div>

                                                <!-- DATA PRIVACY AGREEMENT TRIGGER (DYNAMICALLY SWITCHES TO GREEN ON ACCEPTANCE) -->
                                                <div class="consent-card-box mb-4">
                                                    <input type="hidden" id="dataConsent" name="data_consent" value="0">
                                                    
                                                    <button type="button" class="btn btn-outline-primary w-100 py-3 rounded-3 fw-bold d-flex align-items-center justify-content-center gap-2 shadow-sm" id="btnOpenPrivacyModal">
                                                        <i class="bi bi-file-earmark-check fs-5" id="consentBtnIcon"></i>
                                                        <span>Read & Sign Agreement Form</span>
                                                    </button>
                                                    <div class="text-muted small text-center mt-2" id="consentSubText">
                                                        <i class="bi bi-shield-lock me-1"></i> Data privacy verification is required before proceeding.
                                                    </div>
                                                </div>

                                                <button type="button" id="btnContinueStep2" class="btn btn-kiosk-primary w-100 py-3 d-flex align-items-center justify-content-center gap-2 shadow-sm">
                                                    <span>Continue to Building Selection</span>
                                                    <i class="bi bi-arrow-right fs-5"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- ========================================================== -->
                                        <!-- STEP 2: BUILDING SELECTION                                 -->
                                        <!-- ========================================================== -->
                                        <div id="step2_buildingSelection" class="d-none">
                                            <div class="mb-3 mb-sm-4">
                                                <h3 class="fw-bold text-dark mb-1 fs-5 fs-sm-4">Select Building</h3>
                                                <p class="text-muted small mb-0">Tap the campus building where your destination office is located.</p>
                                            </div>

                                            <div class="d-flex flex-column gap-3 mb-4" id="buildingListContainer">
                                                <?php if (!empty($facilities_list)): ?>
                                                    <?php foreach ($facilities_list as $fac): ?>
                                                        <div class="building-card d-flex align-items-center justify-content-between gap-3" 
                                                             data-facility-id="<?= $fac['facility_id']; ?>" 
                                                             data-facility-name="<?= htmlspecialchars($fac['facility_name']); ?>"
                                                             data-facility-code="<?= htmlspecialchars($fac['facility_code']); ?>">
                                                            <div class="d-flex align-items-center gap-3 min-w-0 flex-grow-1">
                                                                <div class="office-logo-placeholder">
                                                                    <i class="bi bi-buildings"></i>
                                                                </div>
                                                                <div class="min-w-0 flex-grow-1">
                                                                    <h6 class="fw-bold text-dark mb-0 fs-6"><?= htmlspecialchars($fac['facility_name']); ?></h6>
                                                                    <span class="badge bg-light text-primary border mt-1 fw-semibold small"><?= htmlspecialchars($fac['facility_code']); ?></span>
                                                                </div>
                                                            </div>
                                                            <i class="bi bi-chevron-right text-muted fs-5"></i>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <div class="text-center p-4 bg-light rounded-3 text-muted">No active campus buildings available.</div>
                                                <?php endif; ?>
                                            </div>

                                            <button type="button" id="btnBackToStep1" class="btn btn-outline-secondary py-3 fw-bold w-100 rounded-3">
                                                <i class="bi bi-arrow-left me-1"></i> Back to Employee Information
                                            </button>
                                        </div>

                                        <!-- ========================================================== -->
                                        <!-- STEP 3: OFFICE SELECTION                                  -->
                                        <!-- ========================================================== -->
                                        <div id="step3_officeSelection" class="d-none">
                                            <div class="p-3 bg-light rounded-3 border d-flex align-items-center justify-content-between mb-3">
                                                <div>
                                                    <span class="text-muted text-uppercase fw-bold small d-block">Selected Building</span>
                                                    <h6 class="fw-bold text-primary mb-0" id="summaryBuildingName">---</h6>
                                                </div>
                                                <span class="badge bg-primary fs-6 px-3 py-1" id="summaryBuildingCode">---</span>
                                            </div>

                                            <div class="mb-3 mb-sm-4">
                                                <h3 class="fw-bold text-dark mb-1 fs-5 fs-sm-4">Select Destination Office</h3>
                                                <p class="text-muted small mb-0">Tap your target office inside this building.</p>
                                            </div>

                                            <div class="d-flex flex-column gap-3 mb-4" id="officeListContainer">
                                                <?php if (!empty($offices_list)): ?>
                                                    <?php foreach ($offices_list as $off): ?>
                                                        <?php $logo_path = !empty($off['office_logo']) ? BASE_URL . $off['office_logo'] : ''; ?>
                                                        <div class="office-card d-flex align-items-center justify-content-between gap-2" 
                                                             data-office-id="<?= $off['office_id']; ?>" 
                                                             data-facility-id="<?= $off['facility_id']; ?>"
                                                             data-office-name="<?= htmlspecialchars($off['office_name']); ?>"
                                                             data-office-code="<?= htmlspecialchars($off['office_code']); ?>"
                                                             data-room-name="<?= htmlspecialchars($off['room_name']); ?>">
                                                            <div class="d-flex align-items-center gap-3 min-w-0 flex-grow-1">
                                                                <?php if (!empty($logo_path)): ?>
                                                                    <img src="<?= htmlspecialchars($logo_path); ?>" class="office-logo-img me-2" onerror="this.style.display='none'; this.nextElementSibling.classList.remove('d-none');">
                                                                    <div class="office-logo-placeholder me-2 d-none"><i class="bi bi-building"></i></div>
                                                                <?php else: ?>
                                                                    <div class="office-logo-placeholder me-2"><i class="bi bi-building"></i></div>
                                                                <?php endif; ?>

                                                                <div class="min-w-0 flex-grow-1">
                                                                    <h6 class="fw-bold text-dark mb-1 fs-6"><?= htmlspecialchars($off['office_name']); ?></h6>
                                                                    <div class="mb-1">
                                                                        <span class="badge bg-light text-primary border fw-semibold small"><?= htmlspecialchars($off['office_code']); ?></span>
                                                                    </div>
                                                                    <div class="text-muted small">
                                                                        <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($off['room_name']); ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <i class="bi bi-chevron-right text-muted fs-5"></i>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>

                                            <button type="button" id="btnBackToStep2" class="btn btn-outline-secondary py-3 fw-bold w-100 rounded-3">
                                                <i class="bi bi-arrow-left me-1"></i> Back to Building Selection
                                            </button>
                                        </div>

                                        <!-- ========================================================== -->
                                        <!-- STEP 4: TRANSACTION CONCERNS                               -->
                                        <!-- ========================================================== -->
                                        <div id="step4_purposeSelection" class="d-none">
                                            <div class="p-3 bg-light rounded-3 border d-flex align-items-center justify-content-between mb-3">
                                                <div>
                                                    <span class="text-muted text-uppercase fw-bold small d-block">Destination Office</span>
                                                    <h5 class="fw-bold text-primary mb-0" id="summaryOfficeName">---</h5>
                                                </div>
                                                <span class="badge bg-primary fs-6 px-3 py-2" id="summaryOfficeCode">---</span>
                                            </div>

                                            <div class="mb-3">
                                                <h4 class="fw-bold text-dark mb-1 fs-5 fs-sm-4">Select Transaction Concern(s)</h4>
                                                <p class="text-muted small mb-0">Select one or more concerns below. Choose "Others" if your specific concern is not listed.</p>
                                            </div>

                                            <div class="d-flex flex-column gap-3 mb-4" id="transactionCardsContainer">
                                                <div class="text-center p-3 text-muted">Loading available concerns...</div>
                                            </div>

                                            <div id="otherConcernInputContainer" class="d-none mb-4 p-3 bg-light rounded-3 border">
                                                <label for="otherConcernText" class="form-label fw-bold text-dark small">Specify Your Transaction Concern:</label>
                                                <textarea class="form-control form-control-kiosk" id="otherConcernText" name="other_concern_text" rows="3" placeholder="Please describe your specific concern here..."></textarea>
                                            </div>

                                            <div class="d-flex action-btn-group gap-2 gap-sm-3 mt-4">
                                                <button type="button" id="btnBackToStep3" class="btn btn-outline-secondary py-3 fw-bold rounded-3 px-4">
                                                    <i class="bi bi-arrow-left me-1"></i> Back
                                                </button>
                                                <button type="submit" id="btnFinalSubmit" class="btn btn-success py-3 fw-bold rounded-3 flex-grow-1 shadow-sm">
                                                    <span>Complete Check-in & Get Ticket</span>
                                                    <i class="bi bi-check-circle ms-1 fs-5"></i>
                                                </button>
                                            </div>
                                        </div>

                                    </form>

                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <?php include_once ADMIN_FOOTER_PATH; ?>
        </div>
    </div>

    <!-- PRIVACY AGREEMENT MODAL -->
    <div class="modal fade" id="privacyAgreementModal" data-bs-backdrop="static" tabindex="-1" aria-labelledby="privacyAgreementModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-light border-bottom py-3 px-4">
                    <h6 class="modal-title fw-bold text-dark mb-0" id="privacyAgreementModalLabel">
                        <i class="bi bi-shield-check text-primary me-2 fs-5"></i>DATA PRIVACY & COLLECTION AGREEMENT
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-3 p-md-4">
                    <div class="card shadow-none border mb-0">
                        <div class="card-body p-3 p-md-4">

                            <div class="pe-2 text-secondary small" id="privacyModalScrollBody" style="max-height: 48vh; overflow-y: auto; line-height: 1.6;">
                                <div class="mb-3">
                                    <h6 class="fw-bold text-dark fs-6 mb-1">1. Purpose of Information Collection</h6>
                                    <p class="mb-0 text-muted">City College of Calamba collects personal data including employee ID numbers, full names, departments, and contact numbers to manage campus queue flows, logbook verification, and official records.</p>
                                </div>
                                
                                <div class="mb-3">
                                    <h6 class="fw-bold text-dark fs-6 mb-1">2. Data Usage & Protection</h6>
                                    <p class="mb-0 text-muted">All recorded information is strictly stored within internal database systems in accordance with Republic Act No. 10173, otherwise known as the Data Privacy Act of 2012. Data will not be shared with unauthorized third parties.</p>
                                </div>

                                <div class="mb-3">
                                    <h6 class="fw-bold text-dark fs-6 mb-1">3. Employee Authorization & Consent</h6>
                                    <p class="mb-0 text-muted">By checking the authorization consent inside this agreement form, you acknowledge that all information provided is accurate and grant explicit consent for processing queue tickets, operational statistics, and campus administrative records.</p>
                                </div>

                                <div class="mb-3">
                                    <h6 class="fw-bold text-dark fs-6 mb-1">4. Retention & Confidentiality</h6>
                                    <p class="mb-0 text-muted">Logbook data will be retained only as long as necessary to fulfill service fulfillment and reporting analytics. Security measures are enforced to safeguard records against unauthorized access, loss, or alteration.</p>
                                </div>

                                <div class="p-3 bg-light rounded-3 border mt-4">
                                    <div class="form-check d-flex align-items-center gap-2 ps-0 mb-0">
                                        <input class="form-check-input mt-0 flex-shrink-0 ms-0" type="checkbox" id="modalConsentCheckbox">
                                        <label class="form-check-label fw-bold text-dark small cursor-pointer mb-0" for="modalConsentCheckbox">
                                            I have read, understood, and agree to the Data Privacy Terms & Conditions.
                                        </label>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light border-top p-3 d-flex align-items-center justify-content-end">
                    <button type="button" class="btn btn-success rounded-3 px-4 fw-bold" id="btnAcceptPrivacyModal" disabled>
                        <i class="bi bi-check2-circle me-1"></i> Confirm & Accept Agreement
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- TICKET SUCCESS MODAL -->
    <div class="modal fade" id="ticketSuccessModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg p-3 p-sm-4 rounded-4 text-center">
                <div class="modal-body p-0">
                    <div class="text-success display-3 mb-2"><i class="bi bi-check-circle-fill"></i></div>
                    <h3 class="fw-bold text-dark mb-1 fs-4">Check-in Complete!</h3>
                    <p class="text-muted small mb-3">Please keep a record of your queue ticket receipt details.</p>

                    <div class="card receipt-card my-3 text-start shadow-sm overflow-hidden">
                        <div class="receipt-header text-center position-relative">
                            <span class="text-uppercase tracking-wide fw-semibold small opacity-75 d-block">Queue Ticket Number</span>
                            <h1 class="display-3 fw-black mb-0" id="modalTicketNumber">---</h1>
                            <div id="modalPriorityBadge" class="d-none mt-2">
                                <span class="badge bg-warning text-dark fw-bold px-3 py-2 fs-7 border border-dark">
                                    <i class="bi bi-star-fill me-1 text-danger"></i><span id="modalPriorityLabel">PRIORITY TICKET</span>
                                </span>
                            </div>
                        </div>

                        <div class="p-3 p-md-4 bg-white">
                            <div class="mb-2">
                                <span class="text-muted d-block small">Destination Office</span>
                                <strong class="text-dark fs-6 d-block" id="modalOfficeName">---</strong>
                            </div>
                            <hr class="my-2 opacity-25">
                            
                            <div class="mb-2">
                                <span class="text-muted d-block small mb-1.5">Transaction Concern(s)</span>
                                <div id="modalTransactionContainer" class="d-flex flex-column gap-1.5">
                                </div>
                            </div>

                            <hr class="my-2 opacity-25">
                            <div class="mb-2">
                                <span class="text-muted d-block small">Issued Date & Time</span>
                                <span class="fw-semibold text-dark small d-block" id="modalIssuedDate">---</span>
                            </div>
                            <hr class="my-2 opacity-25">
                            <div class="d-flex align-items-center justify-content-between pt-1">
                                <span class="text-muted small fw-semibold"><i class="bi bi-clock-history me-1"></i>Est. Process Time</span>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-bold px-3 py-2 fs-7" id="modalEstTime">~15 mins</span>
                            </div>
                        </div>
                    </div>

                    <button type="button" class="btn btn-kiosk-primary btn-lg w-100 py-3 shadow-sm" id="btnFinishKiosk">
                        <span>Done & Reset Kiosk</span>
                        <i class="bi bi-arrow-counterclockwise ms-1"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php include_once ADMIN_SCRIPT_PATH; ?>

<script>
    (function() {
        const employeeIdInput      = document.getElementById('employeeId');
        const detailsSection       = document.getElementById('employeeDetailsSection');
        const idHelpBlock          = document.getElementById('idHelpBlock');
        const userIdInput          = document.getElementById('userId');
        const firstNameInput       = document.getElementById('firstName');
        const middleNameInput      = document.getElementById('middleName');
        const lastNameInput        = document.getElementById('lastName');
        const suffixSelect         = document.getElementById('suffix');
        const postNominalInput     = document.getElementById('postNominal');
        const sexSelect            = document.getElementById('sex');
        const emailInput           = document.getElementById('email');
        const departmentSelect     = document.getElementById('departmentId');
        const employeeOfficeSelect = document.getElementById('employeeOfficeId');
        const contactInput         = document.getElementById('contactNumber');
        
        // Data Privacy Elements
        const dataConsentInput     = document.getElementById('dataConsent');
        const btnOpenPrivacyModal  = document.getElementById('btnOpenPrivacyModal');
        const consentSubText       = document.getElementById('consentSubText');

        const privacyModalEl       = document.getElementById('privacyAgreementModal');
        const modalConsentCheckbox = document.getElementById('modalConsentCheckbox');
        const btnAcceptPrivacy     = document.getElementById('btnAcceptPrivacyModal');

        const btnContinue          = document.getElementById('btnContinueStep2');

        // Priority Switches
        const prioritySwitch       = document.getElementById('isPrioritySwitch');
        const prioritySection      = document.getElementById('priorityOptionsSection');

        // Step Containers & Wizards
        const step1Div             = document.getElementById('step1_employeeInfo');
        const step2Div             = document.getElementById('step2_buildingSelection');
        const step3Div             = document.getElementById('step3_officeSelection');
        const step4Div             = document.getElementById('step4_purposeSelection');

        const wizardStep1          = document.getElementById('wizardStep1');
        const wizardStep2          = document.getElementById('wizardStep2');
        const wizardStep3          = document.getElementById('wizardStep3');
        const wizardStep4          = document.getElementById('wizardStep4');

        const btnBackTo1           = document.getElementById('btnBackToStep1');
        const btnBackTo2           = document.getElementById('btnBackToStep2');
        const btnBackTo3           = document.getElementById('btnBackToStep3');

        const facilityInput        = document.getElementById('facilitySelect');
        const buildingCards        = document.querySelectorAll('.building-card');
        const summaryBuildingName  = document.getElementById('summaryBuildingName');
        const summaryBuildingCode  = document.getElementById('summaryBuildingCode');

        const officeInput          = document.getElementById('officeSelect');
        const officeCards          = document.querySelectorAll('.office-card');
        const summaryOfficeName    = document.getElementById('summaryOfficeName');
        const summaryOfficeCode    = document.getElementById('summaryOfficeCode');

        const transactionInput     = document.getElementById('transactionSelect');
        const subConcernInput      = document.getElementById('subConcernSelect');
        const transactionContainer = document.getElementById('transactionCardsContainer');
        const otherConcernBox      = document.getElementById('otherConcernInputContainer');
        const otherConcernText     = document.getElementById('otherConcernText');

        const kioskForm            = document.getElementById('kioskForm');
        const btnFinalSubmit       = document.getElementById('btnFinalSubmit');

        let selectedConcernIds     = [];
        let selectedSubConcernsMap = {}; 
        let debounceTimer;

        // Data Privacy Modal Logic
        const privacyModalInstance = privacyModalEl ? new bootstrap.Modal(privacyModalEl) : null;

        if (btnOpenPrivacyModal && privacyModalInstance) {
            btnOpenPrivacyModal.addEventListener('click', () => privacyModalInstance.show());
        }

        if (modalConsentCheckbox && btnAcceptPrivacy) {
            modalConsentCheckbox.addEventListener('change', function() {
                btnAcceptPrivacy.disabled = !this.checked;
            });
        }

        if (btnAcceptPrivacy) {
            btnAcceptPrivacy.addEventListener('click', function() {
                dataConsentInput.value = "1";
                btnOpenPrivacyModal.className = 'btn btn-success w-100 py-3 rounded-3 fw-bold d-flex align-items-center justify-content-center gap-2 shadow-sm';
                btnOpenPrivacyModal.innerHTML = '<i class="bi bi-check-circle-fill fs-5"></i><span>Read & Sign Agreement Form</span>';
                
                if (consentSubText) {
                    consentSubText.className = 'text-success small text-center mt-2 fw-semibold';
                    consentSubText.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Data privacy terms verified & accepted. Tap above to review again.';
                }

                if (privacyModalInstance) {
                    privacyModalInstance.hide();
                }
            });
        }

        if (prioritySwitch && prioritySection) {
            prioritySwitch.addEventListener('change', function() {
                prioritySection.classList.toggle('d-none', !this.checked);
            });
        }

        function setWizardStep(stepNum) {
            wizardStep1.className = 'step-item ' + (stepNum === 1 ? 'active' : (stepNum > 1 ? 'completed' : ''));
            wizardStep2.className = 'step-item ' + (stepNum === 2 ? 'active' : (stepNum > 2 ? 'completed' : ''));
            wizardStep3.className = 'step-item ' + (stepNum === 3 ? 'active' : (stepNum > 3 ? 'completed' : ''));
            wizardStep4.className = 'step-item ' + (stepNum === 4 ? 'active' : '');
        }

        employeeIdInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            const employeeId = this.value.trim();

            if (employeeId.length >= 3) {
                idHelpBlock.textContent = "Verifying Employee ID...";
                idHelpBlock.className = "form-text text-info fw-semibold small";
                debounceTimer = setTimeout(() => fetchEmployeeInfo(employeeId), 400);
            } else {
                detailsSection.classList.add('d-none');
                idHelpBlock.textContent = "Enter your Employee ID to verify details.";
                idHelpBlock.className = "form-text text-muted small";
            }
        });

        function fetchEmployeeInfo(employeeId) {
            const formData = new FormData();
            formData.append('action', 'fetch_employee_info');
            formData.append('employee_id', employeeId);

            fetch('<?= BASE_URL; ?>admin/ajax/logbook', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(result => {
                if (result.success && result.data) {
                    const emp = result.data;
                    userIdInput.value       = emp.user_id || '';
                    firstNameInput.value    = emp.first_name || '';
                    middleNameInput.value   = emp.middle_name || '';
                    lastNameInput.value     = emp.last_name || '';
                    suffixSelect.value      = emp.suffix || '';
                    postNominalInput.value  = emp.post_nominal || '';
                    sexSelect.value         = emp.sex || '';
                    emailInput.value        = emp.email || '';
                    contactInput.value      = emp.contact_no || '';

                    if (emp.department_id) departmentSelect.value = emp.department_id;
                    if (emp.office_id && employeeOfficeSelect) employeeOfficeSelect.value = emp.office_id;

                    detailsSection.classList.remove('d-none');
                    idHelpBlock.textContent = "Record verified. Please review details below.";
                    idHelpBlock.className = "form-text text-success fw-bold small";
                } else {
                    detailsSection.classList.add('d-none');
                    idHelpBlock.textContent = "Employee ID not recognized. Please verify.";
                    idHelpBlock.className = "form-text text-danger fw-semibold small";
                }
            });
        }

        btnContinue.addEventListener('click', function() {
            if (!firstNameInput.value.trim() || !lastNameInput.value.trim() || !sexSelect.value || !contactInput.value.trim() || !emailInput.value.trim() || !departmentSelect.value) {
                alert("Please complete all required personal fields.");
                return;
            }
            if (dataConsentInput.value !== "1") {
                alert("Please click '[ Read & Sign Agreement Form ]' and accept the privacy terms before proceeding.");
                if (privacyModalInstance) {
                    privacyModalInstance.show();
                }
                return;
            }
            step1Div.classList.add('d-none');
            step2Div.classList.remove('d-none');
            setWizardStep(2);
        });

        btnBackTo1.addEventListener('click', function() {
            step2Div.classList.add('d-none');
            step1Div.classList.remove('d-none');
            setWizardStep(1);
        });

        buildingCards.forEach(card => {
            card.addEventListener('click', function() {
                const facId   = this.dataset.facilityId;
                const facName = this.dataset.facilityName;
                const facCode = this.dataset.facilityCode;

                buildingCards.forEach(c => c.classList.remove('active'));
                this.classList.add('active');

                facilityInput.value = facId;
                summaryBuildingName.textContent = facName;
                summaryBuildingCode.textContent = facCode;

                officeCards.forEach(offCard => {
                    if (offCard.dataset.facilityId === facId) {
                        offCard.classList.remove('d-none');
                    } else {
                        offCard.classList.add('d-none');
                    }
                });

                step2Div.classList.add('d-none');
                step3Div.classList.remove('d-none');
                setWizardStep(3);
            });
        });

        btnBackTo2.addEventListener('click', function() {
            step3Div.classList.add('d-none');
            step2Div.classList.remove('d-none');
            setWizardStep(2);
        });

        officeCards.forEach(card => {
            card.addEventListener('click', function() {
                const selectedOfficeId = this.dataset.officeId;
                officeCards.forEach(c => c.classList.remove('active'));
                this.classList.add('active');

                officeInput.value = selectedOfficeId;
                summaryOfficeName.textContent = this.dataset.officeName;
                summaryOfficeCode.textContent = this.dataset.officeCode;

                fetchOfficeTransactions(selectedOfficeId);

                step3Div.classList.add('d-none');
                step4Div.classList.remove('d-none');
                setWizardStep(4);
            });
        });

        btnBackTo3.addEventListener('click', function() {
            step4Div.classList.add('d-none');
            step3Div.classList.remove('d-none');
            setWizardStep(3);
        });

        function fetchOfficeTransactions(officeId) {
            transactionContainer.innerHTML = '<div class="text-center p-3 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Loading concerns...</div>';
            transactionInput.value = '';
            subConcernInput.value = '';
            selectedConcernIds = [];
            selectedSubConcernsMap = {};
            otherConcernBox.classList.add('d-none');
            otherConcernText.value = '';

            const formData = new FormData();
            formData.append('action', 'fetch_office_transactions');
            formData.append('office_id', officeId);

            fetch('<?= BASE_URL; ?>admin/ajax/office', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(result => {
                transactionContainer.innerHTML = '';
                let dataList = (result.success && result.data) ? result.data : [];
                
                dataList.push({
                    id: 'OTHERS',
                    transaction_name: 'Others (Please Specify Concern)',
                    estimated_minutes: 10,
                    is_others: true,
                    sub_concerns: []
                });

                dataList.forEach(trans => {
                    const card = document.createElement('div');
                    card.className = 'transaction-card p-3 mb-2';
                    card.dataset.transId = trans.id;
                    card.dataset.estMins  = trans.estimated_minutes || 5;

                    const estLabel = `Estimated Process Time: ${trans.estimated_minutes || 5} mins`;

                    let subConcernsHtml = '';
                    if (trans.sub_concerns && trans.sub_concerns.length > 0) {
                        subConcernsHtml = `
                            <div class="sub-concerns-container d-none mt-3 pt-3 border-top">
                                <label class="form-label text-muted fw-bold small d-block mb-2">Specific Sub-Concern (Optional):</label>
                                <div class="d-flex flex-wrap gap-2">
                                    ${trans.sub_concerns.map(sub => `
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-primary sub-concern-btn px-3 py-1.5" 
                                                data-sub-id="${sub.id}">
                                            ${sub.sub_concern_name}
                                        </button>
                                    `).join('')}
                                </div>
                            </div>
                        `;
                    }

                    card.innerHTML = `
                        <div class="card-header-row d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-3 min-w-0 flex-grow-1">
                                <i class="bi bi-square text-muted fs-4 select-icon flex-shrink-0"></i>
                                <div class="min-w-0 flex-grow-1">
                                    <h6 class="fw-bold text-dark mb-0 fs-6">${trans.transaction_name}</h6>
                                    <span class="badge bg-light text-secondary border mt-1 small">${estLabel}</span>
                                </div>
                            </div>
                        </div>
                        ${subConcernsHtml}
                    `;

                    const cardHeader = card.querySelector('.card-header-row');
                    cardHeader.addEventListener('click', function(e) {
                        e.stopPropagation();
                        const icon = card.querySelector('.select-icon');
                        const subContainer = card.querySelector('.sub-concerns-container');

                        if (selectedConcernIds.includes(trans.id)) {
                            selectedConcernIds = selectedConcernIds.filter(id => id !== trans.id);
                            delete selectedSubConcernsMap[trans.id];
                            card.classList.remove('active');
                            icon.className = 'bi bi-square text-muted fs-4 select-icon flex-shrink-0';
                            if (subContainer) subContainer.classList.add('d-none');
                            if (trans.is_others) otherConcernBox.classList.add('d-none');
                        } else {
                            selectedConcernIds.push(trans.id);
                            selectedSubConcernsMap[trans.id] = [];
                            card.classList.add('active');
                            icon.className = 'bi bi-check-square-fill text-primary fs-4 select-icon flex-shrink-0';
                            if (subContainer) subContainer.classList.remove('d-none');
                            if (trans.is_others) otherConcernBox.classList.remove('d-none');
                        }

                        transactionInput.value = selectedConcernIds.join(',');
                        subConcernInput.value  = JSON.stringify(selectedSubConcernsMap);
                    });

                    const subBtns = card.querySelectorAll('.sub-concern-btn');
                    subBtns.forEach(btn => {
                        btn.addEventListener('click', function(e) {
                            e.stopPropagation();
                            const subId = parseInt(this.dataset.subId);
                            if (!selectedSubConcernsMap[trans.id]) {
                                selectedSubConcernsMap[trans.id] = [];
                            }

                            const index = selectedSubConcernsMap[trans.id].indexOf(subId);
                            if (index > -1) {
                                selectedSubConcernsMap[trans.id].splice(index, 1);
                                this.classList.remove('btn-primary', 'text-white');
                                this.classList.add('btn-outline-primary');
                            } else {
                                selectedSubConcernsMap[trans.id].push(subId);
                                this.classList.remove('btn-outline-primary');
                                this.classList.add('btn-primary', 'text-white');
                            }

                            subConcernInput.value = JSON.stringify(selectedSubConcernsMap);
                        });
                    });

                    transactionContainer.appendChild(card);
                });
            });
        }

        kioskForm.addEventListener('submit', function(e) {
            e.preventDefault();
            if (!transactionInput.value && !otherConcernText.value.trim()) {
                alert("Please select at least one transaction concern or specify your concern in Others.");
                return;
            }

            let structuredConcernsHtml = '';
            let totalEstimatedMinutes = 0;

            document.querySelectorAll('#transactionCardsContainer .transaction-card.active').forEach(card => {
                const titleEl = card.querySelector('h6');
                if (!titleEl) return;
                let title = titleEl.textContent.trim();

                const cardMins = parseInt(card.dataset.estMins || 10);
                totalEstimatedMinutes += cardMins;

                if (card.dataset.transId === 'OTHERS') {
                    const customVal = otherConcernText.value.trim();
                    structuredConcernsHtml += `
                        <div class="receipt-concern-item">
                            <div class="receipt-concern-title"><i class="bi bi-pen me-1 text-primary"></i>Others</div>
                            <div class="text-muted small mt-1">${customVal ? customVal : 'General Concern'}</div>
                        </div>
                    `;
                    return;
                }

                const activeSubBtns = card.querySelectorAll('.sub-concern-btn.btn-primary');
                let subTagsHtml = '';
                if (activeSubBtns.length > 0) {
                    const subNames = Array.from(activeSubBtns).map(b => `<span class="receipt-subconcern-tag"><i class="bi bi-tag-fill me-1"></i>${b.textContent.trim()}</span>`).join(' ');
                    subTagsHtml = `<div class="mt-1">${subNames}</div>`;
                }

                structuredConcernsHtml += `
                    <div class="receipt-concern-item">
                        <div class="receipt-concern-title">${title}</div>
                        ${subTagsHtml}
                    </div>
                `;
            });

            if (!structuredConcernsHtml) {
                structuredConcernsHtml = `
                    <div class="receipt-concern-item">
                        <div class="receipt-concern-title">General Concern</div>
                    </div>
                `;
                totalEstimatedMinutes = 15;
            }

            btnFinalSubmit.disabled = true;
            btnFinalSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Issuing Ticket...';

            const formData = new FormData(kioskForm);

            fetch('<?= BASE_URL; ?>admin/ajax/logbook', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(result => {
                btnFinalSubmit.disabled = false;
                btnFinalSubmit.innerHTML = '<span>Complete Check-in & Get Ticket</span> <i class="bi bi-check-circle ms-1 fs-5"></i>';

                if (result.success) {
                    document.getElementById('modalTicketNumber').textContent = result.ticket_number || '---';
                    document.getElementById('modalOfficeName').textContent   = result.office_name || summaryOfficeName.textContent;
                    document.getElementById('modalTransactionContainer').innerHTML = structuredConcernsHtml;
                    document.getElementById('modalIssuedDate').textContent = new Date().toLocaleString();
                    
                    const estMins = result.estimated_minutes || totalEstimatedMinutes || 15;
                    document.getElementById('modalEstTime').textContent = `~${estMins} mins`;

                    const priorityBadge = document.getElementById('modalPriorityBadge');
                    if (priorityBadge) {
                        priorityBadge.classList.toggle('d-none', !result.is_priority);
                    }

                    const ticketModal = new bootstrap.Modal(document.getElementById('ticketSuccessModal'));
                    ticketModal.show();
                } else {
                    alert(result.message || "Failed to process check-in.");
                }
            });
        });

        document.getElementById('btnFinishKiosk')?.addEventListener('click', function() {
            window.location.reload();
        });
    })();
</script>
</body>
</html>