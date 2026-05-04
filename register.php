<?php
// register.php – NU SAMS Student Assistant Application (Step 1: Personal Information)
// Student Assistant Management System | National University – Lipa

session_start();
require_once __DIR__ . '/db.php';

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/mail_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$errors = [];
$otp_errors = [];
$otp_notice = '';
$otp_notice_type = 'success';
$otp_stage = isset($_SESSION['register_otp_pending']) && is_array($_SESSION['register_otp_pending']);

$otp_max_attempts = 5;
$otp_resend_cooldown = 60;
$otp_resend_available_in = 0;
$otp_attempts_used = 0;
$otp_attempts_remaining = $otp_max_attempts;

$values = [
  'full_name'      => '',
  'student_id'     => '',
  'email'          => '',
  'contact_number' => '',
  'date_of_birth'  => '',
  'gender'         => '',
];

if ($otp_stage) {
  $pending_values = $_SESSION['register_otp_pending']['values'] ?? [];
  if (is_array($pending_values)) {
    foreach ($values as $k => $v) {
      if (isset($pending_values[$k])) {
        $values[$k] = (string) $pending_values[$k];
      }
    }
  }

  $otp_attempts_used = (int) ($_SESSION['register_otp_pending']['failed_attempts'] ?? 0);
  if ($otp_attempts_used < 0) {
    $otp_attempts_used = 0;
  }
  if ($otp_attempts_used > $otp_max_attempts) {
    $otp_attempts_used = $otp_max_attempts;
  }
  $otp_attempts_remaining = max(0, $otp_max_attempts - $otp_attempts_used);

  $last_otp_sent_at = (int) ($_SESSION['register_otp_pending']['last_sent_at'] ?? $_SESSION['register_otp_pending']['created_at'] ?? 0);
  $otp_resend_available_in = max(0, ($last_otp_sent_at + $otp_resend_cooldown) - time());
}

function generate_otp_code(int $length = 6): string {
  $min = (int) pow(10, $length - 1);
  $max = (int) pow(10, $length) - 1;
  return (string) random_int($min, $max);
}

function build_otp_email_html(string $toName, string $otpCode): string {
  $safeName = htmlspecialchars($toName !== '' ? $toName : 'Applicant');
  $safeOtp = htmlspecialchars($otpCode);
  $logoHtml = '';

  if (defined('MAIL_LOGO_URL') && trim((string) MAIL_LOGO_URL) !== '') {
    $logoHtml = '<img src="' . htmlspecialchars((string) MAIL_LOGO_URL) . '" alt="National University" style="width:64px;height:64px;object-fit:contain;border-radius:12px;background:#fff;padding:6px;display:block;margin:0 auto 12px auto;" />';
  } else {
    $logoHtml = '<div style="width:64px;height:64px;border-radius:12px;background:#ffffff;color:#003087;font-size:30px;line-height:64px;text-align:center;font-weight:900;margin:0 auto 12px auto;">NU</div>';
  }

  return '<div style="margin:0;padding:24px;background:#f3f7ff;font-family:Inter,Segoe UI,Arial,sans-serif;color:#111827;">'
    . '<table role="presentation" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #dbeafe;">'
    . '<tr><td style="background:linear-gradient(135deg,#003087 0%,#0047ab 100%);padding:24px;text-align:center;color:#ffffff;">'
    . $logoHtml
    . '<div style="font-size:22px;font-weight:800;letter-spacing:.3px;">NU SAMS Email Verification</div>'
    . '<div style="font-size:13px;opacity:.9;margin-top:6px;">National University - Lipa</div>'
    . '</td></tr>'
    . '<tr><td style="padding:24px;">'
    . '<p style="margin:0 0 12px 0;font-size:15px;">Hello <strong>' . $safeName . '</strong>,</p>'
    . '<p style="margin:0 0 16px 0;font-size:14px;line-height:1.6;color:#334155;">Use the OTP below to continue your Student Assistant registration.</p>'
    . '<div style="margin:0 auto 16px auto;width:max-content;padding:10px 18px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:12px;font-size:30px;font-weight:800;letter-spacing:6px;color:#003087;">' . $safeOtp . '</div>'
    . '<p style="margin:0 0 8px 0;font-size:13px;color:#475569;">This OTP expires in <strong>10 minutes</strong>.</p>'
    . '<p style="margin:0;font-size:13px;color:#64748b;">If you did not request this code, you can safely ignore this email.</p>'
    . '</td></tr>'
    . '</table>'
    . '</div>';
}

function send_otp_email(string $toEmail, string $toName, string $otpCode, string &$error): bool {
  $mail = new PHPMailer(true);

  try {
    $smtpPassword = (string) MAIL_PASSWORD;
    // Gmail app passwords are sometimes pasted with spaces (e.g. "abcd efgh ijkl mnop").
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

    $mail->isHTML(true);
    $mail->Subject = 'NU SAMS Registration OTP';
    $mail->Body = build_otp_email_html($toName, $otpCode);
    $mail->AltBody = "Your NU SAMS registration OTP is {$otpCode}. It expires in 10 minutes.";

    $mail->send();
    return true;
  } catch (Exception $ex) {
    $error = 'Unable to send OTP email. Please check your SMTP settings. Mailer error: ' . $mail->ErrorInfo;
    if (stripos($mail->ErrorInfo, 'Could not authenticate') !== false) {
      $error .= ' For Gmail, use an App Password (not your regular Gmail password) and enable 2-Step Verification.';
    }
    return false;
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $otp_action = trim((string) ($_POST['otp_action'] ?? 'send_otp'));

  if ($otp_action === 'verify_otp') {
    $otp_input = trim((string) ($_POST['otp_code'] ?? ''));
    $pending = $_SESSION['register_otp_pending'] ?? null;

    if (!is_array($pending)) {
      $otp_errors['otp_code'] = 'OTP session not found. Please submit the form again.';
      $otp_stage = false;
    } else {
      $failed_attempts = (int) ($pending['failed_attempts'] ?? 0);
      if ($failed_attempts >= $otp_max_attempts) {
        unset($_SESSION['register_otp_pending']);
        $otp_errors['otp_code'] = 'Maximum OTP attempts reached. Please restart your registration details.';
        $otp_stage = false;
        $otp_notice = 'OTP session locked after too many failed attempts.';
        $otp_notice_type = 'error';
      } else {
        $expires = (int) ($pending['expires_at'] ?? 0);
        if ($expires < time()) {
          $otp_errors['otp_code'] = 'OTP has expired. Please resend a new OTP.';
        } elseif (!preg_match('/^\d{6}$/', $otp_input)) {
          $otp_errors['otp_code'] = 'Enter the 6-digit OTP code.';
        } else {
          $input_hash = hash('sha256', $otp_input);
          $stored_hash = (string) ($pending['otp_hash'] ?? '');
          if (!hash_equals($stored_hash, $input_hash)) {
            $failed_attempts++;
            $_SESSION['register_otp_pending']['failed_attempts'] = $failed_attempts;
            $remaining = max(0, $otp_max_attempts - $failed_attempts);
            if ($remaining === 0) {
              unset($_SESSION['register_otp_pending']);
              $otp_errors['otp_code'] = 'Maximum OTP attempts reached. Please restart your registration details.';
              $otp_stage = false;
              $otp_notice = 'OTP session locked after too many failed attempts.';
              $otp_notice_type = 'error';
            } else {
              $otp_errors['otp_code'] = 'Invalid OTP code. Please try again.';
              $otp_notice = 'Incorrect OTP. ' . $remaining . ' attempt(s) remaining.';
              $otp_notice_type = 'error';
            }
          } else {
            $pending_values = $pending['values'] ?? [];
            $password_hash = (string) ($pending['password_hash'] ?? '');
            if (!is_array($pending_values) || $password_hash === '') {
              $otp_errors['otp_code'] = 'OTP session is incomplete. Please submit the form again.';
            } else {
              $_SESSION['step1'] = $pending_values;
              $_SESSION['step1']['password_hash'] = $password_hash;
              unset($_SESSION['register_otp_pending']);

              header('Location: register1.php');
              exit;
            }
          }
        }
      }
      $otp_stage = isset($_SESSION['register_otp_pending']) && is_array($_SESSION['register_otp_pending']);
    }
  } elseif ($otp_action === 'resend_otp') {
    $pending = $_SESSION['register_otp_pending'] ?? null;
    if (!is_array($pending)) {
      $otp_errors['otp_code'] = 'OTP session not found. Please submit the form again.';
      $otp_stage = false;
    } else {
      $failed_attempts = (int) ($pending['failed_attempts'] ?? 0);
      if ($failed_attempts >= $otp_max_attempts) {
        unset($_SESSION['register_otp_pending']);
        $otp_errors['otp_code'] = 'OTP session locked after too many failed attempts. Please submit your details again.';
        $otp_stage = false;
        $otp_notice = 'OTP session expired due to failed attempts.';
        $otp_notice_type = 'error';
      } else {
        $last_sent_at = (int) ($pending['last_sent_at'] ?? $pending['created_at'] ?? 0);
        $seconds_left = max(0, ($last_sent_at + $otp_resend_cooldown) - time());
        if ($seconds_left > 0) {
          $otp_errors['otp_code'] = 'Please wait ' . $seconds_left . ' seconds before resending OTP.';
          $otp_notice_type = 'error';
          $otp_stage = true;
        } else {
          $otp_code = generate_otp_code(6);
          $mail_error = '';
          $to_email = (string) ($pending['email'] ?? '');
          $to_name = (string) (($pending['values']['full_name'] ?? '') ?: 'Applicant');

          if ($to_email === '') {
            $otp_errors['otp_code'] = 'Email address is missing. Please submit the form again.';
          } elseif (send_otp_email($to_email, $to_name, $otp_code, $mail_error)) {
            $_SESSION['register_otp_pending']['otp_hash'] = hash('sha256', $otp_code);
            $_SESSION['register_otp_pending']['expires_at'] = time() + 600;
            $_SESSION['register_otp_pending']['last_sent_at'] = time();
            $otp_notice = 'A new OTP has been sent to your email.';
            $otp_notice_type = 'success';
          } else {
            $otp_errors['otp_code'] = $mail_error;
          }
        }
      }
      $otp_stage = isset($_SESSION['register_otp_pending']) && is_array($_SESSION['register_otp_pending']);
    }
  } elseif ($otp_action === 'edit_details') {
    unset($_SESSION['register_otp_pending']);
    $otp_stage = false;
  } else {
  $values['full_name']      = trim($_POST['full_name']      ?? '');
  $values['student_id']     = trim($_POST['student_id']     ?? '');
  $values['email']          = trim($_POST['email']          ?? '');
  $values['contact_number'] = trim($_POST['contact_number'] ?? '');
  $values['date_of_birth']  = trim($_POST['date_of_birth']  ?? '');
  $values['gender']         = trim($_POST['gender']         ?? '');

  $password         = $_POST['password']         ?? '';
  $confirm_password = $_POST['confirm_password'] ?? '';

  if ($values['full_name']      === '') $errors['full_name']      = 'Full name is required.';
  if ($values['student_id']     === '') $errors['student_id']     = 'Student ID is required.';
  if ($values['email']          === '') $errors['email']          = 'Email address is required.';
  elseif (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email address.';
  if ($values['contact_number'] === '') $errors['contact_number'] = 'Contact number is required.';
  if ($values['date_of_birth']  === '') $errors['date_of_birth']  = 'Date of birth is required.';
  if ($values['gender']         === '') $errors['gender']         = 'Gender is required.';

  if ($password === '') {
    $errors['password'] = 'Password is required.';
  } elseif (strlen($password) < 8) {
    $errors['password'] = 'Password must be at least 8 characters.';
  } elseif (!preg_match('/[^a-zA-Z0-9]/', $password)) {
    $errors['password'] = 'Password must include at least one special character.';
  }

  if ($confirm_password === '') {
    $errors['confirm_password'] = 'Please confirm your password.';
  } elseif ($password !== '' && $password !== $confirm_password) {
    $errors['confirm_password'] = 'Passwords do not match.';
  }

  // Uniqueness checks against existing applications
  if (empty($errors) && isset($mysqli)) {
    $uniqueFields = [
      'full_name'      => 'Full name',
      'student_id'     => 'Student ID',
      'email'          => 'Email address',
      'contact_number' => 'Contact number',
    ];

    foreach ($uniqueFields as $field => $label) {
      $value = $values[$field] ?? '';
      if ($value === '') {
        continue;
      }

      $sql = "SELECT 1 FROM student_applications WHERE $field = ? LIMIT 1";
      if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param('s', $value);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
          $errors[$field] = $label . ' is already registered.';
        }

        $stmt->close();
      }
    }
  }

  if (empty($errors)) {
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $otp_code = generate_otp_code(6);
    $mail_error = '';

    if (send_otp_email($values['email'], $values['full_name'], $otp_code, $mail_error)) {
      $_SESSION['register_otp_pending'] = [
        'values' => $values,
        'password_hash' => $password_hash,
        'email' => $values['email'],
        'otp_hash' => hash('sha256', $otp_code),
        'expires_at' => time() + 600,
        'created_at' => time(),
        'last_sent_at' => time(),
        'failed_attempts' => 0,
      ];

      $otp_notice = 'OTP sent to ' . $values['email'] . '. Enter the 6-digit code to continue.';
      $otp_notice_type = 'success';
      $otp_stage = true;
    } else {
      $errors['email'] = $mail_error;
    }
  }
  }
}

if ($otp_stage && isset($_SESSION['register_otp_pending']) && is_array($_SESSION['register_otp_pending'])) {
  $otp_attempts_used = (int) ($_SESSION['register_otp_pending']['failed_attempts'] ?? 0);
  if ($otp_attempts_used < 0) {
    $otp_attempts_used = 0;
  }
  if ($otp_attempts_used > $otp_max_attempts) {
    $otp_attempts_used = $otp_max_attempts;
  }
  $otp_attempts_remaining = max(0, $otp_max_attempts - $otp_attempts_used);

  $last_otp_sent_at = (int) ($_SESSION['register_otp_pending']['last_sent_at'] ?? $_SESSION['register_otp_pending']['created_at'] ?? 0);
  $otp_resend_available_in = max(0, ($last_otp_sent_at + $otp_resend_cooldown) - time());
}

function val(string $key, array $values): string {
    return htmlspecialchars($values[$key] ?? '');
}
function err(string $key, array $errors): string {
    return isset($errors[$key])
        ? '<p class="form__error" role="alert">' . htmlspecialchars($errors[$key]) . '</p>'
        : '';
}
function fieldClass(string $key, array $errors): string {
    return isset($errors[$key]) ? 'form__input form__input--error' : 'form__input';
}

function otpErr(string $key, array $errors): string {
  return isset($errors[$key])
    ? '<p class="form__error" role="alert">' . htmlspecialchars($errors[$key]) . '</p>'
    : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Apply – Student Assistant | SAMS NU Lipa</title>
  <meta name="description" content="Apply as a Student Assistant at National University Lipa through SAMS." />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;900&display=swap" rel="stylesheet" />
  <style>
    /* =============================================
       CSS VARIABLES / DESIGN TOKENS
    ============================================= */
    :root {
      --color-primary:       #003087;
      --color-primary-end:   #004aab;
      --color-gold:          #ffb81c;
      --color-dark:          #101828;
      --color-body:          #364153;
      --color-muted:         #4a5565;
      --color-muted-light:   #99a1af;
      --color-white:         #ffffff;
      --color-border:        #e5e7eb;
      --color-input-border:  #d1d5dc;
      --color-input-ph:      rgba(10,10,10,.50);
      --color-bg-step-off:   #f3f4f6;
      --color-page-bg-start: #eff6ff;
      --color-page-bg-mid:   #ffffff;
      --color-page-bg-end:   #fffbeb;
      --color-error:         #b91c1c;
      --color-error-bg:      #fef2f2;
      --color-error-border:  #fecaca;

      --grad-primary:       linear-gradient(90deg,  #003087 0%, #004aab 100%);
      --grad-primary-135:   linear-gradient(135deg, #003087 0%, #004aab 100%);
      --grad-primary-159:   linear-gradient(159deg, #003087 0%, #004aab 100%);
      --grad-progress:      linear-gradient(90deg,  #003087 0%, #ffb81c 100%);
      --grad-page:          linear-gradient(145deg, var(--color-page-bg-start) 0%, var(--color-page-bg-mid) 50%, var(--color-page-bg-end) 100%);

      --shadow-card: 0 10px 15px 0 rgba(0,0,0,.10), 0 4px 6px 0 rgba(0,0,0,.10);

      --radius-sm:  10px;
      --radius-md:  14px;
      --radius-lg:  16px;

      --font-xs:   12px;
      --font-sm:   14px;
      --font-base: 16px;
      --font-lg:   18px;
      --font-xl:   24px;
      --font-2xl:  36px;

      --space-1:  4px;
      --space-2:  8px;
      --space-3:  12px;
      --space-4:  16px;
      --space-5:  20px;
      --space-6:  24px;
      --space-8:  32px;
      --space-10: 40px;
      --space-12: 48px;
    }

    /* =============================================
       RESET & BASE
    ============================================= */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html, body { min-height: 100%; }
    body {
      font-family: 'Inter', sans-serif;
      font-size: var(--font-base);
      color: var(--color-dark);
      background: var(--grad-page);
      -webkit-font-smoothing: antialiased;
    }
    a { text-decoration: none; color: inherit; }
    img { display: block; }
    button, input, select { font-family: inherit; }

    /* =============================================
       PAGE LAYOUT
    ============================================= */
    .page {
      max-width: 1024px;
      margin-inline: auto;
      padding: var(--space-8) var(--space-8) var(--space-12);
    }

    /* =============================================
       BACK LINK
    ============================================= */
    .back-link {
      display: inline-flex;
      align-items: center;
      gap: var(--space-2);
      font-size: var(--font-base);
      font-weight: 700;
      color: var(--color-primary);
      margin-bottom: var(--space-8);
      transition: opacity .2s;
    }
    .back-link:hover { opacity: .75; }
    .back-link__icon { width: 20px; height: 20px; flex-shrink: 0; }

    /* =============================================
       PAGE HEADER
    ============================================= */
    .page-header {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 0;
      margin-bottom: var(--space-8);
    }
    .page-header__icon-wrap {
      width: 64px;
      height: 64px;
      border-radius: var(--radius-lg);
      background: var(--grad-primary-135);
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: var(--space-5);
    }
    .page-header__icon-wrap img { width: 32px; height: 32px; }
    .page-header__title {
      font-size: var(--font-2xl);
      font-weight: 900;
      color: var(--color-dark);
      text-align: center;
      line-height: 1.1;
      margin-bottom: var(--space-2);
    }
    .page-header__subtitle {
      font-size: var(--font-lg);
      font-weight: 500;
      color: var(--color-muted);
      text-align: center;
    }

    /* =============================================
       PROGRESS CARD
    ============================================= */
    .progress-card {
      background: var(--color-white);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow-card);
      padding: var(--space-8);
      margin-bottom: var(--space-8);
    }
    .progress-card__meta {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: var(--space-5);
    }
    .progress-card__step-label {
      font-size: var(--font-sm);
      font-weight: 700;
      color: var(--color-muted);
    }
    .progress-card__pct-label {
      font-size: var(--font-sm);
      font-weight: 700;
      color: var(--color-primary);
    }
    .progress-card__bar-track {
      height: 12px;
      background: var(--color-border);
      border-radius: 9999px;
      overflow: hidden;
      margin-bottom: var(--space-6);
    }
    .progress-card__bar-fill {
      height: 100%;
      width: 25%;
      background: var(--grad-progress);
      border-radius: 9999px;
    }

    /* Step tabs */
    .progress-card__steps {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: var(--space-4);
    }
    .step-tab {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: var(--space-2);
      padding: var(--space-4) var(--space-4);
      border-radius: var(--radius-md);
      background: var(--color-bg-step-off);
      cursor: pointer;
      border: none;
      transition: background .2s;
      text-decoration: none;
    }
    .step-tab--active {
      background: var(--grad-primary-159);
    }
    .step-tab__icon { width: 24px; height: 24px; flex-shrink: 0; }
    .step-tab__label {
      font-size: var(--font-xs);
      font-weight: 700;
      color: var(--color-muted-light);
      text-align: center;
      white-space: nowrap;
    }
    .step-tab--active .step-tab__label { color: var(--color-white); }

    /* SVG icons for step tabs */
    .step-tab__svg { width: 24px; height: 24px; flex-shrink: 0; }
    .step-tab--active .step-tab__svg { color: var(--color-white); }
    .step-tab:not(.step-tab--active) .step-tab__svg { color: var(--color-muted-light); }

    /* =============================================
       FORM CARD
    ============================================= */
    .form-card {
      background: var(--color-white);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow-card);
      padding: var(--space-8);
      margin-bottom: var(--space-8);
    }
    .form-card__heading {
      display: flex;
      align-items: center;
      gap: var(--space-3);
      margin-bottom: var(--space-6);
    }
    .form-card__heading-icon { width: 32px; height: 32px; flex-shrink: 0; }
    .form-card__heading-text {
      font-size: var(--font-xl);
      font-weight: 900;
      color: var(--color-dark);
    }

    /* Two-column grid */
    .form__grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: var(--space-6) var(--space-6);
    }

    /* Field group */
    .form__group {
      display: flex;
      flex-direction: column;
      gap: var(--space-2);
    }
    .form__label {
      font-size: var(--font-sm);
      font-weight: 700;
      color: var(--color-body);
      line-height: 1.43;
    }
    .form__input {
      width: 100%;
      height: 52px;
      border: 2px solid var(--color-input-border);
      border-radius: var(--radius-md);
      padding: 12px 16px;
      font-size: var(--font-base);
      font-weight: 500;
      color: var(--color-dark);
      background: var(--color-white);
      outline: none;
      transition: border-color .2s, box-shadow .2s;
      line-height: normal;
      appearance: none;
      -webkit-appearance: none;
    }
    .form__input::placeholder { color: var(--color-input-ph); }
    .form__input:focus {
      border-color: var(--color-primary);
      box-shadow: 0 0 0 3px rgba(0,48,135,.12);
    }
    .form__input--error {
      border-color: var(--color-error);
    }
    .form__input--error:focus {
      border-color: var(--color-error);
      box-shadow: 0 0 0 3px rgba(185,28,28,.12);
    }
    .form__error {
      font-size: var(--font-xs);
      font-weight: 500;
      color: var(--color-error);
      line-height: 1.4;
    }

    .otp-notice {
      border-radius: var(--radius-md);
      padding: 12px 14px;
      margin-bottom: var(--space-5);
      font-size: var(--font-sm);
      font-weight: 600;
    }
    .otp-notice--success {
      background: #ecfdf3;
      border: 1px solid #a7f3d0;
      color: #065f46;
    }
    .otp-notice--error {
      background: var(--color-error-bg);
      border: 1px solid var(--color-error-border);
      color: var(--color-error);
    }

    .otp-card {
      background: var(--color-white);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow-card);
      padding: var(--space-8);
      margin-bottom: var(--space-8);
      border: 2px solid #bedbff;
    }

    .otp-card__title {
      font-size: var(--font-lg);
      font-weight: 800;
      margin-bottom: var(--space-2);
      color: var(--color-primary);
    }

    .otp-card__desc {
      font-size: var(--font-sm);
      color: var(--color-body);
      margin-bottom: var(--space-5);
    }

    .otp-form {
      display: flex;
      flex-direction: column;
      gap: var(--space-4);
    }

    .otp-actions {
      display: flex;
      gap: var(--space-3);
      flex-wrap: wrap;
    }

    .otp-meta {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: var(--space-3);
      margin-bottom: var(--space-4);
      font-size: var(--font-sm);
      color: var(--color-body);
      flex-wrap: wrap;
    }

    .otp-meta__danger {
      color: var(--color-error);
      font-weight: 700;
    }

    .otp-actions .is-disabled,
    .otp-actions .is-disabled:hover {
      opacity: .6;
      cursor: not-allowed;
    }

    /* Select specific */
    .form__select-wrap {
      position: relative;
    }
    .form__select-wrap::after {
      content: '';
      position: absolute;
      right: 16px;
      top: 50%;
      transform: translateY(-50%);
      width: 0;
      height: 0;
      border-left: 5px solid transparent;
      border-right: 5px solid transparent;
      border-top: 6px solid var(--color-muted);
      pointer-events: none;
    }
    .form__select {
      width: 100%;
      height: 52px;
      border: 2px solid var(--color-input-border);
      border-radius: var(--radius-md);
      padding: 12px 36px 12px 16px;
      font-size: var(--font-base);
      font-weight: 500;
      color: var(--color-dark);
      background: var(--color-white);
      outline: none;
      transition: border-color .2s, box-shadow .2s;
      appearance: none;
      -webkit-appearance: none;
      cursor: pointer;
    }
    .form__select:focus {
      border-color: var(--color-primary);
      box-shadow: 0 0 0 3px rgba(0,48,135,.12);
    }
    .form__select--error { border-color: var(--color-error); }
    .form__select option[value=""] { color: var(--color-input-ph); }

    /* =============================================
       NAVIGATION BUTTONS
    ============================================= */
    .form-nav {
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .form-nav__back {
      display: inline-flex;
      align-items: center;
      gap: var(--space-2);
      height: 56px;
      padding: 0 var(--space-8);
      border-radius: var(--radius-md);
      background: var(--color-border);
      border: none;
      cursor: pointer;
      font-size: var(--font-base);
      font-weight: 700;
      color: var(--color-muted-light);
      text-decoration: none;
      transition: background .2s;
    }
    .form-nav__back:hover { background: #d1d5db; }
    .form-nav__back img { width: 20px; height: 20px; }
    .form-nav__next {
      display: inline-flex;
      align-items: center;
      gap: var(--space-2);
      height: 56px;
      padding: 0 var(--space-8);
      border-radius: var(--radius-md);
      background: var(--grad-primary);
      border: none;
      cursor: pointer;
      font-size: var(--font-base);
      font-weight: 700;
      color: var(--color-white);
      text-decoration: none;
      transition: opacity .2s;
    }
    .form-nav__next:hover { opacity: .88; }
    .form-nav__next img { width: 20px; height: 20px; }

    /* =============================================
       RESPONSIVE – TABLET (≤1024px)
    ============================================= */
    @media (max-width: 1024px) {
      .page { padding-inline: var(--space-6); }
      .progress-card__steps { grid-template-columns: repeat(4, 1fr); gap: var(--space-2); }
      .step-tab { padding: var(--space-3) var(--space-2); }
    }

    /* =============================================
       RESPONSIVE – MOBILE (≤768px)
    ============================================= */
    @media (max-width: 768px) {
      .page { padding: var(--space-4) var(--space-4) var(--space-10); }
      .page-header__title { font-size: 26px; }
      .page-header__subtitle { font-size: var(--font-base); }

      .progress-card__steps {
        grid-template-columns: repeat(2, 1fr);
        gap: var(--space-2);
      }

      .form__grid {
        grid-template-columns: 1fr;
        gap: var(--space-4);
      }

      .form-nav__back,
      .form-nav__next { padding: 0 var(--space-6); }
    }
  </style>
</head>
<body>

  <main class="page" role="main">

    <!-- Back to Home -->
    <a class="back-link" href="index.php">
      <img
        class="back-link__icon"
        src="https://www.figma.com/api/mcp/asset/d7b8649b-6ce9-4b30-bc12-f47e2ec8ace8"
        alt=""
        aria-hidden="true"
      />
      Back to Home
    </a>

    <!-- Page header -->
    <header class="page-header">
      <div class="page-header__icon-wrap" aria-hidden="true">
        <img
          src="https://www.figma.com/api/mcp/asset/02563ae0-6f2d-41f7-970b-4365358e3511"
          alt="Graduation cap icon"
        />
      </div>
      <h1 class="page-header__title">Student Assistant Application</h1>
      <p class="page-header__subtitle">Complete the 4-step process to apply</p>
    </header>

    <!-- Progress card -->
    <div class="progress-card" aria-label="Application progress">
      <div class="progress-card__meta">
        <span class="progress-card__step-label">Step 1 of 4</span>
        <span class="progress-card__pct-label">25% Complete</span>
      </div>

      <div class="progress-card__bar-track" role="progressbar" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100" aria-label="Application progress 25%">
        <div class="progress-card__bar-fill"></div>
      </div>

      <!-- Step tabs -->
      <nav class="progress-card__steps" aria-label="Application steps">

        <!-- Step 1 – active -->
        <div class="step-tab step-tab--active" aria-current="step" aria-label="Step 1: Personal Info (current)">
          <svg class="step-tab__svg" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
          </svg>
          <span class="step-tab__label">Personal Info</span>
        </div>

        <!-- Step 2 -->
        <a class="step-tab" href="#" aria-label="Step 2: Academic Info (not yet available)">
          <svg class="step-tab__svg" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
          </svg>
          <span class="step-tab__label">Academic Info</span>
        </a>

        <!-- Step 3 -->
        <a class="step-tab" href="#" aria-label="Step 3: Requirements (not yet available)">
          <svg class="step-tab__svg" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          <span class="step-tab__label">Requirements</span>
        </a>

        <!-- Step 4 -->
        <a class="step-tab" href="#" aria-label="Step 4: Assessment (not yet available)">
          <svg class="step-tab__svg" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
          </svg>
          <span class="step-tab__label">Assessment</span>
        </a>

      </nav>
    </div>

    <!-- Personal Information form card -->
    <section class="form-card" aria-labelledby="personal-info-heading">
      <div class="form-card__heading">
        <img
          class="form-card__heading-icon"
          src="https://www.figma.com/api/mcp/asset/4163249c-8cd8-47ad-bbd7-1bf885fe7a65"
          alt=""
          aria-hidden="true"
        />
        <h2 class="form-card__heading-text" id="personal-info-heading">Personal Information</h2>
      </div>

      <?php if ($otp_notice !== ''): ?>
        <div class="otp-notice <?php echo $otp_notice_type === 'error' ? 'otp-notice--error' : 'otp-notice--success'; ?>">
          <?php echo htmlspecialchars($otp_notice); ?>
        </div>
      <?php endif; ?>

      <form class="personal-form" method="POST" action="" novalidate id="personal-form">
        <input type="hidden" name="otp_action" value="send_otp" />

        <div class="form__grid">

          <!-- Full Name -->
          <div class="form__group">
            <label class="form__label" for="full_name">Full Name *</label>
            <input
              class="<?php echo fieldClass('full_name', $errors); ?>"
              type="text"
              id="full_name"
              name="full_name"
              placeholder="Juan Dela Cruz"
              value="<?php echo val('full_name', $values); ?>"
              autocomplete="name"
              required
            />
            <?php echo err('full_name', $errors); ?>
          </div>

          <!-- Student ID -->
          <div class="form__group">
            <label class="form__label" for="student_id">Student ID *</label>
            <input
              class="<?php echo fieldClass('student_id', $errors); ?>"
              type="text"
              id="student_id"
              name="student_id"
              placeholder="2021-12345"
              value="<?php echo val('student_id', $values); ?>"
              autocomplete="off"
              required
            />
            <?php echo err('student_id', $errors); ?>
          </div>

          <!-- Email Address -->
          <div class="form__group">
            <label class="form__label" for="email">Email Address *</label>
            <input
              class="<?php echo fieldClass('email', $errors); ?>"
              type="email"
              id="email"
              name="email"
              placeholder="juan.delacruz@nu-lipa.edu.ph"
              value="<?php echo val('email', $values); ?>"
              autocomplete="email"
              required
            />
            <?php echo err('email', $errors); ?>
          </div>

          <!-- Contact Number -->
          <div class="form__group">
            <label class="form__label" for="contact_number">Contact Number *</label>
            <input
              class="<?php echo fieldClass('contact_number', $errors); ?>"
              type="tel"
              id="contact_number"
              name="contact_number"
              placeholder="09XX-XXX-XXXX"
              value="<?php echo val('contact_number', $values); ?>"
              autocomplete="tel"
              required
            />
            <?php echo err('contact_number', $errors); ?>
          </div>

          <!-- Date of Birth -->
          <div class="form__group">
            <label class="form__label" for="date_of_birth">Date of Birth *</label>
            <input
              class="<?php echo fieldClass('date_of_birth', $errors); ?>"
              type="date"
              id="date_of_birth"
              name="date_of_birth"
              value="<?php echo val('date_of_birth', $values); ?>"
              required
            />
            <?php echo err('date_of_birth', $errors); ?>
          </div>

          <!-- Gender -->
          <div class="form__group">
            <label class="form__label" for="gender">Gender *</label>
            <div class="form__select-wrap">
              <select
                class="form__select<?php echo isset($errors['gender']) ? ' form__select--error' : ''; ?>"
                id="gender"
                name="gender"
                required
              >
                <option value="" <?php echo $values['gender'] === '' ? 'selected' : ''; ?>>Select gender</option>
                <option value="male"   <?php echo $values['gender'] === 'male'   ? 'selected' : ''; ?>>Male</option>
                <option value="female" <?php echo $values['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                <option value="other"  <?php echo $values['gender'] === 'other'  ? 'selected' : ''; ?>>Prefer not to say</option>
              </select>
            </div>
            <?php echo err('gender', $errors); ?>
          </div>

          <!-- Password -->
          <div class="form__group">
            <label class="form__label" for="password">Password *</label>
            <input
              class="<?php echo fieldClass('password', $errors); ?>"
              type="password"
              id="password"
              name="password"
              placeholder="Create a password"
              autocomplete="new-password"
              required
            />
            <?php echo err('password', $errors); ?>
          </div>

          <!-- Confirm Password -->
          <div class="form__group">
            <label class="form__label" for="confirm_password">Confirm Password *</label>
            <input
              class="<?php echo fieldClass('confirm_password', $errors); ?>"
              type="password"
              id="confirm_password"
              name="confirm_password"
              placeholder="Re-enter your password"
              autocomplete="new-password"
              required
            />
            <?php echo err('confirm_password', $errors); ?>
          </div>

        </div><!-- /.form__grid -->

      </form>
    </section>

    <?php if ($otp_stage): ?>
    <section class="otp-card" aria-labelledby="otp-heading">
      <h3 class="otp-card__title" id="otp-heading">Email OTP Verification</h3>
      <p class="otp-card__desc">
        We sent a 6-digit OTP to
        <strong><?php echo htmlspecialchars($_SESSION['register_otp_pending']['email'] ?? $values['email']); ?></strong>.
        Enter it below to continue.
      </p>

      <div class="otp-meta">
        <span class="<?php echo $otp_attempts_remaining <= 2 ? 'otp-meta__danger' : ''; ?>">
          Attempts remaining: <strong><?php echo (int) $otp_attempts_remaining; ?>/<?php echo (int) $otp_max_attempts; ?></strong>
        </span>
        <span id="otpCooldownText" data-seconds-left="<?php echo (int) $otp_resend_available_in; ?>">
          <?php if ($otp_resend_available_in > 0): ?>
            Resend available in <?php echo (int) $otp_resend_available_in; ?>s
          <?php else: ?>
            You can resend OTP now
          <?php endif; ?>
        </span>
      </div>

      <form method="POST" action="" class="otp-form" novalidate>
        <input type="hidden" name="otp_action" value="verify_otp" />
        <div class="form__group">
          <label class="form__label" for="otp_code">OTP Code *</label>
          <input
            class="<?php echo fieldClass('otp_code', $otp_errors); ?>"
            type="text"
            id="otp_code"
            name="otp_code"
            placeholder="Enter 6-digit OTP"
            inputmode="numeric"
            maxlength="6"
            pattern="[0-9]{6}"
            required
          />
          <?php echo otpErr('otp_code', $otp_errors); ?>
        </div>

        <div class="otp-actions">
          <button class="form-nav__next" type="submit">Verify OTP</button>
        </div>
      </form>
    </section>
    <?php endif; ?>

    <!-- Navigation buttons -->
    <div class="form-nav">

      <a class="form-nav__back" href="index.php" aria-label="Go back to home">
        <img
          src="https://www.figma.com/api/mcp/asset/62767a77-da7b-4e87-8a2d-384e7bf5b1eb"
          alt=""
          aria-hidden="true"
        />
        Back
      </a>

      <?php if (!$otp_stage): ?>
        <button
          class="form-nav__next"
          type="submit"
          form="personal-form"
          aria-label="Proceed to OTP verification"
        >
          Next
          <img
            src="https://www.figma.com/api/mcp/asset/2372ea79-f063-4762-93b1-9d0f4285ac4a"
            alt=""
            aria-hidden="true"
          />
        </button>
      <?php else: ?>
        <div class="otp-actions">
          <form method="POST" action="" style="display:inline;">
            <input type="hidden" name="otp_action" value="resend_otp" />
            <button
              class="form-nav__next <?php echo $otp_resend_available_in > 0 ? 'is-disabled' : ''; ?>"
              type="submit"
              id="resendOtpButton"
              aria-label="Resend OTP"
              <?php echo $otp_resend_available_in > 0 ? 'disabled' : ''; ?>
            >
              Resend OTP
            </button>
          </form>
          <form method="POST" action="" style="display:inline;">
            <input type="hidden" name="otp_action" value="edit_details" />
            <button class="form-nav__back" type="submit" aria-label="Edit details">Edit Details</button>
          </form>
        </div>
      <?php endif; ?>

    </div>

  </main>

  <script>
    (function () {
      'use strict';

      /* ---- Client-side validation before submit ---- */
      var form = document.getElementById('personal-form');
      if (form) {
        form.addEventListener('submit', function (e) {
          var valid = true;
          var fields = form.querySelectorAll('[required]');

          fields.forEach(function (field) {
            var group = field.closest('.form__group');
            var existingErr = group ? group.querySelector('.form__client-error') : null;
            if (existingErr) existingErr.remove();

            field.classList.remove('form__input--error', 'form__select--error');

            if (!field.value.trim()) {
              valid = false;
              field.classList.add(field.tagName === 'SELECT' ? 'form__select--error' : 'form__input--error');
              if (group) {
                var errEl = document.createElement('p');
                errEl.className = 'form__error form__client-error';
                errEl.setAttribute('role', 'alert');
                errEl.textContent = 'This field is required.';
                group.appendChild(errEl);
              }
            } else if (field.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(field.value)) {
              valid = false;
              field.classList.add('form__input--error');
              if (group) {
                var errEl = document.createElement('p');
                errEl.className = 'form__error form__client-error';
                errEl.setAttribute('role', 'alert');
                errEl.textContent = 'Enter a valid email address.';
                group.appendChild(errEl);
              }
            }
          });

          if (!valid) {
            e.preventDefault();
            var firstErr = form.querySelector('.form__input--error, .form__select--error');
            if (firstErr) firstErr.focus();
          }
        });
      }
    })();
  </script>

  <script>
    (function () {
      'use strict';

      var cooldownText = document.getElementById('otpCooldownText');
      var resendBtn = document.getElementById('resendOtpButton');
      if (!cooldownText || !resendBtn) {
        return;
      }

      var secondsLeft = parseInt(cooldownText.getAttribute('data-seconds-left') || '0', 10);
      if (isNaN(secondsLeft) || secondsLeft < 0) {
        secondsLeft = 0;
      }

      function render() {
        if (secondsLeft > 0) {
          cooldownText.textContent = 'Resend available in ' + secondsLeft + 's';
          resendBtn.disabled = true;
          resendBtn.classList.add('is-disabled');
        } else {
          cooldownText.textContent = 'You can resend OTP now';
          resendBtn.disabled = false;
          resendBtn.classList.remove('is-disabled');
        }
      }

      render();
      if (secondsLeft > 0) {
        var timer = setInterval(function () {
          secondsLeft--;
          render();
          if (secondsLeft <= 0) {
            clearInterval(timer);
          }
        }, 1000);
      }
    })();
  </script>

  <script>
    (function () {
      'use strict';

      var iconSvgs = {
        'default': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"><rect x="3" y="3" width="18" height="18" rx="4" fill="#EAF2FF"/><path d="M8 8h8v8H8z" stroke="#155DFC" stroke-width="1.8"/><path d="M7 16l3.5-3.5 2.5 2.5L15.5 12 17 13.5" stroke="#155DFC" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'back': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"><path d="M14.5 6.5L9 12l5.5 5.5" stroke="#155DFC" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'next': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"><path d="M9.5 6.5L15 12l-5.5 5.5" stroke="#155DFC" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'cap': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"><path d="M3 9l9-4 9 4-9 4-9-4z" stroke="#155DFC" stroke-width="1.8"/><path d="M7 11.5V15c0 .7 2.2 2 5 2s5-1.3 5-2v-3.5" stroke="#155DFC" stroke-width="1.8" stroke-linecap="round"/></svg>',
        'user': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="3.5" stroke="#155DFC" stroke-width="1.8"/><path d="M5 19c0-3 3.1-5.2 7-5.2s7 2.2 7 5.2" stroke="#155DFC" stroke-width="1.8" stroke-linecap="round"/></svg>'
      };

      function fallbackSrcFor(key) {
        var svg = iconSvgs[key] || iconSvgs.default;
        return 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg);
      }

      function iconKeyFor(img) {
        var cls = String(img.className || '').toLowerCase();
        var alt = String(img.getAttribute('alt') || '').toLowerCase();
        var text = String((img.closest('a,button,li,div') || {}).textContent || '').toLowerCase();
        var hint = cls + ' ' + alt + ' ' + text;

        if (hint.indexOf('back') !== -1 || hint.indexOf('home') !== -1) return 'back';
        if (hint.indexOf('next') !== -1 || hint.indexOf('proceed') !== -1) return 'next';
        if (hint.indexOf('graduation') !== -1 || hint.indexOf('cap') !== -1 || hint.indexOf('header') !== -1) return 'cap';
        if (hint.indexOf('personal') !== -1 || hint.indexOf('information') !== -1 || hint.indexOf('heading-icon') !== -1) return 'user';
        return 'default';
      }

      function setFallback(img) {
        if (!img || img.getAttribute('data-icon-fallback') === '1') {
          return;
        }
        img.setAttribute('data-icon-fallback', '1');
        img.src = fallbackSrcFor(iconKeyFor(img));
      }

      document.querySelectorAll('img[src*="figma.com/api/mcp/asset"]').forEach(function (img) {
        img.addEventListener('error', function () { setFallback(img); }, { once: true });
        if (img.complete && img.naturalWidth === 0) {
          setFallback(img);
        }
      });
    })();
  </script>

</body>
</html>