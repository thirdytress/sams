<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$admin_role = $_SESSION['admin_role'] ?? 'SDAO Head';
$department = $_SESSION['department'] ?? 'NU Lipa - Student Development and Activities Office';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Evaluation | NU SA System</title>
    <style>
        :root {
            --bg: #f8fafc;
            --white: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;
            --blue: #1d4ed8;
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Inter, Arial, sans-serif; background: var(--bg); color: var(--text); }
        .shell { display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background: var(--white); border-right: 1px solid var(--border); display: flex; flex-direction: column; }
        .brand { padding: 20px; border-bottom: 1px solid var(--border); font-weight: 800; }
        .nav { padding: 12px; display: flex; flex-direction: column; gap: 6px; flex: 1; }
        .nav a { text-decoration: none; color: #334155; padding: 10px 12px; border-radius: 10px; font-size: 14px; }
        .nav a:hover { background: #f1f5f9; }
        .nav a.active { background: var(--blue); color: #fff; }
        .footer { border-top: 1px solid var(--border); padding: 12px; display: flex; flex-direction: column; gap: 6px; }
        .main { flex: 1; }
        .top { background: var(--white); border-bottom: 1px solid var(--border); padding: 16px 24px; display: flex; justify-content: space-between; }
        .content { padding: 24px; }
        .card { background: var(--white); border: 1px solid var(--border); border-radius: 14px; padding: 20px; }
        .hint { color: var(--muted); }
        @media (max-width: 900px) { .shell { flex-direction: column; } .sidebar { width: 100%; } }
    </style>
</head>
<body>
<div class="shell">
    <aside class="sidebar">
        <div class="brand">NU SAMS Admin</div>
        <nav class="nav">
            <a href="dashboard.php">Dashboard</a>
            <a href="application.php">Applications</a>
            <a href="scheduling.php">Scheduling</a>
            <a href="attendance.php">Attendance</a>
            <a href="chat.php">Messages</a>
            <a href="documents.php">Documents</a>
            <a class="active" href="evaluation.php" aria-current="page">Evaluation</a>
            <a href="reports.php">Reports</a>
            <a href="students.php">Students</a>
        </nav>
        <div class="footer">
            <a href="settings.php">Settings</a>
            <a href="logout.php">Sign Out</a>
        </div>
    </aside>

    <main class="main">
        <header class="top">
            <div>
                <div style="font-size:24px;font-weight:800;">Evaluation</div>
                <div class="hint"><?= htmlspecialchars($department) ?></div>
            </div>
            <div style="text-align:right;">
                <div style="font-weight:700;"><?= htmlspecialchars($admin_name) ?></div>
                <div class="hint" style="font-size:12px;"><?= htmlspecialchars($admin_role) ?></div>
            </div>
        </header>

        <section class="content">
            <div class="card">
                <h2 style="margin:0 0 10px;">Evaluation Module</h2>
                <p class="hint" style="margin:0;">This page is ready as a dedicated destination in the unified sidebar. You can now navigate here from every admin page.</p>
            </div>
        </section>
    </main>
</div>
</body>
</html>
