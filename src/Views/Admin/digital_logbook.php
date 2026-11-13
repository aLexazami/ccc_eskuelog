<?php
# Core Config Dependencies
$db_connect  = $db_connect ?? null;
$g_user_role = $g_user_role ?? '';

# Fetch Active Programs
$programs_list = [];
if ($db_connect) {
    $stmtProg = $db_connect->query("SELECT program_id, department_id, program_code, program_name, major FROM programs WHERE flag_offer = 0 ORDER BY program_code ASC");
    if ($stmtProg) {
        while ($row = $stmtProg->fetch_assoc()) {
            $programs_list[] = $row;
        }
    }
}

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

# Fetch Active Offices Joined with Room
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
    
    <style>
        :root {
            --bg-wrapper: linear-gradient(135deg, rgba(11, 32, 117, 0.25) 0%, rgba(0, 10, 76, 0.45) 100%), url('<?= BASE_URL; ?>public/assets/main/css/bg-template.png');
            --kiosk-primary: #1d4ed8;
            --kiosk-primary-hover: #1e40af;
            --kiosk-primary-light: #eff6ff;
            --kiosk-border: #e2e8f0;
            --kiosk-bg-subtle: #f8fafc;
            --kiosk-shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.2), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            --kiosk-radius: 1rem;
        }

        body.kiosk-mode {
            background-color: #000a4c !important;
            background-image: var(--bg-wrapper) !important;
            background-position: center center !important;
            background-repeat: no-repeat !important;
            background-size: cover !important;
            background-attachment: fixed !important;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem 1rem;
            margin: 0;
        }

        .kiosk-main-card {
            border: 1px solid var(--kiosk-border);
            border-radius: var(--kiosk-radius);
            box-shadow: var(--kiosk-shadow-lg);
            background: #ffffff;
            overflow: hidden;
            width: 100%;
            max-width: 1080px;
            margin: 0 auto;
        }

        .kiosk-header-blue {
            background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%) !important;
            position: relative;
            overflow: hidden;
        }

        .kiosk-header-blue::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.15) 0%, transparent 60%);
            pointer-events: none;
        }

        .step-progress-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.875rem 1rem;
            background: var(--kiosk-bg-subtle);
            border-bottom: 1px solid var(--kiosk-border);
            overflow-x: auto;
        }

        .step-item {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.8rem;
            font-weight: 600;
            color: #64748b;
            transition: all 0.3s ease;
            white-space: nowrap;
            cursor: default;
            user-select: none;
        }

        .step-item.completed {
            cursor: pointer;
        }

        .step-item.completed:hover {
            color: var(--kiosk-primary);
        }

        .step-item.completed:hover .step-number {
            background: var(--kiosk-primary);
            transform: scale(1.08);
        }

        .step-item:focus-visible {
            outline: 3px solid rgba(29, 78, 216, 0.35);
            outline-offset: 4px;
            border-radius: 6px;
        }

        .step-item .step-number {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: #cbd5e1;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
            flex-shrink: 0;
            transition: all 0.2s ease;
        }

        .step-item.active { color: var(--kiosk-primary); }
        .step-item.active .step-number {
            background: var(--kiosk-primary);
            box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.15);
        }
        .step-item.completed .step-number { background: #10b981; }

        .step-divider {
            flex-grow: 1;
            height: 2px;
            background: #e2e8f0;
            margin: 0 0.35rem;
            min-width: 6px;
        }

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
            display: inline-flex;
            flex-direction: column;
            align-items: flex-start;
            text-align: left;
            gap: 0.25rem;
        }

        .sub-concern-btn .sub-est-badge {
            font-size: 0.72rem;
            padding: 0.15rem 0.45rem;
            border-radius: 0.4rem;
            background-color: rgba(29, 78, 216, 0.1);
            color: #1d4ed8;
            font-weight: 700;
        }

        .sub-concern-btn.btn-primary .sub-est-badge {
            background-color: rgba(255, 255, 255, 0.22);
            color: #ffffff;
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

        .visitor-type-btn {
            border: 2px solid #cbd5e1;
            background-color: #f8fafc;
            color: #475569;
            font-weight: 700;
            padding: 0.85rem 1rem;
            border-radius: 0.75rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .visitor-type-btn.active {
            border-color: var(--kiosk-primary);
            background-color: var(--kiosk-primary-light);
            color: var(--kiosk-primary);
            box-shadow: 0 4px 6px -1px rgba(29, 78, 216, 0.15);
        }

        /* GLASSMORPHISM VERIFICATION MODAL */
        .glass-modal-content {
            background: rgba(255, 255, 255, 0.88) !important;
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.6) !important;
            box-shadow: 0 30px 60px -12px rgba(0, 10, 76, 0.35), 0 0 0 1px rgba(255, 255, 255, 0.4) !important;
        }

        .glass-header-card {
            background: linear-gradient(135deg, rgba(29, 78, 216, 0.95) 0%, rgba(30, 58, 138, 0.98) 100%) !important;
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
        }

        .glass-header-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.18) 0%, transparent 60%);
            pointer-events: none;
        }

        .verify-profile-avatar {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid rgba(255, 255, 255, 0.9);
            box-shadow: 0 12px 24px -6px rgba(0, 0, 0, 0.25), 0 0 0 3px rgba(29, 78, 216, 0.3);
            background-color: #ffffff;
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .verify-profile-avatar:hover { transform: scale(1.05); }

        .glass-footer-card {
            background: rgba(248, 250, 252, 0.85) !important;
            backdrop-filter: blur(10px);
            border-top: 1px solid rgba(226, 232, 240, 0.8) !important;
        }

        .summary-section-box {
            background-color: #ffffff;
            border: 1.5px solid #e2e8f0;
            border-radius: 0.875rem;
            padding: 1.25rem;
            margin-bottom: 1.25rem;
        }

        .detail-group-card {
            background-color: #f8fafc;
            border: 1.5px solid #cbd5e1;
            border-left: 4px solid var(--kiosk-primary);
            border-radius: 0.75rem;
            padding: 1rem 1.15rem;
        }

        .receipt-reminder-highlight {
            background-color: #fef3c7;
            border: 1.5px solid #f59e0b;
            color: #78350f;
            border-radius: 0.75rem;
            padding: 0.65rem 0.9rem;
            font-weight: 700;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
    </style>
</head>

<body class="kiosk-mode">

    <div class="card kiosk-main-card border-0">

        <!-- MODERN BLUE THEME HEADER -->
        <div class="card-header kiosk-header-blue py-3 px-4 border-0">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-white bg-opacity-25 text-white rounded-3 d-flex align-items-center justify-content-center flex-shrink-0 shadow-sm" style="width: 44px; height: 44px; backdrop-filter: blur(4px);">
                    <i class="bi bi-journal-check fs-4"></i>
                </div>
                <div>
                    <h5 class="fw-bold text-white tracking-wide mb-0" style="letter-spacing: 0.5px;">Digital Logbook</h5>
                </div>
            </div>
        </div>

        <div class="step-progress-bar">
            <div class="step-item active" id="wizardStep1"
                role="button" tabindex="0" aria-label="Go back to Info">
                <span class="step-number">1</span>
                <span class="d-none d-sm-inline">Info</span>
            </div>

            <div class="step-divider"></div>

            <div class="step-item" id="wizardStep2"
                role="button" tabindex="0" aria-label="Go back to Building">
                <span class="step-number">2</span>
                <span class="d-none d-sm-inline">Building</span>
            </div>

            <div class="step-divider"></div>

            <div class="step-item" id="wizardStep3"
                role="button" tabindex="0" aria-label="Go back to Office">
                <span class="step-number">3</span>
                <span class="d-none d-sm-inline">Office</span>
            </div>

            <div class="step-divider"></div>

            <div class="step-item" id="wizardStep4"
                role="button" tabindex="0" aria-label="Go back to Concerns">
                <span class="step-number">4</span>
                <span class="d-none d-sm-inline">Concerns</span>
            </div>

            <div class="step-divider" id="wizardStep5Divider"></div>

            <div class="step-item" id="wizardStep5"
                role="button" tabindex="0" aria-label="Go back to Summary">
                <span class="step-number">5</span>
                <span class="d-none d-sm-inline">Summary & Submit</span>
            </div>
        </div>

        <div class="card-body card-body-kiosk p-3 p-sm-4 p-md-5">

            <form id="kioskForm" novalidate>
                <input type="hidden" name="action" value="logbook_checkin">
                <input type="hidden" id="visitorType" name="visitor_type" value="STUDENT">
                <input type="hidden" id="userId" name="user_id" value="">
                <input type="hidden" id="departmentId" name="department_id" value="0">
                <input type="hidden" id="programCode" name="program_code" value="">
                <input type="hidden" id="programMajor" name="major" value="">
                
                <!-- SELECTION STATES -->
                <input type="hidden" id="facilitySelect" name="facility_id" value="" required>
                <input type="hidden" id="officeSelect" name="office_id" value="" required>
                <input type="hidden" id="transactionSelect" name="transaction_type_id" value="" required>
                <input type="hidden" id="subConcernSelect" name="sub_concern_id" value="">

                <!-- ========================================================== -->
                <!-- STEP 1: VISITOR CATEGORY & PERSONAL INFORMATION             -->
                <!-- ========================================================== -->
                <div id="step1_visitorInfo">
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold text-dark small d-block">Select Visitor Category:</label>
                        <div class="row g-2">
                            <div class="col-4">
                                <button type="button" class="visitor-type-btn active w-100 d-flex align-items-center justify-content-center gap-2" data-type="STUDENT">
                                    <i class="bi bi-person-vcard fs-5"></i>
                                    <span>Student</span>
                                </button>
                            </div>
                            <div class="col-4">
                                <button type="button" class="visitor-type-btn w-100 d-flex align-items-center justify-content-center gap-2" data-type="EMPLOYEE">
                                    <i class="bi bi-person-badge fs-5"></i>
                                    <span>Employee</span>
                                </button>
                            </div>
                            <div class="col-4">
                                <button type="button" class="visitor-type-btn w-100 d-flex align-items-center justify-content-center gap-2" data-type="GUEST">
                                    <i class="bi bi-person-walking fs-5"></i>
                                    <span>Guest</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div id="idLookupSection" class="mb-3 mb-sm-4">
                        <label for="referenceId" class="form-label fw-bold text-dark small" id="referenceIdLabel">Student ID Number</label>
                        <div class="input-group">
                            <input type="text" class="form-control form-control-kiosk form-control-lg fw-bold text-primary" id="referenceId" name="reference_id" placeholder="e.g., 2000-1234" maxlength="20" autocomplete="off" autofocus>
                            <button type="button" class="btn btn-primary px-4 fw-bold rounded-end-3" id="btnVerifyId">
                                <i class="bi bi-search me-1"></i> Verify
                            </button>
                        </div>
                        <div id="idHelpBlock" class="form-text text-muted mt-2 small">Enter your ID number and click Verify.</div>
                    </div>

                    <div id="personalDetailsSection" class="summary-section-box d-none">

                        <div class="row g-2 g-sm-3 mb-3" id="step1NameRow">
                            <div class="col-12 col-sm-6 col-md-3 name-col-primary">
                                <label for="firstName" class="form-label fw-bold text-dark small">First Name</label>
                                <input type="text" class="form-control form-control-kiosk step1-sync" id="firstName" name="first_name" placeholder="First Name" required>
                            </div>
                            <div class="col-12 col-sm-6 col-md-3 name-col-secondary">
                                <label for="middleName" class="form-label fw-bold text-dark small">Middle Name</label>
                                <input type="text" class="form-control form-control-kiosk step1-sync" id="middleName" name="middle_name" placeholder="Middle Name">
                            </div>
                            <div class="col-12 col-sm-6 col-md-3 name-col-primary">
                                <label for="lastName" class="form-label fw-bold text-dark small">Last Name</label>
                                <input type="text" class="form-control form-control-kiosk step1-sync" id="lastName" name="last_name" placeholder="Last Name" required>
                            </div>
                            <div class="col-12 col-sm-6 col-md-3 name-col-secondary" id="suffixContainer">
                                <label for="suffix" class="form-label fw-bold text-dark small">Suffix</label>
                                <select class="form-select form-select-kiosk step1-sync" id="suffix" name="suffix">
                                    <option value="" selected>None</option>
                                    <option value="Jr.">Jr.</option>
                                    <option value="Sr.">Sr.</option>
                                    <option value="I">I</option>
                                    <option value="II">II</option>
                                    <option value="III">III</option>
                                </select>
                            </div>
                            <div class="col-6 col-sm-6 col-md-2 d-none" id="postNominalContainer">
                                <label for="postNominal" class="form-label fw-bold text-dark small">Post Nominal</label>
                                <input type="text" class="form-control form-control-kiosk step1-sync" id="postNominal" name="post_nominal" placeholder="PhD, LPT">
                            </div>
                        </div>

                        <div class="row g-2 g-sm-3 mb-3">
                            <div class="col-12 col-md-6">
                                <label for="sex" class="form-label fw-bold text-dark small">Sex</label>
                                <select class="form-select form-select-kiosk step1-sync" id="sex" name="sex" required>
                                    <option value="" selected disabled>Select sex</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="email" class="form-label fw-bold text-dark small">Email Address</label>
                                <input type="email" class="form-control form-control-kiosk step1-sync" id="email" name="email" placeholder="user@example.com" required>
                            </div>
                        </div>

                        <div id="studentFieldsContainer" class="row g-2 g-sm-3 mb-3">
                            <div class="col-12 col-sm-6">
                                <label for="courseProgram" class="form-label fw-bold text-dark small">Course / Program</label>
                                <select class="form-select form-select-kiosk step1-sync" id="courseProgram" name="program_id" required>
                                    <option value="" selected disabled>Select course / program</option>
                                    <?php foreach ($programs_list as $prog): ?>
                                        <?php 
                                            $displayName = $prog['program_code'] . ' - ' . $prog['program_name'];
                                            if (!empty($prog['major'])) {
                                                $displayName .= ' (Major in ' . $prog['major'] . ')';
                                            }
                                        ?>
                                        <option value="<?= htmlspecialchars($prog['program_id']); ?>" 
                                                data-department-id="<?= htmlspecialchars($prog['department_id']); ?>"
                                                data-program-code="<?= htmlspecialchars($prog['program_code']); ?>"
                                                data-program-name="<?= htmlspecialchars($prog['program_name']); ?>"
                                                data-major="<?= htmlspecialchars($prog['major'] ?? ''); ?>">
                                            <?= htmlspecialchars($displayName); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12 col-sm-6">
                                <label for="yearLevel" class="form-label fw-bold text-dark small">Year Level</label>
                                <select class="form-select form-select-kiosk step1-sync" id="yearLevel" name="year_level" required>
                                    <option value="" selected disabled>Select year level</option>
                                    <option value="1">1st Year</option>
                                    <option value="2">2nd Year</option>
                                    <option value="3">3rd Year</option>
                                    <option value="4">4th Year</option>
                                </select>
                            </div>
                        </div>

                        <div id="employeeFieldsContainer" class="row g-2 g-sm-3 mb-3 d-none">
                            <div class="col-12 col-sm-6">
                                <label for="employeeDepartment" class="form-label fw-bold text-dark small">Department</label>
                                <select class="form-select form-select-kiosk step1-sync" id="employeeDepartment" name="department_id_select">
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
                                <select class="form-select form-select-kiosk step1-sync" id="employeeOfficeId" name="employee_office_id">
                                    <option value="" selected>Select office</option>
                                    <?php foreach ($offices_list as $off): ?>
                                        <option value="<?= htmlspecialchars($off['office_id']); ?>">
                                            <?= htmlspecialchars($off['office_code'] . ' - ' . $off['office_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="priority-card-box mb-4">
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
                        <i class="bi bi-arrow-left me-1"></i> Back to Personal Information
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
                <!-- STEP 4: TRANSACTION CONCERNS SELECTION                     -->
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
                            <i class="bi bi-arrow-left me-1"></i> Back to Office Selection
                        </button>
                        <button type="button" id="btnContinueToStep5" class="btn btn-kiosk-primary py-3 fw-bold rounded-3 flex-grow-1 shadow-sm">
                            <span>Proceed to Logbook Summary</span>
                            <i class="bi bi-arrow-right ms-1 fs-5"></i>
                        </button>
                    </div>
                </div>

                <!-- ========================================================== -->
                <!-- STEP 5: FINAL LOGBOOK SUMMARY & DATA PRIVACY AGREEMENT    -->
                <!-- ========================================================== -->
                <div id="step5_summaryReview" class="d-none">
                    
                    <div class="mb-4">
                        <h3 class="fw-bold text-dark mb-1 fs-4">Review Logbook Summary</h3>
                        <p class="text-muted small mb-0">Please verify your details, complete any required transaction information, and accept the agreement before submitting.</p>
                    </div>

                    <!-- VISITOR PERSONAL DETAILS (SHOWN FOR ALL FLOWS & SYNCED WITH STEP 1) -->
                    <div id="step5VisitorDetailsSection">
                        <div class="summary-section-box">
                            <h6 class="fw-bold text-primary mb-3">
                                <i class="bi bi-person-lines-fill me-1"></i> Personal Information
                            </h6>

                            <div class="row g-2 g-sm-3 mb-3" id="step5NameRow">
                                <div class="col-12 col-sm-6 col-md-3 name-col-primary">
                                    <label for="step5_firstName" class="form-label fw-bold text-dark small">First Name</label>
                                    <input type="text" class="form-control form-control-kiosk step5-sync" id="step5_firstName" placeholder="First Name" required>
                                </div>
                                <div class="col-12 col-sm-6 col-md-3 name-col-secondary">
                                    <label for="step5_middleName" class="form-label fw-bold text-dark small">Middle Name</label>
                                    <input type="text" class="form-control form-control-kiosk step5-sync" id="step5_middleName" placeholder="Middle Name">
                                </div>
                                <div class="col-12 col-sm-6 col-md-3 name-col-primary">
                                    <label for="step5_lastName" class="form-label fw-bold text-dark small">Last Name</label>
                                    <input type="text" class="form-control form-control-kiosk step5-sync" id="step5_lastName" placeholder="Last Name" required>
                                </div>
                                <div class="col-12 col-sm-6 col-md-3 name-col-secondary" id="step5_suffixContainer">
                                    <label for="step5_suffix" class="form-label fw-bold text-dark small">Suffix</label>
                                    <select class="form-select form-select-kiosk step5-sync" id="step5_suffix">
                                        <option value="">None</option>
                                        <option value="Jr.">Jr.</option>
                                        <option value="Sr.">Sr.</option>
                                        <option value="I">I</option>
                                        <option value="II">II</option>
                                        <option value="III">III</option>
                                    </select>
                                </div>
                                <div class="col-6 col-sm-6 col-md-2 d-none" id="step5_postNominalContainer">
                                    <label for="step5_postNominal" class="form-label fw-bold text-dark small">Post Nominal</label>
                                    <input type="text" class="form-control form-control-kiosk step5-sync" id="step5_postNominal" placeholder="PhD, LPT">
                                </div>
                            </div>

                            <div class="row g-2 g-sm-3 mb-3">
                                <div class="col-12 col-md-6">
                                    <label for="step5_sex" class="form-label fw-bold text-dark small">Sex</label>
                                    <select class="form-select form-select-kiosk step5-sync" id="step5_sex" required>
                                        <option value="" selected disabled>Select sex</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="step5_email" class="form-label fw-bold text-dark small">Email Address</label>
                                    <input type="email" class="form-control form-control-kiosk step5-sync" id="step5_email" placeholder="user@example.com" required>
                                </div>
                            </div>

                            <!-- STUDENT FIELDS -->
                            <div id="step5_studentFieldsContainer" class="row g-2 g-sm-3 mb-1">
                                <div class="col-12 col-sm-6">
                                    <label for="step5_courseProgram" class="form-label fw-bold text-dark small">Course / Program</label>
                                    <select class="form-select form-select-kiosk step5-sync" id="step5_courseProgram" required>
                                        <option value="" selected disabled>Select course / program</option>
                                        <?php foreach ($programs_list as $prog): ?>
                                            <?php 
                                                $displayName = $prog['program_code'] . ' - ' . $prog['program_name'];
                                                if (!empty($prog['major'])) {
                                                    $displayName .= ' (Major in ' . $prog['major'] . ')';
                                                }
                                            ?>
                                            <option value="<?= htmlspecialchars($prog['program_id']); ?>">
                                                <?= htmlspecialchars($displayName); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-12 col-sm-6">
                                    <label for="step5_yearLevel" class="form-label fw-bold text-dark small">Year Level</label>
                                    <select class="form-select form-select-kiosk step5-sync" id="step5_yearLevel" required>
                                        <option value="" selected disabled>Select year level</option>
                                        <option value="1">1st Year</option>
                                        <option value="2">2nd Year</option>
                                        <option value="3">3rd Year</option>
                                        <option value="4">4th Year</option>
                                    </select>
                                </div>
                            </div>

                            <!-- EMPLOYEE FIELDS -->
                            <div id="step5_employeeFieldsContainer" class="row g-2 g-sm-3 mb-1 d-none">
                                <div class="col-12 col-sm-6">
                                    <label for="step5_employeeDepartment" class="form-label fw-bold text-dark small">Department</label>
                                    <select class="form-select form-select-kiosk step5-sync" id="step5_employeeDepartment">
                                        <option value="" selected disabled>Select department</option>
                                        <?php foreach ($departments_list as $dept): ?>
                                            <option value="<?= htmlspecialchars($dept['department_id']); ?>">
                                                <?= htmlspecialchars($dept['department_code'] . ' - ' . $dept['department_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-12 col-sm-6">
                                    <label for="step5_employeeOfficeId" class="form-label fw-bold text-dark small">
                                        Office <span class="text-muted fw-normal fs-8">(Optional)</span>
                                    </label>
                                    <select class="form-select form-select-kiosk step5-sync" id="step5_employeeOfficeId">
                                        <option value="">Select office</option>
                                        <?php foreach ($offices_list as $off): ?>
                                            <option value="<?= htmlspecialchars($off['office_id']); ?>">
                                                <?= htmlspecialchars($off['office_code'] . ' - ' . $off['office_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- DYNAMIC TRANSACTION ADDITIONAL DETAILS (SHOWN WHEN SELECTED CONCERNS HAVE DETAIL FIELDS) -->
                    <div id="step5DynamicDetailsSection" class="summary-section-box d-none">
                        <div class="mb-3">
                            <h6 class="fw-bold text-primary mb-1">
                                <i class="bi bi-ui-checks-grid me-1"></i> Required Transaction Details
                            </h6>
                            <p class="text-muted small mb-0">Please provide the required additional information for your selected service(s).</p>
                        </div>
                        <div id="step5DynamicDetailsContainer" class="d-flex flex-column gap-3"></div>
                    </div>

                    <!-- DATA PRIVACY AGREEMENT CONTAINER -->
                    <div class="consent-card-box mb-4">
                        <input type="hidden" id="dataConsent" name="data_consent" value="0">
                        
                        <div class="d-flex flex-column align-items-center text-center">
                            <button type="button" class="btn btn-outline-warning w-100 py-3 rounded-3 fw-bold d-flex align-items-center justify-content-center gap-2 shadow-sm btn-open-privacy">
                                <i class="bi bi-file-earmark-check fs-5"></i>
                                <span class="consent-btn-label">Read & Sign Agreement Form</span>
                            </button>
                        </div>
                    </div>

                    <div class="d-flex action-btn-group gap-2 gap-sm-3 mt-4">
                        <button type="button" id="btnBackToStep4" class="btn btn-outline-secondary py-3 fw-bold rounded-3 px-4">
                            <i class="bi bi-arrow-left me-1"></i> Back to Concerns
                        </button>
                        <button type="submit" id="btnFinalSubmit" class="btn btn-success py-3 fw-bold rounded-3 flex-grow-1 shadow-sm" disabled>
                            <i class="bi bi-lock-fill me-1" id="submitBtnLockIcon"></i>
                            <span id="submitBtnText">Confirm & Issue Ticket</span>
                            <i class="bi bi-check-circle ms-1 fs-5"></i>
                        </button>
                    </div>

                </div>

            </form>

        </div>
    </div>

    <!-- ================================================================== -->
    <!-- IDENTITY VERIFICATION CONFIRMATION MODAL                           -->
    <!-- ================================================================== -->
    <div class="modal fade" id="idVerificationModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="idVerificationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content glass-modal-content border-0 rounded-4 overflow-hidden">
                <div class="glass-header-card p-4 text-center text-white position-relative">
                    <div class="badge bg-white bg-opacity-25 text-white backdrop-blur border border-white border-opacity-25 px-3 py-1.5 rounded-pill fw-bold text-uppercase mb-2 shadow-sm" style="letter-spacing: 0.5px;">
                        MATCH RECORD FOUND
                    </div>
                    
                    <div class="d-flex justify-content-center my-2 position-relative">
                        <img id="modalProfilePic" src="<?= BASE_URL; ?>public/upload/images/profile/profile-img.png" class="verify-profile-avatar" alt="Profile Picture" onerror="this.src='<?= BASE_URL; ?>public/upload/images/profile/profile-img.png';">
                    </div>

                    <h3 class="fw-bold text-white mb-1 mt-2" id="modalFullName" style="text-shadow: 0 2px 4px rgba(0,0,0,0.2);">---</h3>
                    <div class="text-white-50 fw-semibold fs-6 mb-1" id="modalIdNumber">---</div>
                    <p class="text-white small mb-0 fw-medium px-2" id="modalSubTitle">---</p>
                </div>
                
                <div class="modal-body p-4 text-center">
                    <h5 class="fw-bold text-dark mb-1">Is this you?</h5>
                    <p class="text-muted small mb-0">Please confirm your identity details above to proceed to campus building selection.</p>
                </div>

                <div class="modal-footer glass-footer-card p-3 d-flex align-items-center justify-content-between">
                    <button type="button" class="btn btn-outline-secondary px-3 py-2.5 rounded-3 fw-bold" id="btnRejectIdentity">
                        <i class="bi bi-x-circle me-1"></i> No, Not Me
                    </button>
                    <button type="button" class="btn btn-success px-4 py-2.5 rounded-3 fw-bold shadow-sm" id="btnConfirmIdentity">
                        <i class="bi bi-check-circle-fill me-1"></i> Yes, This Is Me
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================== -->
    <!-- NO RECORD FOUND MODAL                                              -->
    <!-- ================================================================== -->
    <div class="modal fade" id="idNotFoundModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 text-center p-3">
                <div class="modal-body p-4">
                    <div class="text-warning display-3 mb-2"><i class="bi bi-exclamation-triangle-fill"></i></div>
                    <h4 class="fw-bold text-dark mb-2">No Record Found</h4>
                    <p class="text-secondary small mb-4">Would you like to fill out your information or try entering your ID again?</p>

                    <div class="d-flex flex-column gap-2">
                        <button type="button" class="btn btn-primary py-3 rounded-3 fw-bold" id="btnManualEntryModal">
                            <i class="bi bi-pencil-square me-1"></i> Proceed to Manual Entry
                        </button>
                        <button type="button" class="btn btn-outline-secondary py-2.5 rounded-3 fw-bold" id="btnTryAgainModal">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Try Re-entering ID
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================== -->
    <!-- PRIVACY AGREEMENT MODAL WITH TOGGLEABLE CHECKBOX                   -->
    <!-- ================================================================== -->
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
                                    <p class="mb-0 text-muted">City College of Calamba collects personal data to manage campus queue flows, logbook verification, and official administrative service records.</p>
                                </div>
                                
                                <div class="mb-3">
                                    <h6 class="fw-bold text-dark fs-6 mb-1">2. Data Usage & Protection</h6>
                                    <p class="mb-0 text-muted">All recorded information is strictly stored within internal database systems in accordance with Republic Act No. 10173 (Data Privacy Act of 2012). Data will not be shared with unauthorized third parties.</p>
                                </div>

                                <div class="mb-3">
                                    <h6 class="fw-bold text-dark fs-6 mb-1">3. Authorization & Consent</h6>
                                    <p class="mb-0 text-muted">By checking the authorization consent inside this agreement form, you acknowledge that all information provided is accurate and grant explicit consent for processing queue tickets and campus records.</p>
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
                    <button type="button" class="btn btn-success rounded-3 px-4 fw-bold" id="btnAcceptPrivacyModal">
                        <i class="bi bi-check2-circle me-1"></i> Apply & Save Preference
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
                    <h3 class="fw-bold text-dark mb-2 fs-4">Check-in Complete!</h3>
                    <div class="receipt-reminder-highlight mb-3 shadow-sm">
                        <i class="bi bi-exclamation-circle-fill text-warning fs-5 flex-shrink-0"></i>
                        <span>Please keep a record of your queue ticket receipt details.</span>
                    </div>

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
                                <div id="modalTransactionContainer" class="d-flex flex-column gap-1.5"></div>
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
        const visitorTypeBtns      = document.querySelectorAll('.visitor-type-btn');
        const visitorTypeInput     = document.getElementById('visitorType');
        
        const idLookupSection      = document.getElementById('idLookupSection');
        const referenceIdLabel     = document.getElementById('referenceIdLabel');
        const referenceIdInput     = document.getElementById('referenceId');
        const btnVerifyId          = document.getElementById('btnVerifyId');
        const idHelpBlock          = document.getElementById('idHelpBlock');
        const personalDetailsSec   = document.getElementById('personalDetailsSection');
        
        const userIdInput          = document.getElementById('userId');
        const departmentIdInput    = document.getElementById('departmentId');
        const programCodeInput     = document.getElementById('programCode');
        const programMajorInput    = document.getElementById('programMajor');
        
        // STEP 1 INPUTS
        const firstNameInput       = document.getElementById('firstName');
        const middleNameInput      = document.getElementById('middleName');
        const lastNameInput        = document.getElementById('lastName');
        const suffixSelect         = document.getElementById('suffix');
        const postNominalContainer = document.getElementById('postNominalContainer');
        const postNominalInput     = document.getElementById('postNominal');
        const sexSelect            = document.getElementById('sex');
        const emailInput           = document.getElementById('email');
        
        const studentFieldsCont    = document.getElementById('studentFieldsContainer');
        const courseSelect         = document.getElementById('courseProgram');
        const yearSelect           = document.getElementById('yearLevel');

        const employeeFieldsCont   = document.getElementById('employeeFieldsContainer');
        const employeeDeptSelect   = document.getElementById('employeeDepartment');
        const employeeOfficeSelect = document.getElementById('employeeOfficeId');

        // STEP 5 INPUTS (PERSONAL DETAILS REVIEW)
        const step5FirstName       = document.getElementById('step5_firstName');
        const step5MiddleName      = document.getElementById('step5_middleName');
        const step5LastName        = document.getElementById('step5_lastName');
        const step5Suffix          = document.getElementById('step5_suffix');
        const step5PostNomCont     = document.getElementById('step5_postNominalContainer');
        const step5PostNominal     = document.getElementById('step5_postNominal');
        const step5Sex             = document.getElementById('step5_sex');
        const step5Email           = document.getElementById('step5_email');
        const step5StudentCont     = document.getElementById('step5_studentFieldsContainer');
        const step5Course          = document.getElementById('step5_courseProgram');
        const step5Year            = document.getElementById('step5_yearLevel');
        const step5EmployeeCont    = document.getElementById('step5_employeeFieldsContainer');
        const step5EmpDept         = document.getElementById('step5_employeeDepartment');
        const step5EmpOffice       = document.getElementById('step5_employeeOfficeId');

        // STEP 5 DYNAMIC DETAILS ELEMENTS
        const step5DynamicDetailsSec  = document.getElementById('step5DynamicDetailsSection');
        const step5DynamicDetailsCont = document.getElementById('step5DynamicDetailsContainer');

        // IDENTITY VERIFICATION MODAL ELEMENTS
        const idVerificationModalEl = document.getElementById('idVerificationModal');
        const idVerificationModal   = idVerificationModalEl ? new bootstrap.Modal(idVerificationModalEl) : null;
        const modalProfilePic       = document.getElementById('modalProfilePic');
        const modalFullName         = document.getElementById('modalFullName');
        const modalIdNumber         = document.getElementById('modalIdNumber');
        const modalSubTitle         = document.getElementById('modalSubTitle');
        const btnConfirmIdentity    = document.getElementById('btnConfirmIdentity');
        const btnRejectIdentity     = document.getElementById('btnRejectIdentity');

        // NO RECORD FOUND MODAL ELEMENTS
        const idNotFoundModalEl     = document.getElementById('idNotFoundModal');
        const idNotFoundModal       = idNotFoundModalEl ? new bootstrap.Modal(idNotFoundModalEl) : null;
        const btnManualEntryModal   = document.getElementById('btnManualEntryModal');
        const btnTryAgainModal      = document.getElementById('btnTryAgainModal');

        // PRIVACY AGREEMENT MODAL & STATE
        const dataConsentInput     = document.getElementById('dataConsent');
        const privacyOpenBtns      = document.querySelectorAll('.btn-open-privacy');
        const privacyModalEl       = document.getElementById('privacyAgreementModal');
        const modalConsentCheckbox = document.getElementById('modalConsentCheckbox');
        const btnAcceptPrivacy     = document.getElementById('btnAcceptPrivacyModal');
        const submitBtnLockIcon    = document.getElementById('submitBtnLockIcon');
        const btnFinalSubmit       = document.getElementById('btnFinalSubmit');

        const prioritySwitch       = document.getElementById('isPrioritySwitch');
        const prioritySection      = document.getElementById('priorityOptionsSection');

        // WIZARD STEPS DIVS
        const step1Div             = document.getElementById('step1_visitorInfo');
        const step2Div             = document.getElementById('step2_buildingSelection');
        const step3Div             = document.getElementById('step3_officeSelection');
        const step4Div             = document.getElementById('step4_purposeSelection');
        const step5Div             = document.getElementById('step5_summaryReview');

        // WIZARD PROGRESS ITEMS
        const wizardStep1          = document.getElementById('wizardStep1');
        const wizardStep2          = document.getElementById('wizardStep2');
        const wizardStep3          = document.getElementById('wizardStep3');
        const wizardStep4          = document.getElementById('wizardStep4');
        const wizardStep5          = document.getElementById('wizardStep5');

        // NAVIGATION BUTTONS
        const btnContinue          = document.getElementById('btnContinueStep2');
        const btnBackTo1           = document.getElementById('btnBackToStep1');
        const btnBackTo2           = document.getElementById('btnBackToStep2');
        const btnBackTo3           = document.getElementById('btnBackToStep3');
        const btnContinueTo5       = document.getElementById('btnContinueToStep5');
        const btnBackTo4           = document.getElementById('btnBackToStep4');

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

        let selectedConcernIds     = [];
        let selectedSubConcernsMap = {};
        let officeTransactionsMap  = {};
        let pendingVisitorData     = null;
        let visitorRecordFound     = false;

        function escapeHtml(str) {
            return String(str ?? '')
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        function showSweetAlert(title, text, icon = 'warning') {
            const config = {
                title: title,
                text: text,
                icon: icon,
                type: icon,
                buttons: {
                    confirm: {
                        className: icon === 'error' ? 'btn btn-danger' : (icon === 'warning' ? 'btn btn-warning' : 'btn btn-primary')
                    }
                }
            };

            if (typeof swal === 'function') {
                if (typeof swal.fire === 'function') { swal.fire(config); } 
                else { try { swal(config); } catch (e) { new swal(config); } }
            } else if (typeof SweetAlert === 'function') {
                try { new SweetAlert(config); } catch (e) { alert(title + "\n\n" + text); }
            } else {
                alert(title + "\n\n" + text);
            }
        }

        function clearVisitorPersonalInputs() {
            userIdInput.value       = '';
            departmentIdInput.value = '0';
            programCodeInput.value  = '';
            programMajorInput.value = '';

            firstNameInput.value    = '';
            middleNameInput.value   = '';
            lastNameInput.value     = '';
            suffixSelect.value      = '';
            postNominalInput.value  = '';
            sexSelect.value         = '';
            emailInput.value        = '';

            if (courseSelect) courseSelect.value = '';
            if (yearSelect) yearSelect.value = '';
            if (employeeDeptSelect) employeeDeptSelect.value = '';
            if (employeeOfficeSelect) employeeOfficeSelect.value = '';

            if (prioritySwitch) {
                prioritySwitch.checked = false;
                prioritySection?.classList.add('d-none');
            }

            syncStep1ToStep5Fields();
        }

        function updateCategoryRequiredAttributes(type) {
            const isStudent  = type === 'STUDENT';
            const isEmployee = type === 'EMPLOYEE';

            if (courseSelect) courseSelect.required = isStudent;
            if (yearSelect) yearSelect.required = isStudent;
            if (step5Course) step5Course.required = isStudent;
            if (step5Year) step5Year.required = isStudent;

            if (employeeDeptSelect) employeeDeptSelect.required = isEmployee;
            if (step5EmpDept) step5EmpDept.required = isEmployee;
        }

        function syncStudentProgramMetadata() {
            if (!courseSelect) return;
            const selectedOpt = courseSelect.options[courseSelect.selectedIndex];
            if (selectedOpt && selectedOpt.value) {
                departmentIdInput.value = selectedOpt.dataset.departmentId || '0';
                programCodeInput.value  = selectedOpt.dataset.programCode || '';
                programMajorInput.value = selectedOpt.dataset.major || '';
            } else {
                departmentIdInput.value = '0';
                programCodeInput.value  = '';
                programMajorInput.value = '';
            }
        }

        function updateNameGridColumns(isEmployee) {
            document.querySelectorAll('.name-col-secondary').forEach(col => {
                col.classList.toggle('col-md-2', isEmployee);
                col.classList.toggle('col-md-3', !isEmployee);
            });

            ['suffixContainer', 'step5_suffixContainer'].forEach(id => {
                const el = document.getElementById(id);
                if (!el) return;
                el.classList.toggle('col-6', isEmployee);
                el.classList.toggle('col-12', !isEmployee);
            });
        }

        visitorTypeBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                visitorTypeBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                const type = this.dataset.type;
                visitorTypeInput.value = type;
                switchVisitorTypeUI(type);
            });
        });

        function switchVisitorTypeUI(type) {
            referenceIdInput.value = '';
            pendingVisitorData = null;
            visitorRecordFound = false;
            furthestWizardStep = 1;
            clearVisitorPersonalInputs();
            updateCategoryRequiredAttributes(type);
            updateConsentState(false);
            setWizardStep(1);
            
            if (type === 'STUDENT') {
                updateNameGridColumns(false);
                idLookupSection.classList.remove('d-none');
                personalDetailsSec.classList.add('d-none');
                referenceIdLabel.textContent = "Student ID Number";
                referenceIdInput.placeholder = "e.g., 2000-1234";
                idHelpBlock.textContent = "Enter your ID number and click Verify.";
                
                studentFieldsCont.classList.remove('d-none');
                employeeFieldsCont.classList.add('d-none');
                postNominalContainer.classList.add('d-none');

                step5StudentCont.classList.remove('d-none');
                step5EmployeeCont.classList.add('d-none');
                step5PostNomCont.classList.add('d-none');
            } else if (type === 'EMPLOYEE') {
                updateNameGridColumns(true);
                idLookupSection.classList.remove('d-none');
                personalDetailsSec.classList.add('d-none');
                referenceIdLabel.textContent = "Employee ID Number";
                referenceIdInput.placeholder = "e.g., ABC-12345";
                idHelpBlock.textContent = "Enter your ID number and click Verify.";
                
                studentFieldsCont.classList.add('d-none');
                employeeFieldsCont.classList.remove('d-none');
                postNominalContainer.classList.remove('d-none');

                step5StudentCont.classList.add('d-none');
                step5EmployeeCont.classList.remove('d-none');
                step5PostNomCont.classList.remove('d-none');
            } else { // GUEST
                updateNameGridColumns(false);
                idLookupSection.classList.add('d-none');
                personalDetailsSec.classList.remove('d-none');
                
                studentFieldsCont.classList.add('d-none');
                employeeFieldsCont.classList.add('d-none');
                postNominalContainer.classList.add('d-none');

                step5StudentCont.classList.add('d-none');
                step5EmployeeCont.classList.add('d-none');
                step5PostNomCont.classList.add('d-none');
            }
        }

        btnVerifyId.addEventListener('click', executeVerification);
        referenceIdInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                executeVerification();
            }
        });

        function executeVerification() {
            const refId = referenceIdInput.value.trim();
            const currentType = visitorTypeInput.value;

            if (!refId) {
                showSweetAlert("Missing ID Number", "Please enter an ID number before verifying.", "warning");
                referenceIdInput.focus();
                return;
            }

            referenceIdInput.value = refId;
            btnVerifyId.disabled = true;
            btnVerifyId.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Verifying...';
            
            fetchVisitorInfo(currentType, refId);
        }

        function formatFormattedName(info) {
            let fname = (info.first_name || '').trim();
            let mname = (info.middle_name || '').trim();
            let minit = (info.middle_initial || '').trim();
            let lname = (info.last_name || '').trim();
            let sfix  = (info.suffix || '').trim();

            let middleFormatted = '';
            if (minit !== '') {
                middleFormatted = minit.toUpperCase() + (minit.endsWith('.') ? '' : '.');
            } else if (mname !== '') {
                middleFormatted = mname.charAt(0).toUpperCase() + '.';
            }

            let parts = [fname, middleFormatted, lname, sfix].filter(p => p !== '');
            return parts.join(' ');
        }

        function normalizeSexValue(val) {
            if (!val) return '';
            const lower = val.toString().trim().toLowerCase();
            if (lower === 'male') return 'Male';
            if (lower === 'female') return 'Female';
            return val;
        }

        function fetchVisitorInfo(type, refId) {
            const formData = new FormData();
            const action = (type === 'STUDENT') ? 'fetch_student_info' : 'fetch_employee_info';
            const paramKey = (type === 'STUDENT') ? 'student_id' : 'employee_id';
            
            formData.append('action', action);
            formData.append(paramKey, refId);

            fetch('<?= BASE_URL; ?>admin/ajax/logbook', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(result => {
                btnVerifyId.disabled = false;
                btnVerifyId.innerHTML = '<i class="bi bi-search me-1"></i> Verify';

                if (result.success && result.data) {
                    const info = result.data;
                    pendingVisitorData = info;

                    let defaultPic = '<?= BASE_URL; ?>public/upload/images/profile/profile-img.png';
                    let picPath = defaultPic;
                    if (info.profile_pic && info.profile_pic !== 'default-profile.jpg' && info.profile_pic !== 'profile-img.png') {
                        picPath = '<?= BASE_URL; ?>public/upload/images/profile/' + info.profile_pic;
                    }

                    modalProfilePic.src = picPath;
                    modalFullName.textContent = formatFormattedName(info);
                    
                    if (type === 'STUDENT') {
                        modalIdNumber.textContent = info.student_id || refId;
                        let programName = info.program_name || '';
                        if (!programName && info.program_id && courseSelect) {
                            const opt = courseSelect.querySelector(`option[value="${info.program_id}"]`);
                            if (opt) programName = opt.dataset.programName || opt.textContent.trim();
                        }
                        modalSubTitle.textContent = programName || info.program_code || 'Student';
                    } else {
                        modalIdNumber.textContent = info.employee_id || refId;
                        modalSubTitle.textContent = info.position || 'Employee Personnel';
                    }

                    if (idVerificationModal) idVerificationModal.show();
                } else {
                    pendingVisitorData = null;
                    if (idNotFoundModal) idNotFoundModal.show();
                }
            })
            .catch(err => {
                btnVerifyId.disabled = false;
                btnVerifyId.innerHTML = '<i class="bi bi-search me-1"></i> Verify';
                showSweetAlert("Lookup Error", "Verification request failed. Please try again.", "error");
            });
        }

        btnConfirmIdentity?.addEventListener('click', function() {
            if (!pendingVisitorData) return;
            const info = pendingVisitorData;
            const type = visitorTypeInput.value;

            clearVisitorPersonalInputs();

            userIdInput.value       = info.user_id || '';
            firstNameInput.value    = info.first_name || '';
            middleNameInput.value   = info.middle_name || '';
            lastNameInput.value     = info.last_name || '';
            suffixSelect.value      = info.suffix || '';
            sexSelect.value         = normalizeSexValue(info.sex);
            emailInput.value        = info.email || '';

            if (type === 'STUDENT') {
                if (info.student_id) referenceIdInput.value = info.student_id.trim();
                if (info.program_id) courseSelect.value = info.program_id;
                syncStudentProgramMetadata();
                if (info.department_id) departmentIdInput.value = info.department_id;
                if (info.program_code) programCodeInput.value   = info.program_code;
                if (info.major) programMajorInput.value         = info.major;
                if (info.year_level) yearSelect.value = info.year_level.toString().charAt(0);
            } else if (type === 'EMPLOYEE') {
                if (info.employee_id) referenceIdInput.value = info.employee_id.trim();
                postNominalInput.value = info.post_nominal || '';
                if (info.department_id && info.department_id > 0) {
                    employeeDeptSelect.value = info.department_id;
                    departmentIdInput.value  = info.department_id;
                }
                if (info.office_id && employeeOfficeSelect) employeeOfficeSelect.value = info.office_id;
            }

            visitorRecordFound = true;
            updateConsentState(false);
            syncStep1ToStep5Fields();

            if (idVerificationModal) idVerificationModal.hide();

            step1Div.classList.add('d-none');
            step2Div.classList.remove('d-none');
            setWizardStep(2);
        });

        btnRejectIdentity?.addEventListener('click', function() {
            pendingVisitorData = null;
            visitorRecordFound = false;
            referenceIdInput.value = '';
            furthestWizardStep = 1;
            clearVisitorPersonalInputs();

            idLookupSection.classList.remove('d-none');
            personalDetailsSec.classList.add('d-none');

            if (idVerificationModal) idVerificationModal.hide();
            referenceIdInput.focus();
        });

        btnManualEntryModal?.addEventListener('click', function() {
            if (idNotFoundModal) idNotFoundModal.hide();

            pendingVisitorData = null;
            visitorRecordFound = false;
            furthestWizardStep = 1;
            clearVisitorPersonalInputs();
            updateConsentState(false);

            idLookupSection.classList.add('d-none');
            personalDetailsSec.classList.remove('d-none');

            firstNameInput.focus();
        });

        btnTryAgainModal?.addEventListener('click', function() {
            if (idNotFoundModal) idNotFoundModal.hide();
            referenceIdInput.value = '';
            referenceIdInput.focus();
        });

        // PRIVACY AGREEMENT MODAL LOGIC
        const privacyModalInstance = privacyModalEl ? new bootstrap.Modal(privacyModalEl) : null;

        if (privacyModalInstance) {
            privacyOpenBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    modalConsentCheckbox.checked = (dataConsentInput.value === "1");
                    privacyModalInstance.show();
                });
            });
        }

        if (btnAcceptPrivacy) {
            btnAcceptPrivacy.addEventListener('click', function() {
                const isChecked = modalConsentCheckbox.checked;
                updateConsentState(isChecked);
                if (privacyModalInstance) privacyModalInstance.hide();
            });
        }

        function updateConsentState(isApproved) {
            dataConsentInput.value = isApproved ? "1" : "0";

            privacyOpenBtns.forEach(btn => {
                if (isApproved) {
                    btn.className = 'btn btn-success w-100 py-3 rounded-3 fw-bold d-flex align-items-center justify-content-center gap-2 shadow-sm btn-open-privacy';
                    btn.innerHTML = '<i class="bi bi-check-circle-fill fs-5"></i><span class="consent-btn-label">Agreement Signed & Accepted</span>';
                } else {
                    btn.className = 'btn btn-outline-warning w-100 py-3 rounded-3 fw-bold d-flex align-items-center justify-content-center gap-2 shadow-sm btn-open-privacy';
                    btn.innerHTML = '<i class="bi bi-file-earmark-check fs-5"></i><span class="consent-btn-label">Read & Sign Agreement Form</span>';
                }
            });

            btnFinalSubmit.disabled = !isApproved;
            if (submitBtnLockIcon) submitBtnLockIcon.className = isApproved ? 'bi bi-check-circle-fill me-1' : 'bi bi-lock-fill me-1';
        }

        if (prioritySwitch && prioritySection) {
            prioritySwitch.addEventListener('change', function() {
                prioritySection.classList.toggle('d-none', !this.checked);
            });
        }

        employeeDeptSelect.addEventListener('change', function() {
            departmentIdInput.value = this.value || '0';
        });

        courseSelect.addEventListener('change', function() {
            syncStudentProgramMetadata();
        });

        let currentWizardStep = 1;
        let furthestWizardStep = 1;

        const wizardStepContainers = {
            1: step1Div,
            2: step2Div,
            3: step3Div,
            4: step4Div,
            5: step5Div
        };

        const wizardStepItems = {
            1: wizardStep1,
            2: wizardStep2,
            3: wizardStep3,
            4: wizardStep4,
            5: wizardStep5
        };

        function setWizardStep(stepNum) {
            const maxAllowedStep = 5;
            stepNum = Math.min(stepNum, maxAllowedStep);
            currentWizardStep = stepNum;
            furthestWizardStep = Math.min(Math.max(furthestWizardStep, stepNum), maxAllowedStep);

            // Enforce Step 1 view state whenever Step 1 is active
            if (stepNum === 1 && visitorRecordFound) {
                idLookupSection.classList.remove('d-none');
                personalDetailsSec.classList.add('d-none');
            }

            Object.entries(wizardStepItems).forEach(([number, element]) => {
                const step = Number(number);
                const canNavigate = step !== stepNum && step <= furthestWizardStep;

                element.classList.remove('active', 'completed');

                if (step === stepNum) {
                    element.classList.add('active');
                } else if (step <= furthestWizardStep) {
                    element.classList.add('completed');
                }

                element.setAttribute('aria-disabled', String(!canNavigate));
                element.tabIndex = canNavigate ? 0 : -1;
            });
        }

        updateCategoryRequiredAttributes('STUDENT');
        setWizardStep(1);

        function navigateToVisitedStep(stepNum) {
            if (!wizardStepContainers[stepNum] ||
                stepNum > 5 ||
                stepNum === currentWizardStep ||
                stepNum > furthestWizardStep) {
                return;
            }

            Object.values(wizardStepContainers).forEach(section => {
                section.classList.add('d-none');
            });

            wizardStepContainers[stepNum].classList.remove('d-none');

            if (stepNum === 5) {
                syncStep1ToStep5Fields();
                renderStep5DynamicDetails();
            }

            setWizardStep(stepNum);

            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }

        Object.entries(wizardStepItems).forEach(([number, element]) => {
            const stepNum = Number(number);

            element.addEventListener('click', function() {
                navigateToVisitedStep(stepNum);
            });

            element.addEventListener('keydown', function(event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    navigateToVisitedStep(stepNum);
                }
            });
        });

        // BI-DIRECTIONAL SYNC BETWEEN STEP 1 AND STEP 5 PERSONAL FIELDS
        function syncStep1ToStep5Fields() {
            step5FirstName.value   = firstNameInput.value;
            step5MiddleName.value  = middleNameInput.value;
            step5LastName.value    = lastNameInput.value;
            step5Suffix.value      = suffixSelect.value;
            step5PostNominal.value = postNominalInput.value;
            step5Sex.value         = sexSelect.value;
            step5Email.value       = emailInput.value;
            step5Course.value      = courseSelect.value;
            step5Year.value        = yearSelect.value;
            step5EmpDept.value     = employeeDeptSelect.value;
            step5EmpOffice.value   = employeeOfficeSelect.value;
        }

        function syncStep5ToStep1Fields() {
            firstNameInput.value       = step5FirstName.value;
            middleNameInput.value      = step5MiddleName.value;
            lastNameInput.value        = step5LastName.value;
            suffixSelect.value         = step5Suffix.value;
            postNominalInput.value     = step5PostNominal.value;
            sexSelect.value            = step5Sex.value;
            emailInput.value           = step5Email.value;
            courseSelect.value         = step5Course.value;
            yearSelect.value           = step5Year.value;
            employeeDeptSelect.value   = step5EmpDept.value;
            employeeOfficeSelect.value = step5EmpOffice.value;

            if (visitorTypeInput.value === 'STUDENT') {
                syncStudentProgramMetadata();
            } else if (visitorTypeInput.value === 'EMPLOYEE') {
                departmentIdInput.value = step5EmpDept.value || '0';
            } else {
                departmentIdInput.value = '0';
            }
        }

        document.querySelectorAll('.step1-sync').forEach(el => {
            el.addEventListener('input', syncStep1ToStep5Fields);
            el.addEventListener('change', syncStep1ToStep5Fields);
        });

        document.querySelectorAll('.step5-sync').forEach(el => {
            el.addEventListener('input', syncStep5ToStep1Fields);
            el.addEventListener('change', syncStep5ToStep1Fields);
        });

        function validatePersonalInfoFields(sourceStep) {
            const vType = visitorTypeInput.value;
            const fName = (sourceStep === 5 ? step5FirstName : firstNameInput).value.trim();
            const lName = (sourceStep === 5 ? step5LastName : lastNameInput).value.trim();
            const sexV  = (sourceStep === 5 ? step5Sex : sexSelect).value;
            const mailV = (sourceStep === 5 ? step5Email : emailInput).value.trim();

            if (!fName || !lName || !sexV || !mailV) {
                showSweetAlert("Incomplete Information", "Please complete all required personal fields (First Name, Last Name, Sex, and Email Address).", "warning");
                return false;
            }

            if (vType === 'STUDENT') {
                const progV = (sourceStep === 5 ? step5Course : courseSelect).value;
                const yearV = (sourceStep === 5 ? step5Year : yearSelect).value;
                if (!progV || !yearV) {
                    showSweetAlert("Incomplete Student Information", "Please select your Course / Program and Year Level.", "warning");
                    return false;
                }
            } else if (vType === 'EMPLOYEE') {
                const deptV = (sourceStep === 5 ? step5EmpDept : employeeDeptSelect).value;
                if (!deptV) {
                    showSweetAlert("Incomplete Employee Information", "Please select your Department.", "warning");
                    return false;
                }
            }

            return true;
        }

        // STEP 1 CONTINUATION (Manual Entry & Guest)
        if (btnContinue) {
            btnContinue.addEventListener('click', function() {
                if (!validatePersonalInfoFields(1)) {
                    return;
                }
                if (visitorTypeInput.value === 'STUDENT') {
                    syncStudentProgramMetadata();
                } else if (visitorTypeInput.value === 'EMPLOYEE') {
                    departmentIdInput.value = employeeDeptSelect.value || '0';
                }
                syncStep1ToStep5Fields();
                step1Div.classList.add('d-none');
                step2Div.classList.remove('d-none');
                setWizardStep(2);
            });
        }

        btnBackTo1.addEventListener('click', function() {
            step2Div.classList.add('d-none');
            step1Div.classList.remove('d-none');
            setWizardStep(1);
        });

        // STEP 2 BUILDING SELECTION
        buildingCards.forEach(card => {
            card.addEventListener('click', function() {
                const facId = this.dataset.facilityId;
                buildingCards.forEach(c => c.classList.remove('active'));
                this.classList.add('active');

                facilityInput.value = facId;
                summaryBuildingName.textContent = this.dataset.facilityName;
                summaryBuildingCode.textContent = this.dataset.facilityCode;

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

        // STEP 3 OFFICE SELECTION
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

        // STEP 4 -> STEP 5 (LOGBOOK SUMMARY, ADDITIONAL DETAILS & AGREEMENT)
        btnContinueTo5.addEventListener('click', function() {
            if (!transactionInput.value && !otherConcernText.value.trim()) {
                showSweetAlert("Transaction Concern Required", "Please select at least one transaction concern or specify your concern under Others.", "warning");
                return;
            }

            // Ensure every selected primary transaction with sub-transactions has at least one sub-transaction selected
            for (const tid of selectedConcernIds) {
                if (tid === 'OTHERS') continue;
                const transObj = officeTransactionsMap[tid];
                if (transObj && Array.isArray(transObj.sub_concerns) && transObj.sub_concerns.length > 0) {
                    const pickedSubs = selectedSubConcernsMap[tid] || [];
                    if (pickedSubs.length === 0) {
                        showSweetAlert(
                            "Sub-Transaction Required",
                            `Please select at least one specific sub-transaction under "${transObj.transaction_name}".`,
                            "warning"
                        );
                        return;
                    }
                }
            }

            if (selectedConcernIds.includes('OTHERS') && !otherConcernText.value.trim()) {
                showSweetAlert("Specify Concern", "Please describe your specific concern under Others.", "warning");
                otherConcernText.focus();
                return;
            }

            syncStep1ToStep5Fields();
            renderStep5DynamicDetails();

            step4Div.classList.add('d-none');
            step5Div.classList.remove('d-none');
            setWizardStep(5);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        btnBackTo4.addEventListener('click', function() {
            step5Div.classList.add('d-none');
            step4Div.classList.remove('d-none');
            setWizardStep(4);
        });

        function fetchOfficeTransactions(officeId) {
            transactionContainer.innerHTML = '<div class="text-center p-3 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Loading concerns...</div>';
            transactionInput.value = '';
            subConcernInput.value = '';
            selectedConcernIds = [];
            selectedSubConcernsMap = {};
            officeTransactionsMap = {};
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
                    direct_details: null,
                    sub_concerns: []
                });

                dataList.forEach(trans => {
                    officeTransactionsMap[trans.id] = trans;
                    const hasSubConcerns = Array.isArray(trans.sub_concerns) && trans.sub_concerns.length > 0;

                    const card = document.createElement('div');
                    card.className = 'transaction-card p-3 mb-2';
                    card.dataset.transId = trans.id;
                    card.dataset.estMins  = trans.estimated_minutes || 5;

                    let subConcernsHtml = '';
                    if (hasSubConcerns) {
                        subConcernsHtml = `
                            <div class="sub-concerns-container d-none mt-3 pt-3 border-top">
                                <label class="form-label text-muted fw-bold small d-block mb-2">Select Specific Sub-Transaction(s):</label>
                                <div class="d-flex flex-wrap gap-2">
                                    ${trans.sub_concerns.map(sub => `
                                        <button type="button"
                                                class="btn btn-sm btn-outline-primary sub-concern-btn px-3 py-2"
                                                data-sub-id="${sub.id}"
                                                data-sub-est="${sub.estimated_minutes || 5}">
                                            <span>${escapeHtml(sub.sub_concern_name)}</span>
                                            <span class="sub-est-badge">Est. ${sub.estimated_minutes || 5} mins</span>
                                        </button>
                                    `).join('')}
                                </div>
                            </div>
                        `;
                    }

                    // Show Est. Time on primary transaction ONLY when it has NO sub-transactions
                    const primaryEstBadgeHtml = !hasSubConcerns
                        ? `<span class="badge bg-light text-secondary border mt-1 small">Est. Time: ${trans.estimated_minutes || 5} mins</span>`
                        : `<span class="badge bg-primary-subtle text-primary border border-primary-subtle mt-1 small">${trans.sub_concerns.length} Sub-Transaction Option(s)</span>`;

                    card.innerHTML = `
                        <div class="card-header-row d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-3 min-w-0 flex-grow-1">
                                <i class="bi bi-square text-muted fs-4 select-icon flex-shrink-0"></i>
                                <div class="min-w-0 flex-grow-1">
                                    <h6 class="fw-bold text-dark mb-0 fs-6">${escapeHtml(trans.transaction_name)}</h6>
                                    ${primaryEstBadgeHtml}
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
                            if (subContainer) {
                                subContainer.classList.add('d-none');
                                subContainer.querySelectorAll('.sub-concern-btn').forEach(b => {
                                    b.classList.remove('btn-primary', 'text-white');
                                    b.classList.add('btn-outline-primary');
                                });
                            }
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
                            const subId = parseInt(this.dataset.subId, 10);
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

        // RENDER DYNAMIC ADDITIONAL DETAILS IN STEP 5
        function buildDynamicFieldControlHtml(scopeKey, field) {
            const inputName = `transaction_details[${scopeKey}][${field.id}][value]`;
            const fieldIdAttr = `detail_${scopeKey}_${field.id}`;

            if (field.mode === 'SELECT') {
                const optionsHtml = (field.choices || []).map(choice => `
                    <option value="${escapeHtml(choice.value)}" data-choice-id="${choice.id}">
                        ${escapeHtml(choice.value)}
                    </option>
                `).join('');

                return `
                    <input type="hidden" name="transaction_details[${scopeKey}][${field.id}][choice_id]" class="detail-choice-id-hidden" value="">
                    <select class="form-select form-select-kiosk dynamic-detail-input dynamic-detail-select"
                            id="${fieldIdAttr}"
                            name="${inputName}"
                            data-field-title="${escapeHtml(field.title)}"
                            data-scope-key="${escapeHtml(scopeKey)}"
                            required>
                        <option value="" selected disabled>Select ${escapeHtml(field.title)}</option>
                        ${optionsHtml}
                    </select>
                `;
            }

            if (field.format === 'INT') {
                return `
                    <input type="number"
                           step="1"
                           inputmode="numeric"
                           class="form-control form-control-kiosk dynamic-detail-input"
                           id="${fieldIdAttr}"
                           name="${inputName}"
                           data-field-title="${escapeHtml(field.title)}"
                           data-scope-key="${escapeHtml(scopeKey)}"
                           data-format="INT"
                           placeholder="Enter ${escapeHtml(field.title)} (Numbers only)"
                           required>
                `;
            }

            const placeholderHint = field.format === 'CHAR'
                ? `Enter ${field.title} (Text / Characters)`
                : `Enter ${field.title}`;

            return `
                <input type="text"
                       class="form-control form-control-kiosk dynamic-detail-input"
                       id="${fieldIdAttr}"
                       name="${inputName}"
                       data-field-title="${escapeHtml(field.title)}"
                       data-scope-key="${escapeHtml(scopeKey)}"
                       data-format="${escapeHtml(field.format || 'VAR')}"
                       placeholder="${escapeHtml(placeholderHint)}"
                       required>
            `;
        }

        function renderStep5DynamicDetails() {
            // Preserve previously entered values if user goes back and forth
            const existingValues = {};
            step5DynamicDetailsCont.querySelectorAll('.dynamic-detail-input').forEach(inp => {
                existingValues[inp.name] = inp.value;
            });

            step5DynamicDetailsCont.innerHTML = '';
            let totalGroupsRendered = 0;

            selectedConcernIds.forEach(tid => {
                if (tid === 'OTHERS') return;
                const transObj = officeTransactionsMap[tid];
                if (!transObj) return;

                const hasSubs = Array.isArray(transObj.sub_concerns) && transObj.sub_concerns.length > 0;

                if (!hasSubs) {
                    const dd = transObj.direct_details;
                    if (dd && dd.has_details && Array.isArray(dd.fields) && dd.fields.length > 0) {
                        totalGroupsRendered++;
                        const scopeKey = `type_${tid}`;
                        const groupEl = document.createElement('div');
                        groupEl.className = 'detail-group-card';
                        groupEl.dataset.scopeKey = scopeKey;
                        groupEl.innerHTML = `
                            <div class="fw-bold text-dark mb-2">
                                <i class="bi bi-card-checklist text-primary me-1"></i>${escapeHtml(transObj.transaction_name)}
                            </div>
                            <div class="row g-3">
                                ${dd.fields.map(field => `
                                    <div class="col-12 col-md-6">
                                        <label for="detail_${scopeKey}_${field.id}" class="form-label fw-bold text-dark small">
                                            ${escapeHtml(field.title)}
                                        </label>
                                        ${buildDynamicFieldControlHtml(scopeKey, field)}
                                    </div>
                                `).join('')}
                            </div>
                        `;
                        step5DynamicDetailsCont.appendChild(groupEl);
                    }
                } else {
                    const pickedSubIds = selectedSubConcernsMap[tid] || [];
                    pickedSubIds.forEach(sid => {
                        const subObj = transObj.sub_concerns.find(s => Number(s.id) === Number(sid));
                        if (!subObj) return;
                        const sd = subObj.parsed_details;
                        if (sd && sd.has_details && Array.isArray(sd.fields) && sd.fields.length > 0) {
                            totalGroupsRendered++;
                            const scopeKey = `sub_${sid}`;
                            const groupEl = document.createElement('div');
                            groupEl.className = 'detail-group-card';
                            groupEl.dataset.scopeKey = scopeKey;
                            groupEl.innerHTML = `
                                <div class="fw-bold text-dark mb-2">
                                    <i class="bi bi-card-checklist text-primary me-1"></i>${escapeHtml(transObj.transaction_name)}
                                    <span class="badge bg-primary-subtle text-primary border ms-1">${escapeHtml(subObj.sub_concern_name)}</span>
                                </div>
                                <div class="row g-3">
                                    ${sd.fields.map(field => `
                                        <div class="col-12 col-md-6">
                                            <label for="detail_${scopeKey}_${field.id}" class="form-label fw-bold text-dark small">
                                                ${escapeHtml(field.title)}
                                            </label>
                                            ${buildDynamicFieldControlHtml(scopeKey, field)}
                                        </div>
                                    `).join('')}
                                </div>
                            `;
                            step5DynamicDetailsCont.appendChild(groupEl);
                        }
                    });
                }
            });

            // Restore values & wire choice_id hidden sync for SELECT fields
            step5DynamicDetailsCont.querySelectorAll('.dynamic-detail-input').forEach(inp => {
                if (existingValues[inp.name] !== undefined && existingValues[inp.name] !== '') {
                    inp.value = existingValues[inp.name];
                }
                if (inp.classList.contains('dynamic-detail-select')) {
                    const syncChoiceId = () => {
                        const opt = inp.options[inp.selectedIndex];
                        const hiddenChoice = inp.parentElement.querySelector('.detail-choice-id-hidden');
                        if (hiddenChoice) {
                            hiddenChoice.value = opt ? (opt.dataset.choiceId || '') : '';
                        }
                    };
                    syncChoiceId();
                    inp.addEventListener('change', syncChoiceId);
                }
            });

            step5DynamicDetailsSec.classList.toggle('d-none', totalGroupsRendered === 0);
        }

        function submitLogbookForm(triggerBtn) {
            syncStep5ToStep1Fields();

            if (referenceIdInput.value) {
                referenceIdInput.value = referenceIdInput.value.trim();
            }

            if (!validatePersonalInfoFields(5)) {
                return;
            }

            // Validate required dynamic transaction detail fields in Step 5
            const dynamicInputs = step5DynamicDetailsCont.querySelectorAll('.dynamic-detail-input');
            for (const inp of dynamicInputs) {
                const val = inp.value.trim();
                const fieldTitle = inp.dataset.fieldTitle || 'Additional Detail';
                if (!val) {
                    inp.focus();
                    showSweetAlert("Required Detail Missing", `Please fill out "${fieldTitle}".`, "warning");
                    return;
                }
                if (inp.dataset.format === 'INT' && !/^-?\d+$/.test(val)) {
                    inp.focus();
                    showSweetAlert("Invalid Number", `"${fieldTitle}" must be a whole number.`, "warning");
                    return;
                }
            }

            if (dataConsentInput.value !== "1") {
                showSweetAlert("Privacy Consent Required", "Please click 'Read & Sign Agreement Form' and accept the data privacy terms.", "warning");
                if (privacyModalInstance) privacyModalInstance.show();
                return;
            }

            if (!transactionInput.value && !otherConcernText.value.trim()) {
                showSweetAlert("Transaction Concern Required", "Please select at least one transaction concern.", "warning");
                return;
            }

            let structuredConcernsHtml = '';
            let totalEstimatedMinutes = 0;

            document.querySelectorAll('#transactionCardsContainer .transaction-card.active').forEach(card => {
                const titleEl = card.querySelector('h6');
                if (!titleEl) return;
                const title = titleEl.textContent.trim();
                const transId = card.dataset.transId;

                if (transId === 'OTHERS') {
                    totalEstimatedMinutes += 10;
                    const customVal = otherConcernText.value.trim();
                    structuredConcernsHtml += `
                        <div class="receipt-concern-item">
                            <div class="receipt-concern-title"><i class="bi bi-pen me-1 text-primary"></i>Others</div>
                            <div class="text-muted small mt-1">${escapeHtml(customVal || 'General Concern')}</div>
                        </div>
                    `;
                    return;
                }

                const activeSubBtns = card.querySelectorAll('.sub-concern-btn.btn-primary');
                let subTagsHtml = '';

                if (activeSubBtns.length > 0) {
                    const subNames = Array.from(activeSubBtns).map(b => {
                        totalEstimatedMinutes += parseInt(b.dataset.subEst || '5', 10);
                        const subName = b.querySelector('span')?.textContent.trim() || b.textContent.trim();
                        return `<div class="mt-1"><span class="receipt-subconcern-tag"><i class="bi bi-tag-fill me-1"></i>${escapeHtml(subName)}</span></div>`;
                    }).join('');
                    subTagsHtml = `<div>${subNames}</div>`;
                } else {
                    totalEstimatedMinutes += parseInt(card.dataset.estMins || '5', 10);
                }

                structuredConcernsHtml += `
                    <div class="receipt-concern-item">
                        <div class="receipt-concern-title">${escapeHtml(title)}</div>
                        ${subTagsHtml}
                    </div>
                `;
            });

            const activeBtn = triggerBtn || btnFinalSubmit;
            const originalBtnHtml = activeBtn.innerHTML;
            activeBtn.disabled = true;
            activeBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Issuing Ticket...';

            const formData = new FormData(kioskForm);

            fetch('<?= BASE_URL; ?>admin/ajax/logbook', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(result => {
                activeBtn.disabled = false;
                activeBtn.innerHTML = originalBtnHtml;

                if (result.success) {
                    document.getElementById('modalTicketNumber').textContent = result.ticket_number || '---';
                    document.getElementById('modalOfficeName').textContent   = result.office_name || summaryOfficeName.textContent;
                    document.getElementById('modalTransactionContainer').innerHTML = structuredConcernsHtml;
                    document.getElementById('modalIssuedDate').textContent = new Date().toLocaleString();
                    document.getElementById('modalEstTime').textContent = `~${result.estimated_minutes || totalEstimatedMinutes || 5} mins`;

                    const priorityBadge = document.getElementById('modalPriorityBadge');
                    if (priorityBadge) {
                        priorityBadge.classList.toggle('d-none', !result.is_priority);
                    }

                    const ticketModal = new bootstrap.Modal(document.getElementById('ticketSuccessModal'));
                    ticketModal.show();
                } else {
                    showSweetAlert("Check-in Failed", result.message || "Failed to process check-in.", "error");
                }
            })
            .catch(err => {
                activeBtn.disabled = false;
                activeBtn.innerHTML = originalBtnHtml;
                showSweetAlert("Submission Error", "Check-in request failed. Please try again.", "error");
            });
        }

        // FINAL SUBMISSION FORM SUBMIT (Step 5)
        kioskForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitLogbookForm(btnFinalSubmit);
        });

        document.getElementById('btnFinishKiosk')?.addEventListener('click', function() {
            window.location.reload();
        });
    })();
</script>
</body>
</html>