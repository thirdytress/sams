<?php
// dashboard.php – NU SAMS Student Portal Dashboard

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

function format_weekly_duration(int $totalMinutes): string {
    $totalMinutes = max(0, $totalMinutes);
    $hours = intdiv($totalMinutes, 60);
    $minutes = $totalMinutes % 60;

    if ($hours === 0 && $minutes === 0) {
        return '0 hrs 0 mins';
    }

    $parts = [];
    if ($hours > 0) {
        $parts[] = $hours . ' hr' . ($hours === 1 ? '' : 's');
    }
    if ($minutes > 0) {
        $parts[] = $minutes . ' min' . ($minutes === 1 ? '' : 's');
    }

    return implode(' ', $parts);
}

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

// Dashboard metrics (start from zero / empty; later can be populated from database)
$total_minutes_this_week     = 0;
$total_hours_this_week       = '0 hrs 0 mins';
$upcoming_duties_count       = 0;
$duties_completed_this_month = 0;
$attendance_rate_percent     = 0;

$weekStart = date('Y-m-d 00:00:00', strtotime('monday this week'));
$weekEnd   = date('Y-m-d 23:59:59', strtotime('sunday this week'));

if ($stmt = $mysqli->prepare('SELECT check_in_time, check_out_time FROM attendance WHERE student_id = ? AND check_in_time BETWEEN ? AND ? ORDER BY check_in_time ASC')) {
    $stmt->bind_param('sss', $student_id, $weekStart, $weekEnd);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($row = $res ? $res->fetch_assoc() : null) {
            if ($row === null) {
                break;
            }

            $checkIn = !empty($row['check_in_time']) ? strtotime((string) $row['check_in_time']) : false;
            if ($checkIn === false) {
                continue;
            }

            $checkOut = !empty($row['check_out_time']) ? strtotime((string) $row['check_out_time']) : time();
            if ($checkOut === false || $checkOut < $checkIn) {
                continue;
            }

            $totalMinutesThisRecord = (int) floor(($checkOut - $checkIn) / 60);
            $total_minutes_this_week += $totalMinutesThisRecord;
        }
        $res && $res->free();
    }
    $stmt->close();
}

$total_hours_this_week = format_weekly_duration($total_minutes_this_week);

// Upcoming duties, announcements, and performance could later come from DB
$upcoming_duties = [];
$announcements   = [];

// Fetch latest saved working schedule for this student (from application step)
$work_schedule_text    = '';
$today_schedule_line   = '';
$has_today_schedule    = false;
$today_label_for_card  = '';

if ($stmt = $mysqli->prepare('SELECT work_schedule FROM student_applications WHERE student_id = ? ORDER BY created_at DESC LIMIT 1')) {
    $stmt->bind_param('s', $student_id);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $work_schedule_text = trim($row['work_schedule'] ?? '');
        }
        $res->free();
    }
    $stmt->close();
}

if ($work_schedule_text !== '') {
    $todayLabel = date('l'); // e.g. "Monday"
    $parts      = explode('|', $work_schedule_text);
    foreach ($parts as $part) {
        $partTrim = trim($part);
        if ($partTrim === '') {
            continue;
        }
        // Expect format like "Monday: 8:00AM–2:00PM, 4:00PM–5:00PM"
        if (stripos($partTrim, $todayLabel . ':') === 0) {
            $today_schedule_line  = $partTrim;
            $today_label_for_card = $todayLabel;
            $has_today_schedule   = true;
            break;
        }
    }

    // Fallback: show full schedule string if no specific day line matched
    if (!$has_today_schedule) {
        $today_schedule_line  = $work_schedule_text;
        $today_label_for_card = 'Your working hours';
        $has_today_schedule   = true;
    }
}

$unread_reports = 0;
$mysqli->query("CREATE TABLE IF NOT EXISTS student_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL,
    student_name VARCHAR(150) NOT NULL,
    report_type VARCHAR(50) NOT NULL,
    subject VARCHAR(180) NOT NULL,
    report_body TEXT NOT NULL,
    created_by VARCHAR(150) NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    read_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_student_id (student_id),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
if ($stmt = $mysqli->prepare('SELECT COUNT(*) AS c FROM student_reports WHERE student_id = ? AND is_read = 0')) {
    $stmt->bind_param('s', $student_id);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        if ($row = $res ? $res->fetch_assoc() : null) {
            $unread_reports = (int) ($row['c'] ?? 0);
        }
        $res && $res->free();
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <script src="../assets/realtime.js"></script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>NU SAMS – Student Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;900&display=swap" rel="stylesheet" />
    <style>
        /* =============================================
           CSS VARIABLES – Design System
        ============================================= */
        :root {
            /* Brand */
            --color-primary:        #003087;
            --color-primary-light:  #0047ab;

            /* Neutral */
            --color-heading:        #101828;
            --color-body:           #4a5565;
            --color-label:          #364153;
            --color-muted:          #6a7282;
            --color-border:         #e5e7eb;
            --color-border-card:    #f3f4f6;
            --color-bg-row:         #f9fafb;
            --color-white:          #ffffff;

            /* Sidebar */
            --sidebar-width:        288px;
            --sidebar-bg-start:     #003087;
            --sidebar-bg-end:       #0047ab;
            --color-sidebar-text:   #ffffff;
            --color-sidebar-muted:  #bedbff;

            /* Status colours */
            --color-green:          #00c950;
            --color-green-dark:     #00a63e;
            --color-green-bg:       #dcfce7;
            --color-green-light-bg: #f0fdf4;
            --color-blue:           #2b7fff;
            --color-blue-dark:      #155dfc;
            --color-blue-bg:        #dbeafe;
            --color-blue-light-bg:  #eff6ff;
            --color-yellow:         #ffb81c;
            --color-yellow-dark:    #e17100;
            --color-yellow-bg:      #fffbeb;
            --color-purple:         #ad46ff;
            --color-purple-dark:    #9810fa;
            --color-purple-bg:      #f3e8ff;
            --color-red:            #fb2c36;

            /* Gradients */
            --gradient-page:        linear-gradient(134.87deg, #eff6ff 0%, #ffffff 50%, #fffbeb 100%);
            --gradient-sidebar:     linear-gradient(180deg, #003087 0%, #0047ab 100%);
            --gradient-primary:     linear-gradient(135deg, #003087 0%, #0047ab 100%);
            --gradient-yellow:      linear-gradient(135deg, #ffb81c 0%, #ffa500 100%);
            --gradient-green:       linear-gradient(135deg, #00c950 0%, #00a63e 100%);
            --gradient-purple:      linear-gradient(135deg, #ad46ff 0%, #9810fa 100%);
            --gradient-tips:        linear-gradient(146.52deg, #003087 0%, #0047ab 100%);
            --gradient-qa-green:    linear-gradient(141.1deg, #f0fdf4 0%, #dcfce7 100%);
            --gradient-qa-blue:     linear-gradient(141.1deg, #eff6ff 0%, #dbeafe 100%);
            --gradient-qa-purple:   linear-gradient(141.1deg, #faf5ff 0%, #f3e8ff 100%);

            /* Radii */
            --radius-card:   16px;
            --radius-item:   14px;
            --radius-badge:  9999px;
            --radius-btn:    14px;
            --radius-annc:   10px;

            /* Shadows */
            --shadow-sidebar: 0 25px 50px rgba(0,0,0,.25);
            --shadow-header:  0 1px 3px rgba(0,0,0,.10), 0 1px 2px rgba(0,0,0,.10);
            --shadow-card:    0 10px 15px rgba(0,0,0,.10), 0 4px 6px rgba(0,0,0,.10);

            /* Typography */
            --font-xs:   12px;
            --font-sm:   14px;
            --font-base: 16px;
            --font-md:   18px;
            --font-lg:   20px;
            --font-xl:   24px;
            --font-2xl:  30px;
            --font-3xl:  36px;
            --font-4xl:  40px; /* not used, kept for scale */

            --lh-xs:   16px;
            --lh-sm:   20px;
            --lh-base: 24px;
            --lh-md:   28px;
            --lh-lg:   32px;
            --lh-xl:   36px;
            --lh-2xl:  40px;
        }

        /* =============================================
           RESET & BASE
        ============================================= */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--gradient-page);
            min-height: 100vh;
            color: var(--color-heading);
            display: flex;
        }
        a { text-decoration: none; color: inherit; }
        img { display: block; max-width: 100%; }
        button { font-family: inherit; cursor: pointer; border: none; background: none; }

        /* =============================================
           LAYOUT SHELL
        ============================================= */
        .shell {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }

        /* =============================================
           SIDEBAR
        ============================================= */
        .sidebar {
            width: var(--sidebar-width);
            min-height: 100vh;
            background: var(--gradient-sidebar);
            box-shadow: var(--shadow-sidebar);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }

        /* Brand */
        .sidebar__brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 24px 24px 20px;
            border-bottom: 1px solid rgba(255,255,255,.20);
        }
        .sidebar__logo {
            width: 48px;
            height: 48px;
            background: var(--color-white);
            border-radius: var(--radius-item);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .sidebar__logo-text {
            font-size: var(--font-xl);
            font-weight: 900;
            color: var(--color-primary);
            line-height: 1;
        }
        .sidebar__brand-info {}
        .sidebar__brand-name {
            font-size: var(--font-lg);
            font-weight: 900;
            color: var(--color-sidebar-text);
            line-height: var(--lh-md);
        }
        .sidebar__brand-sub {
            font-size: var(--font-xs);
            font-weight: 400;
            color: var(--color-sidebar-muted);
            line-height: var(--lh-xs);
            white-space: nowrap;
        }

        /* Nav */
        .sidebar__nav {
            flex: 1;
            padding: 24px 16px 0;
        }
        .sidebar__nav-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .sidebar__nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            height: 48px;
            padding: 0 16px;
            border-radius: var(--radius-item);
            font-size: var(--font-base);
            font-weight: 700;
            color: var(--color-sidebar-text);
            transition: background .15s;
        }
        .sidebar__nav-link:hover {
            background: rgba(255,255,255,.12);
        }
        .sidebar__nav-link--active {
            background: var(--color-white);
            color: var(--color-primary);
            box-shadow: var(--shadow-card);
        }
        .sidebar__nav-icon {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }

        /* User block */
        .sidebar__footer {
            padding: 16px 16px 20px;
            border-top: 1px solid rgba(255,255,255,.20);
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .sidebar__user {
            background: rgba(255,255,255,.10);
            border-radius: var(--radius-item);
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .sidebar__user-label {
            font-size: var(--font-sm);
            font-weight: 500;
            color: var(--color-sidebar-muted);
            line-height: var(--lh-sm);
        }
        .sidebar__user-name {
            font-size: var(--font-base);
            font-weight: 900;
            color: var(--color-sidebar-text);
            line-height: var(--lh-base);
        }
        .sidebar__user-id {
            font-size: var(--font-xs);
            font-weight: 400;
            color: var(--color-sidebar-muted);
            line-height: var(--lh-xs);
        }
        .sidebar__logout {
            background: rgba(255,255,255,.10);
            border-radius: var(--radius-item);
            height: 48px;
            padding: 0 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: background .15s;
        }
        .sidebar__logout:hover { background: rgba(255,255,255,.20); }
        .sidebar__logout-icon { width: 20px; height: 20px; flex-shrink: 0; }
        .sidebar__logout-label {
            font-size: var(--font-base);
            font-weight: 700;
            color: var(--color-sidebar-text);
            line-height: var(--lh-base);
        }

        /* =============================================
           MAIN AREA
        ============================================= */
        .main {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
        }

        /* Top header bar */
        .topbar {
            background: var(--color-white);
            border-bottom: 1px solid var(--color-border);
            box-shadow: var(--shadow-header);
            height: 85px;
            padding: 16px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .topbar__info {}
        .topbar__title {
            font-size: var(--font-xl);
            font-weight: 900;
            line-height: var(--lh-lg);
            color: var(--color-heading);
        }
        .topbar__sub {
            font-size: var(--font-sm);
            font-weight: 500;
            line-height: var(--lh-sm);
            color: var(--color-body);
            white-space: nowrap;
        }
        .topbar__actions {
            display: flex;
            align-items: center;
            gap: 0;
        }
        .topbar__icon-btn {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            cursor: pointer;
            transition: background .15s;
        }
        .topbar__icon-btn:hover { background: var(--color-border-card); }
        .topbar__icon-btn img { width: 24px; height: 24px; }
        .topbar__badge {
            position: absolute;
            top: 0;
            right: 0;
            width: 12px;
            height: 12px;
            background: var(--color-red);
            border: 2px solid var(--color-white);
            border-radius: var(--radius-badge);
        }

        /* Hamburger (mobile only) */
        .topbar__hamburger {
            display: none;
            flex-direction: column;
            gap: 5px;
            width: 32px;
            height: 32px;
            justify-content: center;
            align-items: center;
            padding: 0;
            margin-right: 16px;
        }
        .topbar__hamburger-bar {
            display: block;
            width: 22px;
            height: 2px;
            background: var(--color-heading);
            border-radius: 2px;
            transition: transform .3s, opacity .3s;
        }
        .topbar__hamburger[aria-expanded="true"] .topbar__hamburger-bar:nth-child(1) { transform: translateY(7px) rotate(45deg); }
        .topbar__hamburger[aria-expanded="true"] .topbar__hamburger-bar:nth-child(2) { opacity: 0; }
        .topbar__hamburger[aria-expanded="true"] .topbar__hamburger-bar:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

        /* =============================================
           DASHBOARD CONTENT
        ============================================= */
        .dashboard {
            padding: 32px 32px 48px;
            display: flex;
            flex-direction: column;
            gap: 32px;
            flex: 1;
        }

        /* Page heading */
        .dashboard__heading {}
        .dashboard__title {
            font-size: 36px;
            font-weight: 900;
            line-height: var(--lh-2xl);
            color: var(--color-heading);
            margin-bottom: 4px;
        }
        .dashboard__sub {
            font-size: var(--font-md);
            font-weight: 500;
            line-height: var(--lh-md);
            color: var(--color-body);
        }

        /* =============================================
           STAT CARDS ROW
        ============================================= */
        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
        }
        .stat-card {
            background: var(--color-white);
            border: 2px solid var(--color-border-card);
            border-radius: var(--radius-card);
            box-shadow: var(--shadow-card);
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 0;
        }
        .stat-card__top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }
        .stat-card__icon-wrap {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-item);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .stat-card__icon-wrap img { width: 24px; height: 24px; }
        .stat-card__icon-wrap--blue    { background: var(--gradient-primary); }
        .stat-card__icon-wrap--yellow  { background: var(--gradient-yellow); }
        .stat-card__icon-wrap--green   { background: var(--gradient-green); }
        .stat-card__icon-wrap--purple  { background: var(--gradient-purple); }

        .stat-card__badge {
            height: 24px;
            border-radius: var(--radius-badge);
            padding: 4px 12px;
            font-size: var(--font-xs);
            font-weight: 700;
            line-height: var(--lh-xs);
            white-space: nowrap;
        }
        .stat-card__badge--green  { background: var(--color-green-bg);  color: var(--color-green-dark); }
        .stat-card__badge--blue   { background: var(--color-blue-bg);   color: var(--color-blue-dark); }
        .stat-card__badge--purple { background: var(--color-purple-bg); color: var(--color-purple-dark); }

        .stat-card__value {
            font-size: var(--font-2xl);
            font-weight: 900;
            line-height: var(--lh-xl);
            color: var(--color-heading);
            margin-bottom: 4px;
        }
        .stat-card__label {
            font-size: var(--font-sm);
            font-weight: 500;
            line-height: var(--lh-sm);
            color: var(--color-body);
        }

        /* =============================================
           TWO-COLUMN LOWER GRID
        ============================================= */
        .dashboard__grid {
            display: grid;
            grid-template-columns: 1fr 381px;
            gap: 32px;
            align-items: start;
        }

        /* Left column */
        .dashboard__left {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        /* Right column */
        .dashboard__right {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        /* =============================================
           SHARED CARD BASE
        ============================================= */
        .card {
            background: var(--color-white);
            border: 2px solid var(--color-border-card);
            border-radius: var(--radius-card);
            box-shadow: var(--shadow-card);
            padding: 26px;
        }
        .card__header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }
        .card__title-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .card__title-icon { width: 28px; height: 28px; flex-shrink: 0; }
        .card__title {
            font-size: var(--font-xl);
            font-weight: 900;
            line-height: var(--lh-lg);
            color: var(--color-heading);
            white-space: nowrap;
        }
        .card__title--lg {
            font-size: var(--font-lg);
            line-height: var(--lh-md);
        }
        .card__link {
            font-size: var(--font-sm);
            font-weight: 700;
            line-height: var(--lh-sm);
            color: var(--color-primary);
            white-space: nowrap;
        }
        .card__link:hover { text-decoration: underline; }

        /* =============================================
           TODAY'S SCHEDULE – EMPTY STATE
        ============================================= */
        .schedule-empty {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 48px 0 16px;
            gap: 16px;
        }
        .schedule-empty__icon-wrap {
            width: 96px;
            height: 96px;
            background: var(--color-border-card);
            border-radius: var(--radius-badge);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .schedule-empty__icon-wrap img { width: 48px; height: 48px; }
        .schedule-empty__title {
            font-size: var(--font-lg);
            font-weight: 900;
            line-height: var(--lh-md);
            color: var(--color-heading);
            text-align: center;
        }
        .schedule-empty__sub {
            font-size: var(--font-base);
            font-weight: 500;
            line-height: var(--lh-base);
            color: var(--color-body);
            text-align: center;
        }
        .schedule-empty__btn {
            display: inline-block;
            background: var(--color-primary);
            color: var(--color-white);
            font-size: var(--font-base);
            font-weight: 700;
            line-height: var(--lh-base);
            padding: 12px 24px;
            border-radius: var(--radius-btn);
            text-decoration: none;
            transition: opacity .15s;
        }
        .schedule-empty__btn:hover { opacity: .9; }

        /* =============================================
           QUICK ACTIONS
        ============================================= */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }
        .qa-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            padding: 26px 2px;
            border-radius: var(--radius-card);
            border: 2px solid;
            cursor: pointer;
            text-decoration: none;
            transition: opacity .15s;
        }
        .qa-btn:hover { opacity: .88; }
        .qa-btn--green  { background: var(--gradient-qa-green);  border-color: #b9f8cf; }
        .qa-btn--blue   { background: var(--gradient-qa-blue);   border-color: #bedbff; }
        .qa-btn--purple { background: var(--gradient-qa-purple); border-color: #e9d4ff; }

        .qa-btn__icon-wrap {
            width: 56px;
            height: 56px;
            border-radius: var(--radius-card);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .qa-btn__icon-wrap--green  { background: #00c950; }
        .qa-btn__icon-wrap--blue   { background: #2b7fff; }
        .qa-btn__icon-wrap--purple { background: #ad46ff; }
        .qa-btn__icon-wrap img { width: 28px; height: 28px; }
        .qa-btn__icon-wrap--emoji {
            font-size: 30px;
            line-height: 1;
        }
        .qa-btn__label {
            font-size: var(--font-sm);
            font-weight: 900;
            line-height: var(--lh-sm);
            color: var(--color-heading);
            text-align: center;
            white-space: nowrap;
        }

        /* =============================================
           UPCOMING DUTIES
        ============================================= */
        .duties-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .duty-row {
            background: var(--color-bg-row);
            border-radius: var(--radius-item);
            height: 80px;
            padding: 0 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .duty-row__left {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .duty-row__icon-wrap {
            width: 48px;
            height: 48px;
            background: var(--color-primary);
            border-radius: var(--radius-item);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .duty-row__icon-wrap img { width: 24px; height: 24px; }
        .duty-row__day {
            font-size: var(--font-base);
            font-weight: 900;
            line-height: var(--lh-base);
            color: var(--color-heading);
        }
        .duty-row__date {
            font-size: var(--font-sm);
            font-weight: 500;
            line-height: var(--lh-sm);
            color: var(--color-body);
        }
        .duty-row__right {
            text-align: right;
        }
        .duty-row__time {
            font-size: var(--font-base);
            font-weight: 900;
            line-height: var(--lh-base);
            color: var(--color-primary);
        }
        .duty-row__location {
            font-size: var(--font-sm);
            font-weight: 500;
            line-height: var(--lh-sm);
            color: var(--color-body);
        }

        /* =============================================
           ANNOUNCEMENTS
        ============================================= */
        .annc-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 16px;
        }
        .annc-header__icon { width: 24px; height: 24px; flex-shrink: 0; }
        .annc-header__title {
            font-size: var(--font-lg);
            font-weight: 900;
            line-height: var(--lh-md);
            color: var(--color-heading);
        }
        .annc-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .annc-item {
            border-left: 4px solid;
            border-radius: var(--radius-annc);
            padding: 16px;
        }
        .annc-item--blue   { background: var(--color-blue-light-bg);  border-color: var(--color-primary); }
        .annc-item--yellow { background: var(--color-yellow-bg);       border-color: var(--color-yellow); }
        .annc-item--green  { background: var(--color-green-light-bg);  border-color: var(--color-green); }
        .annc-item__time {
            font-size: var(--font-xs);
            font-weight: 700;
            line-height: var(--lh-xs);
            margin-bottom: 4px;
        }
        .annc-item--blue   .annc-item__time { color: var(--color-blue-dark); }
        .annc-item--yellow .annc-item__time { color: var(--color-yellow-dark); }
        .annc-item--green  .annc-item__time { color: var(--color-green-dark); }
        .annc-item__title {
            font-size: var(--font-base);
            font-weight: 900;
            line-height: var(--lh-base);
            color: var(--color-heading);
            margin-bottom: 8px;
        }
        .annc-item__body {
            font-size: var(--font-sm);
            font-weight: 500;
            line-height: var(--lh-sm);
            color: var(--color-label);
        }

        /* =============================================
           TIPS & REMINDERS
        ============================================= */
        .tips-card {
            background: var(--gradient-tips);
            border-radius: var(--radius-card);
            box-shadow: var(--shadow-card);
            padding: 24px;
        }
        .tips-card__header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 16px;
        }
        .tips-card__emoji {
            font-size: var(--font-xl);
            line-height: 1;
        }
        .tips-card__title {
            font-size: var(--font-lg);
            font-weight: 900;
            line-height: var(--lh-md);
            color: var(--color-white);
        }
        .tips-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .tips-list__item {
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }
        .tips-list__check {
            font-size: var(--font-sm);
            font-weight: 500;
            color: var(--color-yellow);
            line-height: var(--lh-sm);
            flex-shrink: 0;
        }
        .tips-list__text {
            font-size: var(--font-sm);
            font-weight: 500;
            color: #dbeafe;
            line-height: var(--lh-sm);
        }

        /* =============================================
           PERFORMANCE
        ============================================= */
        .perf-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .perf-row {}
        .perf-row__top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        .perf-row__label {
            font-size: var(--font-sm);
            font-weight: 700;
            line-height: var(--lh-sm);
            color: var(--color-label);
        }
        .perf-row__value {
            font-size: var(--font-sm);
            font-weight: 900;
            line-height: var(--lh-sm);
        }
        .perf-row__value--green  { color: var(--color-green-dark); }
        .perf-row__value--blue   { color: var(--color-blue-dark); }
        .perf-row__value--purple { color: var(--color-purple-dark); }
        .perf-bar-track {
            height: 12px;
            background: var(--color-border);
            border-radius: var(--radius-badge);
            overflow: hidden;
        }
        .perf-bar-fill {
            height: 100%;
            border-radius: var(--radius-badge);
        }
        .perf-bar-fill--green  { background: var(--color-green); }
        .perf-bar-fill--blue   { background: var(--color-blue); }
        .perf-bar-fill--purple { background: var(--color-purple); }

        /* =============================================
           SIDEBAR OVERLAY (mobile)
        ============================================= */
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.4);
            z-index: 90;
        }
        .sidebar-overlay--visible { display: block; }

        /* =============================================
           RESPONSIVE – TABLET (≤1024px)
        ============================================= */
        @media (max-width: 1024px) {
            .sidebar {
                position: fixed;
                left: 0;
                top: 0;
                height: 100%;
                z-index: 100;
                transform: translateX(-100%);
                transition: transform .3s ease;
            }
            .sidebar--open {
                transform: translateX(0);
            }
            .topbar__hamburger {
                display: flex;
            }
            .topbar {
                padding: 16px 24px;
            }
            .dashboard {
                padding: 24px 24px 48px;
            }
            .stats {
                grid-template-columns: repeat(2, 1fr);
            }
            .dashboard__grid {
                grid-template-columns: 1fr;
            }
            .dashboard__right {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* =============================================
           RESPONSIVE – MOBILE (≤768px)
        ============================================= */
        @media (max-width: 768px) {
            .topbar {
                padding: 12px 16px;
                height: auto;
                min-height: 64px;
            }
            .dashboard {
                padding: 20px 16px 48px;
                gap: 20px;
            }
            .stats {
                grid-template-columns: 1fr 1fr;
                gap: 12px;
            }
            .stat-card { padding: 16px; }
            .dashboard__grid {
                grid-template-columns: 1fr;
            }
            .dashboard__right {
                display: flex;
                flex-direction: column;
                gap: 20px;
            }
            .quick-actions {
                grid-template-columns: repeat(3, 1fr);
                gap: 8px;
            }
            .dashboard__title { font-size: 26px; }
            .card { padding: 20px 16px; }
        }
    </style>
</head>
<body>

<!-- Sidebar overlay for mobile -->
<div class="sidebar-overlay" id="sidebar-overlay" aria-hidden="true"></div>

<div class="shell">

    <!-- ════════════════════════════════════════
                <li>
                    <a href="chat.php" class="sidebar__nav-link">
                        <svg class="sidebar__nav-icon" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M4 4h12a2 2 0 012 2v6a2 2 0 01-2 2H9l-4 3v-3H4a2 2 0 01-2-2V6a2 2 0 012-2z" stroke="rgba(255,255,255,0.7)" stroke-width="1.5" stroke-linejoin="round"/>
                        </svg>
                        Messages
                    </a>
                </li>
         SIDEBAR
    ════════════════════════════════════════ -->
    <aside class="sidebar" id="sidebar" aria-label="Main navigation">

        <!-- Brand -->
        <div class="sidebar__brand">
            <div class="sidebar__logo" aria-hidden="true">
                <span class="sidebar__logo-text">NU</span>
            </div>
            <div class="sidebar__brand-info">
                <div class="sidebar__brand-name">SAMS</div>
                <div class="sidebar__brand-sub">Student Assistant Management</div>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="sidebar__nav" aria-label="Sidebar navigation">
            <ul class="sidebar__nav-list">
                <li>
                    <a href="dashboard.php" class="sidebar__nav-link sidebar__nav-link--active" aria-current="page">
                        <!-- Home/Dashboard icon -->
                        <svg class="sidebar__nav-icon" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M2.5 7.5L10 2.5L17.5 7.5V17.5H12.5V12.5H7.5V17.5H2.5V7.5Z" stroke="#003087" stroke-width="1.67" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Dashboard
                    </a>
                </li>
                <li>
                    <a href="schedule.php" class="sidebar__nav-link">
                        <!-- Calendar icon -->
                        <svg class="sidebar__nav-icon" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <rect x="2.5" y="3.5" width="15" height="14" rx="2" stroke="white" stroke-width="1.67"/>
                            <path d="M2.5 8H17.5" stroke="white" stroke-width="1.67"/>
                            <path d="M6.5 2V5" stroke="white" stroke-width="1.67" stroke-linecap="round"/>
                            <path d="M13.5 2V5" stroke="white" stroke-width="1.67" stroke-linecap="round"/>
                        </svg>
                        My Schedule
                    </a>
                </li>
                <li>
                    <a href="attendance.php" class="sidebar__nav-link">
                        <!-- Checkmark icon -->
                        <svg class="sidebar__nav-icon" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M17.5 5L8.5 14.5L3 9" stroke="white" stroke-width="1.67" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Attendance
                    </a>
                </li>
                <li>
                    <a href="profile.php" class="sidebar__nav-link">
                        <!-- Person icon -->
                        <svg class="sidebar__nav-icon" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M10 10C12.2091 10 14 8.20914 14 6C14 3.79086 12.2091 2 10 2C7.79086 2 6 3.79086 6 6C6 8.20914 7.79086 10 10 10Z" stroke="white" stroke-width="1.67"/>
                            <path d="M17.5 18C17.5 15.2386 14.1421 13 10 13C5.85786 13 2.5 15.2386 2.5 18" stroke="white" stroke-width="1.67" stroke-linecap="round"/>
                        </svg>
                        Profile
                    </a>
                </li>
            </ul>
        </nav>

        <!-- User footer -->
        <div class="sidebar__footer">
            <div class="sidebar__user" aria-label="Logged in user">
                <span class="sidebar__user-label">Logged in as</span>
                <span class="sidebar__user-name"><?= htmlspecialchars($student_name) ?></span>
                <span class="sidebar__user-id">Student ID: <?= htmlspecialchars($student_id) ?></span>
            </div>
            <a href="logout.php" class="sidebar__logout" role="button" aria-label="Logout">
                <!-- Logout icon -->
                <svg class="sidebar__logout-icon" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M13 15L18 10L13 5" stroke="white" stroke-width="1.67" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M18 10H8" stroke="white" stroke-width="1.67" stroke-linecap="round"/>
                    <path d="M8 18H3.5C3.22386 18 3 17.7761 3 17.5V2.5C3 2.22386 3.22386 2 3.5 2H8" stroke="white" stroke-width="1.67" stroke-linecap="round"/>
                </svg>
                <span class="sidebar__logout-label">Logout</span>
            </a>
        </div>

    </aside>
    <!-- /SIDEBAR -->

    <!-- ════════════════════════════════════════
         MAIN
    ════════════════════════════════════════ -->
    <div class="main">

        <!-- Top bar -->
        <header class="topbar" role="banner">
            <div style="display:flex;align-items:center;">
                <button
                    class="topbar__hamburger"
                    id="hamburger-btn"
                    aria-expanded="false"
                    aria-controls="sidebar"
                    aria-label="Toggle navigation"
                >
                    <span class="topbar__hamburger-bar"></span>
                    <span class="topbar__hamburger-bar"></span>
                    <span class="topbar__hamburger-bar"></span>
                </button>
                <div class="topbar__info">
                    <div class="topbar__title">Student Portal</div>
                    <div class="topbar__sub"><?= htmlspecialchars($campus) ?></div>
                </div>
            </div>
            <div class="topbar__actions">
                <!-- Settings icon -->
                <div class="topbar__icon-btn" aria-label="Settings" role="button" tabindex="0">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M12 15C13.6569 15 15 13.6569 15 12C15 10.3431 13.6569 9 12 9C10.3431 9 9 10.3431 9 12C9 13.6569 10.3431 15 12 15Z" stroke="#4a5565" stroke-width="1.5"/>
                        <path d="M19.4 15C19.1277 15.6171 19.2583 16.3378 19.73 16.82L19.79 16.88C20.1656 17.2551 20.3766 17.7642 20.3766 18.295C20.3766 18.8258 20.1656 19.3349 19.79 19.71C19.4149 20.0856 18.9058 20.2966 18.375 20.2966C17.8442 20.2966 17.3351 20.0856 16.96 19.71L16.9 19.65C16.4178 19.1783 15.6971 19.0477 15.08 19.32C14.4755 19.5791 14.0826 20.1724 14.08 20.83V21C14.08 22.1046 13.1846 23 12.08 23C10.9754 23 10.08 22.1046 10.08 21V20.91C10.0642 20.2327 9.63587 19.6339 9 19.4C8.38291 19.1277 7.66219 19.2583 7.18 19.73L7.12 19.79C6.74485 20.1656 6.23577 20.3766 5.705 20.3766C5.17423 20.3766 4.66515 20.1656 4.29 19.79C3.91435 19.4149 3.70343 18.9058 3.70343 18.375C3.70343 17.8442 3.91435 17.3351 4.29 16.96L4.35 16.9C4.82167 16.4178 4.95231 15.6971 4.68 15.08C4.42093 14.4755 3.82764 14.0826 3.17 14.08H3C1.89543 14.08 1 13.1846 1 12.08C1 10.9754 1.89543 10.08 3 10.08H3.09C3.76733 10.0642 4.36613 9.63587 4.6 9C4.87231 8.38291 4.74167 7.66219 4.27 7.18L4.21 7.12C3.83435 6.74485 3.62343 6.23577 3.62343 5.705C3.62343 5.17423 3.83435 4.66515 4.21 4.29C4.58515 3.91435 5.09423 3.70343 5.625 3.70343C6.15577 3.70343 6.66485 3.91435 7.04 4.29L7.1 4.35C7.58219 4.82167 8.30291 4.95231 8.92 4.68H9C9.60447 4.42093 9.99739 3.82764 10 3.17V3C10 1.89543 10.8954 1 12 1C13.1046 1 14 1.89543 14 3V3.09C14.0026 3.74764 14.3955 4.34093 15 4.6C15.6171 4.87231 16.3378 4.74167 16.82 4.27L16.88 4.21C17.2551 3.83435 17.7642 3.62343 18.295 3.62343C18.8258 3.62343 19.3349 3.83435 19.71 4.21C20.0856 4.58515 20.2966 5.09423 20.2966 5.625C20.2966 6.15577 20.0856 6.66485 19.71 7.04L19.65 7.1C19.1783 7.58219 19.0477 8.30291 19.32 8.92V9C19.5791 9.60447 20.1724 9.99739 20.83 10H21C22.1046 10 23 10.8954 23 12C23 13.1046 22.1046 14 21 14H20.91C20.2524 14.0026 19.6591 14.3955 19.4 15Z" stroke="#4a5565" stroke-width="1.5"/>
                    </svg>
                </div>
                <!-- Notification icon -->
                <a href="reports.php" class="topbar__icon-btn" aria-label="Notifications">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M18 8C18 6.4087 17.3679 4.88258 16.2426 3.75736C15.1174 2.63214 13.5913 2 12 2C10.4087 2 8.88258 2.63214 7.75736 3.75736C6.63214 4.88258 6 6.4087 6 8C6 15 3 17 3 17H21C21 17 18 15 18 8Z" stroke="#4a5565" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M13.73 21C13.5542 21.3031 13.3019 21.5547 12.9982 21.7295C12.6946 21.9044 12.3504 21.9965 12 21.9965C11.6496 21.9965 11.3054 21.9044 11.0018 21.7295C10.6981 21.5547 10.4458 21.3031 10.27 21" stroke="#4a5565" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <?php if ($unread_reports > 0): ?>
                    <span class="topbar__badge" aria-label="New notifications"></span>
                    <?php endif; ?>
                </a>
            </div>
        </header>

        <!-- Dashboard content -->
        <main class="dashboard" id="main-content">

            <!-- Welcome heading -->
            <div class="dashboard__heading">
                 <h1 class="dashboard__title">Welcome back, <?= htmlspecialchars($student_name) ?>! 👋</h1>
                <p class="dashboard__sub">Here's what's happening with your duties today</p>
            </div>
                <li>
                    <a href="reports.php" class="sidebar__nav-link">
                        <svg class="sidebar__nav-icon" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <rect x="2.5" y="2.5" width="15" height="15" rx="2" stroke="white" stroke-width="1.5"/>
                            <path d="M6 14V10M10 14V7M14 14V11" stroke="white" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                        Reports
                        <?php if ($unread_reports > 0): ?>
                        <span class="sidebar__nav-badge"><?= (int) $unread_reports ?></span>
                        <?php endif; ?>
                    </a>
                </li>

            <!-- Stat cards -->
            <div class="stats" role="list" aria-label="Statistics overview">

                <div class="stat-card" role="listitem">
                    <div class="stat-card__top">
                        <div class="stat-card__icon-wrap stat-card__icon-wrap--blue" aria-hidden="true">
                            <!-- Clock icon -->
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="12" cy="12" r="9" stroke="white" stroke-width="2"/>
                                <path d="M12 7V12L15 15" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <span class="stat-card__badge stat-card__badge--green">This Week</span>
                    </div>
                    <div class="stat-card__value"><?= htmlspecialchars($total_hours_this_week) ?></div>
                    <div class="stat-card__label">Total Hours (this week)</div>
                </div>

                <div class="stat-card" role="listitem">
                    <div class="stat-card__top">
                        <div class="stat-card__icon-wrap stat-card__icon-wrap--yellow" aria-hidden="true">
                            <!-- Calendar icon -->
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="3" y="4" width="18" height="17" rx="2" stroke="white" stroke-width="2"/>
                                <path d="M3 9H21" stroke="white" stroke-width="2"/>
                                <path d="M8 2V5" stroke="white" stroke-width="2" stroke-linecap="round"/>
                                <path d="M16 2V5" stroke="white" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <span class="stat-card__badge stat-card__badge--blue">Scheduled</span>
                    </div>
                    <div class="stat-card__value"><?= (int)$upcoming_duties_count ?></div>
                    <div class="stat-card__label">Upcoming Duties</div>
                </div>

                <div class="stat-card" role="listitem">
                    <div class="stat-card__top">
                        <div class="stat-card__icon-wrap stat-card__icon-wrap--green" aria-hidden="true">
                            <!-- Checkmark icon -->
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="12" cy="12" r="9" stroke="white" stroke-width="2"/>
                                <path d="M8 12L11 15L16 9" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <span class="stat-card__badge stat-card__badge--green">This Month</span>
                    </div>
                    <div class="stat-card__value"><?= (int)$duties_completed_this_month ?></div>
                    <div class="stat-card__label">Duties Completed</div>
                </div>

                <div class="stat-card" role="listitem">
                    <div class="stat-card__top">
                        <div class="stat-card__icon-wrap stat-card__icon-wrap--purple" aria-hidden="true">
                            <!-- Trend icon -->
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M22 7L13.5 15.5L8.5 10.5L2 17" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M16 7H22V13" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <span class="stat-card__badge stat-card__badge--purple">Excellent</span>
                    </div>
                    <div class="stat-card__value"><?= (int)$attendance_rate_percent ?>%</div>
                    <div class="stat-card__label">Attendance Rate</div>
                </div>

            </div>
            <!-- /stats -->

            <!-- Two-column lower grid -->
            <div class="dashboard__grid">

                <!-- LEFT COLUMN -->
                <div class="dashboard__left">

                    <!-- Today's Schedule -->
                    <section class="card" aria-labelledby="schedule-heading">
                        <div class="card__header">
                            <div class="card__title-wrap">
                                <!-- Calendar-days icon -->
                                <svg class="card__title-icon" width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                    <rect x="3.5" y="5" width="21" height="20" rx="3" stroke="#003087" stroke-width="2"/>
                                    <path d="M3.5 11H24.5" stroke="#003087" stroke-width="2"/>
                                    <path d="M9.5 3V7" stroke="#003087" stroke-width="2" stroke-linecap="round"/>
                                    <path d="M18.5 3V7" stroke="#003087" stroke-width="2" stroke-linecap="round"/>
                                </svg>
                                <h2 class="card__title" id="schedule-heading">Today's Schedule</h2>
                            </div>
                            <a href="schedule.php" class="card__link">View Full Schedule →</a>
                        </div>

                        <?php if ($has_today_schedule): ?>
                            <div style="padding:12px 0 4px;">
                                <p class="schedule-empty__title" style="margin-bottom:8px;">
                                    <?= htmlspecialchars($today_label_for_card) ?>
                                </p>
                                <p class="schedule-empty__sub" style="margin-bottom:12px;">
                                    <?= htmlspecialchars($today_schedule_line) ?>
                                </p>
                                <p class="schedule-empty__sub" style="font-size:14px;">
                                    Your working hours are automatically computed based on the
                                    class schedule you submitted during your application.
                                </p>
                            </div>
                        <?php else: ?>
                            <!-- Empty state when no schedule has been set yet -->
                            <div class="schedule-empty">
                                <div class="schedule-empty__icon-wrap" aria-hidden="true">
                                    <!-- Calendar-x icon -->
                                    <svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <rect x="6" y="9" width="36" height="33" rx="4" stroke="#9ca3af" stroke-width="2.5"/>
                                        <path d="M6 18H42" stroke="#9ca3af" stroke-width="2.5"/>
                                        <path d="M16 5V11" stroke="#9ca3af" stroke-width="2.5" stroke-linecap="round"/>
                                        <path d="M32 5V11" stroke="#9ca3af" stroke-width="2.5" stroke-linecap="round"/>
                                        <path d="M18 28L30 36M30 28L18 36" stroke="#9ca3af" stroke-width="2.5" stroke-linecap="round"/>
                                    </svg>
                                </div>
                                <p class="schedule-empty__title">No schedule found yet</p>
                                <p class="schedule-empty__sub">Once your application is submitted with your class schedule, your working hours will appear here.</p>
                                <a href="schedule.php" class="schedule-empty__btn">View Schedule</a>
                            </div>
                        <?php endif; ?>
                    </section>

                    <!-- Quick Actions -->
                    <section class="card" aria-labelledby="qa-heading">
                        <h2 class="card__title" id="qa-heading" style="margin-bottom:24px;">Quick Actions</h2>
                        <div class="quick-actions">
                            <a href="checkin.php" class="qa-btn qa-btn--green" aria-label="Check In or Out">
                                <div class="qa-btn__icon-wrap qa-btn__icon-wrap--green">
                                    <!-- QR/scan icon -->
                                    <svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                        <rect x="3" y="3" width="9" height="9" rx="1.5" stroke="white" stroke-width="2"/>
                                        <rect x="16" y="3" width="9" height="9" rx="1.5" stroke="white" stroke-width="2"/>
                                        <rect x="3" y="16" width="9" height="9" rx="1.5" stroke="white" stroke-width="2"/>
                                        <rect x="16" y="16" width="3" height="3" fill="white"/>
                                        <rect x="22" y="16" width="3" height="3" fill="white"/>
                                        <rect x="16" y="22" width="3" height="3" fill="white"/>
                                        <rect x="22" y="22" width="3" height="3" fill="white"/>
                                        <rect x="6" y="6" width="3" height="3" fill="white"/>
                                        <rect x="19" y="6" width="3" height="3" fill="white"/>
                                        <rect x="6" y="19" width="3" height="3" fill="white"/>
                                    </svg>
                                </div>
                                <span class="qa-btn__label">Check In/Out</span>
                            </a>
                            <a href="schedule.php" class="qa-btn qa-btn--blue" aria-label="View Schedule">
                                <div class="qa-btn__icon-wrap qa-btn__icon-wrap--blue">
                                    <svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                        <rect x="3.5" y="4" width="21" height="20" rx="3" stroke="white" stroke-width="2"/>
                                        <path d="M3.5 10H24.5" stroke="white" stroke-width="2"/>
                                        <path d="M9 2V6" stroke="white" stroke-width="2" stroke-linecap="round"/>
                                        <path d="M19 2V6" stroke="white" stroke-width="2" stroke-linecap="round"/>
                                        <path d="M8 16H20" stroke="white" stroke-width="2" stroke-linecap="round"/>
                                        <path d="M8 20H14" stroke="white" stroke-width="2" stroke-linecap="round"/>
                                    </svg>
                                </div>
                                <span class="qa-btn__label">View Schedule</span>
                            </a>
                            <a href="profile.php" class="qa-btn qa-btn--purple" aria-label="My Profile">
                                <div class="qa-btn__icon-wrap qa-btn__icon-wrap--purple">
                                    <span class="qa-btn__icon-wrap--emoji" aria-hidden="true">👤</span>
                                </div>
                                <span class="qa-btn__label">My Profile</span>
                            </a>
                        </div>
                    </section>

                    <!-- Upcoming Duties -->
                    <section class="card" aria-labelledby="duties-heading">
                        <h2 class="card__title" id="duties-heading" style="margin-bottom:24px;">Upcoming Duties</h2>
                        <div class="duties-list">
                            <p class="schedule-empty__sub" style="padding:12px 0;">No upcoming duties yet.</p>
                        </div>
                    </section>

                </div>
                <!-- /LEFT COLUMN -->

                <!-- RIGHT COLUMN -->
                <div class="dashboard__right">

                    <!-- Announcements -->
                    <section class="card" aria-labelledby="annc-heading">
                        <div class="annc-header">
                            <!-- Star/sparkle icon -->
                            <svg class="annc-header__icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <path d="M12 2L13.5 8.5L20 7L15.5 12L20 17L13.5 15.5L12 22L10.5 15.5L4 17L8.5 12L4 7L10.5 8.5L12 2Z" stroke="#ffb81c" stroke-width="1.5" stroke-linejoin="round" fill="#ffb81c"/>
                            </svg>
                            <h2 class="annc-header__title" id="annc-heading">Announcements</h2>
                        </div>

                        <div class="annc-list">
                            <p class="schedule-empty__sub">No announcements yet. When SDAO posts updates, they will appear here.</p>
                        </div>
                    </section>

                    <!-- Tips & Reminders -->
                    <section class="tips-card" aria-labelledby="tips-heading">
                        <div class="tips-card__header">
                            <span class="tips-card__emoji" aria-hidden="true">💡</span>
                            <h2 class="tips-card__title" id="tips-heading">Tips &amp; Reminders</h2>
                        </div>
                        <ul class="tips-list">
                            <li class="tips-list__item">
                                <span class="tips-list__check" aria-hidden="true">✓</span>
                                <span class="tips-list__text">Always arrive 5-10 minutes before your scheduled time</span>
                            </li>
                            <li class="tips-list__item">
                                <span class="tips-list__check" aria-hidden="true">✓</span>
                                <span class="tips-list__text">Remember to scan QR code when checking in/out</span>
                            </li>
                            <li class="tips-list__item">
                                <span class="tips-list__check" aria-hidden="true">✓</span>
                                <span class="tips-list__text">Check your schedule regularly for updates</span>
                            </li>
                            <li class="tips-list__item">
                                <span class="tips-list__check" aria-hidden="true">✓</span>
                                <span class="tips-list__text">Notify Miss Zai if you need to reschedule</span>
                            </li>
                        </ul>
                    </section>

                    <!-- Your Performance -->
                    <section class="card" aria-labelledby="perf-heading">
                        <h2 class="card__title card__title--lg" id="perf-heading" style="margin-bottom:16px;">Your Performance</h2>
                        <p class="schedule-empty__sub">No performance data yet. Once you start logging duties and attendance, your stats will appear here.</p>
                    </section>

                </div>
                <!-- /RIGHT COLUMN -->

            </div>
            <!-- /dashboard__grid -->

        </main>

    </div>
    <!-- /main -->

</div>
<!-- /shell -->

<script>
(function () {
    'use strict';

    var sidebar  = document.getElementById('sidebar');
    var overlay  = document.getElementById('sidebar-overlay');
    var hamburger = document.getElementById('hamburger-btn');

    function openSidebar() {
        sidebar.classList.add('sidebar--open');
        overlay.classList.add('sidebar-overlay--visible');
        hamburger.setAttribute('aria-expanded', 'true');
        overlay.setAttribute('aria-hidden', 'false');
    }

    function closeSidebar() {
        sidebar.classList.remove('sidebar--open');
        overlay.classList.remove('sidebar-overlay--visible');
        hamburger.setAttribute('aria-expanded', 'false');
        overlay.setAttribute('aria-hidden', 'true');
    }

    if (hamburger) {
        hamburger.addEventListener('click', function () {
            if (sidebar.classList.contains('sidebar--open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    }

    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }

    /* Close sidebar on Escape key */
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && sidebar.classList.contains('sidebar--open')) {
            closeSidebar();
        }
    });

    /* Resize: close sidebar if viewport returns to desktop */
    window.addEventListener('resize', function () {
        if (window.innerWidth > 1024) {
            closeSidebar();
        }
    });

})();
</script>

</body>
</html>