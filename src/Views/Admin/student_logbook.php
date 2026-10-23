<?php
# Core Config Dependencies
$db_connect  = $db_connect ?? null;
$g_user_role = $g_user_role ?? '';

# Page Helpers
require_once ADMIN_PAGE_HEADER_PATH;

# Page Header Initialization
$title_page   = "Student Logbook";
$active_links = [
    ['label' => 'Digital Logbook', 'url' => 'javascript:void(0)'],
    ['label' => 'Student Check-in', 'url' => '']
];
$page_header = render_page_header((string) $title_page, $active_links) ?? '';

# Fetch Active Programs
$programs_list = [];
if ($db_connect) {
    $stmtProg = $db_connect->query("SELECT program_code, program_name FROM programs WHERE flag_offer = 0 ORDER BY program_code ASC");
    if ($stmtProg) {
        while ($row = $stmtProg->fetch_assoc()) {
            $programs_list[] = $row;
        }
    }
}

# Fetch Active Offices
$offices_list = [];
if ($db_connect) {
    $stmtOff = $db_connect->query("SELECT office_id, office_code, office_name, office_logo FROM office WHERE flag_status = 0 ORDER BY office_name ASC");
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
            --kiosk-primary: #1d4ed8;
            --kiosk-primary-hover: #1e40af;
            --kiosk-primary-light: #eff6ff;
            --kiosk-border: #e2e8f0;
            --kiosk-bg-subtle: #f8fafc;
            --kiosk-shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
            --kiosk-shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
            --kiosk-radius: 1rem;
        }

        /* Container Card Styling */
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

        .kiosk-header-banner {
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
            color: #ffffff;
            padding: 1.5rem 2rem;
        }

        /* Visual Step Progress Wizard */
        .step-progress-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.5rem;
            background: var(--kiosk-bg-subtle);
            border-bottom: 1px solid var(--kiosk-border);
        }

        .step-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            font-weight: 600;
            color: #64748b;
            transition: all 0.3s ease;
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
        }

        .step-item.active {
            color: var(--kiosk-primary);
        }

        .step-item.active .step-number {
            background: var(--kiosk-primary);
            box-shadow: 0 0 0 4px rgba(29, 78, 216, 0.15);
        }

        .step-item.completed .step-number {
            background: #10b981;
        }

        .step-divider {
            flex-grow: 1;
            height: 2px;
            background: #e2e8f0;
            margin: 0 1rem;
        }

        /* Form Control Improvements */
        .form-control-kiosk, .form-select-kiosk {
            border: 1.5px solid #cbd5e1;
            border-radius: 0.75rem;
            padding: 0.85rem 1rem;
            font-size: 0.95rem;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .form-control-kiosk:focus, .form-select-kiosk:focus {
            border-color: var(--kiosk-primary);
            box-shadow: 0 0 0 4px rgba(29, 78, 216, 0.12);
            outline: none;
        }

        .input-group-kiosk {
            position: relative;
        }

        .input-group-kiosk .input-icon {
            position: absolute;
            right: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            pointer-events: none;
        }

        /* Office & Transaction Cards */
        .office-card, .transaction-card {
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            border: 2px solid #e2e8f0;
            background: #ffffff;
            border-radius: 0.875rem;
            position: relative;
            padding: 1.25rem 1.5rem;
        }

        .office-card:hover, .transaction-card:hover {
            border-color: #93c5fd;
            background-color: var(--kiosk-bg-subtle);
            transform: translateY(-2px);
            box-shadow: var(--kiosk-shadow-sm);
        }

        .office-card.active, .transaction-card.active {
            border-color: var(--kiosk-primary);
            background-color: var(--kiosk-primary-light);
            box-shadow: 0 10px 15px -3px rgba(29, 78, 216, 0.1);
        }

        .office-card.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 15%;
            bottom: 15%;
            width: 4px;
            background-color: var(--kiosk-primary);
            border-radius: 0 4px 4px 0;
        }

        .office-logo-img {
            width: 50px;
            height: 50px;
            object-fit: contain;
            border-radius: 50%;
            background: #ffffff;
            padding: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.06);
        }

        .office-logo-placeholder {
            width: 50px;
            height: 50px;
            background-color: #e2e8f0;
            color: #475569;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-size: 1.35rem;
        }

        /* Sub-Concern Pill Buttons (Single Column) */
        .sub-concern-btn {
            border-radius: 0.65rem;
            font-weight: 600;
            font-size: 0.88rem;
            transition: all 0.2s ease;
        }

        /* Consent Card Styling */
        .consent-card-box {
            padding: 1.25rem 1.5rem;
            background-color: var(--kiosk-bg-subtle);
            border: 1px solid var(--kiosk-border);
            border-radius: 0.875rem;
        }

        /* Action Buttons */
        .btn-kiosk-primary {
            background-color: var(--kiosk-primary);
            border-color: var(--kiosk-primary);
            color: #ffffff;
            border-radius: 0.75rem;
            padding: 0.9rem 1.5rem;
            font-weight: 700;
            transition: all 0.2s ease;
        }

        .btn-kiosk-primary:hover {
            background-color: var(--kiosk-primary-hover);
            border-color: var(--kiosk-primary-hover);
            color: #ffffff;
            transform: translateY(-1px);
        }

        /* Thermal Receipt Modal */
        .receipt-card {
            background: #ffffff;
            border: 2px dashed #cbd5e1;
            border-radius: 1rem;
            position: relative;
        }

        .receipt-header {
            background: var(--kiosk-primary);
            color: #ffffff;
            border-radius: 0.85rem 0.85rem 0 0;
            padding: 1.25rem;
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

                    <!-- Page Header -->
                    <?php echo is_array($page_header) ? '' : $page_header; ?>

                    <div class="row justify-content-center w-100 mx-0">
                        <div class="col-12">
                            <div class="card kiosk-main-card border-0">

                                <!-- KIOSK HEADER BANNER WITH SVG ICON -->
                                <div class="kiosk-header-banner d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="p-2 rounded-3 text-white d-flex align-items-center justify-content-center" style="background-color: rgba(255, 255, 255, 0.2); width: 44px; height: 44px;">
                                            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path fill-rule="evenodd" clip-rule="evenodd" d="M4.72718 2.71244C5.03258 2.41324 5.46135 2.21816 6.27103 2.11151C7.10452 2.00172 8.2092 2 9.7931 2H14.2069C15.7908 2 16.8955 2.00172 17.729 2.11151C18.5387 2.21816 18.9674 2.41324 19.2728 2.71244ZM7.58621 5.78378C7.12914 5.78378 6.75862 6.1468 6.75862 6.59459C6.75862 7.04239 7.12914 7.40541 7.58621 7.40541H16.4138C16.8709 7.40541 17.2414 7.04239 17.2414 6.59459C17.2414 6.1468 16.8709 5.78378 16.4138 5.78378H7.58621ZM6.75862 10.3784C6.75862 9.93058 7.12914 9.56757 7.58621 9.56757H13.1034C13.5605 9.56757 13.931 9.93058 13.931 10.3784C13.931 10.8262 13.5605 11.1892 13.1034 11.1892H7.58621C7.12914 11.1892 6.75862 10.8262 6.75862 10.3784Z" fill="#ffffff"></path>
                                                <path d="M7.47341 17.1351C6.39395 17.1351 6.01657 17.1421 5.72738 17.218C4.93365 17.4264 4.30088 18.0044 4.02952 18.7558C4.0463 19.1382 4.07259 19.4746 4.11382 19.775C4.22268 20.5683 4.42179 20.9884 4.72718 21.2876C5.03258 21.5868 5.46135 21.7818 6.27103 21.8885C7.10452 21.9983 8.2092 22 9.7931 22H14.2069C15.7908 22 16.8955 21.9983 17.729 21.8885C18.5387 21.7818 18.9674 21.5868 19.2728 21.2876C19.4894 21.0753 19.6526 20.8023 19.768 20.3784H7.58621C7.12914 20.3784 6.75862 20.0154 6.75862 19.5676C6.75862 19.1198 7.12914 18.7568 7.58621 18.7568H19.9704C19.9909 18.2908 19.9972 17.7564 19.9991 17.1351H7.47341Z" fill="#ffffff"></path>
                                            </svg>
                                        </div>
                                        <div>
                                            <h4 class="fw-bold text-white mb-0 tracking-wide">STUDENT DIGITAL LOGBOOK</h4>
                                        </div>
                                    </div>
                                    <span class="badge bg-white text-primary px-3 py-2 fw-bold fs-7 shadow-sm" id="stepBadge">STEP 1 OF 3</span>
                                </div>

                                <!-- VISUAL STEP WIZARD -->
                                <div class="step-progress-bar">
                                    <div class="step-item active" id="wizardStep1">
                                        <span class="step-number">1</span>
                                        <span class="d-none d-sm-inline">Student Info</span>
                                    </div>
                                    <div class="step-divider"></div>
                                    <div class="step-item" id="wizardStep2">
                                        <span class="step-number">2</span>
                                        <span class="d-none d-sm-inline">Select Office</span>
                                    </div>
                                    <div class="step-divider"></div>
                                    <div class="step-item" id="wizardStep3">
                                        <span class="step-number">3</span>
                                        <span class="d-none d-sm-inline">Transaction</span>
                                    </div>
                                </div>

                                <div class="card-body p-4 p-md-5">

                                    <!-- KIOSK FORM -->
                                    <form id="kioskForm" novalidate>
                                        <input type="hidden" name="action" value="logbook_checkin">
                                        <input type="hidden" name="visitor_type" value="STUDENT">
                                        <input type="hidden" id="userId" name="user_id" value="">
                                        <input type="hidden" id="officeSelect" name="office_id" value="" required>
                                        <input type="hidden" id="transactionSelect" name="transaction_type_id" value="" required>
                                        <input type="hidden" id="subConcernSelect" name="sub_concern_id" value="">

                                        <!-- ========================================================== -->
                                        <!-- STEP 1: STUDENT INFORMATION                                -->
                                        <!-- ========================================================== -->
                                        <div id="step1_studentInfo">

                                            <div class="mb-4">
                                                <h3 class="fw-bold text-dark mb-1">Student Identification</h3>
                                                <p class="text-muted small mb-0">
                                                    Enter or scan your Student ID to retrieve your record.
                                                </p>
                                            </div>

                                            <!-- ALWAYS VISIBLE: STUDENT ID INPUT -->
                                            <div class="mb-4">
                                                <label for="studentId" class="form-label fw-bold text-dark">Student ID Number</label>
                                                <div class="input-group-kiosk">
                                                    <input type="text" class="form-control form-control-kiosk form-control-lg fw-bold text-primary" id="studentId" name="reference_id" placeholder="e.g., 2023-11961" maxlength="15" autocomplete="off" required autofocus>
                                                    <i class="bi bi-upc-scan input-icon fs-4"></i>
                                                </div>
                                                <div id="idHelpBlock" class="form-text text-muted mt-2">Enter your Student ID to verify details.</div>
                                            </div>

                                            <!-- HIDDEN UNTIL VALID STUDENT ID IS ENTERED -->
                                            <div id="studentDetailsSection" class="d-none pt-2">

                                                <!-- NAME FIELDS ROW -->
                                                <div class="row g-3 mb-3">
                                                    <div class="col-md-4">
                                                        <label for="firstName" class="form-label fw-bold text-dark">First Name</label>
                                                        <input type="text" class="form-control form-control-kiosk" id="firstName" name="first_name" placeholder="First Name" required>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label for="middleName" class="form-label fw-bold text-dark">Middle Name</label>
                                                        <input type="text" class="form-control form-control-kiosk" id="middleName" name="middle_name" placeholder="Middle Name">
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label for="lastName" class="form-label fw-bold text-dark">Last Name</label>
                                                        <input type="text" class="form-control form-control-kiosk" id="lastName" name="last_name" placeholder="Last Name" required>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label for="suffix" class="form-label fw-bold text-dark">Suffix</label>
                                                        <select class="form-select form-select-kiosk" id="suffix" name="suffix">
                                                            <option value="" selected>None</option>
                                                            <option value="Jr.">Jr.</option>
                                                            <option value="Sr.">Sr.</option>
                                                            <option value="I">I</option>
                                                            <option value="II">II</option>
                                                            <option value="III">III</option>
                                                            <option value="IV">IV</option>
                                                            <option value="V">V</option>
                                                        </select>
                                                    </div>
                                                </div>

                                                <!-- SEX & CONTACT NUMBER ROW -->
                                                <div class="row g-3 mb-3">
                                                    <div class="col-md-6">
                                                        <label for="sex" class="form-label fw-bold text-dark">Sex</label>
                                                        <select class="form-select form-select-kiosk" id="sex" name="sex" required>
                                                            <option value="" selected disabled>Select sex</option>
                                                            <option value="Male">Male</option>
                                                            <option value="Female">Female</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label for="contactNumber" class="form-label fw-bold text-dark">Contact Number</label>
                                                        <input type="tel" class="form-control form-control-kiosk" id="contactNumber" name="contact_no" placeholder="09XXXXXXXXX" maxlength="11" required>
                                                    </div>
                                                </div>

                                                <!-- COURSE & YEAR LEVEL ROW -->
                                                <div class="row g-3 mb-3">
                                                    <div class="col-md-6">
                                                        <label for="courseProgram" class="form-label fw-bold text-dark">Course / Program</label>
                                                        <select class="form-select form-select-kiosk" id="courseProgram" name="program_code" required>
                                                            <option value="" selected disabled>Select course / program</option>
                                                            <?php foreach ($programs_list as $prog): ?>
                                                                <option value="<?= htmlspecialchars($prog['program_code']); ?>">
                                                                    <?= htmlspecialchars($prog['program_code'] . ' - ' . $prog['program_name']); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>

                                                    <div class="col-md-6">
                                                        <label for="yearLevel" class="form-label fw-bold text-dark">Year Level</label>
                                                        <select class="form-select form-select-kiosk" id="yearLevel" name="year_level" required>
                                                            <option value="" selected disabled>Select year level</option>
                                                            <option value="1">1st Year</option>
                                                            <option value="2">2nd Year</option>
                                                            <option value="3">3rd Year</option>
                                                            <option value="4">4th Year</option>
                                                        </select>
                                                    </div>
                                                </div>

                                                <!-- CONSENT BOX -->
                                                <div class="consent-card-box mb-4">
                                                    <div class="form-check d-flex align-items-start gap-3 ps-0">
                                                        <input class="form-check-input mt-1 flex-shrink-0 ms-0" type="checkbox" id="dataConsent" name="data_consent" value="1" required>
                                                        <label class="form-check-label small text-muted flex-grow-1 text-wrap" for="dataConsent">
                                                            I authorize the school to collect and use the information I have provided for 
                                                            <strong class="text-dark">school-related services, queue management, and record-keeping purposes</strong>.
                                                        </label>
                                                    </div>
                                                </div>

                                                <button type="button" id="btnContinueStep2" class="btn btn-kiosk-primary w-100 py-3 d-flex align-items-center justify-content-center gap-2 shadow-sm">
                                                    <span>Continue to Office Selection</span>
                                                    <i class="bi bi-arrow-right fs-5"></i>
                                                </button>

                                            </div>
                                        </div>

                                        <!-- ========================================================== -->
                                        <!-- STEP 2: OFFICE SELECTION                                   -->
                                        <!-- ========================================================== -->
                                        <div id="step2_officeSelection" class="d-none">

                                            <div class="mb-4">
                                                <h3 class="fw-bold text-dark mb-1">Select Destination Office</h3>
                                                <p class="text-muted small mb-0">
                                                    Tap your destination office below to proceed to transaction selection.
                                                </p>
                                            </div>

                                            <!-- SINGLE-COLUMN OFFICE LIST -->
                                            <div class="d-flex flex-column gap-3 mb-4" id="officeListContainer">
                                                <?php if (!empty($offices_list)): ?>
                                                    <?php foreach ($offices_list as $off): ?>
                                                        <?php 
                                                            $logo_path = !empty($off['office_logo']) ? BASE_URL . $off['office_logo'] : ''; 
                                                        ?>
                                                        <div class="office-card d-flex align-items-center justify-content-between" 
                                                             data-office-id="<?= $off['office_id']; ?>" 
                                                             data-office-name="<?= htmlspecialchars($off['office_name']); ?>"
                                                             data-office-code="<?= htmlspecialchars($off['office_code']); ?>">
                                                            <div class="d-flex align-items-center gap-3">
                                                                <?php if (!empty($logo_path)): ?>
                                                                    <img src="<?= htmlspecialchars($logo_path); ?>" 
                                                                         alt="<?= htmlspecialchars($off['office_code']); ?> Logo" 
                                                                         class="office-logo-img"
                                                                         onerror="this.style.display='none'; this.nextElementSibling.classList.remove('d-none');">
                                                                    <div class="office-logo-placeholder d-none">
                                                                        <i class="bi bi-building"></i>
                                                                    </div>
                                                                <?php else: ?>
                                                                    <div class="office-logo-placeholder">
                                                                        <i class="bi bi-building"></i>
                                                                    </div>
                                                                <?php endif; ?>

                                                                <div>
                                                                    <h6 class="fw-bold text-dark mb-0 fs-6"><?= htmlspecialchars($off['office_name']); ?></h6>
                                                                    <span class="badge bg-light text-primary border mt-1 fw-semibold small"><?= htmlspecialchars($off['office_code']); ?></span>
                                                                </div>
                                                            </div>

                                                            <i class="bi bi-chevron-right text-muted fs-5 select-icon ms-2"></i>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <div class="text-center p-4 bg-light rounded-3 text-muted">
                                                        No active offices available.
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <!-- ACTION BUTTON -->
                                            <button type="button" id="btnBackToStep1" class="btn btn-outline-secondary py-3 fw-bold w-100 rounded-3">
                                                <i class="bi bi-arrow-left me-1"></i> Back to Student Information
                                            </button>

                                        </div>

                                        <!-- ========================================================== -->
                                        <!-- STEP 3: TRANSACTION / PURPOSE SELECTION                   -->
                                        <!-- ========================================================== -->
                                        <div id="step3_purposeSelection" class="d-none">

                                            <!-- SELECTED OFFICE SUMMARY BANNER -->
                                            <div class="p-3 p-md-4 bg-light rounded-3 border d-flex align-items-center justify-content-between mb-4">
                                                <div>
                                                    <span class="text-muted text-uppercase fw-bold small d-block">Destination Office</span>
                                                    <h5 class="fw-bold text-primary mb-0" id="summaryOfficeName">---</h5>
                                                </div>
                                                <span class="badge bg-primary fs-6 px-3 py-2" id="summaryOfficeCode">---</span>
                                            </div>

                                            <div class="mb-3">
                                                <h4 class="fw-bold text-dark mb-1">Select Transaction Concern</h4>
                                                <p class="text-muted small mb-0">Tap your primary concern below to view dynamic options.</p>
                                            </div>

                                            <!-- DYNAMIC TRANSACTION CARDS CONTAINER -->
                                            <div class="d-flex flex-column gap-3 mb-4" id="transactionCardsContainer">
                                                <div class="text-center p-3 text-muted">Loading available concerns...</div>
                                            </div>

                                            <!-- ACTION BUTTONS -->
                                            <div class="d-flex gap-3 mt-4">
                                                <button type="button" id="btnBackToStep2" class="btn btn-outline-secondary py-3 fw-bold rounded-3 w-33">
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

    <!-- QUEUE TICKET SUCCESS MODAL -->
    <div class="modal fade" id="ticketSuccessModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg p-4 rounded-4 text-center">
                <div class="modal-body p-0">
                    
                    <!-- SUCCESS ICON & HEADER -->
                    <div class="text-success display-3 mb-2">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                    <h3 class="fw-bold text-dark mb-1">Check-in Complete!</h3>
                    <p class="text-muted small mb-3">Please keep a record of your queue ticket receipt details.</p>

                    <!-- TICKET RECEIPT CARD -->
                    <div class="card receipt-card my-3 text-start shadow-sm overflow-hidden">
                        
                        <!-- TICKET NUMBER DISPLAY HEADER -->
                        <div class="receipt-header text-center">
                            <span class="text-uppercase tracking-wide fw-semibold small opacity-75 d-block">Queue Ticket Number</span>
                            <h1 class="display-3 fw-black mb-0" id="modalTicketNumber">---</h1>
                        </div>

                        <!-- TICKET BREAKDOWN DETAILS (SINGLE COLUMN) -->
                        <div class="p-3 p-md-4 bg-white">
                            <div class="mb-2">
                                <span class="text-muted d-block small">Destination Office</span>
                                <strong class="text-dark fs-6 d-block" id="modalOfficeName">---</strong>
                            </div>

                            <hr class="my-2 text-muted opacity-25">

                            <div class="mb-2">
                                <span class="text-muted d-block small">Transaction Concern</span>
                                <strong class="text-dark fs-6 d-block" id="modalTransactionName">---</strong>
                            </div>

                            <hr class="my-2 text-muted opacity-25">

                            <div class="mb-2">
                                <span class="text-muted d-block small">Issued Date & Time</span>
                                <span class="fw-semibold text-dark small d-block" id="modalIssuedDate">---</span>
                            </div>

                            <hr class="my-2 text-muted opacity-25">

                            <div class="d-flex align-items-center justify-content-between">
                                <span class="text-muted small fw-semibold">Est. Wait Time</span>
                                <span class="badge bg-warning text-dark fw-bold px-3 py-2" id="modalEstTime">---</span>
                            </div>
                        </div>

                    </div>

                    <p class="text-muted small mb-4">
                        <i class="bi bi-info-circle me-1"></i> Please watch the office display screen for your ticket number call.
                    </p>

                    <!-- ACTION BUTTON -->
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
        // Elements - Step 1
        const studentIdInput  = document.getElementById('studentId');
        const detailsSection  = document.getElementById('studentDetailsSection');
        const idHelpBlock     = document.getElementById('idHelpBlock');

        const userIdInput     = document.getElementById('userId');
        const firstNameInput  = document.getElementById('firstName');
        const middleNameInput = document.getElementById('middleName');
        const lastNameInput   = document.getElementById('lastName');
        const suffixSelect    = document.getElementById('suffix');
        const sexSelect       = document.getElementById('sex');

        const courseSelect    = document.getElementById('courseProgram');
        const yearSelect      = document.getElementById('yearLevel');
        const contactInput    = document.getElementById('contactNumber');
        const consentCheckbox = document.getElementById('dataConsent');
        const btnContinue     = document.getElementById('btnContinueStep2');

        // Elements - Step 2 & 3
        const step1Div                 = document.getElementById('step1_studentInfo');
        const step2Div                 = document.getElementById('step2_officeSelection');
        const step3Div                 = document.getElementById('step3_purposeSelection');
        const stepBadge                = document.getElementById('stepBadge');
        
        // Wizard Steps Bar
        const wizardStep1              = document.getElementById('wizardStep1');
        const wizardStep2              = document.getElementById('wizardStep2');
        const wizardStep3              = document.getElementById('wizardStep3');

        const btnBackTo1               = document.getElementById('btnBackToStep1');
        const btnBackTo2               = document.getElementById('btnBackToStep2');
        
        const officeInput              = document.getElementById('officeSelect');
        const officeCards              = document.querySelectorAll('.office-card');
        const summaryOfficeName        = document.getElementById('summaryOfficeName');
        const summaryOfficeCode        = document.getElementById('summaryOfficeCode');
        
        const transactionInput         = document.getElementById('transactionSelect');
        const subConcernInput          = document.getElementById('subConcernSelect');
        const transactionContainer     = document.getElementById('transactionCardsContainer');
        const kioskForm                = document.getElementById('kioskForm');
        const btnFinalSubmit           = document.getElementById('btnFinalSubmit');

        if (!studentIdInput || !detailsSection) return;

        let debounceTimer;

        // Helper to sync Wizard progress UI (Consistent Badge Style)
        function setWizardStep(stepNum) {
            wizardStep1.className = 'step-item ' + (stepNum === 1 ? 'active' : 'completed');
            wizardStep2.className = 'step-item ' + (stepNum === 2 ? 'active' : (stepNum > 2 ? 'completed' : ''));
            wizardStep3.className = 'step-item ' + (stepNum === 3 ? 'active' : '');

            stepBadge.textContent = `STEP ${stepNum} OF 3`;
            stepBadge.className = "badge bg-white text-primary px-3 py-2 fw-bold fs-7 shadow-sm";
        }

        // ---------------------------------------------------------------------
        // 1. DEBOUNCED STUDENT ID LOOKUP
        // ---------------------------------------------------------------------
        studentIdInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            const studentId = this.value.trim();

            if (studentId.length >= 5) {
                idHelpBlock.textContent = "Verifying Student ID...";
                idHelpBlock.className = "form-text text-info fw-semibold";

                debounceTimer = setTimeout(() => {
                    fetchStudentInfo(studentId);
                }, 400);
            } else {
                hideDetailsSection();
                idHelpBlock.textContent = "Enter your Student ID to retrieve details.";
                idHelpBlock.className = "form-text text-muted";
            }
        });

        function fetchStudentInfo(studentId) {
            const formData = new FormData();
            formData.append('action', 'fetch_student_info');
            formData.append('student_id', studentId);

            fetch('<?= BASE_URL; ?>admin/ajax/logbook', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                if (result.success && result.data) {
                    const student = result.data;

                    userIdInput.value     = student.user_id || '';
                    firstNameInput.value  = student.first_name || '';
                    middleNameInput.value = student.middle_name || '';
                    lastNameInput.value   = student.last_name || '';
                    suffixSelect.value    = student.suffix || '';
                    sexSelect.value       = student.sex || '';
                    courseSelect.value    = student.program_code || '';
                    contactInput.value    = student.contact_no || '';

                    if (student.year_level) {
                        const yearNum = student.year_level.toString().charAt(0);
                        yearSelect.value = yearNum;
                    }

                    detailsSection.classList.remove('d-none');
                    idHelpBlock.textContent = "Record verified. Please review your details below.";
                    idHelpBlock.className = "form-text text-success fw-bold";
                } else {
                    hideDetailsSection();
                    idHelpBlock.textContent = "Student ID not recognized. Please check and try again.";
                    idHelpBlock.className = "form-text text-danger fw-semibold";
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                idHelpBlock.textContent = "Unable to verify ID at this time. Please try again.";
                idHelpBlock.className = "form-text text-danger";
            });
        }

        function hideDetailsSection() {
            detailsSection.classList.add('d-none');
            userIdInput.value     = '';
            firstNameInput.value  = '';
            middleNameInput.value = '';
            lastNameInput.value   = '';
            suffixSelect.value    = '';
            sexSelect.value       = '';
            courseSelect.value    = '';
            yearSelect.value      = '';
            contactInput.value    = '';
            consentCheckbox.checked = false;

            resetOfficeAndTransactionState();

            step3Div.classList.add('d-none');
            step2Div.classList.add('d-none');
            step1Div.classList.remove('d-none');
            setWizardStep(1);
        }

        function resetOfficeAndTransactionState() {
            officeInput.value = '';
            transactionInput.value = '';
            if (subConcernInput) subConcernInput.value = '';
            officeCards.forEach(card => card.classList.remove('active'));
            transactionContainer.innerHTML = '';
        }

        // ---------------------------------------------------------------------
        // 2. STEP TRANSITIONS
        // ---------------------------------------------------------------------
        btnContinue.addEventListener('click', function() {
            if (!firstNameInput.value.trim() || !lastNameInput.value.trim() || !sexSelect.value || !contactInput.value.trim() || !courseSelect.value || !yearSelect.value) {
                new swal({
                    title: "Incomplete Details",
                    text: "Please complete all required personal fields before proceeding.",
                    icon: "warning",
                    buttons: { confirm: { className: "btn btn-warning" } }
                });
                return;
            }

            if (!consentCheckbox.checked) {
                new swal({
                    title: "Consent Required",
                    text: "Please accept the Data Privacy Authorization to proceed.",
                    icon: "warning",
                    buttons: { confirm: { className: "btn btn-warning" } }
                });
                return;
            }

            step1Div.classList.add('d-none');
            step2Div.classList.remove('d-none');
            step3Div.classList.add('d-none');
            setWizardStep(2);
        });

        btnBackTo1.addEventListener('click', function() {
            step2Div.classList.add('d-none');
            step1Div.classList.remove('d-none');
            setWizardStep(1);
        });

        btnBackTo2.addEventListener('click', function() {
            step3Div.classList.add('d-none');
            step2Div.classList.remove('d-none');
            setWizardStep(2);
        });

        // ---------------------------------------------------------------------
        // 3. STEP 2: OFFICE CARD SELECTION -> ADVANCE TO STEP 3
        // ---------------------------------------------------------------------
        officeCards.forEach(card => {
            card.addEventListener('click', function() {
                const selectedOfficeId = this.dataset.officeId;
                const officeName       = this.dataset.officeName;
                const officeCode       = this.dataset.officeCode;

                officeCards.forEach(c => c.classList.remove('active'));
                this.classList.add('active');

                officeInput.value = selectedOfficeId;
                summaryOfficeName.textContent = officeName;
                summaryOfficeCode.textContent = officeCode;

                // Load transactions for selected office
                fetchOfficeTransactions(selectedOfficeId);

                // Transition view to Step 3
                step2Div.classList.add('d-none');
                step3Div.classList.remove('d-none');
                setWizardStep(3);
            });
        });

        // ---------------------------------------------------------------------
        // 4. STEP 3: DYNAMIC TRANSACTION CARDS WITH SINGLE-COLUMN SUB-CONCERNS
        // ---------------------------------------------------------------------
        function fetchOfficeTransactions(officeId) {
            transactionContainer.innerHTML = '<div class="text-center p-3 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Loading concerns...</div>';
            transactionInput.value = '';
            if (subConcernInput) subConcernInput.value = '';

            const formData = new FormData();
            formData.append('action', 'fetch_office_transactions');
            formData.append('office_id', officeId);

            fetch('<?= BASE_URL; ?>admin/ajax/office', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(result => {
                transactionContainer.innerHTML = '';

                if (result.success && result.data && result.data.length > 0) {
                    result.data.forEach(trans => {
                        const card = document.createElement('div');
                        card.className = 'transaction-card p-0 overflow-hidden mb-3';
                        card.dataset.transId = trans.id;
                        card.dataset.estimatedMinutes = trans.estimated_minutes || 5;

                        const hasSubConcerns = trans.sub_concerns && trans.sub_concerns.length > 0;
                        card.dataset.hasSubConcerns = hasSubConcerns ? '1' : '0';

                        // Single Column Sub-concerns layout (flex-column)
                        let subConcernsHtml = '';
                        if (hasSubConcerns) {
                            subConcernsHtml = `
                                <div class="mt-3 pt-3 border-top">
                                    <label class="form-label fw-bold text-dark small mb-2 d-block">Select Specific Concern Detail:</label>
                                    <div class="d-flex flex-column gap-2">
                                        ${trans.sub_concerns.map(sub => `
                                            <button type="button" class="btn btn-outline-primary btn-sm sub-concern-btn w-100 text-start py-2.5 px-3" data-sub-id="${sub.id}" data-sub-name="${sub.sub_concern_name}">
                                                ${sub.sub_concern_name}
                                            </button>
                                        `).join('')}
                                    </div>
                                </div>
                            `;
                        }

                        card.innerHTML = `
                            <div class="p-3 d-flex align-items-center justify-content-between trans-card-header">
                                <div class="d-flex align-items-center gap-3">
                                    <i class="bi bi-circle text-muted fs-5 select-trans-icon"></i>
                                    <div>
                                        <h6 class="fw-bold text-dark mb-0 fs-6 trans-title">${trans.transaction_name}</h6>
                                        <span class="badge bg-light text-secondary border mt-1 small">~${trans.estimated_minutes} mins</span>
                                    </div>
                                </div>
                                ${hasSubConcerns ? '<i class="bi bi-chevron-down text-muted collapse-chevron"></i>' : ''}
                            </div>
                            <div class="trans-card-body p-3 pt-0 d-none">
                                ${subConcernsHtml}
                            </div>
                        `;

                        // Main Transaction Card Click
                        const header = card.querySelector('.trans-card-header');
                        const body   = card.querySelector('.trans-card-body');

                        header.addEventListener('click', function() {
                            // Reset all other transaction cards
                            document.querySelectorAll('.transaction-card').forEach(tc => {
                                tc.classList.remove('active');
                                const icon = tc.querySelector('.select-trans-icon');
                                if (icon) icon.className = 'bi bi-circle text-muted fs-5 select-trans-icon';
                                
                                const tcBody = tc.querySelector('.trans-card-body');
                                if (tcBody) tcBody.classList.add('d-none');

                                tc.querySelectorAll('.sub-concern-btn').forEach(b => {
                                    b.classList.remove('btn-primary', 'text-white');
                                    b.classList.add('btn-outline-primary');
                                });
                            });

                            // Highlight clicked card
                            card.classList.add('active');
                            const icon = card.querySelector('.select-trans-icon');
                            if (icon) icon.className = 'bi bi-check-circle-fill text-primary fs-5 select-trans-icon';

                            transactionInput.value = trans.id;
                            if (subConcernInput) subConcernInput.value = '';

                            if (hasSubConcerns && body) {
                                body.classList.remove('d-none');
                            }
                        });

                        // Sub-concern button handlers
                        if (hasSubConcerns) {
                            card.querySelectorAll('.sub-concern-btn').forEach(subBtn => {
                                subBtn.addEventListener('click', function(e) {
                                    e.stopPropagation();

                                    card.querySelectorAll('.sub-concern-btn').forEach(b => {
                                        b.classList.remove('btn-primary', 'text-white');
                                        b.classList.add('btn-outline-primary');
                                    });

                                    this.classList.remove('btn-outline-primary');
                                    this.classList.add('btn-primary', 'text-white');

                                    if (subConcernInput) {
                                        subConcernInput.value = this.dataset.subId;
                                    }

                                    // Store sub-concern name for receipt display
                                    card.dataset.selectedSubName = this.dataset.subName;
                                });
                            });
                        }

                        transactionContainer.appendChild(card);
                    });
                } else {
                    transactionContainer.innerHTML = '<div class="text-center p-4 bg-light rounded-3 text-muted small">No active transaction concerns listed for this office.</div>';
                }
            })
            .catch(err => {
                console.error(err);
                transactionContainer.innerHTML = '<div class="text-center p-3 bg-light text-danger rounded-3 small">Error loading transactions. Please try again.</div>';
            });
        }

        // ---------------------------------------------------------------------
        // 5. FINAL CHECK-IN FORM SUBMISSION WITH DETAILED RECEIPT
        // ---------------------------------------------------------------------
        kioskForm.addEventListener('submit', function(e) {
            e.preventDefault();

            if (!officeInput.value) {
                new swal({
                    title: "Office Required",
                    text: "Please select a Destination Office.",
                    icon: "warning",
                    buttons: { confirm: { className: "btn btn-warning" } }
                });
                return;
            }

            if (!transactionInput.value) {
                new swal({
                    title: "Concern Required",
                    text: "Please select a Transaction Concern to proceed.",
                    icon: "warning",
                    buttons: { confirm: { className: "btn btn-warning" } }
                });
                return;
            }

            const activeCard = document.querySelector('.transaction-card.active');
            if (activeCard && activeCard.dataset.hasSubConcerns === '1' && (!subConcernInput || !subConcernInput.value)) {
                new swal({
                    title: "Sub-Concern Required",
                    text: "Please select a specific detail option for your concern.",
                    icon: "warning",
                    buttons: { confirm: { className: "btn btn-warning" } }
                });
                return;
            }

            btnFinalSubmit.disabled = true;
            btnFinalSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Issuing Ticket...';

            const formData = new FormData(kioskForm);

            fetch('<?= BASE_URL; ?>admin/ajax/logbook', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(result => {
                btnFinalSubmit.disabled = false;
                btnFinalSubmit.innerHTML = '<span>Complete Check-in & Get Ticket</span> <i class="bi bi-check-circle ms-1 fs-5"></i>';

                if (result.success) {
                    // Compose transaction name display
                    let selectedTransName = 'General Inquiry';
                    if (activeCard) {
                        const titleEl = activeCard.querySelector('.trans-title');
                        selectedTransName = titleEl ? titleEl.textContent.trim() : selectedTransName;
                        if (activeCard.dataset.selectedSubName) {
                            selectedTransName += ` — ${activeCard.dataset.selectedSubName}`;
                        }
                    }

                    // Populate Modal Receipt Elements
                    document.getElementById('modalTicketNumber').textContent = result.ticket_number || '---';
                    document.getElementById('modalOfficeName').textContent   = result.office_name || summaryOfficeName.textContent;
                    document.getElementById('modalTransactionName').textContent = result.transaction_name || selectedTransName;
                    
                    // Format current date and time
                    const now = new Date();
                    const formattedDate = result.issued_date || now.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) + ' ' + now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                    document.getElementById('modalIssuedDate').textContent = formattedDate;

                    // Estimated wait time display using exact database estimated_minutes
                    const estMins = result.estimated_minutes ?? (activeCard ? activeCard.dataset.estimatedMinutes : 5);
                    document.getElementById('modalEstTime').textContent = `~${estMins} mins`;

                    // Display Modal
                    const ticketModal = new bootstrap.Modal(document.getElementById('ticketSuccessModal'));
                    ticketModal.show();
                } else {
                    new swal({
                        title: "Check-in Failed",
                        text: result.message || "Failed to process check-in. Please try again.",
                        icon: "error",
                        buttons: { confirm: { className: "btn btn-danger" } }
                    });
                }
            })
            .catch(err => {
                console.error(err);
                btnFinalSubmit.disabled = false;
                btnFinalSubmit.innerHTML = '<span>Complete Check-in & Get Ticket</span> <i class="bi bi-check-circle ms-1 fs-5"></i>';
                new swal({
                    title: "System Error",
                    text: "An unexpected server error occurred. Please try again.",
                    icon: "error",
                    buttons: { confirm: { className: "btn btn-danger" } }
                });
            });
        });

        // ---------------------------------------------------------------------
        // 6. MANUAL KIOSK RESET
        // ---------------------------------------------------------------------
        window.addEventListener('pageshow', function() {
            if (kioskForm) kioskForm.reset();
            hideDetailsSection();
        });

        const btnFinish = document.getElementById('btnFinishKiosk');
        if (btnFinish) {
            btnFinish.addEventListener('click', function() {
                window.location.reload();
            });
        }

    })();
</script>
</body>
</html>