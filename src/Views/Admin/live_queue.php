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
        /* LIGHT THEME (DEFAULT) - HIGH CONTRAST FROSTED SLATE BLUE GLASS */
        :root {
            /* CAMPUS TEMPLATE BACKGROUND WITH BLUE GRADIENT OVERLAY */
            --bg-wrapper: linear-gradient(135deg, rgba(11, 32, 117, 0.25) 0%, rgba(0, 10, 76, 0.45) 100%), url('<?= BASE_URL; ?>public/assets/main/css/bg-template.png');
            --bg-header: rgba(0, 16, 77, 0.88);
            --border-header: 1px solid rgba(255, 255, 255, 0.15);
            --header-title-color: #ffffff;

            /* HIGH CONTRAST FROSTED DEEP-BLUE GLASS CARD */
            --bg-card: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%);
            --border-card: 1px solid rgba(255, 255, 255, 0.22);
            --card-text-color: #ffffff;
            --card-shadow: 0 16px 36px rgba(0, 10, 50, 0.45);

            --pillar-header-border: rgba(255, 255, 255, 0.18);
            --office-name-color: #ffffff;
            --office-meta-color: #93c5fd;

            --serving-bg: rgba(15, 23, 42, 0.75);
            --serving-border: 1px solid rgba(59, 130, 246, 0.4);
            --serving-label-color: #60a5fa;
            --serving-ticket-color: #ffffff;

            /* SOLID GREEN HIGHLIGHT & SHADOW GLOW */
            --calling-border: #22c55e;
            --calling-shadow: 0 0 35px rgba(34, 197, 94, 0.6);

            --priority-tag-bg: linear-gradient(135deg, #d97706 0%, #f59e0b 100%);
            --priority-tag-color: #ffffff;
            --priority-tag-border: none;
            --priority-tag-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);

            --lineup-border-top: rgba(255, 255, 255, 0.18);
            --lineup-label-color: #93c5fd;
            --lineup-pill-bg: rgba(255, 255, 255, 0.12);
            --lineup-pill-border: 1px solid rgba(255, 255, 255, 0.22);
            --lineup-pill-color: #ffffff;

            --lineup-pill-priority-bg: linear-gradient(135deg, rgba(217, 119, 6, 0.4) 0%, rgba(245, 158, 11, 0.5) 100%);
            --lineup-pill-priority-border: 1.5px solid #f59e0b;
            --lineup-pill-priority-color: #fef08a;

            --lineup-pill-empty-bg: rgba(15, 23, 42, 0.3);
            --lineup-pill-empty-border: 1px dashed rgba(255, 255, 255, 0.25);
            --lineup-pill-empty-color: #94a3b8;

            --page-badge-bg: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
            --page-badge-color: #ffffff;
            --page-badge-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);

            /* DATETIME FOOTER COLORS */
            --datetime-bg: transparent;
            --datetime-border: none;
            --datetime-icon-color: #60a5fa;
            --datetime-text-color: #cbd5e1;
            --datetime-clock-color: #ffffff;

            --footer-bg: rgba(0, 16, 77, 0.85);
            --footer-border: 1px solid rgba(255, 255, 255, 0.15);

            /* MODERN SQUIRCLE ACTION BUTTONS */
            --btn-action-bg: rgba(255, 255, 255, 0.14);
            --btn-action-border: 1px solid rgba(255, 255, 255, 0.22);
            --btn-action-color: #ffffff;
            --btn-action-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            --btn-action-hover-bg: #2563eb;
            --btn-action-hover-color: #ffffff;
            --btn-action-hover-border: 1px solid #3b82f6;
            --btn-action-hover-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        /* DARK THEME OVERRIDES */
        body.dark-mode {
            --bg-wrapper: linear-gradient(135deg, rgba(10, 15, 28, 0.92) 0%, rgba(15, 23, 42, 0.98) 100%), url('<?= BASE_URL; ?>public/assets/main/css/bg-template.png');
            --bg-header: rgba(0, 16, 77, 0.90);
            --border-header: 1px solid rgba(255, 255, 255, 0.15);
            --header-title-color: #ffffff;

            --bg-card: linear-gradient(135deg, #0a0f1d 0%, #172554 100%);
            --border-card: 1px solid rgba(255, 255, 255, 0.20);
            --card-text-color: #ffffff;
            --card-shadow: 0 16px 36px rgba(0, 0, 0, 0.65);

            --pillar-header-border: rgba(255, 255, 255, 0.18);
            --office-name-color: #ffffff;
            --office-meta-color: #93c5fd;

            --serving-bg: rgba(15, 23, 42, 0.75);
            --serving-border: 1px solid rgba(59, 130, 246, 0.4);
            --serving-label-color: #60a5fa;
            --serving-ticket-color: #ffffff;

            --calling-border: #22c55e;
            --calling-shadow: 0 0 35px rgba(34, 197, 94, 0.65);

            --priority-tag-bg: linear-gradient(135deg, #d97706 0%, #f59e0b 100%);
            --priority-tag-color: #ffffff;
            --priority-tag-border: none;
            --priority-tag-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);

            --lineup-border-top: rgba(255, 255, 255, 0.18);
            --lineup-label-color: #93c5fd;
            --lineup-pill-bg: rgba(255, 255, 255, 0.12);
            --lineup-pill-border: 1px solid rgba(255, 255, 255, 0.22);
            --lineup-pill-color: #ffffff;

            --lineup-pill-priority-bg: linear-gradient(135deg, rgba(217, 119, 6, 0.4) 0%, rgba(245, 158, 11, 0.5) 100%);
            --lineup-pill-priority-border: 1.5px solid #f59e0b;
            --lineup-pill-priority-color: #fef08a;

            --lineup-pill-empty-bg: rgba(15, 23, 42, 0.3);
            --lineup-pill-empty-border: 1px dashed rgba(255, 255, 255, 0.25);
            --lineup-pill-empty-color: #94a3b8;

            --page-badge-bg: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
            --page-badge-color: #ffffff;
            --page-badge-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);

            --datetime-bg: transparent;
            --datetime-border: none;
            --datetime-icon-color: #60a5fa;
            --datetime-text-color: #cbd5e1;
            --datetime-clock-color: #ffffff;

            --footer-bg: rgba(0, 16, 77, 0.90);
            --footer-border: 1px solid rgba(255, 255, 255, 0.15);

            --btn-action-bg: rgba(255, 255, 255, 0.14);
            --btn-action-border: 1px solid rgba(255, 255, 255, 0.22);
            --btn-action-color: #ffffff;
            --btn-action-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
            --btn-action-hover-bg: #2563eb;
            --btn-action-hover-color: #ffffff;
            --btn-action-hover-border: 1px solid #3b82f6;
            --btn-action-hover-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
        }

        body,
        html {
            height: 100%;
            margin: 0;
            padding: 0;
            background-color: #0f172a !important;
            font-family: 'Public Sans', system-ui, -apple-system, sans-serif;
            overflow: hidden;
            transition: background-color 0.3s ease;
        }

        /* FULLSCREEN MASTER WRAPPER */
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

        /* DYNAMIC RESPONSIVE HEADER BAR */
        .master-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background-color: var(--bg-header) !important;
            border-bottom: var(--border-header);
            padding: clamp(0.4rem, 1vh, 0.9rem) 1.75rem;
            margin: 0;
            width: 100%;
            flex-shrink: 0;
            box-sizing: border-box;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            z-index: 10;
            transition: background-color 0.3s ease;
            isolation: isolate;
        }

        /* DYNAMIC SCALING HEADER BANNER IMAGE */
        .header-banner-img {
            height: clamp(44px, 6.5vh, 85px);
            width: auto;
            max-width: calc(100vw - 140px);
            object-fit: contain;
            display: block;
        }

        /* ACTION BUTTONS */
        .header-action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: clamp(38px, 4.5vh, 52px);
            height: clamp(38px, 4.5vh, 52px);
            border-radius: 0.65rem;
            border: var(--btn-action-border);
            background: var(--btn-action-bg);
            color: var(--btn-action-color);
            box-shadow: var(--btn-action-shadow);
            transition: background 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
            cursor: pointer;
            outline: none;
        }

        .header-action-btn:hover {
            background: var(--btn-action-hover-bg);
            color: var(--btn-action-hover-color);
            border: var(--btn-action-hover-border);
            box-shadow: var(--btn-action-hover-shadow);
        }

        /* MAIN DISPLAY AREA */
        .main-display-area {
            display: flex;
            flex-direction: column;
            flex-grow: 1;
            overflow: hidden;
            padding: clamp(0.5rem, 1.2vh, 1.25rem) clamp(0.75rem, 1.5vw, 1.75rem) clamp(3.2rem, 5vh, 4.5rem) clamp(0.75rem, 1.5vw, 1.75rem);
            box-sizing: border-box;
            contain: paint;
        }

        /* HORIZONTAL PILLAR GRID */
        .pillar-grid-container {
            display: flex;
            flex-direction: row;
            gap: clamp(0.75rem, 1.2vw, 1.5rem);
            flex-grow: 1;
            align-items: stretch;
            justify-content: center;
            overflow: hidden;
            box-sizing: border-box;
            transition: opacity 0.35s cubic-bezier(0.4, 0, 0.2, 1), transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            will-change: opacity, transform;
        }

        .pillar-grid-container.fade-out {
            opacity: 0;
            transform: scale(0.97) translateY(4px);
        }

        /* DYNAMICALLY FLUID PILLAR CARD */
        .office-pillar-card {
            flex: 1 1 240px;
            min-width: 200px;
            max-width: 420px;
            min-height: 200px;
            background: var(--bg-card) !important;
            border: var(--border-card) !important;
            box-shadow: var(--card-shadow) !important;
            border-radius: clamp(1.25rem, 2vh, 2rem);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: clamp(0.75rem, 1.5vh, 1.4rem);
            height: 100%;
            box-sizing: border-box;
            position: relative;
            contain: paint;
            isolation: isolate;
            transition: box-shadow 0.3s ease, outline-color 0.3s ease;
        }

        .office-pillar-card::before {
            display: none !important;
        }

        .office-pillar-card.is-calling {
            outline: 3px solid var(--calling-border) !important;
            outline-offset: -3px;
            box-shadow: var(--calling-shadow) !important;
            animation: none !important;
        }

        /* PILLAR HEADER */
        .pillar-header {
            text-align: center;
            border-bottom: var(--pillar-header-border);
            padding-bottom: clamp(0.35rem, 0.8vh, 0.65rem);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            flex-shrink: 0;
            min-height: clamp(90px, 14vh, 140px);
            height: auto;
            box-sizing: border-box;
            position: relative;
            z-index: 2;
        }

        .pillar-logo-img {
            width: clamp(38px, 5vh, 58px);
            height: clamp(38px, 5vh, 58px);
            object-fit: contain;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.95);
            border: 2px solid rgba(255, 255, 255, 0.4);
            padding: 2px;
            margin: 0 auto clamp(0.2rem, 0.4vh, 0.4rem) auto;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .pillar-office-name {
            font-size: clamp(0.88rem, 1.1vw, 1.25rem);
            font-weight: 800;
            color: var(--office-name-color);
            margin: 0;
            line-height: 1.2;
            text-transform: uppercase;
            word-break: break-word;
            text-align: center;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            max-height: 2.5em;
            width: 100%;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.4);
        }

        .pillar-office-meta {
            font-size: clamp(0.7rem, 0.85vw, 0.9rem);
            color: var(--office-meta-color);
            margin-top: 0.2rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            line-height: 1;
        }

        /* DYNAMIC NOW SERVING BLOCK */
        .pillar-serving-block {
            text-align: center;
            padding: clamp(0.5rem, 1.2vh, 1rem) 0.5rem;
            background: var(--serving-bg);
            border-radius: clamp(1rem, 1.5vh, 1.5rem);
            border: var(--serving-border);
            margin: clamp(0.35rem, 0.8vh, 0.75rem) 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            flex-grow: 1;
            min-height: clamp(130px, 20vh, 240px);
            box-sizing: border-box;
            position: relative;
            z-index: 2;
            box-shadow: inset 0 1px 1px rgba(255, 255, 255, 0.15), 0 4px 15px rgba(0, 0, 0, 0.15);
        }

        .serving-label {
            font-size: clamp(0.75rem, 0.9vw, 1rem);
            font-weight: 800;
            letter-spacing: 0.14em;
            color: var(--serving-label-color);
            text-transform: uppercase;
            margin-bottom: 0.15rem;
        }

        .serving-ticket-number {
            font-size: clamp(2rem, 3.8vw, 4.2rem);
            font-weight: 900;
            color: var(--serving-ticket-color);
            line-height: 1;
            letter-spacing: 0.02em;
            margin: clamp(0.2rem, 0.5vh, 0.5rem) 0;
            white-space: nowrap;
            overflow: visible;
            display: block;
            width: 100%;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.4);
        }

        .priority-badge-container {
            height: clamp(22px, 3vh, 32px);
            min-height: clamp(22px, 3vh, 32px);
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
            font-size: clamp(0.65rem, 0.75vw, 0.85rem);
            padding: 0.25rem 0.65rem;
            border-radius: 9999px;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        /* UP NEXT WAITING LINEUP BLOCK */
        .pillar-lineup-block {
            border-top: var(--lineup-border-top);
            padding-top: clamp(0.35rem, 0.8vh, 0.65rem);
            padding-bottom: 0.1rem;
            text-align: center;
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            position: relative;
            z-index: 2;
        }

        .lineup-label {
            font-size: clamp(0.7rem, 0.8vw, 0.9rem);
            font-weight: 800;
            color: var(--lineup-label-color);
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: clamp(0.25rem, 0.5vh, 0.45rem);
            display: block;
        }

        .lineup-pills-stack {
            display: flex;
            flex-direction: column;
            gap: clamp(0.25rem, 0.5vh, 0.45rem);
        }

        .lineup-pill {
            background: var(--lineup-pill-bg);
            border: var(--lineup-pill-border);
            color: var(--lineup-pill-color);
            font-weight: 800;
            font-size: clamp(0.85rem, 1vw, 1.15rem);
            padding: 0.35rem 0.65rem;
            border-radius: 0.75rem;
            letter-spacing: 0.05em;
            height: clamp(30px, 4.2vh, 44px);
            display: flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
            white-space: nowrap;
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
            opacity: 0.6;
        }

        .waiting-count-footer {
            font-size: clamp(0.7rem, 0.8vw, 0.9rem);
            color: var(--office-meta-color);
            margin-top: clamp(0.25rem, 0.5vh, 0.5rem);
            font-weight: 700;
            height: 18px;
            line-height: 18px;
        }

        /* PAGE ROTATION BADGE */
        .page-badge-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 0.4rem 0 0.1rem 0;
            flex-shrink: 0;
        }

        .page-badge-pill {
            background: var(--page-badge-bg);
            color: var(--page-badge-color);
            font-size: clamp(0.78rem, 0.9vw, 1rem);
            font-weight: 800;
            padding: 0.35rem 1.25rem;
            border-radius: 9999px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            box-shadow: var(--page-badge-shadow);
            border: 1px solid rgba(255, 255, 255, 0.25);
            transition: all 0.3s ease;
        }

        /* BOTTOM FOOTER BAR */
        .bottom-pagination-bar {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding: clamp(0.35rem, 0.8vh, 0.6rem) 1.5rem;
            background-color: var(--footer-bg) !important;
            border-top: var(--footer-border);
            flex-shrink: 0;
            width: 100%;
            box-sizing: border-box;
            z-index: 100;
            transition: background-color 0.3s ease;
        }

        .footer-datetime-block {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--datetime-bg);
            border: var(--datetime-border);
            padding: 0.25rem 0.5rem;
            box-shadow: none;
            transition: all 0.3s ease;
        }

        .footer-datetime-icon {
            color: var(--datetime-icon-color);
            font-size: clamp(0.9rem, 1vw, 1.2rem);
        }

        .digital-date-text {
            font-size: clamp(0.78rem, 0.9vw, 1.05rem);
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
            font-size: clamp(0.85rem, 1vw, 1.15rem);
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

        /* FLOATING AUDIO ACTIVATION NOTICE */
        .audio-notice-banner {
            position: fixed;
            top: 4.8rem;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1050;
            background: rgba(15, 23, 42, 0.92) !important;
            border: 1px solid rgba(59, 130, 246, 0.5) !important;
            box-shadow: 0 12px 32px rgba(0, 10, 50, 0.5), 0 0 20px rgba(59, 130, 246, 0.3);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 1rem;
            padding: 0.75rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            max-width: 90vw;
            width: 480px;
            cursor: pointer;
            transition: opacity 0.4s ease, transform 0.4s ease;
            animation: pulseNotice 2.5s infinite ease-in-out;
        }

        .audio-notice-banner.dismissed {
            opacity: 0;
            transform: translate(-50%, -15px) scale(0.96);
            pointer-events: none;
        }

        @keyframes pulseNotice {
            0%, 100% { box-shadow: 0 12px 32px rgba(0, 10, 50, 0.5), 0 0 15px rgba(59, 130, 246, 0.25); }
            50% { box-shadow: 0 12px 32px rgba(0, 10, 50, 0.5), 0 0 30px rgba(59, 130, 246, 0.6); }
        }

        .audio-notice-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
        }
    </style>
</head>

<body>

    <div class="master-screen-wrapper">

        <!-- FLOATING AUDIO UNLOCK REMINDER -->
        <div class="audio-notice-banner" id="audioNoticeBanner" title="Click anywhere to enable voice announcements">
            <div class="audio-notice-icon">
                <i class="bi bi-volume-up-fill"></i>
            </div>
            <div class="flex-grow-1 min-w-0">
                <div class="d-flex align-items-center gap-2 mb-0.5">
                    <h6 class="fw-bold text-white mb-0 fs-6">Enable Live Audio</h6>
                    <span class="badge bg-primary text-white px-2 py-0.5 rounded-pill" style="font-size: 0.68rem; letter-spacing: 0.05em;">ACTION REQUIRED</span>
                </div>
                <p class="text-white-50 mb-0 small" style="font-size: 0.78rem; line-height: 1.3;">
                    Click anywhere on the screen to activate live voice announcements.
                </p>
            </div>
            <button type="button" class="btn btn-sm btn-primary rounded-3 px-3 fw-bold flex-shrink-0 shadow-sm">
                Enable <i class="bi bi-check-lg ms-1"></i>
            </button>
        </div>

        <!-- HEADER BAR WITH BANNER IMAGE AND ACTION BUTTONS -->
        <header class="master-header">
            <img src="<?= BASE_URL; ?>public/upload/images/logo/ccc-banner.webp" alt="CCC Banner" class="header-banner-img">

            <div class="d-flex align-items-center gap-2">
                <button type="button" class="header-action-btn" id="btnToggleTheme" title="Toggle Dark/Light Theme">
                    <i class="bi bi-moon-stars-fill fs-5" id="themeIcon"></i>
                </button>

                <button type="button" class="header-action-btn" id="btnToggleFullscreen" title="Toggle Fullscreen">
                    <i class="bi bi-fullscreen fs-5"></i>
                </button>
            </div>
        </header>

        <!-- MAIN CONTENT AREA -->
        <div class="main-display-area">
            <main class="pillar-grid-container" id="pillarGridContainer">
                <div class="empty-active-state" id="emptyStateDisplay">
                    <i class="bi bi-clock-history display-1 mb-3 opacity-50"></i>
                    <h3 class="fw-bold mb-1">ALL COUNTERS STANDBY</h3>
                    <p class="small opacity-75">Active queue tickets will appear in real-time as visitors are called.</p>
                </div>
            </main>

            <div class="page-badge-wrapper d-none" id="pageBadgeWrapper">
                <span class="page-badge-pill" id="pageRotationBadge">PAGE 1 / 1</span>
            </div>
        </div>

        <!-- BOTTOM FOOTER BAR -->
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
            const QUEUE_MODE = <?= json_encode($queue_mode); ?>;
            const ITEMS_PER_PAGE = 5;
            const ROTATION_INTERVAL_MS = 5000;
            const POST_CALL_HOLD_MS = 6000; // Freeze page during voice callouts

            const digitalDate = document.getElementById('digitalDate');
            const digitalClock = document.getElementById('digitalClock');
            const pageRotationBadge = document.getElementById('pageRotationBadge');
            const pageBadgeWrapper = document.getElementById('pageBadgeWrapper');
            const pillarGridContainer = document.getElementById('pillarGridContainer');
            const emptyStateDisplay = document.getElementById('emptyStateDisplay');
            const btnToggleFullscreen = document.getElementById('btnToggleFullscreen');
            const btnToggleTheme = document.getElementById('btnToggleTheme');
            const themeIcon = document.getElementById('themeIcon');

            const totalOfficesCount = <?= count($all_offices); ?>;
            let calledTicketsHistory = new Set();
            let cachedActiveOffices = [];
            let currentPageIndex = 0;
            let rotationTimer = null;
            let postCallHoldTimer = null;
            let isHoldingCall = false;
            let lastRenderedJson = '';

            function applyTheme(isDark) {
                if (isDark) {
                    document.body.classList.add('dark-mode');
                    themeIcon.className = 'bi bi-sun-fill fs-5 text-warning';
                } else {
                    document.body.classList.remove('dark-mode');
                    themeIcon.className = 'bi bi-moon-stars-fill fs-5';
                }
            }

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

            let audioQueue = []; // Holds objects: { phrase: string, pageIndex: number }
            let isSpeaking = false;
            let singleFemaleVoice = null;

            function getBestFemaleVoice() {
                if (!('speechSynthesis' in window)) return null;
                const voices = window.speechSynthesis.getVoices();
                if (!voices || voices.length === 0) return null;

                const femaleNames = ["zira", "janny", "jenny", "samantha", "siri", "victoria", "karen", "aria", "ava", "emma", "hazel", "susan", "female", "google us english"];
                const maleNames = ["david", "mark", "george", "james", "richard", "male", "guy", "stefan"];

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

            function playBellDing(callback) {
                try {
                    const AudioContext = window.AudioContext || window.webkitAudioContext;
                    if (!AudioContext) {
                        if (callback) callback();
                        return;
                    }
                    const ctx = new AudioContext();

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

            function updateDateTime() {
                const now = new Date();
                const options = {
                    weekday: 'short',
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric'
                };
                digitalDate.textContent = now.toLocaleDateString('en-US', options);
                digitalClock.textContent = now.toLocaleTimeString('en-US', {
                    hour12: true,
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
            }
            setInterval(updateDateTime, 1000);
            updateDateTime();

            if (btnToggleFullscreen) {
                btnToggleFullscreen.addEventListener('click', function() {
                    if (!document.fullscreenElement) {
                        document.documentElement.requestFullscreen();
                    } else {
                        document.exitFullscreen();
                    }
                });
            }

            function fetchLiveQueueData() {
                const formData = new FormData();
                formData.append('action', 'fetch_master_queue');
                formData.append('queue_mode', QUEUE_MODE);

                fetch('<?= BASE_URL; ?>admin/ajax/live-queue', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(result => {
                        if (result.success && result.data) {
                            cachedActiveOffices = result.data.active_offices || [];

                            if (cachedActiveOffices.length === 0) {
                                if (lastRenderedJson !== 'EMPTY_STATE') {
                                    renderEmptyState();
                                    lastRenderedJson = 'EMPTY_STATE';
                                }
                                return;
                            }

                            // AUTO-CLAMP PAGE INDEX IF TOTAL PAGES DECREASED
                            const totalPages = Math.ceil(cachedActiveOffices.length / ITEMS_PER_PAGE);
                            if (currentPageIndex >= totalPages) {
                                currentPageIndex = 0;
                            }

                            cachedActiveOffices.forEach((office, index) => {
                                const isCalling = office.now_serving && office.now_serving.status === 'CALLED';

                                if (isCalling && office.now_serving.ticket_number) {
                                    const callKey = `${office.now_serving.ticket_number}_${office.now_serving.called_at || ''}`;

                                    if (!calledTicketsHistory.has(callKey)) {
                                        calledTicketsHistory.add(callKey);
                                        const officePageIndex = Math.floor(index / ITEMS_PER_PAGE);
                                        queueVoiceCallout(office.now_serving.ticket_number, officePageIndex);
                                    }
                                }
                            });

                            const currentDataFingerprint = JSON.stringify(cachedActiveOffices);

                            if (currentDataFingerprint !== lastRenderedJson) {
                                renderCurrentPage(false);
                                lastRenderedJson = currentDataFingerprint;
                            }
                        }
                    })
                    .catch(err => console.error("Live Queue Fetch Error:", err));
            }

            function renderCurrentPage(animate = false) {
                if (cachedActiveOffices.length === 0) return;

                const totalPages = Math.ceil(cachedActiveOffices.length / ITEMS_PER_PAGE);
                if (currentPageIndex >= totalPages) currentPageIndex = 0;

                if (totalPages > 1) {
                    pageRotationBadge.textContent = `PAGE ${currentPageIndex + 1} / ${totalPages}`;
                    if (pageBadgeWrapper) pageBadgeWrapper.classList.remove('d-none');
                } else {
                    if (pageBadgeWrapper) pageBadgeWrapper.classList.add('d-none');
                }

                const start = currentPageIndex * ITEMS_PER_PAGE;
                const end = start + ITEMS_PER_PAGE;
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

                        const logoHtml = office.office_logo ?
                            `<img src="<?= BASE_URL; ?>${office.office_logo}" class="pillar-logo-img" alt="Logo" width="46" height="46">` :
                            `<div class="pillar-logo-img text-primary fw-bold fs-4"><i class="bi bi-building"></i></div>`;

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
                            <span class="lineup-label">NEXT IN LINE</span>
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

                        requestAnimationFrame(() => {
                            pillarGridContainer.classList.remove('fade-out');
                        });
                    }, 350);
                } else {
                    buildPillars();
                }
            }

            function renderEmptyState() {
                if (pageBadgeWrapper) pageBadgeWrapper.classList.add('d-none');
                pillarGridContainer.innerHTML = '';
                pillarGridContainer.appendChild(emptyStateDisplay);
                emptyStateDisplay.classList.remove('d-none');
            }

            function startPageRotation() {
                if (rotationTimer) clearInterval(rotationTimer);
                rotationTimer = setInterval(() => {
                    if (isSpeaking || isHoldingCall || audioQueue.length > 0) {
                        return;
                    }

                    const totalPages = Math.ceil(cachedActiveOffices.length / ITEMS_PER_PAGE);

                    if (totalPages > 1) {
                        currentPageIndex = (currentPageIndex + 1) % totalPages;
                        renderCurrentPage(true);
                    } else if (currentPageIndex !== 0) {
                        currentPageIndex = 0;
                        renderCurrentPage(true);
                    }
                }, ROTATION_INTERVAL_MS);
            }

            function queueVoiceCallout(rawTicketNumber, pageIndex) {
                const spokenTicket = rawTicketNumber
                    .split('')
                    .map(char => (char === '-' ? ' ' : char))
                    .join(' ');

                const phrase = `Now calling ${spokenTicket}, please proceed to the office. I repeat, ${spokenTicket}, please proceed to the office.`;

                audioQueue.push({ phrase, pageIndex });
                processAudioQueue();
            }

            function finishSpeechAndHold() {
                isSpeaking = false;
                isHoldingCall = true;

                if (postCallHoldTimer) clearTimeout(postCallHoldTimer);
                postCallHoldTimer = setTimeout(() => {
                    isHoldingCall = false;
                    startPageRotation();
                }, POST_CALL_HOLD_MS);

                processAudioQueue();
            }

            function processAudioQueue() {
                if (isSpeaking || audioQueue.length === 0 || !('speechSynthesis' in window)) return;

                isSpeaking = true;
                const nextItem = audioQueue.shift();

                // DYNAMICALLY SYNC SCREEN PAGE TO MATCH SPONKEN TICKET
                if (currentPageIndex !== nextItem.pageIndex) {
                    currentPageIndex = nextItem.pageIndex;
                    renderCurrentPage(true);
                }

                playBellDing(() => {
                    if (!singleFemaleVoice) loadVoices();

                    const utterance = new SpeechSynthesisUtterance(nextItem.phrase);
                    utterance.rate = 1.0;
                    utterance.pitch = 1.1;
                    utterance.lang = 'en-US';
                    if (singleFemaleVoice) utterance.voice = singleFemaleVoice;

                    utterance.onend = finishSpeechAndHold;
                    utterance.onerror = finishSpeechAndHold;

                    window.speechSynthesis.speak(utterance);
                });
            }

            setInterval(fetchLiveQueueData, 3000);
            fetchLiveQueueData();
            startPageRotation();

            // -----------------------------------------------------------------
            // BROWSER AUDIO UNLOCK & REMINDER BANNER DISMISSAL
            // -----------------------------------------------------------------
            const audioNoticeBanner = document.getElementById('audioNoticeBanner');

            function unlockAudioContext() {
                if ('speechSynthesis' in window) {
                    window.speechSynthesis.resume();
                    loadVoices();
                }

                try {
                    const AudioContext = window.AudioContext || window.webkitAudioContext;
                    if (AudioContext) {
                        const tempCtx = new AudioContext();
                        tempCtx.resume().then(() => tempCtx.close());
                    }
                } catch (e) {
                    console.warn("Audio Context unlock attempt:", e);
                }

                if (audioNoticeBanner) {
                    audioNoticeBanner.classList.add('dismissed');
                    setTimeout(() => {
                        audioNoticeBanner.remove();
                    }, 400);
                }

                document.removeEventListener('click', unlockAudioContext);
                document.removeEventListener('keydown', unlockAudioContext);
            }

            document.addEventListener('click', unlockAudioContext);
            document.addEventListener('keydown', unlockAudioContext);

        })();
    </script>
</body>

</html>