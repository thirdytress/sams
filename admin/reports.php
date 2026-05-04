<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../db.php';

$admin_name = (string) ($_SESSION['admin_name'] ?? 'Admin');
$admin_role = (string) ($_SESSION['admin_role'] ?? 'SDAO Head');
$department = (string) ($_SESSION['department'] ?? 'NU Lipa - Student Development and Activities Office');

$mysqli->query("CREATE TABLE IF NOT EXISTS alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(150) NOT NULL,
    body TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

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

$students = [];
if ($res = $mysqli->query("SELECT DISTINCT student_id, full_name FROM student_applications ORDER BY full_name ASC")) {
    while ($row = $res->fetch_assoc()) {
        $students[] = $row;
    }
    $res->free();
}

$errors = [];
$sent = isset($_GET['sent']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_report') {
    $student_id = trim((string) ($_POST['student_id'] ?? ''));
    $report_type = trim((string) ($_POST['report_type'] ?? 'Concern'));
    $subject = trim((string) ($_POST['subject'] ?? ''));
    $report_body = trim((string) ($_POST['report_body'] ?? ''));

    $student_name = '';

    if ($student_id === '') {
        $errors[] = 'Please select a student.';
    }
    if ($subject === '') {
        $errors[] = 'Subject is required.';
    }
    if ($report_body === '') {
        $errors[] = 'Report details are required.';
    }

    if (!$errors) {
        $st = $mysqli->prepare('SELECT full_name FROM student_applications WHERE student_id = ? ORDER BY created_at DESC LIMIT 1');
        if ($st) {
            $st->bind_param('s', $student_id);
            $st->execute();
            $result = $st->get_result();
            if ($row = $result ? $result->fetch_assoc() : null) {
                $student_name = (string) ($row['full_name'] ?? '');
            }
            $result && $result->free();
            $st->close();
        }

        if ($student_name === '') {
            $errors[] = 'Selected student was not found in the system.';
        } else {
            $created_by = $admin_name;
            $ins = $mysqli->prepare('INSERT INTO student_reports (student_id, student_name, report_type, subject, report_body, created_by, is_read, created_at) VALUES (?, ?, ?, ?, ?, ?, 0, NOW())');
            if ($ins) {
                $ins->bind_param('ssssss', $student_id, $student_name, $report_type, $subject, $report_body, $created_by);
                if ($ins->execute()) {
                    $alertTitle = 'New report sent to ' . $student_name;
                    $alertBody = $subject . ' - ' . $report_type;
                    if ($alert = $mysqli->prepare('INSERT INTO alerts (type, title, body, created_at) VALUES (?, ?, ?, NOW())')) {
                        $type = 'info';
                        $alert->bind_param('sss', $type, $alertTitle, $alertBody);
                        $alert->execute();
                        $alert->close();
                    }
                    header('Location: reports.php?sent=1');
                    exit;
                }
                $errors[] = 'Unable to save the report.';
                $ins->close();
            } else {
                $errors[] = 'Database error while creating the report.';
            }
        }
    }
}

$totalReports = 0;
$unreadReports = 0;
$todayReports = 0;
$studentsReported = 0;

if ($res = $mysqli->query('SELECT COUNT(*) AS c FROM student_reports')) {
    $row = $res->fetch_assoc();
    $totalReports = (int) ($row['c'] ?? 0);
    $res->free();
}
if ($res = $mysqli->query('SELECT COUNT(*) AS c FROM student_reports WHERE is_read = 0')) {
    $row = $res->fetch_assoc();
    $unreadReports = (int) ($row['c'] ?? 0);
    $res->free();
}
if ($res = $mysqli->query('SELECT COUNT(*) AS c FROM student_reports WHERE DATE(created_at) = CURDATE()')) {
    $row = $res->fetch_assoc();
    $todayReports = (int) ($row['c'] ?? 0);
    $res->free();
}
if ($res = $mysqli->query('SELECT COUNT(DISTINCT student_id) AS c FROM student_reports')) {
    $row = $res->fetch_assoc();
    $studentsReported = (int) ($row['c'] ?? 0);
    $res->free();
}

$recentReports = [];
if ($res = $mysqli->query('SELECT student_name, student_id, report_type, subject, report_body, is_read, created_at, read_at, created_by FROM student_reports ORDER BY created_at DESC LIMIT 12')) {
    while ($row = $res->fetch_assoc()) {
        $recentReports[] = $row;
    }
    $res->free();
}

function report_badge_class(string $type): string {
    $type = strtolower($type);
    if ($type === 'warning') return 'badge--warning';
    if ($type === 'reminder') return 'badge--reminder';
    if ($type === 'incident') return 'badge--incident';
    return 'badge--concern';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <script src="../assets/realtime.js"></script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>NU SA System – Reports</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;900&display=swap" rel="stylesheet" />
    <style>
        :root {
            --clr-white: #ffffff;
            --clr-bg: #f8fafc;
            --clr-card: #ffffff;
            --clr-border: #e5e7eb;
            --clr-text: #101828;
            --clr-body: #4a5565;
            --clr-muted: #6a7282;
            --clr-blue: #155dfc;
            --clr-blue-bg: #dbeafe;
            --clr-green: #00a63e;
            --clr-green-bg: #dcfce7;
            --clr-yellow: #a65f00;
            --clr-yellow-bg: #fef9c2;
            --clr-red-bg: #fee2e2;
            --shadow: 0 10px 25px rgba(15, 23, 42, .08);
            --radius: 18px;
            --sidebar-width: 256px;
        }
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; font-family: Inter, sans-serif; background: linear-gradient(180deg, #eff6ff 0%, #f8fafc 26%, #fff 100%); color: var(--clr-text); }
        a { color: inherit; text-decoration: none; }
        button, select, input, textarea { font: inherit; }
        .shell { display: flex; min-height: 100vh; }
        .sidebar { width: var(--sidebar-width); background: var(--clr-white); border-right: 1px solid var(--clr-border); position: sticky; top: 0; height: 100vh; display: flex; flex-direction: column; }
        .sidebar__brand { padding: 24px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid var(--clr-border); }
        .sidebar__logo { width: 40px; height: 40px; border-radius: 12px; background: linear-gradient(135deg, #155dfc, #9810fa); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 800; }
        .sidebar__nav { padding: 16px; display: flex; flex-direction: column; gap: 6px; flex: 1; }
        .sidebar__nav-link { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border-radius: 12px; color: var(--clr-body); font-size: 15px; }
        .sidebar__nav-link:hover { background: #f8fafc; }
        .sidebar__nav-link--active { background: var(--clr-blue); color: var(--clr-white); }
        .sidebar__nav-icon { width: 20px; height: 20px; flex-shrink: 0; }
        .sidebar__nav-badge { margin-left: auto; padding: 2px 8px; border-radius: 999px; background: var(--clr-blue-bg); color: var(--clr-blue); font-size: 12px; font-weight: 700; }
        .sidebar__footer { padding: 16px; border-top: 1px solid var(--clr-border); display: flex; flex-direction: column; gap: 6px; }
        .main { flex: 1; min-width: 0; }
        .topbar { height: 88px; background: rgba(255,255,255,.8); backdrop-filter: blur(16px); border-bottom: 1px solid var(--clr-border); display: flex; align-items: center; justify-content: space-between; padding: 0 32px; position: sticky; top: 0; z-index: 20; }
        .topbar__title { font-size: 24px; font-weight: 900; }
        .topbar__sub { color: var(--clr-muted); font-size: 14px; }
        .topbar__right { display: flex; align-items: center; gap: 12px; }
        .topbar__avatar { width: 40px; height: 40px; border-radius: 999px; background: linear-gradient(135deg, #155dfc, #9810fa); display: flex; align-items: center; justify-content: center; }
        .page { padding: 32px; display: flex; flex-direction: column; gap: 24px; }
        .hero { background: linear-gradient(135deg, #155dfc 0%, #7c3aed 100%); color: #fff; border-radius: 24px; padding: 28px; box-shadow: var(--shadow); display: flex; justify-content: space-between; gap: 20px; align-items: center; }
        .hero__title { font-size: 32px; font-weight: 900; margin: 0 0 8px; }
        .hero__sub { margin: 0; color: rgba(255,255,255,.88); max-width: 720px; line-height: 1.5; }
        .hero__pill { padding: 10px 14px; border-radius: 999px; background: rgba(255,255,255,.15); font-weight: 700; white-space: nowrap; }
        .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
        .stat { background: var(--clr-card); border: 1px solid var(--clr-border); border-radius: var(--radius); padding: 18px; box-shadow: var(--shadow); }
        .stat__label { color: var(--clr-muted); font-size: 13px; margin-bottom: 8px; }
        .stat__value { font-size: 28px; font-weight: 900; }
        .grid { display: grid; grid-template-columns: 1.05fr .95fr; gap: 24px; align-items: start; }
        .card { background: var(--clr-card); border: 1px solid var(--clr-border); border-radius: var(--radius); box-shadow: var(--shadow); overflow: hidden; }
        .card__head { padding: 22px 24px; border-bottom: 1px solid var(--clr-border); background: linear-gradient(135deg, #f8fafc 0%, #fff 100%); }
        .card__title { margin: 0; font-size: 18px; font-weight: 800; }
        .card__sub { margin: 6px 0 0; color: var(--clr-muted); font-size: 14px; }
        .form { padding: 24px; display: grid; gap: 16px; }
        .field label { display: block; margin-bottom: 8px; color: var(--clr-text); font-size: 14px; font-weight: 700; }
        .field select, .field input, .field textarea { width: 100%; border: 1px solid var(--clr-border); border-radius: 14px; padding: 12px 14px; background: #fff; color: var(--clr-body); }
        .field textarea { min-height: 160px; resize: vertical; }
        .btn { border: 0; border-radius: 14px; padding: 14px 18px; font-weight: 800; cursor: pointer; }
        .btn--primary { background: linear-gradient(135deg, #155dfc 0%, #7c3aed 100%); color: #fff; box-shadow: 0 12px 24px rgba(21, 93, 252, .28); }
        .notice { padding: 14px 16px; border-radius: 14px; margin: 0; }
        .notice--success { background: var(--clr-green-bg); color: var(--clr-green); }
        .notice--error { background: var(--clr-red-bg); color: #b91c1c; }
        .timeline { display: flex; flex-direction: column; gap: 14px; padding: 24px; }
        .report { border: 1px solid var(--clr-border); border-radius: 18px; padding: 18px; background: linear-gradient(180deg, #fff 0%, #fbfdff 100%); }
        .report__top { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; margin-bottom: 10px; }
        .report__student { font-weight: 900; }
        .report__meta { color: var(--clr-muted); font-size: 13px; margin-top: 4px; }
        .badge { display: inline-flex; align-items: center; padding: 6px 10px; border-radius: 999px; font-size: 12px; font-weight: 800; }
        .badge--concern { background: var(--clr-yellow-bg); color: var(--clr-yellow); }
        .badge--warning { background: #fee2e2; color: #b91c1c; }
        .badge--reminder { background: var(--clr-blue-bg); color: var(--clr-blue); }
        .badge--incident { background: #f3e8ff; color: #7c3aed; }
        .badge--read { background: var(--clr-green-bg); color: var(--clr-green); }
        .badge--unread { background: #fee2e2; color: #b91c1c; }
        .report__subject { margin: 0 0 8px; font-size: 16px; font-weight: 800; }
        .report__body { margin: 0; color: var(--clr-body); line-height: 1.55; white-space: pre-wrap; }
        .report__actions { margin-top: 14px; display: flex; gap: 10px; flex-wrap: wrap; }
        .mini-link { padding: 9px 12px; border-radius: 12px; border: 1px solid var(--clr-border); color: var(--clr-text); font-size: 13px; font-weight: 700; }
        .empty { padding: 28px 24px; color: var(--clr-muted); }
        .hamburger { display: none; }
        @media (max-width: 1024px) { .sidebar { position: fixed; transform: translateX(-100%); transition: transform .28s ease; z-index: 30; } .sidebar--open { transform: translateX(0); } .hamburger { display: inline-flex; } .stats, .grid { grid-template-columns: 1fr; } .topbar { padding: 0 16px; } .page { padding: 16px; } .hero { flex-direction: column; align-items: flex-start; } }
    </style>
</head>
<body>
<div class="shell">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar__brand"><div class="sidebar__logo">NU</div><div><div style="font-weight:800;">SAMS</div><div style="color:var(--clr-muted);font-size:12px;">Admin Panel</div></div></div>
        <nav class="sidebar__nav">
            <a class="sidebar__nav-link" href="dashboard.php"><svg class="sidebar__nav-icon" viewBox="0 0 20 20" fill="none"><path d="M2.5 8L10 2.5L17.5 8V17.5H12.5V12.5H7.5V17.5H2.5V8Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>Dashboard</a>
            <a class="sidebar__nav-link" href="application.php"><svg class="sidebar__nav-icon" viewBox="0 0 20 20" fill="none"><path d="M6 2h8a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V4a2 2 0 012-2z" stroke="currentColor" stroke-width="1.5"/><path d="M7 7h6M7 10h6M7 13h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>Applications</a>
            <a class="sidebar__nav-link" href="scheduling.php"><svg class="sidebar__nav-icon" viewBox="0 0 20 20" fill="none"><rect x="2" y="4" width="16" height="14" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M6 2v4M14 2v4M2 9h16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>Scheduling</a>
            <a class="sidebar__nav-link" href="attendance.php"><svg class="sidebar__nav-icon" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="1.5"/><path d="M6.5 10.5l2.5 2.5 4.5-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>Attendance</a>
            <a class="sidebar__nav-link" href="chat.php"><svg class="sidebar__nav-icon" viewBox="0 0 20 20" fill="none"><path d="M4 4h12a2 2 0 012 2v6a2 2 0 01-2 2H9l-4 3v-3H4a2 2 0 01-2-2V6a2 2 0 012-2z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>Messages</a>
            <a class="sidebar__nav-link" href="documents.php"><svg class="sidebar__nav-icon" viewBox="0 0 20 20" fill="none"><path d="M5 2h7l4 4v12a1 1 0 01-1 1H5a1 1 0 01-1-1V3a1 1 0 011-1z" stroke="currentColor" stroke-width="1.5"/><path d="M12 2v4h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>Documents</a>
            <a class="sidebar__nav-link" href="evaluation.php"><svg class="sidebar__nav-icon" viewBox="0 0 20 20" fill="none"><path d="M4 10h12M4 5h12M4 15h8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>Evaluation</a>
            <a class="sidebar__nav-link sidebar__nav-link--active" href="reports.php" aria-current="page"><svg class="sidebar__nav-icon" viewBox="0 0 20 20" fill="none"><rect x="2.5" y="2.5" width="15" height="15" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M6 14V10M10 14V7M14 14V11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>Reports</a>
            <a class="sidebar__nav-link" href="students.php"><svg class="sidebar__nav-icon" viewBox="0 0 20 20" fill="none"><path d="M10 10a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM17.5 17.5c0-4.14-3.36-7.5-7.5-7.5S2.5 13.36 2.5 17.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>Students</a>
        </nav>
        <div class="sidebar__footer">
            <a class="sidebar__nav-link" href="settings.php">Settings</a>
            <a class="sidebar__nav-link" href="logout.php">Sign Out</a>
        </div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div style="display:flex;align-items:center;gap:12px;">
                <button class="hamburger" id="hamburgerBtn" type="button" aria-label="Toggle navigation" aria-expanded="false">☰</button>
                <div>
                    <div class="topbar__title">Reports</div>
                    <div class="topbar__sub"><?= htmlspecialchars($department) ?></div>
                </div>
            </div>
            <div class="topbar__right">
                <div style="text-align:right;">
                    <div style="font-weight:800;"><?= htmlspecialchars($admin_name) ?></div>
                    <div style="font-size:12px;color:var(--clr-muted);"><?= htmlspecialchars($admin_role) ?></div>
                </div>
                <div class="topbar__avatar" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M10 10a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM17.5 17.5c0-4.14-3.36-7.5-7.5-7.5S2.5 13.36 2.5 17.5" stroke="white" stroke-width="1.5" stroke-linecap="round"/></svg></div>
            </div>
        </header>

        <section class="page">
            <div class="hero">
                <div>
                    <h1 class="hero__title">Student Report Center</h1>
                    <p class="hero__sub">Send feedback, warnings, and concerns directly to a student. The report appears in the student inbox and is marked unread until they open it.</p>
                </div>
                <div class="hero__pill"><?= number_format($unreadReports) ?> unread notifications</div>
            </div>

            <?php if ($sent): ?>
                <div class="notice notice--success">Report sent successfully. The student will see it in their report inbox.</div>
            <?php endif; ?>
            <?php if ($errors): ?>
                <div class="notice notice--error"><?= htmlspecialchars(implode(' ', $errors)) ?></div>
            <?php endif; ?>

            <div class="stats">
                <div class="stat"><div class="stat__label">Total Reports</div><div class="stat__value"><?= number_format($totalReports) ?></div></div>
                <div class="stat"><div class="stat__label">Unread by Students</div><div class="stat__value"><?= number_format($unreadReports) ?></div></div>
                <div class="stat"><div class="stat__label">Sent Today</div><div class="stat__value"><?= number_format($todayReports) ?></div></div>
                <div class="stat"><div class="stat__label">Students Reported</div><div class="stat__value"><?= number_format($studentsReported) ?></div></div>
            </div>

            <div class="grid">
                <section class="card">
                    <div class="card__head">
                        <h2 class="card__title">Create Student Report</h2>
                        <p class="card__sub">Choose a student, set the report type, and write the message clearly.</p>
                    </div>
                    <form class="form" method="post" action="reports.php">
                        <input type="hidden" name="action" value="create_report" />
                        <div class="field">
                            <label for="student_id">Student</label>
                            <select name="student_id" id="student_id" required>
                                <option value="">Select a student</option>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?= htmlspecialchars($student['student_id'] ?? '') ?>"><?= htmlspecialchars($student['full_name'] ?? '') ?> (<?= htmlspecialchars($student['student_id'] ?? '') ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label for="report_type">Report Type</label>
                            <select name="report_type" id="report_type" required>
                                <option>Concern</option>
                                <option>Warning</option>
                                <option>Reminder</option>
                                <option>Incident</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="subject">Subject</label>
                            <input type="text" id="subject" name="subject" placeholder="e.g. Attendance issue this week" required />
                        </div>
                        <div class="field">
                            <label for="report_body">Report Details</label>
                            <textarea id="report_body" name="report_body" placeholder="Write the full report here..." required></textarea>
                        </div>
                        <button class="btn btn--primary" type="submit">Send Report to Student</button>
                    </form>
                </section>

                <section class="card">
                    <div class="card__head">
                        <h2 class="card__title">Recent Reports</h2>
                        <p class="card__sub">Latest communication sent to students</p>
                    </div>
                    <div class="timeline">
                        <?php if (!$recentReports): ?>
                            <div class="empty">No reports sent yet.</div>
                        <?php endif; ?>
                        <?php foreach ($recentReports as $report): ?>
                            <?php $badgeClass = report_badge_class((string) ($report['report_type'] ?? 'concern')); ?>
                            <article class="report">
                                <div class="report__top">
                                    <div>
                                        <div class="report__student"><?= htmlspecialchars($report['student_name'] ?? '') ?></div>
                                        <div class="report__meta">ID: <?= htmlspecialchars($report['student_id'] ?? '') ?> · <?= htmlspecialchars(date('M j, Y g:i A', strtotime((string) ($report['created_at'] ?? 'now')))) ?></div>
                                    </div>
                                    <div class="badge <?= $badgeClass ?>"><?= htmlspecialchars(strtoupper((string) ($report['report_type'] ?? 'Concern'))) ?></div>
                                </div>
                                <div class="report__subject"><?= htmlspecialchars($report['subject'] ?? '') ?></div>
                                <p class="report__body"><?= htmlspecialchars($report['report_body'] ?? '') ?></p>
                                <div class="report__actions">
                                    <span class="badge <?= !empty($report['is_read']) ? 'badge--read' : 'badge--unread' ?>"><?= !empty($report['is_read']) ? 'Seen by student' : 'Unread' ?></span>
                                    <span class="mini-link">Sent by <?= htmlspecialchars($report['created_by'] ?? $admin_name) ?></span>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>
        </section>
    </main>
</div>

<script>
(function () {
    var hamburger = document.getElementById('hamburgerBtn');
    var sidebar = document.getElementById('sidebar');
    if (!hamburger || !sidebar) return;
    hamburger.addEventListener('click', function () {
        sidebar.classList.toggle('sidebar--open');
        hamburger.setAttribute('aria-expanded', sidebar.classList.contains('sidebar--open') ? 'true' : 'false');
    });
})();
</script>
</body>
</html>
