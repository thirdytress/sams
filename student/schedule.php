<?php
// schedule.php - NU SAMS Student Portal - My Schedule Page
// National University - Student Assistant Management System

session_start();

// Require login: redirect to main login page if not authenticated
if (!isset($_SESSION['student_id']) || !isset($_SESSION['student_name'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../db.php';

$has_status_column = false;
if ($colRes = $mysqli->query("SHOW COLUMNS FROM student_applications LIKE 'application_status'")) {
    $has_status_column = $colRes->num_rows > 0;
    $colRes->free();
}

$student_name = $_SESSION['student_name'];
$student_id   = $_SESSION['student_id'];
$campus       = 'National University - Lipa Campus';

if ($has_status_column) {
    $st = $mysqli->prepare('SELECT application_status FROM student_applications WHERE student_id = ? ORDER BY created_at DESC LIMIT 1');
    if ($st) {
        $st->bind_param('s', $student_id);
        $st->execute();
        $resSt = $st->get_result();
        $appRow = $resSt ? $resSt->fetch_assoc() : null;
        $resSt && $resSt->free();
        $st->close();

        $appStatus = strtolower(trim((string) ($appRow['application_status'] ?? 'pending')));
        if ($appStatus !== 'approved') {
            unset($_SESSION['student_id'], $_SESSION['student_name']);
            header('Location: ../status.php?student_id=' . urlencode((string) $student_id));
            exit;
        }
    }
}

// Load latest saved working schedule & location for this student
$work_schedule_text      = '';
$work_location_display   = '';
$weekly_schedule         = [
    'Monday'    => [],
    'Tuesday'   => [],
    'Wednesday' => [],
    'Thursday'  => [],
    'Friday'    => [],
    'Saturday'  => [],
    'Sunday'    => [],
];
$total_hours_this_week   = 0;
$scheduled_days_count    = 0;

if ($stmt = $mysqli->prepare('SELECT work_schedule, work_location FROM student_applications WHERE student_id = ? ORDER BY created_at DESC LIMIT 1')) {
    $stmt->bind_param('s', $student_id);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $work_schedule_text    = trim($row['work_schedule'] ?? '');
            $work_location_display = trim($row['work_location'] ?? '');
        }
        $res->free();
    }
    $stmt->close();
}

if ($work_schedule_text !== '') {
    $parts = explode('|', $work_schedule_text);
    foreach ($parts as $part) {
        $partTrim = trim($part);
        if ($partTrim === '') {
            continue;
        }

        $daySepPos = strpos($partTrim, ':');
        if ($daySepPos === false) {
            continue;
        }

        $dayName = trim(substr($partTrim, 0, $daySepPos));
        $times   = trim(substr($partTrim, $daySepPos + 1));
        if ($times === '' || !array_key_exists($dayName, $weekly_schedule)) {
            continue;
        }

        $intervals = array_map('trim', explode(',', $times));
        foreach ($intervals as $interval) {
            if ($interval === '') {
                continue;
            }
            $weekly_schedule[$dayName][] = $interval;

            // Compute total hours
            $rangeParts = preg_split('/[–-]/', $interval);
            if (count($rangeParts) === 2) {
                $startStr = trim($rangeParts[0]);
                $endStr   = trim($rangeParts[1]);
                $startTs  = strtotime($startStr);
                $endTs    = strtotime($endStr);
                if ($startTs && $endTs && $endTs > $startTs) {
                    $total_hours_this_week += ($endTs - $startTs) / 3600;
                }
            }
        }
    }

    foreach ($weekly_schedule as $dayName => $intervals) {
        if (!empty($intervals)) {
            $scheduled_days_count++;
        }
    }
}

// Fallback labels
if ($work_location_display === '') {
    $work_location_display = 'Assigned Office';
}

$total_hours_this_week = (int) round($total_hours_this_week, 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <script src="../assets/realtime.js"></script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Schedule | NU SAMS</title>
    <style>
        /* ============================================================
           CSS VARIABLES (Design System)
        ============================================================ */
        :root {
            /* Colors */
            --color-primary:         #003087;
            --color-primary-light:   #0047AB;
            --color-primary-dark:    #002060;
            --color-accent:          #FFB81C;
            --color-white:           #FFFFFF;
            --color-bg-page:         #F9FAFB;
            --color-bg-gradient-start: #EFF6FF;
            --color-bg-gradient-end:   #FFFBEB;
            --color-sidebar-bg-start:  #003087;
            --color-sidebar-bg-end:    #0047AB;
            --color-text-heading:    #101828;
            --color-text-body:       #364153;
            --color-text-muted:      #4A5565;
            --color-text-sidebar:    #BEDBFF;
            --color-border:          #E5E7EB;
            --color-border-light:    #F3F4F6;
            --color-border-card:     #BEDBFF;
            --color-alert:           #FB2C36;
            --color-guideline-bg:    #EEF2FF;
            --color-guideline-text:  #1C398E;

            /* Gradient - Sidebar */
            --gradient-sidebar: linear-gradient(180deg,
                #003087 0%, #00328B 10%, #00358E 20%, #003792 30%,
                #003995 40%, #003B99 50%, #003E9C 60%, #0040A0 70%,
                #0042A4 80%, #0045A7 90%, #0047AB 100%);

            /* Gradient - Button / Header accent */
            --gradient-primary-h: linear-gradient(90deg,
                #003087 0%, #00328B 10%, #00358E 20%, #003792 30%,
                #003995 40%, #003B99 50%, #003E9C 60%, #0040A0 70%,
                #0042A4 80%, #0045A7 90%, #0047AB 100%);

            /* Gradient - Stat card icon (blue) */
            --gradient-icon-blue: linear-gradient(135deg,
                #003087 0%, #00328B 10%, #00358E 20%, #003792 30%,
                #003995 40%, #003B99 50%, #003E9C 60%, #0040A0 70%,
                #0042A4 80%, #0045A7 90%, #0047AB 100%);

            /* Gradient - Stat card icon (gold) */
            --gradient-icon-gold: linear-gradient(135deg,
                #FFB81C 0%, #FFB61A 12.5%, #FFB317 25%, #FFB114 37.5%,
                #FFAF11 50%, #FFAC0D 62.5%, #FFAA09 75%, #FFA704 87.5%,
                #FFA500 100%);

            /* Gradient - Schedule block */
            --gradient-schedule-block: linear-gradient(126deg,
                #003087 0%, #00328B 10%, #00358E 20%, #003792 30%,
                #003995 40%, #003B99 50%, #003E9C 60%, #0040A0 70%,
                #0042A4 80%, #0045A7 90%, #0047AB 100%);

            /* Gradient - Guidelines panel */
            --gradient-guidelines: linear-gradient(168deg, #EFF6FF 0%, #EEF2FF 100%);

            /* Font Sizes */
            --fs-xs:   12px;
            --fs-sm:   14px;
            --fs-base: 16px;
            --fs-md:   18px;
            --fs-lg:   20px;
            --fs-xl:   24px;
            --fs-2xl:  30px;
            --fs-3xl:  36px;

            /* Spacing */
            --sp-4:   4px;
            --sp-8:   8px;
            --sp-12:  12px;
            --sp-16:  16px;
            --sp-24:  24px;
            --sp-32:  32px;

            /* Border Radius */
            --radius-sm:  10px;
            --radius-md:  14px;
            --radius-lg:  16px;

            /* Shadows */
            --shadow-sm:  0 1px 3px rgba(0,0,0,.10), 0 1px 2px rgba(0,0,0,.10);
            --shadow-md:  0 10px 15px rgba(0,0,0,.10), 0 4px 6px rgba(0,0,0,.10);
            --shadow-lg:  0 10px 15px -3px rgba(0,0,0,.10), 0 4px 6px -4px rgba(0,0,0,.10);
            --shadow-xl:  0 25px 50px rgba(0,0,0,.25);

            /* Sidebar width */
            --sidebar-width: 288px;

            /* Schedule grid */
            --time-col-width: 150px;
            --day-col-width:  150px;
            --hour-row-height: 64px;
        }

        /* ============================================================
           RESET & BASE
        ============================================================ */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html { font-size: 16px; }

        body {
            font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(132.75deg, var(--color-bg-gradient-start) 0%, var(--color-white) 50%, var(--color-bg-gradient-end) 100%);
            min-height: 100vh;
            display: flex;
            align-items: flex-start;
            color: var(--color-text-heading);
            overflow-x: hidden;
        }

        a { text-decoration: none; color: inherit; }
        button { cursor: pointer; border: none; background: none; font-family: inherit; }
        ul, ol { list-style: none; }
        img { display: block; }

        /* ============================================================
           LAYOUT WRAPPER
        ============================================================ */
        .app {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }

        /* ============================================================
           SIDEBAR
        ============================================================ */
        .sidebar {
            width: var(--sidebar-width);
            min-height: 100vh;
            background: var(--gradient-sidebar);
            box-shadow: var(--shadow-xl);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            z-index: 100;
        }

        /* Sidebar – Logo Header */
        .sidebar__header {
            padding: var(--sp-24) var(--sp-24) 0;
            border-bottom: 1px solid rgba(255,255,255,.20);
            padding-bottom: var(--sp-16);
        }

        .sidebar__brand {
            display: flex;
            align-items: center;
            gap: var(--sp-12);
            height: 48px;
        }

        .sidebar__logo {
            width: 48px;
            height: 48px;
            background: var(--color-white);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .sidebar__logo-text {
            font-size: var(--fs-xl);
            font-weight: 900;
            color: var(--color-primary);
            line-height: 1;
            font-style: normal;
        }

        .sidebar__brand-info { display: flex; flex-direction: column; gap: 0; }

        .sidebar__app-name {
            font-size: var(--fs-lg);
            font-weight: 900;
            color: var(--color-white);
            line-height: 28px;
        }

        .sidebar__app-sub {
            font-size: var(--fs-xs);
            font-weight: 400;
            color: var(--color-text-sidebar);
            line-height: 16px;
            white-space: nowrap;
        }

        /* Sidebar – Navigation */
        .sidebar__nav {
            flex: 1;
            padding: var(--sp-24) var(--sp-16) 0;
        }

        .nav__list {
            display: flex;
            flex-direction: column;
            gap: var(--sp-8);
        }

        .nav__item { display: block; }

        .nav__link {
            display: flex;
            align-items: center;
            gap: var(--sp-12);
            height: 48px;
            padding-left: var(--sp-16);
            border-radius: var(--radius-md);
            transition: background 0.2s;
        }

        .nav__link:hover { background: rgba(255,255,255,.10); }

        .nav__link--active {
            background: var(--color-white);
            box-shadow: var(--shadow-md);
        }

        .nav__icon {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }

        .nav__icon img { width: 100%; height: 100%; object-fit: contain; }

        .nav__label {
            font-size: var(--fs-base);
            font-weight: 700;
            color: var(--color-white);
            line-height: 24px;
            white-space: nowrap;
        }

        .nav__link--active .nav__label { color: var(--color-primary); }

        /* Sidebar – User / Logout */
        .sidebar__footer {
            border-top: 1px solid rgba(255,255,255,.20);
            padding: 17px var(--sp-16) var(--sp-16);
            display: flex;
            flex-direction: column;
            gap: var(--sp-12);
        }

        .sidebar__user-card {
            background: rgba(255,255,255,.10);
            border-radius: var(--radius-md);
            padding: var(--sp-16) var(--sp-16) var(--sp-8);
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .sidebar__user-label {
            font-size: var(--fs-sm);
            font-weight: 500;
            color: var(--color-text-sidebar);
            line-height: 20px;
        }

        .sidebar__user-name {
            font-size: var(--fs-base);
            font-weight: 900;
            color: var(--color-white);
            line-height: 24px;
            white-space: nowrap;
        }

        .sidebar__user-id {
            font-size: var(--fs-xs);
            font-weight: 400;
            color: var(--color-text-sidebar);
            line-height: 16px;
        }

        .sidebar__logout {
            display: flex;
            align-items: center;
            gap: var(--sp-8);
            height: 48px;
            padding-left: var(--sp-16);
            border-radius: var(--radius-md);
            background: rgba(255,255,255,.10);
            transition: background 0.2s;
            width: 100%;
        }

        .sidebar__logout:hover { background: rgba(255,255,255,.20); }

        .sidebar__logout-icon {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }

        .sidebar__logout-icon img { width: 100%; height: 100%; object-fit: contain; }

        .sidebar__logout-label {
            font-size: var(--fs-base);
            font-weight: 700;
            color: var(--color-white);
            line-height: 24px;
        }

        /* Hamburger (mobile) */
        .hamburger {
            display: none;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            gap: 5px;
            width: 40px;
            height: 40px;
            border-radius: var(--radius-sm);
            background: var(--color-primary);
            flex-shrink: 0;
        }

        .hamburger__bar {
            width: 22px;
            height: 2px;
            background: var(--color-white);
            border-radius: 2px;
            transition: transform 0.3s, opacity 0.3s;
        }

        .hamburger[aria-expanded="true"] .hamburger__bar:nth-child(1) { transform: translateY(7px) rotate(45deg); }
        .hamburger[aria-expanded="true"] .hamburger__bar:nth-child(2) { opacity: 0; }
        .hamburger[aria-expanded="true"] .hamburger__bar:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

        /* Overlay */
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.4);
            z-index: 99;
        }

        /* ============================================================
           MAIN CONTENT
        ============================================================ */
        .main {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
        }

        /* ============================================================
           TOP HEADER BAR
        ============================================================ */
        .topbar {
            background: var(--color-white);
            border-bottom: 1px solid var(--color-border);
            box-shadow: var(--shadow-sm);
            padding: var(--sp-16) var(--sp-32);
            height: 85px;
            display: flex;
            align-items: center;
            flex-shrink: 0;
        }

        .topbar__inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            height: 52px;
        }

        .topbar__left { display: flex; flex-direction: column; gap: 0; }

        .topbar__title {
            font-size: var(--fs-xl);
            font-weight: 900;
            color: var(--color-text-heading);
            line-height: 32px;
        }

        .topbar__subtitle {
            font-size: var(--fs-sm);
            font-weight: 500;
            color: var(--color-text-muted);
            line-height: 20px;
            white-space: nowrap;
        }

        .topbar__actions {
            display: flex;
            align-items: center;
            gap: var(--sp-8);
        }

        .topbar__action-btn {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            transition: background 0.2s;
        }

        .topbar__action-btn:hover { background: var(--color-bg-page); }

        .topbar__action-btn img { width: 24px; height: 24px; object-fit: contain; }

        .topbar__badge {
            position: absolute;
            top: 0;
            right: 0;
            width: 12px;
            height: 12px;
            background: var(--color-alert);
            border: 2px solid var(--color-white);
            border-radius: 50%;
        }

        /* ============================================================
           PAGE CONTENT AREA
        ============================================================ */
        .page-content {
            flex: 1;
            padding: var(--sp-32);
            display: flex;
            flex-direction: column;
            gap: var(--sp-32);
            overflow-x: auto;
        }

        /* ============================================================
           PAGE HEADER (Title + Controls)
        ============================================================ */
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: var(--sp-16);
        }

        .page-header__left { display: flex; flex-direction: column; gap: var(--sp-8); }

        .page-header__title-row {
            display: flex;
            align-items: center;
            gap: var(--sp-12);
        }

        .page-header__icon { width: 40px; height: 40px; flex-shrink: 0; }
        .page-header__icon img { width: 100%; height: 100%; object-fit: contain; }

        .page-header__title {
            font-size: var(--fs-3xl);
            font-weight: 900;
            color: var(--color-text-heading);
            line-height: 40px;
            white-space: nowrap;
        }

        .page-header__subtitle {
            font-size: var(--fs-md);
            font-weight: 500;
            color: var(--color-text-muted);
            line-height: 28px;
        }

        .page-header__controls {
            display: flex;
            align-items: center;
            gap: var(--sp-12);
        }

        /* View toggle (Calendar / List) */
        .view-toggle {
            background: var(--color-white);
            border: 2px solid var(--color-border);
            border-radius: var(--radius-md);
            padding: 6px;
            display: flex;
            gap: var(--sp-4);
            height: 52px;
        }

        .view-toggle__btn {
            height: 40px;
            padding: 0 var(--sp-16);
            border-radius: var(--radius-sm);
            font-size: var(--fs-base);
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: var(--sp-8);
            transition: background 0.2s, color 0.2s;
            color: var(--color-text-muted);
        }

        .view-toggle__btn img { width: 16px; height: 16px; object-fit: contain; }

        .view-toggle__btn--active {
            background: var(--color-primary);
            color: var(--color-white);
        }

        .view-toggle__btn--active img { filter: brightness(0) invert(1); }

        /* Load Demo Data button */
        .btn-demo {
            height: 48px;
            padding: 0 var(--sp-24);
            border-radius: var(--radius-md);
            background: var(--gradient-primary-h);
            font-size: var(--fs-base);
            font-weight: 700;
            color: var(--color-white);
            display: flex;
            align-items: center;
            gap: var(--sp-8);
            white-space: nowrap;
            transition: opacity 0.2s;
        }

        .btn-demo:hover { opacity: .88; }
        .btn-demo img { width: 20px; height: 20px; object-fit: contain; filter: brightness(0) invert(1); }

        /* ============================================================
           STAT CARDS ROW
        ============================================================ */
        .stats-row {
            display: flex;
            gap: var(--sp-24);
            flex-wrap: wrap;
        }

        .stat-card {
            background: var(--color-white);
            border: 2px solid var(--color-border-light);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            padding: 26px var(--sp-24);
            display: flex;
            align-items: center;
            gap: var(--sp-16);
            flex: 1;
            min-width: 260px;
        }

        .stat-card__icon-wrap {
            width: 56px;
            height: 56px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .stat-card__icon-wrap--blue  { background: var(--gradient-icon-blue); }
        .stat-card__icon-wrap--gold  { background: var(--gradient-icon-gold); }

        .stat-card__icon-wrap img { width: 28px; height: 28px; object-fit: contain; filter: brightness(0) invert(1); }

        .stat-card__info { display: flex; flex-direction: column; gap: 0; }

        .stat-card__label {
            font-size: var(--fs-sm);
            font-weight: 700;
            color: var(--color-text-muted);
            line-height: 20px;
        }

        .stat-card__value {
            font-size: var(--fs-2xl);
            font-weight: 900;
            color: var(--color-text-heading);
            line-height: 36px;
        }

        /* Week Navigator (3rd card) */
        .week-nav {
            background: var(--color-white);
            border: 2px solid var(--color-border-light);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            padding: 0 var(--sp-16);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: var(--sp-8);
            flex: 1;
            min-width: 300px;
            height: 108px;
        }

        .week-nav__btn {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: background 0.2s;
        }

        .week-nav__btn:hover { background: var(--color-border-light); }
        .week-nav__btn img { width: 16px; height: 16px; object-fit: contain; }

        .week-nav__info { text-align: center; }

        .week-nav__label {
            font-size: var(--fs-sm);
            font-weight: 700;
            color: var(--color-text-muted);
            line-height: 20px;
        }

        .week-nav__range {
            font-size: var(--fs-md);
            font-weight: 900;
            color: var(--color-text-heading);
            line-height: 28px;
        }

        /* ============================================================
           SCHEDULE CALENDAR GRID
        ============================================================ */
        .schedule-card {
            background: var(--color-white);
            border: 2px solid var(--color-border-light);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
        }

        .schedule-grid {
            width: 100%;
            overflow-x: auto;
        }

        .schedule-table {
            min-width: 1100px;
            border-collapse: collapse;
            width: 100%;
            table-layout: fixed;
        }

        /* Column widths */
        .schedule-table col.col-time { width: var(--time-col-width); }
        .schedule-table col.col-day  { width: var(--day-col-width); }

        /* Header row */
        .schedule-table thead th {
            background: var(--color-bg-page);
            border-bottom: 2px solid var(--color-border);
            border-right: 1px solid var(--color-border);
            height: 58px;
            padding: 0 var(--sp-16);
            text-align: center;
            font-size: var(--fs-base);
            font-weight: 900;
            color: var(--color-text-heading);
        }

        .schedule-table thead th:first-child {
            text-align: left;
            font-size: var(--fs-sm);
            font-weight: 900;
            color: var(--color-text-muted);
            letter-spacing: .05em;
        }

        .schedule-table thead th:last-child { border-right: none; }

        /* Body cells */
        .schedule-table tbody td {
            border-bottom: 1px solid var(--color-border);
            border-right: 1px solid var(--color-border);
            height: var(--hour-row-height);
            vertical-align: top;
            padding: 0;
            position: relative;
        }

        .schedule-table tbody td:last-child { border-right: none; }

        .schedule-table tbody td.td-time {
            padding: 6px 8px;
            font-size: var(--fs-xs);
            font-weight: 700;
            color: var(--color-text-muted);
            white-space: nowrap;
        }

        /* Schedule block inside a cell */
        .schedule-block {
            position: absolute;
            left: 4px;
            right: 4px;
            background: var(--gradient-schedule-block);
            border: 2px solid var(--color-accent);
            border-radius: var(--radius-sm);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            padding: 10px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            z-index: 2;
        }

        .schedule-block__meta { display: flex; flex-direction: column; gap: var(--sp-4); }

        .schedule-block__time-row,
        .schedule-block__loc-row {
            display: flex;
            align-items: center;
            gap: var(--sp-4);
        }

        .schedule-block__icon {
            width: 12px;
            height: 12px;
            flex-shrink: 0;
        }

        .schedule-block__icon img { width: 100%; height: 100%; object-fit: contain; filter: brightness(0) invert(1); }

        .schedule-block__time {
            font-size: var(--fs-xs);
            font-weight: 900;
            color: var(--color-white);
            white-space: nowrap;
            line-height: 15px;
        }

        .schedule-block__loc {
            font-size: var(--fs-xs);
            font-weight: 700;
            color: var(--color-white);
            white-space: nowrap;
            line-height: 15px;
        }

        .schedule-block__badge {
            font-size: var(--fs-xs);
            color: var(--color-accent);
            line-height: 16px;
        }

        /* Block heights: 1 hour = 64px; each hour = 1 row height */
        /* 3-hour block = 192px */
        .schedule-block--3h { height: 192px; }

        /* ============================================================
           SCHEDULE GUIDELINES
        ============================================================ */
        .guidelines {
            background: var(--gradient-guidelines);
            border: 2px solid var(--color-border-card);
            border-radius: var(--radius-lg);
            padding: 34px;
            display: flex;
            flex-direction: column;
            gap: var(--sp-24);
        }

        .guidelines__title-row {
            display: flex;
            align-items: center;
            gap: var(--sp-8);
        }

        .guidelines__icon { width: 28px; height: 28px; flex-shrink: 0; }
        .guidelines__icon img { width: 100%; height: 100%; object-fit: contain; }

        .guidelines__title {
            font-size: var(--fs-xl);
            font-weight: 900;
            color: var(--color-guideline-text);
            line-height: 32px;
        }

        .guidelines__grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px 24px;
        }

        .guidelines__item {
            display: flex;
            align-items: flex-start;
            gap: var(--sp-12);
        }

        .guidelines__emoji {
            font-size: 30px;
            line-height: 36px;
            flex-shrink: 0;
            width: 42px;
        }

        .guidelines__text { display: flex; flex-direction: column; gap: var(--sp-4); }

        .guidelines__item-title {
            font-size: var(--fs-base);
            font-weight: 900;
            color: var(--color-text-heading);
            line-height: 24px;
        }

        .guidelines__item-desc {
            font-size: var(--fs-base);
            font-weight: 500;
            color: var(--color-text-body);
            line-height: 24px;
        }

        /* ============================================================
           RESPONSIVE – TABLET (≤1024px)
        ============================================================ */
        @media (max-width: 1024px) {
            .sidebar {
                position: fixed;
                left: -100%;
                top: 0;
                height: 100vh;
                transition: left 0.3s ease;
                z-index: 200;
            }

            .sidebar--open { left: 0; }

            .sidebar-overlay { display: block; }
            .sidebar-overlay--visible { display: block; }
            .sidebar-overlay--hidden  { display: none; }

            .hamburger { display: flex; }

            .topbar { padding: var(--sp-16) var(--sp-16); }

            .page-content { padding: var(--sp-16); }

            .page-header { flex-direction: column; align-items: flex-start; }

            .stats-row { gap: var(--sp-16); }

            .stat-card { min-width: 0; flex: 1 1 calc(50% - 8px); }

            .week-nav { min-width: 0; flex: 1 1 100%; }

            .guidelines__grid { grid-template-columns: 1fr; }

            .page-header__title { font-size: 28px; }
        }

        /* ============================================================
           RESPONSIVE – MOBILE (≤768px)
        ============================================================ */
        @media (max-width: 768px) {
            .topbar { height: auto; min-height: 70px; }

            .topbar__title { font-size: 18px; }
            .topbar__subtitle { font-size: 12px; }

            .page-content { padding: var(--sp-12); gap: var(--sp-16); }

            .page-header__title { font-size: var(--fs-xl); }
            .page-header__subtitle { font-size: var(--fs-sm); }

            .page-header__controls { flex-wrap: wrap; gap: var(--sp-8); }

            .stats-row { flex-direction: column; gap: var(--sp-12); }
            .stat-card { min-width: 0; flex: none; width: 100%; }
            .week-nav { min-width: 0; width: 100%; height: auto; padding: var(--sp-16); }

            .btn-demo { font-size: var(--fs-sm); padding: 0 var(--sp-16); height: 44px; }

            .guidelines { padding: var(--sp-16); gap: var(--sp-16); }
            .guidelines__title { font-size: 18px; }
            .guidelines__item-title,
            .guidelines__item-desc { font-size: var(--fs-sm); }
        }
    </style>
</head>
<body>

<div class="app">

    <!-- ================================================================
         SIDEBAR OVERLAY (mobile)
    ================================================================ -->
    <div class="sidebar-overlay sidebar-overlay--hidden" id="sidebarOverlay" aria-hidden="true"></div>

    <!-- ================================================================
         SIDEBAR
    ================================================================ -->
    <aside class="sidebar" id="sidebar" role="navigation" aria-label="Main navigation">

        <!-- Logo / Brand -->
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

        <!-- Navigation Links -->
        <nav class="sidebar__nav">
            <ul class="nav__list">
                <li class="nav__item">
                    <a href="dashboard.php" class="nav__link">
                        <span class="nav__icon" aria-hidden="true">
                            <!-- Dashboard icon (grid) -->
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
                    <a href="schedule.php" class="nav__link nav__link--active" aria-current="page">
                        <span class="nav__icon" aria-hidden="true">
                            <!-- Calendar icon -->
                            <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="2" y="4" width="16" height="14" rx="2" stroke="#003087" stroke-width="1.8"/>
                                <path d="M6 2v4M14 2v4" stroke="#003087" stroke-width="1.8" stroke-linecap="round"/>
                                <path d="M2 8h16" stroke="#003087" stroke-width="1.5"/>
                            </svg>
                        </span>
                        <span class="nav__label">My Schedule</span>
                    </a>
                </li>
                <li class="nav__item">
                    <a href="attendance.php" class="nav__link">
                        <span class="nav__icon" aria-hidden="true">
                            <!-- Check circle icon -->
                            <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="10" cy="10" r="8" stroke="rgba(255,255,255,0.7)" stroke-width="1.8"/>
                                <path d="M6.5 10.5l2.5 2.5 4.5-5" stroke="rgba(255,255,255,0.7)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <span class="nav__label">Attendance</span>
                    </a>
                </li>
                <li class="nav__item">
                    <a href="chat.php" class="nav__link">
                        <span class="nav__icon" aria-hidden="true">
                            <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M4 4h12a2 2 0 012 2v6a2 2 0 01-2 2H9l-4 3v-3H4a2 2 0 01-2-2V6a2 2 0 012-2z" stroke="rgba(255,255,255,0.7)" stroke-width="1.5" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <span class="nav__label">Messages</span>
                    </a>
                </li>
                <li class="nav__item">
                    <a href="profile.php" class="nav__link">
                        <span class="nav__icon" aria-hidden="true">
                            <!-- User icon -->
                            <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="10" cy="7" r="4" stroke="rgba(255,255,255,0.7)" stroke-width="1.8"/>
                                <path d="M2 17c0-3.314 3.582-6 8-6s8 2.686 8 6" stroke="rgba(255,255,255,0.7)" stroke-width="1.8" stroke-linecap="round"/>
                            </svg>
                        </span>
                        <span class="nav__label">Profile</span>
                    </a>
                </li>
            </ul>
        </nav>

        <!-- User Info + Logout -->
        <div class="sidebar__footer">
            <div class="sidebar__user-card">
                <span class="sidebar__user-label">Logged in as</span>
                <span class="sidebar__user-name"><?php echo htmlspecialchars($student_name); ?></span>
                <span class="sidebar__user-id">Student ID: <?php echo htmlspecialchars($student_id); ?></span>
            </div>
            <button class="sidebar__logout" type="button" onclick="window.location.href='logout.php'">
                <span class="sidebar__logout-icon" aria-hidden="true">
                    <!-- Logout icon -->
                    <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M7 3H4a1 1 0 00-1 1v12a1 1 0 001 1h3" stroke="rgba(255,255,255,0.85)" stroke-width="1.8" stroke-linecap="round"/>
                        <path d="M13 14l3-4-3-4" stroke="rgba(255,255,255,0.85)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M16 10H7" stroke="rgba(255,255,255,0.85)" stroke-width="1.8" stroke-linecap="round"/>
                    </svg>
                </span>
                <span class="sidebar__logout-label">Logout</span>
            </button>
        </div>
    </aside>

    <!-- ================================================================
         MAIN CONTENT
    ================================================================ -->
    <main class="main">

        <!-- Top Header Bar -->
        <header class="topbar">
            <div class="topbar__inner">
                <div style="display:flex;align-items:center;gap:12px;">
                    <!-- Hamburger (mobile only) -->
                    <button class="hamburger" id="hamburgerBtn" aria-expanded="false" aria-controls="sidebar" aria-label="Toggle navigation">
                        <span class="hamburger__bar"></span>
                        <span class="hamburger__bar"></span>
                        <span class="hamburger__bar"></span>
                    </button>
                    <div class="topbar__left">
                        <span class="topbar__title">Student Portal</span>
                        <span class="topbar__subtitle">National University - Lipa Campus</span>
                    </div>
                </div>
                <div class="topbar__actions">
                    <!-- Notification bell -->
                    <a href="#" class="topbar__action-btn" aria-label="Notifications">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" stroke="#4A5565" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span class="topbar__badge" aria-label="New notifications"></span>
                    </a>
                    <!-- Settings -->
                    <a href="#" class="topbar__action-btn" aria-label="Settings">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37a1.724 1.724 0 002.572-1.065z" stroke="#4A5565" stroke-width="1.8"/>
                            <circle cx="12" cy="12" r="3" stroke="#4A5565" stroke-width="1.8"/>
                        </svg>
                    </a>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <section class="page-content" aria-label="My Schedule">

            <!-- Page Header -->
            <div class="page-header">
                <div class="page-header__left">
                    <div class="page-header__title-row">
                        <div class="page-header__icon" aria-hidden="true">
                            <!-- Calendar icon for title -->
                            <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="4" y="8" width="32" height="28" rx="4" fill="#003087" opacity=".12"/>
                                <rect x="4" y="8" width="32" height="28" rx="4" stroke="#003087" stroke-width="2.5"/>
                                <path d="M13 4v8M27 4v8" stroke="#003087" stroke-width="2.5" stroke-linecap="round"/>
                                <path d="M4 18h32" stroke="#003087" stroke-width="2"/>
                                <rect x="10" y="23" width="5" height="5" rx="1" fill="#003087"/>
                                <rect x="18" y="23" width="5" height="5" rx="1" fill="#003087"/>
                                <rect x="26" y="23" width="5" height="5" rx="1" fill="#003087" opacity=".4"/>
                            </svg>
                        </div>
                        <h1 class="page-header__title">My Duty Schedule</h1>
                    </div>
                    <p class="page-header__subtitle">View and manage your weekly duty schedule</p>
                </div>
                <div class="page-header__controls">
                    <!-- View Toggle -->
                    <div class="view-toggle" role="group" aria-label="View mode">
                        <button class="view-toggle__btn view-toggle__btn--active" id="btnCalendar" type="button" aria-pressed="true">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                <rect x="1" y="3" width="14" height="12" rx="2" stroke="currentColor" stroke-width="1.5" fill="none"/>
                                <path d="M5 1v4M11 1v4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                <path d="M1 7h14" stroke="currentColor" stroke-width="1.2"/>
                            </svg>
                            Calendar
                        </button>
                        <button class="view-toggle__btn" id="btnList" type="button" aria-pressed="false">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                <path d="M2 4h12M2 8h12M2 12h12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" fill="none"/>
                            </svg>
                            List
                        </button>
                    </div>
                    <!-- Load Demo Data -->
                    <button class="btn-demo" type="button" id="btnLoadDemo">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M10 3v10M10 13l-3-3M10 13l3-3" stroke="white" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M3 14v2a1 1 0 001 1h12a1 1 0 001-1v-2" stroke="white" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                        Load Demo Data
                    </button>
                </div>
            </div>

            <!-- Stats Row -->
            <div class="stats-row">
                <!-- Total Hours -->
                <div class="stat-card">
                    <div class="stat-card__icon-wrap stat-card__icon-wrap--blue">
                        <svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="14" cy="14" r="11" stroke="white" stroke-width="2"/>
                            <path d="M14 8v6l4 3" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <div class="stat-card__info">
                        <span class="stat-card__label">Total Hours This Week</span>
                        <span class="stat-card__value"><?php echo (int) $total_hours_this_week; ?> hrs</span>
                    </div>
                </div>
                <!-- Scheduled Days -->
                <div class="stat-card">
                    <div class="stat-card__icon-wrap stat-card__icon-wrap--gold">
                        <svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect x="4" y="6" width="20" height="18" rx="3" stroke="white" stroke-width="2"/>
                            <path d="M9 3v6M19 3v6" stroke="white" stroke-width="2" stroke-linecap="round"/>
                            <path d="M4 12h20" stroke="white" stroke-width="1.5"/>
                        </svg>
                    </div>
                    <div class="stat-card__info">
                        <span class="stat-card__label">Scheduled Days</span>
                        <span class="stat-card__value"><?php echo (int) $scheduled_days_count; ?></span>
                    </div>
                </div>
                <!-- Week Navigator -->
                <div class="week-nav">
                    <button class="week-nav__btn" type="button" aria-label="Previous week" id="btnPrevWeek">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M10 12L6 8l4-4" stroke="#4A5565" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                    <div class="week-nav__info">
                        <div class="week-nav__label">Current Week</div>
                        <div class="week-nav__range" id="weekRange">Feb 10 - Feb 15, 2026</div>
                    </div>
                    <button class="week-nav__btn" type="button" aria-label="Next week" id="btnNextWeek">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M6 12l4-4-4-4" stroke="#4A5565" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Schedule Calendar Grid -->
            <div class="schedule-card">
                <?php if ($work_schedule_text === ''): ?>
                    <p style="margin:0 0 12px;font-size:14px;color:#4A5565;">
                        No duty schedule found yet. Once your application is processed, your weekly duty schedule will appear here.
                    </p>
                <?php endif; ?>
                <div class="schedule-grid">
                    <table class="schedule-table" role="grid" aria-label="Weekly duty schedule">
                        <colgroup>
                            <col class="col-time">
                            <col class="col-day"><!-- Monday -->
                            <col class="col-day"><!-- Tuesday -->
                            <col class="col-day"><!-- Wednesday -->
                            <col class="col-day"><!-- Thursday -->
                            <col class="col-day"><!-- Friday -->
                            <col class="col-day"><!-- Saturday -->
                            <col class="col-day"><!-- Sunday -->
                        </colgroup>
                        <thead>
                            <tr>
                                <th scope="col">TIME</th>
                                <th scope="col">Monday</th>
                                <th scope="col">Tuesday</th>
                                <th scope="col">Wednesday</th>
                                <th scope="col">Thursday</th>
                                <th scope="col">Friday</th>
                                <th scope="col">Saturday</th>
                                <th scope="col">Sunday</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Map weekly working schedule into visual blocks on the grid
                            $hours       = ['7AM','8AM','9AM','10AM','11AM','12PM','1PM','2PM','3PM','4PM','5PM','6PM','7PM','8PM','9PM'];
                            $totalRows   = count($hours);
                            $totalDays   = 7;

                            $dayIndexMap = [
                                'Monday'    => 1,
                                'Tuesday'   => 2,
                                'Wednesday' => 3,
                                'Thursday'  => 4,
                                'Friday'    => 5,
                                'Saturday'  => 6,
                                'Sunday'    => 7,
                            ];

                            $scheduleEvents = [];

                            foreach ($weekly_schedule as $dayName => $intervals) {
                                if (empty($intervals) || !isset($dayIndexMap[$dayName])) {
                                    continue;
                                }
                                $dayIndex = $dayIndexMap[$dayName];

                                foreach ($intervals as $interval) {
                                    $rangeParts = explode('–', $interval);
                                    if (count($rangeParts) !== 2) {
                                        continue;
                                    }

                                    $startStr = trim($rangeParts[0]);
                                    $endStr   = trim($rangeParts[1]);
                                    $startTs  = strtotime($startStr);
                                    $endTs    = strtotime($endStr);

                                    if (!$startTs || !$endTs || $endTs <= $startTs) {
                                        continue;
                                    }

                                    $startLabel = date('gA', $startTs); // e.g. 2PM
                                    $rowIndex   = array_search($startLabel, $hours, true);
                                    if ($rowIndex === false) {
                                        continue;
                                    }

                                    $spanHours = (int) (($endTs - $startTs) / 3600);
                                    if ($spanHours < 1) {
                                        continue;
                                    }

                                    $scheduleEvents[] = [
                                        'day'   => $dayIndex,
                                        'start' => $rowIndex,
                                        'span'  => $spanHours,
                                        'time'  => $interval,
                                        'loc'   => $work_location_display,
                                    ];
                                }
                            }

                            // Build lookup: [row][day] => event
                            $eventMap = [];
                            $skipMap  = []; // cells to skip (occupied by rowspan)
                            foreach ($scheduleEvents as $ev) {
                                $eventMap[$ev['start']][$ev['day']] = $ev;
                                for ($s = $ev['start'] + 1; $s < $ev['start'] + $ev['span']; $s++) {
                                    $skipMap[$s][$ev['day']] = true;
                                }
                            }

                            for ($r = 0; $r < $totalRows; $r++):
                            ?>
                            <tr>
                                <td class="td-time"><?= htmlspecialchars($hours[$r]) ?></td>
                                <?php for ($d = 1; $d <= $totalDays; $d++): ?>
                                    <?php if (!empty($skipMap[$r][$d])): ?>
                                        <?php /* Skip – merged by rowspan above */ ?>
                                    <?php elseif (!empty($eventMap[$r][$d])): ?>
                                        <?php $ev = $eventMap[$r][$d]; ?>
                                        <td rowspan="<?= $ev['span'] ?>" style="position:relative;vertical-align:top;">
                                            <div class="schedule-block" style="height:<?= ($ev['span'] * 64) - 8 ?>px;" role="article" aria-label="<?= htmlspecialchars($ev['time']) ?> at <?= htmlspecialchars($ev['loc']) ?>">
                                                <div class="schedule-block__meta">
                                                    <div class="schedule-block__time-row">
                                                        <span class="schedule-block__icon" aria-hidden="true">
                                                            <svg viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                <circle cx="6" cy="6" r="5" stroke="white" stroke-width="1.2"/>
                                                                <path d="M6 3.5V6l2 1.5" stroke="white" stroke-width="1.2" stroke-linecap="round"/>
                                                            </svg>
                                                        </span>
                                                        <span class="schedule-block__time"><?= htmlspecialchars($ev['time']) ?></span>
                                                    </div>
                                                    <div class="schedule-block__loc-row">
                                                        <span class="schedule-block__icon" aria-hidden="true">
                                                            <svg viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                <path d="M6 1a3.5 3.5 0 013.5 3.5C9.5 7.5 6 11 6 11S2.5 7.5 2.5 4.5A3.5 3.5 0 016 1z" stroke="white" stroke-width="1.2"/>
                                                                <circle cx="6" cy="4.5" r="1.2" fill="white"/>
                                                            </svg>
                                                        </span>
                                                        <span class="schedule-block__loc"><?= htmlspecialchars($ev['loc']) ?></span>
                                                    </div>
                                                </div>
                                                <div class="schedule-block__badge" aria-label="Recurring">🔄</div>
                                            </div>
                                        </td>
                                    <?php else: ?>
                                        <td></td>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Schedule Guidelines -->
            <div class="guidelines" role="region" aria-label="Schedule Guidelines">
                <div class="guidelines__title-row">
                    <div class="guidelines__icon" aria-hidden="true">
                        <svg viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="14" cy="14" r="11" stroke="#1C398E" stroke-width="2"/>
                            <path d="M14 9v6" stroke="#1C398E" stroke-width="2" stroke-linecap="round"/>
                            <circle cx="14" cy="18.5" r="1" fill="#1C398E"/>
                        </svg>
                    </div>
                    <h2 class="guidelines__title">Schedule Guidelines 📋</h2>
                </div>
                <div class="guidelines__grid">
                    <div class="guidelines__item">
                        <span class="guidelines__emoji" aria-hidden="true">⏰</span>
                        <div class="guidelines__text">
                            <span class="guidelines__item-title">Be Punctual</span>
                            <span class="guidelines__item-desc">Arrive 5-10 minutes before your scheduled time</span>
                        </div>
                    </div>
                    <div class="guidelines__item">
                        <span class="guidelines__emoji" aria-hidden="true">📞</span>
                        <div class="guidelines__text">
                            <span class="guidelines__item-title">Communication</span>
                            <span class="guidelines__item-desc">Notify Miss Zai if you need to reschedule</span>
                        </div>
                    </div>
                    <div class="guidelines__item">
                        <span class="guidelines__emoji" aria-hidden="true">📷</span>
                        <div class="guidelines__text">
                            <span class="guidelines__item-title">Attendance</span>
                            <span class="guidelines__item-desc">Always scan QR code when checking in/out</span>
                        </div>
                    </div>
                    <div class="guidelines__item">
                        <span class="guidelines__emoji" aria-hidden="true">✅</span>
                        <div class="guidelines__text">
                            <span class="guidelines__item-title">Complete Tasks</span>
                            <span class="guidelines__item-desc">Finish all assigned duties during your shift</span>
                        </div>
                    </div>
                </div>
            </div>

        </section><!-- /page-content -->
    </main>

</div><!-- /app -->

<script>
(function () {
    'use strict';

    /* ----------------------------------------------------------------
       Hamburger / Sidebar toggle
    ---------------------------------------------------------------- */
    const hamburger = document.getElementById('hamburgerBtn');
    const sidebar   = document.getElementById('sidebar');
    const overlay   = document.getElementById('sidebarOverlay');

    function openSidebar() {
        sidebar.classList.add('sidebar--open');
        overlay.classList.remove('sidebar-overlay--hidden');
        overlay.classList.add('sidebar-overlay--visible');
        hamburger.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        sidebar.classList.remove('sidebar--open');
        overlay.classList.remove('sidebar-overlay--visible');
        overlay.classList.add('sidebar-overlay--hidden');
        hamburger.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
    }

    if (hamburger) {
        hamburger.addEventListener('click', function () {
            const isOpen = sidebar.classList.contains('sidebar--open');
            isOpen ? closeSidebar() : openSidebar();
        });
    }

    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }

    /* Close sidebar on Escape key */
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && sidebar.classList.contains('sidebar--open')) {
            closeSidebar();
            hamburger && hamburger.focus();
        }
    });

    /* ----------------------------------------------------------------
       View Toggle (Calendar / List)
    ---------------------------------------------------------------- */
    const btnCalendar = document.getElementById('btnCalendar');
    const btnList     = document.getElementById('btnList');

    if (btnCalendar && btnList) {
        btnCalendar.addEventListener('click', function () {
            btnCalendar.classList.add('view-toggle__btn--active');
            btnCalendar.setAttribute('aria-pressed', 'true');
            btnList.classList.remove('view-toggle__btn--active');
            btnList.setAttribute('aria-pressed', 'false');
        });

        btnList.addEventListener('click', function () {
            btnList.classList.add('view-toggle__btn--active');
            btnList.setAttribute('aria-pressed', 'true');
            btnCalendar.classList.remove('view-toggle__btn--active');
            btnCalendar.setAttribute('aria-pressed', 'false');
        });
    }

    /* ----------------------------------------------------------------
       Week Navigator
    ---------------------------------------------------------------- */
    var weekOffset = 0;

    // Compute the Monday of the current week, then move by offset weeks.
    function getWeekRange(offset) {
        var today = new Date();
        var day   = today.getDay(); // 0=Sun,1=Mon,...6=Sat

        var start = new Date(today);
        var diffToMonday = (day === 0 ? -6 : 1 - day); // move to Monday
        start.setDate(start.getDate() + diffToMonday + offset * 7);

        var end = new Date(start);
        end.setDate(end.getDate() + 5); // Monday–Saturday window

        var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        var sm = months[start.getMonth()];
        var em = months[end.getMonth()];
        var sy = start.getFullYear();
        var ey = end.getFullYear();

        if (sm === em && sy === ey) {
            return sm + ' ' + start.getDate() + ' - ' + end.getDate() + ', ' + sy;
        }
        var endStr = em + ' ' + end.getDate() + ', ' + ey;
        return sm + ' ' + start.getDate() + ' - ' + endStr;
    }

    var weekRangeEl = document.getElementById('weekRange');
    var btnPrev     = document.getElementById('btnPrevWeek');
    var btnNext     = document.getElementById('btnNextWeek');

    if (btnPrev && btnNext && weekRangeEl) {
        // Set initial range based on the actual current week
        weekRangeEl.textContent = getWeekRange(0);

        btnPrev.addEventListener('click', function () {
            weekOffset--;
            weekRangeEl.textContent = getWeekRange(weekOffset);
        });

        btnNext.addEventListener('click', function () {
            weekOffset++;
            weekRangeEl.textContent = getWeekRange(weekOffset);
        });
    }

    /* ----------------------------------------------------------------
       Load Demo Data (no-op placeholder)
    ---------------------------------------------------------------- */
    var btnDemo = document.getElementById('btnLoadDemo');
    if (btnDemo) {
        btnDemo.addEventListener('click', function () {
            // Demo data is already displayed on page load.
            // Extend here to fetch from backend via fetch() if needed.
            alert('Demo schedule data is already loaded.');
        });
    }

})();
</script>

</body>
</html>