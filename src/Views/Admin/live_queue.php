<?php
# Core Config Dependencies
$db_connect  = $db_connect ?? null;
$g_user_role = $g_user_role ?? '';

# Mode Parameter Parsing
$queue_mode = $_GET['mode'] ?? 'all';

$mode_titles = [
    'all'           => 'Master Queue Monitor',
    'student_guest' => 'Students & Guests Monitor',
    'employee'      => 'Employee Queue Monitor'
];

$page_title_text = $mode_titles[$queue_mode] ?? $mode_titles['all'];

# Fetch All Offices for Initial State
$all_offices = [];
if ($db_connect) {
    $stmtOff = $db_connect->query("SELECT office_id, office_code, office_name, office_logo FROM office WHERE flag_status = 0 ORDER BY office_name ASC");
    if ($stmtOff) {
        while ($row = $stmtOff->fetch_assoc()) {
            $all_offices[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-100">

<head>
    <?php include_once ADMIN_META_DATA_PATH; ?>
    <?php include_once ADMIN_LINK_PATH; ?>
    
    <title>E-Eskuelog — <?= htmlspecialchars($page_title_text); ?></title>

    <style>
        /* LIGHT THEME (DEFAULT) - MODERN FROSTED GLASSMORPHISM */
        :root {
            /* CAMPUS TEMPLATE BACKGROUND WITH BLUE GRADIENT OVERLAY */
            --bg-wrapper: linear-gradient(135deg, rgba(11, 32, 117, 0.10) 0%, rgba(0, 10, 76, 0.25) 100%), url('<?= BASE_URL; ?>public/assets/main/css/bg-template.png');
            --bg-header: rgba(0, 16, 77, 0.4);
            --border-header: 1px solid rgba(255, 255, 255, 0.1);
            --header-title-color: #ffffff;
            
            /* GLASSMORPHISM PILLAR CARD STYLING (LIGHT FROSTED GLASS) */
            --bg-card: linear-gradient(135deg, rgba(255, 255, 255, 0.22) 0%, rgba(255, 255, 255, 0.08) 100%);
            --border-card: 1px solid rgba(255, 255, 255, 0.35);
            --card-text-color: #ffffff;
            --card-shadow: 0 20px 40px 0 rgba(0, 16, 77, 0.25), inset 0 1px 2px 0 rgba(255, 255, 255, 0.4);
            
            --pillar-header-border: rgba(255, 255, 255, 0.2);
            --office-name-color: #ffffff;
            --office-meta-color: #e2e8f0;
            
            --serving-bg: rgba(0, 16, 77, 0.35);
            --serving-border: 1px solid rgba(255, 255, 255, 0.22);
            --serving-label-color: #60a5fa;
            --serving-ticket-color: #ffffff;
            
            /* SOLID GREEN HIGHLIGHT & SHADOW GLOW */
            --calling-border: #22c55e;
            --calling-shadow: 0 0 35px rgba(34, 197, 94, 0.55), inset 0 0 15px rgba(34, 197, 94, 0.25);
            
            --priority-tag-bg: linear-gradient(135deg, rgba(217, 119, 6, 0.85) 0%, rgba(245, 158, 11, 0.95) 100%);
            --priority-tag-color: #ffffff;
            --priority-tag-border: 1px solid rgba(255, 255, 255, 0.3);
            --priority-tag-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
            
            --lineup-border-top: rgba(255, 255, 255, 0.2);
            --lineup-label-color: #cbd5e1;
            --lineup-pill-bg: rgba(255, 255, 255, 0.12);
            --lineup-pill-border: 1px solid rgba(255, 255, 255, 0.2);
            --lineup-pill-color: #ffffff;
            
            --lineup-pill-priority-bg: linear-gradient(135deg, rgba(217, 119, 6, 0.35) 0%, rgba(245, 158, 11, 0.45) 100%);
            --lineup-pill-priority-border: 1.5px solid #f59e0b;
            --lineup-pill-priority-color: #fef08a;
            
            --lineup-pill-empty-bg: transparent;
            --lineup-pill-empty-border: 1px dashed rgba(255, 255, 255, 0.25);
            --lineup-pill-empty-color: rgba(255, 255, 255, 0.4);
            
            --page-badge-bg: linear-gradient(135deg, rgba(30, 58, 138, 0.8) 0%, rgba(37, 99, 235, 0.9) 100%);
            --page-badge-color: #ffffff;
            --page-badge-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            
            /* DATETIME FOOTER COLORS */
            --datetime-bg: transparent;
            --datetime-border: none;
            --datetime-icon-color: #60a5fa;
            --datetime-text-color: #cbd5e1;
            --datetime-clock-color: #ffffff;
            
            --footer-bg: rgba(0, 16, 77, 0.4);
            --footer-border: 1px solid rgba(255, 255, 255, 0.1);

            /* MODERN SQUIRCLE ACTION BUTTONS */
            --btn-action-bg: rgba(255, 255, 255, 0.12);
            --btn-action-border: 1px solid rgba(255, 255, 255, 0.2);
            --btn-action-color: #ffffff;
            --btn-action-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            --btn-action-hover-bg: #2563eb;
            --btn-action-hover-color: #ffffff;
            --btn-action-hover-border: 1px solid #3b82f6;
            --btn-action-hover-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        /* DARK THEME OVERRIDES - DARK GLASSMORPHISM */
        body.dark-mode {
            --bg-wrapper: linear-gradient(135deg, rgba(15, 23, 42, 0.75) 0%, rgba(15, 23, 42, 0.92) 100%), url('<?= BASE_URL; ?>public/assets/main/css/bg-template.png');
            --bg-header: rgba(0, 16, 77, 0.4);
            --border-header: 1px solid rgba(255, 255, 255, 0.1);
            --header-title-color: #ffffff;
            
            /* DARK GLASS PILLAR CARD STYLING */
            --bg-card: linear-gradient(135deg, rgba(30, 41, 59, 0.65) 0%, rgba(15, 23, 42, 0.8) 100%);
            --border-card: 1px solid rgba(255, 255, 255, 0.15);
            --card-text-color: #f8fafc;
            --card-shadow: 0 20px 40px 0 rgba(0, 0, 0, 0.45), inset 0 1px 1px 0 rgba(255, 255, 255, 0.2);
            
            --pillar-header-border: rgba(255, 255, 255, 0.1);
            --office-name-color: #ffffff;
            --office-meta-color: #94a3b8;
            
            --serving-bg: rgba(15, 23, 42, 0.75);
            --serving-border: 1px solid rgba(255, 255, 255, 0.12);
            --serving-label-color: #f59e0b;
            --serving-ticket-color: #ffffff;
            
            /* SOLID GREEN HIGHLIGHT & SHADOW GLOW */
            --calling-border: #22c55e;
            --calling-shadow: 0 0 35px rgba(34, 197, 94, 0.6), inset 0 0 15px rgba(34, 197, 94, 0.25);
            
            --priority-tag-bg: linear-gradient(135deg, #d97706 0%, #f59e0b 100%);
            --priority-tag-color: #ffffff;
            --priority-tag-border: 1px solid rgba(255, 255, 255, 0.2);
            --priority-tag-shadow: 0 2px 6px rgba(245, 158, 11, 0.4);
            
            --lineup-border-top: rgba(255, 255, 255, 0.1);
            --lineup-label-color: #94a3b8;
            --lineup-pill-bg: rgba(255, 255, 255, 0.06);
            --lineup-pill-border: 1px solid rgba(255, 255, 255, 0.12);
            --lineup-pill-color: #f1f5f9;
            
            --lineup-pill-priority-bg: linear-gradient(135deg, rgba(217, 119, 6, 0.25) 0%, rgba(245, 158, 11, 0.35) 100%);
            --lineup-pill-priority-border: 1.5px solid #f59e0b;
            --lineup-pill-priority-color: #fef08a;
            
            --lineup-pill-empty-bg: transparent;
            --lineup-pill-empty-border: 1px dashed rgba(255, 255, 255, 0.15);
            --lineup-pill-empty-color: #64748b;
            
            --page-badge-bg: linear-gradient(135deg, rgba(217, 119, 6, 0.8) 0%, rgba(245, 158, 11, 0.9) 100%);
            --page-badge-color: #ffffff;
            --page-badge-shadow: 0 3px 12px rgba(245, 158, 11, 0.35);
            
            --datetime-bg: transparent;
            --datetime-border: none;
            --datetime-icon-color: #f59e0b;
            --datetime-text-color: #94a3b8;
            --datetime-clock-color: #ffffff;
            
            --footer-bg: rgba(0, 16, 77, 0.4);
            --footer-border: 1px solid rgba(255, 255, 255, 0.1);

            /* MODERN SQUIRCLE ACTION BUTTONS - DARK MODE */
            --btn-action-bg: rgba(30, 41, 59, 0.8);
            --btn-action-border: 1px solid rgba(255, 255, 255, 0.15);
            --btn-action-color: #f8fafc;
            --btn-action-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            --btn-action-hover-bg: #2563eb;
            --btn-action-hover-color: #ffffff;
            --btn-action-hover-border: 1px solid #3b82f6;
            --btn-action-hover-shadow: 0 4px 16px rgba(37, 99, 235, 0.4);
        }

        body, html {
            height: 100%;
            margin: 0;
            padding: 0;
            background-color: #0f172a !important;
            font-family: 'Public Sans', system-ui, -apple-system, sans-serif;
            overflow: hidden;
            transition: background-color 0.3s ease;
        }

        /* FULLSCREEN MASTER WRAPPER WITH COVER BACKGROUND TEMPLATE */
        .master-screen-wrapper {
            position: relative;
            display: flex;
            flex-direction: column;
            height: 100vh;
            width: 100vw;
            padding: 0;
            margin: 0;
            box-sizing: border-box;
            background: var(--bg-wrapper);
            background-size: cover;
            background-position: center center;
            background-attachment: fixed;
            overflow: hidden;
            transition: background 0.3s ease;
        }

        /* HEADER BAR */
        .master-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background-color: rgba(0, 16, 77, 0.4) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 0.75rem 1.75rem;
            margin: 0;
            width: 100%;
            flex-shrink: 0;
            box-sizing: border-box;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            z-index: 10;
            transition: all 0.3s ease;
        }

        .master-title {
            font-size: 1.35rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            color: var(--header-title-color);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.85rem;
            transition: color 0.3s ease;
        }

        .title-icon-badge {
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
            width: 42px;
            height: 42px;
            border-radius: 0.65rem;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(37, 99, 235, 0.3);
        }

        /* RESTYLED MODERN SQUIRCLE ACTION BUTTONS */
        .header-action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 0.65rem;
            border: var(--btn-action-border);
            background: var(--btn-action-bg);
            color: var(--btn-action-color);
            box-shadow: var(--btn-action-shadow);
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            cursor: pointer;
            outline: none;
        }

        .header-action-btn:hover {
            background: var(--btn-action-hover-bg);
            color: var(--btn-action-hover-color);
            border: var(--btn-action-hover-border);
            transform: translateY(-2px);
            box-shadow: var(--btn-action-hover-shadow);
        }

        .header-action-btn:active {
            transform: translateY(0);
        }

        /* MAIN CONTENT AREA WITH INTEGRATED PAGE BADGE */
        .main-display-area {
            display: flex;
            flex-direction: column;
            flex-grow: 1;
            overflow: hidden;
            padding: 0.85rem 1.25rem 3.5rem 1.25rem;
            box-sizing: border-box;
        }

        /* DYNAMICALLY CENTERED HORIZONTAL PILLAR GRID */
        .pillar-grid-container {
            display: flex;
            flex-direction: row;
            gap: 1.15rem;
            flex-grow: 1;
            align-items: stretch;
            justify-content: center;
            overflow: hidden;
            box-sizing: border-box;
            transition: opacity 0.3s ease-in-out;
        }

        .pillar-grid-container.fade-out {
            opacity: 0;
        }

        /* PILLAR BANNER CARD - HIGH-END GLASSMORPHISM CARD STRUCTURE */
        .office-pillar-card {
            flex: 1 1 280px;
            min-width: 240px;
            max-width: 340px;
            background: var(--bg-card);
            border: var(--border-card);
            border-radius: 1.25rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 1.15rem;
            box-shadow: var(--card-shadow);
            backdrop-filter: blur(16px) saturate(160%);
            -webkit-backdrop-filter: blur(16px) saturate(160%);
            transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            overflow: hidden;
            height: 100%;
            box-sizing: border-box;
        }

        .office-pillar-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 40%;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.08) 0%, rgba(255, 255, 255, 0) 100%);
            pointer-events: none;
            border-top-left-radius: 1.25rem;
            border-top-right-radius: 1.25rem;
        }

        /* ACTIVE CALLING HIGHLIGHT (GREEN BORDER WITH INTENSE GLASS GLOW) */
        .office-pillar-card.is-calling {
            border: 2.5px solid var(--calling-border) !important;
            box-shadow: var(--calling-shadow) !important;
            animation: none !important;
        }

        /* PILLAR HEADER SECTION - FIXED HEIGHT LOCK FOR UNIFORM ALIGNMENT */
        .pillar-header {
            text-align: center;
            border-bottom: var(--pillar-header-border);
            padding-bottom: 0.65rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            flex-shrink: 0;
            height: 124px;
            box-sizing: border-box;
            position: relative;
            z-index: 2;
        }

        .pillar-logo-img {
            width: 46px;
            height: 46px;
            object-fit: contain;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.95);
            border: 2px solid rgba(255, 255, 255, 0.4);
            padding: 2px;
            margin: 0 auto 0.35rem auto;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
            display: block;
            flex-shrink: 0;
        }

        .pillar-office-name {
            font-size: 0.95rem;
            font-weight: 800;
            color: var(--office-name-color);
            margin: 0;
            line-height: 1.25;
            text-transform: uppercase;
            word-break: break-word;
            text-align: center;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            height: 2.5em;
            width: 100%;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
        }

        .pillar-office-meta {
            font-size: 0.75rem;
            color: var(--office-meta-color);
            margin-top: 0.25rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            line-height: 1;
        }

        /* NOW SERVING FOCUS BLOCK - FROSTED GLASS INNER CAPSULE */
        .pillar-serving-block {
            text-align: center;
            padding: 0.85rem 0.5rem;
            background: var(--serving-bg);
            border-radius: 0.95rem;
            border: var(--serving-border);
            margin: 0.55rem 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            flex-grow: 1;
            min-height: 165px;
            box-sizing: border-box;
            position: relative;
            z-index: 2;
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            box-shadow: inset 0 1px 1px rgba(255, 255, 255, 0.15), 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .serving-label {
            font-size: 0.82rem;
            font-weight: 800;
            letter-spacing: 0.14em;
            color: var(--serving-label-color);
            text-transform: uppercase;
            margin-bottom: 0.15rem;
        }

        .serving-ticket-number {
            font-size: clamp(1.85rem, 2.7vw, 2.75rem);
            font-weight: 900;
            color: var(--serving-ticket-color);
            line-height: 1;
            letter-spacing: 0.02em;
            margin: 0.35rem 0;
            white-space: nowrap;
            overflow: visible;
            display: block;
            width: 100%;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        }

        .priority-badge-container {
            height: 26px;
            min-height: 26px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 0.15rem;
        }

        .priority-badge-tag {
            background: var(--priority-tag-bg);
            color: var(--priority-tag-color);
            border: var(--priority-tag-border);
            box-shadow: var(--priority-tag-shadow);
            font-weight: 800;
            font-size: 0.72rem;
            padding: 0.28rem 0.65rem;
            border-radius: 9999px;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        /* UP NEXT WAITING LINEUP BLOCK */
        .pillar-lineup-block {
            border-top: var(--lineup-border-top);
            padding-top: 0.55rem;
            padding-bottom: 0.1rem;
            text-align: center;
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            position: relative;
            z-index: 2;
        }

        .lineup-label {
            font-size: 0.75rem;
            font-weight: 800;
            color: var(--lineup-label-color);
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 0.4rem;
            display: block;
        }

        .lineup-pills-stack {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }

        .lineup-pill {
            background: var(--lineup-pill-bg);
            border: var(--lineup-pill-border);
            color: var(--lineup-pill-color);
            font-weight: 800;
            font-size: 0.92rem;
            padding: 0.35rem 0.65rem;
            border-radius: 0.55rem;
            letter-spacing: 0.05em;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
            white-space: nowrap;
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
        }

        .lineup-pill.is-priority {
            background: var(--lineup-pill-priority-bg) !important;
            border: var(--lineup-pill-priority-border) !important;
            color: var(--lineup-pill-priority-color) !important;
            font-weight: 900;
        }

        .lineup-pill.empty-pill {
            background: var(--lineup-pill-empty-bg);
            border: var(--lineup-pill-empty-border);
            color: var(--lineup-pill-empty-color);
            font-weight: 600;
            opacity: 0.4;
        }

        .waiting-count-footer {
            font-size: 0.75rem;
            color: var(--office-meta-color);
            margin-top: 0.45rem;
            font-weight: 700;
            height: 18px;
            line-height: 18px;
        }

        /* PAGE ROTATION BADGE CONTAINER INSIDE MAIN AREA */
        .page-badge-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 0.5rem 0 0.2rem 0;
            flex-shrink: 0;
        }

        .page-badge-pill {
            background: var(--page-badge-bg);
            color: var(--page-badge-color);
            font-size: 0.85rem;
            font-weight: 800;
            padding: 0.4rem 1.25rem;
            border-radius: 9999px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            box-shadow: var(--page-badge-shadow);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            transition: all 0.3s ease;
        }

        /* BOTTOM FOOTER BAR WITH ABSOLUTE POSITIONING */
        .bottom-pagination-bar {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding: 0.45rem 1.5rem;
            background-color: rgba(0, 16, 77, 0.4) !important;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            flex-shrink: 0;
            width: 100%;
            box-sizing: border-box;
            z-index: 100;
            transition: all 0.3s ease;
        }

        /* CLEANED DATETIME CAPSULE BLOCK */
        .footer-datetime-block {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--datetime-bg);
            border: var(--datetime-border);
            padding: 0.35rem 0.5rem;
            box-shadow: none;
            transition: all 0.3s ease;
        }

        .footer-datetime-icon {
            color: var(--datetime-icon-color);
            font-size: 1rem;
        }

        .digital-date-text {
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--datetime-text-color);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .datetime-divider {
            color: rgba(255, 255, 255, 0.4);
            font-weight: 400;
            font-size: 0.85rem;
            margin: 0 0.1rem;
        }

        .digital-clock-text {
            font-size: 0.9rem;
            font-weight: 800;
            color: var(--datetime-clock-color);
            letter-spacing: 0.04em;
            font-variant-numeric: tabular-nums;
        }

        .empty-active-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            color: var(--office-meta-color);
            text-align: center;
        }

        /* Responsive adjustments for mobile/portrait screens */
        @media (max-width: 768px) {
            .pillar-grid-container {
                flex-direction: column;
                overflow-y: auto;
            }

            .office-pillar-card {
                max-width: 100%;
                min-height: 280px;
            }

            .bottom-pagination-bar {
                justify-content: center;
            }
        }
    </style>
</head>

<body>

    <div class="master-screen-wrapper">

        <!-- HEADER BAR WITH DYNAMIC TITLE AND MODERN ACTION BUTTONS -->
        <header class="master-header">
            <h1 class="master-title">
                <span class="title-icon-badge">
                    <i class="bi bi-display-fill text-white fs-4"></i>
                </span>
                <span>E-ESKUELOG — <?= strtoupper(htmlspecialchars($page_title_text)); ?></span>
            </h1>

            <div class="d-flex align-items-center gap-2">
                <!-- DARK / LIGHT MODE TOGGLE BUTTON -->
                <button type="button" class="header-action-btn" id="btnToggleTheme" title="Toggle Dark/Light Theme">
                    <i class="bi bi-moon-stars-fill fs-5" id="themeIcon"></i>
                </button>

                <!-- FULLSCREEN TOGGLE BUTTON -->
                <button type="button" class="header-action-btn" id="btnToggleFullscreen" title="Toggle Fullscreen">
                    <i class="bi bi-fullscreen fs-5"></i>
                </button>
            </div>
        </header>

        <!-- MAIN CONTENT AREA CONTAINING PILLAR GRID & PAGE BADGE -->
        <div class="main-display-area">
            <!-- MAIN HORIZONTAL PILLAR GRID -->
            <main class="pillar-grid-container" id="pillarGridContainer">
                <div class="empty-active-state" id="emptyStateDisplay">
                    <i class="bi bi-clock-history display-1 mb-3 opacity-50"></i>
                    <h3 class="fw-bold mb-1">ALL COUNTERS STANDBY</h3>
                    <p class="small opacity-75">Active queue tickets will appear in real-time as visitors are called.</p>
                </div>
            </main>

            <!-- PAGE BADGE INSIDE MAIN CONTAINER WITH SPACING (HIDDEN WHEN <= 1 PAGE) -->
            <div class="page-badge-wrapper d-none" id="pageBadgeWrapper">
                <span class="page-badge-pill" id="pageRotationBadge">PAGE 1 / 1</span>
            </div>
        </div>

        <!-- BOTTOM FOOTER BAR WITH ABSOLUTE POSITIONING -->
        <footer class="bottom-pagination-bar">
            <div class="footer-datetime-block">
                <i class="bi bi-calendar3 footer-datetime-icon"></i>
                <span class="digital-date-text" id="digitalDate">-- --- ----</span>
                <span class="datetime-divider">|</span>
                <span class="digital-clock-text" id="digitalClock">--:--:-- AM</span>
            </div>
        </footer>

    </div>

    <?php include_once ADMIN_SCRIPT_PATH; ?>

<script>
    (function() {
        // Dynamic Monitor Mode Configuration
        const QUEUE_MODE           = <?= json_encode($queue_mode); ?>;
        const ITEMS_PER_PAGE       = 5; // Display max 5 offices per page
        const ROTATION_INTERVAL_MS = 5000; // Rotate page every 5 seconds

        // DOM Handles
        const digitalDate         = document.getElementById('digitalDate');
        const digitalClock        = document.getElementById('digitalClock');
        const pageRotationBadge   = document.getElementById('pageRotationBadge');
        const pageBadgeWrapper    = document.getElementById('pageBadgeWrapper');
        const pillarGridContainer = document.getElementById('pillarGridContainer');
        const emptyStateDisplay   = document.getElementById('emptyStateDisplay');
        const btnToggleFullscreen = document.getElementById('btnToggleFullscreen');
        const btnToggleTheme      = document.getElementById('btnToggleTheme');
        const themeIcon           = document.getElementById('themeIcon');

        const totalOfficesCount   = <?= count($all_offices); ?>;
        let calledTicketsHistory  = new Set();
        let cachedActiveOffices   = [];
        let currentPageIndex      = 0;
        let rotationTimer         = null;

        // Theme Toggle Handler (Light vs Dark Mode with LocalStorage Persistence)
        function applyTheme(isDark) {
            if (isDark) {
                document.body.classList.add('dark-mode');
                themeIcon.className = 'bi bi-sun-fill fs-5 text-warning';
            } else {
                document.body.classList.remove('dark-mode');
                themeIcon.className = 'bi bi-moon-stars-fill fs-5';
            }
        }

        // Initialize saved theme (default to Light mode)
        const savedTheme = localStorage.getItem('queue_monitor_theme');
        applyTheme(savedTheme === 'dark');

        if (btnToggleTheme) {
            btnToggleTheme.addEventListener('click', function() {
                const isCurrentlyDark = document.body.classList.contains('dark-mode');
                const newDarkState = !isCurrentlyDark;
                applyTheme(newDarkState);
                localStorage.setItem('queue_monitor_theme', newDarkState ? 'dark' : 'light');
            });
        }

        // Audio Queue & Voice Setup
        let audioQueue            = [];
        let isSpeaking            = false;
        let singleFemaleVoice     = null;

        // 1. FEMALE VOICE DETECTION SELECTION
        function getBestFemaleVoice() {
            if (!('speechSynthesis' in window)) return null;
            const voices = window.speechSynthesis.getVoices();
            if (!voices || voices.length === 0) return null;

            const femaleNames = ["zira", "janny", "jenny", "samantha", "siri", "victoria", "karen", "aria", "ava", "emma", "hazel", "susan", "female", "google us english"];
            const maleNames   = ["david", "mark", "george", "james", "richard", "male", "guy", "stefan"];

            let selected = voices.find(v => v.lang.startsWith('en') && femaleNames.some(fn => v.name.toLowerCase().includes(fn)));
            if (!selected) {
                selected = voices.find(v => v.lang.startsWith('en') && !maleNames.some(mn => v.name.toLowerCase().includes(mn)));
            }
            return selected || voices.find(v => v.lang.startsWith('en')) || voices[0];
        }

        function loadVoices() { 
            singleFemaleVoice = getBestFemaleVoice(); 
        }

        if ('speechSynthesis' in window) {
            window.speechSynthesis.onvoiceschanged = loadVoices;
            loadVoices();
        }

        // 2. BELL DING CHIME
        function playBellDing(callback) {
            try {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                if (!AudioContext) {
                    if (callback) callback();
                    return;
                }
                const ctx = new AudioContext();

                // Tone 1: E5 (659.25 Hz)
                const osc1 = ctx.createOscillator();
                const gain1 = ctx.createGain();
                osc1.type = 'sine';
                osc1.frequency.setValueAtTime(659.25, ctx.currentTime);
                gain1.gain.setValueAtTime(0.3, ctx.currentTime);
                gain1.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 1.2);

                osc1.connect(gain1);
                gain1.connect(ctx.destination);
                osc1.start(ctx.currentTime);
                osc1.stop(ctx.currentTime + 1.2);

                // Tone 2: G5 (783.99 Hz) at +0.25s
                const osc2 = ctx.createOscillator();
                const gain2 = ctx.createGain();
                osc2.type = 'sine';
                osc2.frequency.setValueAtTime(783.99, ctx.currentTime + 0.25);
                gain2.gain.setValueAtTime(0.35, ctx.currentTime + 0.25);
                gain2.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 1.6);

                osc2.connect(gain2);
                gain2.connect(ctx.destination);
                osc2.start(ctx.currentTime + 0.25);
                osc2.stop(ctx.currentTime + 1.6);

                setTimeout(() => {
                    if (callback) callback();
                }, 500);

            } catch (e) {
                console.warn("Audio Context blocked until page interaction.", e);
                if (callback) callback();
            }
        }

        // 3. DYNAMIC DATE & DIGITAL CLOCK UPDATER
        function updateDateTime() {
            const now = new Date();
            const options = { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' };
            digitalDate.textContent = now.toLocaleDateString('en-US', options);
            digitalClock.textContent = now.toLocaleTimeString('en-US', { hour12: true, hour: '2-digit', minute: '2-digit', second: '2-digit' });
        }
        setInterval(updateDateTime, 1000);
        updateDateTime();

        // 4. FULLSCREEN TOGGLE
        if (btnToggleFullscreen) {
            btnToggleFullscreen.addEventListener('click', function() {
                if (!document.fullscreenElement) {
                    document.documentElement.requestFullscreen();
                } else {
                    document.exitFullscreen();
                }
            });
        }

        // 5. FETCH LIVE QUEUE DATA & CHECK FOR NEW CALLS / RECALLS
        function fetchLiveQueueData() {
            const formData = new FormData();
            formData.append('action', 'fetch_master_queue');
            formData.append('queue_mode', QUEUE_MODE); // Forward mode filter to backend

            fetch('<?= BASE_URL; ?>admin/ajax/queue', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(result => {
                if (result.success && result.data) {
                    cachedActiveOffices = result.data.active_offices || [];
                    
                    if (cachedActiveOffices.length === 0) {
                        renderEmptyState();
                    } else {
                        let targetPageIndex = -1;

                        cachedActiveOffices.forEach((office, index) => {
                            const isCalling = office.now_serving && office.now_serving.status === 'CALLED';
                            
                            if (isCalling && office.now_serving.ticket_number) {
                                // Create unique call key combining ticket number & call timestamp
                                const callKey = `${office.now_serving.ticket_number}_${office.now_serving.called_at || ''}`;

                                if (!calledTicketsHistory.has(callKey)) {
                                    calledTicketsHistory.add(callKey);
                                    
                                    queueVoiceCallout(office.now_serving.ticket_number);

                                    targetPageIndex = Math.floor(index / ITEMS_PER_PAGE);
                                }
                            }
                        });

                        if (targetPageIndex !== -1 && targetPageIndex !== currentPageIndex) {
                            currentPageIndex = targetPageIndex;
                            renderCurrentPage(true);
                            startPageRotation();
                        } else {
                            renderCurrentPage(false);
                        }
                    }
                }
            })
            .catch(err => console.error("Live Queue Fetch Error:", err));
        }

        // 6. RENDER CURRENT PAGE
        function renderCurrentPage(animate = false) {
            if (cachedActiveOffices.length === 0) return;

            const totalPages = Math.ceil(cachedActiveOffices.length / ITEMS_PER_PAGE);
            if (currentPageIndex >= totalPages) currentPageIndex = 0;

            // Activate and display page badge ONLY when totalPages > 1
            if (totalPages > 1) {
                pageRotationBadge.textContent = `PAGE ${currentPageIndex + 1} / ${totalPages}`;
                if (pageBadgeWrapper) pageBadgeWrapper.classList.remove('d-none');
            } else {
                if (pageBadgeWrapper) pageBadgeWrapper.classList.add('d-none');
            }

            const start = currentPageIndex * ITEMS_PER_PAGE;
            const end   = start + ITEMS_PER_PAGE;
            const pageItems = cachedActiveOffices.slice(start, end);

            const buildPillars = () => {
                pillarGridContainer.innerHTML = '';

                pageItems.forEach(office => {
                    const pillar = document.createElement('div');
                    const isCalling = office.now_serving && office.now_serving.status === 'CALLED';
                    
                    pillar.className = `office-pillar-card ${isCalling ? 'is-calling' : ''}`;

                    let lineupHtml = '';
                    const upcoming = office.up_next || [];
                    for (let i = 0; i < 4; i++) {
                        if (upcoming[i]) {
                            const isPri = upcoming[i].is_priority == 1;
                            const priClass = isPri ? 'is-priority' : '';
                            const priTag = isPri ? '★ ' : '';
                            lineupHtml += `<div class="lineup-pill ${priClass}">${priTag}${upcoming[i].ticket_number}</div>`;
                        } else {
                            lineupHtml += `<div class="lineup-pill empty-pill">---</div>`;
                        }
                    }

                    const logoHtml = office.office_logo 
                        ? `<img src="<?= BASE_URL; ?>${office.office_logo}" class="pillar-logo-img" alt="Logo">`
                        : `<div class="pillar-logo-img d-flex align-items-center justify-content-center text-primary fw-bold fs-4"><i class="bi bi-building"></i></div>`;

                    pillar.innerHTML = `
                        <div class="pillar-header">
                            ${logoHtml}
                            <h2 class="pillar-office-name">${office.office_name}</h2>
                            <div class="pillar-office-meta">${office.office_code}</div>
                        </div>

                        <div class="pillar-serving-block">
                            <span class="serving-label">NOW SERVING</span>
                            <div class="serving-ticket-number">${office.now_serving ? office.now_serving.ticket_number : '---'}</div>
                            <div class="priority-badge-container">
                                ${office.now_serving && office.now_serving.is_priority ? '<span class="priority-badge-tag">★ PRIORITY</span>' : ''}
                            </div>
                        </div>

                        <div class="pillar-lineup-block">
                            <span class="lineup-label">UP NEXT</span>
                            <div class="lineup-pills-stack">
                                ${lineupHtml}
                            </div>
                            <div class="waiting-count-footer">(${office.waiting_count || 0} Waiting)</div>
                        </div>
                    `;

                    pillarGridContainer.appendChild(pillar);
                });
            };

            if (animate) {
                pillarGridContainer.classList.add('fade-out');
                setTimeout(() => {
                    buildPillars();
                    pillarGridContainer.classList.remove('fade-out');
                }, 150);
            } else {
                buildPillars();
            }
        }

        // 7. RENDER EMPTY STATE
        function renderEmptyState() {
            if (pageBadgeWrapper) pageBadgeWrapper.classList.add('d-none');
            pillarGridContainer.innerHTML = '';
            pillarGridContainer.appendChild(emptyStateDisplay);
            emptyStateDisplay.classList.remove('d-none');
        }

        // 8. AUTO-PAGE ROTATION TIMER (PAUSES WHILE SPEAKING)
        function startPageRotation() {
            if (rotationTimer) clearInterval(rotationTimer);
            rotationTimer = setInterval(() => {
                if (isSpeaking || audioQueue.length > 0) {
                    return; // Pause rotation while announcements are playing
                }

                if (cachedActiveOffices.length > ITEMS_PER_PAGE) {
                    const totalPages = Math.ceil(cachedActiveOffices.length / ITEMS_PER_PAGE);
                    currentPageIndex = (currentPageIndex + 1) % totalPages;
                    renderCurrentPage(true);
                }
            }, ROTATION_INTERVAL_MS);
        }

        // 9. VOICE CALLOUT QUEUE PROCESSOR WITH PAUSES
        function queueVoiceCallout(rawTicketNumber) {
            // Converts ticket number into letter/digit units separated by commas
            const spokenTicket = rawTicketNumber
                .split('')
                .map(char => (char === '-' ? ' ' : char))
                .join(', ');

            // Added ellipses (...) for explicit breathing/cadence pauses
            const phrase = `Now calling... ${spokenTicket}... you may now proceed to the office.`;
            
            audioQueue.push(phrase);
            processAudioQueue();
        }

        function processAudioQueue() {
            if (isSpeaking || audioQueue.length === 0 || !('speechSynthesis' in window)) return;

            isSpeaking = true;
            const nextText = audioQueue.shift();

            // Step A: Play Bell Chime
            playBellDing(() => {
                // Step B: Speak Announcement
                if (!singleFemaleVoice) loadVoices();

                const utterance = new SpeechSynthesisUtterance(nextText);
                utterance.rate = 0.88;
                utterance.pitch = 1.1;
                utterance.lang = 'en-US';
                if (singleFemaleVoice) utterance.voice = singleFemaleVoice;

                utterance.onend = function() {
                    isSpeaking = false;
                    processAudioQueue(); // Process next queued ticket callout
                };

                utterance.onerror = function() {
                    isSpeaking = false;
                    processAudioQueue();
                };

                window.speechSynthesis.speak(utterance);
            });
        }

        // 10. INITIALIZE LOOPS
        setInterval(fetchLiveQueueData, 3000);
        fetchLiveQueueData();
        startPageRotation();

    })();
</script>
</body>
</html>