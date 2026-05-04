<?php
// attendance.php — NU SA System | Admin Panel — Attendance Monitoring
// National University - Student Development and Activities Office

session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../db.php';

$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$admin_role = $_SESSION['admin_role'] ?? 'SDAO Head';

$has_checkout_column = false;
if ($colRes = $mysqli->query("SHOW COLUMNS FROM attendance LIKE 'check_out_time'")) {
    $has_checkout_column = $colRes->num_rows > 0;
    $colRes->free();
}

if (!$has_checkout_column) {
    $mysqli->query("ALTER TABLE attendance ADD COLUMN check_out_time DATETIME NULL DEFAULT NULL AFTER check_in_time");
    if ($colRes = $mysqli->query("SHOW COLUMNS FROM attendance LIKE 'check_out_time'")) {
        $has_checkout_column = $colRes->num_rows > 0;
        $colRes->free();
    }
}

// Add validation_status column if it doesn't exist
$has_validation_column = false;
if ($colRes = $mysqli->query("SHOW COLUMNS FROM attendance LIKE 'validation_status'")) {
    $has_validation_column = $colRes->num_rows > 0;
    $colRes->free();
}

if (!$has_validation_column) {
    $mysqli->query("ALTER TABLE attendance ADD COLUMN validation_status ENUM('pending', 'accepted', 'rejected') NOT NULL DEFAULT 'pending' AFTER check_out_time");
}

// Handle validation POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['attendance_id'])) {
    if ($_POST['action'] === 'validate_attendance') {
        $attId = (int)$_POST['attendance_id'];
        $newStatus = in_array($_POST['val_status'], ['accepted', 'rejected']) ? $_POST['val_status'] : 'pending';
        
        if ($stmt = $mysqli->prepare("UPDATE attendance SET validation_status = ? WHERE id = ?")) {
            $stmt->bind_param('si', $newStatus, $attId);
            $stmt->execute();
            $stmt->close();
        }
        
        // Redirect to avoid form resubmission
        header('Location: attendance.php?period=' . urlencode($_GET['period'] ?? 'today'));
        exit;
    }
}


$allowedPeriods = ['today', 'yesterday', 'week', 'month'];
$selected_period = strtolower(trim((string) ($_GET['period'] ?? 'today')));
if (!in_array($selected_period, $allowedPeriods, true)) {
    $selected_period = 'today';
}

$whereSql = 'DATE(check_in_time) = CURDATE()';
$periodLabel = date('l, F j, Y');
switch ($selected_period) {
    case 'yesterday':
        $whereSql = 'DATE(check_in_time) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)';
        $periodLabel = date('l, F j, Y', strtotime('-1 day'));
        break;
    case 'week':
        $whereSql = 'YEARWEEK(check_in_time, 1) = YEARWEEK(CURDATE(), 1)';
        $monday = date('F j, Y', strtotime('monday this week'));
        $sunday = date('F j, Y', strtotime('sunday this week'));
        $periodLabel = 'Week of ' . $monday . ' - ' . $sunday;
        break;
    case 'month':
        $whereSql = 'YEAR(check_in_time) = YEAR(CURDATE()) AND MONTH(check_in_time) = MONTH(CURDATE())';
        $periodLabel = date('F Y');
        break;
}

$attendance_rows = [];
$attendanceSql = "SELECT id, student_id, student_name, location, status, check_in_time, check_out_time, validation_status
                  FROM attendance
                  WHERE {$whereSql}
                  ORDER BY check_in_time DESC
                  LIMIT 300";

if ($result = $mysqli->query($attendanceSql)) {
    while ($row = $result->fetch_assoc()) {
        $statusRaw = strtolower(trim((string) ($row['status'] ?? '')));
        $isOpenLog = empty($row['check_out_time']);
        $isPresent = $statusRaw === 'present';
        $statusLabel = $isOpenLog ? 'In Progress' : 'Completed';
        $statusClass = $isOpenLog ? 'att-status--active' : 'att-status--present';

        $timeInValue = !empty($row['check_in_time']) ? (string) $row['check_in_time'] : null;
        $timeOutValue = !empty($row['check_out_time']) ? (string) $row['check_out_time'] : null;
        $durationValue = '-';
        if ($timeInValue !== null && $timeOutValue !== null) {
            $startTs = strtotime($timeInValue);
            $endTs = strtotime($timeOutValue);
            if ($startTs !== false && $endTs !== false && $endTs >= $startTs) {
                $diffMinutes = (int) floor(($endTs - $startTs) / 60);
                $hours = intdiv($diffMinutes, 60);
                $minutes = $diffMinutes % 60;
                $durationValue = sprintf('%dh %02dm', $hours, $minutes);
            }
        }

        $attendance_rows[] = [
            'id' => (int)($row['id'] ?? 0),
            'dot' => $isOpenLog ? 'orange' : 'green',
            'name' => (string) ($row['student_name'] ?? 'Unknown Student'),
            'office' => (string) ($row['location'] ?? 'Unassigned'),
            'time_in' => !empty($row['check_in_time']) ? date('g:i A', strtotime((string) $row['check_in_time'])) : '-',
            'time_out' => !empty($row['check_out_time']) ? date('g:i A', strtotime((string) $row['check_out_time'])) : '-',
            'duration' => $durationValue,
            'method' => 'System',
            'status' => $statusLabel,
            'status_class' => $statusClass,
            'is_open' => $isOpenLog,
            'validation_status' => (string)($row['validation_status'] ?? 'pending'),
        ];
    }
    $result->free();
}

$today_total = 0;
$today_present = 0;
if ($result = $mysqli->query("SELECT COUNT(*) AS total, SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS present_count FROM attendance WHERE DATE(check_in_time) = CURDATE()")) {
    if ($row = $result->fetch_assoc()) {
        $today_total = (int) ($row['total'] ?? 0);
        $today_present = (int) ($row['present_count'] ?? 0);
    }
    $result->free();
}

$on_time_pct = $today_total > 0 ? (int) round(($today_present / $today_total) * 100) : 0;

$month_logs = 0;
if ($result = $mysqli->query("SELECT COUNT(*) AS c FROM attendance WHERE YEAR(check_in_time) = YEAR(CURDATE()) AND MONTH(check_in_time) = MONTH(CURDATE())")) {
    if ($row = $result->fetch_assoc()) {
        $month_logs = (int) ($row['c'] ?? 0);
    }
    $result->free();
}

$daysElapsed = max(1, (int) date('j'));
$avg_logs_per_day = $month_logs > 0 ? round($month_logs / $daysElapsed, 1) : 0;

$active_now = 0;
if ($result = $mysqli->query("SELECT COUNT(*) AS c FROM attendance WHERE DATE(check_in_time) = CURDATE() AND check_out_time IS NULL AND check_in_time >= DATE_SUB(NOW(), INTERVAL 4 HOUR)")) {
    if ($row = $result->fetch_assoc()) {
        $active_now = (int) ($row['c'] ?? 0);
    }
    $result->free();
}

$verified_logs = 0;
if ($result = $mysqli->query("SELECT COUNT(*) AS c FROM attendance WHERE check_out_time IS NOT NULL")) {
    if ($row = $result->fetch_assoc()) {
        $verified_logs = (int) ($row['c'] ?? 0);
    }
    $result->free();
}

$total_logs = 0;
if ($result = $mysqli->query("SELECT COUNT(*) AS c FROM attendance")) {
    if ($row = $result->fetch_assoc()) {
        $total_logs = (int) ($row['c'] ?? 0);
    }
    $result->free();
}

$accuracy_pct = $total_logs > 0 ? (int) round(($verified_logs / $total_logs) * 100) : 0;

$offices = [];
$officeSql = "SELECT COALESCE(NULLIF(location, ''), 'Unassigned') AS office_name, COUNT(*) AS c
              FROM attendance
              WHERE {$whereSql}
              GROUP BY office_name
              ORDER BY c DESC
              LIMIT 5";

$palette = ['blue', 'green', 'purple', 'orange', 'grey'];
$maxOfficeCount = 0;
if ($result = $mysqli->query($officeSql)) {
    while ($row = $result->fetch_assoc()) {
        $count = (int) ($row['c'] ?? 0);
        $maxOfficeCount = max($maxOfficeCount, $count);
        $offices[] = [
            'name' => (string) ($row['office_name'] ?? 'Unassigned'),
            'count' => $count,
            'pct' => 0,
            'color' => 'grey',
        ];
    }
    $result->free();
}

if ($maxOfficeCount > 0) {
    foreach ($offices as $idx => $office) {
        $offices[$idx]['pct'] = (int) round(($office['count'] / $maxOfficeCount) * 100);
        $offices[$idx]['color'] = $palette[$idx % count($palette)];
    }
}

if (empty($offices)) {
    $offices[] = ['name' => 'No data', 'count' => 0, 'pct' => 0, 'color' => 'grey'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <script src="../assets/realtime.js"></script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Monitoring | NU SA System</title>
    <style>
        /* ============================================================
           CSS VARIABLES — Design System
        ============================================================ */
        :root {
            --clr-white:          #FFFFFF;
            --clr-bg:             #F9FAFB;
            --clr-border:         #E5E7EB;
            --clr-border-input:   #D1D5DC;
            --clr-border-track:   #E5E7EB;

            --clr-text-primary:   #101828;
            --clr-text-body:      #364153;
            --clr-text-muted:     #4A5565;
            --clr-text-placeholder: rgba(10,10,10,0.5);
            --clr-text-dark:      #0A0A0A;

            /* Brand / Blue */
            --clr-blue:           #155DFC;
            --clr-blue-dark:      #1447E6;
            --clr-blue-bg:        #DBEAFE;

            /* Green */
            --clr-green:          #008236;
            --clr-green-bright:   #00A63E;
            --clr-green-vivid:    #00C950;
            --clr-green-bg:       #DCFCE7;
            --clr-green-light:    #DCFCE7;

            /* Purple / Orange / Grey */
            --clr-purple:         #9810FA;
            --clr-purple-bg:      #F3E8FF;
            --clr-orange:         #F54900;
            --clr-orange-bg:      #FFEDD4;
            --clr-grey-dot:       #99A1AF;
            --clr-grey-bg:        #F3F4F6;
            --clr-blue-dot:       #2B7FFF;

            /* Gradients */
            --grad-brand:         linear-gradient(135deg, #155DFC 0%, #9810FA 100%);
            --grad-green-panel:   linear-gradient(155.38deg, #00A63E 0%, #008236 100%);

            /* Status badge colors */
            --clr-status-completed-bg:   #DCFCE7;
            --clr-status-completed-text: #008236;
            --clr-status-active-bg:      #DBEAFE;
            --clr-status-active-text:    #1447E6;
            --clr-status-scheduled-bg:   #F3F4F6;
            --clr-status-scheduled-text: #364153;

            /* Sidebar */
            --sidebar-width:      256px;

            /* Typography */
            --fs-xs:   12px;
            --fs-sm:   14px;
            --fs-base: 16px;
            --fs-md:   18px;
            --fs-lg:   24px;

            /* Spacing */
            --sp-4:   4px;
            --sp-8:   8px;
            --sp-12:  12px;
            --sp-16:  16px;
            --sp-24:  24px;
            --sp-32:  32px;

            /* Radius */
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
            font-size: 18px;
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

        /* ============================================================
           MAIN
        ============================================================ */
        .main {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
        }

        /* ============================================================
           TOP BAR
        ============================================================ */
        .topbar {
            background: var(--clr-white);
            border-bottom: 1px solid var(--clr-border);
            height: 89px;
            padding: 0 var(--sp-32);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }

        .topbar__left { display: flex; align-items: center; gap: var(--sp-12); }

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
            background: #FB2C36;
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
            overflow-x: hidden;
        }

        /* ============================================================
           CONTROLS ROW (Search + Filter + Export)
        ============================================================ */
        .controls-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: var(--sp-12);
            flex-wrap: wrap;
            min-height: 42px;
        }

        .controls-row__left {
            display: flex;
            align-items: center;
            gap: var(--sp-16);
            flex-wrap: wrap;
        }

        /* Search Input */
        .search-wrap {
            position: relative;
            width: 320px;
        }

        .search-wrap__icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            pointer-events: none;
        }
        .search-wrap__icon svg { width: 100%; height: 100%; }

        .search-wrap__input {
            width: 100%;
            height: 42px;
            border: 1px solid var(--clr-border-input);
            border-radius: var(--radius-sm);
            padding: 8px 16px 8px 40px;
            font-family: Arial, sans-serif;
            font-size: var(--fs-base);
            color: var(--clr-text-primary);
            background: var(--clr-white);
            outline: none;
            transition: border-color 0.15s;
        }
        .search-wrap__input::placeholder { color: var(--clr-text-placeholder); }
        .search-wrap__input:focus { border-color: var(--clr-blue); }

        /* Filter Dropdown */
        .filter-select {
            height: 42px;
            width: 134px;
            border: 1px solid var(--clr-border-input);
            border-radius: var(--radius-sm);
            padding: 0 var(--sp-12);
            font-family: Arial, sans-serif;
            font-size: var(--fs-base);
            color: var(--clr-text-dark);
            background: var(--clr-white);
            outline: none;
            cursor: pointer;
            transition: border-color 0.15s;
        }
        .filter-select:focus { border-color: var(--clr-blue); }

        /* Export Button */
        .btn-export {
            height: 40px;
            padding: 0 var(--sp-16);
            background: var(--clr-blue);
            color: var(--clr-white);
            border-radius: var(--radius-sm);
            font-size: var(--fs-base);
            line-height: 24px;
            display: flex;
            align-items: center;
            gap: var(--sp-8);
            white-space: nowrap;
            transition: opacity 0.15s;
        }
        .btn-export:hover { opacity: .88; }
        .btn-export svg { width: 16px; height: 16px; }

        /* ============================================================
           STAT CARDS
        ============================================================ */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: var(--sp-16);
        }

        .stat-card {
            background: var(--clr-white);
            border: 1px solid var(--clr-border);
            border-radius: var(--radius-md);
            padding: 17px;
            display: flex;
            flex-direction: column;
            gap: var(--sp-12);
            min-height: 142px;
        }

        .stat-card__top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
        }

        .stat-card__icon-wrap {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .stat-card__icon-wrap svg { width: 20px; height: 20px; }

        .stat-card__icon-wrap--green  { background: var(--clr-green-light); }
        .stat-card__icon-wrap--blue   { background: var(--clr-blue-bg); }
        .stat-card__icon-wrap--purple { background: var(--clr-purple-bg); }
        .stat-card__icon-wrap--orange { background: var(--clr-orange-bg); }

        .stat-card__pct {
            font-size: var(--fs-sm);
            color: var(--clr-green-bright);
            line-height: 20px;
        }

        .stat-card__value {
            font-size: var(--fs-lg);
            font-weight: bold;
            color: var(--clr-text-primary);
            line-height: 32px;
        }

        .stat-card__label {
            font-size: var(--fs-sm);
            color: var(--clr-text-muted);
            line-height: 20px;
        }

        /* ============================================================
           ATTENDANCE TABLE CARD
        ============================================================ */
        .table-card {
            background: var(--clr-white);
            border: 1px solid var(--clr-border);
            border-radius: var(--radius-md);
            overflow: hidden;
        }

        .table-card__header {
            border-bottom: 1px solid var(--clr-border);
            padding: var(--sp-24);
            display: flex;
            flex-direction: column;
            gap: var(--sp-4);
            min-height: 101px;
        }

        .table-card__title {
            font-size: var(--fs-md);
            font-weight: bold;
            color: var(--clr-text-primary);
            line-height: 28px;
        }

        .table-card__date {
            font-size: var(--fs-sm);
            color: var(--clr-text-muted);
            line-height: 20px;
        }

        /* Table */
        .att-table-wrap { overflow-x: auto; }

        .att-table {
            width: 100%;
            min-width: 900px;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .att-table col.col-sa       { width: 17%; }
        .att-table col.col-office   { width: 14%; }
        .att-table col.col-timein   { width: 14%; }
        .att-table col.col-timeout  { width: 12%; }
        .att-table col.col-duration { width: 10%; }
        .att-table col.col-method   { width: 10%; }
        .att-table col.col-status   { width: 10%; }
        .att-table col.col-validation { width: 13%; }

        .att-table thead th {
            background: var(--clr-bg);
            border-bottom: 1px solid var(--clr-border);
            padding: 16px var(--sp-24);
            text-align: left;
            font-size: var(--fs-sm);
            font-weight: bold;
            color: var(--clr-text-primary);
            height: 52.5px;
        }

        .att-table tbody tr {
            border-bottom: 1px solid var(--clr-border);
        }
        .att-table tbody tr:last-child { border-bottom: none; }

        .att-table tbody td {
            padding: 0 var(--sp-24);
            height: 57px;
            vertical-align: middle;
            font-size: var(--fs-base);
            color: var(--clr-text-primary);
            line-height: 24px;
        }

        /* Name cell with dot */
        .att-name {
            display: flex;
            align-items: center;
            gap: var(--sp-12);
        }

        .att-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        .att-dot--green  { background: var(--clr-green-vivid); }
        .att-dot--blue   { background: var(--clr-blue-dot); }
        .att-dot--grey   { background: var(--clr-grey-dot); }
        .att-dot--orange { background: var(--clr-orange); }

        /* Duration bold */
        .att-duration { font-weight: bold; }

        /* Method badge */
        .att-method {
            display: inline-flex;
            align-items: center;
            height: 24px;
            padding: 4px 8px;
            background: var(--clr-grey-bg);
            border-radius: var(--radius-xs);
            font-size: var(--fs-xs);
            color: var(--clr-text-body);
            white-space: nowrap;
        }

        /* Status badge */
        .att-status {
            display: inline-flex;
            align-items: center;
            height: 24px;
            padding: 4px 12px;
            border-radius: var(--radius-pill);
            font-size: var(--fs-xs);
            white-space: nowrap;
        }
        .att-status--completed { background: var(--clr-status-completed-bg); color: var(--clr-status-completed-text); }
        .att-status--active    { background: var(--clr-status-active-bg);    color: var(--clr-status-active-text); }
        .att-status--scheduled { background: var(--clr-status-scheduled-bg); color: var(--clr-status-scheduled-text); }
        .att-status--present   { background: var(--clr-status-completed-bg); color: var(--clr-status-completed-text); }
        .att-status--absent    { background: #fef2f2; color: #b42318; }

        /* ============================================================
           BOTTOM PANELS ROW
        ============================================================ */
        .bottom-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--sp-24);
        }

        /* ---- Green Security Panel ---- */
        .security-panel {
            background: var(--grad-green-panel);
            border-radius: var(--radius-md);
            padding: var(--sp-24);
            display: flex;
            flex-direction: column;
            gap: var(--sp-16);
            min-height: 302px;
        }

        .security-panel__title {
            font-size: var(--fs-md);
            font-weight: bold;
            color: var(--clr-white);
            line-height: 28px;
        }

        .security-panel__desc {
            font-size: var(--fs-sm);
            color: var(--clr-green-light);
            line-height: 20px;
        }

        .security-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--sp-16);
        }

        .security-stat {
            background: rgba(255,255,255,.10);
            border-radius: var(--radius-sm);
            padding: var(--sp-12);
            display: flex;
            flex-direction: column;
            gap: 0;
        }

        .security-stat__value {
            font-size: var(--fs-lg);
            font-weight: bold;
            color: var(--clr-white);
            line-height: 32px;
        }

        .security-stat__label {
            font-size: var(--fs-sm);
            color: var(--clr-green-light);
            line-height: 20px;
        }

        /* ---- Office Distribution Panel ---- */
        .dist-panel {
            background: var(--clr-white);
            border: 1px solid var(--clr-border);
            border-radius: var(--radius-md);
            padding: 25px;
            display: flex;
            flex-direction: column;
            gap: var(--sp-16);
            min-height: 302px;
        }

        .dist-panel__title {
            font-size: var(--fs-md);
            font-weight: bold;
            color: var(--clr-text-primary);
            line-height: 28px;
        }

        .office-bars {
            display: flex;
            flex-direction: column;
            gap: var(--sp-12);
        }

        .office-bar { display: flex; flex-direction: column; gap: var(--sp-4); }

        .office-bar__header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 20px;
        }

        .office-bar__name {
            font-size: var(--fs-sm);
            color: var(--clr-text-primary);
            line-height: 20px;
        }

        .office-bar__count {
            font-size: var(--fs-sm);
            color: var(--clr-text-muted);
            line-height: 20px;
        }

        .office-bar__track {
            height: 8px;
            background: var(--clr-border-track);
            border-radius: var(--radius-pill);
            overflow: hidden;
        }

        .office-bar__fill {
            height: 100%;
            border-radius: var(--radius-pill);
        }

        .office-bar__fill--blue   { background: var(--clr-blue); }
        .office-bar__fill--green  { background: var(--clr-green-bright); }
        .office-bar__fill--purple { background: var(--clr-purple); }
        .office-bar__fill--orange { background: var(--clr-orange); }
        .office-bar__fill--grey   { background: var(--clr-text-muted); }

        /* ============================================================
           SIDEBAR OVERLAY
        ============================================================ */
        .sidebar-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.4);
            z-index: 99;
        }
        .sidebar-overlay--hidden  { display: none; }
        .sidebar-overlay--visible { display: block; }

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
            .stats-row { grid-template-columns: repeat(2, 1fr); }
            .bottom-row { grid-template-columns: 1fr; }
            .controls-row { flex-direction: column; align-items: flex-start; }
            .topbar__user-info { display: none; }
        }

        /* ============================================================
           RESPONSIVE — MOBILE (≤768px)
        ============================================================ */
        @media (max-width: 768px) {
            .topbar__title { font-size: 18px; }
            .topbar__subtitle { font-size: var(--fs-xs); }
            .page-content { padding: var(--sp-12); gap: var(--sp-16); }
            .stats-row { grid-template-columns: 1fr 1fr; gap: var(--sp-12); }
            .stat-card { min-height: auto; }
            .search-wrap { width: 100%; }
            .filter-select { width: 120px; }
            .btn-export { font-size: var(--fs-sm); height: 38px; }
            .security-stats { grid-template-columns: 1fr 1fr; }
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
                    <a href="attendance.php" class="nav__link nav__link--active" aria-current="page">
                        <span class="nav__icon" aria-hidden="true">
                            <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="10" cy="10" r="8" stroke="white" stroke-width="1.5"/>
                                <path d="M6.5 10.5l2.5 2.5 4.5-5" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
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
                    <h1 class="topbar__title">Attendance Monitoring</h1>
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
                        <span class="topbar__user-name"><?php echo htmlspecialchars($admin_name); ?></span>
                        <span class="topbar__user-role"><?php echo htmlspecialchars($admin_role); ?></span>
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
        <section class="page-content" aria-label="Attendance Monitoring content">
            <!-- Controls Row -->
            <div class="controls-row">
                <div class="controls-row__left">
                    <!-- Search -->
                    <div class="search-wrap">
                        <span class="search-wrap__icon" aria-hidden="true">
                            <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="9" cy="9" r="6" stroke="#99A1AF" stroke-width="1.5"/>
                                <path d="M13.5 13.5L17 17" stroke="#99A1AF" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                        </span>
                        <input
                            class="search-wrap__input"
                            type="search"
                            placeholder="Search student assistant..."
                            aria-label="Search student assistant"
                            id="searchInput"
                        >
                    </div>
                    <!-- Filter -->
                    <select class="filter-select" aria-label="Filter by period" id="filterSelect">
                        <option value="today" <?php echo $selected_period === 'today' ? 'selected' : ''; ?>>Today</option>
                        <option value="yesterday" <?php echo $selected_period === 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
                        <option value="week" <?php echo $selected_period === 'week' ? 'selected' : ''; ?>>This Week</option>
                        <option value="month" <?php echo $selected_period === 'month' ? 'selected' : ''; ?>>This Month</option>
                    </select>
                </div>
                <!-- Export -->
                <button class="btn-export" type="button">
                    <svg viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M8 2v8M8 10L5 7M8 10l3-3" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M2 11v2a1 1 0 001 1h10a1 1 0 001-1v-2" stroke="white" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    Export Report
                </button>
            </div>

            <!-- Stat Cards -->
            <div class="stats-row">
                <!-- On Time Today -->
                <div class="stat-card">
                    <div class="stat-card__top">
                        <div class="stat-card__icon-wrap stat-card__icon-wrap--green" aria-hidden="true">
                            <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="10" cy="10" r="8" stroke="#00A63E" stroke-width="1.5"/>
                                <path d="M10 6v4l2.5 2.5" stroke="#00A63E" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <span class="stat-card__pct"><?php echo (int) $on_time_pct; ?>%</span>
                    </div>
                    <div class="stat-card__value"><?php echo (int) $today_present; ?>/<?php echo (int) $today_total; ?></div>
                    <div class="stat-card__label">On Time Today</div>
                </div>
                <!-- Avg Hours/Day -->
                <div class="stat-card">
                    <div class="stat-card__top">
                        <div class="stat-card__icon-wrap stat-card__icon-wrap--blue" aria-hidden="true">
                            <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M3 17l4-8 4 5 3-3 3 2" stroke="#155DFC" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <span class="stat-card__pct">Monthly</span>
                    </div>
                    <div class="stat-card__value"><?php echo number_format($avg_logs_per_day, 1); ?></div>
                    <div class="stat-card__label">Avg. Logs/Day</div>
                </div>
                <!-- Active Now -->
                <div class="stat-card">
                    <div class="stat-card__top">
                        <div class="stat-card__icon-wrap stat-card__icon-wrap--purple" aria-hidden="true">
                            <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="10" cy="7" r="4" stroke="#9810FA" stroke-width="1.5"/>
                                <path d="M2 17c0-3.314 3.582-6 8-6s8 2.686 8 6" stroke="#9810FA" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <span class="stat-card__pct">Live</span>
                    </div>
                    <div class="stat-card__value"><?php echo (int) $active_now; ?></div>
                    <div class="stat-card__label">Active Now</div>
                </div>
                <!-- This Month -->
                <div class="stat-card">
                    <div class="stat-card__top">
                        <div class="stat-card__icon-wrap stat-card__icon-wrap--orange" aria-hidden="true">
                            <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="2" y="4" width="16" height="14" rx="2" stroke="#F54900" stroke-width="1.5"/>
                                <path d="M6 2v4M14 2v4" stroke="#F54900" stroke-width="1.5" stroke-linecap="round"/>
                                <path d="M2 9h16" stroke="#F54900" stroke-width="1.2"/>
                            </svg>
                        </div>
                        <span class="stat-card__pct"><?php echo date('M Y'); ?></span>
                    </div>
                    <div class="stat-card__value"><?php echo number_format($month_logs); ?></div>
                    <div class="stat-card__label">This Month Logs</div>
                </div>
            </div>

            <!-- Attendance Table -->
            <div class="table-card">
                <div class="table-card__header">
                    <h2 class="table-card__title">Attendance Log</h2>
                    <p class="table-card__date"><?php echo htmlspecialchars($periodLabel); ?></p>
                </div>
                <div class="att-table-wrap">
                    <table class="att-table" aria-label="Today's attendance log">
                        <colgroup>
                            <col class="col-sa">
                            <col class="col-office">
                            <col class="col-timein">
                            <col class="col-timeout">
                            <col class="col-duration">
                            <col class="col-method">
                            <col class="col-status">
                            <col class="col-validation">
                        </colgroup>
                        <thead>
                            <tr>
                                <th scope="col">Student Assistant</th>
                                <th scope="col">Office</th>
                                <th scope="col">Time In</th>
                                <th scope="col">Time Out</th>
                                <th scope="col">Duration</th>
                                <th scope="col">Method</th>
                                <th scope="col">Status</th>
                                <th scope="col">Validation</th>
                            </tr>
                        </thead>
                        <tbody id="attendanceTableBody">
                            <?php if (empty($attendance_rows)): ?>
                            <tr>
                                <td colspan="7">No attendance records found for this period.</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($attendance_rows as $row): ?>
                            <tr>
                                <td>
                                    <div class="att-name">
                                        <span class="att-dot att-dot--<?= htmlspecialchars($row['dot']) ?>" aria-hidden="true"></span>
                                        <?= htmlspecialchars($row['name']) ?>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($row['office']) ?></td>
                                <td><?= htmlspecialchars($row['time_in']) ?></td>
                                <td><?= htmlspecialchars($row['time_out']) ?></td>
                                <td><span class="att-duration"><?= htmlspecialchars($row['duration']) ?></span></td>
                                <td><span class="att-method"><?= htmlspecialchars($row['method']) ?></span></td>
                                <td>
                                    <span class="att-status <?= htmlspecialchars($row['status_class']) ?>"><?= htmlspecialchars($row['status']) ?></span>
                                </td>
                                <td>
                                    <?php if (!$row['is_open']): ?>
                                        <?php if ($row['validation_status'] === 'pending'): ?>
                                            <form method="POST" style="display:inline-flex;gap:4px;">
                                                <input type="hidden" name="action" value="validate_attendance">
                                                <input type="hidden" name="attendance_id" value="<?= $row['id'] ?>">
                                                <button type="submit" name="val_status" value="accepted" title="Accept" style="background:#00C950;color:#fff;border-radius:4px;padding:4px 8px;font-size:12px;">✓ Accept</button>
                                                <button type="submit" name="val_status" value="rejected" title="Reject" style="background:#FB2C36;color:#fff;border-radius:4px;padding:4px 8px;font-size:12px;">✗ Reject</button>
                                            </form>
                                        <?php elseif ($row['validation_status'] === 'accepted'): ?>
                                            <span style="color:#008236;font-size:12px;font-weight:bold;">Accepted</span>
                                        <?php elseif ($row['validation_status'] === 'rejected'): ?>
                                            <span style="color:#b42318;font-size:12px;font-weight:bold;">Rejected</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color:#99A1AF;font-size:12px;">Awaiting Checkout</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Bottom Row: Security Panel + Office Distribution -->
            <div class="bottom-row">

                <!-- Dual-Layer Security Panel -->
                <div class="security-panel" role="region" aria-label="Dual-Layer Security">
                    <h2 class="security-panel__title">🔒 Dual-Layer Security</h2>
                    <p class="security-panel__desc">Attendance integrity metrics based on system logs</p>
                    <div class="security-stats">
                        <div class="security-stat">
                            <span class="security-stat__value"><?php echo number_format($verified_logs); ?></span>
                            <span class="security-stat__label">Verified Logs</span>
                        </div>
                        <div class="security-stat">
                            <span class="security-stat__value"><?php echo (int) $accuracy_pct; ?>%</span>
                            <span class="security-stat__label">Accuracy</span>
                        </div>
                    </div>
                </div>

                <!-- Office Distribution Panel -->
                <div class="dist-panel" role="region" aria-label="Office Distribution">
                    <h2 class="dist-panel__title">Office Distribution</h2>
                    <div class="office-bars">
                        <?php foreach ($offices as $office): ?>
                        <div class="office-bar">
                            <div class="office-bar__header">
                                <span class="office-bar__name"><?= htmlspecialchars($office['name']) ?></span>
                                <span class="office-bar__count"><?= (int)$office['count'] ?> SAs</span>
                            </div>
                            <div class="office-bar__track" role="progressbar" aria-valuenow="<?= (int)$office['pct'] ?>" aria-valuemin="0" aria-valuemax="100" aria-label="<?= htmlspecialchars($office['name']) ?> distribution">
                                <div class="office-bar__fill office-bar__fill--<?= htmlspecialchars($office['color']) ?>" style="width:<?= (int)$office['pct'] ?>%"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
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

    /* ---- Live search filter ---- */
    var searchInput = document.getElementById('searchInput');
    var tableBody   = document.getElementById('attendanceTableBody');
    var filterSelect = document.getElementById('filterSelect');

    if (filterSelect) {
        filterSelect.addEventListener('change', function () {
            var url = new URL(window.location.href);
            url.searchParams.set('period', filterSelect.value || 'today');
            window.location.href = url.toString();
        });
    }

    if (searchInput && tableBody) {
        searchInput.addEventListener('input', function () {
            var q = this.value.trim().toLowerCase();
            var rows = tableBody.querySelectorAll('tr');
            rows.forEach(function (row) {
                var name = row.querySelector('.att-name');
                var text = name ? name.textContent.toLowerCase() : '';
                row.style.display = (!q || text.indexOf(q) !== -1) ? '' : 'none';
            });
        });
    }

})();
</script>

</body>
</html>