<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../db.php';

function ensure_student_class_blocks_table(mysqli $mysqli): void {
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
}

function fetch_latest_students(mysqli $mysqli): array {
    $students = [];
    $sql = "SELECT full_name, student_id, created_at
            FROM student_applications
            ORDER BY full_name ASC, created_at DESC";

    if ($res = $mysqli->query($sql)) {
        $seen = [];
        while ($row = $res->fetch_assoc()) {
            $sid = (string) ($row['student_id'] ?? '');
            if ($sid === '' || isset($seen[$sid])) {
                continue;
            }
            $seen[$sid] = true;
            $students[] = $row;
        }
        $res->free();
    }

    return $students;
}

function format_hour_label(int $hour): string {
    if ($hour === 0) return '12:00AM';
    if ($hour === 12) return '12:00PM';
    if ($hour > 12) return ($hour - 12) . ':00PM';
    return $hour . ':00AM';
}

function parse_time_label_to_timestamp(string $label): ?int {
    $normalized = preg_replace('/\s+/', ' ', trim($label));
    if ($normalized === '') {
        return null;
    }

    $ts = strtotime($normalized);
    if ($ts === false) {
        return null;
    }

    return $ts;
}

function parse_class_schedule_text(string $text): array {
    $dayMap = [
        'monday' => 'mon',
        'tuesday' => 'tue',
        'wednesday' => 'wed',
        'thursday' => 'thu',
        'friday' => 'fri',
        'saturday' => 'sat',
        'mon' => 'mon',
        'tue' => 'tue',
        'wed' => 'wed',
        'thu' => 'thu',
        'fri' => 'fri',
        'sat' => 'sat',
    ];

    $result = [
        'mon' => [],
        'tue' => [],
        'wed' => [],
        'thu' => [],
        'fri' => [],
        'sat' => [],
    ];

    if (trim($text) === '') {
        return $result;
    }

    $parts = explode('|', $text);
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '' || strpos($part, ':') === false) {
            continue;
        }

        [$dayText, $rangesText] = explode(':', $part, 2);
        $dayKey = strtolower(trim($dayText));
        if (!isset($dayMap[$dayKey])) {
            continue;
        }

        $normalizedDay = $dayMap[$dayKey];
        $ranges = array_map('trim', explode(',', $rangesText));
        foreach ($ranges as $range) {
            if ($range === '') {
                continue;
            }

            $bounds = preg_split('/\s*(?:\x{2013}|\x{2014}|-)\s*/u', $range, 2);
            if (!$bounds || count($bounds) !== 2) {
                continue;
            }

            $startTs = parse_time_label_to_timestamp($bounds[0]);
            $endTs = parse_time_label_to_timestamp($bounds[1]);
            if ($startTs === null || $endTs === null || $endTs <= $startTs) {
                continue;
            }

            // Convert arbitrary times to occupied 1-hour slots in 8AM-5PM window.
            for ($hour = 8; $hour <= 16; $hour++) {
                $slotStart = strtotime($hour . ':00');
                $slotEnd = strtotime(($hour + 1) . ':00');
                if (max($startTs, $slotStart) < min($endTs, $slotEnd)) {
                    $result[$normalizedDay][] = $hour;
                }
            }
        }
    }

    foreach ($result as $k => $hours) {
        $hours = array_values(array_unique(array_map('intval', $hours)));
        sort($hours);
        $result[$k] = $hours;
    }

    return $result;
}

function save_student_class_blocks(mysqli $mysqli, string $studentId, array $classHoursByDay): void {
    $del = $mysqli->prepare('DELETE FROM student_class_blocks WHERE student_id = ?');
    if ($del) {
        $del->bind_param('s', $studentId);
        $del->execute();
        $del->close();
    }

    $ins = $mysqli->prepare('INSERT INTO student_class_blocks (student_id, day_key, hour_start, hour_end) VALUES (?, ?, ?, ?)');
    if (!$ins) {
        return;
    }

    foreach ($classHoursByDay as $dayKey => $hours) {
        foreach ($hours as $hourStart) {
            if ($hourStart < 8 || $hourStart > 16) {
                continue;
            }
            $hourEnd = $hourStart + 1;
            $ins->bind_param('ssii', $studentId, $dayKey, $hourStart, $hourEnd);
            $ins->execute();
        }
    }

    $ins->close();
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

ensure_student_class_blocks_table($mysqli);
$students = fetch_latest_students($mysqli);
$message = '';
$messageType = 'success';
$generatedSchedule = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentId = trim((string) ($_POST['student_id'] ?? ''));
    $scheduleText = trim((string) ($_POST['class_schedule_text'] ?? ''));

    if ($studentId === '' || $scheduleText === '') {
        $messageType = 'error';
        $message = 'Please select a student and provide class schedule text.';
    } else {
        $classHours = parse_class_schedule_text($scheduleText);

        $hasData = false;
        foreach ($classHours as $hours) {
            if (!empty($hours)) {
                $hasData = true;
                break;
            }
        }

        if (!$hasData) {
            $messageType = 'error';
            $message = 'Could not parse class times. Use format like: Monday: 11:00AM-1:00PM, 2:40PM-6:00PM | Tuesday: 7:00AM-2:20PM';
        } else {
            save_student_class_blocks($mysqli, $studentId, $classHours);
            $generatedSchedule = build_rule_based_schedule_text($classHours);

            $upd = $mysqli->prepare('UPDATE student_applications SET work_schedule = ? WHERE student_id = ? ORDER BY created_at DESC LIMIT 1');
            if (!$upd) {
                $messageType = 'error';
                $message = 'Database error while saving generated schedule.';
            } else {
                $upd->bind_param('ss', $generatedSchedule, $studentId);
                if ($upd->execute()) {
                    $messageType = 'success';
                    $message = 'Backfill complete. Class blocks saved and working schedule recomputed.';
                } else {
                    $messageType = 'error';
                    $message = 'Failed to save generated schedule.';
                }
                $upd->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Backfill Old Student Class Blocks</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f7fb; margin: 0; padding: 24px; }
        .card { max-width: 980px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 8px 20px rgba(0,0,0,.08); }
        h1 { margin: 0 0 12px; color: #003087; }
        p { color: #475569; }
        label { display: block; font-weight: 700; margin: 16px 0 8px; }
        select, textarea, button { width: 100%; }
        select, textarea { padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; }
        textarea { min-height: 140px; resize: vertical; }
        button { margin-top: 16px; padding: 12px; border: none; border-radius: 8px; background: #155DFC; color: #fff; font-weight: 700; cursor: pointer; }
        .msg { margin-top: 12px; padding: 10px; border-radius: 8px; }
        .msg.success { background: #dcfce7; color: #166534; }
        .msg.error { background: #fee2e2; color: #991b1b; }
        .preview { margin-top: 14px; padding: 10px; border-radius: 8px; background: #eff6ff; color: #1e3a8a; }
        .back { display: inline-block; margin-bottom: 12px; color: #155DFC; text-decoration: none; font-weight: 700; }
    </style>
</head>
<body>
    <div class="card">
        <a class="back" href="scheduling.php">Back to Schedule Management</a>
        <h1>Backfill Old Student Class Blocks</h1>
        <p>Use this for old records. Paste class schedule text, save class blocks, and auto-recompute working schedule.</p>

        <form method="post" action="">
            <label for="student_id">Student</label>
            <select name="student_id" id="student_id" required>
                <option value="">Select student</option>
                <?php foreach ($students as $row): ?>
                    <option value="<?php echo htmlspecialchars((string) $row['student_id']); ?>">
                        <?php echo htmlspecialchars((string) $row['full_name'] . ' (' . (string) $row['student_id'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="class_schedule_text">Class Schedule Text</label>
            <textarea name="class_schedule_text" id="class_schedule_text" placeholder="Monday: 11:00AM-1:00PM, 2:40PM-6:00PM | Tuesday: 7:00AM-2:20PM | Thursday: 11:00AM-1:00PM, 2:40PM-6:00PM | Friday: 7:00AM-2:20PM" required></textarea>

            <button type="submit">Backfill and Recompute</button>
        </form>

        <?php if ($message !== ''): ?>
            <div class="msg <?php echo $messageType === 'success' ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($generatedSchedule !== ''): ?>
            <div class="preview">
                <strong>Generated Work Schedule:</strong><br>
                <?php echo htmlspecialchars($generatedSchedule); ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
