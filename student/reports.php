<?php
session_start();

if (!isset($_SESSION['student_id']) || !isset($_SESSION['student_name'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../db.php';

$student_id = (string) $_SESSION['student_id'];
$student_name = (string) $_SESSION['student_name'];
$campus = 'National University - Lipa Campus';

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

if ($stmt = $mysqli->prepare('UPDATE student_reports SET is_read = 1, read_at = IF(read_at IS NULL, NOW(), read_at) WHERE student_id = ? AND is_read = 0')) {
    $stmt->bind_param('s', $student_id);
    $stmt->execute();
    $stmt->close();
}

$unread = 0;
if ($stmt = $mysqli->prepare('SELECT COUNT(*) AS c FROM student_reports WHERE student_id = ? AND is_read = 0')) {
    $stmt->bind_param('s', $student_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res ? $res->fetch_assoc() : null) {
        $unread = (int) ($row['c'] ?? 0);
    }
    $res && $res->free();
    $stmt->close();
}

$reports = [];
if ($stmt = $mysqli->prepare('SELECT report_type, subject, report_body, created_by, is_read, created_at, read_at FROM student_reports WHERE student_id = ? ORDER BY created_at DESC')) {
    $stmt->bind_param('s', $student_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res ? $res->fetch_assoc() : null) {
        if ($row === null) {
            break;
        }
        $reports[] = $row;
    }
    $res && $res->free();
    $stmt->close();
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
    <title>My Reports | NU SAMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;900&display=swap" rel="stylesheet" />
    <style>
        :root { --bg:#f8fafc; --card:#fff; --border:#e5e7eb; --text:#101828; --body:#4a5565; --muted:#6a7282; --blue:#155dfc; --blue-bg:#dbeafe; --green:#00a63e; --green-bg:#dcfce7; --yellow:#a65f00; --yellow-bg:#fef9c2; --shadow:0 10px 25px rgba(15,23,42,.08); --radius:18px; --sidebar-width:256px; }
+        *,*::before,*::after{box-sizing:border-box} body{margin:0;font-family:Inter,sans-serif;background:linear-gradient(180deg,#eff6ff 0%,#f8fafc 26%,#fff 100%);color:var(--text)} a{text-decoration:none;color:inherit}
+        .shell{display:flex;min-height:100vh}.sidebar{width:var(--sidebar-width);background:#fff;border-right:1px solid var(--border);position:sticky;top:0;height:100vh;display:flex;flex-direction:column}.sidebar__brand{padding:24px;display:flex;align-items:center;gap:12px;border-bottom:1px solid var(--border)}.sidebar__logo{width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,#155dfc,#9810fa);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800}.sidebar__nav{padding:16px;display:flex;flex-direction:column;gap:6px;flex:1}.sidebar__nav-link{display:flex;align-items:center;gap:12px;padding:12px 14px;border-radius:12px;color:var(--body);font-size:15px}.sidebar__nav-link:hover{background:#f8fafc}.sidebar__nav-link--active{background:var(--blue);color:#fff}.sidebar__nav-badge{margin-left:auto;padding:2px 8px;border-radius:999px;background:var(--blue-bg);color:var(--blue);font-size:12px;font-weight:700}.sidebar__footer{padding:16px;border-top:1px solid var(--border);display:flex;flex-direction:column;gap:6px}.main{flex:1;min-width:0}.topbar{height:88px;background:rgba(255,255,255,.8);backdrop-filter:blur(16px);border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;padding:0 32px;position:sticky;top:0;z-index:20}.topbar__title{font-size:24px;font-weight:900}.topbar__sub{color:var(--muted);font-size:14px}.topbar__right{display:flex;align-items:center;gap:12px}.topbar__avatar{width:40px;height:40px;border-radius:999px;background:linear-gradient(135deg,#155dfc,#9810fa);display:flex;align-items:center;justify-content:center}.page{padding:32px;display:flex;flex-direction:column;gap:24px}.hero{background:linear-gradient(135deg,#155dfc 0%,#7c3aed 100%);color:#fff;border-radius:24px;padding:28px;box-shadow:var(--shadow)}.hero__title{font-size:32px;font-weight:900;margin:0 0 8px}.hero__sub{margin:0;color:rgba(255,255,255,.88);max-width:760px;line-height:1.5}.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}.stat{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:18px;box-shadow:var(--shadow)}.stat__label{color:var(--muted);font-size:13px;margin-bottom:8px}.stat__value{font-size:28px;font-weight:900}.badge{display:inline-flex;align-items:center;padding:6px 10px;border-radius:999px;font-size:12px;font-weight:800}.badge--concern{background:var(--yellow-bg);color:var(--yellow)}.badge--warning{background:#fee2e2;color:#b91c1c}.badge--reminder{background:var(--blue-bg);color:var(--blue)}.badge--incident{background:#f3e8ff;color:#7c3aed}.badge--read{background:var(--green-bg);color:var(--green)}.badge--unread{background:#fee2e2;color:#b91c1c}.card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden}.card__head{padding:22px 24px;border-bottom:1px solid var(--border);background:linear-gradient(135deg,#f8fafc 0%,#fff 100%)}.card__title{margin:0;font-size:18px;font-weight:800}.card__sub{margin:6px 0 0;color:var(--muted);font-size:14px}.timeline{display:flex;flex-direction:column;gap:14px;padding:24px}.report{border:1px solid var(--border);border-radius:18px;padding:18px;background:linear-gradient(180deg,#fff 0%,#fbfdff 100%)}.report__top{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;margin-bottom:10px}.report__student{font-weight:900}.report__meta{color:var(--muted);font-size:13px;margin-top:4px}.report__subject{margin:0 0 8px;font-size:16px;font-weight:800}.report__body{margin:0;color:var(--body);line-height:1.55;white-space:pre-wrap}.report__actions{margin-top:14px;display:flex;gap:10px;flex-wrap:wrap}.mini-link{padding:9px 12px;border-radius:12px;border:1px solid var(--border);color:var(--text);font-size:13px;font-weight:700}.empty{padding:28px 24px;color:var(--muted)}.hamburger{display:none}.sidebar-overlay{display:none}
+        @media (max-width:1024px){.sidebar{position:fixed;transform:translateX(-100%);transition:transform .28s ease;z-index:30}.sidebar--open{transform:translateX(0)}.hamburger{display:inline-flex}.stats{grid-template-columns:1fr}.topbar{padding:0 16px}.page{padding:16px}.hero{flex-direction:column;align-items:flex-start}}
+    </style>
+</head>
+<body>
+<div class="shell">
+    <aside class="sidebar" id="sidebar">
+        <div class="sidebar__brand"><div class="sidebar__logo">NU</div><div><div style="font-weight:800;">SAMS</div><div style="color:var(--muted);font-size:12px;">Student Portal</div></div></div>
+        <nav class="sidebar__nav">
+            <a class="sidebar__nav-link" href="dashboard.php">Dashboard</a>
+            <a class="sidebar__nav-link" href="schedule.php">My Schedule</a>
+            <a class="sidebar__nav-link" href="attendance.php">Attendance</a>
+            <a class="sidebar__nav-link sidebar__nav-link--active" href="reports.php" aria-current="page">Reports <span class="sidebar__nav-badge"><?= (int) $unread ?></span></a>
+            <a class="sidebar__nav-link" href="profile.php">Profile</a>
+        </nav>
+        <div class="sidebar__footer"><a class="sidebar__nav-link" href="chat.php">Messages</a><a class="sidebar__nav-link" href="logout.php">Logout</a></div>
+    </aside>
+    <main class="main">
+        <header class="topbar"><div><div class="topbar__title">My Reports</div><div class="topbar__sub"><?= htmlspecialchars($campus) ?></div></div><div class="topbar__right"><div style="text-align:right"><div style="font-weight:800;"><?= htmlspecialchars($student_name) ?></div><div style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($student_id) ?></div></div><div class="topbar__avatar" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M10 10a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM17.5 17.5c0-4.14-3.36-7.5-7.5-7.5S2.5 13.36 2.5 17.5" stroke="white" stroke-width="1.5" stroke-linecap="round"/></svg></div></div></header>
+        <section class="page">
+            <div class="hero"><h1 class="hero__title">Reports from SDAO</h1><p class="hero__sub">Review anything the admin sent about your attendance, duties, or requirements. Reports are marked unread until you open this page.</p></div>
+            <div class="stats"><div class="stat"><div class="stat__label">Unread Reports</div><div class="stat__value"><?= number_format($unread) ?></div></div><div class="stat"><div class="stat__label">Total Reports</div><div class="stat__value"><?= number_format(count($reports)) ?></div></div><div class="stat"><div class="stat__label">Latest Notice</div><div class="stat__value" style="font-size:18px;"><?= !empty($reports) ? htmlspecialchars((string) $reports[0]['report_type']) : 'None' ?></div></div><div class="stat"><div class="stat__label">Status</div><div class="stat__value" style="font-size:18px;"><?= $unread > 0 ? 'Action needed' : 'Up to date' ?></div></div></div>
+            <section class="card">
+                <div class="card__head"><h2 class="card__title">Report Inbox</h2><p class="card__sub">Your recent communications from the admin office</p></div>
+                <div class="timeline">
+                    <?php if (!$reports): ?><div class="empty">No reports yet. When the admin sends one, it will appear here.</div><?php endif; ?>
+                    <?php foreach ($reports as $report): ?>
+                        <?php $badgeClass = report_badge_class((string) ($report['report_type'] ?? 'concern')); ?>
+                        <article class="report">
+                            <div class="report__top"><div><div class="report__student"><?= htmlspecialchars($report['subject'] ?? '') ?></div><div class="report__meta">Sent by <?= htmlspecialchars($report['created_by'] ?? 'Admin') ?> · <?= htmlspecialchars(date('M j, Y g:i A', strtotime((string) ($report['created_at'] ?? 'now')))) ?></div></div><div class="badge <?= $badgeClass ?>"><?= htmlspecialchars(strtoupper((string) ($report['report_type'] ?? 'Concern'))) ?></div></div>
+                            <p class="report__body"><?= htmlspecialchars($report['report_body'] ?? '') ?></p>
+                            <div class="report__actions"><span class="badge <?= !empty($report['is_read']) ? 'badge--read' : 'badge--unread' ?>"><?= !empty($report['is_read']) ? 'Seen by student' : 'Unread' ?></span><?php if (!empty($report['read_at'])): ?><span class="mini-link">Opened <?= htmlspecialchars(date('M j, g:i A', strtotime((string) $report['read_at']))) ?></span><?php endif; ?></div>
+                        </article>
+                    <?php endforeach; ?>
+                </div>
+            </section>
+        </section>
+    </main>
+</div>
+</body>
+</html>
