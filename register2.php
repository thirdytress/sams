<?php
// register2.php - Step 3: Upload Requirements
// Validates requirement files, saves them, and stores paths in the session.

session_start();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
    $max_size_5mb = 5 * 1024 * 1024;
    $max_size_2mb = 2 * 1024 * 1024;

    // Requirements based on official SDAO list
    $files = [
        'resume'               => ['label' => 'Resume', 'max' => $max_size_5mb],
        'letter_intent'        => ['label' => 'Letter of Intent addressed to Assistant Director for Academic Services', 'max' => $max_size_5mb],
        'letter_consent_parent'=> ['label' => 'Letter of Consent from Parent with three signature specimen and photocopy of valid ID', 'max' => $max_size_5mb],
        'recommendation_letter'=> ['label' => 'Recommendation Letter from Program Chair/Deans', 'max' => $max_size_5mb],
        'photocopy_grades'     => ['label' => 'Photocopy of Grades 2nd Term AY 25-26', 'max' => $max_size_5mb],
        'class_schedule'       => ['label' => 'Copy of Class Schedule 3rd Term AY 25-26', 'max' => $max_size_5mb],
        'good_moral'           => ['label' => 'Good Moral (from SDAO)', 'max' => $max_size_5mb],
    ];

    $storedFiles = [];

    foreach ($files as $key => $config) {
        if (!isset($_FILES[$key]) || $_FILES[$key]['error'] === UPLOAD_ERR_NO_FILE) {
            $errors[$key] = $config['label'] . ' is required.';
        } elseif ($_FILES[$key]['error'] !== UPLOAD_ERR_OK) {
            $errors[$key] = $config['label'] . ' upload failed.';
        } elseif (!in_array($_FILES[$key]['type'], $allowed_types)) {
            $errors[$key] = $config['label'] . ' must be PDF, JPG, or PNG.';
        } elseif ($_FILES[$key]['size'] > $config['max']) {
            $max_label = ($config['max'] === $max_size_2mb) ? '2MB' : '5MB';
            $errors[$key] = $config['label'] . ' must not exceed ' . $max_label . '.';
        } else {
            // Move uploaded file to a persistent directory
            $uploadDir = __DIR__ . '/uploads/requirements/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $ext      = pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION);
            $safeName = $key . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $target   = $uploadDir . $safeName;

            if (move_uploaded_file($_FILES[$key]['tmp_name'], $target)) {
                $storedFiles[$key] = 'uploads/requirements/' . $safeName;
            } else {
                $errors[$key] = $config['label'] . ' could not be saved.';
            }
        }
    }

    if (empty($errors)) {
        $success = true;

        // Save uploaded file paths into the session so final step can persist them
        $_SESSION['requirements'] = $storedFiles;

        // Proceed to final assessment step
        header('Location: register3.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Student Assistant Application – Step 3</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;900&display=swap" rel="stylesheet" />
    <style>
        /* =============================================
           CSS VARIABLES – Design System
        ============================================= */
        :root {
            --color-primary:        #003087;
            --color-primary-light:  #0047ab;
            --color-heading:        #101828;
            --color-body:           #4a5565;
            --color-label:          #364153;
            --color-muted:          #6a7282;
            --color-disabled:       #99a1af;
            --color-border:         #d1d5dc;
            --color-bg-step-off:    #f3f4f6;
            --color-white:          #ffffff;
            --color-error:          #dc2626;

            --gradient-bg:          linear-gradient(133.69deg, #eff6ff 0%, #ffffff 50%, #fffbeb 100%);
            --gradient-primary:     linear-gradient(159.33deg, #003087 0%, #0047ab 100%);
            --gradient-progress:    linear-gradient(90deg, #003087 0%, #ffb81c 100%);
            --gradient-next-btn:    linear-gradient(90deg, #003087 0%, #0047ab 100%);

            --radius-card:          16px;
            --radius-step:          14px;
            --radius-upload:        14px;
            --radius-btn:           14px;
            --radius-pill:          9999px;

            --shadow-card:          0 10px 15px rgba(0,0,0,.10), 0 4px 6px rgba(0,0,0,.10);

            --font-xs:    12px;
            --font-sm:    14px;
            --font-base:  16px;
            --font-md:    18px;
            --font-lg:    24px;
            --font-xl:    36px;

            --lh-xs:  16px;
            --lh-sm:  20px;
            --lh-base:24px;
            --lh-md:  28px;
            --lh-lg:  32px;
            --lh-xl:  40px;
        }

        /* =============================================
           RESET & BASE
        ============================================= */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html, body {
            height: 100%;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--gradient-bg);
            min-height: 100vh;
            color: var(--color-heading);
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        img {
            display: block;
            max-width: 100%;
        }

        button {
            font-family: inherit;
            cursor: pointer;
            border: none;
            background: none;
        }

        /* =============================================
           PAGE WRAPPER
        ============================================= */
        .page {
            position: relative;
            min-height: 100vh;
            padding: 32px 0 64px;
        }

        /* =============================================
           BACK LINK
        ============================================= */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            position: absolute;
            top: 32px;
            left: 32px;
            font-size: var(--font-base);
            font-weight: 700;
            line-height: var(--lh-base);
            color: var(--color-primary);
            white-space: nowrap;
        }

        .back-link__icon {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }

        /* =============================================
           MAIN CONTAINER
        ============================================= */
        .container {
            max-width: 1024px;
            width: 100%;
            margin: 0 auto;
            padding: 92px 32px 0;
            display: flex;
            flex-direction: column;
            gap: 32px;
        }

        /* =============================================
           HERO / HEADING BLOCK
        ============================================= */
        .hero {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0;
        }

        .hero__icon-wrap {
            width: 64px;
            height: 64px;
            background: var(--gradient-primary);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
        }

        .hero__icon-wrap img {
            width: 32px;
            height: 32px;
        }

        .hero__title {
            font-size: var(--font-xl);
            font-weight: 900;
            line-height: var(--lh-xl);
            color: var(--color-heading);
            text-align: center;
            margin-bottom: 4px;
        }

        .hero__subtitle {
            font-size: var(--font-md);
            font-weight: 500;
            line-height: var(--lh-md);
            color: var(--color-body);
            text-align: center;
        }

        /* =============================================
           PROGRESS CARD
        ============================================= */
        .progress-card {
            background: var(--color-white);
            border-radius: var(--radius-card);
            box-shadow: var(--shadow-card);
            padding: 32px 32px 24px;
        }

        .progress-card__header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }

        .progress-card__step-label {
            font-size: var(--font-sm);
            font-weight: 700;
            line-height: var(--lh-sm);
            color: var(--color-body);
        }

        .progress-card__pct-label {
            font-size: var(--font-sm);
            font-weight: 700;
            line-height: var(--lh-sm);
            color: var(--color-primary);
        }

        .progress-card__bar-track {
            height: 12px;
            background: #e5e7eb;
            border-radius: var(--radius-pill);
            overflow: hidden;
            margin-bottom: 16px;
        }

        .progress-card__bar-fill {
            height: 100%;
            width: 75%;
            border-radius: var(--radius-pill);
            background: var(--gradient-progress);
        }

        /* =============================================
           STEP TABS
        ============================================= */
        .steps {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
        }

        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            padding: 16px;
            border-radius: var(--radius-step);
            text-decoration: none;
            cursor: pointer;
        }

        .step--active {
            background: var(--gradient-primary);
        }

        .step--inactive {
            background: var(--color-bg-step-off);
        }

        .step__icon {
            width: 24px;
            height: 24px;
            flex-shrink: 0;
        }

        .step__label {
            font-size: var(--font-xs);
            font-weight: 700;
            line-height: var(--lh-xs);
            text-align: center;
            white-space: nowrap;
        }

        .step--active  .step__label { color: var(--color-white); }
        .step--inactive .step__label { color: var(--color-disabled); }

        /* =============================================
           UPLOAD CARD
        ============================================= */
        .upload-card {
            background: var(--color-white);
            border-radius: var(--radius-card);
            box-shadow: var(--shadow-card);
            padding: 32px;
        }

        /* =============================================
           SECTION HEADING
        ============================================= */
        .section-heading {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
        }

        .section-heading__icon {
            width: 32px;
            height: 32px;
            flex-shrink: 0;
        }

        .section-heading__title {
            font-size: var(--font-lg);
            font-weight: 900;
            line-height: var(--lh-lg);
            color: var(--color-heading);
            white-space: nowrap;
        }

        /* =============================================
           UPLOAD FIELDS
        ============================================= */
        .upload-fields {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .upload-field {}

        .upload-field__label {
            display: block;
            font-size: var(--font-sm);
            font-weight: 700;
            line-height: var(--lh-sm);
            color: var(--color-label);
            margin-bottom: 8px;
        }

        .upload-field__error {
            display: block;
            font-size: var(--font-xs);
            font-weight: 400;
            line-height: var(--lh-xs);
            color: var(--color-error);
            margin-top: 6px;
        }

        .upload-field__drop-zone {
            border: 2px dashed var(--color-border);
            border-radius: var(--radius-upload);
            height: 232px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0;
            position: relative;
            transition: border-color .2s, background .2s;
            cursor: pointer;
        }

        .upload-field__drop-zone--dragover {
            border-color: var(--color-primary);
            background: rgba(0, 48, 135, .04);
        }

        .upload-field__drop-zone--has-file {
            border-color: #22c55e;
            background: rgba(34, 197, 94, .04);
        }

        .upload-field__drop-zone--error {
            border-color: var(--color-error);
        }

        .upload-field__drop-icon {
            width: 48px;
            height: 48px;
            margin-bottom: 16px;
        }

        .upload-field__drop-title {
            font-size: var(--font-sm);
            font-weight: 700;
            line-height: var(--lh-sm);
            color: var(--color-label);
            text-align: center;
            margin-bottom: 4px;
        }

        .upload-field__drop-hint {
            font-size: var(--font-xs);
            font-weight: 400;
            line-height: var(--lh-xs);
            color: var(--color-muted);
            text-align: center;
            margin-bottom: 16px;
        }

        .upload-field__file-name {
            font-size: var(--font-xs);
            font-weight: 700;
            color: #22c55e;
            text-align: center;
            max-width: 80%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            margin-bottom: 8px;
        }

        /* Hidden real file input */
        .upload-field__input {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
        }

        .upload-field__btn {
            background: var(--color-primary);
            color: var(--color-white);
            font-size: var(--font-base);
            font-weight: 700;
            line-height: var(--lh-base);
            padding: 8px 20px;
            border-radius: var(--radius-btn);
            pointer-events: none; /* click handled by drop zone */
        }

        /* =============================================
           ACTION BUTTONS ROW
        ============================================= */
        .actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            height: 60px;
            padding: 0 24px;
            background: var(--color-white);
            border: 2px solid var(--color-primary);
            border-radius: var(--radius-btn);
            font-size: var(--font-base);
            font-weight: 700;
            line-height: var(--lh-base);
            color: var(--color-primary);
            text-decoration: none;
            white-space: nowrap;
            transition: background .2s, color .2s;
        }

        .btn-back:hover {
            background: rgba(0, 48, 135, .05);
        }

        .btn-back__icon {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }

        .btn-next {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            height: 56px;
            padding: 0 24px;
            background: var(--gradient-next-btn);
            border: none;
            border-radius: var(--radius-btn);
            font-size: var(--font-base);
            font-weight: 700;
            line-height: var(--lh-base);
            color: var(--color-white);
            text-decoration: none;
            white-space: nowrap;
            cursor: pointer;
            transition: opacity .2s;
        }

        .btn-next:hover {
            opacity: .9;
        }

        .btn-next__icon {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }

        /* =============================================
           SUCCESS BANNER
        ============================================= */
        .success-banner {
            background: #dcfce7;
            border: 2px solid #22c55e;
            border-radius: var(--radius-card);
            padding: 16px 24px;
            font-size: var(--font-sm);
            font-weight: 700;
            color: #15803d;
            text-align: center;
        }

        /* =============================================
           HAMBURGER NAV (tablet / mobile)
        ============================================= */
        .nav {
            display: none; /* shown via media query */
        }

        /* =============================================
           RESPONSIVE – TABLET  (≤1024px)
        ============================================= */
        @media (max-width: 1024px) {
            .back-link {
                position: static;
                margin: 0 0 0 24px;
            }

            .page {
                padding: 24px 0 48px;
            }

            .page__top-bar {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 0 24px;
                margin-bottom: 0;
            }

            .nav {
                display: flex;
                align-items: center;
            }

            .nav__hamburger {
                display: flex;
                flex-direction: column;
                gap: 5px;
                width: 32px;
                height: 32px;
                justify-content: center;
                align-items: center;
                background: none;
                border: none;
                cursor: pointer;
                padding: 0;
            }

            .nav__hamburger-bar {
                display: block;
                width: 22px;
                height: 2px;
                background: var(--color-primary);
                border-radius: 2px;
                transition: transform .3s, opacity .3s;
            }

            .nav__hamburger[aria-expanded="true"] .nav__hamburger-bar:nth-child(1) {
                transform: translateY(7px) rotate(45deg);
            }

            .nav__hamburger[aria-expanded="true"] .nav__hamburger-bar:nth-child(2) {
                opacity: 0;
            }

            .nav__hamburger[aria-expanded="true"] .nav__hamburger-bar:nth-child(3) {
                transform: translateY(-7px) rotate(-45deg);
            }

            .nav__menu {
                display: none;
                position: absolute;
                top: 64px;
                right: 24px;
                background: var(--color-white);
                border-radius: var(--radius-card);
                box-shadow: var(--shadow-card);
                padding: 12px 0;
                min-width: 200px;
                z-index: 100;
            }

            .nav__menu--open {
                display: block;
            }

            .nav__item {
                display: block;
                padding: 12px 24px;
                font-size: var(--font-sm);
                font-weight: 700;
                color: var(--color-label);
                text-decoration: none;
                transition: background .2s;
            }

            .nav__item:hover {
                background: var(--color-bg-step-off);
            }

            .nav__item--active {
                color: var(--color-primary);
            }

            .container {
                padding: 24px 24px 0;
            }

            .steps {
                grid-template-columns: repeat(4, 1fr);
                gap: 8px;
            }

            .step {
                padding: 12px 8px;
            }

            .step__label {
                font-size: 10px;
            }

            .hero__title {
                font-size: 28px;
                line-height: 36px;
            }
        }

        /* =============================================
           RESPONSIVE – MOBILE (≤768px)
        ============================================= */
        @media (max-width: 768px) {
            .container {
                padding: 20px 16px 0;
                gap: 20px;
            }

            .page__top-bar {
                padding: 0 16px;
            }

            .hero__title {
                font-size: 22px;
                line-height: 30px;
            }

            .hero__subtitle {
                font-size: var(--font-base);
                line-height: var(--lh-base);
            }

            .hero__icon-wrap {
                width: 52px;
                height: 52px;
                border-radius: 12px;
            }

            .progress-card {
                padding: 20px 16px 16px;
            }

            .steps {
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
            }

            .upload-card {
                padding: 20px 16px;
            }

            .section-heading__title {
                font-size: 18px;
            }

            .upload-field__drop-zone {
                height: 200px;
            }

            .actions {
                gap: 12px;
            }

            .btn-back,
            .btn-next {
                flex: 1;
                justify-content: center;
                height: 52px;
                font-size: var(--font-sm);
            }

            .nav__menu {
                right: 16px;
            }
        }
    </style>
</head>
<body>

<main class="page">

    <!-- ── TOP BAR (back link + hamburger) ── -->
    <div class="page__top-bar">
        <a href="index.php" class="back-link" aria-label="Back to Home">
            <!-- Arrow-left icon (inline SVG matching Figma asset) -->
            <svg class="back-link__icon" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M15.8333 10H4.16667M4.16667 10L10 15.8333M4.16667 10L10 4.16667" stroke="#003087" stroke-width="1.67" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Back to Home
        </a>

        <!-- Hamburger nav (tablet / mobile only) -->
        <nav class="nav" aria-label="Main navigation">
            <button
                class="nav__hamburger"
                aria-expanded="false"
                aria-controls="nav-menu"
                aria-label="Toggle navigation"
            >
                <span class="nav__hamburger-bar"></span>
                <span class="nav__hamburger-bar"></span>
                <span class="nav__hamburger-bar"></span>
            </button>
            <ul id="nav-menu" class="nav__menu" role="list">
                <li><a href="index.php"      class="nav__item">Home</a></li>
                <li><a href="register.php"   class="nav__item">Personal Info</a></li>
                <li><a href="register1.php"  class="nav__item">Academic Info</a></li>
                <li><a href="register2.php"  class="nav__item nav__item--active" aria-current="page">Requirements</a></li>
                <li><a href="register3.php"  class="nav__item">Assessment</a></li>
            </ul>
        </nav>
    </div>
    <!-- /TOP BAR -->

    <div class="container">

        <?php if ($success): ?>
        <div class="success-banner" role="alert">
            ✓ Files uploaded successfully! Proceeding to the next step…
        </div>
        <?php endif; ?>

        <!-- ── HERO ── -->
        <header class="hero">
            <div class="hero__icon-wrap" aria-hidden="true">
                <!-- Graduation-cap icon (SVG inline, matches Figma asset imgIcon1) -->
                <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M16 4L2 11.2727L16 18.5455L30 11.2727L16 4Z" stroke="white" stroke-width="2" stroke-linejoin="round"/>
                    <path d="M7.27271 15.2727V22.5454C7.27271 22.5454 10.9091 26.1818 16 26.1818C21.0909 26.1818 24.7272 22.5454 24.7272 22.5454V15.2727" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M30 11.2727V18.5454" stroke="white" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </div>
            <h1 class="hero__title">Student Assistant Application</h1>
            <p class="hero__subtitle">Complete the 4-step process to apply</p>
        </header>
        <!-- /HERO -->

        <!-- ── PROGRESS CARD ── -->
        <section class="progress-card" aria-label="Application progress">
            <div class="progress-card__header">
                <span class="progress-card__step-label">Step 3 of 4</span>
                <span class="progress-card__pct-label">75% Complete</span>
            </div>

            <div class="progress-card__bar-track" role="progressbar" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100" aria-label="75% complete">
                <div class="progress-card__bar-fill"></div>
            </div>

            <!-- Step tabs -->
            <div class="steps" role="list">
                <!-- Step 1 – Personal Info (completed) -->
                <a href="register.php" class="step step--active" role="listitem">
                    <svg class="step__icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M12 12C14.7614 12 17 9.76142 17 7C17 4.23858 14.7614 2 12 2C9.23858 2 7 4.23858 7 7C7 9.76142 9.23858 12 12 12Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M20.59 22C20.59 18.13 16.74 15 12 15C7.26 15 3.41 18.13 3.41 22" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span class="step__label">Personal Info</span>
                </a>

                <!-- Step 2 – Academic Info (completed) -->
                <a href="register1.php" class="step step--active" role="listitem">
                    <svg class="step__icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M4 19.5V4.5C4 3.4 4.9 2.5 6 2.5H18C19.1 2.5 20 3.4 20 4.5V19.5L12 15.5L4 19.5Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span class="step__label">Academic Info</span>
                </a>

                <!-- Step 3 – Requirements (current) -->
                <div class="step step--active" role="listitem" aria-current="step">
                    <svg class="step__icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M14 2H6C5.46957 2 4.96086 2.21071 4.58579 2.58579C4.21071 2.96086 4 3.46957 4 4V20C4 20.5304 4.21071 21.0391 4.58579 21.4142C4.96086 21.7893 5.46957 22 6 22H18C18.5304 22 19.0391 21.7893 19.4142 21.4142C19.7893 21.0391 20 20.5304 20 20V8L14 2Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M14 2V8H20" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M16 13H8" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M16 17H8" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M10 9H9H8" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span class="step__label">Requirements</span>
                </div>

                <!-- Step 4 – Assessment (upcoming) -->
                <a href="register3.php" class="step step--inactive" role="listitem">
                    <svg class="step__icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <rect x="3" y="3" width="18" height="18" rx="2" stroke="#99a1af" stroke-width="2"/>
                        <path d="M9 9H15" stroke="#99a1af" stroke-width="2" stroke-linecap="round"/>
                        <path d="M9 12H15" stroke="#99a1af" stroke-width="2" stroke-linecap="round"/>
                        <path d="M9 15H12" stroke="#99a1af" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <span class="step__label">Assessment</span>
                </a>
            </div>
        </section>
        <!-- /PROGRESS CARD -->

        <!-- ── UPLOAD CARD ── -->
        <section class="upload-card" aria-labelledby="upload-heading">
            <div class="section-heading">
                <!-- Document icon matching Figma imgIcon2 -->
                <svg class="section-heading__icon" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <rect x="5" y="3" width="22" height="26" rx="3" stroke="#003087" stroke-width="2"/>
                    <path d="M11 10H21" stroke="#003087" stroke-width="2" stroke-linecap="round"/>
                    <path d="M11 15H21" stroke="#003087" stroke-width="2" stroke-linecap="round"/>
                    <path d="M11 20H17" stroke="#003087" stroke-width="2" stroke-linecap="round"/>
                </svg>
                <h2 class="section-heading__title" id="upload-heading">Upload Requirements</h2>
            </div>

            <form method="POST" enctype="multipart/form-data" novalidate>

                <div class="upload-fields">

                    <!-- Field: Resume -->
                    <div class="upload-field">
                        <label class="upload-field__label" for="resume">Resume *</label>
                        <div
                            class="upload-field__drop-zone<?= (!empty($errors['resume'])) ? ' upload-field__drop-zone--error' : '' ?>"
                            id="resume-zone"
                            aria-label="Upload Resume"
                        >
                            <input
                                class="upload-field__input"
                                type="file"
                                id="resume"
                                name="resume"
                                accept=".pdf,.jpg,.jpeg,.png"
                                aria-required="true"
                                aria-describedby="resume-hint"
                            />
                            <svg class="upload-field__drop-icon" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <path d="M32 32L24 24L16 32" stroke="#9ca3af" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M24 24V42" stroke="#9ca3af" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M40.7804 36.78C42.7296 35.7166 44.2716 34.0338 45.1594 32.0013C46.0472 29.9687 46.2285 27.6982 45.6735 25.5497C45.1185 23.4012 43.8582 21.4979 42.1017 20.1399C40.3452 18.782 38.1944 18.0462 35.9804 18.04H33.4804C32.8679 15.6585 31.7157 13.447 30.1081 11.5771C28.5005 9.7072 26.4797 8.22667 24.2084 7.24577C21.9372 6.26487 19.4741 5.80958 16.9983 5.91403C14.5225 6.01849 12.1082 6.67999 9.92907 7.84974C7.74991 9.01949 5.86003 10.665 4.41036 12.6642C2.96069 14.6634 1.98663 16.9617 1.56253 19.3921C1.13843 21.8225 1.27569 24.3189 1.96366 26.6881C2.65162 29.0573 3.87258 31.2337 5.52044 33.06" stroke="#9ca3af" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <p class="upload-field__drop-title">Click to upload or drag and drop</p>
                            <p class="upload-field__drop-hint" id="resume-hint">PDF, JPG, or PNG (Max 5MB)</p>
                            <span class="upload-field__file-name" id="resume-filename" aria-live="polite"></span>
                            <span class="upload-field__btn" aria-hidden="true">Choose File</span>
                        </div>
                        <?php if (!empty($errors['resume'])): ?>
                            <span class="upload-field__error" role="alert"><?= htmlspecialchars($errors['resume']) ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Field: Letter of Intent -->
                    <div class="upload-field">
                        <label class="upload-field__label" for="letter_intent">Letter of Intent addressed to Assistant Director for Academic Services *</label>
                        <div
                            class="upload-field__drop-zone<?= (!empty($errors['letter_intent'])) ? ' upload-field__drop-zone--error' : '' ?>"
                            id="letter-intent-zone"
                            aria-label="Upload Letter of Intent"
                        >
                            <input
                                class="upload-field__input"
                                type="file"
                                id="letter_intent"
                                name="letter_intent"
                                accept=".pdf,.jpg,.jpeg,.png"
                                aria-required="true"
                                aria-describedby="letter-intent-hint"
                            />
                            <svg class="upload-field__drop-icon" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <path d="M32 32L24 24L16 32" stroke="#9ca3af" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M24 24V42" stroke="#9ca3af" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M40.7804 36.78C42.7296 35.7166 44.2716 34.0338 45.1594 32.0013C46.0472 29.9687 46.2285 27.6982 45.6735 25.5497C45.1185 23.4012 43.8582 21.4979 42.1017 20.1399C40.3452 18.782 38.1944 18.0462 35.9804 18.04H33.4804C32.8679 15.6585 31.7157 13.447 30.1081 11.5771C28.5005 9.7072 26.4797 8.22667 24.2084 7.24577C21.9372 6.26487 19.4741 5.80958 16.9983 5.91403C14.5225 6.01849 12.1082 6.67999 9.92907 7.84974C7.74991 9.01949 5.86003 10.665 4.41036 12.6642C2.96069 14.6634 1.98663 16.9617 1.56253 19.3921C1.13843 21.8225 1.27569 24.3189 1.96366 26.6881C2.65162 29.0573 3.87258 31.2337 5.52044 33.06" stroke="#9ca3af" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <p class="upload-field__drop-title">Click to upload or drag and drop</p>
                            <p class="upload-field__drop-hint" id="letter-intent-hint">PDF, JPG, or PNG (Max 5MB)</p>
                            <span class="upload-field__file-name" id="letter-intent-filename" aria-live="polite"></span>
                            <span class="upload-field__btn" aria-hidden="true">Choose File</span>
                        </div>
                        <?php if (!empty($errors['letter_intent'])): ?>
                            <span class="upload-field__error" role="alert"><?= htmlspecialchars($errors['letter_intent']) ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Field: Letter of Consent from Parent -->
                    <div class="upload-field">
                        <label class="upload-field__label" for="letter_consent_parent">Letter of Consent from Parent with three signature specimen and photocopy of valid ID *</label>
                        <div
                            class="upload-field__drop-zone<?= (!empty($errors['letter_consent_parent'])) ? ' upload-field__drop-zone--error' : '' ?>"
                            id="letter-consent-zone"
                            aria-label="Upload Letter of Consent from Parent"
                        >
                            <input
                                class="upload-field__input"
                                type="file"
                                id="letter_consent_parent"
                                name="letter_consent_parent"
                                accept=".pdf,.jpg,.jpeg,.png"
                                aria-required="true"
                                aria-describedby="letter-consent-hint"
                            />
                            <svg class="upload-field__drop-icon" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <path d="M32 32L24 24L16 32" stroke="#9ca3af" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M24 24V42" stroke="#9ca3af" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M40.7804 36.78C42.7296 35.7166 44.2716 34.0338 45.1594 32.0013C46.0472 29.9687 46.2285 27.6982 45.6735 25.5497C45.1185 23.4012 43.8582 21.4979 42.1017 20.1399C40.3452 18.782 38.1944 18.0462 35.9804 18.04H33.4804C32.8679 15.6585 31.7157 13.447 30.1081 11.5771C28.5005 9.7072 26.4797 8.22667 24.2084 7.24577C21.9372 6.26487 19.4741 5.80958 16.9983 5.91403C14.5225 6.01849 12.1082 6.67999 9.92907 7.84974C7.74991 9.01949 5.86003 10.665 4.41036 12.6642C2.96069 14.6634 1.98663 16.9617 1.56253 19.3921C1.13843 21.8225 1.27569 24.3189 1.96366 26.6881C2.65162 29.0573 3.87258 31.2337 5.52044 33.06" stroke="#9ca3af" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <p class="upload-field__drop-title">Click to upload or drag and drop</p>
                            <p class="upload-field__drop-hint" id="letter-consent-hint">PDF, JPG, or PNG (Max 5MB)</p>
                            <span class="upload-field__file-name" id="letter-consent-filename" aria-live="polite"></span>
                            <span class="upload-field__btn" aria-hidden="true">Choose File</span>
                        </div>
                        <?php if (!empty($errors['letter_consent_parent'])): ?>
                            <span class="upload-field__error" role="alert"><?= htmlspecialchars($errors['letter_consent_parent']) ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Field: Recommendation Letter -->
                    <div class="upload-field">
                        <label class="upload-field__label" for="recommendation_letter">Recommendation Letter from Program Chair/Deans *</label>
                        <div
                            class="upload-field__drop-zone<?= (!empty($errors['recommendation_letter'])) ? ' upload-field__drop-zone--error' : '' ?>"
                            id="recommendation-zone"
                            aria-label="Upload Recommendation Letter"
                        >
                            <input
                                class="upload-field__input"
                                type="file"
                                id="recommendation_letter"
                                name="recommendation_letter"
                                accept=".pdf,.jpg,.jpeg,.png"
                                aria-required="true"
                                aria-describedby="recommendation-hint"
                            />
                            <svg class="upload-field__drop-icon" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <path d="M32 32L24 24L16 32" stroke="#9ca3af" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M24 24V42" stroke="#9ca3af" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M40.7804 36.78C42.7296 35.7166 44.2716 34.0338 45.1594 32.0013C46.0472 29.9687 46.2285 27.6982 45.6735 25.5497C45.1185 23.4012 43.8582 21.4979 42.1017 20.1399C40.3452 18.782 38.1944 18.0462 35.9804 18.04H33.4804C32.8679 15.6585 31.7157 13.447 30.1081 11.5771C28.5005 9.7072 26.4797 8.22667 24.2084 7.24577C21.9372 6.26487 19.4741 5.80958 16.9983 5.91403C14.5225 6.01849 12.1082 6.67999 9.92907 7.84974C7.74991 9.01949 5.86003 10.665 4.41036 12.6642C2.96069 14.6634 1.98663 16.9617 1.56253 19.3921C1.13843 21.8225 1.27569 24.3189 1.96366 26.6881C2.65162 29.0573 3.87258 31.2337 5.52044 33.06" stroke="#9ca3af" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <p class="upload-field__drop-title">Click to upload or drag and drop</p>
                            <p class="upload-field__drop-hint" id="recommendation-hint">PDF, JPG, or PNG (Max 5MB)</p>
                            <span class="upload-field__file-name" id="recommendation-filename" aria-live="polite"></span>
                            <span class="upload-field__btn" aria-hidden="true">Choose File</span>
                        </div>
                        <?php if (!empty($errors['recommendation_letter'])): ?>
                            <span class="upload-field__error" role="alert"><?= htmlspecialchars($errors['recommendation_letter']) ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Field: Photocopy of Grades 2nd Term AY 25-26 -->
                    <div class="upload-field">
                        <label class="upload-field__label" for="photocopy_grades">Photocopy of Grades 2nd Term AY 25-26 *</label>
                        <div
                            class="upload-field__drop-zone<?= (!empty($errors['photocopy_grades'])) ? ' upload-field__drop-zone--error' : '' ?>"
                            id="photocopy-grades-zone"
                            aria-label="Upload Photocopy of Grades 2nd Term AY 25-26"
                        >
                            <input
                                class="upload-field__input"
                                type="file"
                                id="photocopy_grades"
                                name="photocopy_grades"
                                accept=".pdf,.jpg,.jpeg,.png"
                                aria-required="true"
                                aria-describedby="photocopy-grades-hint"
                            />
                            <svg class="upload-field__drop-icon" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <path d="M32 32L24 24L16 32" stroke="#9ca3af" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M24 24V42" stroke="#9ca3af" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M40.7804 36.78C42.7296 35.7166 44.2716 34.0338 45.1594 32.0013C46.0472 29.9687 46.2285 27.6982 45.6735 25.5497C45.1185 23.4012 43.8582 21.4979 42.1017 20.1399C40.3452 18.782 38.1944 18.0462 35.9804 18.04H33.4804C32.8679 15.6585 31.7157 13.447 30.1081 11.5771C28.5005 9.7072 26.4797 8.22667 24.2084 7.24577C21.9372 6.26487 19.4741 5.80958 16.9983 5.91403C14.5225 6.01849 12.1082 6.67999 9.92907 7.84974C7.74991 9.01949 5.86003 10.665 4.41036 12.6642C2.96069 14.6634 1.98663 16.9617 1.56253 19.3921C1.13843 21.8225 1.27569 24.3189 1.96366 26.6881C2.65162 29.0573 3.87258 31.2337 5.52044 33.06" stroke="#9ca3af" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <p class="upload-field__drop-title">Click to upload or drag and drop</p>
                            <p class="upload-field__drop-hint" id="photocopy-grades-hint">PDF, JPG, or PNG (Max 5MB)</p>
                            <span class="upload-field__file-name" id="photocopy-grades-filename" aria-live="polite"></span>
                            <span class="upload-field__btn" aria-hidden="true">Choose File</span>
                        </div>
                        <?php if (!empty($errors['photocopy_grades'])): ?>
                            <span class="upload-field__error" role="alert"><?= htmlspecialchars($errors['photocopy_grades']) ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Field: Copy of Class Schedule 3rd Term AY 25-26 -->
                    <div class="upload-field">
                        <label class="upload-field__label" for="class_schedule">Copy of Class Schedule 3rd Term AY 25-26 *</label>
                        <div
                            class="upload-field__drop-zone<?= (!empty($errors['class_schedule'])) ? ' upload-field__drop-zone--error' : '' ?>"
                            id="class-schedule-zone"
                            aria-label="Upload Copy of Class Schedule 3rd Term AY 25-26"
                        >
                            <input
                                class="upload-field__input"
                                type="file"
                                id="class_schedule"
                                name="class_schedule"
                                accept=".pdf,.jpg,.jpeg,.png"
                                aria-required="true"
                                aria-describedby="class-schedule-hint"
                            />
                            <svg class="upload-field__drop-icon" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <path d="M32 32L24 24L16 32" stroke="#9ca3af" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M24 24V42" stroke="#9ca3af" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M40.7804 36.78C42.7296 35.7166 44.2716 34.0338 45.1594 32.0013C46.0472 29.9687 46.2285 27.6982 45.6735 25.5497C45.1185 23.4012 43.8582 21.4979 42.1017 20.1399C40.3452 18.782 38.1944 18.0462 35.9804 18.04H33.4804C32.8679 15.6585 31.7157 13.447 30.1081 11.5771C28.5005 9.7072 26.4797 8.22667 24.2084 7.24577C21.9372 6.26487 19.4741 5.80958 16.9983 5.91403C14.5225 6.01849 12.1082 6.67999 9.92907 7.84974C7.74991 9.01949 5.86003 10.665 4.41036 12.6642C2.96069 14.6634 1.98663 16.9617 1.56253 19.3921C1.13843 21.8225 1.27569 24.3189 1.96366 26.6881C2.65162 29.0573 3.87258 31.2337 5.52044 33.06" stroke="#9ca3af" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <p class="upload-field__drop-title">Click to upload or drag and drop</p>
                            <p class="upload-field__drop-hint" id="class-schedule-hint">PDF, JPG, or PNG (Max 5MB)</p>
                            <span class="upload-field__file-name" id="class-schedule-filename" aria-live="polite"></span>
                            <span class="upload-field__btn" aria-hidden="true">Choose File</span>
                        </div>
                        <?php if (!empty($errors['class_schedule'])): ?>
                            <span class="upload-field__error" role="alert"><?= htmlspecialchars($errors['class_schedule']) ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Field: Good Moral (from SDAO) -->
                    <div class="upload-field">
                        <label class="upload-field__label" for="good_moral">Good Moral (from SDAO) *</label>
                        <div
                            class="upload-field__drop-zone<?= (!empty($errors['good_moral'])) ? ' upload-field__drop-zone--error' : '' ?>"
                            id="good-moral-zone"
                            aria-label="Upload Good Moral from SDAO"
                        >
                            <input
                                class="upload-field__input"
                                type="file"
                                id="good_moral"
                                name="good_moral"
                                accept=".pdf,.jpg,.jpeg,.png"
                                aria-required="true"
                                aria-describedby="good-moral-hint"
                            />
                            <svg class="upload-field__drop-icon" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <path d="M32 32L24 24L16 32" stroke="#9ca3af" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M24 24V42" stroke="#9ca3af" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M40.7804 36.78C42.7296 35.7166 44.2716 34.0338 45.1594 32.0013C46.0472 29.9687 46.2285 27.6982 45.6735 25.5497C45.1185 23.4012 43.8582 21.4979 42.1017 20.1399C40.3452 18.782 38.1944 18.0462 35.9804 18.04H33.4804C32.8679 15.6585 31.7157 13.447 30.1081 11.5771C28.5005 9.7072 26.4797 8.22667 24.2084 7.24577C21.9372 6.26487 19.4741 5.80958 16.9983 5.91403C14.5225 6.01849 12.1082 6.67999 9.92907 7.84974C7.74991 9.01949 5.86003 10.665 4.41036 12.6642C2.96069 14.6634 1.98663 16.9617 1.56253 19.3921C1.13843 21.8225 1.27569 24.3189 1.96366 26.6881C2.65162 29.0573 3.87258 31.2337 5.52044 33.06" stroke="#9ca3af" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <p class="upload-field__drop-title">Click to upload or drag and drop</p>
                            <p class="upload-field__drop-hint" id="good-moral-hint">PDF, JPG, or PNG (Max 5MB)</p>
                            <span class="upload-field__file-name" id="good-moral-filename" aria-live="polite"></span>
                            <span class="upload-field__btn" aria-hidden="true">Choose File</span>
                        </div>
                        <?php if (!empty($errors['good_moral'])): ?>
                            <span class="upload-field__error" role="alert"><?= htmlspecialchars($errors['good_moral']) ?></span>
                        <?php endif; ?>
                    </div>

                </div><!-- /upload-fields -->

                <!-- ── ACTION BUTTONS ── -->
                <div class="actions" style="margin-top: 32px;">
                    <a href="register1.php" class="btn-back">
                        <svg class="btn-back__icon" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M15.8333 10H4.16667M4.16667 10L10 15.8333M4.16667 10L10 4.16667" stroke="#003087" stroke-width="1.67" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Back
                    </a>

                    <button type="submit" class="btn-next">
                        Next
                        <svg class="btn-next__icon" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M4.16667 10H15.8333M15.8333 10L10 4.16667M15.8333 10L10 15.8333" stroke="white" stroke-width="1.67" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                </div>

            </form>
        </section>
        <!-- /UPLOAD CARD -->

    </div><!-- /container -->

</main>

<script>
(function () {
    'use strict';

    /* ── Hamburger menu toggle ── */
    const hamburger = document.querySelector('.nav__hamburger');
    const navMenu   = document.getElementById('nav-menu');

    if (hamburger && navMenu) {
        hamburger.addEventListener('click', function () {
            const expanded = this.getAttribute('aria-expanded') === 'true';
            this.setAttribute('aria-expanded', String(!expanded));
            navMenu.classList.toggle('nav__menu--open', !expanded);
        });

        /* Close menu when clicking outside */
        document.addEventListener('click', function (e) {
            if (!hamburger.contains(e.target) && !navMenu.contains(e.target)) {
                hamburger.setAttribute('aria-expanded', 'false');
                navMenu.classList.remove('nav__menu--open');
            }
        });
    }

    /* ── Upload zone interactions ── */
    const zones = [
        { zoneId: 'resume-zone',           inputId: 'resume',               filenameId: 'resume-filename' },
        { zoneId: 'letter-intent-zone',    inputId: 'letter_intent',        filenameId: 'letter-intent-filename' },
        { zoneId: 'letter-consent-zone',   inputId: 'letter_consent_parent',filenameId: 'letter-consent-filename' },
        { zoneId: 'recommendation-zone',   inputId: 'recommendation_letter',filenameId: 'recommendation-filename' },
        { zoneId: 'photocopy-grades-zone', inputId: 'photocopy_grades',     filenameId: 'photocopy-grades-filename' },
        { zoneId: 'class-schedule-zone',   inputId: 'class_schedule',       filenameId: 'class-schedule-filename' },
        { zoneId: 'good-moral-zone',       inputId: 'good_moral',           filenameId: 'good-moral-filename' },
    ];

    zones.forEach(function (cfg) {
        const zone     = document.getElementById(cfg.zoneId);
        const input    = document.getElementById(cfg.inputId);
        const filename = document.getElementById(cfg.filenameId);

        if (!zone || !input || !filename) return;

        /* Show selected filename */
        input.addEventListener('change', function () {
            if (this.files && this.files[0]) {
                filename.textContent = this.files[0].name;
                zone.classList.add('upload-field__drop-zone--has-file');
                zone.classList.remove('upload-field__drop-zone--error');
            } else {
                filename.textContent = '';
                zone.classList.remove('upload-field__drop-zone--has-file');
            }
        });

        /* Drag-and-drop visual feedback */
        zone.addEventListener('dragover', function (e) {
            e.preventDefault();
            zone.classList.add('upload-field__drop-zone--dragover');
        });

        zone.addEventListener('dragleave', function () {
            zone.classList.remove('upload-field__drop-zone--dragover');
        });

        zone.addEventListener('drop', function (e) {
            e.preventDefault();
            zone.classList.remove('upload-field__drop-zone--dragover');
            if (e.dataTransfer.files && e.dataTransfer.files[0]) {
                /* Transfer dropped file to the hidden input */
                const dt = new DataTransfer();
                dt.items.add(e.dataTransfer.files[0]);
                input.files = dt.files;
                input.dispatchEvent(new Event('change'));
            }
        });
    });

})();
</script>

</body>
</html>