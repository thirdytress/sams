<?php
// admin/chat.php - admin side chat with students

session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../chat_common.php';

chat_ensure_table($mysqli);

$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$admin_role = $_SESSION['admin_role'] ?? 'SDAO Head';

$threads = chat_fetch_student_threads($mysqli);
$selected_student_id = trim((string) ($_GET['student_id'] ?? ''));
if ($selected_student_id === '' && !empty($threads)) {
    $selected_student_id = (string) ($threads[0]['student_id'] ?? '');
}

$chat_notice = '';
$chat_notice_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_student_id = trim((string) ($_POST['student_id'] ?? $selected_student_id));
    $message = trim((string) ($_POST['message'] ?? ''));

    if ($selected_student_id === '') {
        $chat_notice = 'Select a student first.';
        $chat_notice_type = 'error';
    } elseif ($message === '') {
        $chat_notice = 'Please type a reply before sending.';
        $chat_notice_type = 'error';
    } elseif (chat_send_message($mysqli, $selected_student_id, 'admin', $admin_name, $message)) {
        header('Location: chat.php?student_id=' . urlencode($selected_student_id) . '&sent=1');
        exit;
    } else {
        $chat_notice = 'Unable to send reply right now.';
        $chat_notice_type = 'error';
    }
}

if (isset($_GET['sent'])) {
    $chat_notice = 'Reply sent to student.';
    $chat_notice_type = 'success';
}

$selected_context = $selected_student_id !== '' ? chat_get_student_context($mysqli, $selected_student_id) : [];
if (($selected_context['student_id'] ?? '') === '' && !empty($threads)) {
    $selected_student_id = (string) ($threads[0]['student_id'] ?? '');
    $selected_context = chat_get_student_context($mysqli, $selected_student_id);
}

if ($selected_student_id !== '') {
    chat_mark_read_by_admin($mysqli, $selected_student_id);
}

$messages = $selected_student_id !== '' ? chat_fetch_thread($mysqli, $selected_student_id) : [];

function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <script src="../assets/realtime.js"></script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages | NU SAMS Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --clr-white: #FFFFFF;
            --clr-bg: #F9FAFB;
            --clr-border: #E5E7EB;
            --clr-border-input: #D1D5DC;
            --clr-text-primary: #101828;
            --clr-text-body: #364153;
            --clr-text-muted: #4A5565;
            --clr-blue: #155DFC;
            --clr-blue-dark: #1447E6;
            --clr-blue-bg: #DBEAFE;
            --clr-green: #008236;
            --clr-green-bg: #DCFCE7;
            --grad-brand: linear-gradient(135deg, #155DFC 0%, #9810FA 100%);
            --sidebar-width: 256px;
            --fs-xs: 12px;
            --fs-sm: 14px;
            --fs-base: 16px;
            --fs-md: 18px;
            --fs-lg: 24px;
            --sp-4: 4px;
            --sp-8: 8px;
            --sp-12: 12px;
            --sp-16: 16px;
            --sp-24: 24px;
            --radius-xs: 4px;
            --radius-sm: 10px;
            --radius-md: 16.4px;
            --radius-pill: 9999px;
            --shadow-sm: 0 1px 3px rgba(0,0,0,.10), 0 1px 2px rgba(0,0,0,.10);
            --shadow-md: 0 10px 15px rgba(0,0,0,.10), 0 4px 6px rgba(0,0,0,.10);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { font-size: 16px; }
        body {
            font-family: Arial, sans-serif;
            background: var(--clr-bg);
            color: var(--clr-text-primary);
            min-height: 100vh;
            display: flex;
        }
        a { text-decoration: none; color: inherit; }
        button { cursor: pointer; font-family: inherit; border: none; background: none; }
        textarea { font: inherit; }
        ul { list-style: none; }

        .app { display: flex; width: 100%; min-height: 100vh; }
        .sidebar {
            width: var(--sidebar-width);
            min-height: 100vh;
            background: var(--clr-white);
            border-right: 1px solid var(--clr-border);
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
            height: 89px;
            border-bottom: 1px solid var(--clr-border);
            padding: var(--sp-24) var(--sp-24) 0;
            flex-shrink: 0;
        }
        .sidebar__brand { display: flex; align-items: center; gap: var(--sp-12); height: 40px; }
        .sidebar__logo {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-sm);
            background: var(--grad-brand);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .sidebar__logo-text { font-size: 18px; font-weight: bold; color: var(--clr-white); line-height: 28px; }
        .sidebar__brand-info { display: flex; flex-direction: column; }
        .sidebar__app-name { font-size: var(--fs-base); font-weight: bold; color: var(--clr-text-primary); line-height: 24px; }
        .sidebar__app-sub { font-size: var(--fs-xs); color: var(--clr-text-muted); line-height: 16px; }
        .sidebar__nav { flex: 1; padding: var(--sp-16) var(--sp-16) 0; }
        .nav__list { display: flex; flex-direction: column; gap: var(--sp-4); }
        .nav__link {
            display: flex;
            align-items: center;
            gap: var(--sp-12);
            height: 48px;
            padding: 0 var(--sp-16);
            border-radius: var(--radius-sm);
            transition: background 0.15s;
        }
        .nav__link:hover { background: var(--clr-bg); }
        .nav__link--active { background: var(--clr-blue); }
        .nav__link--active .nav__label { color: var(--clr-white); }
        .nav__icon { width: 20px; height: 20px; flex-shrink: 0; display: flex; align-items: center; }
        .nav__icon svg { width: 100%; height: 100%; }
        .nav__label { font-size: var(--fs-base); color: var(--clr-text-body); line-height: 24px; flex: 1; white-space: nowrap; }
        .nav__badge {
            background: var(--clr-blue-bg);
            color: var(--clr-blue);
            font-size: var(--fs-xs);
            font-weight: bold;
            line-height: 16px;
            padding: 2px 8px;
            border-radius: var(--radius-pill);
            height: 20px;
            display: flex;
            align-items: center;
        }
        .sidebar__footer {
            border-top: 1px solid var(--clr-border);
            padding: 17px var(--sp-16) var(--sp-16);
            flex-shrink: 0;
        }

        .main {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
        }
        .topbar {
            background: var(--clr-white);
            border-bottom: 1px solid var(--clr-border);
            height: 89px;
            padding: 0 var(--sp-24);
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--shadow-sm);
            flex-shrink: 0;
            z-index: 10;
        }
        .topbar__left { display: flex; align-items: center; gap: var(--sp-16); min-width: 0; }
        .topbar__heading { display: flex; flex-direction: column; min-width: 0; }
        .topbar__title { font-size: var(--fs-lg); font-weight: bold; line-height: 32px; color: var(--clr-text-primary); }
        .topbar__subtitle { font-size: var(--fs-sm); color: var(--clr-text-muted); line-height: 20px; }
        .topbar__right { display: flex; align-items: center; gap: var(--sp-12); }
        .topbar__notif {
            width: 40px;
            height: 40px;
            border: 1px solid var(--clr-border);
            border-radius: var(--radius-sm);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            position: relative;
            background: var(--clr-white);
        }
        .topbar__notif svg { width: 20px; height: 20px; }
        .topbar__notif-dot {
            position: absolute;
            top: 8px;
            right: 9px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #FB2C36;
            border: 1px solid var(--clr-white);
        }
        .topbar__user {
            display: flex;
            align-items: center;
            gap: var(--sp-12);
            padding-left: var(--sp-12);
            border-left: 1px solid var(--clr-border);
        }
        .topbar__user-info {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            line-height: 1.2;
        }
        .topbar__user-name { font-size: var(--fs-sm); font-weight: bold; color: var(--clr-text-primary); }
        .topbar__user-role { font-size: var(--fs-xs); color: var(--clr-text-muted); }
        .topbar__avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--grad-brand);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .topbar__avatar svg { width: 20px; height: 20px; }
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

        .page-content { padding: var(--sp-24); display: grid; grid-template-columns: 340px minmax(0, 1fr); gap: var(--sp-24); }
        .panel {
            background: var(--clr-white);
            border: 1px solid var(--clr-border);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-md);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }
        .panel__head {
            border-bottom: 1px solid var(--clr-border);
            padding: var(--sp-24);
            display: flex;
            flex-direction: column;
            gap: var(--sp-4);
        }
        .panel__title { font-size: var(--fs-md); font-weight: bold; color: var(--clr-text-primary); line-height: 28px; }
        .panel__sub { font-size: var(--fs-sm); color: var(--clr-text-muted); line-height: 20px; }
        .notice {
            margin: var(--sp-16) var(--sp-16) 0;
            border-radius: var(--radius-sm);
            padding: 12px 14px;
            font-size: var(--fs-sm);
            font-weight: 700;
            line-height: 20px;
        }
        .notice--success { background: #ECFDF3; border: 1px solid #ABEFC6; color: #027A48; }
        .notice--error { background: #FEF2F2; border: 1px solid #FECACA; color: #B42318; }

        .thread-list {
            padding: var(--sp-12);
            display: flex;
            flex-direction: column;
            gap: var(--sp-8);
            max-height: calc(100vh - 250px);
            overflow-y: auto;
            background: var(--clr-bg);
        }
        .thread-item {
            display: block;
            border: 1px solid var(--clr-border);
            background: var(--clr-white);
            border-radius: var(--radius-sm);
            padding: 12px;
        }
        .thread-item:hover { border-color: var(--clr-blue); }
        .thread-item--active { background: var(--clr-blue-bg); border-color: var(--clr-blue); }
        .thread-item__top { display: flex; align-items: center; justify-content: space-between; gap: var(--sp-8); }
        .thread-item__name { font-size: var(--fs-base); font-weight: bold; color: var(--clr-text-primary); }
        .thread-item__badge {
            background: var(--clr-green-bg);
            color: var(--clr-green);
            border-radius: var(--radius-pill);
            padding: 3px 8px;
            font-size: var(--fs-xs);
            font-weight: bold;
        }
        .thread-item__meta {
            margin-top: 4px;
            font-size: var(--fs-xs);
            color: var(--clr-text-muted);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: var(--sp-8);
        }

        .thread {
            padding: var(--sp-16);
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-height: calc(100vh - 390px);
            overflow-y: auto;
            background: var(--clr-bg);
            border-top: 1px solid var(--clr-border);
            border-bottom: 1px solid var(--clr-border);
        }
        .msg {
            max-width: 82%;
            border-radius: var(--radius-sm);
            padding: 12px 14px;
            display: flex;
            flex-direction: column;
            gap: 6px;
            font-size: var(--fs-sm);
            line-height: 20px;
        }
        .msg--student {
            align-self: flex-start;
            background: var(--clr-white);
            color: var(--clr-text-primary);
            border: 1px solid var(--clr-border);
            border-bottom-left-radius: var(--radius-xs);
        }
        .msg--admin {
            align-self: flex-end;
            background: var(--clr-blue);
            color: var(--clr-white);
            border-bottom-right-radius: var(--radius-xs);
        }
        .msg__meta { font-size: var(--fs-xs); opacity: .85; display: flex; gap: var(--sp-8); flex-wrap: wrap; font-weight: 700; }
        .empty {
            padding: var(--sp-16);
            border: 1px dashed var(--clr-border);
            border-radius: var(--radius-sm);
            background: var(--clr-white);
            color: var(--clr-text-muted);
            font-size: var(--fs-sm);
            text-align: center;
        }
        .composer { border-top: 1px solid var(--clr-border); padding: var(--sp-16); display: flex; flex-direction: column; gap: var(--sp-12); }
        .composer__input {
            width: 100%;
            min-height: 104px;
            resize: vertical;
            border: 1px solid var(--clr-border-input);
            border-radius: var(--radius-sm);
            padding: 14px 16px;
            outline: none;
            color: var(--clr-text-primary);
        }
        .composer__input:focus { border-color: var(--clr-blue); box-shadow: 0 0 0 3px rgba(21, 93, 252, .12); }
        .composer__row { display: flex; justify-content: space-between; gap: var(--sp-12); align-items: center; }
        .composer__hint { color: var(--clr-text-muted); font-size: var(--fs-sm); }
        .btn-primary {
            height: 40px;
            border-radius: var(--radius-sm);
            background: var(--clr-blue);
            color: var(--clr-white);
            padding: 0 16px;
            font-size: var(--fs-sm);
            font-weight: bold;
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
            .page-content { padding: var(--sp-16); grid-template-columns: 1fr; }
            .topbar__user-info { display: none; }
            .thread-list { max-height: 320px; }
            .thread { max-height: 400px; }
        }
        @media (max-width: 768px) {
            .topbar__title { font-size: 18px; }
            .topbar__subtitle { font-size: var(--fs-xs); }
            .page-content { padding: var(--sp-12); gap: var(--sp-16); }
            .thread-item__meta { flex-direction: column; align-items: flex-start; }
            .msg { max-width: 92%; }
            .composer__row { flex-direction: column; align-items: stretch; }
            .btn-primary { width: 100%; }
        }
    </style>
</head>
<body>
<div class="sidebar-overlay sidebar-overlay--hidden" id="sidebarOverlay"></div>

<div class="app">
    <aside class="sidebar" id="sidebar" role="navigation" aria-label="Admin navigation">
        <div class="sidebar__header">
            <div class="sidebar__brand">
                <div class="sidebar__logo" aria-hidden="true">
                    <span class="sidebar__logo-text">NU</span>
                </div>
                <div class="sidebar__brand-info">
                    <span class="sidebar__app-name">SA System</span>
                    <span class="sidebar__app-sub">Admin Panel</span>
                </div>
            </div>
        </div>

        <nav class="sidebar__nav" aria-label="Main menu">
            <ul class="nav__list">
                <li class="nav__item">
                    <a href="dashboard.php" class="nav__link">
                        <span class="nav__icon" aria-hidden="true">
                            <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="2" y="2" width="7" height="7" rx="1.5" fill="#364153"/>
                                <rect x="11" y="2" width="7" height="7" rx="1.5" fill="#364153"/>
                                <rect x="2" y="11" width="7" height="7" rx="1.5" fill="#364153"/>
                                <rect x="11" y="11" width="7" height="7" rx="1.5" fill="#364153"/>
                            </svg>
                        </span>
                        <span class="nav__label">Dashboard</span>
                    </a>
                </li>
                <li class="nav__item">
                    <a href="application.php" class="nav__link">
                        <span class="nav__icon" aria-hidden="true">
                            <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M6 2h8a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V4a2 2 0 012-2z" stroke="#364153" stroke-width="1.5"/>
                                <path d="M7 7h6M7 10h6M7 13h4" stroke="#364153" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                        </span>
                        <span class="nav__label">Applications</span>
                        <span class="nav__badge" aria-label="12 pending">12</span>
                    </a>
                </li>
                <li class="nav__item">
                    <a href="scheduling.php" class="nav__link">
                        <span class="nav__icon" aria-hidden="true">
                            <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="2" y="4" width="16" height="14" rx="2" stroke="#364153" stroke-width="1.5"/>
                                <path d="M6 2v4M14 2v4" stroke="#364153" stroke-width="1.5" stroke-linecap="round"/>
                                <path d="M2 9h16" stroke="#364153" stroke-width="1.2"/>
                            </svg>
                        </span>
                        <span class="nav__label">Scheduling</span>
                    </a>
                </li>
                <li class="nav__item">
                    <a href="attendance.php" class="nav__link">
                        <span class="nav__icon" aria-hidden="true">
                            <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="10" cy="10" r="8" stroke="#364153" stroke-width="1.5"/>
                                <path d="M6.5 10.5l2.5 2.5 4.5-5" stroke="#364153" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <span class="nav__label">Attendance</span>
                    </a>
                </li>
                <li class="nav__item">
                    <a href="chat.php" class="nav__link nav__link--active" aria-current="page">
                        <span class="nav__icon" aria-hidden="true">
                            <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M4 4h12a2 2 0 012 2v6a2 2 0 01-2 2H9l-4 3v-3H4a2 2 0 01-2-2V6a2 2 0 012-2z" stroke="white" stroke-width="1.5" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <span class="nav__label">Messages</span>
                    </a>
                </li>
                <li class="nav__item">
                    <a href="documents.php" class="nav__link">
                        <span class="nav__icon" aria-hidden="true">
                            <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M5 2h7l4 4v12a1 1 0 01-1 1H5a1 1 0 01-1-1V3a1 1 0 011-1z" stroke="#364153" stroke-width="1.5"/>
                                <path d="M12 2v4h4" stroke="#364153" stroke-width="1.5" stroke-linecap="round"/>
                                <path d="M7 10h6M7 13h4" stroke="#364153" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                        </span>
                        <span class="nav__label">Documents</span>
                    </a>
                </li>
                <li class="nav__item">
                    <a href="evaluation.php" class="nav__link">
                        <span class="nav__icon" aria-hidden="true">
                            <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M10 2l2.09 4.26L17 7.27l-3.5 3.41.83 4.82L10 13.27l-4.33 2.23.83-4.82L3 7.27l4.91-.71L10 2z" stroke="#364153" stroke-width="1.5" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <span class="nav__label">Evaluation</span>
                    </a>
                </li>
                <li class="nav__item">
                    <a href="reports.php" class="nav__link">
                        <span class="nav__icon" aria-hidden="true">
                            <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="3" y="12" width="3" height="6" rx="1" fill="#364153"/>
                                <rect x="8.5" y="8" width="3" height="10" rx="1" fill="#364153"/>
                                <rect x="14" y="4" width="3" height="14" rx="1" fill="#364153"/>
                            </svg>
                        </span>
                        <span class="nav__label">Reports</span>
                    </a>
                </li>
                <li class="nav__item">
                    <a href="students.php" class="nav__link">
                        <span class="nav__icon" aria-hidden="true">
                            <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="10" cy="7" r="4" stroke="#364153" stroke-width="1.5"/>
                                <path d="M2 17c0-3.314 3.582-6 8-6s8 2.686 8 6" stroke="#364153" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                        </span>
                        <span class="nav__label">Students</span>
                    </a>
                </li>
            </ul>
        </nav>

        <div class="sidebar__footer">
            <ul class="nav__list">
                <li class="nav__item">
                    <a href="settings.php" class="nav__link">
                        <span class="nav__icon" aria-hidden="true">
                            <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M8.325 2.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37a1.724 1.724 0 002.572-1.065z" stroke="#364153" stroke-width="1.3"/>
                                <circle cx="10" cy="10" r="3" stroke="#364153" stroke-width="1.3"/>
                            </svg>
                        </span>
                        <span class="nav__label">Settings</span>
                    </a>
                </li>
                <li class="nav__item">
                    <a href="logout.php" class="nav__link">
                        <span class="nav__icon" aria-hidden="true">
                            <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M7 3H4a1 1 0 00-1 1v12a1 1 0 001 1h3" stroke="#364153" stroke-width="1.5" stroke-linecap="round"/>
                                <path d="M13 14l3-4-3-4M16 10H7" stroke="#364153" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <span class="nav__label">Sign Out</span>
                    </a>
                </li>
            </ul>
        </div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div class="topbar__left">
                <button class="hamburger" id="hamburgerBtn" type="button" aria-expanded="false" aria-controls="sidebar" aria-label="Toggle navigation">
                    <span class="hamburger__bar"></span>
                    <span class="hamburger__bar"></span>
                    <span class="hamburger__bar"></span>
                </button>
                <div class="topbar__heading">
                    <h1 class="topbar__title">Messages Hub</h1>
                    <p class="topbar__subtitle">NU Lipa - Student Development and Activities Office</p>
                </div>
            </div>
            <div class="topbar__right">
                <a href="#" class="topbar__notif" aria-label="Notifications">
                    <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z" fill="#4A5565"/>
                    </svg>
                    <span class="topbar__notif-dot" aria-label="New notifications"></span>
                </a>
                <div class="topbar__user">
                    <div class="topbar__user-info">
                        <span class="topbar__user-name"><?php echo h($admin_name); ?></span>
                        <span class="topbar__user-role"><?php echo h($admin_role); ?></span>
                    </div>
                    <div class="topbar__avatar" aria-hidden="true">
                        <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="10" cy="7" r="4" fill="white" opacity=".9"/>
                            <path d="M2 17c0-3.314 3.582-6 8-6s8 2.686 8 6" fill="white" opacity=".9"/>
                        </svg>
                    </div>
                </div>
            </div>
        </header>

        <section class="page-content" aria-label="Admin chat threads">
            <aside class="panel">
                <div class="panel__head">
                    <h2 class="panel__title">Student Threads</h2>
                    <p class="panel__sub"><?php echo count($threads); ?> approved students</p>
                </div>
                <div class="thread-list">
                    <?php if (empty($threads)): ?>
                        <div class="empty">No student chats yet.</div>
                    <?php else: ?>
                        <?php foreach ($threads as $thread): ?>
                            <?php $isActive = (string) $thread['student_id'] === $selected_student_id; ?>
                            <a class="thread-item <?php echo $isActive ? 'thread-item--active' : ''; ?>" href="chat.php?student_id=<?php echo urlencode((string) $thread['student_id']); ?>">
                                <div class="thread-item__top">
                                    <div class="thread-item__name"><?php echo h((string) $thread['full_name']); ?></div>
                                    <?php if ((int) $thread['unread_count'] > 0): ?>
                                        <span class="thread-item__badge"><?php echo (int) $thread['unread_count']; ?> new</span>
                                    <?php endif; ?>
                                </div>
                                <div class="thread-item__meta">
                                    <span><?php echo h((string) $thread['student_id']); ?></span>
                                    <span><?php echo h((string) ($thread['work_location'] !== '' ? $thread['work_location'] : 'Assigned Office')); ?></span>
                                </div>
                                <div class="thread-item__meta">
                                    <span>Last activity</span>
                                    <span><?php echo h(chat_format_time((string) ($thread['last_message_at'] ?: ''))); ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </aside>

            <div class="panel">
                <div class="panel__head">
                    <h2 class="panel__title"><?php echo h((string) ($selected_context['full_name'] ?: 'Select a student')); ?></h2>
                    <p class="panel__sub">
                        <?php if ($selected_student_id !== ''): ?>
                            Student ID: <?php echo h($selected_student_id); ?>
                            <?php if (!empty($selected_context['email'])): ?>
                                · <?php echo h((string) $selected_context['email']); ?>
                            <?php endif; ?>
                        <?php else: ?>
                            Choose a student thread to start messaging
                        <?php endif; ?>
                    </p>
                </div>

                <?php if ($chat_notice !== ''): ?>
                    <div class="notice notice--<?php echo h($chat_notice_type); ?>"><?php echo h($chat_notice); ?></div>
                <?php endif; ?>

                <div class="thread" id="threadContainer">
                    <?php if ($selected_student_id === ''): ?>
                        <div class="empty">There is no student selected yet.</div>
                    <?php elseif (empty($messages)): ?>
                        <div class="empty">No messages in this thread yet.</div>
                    <?php else: ?>
                        <?php foreach ($messages as $message): ?>
                            <div class="msg <?php echo $message['sender_role'] === 'admin' ? 'msg--admin' : 'msg--student'; ?>">
                                <div class="msg__meta">
                                    <span><?php echo h((string) $message['sender_name']); ?></span>
                                    <span><?php echo h(chat_format_time((string) $message['created_at'])); ?></span>
                                </div>
                                <div><?php echo nl2br(h((string) $message['message'])); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <?php if ($selected_student_id !== ''): ?>
                    <form class="composer" method="post">
                        <input type="hidden" name="student_id" value="<?php echo h($selected_student_id); ?>">
                        <textarea class="composer__input" name="message" maxlength="1000" placeholder="Type your reply to this student..." required></textarea>
                        <div class="composer__row">
                            <div class="composer__hint">Your reply is stored in the shared conversation log.</div>
                            <button class="btn-primary" type="submit">Send Reply</button>
                        </div>
                    </form>
                <?php endif; ?>
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
