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
        /* HIGH CONTRAST FROSTED SLATE BLUE GLASS THEME */
        :root {
            --bg-wrapper: linear-gradient(135deg, rgba(11, 32, 117, 0.25) 0%, rgba(0, 10, 76, 0.45) 100%), url('<?= BASE_URL; ?>public/assets/main/css/bg-template.png');
            --bg-header: rgba(0, 16, 77, 0.88);
            --border-header: 1px solid rgba(255, 255, 255, 0.15);
            --header-title-color: #ffffff;

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

            --datetime-bg: transparent;
            --datetime-border: none;
            --datetime-icon-color: #60a5fa;
            --datetime-text-color: #cbd5e1;
            --datetime-clock-color: #ffffff;

            --footer-bg: rgba(0, 16, 77, 0.85);
            --footer-border: 1px solid rgba(255, 255, 255, 0.15);

            --btn-action-bg: rgba(255, 255, 255, 0.14);
            --btn-action-border: 1px solid rgba(255, 255, 255, 0.22);
            --btn-action-color: #ffffff;
            --btn-action-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            --btn-action-hover-bg: #2563eb;
            --btn-action-hover-color: #ffffff;
            --btn-action-hover-border: 1px solid #3b82f6;
            --btn-action-hover-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);

            --win-color: #0ea5e9;   /* every window uses the same blue */
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

        .header-banner-img {
            height: clamp(44px, 6.5vh, 85px);
            width: auto;
            max-width: calc(100vw - 140px);
            object-fit: contain;
            display: block;
        }

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

        .main-display-area {
            display: flex;
            flex-direction: column;
            flex-grow: 1;
            overflow: hidden;
            padding: clamp(0.5rem, 1.2vh, 1.25rem) clamp(0.75rem, 1.5vw, 1.75rem) clamp(3.2rem, 5vh, 4.5rem) clamp(0.75rem, 1.5vw, 1.75rem);
            box-sizing: border-box;
            contain: paint;
        }

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

        .office-pillar-card {
            flex: 1 1 0;
            min-width: 160px;
            max-width: 420px;
            min-height: 200px;
            background: var(--bg-card) !important;
            border: var(--border-card) !important;
            box-shadow: var(--card-shadow) !important;
            border-radius: clamp(1rem, 2vh, 2rem);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: clamp(0.5rem, 1.2vh, 1.2rem);
            height: 100%;
            box-sizing: border-box;
            position: relative;
            contain: paint;
            isolation: isolate;
            transition: box-shadow 0.3s ease, outline-color 0.3s ease;
        }

        .office-pillar-card.is-calling {
            outline: 3px solid var(--calling-border) !important;
            outline-offset: -3px;
            box-shadow: var(--calling-shadow) !important;
        }

        .pillar-header {
            text-align: center;
            border-bottom: var(--pillar-header-border);
            padding-bottom: clamp(0.35rem, 0.8vh, 0.65rem);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            min-height: clamp(105px, 16vh, 155px); /* Increased min-height for multi-line titles */
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
            font-size: clamp(0.72rem, 0.95vw, 1.1rem); /* Scaled down slightly for long titles */
            font-weight: 800;
            color: var(--office-name-color);
            margin: 0;
            line-height: 1.25;
            text-transform: uppercase;
            word-break: break-word;
            text-align: center;
            display: -webkit-box;
            -webkit-line-clamp: 3; /* Increased from 2 to 3 lines */
            -webkit-box-orient: vertical;
            overflow: hidden;
            max-height: 3.8em; /* Increased max-height to accommodate line 3 */
            width: 100%;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.4);
        }

        /* NOW SERVING BLOCK - WINDOW BANNER SITS AT THE TOP, INSIDE THE CONTAINER BORDER */
        .pillar-serving-block {
            container-type: inline-size;
            text-align: center;
            padding: 0;
            background: var(--serving-bg);
            border-radius: clamp(0.75rem, 1.5vh, 1.5rem);
            border: 2px solid var(--win-color);
            margin: clamp(0.35rem, 0.8vh, 0.75rem) 0;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            align-items: stretch;
            flex-grow: 1;
            min-height: clamp(120px, 18vh, 240px);
            box-sizing: border-box;
            position: relative;
            z-index: 2;
            box-shadow: inset 0 1px 1px rgba(255, 255, 255, 0.15), 0 4px 15px rgba(0, 0, 0, 0.15);
            width: 100%;
            overflow: hidden;
        }

        .window-banner {
            width: 100%;
            padding: clamp(0.3rem, 0.7vh, 0.6rem) 0.5rem;
            background: var(--win-color);
            color: #ffffff;
            font-size: clamp(0.9rem, 1.15vw, 1.45rem);
            font-weight: 900;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.45);
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.28);
            border-bottom: 1px solid rgba(255, 255, 255, 0.35);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            box-sizing: border-box;
            flex-shrink: 0;
        }

        .serving-body {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: clamp(0.3rem, 0.8vh, 0.7rem) 0.25rem;
            min-width: 0;
        }

        .serving-label {
            font-size: clamp(0.75rem, 0.9vw, 1rem);
            font-weight: 800;
            letter-spacing: 0.14em;
            color: var(--serving-label-color);
            text-transform: uppercase;
            margin-bottom: 0.1rem;
        }

        .serving-ticket-number {
            font-size: clamp(1.1rem, 9.5cqw, 3.4rem); /* Reduced container width query from 13.5cqw to 9.5cqw */
            font-weight: 900;
            color: var(--serving-ticket-color);
            line-height: 1;
            letter-spacing: -0.02em;
            margin: clamp(0.2rem, 0.5vh, 0.5rem) 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            max-width: 100%;
            padding: 0 0.3rem; /* Small internal safety padding */
            box-sizing: border-box;
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
            font-size: clamp(0.75rem, 0.88vw, 1.05rem); /* Reduced max size slightly for long codes */
            padding: 0.35rem 0.5rem;
            border-radius: 0.75rem;
            letter-spacing: 0.03em;
            height: clamp(30px, 4.2vh, 44px);
            display: flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
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
            const POST_CALL_HOLD_MS = 6000;
            const FRESH_CALL_SECONDS = 20;      // on first load, only announce calls newer than this
            const PENDING_MAX_AGE_MS = 60000;   // drop announcements that waited too long for audio unlock
            const SPEECH_WATCHDOG_MS = 20000;   // never let a stuck utterance freeze the queue

            const digitalDate = document.getElementById('digitalDate');
            const digitalClock = document.getElementById('digitalClock');
            const pageRotationBadge = document.getElementById('pageRotationBadge');
            const pageBadgeWrapper = document.getElementById('pageBadgeWrapper');
            const pillarGridContainer = document.getElementById('pillarGridContainer');
            const emptyStateDisplay = document.getElementById('emptyStateDisplay');
            const btnToggleFullscreen = document.getElementById('btnToggleFullscreen');

            let announcedCalls = new Set();
            let firstPollDone = false;
            let cachedPillars = [];
            let currentPageIndex = 0;
            let rotationTimer = null;
            let postCallHoldTimer = null;
            let isHoldingCall = false;
            let lastRenderedJson = '';
            let renderSeq = 0;               // only the newest scheduled render may touch the screen
            let knownPillarKeys = null;      // pillars seen on the previous poll (null until first poll)

            function escapeHtml(str) {
                if (str === null || str === undefined) return '';
                return String(str)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            /* ---------------- AUDIO ---------------- */
            let audioQueue = [];
            let isSpeaking = false;
            let audioUnlocked = false;
            let audioCtx = null;
            let singleFemaleVoice = null;
            let currentUtterance = null;   // keep a reference so the browser does not garbage-collect it mid-speech

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

            function playBellDing() {
                try {
                    if (!audioCtx) return;
                    if (audioCtx.state === 'suspended') audioCtx.resume();
                    const ctx = audioCtx;

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
                } catch (e) {
                    console.warn("Bell could not be played.", e);
                }
            }

            function updateDateTime() {
                const now = new Date();
                digitalDate.textContent = now.toLocaleDateString('en-US', {
                    weekday: 'short',
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric'
                });
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

            /* ---------------- DATA ---------------- */
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
                        if (!(result.success && result.data)) return;

                        cachedPillars = result.data.pillars || result.data.active_offices || [];

                        // 1. Drop audio for tickets that are no longer being called (completed / skipped / replaced)
                        audioQueue = audioQueue.filter(item => isTicketActive(item.ticketId));
                        if (activeSpeech && !isTicketActive(activeSpeech.ticketId)) {
                            activeSpeech.abort();
                        }

                        // 2. Announce newly called tickets, oldest call first
                        detectNewCalls(cachedPillars);

                        // 3. A pillar that was not on screen before (new office/window activity) is shown on page 1
                        const currentKeys = new Set(cachedPillars.map(p => p.pillar_key));
                        if (knownPillarKeys !== null) {
                            let hasNewPillar = false;
                            currentKeys.forEach(k => { if (!knownPillarKeys.has(k)) hasNewPillar = true; });
                            if (hasNewPillar) {
                                holdOnFirstPage();
                            }
                        }
                        knownPillarKeys = currentKeys;

                        if (cachedPillars.length === 0) {
                            renderEmptyState();
                            return;
                        }

                        // called_age changes every poll, so it must not trigger a re-render
                        if (currentFingerprint() !== lastRenderedJson) {
                            renderCurrentPage(false);
                        }
                    })
                    .catch(err => console.error("Live Queue Fetch Error:", err));
            }

            function currentFingerprint() {
                // called_age changes every poll, so it is excluded
                return JSON.stringify(cachedPillars, (k, v) => (k === 'called_age' ? undefined : v));
            }

            function isTicketActive(ticketId) {
                return cachedPillars.some(p => p.now_serving
                    && p.now_serving.ticket_id === ticketId
                    && (p.now_serving.status === 'CALLED' || p.now_serving.status === 'SERVING'));
            }

            function holdOnFirstPage() {
                currentPageIndex = 0;
                isHoldingCall = true;
                if (postCallHoldTimer) clearTimeout(postCallHoldTimer);
                postCallHoldTimer = setTimeout(() => { isHoldingCall = false; }, POST_CALL_HOLD_MS + 2000);
            }

            function detectNewCalls(pillars) {
                const fresh = [];

                pillars.forEach(p => {
                    const ns = p.now_serving;
                    if (!ns || !ns.ticket_number) return;

                    // A call is "new" while CALLED (or if the staff pressed Start Serving within seconds of calling)
                    const isCallState = ns.status === 'CALLED' || (ns.status === 'SERVING' && ns.called_age !== null && ns.called_age <= 6);
                    if (!isCallState) return;

                    const callKey = `${ns.ticket_id}_${ns.called_at || ''}`;
                    if (announcedCalls.has(callKey)) return;
                    announcedCalls.add(callKey);

                    // First load of this screen: calls made a while ago were already announced earlier
                    if (!firstPollDone && (ns.called_age === null || ns.called_age > FRESH_CALL_SECONDS)) return;

                    fresh.push({ pillar: p, ns: ns });
                });

                firstPollDone = true;

                // A new call is shown on page 1 (the handler sorts newest call first): keep the screen there for a moment
                if (fresh.length > 0) {
                    holdOnFirstPage();
                }

                // FIRST CALLED = FIRST SPOKEN (the list order is by office/window, not by call time)
                fresh.sort((a, b) => {
                    const t = String(a.ns.called_at || '').localeCompare(String(b.ns.called_at || ''));
                    return t !== 0 ? t : (a.ns.ticket_id - b.ns.ticket_id);
                });

                fresh.forEach(item => queueVoiceCallout(item.ns.ticket_number, item.pillar.counter_name, item.pillar.pillar_key, item.ns.ticket_id));
            }

            /* ---------------- RENDER ---------------- */
            function renderCurrentPage(animate = false) {
                if (cachedPillars.length === 0) return;

                const seq = ++renderSeq;

                // Everything is computed when the build actually runs (never from data captured earlier),
                // and a newer render cancels any older pending one. This is what stops a slow page-rotation
                // fade from putting old pillars back on the screen after fresh data arrived.
                const build = () => {
                    if (seq !== renderSeq || cachedPillars.length === 0) return;

                    const totalPages = Math.ceil(cachedPillars.length / ITEMS_PER_PAGE);
                    if (currentPageIndex >= totalPages) currentPageIndex = 0;

                    if (totalPages > 1) {
                        pageRotationBadge.textContent = `PAGE ${currentPageIndex + 1} / ${totalPages}`;
                        if (pageBadgeWrapper) pageBadgeWrapper.classList.remove('d-none');
                    } else {
                        if (pageBadgeWrapper) pageBadgeWrapper.classList.add('d-none');
                    }

                    const start = currentPageIndex * ITEMS_PER_PAGE;
                    const pageItems = cachedPillars.slice(start, start + ITEMS_PER_PAGE);

                    pillarGridContainer.innerHTML = '';

                    pageItems.forEach(p => {
                        const pillar = document.createElement('div');
                        const ns = p.now_serving;
                        const isCalling = ns && ns.status === 'CALLED';
                        const windowName = p.counter_name || 'OFFICE';

                        pillar.className = `office-pillar-card ${isCalling ? 'is-calling' : ''}`;

                        let lineupHtml = '';
                        const upcoming = p.up_next || [];
                        for (let i = 0; i < 4; i++) {
                            if (upcoming[i]) {
                                const isPri = upcoming[i].is_priority == 1;
                                lineupHtml += `<div class="lineup-pill ${isPri ? 'is-priority' : ''}">${isPri ? '★ ' : ''}${escapeHtml(upcoming[i].ticket_number)}</div>`;
                            } else {
                                lineupHtml += `<div class="lineup-pill empty-pill">---</div>`;
                            }
                        }

                        const logoHtml = p.office_logo ?
                            `<img src="<?= BASE_URL; ?>${escapeHtml(p.office_logo)}" class="pillar-logo-img" alt="Logo" width="46" height="46">` :
                            `<div class="pillar-logo-img text-primary fw-bold fs-4"><i class="bi bi-building"></i></div>`;

                        pillar.innerHTML = `
                            <div class="pillar-header">
                                ${logoHtml}
                                <h2 class="pillar-office-name">${escapeHtml(p.office_name)}</h2>
                            </div>

                            <div class="pillar-serving-block">
                                <div class="window-banner">${escapeHtml(windowName)}</div>
                                <div class="serving-body">
                                    <span class="serving-label">NOW SERVING</span>
                                    <div class="serving-ticket-number">${ns ? escapeHtml(ns.ticket_number) : '---'}</div>
                                    <div class="priority-badge-container">
                                        ${ns && ns.is_priority == 1 ? '<span class="priority-badge-tag">★ PRIORITY</span>' : ''}
                                    </div>
                                </div>
                            </div>

                            <div class="pillar-lineup-block">
                                <span class="lineup-label">WAITING LINE</span>
                                <div class="lineup-pills-stack">
                                    ${lineupHtml}
                                </div>
                                <div class="waiting-count-footer">(${p.waiting_count || 0} Waiting)</div>
                            </div>
                        `;

                        pillarGridContainer.appendChild(pillar);
                    });

                    lastRenderedJson = currentFingerprint();
                };

                if (animate) {
                    pillarGridContainer.classList.add('fade-out');
                    setTimeout(() => {
                        if (seq !== renderSeq) return;   // superseded by a newer render, which handles the fade itself
                        build();
                        requestAnimationFrame(() => pillarGridContainer.classList.remove('fade-out'));
                    }, 350);
                } else {
                    pillarGridContainer.classList.remove('fade-out');
                    build();
                }
            }

            function renderEmptyState() {
                renderSeq++;   // cancel any pending animated render
                if (lastRenderedJson === 'EMPTY_STATE') return;
                lastRenderedJson = 'EMPTY_STATE';
                pillarGridContainer.classList.remove('fade-out');
                if (pageBadgeWrapper) pageBadgeWrapper.classList.add('d-none');
                pillarGridContainer.innerHTML = '';
                pillarGridContainer.appendChild(emptyStateDisplay);
                emptyStateDisplay.classList.remove('d-none');
            }

            function showPillarPage(pillarKey) {
                const idx = cachedPillars.findIndex(p => p.pillar_key === pillarKey);
                if (idx < 0) return;
                const page = Math.floor(idx / ITEMS_PER_PAGE);
                if (page !== currentPageIndex) {
                    currentPageIndex = page;
                    renderCurrentPage(true);
                }
            }

            function startPageRotation() {
                if (rotationTimer) clearInterval(rotationTimer);
                rotationTimer = setInterval(() => {
                    // Hold the page while a call is being spoken (only when audio is actually working)
                    if (isHoldingCall || (audioUnlocked && (isSpeaking || audioQueue.length > 0))) {
                        return;
                    }

                    const totalPages = Math.ceil(cachedPillars.length / ITEMS_PER_PAGE);

                    if (totalPages > 1) {
                        currentPageIndex = (currentPageIndex + 1) % totalPages;
                        renderCurrentPage(true);
                    } else if (currentPageIndex !== 0) {
                        currentPageIndex = 0;
                        renderCurrentPage(true);
                    }
                }, ROTATION_INTERVAL_MS);
            }

            /* ---------------- VOICE QUEUE ---------------- */
            function queueVoiceCallout(rawTicketNumber, counterName, pillarKey, ticketId) {
                const spokenTicket = String(rawTicketNumber)
                    .split('')
                    .map(char => (char === '-' ? ' ' : char))
                    .join(' ');

                const windowText = counterName ? `, proceed to ${counterName}` : ', proceed to the office';
                const phrase = `Now calling, ${spokenTicket}${windowText}. I repeat, ${spokenTicket}${windowText}.`;

                audioQueue.push({ phrase, pillarKey, ticketId, queuedAt: Date.now() });
                if (audioQueue.length > 10) audioQueue.shift();
                processAudioQueue();
            }

            function finishSpeechAndHold() {
                isSpeaking = false;
                isHoldingCall = true;

                if (postCallHoldTimer) clearTimeout(postCallHoldTimer);
                postCallHoldTimer = setTimeout(() => {
                    isHoldingCall = false;
                }, POST_CALL_HOLD_MS);

                processAudioQueue();
            }

            let activeSpeech = null;   // { ticketId, abort() } while a call is being spoken

            function speakPhrase(item) {
                let finished = false;
                let watchdog = null;

                // aborted = the ticket was completed/skipped while speaking: stop right away, no post-call hold
                const finish = (aborted) => {
                    if (finished) return;
                    finished = true;
                    clearTimeout(watchdog);
                    currentUtterance = null;
                    activeSpeech = null;
                    if (aborted) {
                        isSpeaking = false;
                        processAudioQueue();
                    } else {
                        finishSpeechAndHold();
                    }
                };

                activeSpeech = {
                    ticketId: item.ticketId,
                    abort: () => {
                        try { window.speechSynthesis.cancel(); } catch (e) {}
                        finish(true);
                    }
                };

                watchdog = setTimeout(() => {
                    try { window.speechSynthesis.cancel(); } catch (e) {}
                    finish(false);
                }, SPEECH_WATCHDOG_MS);

                try {
                    if (!singleFemaleVoice) loadVoices();

                    const utterance = new SpeechSynthesisUtterance(item.phrase);
                    utterance.rate = 1.0;
                    utterance.pitch = 1.1;
                    utterance.lang = 'en-US';
                    if (singleFemaleVoice) utterance.voice = singleFemaleVoice;
                    utterance.onend = () => finish(false);
                    utterance.onerror = () => finish(false);

                    currentUtterance = utterance;
                    window.speechSynthesis.speak(utterance);
                } catch (e) {
                    console.warn('Speech failed.', e);
                    finish(false);
                }
            }

            function processAudioQueue() {
                if (isSpeaking || audioQueue.length === 0) return;

                // Ticket already completed / skipped before it could be announced: do not announce it
                if (!isTicketActive(audioQueue[0].ticketId)) {
                    audioQueue.shift();
                    processAudioQueue();
                    return;
                }

                // Show the window that is being called, even while audio is still locked
                showPillarPage(audioQueue[0].pillarKey);

                if (!audioUnlocked || !('speechSynthesis' in window)) return;   // wait for the first click

                const nextItem = audioQueue.shift();
                if (Date.now() - nextItem.queuedAt > PENDING_MAX_AGE_MS) {
                    processAudioQueue();   // too old to be useful, skip to the next one
                    return;
                }

                isSpeaking = true;
                playBellDing();
                // Let the bell start first, then speak the ticket (if it is still being called)
                setTimeout(() => {
                    if (!isTicketActive(nextItem.ticketId)) {
                        isSpeaking = false;
                        processAudioQueue();
                        return;
                    }
                    speakPhrase(nextItem);
                }, 500);
            }

            /* ---------------- AUDIO UNLOCK (browsers require one click) ---------------- */
            const audioNoticeBanner = document.getElementById('audioNoticeBanner');

            function unlockAudioContext() {
                try {
                    const AudioContext = window.AudioContext || window.webkitAudioContext;
                    if (AudioContext && !audioCtx) audioCtx = new AudioContext();
                    if (audioCtx) audioCtx.resume();
                } catch (e) {
                    console.warn("Audio Context unlock attempt:", e);
                }

                if ('speechSynthesis' in window) {
                    try {
                        window.speechSynthesis.resume();
                        loadVoices();
                        // Silent primer so the very first real announcement is not dropped by the browser
                        const primer = new SpeechSynthesisUtterance(' ');
                        primer.volume = 0;
                        window.speechSynthesis.speak(primer);
                    } catch (e) {}
                }

                audioUnlocked = true;

                if (audioNoticeBanner) {
                    audioNoticeBanner.classList.add('dismissed');
                    setTimeout(() => audioNoticeBanner.remove(), 400);
                }

                document.removeEventListener('click', unlockAudioContext);
                document.removeEventListener('keydown', unlockAudioContext);
                document.removeEventListener('touchstart', unlockAudioContext);

                // Speak anything that was called before the first click
                processAudioQueue();
            }

            document.addEventListener('click', unlockAudioContext);
            document.addEventListener('keydown', unlockAudioContext);
            document.addEventListener('touchstart', unlockAudioContext);

            setInterval(fetchLiveQueueData, 2000);
            fetchLiveQueueData();
            startPageRotation();

        })();
    </script>
</body>

</html>