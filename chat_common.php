<?php
// chat_common.php - shared chat helpers for student/admin messaging

function chat_ensure_table(mysqli $mysqli): void {
    $sql = "CREATE TABLE IF NOT EXISTS chat_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(100) NOT NULL,
        sender_role ENUM('student','admin') NOT NULL,
        sender_name VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        read_by_student TINYINT(1) NOT NULL DEFAULT 0,
        read_by_admin TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_student_created (student_id, created_at),
        INDEX idx_admin_read (read_by_admin, created_at),
        INDEX idx_student_read (read_by_student, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $mysqli->query($sql);
}

function chat_trim_message(string $message): string {
    return trim(preg_replace('/\s+/', ' ', $message));
}

function chat_format_time(?string $value): string {
    if ($value === null || trim($value) === '') {
        return '-';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('M j, g:i A', $timestamp) : '-';
}

function chat_get_student_context(mysqli $mysqli, string $student_id): array {
    $context = [
        'student_id' => $student_id,
        'full_name' => '',
        'email' => '',
        'work_location' => '',
        'application_status' => 'pending',
    ];

    $stmt = $mysqli->prepare('SELECT full_name, email, work_location, application_status FROM student_applications WHERE student_id = ? ORDER BY created_at DESC LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('s', $student_id);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($row = $result ? $result->fetch_assoc() : null) {
                $context['full_name'] = (string) ($row['full_name'] ?? '');
                $context['email'] = (string) ($row['email'] ?? '');
                $context['work_location'] = (string) ($row['work_location'] ?? '');
                $context['application_status'] = strtolower(trim((string) ($row['application_status'] ?? 'pending')));
            }
            $result && $result->free();
        }
        $stmt->close();
    }

    return $context;
}

function chat_send_message(mysqli $mysqli, string $student_id, string $sender_role, string $sender_name, string $message): bool {
    $sender_role = $sender_role === 'admin' ? 'admin' : 'student';
    $message = chat_trim_message($message);

    if ($student_id === '' || $sender_name === '' || $message === '') {
        return false;
    }

    $read_by_student = $sender_role === 'admin' ? 0 : 1;
    $read_by_admin = $sender_role === 'student' ? 0 : 1;

    $stmt = $mysqli->prepare('INSERT INTO chat_messages (student_id, sender_role, sender_name, message, read_by_student, read_by_admin) VALUES (?, ?, ?, ?, ?, ?)');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ssssii', $student_id, $sender_role, $sender_name, $message, $read_by_student, $read_by_admin);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

function chat_fetch_thread(mysqli $mysqli, string $student_id): array {
    $messages = [];
    $stmt = $mysqli->prepare('SELECT sender_role, sender_name, message, created_at FROM chat_messages WHERE student_id = ? ORDER BY created_at ASC, id ASC');
    if ($stmt) {
        $stmt->bind_param('s', $student_id);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result ? $result->fetch_assoc() : null) {
                if ($row === null) {
                    break;
                }

                $messages[] = [
                    'sender_role' => (string) ($row['sender_role'] ?? 'student'),
                    'sender_name' => (string) ($row['sender_name'] ?? ''),
                    'message' => (string) ($row['message'] ?? ''),
                    'created_at' => (string) ($row['created_at'] ?? ''),
                ];
            }
            $result && $result->free();
        }
        $stmt->close();
    }

    return $messages;
}

function chat_fetch_student_threads(mysqli $mysqli): array {
    $threads = [];
    $sql = "SELECT sa.student_id, sa.full_name, sa.email, sa.work_location,
                   MAX(cm.created_at) AS last_message_at,
                   SUM(CASE WHEN cm.sender_role = 'student' AND cm.read_by_admin = 0 THEN 1 ELSE 0 END) AS unread_count,
                   MAX(cm.message) AS last_message
            FROM student_applications sa
            LEFT JOIN chat_messages cm ON cm.student_id = sa.student_id
            INNER JOIN (
                SELECT student_id, MAX(created_at) AS latest_created_at
                FROM student_applications
                GROUP BY student_id
            ) latest ON latest.student_id = sa.student_id AND latest.latest_created_at = sa.created_at
            WHERE sa.application_status = 'approved'
            GROUP BY sa.student_id, sa.full_name, sa.email, sa.work_location
            ORDER BY COALESCE(MAX(cm.created_at), sa.created_at) DESC, sa.full_name ASC";

    if ($result = $mysqli->query($sql)) {
        while ($row = $result->fetch_assoc()) {
            $threads[] = [
                'student_id' => (string) ($row['student_id'] ?? ''),
                'full_name' => (string) ($row['full_name'] ?? ''),
                'email' => (string) ($row['email'] ?? ''),
                'work_location' => (string) ($row['work_location'] ?? ''),
                'last_message_at' => (string) ($row['last_message_at'] ?? ''),
                'unread_count' => (int) ($row['unread_count'] ?? 0),
                'last_message' => (string) ($row['last_message'] ?? ''),
            ];
        }
        $result->free();
    }

    return $threads;
}

function chat_mark_read_by_student(mysqli $mysqli, string $student_id): void {
    $stmt = $mysqli->prepare("UPDATE chat_messages SET read_by_student = 1 WHERE student_id = ? AND read_by_student = 0 AND sender_role = 'admin'");
    if ($stmt) {
        $stmt->bind_param('s', $student_id);
        $stmt->execute();
        $stmt->close();
    }
}

function chat_mark_read_by_admin(mysqli $mysqli, string $student_id): void {
    $stmt = $mysqli->prepare("UPDATE chat_messages SET read_by_admin = 1 WHERE student_id = ? AND read_by_admin = 0 AND sender_role = 'student'");
    if ($stmt) {
        $stmt->bind_param('s', $student_id);
        $stmt->execute();
        $stmt->close();
    }
}
