<?php
// login.php – NU SAMS Login Page
// Student Assistant Management System | National University – Lipa

session_start();
require_once __DIR__ . '/db.php';

$has_status_column = false;
if ($colRes = $mysqli->query("SHOW COLUMNS FROM student_applications LIKE 'application_status'")) {
  $has_status_column = $colRes->num_rows > 0;
  $colRes->free();
}

// PHP: handle form submission
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $student_id = trim($_POST['student_id'] ?? '');
  $password   = trim($_POST['password']   ?? '');

  if ($student_id === '' || $password === '') {
    $error = 'Please fill in all fields.';
  } else {
    // First: check if this is the hard-coded admin account
    if ($student_id === 'Admin' && $password === 'Admin123') {
      // Admin login (shared login page)
      $_SESSION['admin_id']   = 1;
      $_SESSION['admin_name'] = 'Admin';
      $_SESSION['admin_role'] = 'SDAO Head';
      $_SESSION['department'] = 'NU Lipa - Student Development and Activities Office';

      header('Location: admin/dashboard.php');
      exit;
    }

    // Otherwise, treat as student login
    $statusSelect = $has_status_column ? ', application_status' : '';
    $stmt = $mysqli->prepare("SELECT full_name, student_id, password_hash{$statusSelect} FROM student_applications WHERE student_id = ? ORDER BY created_at DESC LIMIT 1");

    if ($stmt) {
      $stmt->bind_param('s', $student_id);
      $stmt->execute();
      $result = $stmt->get_result();

      if ($row = $result->fetch_assoc()) {
        if (!empty($row['password_hash']) && password_verify($password, $row['password_hash'])) {
          $application_status = strtolower(trim((string) ($row['application_status'] ?? 'pending')));

          if ($application_status === 'approved') {
            // Successful student login for approved applicants
            $_SESSION['student_id']   = $row['student_id'];
            $_SESSION['student_name'] = $row['full_name'];

            header('Location: student/dashboard.php');
            exit;
          }

          // Not yet approved: send student to status page only.
          unset($_SESSION['student_id'], $_SESSION['student_name']);
          header('Location: status.php?student_id=' . urlencode((string) $row['student_id']));
          exit;
        } else {
          $error = 'Invalid credentials. Please try again.';
        }
      } else {
        $error = 'Account not found. Please register first.';
      }

      $stmt->close();
    } else {
      $error = 'Database error. Please try again later.';
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login – SAMS | NU Lipa</title>
  <meta name="description" content="Login to SAMS – the Student Assistant Management System for National University Lipa." />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;900&display=swap" rel="stylesheet" />
  <style>
    /* =============================================
       CSS VARIABLES / DESIGN TOKENS
    ============================================= */
    :root {
      --color-primary:        #003087;
      --color-primary-end:    #004aab;
      --color-gold:           #ffb81c;
      --color-dark:           #101828;
      --color-body:           #364153;
      --color-muted:          #4a5565;
      --color-muted-2:        #6a7282;
      --color-blue-light:     #dbeafe;
      --color-blue-pale:      #bedbff;
      --color-white:          #ffffff;
      --color-border:         #e5e7eb;
      --color-input-border:   #d1d5dc;
      --color-placeholder:    rgba(10,10,10,0.5);
      --color-page-bg-start:  #eff6ff;
      --color-page-bg-mid:    #ffffff;
      --color-page-bg-end:    #fffbeb;

      --grad-primary:         linear-gradient(90deg, #003087 0%, #004aab 100%);
      --grad-primary-134:     linear-gradient(134deg, #003087 0%, #004aab 100%);
      --grad-page:            linear-gradient(149deg, var(--color-page-bg-start) 0%, var(--color-page-bg-mid) 50%, var(--color-page-bg-end) 100%);

      --shadow-card:          0 25px 50px 0 rgba(0,0,0,.25);

      --radius-sm:   10px;
      --radius-md:   14px;
      --radius-lg:   16px;
      --radius-xl:   24px;

      --font-xs:   12px;
      --font-sm:   14px;
      --font-base: 16px;
      --font-lg:   18px;
      --font-xl:   20px;
      --font-2xl:  24px;
      --font-3xl:  30px;
      --font-4xl:  36px;

      --space-1:   4px;
      --space-2:   8px;
      --space-3:   12px;
      --space-4:   16px;
      --space-5:   20px;
      --space-6:   24px;
      --space-7:   28px;
      --space-8:   32px;
      --space-10:  40px;
      --space-12:  48px;
    }

    /* =============================================
       RESET & BASE
    ============================================= */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html, body {
      height: 100%;
    }
    body {
      font-family: 'Inter', sans-serif;
      font-size: var(--font-base);
      color: var(--color-dark);
      background: var(--grad-page);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      -webkit-font-smoothing: antialiased;
    }
    a { text-decoration: none; color: inherit; }
    img { display: block; }
    button, input { font-family: inherit; }

    /* =============================================
       PAGE WRAPPER – centres the two-card layout
    ============================================= */
    .page {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: var(--space-12) var(--space-4);
    }

    /* =============================================
       TWO-CARD CONTAINER
    ============================================= */
    .login-wrap {
      display: grid;
      grid-template-columns: 560px 560px;
      gap: 32px;
      width: 100%;
      max-width: 1152px;
    }

    /* =============================================
       LEFT PANEL  (blue brand card)
    ============================================= */
    .brand-card {
      background: var(--grad-primary-134);
      border: 4px solid var(--color-gold);
      border-radius: var(--radius-xl);
      box-shadow: var(--shadow-card);
      padding: var(--space-12);
      display: flex;
      flex-direction: column;
      gap: 0;
      min-height: 581px;
    }

    /* Brand row */
    .brand-card__header {
      display: flex;
      align-items: center;
      gap: var(--space-3);
      margin-bottom: 32px;        /* 144px top - 48px padding - 64px row ≈ 32px gap to heading */
    }
    .brand-card__logo {
      width: 64px;
      height: 64px;
      border-radius: var(--radius-lg);
      background: var(--color-white);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: var(--font-3xl);
      font-weight: 900;
      color: var(--color-primary);
      flex-shrink: 0;
    }
    .brand-card__title {
      font-size: var(--font-3xl);
      font-weight: 900;
      color: var(--color-white);
      line-height: 1.2;
    }
    .brand-card__subtitle {
      font-size: var(--font-base);
      font-weight: 500;
      color: var(--color-blue-pale);
      line-height: 1.5;
    }

    /* Heading & paragraph */
    .brand-card__heading {
      font-size: var(--font-4xl);
      font-weight: 900;
      color: var(--color-white);
      line-height: 1.25;
      margin-bottom: var(--space-4);
    }
    .brand-card__desc {
      font-size: var(--font-xl);
      font-weight: 500;
      color: var(--color-blue-light);
      line-height: 1.4;
      margin-bottom: var(--space-8);
      max-width: 437px;
    }

    /* Features list */
    .brand-card__features {
      background: rgba(255,255,255,.10);
      border-radius: var(--radius-lg);
      padding: var(--space-6) var(--space-6) var(--space-6);
      display: flex;
      flex-direction: column;
      gap: var(--space-4);
    }
    .brand-card__feature {
      display: flex;
      align-items: flex-start;
      gap: var(--space-3);
      min-height: 48px;
    }
    .brand-card__feature-icon {
      width: 32px;
      height: 32px;
      border-radius: var(--radius-sm);
      background: var(--color-gold);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      flex-shrink: 0;
      margin-top: 4px;
    }
    .brand-card__feature-title {
      font-size: var(--font-base);
      font-weight: 900;
      color: var(--color-white);
      line-height: 1.5;
    }
    .brand-card__feature-sub {
      font-size: var(--font-sm);
      font-weight: 500;
      color: var(--color-blue-pale);
      line-height: 1.43;
    }

    /* =============================================
       RIGHT PANEL  (white login card)
    ============================================= */
    .login-card {
      background: var(--color-white);
      border: 2px solid var(--color-border);
      border-radius: var(--radius-xl);
      box-shadow: var(--shadow-card);
      padding: var(--space-12);
      display: flex;
      flex-direction: column;
      min-height: 581px;
    }

    /* Card heading */
    .login-card__heading {
      font-size: var(--font-4xl);
      font-weight: 900;
      color: var(--color-dark);
      line-height: 1.11;
      margin-bottom: var(--space-2);
    }
    .login-card__tagline {
      font-size: var(--font-base);
      font-weight: 500;
      color: var(--color-muted);
      line-height: 1.5;
      margin-bottom: var(--space-10);
    }

    /* Alert (PHP error feedback) */
    .login-card__alert {
      background: #fef2f2;
      border: 1px solid #fecaca;
      color: #b91c1c;
      border-radius: var(--radius-sm);
      padding: var(--space-3) var(--space-4);
      font-size: var(--font-sm);
      font-weight: 500;
      margin-bottom: var(--space-6);
    }

    /* Form */
    .login-form {
      display: flex;
      flex-direction: column;
      gap: var(--space-6);
    }
    .login-form__group {
      display: flex;
      flex-direction: column;
      gap: var(--space-2);
    }
    .login-form__label {
      font-size: var(--font-sm);
      font-weight: 900;
      color: var(--color-body);
      line-height: 1.43;
    }
    .login-form__input {
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
    }
    .login-form__input::placeholder { color: var(--color-placeholder); }
    .login-form__input:focus {
      border-color: var(--color-primary);
      box-shadow: 0 0 0 3px rgba(0,48,135,.12);
    }

    /* Password wrapper */
    .login-form__password-wrap {
      position: relative;
    }
    .login-form__password-wrap .login-form__input {
      padding-right: 48px;
    }
    .login-form__toggle-pw {
      position: absolute;
      right: 14px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      cursor: pointer;
      padding: 0;
      display: flex;
      align-items: center;
      color: var(--color-muted);
      line-height: 0;
    }
    .login-form__toggle-pw svg { width: 20px; height: 20px; }

    /* Remember + Forgot row */
    .login-form__row {
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .login-form__remember {
      display: flex;
      align-items: center;
      gap: var(--space-2);
      cursor: pointer;
    }
    .login-form__remember input[type="checkbox"] {
      width: 16px;
      height: 16px;
      accent-color: var(--color-primary);
      cursor: pointer;
      flex-shrink: 0;
    }
    .login-form__remember-text {
      font-size: var(--font-sm);
      font-weight: 500;
      color: var(--color-body);
      line-height: 1.43;
    }
    .login-form__forgot {
      font-size: var(--font-sm);
      font-weight: 700;
      color: var(--color-primary);
      line-height: 1.43;
      transition: opacity .2s;
    }
    .login-form__forgot:hover { opacity: .75; }

    /* Submit button */
    .login-form__submit {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: var(--space-3);
      width: 100%;
      height: 60px;
      border: none;
      border-radius: var(--radius-md);
      background: var(--grad-primary);
      color: var(--color-white);
      font-size: var(--font-lg);
      font-weight: 900;
      cursor: pointer;
      transition: opacity .2s;
      line-height: 1;
    }
    .login-form__submit:hover { opacity: .88; }
    .login-form__submit img {
      width: 24px;
      height: 24px;
      flex-shrink: 0;
    }

    /* Footer links below form */
    .login-card__register {
      margin-top: var(--space-6);
      text-align: center;
      font-size: var(--font-base);
      font-weight: 500;
      color: var(--color-muted);
      line-height: 1.5;
    }
    .login-card__register a {
      font-weight: 900;
      color: var(--color-primary);
    }
    .login-card__register a:hover { text-decoration: underline; }

    .login-card__back {
      margin-top: var(--space-4);
      text-align: center;
    }
    .login-card__back a {
      font-size: var(--font-sm);
      font-weight: 500;
      color: var(--color-muted-2);
      transition: color .2s;
    }
    .login-card__back a:hover { color: var(--color-primary); }

    /* =============================================
       RESPONSIVE – TABLET (≤1024px)
    ============================================= */
    @media (max-width: 1024px) {
      .login-wrap {
        grid-template-columns: 1fr;
        max-width: 560px;
      }
      .brand-card { min-height: auto; }
      .login-card { min-height: auto; }
    }

    /* =============================================
       RESPONSIVE – MOBILE (≤768px)
    ============================================= */
    @media (max-width: 768px) {
      .page { padding: var(--space-6) var(--space-4); }

      .brand-card {
        padding: var(--space-8);
      }
      .brand-card__heading { font-size: 26px; }
      .brand-card__desc    { font-size: var(--font-base); }

      .login-card {
        padding: var(--space-8);
      }
      .login-card__heading { font-size: 26px; }
    }
  </style>
</head>
<body>

  <main class="page" role="main">
    <div class="login-wrap">

      <!-- ============================================
           LEFT – Brand / info card
      ============================================= -->
      <aside class="brand-card" aria-label="SAMS information panel">

        <!-- Logo row -->
        <div class="brand-card__header">
          <div class="brand-card__logo" aria-hidden="true">NU</div>
          <div>
            <div class="brand-card__title">SAMS</div>
            <div class="brand-card__subtitle">Student Assistant Management System</div>
          </div>
        </div>

        <!-- Heading -->
        <h1 class="brand-card__heading">Welcome Back! 👋</h1>

        <!-- Description -->
        <p class="brand-card__desc">Login to access your dashboard, view schedules, and manage your duties at SDAO.</p>

        <!-- Features -->
        <div class="brand-card__features">

          <div class="brand-card__feature">
            <div class="brand-card__feature-icon" aria-hidden="true">📅</div>
            <div>
              <div class="brand-card__feature-title">View Your Schedule</div>
              <div class="brand-card__feature-sub">Access your duty schedule anytime, anywhere</div>
            </div>
          </div>

          <div class="brand-card__feature">
            <div class="brand-card__feature-icon" aria-hidden="true">📷</div>
            <div>
              <div class="brand-card__feature-title">Quick Attendance</div>
              <div class="brand-card__feature-sub">Check in/out with QR code and PIN</div>
            </div>
          </div>

          <div class="brand-card__feature">
            <div class="brand-card__feature-icon" aria-hidden="true">🔔</div>
            <div>
              <div class="brand-card__feature-title">Stay Updated</div>
              <div class="brand-card__feature-sub">Get notifications for schedule changes</div>
            </div>
          </div>

        </div>
      </aside>

      <!-- ============================================
           RIGHT – Login form card
      ============================================= -->
      <section class="login-card" aria-labelledby="login-heading">

        <h2 class="login-card__heading" id="login-heading">Login</h2>
        <p class="login-card__tagline">Enter your credentials to access SAMS</p>

        <?php if ($error): ?>
          <div class="login-card__alert" role="alert"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form class="login-form" method="POST" action="" novalidate>

          <!-- Student ID -->
          <div class="login-form__group">
            <label class="login-form__label" for="student_id">Student ID / Username</label>
            <input
              class="login-form__input"
              type="text"
              id="student_id"
              name="student_id"
              placeholder="2021-12345"
              autocomplete="username"
              value="<?php echo htmlspecialchars($_POST['student_id'] ?? ''); ?>"
              required
            />
          </div>

          <!-- Password -->
          <div class="login-form__group">
            <label class="login-form__label" for="password">Password</label>
            <div class="login-form__password-wrap">
              <input
                class="login-form__input"
                type="password"
                id="password"
                name="password"
                placeholder="student123"
                autocomplete="current-password"
                required
              />
              <button
                type="button"
                class="login-form__toggle-pw"
                aria-label="Toggle password visibility"
                id="toggle-pw"
              >
                <!-- Eye icon (show) -->
                <svg id="icon-eye" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                <!-- Eye-off icon (hide) – hidden by default -->
                <svg id="icon-eye-off" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true" style="display:none;">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7a9.97 9.97 0 012.168-3.857M6.53 6.53A9.956 9.956 0 0112 5c4.477 0 8.268 2.943 9.542 7a9.965 9.965 0 01-4.293 5.214M3 3l18 18" />
                </svg>
              </button>
            </div>
          </div>

          <!-- Remember me + Forgot password -->
          <div class="login-form__row">
            <label class="login-form__remember">
              <input type="checkbox" name="remember" id="remember" <?php echo isset($_POST['remember']) ? 'checked' : ''; ?> />
              <span class="login-form__remember-text">Remember me</span>
            </label>
            <a class="login-form__forgot" href="#">Forgot Password?</a>
          </div>

          <!-- Submit -->
          <button class="login-form__submit" type="submit">
            <img
              src="https://www.figma.com/api/mcp/asset/c5624380-e6ad-4e87-ae39-38852df4717f"
              alt=""
              aria-hidden="true"
            />
            Login
          </button>

        </form>

        <!-- Register link -->
        <p class="login-card__register">
          Don't have an account? <a href="register.php">Register as Student Assistant</a>
        </p>

        <!-- Back to home -->
        <div class="login-card__back">
          <a href="index.php">← Back to Home</a>
        </div>

      </section>

    </div>
  </main>

  <script>
    (function () {
      'use strict';

      /* ---- Password visibility toggle ---- */
      var toggleBtn  = document.getElementById('toggle-pw');
      var pwInput    = document.getElementById('password');
      var iconEye    = document.getElementById('icon-eye');
      var iconEyeOff = document.getElementById('icon-eye-off');

      if (toggleBtn && pwInput) {
        toggleBtn.addEventListener('click', function () {
          var isPassword = pwInput.type === 'password';
          pwInput.type        = isPassword ? 'text' : 'password';
          iconEye.style.display    = isPassword ? 'none'  : '';
          iconEyeOff.style.display = isPassword ? ''      : 'none';
          toggleBtn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
        });
      }
    })();
  </script>

  <script>
    (function () {
      'use strict';

      var iconSvgs = {
        'default': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"><rect x="3" y="3" width="18" height="18" rx="4" fill="#EAF2FF"/><path d="M8 8h8v8H8z" stroke="#155DFC" stroke-width="1.8"/><path d="M7 16l3.5-3.5 2.5 2.5L15.5 12 17 13.5" stroke="#155DFC" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'next': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"><path d="M9.5 6.5L15 12l-5.5 5.5" stroke="#155DFC" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'back': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"><path d="M14.5 6.5L9 12l5.5 5.5" stroke="#155DFC" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'lock': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"><rect x="6" y="11" width="12" height="9" rx="2" stroke="#155DFC" stroke-width="1.8"/><path d="M8.5 11V8a3.5 3.5 0 117 0v3" stroke="#155DFC" stroke-width="1.8"/></svg>'
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
        if (hint.indexOf('login') !== -1 || hint.indexOf('submit') !== -1) return 'next';
        if (hint.indexOf('password') !== -1 || hint.indexOf('lock') !== -1) return 'lock';
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