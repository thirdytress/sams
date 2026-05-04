<?php
// documents.php — NU SA System | Admin Panel — Document Center
// National University - Student Development and Activities Office
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <script src="../assets/realtime.js"></script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Center | NU SA System</title>
    <style>
        /* ============================================================
           CSS VARIABLES — Design System
        ============================================================ */
        :root {
            --clr-white:            #FFFFFF;
            --clr-bg:               #F9FAFB;
            --clr-border:           #E5E7EB;
            --clr-border-upload:    #D1D5DC;
            --clr-text-primary:     #101828;
            --clr-text-body:        #364153;
            --clr-text-muted:       #4A5565;
            --clr-text-subtle:      #6A7282;
            --clr-text-dark:        #0A0A0A;

            --clr-blue:             #155DFC;
            --clr-blue-bg:          #DBEAFE;
            --clr-blue-text:        #155DFC;

            --clr-green-bg:         #DCFCE7;
            --clr-green-text:       #008236;

            --clr-yellow-bg:        #FEF9C2;
            --clr-yellow-text:      #A65F00;

            --clr-alert:            #FB2C36;

            --grad-brand:           linear-gradient(135deg, #155DFC 0%, #9810FA 100%);

            --shadow-sm:    0 1px 3px rgba(0,0,0,.10), 0 1px 2px rgba(0,0,0,.10);

            --sidebar-width: 256px;

            --fs-xs:   12px;
            --fs-sm:   14px;
            --fs-base: 16px;
            --fs-md:   18px;
            --fs-lg:   24px;

            --sp-4:    4px;
            --sp-8:    8px;
            --sp-12:   12px;
            --sp-16:   16px;
            --sp-24:   24px;
            --sp-32:   32px;

            --radius-xs:   4px;
            --radius-sm:   10px;
            --radius-md:   16.4px;
            --radius-pill: 9999px;
        }

        /* ============================================================
           RESET & BASE
        ============================================================ */
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
        img { display: block; max-width: 100%; }
        ul { list-style: none; }

        /* ============================================================
           APP LAYOUT
        ============================================================ */
        .app { display: flex; width: 100%; min-height: 100vh; }

        /* ============================================================
           SIDEBAR
        ============================================================ */
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

        .sidebar__brand {
            display: flex;
            align-items: center;
            gap: var(--sp-12);
            height: 40px;
        }

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

        .sidebar__logo-text {
            font-size: var(--fs-md);
            font-weight: bold;
            color: var(--clr-white);
            line-height: 28px;
        }

        .sidebar__brand-info { display: flex; flex-direction: column; }

        .sidebar__app-name {
            font-size: var(--fs-base);
            font-weight: bold;
            color: var(--clr-text-primary);
            line-height: 24px;
        }

        .sidebar__app-sub {
            font-size: var(--fs-xs);
            color: var(--clr-text-muted);
            line-height: 16px;
        }

        .sidebar__nav {
            flex: 1;
            padding: var(--sp-16) var(--sp-16) 0;
        }

        .nav__list { display: flex; flex-direction: column; gap: var(--sp-4); }
        .nav__item { display: block; }

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

        .nav__icon {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
        }
        .nav__icon svg { width: 100%; height: 100%; }

        .nav__label {
            font-size: var(--fs-base);
            color: var(--clr-text-body);
            line-height: 24px;
            flex: 1;
            white-space: nowrap;
        }

        .nav__badge {
            background: var(--clr-blue-bg);
            color: var(--clr-blue-text);
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

        /* ============================================================
           HAMBURGER
        ============================================================ */
        .hamburger {
            display: none;
            flex-direction: column;
            gap: 5px;
            width: 36px;
            height: 36px;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-sm);
            transition: background 0.15s;
            flex-shrink: 0;
        }
        .hamburger:hover { background: var(--clr-bg); }
        .hamburger__bar {
            width: 20px;
            height: 2px;
            background: var(--clr-text-muted);
            border-radius: 2px;
            transition: transform 0.25s, opacity 0.25s;
        }
        .hamburger[aria-expanded="true"] .hamburger__bar:nth-child(1) { transform: translateY(7px) rotate(45deg); }
        .hamburger[aria-expanded="true"] .hamburger__bar:nth-child(2) { opacity: 0; }
        .hamburger[aria-expanded="true"] .hamburger__bar:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

        .sidebar-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.4);
            z-index: 99;
        }
        .sidebar-overlay--hidden  { display: none; }
        .sidebar-overlay--visible { display: block; }

        /* ============================================================
           MAIN
        ============================================================ */
        .main { flex: 1; min-width: 0; display: flex; flex-direction: column; }

        /* ============================================================
           TOP BAR
        ============================================================ */
        .topbar {
            background: var(--clr-white);
            border-bottom: 1px solid var(--clr-border);
            box-shadow: var(--shadow-sm);
            height: 89px;
            padding: 0 var(--sp-32);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }

        .topbar__left { display: flex; align-items: center; gap: var(--sp-12); }

        .topbar__heading { display: flex; flex-direction: column; gap: var(--sp-4); }

        .topbar__title {
            font-size: var(--fs-lg);
            font-weight: bold;
            color: var(--clr-text-primary);
            line-height: 32px;
        }

        .topbar__subtitle {
            font-size: var(--fs-sm);
            color: var(--clr-text-muted);
            line-height: 20px;
        }

        .topbar__right { display: flex; align-items: center; gap: var(--sp-12); }

        .topbar__notif {
            position: relative;
            width: 36px;
            height: 36px;
            border-radius: var(--radius-pill);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.15s;
        }
        .topbar__notif:hover { background: var(--clr-bg); }
        .topbar__notif svg { width: 20px; height: 20px; }
        .topbar__notif-dot {
            position: absolute;
            top: 4px;
            right: 4px;
            width: 8px;
            height: 8px;
            background: var(--clr-alert);
            border-radius: 50%;
        }

        .topbar__user { display: flex; align-items: center; gap: var(--sp-12); }
        .topbar__user-info { text-align: right; display: flex; flex-direction: column; }
        .topbar__user-name { font-size: var(--fs-sm); color: var(--clr-text-primary); line-height: 20px; }
        .topbar__user-role { font-size: var(--fs-xs); color: var(--clr-text-muted); line-height: 16px; }

        .topbar__avatar {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-pill);
            background: var(--grad-brand);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .topbar__avatar svg { width: 20px; height: 20px; }

        /* ============================================================
           PAGE CONTENT
        ============================================================ */
        .page-content {
            flex: 1;
            padding: var(--sp-32);
            display: flex;
            flex-direction: column;
            gap: var(--sp-24);
        }

        /* ============================================================
           DOCUMENT TAB ROW
        ============================================================ */
        .doc-tabs {
            display: flex;
            align-items: center;
            gap: var(--sp-16);
            height: 48px;
            flex-wrap: wrap;
        }

        .doc-tab {
            height: 48px;
            padding: 0 var(--sp-16);
            border-radius: var(--radius-sm);
            font-size: var(--fs-base);
            line-height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
            transition: background 0.15s, color 0.15s;
        }

        .doc-tab--active {
            background: var(--clr-blue);
            color: var(--clr-white);
        }

        .doc-tab--inactive {
            background: #F3F4F6;
            color: var(--clr-text-body);
        }
        .doc-tab--inactive:hover { background: #E5E7EB; }

        /* ============================================================
           UPLOAD SECTION CARD
        ============================================================ */
        .upload-card {
            background: var(--clr-white);
            border: 1px solid var(--clr-border);
            border-radius: var(--radius-md);
            padding: 25px;
            display: flex;
            flex-direction: column;
            gap: var(--sp-16);
        }

        .upload-card__title {
            font-size: var(--fs-md);
            font-weight: bold;
            color: var(--clr-text-primary);
            line-height: 28px;
        }

        .upload-dropzone {
            border: 2px solid var(--clr-border-upload);
            border-radius: var(--radius-md);
            height: 176px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: var(--sp-8);
            cursor: pointer;
            transition: border-color 0.15s, background 0.15s;
        }
        .upload-dropzone:hover { border-color: var(--clr-blue); background: #F5F8FF; }

        .upload-dropzone__icon { width: 48px; height: 48px; }
        .upload-dropzone__icon svg { width: 100%; height: 100%; }

        .upload-dropzone__text {
            font-size: var(--fs-base);
            color: var(--clr-text-body);
            line-height: 24px;
            text-align: center;
        }

        .upload-dropzone__hint {
            font-size: var(--fs-sm);
            color: var(--clr-text-subtle);
            line-height: 20px;
            text-align: center;
        }

        /* Hidden file input */
        .upload-dropzone__input { display: none; }

        /* ============================================================
           DOCUMENTS TABLE CARD
        ============================================================ */
        .table-card {
            background: var(--clr-white);
            border: 1px solid var(--clr-border);
            border-radius: var(--radius-md);
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,.06);
            transition: box-shadow 0.2s ease;
        }
        .table-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,.10);
        }

        .table-card__header {
            border-bottom: 1px solid var(--clr-border);
            padding: 24px;
            min-height: auto;
            display: flex;
            align-items: center;
            background: linear-gradient(135deg, #f9fafb 0%, #ffffff 100%);
        }

        .table-card__title {
            font-size: var(--fs-md);
            font-weight: bold;
            color: var(--clr-text-primary);
            line-height: 28px;
        }

        /* Table */
        .doc-table-wrap { overflow-x: auto; }

        .doc-table {
            width: 100%;
            min-width: 860px;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .doc-table col.col-name     { width: 22%; }
        .doc-table col.col-id       { width: 15%; }
        .doc-table col.col-date     { width: 15%; }
        .doc-table col.col-signed   { width: 20%; }
        .doc-table col.col-status   { width: 15%; }
        .doc-table col.col-actions  { width: 13%; }

        .doc-table thead th {
            background: var(--clr-bg);
            border-bottom: 1px solid var(--clr-border);
            padding: var(--sp-16) var(--sp-24);
            text-align: left;
            font-size: var(--fs-sm);
            font-weight: bold;
            color: var(--clr-text-primary);
            height: 52.5px;
        }

        .doc-table tbody tr { 
            border-bottom: 1px solid var(--clr-border); 
            transition: background 0.15s ease;
        }
        .doc-table tbody tr:hover { background: #f9fafb; }
        .doc-table tbody tr:last-child { border-bottom: none; }

        .doc-table tbody td {
            padding: 16px var(--sp-24);
            height: auto;
            min-height: 70px;
            vertical-align: middle;
            font-size: var(--fs-base);
            color: var(--clr-text-primary);
            line-height: 24px;
        }

        /* Name cell with doc icon */
        .doc-name {
            display: flex;
            align-items: center;
            gap: var(--sp-12);
        }

        .doc-name__icon { width: 20px; height: 20px; flex-shrink: 0; }
        .doc-name__icon svg { width: 100%; height: 100%; }

        /* Status badges */
        .doc-status {
            display: inline-flex;
            align-items: center;
            height: 24px;
            padding: 4px 12px 4px 12px;
            border-radius: var(--radius-pill);
            font-size: var(--fs-xs);
            gap: var(--sp-4);
            white-space: nowrap;
        }

        .doc-status--signed  { background: var(--clr-green-bg);  color: var(--clr-green-text); }
        .doc-status--pending { background: var(--clr-yellow-bg); color: var(--clr-yellow-text); }

        .doc-status__icon { width: 16px; height: 16px; flex-shrink: 0; }
        .doc-status__icon svg { width: 100%; height: 100%; }

        /* Action buttons cell */
        .doc-actions {
            display: flex;
            align-items: center;
            gap: var(--sp-8);
        }

        .doc-action-btn {
            width: 36px;
            height: 36px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.15s, transform 0.15s;
        }
        .doc-action-btn:hover { 
            background: var(--clr-blue-bg);
            transform: scale(1.05);
        }
        .doc-action-btn svg { width: 18px; height: 18px; }

        /* ============================================================
           RESPONSIVE — TABLET (≤1024px)
        ============================================================ */
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
            .topbar__user-info { display: none; }
        }

        /* ============================================================
           RESPONSIVE — MOBILE (≤768px)
        ============================================================ */
        @media (max-width: 768px) {
            .topbar__title { font-size: 18px; }
            .topbar__subtitle { font-size: var(--fs-xs); }
            .page-content { padding: var(--sp-12); gap: var(--sp-16); }
            .doc-tabs { gap: var(--sp-8); }
            .doc-tab { font-size: var(--fs-sm); padding: 0 var(--sp-12); }
            .upload-card { padding: var(--sp-16); }
            .upload-dropzone { height: 140px; }
        }
    </style>
</head>
<body>

<div class="sidebar-overlay sidebar-overlay--hidden" id="sidebarOverlay"></div>

<div class="app">

    <!-- ================================================================
         SIDEBAR
    ================================================================ -->
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
                    <a href="chat.php" class="nav__link">
                        <span class="nav__icon" aria-hidden="true">
                            <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M4 4h12a2 2 0 012 2v6a2 2 0 01-2 2H9l-4 3v-3H4a2 2 0 01-2-2V6a2 2 0 012-2z" stroke="#364153" stroke-width="1.5" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <span class="nav__label">Messages</span>
                    </a>
                </li>
                <li class="nav__item">
                    <a href="documents.php" class="nav__link nav__link--active" aria-current="page">
                        <span class="nav__icon" aria-hidden="true">
                            <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M5 2h7l4 4v12a1 1 0 01-1 1H5a1 1 0 01-1-1V3a1 1 0 011-1z" stroke="white" stroke-width="1.5"/>
                                <path d="M12 2v4h4" stroke="white" stroke-width="1.5" stroke-linecap="round"/>
                                <path d="M7 10h6M7 13h4" stroke="white" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                        </span>
                        <span class="nav__label">Documents</span>
                    </a>
                </li>
                <li class="nav__item">
                    <a href="evaluation.php" class="nav__link">
                        <span class="nav__icon" aria-hidden="true">
                            <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M4 10h12M4 5h12M4 15h8" stroke="#364153" stroke-width="1.6" stroke-linecap="round"/>
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

    <!-- ================================================================
         MAIN
    ================================================================ -->
    <main class="main">

        <!-- Top Bar -->
        <header class="topbar">
            <div class="topbar__left">
                <button class="hamburger" id="hamburgerBtn" type="button" aria-expanded="false" aria-controls="sidebar" aria-label="Toggle navigation">
                    <span class="hamburger__bar"></span>
                    <span class="hamburger__bar"></span>
                    <span class="hamburger__bar"></span>
                </button>
                <div class="topbar__heading">
                    <h1 class="topbar__title">Document Center</h1>
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
                        <span class="topbar__user-name">Zaira Joy S. Enayo</span>
                        <span class="topbar__user-role">SDAO Head</span>
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

        <!-- Page Content -->
        <section class="page-content" aria-label="Document Center content">

            <?php
            // Load documents from the system database (student_applications)
            require_once __DIR__ . '/../db.php';

            $documents = [];
            $sql = "SELECT id, full_name, student_id, created_at,
                        resume_path, letter_intent_path, letter_consent_parent_path,
                        recommendation_letter_path, photocopy_grades_path, class_schedule_path,
                        good_moral_path, work_schedule
                    FROM student_applications
                    ORDER BY created_at DESC";

            if (isset($mysqli)) {
                $res = $mysqli->query($sql);
                if ($res) {
                    while ($row = $res->fetch_assoc()) {
                        $documents[] = $row;
                    }
                    $res->free();
                }
            }
            ?>

            <!-- Student Filter Section -->
            <div style="background: var(--clr-white); padding: 20px 24px; border-radius: var(--radius-md); border: 1px solid var(--clr-border); margin-bottom: 24px; box-shadow: var(--shadow-sm);">
                <label for="studentFilter" style="display: block; font-weight: 600; color: var(--clr-text-primary); margin-bottom: 10px; font-size: var(--fs-sm);">Filter by Student</label>
                <select id="studentFilter" style="width: 100%; max-width: 500px; padding: 12px 14px; border: 1px solid var(--clr-border); border-radius: var(--radius-sm); font-size: var(--fs-base); color: var(--clr-text-body); background: var(--clr-white); cursor: pointer; transition: border-color 0.2s;">
                    <option value="">-- All Students --</option>
                    <?php foreach ($documents as $doc): ?>
                    <option value="<?= htmlspecialchars($doc['student_id'] ?? '') ?>">
                        <?= htmlspecialchars($doc['full_name'] ?? '') ?> (<?= htmlspecialchars($doc['student_id'] ?? '') ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Document Type Tabs -->
            <div class="doc-tabs" role="tablist" aria-label="Document types">
                <button class="doc-tab doc-tab--active" id="tabResume" type="button" role="tab" aria-selected="true" aria-controls="panelResume">
                    Resume
                </button>
                <button class="doc-tab doc-tab--inactive" id="tabLetterIntent" type="button" role="tab" aria-selected="false" aria-controls="panelLetterIntent">
                    Letter of Intent
                </button>
                <button class="doc-tab doc-tab--inactive" id="tabLetterConsent" type="button" role="tab" aria-selected="false" aria-controls="panelLetterConsent">
                    Letter of Consent (Parent)
                </button>
                <button class="doc-tab doc-tab--inactive" id="tabRecommendation" type="button" role="tab" aria-selected="false" aria-controls="panelRecommendation">
                    Recommendation Letter
                </button>
                <button class="doc-tab doc-tab--inactive" id="tabGrades" type="button" role="tab" aria-selected="false" aria-controls="panelGrades">
                    Grades (Photocopy)
                </button>
                <button class="doc-tab doc-tab--inactive" id="tabClassSchedule" type="button" role="tab" aria-selected="false" aria-controls="panelClassSchedule">
                    Class Schedule
                </button>
                <button class="doc-tab doc-tab--inactive" id="tabGoodMoral" type="button" role="tab" aria-selected="false" aria-controls="panelGoodMoral">
                    Good Moral
                </button>
            </div>

            <!-- Resume Panel -->
            <div id="panelResume" role="tabpanel" aria-labelledby="tabResume">
                <div class="table-card">
                    <div class="table-card__header">
                        <h2 class="table-card__title">Resume Files</h2>
                    </div>
                    <div class="doc-table-wrap">
                        <table class="doc-table">
                            <thead><tr>
                                <th scope="col">Student Name</th>
                                <th scope="col">Student ID</th>
                                <th scope="col">Upload Date</th>
                                <th scope="col">File</th>
                                <th scope="col">Actions</th>
                            </tr></thead>
                            <tbody>
                                <?php foreach ($documents as $doc): if (!empty($doc['resume_path'])): $url = htmlspecialchars('../' . ltrim($doc['resume_path'], '/')); ?>
                                <tr class="doc-row" data-student-id="<?= htmlspecialchars($doc['student_id'] ?? '') ?>">
                                    <td><?= htmlspecialchars($doc['full_name'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($doc['student_id'] ?? '') ?></td>
                                    <td><?= htmlspecialchars(isset($doc['created_at']) ? date('M j, Y', strtotime($doc['created_at'])) : '') ?></td>
                                    <td><a href="<?= $url ?>" target="_blank" rel="noopener" style="color: #155DFC; font-weight: 500;">📄 Resume</a></td>
                                    <td><div class="doc-actions"><a class="doc-action-btn" href="<?= $url ?>" target="_blank" rel="noopener" title="View"><svg viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z" stroke="#4A5565" stroke-width="1.2"/><circle cx="8" cy="8" r="2" stroke="#4A5565" stroke-width="1.2"/></svg></a><a class="doc-action-btn" href="<?= $url ?>" download title="Download"><svg viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8 2v8M8 10L5 7M8 10l3-3" stroke="#155DFC" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/><path d="M2 12v2a1 1 0 001 1h10a1 1 0 001-1v-2" stroke="#155DFC" stroke-width="1.3" stroke-linecap="round"/></svg></a></div></td>
                                </tr>
                                <?php endif; endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Letter of Intent Panel -->
            <div id="panelLetterIntent" role="tabpanel" aria-labelledby="tabLetterIntent" hidden>
                <div class="table-card">
                    <div class="table-card__header">
                        <h2 class="table-card__title">Letter of Intent Files</h2>
                    </div>
                    <div class="doc-table-wrap">
                        <table class="doc-table">
                            <thead><tr>
                                <th scope="col">Student Name</th>
                                <th scope="col">Student ID</th>
                                <th scope="col">Upload Date</th>
                                <th scope="col">File</th>
                                <th scope="col">Actions</th>
                            </tr></thead>
                            <tbody>
                                <?php foreach ($documents as $doc): if (!empty($doc['letter_intent_path'])): $url = htmlspecialchars('../' . ltrim($doc['letter_intent_path'], '/')); ?>
                                <tr class="doc-row" data-student-id="<?= htmlspecialchars($doc['student_id'] ?? '') ?>">
                                    <td><?= htmlspecialchars($doc['full_name'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($doc['student_id'] ?? '') ?></td>
                                    <td><?= htmlspecialchars(isset($doc['created_at']) ? date('M j, Y', strtotime($doc['created_at'])) : '') ?></td>
                                    <td><a href="<?= $url ?>" target="_blank" rel="noopener" style="color: #155DFC; font-weight: 500;">📄 Letter of Intent</a></td>
                                    <td><div class="doc-actions"><a class="doc-action-btn" href="<?= $url ?>" target="_blank" rel="noopener" title="View"><svg viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z" stroke="#4A5565" stroke-width="1.2"/><circle cx="8" cy="8" r="2" stroke="#4A5565" stroke-width="1.2"/></svg></a><a class="doc-action-btn" href="<?= $url ?>" download title="Download"><svg viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8 2v8M8 10L5 7M8 10l3-3" stroke="#155DFC" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/><path d="M2 12v2a1 1 0 001 1h10a1 1 0 001-1v-2" stroke="#155DFC" stroke-width="1.3" stroke-linecap="round"/></svg></a></div></td>
                                </tr>
                                <?php endif; endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Letter of Consent Panel -->
            <div id="panelLetterConsent" role="tabpanel" aria-labelledby="tabLetterConsent" hidden>
                <div class="table-card">
                    <div class="table-card__header">
                        <h2 class="table-card__title">Letter of Consent (Parent) Files</h2>
                    </div>
                    <div class="doc-table-wrap">
                        <table class="doc-table">
                            <thead><tr>
                                <th scope="col">Student Name</th>
                                <th scope="col">Student ID</th>
                                <th scope="col">Upload Date</th>
                                <th scope="col">File</th>
                                <th scope="col">Actions</th>
                            </tr></thead>
                            <tbody>
                                <?php foreach ($documents as $doc): if (!empty($doc['letter_consent_parent_path'])): $url = htmlspecialchars('../' . ltrim($doc['letter_consent_parent_path'], '/')); ?>
                                <tr class="doc-row" data-student-id="<?= htmlspecialchars($doc['student_id'] ?? '') ?>">
                                    <td><?= htmlspecialchars($doc['full_name'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($doc['student_id'] ?? '') ?></td>
                                    <td><?= htmlspecialchars(isset($doc['created_at']) ? date('M j, Y', strtotime($doc['created_at'])) : '') ?></td>
                                    <td><a href="<?= $url ?>" target="_blank" rel="noopener" style="color: #155DFC; font-weight: 500;">📄 Letter of Consent</a></td>
                                    <td><div class="doc-actions"><a class="doc-action-btn" href="<?= $url ?>" target="_blank" rel="noopener" title="View"><svg viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z" stroke="#4A5565" stroke-width="1.2"/><circle cx="8" cy="8" r="2" stroke="#4A5565" stroke-width="1.2"/></svg></a><a class="doc-action-btn" href="<?= $url ?>" download title="Download"><svg viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8 2v8M8 10L5 7M8 10l3-3" stroke="#155DFC" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/><path d="M2 12v2a1 1 0 001 1h10a1 1 0 001-1v-2" stroke="#155DFC" stroke-width="1.3" stroke-linecap="round"/></svg></a></div></td>
                                </tr>
                                <?php endif; endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Recommendation Letter Panel -->
            <div id="panelRecommendation" role="tabpanel" aria-labelledby="tabRecommendation" hidden>
                <div class="table-card">
                    <div class="table-card__header">
                        <h2 class="table-card__title">Recommendation Letter Files</h2>
                    </div>
                    <div class="doc-table-wrap">
                        <table class="doc-table">
                            <thead><tr>
                                <th scope="col">Student Name</th>
                                <th scope="col">Student ID</th>
                                <th scope="col">Upload Date</th>
                                <th scope="col">File</th>
                                <th scope="col">Actions</th>
                            </tr></thead>
                            <tbody>
                                <?php foreach ($documents as $doc): if (!empty($doc['recommendation_letter_path'])): $url = htmlspecialchars('../' . ltrim($doc['recommendation_letter_path'], '/')); ?>
                                <tr class="doc-row" data-student-id="<?= htmlspecialchars($doc['student_id'] ?? '') ?>">
                                    <td><?= htmlspecialchars($doc['full_name'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($doc['student_id'] ?? '') ?></td>
                                    <td><?= htmlspecialchars(isset($doc['created_at']) ? date('M j, Y', strtotime($doc['created_at'])) : '') ?></td>
                                    <td><a href="<?= $url ?>" target="_blank" rel="noopener" style="color: #155DFC; font-weight: 500;">📄 Recommendation Letter</a></td>
                                    <td><div class="doc-actions"><a class="doc-action-btn" href="<?= $url ?>" target="_blank" rel="noopener" title="View"><svg viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z" stroke="#4A5565" stroke-width="1.2"/><circle cx="8" cy="8" r="2" stroke="#4A5565" stroke-width="1.2"/></svg></a><a class="doc-action-btn" href="<?= $url ?>" download title="Download"><svg viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8 2v8M8 10L5 7M8 10l3-3" stroke="#155DFC" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/><path d="M2 12v2a1 1 0 001 1h10a1 1 0 001-1v-2" stroke="#155DFC" stroke-width="1.3" stroke-linecap="round"/></svg></a></div></td>
                                </tr>
                                <?php endif; endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Grades Panel -->
            <div id="panelGrades" role="tabpanel" aria-labelledby="tabGrades" hidden>
                <div class="table-card">
                    <div class="table-card__header">
                        <h2 class="table-card__title">Grades (Photocopy) Files</h2>
                    </div>
                    <div class="doc-table-wrap">
                        <table class="doc-table">
                            <thead><tr>
                                <th scope="col">Student Name</th>
                                <th scope="col">Student ID</th>
                                <th scope="col">Upload Date</th>
                                <th scope="col">File</th>
                                <th scope="col">Actions</th>
                            </tr></thead>
                            <tbody>
                                <?php foreach ($documents as $doc): if (!empty($doc['photocopy_grades_path'])): $url = htmlspecialchars('../' . ltrim($doc['photocopy_grades_path'], '/')); ?>
                                <tr class="doc-row" data-student-id="<?= htmlspecialchars($doc['student_id'] ?? '') ?>">
                                    <td><?= htmlspecialchars($doc['full_name'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($doc['student_id'] ?? '') ?></td>
                                    <td><?= htmlspecialchars(isset($doc['created_at']) ? date('M j, Y', strtotime($doc['created_at'])) : '') ?></td>
                                    <td><a href="<?= $url ?>" target="_blank" rel="noopener" style="color: #155DFC; font-weight: 500;">📄 Grades</a></td>
                                    <td><div class="doc-actions"><a class="doc-action-btn" href="<?= $url ?>" target="_blank" rel="noopener" title="View"><svg viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z" stroke="#4A5565" stroke-width="1.2"/><circle cx="8" cy="8" r="2" stroke="#4A5565" stroke-width="1.2"/></svg></a><a class="doc-action-btn" href="<?= $url ?>" download title="Download"><svg viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8 2v8M8 10L5 7M8 10l3-3" stroke="#155DFC" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/><path d="M2 12v2a1 1 0 001 1h10a1 1 0 001-1v-2" stroke="#155DFC" stroke-width="1.3" stroke-linecap="round"/></svg></a></div></td>
                                </tr>
                                <?php endif; endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Class Schedule Panel -->
            <div id="panelClassSchedule" role="tabpanel" aria-labelledby="tabClassSchedule" hidden>
                <div class="table-card">
                    <div class="table-card__header">
                        <h2 class="table-card__title">Class Schedule Files</h2>
                    </div>
                    <div class="doc-table-wrap">
                        <table class="doc-table">
                            <thead><tr>
                                <th scope="col">Student Name</th>
                                <th scope="col">Student ID</th>
                                <th scope="col">Upload Date</th>
                                <th scope="col">File</th>
                                <th scope="col">Actions</th>
                            </tr></thead>
                            <tbody>
                                <?php foreach ($documents as $doc): if (!empty($doc['class_schedule_path'])): $url = htmlspecialchars('../' . ltrim($doc['class_schedule_path'], '/')); ?>
                                <tr class="doc-row" data-student-id="<?= htmlspecialchars($doc['student_id'] ?? '') ?>">
                                    <td><?= htmlspecialchars($doc['full_name'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($doc['student_id'] ?? '') ?></td>
                                    <td><?= htmlspecialchars(isset($doc['created_at']) ? date('M j, Y', strtotime($doc['created_at'])) : '') ?></td>
                                    <td><a href="<?= $url ?>" target="_blank" rel="noopener" style="color: #155DFC; font-weight: 500;">📄 Class Schedule</a></td>
                                    <td><div class="doc-actions"><a class="doc-action-btn" href="<?= $url ?>" target="_blank" rel="noopener" title="View"><svg viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z" stroke="#4A5565" stroke-width="1.2"/><circle cx="8" cy="8" r="2" stroke="#4A5565" stroke-width="1.2"/></svg></a><a class="doc-action-btn" href="<?= $url ?>" download title="Download"><svg viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8 2v8M8 10L5 7M8 10l3-3" stroke="#155DFC" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/><path d="M2 12v2a1 1 0 001 1h10a1 1 0 001-1v-2" stroke="#155DFC" stroke-width="1.3" stroke-linecap="round"/></svg></a></div></td>
                                </tr>
                                <?php endif; endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Good Moral Panel -->
            <div id="panelGoodMoral" role="tabpanel" aria-labelledby="tabGoodMoral" hidden>
                <div class="table-card">
                    <div class="table-card__header">
                        <h2 class="table-card__title">Good Moral Files</h2>
                    </div>
                    <div class="doc-table-wrap">
                        <table class="doc-table">
                            <thead><tr>
                                <th scope="col">Student Name</th>
                                <th scope="col">Student ID</th>
                                <th scope="col">Upload Date</th>
                                <th scope="col">File</th>
                                <th scope="col">Actions</th>
                            </tr></thead>
                            <tbody>
                                <?php foreach ($documents as $doc): if (!empty($doc['good_moral_path'])): $url = htmlspecialchars('../' . ltrim($doc['good_moral_path'], '/')); ?>
                                <tr class="doc-row" data-student-id="<?= htmlspecialchars($doc['student_id'] ?? '') ?>">
                                    <td><?= htmlspecialchars($doc['full_name'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($doc['student_id'] ?? '') ?></td>
                                    <td><?= htmlspecialchars(isset($doc['created_at']) ? date('M j, Y', strtotime($doc['created_at'])) : '') ?></td>
                                    <td><a href="<?= $url ?>" target="_blank" rel="noopener" style="color: #155DFC; font-weight: 500;">📄 Good Moral</a></td>
                                    <td><div class="doc-actions"><a class="doc-action-btn" href="<?= $url ?>" target="_blank" rel="noopener" title="View"><svg viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z" stroke="#4A5565" stroke-width="1.2"/><circle cx="8" cy="8" r="2" stroke="#4A5565" stroke-width="1.2"/></svg></a><a class="doc-action-btn" href="<?= $url ?>" download title="Download"><svg viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8 2v8M8 10L5 7M8 10l3-3" stroke="#155DFC" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/><path d="M2 12v2a1 1 0 001 1h10a1 1 0 001-1v-2" stroke="#155DFC" stroke-width="1.3" stroke-linecap="round"/></svg></a></div></td>
                                </tr>
                                <?php endif; endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </section>
    </main>
</div><!-- /app -->

<script>
(function () {
    'use strict';

    /* ---- Hamburger / Sidebar ---- */
    var hamburger = document.getElementById('hamburgerBtn');
    var sidebar   = document.getElementById('sidebar');
    var overlay   = document.getElementById('sidebarOverlay');

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

    if (hamburger) hamburger.addEventListener('click', function () {
        sidebar.classList.contains('sidebar--open') ? closeSidebar() : openSidebar();
    });
    if (overlay) overlay.addEventListener('click', closeSidebar);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && sidebar.classList.contains('sidebar--open')) {
            closeSidebar();
            hamburger && hamburger.focus();
        }
    });

    /* ---- Document Type Tabs ---- */
    var tabs = [
        { btn: document.getElementById('tabResume'), panel: document.getElementById('panelResume') },
        { btn: document.getElementById('tabLetterIntent'), panel: document.getElementById('panelLetterIntent') },
        { btn: document.getElementById('tabLetterConsent'), panel: document.getElementById('panelLetterConsent') },
        { btn: document.getElementById('tabRecommendation'), panel: document.getElementById('panelRecommendation') },
        { btn: document.getElementById('tabGrades'), panel: document.getElementById('panelGrades') },
        { btn: document.getElementById('tabClassSchedule'), panel: document.getElementById('panelClassSchedule') },
        { btn: document.getElementById('tabGoodMoral'), panel: document.getElementById('panelGoodMoral') }
    ];

    tabs.forEach(function (t) {
        if (!t.btn) return;
        t.btn.addEventListener('click', function () {
            tabs.forEach(function (item) {
                item.btn.classList.remove('doc-tab--active');
                item.btn.classList.add('doc-tab--inactive');
                item.btn.setAttribute('aria-selected', 'false');
                if (item.panel) item.panel.hidden = true;
            });
            t.btn.classList.remove('doc-tab--inactive');
            t.btn.classList.add('doc-tab--active');
            t.btn.setAttribute('aria-selected', 'true');
            if (t.panel) t.panel.hidden = false;
        });
    });

    /* ---- Student Filter ---- */
    var studentFilter = document.getElementById('studentFilter');
    if (studentFilter) {
        studentFilter.addEventListener('change', function () {
            var selectedStudentId = this.value.trim();
            var rows = document.querySelectorAll('.doc-row');
            rows.forEach(function (row) {
                if (selectedStudentId === '') {
                    row.style.display = '';
                } else {
                    var rowStudentId = row.getAttribute('data-student-id');
                    row.style.display = (rowStudentId === selectedStudentId) ? '' : 'none';
                }
            });
        });
    }

    /* ---- Upload drag-and-drop visual feedback ---- */
    document.querySelectorAll('.upload-dropzone').forEach(function (zone) {
        zone.addEventListener('dragover', function (e) {
            e.preventDefault();
            zone.style.borderColor = '#155DFC';
            zone.style.background  = '#F5F8FF';
        });
        zone.addEventListener('dragleave', function () {
            zone.style.borderColor = '';
            zone.style.background  = '';
        });
        zone.addEventListener('drop', function (e) {
            e.preventDefault();
            zone.style.borderColor = '';
            zone.style.background  = '';
            var files = e.dataTransfer.files;
            if (files.length) {
                var input = zone.querySelector('input[type="file"]');
                // In a real implementation, assign DataTransfer files to input
                // For now, just update the text label
                var textEl = zone.querySelector('.upload-dropzone__text');
                if (textEl && files[0]) textEl.textContent = files[0].name;
            }
        });
    });

})();
</script>

</body>
</html>