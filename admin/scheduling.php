<?php
// scheduling.php — NU SA System | Admin Panel — Schedule Management
// National University - Student Development and Activities Office

session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../gemini_ai.php';

$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$admin_role = $_SESSION['admin_role'] ?? 'SDAO Head';

function parse_time_label_to_hour(string $label): ?int {
    $normalized = preg_replace('/\s+/', ' ', trim($label));
    $ts = strtotime((string) $normalized);
    if ($ts === false) {
        return null;
    }
    return (int) date('G', $ts);
}

function parse_schedule_text(string $text): array {
    $daysMap = [
        'Monday' => [],
        'Tuesday' => [],
        'Wednesday' => [],
        'Thursday' => [],
        'Friday' => [],
        'Saturday' => [],
        'Sunday' => [],
    ];

    if (trim($text) === '') {
        return $daysMap;
    }

    $parts = explode('|', $text);
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '' || strpos($part, ':') === false) {
            continue;
        }

        list($dayName, $rangesText) = explode(':', $part, 2);
        $dayName = trim($dayName);
        if (!array_key_exists($dayName, $daysMap)) {
            continue;
        }

        $ranges = array_map('trim', explode(',', $rangesText));
        foreach ($ranges as $range) {
            if ($range === '') {
                continue;
            }
            $bounds = preg_split('/\s*(?:\x{2013}|\x{2014}|-)\s*/u', $range, 2);
            if (!$bounds || count($bounds) !== 2) {
                continue;
            }

            $startHour = parse_time_label_to_hour($bounds[0]);
            $endHour   = parse_time_label_to_hour($bounds[1]);
            if ($startHour === null || $endHour === null || $endHour <= $startHour) {
                continue;
            }

            $daysMap[$dayName][] = ['start' => $startHour, 'end' => $endHour];
        }
    }

    return $daysMap;
}

function merge_intervals(array $intervals): array {
    if (empty($intervals)) {
        return [];
    }

    usort($intervals, function ($a, $b) {
        return $a['start'] <=> $b['start'];
    });

    $merged = [$intervals[0]];
    for ($i = 1; $i < count($intervals); $i++) {
        $curr = $intervals[$i];
        $lastIndex = count($merged) - 1;

        if ($curr['start'] <= $merged[$lastIndex]['end']) {
            $merged[$lastIndex]['end'] = max($merged[$lastIndex]['end'], $curr['end']);
        } else {
            $merged[] = $curr;
        }
    }

    return $merged;
}

function subtract_interval(array $intervals, int $removeStart, int $removeEnd): array {
    $result = [];
    foreach ($intervals as $iv) {
        $s = (int) $iv['start'];
        $e = (int) $iv['end'];

        if ($removeEnd <= $s || $removeStart >= $e) {
            $result[] = ['start' => $s, 'end' => $e];
            continue;
        }

        if ($removeStart > $s) {
            $result[] = ['start' => $s, 'end' => $removeStart];
        }
        if ($removeEnd < $e) {
            $result[] = ['start' => $removeEnd, 'end' => $e];
        }
    }

    return merge_intervals($result);
}

function format_hour_label(int $hour): string {
    if ($hour === 0) return '12:00AM';
    if ($hour === 12) return '12:00PM';
    if ($hour > 12) return ($hour - 12) . ':00PM';
    return $hour . ':00AM';
}

function format_schedule_text(array $scheduleByDay): string {
    $dayOrder = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    $parts = [];

    foreach ($dayOrder as $dayName) {
        if (empty($scheduleByDay[$dayName])) {
            continue;
        }

        $ranges = [];
        foreach ($scheduleByDay[$dayName] as $iv) {
            $ranges[] = format_hour_label((int) $iv['start']) . '–' . format_hour_label((int) $iv['end']);
        }

        if (!empty($ranges)) {
            $parts[] = $dayName . ': ' . implode(', ', $ranges);
        }
    }

    return implode(' | ', $parts);
}

function ensure_student_class_blocks_table(mysqli $mysqli): void {
    static $checked = false;
    if ($checked) {
        return;
    }

    $sql = "CREATE TABLE IF NOT EXISTS student_class_blocks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(64) NOT NULL,
        day_key VARCHAR(3) NOT NULL,
        hour_start TINYINT UNSIGNED NOT NULL,
        hour_end TINYINT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_student_day_hour (student_id, day_key, hour_start),
        KEY idx_student_id (student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $mysqli->query($sql);
    $checked = true;
}

function load_student_class_hours(mysqli $mysqli, string $studentId): array {
    ensure_student_class_blocks_table($mysqli);

    $result = [
        'mon' => [],
        'tue' => [],
        'wed' => [],
        'thu' => [],
        'fri' => [],
        'sat' => [],
    ];

    $stmt = $mysqli->prepare('SELECT day_key, hour_start FROM student_class_blocks WHERE student_id = ? ORDER BY day_key, hour_start');
    if (!$stmt) {
        return $result;
    }

    $stmt->bind_param('s', $studentId);
    if (!$stmt->execute()) {
        $stmt->close();
        return $result;
    }

    $res = $stmt->get_result();
    while ($row = $res ? $res->fetch_assoc() : null) {
        $dayKey = (string) ($row['day_key'] ?? '');
        $hourStart = (int) ($row['hour_start'] ?? -1);
        if (isset($result[$dayKey]) && $hourStart >= 8 && $hourStart <= 16) {
            $result[$dayKey][] = $hourStart;
        }
    }
    $res && $res->free();
    $stmt->close();

    foreach ($result as $k => $hours) {
        $hours = array_values(array_unique($hours));
        sort($hours);
        $result[$k] = $hours;
    }

    return $result;
}

function build_rule_based_schedule_text(array $classHoursByDay): string {
    $days = [
        'mon' => 'Monday',
        'tue' => 'Tuesday',
        'wed' => 'Wednesday',
        'thu' => 'Thursday',
        'fri' => 'Friday',
        'sat' => 'Saturday',
    ];

    $slotHours = range(8, 16);
    $lunchHour = 12;
    $parts = [];

    foreach ($days as $key => $dayLabel) {
        $classHours = $classHoursByDay[$key] ?? [];
        $classHours = array_values(array_unique(array_map('intval', $classHours)));
        sort($classHours);

        $freeHours = array_values(array_diff($slotHours, $classHours, [$lunchHour]));
        sort($freeHours);

        if (empty($freeHours)) {
            continue;
        }

        $segments = [];
        $start = null;
        $prev = null;
        foreach ($freeHours as $hour) {
            if ($start === null) {
                $start = $hour;
                $prev = $hour;
                continue;
            }

            if ($hour === $prev + 1) {
                $prev = $hour;
                continue;
            }

            $segments[] = ['start' => $start, 'end' => $prev + 1];
            $start = $hour;
            $prev = $hour;
        }
        if ($start !== null) {
            $segments[] = ['start' => $start, 'end' => $prev + 1];
        }

        $segmentLabels = [];
        foreach ($segments as $seg) {
            // Minimum duty block: 2 hours.
            if (((int) $seg['end'] - (int) $seg['start']) < 2) {
                continue;
            }
            $segmentLabels[] = format_hour_label((int) $seg['start']) . '–' . format_hour_label((int) $seg['end']);
        }

        if (!empty($segmentLabels)) {
            $parts[] = $dayLabel . ': ' . implode(', ', $segmentLabels);
        }
    }

    return implode(' | ', $parts);
}

function fetch_latest_students(mysqli $mysqli): array {
    $students = [];
    $sql = "SELECT full_name, student_id, course, year_level, skills, work_location, work_schedule, class_schedule_path, created_at
            FROM student_applications
            ORDER BY full_name ASC, created_at DESC";

    if ($res = $mysqli->query($sql)) {
        $seenStudentIds = [];
        while ($row = $res->fetch_assoc()) {
            $sid = (string) ($row['student_id'] ?? '');
            if ($sid === '' || isset($seenStudentIds[$sid])) {
                continue;
            }
            $seenStudentIds[$sid] = true;
            $students[] = $row;
        }
        $res->free();
    }

    return $students;
}

function calculate_schedule_totals(array $students): array {
    $active_sas_count = count($students);
    $total_shifts = 0;
    $total_hours = 0;

    foreach ($students as $row) {
        $parsed = parse_schedule_text((string) ($row['work_schedule'] ?? ''));
        foreach ($parsed as $ivs) {
            foreach ($ivs as $iv) {
                $total_shifts++;
                $total_hours += ((int) $iv['end'] - (int) $iv['start']);
            }
        }
    }

    return [
        'active_sas_count' => $active_sas_count,
        'total_shifts' => $total_shifts,
        'total_hours' => $total_hours,
    ];
}

// Load the latest application row per student (same source used by student pages)
$students = fetch_latest_students($mysqli);

if (isset($_GET['ajax']) && $_GET['ajax'] === 'stats') {
    $totals = calculate_schedule_totals($students);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'active_sas_count' => (int) $totals['active_sas_count'],
        'total_shifts' => (int) $totals['total_shifts'],
        'total_hours' => (int) $totals['total_hours'],
        'generated_at' => date('c'),
    ]);
    exit;
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'rules') {
    $target_student_id = trim((string) ($_GET['student_id'] ?? ''));
    header('Content-Type: application/json; charset=utf-8');

    if ($target_student_id === '') {
        echo json_encode(['ok' => false, 'error' => 'Please select a student first.']);
        exit;
    }

    $sel = $mysqli->prepare('SELECT full_name, student_id FROM student_applications WHERE student_id = ? ORDER BY created_at DESC LIMIT 1');
    if (!$sel) {
        echo json_encode(['ok' => false, 'error' => 'Database error while loading the student.']);
        exit;
    }

    $sel->bind_param('s', $target_student_id);
    if (!$sel->execute()) {
        $sel->close();
        echo json_encode(['ok' => false, 'error' => 'Failed to load the selected student.']);
        exit;
    }

    $res = $sel->get_result();
    $studentRow = $res ? $res->fetch_assoc() : null;
    $res && $res->free();
    $sel->close();

    if (!$studentRow) {
        echo json_encode(['ok' => false, 'error' => 'Student not found.']);
        exit;
    }

    $classHoursByDay = load_student_class_hours($mysqli, $target_student_id);
    $hasClassData = false;
    foreach ($classHoursByDay as $hours) {
        if (!empty($hours)) {
            $hasClassData = true;
            break;
        }
    }

    if (!$hasClassData) {
        echo json_encode([
            'ok' => false,
            'error' => 'No structured class blocks found for this student yet. Please update/re-submit class schedule blocks first.',
        ]);
        exit;
    }

    $recommendedSchedule = build_rule_based_schedule_text($classHoursByDay);
    if ($recommendedSchedule === '') {
        echo json_encode([
            'ok' => false,
            'error' => 'No valid duty window generated (minimum 2-hour block, lunch 12PM-1PM excluded).',
        ]);
        exit;
    }

    $upd = $mysqli->prepare('UPDATE student_applications SET work_schedule = ? WHERE student_id = ? ORDER BY created_at DESC LIMIT 1');
    if (!$upd) {
        echo json_encode(['ok' => false, 'error' => 'Database error while saving the rule-based schedule.']);
        exit;
    }

    $upd->bind_param('ss', $recommendedSchedule, $target_student_id);
    if (!$upd->execute()) {
        $upd->close();
        echo json_encode(['ok' => false, 'error' => 'Failed to save the rule-based schedule.']);
        exit;
    }
    $upd->close();

    echo json_encode([
        'ok' => true,
        'student_id' => $target_student_id,
        'full_name' => (string) ($studentRow['full_name'] ?? ''),
        'recommended_work_schedule' => $recommendedSchedule,
        'source' => 'rules',
    ]);
    exit;
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'gemini') {
    $target_student_id = trim((string) ($_GET['student_id'] ?? ''));
    header('Content-Type: application/json; charset=utf-8');

    if ($target_student_id === '') {
        echo json_encode(['ok' => false, 'error' => 'Please select a student first.']);
        exit;
    }

    $sel = $mysqli->prepare('SELECT full_name, student_id, course, year_level, skills, work_location, class_schedule_path, work_schedule FROM student_applications WHERE student_id = ? ORDER BY created_at DESC LIMIT 1');
    if (!$sel) {
        echo json_encode(['ok' => false, 'error' => 'Database error while loading the student.']);
        exit;
    }

    $sel->bind_param('s', $target_student_id);
    if (!$sel->execute()) {
        $sel->close();
        echo json_encode(['ok' => false, 'error' => 'Failed to load the selected student.']);
        exit;
    }

    $res = $sel->get_result();
    $studentRow = $res ? $res->fetch_assoc() : null;
    $res && $res->free();
    $sel->close();

    if (!$studentRow) {
        echo json_encode(['ok' => false, 'error' => 'Student not found.']);
        exit;
    }

    $corPath = trim((string) ($studentRow['class_schedule_path'] ?? ''));
    if ($corPath === '') {
        echo json_encode(['ok' => false, 'error' => 'This student has no uploaded COR or class schedule file yet.']);
        exit;
    }

    $analysis = gemini_analyze_cor($corPath, $studentRow);
    if (empty($analysis['ok'])) {
        $isQuotaExceeded = (($analysis['code'] ?? '') === 'quota_exceeded');
        if ($isQuotaExceeded) {
            $existingSchedule = trim((string) ($studentRow['work_schedule'] ?? ''));
            $message = 'Gemini is quota-limited right now, so no new schedule was generated. The current saved schedule remains unchanged.';
            if ($existingSchedule !== '') {
                $message .= ' Current saved schedule: ' . $existingSchedule;
            }

            echo json_encode([
                'ok' => false,
                'code' => 'quota_exceeded',
                'unchanged' => true,
                'error' => $message,
            ]);
            exit;
        }

        echo json_encode(['ok' => false, 'error' => (string) ($analysis['error'] ?? 'Gemini analysis failed.')]);
        exit;
    }

    if (!empty($analysis['used_fallback'])) {
        echo json_encode([
            'ok' => false,
            'error' => 'Gemini is currently quota-limited. Auto-schedule was not saved to avoid inaccurate fallback output. Please retry once API quota is available.',
        ]);
        exit;
    }

    $analysisData = $analysis['data'] ?? [];
    $recommendedSchedule = trim((string) ($analysisData['recommended_work_schedule'] ?? ''));
    if ($recommendedSchedule === '') {
        echo json_encode(['ok' => false, 'error' => 'Gemini did not return a working schedule recommendation.']);
        exit;
    }

    $upd = $mysqli->prepare('UPDATE student_applications SET work_schedule = ? WHERE student_id = ? ORDER BY created_at DESC LIMIT 1');
    if (!$upd) {
        echo json_encode(['ok' => false, 'error' => 'Database error while saving the AI schedule.']);
        exit;
    }

    $upd->bind_param('ss', $recommendedSchedule, $target_student_id);
    if (!$upd->execute()) {
        $upd->close();
        echo json_encode(['ok' => false, 'error' => 'Failed to save the AI schedule.']);
        exit;
    }
    $upd->close();

    echo json_encode([
        'ok' => true,
        'student_id' => $target_student_id,
        'full_name' => (string) ($studentRow['full_name'] ?? ''),
        'recommended_work_schedule' => $recommendedSchedule,
        'analysis' => $analysisData,
    ]);
    exit;
}

$studentsById = [];
foreach ($students as $s) {
    $studentsById[$s['student_id']] = $s;
}

$office_options = [
    'SDAO Office',
    'Registrar Office',
    'Library',
    'Guidance Office',
    'Accounting Office',
    'Admissions Office',
    'Health Services',
    'Computer Laboratory',
    'Student Affairs Office',
];

foreach ($students as $row) {
    $existingOffice = trim((string) ($row['work_location'] ?? ''));
    if ($existingOffice !== '' && !in_array($existingOffice, $office_options, true)) {
        $office_options[] = $existingOffice;
    }
}

$message = '';
$message_type = '';

$selected_student_id = trim((string) ($_GET['student_id'] ?? $_GET['student-id'] ?? ''));
$view_mode = (($_GET['view'] ?? '') === 'list') ? 'list' : 'calendar';
if ($selected_student_id === '' && !empty($students)) {
    // Default to the first student who already has a schedule.
    foreach ($students as $row) {
        if (trim((string) ($row['work_schedule'] ?? '')) !== '') {
            $selected_student_id = (string) $row['student_id'];
            break;
        }
    }

    // Fallback to the first student if none has a schedule yet.
    if ($selected_student_id === '') {
        $selected_student_id = (string) $students[0]['student_id'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_student_id = trim($_POST['student_id'] ?? $selected_student_id);
    $action_type = trim($_POST['action_type'] ?? '');
    $day_name = trim($_POST['day_name'] ?? '');
    $start_hour = (int) ($_POST['start_hour'] ?? 0);
    $end_hour = (int) ($_POST['end_hour'] ?? 0);
    $assigned_office = trim((string) ($_POST['assigned_office'] ?? ''));

    if (!isset($studentsById[$selected_student_id])) {
        $message = 'Student not found.';
        $message_type = 'error';
    } elseif ($assigned_office !== '' && !in_array($assigned_office, $office_options, true)) {
        $message = 'Please select a valid office assignment.';
        $message_type = 'error';
    } elseif ($action_type === 'assign_office') {
        $updOffice = $mysqli->prepare('UPDATE student_applications SET work_location = ? WHERE student_id = ? ORDER BY created_at DESC LIMIT 1');
        if ($updOffice) {
            $updOffice->bind_param('ss', $assigned_office, $selected_student_id);
            if ($updOffice->execute()) {
                $message = 'Office assignment updated successfully.';
                $message_type = 'success';
                $studentsById[$selected_student_id]['work_location'] = $assigned_office;
                foreach ($students as $idx => $row) {
                    if ($row['student_id'] === $selected_student_id) {
                        $students[$idx]['work_location'] = $assigned_office;
                        break;
                    }
                }
            } else {
                $message = 'Failed to update office assignment.';
                $message_type = 'error';
            }
            $updOffice->close();
        } else {
            $message = 'Database error while updating office assignment.';
            $message_type = 'error';
        }
    } elseif ($day_name === '' || !in_array($day_name, ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'], true)) {
        $message = 'Please select a valid day.';
        $message_type = 'error';
    } elseif ($start_hour < 8 || $end_hour > 17 || $start_hour >= $end_hour) {
        $message = 'Please select a valid time range between 8:00AM and 5:00PM.';
        $message_type = 'error';
    } else {
        $student = $studentsById[$selected_student_id];
        $parsed = parse_schedule_text((string) ($student['work_schedule'] ?? ''));

        if (!isset($parsed[$day_name])) {
            $parsed[$day_name] = [];
        }

        if ($action_type === 'add') {
            $parsed[$day_name][] = ['start' => $start_hour, 'end' => $end_hour];
            $parsed[$day_name] = merge_intervals($parsed[$day_name]);
        } elseif ($action_type === 'remove') {
            $parsed[$day_name] = subtract_interval($parsed[$day_name], $start_hour, $end_hour);
        }

        $newText = format_schedule_text($parsed);

        $upd = $mysqli->prepare('UPDATE student_applications SET work_schedule = ?, work_location = ? WHERE student_id = ? ORDER BY created_at DESC LIMIT 1');
        if ($upd) {
            $upd->bind_param('sss', $newText, $assigned_office, $selected_student_id);
            if ($upd->execute()) {
                $message = 'Schedule updated successfully.';
                $message_type = 'success';
                $studentsById[$selected_student_id]['work_schedule'] = $newText;
                $studentsById[$selected_student_id]['work_location'] = $assigned_office;

                foreach ($students as $idx => $row) {
                    if ($row['student_id'] === $selected_student_id) {
                        $students[$idx]['work_schedule'] = $newText;
                        $students[$idx]['work_location'] = $assigned_office;
                        break;
                    }
                }
            } else {
                $message = 'Failed to update schedule.';
                $message_type = 'error';
            }
            $upd->close();
        } else {
            $message = 'Database error while updating schedule.';
            $message_type = 'error';
        }
    }
}

$selected_student = $studentsById[$selected_student_id] ?? null;
$selected_schedule = parse_schedule_text((string) ($selected_student['work_schedule'] ?? ''));

$totals = calculate_schedule_totals($students);
$active_sas_count = (int) $totals['active_sas_count'];
$total_shifts = (int) $totals['total_shifts'];
$total_hours = (int) $totals['total_hours'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <script src="../assets/realtime.js"></script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Management | NU SA System</title>
    <style>
        /* ============================================================
           CSS VARIABLES — Design System
        ============================================================ */
        :root {
            --clr-white:          #FFFFFF;
            --clr-bg:             #F9FAFB;
            --clr-bg-soft:        #F3F7FF;
            --clr-border:         #E5E7EB;
            --clr-border-input:   #D1D5DC;

            --clr-text-primary:   #101828;
            --clr-text-body:      #364153;
            --clr-text-muted:     #4A5565;
            --clr-text-dark:      #0A0A0A;

            --clr-blue:           #155DFC;
            --clr-blue-dark:      #1447E6;
            --clr-navy:           #1E3A8A;
            --clr-blue-bg:        #DBEAFE;
            --clr-blue-border:    #8EC5FF;

            --clr-green:          #008236;
            --clr-green-bg:       #DCFCE7;
            --clr-green-border:   #7BF1A8;

            --clr-purple:         #9810FA;
            --clr-purple-light:   #F3E8FF;

            --clr-badge-bg:       #DBEAFE;
            --clr-badge-text:     #155DFC;

            --clr-alert:          #FB2C36;
            --clr-orange-text:    #FFEDD4;

            --grad-brand:         linear-gradient(135deg, #155DFC 0%, #9810FA 100%);
            --grad-purple-panel:  linear-gradient(170.52deg, #9810FA 0%, #8200DB 100%);
            --grad-orange-card:   linear-gradient(162.82deg, #F54900 0%, #CA3500 100%);
            --grad-page:          radial-gradient(circle at 8% 10%, #dbeafe 0%, rgba(219,234,254,0) 38%), radial-gradient(circle at 92% 4%, #fee2e2 0%, rgba(254,226,226,0) 34%), linear-gradient(180deg, #f8fbff 0%, #f9fafb 100%);
            --grad-card:          linear-gradient(180deg, #ffffff 0%, #f8faff 100%);
            --shadow-soft:        0 12px 30px rgba(15, 23, 42, 0.07);
            --shadow-card:        0 10px 24px rgba(15, 23, 42, 0.06);

            --sidebar-width:      256px;

            --fs-xs:   12px;
            --fs-sm:   14px;
            --fs-base: 16px;
            --fs-md:   18px;
            --fs-lg:   24px;

            --sp-4:   4px;
            --sp-8:   8px;
            --sp-12:  12px;
            --sp-16:  16px;
            --sp-24:  24px;
            --sp-32:  32px;

            --radius-sm:   4px;
            --radius-md:   10px;
            --radius-lg:   16.4px;
            --radius-pill: 9999px;
        }

        /* ============================================================
           RESET & BASE
        ============================================================ */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { font-size: 16px; }
        body {
            font-family: "Plus Jakarta Sans", "Nunito Sans", "Segoe UI Variable Text", sans-serif;
            background: var(--grad-page);
            color: var(--clr-text-primary);
            min-height: 100vh;
            display: flex;
            letter-spacing: 0.01em;
            position: relative;
        }
        body::before,
        body::after {
            content: "";
            position: fixed;
            z-index: -1;
            border-radius: 9999px;
            pointer-events: none;
            filter: blur(70px);
            opacity: 0.5;
        }
        body::before {
            width: 280px;
            height: 280px;
            top: -90px;
            right: 12%;
            background: #c4b5fd;
        }
        body::after {
            width: 320px;
            height: 320px;
            bottom: -130px;
            left: 16%;
            background: #93c5fd;
        }
        a { text-decoration: none; color: inherit; }
        button { cursor: pointer; font-family: inherit; border: none; background: none; }
        img { display: block; max-width: 100%; }
        ul { list-style: none; }

        /* ============================================================
           LAYOUT
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
            background: rgba(255,255,255,.88);
            border-right: 1px solid rgba(148,163,184,.25);
            backdrop-filter: blur(10px);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            z-index: 100;
            box-shadow: 0 0 0 1px rgba(255,255,255,.4) inset;
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
            border-radius: var(--radius-md);
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
            border-radius: var(--radius-md);
            transition: background 0.2s, transform 0.2s;
        }

        .nav__link:hover {
            background: var(--clr-bg-soft);
            transform: translateX(2px);
        }

        .nav__link--active { background: var(--clr-blue); }
        .nav__link--active .nav__label { color: var(--clr-white); }

        .nav__icon {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }
        .nav__icon img { width: 100%; height: 100%; object-fit: contain; }

        .nav__label {
            font-size: var(--fs-base);
            color: var(--clr-text-body);
            line-height: 24px;
            flex: 1;
            white-space: nowrap;
        }

        .nav__badge {
            background: var(--clr-badge-bg);
            color: var(--clr-badge-text);
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
            background: rgba(255,255,255,.74);
            border-bottom: 1px solid rgba(148,163,184,.22);
            backdrop-filter: blur(10px);
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
            border-radius: var(--radius-md);
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
            font-weight: 800;
            color: var(--clr-text-primary);
            line-height: 32px;
            letter-spacing: -0.015em;
        }

        .topbar__subtitle {
            font-size: var(--fs-sm);
            color: var(--clr-text-muted);
            line-height: 20px;
        }

        .topbar__right {
            display: flex;
            align-items: center;
            gap: var(--sp-12);
        }

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
        .topbar__notif img { width: 20px; height: 20px; }
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

        .topbar__user-info {
            text-align: right;
            display: flex;
            flex-direction: column;
        }

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
        .topbar__avatar img { width: 20px; height: 20px; }

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

        .stats-row,
        .manage-card,
        .calendar-card {
            animation: riseIn 0.5s ease both;
        }

        .manage-card { animation-delay: .08s; }
        .calendar-card { animation-delay: .14s; }

        @keyframes riseIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ============================================================
           CONTROLS ROW
        ============================================================ */
        .controls-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: var(--sp-16);
            flex-wrap: wrap;
            height: 42px;
        }

        .view-toggle { display: flex; gap: var(--sp-16); align-items: center; }

        .btn-view {
            height: 40px;
            padding: 0 var(--sp-16);
            border-radius: var(--radius-md);
            font-size: var(--fs-base);
            line-height: 24px;
            transition: background 0.15s, color 0.15s;
        }

        .btn-view--active { background: var(--clr-blue); color: var(--clr-white); }
        .btn-view--inactive { background: #F3F4F6; color: var(--clr-text-body); }
        .btn-view--inactive:hover { background: #E5E7EB; }

        .action-btns { display: flex; align-items: center; gap: var(--sp-12); }

        .btn-export {
            height: 42px;
            padding: 0 var(--sp-16);
            border: 1px solid var(--clr-border-input);
            border-radius: var(--radius-md);
            background: rgba(255,255,255,.85);
            color: var(--clr-text-body);
            font-size: var(--fs-base);
            line-height: 24px;
            display: flex;
            align-items: center;
            gap: var(--sp-8);
            transition: background 0.2s, border-color 0.2s;
        }
        .btn-export:hover {
            background: var(--clr-bg-soft);
            border-color: #93c5fd;
        }
        .btn-export img { width: 16px; height: 16px; object-fit: contain; }

        .btn-create {
            height: 42px;
            padding: 0 var(--sp-16);
            border-radius: var(--radius-md);
            background: var(--clr-navy);
            color: var(--clr-white);
            font-size: var(--fs-base);
            line-height: 24px;
            display: flex;
            align-items: center;
            gap: var(--sp-8);
            transition: opacity 0.15s;
        }
        .btn-create:hover { opacity: .88; }
        .btn-create img { width: 16px; height: 16px; filter: brightness(0) invert(1); }

        .btn-ai {
            height: 42px;
            padding: 0 var(--sp-16);
            border-radius: var(--radius-md);
            background: var(--grad-brand);
            color: var(--clr-white);
            font-size: var(--fs-base);
            line-height: 24px;
            display: flex;
            align-items: center;
            gap: var(--sp-8);
            transition: opacity 0.15s;
        }
        .btn-ai:hover { opacity: .88; }
        .btn-ai:disabled { opacity: .6; cursor: not-allowed; }

        .ai-card {
            background: rgba(255,255,255,.9);
            border: 1px solid rgba(191,219,254,.9);
            border-radius: var(--radius-lg);
            padding: var(--sp-16);
            display: flex;
            flex-direction: column;
            gap: var(--sp-12);
        }

        .ai-card__header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: var(--sp-12);
        }

        .ai-card__title {
            font-size: var(--fs-md);
            font-weight: 800;
            color: var(--clr-text-primary);
            line-height: 24px;
        }

        .ai-card__desc {
            font-size: var(--fs-sm);
            color: var(--clr-text-muted);
            line-height: 20px;
            margin-top: 2px;
        }

        .ai-card__badge {
            flex-shrink: 0;
            height: 24px;
            padding: 0 10px;
            border-radius: var(--radius-pill);
            background: #eef2ff;
            color: #4338ca;
            font-size: var(--fs-xs);
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            white-space: nowrap;
        }

        .ai-card__result {
            border-radius: var(--radius-md);
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: var(--sp-12);
            font-size: var(--fs-sm);
            color: var(--clr-text-body);
            line-height: 20px;
            white-space: pre-wrap;
        }

        .ai-card__result--success {
            background: #ecfdf3;
            border-color: #a7f3d0;
            color: #065f46;
        }

        .ai-card__result--error {
            background: #fef2f2;
            border-color: #fecaca;
            color: #991b1b;
        }

        .manage-card {
            background: var(--grad-card);
            border: 1px solid rgba(191,219,254,.8);
            border-radius: var(--radius-lg);
            padding: 18px;
            display: flex;
            flex-direction: column;
            gap: var(--sp-12);
            box-shadow: var(--shadow-card);
        }

        .manage-card__title {
            font-size: var(--fs-md);
            font-weight: bold;
            color: var(--clr-text-primary);
            line-height: 28px;
        }

        .schedule-form {
            display: grid;
            grid-template-columns: 1.5fr 1.2fr 1fr 1fr 1fr auto auto auto;
            gap: var(--sp-8);
            align-items: end;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .field label {
            font-size: var(--fs-xs);
            color: var(--clr-text-muted);
            line-height: 16px;
        }

        .field select {
            height: 38px;
            border: 1px solid var(--clr-border-input);
            border-radius: var(--radius-md);
            padding: 0 var(--sp-8);
            font-size: var(--fs-sm);
            color: var(--clr-text-primary);
            background: rgba(255,255,255,.92);
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .field select:focus {
            outline: none;
            border-color: #60a5fa;
            box-shadow: 0 0 0 3px rgba(59,130,246,.12);
        }

        .btn-add, .btn-remove, .btn-office {
            height: 38px;
            border-radius: var(--radius-md);
            padding: 0 var(--sp-12);
            font-size: var(--fs-sm);
            color: var(--clr-white);
        }

        .btn-add { background: var(--clr-green); }
        .btn-remove { background: #b91c1c; }
        .btn-office { background: var(--clr-blue); }
        .btn-add:hover,
        .btn-remove:hover,
        .btn-office:hover {
            filter: brightness(1.06);
        }

        .msg {
            border-radius: var(--radius-md);
            padding: 10px var(--sp-12);
            font-size: var(--fs-sm);
        }

        .msg--success {
            background: #ecfdf3;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }

        .msg--error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .student-summary {
            font-size: var(--fs-sm);
            color: var(--clr-text-body);
            line-height: 20px;
        }

        .schedule-list {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: var(--sp-8);
        }

        .is-hidden {
            display: none !important;
        }

        .schedule-list__day {
            border: 1px solid #dbeafe;
            border-radius: var(--radius-md);
            padding: var(--sp-8);
            background: #f8fbff;
            min-height: 58px;
        }

        .schedule-list__day strong {
            display: block;
            font-size: var(--fs-sm);
            color: var(--clr-text-primary);
            margin-bottom: 4px;
        }

        .schedule-list__day span {
            font-size: var(--fs-xs);
            color: var(--clr-text-muted);
            line-height: 16px;
        }

        /* ============================================================
           STAT CARDS
        ============================================================ */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: var(--sp-16);
        }

        .stat-card {
            background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
            border: 1px solid #1e3a8a;
            border-radius: var(--radius-lg);
            padding: 17px;
            display: flex;
            align-items: center;
            gap: var(--sp-12);
            min-height: 100px;
            box-shadow: var(--shadow-card);
        }

        .stat-card__icon-wrap {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .stat-card__icon-wrap--blue,
        .stat-card__icon-wrap--green,
        .stat-card__icon-wrap--purple {
            background: rgba(255,255,255,.16);
        }
        .stat-card__icon-wrap svg path,
        .stat-card__icon-wrap svg circle,
        .stat-card__icon-wrap svg rect {
            stroke: #ffffff !important;
            fill: transparent;
        }
        .stat-card__icon-wrap img { width: 20px; height: 20px; object-fit: contain; }

        .stat-card__info { display: flex; flex-direction: column; }

        .stat-card__value {
            font-size: var(--fs-lg);
            font-weight: bold;
            color: #ffffff;
            line-height: 32px;
        }

        .stat-card__label {
            font-size: var(--fs-sm);
            color: #ffffff;
            line-height: 20px;
            white-space: nowrap;
        }

        .stat-card--alert {
            background: linear-gradient(135deg, #1e3a8a 0%, #1d4ed8 100%);
            border-color: #1e3a8a;
            flex-direction: column;
            align-items: flex-start;
            gap: var(--sp-4);
            padding: var(--sp-16);
        }

        .stat-card--alert .stat-card__tag { font-size: var(--fs-sm); color: var(--clr-white); line-height: 20px; }
        .stat-card--alert .stat-card__status { font-size: var(--fs-base); font-weight: bold; color: var(--clr-white); line-height: 24px; }
        .stat-card--alert .stat-card__sub { font-size: var(--fs-xs); color: rgba(255,255,255,.9); line-height: 16px; }

        /* ============================================================
           CALENDAR CARD
        ============================================================ */
        .calendar-card {
            background: var(--grad-card);
            border: 1px solid rgba(191,219,254,.75);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-soft);
        }

        .calendar-card__header {
            border-bottom: 1px solid #dbeafe;
            padding: var(--sp-24);
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 77px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        }

        .calendar-card__week-title {
            font-size: var(--fs-md);
            font-weight: bold;
            color: var(--clr-text-primary);
            line-height: 28px;
        }

        .calendar-card__nav { display: flex; gap: var(--sp-8); }

        .btn-week-nav {
            height: 28px;
            padding: 4px var(--sp-12);
            background: #F3F4F6;
            border-radius: var(--radius-md);
            font-size: var(--fs-sm);
            color: var(--clr-text-dark);
            line-height: 20px;
            transition: background 0.15s;
        }
        .btn-week-nav:hover { background: #E5E7EB; }

        .calendar-scroll { overflow-x: auto; }

        .calendar-table {
            width: 100%;
            min-width: 900px;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .calendar-table col.col-time { width: 128px; }

        .calendar-table thead th {
            background: #eef5ff;
            border-bottom: 1px solid #dbeafe;
            padding: var(--sp-12) var(--sp-16);
            text-align: left;
            font-size: var(--fs-sm);
            font-weight: bold;
            color: var(--clr-text-primary);
            height: 44.5px;
        }

        .calendar-table tbody td {
            border-bottom: 1px solid #eaf2ff;
            vertical-align: top;
            padding: 0;
        }

        .calendar-table tbody td.td-time {
            background: #f7faff;
            padding: 0 var(--sp-16);
            vertical-align: middle;
            height: 73px;
        }

        .td-time__label {
            font-size: var(--fs-sm);
            color: var(--clr-text-muted);
            line-height: 20px;
            white-space: nowrap;
        }

        .td-day { height: 73px; }
        .td-day--span { vertical-align: top; }

        .sched-blocks {
            padding: 12.5px var(--sp-16);
            display: flex;
            flex-direction: column;
            gap: var(--sp-8);
            height: 100%;
        }

        .sched-block {
            height: 48px;
            border-left: 3px solid;
            border-radius: var(--radius-sm);
            padding: var(--sp-8) var(--sp-8) var(--sp-8) var(--sp-12);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            box-shadow: 0 6px 14px rgba(21, 93, 252, 0.12);
        }

        .sched-block--blue  {
            background: linear-gradient(140deg, #dbeafe 0%, #eff6ff 100%);
            border-color: #3b82f6;
        }
        .sched-block--green { background: var(--clr-green-bg); border-color: var(--clr-green-border); }

        .sched-block__name { font-size: var(--fs-xs); font-weight: bold; line-height: 16px; }
        .sched-block__name--blue  { color: var(--clr-blue-dark); }
        .sched-block__name--green { color: var(--clr-green); }

        .sched-block__loc { font-size: var(--fs-xs); line-height: 16px; opacity: 0.75; }
        .sched-block__loc--blue  { color: var(--clr-blue-dark); }
        .sched-block__loc--green { color: var(--clr-green); }

        .sched-block__time {
            font-size: 11px;
            line-height: 14px;
            font-weight: 700;
            letter-spacing: 0.01em;
            margin-bottom: 2px;
        }
        .sched-block__time--blue  { color: var(--clr-blue-dark); }
        .sched-block__time--green { color: var(--clr-green); }

        /* ============================================================
           AI PANEL
        ============================================================ */
        .ai-panel {
            background: var(--grad-purple-panel);
            border-radius: var(--radius-lg);
            padding: var(--sp-24);
            display: flex;
            flex-direction: column;
            gap: var(--sp-16);
        }

        .ai-panel__title {
            font-size: var(--fs-md);
            font-weight: bold;
            color: var(--clr-white);
            line-height: 28px;
        }

        .ai-panel__cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: var(--sp-16);
        }

        .suggestion-card {
            background: rgba(255,255,255,.10);
            border-radius: var(--radius-md);
            padding: var(--sp-16);
            display: flex;
            flex-direction: column;
            gap: var(--sp-8);
        }

        .suggestion-card__title { font-size: var(--fs-base); font-weight: bold; color: var(--clr-white); line-height: 24px; }
        .suggestion-card__desc  { font-size: var(--fs-sm); color: var(--clr-purple-light); line-height: 20px; }

        .suggestion-card__btn {
            height: 36px;
            background: var(--clr-white);
            border-radius: var(--radius-md);
            font-size: var(--fs-sm);
            color: var(--clr-purple);
            line-height: 20px;
            text-align: center;
            padding: var(--sp-8) 0;
            transition: opacity 0.15s;
            display: block;
            width: 100%;
        }
        .suggestion-card__btn:hover { opacity: .85; }

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
            .ai-panel__cards { grid-template-columns: 1fr; }
            .controls-row { height: auto; flex-direction: column; align-items: flex-start; }
            .topbar__user-info { display: none; }
            .schedule-form { grid-template-columns: 1fr 1fr; }
            .schedule-list { grid-template-columns: 1fr 1fr; }
        }

        /* ============================================================
           RESPONSIVE — MOBILE (≤768px)
        ============================================================ */
        @media (max-width: 768px) {
            .topbar__title { font-size: 18px; }
            .topbar__subtitle { font-size: var(--fs-xs); }
            .page-content { padding: var(--sp-12); gap: var(--sp-16); }
            .stats-row { grid-template-columns: 1fr 1fr; gap: var(--sp-12); }
            .stat-card { min-height: 80px; }
            .action-btns { flex-wrap: wrap; gap: var(--sp-8); }
            .btn-export, .btn-create { font-size: var(--fs-sm); height: 38px; }
            .ai-panel { padding: var(--sp-16); }
            .calendar-card__week-title { font-size: var(--fs-sm); }
            .schedule-form { grid-template-columns: 1fr; }
            .schedule-list { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="sidebar-overlay sidebar-overlay--hidden" id="sidebarOverlay"></div>

<div class="app">

    <!-- ============================================================
         SIDEBAR
    ============================================================ -->
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
                            <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" width="20" height="20">
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
                            <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" width="20" height="20">
                                <path d="M6 2h8a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V4a2 2 0 012-2z" stroke="#364153" stroke-width="1.5"/>
                                <path d="M7 7h6M7 10h6M7 13h4" stroke="#364153" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                        </span>
                        <span class="nav__label">Applications</span>
                        <span class="nav__badge" aria-label="12 pending">12</span>
                    </a>
                </li>
                <li class="nav__item">
                    <a href="scheduling.php" class="nav__link nav__link--active" aria-current="page">
                        <span class="nav__icon" aria-hidden="true">
                            <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" width="20" height="20">
                                <rect x="2" y="4" width="16" height="14" rx="2" stroke="white" stroke-width="1.5"/>
                                <path d="M6 2v4M14 2v4" stroke="white" stroke-width="1.5" stroke-linecap="round"/>
                                <path d="M2 9h16" stroke="white" stroke-width="1.2"/>
                            </svg>
                        </span>
                        <span class="nav__label">Scheduling</span>
                    </a>
                </li>
                <li class="nav__item">
                    <a href="attendance.php" class="nav__link">
                        <span class="nav__icon" aria-hidden="true">
                            <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" width="20" height="20">
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
                            <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" width="20" height="20">
                                <path d="M4 4h12a2 2 0 012 2v6a2 2 0 01-2 2H9l-4 3v-3H4a2 2 0 01-2-2V6a2 2 0 012-2z" stroke="#364153" stroke-width="1.5" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <span class="nav__label">Messages</span>
                    </a>
                </li>
                <li class="nav__item">
                    <a href="documents.php" class="nav__link">
                        <span class="nav__icon" aria-hidden="true">
                            <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" width="20" height="20">
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
                            <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" width="20" height="20">
                                <path d="M10 2l2.09 4.26L17 7.27l-3.5 3.41.83 4.82L10 13.27l-4.33 2.23.83-4.82L3 7.27l4.91-.71L10 2z" stroke="#364153" stroke-width="1.5" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <span class="nav__label">Evaluation</span>
                    </a>
                </li>
                <li class="nav__item">
                    <a href="reports.php" class="nav__link">
                        <span class="nav__icon" aria-hidden="true">
                            <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" width="20" height="20">
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
                            <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" width="20" height="20">
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
                            <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" width="20" height="20">
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
                            <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" width="20" height="20">
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

    <!-- ============================================================
         MAIN
    ============================================================ -->
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
                    <h1 class="topbar__title">Schedule Management</h1>
                    <p class="topbar__subtitle">NU Lipa - Student Development and Activities Office</p>
                </div>
            </div>
            <div class="topbar__right">
                <a href="#" class="topbar__notif" aria-label="Notifications">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z" fill="#4A5565"/>
                    </svg>
                    <span class="topbar__notif-dot" aria-label="New notifications"></span>
                </a>
                <div class="topbar__user">
                    <div class="topbar__user-info">
                        <span class="topbar__user-name"><?php echo htmlspecialchars($admin_name); ?></span>
                        <span class="topbar__user-role"><?php echo htmlspecialchars($admin_role); ?></span>
                    </div>
                    <div class="topbar__avatar" aria-label="User avatar" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="10" cy="7" r="4" fill="white" opacity=".9"/>
                            <path d="M2 17c0-3.314 3.582-6 8-6s8 2.686 8 6" fill="white" opacity=".9"/>
                        </svg>
                    </div>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <section class="page-content" aria-label="Schedule Management content">

            <!-- Controls -->
            <div class="controls-row">
                <div class="view-toggle" role="group" aria-label="View mode">
                    <button class="btn-view <?php echo $view_mode === 'calendar' ? 'btn-view--active' : 'btn-view--inactive'; ?>" id="btnCalView" type="button" aria-pressed="<?php echo $view_mode === 'calendar' ? 'true' : 'false'; ?>">Calendar View</button>
                    <button class="btn-view <?php echo $view_mode === 'list' ? 'btn-view--active' : 'btn-view--inactive'; ?>" id="btnListView" type="button" aria-pressed="<?php echo $view_mode === 'list' ? 'true' : 'false'; ?>">List View</button>
                </div>
                <div class="action-btns">
                    <a class="btn-export" href="backfill_class_blocks.php" style="text-decoration:none; display:inline-flex; align-items:center; gap:8px;">
                        Backfill Old Students
                    </a>
                    <button class="btn-ai" id="btnRulesAuto" type="button">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M2 3h12M2 8h12M2 13h12" stroke="white" stroke-width="1.4" stroke-linecap="round"/>
                        </svg>
                        Auto Recompute (Rules)
                    </button>
                    <button class="btn-export" type="button">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M8 2v8M8 10L5 7M8 10l3-3" stroke="#364153" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M2 11v2a1 1 0 001 1h10a1 1 0 001-1v-2" stroke="#364153" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                        Export Schedule
                    </button>
                    <button class="btn-create" type="button">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M8 2v12M2 8h12" stroke="white" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                        Create Schedule
                    </button>
                    <button class="btn-ai" id="btnAiAuto" type="button">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M8 1.5l1.18 3.28L12.5 6l-3.32 1.22L8 10.5 6.82 7.22 3.5 6l3.32-1.22L8 1.5z" stroke="white" stroke-width="1.2" stroke-linejoin="round"/>
                            <path d="M12.5 9.5l.62 1.73L14.9 12l-1.78.77-.62 1.73-.62-1.73L10.1 12l1.78-.77.62-1.73z" stroke="white" stroke-width="1.2" stroke-linejoin="round"/>
                        </svg>
                        AI Auto-Schedule
                    </button>
                </div>
            </div>

            <!-- Stat Cards -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-card__icon-wrap stat-card__icon-wrap--blue">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="10" cy="7" r="4" stroke="#155DFC" stroke-width="1.5"/>
                            <path d="M2 17c0-3.314 3.582-6 8-6s8 2.686 8 6" stroke="#155DFC" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <div class="stat-card__info">
                        <span class="stat-card__value" id="statActiveSAs"><?php echo (int) $active_sas_count; ?></span>
                        <span class="stat-card__label">Active SAs</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__icon-wrap stat-card__icon-wrap--green">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect x="2" y="4" width="16" height="14" rx="2" stroke="#008236" stroke-width="1.5"/>
                            <path d="M6 2v4M14 2v4" stroke="#008236" stroke-width="1.5" stroke-linecap="round"/>
                            <path d="M2 9h16" stroke="#008236" stroke-width="1.2"/>
                        </svg>
                    </div>
                    <div class="stat-card__info">
                        <span class="stat-card__value" id="statShiftsWeek"><?php echo (int) $total_shifts; ?></span>
                        <span class="stat-card__label">Shifts This Week</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__icon-wrap stat-card__icon-wrap--purple">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="10" cy="10" r="8" stroke="#9810FA" stroke-width="1.5"/>
                            <path d="M10 6v4l3 2" stroke="#9810FA" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <div class="stat-card__info">
                        <span class="stat-card__value" id="statTotalHours"><?php echo (int) $total_hours; ?></span>
                        <span class="stat-card__label">Total Hours</span>
                    </div>
                </div>
                <div class="stat-card stat-card--alert">
                    <span class="stat-card__tag">Selected Student</span>
                    <span class="stat-card__status"><?php echo htmlspecialchars($selected_student['full_name'] ?? 'No student'); ?></span>
                    <span class="stat-card__sub"><?php echo htmlspecialchars($selected_student['student_id'] ?? ''); ?></span>
                </div>
            </div>

            <div class="manage-card">
                <h2 class="manage-card__title">Edit Student Working Schedule</h2>

                <?php if ($message !== ''): ?>
                    <div class="msg <?php echo $message_type === 'success' ? 'msg--success' : 'msg--error'; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <?php if ($selected_student): ?>
                    <div class="ai-card" id="aiAssistantCard" data-has-cor="<?php echo !empty($selected_student['class_schedule_path']) ? '1' : '0'; ?>">
                        <div class="ai-card__header">
                            <div>
                                <div class="ai-card__title">Gemini Assistant</div>
                                <div class="ai-card__desc">Reads the uploaded COR or class schedule file, then suggests a conflict-free work schedule.</div>
                            </div>
                            <span class="ai-card__badge"><?php echo !empty($selected_student['class_schedule_path']) ? 'COR uploaded' : 'No COR uploaded'; ?></span>
                        </div>
                        <div class="ai-card__result" id="aiResult">
                            <?php echo !empty($selected_student['class_schedule_path']) ? 'Click AI Auto-Schedule to generate and save a recommended work schedule.' : 'Upload the student COR or class schedule first to enable AI scheduling.'; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (empty($students)): ?>
                    <p class="student-summary">No student applications found yet.</p>
                <?php else: ?>
                    <form method="post" action="" class="schedule-form">
                        <input type="hidden" name="action_type" id="actionType" value="add" />

                        <div class="field">
                            <label for="student_id">Student</label>
                            <select name="student_id" id="student_id" required>
                                <?php foreach ($students as $row): ?>
                                    <option value="<?php echo htmlspecialchars($row['student_id']); ?>" <?php echo $row['student_id'] === $selected_student_id ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($row['full_name'] . ' (' . $row['student_id'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="field">
                            <label for="assigned_office">Assigned Office</label>
                            <select name="assigned_office" id="assigned_office">
                                <option value="">Unassigned</option>
                                <?php
                                    $selectedOffice = (string) ($selected_student['work_location'] ?? '');
                                    foreach ($office_options as $office):
                                ?>
                                    <option value="<?php echo htmlspecialchars($office); ?>" <?php echo $office === $selectedOffice ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($office); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="field">
                            <label for="day_name">Day</label>
                            <select name="day_name" id="day_name" required>
                                <?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $dn): ?>
                                    <option value="<?php echo $dn; ?>"><?php echo $dn; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="field">
                            <label for="start_hour">Start</label>
                            <select name="start_hour" id="start_hour" required>
                                <?php for ($h = 8; $h <= 16; $h++): ?>
                                    <option value="<?php echo $h; ?>"><?php echo htmlspecialchars(format_hour_label($h)); ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="field">
                            <label for="end_hour">End</label>
                            <select name="end_hour" id="end_hour" required>
                                <?php for ($h = 9; $h <= 17; $h++): ?>
                                    <option value="<?php echo $h; ?>"><?php echo htmlspecialchars(format_hour_label($h)); ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn-office" onclick="document.getElementById('actionType').value='assign_office'">Assign Office</button>
                        <button type="submit" class="btn-add" onclick="document.getElementById('actionType').value='add'">Add</button>
                        <button type="submit" class="btn-remove" onclick="document.getElementById('actionType').value='remove'">Remove</button>
                    </form>

                    <?php if ($selected_student): ?>
                        <p class="student-summary">
                            Editing: <strong><?php echo htmlspecialchars($selected_student['full_name']); ?></strong>
                            (<?php echo htmlspecialchars($selected_student['student_id']); ?>)
                            <?php if (!empty($selected_student['work_location'])): ?>
                                • Preferred Office: <?php echo htmlspecialchars($selected_student['work_location']); ?>
                            <?php endif; ?>
                        </p>

                        <div class="schedule-list <?php echo $view_mode === 'calendar' ? 'is-hidden' : ''; ?>" id="scheduleListPanel">
                            <?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $dn): ?>
                                <div class="schedule-list__day">
                                    <strong><?php echo $dn; ?></strong>
                                    <?php if (!empty($selected_schedule[$dn])): ?>
                                        <span>
                                            <?php
                                                $labels = [];
                                                foreach ($selected_schedule[$dn] as $iv) {
                                                    $labels[] = format_hour_label((int) $iv['start']) . '–' . format_hour_label((int) $iv['end']);
                                                }
                                                echo htmlspecialchars(implode(', ', $labels));
                                            ?>
                                        </span>
                                    <?php else: ?>
                                        <span>No assigned hours</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Calendar Card -->
            <div class="calendar-card <?php echo $view_mode === 'list' ? 'is-hidden' : ''; ?>" id="calendarViewPanel">
                <div class="calendar-card__header">
                    <h2 class="calendar-card__week-title" id="weekTitle">Current Weekly Grid</h2>
                    <div class="calendar-card__nav">
                        <button class="btn-week-nav" id="btnPrev" type="button">← Prev</button>
                        <button class="btn-week-nav" id="btnNext" type="button">Next →</button>
                    </div>
                </div>
                <div class="calendar-scroll">
                    <table class="calendar-table" role="grid" aria-label="Weekly schedule">
                        <colgroup>
                            <col class="col-time">
                            <col class="col-day">
                            <col class="col-day">
                            <col class="col-day">
                            <col class="col-day">
                            <col class="col-day">
                            <col class="col-day">
                        </colgroup>
                        <thead>
                            <tr>
                                <th scope="col">Time</th>
                                <th scope="col">Monday</th>
                                <th scope="col">Tuesday</th>
                                <th scope="col">Wednesday</th>
                                <th scope="col">Thursday</th>
                                <th scope="col">Friday</th>
                                <th scope="col">Saturday</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                                $gridHours = [8, 9, 10, 11, 12, 13, 14, 15, 16];
                                $daysGrid = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
                                $gridStartHour = 8;
                                $gridEndHour = 17;

                                $dayStartMap = [];
                                $daySkipUntil = [];

                                foreach ($daysGrid as $dn) {
                                    $dayStartMap[$dn] = [];
                                    $daySkipUntil[$dn] = $gridStartHour;

                                    if ($selected_student && !empty($selected_schedule[$dn])) {
                                        $intervals = $selected_schedule[$dn];
                                        usort($intervals, function ($a, $b) {
                                            return ((int) $a['start']) <=> ((int) $b['start']);
                                        });

                                        foreach ($intervals as $iv) {
                                            $start = max($gridStartHour, (int) $iv['start']);
                                            $end = min($gridEndHour, (int) $iv['end']);
                                            if ($end <= $start) {
                                                continue;
                                            }

                                            $dayStartMap[$dn][$start] = [
                                                'start' => $start,
                                                'end' => $end,
                                            ];
                                        }
                                    }
                                }
                            ?>
                            <?php foreach ($gridHours as $hour): ?>
                            <tr>
                                <td class="td-time">
                                    <span class="td-time__label"><?php echo htmlspecialchars(format_hour_label($hour) . ' - ' . format_hour_label($hour + 1)); ?></span>
                                </td>
                                <?php foreach ($daysGrid as $dn): ?>
                                <?php
                                    if ($hour < $daySkipUntil[$dn]) {
                                        continue;
                                    }

                                    $interval = $dayStartMap[$dn][$hour] ?? null;
                                ?>
                                <?php if ($interval !== null): ?>
                                    <?php
                                        $duration = max(1, (int) $interval['end'] - (int) $interval['start']);
                                        $timeRangeLabel = format_hour_label((int) $interval['start']) . ' - ' . format_hour_label((int) $interval['end']);
                                        $daySkipUntil[$dn] = (int) $interval['end'];
                                        $blockMinHeight = max(48, ($duration * 73) - 24);
                                    ?>
                                <td class="td-day td-day--span" rowspan="<?php echo (int) $duration; ?>">
                                    <div class="sched-blocks">
                                        <div class="sched-block sched-block--blue"
                                             style="min-height: <?php echo (int) $blockMinHeight; ?>px;"
                                             role="article"
                                             aria-label="<?= htmlspecialchars($selected_student['full_name'] ?? 'Student') ?> — <?= htmlspecialchars($timeRangeLabel) ?> — <?= htmlspecialchars($selected_student['work_location'] ?? 'Assigned Office') ?>">
                                            <span class="sched-block__time sched-block__time--blue">
                                                <?= htmlspecialchars($timeRangeLabel) ?>
                                            </span>
                                            <span class="sched-block__name sched-block__name--blue">
                                                <?= htmlspecialchars($selected_student['full_name'] ?? 'Student') ?>
                                            </span>
                                            <span class="sched-block__loc sched-block__loc--blue">
                                                <?= htmlspecialchars($selected_student['work_location'] ?? 'Assigned Office') ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <?php else: ?>
                                <td class="td-day"></td>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </section>
    </main>
</div>

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
        if (e.key === 'Escape' && sidebar.classList.contains('sidebar--open')) { closeSidebar(); hamburger && hamburger.focus(); }
    });

    /* ---- View Toggle ---- */
    var btnCal  = document.getElementById('btnCalView');
    var btnList = document.getElementById('btnListView');
    var calendarViewPanel = document.getElementById('calendarViewPanel');
    var scheduleListPanel = document.getElementById('scheduleListPanel');
    var btnRulesAuto = document.getElementById('btnRulesAuto');
    var btnAiAuto = document.getElementById('btnAiAuto');
    var aiResult = document.getElementById('aiResult');
    var aiAssistantCard = document.getElementById('aiAssistantCard');

    function applyViewMode(mode) {
        var isCalendar = mode === 'calendar';

        if (btnCal && btnList) {
            if (isCalendar) {
                btnCal.classList.add('btn-view--active');
                btnCal.classList.remove('btn-view--inactive');
                btnCal.setAttribute('aria-pressed', 'true');
                btnList.classList.add('btn-view--inactive');
                btnList.classList.remove('btn-view--active');
                btnList.setAttribute('aria-pressed', 'false');
            } else {
                btnList.classList.add('btn-view--active');
                btnList.classList.remove('btn-view--inactive');
                btnList.setAttribute('aria-pressed', 'true');
                btnCal.classList.add('btn-view--inactive');
                btnCal.classList.remove('btn-view--active');
                btnCal.setAttribute('aria-pressed', 'false');
            }
        }

        if (calendarViewPanel) {
            calendarViewPanel.classList.toggle('is-hidden', !isCalendar);
        }
        if (scheduleListPanel) {
            scheduleListPanel.classList.toggle('is-hidden', isCalendar);
        }

        var viewUrl = new URL(window.location.href);
        viewUrl.searchParams.set('view', mode);
        window.history.replaceState({}, '', viewUrl.toString());
    }

    if (btnCal && btnList) {
        btnCal.addEventListener('click',  function () { applyViewMode('calendar'); });
        btnList.addEventListener('click', function () { applyViewMode('list');  });
    }

    /* ---- Selected Student Filter ---- */
    var studentSelect = document.getElementById('student_id');
    if (studentSelect) {
        studentSelect.addEventListener('change', function () {
            var selected = studentSelect.value || '';
            var url = new URL(window.location.href);
            if (selected) {
                url.searchParams.set('student_id', selected);
            } else {
                url.searchParams.delete('student_id');
            }
            window.location.href = url.toString();
        });
    }

    function setAiResult(message, status) {
        if (!aiResult) {
            return;
        }

        aiResult.classList.remove('ai-card__result--success', 'ai-card__result--error');
        if (status === 'success') {
            aiResult.classList.add('ai-card__result--success');
        } else if (status === 'error') {
            aiResult.classList.add('ai-card__result--error');
        }
        aiResult.textContent = message;
    }

    try {
        var persistedAiNotice = window.sessionStorage.getItem('samsAiNotice');
        if (persistedAiNotice && aiResult) {
            var notice = JSON.parse(persistedAiNotice);
            if (notice && notice.message) {
                setAiResult(notice.message, notice.status || 'success');
            }
            window.sessionStorage.removeItem('samsAiNotice');
        }
    } catch (err) {
        // Ignore storage issues.
    }

    if (btnAiAuto && studentSelect) {
        btnAiAuto.addEventListener('click', function () {
            var selectedId = studentSelect.value || '';
            if (!selectedId) {
                setAiResult('Please select a student before running Gemini.', 'error');
                return;
            }

            btnAiAuto.disabled = true;
            var originalLabel = btnAiAuto.textContent;
            btnAiAuto.textContent = 'Analyzing COR...';
            setAiResult('Reading the uploaded COR with Gemini. Please wait...', '');

            var url = new URL(window.location.href);
            url.searchParams.set('ajax', 'gemini');
            url.searchParams.set('student_id', selectedId);
            url.searchParams.set('_', String(Date.now()));

            fetch(url.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store'
            })
                .then(function (res) {
                    if (!res.ok) {
                        throw new Error('Gemini request failed');
                    }
                    return res.json();
                })
                .then(function (payload) {
                    if (!payload || payload.ok !== true) {
                        throw new Error((payload && payload.error) ? payload.error : 'Gemini could not generate a schedule.');
                    }

                    var scheduleText = payload.recommended_work_schedule || '';
                    var studentName = payload.full_name || 'the selected student';
                    var quotaLimited = payload.quota_limited === true;
                    var successMessage = quotaLimited
                        ? ((payload.message || 'Gemini is quota-limited right now. Showing latest saved schedule for ') + studentName + '.\n\n' + scheduleText)
                        : ('Gemini generated and saved a schedule for ' + studentName + '.\n\n' + scheduleText);
                    window.sessionStorage.setItem('samsAiNotice', JSON.stringify({
                        status: 'success',
                        message: successMessage
                    }));
                    window.location.reload();
                })
                .catch(function (error) {
                    var message = error && error.message ? error.message : 'Gemini auto-scheduling failed.';
                    if (/quota-limited|quota exceeded|no new schedule was generated/i.test(message)) {
                        setAiResult(message, 'error');
                        return;
                    }
                    setAiResult(message, 'error');
                })
                .finally(function () {
                    btnAiAuto.disabled = false;
                    btnAiAuto.textContent = originalLabel;
                });
        });
    }

    if (btnRulesAuto && studentSelect) {
        btnRulesAuto.addEventListener('click', function () {
            var selectedId = studentSelect.value || '';
            if (!selectedId) {
                setAiResult('Please select a student before running rule-based recompute.', 'error');
                return;
            }

            btnRulesAuto.disabled = true;
            var originalLabel = btnRulesAuto.textContent;
            btnRulesAuto.textContent = 'Recomputing...';
            setAiResult('Computing schedule from stored class blocks and rules...', '');

            var url = new URL(window.location.href);
            url.searchParams.set('ajax', 'rules');
            url.searchParams.set('student_id', selectedId);
            url.searchParams.set('_', String(Date.now()));

            fetch(url.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store'
            })
                .then(function (res) {
                    if (!res.ok) {
                        throw new Error('Rule-based recompute request failed');
                    }
                    return res.json();
                })
                .then(function (payload) {
                    if (!payload || payload.ok !== true) {
                        throw new Error((payload && payload.error) ? payload.error : 'Rule-based recompute failed.');
                    }

                    var scheduleText = payload.recommended_work_schedule || '';
                    var studentName = payload.full_name || 'the selected student';
                    window.sessionStorage.setItem('samsAiNotice', JSON.stringify({
                        status: 'success',
                        message: 'Rule-based automation generated and saved schedule for ' + studentName + '.\n\n' + scheduleText
                    }));
                    window.location.reload();
                })
                .catch(function (error) {
                    var message = error && error.message ? error.message : 'Rule-based recompute failed.';
                    setAiResult(message, 'error');
                })
                .finally(function () {
                    btnRulesAuto.disabled = false;
                    btnRulesAuto.textContent = originalLabel;
                });
        });
    }

    /* ---- Week Navigation ---- */
    var weekOffset  = 0;
    var weekTitleEl = document.getElementById('weekTitle');
    var months      = ['January','February','March','April','May','June',
                       'July','August','September','October','November','December'];

    function formatRange(offset) {
        var today = new Date();
        var day = today.getDay();
        var s = new Date(today);
        var diffToMonday = (day === 0 ? -6 : 1 - day);
        s.setDate(s.getDate() + diffToMonday + offset * 7);
        var e = new Date(s);    e.setDate(e.getDate() + 5);
        var sm = months[s.getMonth()], em = months[e.getMonth()];
        var sy = s.getFullYear(),      ey = e.getFullYear();
        if (sm === em && sy === ey) return 'Week of ' + sm + ' ' + s.getDate() + ' - ' + e.getDate() + ', ' + sy;
        return 'Week of ' + sm + ' ' + s.getDate() + ' - ' + em + ' ' + e.getDate() + ', ' + ey;
    }

    var btnPrev = document.getElementById('btnPrev');
    var btnNext = document.getElementById('btnNext');
    if (btnPrev && btnNext && weekTitleEl) {
        weekTitleEl.textContent = formatRange(0);
        btnPrev.addEventListener('click', function () { weekOffset--; weekTitleEl.textContent = formatRange(weekOffset); });
        btnNext.addEventListener('click', function () { weekOffset++; weekTitleEl.textContent = formatRange(weekOffset); });
    }

    var statActiveSAs = document.getElementById('statActiveSAs');
    var statShiftsWeek = document.getElementById('statShiftsWeek');
    var statTotalHours = document.getElementById('statTotalHours');

    function updateStatsUI(payload) {
        if (!payload || payload.ok !== true) {
            return;
        }
        if (statActiveSAs) {
            statActiveSAs.textContent = String(payload.active_sas_count || 0);
        }
        if (statShiftsWeek) {
            statShiftsWeek.textContent = String(payload.total_shifts || 0);
        }
        if (statTotalHours) {
            statTotalHours.textContent = String(payload.total_hours || 0);
        }
    }

    function refreshStats() {
        var url = new URL(window.location.href);
        url.searchParams.set('ajax', 'stats');
        url.searchParams.set('_', String(Date.now()));

        fetch(url.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            cache: 'no-store'
        })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('Failed to refresh stats');
                }
                return res.json();
            })
            .then(updateStatsUI)
            .catch(function () {
                // Keep existing values if refresh fails.
            });
    }

    setInterval(refreshStats, 10000);

})();
</script>

</body>
</html>