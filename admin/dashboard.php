<?php
// dashboard.php – NU SAMS Admin Panel Dashboard
session_start();

// Require admin login
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../db.php';

// Admin identity from session (set at login)
$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$admin_role = $_SESSION['admin_role'] ?? 'SDAO Head';
$department = $_SESSION['department'] ?? 'NU Lipa - Student Development and Activities Office';

// ========= DASHBOARD METRICS =========
$total_applications   = 0;
$todays_applications  = 0;
$total_hours_month    = 0; // placeholder for future hours tracking
$avg_performance      = 0; // placeholder for future performance tracking

// Attendance and alerts data
$today_attendance = [];
$alerts           = [];

// Total applications
if ($result = $mysqli->query("SELECT COUNT(*) AS c FROM student_applications")) {
    $row = $result->fetch_assoc();
    $total_applications = (int)($row['c'] ?? 0);
    $result->free();
}

// Applications submitted today
if ($result = $mysqli->query("SELECT COUNT(*) AS c FROM student_applications WHERE DATE(created_at) = CURDATE()")) {
    $row = $result->fetch_assoc();
    $todays_applications = (int)($row['c'] ?? 0);
    $result->free();
}

// Recent applications list
$recent_applications = [];
if ($result = $mysqli->query("SELECT full_name, student_id, course, created_at, application_status FROM student_applications ORDER BY created_at DESC LIMIT 5")) {
    while ($row = $result->fetch_assoc()) {
        $recent_applications[] = $row;
    }
    $result->free();
}

// Today's attendance (optional table; falls back gracefully if missing)
if ($result = $mysqli->query("SELECT student_name, location, check_in_time, status FROM attendance WHERE DATE(check_in_time) = CURDATE() ORDER BY check_in_time ASC LIMIT 10")) {
    while ($row = $result->fetch_assoc()) {
        $today_attendance[] = $row;
    }
    $result->free();
}

// Alerts & notifications (optional table; falls back gracefully if missing)
if ($result = $mysqli->query("SELECT type, title, body FROM alerts ORDER BY created_at DESC LIMIT 10")) {
    while ($row = $result->fetch_assoc()) {
        $alerts[] = $row;
    }
    $result->free();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <script src="../assets/realtime.js"></script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>NU SA System – Admin Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;900&display=swap" rel="stylesheet" />
    <style>
        /* =============================================
           CSS VARIABLES – Design System
        ============================================= */
        :root {
            /* Brand */
            --color-primary:         #155dfc;
            --color-primary-dark:    #1447e6;
            --color-purple:          #9810fa;
            --gradient-brand:        linear-gradient(135deg, #155dfc 0%, #9810fa 100%);

            /* Neutral */
            --color-heading:         #101828;
            --color-body:            #4a5565;
            --color-label:           #364153;
            --color-muted:           #6a7282;
            --color-border:          #e5e7eb;
            --color-bg-app:          #f9fafb;
            --color-white:           #ffffff;

            /* Status badges */
            --color-pending-bg:      #fef9c2;
            --color-pending-text:    #a65f00;
            --color-interview-bg:    #dbeafe;
            --color-interview-text:  #1447e6;
            --color-approved-bg:     #dcfce7;
            --color-approved-text:   #008236;

            /* Presence dots */
            --color-present:         #00c950;
            --color-absent:          #d1d5dc;

            /* Alert colours */
            --color-alert-warn-bg:   #fff7ed;
            --color-alert-warn-bd:   #ffd6a8;
            --color-alert-succ-bg:   #f0fdf4;
            --color-alert-succ-bd:   #b9f8cf;
            --color-alert-info-bg:   #eff6ff;
            --color-alert-info-bd:   #bedbff;
            --color-red-dot:         #fb2c36;
            --color-green-up:        #00a63e;

            /* Shadows */
            --shadow-card: 0 1px 3px rgba(0,0,0,.07), 0 1px 2px rgba(0,0,0,.05);

            /* Dimensions */
            --sidebar-width: 256px;
            --topbar-height: 89px;
            --radius-card:   16.4px;
            --radius-nav:    10px;
            --radius-badge:  9999px;
            --radius-icon:   10px;

            /* Typography */
            --font-xs:   12px;
            --font-sm:   14px;
            --font-base: 16px;
            --font-md:   18px;
            --font-lg:   24px;
            --font-xl:   30px;

            --lh-xs:   16px;
            --lh-sm:   20px;
            --lh-base: 24px;
            --lh-md:   28px;
            --lh-lg:   32px;
            --lh-xl:   36px;
        }

        /* =============================================
           RESET & BASE
        ============================================= */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--color-bg-app);
            color: var(--color-heading);
            min-height: 100vh;
            display: flex;
        }
        a { text-decoration: none; color: inherit; }
        img { display: block; max-width: 100%; }
        button { font-family: inherit; cursor: pointer; border: none; background: none; }

        /* =============================================
           APP SHELL
        ============================================= */
        .shell { display: flex; width: 100%; min-height: 100vh; }

        /* =============================================
           SIDEBAR
        ============================================= */
        .sidebar {
            width: var(--sidebar-width);
            min-height: 100vh;
            background: var(--color-white);
            border-right: 1px solid var(--color-border);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar__brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 24px 24px 20px;
            border-bottom: 1px solid var(--color-border);
            flex-shrink: 0;
        }
        .sidebar__logo {
            width: 40px;
            height: 40px;
            background: var(--gradient-brand);
            border-radius: var(--radius-icon);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .sidebar__logo-text { font-size: 18px; font-weight: 700; color: var(--color-white); line-height: 1; }
        .sidebar__brand-name { font-size: var(--font-base); font-weight: 700; color: var(--color-heading); line-height: var(--lh-base); }
        .sidebar__brand-sub  { font-size: var(--font-xs); font-weight: 400; color: var(--color-body); line-height: var(--lh-xs); }

        .sidebar__nav {
            flex: 1;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 4px;
            overflow-y: auto;
        }
        .sidebar__nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            height: 48px;
            padding: 0 16px;
            border-radius: var(--radius-nav);
            font-size: var(--font-base);
            font-weight: 400;
            color: var(--color-label);
            transition: background .15s;
            white-space: nowrap;
        }
        .sidebar__nav-link:hover { background: var(--color-bg-app); }
        .sidebar__nav-link--active { background: var(--color-primary); color: var(--color-white); }
        .sidebar__nav-link--active:hover { opacity: .92; }
        .sidebar__nav-icon { width: 20px; height: 20px; flex-shrink: 0; }
        .sidebar__nav-badge {
            background: #dbeafe;
            color: var(--color-primary);
            font-size: var(--font-xs);
            font-weight: 700;
            line-height: var(--lh-xs);
            padding: 2px 8px;
            border-radius: var(--radius-badge);
            margin-left: auto;
        }

        .sidebar__footer {
            border-top: 1px solid var(--color-border);
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 4px;
            flex-shrink: 0;
        }

        /* =============================================
           MAIN
        ============================================= */
        .main { flex: 1; min-width: 0; display: flex; flex-direction: column; }

        .topbar {
            background: var(--color-white);
            border-bottom: 1px solid var(--color-border);
            height: var(--topbar-height);
            padding: 0 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .topbar__left-wrap { display: flex; align-items: center; }
        .topbar__title { font-size: var(--font-lg); font-weight: 700; line-height: var(--lh-lg); color: var(--color-heading); }
        .topbar__sub   { font-size: var(--font-sm); font-weight: 400; line-height: var(--lh-sm); color: var(--color-body); }

        .topbar__right { display: flex; align-items: center; gap: 12px; }
        .topbar__notif-btn {
            width: 36px; height: 36px;
            border-radius: var(--radius-badge);
            display: flex; align-items: center; justify-content: center;
            position: relative; cursor: pointer;
            transition: background .15s;
        }
        .topbar__notif-btn:hover { background: var(--color-bg-app); }
        .topbar__notif-btn svg { width: 20px; height: 20px; }
        .topbar__notif-dot {
            position: absolute; top: 4px; right: 4px;
            width: 8px; height: 8px;
            background: var(--color-red-dot);
            border-radius: var(--radius-badge);
        }
        .topbar__user-info { text-align: right; }
        .topbar__user-name { font-size: var(--font-sm); font-weight: 400; color: var(--color-heading); line-height: var(--lh-sm); }
        .topbar__user-role { font-size: var(--font-xs); font-weight: 400; color: var(--color-body); line-height: var(--lh-xs); }
        .topbar__avatar {
            width: 40px; height: 40px;
            background: var(--gradient-brand);
            border-radius: var(--radius-badge);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .topbar__avatar svg { width: 20px; height: 20px; }

        /* Hamburger */
        .topbar__hamburger {
            display: none;
            flex-direction: column; gap: 5px;
            width: 32px; height: 32px;
            justify-content: center; align-items: center;
            padding: 0; margin-right: 16px; cursor: pointer;
        }
        .topbar__hamburger-bar {
            display: block; width: 22px; height: 2px;
            background: var(--color-heading); border-radius: 2px;
            transition: transform .3s, opacity .3s;
        }
        .topbar__hamburger[aria-expanded="true"] .topbar__hamburger-bar:nth-child(1) { transform: translateY(7px) rotate(45deg); }
        .topbar__hamburger[aria-expanded="true"] .topbar__hamburger-bar:nth-child(2) { opacity: 0; }
        .topbar__hamburger[aria-expanded="true"] .topbar__hamburger-bar:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

        /* =============================================
           DASHBOARD CONTENT
        ============================================= */
        .dashboard { padding: 32px; display: flex; flex-direction: column; gap: 24px; flex: 1; }

        /* =============================================
           STAT CARDS
        ============================================= */
        .stat-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; }
        .stat-card {
            background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
            border: 1px solid #1e3a8a;
            border-radius: var(--radius-card);
            box-shadow: var(--shadow-card);
            padding: 24px;
        }
        .stat-card__top { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 16px; }
        .stat-card__icon-wrap {
            width: 48px; height: 48px;
            background: rgba(255,255,255,.16);
            border-radius: var(--radius-icon);
            display: flex; align-items: center; justify-content: center;
        }
        .stat-card__icon-wrap svg path,
        .stat-card__icon-wrap svg circle,
        .stat-card__icon-wrap svg rect {
            stroke: #ffffff !important;
            fill: transparent;
        }
        .stat-card__icon-wrap svg { width: 24px; height: 24px; }
        .stat-card__trend { font-size: var(--font-sm); font-weight: 400; color: #ffffff; line-height: var(--lh-sm); }
        .stat-card__value { font-size: var(--font-xl); font-weight: 700; line-height: var(--lh-xl); color: #ffffff; margin-bottom: 4px; }
        .stat-card__label { font-size: var(--font-sm); font-weight: 400; line-height: var(--lh-sm); color: #ffffff; margin-bottom: 4px; }
        .stat-card__sub   { font-size: var(--font-xs); font-weight: 400; line-height: var(--lh-xs); color: rgba(255,255,255,.92); }

        /* =============================================
           MID ROW
        ============================================= */
        .mid-row { display: grid; grid-template-columns: 1fr 431px; gap: 24px; }

        /* =============================================
           CARD BASE
        ============================================= */
        .card {
            background: var(--color-white);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-card);
            box-shadow: var(--shadow-card);
            padding: 25px;
        }
        .card__header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; }
        .card__title  { font-size: var(--font-md); font-weight: 700; line-height: var(--lh-md); color: var(--color-heading); }
        .card__link   { font-size: var(--font-sm); font-weight: 400; color: var(--color-primary); line-height: var(--lh-sm); transition: opacity .15s; }
        .card__link:hover { opacity: .75; }

        /* =============================================
           APPLICATION ROWS
        ============================================= */
        .app-list { display: flex; flex-direction: column; gap: 12px; }
        .app-row {
            display: flex; align-items: center; gap: 16px;
            height: 76px; padding: 0 16px;
            border-radius: var(--radius-nav);
            transition: background .12s;
        }
        .app-row:hover { background: var(--color-bg-app); }
        .app-row__avatar {
            width: 40px; height: 40px;
            background: var(--gradient-brand);
            border-radius: var(--radius-badge);
            display: flex; align-items: center; justify-content: center;
            font-size: var(--font-base); font-weight: 700; color: var(--color-white);
            flex-shrink: 0;
        }
        .app-row__info { flex: 1; min-width: 0; }
        .app-row__name   { font-size: var(--font-base); font-weight: 700; line-height: var(--lh-base); color: var(--color-heading); }
        .app-row__detail { font-size: var(--font-sm); font-weight: 400; line-height: var(--lh-sm); color: var(--color-body); }
        .app-row__meta   { text-align: right; flex-shrink: 0; }
        .app-row__badge {
            display: inline-block; height: 24px;
            padding: 4px 12px;
            border-radius: var(--radius-badge);
            font-size: var(--font-xs); font-weight: 400; line-height: var(--lh-xs);
            margin-bottom: 3px;
        }
        .app-row__badge--pending   { background: var(--color-pending-bg);   color: var(--color-pending-text); }
        .app-row__badge--interview { background: var(--color-interview-bg); color: var(--color-interview-text); }
        .app-row__badge--approved  { background: var(--color-approved-bg);  color: var(--color-approved-text); }
        .app-row__badge--rejected   { background: #fee2e2; color: #b91c1c; }
        .app-row__date { font-size: var(--font-xs); font-weight: 400; line-height: var(--lh-xs); color: var(--color-muted); }

        /* =============================================
           QUICK ACTIONS
        ============================================= */
        .qa-list { display: flex; flex-direction: column; gap: 12px; }
        .qa-btn {
            display: flex; align-items: center; gap: 12px;
            height: 64px; padding: 0 16px;
            background: var(--color-bg-app);
            border-radius: var(--radius-nav);
            font-size: var(--font-base); font-weight: 400; color: var(--color-heading);
            text-decoration: none; transition: background .15s; cursor: pointer;
        }
        .qa-btn:hover { background: var(--color-border); }
        .qa-btn__emoji { font-size: 24px; line-height: 1; flex-shrink: 0; width: 33px; }

        /* =============================================
           BOTTOM ROW
        ============================================= */
        .bottom-row { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }

        /* =============================================
           ATTENDANCE ROWS
        ============================================= */
        .att-list { display: flex; flex-direction: column; gap: 12px; }
        .att-row {
            display: flex; align-items: center; gap: 16px;
            height: 76px; padding: 0 16px;
            background: var(--color-bg-app);
            border-radius: var(--radius-nav);
        }
        .att-row__dot { width: 12px; height: 12px; border-radius: var(--radius-badge); flex-shrink: 0; }
        .att-row__dot--present { background: var(--color-present); }
        .att-row__dot--absent  { background: var(--color-absent); }
        .att-row__info { flex: 1; }
        .att-row__name     { font-size: var(--font-base); font-weight: 700; line-height: var(--lh-base); color: var(--color-heading); }
        .att-row__location { font-size: var(--font-sm); font-weight: 400; line-height: var(--lh-sm); color: var(--color-body); }
        .att-row__time     { font-size: var(--font-base); font-weight: 400; line-height: var(--lh-base); color: var(--color-heading); text-align: right; flex-shrink: 0; }

        /* =============================================
           ALERTS
        ============================================= */
        .alert-list { display: flex; flex-direction: column; gap: 12px; }
        .alert-item {
            display: flex; align-items: flex-start; gap: 12px;
            padding: 17px; border-radius: var(--radius-nav); border: 1px solid;
        }
        .alert-item--warn { background: var(--color-alert-warn-bg); border-color: var(--color-alert-warn-bd); }
        .alert-item--succ { background: var(--color-alert-succ-bg); border-color: var(--color-alert-succ-bd); }
        .alert-item--info { background: var(--color-alert-info-bg); border-color: var(--color-alert-info-bd); }
        .alert-item__icon  { width: 20px; height: 20px; flex-shrink: 0; }
        .alert-item__title { font-size: var(--font-sm); font-weight: 700; line-height: var(--lh-sm); color: var(--color-heading); margin-bottom: 4px; }
        .alert-item__body  { font-size: var(--font-xs); font-weight: 400; line-height: var(--lh-xs); color: var(--color-body); }

        /* =============================================
           OVERLAY
        ============================================= */
        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.4); z-index: 90; }
        .sidebar-overlay--visible { display: block; }

        /* =============================================
           RESPONSIVE – TABLET (≤1024px)
        ============================================= */
        @media (max-width: 1024px) {
            .sidebar {
                position: fixed; left: 0; top: 0; height: 100%; z-index: 100;
                transform: translateX(-100%); transition: transform .3s ease;
            }
            .sidebar--open { transform: translateX(0); }
            .topbar__hamburger { display: flex; }
            .topbar { padding: 0 24px; }
            .dashboard { padding: 24px; }
            .stat-grid { grid-template-columns: repeat(2, 1fr); }
            .mid-row { grid-template-columns: 1fr; }
            .bottom-row { grid-template-columns: 1fr; }
        }

        /* =============================================
           RESPONSIVE – MOBILE (≤768px)
        ============================================= */
        @media (max-width: 768px) {
            .topbar { padding: 0 16px; }
            .dashboard { padding: 16px; gap: 16px; }
            .stat-grid { grid-template-columns: 1fr 1fr; gap: 12px; }
            .stat-card { padding: 16px; }
            .topbar__user-info { display: none; }
            .card { padding: 16px; }
            .card__title { font-size: var(--font-base); }
        }
    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebar-overlay" aria-hidden="true"></div>

<div class="shell">

    <!-- ══════════════════ SIDEBAR ══════════════════ -->
    <aside class="sidebar" id="sidebar" aria-label="Admin navigation">

        <div class="sidebar__brand">
            <div class="sidebar__logo" aria-hidden="true">
                <span class="sidebar__logo-text">NU</span>
            </div>
            <div>
                <div class="sidebar__brand-name">SA System</div>
                <div class="sidebar__brand-sub">Admin Panel</div>
            </div>
        </div>

        <nav class="sidebar__nav" aria-label="Main navigation">

            <a href="dashboard.php" class="sidebar__nav-link sidebar__nav-link--active" aria-current="page">
                <svg class="sidebar__nav-icon" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <rect x="2" y="2" width="7" height="7" rx="1.5" fill="currentColor"/>
                    <rect x="11" y="2" width="7" height="7" rx="1.5" fill="currentColor"/>
                    <rect x="2" y="11" width="7" height="7" rx="1.5" fill="currentColor"/>
                    <rect x="11" y="11" width="7" height="7" rx="1.5" fill="currentColor"/>
                </svg>
                Dashboard
            </a>

            <a href="application.php" class="sidebar__nav-link">
                <svg class="sidebar__nav-icon" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <rect x="3" y="2" width="14" height="16" rx="2" stroke="currentColor" stroke-width="1.6"/>
                    <path d="M6 6h8M6 9.5h8M6 13h6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
                Applications
                <span class="sidebar__nav-badge"><?php echo $total_applications; ?></span>
            </a>

            <a href="scheduling.php" class="sidebar__nav-link">
                <svg class="sidebar__nav-icon" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="M4 3h12v14H4z" stroke="currentColor" stroke-width="1.6"/>
                    <path d="M4 7h12M7 3v4M13 3v4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
                Scheduling
            </a>

            <a href="attendance.php" class="sidebar__nav-link">
                <svg class="sidebar__nav-icon" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <circle cx="10" cy="10" r="7" stroke="currentColor" stroke-width="1.6"/>
                    <path d="M10 6v4l3 2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
                Attendance
            </a>

            <a href="chat.php" class="sidebar__nav-link">
                <svg class="sidebar__nav-icon" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="M4 4h12a2 2 0 012 2v6a2 2 0 01-2 2H9l-4 3v-3H4a2 2 0 01-2-2V6a2 2 0 012-2z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                </svg>
                Messages
            </a>

            <a href="documents.php" class="sidebar__nav-link">
                <svg class="sidebar__nav-icon" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="M5 3h8l3 3v11H5z" stroke="currentColor" stroke-width="1.6"/>
                    <path d="M13 3v4h3" stroke="currentColor" stroke-width="1.6"/>
                </svg>
                Documents
            </a>

            <a href="evaluation.php" class="sidebar__nav-link">
                <svg class="sidebar__nav-icon" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="M4 10h12M4 5h12M4 15h8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
                Evaluation
            </a>

            <a href="reports.php" class="sidebar__nav-link">
                <svg class="sidebar__nav-icon" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="M4 16h12M6 13V9M10 13V6M14 13V4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
                Reports
            </a>

            <a href="students.php" class="sidebar__nav-link">
                <svg class="sidebar__nav-icon" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <circle cx="10" cy="7" r="3" stroke="currentColor" stroke-width="1.6"/>
                    <path d="M4 16c0-2.4 2.7-4.2 6-4.2s6 1.8 6 4.2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
                Students
            </a>

        </nav>

        <div class="sidebar__footer">
            <a href="settings.php" class="sidebar__nav-link">
                <svg class="sidebar__nav-icon" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="M10 2l2 2.2 3-.2.6 2.9 2.4 1.8-1.7 2.5.6 2.9-2.9.7-1.9 2.3-2.5-1.6-2.5 1.6-1.9-2.3-2.9-.7.6-2.9L1.9 8.7l2.4-1.8.6-2.9 3 .2L10 2z" stroke="currentColor" stroke-width="1.4"/>
                    <circle cx="10" cy="10" r="2.3" stroke="currentColor" stroke-width="1.4"/>
                </svg>
                Settings
            </a>
            <a href="logout.php" class="sidebar__nav-link">
                <svg class="sidebar__nav-icon" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="M8 3H4.5A1.5 1.5 0 003 4.5v11A1.5 1.5 0 004.5 17H8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    <path d="M12 7l3 3-3 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M15 10H7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
                Sign Out
            </a>
        </div>

    </aside>

    <!-- ══════════════════ MAIN ══════════════════ -->
    <div class="main">

        <header class="topbar" role="banner">
            <div class="topbar__left-wrap">
                <button class="topbar__hamburger" id="hamburger-btn" aria-expanded="false" aria-controls="sidebar" aria-label="Toggle navigation">
                    <span class="topbar__hamburger-bar"></span>
                    <span class="topbar__hamburger-bar"></span>
                    <span class="topbar__hamburger-bar"></span>
                </button>
                <div class="topbar__left">
                    <div class="topbar__title">Dashboard</div>
                    <div class="topbar__sub"><?= htmlspecialchars($department) ?></div>
                </div>
            </div>
            <div class="topbar__right">
                <div class="topbar__notif-btn" role="button" aria-label="Notifications" tabindex="0">
                    <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <path d="M15 6.67A5 5 0 0 0 5 6.67C5 12.5 2.5 14.17 2.5 14.17h15S15 12.5 15 6.67Z" stroke="#364153" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M11.44 17.5a1.67 1.67 0 0 1-2.88 0" stroke="#364153" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    <span class="topbar__notif-dot" aria-hidden="true"></span>
                </div>
                <div class="topbar__user-info" aria-label="Logged in user">
                    <div class="topbar__user-name"><?= htmlspecialchars($admin_name) ?></div>
                    <div class="topbar__user-role"><?= htmlspecialchars($admin_role) ?></div>
                </div>
                <div class="topbar__avatar" aria-hidden="true">
                    <svg viewBox="0 0 20 20" fill="none">
                        <path d="M10 10a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM17.5 17.5c0-4.14-3.36-7.5-7.5-7.5S2.5 13.36 2.5 17.5" stroke="white" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                </div>
            </div>
        </header>

        <main class="dashboard" id="main-content">

            <!-- STAT CARDS -->
            <div class="stat-grid" role="list" aria-label="Key metrics">

                <div class="stat-card" role="listitem">
                    <div class="stat-card__top">
                        <div class="stat-card__icon-wrap" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" stroke="#4a5565" stroke-width="1.5" stroke-linecap="round"/>
                                <circle cx="9" cy="7" r="4" stroke="#4a5565" stroke-width="1.5"/>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" stroke="#4a5565" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <span class="stat-card__trend" aria-label="Trending up">↑</span>
                    </div>
                    <div class="stat-card__value"><?php echo $total_applications; ?></div>
                    <div class="stat-card__label">Active Student Assistants</div>
                    <div class="stat-card__sub">+3 this month</div>
                </div>

                <div class="stat-card" role="listitem">
                    <div class="stat-card__top">
                        <div class="stat-card__icon-wrap" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8L14 2Z" stroke="#4a5565" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M14 2v6h6M16 13H8M16 17H8M10 9H8" stroke="#4a5565" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-card__value"><?php echo $todays_applications; ?></div>
                    <div class="stat-card__label">Pending Applications</div>
                    <div class="stat-card__sub">Needs review</div>
                </div>

                <div class="stat-card" role="listitem">
                    <div class="stat-card__top">
                        <div class="stat-card__icon-wrap" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none">
                                <circle cx="12" cy="12" r="9" stroke="#4a5565" stroke-width="1.5"/>
                                <path d="M12 7v5l3 3" stroke="#4a5565" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <span class="stat-card__trend" aria-label="Trending up">↑</span>
                    </div>
                    <div class="stat-card__value"><?php echo $total_hours_month; ?></div>
                    <div class="stat-card__label">Total Hours (This Month)</div>
                    <div class="stat-card__sub">+12% from last month</div>
                </div>

                <div class="stat-card" role="listitem">
                    <div class="stat-card__top">
                        <div class="stat-card__icon-wrap" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none">
                                <path d="M22 7L13.5 15.5L8.5 10.5L2 17" stroke="#4a5565" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M16 7h6v6" stroke="#4a5565" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <span class="stat-card__trend" aria-label="Trending up">↑</span>
                    </div>
                    <div class="stat-card__value"><?php echo number_format($avg_performance, 1); ?>/5</div>
                    <div class="stat-card__label">Avg. Performance Rating</div>
                    <div class="stat-card__sub">+0.2 improvement</div>
                </div>

            </div>

            <!-- MID ROW -->
            <div class="mid-row">

                <section class="card" aria-labelledby="recent-apps-heading">
                    <div class="card__header">
                        <h2 class="card__title" id="recent-apps-heading">Recent Applications</h2>
                        <a href="application.php" class="card__link">View All →</a>
                    </div>
                    <div class="app-list">
                        <?php if (empty($recent_applications)): ?>
                            <div class="app-row">
                                <div class="app-row__info">
                                    <div class="app-row__name">No applications yet</div>
                                    <div class="app-row__detail">New submissions will appear here in real time.</div>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_applications as $app): ?>
                                <?php
                                    $fullName = $app['full_name'] ?? '';
                                    $studentId = $app['student_id'] ?? '';
                                    $course    = $app['course'] ?? '';
                                    $createdAt = $app['created_at'] ?? '';
                                    $status    = strtolower(trim((string) ($app['application_status'] ?? 'pending')));

                                    $badgeClass = 'app-row__badge--pending';
                                    $badgeLabel = 'pending';
                                    if ($status === 'approved') {
                                        $badgeClass = 'app-row__badge--approved';
                                        $badgeLabel = 'approved';
                                    } elseif ($status === 'rejected') {
                                        $badgeClass = 'app-row__badge--rejected';
                                        $badgeLabel = 'rejected';
                                    }

                                    $nameParts = preg_split('/\s+/', trim($fullName));
                                    $initials  = '';
                                    if (!empty($nameParts[0])) {
                                        $initials .= strtoupper(substr($nameParts[0], 0, 1));
                                    }
                                    if (count($nameParts) > 1 && !empty($nameParts[count($nameParts)-1])) {
                                        $initials .= strtoupper(substr($nameParts[count($nameParts)-1], 0, 1));
                                    }

                                    $dateDisplay = '';
                                    if (!empty($createdAt)) {
                                        $timestamp = strtotime($createdAt);
                                        if ($timestamp !== false) {
                                            $dateDisplay = date('M d, Y', $timestamp);
                                        }
                                    }
                                ?>
                                <div class="app-row">
                                    <div class="app-row__avatar" aria-hidden="true"><?php echo htmlspecialchars($initials ?: 'SA'); ?></div>
                                    <div class="app-row__info">
                                        <div class="app-row__name"><?php echo htmlspecialchars($fullName); ?></div>
                                        <div class="app-row__detail"><?php echo htmlspecialchars($studentId); ?> • <?php echo htmlspecialchars($course); ?></div>
                                    </div>
                                    <div class="app-row__meta">
                                        <span class="app-row__badge <?php echo htmlspecialchars($badgeClass); ?>"><?php echo htmlspecialchars($badgeLabel); ?></span>
                                        <div class="app-row__date"><?php echo htmlspecialchars($dateDisplay ?: ''); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="card" aria-labelledby="qa-heading">
                    <h2 class="card__title" id="qa-heading" style="margin-bottom:24px;">Quick Actions</h2>
                    <div class="qa-list">
                        <a href="application.php" class="qa-btn"><span class="qa-btn__emoji" aria-hidden="true">📋</span> Review Applications</a>
                        <a href="scheduling.php"   class="qa-btn"><span class="qa-btn__emoji" aria-hidden="true">📅</span> Create Schedule</a>
                        <a href="reports.php"      class="qa-btn"><span class="qa-btn__emoji" aria-hidden="true">📄</span> Generate Reports</a>
                        <a href="evaluation.php"   class="qa-btn"><span class="qa-btn__emoji" aria-hidden="true">✍️</span> Evaluate Students</a>
                    </div>
                </section>

            </div>

            <!-- BOTTOM ROW -->
            <div class="bottom-row">

                <section class="card" aria-labelledby="attendance-heading">
                    <div class="card__header">
                        <h2 class="card__title" id="attendance-heading">Today's Attendance</h2>
                        <a href="attendance.php" class="card__link">View All →</a>
                    </div>
                    <div class="att-list">
                        <?php if (empty($today_attendance)): ?>
                            <div class="att-row">
                                <span class="att-row__dot att-row__dot--absent" aria-label="No records"></span>
                                <div class="att-row__info">
                                    <div class="att-row__name">No attendance records yet</div>
                                    <div class="att-row__location">Today's check-ins will appear here.</div>
                                </div>
                                <div class="att-row__time">&nbsp;</div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($today_attendance as $row): ?>
                                <?php
                                    $name     = $row['student_name'] ?? '';
                                    $location = $row['location'] ?? '';
                                    $timeRaw  = $row['check_in_time'] ?? '';
                                    $status   = strtolower($row['status'] ?? 'present');

                                    $timeDisplay = '';
                                    if (!empty($timeRaw)) {
                                        $ts = strtotime($timeRaw);
                                        if ($ts !== false) {
                                            $timeDisplay = date('g:i A', $ts);
                                        }
                                    }

                                    $dotClass = $status === 'present' ? 'att-row__dot--present' : 'att-row__dot--absent';
                                ?>
                                <div class="att-row">
                                    <span class="att-row__dot <?php echo $dotClass; ?>" aria-hidden="true"></span>
                                    <div class="att-row__info">
                                        <div class="att-row__name"><?php echo htmlspecialchars($name); ?></div>
                                        <div class="att-row__location"><?php echo htmlspecialchars($location); ?></div>
                                    </div>
                                    <div class="att-row__time"><?php echo htmlspecialchars($timeDisplay ?: 'Not yet'); ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="card" aria-labelledby="alerts-heading">
                    <h2 class="card__title" id="alerts-heading" style="margin-bottom:24px;">Alerts &amp; Notifications</h2>
                    <div class="alert-list">
                        <?php if (empty($alerts)): ?>
                            <div class="alert-item alert-item--info" role="status">
                                <svg class="alert-item__icon" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                    <circle cx="10" cy="10" r="8" stroke="#155dfc" stroke-width="1.5"/>
                                    <path d="M10 9v5" stroke="#155dfc" stroke-width="1.5" stroke-linecap="round"/>
                                    <circle cx="10" cy="6.5" r=".75" fill="#155dfc"/>
                                </svg>
                                <div>
                                    <div class="alert-item__title">No alerts yet</div>
                                    <div class="alert-item__body">Announcements and reminders will appear here.</div>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($alerts as $alert): ?>
                                <?php
                                    $type  = strtolower($alert['type'] ?? 'info');
                                    $title = $alert['title'] ?? '';
                                    $body  = $alert['body'] ?? '';

                                    $class = 'alert-item--info';
                                    if ($type === 'warn' || $type === 'warning') {
                                        $class = 'alert-item--warn';
                                    } elseif ($type === 'success') {
                                        $class = 'alert-item--succ';
                                    }
                                ?>
                                <div class="alert-item <?php echo $class; ?>">
                                    <svg class="alert-item__icon" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                        <circle cx="10" cy="10" r="8" stroke="#155dfc" stroke-width="1.5"/>
                                        <path d="M10 9v5" stroke="#155dfc" stroke-width="1.5" stroke-linecap="round"/>
                                        <circle cx="10" cy="6.5" r=".75" fill="#155dfc"/>
                                    </svg>
                                    <div>
                                        <div class="alert-item__title"><?php echo htmlspecialchars($title); ?></div>
                                        <div class="alert-item__body"><?php echo htmlspecialchars($body); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>

            </div>

        </main>
    </div>

</div>

<script>
(function () {
    'use strict';
    var sidebar   = document.getElementById('sidebar');
    var overlay   = document.getElementById('sidebar-overlay');
    var hamburger = document.getElementById('hamburger-btn');

    function open()  { sidebar.classList.add('sidebar--open'); overlay.classList.add('sidebar-overlay--visible'); hamburger.setAttribute('aria-expanded','true'); overlay.setAttribute('aria-hidden','false'); }
    function close() { sidebar.classList.remove('sidebar--open'); overlay.classList.remove('sidebar-overlay--visible'); hamburger.setAttribute('aria-expanded','false'); overlay.setAttribute('aria-hidden','true'); }

    if (hamburger) hamburger.addEventListener('click', function () { sidebar.classList.contains('sidebar--open') ? close() : open(); });
    if (overlay)   overlay.addEventListener('click', close);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
    window.addEventListener('resize', function () { if (window.innerWidth > 1024) close(); });
})();
</script>

</body>
</html>