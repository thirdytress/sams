<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/chat_common.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$role = '';
$userId = '';
$userName = '';

if (isset($_SESSION['admin_id'])) {
    $role = 'admin';
    $userId = (string) $_SESSION['admin_id'];
    $userName = (string) ($_SESSION['admin_name'] ?? 'Admin');
} elseif (isset($_SESSION['student_id'])) {
    $role = 'student';
    $userId = (string) $_SESSION['student_id'];
    $userName = (string) ($_SESSION['student_name'] ?? 'Student');
} else {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthenticated']);
    exit;
}

$payload = [
    'ok' => true,
    'role' => $role,
    'timestamp' => time(),
];

if ($role === 'admin') {
    chat_ensure_table($mysqli);

    $payload['counts'] = [
        'applications' => 0,
        'pending_applications' => 0,
        'students' => 0,
        'attendance_today' => 0,
        'unread_reports' => 0,
        'unread_messages' => 0,
    ];

    if ($res = $mysqli->query('SELECT COUNT(*) AS c FROM student_applications')) {
        $row = $res->fetch_assoc();
        $payload['counts']['applications'] = (int) ($row['c'] ?? 0);
        $res->free();
    }

    if ($res = $mysqli->query("SELECT COUNT(*) AS c FROM student_applications WHERE LOWER(COALESCE(application_status, 'pending')) = 'pending'")) {
        $row = $res->fetch_assoc();
        $payload['counts']['pending_applications'] = (int) ($row['c'] ?? 0);
        $res->free();
    }

    if ($res = $mysqli->query("SELECT COUNT(*) AS c FROM student_applications WHERE LOWER(COALESCE(application_status, 'pending')) = 'approved'")) {
        $row = $res->fetch_assoc();
        $payload['counts']['students'] = (int) ($row['c'] ?? 0);
        $res->free();
    }

    if ($res = $mysqli->query('SELECT COUNT(*) AS c FROM attendance WHERE DATE(check_in_time) = CURDATE()')) {
        $row = $res->fetch_assoc();
        $payload['counts']['attendance_today'] = (int) ($row['c'] ?? 0);
        $res->free();
    }

    if ($res = $mysqli->query('SELECT COUNT(*) AS c FROM student_reports WHERE is_read = 0')) {
        $row = $res->fetch_assoc();
        $payload['counts']['unread_reports'] = (int) ($row['c'] ?? 0);
        $res->free();
    }

    if ($res = $mysqli->query("SELECT COUNT(*) AS c FROM chat_messages WHERE sender_role = 'student' AND read_by_admin = 0")) {
        $row = $res->fetch_assoc();
        $payload['counts']['unread_messages'] = (int) ($row['c'] ?? 0);
        $res->free();
    }

    $payload['recent_reports'] = [];
    if ($res = $mysqli->query('SELECT student_name, student_id, report_type, subject, is_read, created_at FROM student_reports ORDER BY created_at DESC LIMIT 5')) {
        while ($row = $res->fetch_assoc()) {
            $payload['recent_reports'][] = $row;
        }
        $res->free();
    }

    $payload['recent_messages'] = [];
    if ($res = $mysqli->query("SELECT student_id, sender_role, sender_name, message, created_at FROM chat_messages ORDER BY created_at DESC, id DESC LIMIT 5")) {
        while ($row = $res->fetch_assoc()) {
            $payload['recent_messages'][] = $row;
        }
        $res->free();
    }
} else {
    chat_ensure_table($mysqli);

    $payload['counts'] = [
        'unread_reports' => 0,
        'unread_messages' => 0,
        'attendance_today' => 0,
    ];

    if ($stmt = $mysqli->prepare('SELECT COUNT(*) AS c FROM student_reports WHERE student_id = ? AND is_read = 0')) {
        $stmt->bind_param('s', $userId);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $payload['counts']['unread_reports'] = (int) ($row['c'] ?? 0);
            $res && $res->free();
        }
        $stmt->close();
    }

    if ($stmt = $mysqli->prepare("SELECT COUNT(*) AS c FROM chat_messages WHERE student_id = ? AND sender_role = 'admin' AND read_by_student = 0")) {
        $stmt->bind_param('s', $userId);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $payload['counts']['unread_messages'] = (int) ($row['c'] ?? 0);
            $res && $res->free();
        }
        $stmt->close();
    }

    if ($stmt = $mysqli->prepare('SELECT COUNT(*) AS c FROM attendance WHERE student_id = ? AND DATE(check_in_time) = CURDATE()')) {
        $stmt->bind_param('s', $userId);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $payload['counts']['attendance_today'] = (int) ($row['c'] ?? 0);
            $res && $res->free();
        }
        $stmt->close();
    }

    if ($stmt = $mysqli->prepare('SELECT work_schedule, work_location, application_status FROM student_applications WHERE student_id = ? ORDER BY created_at DESC LIMIT 1')) {
        $stmt->bind_param('s', $userId);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            $payload['student_context'] = $res ? $res->fetch_assoc() : null;
            $res && $res->free();
        }
        $stmt->close();
    }

    $payload['recent_messages'] = [];
    if ($stmt = $mysqli->prepare('SELECT sender_role, sender_name, message, created_at FROM chat_messages WHERE student_id = ? ORDER BY created_at DESC, id DESC LIMIT 5')) {
        $stmt->bind_param('s', $userId);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($row = $res ? $res->fetch_assoc() : null) {
                if ($row === null) {
                    break;
                }
                $payload['recent_messages'][] = $row;
            }
            $res && $res->free();
        }
        $stmt->close();
    }
}

echo json_encode($payload, JSON_UNESCAPED_SLASHES);
