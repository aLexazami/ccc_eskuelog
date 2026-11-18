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

        /* LOCKED / READ-ONLY KIOSK FORM INPUTS IN STEP 5 */
        .form-control-kiosk:disabled,
        .form-control-kiosk[readonly],
        .form-select-kiosk:disabled,
        .kiosk-locked-field {
            background-color: #f1f5f9 !important;
            border-color: #cbd5e1 !important;
            color: #1e293b !important;
            font-weight: 600;
            cursor: not-allowed;
            opacity: 1 !important;
            box-shadow: none !important;
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

        .building-card:hover, .office-card:hover, .transaction-card:hover:not(.window-disabled):not(.direct-selected) {
            border-color: #93c5fd;
            background-color: var(--kiosk-bg-subtle);
            transform: translateY(-2px);
        }

        .building-card.active, .office-card.active, .transaction-card.active {
            border-color: var(--kiosk-primary);
            background-color: var(--kiosk-primary-light);
            box-shadow: 0 10px 15px -3px rgba(29, 78, 216, 0.1);
        }

        /* SOLID BLUE HIGHLIGHT FOR SELECTED PRIMARY TRANSACTIONS WITHOUT SUB-TRANSACTIONS */
        .transaction-card.active.direct-selected,
        .sub-concern-item-wrapper.sub-selected {
            background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%) !important;
            border-color: #1e3a8a !important;
            color: #ffffff !important;
            box-shadow: 0 10px 20px -4px rgba(29, 78, 216, 0.28);
        }

        .transaction-card.active.direct-selected .transaction-title-highlight,
        .sub-concern-item-wrapper.sub-selected .sub-concern-title-text {
            color: #ffffff !important;
        }

        .transaction-card.active.direct-selected .select-icon {
            color: #ffffff !important;
        }

        .transaction-card.active.direct-selected .window-assign-badge,
        .sub-concern-item-wrapper.sub-selected .window-assign-badge {
            background: rgba(255, 255, 255, 0.2) !important;
            color: #ffffff !important;
            border-color: rgba(255, 255, 255, 0.45) !important;
        }

        .transaction-card.active.direct-selected .est-time-subtle,
        .sub-concern-item-wrapper.sub-selected .sub-est-badge {
            background-color: rgba(255, 255, 255, 0.2) !important;
            border-color: rgba(255, 255, 255, 0.35) !important;
            color: #ffffff !important;
        }

        /* DISABLED STATE WHEN ANOTHER WINDOW IS SELECTED */
        .transaction-card.window-disabled {
            opacity: 0.48;
            background-color: #f1f5f9 !important;
            border-color: #cbd5e1 !important;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }

        .transaction-card.window-disabled .card-header-row {
            pointer-events: none;
        }

        .sub-concern-item-wrapper.sub-disabled {
            opacity: 0.45;
            cursor: not-allowed;
            background-color: #f1f5f9 !important;
            border-color: #cbd5e1 !important;
            transform: none !important;
            box-shadow: none !important;
        }

        .sub-concern-btn.window-disabled,
        .sub-concern-btn:disabled {
            cursor: not-allowed !important;
            pointer-events: none;
            color: #64748b !important;
        }

        /* SUBTLE WINDOW BADGE */
        .window-assign-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.68rem;
            font-weight: 600;
            letter-spacing: 0.3px;
            padding: 0.15rem 0.55rem;
            border-radius: 0.4rem;
            background-color: #eff6ff;
            color: #1e40af;
            border: 1px solid #bfdbfe;
            text-transform: uppercase;
            margin-bottom: 0.3rem;
        }

        .window-assign-badge.window-default {
            background-color: #f1f5f9;
            color: #475569;
            border-color: #cbd5e1;
        }

        /* REDUCED FONT WEIGHT FOR PRIMARY & SUB TRANSACTION NAMES */
        .transaction-title-highlight {
            font-size: 0.98rem;
            font-weight: 500;
            color: #1e293b;
            line-height: 1.4;
            margin-bottom: 0.3rem;
        }

        .transaction-card.active:not(.direct-selected) .transaction-title-highlight {
            color: #1d4ed8;
        }

        .est-time-subtle {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.75rem;
            font-weight: 500;
            color: #64748b;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 0.15rem 0.55rem;
            border-radius: 0.4rem;
        }

        /* SAME-WINDOW RULE HIGHLIGHT NOTICE */
        .window-rule-notice {
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
            border: 1.5px solid #f59e0b;
            border-left: 5px solid #d97706;
            color: #78350f;
            border-radius: 0.75rem;
            padding: 0.85rem 1rem;
            font-size: 0.875rem;
            line-height: 1.45;
        }

        /* SINGLE-COLUMN VERTICAL SUB-TRANSACTIONS LIST */
        .sub-concerns-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.75rem;
            width: 100%;
        }

        /* Each sub-transaction is its own card (same look as a primary transaction without sub-transactions).
           When selected, the card turns blue and its Additional Details container sits inside it. */
        .sub-concern-item-wrapper {
            display: flex;
            flex-direction: column;
            width: 100%;
            cursor: pointer;
            border: 2px solid #e2e8f0;
            background: #ffffff;
            border-radius: 0.875rem;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .sub-concern-item-wrapper:hover:not(.sub-selected):not(.sub-disabled) {
            border-color: #93c5fd;
            background-color: var(--kiosk-bg-subtle);
            transform: translateY(-2px);
        }

        .sub-concern-item-wrapper.sub-selected {
            border-color: #1e3a8a !important;
            box-shadow: 0 10px 20px -4px rgba(29, 78, 216, 0.28);
        }

        .sub-concern-btn {
            width: 100%;
            border-radius: 0.875rem;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            justify-content: center;
            text-align: left;
            gap: 0.25rem;
            padding: 0.95rem 1.15rem;
            border: 0 !important;
            background: transparent !important;
            box-shadow: none !important;
            transform: none !important;
        }

        .sub-concern-btn.btn-outline-primary,
        .sub-concern-btn.btn-primary {
            color: #1e293b;
        }

        .sub-concern-item-wrapper.sub-selected .sub-concern-btn {
            color: #ffffff;
        }

        .sub-concern-title-text {
            font-size: 0.94rem;
            font-weight: 500;
            line-height: 1.4;
            color: #1e293b;
        }

        .sub-concern-item-wrapper:hover:not(.sub-selected):not(.sub-disabled) .sub-concern-title-text {
            color: #1d4ed8;
        }

        .sub-concern-btn .sub-est-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.74rem;
            padding: 0.15rem 0.55rem;
            border-radius: 0.4rem;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #64748b;
            font-weight: 500;
            margin-top: 0.15rem;
        }

        /* IDENTICAL INLINE ADDITIONAL DETAILS DRAWER FOR BOTH PRIMARY & SUB-TRANSACTIONS */
        .inline-detail-drawer,
        .sub-concern-item-wrapper .inline-detail-drawer {
            background-color: #ffffff;
            color: #0f172a;
            border: 1.5px solid #93c5fd;
            border-left: 4px solid var(--kiosk-primary);
            border-radius: 0.75rem;
            padding: 1rem 1.15rem;
            margin-top: 0.75rem;
            margin-left: 0;
            width: 100%;
            cursor: default;
            box-shadow: 0 4px 10px -2px rgba(29, 78, 216, 0.08);
        }

        /* inside a selected (blue) sub-transaction card: same inset white container as the primary card */
        .sub-concern-item-wrapper .inline-detail-drawer {
            width: auto;
            margin: 0 1.15rem 1.15rem;
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

        .receipt-window-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.45);
            color: #ffffff;
            font-size: 0.8rem;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            padding: 0.22rem 0.85rem;
            border-radius: 999px;
            margin-top: 0.35rem;
            margin-bottom: 0.25rem;
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

        /* BLUE-THEMED RECEIPT REMINDER HIGHLIGHT */
        .receipt-reminder-highlight {
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            border: 1.5px solid #3b82f6;
            color: #1e3a8a;
            border-radius: 0.75rem;
            padding: 0.65rem 0.9rem;
            font-weight: 700;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        /* Ensure single asterisk even if global theme CSS also styles required labels */
        label.has-js-asterisk::after {
            content: none !important;
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

                        <!-- MANUAL ENTRY ID NUMBER FIELD (SHOWN FOR STUDENT & EMPLOYEE MANUAL ENTRY) -->
                        <div id="manualIdRow" class="row g-2 g-sm-3 mb-3">
                            <div class="col-12">
                                <label for="manualReferenceId" class="form-label fw-bold text-dark small" id="manualReferenceIdLabel">Student ID Number</label>
                                <input type="text" class="form-control form-control-kiosk fw-bold text-primary" id="manualReferenceId" placeholder="e.g., 2000-1234" maxlength="20" autocomplete="off">
                            </div>
                        </div>

                        <div class="row g-2 g-sm-3 mb-3" id="step1NameRow">
                            <div class="col-12 col-sm-6 col-md-3 name-col-primary">
                                <label for="firstName" class="form-label fw-bold text-dark small">First Name</label>
                                <input type="text" class="form-control form-control-kiosk" id="firstName" name="first_name" placeholder="First Name" required>
                            </div>
                            <div class="col-12 col-sm-6 col-md-3 name-col-secondary">
                                <label for="middleName" class="form-label fw-bold text-dark small">Middle Name</label>
                                <input type="text" class="form-control form-control-kiosk" id="middleName" name="middle_name" placeholder="Middle Name">
                            </div>
                            <div class="col-12 col-sm-6 col-md-3 name-col-primary">
                                <label for="lastName" class="form-label fw-bold text-dark small">Last Name</label>
                                <input type="text" class="form-control form-control-kiosk" id="lastName" name="last_name" placeholder="Last Name" required>
                            </div>
                            <div class="col-12 col-sm-6 col-md-3 name-col-secondary" id="suffixContainer">
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
                            <div class="col-6 col-sm-6 col-md-2 d-none" id="postNominalContainer">
                                <label for="postNominal" class="form-label fw-bold text-dark small">Post Nominal</label>
                                <input type="text" class="form-control form-control-kiosk" id="postNominal" name="post_nominal" placeholder="PhD, LPT">
                            </div>
                        </div>

                        <div class="row g-2 g-sm-3 mb-3">
                            <div class="col-12 col-md-6">
                                <label for="sex" class="form-label fw-bold text-dark small">Sex</label>
                                <select class="form-select form-select-kiosk" id="sex" name="sex" required>
                                    <option value="" selected disabled>Select sex</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="email" class="form-label fw-bold text-dark small">Email Address</label>
                                <input type="email" class="form-control form-control-kiosk" id="email" name="email" placeholder="user@example.com" required>
                            </div>
                        </div>

                        <div id="studentFieldsContainer" class="row g-2 g-sm-3 mb-3">
                            <div class="col-12 col-sm-6">
                                <label for="courseProgram" class="form-label fw-bold text-dark small">Course / Program</label>
                                <select class="form-select form-select-kiosk" id="courseProgram" name="program_id" required>
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
                                <select class="form-select form-select-kiosk" id="yearLevel" name="year_level" required>
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
                                <select class="form-select form-select-kiosk" id="employeeDepartment" name="department_id_select">
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

                        <div id="priorityCardBox" class="priority-card-box mb-4">
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
                <!-- STEP 4: TRANSACTION CONCERNS & INLINE REQUIRED DETAILS     -->
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
                        <p class="text-muted small mb-2">Select one or more concerns below. Choose "Others" if your specific concern is not listed.</p>

                        <!-- HIGHLIGHTED SAME-WINDOW SELECTION NOTICE -->
                        <div class="window-rule-notice d-flex align-items-start gap-2 shadow-sm">
                            <i class="bi bi-exclamation-triangle-fill text-warning fs-5 flex-shrink-0 mt-0.5"></i>
                            <div>
                                <strong>Same-Window Selection Policy:</strong> You may select one or more transactions <strong>only if they are assigned to the same Window</strong>. Selecting a transaction from a specific window will temporarily disable services assigned to other windows. To switch windows, unselect your current window's transaction(s) first.
                            </div>
                        </div>
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
                <!-- STEP 5: LOCKED FORM PREVIEW & AGREEMENT                    -->
                <!-- ========================================================== -->
                <div id="step5_summaryReview" class="d-none">
                    
                    <div class="mb-4">
                        <h3 class="fw-bold text-dark mb-1 fs-4">Review Logbook Summary</h3>
                        <p class="text-muted small mb-0">Please review your locked logbook form details below and accept the data privacy agreement before submitting.</p>
                    </div>

                    <!-- LOCKED FORM PREVIEW (EXACT STEP 1–4 FORM STYLE, READ-ONLY / DISABLED) -->
                    <div id="step5LockedFormContainer" class="d-flex flex-column gap-3 mb-4"></div>

                    <!-- STEP 5 PRIORITY SLOT (MOVED HERE WHEN VISITOR RECORD IS MATCHED) -->
                    <div id="step5PrioritySlot"></div>

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
                        <i class="bi bi-exclamation-circle-fill text-primary fs-5 flex-shrink-0"></i>
                        <span>Please keep a record of your queue ticket receipt details.</span>
                    </div>

                    <div class="card receipt-card my-3 text-start shadow-sm overflow-hidden">
                        <div class="receipt-header text-center position-relative">
                            <span class="text-uppercase tracking-wide fw-semibold small opacity-75 d-block">Queue Ticket Number</span>
                            <div>
                                <span class="receipt-window-pill">
                                    <i class="bi bi-window-sidebar"></i>
                                    <span id="modalWindowName">Any Window</span>
                                </span>
                            </div>
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

        // MANUAL ENTRY ID NUMBER ELEMENTS
        const manualIdRow          = document.getElementById('manualIdRow');
        const manualRefIdLabel     = document.getElementById('manualReferenceIdLabel');
        const manualRefIdInput     = document.getElementById('manualReferenceId');
        
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
        const priorityCardBox      = document.getElementById('priorityCardBox');
        const step5PrioritySlot    = document.getElementById('step5PrioritySlot');

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

        const step5LockedFormCont  = document.getElementById('step5LockedFormContainer');
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

        /**
         * Ensures every <label for="..."> linked to a [required] field displays
         * exactly one red asterisk (*), including dynamically rendered Step 4 fields.
         */
        function syncRequiredLabelIndicators(scopeEl = document) {
            scopeEl.querySelectorAll('label[for]').forEach(label => {
                label.querySelectorAll('.req-asterisk-mark').forEach(m => m.remove());
                label.classList.remove('has-js-asterisk');

                const targetId = label.getAttribute('for');
                if (!targetId) return;

                const targetEl = document.getElementById(targetId);
                if (targetEl && targetEl.hasAttribute('required')) {
                    label.classList.add('has-js-asterisk');
                    const star = document.createElement('span');
                    star.className = 'text-danger req-asterisk-mark ms-1';
                    star.textContent = '*';
                    label.appendChild(star);
                }
            });
        }

        /**
         * Places the Priority / Special Assistance card inside Step 5 when a matched
         * visitor record was confirmed, or returns it to Step 1 for manual/guest entry.
         */
        function syncPriorityCardPlacement() {
            if (!priorityCardBox || !step5PrioritySlot || !btnContinue) return;
            if (visitorRecordFound) {
                step5PrioritySlot.appendChild(priorityCardBox);
            } else {
                btnContinue.parentElement.insertBefore(priorityCardBox, btnContinue);
            }
        }

        // Keep manual entry ID input synchronized with the hidden/submitted referenceId input
        if (manualRefIdInput) {
            manualRefIdInput.addEventListener('input', function() {
                if (!visitorRecordFound) {
                    referenceIdInput.value = this.value;
                }
            });
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

            if (manualRefIdInput) manualRefIdInput.value = '';
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
        }

        function updateCategoryRequiredAttributes(type) {
            const isStudent  = type === 'STUDENT';
            const isEmployee = type === 'EMPLOYEE';

            if (courseSelect) courseSelect.required = isStudent;
            if (yearSelect) yearSelect.required = isStudent;
            if (employeeDeptSelect) employeeDeptSelect.required = isEmployee;

            syncRequiredLabelIndicators(personalDetailsSec);
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

            const suffixEl = document.getElementById('suffixContainer');
            if (suffixEl) {
                suffixEl.classList.toggle('col-6', isEmployee);
                suffixEl.classList.toggle('col-12', !isEmployee);
            }
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
            syncPriorityCardPlacement();
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

                if (manualIdRow) manualIdRow.classList.remove('d-none');
                if (manualRefIdLabel) manualRefIdLabel.textContent = "Student ID Number";
                if (manualRefIdInput) manualRefIdInput.placeholder = "e.g., 2000-1234";
                
                studentFieldsCont.classList.remove('d-none');
                employeeFieldsCont.classList.add('d-none');
                postNominalContainer.classList.add('d-none');
            } else if (type === 'EMPLOYEE') {
                updateNameGridColumns(true);
                idLookupSection.classList.remove('d-none');
                personalDetailsSec.classList.add('d-none');
                referenceIdLabel.textContent = "Employee ID Number";
                referenceIdInput.placeholder = "e.g., ABC-12345";
                idHelpBlock.textContent = "Enter your ID number and click Verify.";

                if (manualIdRow) manualIdRow.classList.remove('d-none');
                if (manualRefIdLabel) manualRefIdLabel.textContent = "Employee ID Number";
                if (manualRefIdInput) manualRefIdInput.placeholder = "e.g., ABC-12345";
                
                studentFieldsCont.classList.add('d-none');
                employeeFieldsCont.classList.remove('d-none');
                postNominalContainer.classList.remove('d-none');
            } else { // GUEST
                updateNameGridColumns(false);
                idLookupSection.classList.add('d-none');
                personalDetailsSec.classList.remove('d-none');

                if (manualIdRow) manualIdRow.classList.add('d-none');
                
                studentFieldsCont.classList.add('d-none');
                employeeFieldsCont.classList.add('d-none');
                postNominalContainer.classList.add('d-none');
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

        /**
         * Builds the full visitor name (First Name, Middle Name, Last Name, Suffix, and Post Nominal if available)
         * for matched-record Step 5 preview.
         */
        function buildReviewFullName() {
            const fname = (firstNameInput.value || '').trim();
            const mname = (middleNameInput.value || '').trim();
            const lname = (lastNameInput.value || '').trim();
            const sfix  = (suffixSelect.value || '').trim();
            const post  = (postNominalInput.value || '').trim();

            const baseParts = [fname, mname, lname, sfix].filter(p => p !== '');
            let fullName = baseParts.join(' ');
            if (post !== '') {
                fullName += (fullName ? ', ' : '') + post;
            }
            return fullName || '---';
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

            // Ensure manualIdRow is hidden so manualReferenceId never overwrites the verified referenceId
            if (manualIdRow) manualIdRow.classList.add('d-none');

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
            syncPriorityCardPlacement();
            updateConsentState(false);

            if (idVerificationModal) idVerificationModal.hide();

            step1Div.classList.add('d-none');
            step2Div.classList.remove('d-none');
            setWizardStep(2);
        });

        btnRejectIdentity?.addEventListener('click', function() {
            pendingVisitorData = null;
            visitorRecordFound = false;
            syncPriorityCardPlacement();
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

            const enteredId = referenceIdInput.value.trim();
            const type      = visitorTypeInput.value;

            pendingVisitorData = null;
            visitorRecordFound = false;
            syncPriorityCardPlacement();
            furthestWizardStep = 1;
            clearVisitorPersonalInputs();
            updateConsentState(false);

            // Carry over the typed ID Number into the editable Manual Entry field
            referenceIdInput.value = enteredId;
            if (manualIdRow) {
                manualIdRow.classList.toggle('d-none', type === 'GUEST');
            }
            if (manualRefIdLabel) {
                manualRefIdLabel.textContent = (type === 'EMPLOYEE') ? 'Employee ID Number' : 'Student ID Number';
            }
            if (manualRefIdInput) {
                manualRefIdInput.placeholder = (type === 'EMPLOYEE') ? 'e.g., ABC-12345' : 'e.g., 2000-1234';
                manualRefIdInput.value = enteredId;
            }

            idLookupSection.classList.add('d-none');
            personalDetailsSec.classList.remove('d-none');

            if (manualRefIdInput && !enteredId) {
                manualRefIdInput.focus();
            } else {
                firstNameInput.focus();
            }
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
                renderStep5LockedFormSummary();
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

        function validatePersonalInfoFields() {
            const vType = visitorTypeInput.value;
            const fName = firstNameInput.value.trim();
            const lName = lastNameInput.value.trim();
            const sexV  = sexSelect.value;
            const mailV = emailInput.value.trim();

            if (!fName || !lName || !sexV || !mailV) {
                showSweetAlert("Incomplete Information", "Please complete all required personal fields (First Name, Last Name, Sex, and Email Address).", "warning");
                return false;
            }

            if (vType === 'STUDENT') {
                const progV = courseSelect.value;
                const yearV = yearSelect.value;
                if (!progV || !yearV) {
                    showSweetAlert("Incomplete Student Information", "Please select your Course / Program and Year Level.", "warning");
                    return false;
                }
            } else if (vType === 'EMPLOYEE') {
                const deptV = employeeDeptSelect.value;
                if (!deptV) {
                    showSweetAlert("Incomplete Employee Information", "Please select your Department.", "warning");
                    return false;
                }
            }

            return true;
        }

        /**
         * Single source of truth: is this Additional Details scope (type_X / sub_Y) currently selected?
         */
        function isDetailScopeSelected(scopeKey) {
            const key = String(scopeKey || '');
            if (key.startsWith('type_')) {
                return selectedConcernIds.map(String).includes(key.slice(5));
            }
            if (key.startsWith('sub_')) {
                const subId = Number(key.slice(4));
                return Object.entries(selectedSubConcernsMap).some(([parentId, subs]) =>
                    selectedConcernIds.map(String).includes(String(parentId)) &&
                    (subs || []).map(Number).includes(subId)
                );
            }
            return false;
        }

        /**
         * Re-sync every sub-transaction card and every Additional Details drawer with the real
         * selection state, so an unselected item can never keep an active/required drawer.
         */
        function syncDetailDrawersWithSelection() {
            transactionContainer.querySelectorAll('.sub-concern-item-wrapper').forEach(wrap => {
                const isSel = isDetailScopeSelected(`sub_${wrap.dataset.subWrapperId}`);
                wrap.classList.toggle('sub-selected', isSel);
                const btn = wrap.querySelector('.sub-concern-btn');
                if (btn) {
                    btn.classList.toggle('btn-primary', isSel);
                    btn.classList.toggle('btn-outline-primary', !isSel);
                }
            });
            transactionContainer.querySelectorAll('.inline-detail-drawer').forEach(drawer => {
                setInlineDrawerActive(drawer, isDetailScopeSelected(drawer.dataset.scopeKey));
            });
        }

        function validateStep4DynamicDetails() {
            syncDetailDrawersWithSelection();
            const activeDrawers = Array.from(transactionContainer.querySelectorAll('.inline-detail-drawer:not(.d-none)'))
                .filter(d => isDetailScopeSelected(d.dataset.scopeKey));
            for (const drawer of activeDrawers) {
                const dynamicInputs = drawer.querySelectorAll('.dynamic-detail-input:not(:disabled)');
                for (const inp of dynamicInputs) {
                    const val = inp.value.trim();
                    const fieldTitle = inp.dataset.fieldTitle || 'Additional Detail';
                    if (!val) {
                        inp.focus();
                        showSweetAlert("Required Detail Missing", `Please fill out "${fieldTitle}".`, "warning");
                        return false;
                    }
                    if (inp.dataset.format === 'INT' && !/^-?\d+$/.test(val)) {
                        inp.focus();
                        showSweetAlert("Invalid Number", `"${fieldTitle}" must be a whole number.`, "warning");
                        return false;
                    }
                }
            }
            return true;
        }

        // STEP 1 CONTINUATION (Manual Entry & Guest)
        if (btnContinue) {
            btnContinue.addEventListener('click', function() {
                if (!visitorRecordFound && manualRefIdInput && !manualIdRow?.classList.contains('d-none')) {
                    referenceIdInput.value = manualRefIdInput.value.trim();
                }
                if (!validatePersonalInfoFields()) {
                    return;
                }
                if (visitorTypeInput.value === 'STUDENT') {
                    syncStudentProgramMetadata();
                } else if (visitorTypeInput.value === 'EMPLOYEE') {
                    departmentIdInput.value = employeeDeptSelect.value || '0';
                }
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

        // STEP 4 -> STEP 5 (VALIDATE CONCERNS & REQUIRED DETAILS, THEN RENDER LOCKED FORM SUMMARY)
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
                            "Transaction Selection Required",
                            `Please select at least one specific transaction under "${transObj.transaction_name}".`,
                            "warning"
                        );
                        return;
                    }
                }
            }

            // Validate required inline dynamic transaction detail fields right in Step 4
            if (!validateStep4DynamicDetails()) {
                return;
            }

            if (selectedConcernIds.includes('OTHERS') && !otherConcernText.value.trim()) {
                showSweetAlert("Specify Concern", "Please describe your specific concern under Others.", "warning");
                otherConcernText.focus();
                return;
            }

            renderStep5LockedFormSummary();

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

        /**
         * Build subtle window badge HTML for Primary Transactions & Sub-Transactions.
         * Defaults to "Any Window" when no specific counter is assigned.
         */
        function buildWindowBadgeHtml(counterId, counterName) {
            if (counterId && counterName) {
                return `
                    <div>
                        <span class="window-assign-badge">
                            <i class="bi bi-window-sidebar"></i> ${escapeHtml(counterName)}
                        </span>
                    </div>
                `;
            }
            return `
                <div>
                    <span class="window-assign-badge window-default">
                        <i class="bi bi-window-sidebar"></i> Any Window
                    </span>
                </div>
            `;
        }

        /**
         * Determine currently active Window ID and Window Name based on selected primary/sub-transactions.
         */
        function getActiveSelectedWindowInfo() {
            for (const tid of selectedConcernIds) {
                if (tid === 'OTHERS') continue;
                const transObj = officeTransactionsMap[tid];
                if (!transObj) continue;

                const hasSubs = Array.isArray(transObj.sub_concerns) && transObj.sub_concerns.length > 0;
                if (hasSubs) {
                    const pickedSubIds = selectedSubConcernsMap[tid] || [];
                    for (const sid of pickedSubIds) {
                        const subObj = transObj.sub_concerns.find(s => Number(s.id) === Number(sid));
                        const subWin = subObj && subObj.counter_id
                            ? Number(subObj.counter_id)
                            : (transObj.counter_id ? Number(transObj.counter_id) : null);
                        if (subWin) {
                            const winName = (subObj && subObj.counter_name) || transObj.counter_name || `Window ${subWin}`;
                            return { id: subWin, name: winName };
                        }
                    }
                    if (transObj.counter_id) {
                        const winId = Number(transObj.counter_id);
                        return { id: winId, name: transObj.counter_name || `Window ${winId}` };
                    }
                } else if (transObj.counter_id) {
                    const winId = Number(transObj.counter_id);
                    return { id: winId, name: transObj.counter_name || `Window ${winId}` };
                }
            }
            return { id: null, name: 'Any Window' };
        }

        function getActiveSelectedWindowId() {
            return getActiveSelectedWindowInfo().id;
        }

        /**
         * Enable/disable primary transaction cards and sub-transaction buttons
         * so users can only combine services assigned to the same window.
         */
        function enforceSameWindowSelectionState() {
            const activeWindowId = getActiveSelectedWindowId();

            transactionContainer.querySelectorAll('.transaction-card').forEach(card => {
                const tid = card.dataset.transId;
                if (tid === 'OTHERS') {
                    card.classList.remove('window-disabled');
                    return;
                }

                const transObj = officeTransactionsMap[tid];
                if (!transObj) return;

                const hasSubs = Array.isArray(transObj.sub_concerns) && transObj.sub_concerns.length > 0;

                if (!hasSubs) {
                    const cardWindowId = transObj.counter_id ? Number(transObj.counter_id) : null;
                    const isConflict = activeWindowId !== null && cardWindowId !== null && cardWindowId !== activeWindowId;
                    card.classList.toggle('window-disabled', isConflict);
                } else {
                    let compatibleSubCount = 0;
                    card.querySelectorAll('.sub-concern-btn').forEach(subBtn => {
                        const subWinRaw = subBtn.dataset.counterId;
                        const subWinId  = subWinRaw ? Number(subWinRaw) : null;
                        const isSubConflict = activeWindowId !== null && subWinId !== null && subWinId !== activeWindowId;

                        subBtn.disabled = isSubConflict;
                        subBtn.classList.toggle('window-disabled', isSubConflict);
                        subBtn.closest('.sub-concern-item-wrapper')?.classList.toggle('sub-disabled', isSubConflict);

                        if (!isSubConflict) {
                            compatibleSubCount++;
                        }
                    });

                    const isParentConflict = activeWindowId !== null && compatibleSubCount === 0;
                    card.classList.toggle('window-disabled', isParentConflict);
                }
            });
        }

        /**
         * Builds the HTML for an individual dynamic field input/select inside a transaction or sub-transaction.
         */
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

        /**
         * Builds the inline Additional Details form drawer directly underneath its own
         * Transaction Type (when no sub-transactions) or Sub-Transaction item.
         */
        function buildInlineDetailsDrawerHtml(scopeKey, detailsObj) {
            if (!detailsObj || !detailsObj.has_details || !Array.isArray(detailsObj.fields) || detailsObj.fields.length === 0) {
                return '';
            }

            return `
                <div class="inline-detail-drawer d-none" data-scope-key="${escapeHtml(scopeKey)}">
                    <div class="fw-bold text-primary small mb-2">
                        <i class="bi bi-ui-checks-grid me-1"></i>Required Additional Details
                    </div>
                    <div class="row g-3">
                        ${detailsObj.fields.map(field => `
                            <div class="col-12 col-md-6">
                                <label for="detail_${scopeKey}_${field.id}" class="form-label fw-bold text-dark small">
                                    ${escapeHtml(field.title)}
                                </label>
                                ${buildDynamicFieldControlHtml(scopeKey, field)}
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        }

        /**
         * Enables/disables & shows/hides an inline Additional Details drawer
         * so unselected items do not trigger browser validation or submit blank payloads.
         */
        function setInlineDrawerActive(drawerEl, isActive) {
            if (!drawerEl) return;
            drawerEl.classList.toggle('d-none', !isActive);
            drawerEl.querySelectorAll('.dynamic-detail-input').forEach(inp => {
                inp.disabled = !isActive;
                if (isActive) {
                    inp.setAttribute('required', 'required');
                } else {
                    inp.removeAttribute('required');
                }
            });
            drawerEl.querySelectorAll('.detail-choice-id-hidden').forEach(hid => {
                hid.disabled = !isActive;
            });
            if (isActive) {
                syncRequiredLabelIndicators(drawerEl);
            }
        }

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
                    counter_id: null,
                    counter_name: null,
                    is_others: true,
                    direct_details: null,
                    sub_concerns: []
                });

                dataList.forEach(trans => {
                    officeTransactionsMap[trans.id] = trans;
                    const hasSubConcerns = Array.isArray(trans.sub_concerns) && trans.sub_concerns.length > 0;

                    const card = document.createElement('div');
                    card.className = 'transaction-card p-3 mb-2';
                    card.dataset.transId   = trans.id;
                    card.dataset.estMins   = trans.estimated_minutes || 5;
                    card.dataset.counterId = trans.counter_id || '';

                    let subConcernsHtml = '';
                    let primaryInlineDetailsHtml = '';

                    if (hasSubConcerns) {
                        subConcernsHtml = `
                            <div class="sub-concerns-container d-none mt-3 pt-3 border-top">
                                <label class="form-label text-primary fw-bold small d-block mb-2">Select Specific Transaction(s):</label>
                                <div class="sub-concerns-grid">
                                    ${trans.sub_concerns.map(sub => {
                                        const effectiveSubCounterId   = sub.counter_id || trans.counter_id || null;
                                        const effectiveSubCounterName = sub.counter_name || trans.counter_name || null;
                                        const subDrawerHtml = buildInlineDetailsDrawerHtml(`sub_${sub.id}`, sub.parsed_details);
                                        return `
                                            <div class="sub-concern-item-wrapper" data-sub-wrapper-id="${sub.id}">
                                                <button type="button"
                                                        class="btn btn-outline-primary sub-concern-btn"
                                                        data-sub-id="${sub.id}"
                                                        data-sub-est="${sub.estimated_minutes || 5}"
                                                        data-counter-id="${effectiveSubCounterId || ''}">
                                                    ${buildWindowBadgeHtml(effectiveSubCounterId, effectiveSubCounterName)}
                                                    <div class="sub-concern-title-text">${escapeHtml(sub.sub_concern_name)}</div>
                                                    <span class="sub-est-badge"><i class="bi bi-clock"></i> Est. Time: ${sub.estimated_minutes || 5} mins</span>
                                                </button>
                                                ${subDrawerHtml}
                                            </div>
                                        `;
                                    }).join('')}
                                </div>
                            </div>
                        `;
                    } else if (!trans.is_others) {
                        primaryInlineDetailsHtml = buildInlineDetailsDrawerHtml(`type_${trans.id}`, trans.direct_details);
                    }

                    // Show Window Badge above Primary Transaction Name (defaults to Any Window)
                    const primaryWindowBadgeHtml = (!hasSubConcerns || trans.counter_id)
                        ? buildWindowBadgeHtml(trans.counter_id, trans.counter_name)
                        : '';

                    // Show Est. Time on primary transaction ONLY when it has NO sub-transactions
                    const primaryEstBadgeHtml = !hasSubConcerns
                        ? `<span class="est-time-subtle"><i class="bi bi-clock"></i> Est. Time: ${trans.estimated_minutes || 5} mins</span>`
                        : '';

                    card.innerHTML = `
                        <div class="card-header-row d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-3 min-w-0 flex-grow-1">
                                <i class="bi bi-square text-primary fs-4 select-icon flex-shrink-0"></i>
                                <div class="min-w-0 flex-grow-1">
                                    ${primaryWindowBadgeHtml}
                                    <div class="transaction-title-highlight">${escapeHtml(trans.transaction_name)}</div>
                                    ${primaryEstBadgeHtml}
                                </div>
                            </div>
                        </div>
                        ${primaryInlineDetailsHtml}
                        ${subConcernsHtml}
                    `;

                    // Initialize all inline drawers inside this card as disabled/hidden until selected
                    card.querySelectorAll('.inline-detail-drawer').forEach(drawer => {
                        drawer.addEventListener('click', ev => ev.stopPropagation());
                        setInlineDrawerActive(drawer, false);
                    });

                    // Wire choice_id hidden sync for SELECT fields inside inline drawers
                    card.querySelectorAll('.dynamic-detail-select').forEach(sel => {
                        const syncChoiceId = () => {
                            const opt = sel.options[sel.selectedIndex];
                            const hiddenChoice = sel.parentElement.querySelector('.detail-choice-id-hidden');
                            if (hiddenChoice) {
                                hiddenChoice.value = opt ? (opt.dataset.choiceId || '') : '';
                            }
                        };
                        sel.addEventListener('change', syncChoiceId);
                    });

                    const cardHeader = card.querySelector('.card-header-row');
                    cardHeader.addEventListener('click', function(e) {
                        e.stopPropagation();
                        if (card.classList.contains('window-disabled')) {
                            return;
                        }

                        const icon = card.querySelector('.select-icon');
                        const subContainer = card.querySelector('.sub-concerns-container');
                        const directDrawer = card.querySelector(`:scope > .inline-detail-drawer[data-scope-key="type_${trans.id}"]`);

                        if (selectedConcernIds.includes(trans.id)) {
                            selectedConcernIds = selectedConcernIds.filter(id => id !== trans.id);
                            delete selectedSubConcernsMap[trans.id];
                            card.classList.remove('active', 'direct-selected');
                            icon.className = 'bi bi-square text-primary fs-4 select-icon flex-shrink-0';
                            if (directDrawer) {
                                setInlineDrawerActive(directDrawer, false);
                            }
                            if (subContainer) {
                                subContainer.classList.add('d-none');
                                subContainer.querySelectorAll('.sub-concern-btn').forEach(b => {
                                    b.classList.remove('btn-primary', 'text-white');
                                    b.classList.add('btn-outline-primary');
                                    b.closest('.sub-concern-item-wrapper')?.classList.remove('sub-selected');
                                });
                                subContainer.querySelectorAll('.inline-detail-drawer').forEach(d => {
                                    setInlineDrawerActive(d, false);
                                });
                            }
                            if (trans.is_others) otherConcernBox.classList.add('d-none');
                        } else {
                            selectedConcernIds.push(trans.id);
                            selectedSubConcernsMap[trans.id] = [];
                            card.classList.add('active');
                            if (!hasSubConcerns) {
                                card.classList.add('direct-selected');
                            }
                            icon.className = 'bi bi-check-square-fill text-primary fs-4 select-icon flex-shrink-0';
                            if (directDrawer) {
                                setInlineDrawerActive(directDrawer, true);
                            }
                            if (subContainer) subContainer.classList.remove('d-none');
                            if (trans.is_others) otherConcernBox.classList.remove('d-none');
                        }

                        transactionInput.value = selectedConcernIds.join(',');
                        subConcernInput.value  = JSON.stringify(selectedSubConcernsMap);
                        syncDetailDrawersWithSelection();
                        enforceSameWindowSelectionState();
                    });

                    const subBtns = card.querySelectorAll('.sub-concern-btn');
                    subBtns.forEach(btn => {
                        btn.addEventListener('click', function(e) {
                            e.stopPropagation();
                            if (this.disabled || this.classList.contains('window-disabled')) {
                                return;
                            }

                            const subId = parseInt(this.dataset.subId, 10);
                            const subWrapper = this.closest('.sub-concern-item-wrapper');
                            const subDrawer  = subWrapper ? subWrapper.querySelector('.inline-detail-drawer') : null;

                            if (!selectedSubConcernsMap[trans.id]) {
                                selectedSubConcernsMap[trans.id] = [];
                            }

                            const index = selectedSubConcernsMap[trans.id].indexOf(subId);
                            if (index > -1) {
                                selectedSubConcernsMap[trans.id].splice(index, 1);
                                this.classList.remove('btn-primary', 'text-white');
                                this.classList.add('btn-outline-primary');
                                setInlineDrawerActive(subDrawer, false);
                            } else {
                                selectedSubConcernsMap[trans.id].push(subId);
                                this.classList.remove('btn-outline-primary');
                                this.classList.add('btn-primary', 'text-white');
                                setInlineDrawerActive(subDrawer, true);
                            }

                            subConcernInput.value = JSON.stringify(selectedSubConcernsMap);
                            syncDetailDrawersWithSelection();
                            enforceSameWindowSelectionState();
                        });
                    });

                    transactionContainer.appendChild(card);
                });

                enforceSameWindowSelectionState();
            });
        }

        /**
         * Helper to collect entered dynamic detail values for a given scopeKey ('type_X' or 'sub_Y')
         * directly from the inline drawer in Step 4 so Step 5 displays them in locked form fields.
         */
        function getEnteredDetailsForScope(scopeKey) {
            const entries = [];
            const drawer = transactionContainer.querySelector(`.inline-detail-drawer:not(.d-none)[data-scope-key="${scopeKey}"]`);
            if (!drawer) return entries;

            drawer.querySelectorAll('.dynamic-detail-input:not(:disabled)').forEach(inp => {
                const label = inp.dataset.fieldTitle || 'Detail';
                const val   = (inp.value || '').trim();
                if (val !== '') {
                    entries.push({ label, val });
                }
            });
            return entries;
        }

        /**
         * Helper to get human-readable display text from a <select> element.
         */
        function getSelectDisplayText(selectEl, fallbackText = 'None') {
            if (!selectEl || selectEl.selectedIndex < 0) return fallbackText;
            const opt = selectEl.options[selectEl.selectedIndex];
            if (!opt || opt.disabled || opt.value === '') return fallbackText;
            return opt.textContent.trim();
        }

        /**
         * Populates Step 5 using locked/disabled form inputs styled identically to Step 1–4 forms.
         * - When matched record ("Yes, This Is Me") was confirmed: Step 1 section shows ONLY
         *   the locked ID Number and Full Name.
         * - When manual entry or Guest was used: Step 1 section mirrors the exact manual personal
         *   information form fields in locked read-only controls.
         */
        function renderStep5LockedFormSummary() {
            if (!step5LockedFormCont) return;

            const vType = visitorTypeInput.value;
            const isEmployee = (vType === 'EMPLOYEE');
            const isStudent  = (vType === 'STUDENT');
            const isGuest    = (vType === 'GUEST');

            const effectiveId = (!visitorRecordFound && manualRefIdInput && !manualIdRow?.classList.contains('d-none'))
                ? manualRefIdInput.value.trim()
                : referenceIdInput.value.trim();

            let step1LockedHtml = '';

            if (visitorRecordFound) {
                // MATCHED RECORD ("Yes, This Is Me"): Only ID Number & Full Name in Step 5 review
                const idLabel = isEmployee ? 'Employee ID Number' : 'Student ID Number';
                const fullNameVal = buildReviewFullName();

                step1LockedHtml = `
                    <div class="summary-section-box mb-0">
                        <div class="row g-2 g-sm-3">
                            <div class="col-12 col-md-5">
                                <label class="form-label fw-normal text-dark small">${escapeHtml(idLabel)}</label>
                                <input type="text" class="form-control form-control-kiosk kiosk-locked-field fw-bold text-primary" value="${escapeHtml(effectiveId || '---')}" readonly disabled>
                            </div>
                            <div class="col-12 col-md-7">
                                <label class="form-label fw-normal text-dark small">Full Name</label>
                                <input type="text" class="form-control form-control-kiosk kiosk-locked-field" value="${escapeHtml(fullNameVal)}" readonly disabled>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                // MANUAL ENTRY OR GUEST: Exact same form fields as Step 1, locked & read-only
                const idRowHtml = !isGuest ? `
                    <div class="row g-2 g-sm-3 mb-3">
                        <div class="col-12">
                            <label class="form-label fw-normal text-dark small">${isEmployee ? 'Employee ID Number' : 'Student ID Number'}</label>
                            <input type="text" class="form-control form-control-kiosk kiosk-locked-field fw-bold text-primary" value="${escapeHtml(effectiveId || '')}" placeholder="Not Provided" readonly disabled>
                        </div>
                    </div>
                ` : '';

                const secColClass = isEmployee ? 'col-md-2' : 'col-md-3';
                const suffixColClass = isEmployee ? 'col-6 col-sm-6 col-md-2' : 'col-12 col-sm-6 col-md-3';
                const suffixDisplay = suffixSelect.value || 'None';

                const postNominalColHtml = isEmployee ? `
                    <div class="col-6 col-sm-6 col-md-2">
                        <label class="form-label fw-normal text-dark small">Post Nominal</label>
                        <input type="text" class="form-control form-control-kiosk kiosk-locked-field" value="${escapeHtml(postNominalInput.value.trim())}" placeholder="None" readonly disabled>
                    </div>
                ` : '';

                let categorySpecificRowHtml = '';
                if (isStudent) {
                    const progDisplay = getSelectDisplayText(courseSelect, '---');
                    const yearDisplay = getSelectDisplayText(yearSelect, '---');
                    categorySpecificRowHtml = `
                        <div class="row g-2 g-sm-3 mb-0">
                            <div class="col-12 col-sm-6">
                                <label class="form-label fw-normal text-dark small">Course / Program</label>
                                <input type="text" class="form-control form-control-kiosk kiosk-locked-field" value="${escapeHtml(progDisplay)}" readonly disabled>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label fw-normal text-dark small">Year Level</label>
                                <input type="text" class="form-control form-control-kiosk kiosk-locked-field" value="${escapeHtml(yearDisplay)}" readonly disabled>
                            </div>
                        </div>
                    `;
                } else if (isEmployee) {
                    const deptDisplay   = getSelectDisplayText(employeeDeptSelect, '---');
                    const officeDisplay = getSelectDisplayText(employeeOfficeSelect, 'None');
                    categorySpecificRowHtml = `
                        <div class="row g-2 g-sm-3 mb-0">
                            <div class="col-12 col-sm-6">
                                <label class="form-label fw-normal text-dark small">Department</label>
                                <input type="text" class="form-control form-control-kiosk kiosk-locked-field" value="${escapeHtml(deptDisplay)}" readonly disabled>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label fw-normal text-dark small">Office</label>
                                <input type="text" class="form-control form-control-kiosk kiosk-locked-field" value="${escapeHtml(officeDisplay)}" readonly disabled>
                            </div>
                        </div>
                    `;
                }

                // Priority classification row if checked during manual/guest entry
                const priorityLockedHtml = (prioritySwitch && prioritySwitch.checked) ? `
                    <div class="row g-2 g-sm-3 mt-3 pt-3 border-top">
                        <div class="col-12">
                            <label class="form-label fw-normal text-dark small"><i class="bi bi-person-wheelchair text-primary me-1"></i>Priority / Special Assistance Classification</label>
                            <input type="text" class="form-control form-control-kiosk kiosk-locked-field" value="${escapeHtml(getSelectDisplayText(document.getElementById('priorityType'), 'Priority'))}" readonly disabled>
                        </div>
                    </div>
                ` : '';

                step1LockedHtml = `
                    <div class="summary-section-box mb-0">
                        ${idRowHtml}
                        <div class="row g-2 g-sm-3 mb-3">
                            <div class="col-12 col-sm-6 col-md-3">
                                <label class="form-label fw-normal text-dark small">First Name</label>
                                <input type="text" class="form-control form-control-kiosk kiosk-locked-field" value="${escapeHtml(firstNameInput.value.trim())}" readonly disabled>
                            </div>
                            <div class="col-12 col-sm-6 ${secColClass}">
                                <label class="form-label fw-normal text-dark small">Middle Name</label>
                                <input type="text" class="form-control form-control-kiosk kiosk-locked-field" value="${escapeHtml(middleNameInput.value.trim())}" placeholder="None" readonly disabled>
                            </div>
                            <div class="col-12 col-sm-6 col-md-3">
                                <label class="form-label fw-normal text-dark small">Last Name</label>
                                <input type="text" class="form-control form-control-kiosk kiosk-locked-field" value="${escapeHtml(lastNameInput.value.trim())}" readonly disabled>
                            </div>
                            <div class="${suffixColClass}">
                                <label class="form-label fw-normal text-dark small">Suffix</label>
                                <input type="text" class="form-control form-control-kiosk kiosk-locked-field" value="${escapeHtml(suffixDisplay)}" readonly disabled>
                            </div>
                            ${postNominalColHtml}
                        </div>
                        <div class="row g-2 g-sm-3 ${categorySpecificRowHtml ? 'mb-3' : 'mb-0'}">
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-normal text-dark small">Sex</label>
                                <input type="text" class="form-control form-control-kiosk kiosk-locked-field" value="${escapeHtml(sexSelect.value || '---')}" readonly disabled>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-normal text-dark small">Email Address</label>
                                <input type="email" class="form-control form-control-kiosk kiosk-locked-field" value="${escapeHtml(emailInput.value.trim())}" readonly disabled>
                            </div>
                        </div>
                        ${categorySpecificRowHtml}
                        ${priorityLockedHtml}
                    </div>
                `;
            }

            // STEP 2 & STEP 3 LOCKED FORM FIELDS
            const activeBldgCard = document.querySelector('#buildingListContainer .building-card.active');
            const bldgName = activeBldgCard?.dataset.facilityName || summaryBuildingName.textContent || '---';
            const bldgCode = activeBldgCard?.dataset.facilityCode || summaryBuildingCode.textContent || '';
            const bldgDisplay = bldgCode ? `${bldgName} (${bldgCode})` : bldgName;

            const activeOfficeCard = document.querySelector('#officeListContainer .office-card.active');
            const offName = activeOfficeCard?.dataset.officeName || summaryOfficeName.textContent || '---';
            const offCode = activeOfficeCard?.dataset.officeCode || summaryOfficeCode.textContent || '';
            const offRoom = activeOfficeCard?.dataset.roomName || '';
            const offDisplay = `${offName}${offCode ? ' (' + offCode + ')' : ''}${offRoom ? ' — ' + offRoom : ''}`;

            const step2And3LockedHtml = `
                <div class="summary-section-box mb-0">
                    <div class="row g-2 g-sm-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-normal text-dark small">Selected Building</label>
                            <input type="text" class="form-control form-control-kiosk kiosk-locked-field" value="${escapeHtml(bldgDisplay)}" readonly disabled>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-normal text-dark small">Destination Office</label>
                            <input type="text" class="form-control form-control-kiosk kiosk-locked-field" value="${escapeHtml(offDisplay)}" readonly disabled>
                        </div>
                    </div>
                </div>
            `;

            // STEP 4 LOCKED FORM FIELDS (SELECTED TRANSACTIONS, SUB-TRANSACTIONS & INLINE ADDITIONAL DETAILS)
            const winInfo = getActiveSelectedWindowInfo();
            let concernFormBlocksHtml = '';

            selectedConcernIds.forEach(tid => {
                if (tid === 'OTHERS') {
                    const customVal = otherConcernText.value.trim();
                    concernFormBlocksHtml += `
                        <div class="p-3 bg-light rounded-3 border">
                            <label class="form-label fw-normal text-primary small">Others (Specified Transaction Concern)</label>
                            <textarea class="form-control form-control-kiosk kiosk-locked-field" rows="2" readonly disabled>${escapeHtml(customVal || 'General Concern')}</textarea>
                        </div>
                    `;
                    return;
                }

                const transObj = officeTransactionsMap[tid];
                if (!transObj) return;

                const hasSubs = Array.isArray(transObj.sub_concerns) && transObj.sub_concerns.length > 0;

                if (!hasSubs) {
                    const details = getEnteredDetailsForScope(`type_${tid}`);
                    const detailInputsHtml = details.length > 0 ? `
                        <div class="mt-3">
                            <div class="small text-primary mb-2"><i class="bi bi-ui-checks-grid me-1"></i>Additional Details</div>
                            <div class="row g-2 g-sm-3">
                                ${details.map(d => `
                                    <div class="col-12 col-md-6">
                                        <label class="form-label fw-normal text-dark small">${escapeHtml(d.label)}</label>
                                        <input type="text" class="form-control form-control-kiosk kiosk-locked-field" value="${escapeHtml(d.val)}" readonly disabled>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    ` : '';

                    concernFormBlocksHtml += `
                        <div class="p-3 bg-light rounded-3 border">
                            <div class="row g-2 g-sm-3">
                                <div class="col-12 col-md-9">
                                    <label class="form-label fw-normal text-primary small">Selected Transaction</label>
                                    <input type="text" class="form-control form-control-kiosk kiosk-locked-field text-primary" value="${escapeHtml(transObj.transaction_name)}" readonly disabled>
                                </div>
                                <div class="col-12 col-md-3">
                                    <label class="form-label fw-normal text-dark small">Est. Time</label>
                                    <input type="text" class="form-control form-control-kiosk kiosk-locked-field" value="${transObj.estimated_minutes || 5} mins" readonly disabled>
                                </div>
                            </div>
                            ${detailInputsHtml}
                        </div>
                    `;
                } else {
                    const pickedSubIds = selectedSubConcernsMap[tid] || [];
                    const subBlocksHtml = pickedSubIds.map(sid => {
                        const subObj = transObj.sub_concerns.find(s => Number(s.id) === Number(sid));
                        if (!subObj) return '';
                        const subDetails = getEnteredDetailsForScope(`sub_${sid}`);
                        const subDetailInputsHtml = subDetails.length > 0 ? `
                            <div class="mt-3">
                                <div class="small text-primary mb-2"><i class="bi bi-ui-checks-grid me-1"></i>Additional Details</div>
                                <div class="row g-2 g-sm-3">
                                    ${subDetails.map(d => `
                                        <div class="col-12 col-md-6">
                                            <label class="form-label fw-normal text-dark small">${escapeHtml(d.label)}</label>
                                            <input type="text" class="form-control form-control-kiosk kiosk-locked-field" value="${escapeHtml(d.val)}" readonly disabled>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        ` : '';

                        return `
                            <div class="mt-3 pt-3 border-top">
                                <div class="row g-2 g-sm-3">
                                    <div class="col-12 col-md-9">
                                        <label class="form-label fw-normal text-dark small">Specific Sub-Transaction</label>
                                        <input type="text" class="form-control form-control-kiosk kiosk-locked-field text-primary" value="${escapeHtml(subObj.sub_concern_name)}" readonly disabled>
                                    </div>
                                    <div class="col-12 col-md-3">
                                        <label class="form-label fw-normal text-dark small">Est. Time</label>
                                        <input type="text" class="form-control form-control-kiosk kiosk-locked-field" value="${subObj.estimated_minutes || 5} mins" readonly disabled>
                                    </div>
                                </div>
                                ${subDetailInputsHtml}
                            </div>
                        `;
                    }).join('');

                    concernFormBlocksHtml += `
                        <div class="p-3 bg-light rounded-3 border">
                            <div>
                                <label class="form-label fw-normal text-primary small">Selected Transaction</label>
                                <input type="text" class="form-control form-control-kiosk kiosk-locked-field text-primary" value="${escapeHtml(transObj.transaction_name)}" readonly disabled>
                            </div>
                            ${subBlocksHtml}
                        </div>
                    `;
                }
            });

            const step4LockedHtml = `
                <div class="summary-section-box mb-0">
                    <div class="row g-2 g-sm-3 mb-3">
                        <div class="col-12">
                            <label class="form-label fw-normal text-dark small">Assigned Service Window</label>
                            <input type="text" class="form-control form-control-kiosk kiosk-locked-field fw-bold text-primary" value="${escapeHtml(winInfo.name || 'Any Window')}" readonly disabled>
                        </div>
                    </div>
                    <div class="d-flex flex-column gap-3">
                        ${concernFormBlocksHtml}
                    </div>
                </div>
            `;

            step5LockedFormCont.innerHTML = step1LockedHtml + step2And3LockedHtml + step4LockedHtml;
        }

        function submitLogbookForm(triggerBtn) {
            if (!visitorRecordFound && manualRefIdInput && !manualIdRow?.classList.contains('d-none')) {
                referenceIdInput.value = manualRefIdInput.value.trim();
            } else if (referenceIdInput.value) {
                referenceIdInput.value = referenceIdInput.value.trim();
            }

            if (!validatePersonalInfoFields()) {
                return;
            }

            if (!validateStep4DynamicDetails()) {
                return;
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
                const titleEl = card.querySelector('.transaction-title-highlight');
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
                        const subName = b.querySelector('.sub-concern-title-text')?.textContent.trim() || b.textContent.trim();
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

            const fallbackWindowName = getActiveSelectedWindowInfo().name || 'Any Window';

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
                    document.getElementById('modalWindowName').textContent   = result.window_name || fallbackWindowName;
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