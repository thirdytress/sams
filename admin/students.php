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

function has_column(mysqli $db, string $table, string $column): bool {
    $table = $db->real_escape_string($table);
    $column = $db->real_escape_string($column);
    if ($res = $db->query("SHOW COLUMNS FROM {$table} LIKE '{$column}'")) {
        $ok = $res->num_rows > 0;
        $res->free();
        return $ok;
    }
    return false;
}

$has_app_status = has_column($mysqli, 'student_applications', 'application_status');
$has_phone = has_column($mysqli, 'student_applications', 'contact_number');
$has_dob = has_column($mysqli, 'student_applications', 'date_of_birth');

$students = [];
$status_counts = ['all' => 0, 'active' => 0, 'inactive' => 0];

$query = "
    SELECT sa.*
    FROM student_applications sa
    INNER JOIN (
        SELECT student_id, MAX(created_at) AS max_created
        FROM student_applications
        GROUP BY student_id
    ) latest ON latest.student_id = sa.student_id AND latest.max_created = sa.created_at
    ORDER BY sa.full_name ASC
";

if ($res = $mysqli->query($query)) {
    while ($row = $res->fetch_assoc()) {
        $student_id = (string) ($row['student_id'] ?? '');

        $hours_minutes = 0;
        $activities = [];
        if ($stmtAtt = $mysqli->prepare('SELECT location, check_in_time, check_out_time FROM attendance WHERE student_id = ? ORDER BY check_in_time DESC LIMIT 5')) {
            $stmtAtt->bind_param('s', $student_id);
            if ($stmtAtt->execute()) {
                $attRes = $stmtAtt->get_result();
                while ($att = $attRes ? $attRes->fetch_assoc() : null) {
                    if ($att === null) {
                        break;
                    }
                    $inTs = !empty($att['check_in_time']) ? strtotime((string) $att['check_in_time']) : false;
                    $outTs = !empty($att['check_out_time']) ? strtotime((string) $att['check_out_time']) : false;
                    if ($inTs !== false) {
                        if ($outTs === false || $outTs < $inTs) {
                            $outTs = $inTs;
                        }
                        $mins = (int) floor(($outTs - $inTs) / 60);
                        if ($mins > 0) {
                            $hours_minutes += $mins;
                        }
                    }

                    $activityHours = 0.0;
                    if ($inTs !== false && $outTs !== false && $outTs >= $inTs) {
                        $activityHours = round(($outTs - $inTs) / 3600, 1);
                    }

                    $activities[] = [
                        'title' => 'Duty at ' . ((string) ($att['location'] ?? 'Assigned Office')),
                        'date' => !empty($att['check_in_time']) ? date('M j, Y', strtotime((string) $att['check_in_time'])) : '-',
                        'hours' => $activityHours > 0 ? $activityHours . 'h' : '-',
                    ];
                }
                $attRes && $attRes->free();
            }
            $stmtAtt->close();
        }

        $status = 'active';
        if ($has_app_status) {
            $app_status = strtolower(trim((string) ($row['application_status'] ?? 'pending')));
            $status = ($app_status === 'approved') ? 'active' : 'inactive';
        } else {
            $status = trim((string) ($row['work_schedule'] ?? '')) !== '' ? 'active' : 'inactive';
        }

        $hours_rendered = round($hours_minutes / 60, 1);
        $performance = min(5.0, 3.8 + min(1.2, $hours_rendered / 300));

        $skills = array_values(array_filter(array_map('trim', preg_split('/[,|]+/', (string) ($row['skills'] ?? '')))));

        $student = [
            'name' => (string) ($row['full_name'] ?? 'Unknown Student'),
            'student_id' => $student_id,
            'program' => (string) ($row['course'] ?? 'N/A'),
            'year_level' => (string) ($row['year_level'] ?? 'N/A'),
            'email' => (string) ($row['email'] ?? 'N/A'),
            'phone' => $has_phone ? (string) ($row['contact_number'] ?? 'N/A') : 'N/A',
            'office' => (string) ($row['work_location'] ?? 'Unassigned'),
            'date_started' => !empty($row['created_at']) ? date('F j, Y', strtotime((string) $row['created_at'])) : '-',
            'hours' => $hours_rendered,
            'rating' => number_format($performance, 1),
            'status' => $status,
            'skills' => $skills,
            'activities' => $activities,
        ];

        $students[] = $student;
        $status_counts['all']++;
        if ($status === 'active') {
            $status_counts['active']++;
        } else {
            $status_counts['inactive']++;
        }
    }
    $res->free();
}

$avg_rating = 0.0;
if ($students) {
    $sum = 0.0;
    foreach ($students as $s) {
        $sum += (float) $s['rating'];
    }
    $avg_rating = $sum / count($students);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <script src="../assets/realtime.js"></script>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Students | SA System</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;800;900&display=swap" rel="stylesheet" />
  <style>
    :root {
      --c-white: #fff;
      --c-bg: #f8fafc;
      --c-border: #e5e7eb;
      --c-text: #111827;
      --c-body: #4b5563;
      --c-muted: #6b7280;
      --c-blue: #1d4ed8;
      --c-blue2: #4f46e5;
      --c-green: #16a34a;
      --c-green-bg: #dcfce7;
      --c-red: #dc2626;
      --c-red-bg: #fee2e2;
      --c-gray-bg: #f3f4f6;
      --c-yellow: #ca8a04;
      --c-yellow-bg: #fef9c3;
      --shadow: 0 10px 30px rgba(2, 6, 23, .08);
      --radius: 16px;
      --sidebar-w: 256px;
    }

    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: Inter, sans-serif;
      color: var(--c-text);
      background: radial-gradient(circle at 10% -20%, #dbeafe 0%, #f8fafc 35%, #f8fafc 100%);
    }
    a { text-decoration: none; color: inherit; }

    .app { display: flex; min-height: 100vh; }

    .sidebar {
      width: var(--sidebar-w);
      background: #fff;
      border-right: 1px solid var(--c-border);
      display: flex;
      flex-direction: column;
      position: sticky;
      top: 0;
      height: 100vh;
    }
    .sidebar__brand {
      border-bottom: 1px solid var(--c-border);
      padding: 24px;
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .logo {
      width: 40px;
      height: 40px;
      border-radius: 12px;
      background: linear-gradient(135deg, #2563eb, #7c3aed);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 800;
    }
    .sidebar__nav {
      padding: 16px;
      display: flex;
      flex-direction: column;
      gap: 6px;
      flex: 1;
    }
    .nav-link {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 12px 14px;
      border-radius: 12px;
      color: var(--c-body);
      font-size: 15px;
    }
    .nav-link:hover { background: #f8fafc; }
    .nav-link--active { background: #1d4ed8; color: #fff; }
    .nav-badge {
      margin-left: auto;
      background: #dbeafe;
      color: #1d4ed8;
      font-size: 12px;
      font-weight: 800;
      border-radius: 999px;
      padding: 2px 8px;
    }
    .sidebar__footer {
      border-top: 1px solid var(--c-border);
      padding: 16px;
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    .main { flex: 1; min-width: 0; }
    .topbar {
      height: 88px;
      background: rgba(255,255,255,.8);
      border-bottom: 1px solid var(--c-border);
      backdrop-filter: blur(10px);
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 28px;
      position: sticky;
      top: 0;
      z-index: 10;
    }
    .top-title { font-size: 24px; font-weight: 800; }
    .top-sub { color: var(--c-muted); font-size: 14px; margin-top: 3px; }
    .top-user { text-align: right; font-size: 14px; }

    .content {
      padding: 28px;
      display: flex;
      flex-direction: column;
      gap: 20px;
    }

    .stats {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 14px;
    }
    .stat {
      border: 1px solid #1e3a8a;
      border-radius: 14px;
      background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
      box-shadow: var(--shadow);
      padding: 16px;
    }
    .stat__label { font-size: 13px; color: #ffffff; margin-bottom: 6px; }
    .stat__value { font-size: 26px; font-weight: 900; color: #ffffff; }

    .toolbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
    }
    .toolbar-left { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
    .search {
      border: 1px solid var(--c-border);
      border-radius: 12px;
      padding: 10px 12px;
      width: 320px;
      max-width: 100%;
    }
    .filter {
      border: 1px solid var(--c-border);
      background: #fff;
      color: var(--c-body);
      border-radius: 10px;
      padding: 8px 12px;
      cursor: pointer;
      font-weight: 600;
    }
    .filter.active {
      background: #1d4ed8;
      border-color: #1d4ed8;
      color: #fff;
    }

    .card {
      background: #fff;
      border: 1px solid var(--c-border);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      overflow: hidden;
    }
    .table-wrap { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; min-width: 980px; }
    thead { background: #f8fafc; }
    th {
      text-align: left;
      padding: 14px 18px;
      font-size: 13px;
      color: #374151;
      border-bottom: 1px solid var(--c-border);
    }
    td {
      padding: 14px 18px;
      border-bottom: 1px solid var(--c-border);
      vertical-align: middle;
    }
    tbody tr:last-child td { border-bottom: 0; }
    tbody tr:hover { background: #f9fbff; }

    .student-cell { display: flex; align-items: center; gap: 10px; }
    .student-avatar {
      width: 40px; height: 40px;
      border-radius: 999px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-weight: 800;
      color: #fff;
      background: linear-gradient(135deg, #1d4ed8, #4f46e5);
      flex-shrink: 0;
    }
    .student-name { font-weight: 700; }
    .student-sub { color: var(--c-muted); font-size: 13px; margin-top: 2px; }

    .pill {
      display: inline-flex;
      border-radius: 999px;
      padding: 4px 10px;
      font-size: 12px;
      font-weight: 700;
    }
    .pill-office { background: #dbeafe; color: #1d4ed8; }
    .pill-active { background: var(--c-green-bg); color: var(--c-green); }
    .pill-inactive { background: var(--c-red-bg); color: var(--c-red); }

    .rating { font-weight: 700; }
    .btn-view {
      border: none;
      background: #1d4ed8;
      color: #fff;
      border-radius: 10px;
      padding: 8px 12px;
      cursor: pointer;
      font-weight: 700;
    }

    .overlay {
      position: fixed;
      inset: 0;
      background: rgba(15, 23, 42, .55);
      display: none;
      align-items: center;
      justify-content: center;
      padding: 20px;
      z-index: 100;
    }
    .overlay.open { display: flex; }

    .profile-modal {
      width: min(1080px, 100%);
      max-height: 90vh;
      overflow: auto;
      border-radius: 14px;
      background: #fff;
      border: 1px solid var(--c-border);
      box-shadow: 0 25px 60px rgba(2,6,23,.28);
    }

    .profile-head {
      background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 60%, #4338ca 100%);
      color: #fff;
      padding: 16px;
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 12px;
    }
    .profile-left { display: flex; gap: 12px; }
    .profile-avatar {
      width: 50px;
      height: 50px;
      border-radius: 12px;
      border: 2px solid rgba(255,255,255,.3);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 900;
      font-size: 26px;
      color: #fff;
      flex-shrink: 0;
      background: rgba(255,255,255,.15);
    }
    .profile-name { font-size: 30px; font-weight: 900; margin-bottom: 4px; }
    .profile-sub { font-size: 14px; opacity: .9; margin-bottom: 8px; }
    .profile-tags { display: flex; gap: 8px; flex-wrap: wrap; }
    .tag {
      border-radius: 999px;
      padding: 4px 10px;
      font-size: 12px;
      font-weight: 800;
      color: #fff;
    }
    .tag-green { background: #16a34a; }
    .tag-yellow { background: #facc15; color: #1f2937; }
    .profile-actions { display: flex; gap: 8px; }
    .icon-btn {
      width: 32px;
      height: 32px;
      border-radius: 10px;
      border: none;
      background: rgba(255,255,255,.2);
      color: #fff;
      font-size: 16px;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }

    .profile-body { padding: 16px; }

    .profile-grid {
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: 14px;
    }

    .metric-row {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 10px;
      margin-bottom: 12px;
    }
    .metric {
      border: 1px solid var(--c-border);
      border-radius: 12px;
      padding: 12px;
    }
    .metric.gray { background: #e2e8f0; }
    .metric.green { background: #dcfce7; }
    .metric.purple { background: #f3e8ff; }
    .metric .num { font-size: 30px; font-weight: 900; }
    .metric .lab { font-size: 12px; color: var(--c-muted); margin-top: 2px; }

    .panel {
      border: 1px solid var(--c-border);
      border-radius: 12px;
      padding: 14px;
      margin-bottom: 12px;
    }
    .panel-title { font-size: 24px; font-weight: 800; margin-bottom: 12px; }
    .kv { display: grid; gap: 10px; }
    .kv-item { display: grid; grid-template-columns: 18px 1fr; gap: 8px; align-items: start; }
    .kv-item strong { display: block; font-size: 12px; color: var(--c-muted); margin-bottom: 2px; font-weight: 600; }

    .skills { display: flex; flex-wrap: wrap; gap: 8px; }
    .skill {
      background: #1e3a8a;
      color: #fff;
      border-radius: 8px;
      padding: 6px 10px;
      font-size: 12px;
      font-weight: 600;
    }

    .activity-item { border-left: 3px solid #1d4ed8; padding-left: 10px; margin-bottom: 12px; }
    .activity-title { font-weight: 700; margin-bottom: 4px; }
    .activity-meta { color: var(--c-muted); font-size: 12px; }

    .quick-actions { display: grid; gap: 8px; margin-top: 8px; }
    .quick-btn {
      border: 1px solid var(--c-border);
      background: #f8fafc;
      border-radius: 10px;
      padding: 10px;
      text-align: left;
      cursor: pointer;
      color: #111827;
      font-weight: 600;
      display: block;
      text-decoration: none;
    }

    .profile-footer {
      border-top: 1px solid var(--c-border);
      padding: 12px 16px;
      display: flex;
      justify-content: flex-end;
    }
    .send-btn {
      border: none;
      background: #1e3a8a;
      color: #fff;
      border-radius: 10px;
      padding: 12px 16px;
      font-weight: 700;
      cursor: pointer;
      text-decoration: none;
    }

    @media (max-width: 1024px) {
      .stats { grid-template-columns: repeat(2, 1fr); }
      .profile-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 700px) {
      .app { flex-direction: column; }
      .sidebar { width: 100%; height: auto; position: relative; }
      .topbar { position: relative; }
      .content { padding: 14px; }
      .stats { grid-template-columns: 1fr; }
      .metric-row { grid-template-columns: 1fr; }
      .profile-name { font-size: 22px; }
      .profile-avatar { width: 44px; height: 44px; font-size: 22px; }
    }
  </style>
</head>
<body>
<div class="app">
  <aside class="sidebar">
    <div class="sidebar__brand">
      <div class="logo">NU</div>
      <div>
        <div style="font-size:16px;font-weight:800;">SA System</div>
        <div style="font-size:12px;color:#6b7280;">Admin Panel</div>
      </div>
    </div>

    <nav class="sidebar__nav">
      <a class="nav-link" href="dashboard.php">Dashboard</a>
      <a class="nav-link" href="application.php">Applications <span class="nav-badge">12</span></a>
      <a class="nav-link" href="scheduling.php">Scheduling</a>
      <a class="nav-link" href="attendance.php">Attendance</a>
      <a class="nav-link" href="chat.php">Messages</a>
      <a class="nav-link" href="documents.php">Documents</a>
      <a class="nav-link" href="evaluation.php">Evaluation</a>
      <a class="nav-link" href="reports.php">Reports</a>
      <a class="nav-link nav-link--active" href="students.php" aria-current="page">Students</a>
    </nav>

    <div class="sidebar__footer">
      <a class="nav-link" href="settings.php">Settings</a>
      <a class="nav-link" href="logout.php">Sign Out</a>
    </div>
  </aside>

  <main class="main">
    <header class="topbar">
      <div>
        <div class="top-title">Student Surveillance</div>
        <div class="top-sub"><?= htmlspecialchars($department) ?></div>
      </div>
      <div class="top-user">
        <div style="font-weight:700;"><?= htmlspecialchars($admin_name) ?></div>
        <div style="font-size:12px;color:#6b7280;"><?= htmlspecialchars($admin_role) ?></div>
      </div>
    </header>

    <section class="content">
      <div class="stats">
        <div class="stat">
          <div class="stat__label">Total Students</div>
          <div class="stat__value"><?= number_format($status_counts['all']) ?></div>
        </div>
        <div class="stat">
          <div class="stat__label">Active Students</div>
          <div class="stat__value"><?= number_format($status_counts['active']) ?></div>
        </div>
        <div class="stat">
          <div class="stat__label">Inactive Students</div>
          <div class="stat__value"><?= number_format($status_counts['inactive']) ?></div>
        </div>
        <div class="stat">
          <div class="stat__label">Average Rating</div>
          <div class="stat__value"><?= number_format($avg_rating, 1) ?>/5</div>
        </div>
      </div>

      <div class="toolbar">
        <div class="toolbar-left">
          <input id="searchInput" class="search" type="search" placeholder="Search student name, ID, program..." />
          <button class="filter active" data-filter="all">All (<?= (int) $status_counts['all'] ?>)</button>
          <button class="filter" data-filter="active">Active (<?= (int) $status_counts['active'] ?>)</button>
          <button class="filter" data-filter="inactive">Inactive (<?= (int) $status_counts['inactive'] ?>)</button>
        </div>
      </div>

      <div class="card">
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Student</th>
                <th>Student ID</th>
                <th>Program</th>
                <th>Office</th>
                <th>Total Hours</th>
                <th>Rating</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody id="studentTableBody">
              <?php if (!$students): ?>
                <tr>
                  <td colspan="8" style="text-align:center;color:#6b7280;padding:24px;">No student records found yet.</td>
                </tr>
              <?php endif; ?>
              <?php foreach ($students as $idx => $s): ?>
                <?php
                  $initials = '';
                  foreach (preg_split('/\s+/', trim((string) $s['name'])) as $p) {
                    if ($p !== '') {
                      $initials .= strtoupper(substr($p, 0, 1));
                    }
                  }
                  $initials = substr($initials, 0, 3);

                  $jsonData = htmlspecialchars(json_encode($s, JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
                ?>
                <tr data-status="<?= htmlspecialchars($s['status']) ?>" data-search="<?= htmlspecialchars(strtolower($s['name'] . ' ' . $s['student_id'] . ' ' . $s['program'])) ?>">
                  <td>
                    <div class="student-cell">
                      <div class="student-avatar"><?= htmlspecialchars($initials) ?></div>
                      <div>
                        <div class="student-name"><?= htmlspecialchars($s['name']) ?></div>
                        <div class="student-sub"><?= htmlspecialchars($s['year_level']) ?></div>
                      </div>
                    </div>
                  </td>
                  <td><?= htmlspecialchars($s['student_id']) ?></td>
                  <td><?= htmlspecialchars($s['program']) ?></td>
                  <td><span class="pill pill-office"><?= htmlspecialchars($s['office']) ?></span></td>
                  <td><?= number_format((float) $s['hours'], 1) ?>h</td>
                  <td class="rating">★ <?= htmlspecialchars($s['rating']) ?></td>
                  <td>
                    <?php if ($s['status'] === 'active'): ?>
                      <span class="pill pill-active">Active</span>
                    <?php else: ?>
                      <span class="pill pill-inactive">Inactive</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <button class="btn-view" data-profile="<?= $jsonData ?>">View</button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </main>
</div>

<div id="profileOverlay" class="overlay" aria-hidden="true">
  <div class="profile-modal" role="dialog" aria-modal="true" aria-label="Student profile details">
    <div class="profile-head">
      <div class="profile-left">
        <div class="profile-avatar" id="mAvatar">ST</div>
        <div>
          <div class="profile-name" id="mName">Student Name</div>
          <div class="profile-sub" id="mSub">ID • Program</div>
          <div class="profile-tags">
            <span class="tag tag-green" id="mStatusTag">Active</span>
            <span class="tag tag-yellow" id="mOfficeTag">Office</span>
          </div>
        </div>
      </div>
      <div class="profile-actions">
        <button class="icon-btn" title="Edit">✎</button>
        <button class="icon-btn" id="closeProfileBtn" title="Close">×</button>
      </div>
    </div>

    <div class="profile-body">
      <div class="profile-grid">
        <div>
          <div class="metric-row">
            <div class="metric gray">
              <div class="num" id="mHours">0</div>
              <div class="lab">Total Hours</div>
            </div>
            <div class="metric green">
              <div class="num" id="mRating">0.0/5</div>
              <div class="lab">Performance</div>
            </div>
            <div class="metric purple">
              <div class="num" id="mYear">N/A</div>
              <div class="lab">Year Level</div>
            </div>
          </div>

          <div class="panel">
            <div class="panel-title">Contact Information</div>
            <div class="kv">
              <div class="kv-item">
                <span>✉</span>
                <div>
                  <strong>Email</strong>
                  <div id="mEmail">-</div>
                </div>
              </div>
              <div class="kv-item">
                <span>☎</span>
                <div>
                  <strong>Phone</strong>
                  <div id="mPhone">-</div>
                </div>
              </div>
              <div class="kv-item">
                <span>⌂</span>
                <div>
                  <strong>Assigned Office</strong>
                  <div id="mOffice">-</div>
                </div>
              </div>
              <div class="kv-item">
                <span>📅</span>
                <div>
                  <strong>Date Started</strong>
                  <div id="mStarted">-</div>
                </div>
              </div>
            </div>
          </div>

          <div class="panel">
            <div class="panel-title">Skills & Competencies</div>
            <div class="skills" id="mSkills"></div>
          </div>
        </div>

        <div>
          <div class="panel">
            <div class="panel-title" style="display:flex;justify-content:space-between;align-items:center;">
              <span>Recent Activities</span>
              <a href="attendance.php" style="font-size:12px;color:#1d4ed8;text-decoration:none;">View All</a>
            </div>
            <div id="mActivities"></div>
          </div>

          <div class="panel">
            <div class="panel-title">Quick Actions</div>
            <div class="quick-actions">
              <a id="mReportLink" class="quick-btn" href="reports.php">Generate Report</a>
              <button class="quick-btn" type="button">Export Data</button>
              <a class="quick-btn" href="scheduling.php">View Schedule</a>
              <button class="quick-btn" type="button">Evaluate Performance</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="profile-footer">
      <a id="mMessageLink" class="send-btn" href="chat.php">Send Message</a>
    </div>
  </div>
</div>

<script>
(function () {
  'use strict';

  var rows = Array.prototype.slice.call(document.querySelectorAll('#studentTableBody tr'));
  var searchInput = document.getElementById('searchInput');
  var filterBtns = Array.prototype.slice.call(document.querySelectorAll('.filter'));
  var currentFilter = 'all';

  function applyFilters() {
    var q = (searchInput.value || '').toLowerCase().trim();
    rows.forEach(function (row) {
      var status = row.getAttribute('data-status') || 'inactive';
      var search = row.getAttribute('data-search') || '';
      var byFilter = (currentFilter === 'all') || (status === currentFilter);
      var bySearch = (!q) || (search.indexOf(q) !== -1);
      row.style.display = (byFilter && bySearch) ? '' : 'none';
    });
  }

  searchInput.addEventListener('input', applyFilters);
  filterBtns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      filterBtns.forEach(function (b) { b.classList.remove('active'); });
      btn.classList.add('active');
      currentFilter = btn.getAttribute('data-filter') || 'all';
      applyFilters();
    });
  });

  var overlay = document.getElementById('profileOverlay');
  var closeBtn = document.getElementById('closeProfileBtn');

  function initials(name) {
    var parts = (name || '').trim().split(/\s+/);
    var out = '';
    parts.forEach(function (p) {
      if (p) out += p.charAt(0).toUpperCase();
    });
    return out.slice(0, 3) || 'ST';
  }

  function renderProfile(data) {
    document.getElementById('mAvatar').textContent = initials(data.name || 'Student');
    document.getElementById('mName').textContent = data.name || 'Student';
    document.getElementById('mSub').textContent = (data.student_id || '-') + ' • ' + (data.program || '-');
    document.getElementById('mStatusTag').textContent = (data.status || 'inactive') === 'active' ? 'Active' : 'Inactive';
    document.getElementById('mStatusTag').className = 'tag ' + (((data.status || 'inactive') === 'active') ? 'tag-green' : 'tag-yellow');
    document.getElementById('mOfficeTag').textContent = data.office || 'Office';

    document.getElementById('mHours').textContent = String(data.hours || 0);
    document.getElementById('mRating').textContent = String(data.rating || '0.0') + '/5';
    document.getElementById('mYear').textContent = data.year_level || 'N/A';

    document.getElementById('mEmail').textContent = data.email || '-';
    document.getElementById('mPhone').textContent = data.phone || '-';
    document.getElementById('mOffice').textContent = data.office || '-';
    document.getElementById('mStarted').textContent = data.date_started || '-';

    var skillsWrap = document.getElementById('mSkills');
    skillsWrap.innerHTML = '';
    var skills = Array.isArray(data.skills) ? data.skills : [];
    if (!skills.length) {
      var noSkill = document.createElement('span');
      noSkill.className = 'skill';
      noSkill.textContent = 'No skills listed';
      skillsWrap.appendChild(noSkill);
    } else {
      skills.forEach(function (s) {
        var el = document.createElement('span');
        el.className = 'skill';
        el.textContent = s;
        skillsWrap.appendChild(el);
      });
    }

    var actWrap = document.getElementById('mActivities');
    actWrap.innerHTML = '';
    var activities = Array.isArray(data.activities) ? data.activities : [];
    if (!activities.length) {
      var empty = document.createElement('div');
      empty.className = 'activity-meta';
      empty.textContent = 'No recent activities yet.';
      actWrap.appendChild(empty);
    } else {
      activities.slice(0, 3).forEach(function (a) {
        var item = document.createElement('div');
        item.className = 'activity-item';
        item.innerHTML = '<div class="activity-title">' + (a.title || 'Duty') + '</div>' +
                         '<div class="activity-meta">' + (a.date || '-') + ' · ' + (a.hours || '-') + '</div>';
        actWrap.appendChild(item);
      });
    }

    var sid = encodeURIComponent(data.student_id || '');
    document.getElementById('mReportLink').href = 'reports.php?student_id=' + sid;
    document.getElementById('mMessageLink').href = 'chat.php?student_id=' + sid;
  }

  function openProfile() {
    overlay.classList.add('open');
    overlay.setAttribute('aria-hidden', 'false');
  }

  function closeProfile() {
    overlay.classList.remove('open');
    overlay.setAttribute('aria-hidden', 'true');
  }

  document.querySelectorAll('.btn-view').forEach(function (btn) {
    btn.addEventListener('click', function () {
      try {
        var payload = btn.getAttribute('data-profile') || '{}';
        var data = JSON.parse(payload);
        renderProfile(data);
        openProfile();
      } catch (e) {
        console.error(e);
      }
    });
  });

  closeBtn.addEventListener('click', closeProfile);
  overlay.addEventListener('click', function (e) {
    if (e.target === overlay) {
      closeProfile();
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      closeProfile();
    }
  });
})();
</script>
</body>
</html>
