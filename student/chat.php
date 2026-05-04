<?php
// student/chat.php - student to admin chat

session_start();

if (!isset($_SESSION['student_id']) || !isset($_SESSION['student_name'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../chat_common.php';

chat_ensure_table($mysqli);

$student_id = (string) $_SESSION['student_id'];
$student_name = (string) $_SESSION['student_name'];
$student_context = chat_get_student_context($mysqli, $student_id);

if (($student_context['application_status'] ?? 'pending') !== 'approved') {
    unset($_SESSION['student_id'], $_SESSION['student_name']);
    header('Location: ../status.php?student_id=' . urlencode($student_id));
    exit;
}

if (!empty($student_context['full_name'])) {
    $student_name = (string) $student_context['full_name'];
}

$chat_notice = '';
$chat_notice_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim((string) ($_POST['message'] ?? ''));
    if ($message === '') {
        $chat_notice = 'Please type a message before sending.';
        $chat_notice_type = 'error';
    } elseif (chat_send_message($mysqli, $student_id, 'student', $student_name, $message)) {
        header('Location: chat.php?sent=1');
        exit;
    } else {
        $chat_notice = 'Unable to send your message right now.';
        $chat_notice_type = 'error';
    }
}

if (isset($_GET['sent'])) {
    $chat_notice = 'Message sent to admin.';
    $chat_notice_type = 'success';
}

chat_mark_read_by_student($mysqli, $student_id);
$messages = chat_fetch_thread($mysqli, $student_id);
$work_location = trim((string) ($student_context['work_location'] ?? ''));
$work_location = $work_location !== '' ? $work_location : 'Assigned Office';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <script src="../assets/realtime.js"></script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages | NU SAMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --clr-white: #FFFFFF;
            --clr-bg-page: linear-gradient(137.05deg, #EFF6FF 0%, #FFFFFF 50%, #FFFBEB 100%);
            --clr-border: #E5E7EB;
            --clr-border-card: #F3F4F6;
            --clr-bg-muted: #F9FAFB;
            --clr-bg-grey: #F3F4F6;
            --clr-text-primary: #101828;
            --clr-text-body: #364153;
            --clr-text-muted: #4A5565;
            --clr-text-subtle: #6A7282;
            --clr-text-light: #BEDBFF;
            --clr-navy: #003087;
            --grad-navy-v: linear-gradient(180deg,
                               #003087 0%, #00328B 10%, #00358E 20%, #003792 30%,
                               #003995 40%, #003B99 50%, #003E9C 60%, #0040A0 70%,
                               #0042A4 80%, #0045A7 90%, #0047AB 100%);
            --grad-navy-h: linear-gradient(90deg,
                               #003087 0%, #00328B 10%, #00358E 20%, #003792 30%,
                               #003995 40%, #003B99 50%, #003E9C 60%, #0040A0 70%,
                               #0042A4 80%, #0045A7 90%, #0047AB 100%);
            --shadow-sm: 0 1px 3px rgba(0,0,0,.10), 0 1px 2px rgba(0,0,0,.10);
            --shadow-md: 0 10px 15px rgba(0,0,0,.10), 0 4px 6px rgba(0,0,0,.10);
            --sidebar-width: 288px;
            --fs-xs: 12px;
            --fs-sm: 14px;
            --fs-base: 16px;
            --fs-md: 18px;
            --fs-lg: 20px;
            --fs-xl: 24px;
            --sp-4: 4px;
            --sp-8: 8px;
            --sp-12: 12px;
            --sp-16: 16px;
            --sp-24: 24px;
            --radius-sm: 10px;
            --radius-md: 14px;
            --radius-lg: 16px;
            --radius-pill: 9999px;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { font-size: 16px; }
        body {
            font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
            background: var(--clr-bg-page);
            min-height: 100vh;
            display: flex;
            color: var(--clr-text-primary);
        }
        a { text-decoration: none; color: inherit; }
        button { cursor: pointer; font-family: inherit; border: none; background: none; }
        textarea { font: inherit; }
        ul { list-style: none; }

        .app { display: flex; width: 100%; min-height: 100vh; }
        .sidebar {
            width: var(--sidebar-width);
            min-height: 100vh;
            background: var(--grad-navy-v);
            box-shadow: 0 25px 50px rgba(0,0,0,.25);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            z-index: 100;
        }
        .sidebar__header {
            border-bottom: 1px solid rgba(255,255,255,.20);
            padding: var(--sp-24) var(--sp-24) 0;
            height: 105px;
            flex-shrink: 0;
        }
        .sidebar__brand { display: flex; align-items: center; gap: var(--sp-12); height: 48px; }
        .sidebar__logo {
            width: 48px;
            height: 48px;
            background: var(--clr-white);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .sidebar__logo-text { font-size: var(--fs-xl); font-weight: 900; color: var(--clr-navy); line-height: 32px; }
        .sidebar__brand-info { display: flex; flex-direction: column; }
        .sidebar__app-name { font-size: var(--fs-lg); font-weight: 900; color: var(--clr-white); line-height: 28px; }
        .sidebar__app-sub { font-size: var(--fs-xs); font-weight: 400; color: var(--clr-text-light); line-height: 16px; white-space: nowrap; }
        .sidebar__nav { flex: 1; padding: var(--sp-24) var(--sp-16) 0; }
        .nav__list { display: flex; flex-direction: column; gap: var(--sp-8); }
        .nav__link {
            display: flex;
            align-items: center;
            gap: var(--sp-12);
            height: 48px;
            padding-left: var(--sp-16);
            border-radius: var(--radius-md);
            transition: background 0.15s;
        }
        .nav__link:hover { background: rgba(255,255,255,.10); }
        .nav__link--active { background: var(--clr-white); box-shadow: var(--shadow-md); }
        .nav__icon { width: 20px; height: 20px; flex-shrink: 0; }
        .nav__icon svg { width: 100%; height: 100%; }
        .nav__label { font-size: var(--fs-base); font-weight: 700; color: var(--clr-white); line-height: 24px; white-space: nowrap; }
        .nav__link--active .nav__label { color: var(--clr-navy); }
        .sidebar__footer {
            border-top: 1px solid rgba(255,255,255,.20);
            padding: 17px var(--sp-16) var(--sp-16);
            display: flex;
            flex-direction: column;
            gap: var(--sp-12);
            flex-shrink: 0;
        }
        .sidebar__user-card {
            background: rgba(255,255,255,.10);
            border-radius: var(--radius-md);
            padding: var(--sp-16) var(--sp-16) var(--sp-8);
            display: flex;
            flex-direction: column;
            gap: 2px;
            height: 92px;
        }
        .sidebar__user-label { font-size: var(--fs-sm); font-weight: 500; color: var(--clr-text-light); line-height: 20px; }
        .sidebar__user-name { font-size: var(--fs-base); font-weight: 900; color: var(--clr-white); line-height: 24px; }
        .sidebar__user-id { font-size: var(--fs-xs); font-weight: 400; color: var(--clr-text-light); line-height: 16px; }
        .sidebar__logout {
            display: flex;
            align-items: center;
            gap: var(--sp-8);
            height: 48px;
            padding-left: var(--sp-16);
            border-radius: var(--radius-md);
            background: rgba(255,255,255,.10);
            width: 100%;
            transition: background 0.15s;
        }
        .sidebar__logout:hover { background: rgba(255,255,255,.18); }
        .sidebar__logout-icon { width: 20px; height: 20px; flex-shrink: 0; }
        .sidebar__logout-icon svg { width: 100%; height: 100%; }
        .sidebar__logout-label { font-size: var(--fs-base); font-weight: 700; color: var(--clr-white); line-height: 24px; }

        .main { flex: 1; min-width: 0; display: flex; flex-direction: column; }
        .topbar {
            height: 89px;
            background: var(--clr-white);
            border-bottom: 1px solid var(--clr-border);
            padding: 0 var(--sp-24);
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--shadow-sm);
            flex-shrink: 0;
            z-index: 10;
        }
        .topbar__inner { width: 100%; display: flex; align-items: center; justify-content: space-between; gap: var(--sp-16); }
        .topbar__left { display: flex; align-items: center; gap: var(--sp-16); min-width: 0; }
        .topbar__heading { display: flex; flex-direction: column; min-width: 0; }
        .topbar__title { font-size: var(--fs-lg); font-weight: 900; color: var(--clr-text-primary); line-height: 32px; }
        .topbar__subtitle { font-size: var(--fs-sm); color: var(--clr-text-muted); line-height: 20px; }
        .topbar__actions { display: flex; align-items: center; gap: var(--sp-12); }
        .topbar__action-btn {
            width: 40px;
            height: 40px;
            border: 1px solid var(--clr-border);
            border-radius: var(--radius-sm);
            background: var(--clr-white);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        .topbar__action-btn svg { width: 20px; height: 20px; }
        .topbar__badge {
            position: absolute;
            top: 8px;
            right: 9px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #FB2C36;
            border: 1px solid var(--clr-white);
        }
        .hamburger {
            width: 40px;
            height: 40px;
            border: 1px solid var(--clr-border);
            border-radius: var(--radius-sm);
            display: none;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 4px;
            background: var(--clr-white);
        }
        .hamburger__bar { width: 16px; height: 2px; border-radius: 999px; background: var(--clr-text-body); }

        .page-content { padding: var(--sp-24); display: flex; flex-direction: column; gap: var(--sp-24); }
        .chat-grid { display: grid; grid-template-columns: minmax(0, 1.6fr) 360px; gap: var(--sp-24); }
        .chat-card {
            background: var(--clr-white);
            border: 2px solid var(--clr-border-card);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .chat-card__head {
            border-bottom: 1px solid var(--clr-border);
            padding: var(--sp-24);
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: var(--sp-12);
        }
        .chat-card__title { font-size: var(--fs-md); font-weight: 900; color: var(--clr-text-primary); line-height: 28px; }
        .chat-card__sub { font-size: var(--fs-sm); color: var(--clr-text-muted); line-height: 20px; }
        .chat-chip {
            display: inline-flex;
            align-items: center;
            height: 28px;
            padding: 0 12px;
            border-radius: var(--radius-pill);
            background: #DBEAFE;
            color: #1447E6;
            font-size: var(--fs-xs);
            font-weight: 800;
            white-space: nowrap;
        }
        .chat-notice {
            margin: var(--sp-16) var(--sp-24) 0;
            border-radius: var(--radius-md);
            padding: 14px 16px;
            font-size: var(--fs-sm);
            font-weight: 700;
            line-height: 20px;
        }
        .chat-notice--success { background: #ECFDF3; border: 1px solid #ABEFC6; color: #027A48; }
        .chat-notice--error { background: #FEF2F2; border: 1px solid #FECACA; color: #B42318; }
        .thread {
            padding: var(--sp-16) var(--sp-24) var(--sp-24);
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-height: 56vh;
            overflow-y: auto;
            background: var(--clr-bg-muted);
            border-top: 1px solid var(--clr-border);
            border-bottom: 1px solid var(--clr-border);
        }
        .msg {
            max-width: 85%;
            border-radius: var(--radius-md);
            padding: 12px 14px;
            display: flex;
            flex-direction: column;
            gap: 6px;
            font-size: var(--fs-sm);
            line-height: 20px;
        }
        .msg--student {
            align-self: flex-end;
            background: var(--grad-navy-h);
            color: var(--clr-white);
            border-bottom-right-radius: 4px;
            box-shadow: var(--shadow-sm);
        }
        .msg--admin {
            align-self: flex-start;
            background: var(--clr-white);
            color: var(--clr-text-primary);
            border: 1px solid var(--clr-border);
            border-bottom-left-radius: 4px;
        }
        .msg__meta {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            font-size: var(--fs-xs);
            opacity: .85;
            font-weight: 700;
        }
        .thread-empty {
            padding: var(--sp-24);
            border-radius: var(--radius-md);
            border: 1px dashed var(--clr-border);
            background: var(--clr-white);
            color: var(--clr-text-muted);
            font-size: var(--fs-sm);
            text-align: center;
        }
        .composer { padding: var(--sp-16) var(--sp-24) var(--sp-24); display: flex; flex-direction: column; gap: var(--sp-12); }
        .composer__input {
            width: 100%;
            min-height: 110px;
            resize: vertical;
            border: 1px solid #D1D5DC;
            border-radius: var(--radius-md);
            padding: 14px 16px;
            font-size: var(--fs-base);
            color: var(--clr-text-primary);
            background: var(--clr-white);
            outline: none;
        }
        .composer__input:focus { border-color: var(--clr-navy); box-shadow: 0 0 0 3px rgba(0, 48, 135, .12); }
        .composer__row { display: flex; align-items: center; justify-content: space-between; gap: var(--sp-12); }
        .composer__hint { font-size: var(--fs-sm); color: var(--clr-text-muted); }
        .btn-primary {
            height: 44px;
            border-radius: var(--radius-md);
            background: var(--clr-navy);
            color: var(--clr-white);
            font-size: var(--fs-sm);
            font-weight: 800;
            padding: 0 16px;
        }

        .info-list { padding: var(--sp-24); display: flex; flex-direction: column; gap: var(--sp-12); }
        .info-item {
            background: var(--clr-bg-muted);
            border: 1px solid var(--clr-border);
            border-radius: var(--radius-md);
            padding: var(--sp-16);
        }
        .info-item__label {
            font-size: var(--fs-xs);
            color: var(--clr-text-muted);
            text-transform: uppercase;
            letter-spacing: .04em;
            font-weight: 700;
        }
        .info-item__value {
            margin-top: 4px;
            font-size: var(--fs-base);
            font-weight: 900;
            color: var(--clr-text-primary);
            line-height: 24px;
            word-break: break-word;
        }

        .sidebar-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.4);
            z-index: 99;
        }
        .sidebar-overlay--hidden { display: none; }
        .sidebar-overlay--visible { display: block; }

        @media (max-width: 1024px) {
            .sidebar {
                position: fixed;
                left: -100%;
                top: 0;
                height: 100vh;
                transition: left 0.28s ease;
                z-index: 200;
            }
            .sidebar--open { left: 0; }
            .hamburger { display: flex; }
            .topbar { padding: 0 var(--sp-16); }
            .page-content { padding: var(--sp-16); }
            .chat-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .topbar__title { font-size: 18px; }
            .topbar__subtitle { font-size: var(--fs-xs); }
            .page-content { padding: var(--sp-12); gap: var(--sp-16); }
            .chat-card__head { flex-direction: column; }
            .msg { max-width: 92%; }
            .composer__row { flex-direction: column; align-items: stretch; }
            .btn-primary { width: 100%; }
        }
    </style>
</head>
<body>
<div class="sidebar-overlay sidebar-overlay--hidden" id="sidebarOverlay"></div>

<div class="app">
    <aside class="sidebar" id="sidebar" role="navigation" aria-label="Student portal navigation">
        <div class="sidebar__header">
            <div class="sidebar__brand">
                <div class="sidebar__logo" aria-hidden="true">
                    <span class="sidebar__logo-text">NU</span>
                </div>
                <div class="sidebar__brand-info">
                    <span class="sidebar__app-name">SAMS</span>
                    <span class="sidebar__app-sub">Student Assistant Management</span>
                </div>
            </div>
        </div>

        <nav class="sidebar__nav" aria-label="Main menu">
            <ul class="nav__list">
                <li class="nav__item">
                    <a href="dashboard.php" class="nav__link">
                        <span class="nav__icon" aria-hidden="true">
                            <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="2" y="2" width="7" height="7" rx="1.5" fill="rgba(255,255,255,0.7)"/>
                                <rect x="11" y="2" width="7" height="7" rx="1.5" fill="rgba(255,255,255,0.7)"/>
                                <rect x="2" y="11" width="7" height="7" rx="1.5" fill="rgba(255,255,255,0.7)"/>
                                <rect x="11" y="11" width="7" height="7" rx="1.5" fill="rgba(255,255,255,0.7)"/>
                            </svg>
                        </span>
                        <span class="nav__label">Dashboard</span>
                    </a>
                </li>
                <li class="nav__item">
                    <a href="schedule.php" class="nav__link">
                        <span class="nav__icon" aria-hidden="true">
                            <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="2" y="4" width="16" height="14" rx="2" stroke="rgba(255,255,255,0.7)" stroke-width="1.5"/>
                                <path d="M6 2v4M14 2v4" stroke="rgba(255,255,255,0.7)" stroke-width="1.5" stroke-linecap="round"/>
                                <path d="M2 9h16" stroke="rgba(255,255,255,0.7)" stroke-width="1.2"/>
                            </svg>
                        </span>
                        <span class="nav__label">My Schedule</span>
                    </a>
                </li>
                <li class="nav__item">
                    <a href="attendance.php" class="nav__link">
                        <span class="nav__icon" aria-hidden="true">
                            <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="10" cy="10" r="8" stroke="rgba(255,255,255,0.7)" stroke-width="1.5"/>
                                <path d="M6.5 10.5l2.5 2.5 4.5-5" stroke="rgba(255,255,255,0.7)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <span class="nav__label">Attendance</span>
                    </a>
                </li>
                <li class="nav__item">
                    <a href="chat.php" class="nav__link nav__link--active" aria-current="page">
                        <span class="nav__icon" aria-hidden="true">
                            <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M4 4h12a2 2 0 012 2v6a2 2 0 01-2 2H9l-4 3v-3H4a2 2 0 01-2-2V6a2 2 0 012-2z" stroke="#003087" stroke-width="1.5" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <span class="nav__label">Messages</span>
                    </a>
                </li>
                <li class="nav__item">
                    <a href="profile.php" class="nav__link">
                        <span class="nav__icon" aria-hidden="true">
                            <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="10" cy="7" r="4" stroke="rgba(255,255,255,0.7)" stroke-width="1.5"/>
                                <path d="M2 17c0-3.314 3.582-6 8-6s8 2.686 8 6" stroke="rgba(255,255,255,0.7)" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                        </span>
                        <span class="nav__label">Profile</span>
                    </a>
                </li>
            </ul>
        </nav>

        <div class="sidebar__footer">
            <div class="sidebar__user-card">
                <span class="sidebar__user-label">Logged in as</span>
                <span class="sidebar__user-name"><?php echo htmlspecialchars($student_name); ?></span>
                <span class="sidebar__user-id">Student ID: <?php echo htmlspecialchars($student_id); ?></span>
            </div>
            <button class="sidebar__logout" type="button" onclick="window.location.href='logout.php'">
                <span class="sidebar__logout-icon" aria-hidden="true">
                    <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M7 3H4a1 1 0 00-1 1v12a1 1 0 001 1h3" stroke="rgba(255,255,255,0.85)" stroke-width="1.5" stroke-linecap="round"/>
                        <path d="M13 14l3-4-3-4M16 10H7" stroke="rgba(255,255,255,0.85)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
                <span class="sidebar__logout-label">Logout</span>
            </button>
        </div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div class="topbar__inner">
                <div class="topbar__left">
                    <button class="hamburger" id="hamburgerBtn" type="button" aria-expanded="false" aria-controls="sidebar" aria-label="Toggle navigation">
                        <span class="hamburger__bar"></span>
                        <span class="hamburger__bar"></span>
                        <span class="hamburger__bar"></span>
                    </button>
                    <div class="topbar__heading">
                        <span class="topbar__title">Student Portal</span>
                        <span class="topbar__subtitle">National University - Lipa Campus</span>
                    </div>
                </div>
                <div class="topbar__actions">
                    <a href="#" class="topbar__action-btn" aria-label="Notifications">
                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" stroke="#4A5565" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span class="topbar__badge" aria-label="New notifications"></span>
                    </a>
                    <a href="#" class="topbar__action-btn" aria-label="Settings">
                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37a1.724 1.724 0 002.572-1.065z" stroke="#4A5565" stroke-width="1.8"/>
                            <circle cx="12" cy="12" r="3" stroke="#4A5565" stroke-width="1.8"/>
                        </svg>
                    </a>
                </div>
            </div>
        </header>

        <section class="page-content" aria-label="Student messaging page">
            <div class="chat-grid">
                <div class="chat-card">
                    <div class="chat-card__head">
                        <div>
                            <h1 class="chat-card__title">Conversation with Admin</h1>
                            <p class="chat-card__sub">Your concerns and updates are stored in one secure thread.</p>
                        </div>
                        <span class="chat-chip"><?php echo htmlspecialchars($work_location); ?></span>
                    </div>

                    <?php if ($chat_notice !== ''): ?>
                        <div class="chat-notice chat-notice--<?php echo htmlspecialchars($chat_notice_type); ?>"><?php echo htmlspecialchars($chat_notice); ?></div>
                    <?php endif; ?>

                    <div class="thread" id="threadContainer">
                        <?php if (empty($messages)): ?>
                            <div class="thread-empty">No messages yet. Send the first message to admin.</div>
                        <?php else: ?>
                            <?php foreach ($messages as $message): ?>
                                <div class="msg <?php echo $message['sender_role'] === 'admin' ? 'msg--admin' : 'msg--student'; ?>">
                                    <div class="msg__meta">
                                        <span><?php echo htmlspecialchars($message['sender_name']); ?></span>
                                        <span><?php echo htmlspecialchars(chat_format_time($message['created_at'])); ?></span>
                                    </div>
                                    <div><?php echo nl2br(htmlspecialchars($message['message'])); ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <form class="composer" method="post">
                        <textarea class="composer__input" name="message" maxlength="1000" placeholder="Write your message to the admin..." required></textarea>
                        <div class="composer__row">
                            <div class="composer__hint">You can send updates, concerns, and follow-up questions here.</div>
                            <button class="btn-primary" type="submit">Send Message</button>
                        </div>
                    </form>
                </div>

                <aside class="chat-card">
                    <div class="chat-card__head">
                        <div>
                            <h2 class="chat-card__title">Student Details</h2>
                            <p class="chat-card__sub">Details visible to admin in this thread</p>
                        </div>
                    </div>
                    <div class="info-list">
                        <div class="info-item">
                            <div class="info-item__label">Full Name</div>
                            <div class="info-item__value"><?php echo htmlspecialchars($student_name); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-item__label">Student ID</div>
                            <div class="info-item__value"><?php echo htmlspecialchars($student_id); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-item__label">Email</div>
                            <div class="info-item__value"><?php echo htmlspecialchars((string) ($student_context['email'] ?? '')); ?></div>
                        </div>
                    </div>
                </aside>
            </div>
        </section>
    </main>
</div>

<script>
(function () {
    'use strict';

    var hamburger = document.getElementById('hamburgerBtn');
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');
    var thread = document.getElementById('threadContainer');

    function openSidebar() {
        sidebar.classList.add('sidebar--open');
        overlay.classList.remove('sidebar-overlay--hidden');
        overlay.classList.add('sidebar-overlay--visible');
        hamburger.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        sidebar.classList.remove('sidebar--open');
        overlay.classList.add('sidebar-overlay--hidden');
        overlay.classList.remove('sidebar-overlay--visible');
        hamburger.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
    }

    if (thread) {
        thread.scrollTop = thread.scrollHeight;
    }

    if (hamburger) {
        hamburger.addEventListener('click', function () {
            sidebar.classList.contains('sidebar--open') ? closeSidebar() : openSidebar();
        });
    }

    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && sidebar.classList.contains('sidebar--open')) {
            closeSidebar();
            if (hamburger) {
                hamburger.focus();
            }
        }
    });
})();
</script>
</body>
</html>
