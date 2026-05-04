<?php
// application.php – NU SAMS Admin | Application Management
// Student Assistant Management System | National University – Lipa

session_start();

// Require admin login (shared with dashboard)
if (!isset($_SESSION['admin_id'])) {
  header('Location: login.php');
  exit;
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../mail_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function send_approval_email(string $toEmail, string $toName, string &$error): bool {
  $mail = new PHPMailer(true);

  try {
    $smtpPassword = (string) MAIL_PASSWORD;
    if (stripos((string) MAIL_HOST, 'gmail.com') !== false) {
      $smtpPassword = str_replace(' ', '', $smtpPassword);
    }

    $mail->isSMTP();
    $mail->Host       = MAIL_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = MAIL_USERNAME;
    $mail->Password   = $smtpPassword;
    $mail->Port       = MAIL_PORT;
    $mail->SMTPSecure = MAIL_ENCRYPTION;

    $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
    $mail->addAddress($toEmail, $toName !== '' ? $toName : $toEmail);

    $safeName = htmlspecialchars($toName !== '' ? $toName : 'Student');
    $mail->isHTML(true);
    $mail->Subject = 'Congratulations! Your NU SAMS application is approved';
    $mail->Body = '<div style="margin:0;padding:24px;background:#f3f7ff;font-family:Inter,Segoe UI,Arial,sans-serif;color:#111827;">'
      . '<table role="presentation" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #dbeafe;">'
      . '<tr><td style="background:linear-gradient(135deg,#003087 0%,#0047ab 100%);padding:24px;text-align:center;color:#ffffff;">'
      . '<div style="width:64px;height:64px;border-radius:12px;background:#ffffff;color:#003087;font-size:30px;line-height:64px;text-align:center;font-weight:900;margin:0 auto 12px auto;">NU</div>'
      . '<div style="font-size:22px;font-weight:800;letter-spacing:.3px;">Welcome to NU SAMS</div>'
      . '<div style="font-size:13px;opacity:.9;margin-top:6px;">National University - Lipa</div>'
      . '</td></tr>'
      . '<tr><td style="padding:24px;">'
      . '<p style="margin:0 0 12px 0;font-size:15px;">Hello <strong>' . $safeName . '</strong>,</p>'
      . '<p style="margin:0 0 12px 0;font-size:14px;line-height:1.6;color:#334155;">Congratulations! Your Student Assistant application has been <strong>approved</strong>.</p>'
      . '<p style="margin:0 0 12px 0;font-size:14px;line-height:1.6;color:#334155;">Welcome to the NU SAMS family. You can now access your student dashboard and begin your journey with us.</p>'
      . '<p style="margin:0;font-size:13px;color:#64748b;">If you have questions, please contact the SDAO office.</p>'
      . '</td></tr>'
      . '</table>'
      . '</div>';

    $mail->AltBody = 'Congratulations! Your Student Assistant application has been approved. Welcome to the NU SAMS family. You can now access your student dashboard.';
    $mail->send();
    return true;
  } catch (Exception $ex) {
    $error = 'Mailer error: ' . $mail->ErrorInfo;
    return false;
  }
}

// Ensure approval status column exists for gating login/access.
$has_status_column = false;
if ($colRes = $mysqli->query("SHOW COLUMNS FROM student_applications LIKE 'application_status'")) {
  $has_status_column = $colRes->num_rows > 0;
  $colRes->free();
}

if (!$has_status_column) {
  $mysqli->query("ALTER TABLE student_applications ADD COLUMN application_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending'");
  if ($colRes = $mysqli->query("SHOW COLUMNS FROM student_applications LIKE 'application_status'")) {
    $has_status_column = $colRes->num_rows > 0;
    $colRes->free();
  }
}

$flash_message = '';
$flash_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = strtolower(trim((string) ($_POST['action'] ?? '')));
  $student_id = trim((string) ($_POST['student_id'] ?? ''));

  if ($has_status_column && $student_id !== '' && in_array($action, ['approve', 'reject'], true)) {
    $student_email = '';
    $student_name_for_email = '';
    $sel = $mysqli->prepare('SELECT full_name, email FROM student_applications WHERE student_id = ? ORDER BY created_at DESC LIMIT 1');
    if ($sel) {
      $sel->bind_param('s', $student_id);
      $sel->execute();
      $resSel = $sel->get_result();
      if ($rowSel = $resSel->fetch_assoc()) {
        $student_name_for_email = (string) ($rowSel['full_name'] ?? '');
        $student_email = (string) ($rowSel['email'] ?? '');
      }
      $resSel && $resSel->free();
      $sel->close();
    }

    $new_status = $action === 'approve' ? 'approved' : 'rejected';
    $upd = $mysqli->prepare('UPDATE student_applications SET application_status = ? WHERE student_id = ? ORDER BY created_at DESC LIMIT 1');
    if ($upd) {
      $upd->bind_param('ss', $new_status, $student_id);
      if ($upd->execute()) {
        $flash_message = $new_status === 'approved' ? 'Applicant approved successfully.' : 'Applicant rejected successfully.';

        if ($new_status === 'approved' && $student_email !== '') {
          $mail_error = '';
          if (send_approval_email($student_email, $student_name_for_email, $mail_error)) {
            $flash_message .= ' Approval email was sent to the student.';
          } else {
            $flash_message .= ' Applicant was approved, but the email could not be sent (' . $mail_error . ').';
          }
        }
      } else {
        $flash_message = 'Failed to update application status.';
        $flash_type = 'error';
      }
      $upd->close();
    } else {
      $flash_message = 'Database error while updating status.';
      $flash_type = 'error';
    }
  }
}

// Load all applications (most recent first)
$applications = [];
$select_status_sql = $has_status_column ? ', application_status' : '';
if ($result = $mysqli->query("SELECT full_name, student_id, email, contact_number, course, year_level, work_location, skills, created_at{$select_status_sql} FROM student_applications ORDER BY created_at DESC")) {
  while ($row = $result->fetch_assoc()) {
    $applications[] = $row;
  }
  $result->free();
}

$total_all = count($applications);
$total_pending   = 0;
$total_interview = 0;
$total_approved  = 0;
$total_rejected  = 0;

foreach ($applications as $app) {
  $st = strtolower(trim((string) ($app['application_status'] ?? 'pending')));
  if ($st === 'approved') {
    $total_approved++;
  } elseif ($st === 'rejected') {
    $total_rejected++;
  } else {
    $total_pending++;
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Application Management – SA System</title>
  <meta name="description" content="Admin Application Management for NU Lipa Student Assistant System." />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap" rel="stylesheet" />
  <style>
    /* =============================================
       CSS VARIABLES / DESIGN TOKENS
    ============================================= */
    :root {
      --color-blue:           #155dfc;
      --color-blue-dark:      #1447e6;
      --color-purple:         #9810fa;
      --color-purple-dark:    #8200db;
      --color-dark:           #101828;
      --color-body:           #364153;
      --color-muted:          #4a5565;
      --color-muted-light:    #99a1af;
      --color-white:          #ffffff;
      --color-bg:             #f9fafb;
      --color-border:         #e5e7eb;
      --color-input-border:   #d1d5dc;
      --color-tag-bg:         #f3f4f6;
      --color-red-dot:        #fb2c36;

      /* Status badge colours */
      --color-pending-bg:     #fef9c2;
      --color-pending-text:   #a65f00;
      --color-interview-bg:   #dbeafe;
      --color-interview-text: #1447e6;
      --color-approved-bg:    #dcfce7;
      --color-approved-text:  #008236;
      --color-rejected-bg:    #fee2e2;
      --color-rejected-text:  #b91c1c;

      --grad-brand:    linear-gradient(135deg, #155dfc 0%, #9810fa 100%);
      --grad-blue:     linear-gradient(158deg, #155dfc 0%, #1447e6 100%);
      --grad-purple:   linear-gradient(158deg, #9810fa 0%, #8200db 100%);

      --shadow-card:   0 1px 3px 0 rgba(0,0,0,.10), 0 1px 2px 0 rgba(0,0,0,.06);

      --sidebar-w:     256px;
      --topbar-h:      89px;

      --radius-sm:     4px;
      --radius-md:     10px;
      --radius-lg:     16px;
      --radius-pill:   9999px;

      --font-xs:   12px;
      --font-sm:   14px;
      --font-base: 16px;
      --font-lg:   18px;
      --font-xl:   24px;

      --space-1:   4px;
      --space-2:   8px;
      --space-3:   12px;
      --space-4:   16px;
      --space-5:   20px;
      --space-6:   24px;
      --space-8:   32px;
    }

    /* =============================================
       RESET & BASE
    ============================================= */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html, body { height: 100%; }
    body {
      font-family: 'Inter', Arial, sans-serif;
      font-size: var(--font-base);
      color: var(--color-dark);
      background: var(--color-bg);
      -webkit-font-smoothing: antialiased;
    }
    a { text-decoration: none; color: inherit; }
    img { display: block; }
    button, input { font-family: inherit; }

    /* =============================================
       APP SHELL
    ============================================= */
    .app {
      display: flex;
      height: 100vh;
      overflow: hidden;
    }

    /* =============================================
       SIDEBAR
    ============================================= */
    .sidebar {
      width: var(--sidebar-w);
      flex-shrink: 0;
      background: var(--color-white);
      border-right: 1px solid var(--color-border);
      display: flex;
      flex-direction: column;
      height: 100%;
      overflow: hidden;
    }

    /* Sidebar brand */
    .sidebar__brand {
      height: var(--topbar-h);
      border-bottom: 1px solid var(--color-border);
      padding: var(--space-6) var(--space-6) 0;
      display: flex;
      align-items: center;
      gap: var(--space-3);
      flex-shrink: 0;
    }
    .sidebar__logo {
      width: 40px;
      height: 40px;
      border-radius: var(--radius-md);
      background: var(--grad-brand);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: var(--font-lg);
      font-weight: 700;
      color: var(--color-white);
      flex-shrink: 0;
    }
    .sidebar__brand-name {
      font-size: var(--font-base);
      font-weight: 700;
      color: var(--color-dark);
    }
    .sidebar__brand-sub {
      font-size: var(--font-xs);
      color: var(--color-muted);
    }

    /* Nav */
    .sidebar__nav {
      flex: 1;
      overflow-y: auto;
      padding: var(--space-4) var(--space-4) 0;
      display: flex;
      flex-direction: column;
      gap: var(--space-1);
    }
    .nav-item {
      display: flex;
      align-items: center;
      gap: var(--space-3);
      height: 48px;
      padding: 0 var(--space-4);
      border-radius: var(--radius-md);
      font-size: var(--font-base);
      color: var(--color-body);
      cursor: pointer;
      transition: background .15s;
    }
    .nav-item:hover { background: var(--color-bg); }
    .nav-item--active {
      background: var(--color-blue);
      color: var(--color-white);
    }
    .nav-item--active:hover { background: var(--color-blue); }
    .nav-item__icon { width: 20px; height: 20px; flex-shrink: 0; }
    .nav-item__label { flex: 1; }
    .nav-item__badge {
      background: var(--color-white);
      color: var(--color-blue);
      font-size: var(--font-xs);
      font-weight: 700;
      padding: 2px var(--space-2);
      border-radius: var(--radius-pill);
      min-width: 20px;
      text-align: center;
    }

    /* Sidebar footer */
    .sidebar__footer {
      border-top: 1px solid var(--color-border);
      padding: 17px var(--space-4) var(--space-4);
      display: flex;
      flex-direction: column;
      gap: var(--space-1);
      flex-shrink: 0;
    }

    /* Mobile sidebar toggle */
    .sidebar-toggle {
      display: none;
      position: fixed;
      top: 16px;
      left: 16px;
      z-index: 200;
      width: 36px;
      height: 36px;
      background: var(--color-white);
      border: 1px solid var(--color-border);
      border-radius: var(--radius-md);
      cursor: pointer;
      align-items: center;
      justify-content: center;
      flex-direction: column;
      gap: 4px;
    }
    .sidebar-toggle__bar {
      display: block;
      width: 18px;
      height: 2px;
      background: var(--color-dark);
      border-radius: 2px;
      transition: transform .3s, opacity .3s;
    }

    /* =============================================
       MAIN AREA
    ============================================= */
    .main {
      flex: 1;
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }

    /* Top bar */
    .topbar {
      height: var(--topbar-h);
      flex-shrink: 0;
      background: var(--color-white);
      border-bottom: 1px solid var(--color-border);
      padding: 0 var(--space-8);
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .topbar__title {
      font-size: var(--font-xl);
      font-weight: 700;
      color: var(--color-dark);
      line-height: 1.33;
    }
    .topbar__sub {
      font-size: var(--font-sm);
      color: var(--color-muted);
    }
    .topbar__user {
      display: flex;
      align-items: center;
      gap: var(--space-3);
    }
    .topbar__notif {
      position: relative;
      width: 36px;
      height: 36px;
      border-radius: var(--radius-pill);
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
    }
    .topbar__notif-icon { width: 20px; height: 20px; }
    .topbar__notif-dot {
      position: absolute;
      top: 4px;
      right: 0;
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: var(--color-red-dot);
    }
    .topbar__user-info { text-align: right; }
    .topbar__user-name {
      font-size: var(--font-sm);
      color: var(--color-dark);
    }
    .topbar__user-role {
      font-size: var(--font-xs);
      color: var(--color-muted);
    }
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
    .topbar__avatar-text {
      font-size: var(--font-sm);
      font-weight: 700;
      color: var(--color-white);
      line-height: 1;
    }

    /* =============================================
       PAGE CONTENT
    ============================================= */
    .content {
      flex: 1;
      overflow-y: auto;
      padding: var(--space-8);
      display: flex;
      flex-direction: column;
      gap: var(--space-6);
    }

    /* =============================================
       TOOLBAR (search + filters + export)
    ============================================= */
    .toolbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: var(--space-4);
      flex-wrap: wrap;
    }
    .toolbar__left {
      display: flex;
      align-items: center;
      gap: var(--space-2);
      flex-wrap: wrap;
    }
    .toolbar__search {
      position: relative;
    }
    .toolbar__search-icon {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      width: 20px;
      height: 20px;
      pointer-events: none;
    }
    .toolbar__search input {
      width: 320px;
      height: 42px;
      border: 1px solid var(--color-input-border);
      border-radius: var(--radius-md);
      padding: var(--space-2) var(--space-4) var(--space-2) 40px;
      font-size: var(--font-base);
      color: var(--color-dark);
      background: var(--color-white);
      outline: none;
      transition: border-color .2s;
    }
    .toolbar__search input::placeholder { color: rgba(10,10,10,.5); }
    .toolbar__search input:focus { border-color: var(--color-blue); }

    /* Filter buttons */
    .filter-btn {
      height: 40px;
      border-radius: var(--radius-md);
      border: none;
      cursor: pointer;
      font-size: var(--font-base);
      padding: 0 16px;
      display: inline-flex;
      align-items: center;
      gap: 4px;
      transition: opacity .15s;
    }
    .filter-btn--active {
      background: var(--color-blue);
      color: var(--color-white);
    }
    .filter-btn--inactive {
      background: var(--color-tag-bg);
      color: var(--color-body);
    }
    .filter-btn__count { opacity: .6; }
    .filter-btn--active .filter-btn__count { opacity: .8; }

    /* Export button */
    .btn-export {
      display: inline-flex;
      align-items: center;
      gap: var(--space-2);
      height: 40px;
      padding: 0 var(--space-4);
      border-radius: var(--radius-md);
      border: none;
      background: var(--color-blue);
      color: var(--color-white);
      font-size: var(--font-base);
      cursor: pointer;
      white-space: nowrap;
      transition: opacity .15s;
      text-decoration: none;
    }
    .btn-export:hover { opacity: .88; }
    .btn-export__icon { width: 16px; height: 16px; }

    /* =============================================
       TABLE CARD
    ============================================= */
    .table-card {
      background: var(--color-white);
      border: 1px solid var(--color-border);
      border-radius: var(--radius-lg);
      overflow: hidden;
    }
    .table-wrap {
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      min-width: 900px;
    }
    thead {
      background: var(--color-bg);
      border-bottom: 1px solid var(--color-border);
    }
    th {
      padding: 16px var(--space-6);
      font-size: var(--font-sm);
      font-weight: 700;
      color: var(--color-dark);
      text-align: left;
      white-space: nowrap;
    }
    tbody tr {
      border-bottom: 1px solid var(--color-border);
    }
    tbody tr:last-child { border-bottom: none; }
    tbody tr:hover { background: #fafafa; }
    td {
      padding: 0 var(--space-6);
      height: 77px;
      vertical-align: middle;
      font-size: var(--font-base);
      color: var(--color-dark);
    }

    /* Applicant cell */
    .applicant {
      display: flex;
      align-items: center;
      gap: var(--space-3);
    }
    .applicant__avatar {
      width: 40px;
      height: 40px;
      border-radius: var(--radius-pill);
      background: var(--grad-brand);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: var(--font-sm);
      font-weight: 700;
      color: var(--color-white);
      flex-shrink: 0;
    }
    .applicant__name {
      font-weight: 700;
      color: var(--color-dark);
      line-height: 1.5;
    }
    .applicant__date {
      font-size: var(--font-sm);
      color: var(--color-muted);
    }

    /* Skills tags */
    .skills {
      display: flex;
      gap: var(--space-1);
      flex-wrap: wrap;
    }
    .skill-tag {
      background: var(--color-tag-bg);
      color: var(--color-body);
      font-size: var(--font-xs);
      padding: var(--space-1) var(--space-2);
      border-radius: var(--radius-sm);
      white-space: nowrap;
    }

    /* Status badges */
    .badge {
      display: inline-block;
      font-size: var(--font-xs);
      padding: var(--space-1) var(--space-3);
      border-radius: var(--radius-pill);
      white-space: nowrap;
    }
    .badge--pending   { background: var(--color-pending-bg);   color: var(--color-pending-text);   }
    .badge--interview { background: var(--color-interview-bg); color: var(--color-interview-text); }
    .badge--approved  { background: var(--color-approved-bg);  color: var(--color-approved-text);  }
    .badge--rejected  { background: var(--color-rejected-bg);  color: var(--color-rejected-text);  }

    /* Action buttons */
    .actions {
      display: flex;
      align-items: center;
      gap: var(--space-2);
    }
    .action-btn {
      width: 32px;
      height: 32px;
      border-radius: var(--radius-md);
      border: none;
      background: none;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 0;
      transition: background .15s;
      flex-shrink: 0;
    }
    .action-btn:hover { background: var(--color-tag-bg); }
    .action-btn svg { width: 16px; height: 16px; }
    .action-btn--view  svg { color: var(--color-muted); }
    .action-btn--approve svg { color: #008236; }
    .action-btn--reject  svg { color: #b91c1c; }
    .action-btn--msg   svg { color: var(--color-blue); }

    /* =============================================
       BOTTOM INFO CARDS (2-col grid)
    ============================================= */
    .info-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: var(--space-6);
    }

    /* Auto-filtering card */
    .card-auto {
      background: var(--grad-blue);
      border-radius: var(--radius-lg);
      padding: var(--space-6);
      display: flex;
      flex-direction: column;
      gap: var(--space-2);
    }
    .card-auto__title {
      font-size: var(--font-lg);
      font-weight: 700;
      color: var(--color-white);
    }
    .card-auto__desc {
      font-size: var(--font-sm);
      color: #dbeafe;
    }
    .card-auto__stats {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: var(--space-4);
      margin-top: var(--space-2);
    }
    .stat-box {
      background: rgba(255,255,255,.10);
      border-radius: var(--radius-md);
      padding: var(--space-3);
    }
    .stat-box__num {
      font-size: var(--font-xl);
      font-weight: 700;
      color: var(--color-white);
      line-height: 1.33;
    }
    .stat-box__label {
      font-size: var(--font-sm);
      color: #dbeafe;
    }

    /* AI Recommendations card */
    .card-ai {
      background: var(--grad-purple);
      border-radius: var(--radius-lg);
      padding: var(--space-6);
      display: flex;
      flex-direction: column;
      gap: var(--space-2);
    }

    .flash {
      border-radius: var(--radius-md);
      padding: 10px 12px;
      font-size: var(--font-sm);
      margin-bottom: var(--space-3);
    }
    .flash--success {
      background: #ecfdf3;
      border: 1px solid #a7f3d0;
      color: #065f46;
    }
    .flash--error {
      background: #fef2f2;
      border: 1px solid #fecaca;
      color: #991b1b;
    }
    .card-ai__title {
      font-size: var(--font-lg);
      font-weight: 700;
      color: var(--color-white);
    }
    .card-ai__desc {
      font-size: var(--font-sm);
      color: #f3e8ff;
    }
    .card-ai__list {
      display: flex;
      flex-direction: column;
      gap: var(--space-2);
      margin-top: var(--space-2);
    }
    .rec-item {
      background: rgba(255,255,255,.10);
      border-radius: var(--radius-md);
      padding: var(--space-3);
      display: flex;
      align-items: center;
      justify-content: space-between;
      height: 68px;
    }
    .rec-item__name {
      font-size: var(--font-sm);
      font-weight: 700;
      color: var(--color-white);
    }
    .rec-item__office {
      font-size: var(--font-xs);
      color: #f3e8ff;
    }
    .rec-item__match {
      text-align: right;
    }
    .rec-item__pct {
      font-size: var(--font-lg);
      font-weight: 700;
      color: var(--color-white);
    }
    .rec-item__label {
      font-size: var(--font-xs);
      color: #f3e8ff;
    }

    /* =============================================
       SIDEBAR MOBILE OVERLAY
    ============================================= */
    .sidebar-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,.4);
      z-index: 99;
    }

    /* =============================================
       RESPONSIVE – TABLET (≤1024px)
    ============================================= */
    @media (max-width: 1024px) {
      .sidebar {
        position: fixed;
        left: 0;
        top: 0;
        bottom: 0;
        z-index: 100;
        transform: translateX(-100%);
        transition: transform .3s;
      }
      .sidebar.is-open { transform: translateX(0); }
      .sidebar-overlay.is-open { display: block; }
      .sidebar-toggle { display: flex; }
      .topbar { padding-left: 64px; }
      .info-grid { grid-template-columns: 1fr; }
    }

    /* =============================================
       RESPONSIVE – MOBILE (≤768px)
    ============================================= */
    @media (max-width: 768px) {
      .content { padding: var(--space-4); gap: var(--space-4); }
      .toolbar { flex-direction: column; align-items: stretch; }
      .toolbar__left { flex-wrap: wrap; }
      .toolbar__search input { width: 100%; }
      .btn-export { justify-content: center; }
      .topbar { padding: 0 var(--space-4) 0 64px; }
      .topbar__title { font-size: var(--font-lg); }
      .topbar__user-info { display: none; }
    }
  </style>
</head>
<body>

<div class="app">

  <!-- ============================================
       SIDEBAR
  ============================================= -->
  <aside class="sidebar" id="sidebar" aria-label="Main navigation">

    <!-- Brand -->
    <div class="sidebar__brand">
      <div class="sidebar__logo" aria-hidden="true">NU</div>
      <div>
        <div class="sidebar__brand-name">SA System</div>
        <div class="sidebar__brand-sub">Admin Panel</div>
      </div>
    </div>

    <!-- Nav items -->
    <nav class="sidebar__nav" aria-label="Site navigation">
      <a class="nav-item" href="dashboard.php">
        <svg class="nav-item__icon" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <rect x="2" y="2" width="7" height="7" rx="1.5" fill="currentColor"/>
          <rect x="11" y="2" width="7" height="7" rx="1.5" fill="currentColor"/>
          <rect x="2" y="11" width="7" height="7" rx="1.5" fill="currentColor"/>
          <rect x="11" y="11" width="7" height="7" rx="1.5" fill="currentColor"/>
        </svg>
        <span class="nav-item__label">Dashboard</span>
      </a>
      <a class="nav-item nav-item--active" href="application.php" aria-current="page">
        <svg class="nav-item__icon" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <rect x="3" y="2" width="14" height="16" rx="2" stroke="currentColor" stroke-width="1.6"/>
          <path d="M6 6h8M6 9.5h8M6 13h6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
        </svg>
        <span class="nav-item__label">Applications</span>
        <span class="nav-item__badge">12</span>
      </a>
      <a class="nav-item" href="scheduling.php">
        <svg class="nav-item__icon" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <path d="M4 3h12v14H4z" stroke="currentColor" stroke-width="1.6"/>
          <path d="M4 7h12M7 3v4M13 3v4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
        </svg>
        <span class="nav-item__label">Scheduling</span>
      </a>
      <a class="nav-item" href="attendance.php">
        <svg class="nav-item__icon" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <circle cx="10" cy="10" r="7" stroke="currentColor" stroke-width="1.6"/>
          <path d="M10 6v4l3 2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
        </svg>
        <span class="nav-item__label">Attendance</span>
      </a>
      <a class="nav-item" href="chat.php">
        <svg class="nav-item__icon" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <path d="M4 4h12a2 2 0 012 2v6a2 2 0 01-2 2H9l-4 3v-3H4a2 2 0 01-2-2V6a2 2 0 012-2z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
        </svg>
        <span class="nav-item__label">Messages</span>
      </a>
      <a class="nav-item" href="documents.php">
        <svg class="nav-item__icon" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <path d="M5 3h8l3 3v11H5z" stroke="currentColor" stroke-width="1.6"/>
          <path d="M13 3v4h3" stroke="currentColor" stroke-width="1.6"/>
        </svg>
        <span class="nav-item__label">Documents</span>
      </a>
      <a class="nav-item" href="evaluation.php">
        <svg class="nav-item__icon" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <path d="M4 10h12M4 5h12M4 15h8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
        </svg>
        <span class="nav-item__label">Evaluation</span>
      </a>
      <a class="nav-item" href="reports.php">
        <svg class="nav-item__icon" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <path d="M4 16h12M6 13V9M10 13V6M14 13V4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
        </svg>
        <span class="nav-item__label">Reports</span>
      </a>
      <a class="nav-item" href="students.php">
        <svg class="nav-item__icon" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <circle cx="10" cy="7" r="3" stroke="currentColor" stroke-width="1.6"/>
          <path d="M4 16c0-2.4 2.7-4.2 6-4.2s6 1.8 6 4.2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
        </svg>
        <span class="nav-item__label">Students</span>
      </a>
    </nav>

    <!-- Footer nav -->
    <div class="sidebar__footer">
      <a class="nav-item" href="settings.php">
        <svg class="nav-item__icon" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <path d="M10 2l2 2.2 3-.2.6 2.9 2.4 1.8-1.7 2.5.6 2.9-2.9.7-1.9 2.3-2.5-1.6-2.5 1.6-1.9-2.3-2.9-.7.6-2.9L1.9 8.7l2.4-1.8.6-2.9 3 .2L10 2z" stroke="currentColor" stroke-width="1.4"/>
          <circle cx="10" cy="10" r="2.3" stroke="currentColor" stroke-width="1.4"/>
        </svg>
        <span class="nav-item__label">Settings</span>
      </a>
      <a class="nav-item" href="logout.php">
        <svg class="nav-item__icon" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <path d="M8 3H4.5A1.5 1.5 0 003 4.5v11A1.5 1.5 0 004.5 17H8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
          <path d="M12 7l3 3-3 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M15 10H7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
        </svg>
        <span class="nav-item__label">Sign Out</span>
      </a>
    </div>
  </aside>

  <!-- Mobile sidebar overlay -->
  <div class="sidebar-overlay" id="sidebar-overlay" aria-hidden="true"></div>

  <!-- Hamburger toggle -->
  <button class="sidebar-toggle" id="sidebar-toggle"
    aria-expanded="false" aria-controls="sidebar" aria-label="Toggle navigation">
    <span class="sidebar-toggle__bar"></span>
    <span class="sidebar-toggle__bar"></span>
    <span class="sidebar-toggle__bar"></span>
  </button>

  <!-- ============================================
       MAIN
  ============================================= -->
  <div class="main">

    <!-- Top bar -->
    <header class="topbar" role="banner">
      <div>
        <div class="topbar__title">Application Management</div>
        <div class="topbar__sub">NU Lipa - Student Development and Activities Office</div>
      </div>
      <div class="topbar__user">
        <div class="topbar__notif" aria-label="Notifications">
          <svg class="topbar__notif-icon" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M6 8a4 4 0 118 0v3l1.5 1.5H4.5L6 11V8z" stroke="#4A5565" stroke-width="1.5" stroke-linejoin="round"/>
            <path d="M8.2 14.5a2 2 0 003.6 0" stroke="#4A5565" stroke-width="1.5" stroke-linecap="round"/>
          </svg>
          <span class="topbar__notif-dot" aria-label="New notifications"></span>
        </div>
          <div class="topbar__user-info">
            <div class="topbar__user-name"><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></div>
            <div class="topbar__user-role"><?php echo htmlspecialchars($_SESSION['admin_role'] ?? 'SDAO Head'); ?></div>
        </div>
        <div class="topbar__avatar">
          <span class="topbar__avatar-text"><?php echo strtoupper(substr((string)($_SESSION['admin_name'] ?? 'A'), 0, 1)); ?></span>
        </div>
      </div>
    </header>

    <!-- Page content -->
    <main class="content" role="main">

      <?php if ($flash_message !== ''): ?>
        <div class="flash <?php echo $flash_type === 'error' ? 'flash--error' : 'flash--success'; ?>">
          <?php echo htmlspecialchars($flash_message); ?>
        </div>
      <?php endif; ?>

      <!-- ---- Toolbar ---- -->
      <div class="toolbar">
        <div class="toolbar__left">
          <!-- Search -->
          <div class="toolbar__search">
            <svg class="toolbar__search-icon" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <circle cx="9" cy="9" r="5.5" stroke="#6B7280" stroke-width="1.6"/>
              <path d="M13.5 13.5L17 17" stroke="#6B7280" stroke-width="1.6" stroke-linecap="round"/>
            </svg>
            <input type="search" id="search-input" placeholder="Search applicants..."
              aria-label="Search applicants" />
          </div>
          <!-- Filter buttons -->
          <button class="filter-btn filter-btn--active" data-filter="all"   aria-pressed="true">
            All <span class="filter-btn__count">(<?php echo $total_all; ?>)</span>
          </button>
          <button class="filter-btn filter-btn--inactive" data-filter="pending"   aria-pressed="false">
            Pending <span class="filter-btn__count">(<?php echo $total_pending; ?>)</span>
          </button>
          <button class="filter-btn filter-btn--inactive" data-filter="interview" aria-pressed="false">
            Interview <span class="filter-btn__count">(<?php echo $total_interview; ?>)</span>
          </button>
          <button class="filter-btn filter-btn--inactive" data-filter="approved"  aria-pressed="false">
            Approved <span class="filter-btn__count">(<?php echo $total_approved; ?>)</span>
          </button>
          <button class="filter-btn filter-btn--inactive" data-filter="rejected"  aria-pressed="false">
            Rejected <span class="filter-btn__count">(<?php echo $total_rejected; ?>)</span>
          </button>
        </div>
        <a class="btn-export" href="#" aria-label="Export applicant list">
          <svg class="btn-export__icon" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M8 2v7M8 9L5.5 6.5M8 9l2.5-2.5" stroke="white" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M3 11.5v1.5h10v-1.5" stroke="white" stroke-width="1.6" stroke-linecap="round"/>
          </svg>
          Export List
        </a>
      </div>

      <!-- ---- Table ---- -->
      <div class="table-card">
        <div class="table-wrap">
          <table aria-label="Applications table">
            <thead>
              <tr>
                <th scope="col">Applicant</th>
                <th scope="col">Student ID</th>
                <th scope="col">Program</th>
                <th scope="col">Preferred Office</th>
                <th scope="col">Skills</th>
                <th scope="col">Status</th>
                <th scope="col">Actions</th>
              </tr>
            </thead>
            <tbody id="table-body">
              <?php if (empty($applications)): ?>
                <tr>
                  <td colspan="7">No applications yet. New applicants will appear here in real time.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($applications as $app): ?>
                  <?php
                    $fullName  = $app['full_name'] ?? '';
                    $studentId = $app['student_id'] ?? '';
                    $course    = $app['course'] ?? '';
                    $workLoc   = $app['work_location'] ?? '';
                    $skillsStr = $app['skills'] ?? '';
                    $createdAt = $app['created_at'] ?? '';

                    $nameParts = preg_split('/\s+/', trim($fullName));
                    $initials  = '';
                    if (!empty($nameParts[0])) {
                      $initials .= strtoupper(substr($nameParts[0], 0, 1));
                    }
                    if (count($nameParts) > 1 && !empty($nameParts[count($nameParts)-1])) {
                      $initials .= strtoupper(substr($nameParts[count($nameParts)-1], 0, 1));
                    }

                    $appliedDisplay = '';
                    if (!empty($createdAt)) {
                      $ts = strtotime($createdAt);
                      if ($ts !== false) {
                        $appliedDisplay = 'Applied ' . date('M d, Y', $ts);
                      }
                    }

                    $skillsList = [];
                    if (!empty($skillsStr)) {
                      foreach (explode(',', $skillsStr) as $s) {
                        $trim = trim($s);
                        if ($trim !== '') {
                          $skillsList[] = $trim;
                        }
                      }
                    }

                    $status      = strtolower(trim((string) ($app['application_status'] ?? 'pending')));
                    $badgeClass  = 'badge--pending';
                    $statusLabel = 'Pending';
                    if ($status === 'approved') {
                      $badgeClass = 'badge--approved';
                      $statusLabel = 'Approved';
                    } elseif ($status === 'rejected') {
                      $badgeClass = 'badge--rejected';
                      $statusLabel = 'Rejected';
                    }
                  ?>
                  <tr data-status="<?php echo $status; ?>">
                    <td>
                      <div class="applicant">
                        <div class="applicant__avatar" aria-hidden="true"><?php echo htmlspecialchars($initials ?: 'SA'); ?></div>
                        <div>
                          <div class="applicant__name"><?php echo htmlspecialchars($fullName); ?></div>
                          <div class="applicant__date"><?php echo htmlspecialchars($appliedDisplay); ?></div>
                        </div>
                      </div>
                    </td>
                    <td><?php echo htmlspecialchars($studentId); ?></td>
                    <td><?php echo htmlspecialchars($course); ?></td>
                    <td><?php echo htmlspecialchars($workLoc); ?></td>
                    <td>
                      <div class="skills">
                        <?php foreach ($skillsList as $skill): ?>
                          <span class="skill-tag"><?php echo htmlspecialchars($skill); ?></span>
                        <?php endforeach; ?>
                      </div>
                    </td>
                    <td><span class="badge <?php echo $badgeClass; ?>"><?php echo $statusLabel; ?></span></td>
                    <td>
                      <div class="actions">
                        <form method="post" action="" style="display:inline;">
                          <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($studentId); ?>">
                          <input type="hidden" name="action" value="approve">
                          <button class="action-btn action-btn--approve" type="submit" title="Approve application" aria-label="Approve <?php echo htmlspecialchars($fullName); ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                          </button>
                        </form>
                        <form method="post" action="" style="display:inline;">
                          <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($studentId); ?>">
                          <input type="hidden" name="action" value="reject">
                          <button class="action-btn action-btn--reject" type="submit" title="Reject application" aria-label="Reject <?php echo htmlspecialchars($fullName); ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                          </button>
                        </form>
                        <a class="action-btn action-btn--view" href="application-view.php?student_id=<?php echo urlencode($studentId); ?>" title="View application" aria-label="View <?php echo htmlspecialchars($fullName); ?>">
                          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </a>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ---- Bottom info grid ---- -->
      <div class="info-grid">

        <!-- Auto-Filtering card -->
        <div class="card-auto">
          <div class="card-auto__title">🤖 Auto-Filtering Active</div>
          <div class="card-auto__desc">Requirements are automatically verified. Only qualified applicants are shown above.</div>
          <div class="card-auto__stats">
            <div class="stat-box">
              <div class="stat-box__num">28</div>
              <div class="stat-box__label">Qualified</div>
            </div>
            <div class="stat-box">
              <div class="stat-box__num">7</div>
              <div class="stat-box__label">Filtered Out</div>
            </div>
          </div>
        </div>

        <!-- AI Recommendations card -->
        <div class="card-ai">
          <div class="card-ai__title">✨ AI Recommendations</div>
          <div class="card-ai__desc">Based on skills, availability, and office needs</div>
          <div class="card-ai__list">
            <div class="rec-item">
              <div>
                <div class="rec-item__name">John Reyes</div>
                <div class="rec-item__office">Computer Lab</div>
              </div>
              <div class="rec-item__match">
                <div class="rec-item__pct">95%</div>
                <div class="rec-item__label">Match</div>
              </div>
            </div>
            <div class="rec-item">
              <div>
                <div class="rec-item__name">Maria Santos</div>
                <div class="rec-item__office">SDAO Office</div>
              </div>
              <div class="rec-item__match">
                <div class="rec-item__pct">92%</div>
                <div class="rec-item__label">Match</div>
              </div>
            </div>
          </div>
        </div>

      </div>

    </main>
  </div><!-- /.main -->

</div><!-- /.app -->

<script>
  (function () {
    'use strict';

    /* ---- Sidebar toggle (mobile/tablet) ---- */
    var toggle  = document.getElementById('sidebar-toggle');
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebar-overlay');

    function openSidebar() {
      sidebar.classList.add('is-open');
      overlay.classList.add('is-open');
      overlay.setAttribute('aria-hidden', 'false');
      toggle.setAttribute('aria-expanded', 'true');
    }
    function closeSidebar() {
      sidebar.classList.remove('is-open');
      overlay.classList.remove('is-open');
      overlay.setAttribute('aria-hidden', 'true');
      toggle.setAttribute('aria-expanded', 'false');
    }

    toggle.addEventListener('click', function () {
      sidebar.classList.contains('is-open') ? closeSidebar() : openSidebar();
    });
    overlay.addEventListener('click', closeSidebar);

    /* ---- Filter buttons ---- */
    var filterBtns = document.querySelectorAll('.filter-btn');
    var rows = document.querySelectorAll('#table-body tr');

    filterBtns.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var filter = btn.getAttribute('data-filter');

        /* Update button states */
        filterBtns.forEach(function (b) {
          b.classList.remove('filter-btn--active');
          b.classList.add('filter-btn--inactive');
          b.setAttribute('aria-pressed', 'false');
        });
        btn.classList.add('filter-btn--active');
        btn.classList.remove('filter-btn--inactive');
        btn.setAttribute('aria-pressed', 'true');

        /* Filter rows */
        rows.forEach(function (row) {
          var status = row.getAttribute('data-status');
          row.style.display = (filter === 'all' || status === filter) ? '' : 'none';
        });
      });
    });

    /* ---- Live search ---- */
    var searchInput = document.getElementById('search-input');
    searchInput.addEventListener('input', function () {
      var q = this.value.toLowerCase().trim();
      rows.forEach(function (row) {
        var text = row.textContent.toLowerCase();
        row.style.display = (!q || text.includes(q)) ? '' : 'none';
      });
    });

  }());
</script>

</body>
</html>