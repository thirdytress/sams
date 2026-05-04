<?php
// application-view.php – Detailed view of a single student application
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../db.php';

$student_id = trim($_GET['student_id'] ?? '');

if ($student_id === '') {
    http_response_code(400);
    echo 'Missing student_id.';
    exit;
}

$stmt = $mysqli->prepare('SELECT * FROM student_applications WHERE student_id = ? ORDER BY created_at DESC LIMIT 1');

if (!$stmt) {
    http_response_code(500);
    echo 'Database error.';
    exit;
}

$stmt->bind_param('s', $student_id);
$stmt->execute();
$result = $stmt->get_result();
$app = $result->fetch_assoc();
$stmt->close();

if (!$app) {
    http_response_code(404);
    echo 'Application not found.';
    exit;
}

function h(string $v): string { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

// Build a browser URL for stored file paths
// DB stores paths like "uploads/requirements/file.pdf" relative to project root.
// From /admin pages we need to go one level up ("../") so the URL resolves.
function file_url(?string $path): string {
    if (!$path) {
        return '';
    }

    // If already absolute ("/..." or "http..."), return as-is
    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0 || strpos($path, '/') === 0) {
        return $path;
    }

    // Default: treat as relative to project root
    return '../' . ltrim($path, '/');
}

$createdDisplay = '';
if (!empty($app['created_at'])) {
    $ts = strtotime($app['created_at']);
    if ($ts !== false) {
        $createdDisplay = date('M d, Y g:i A', $ts);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Application Detail – <?php echo h($app['full_name']); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;900&display=swap" rel="stylesheet" />
    <style>
        :root {
            --color-primary: #155dfc;
            --color-primary-dark: #1447e6;
            --color-gold: #ffb81c;
            --color-dark: #101828;
            --color-body: #4a5565;
            --color-muted: #6b7280;
            --color-border: #e5e7eb;
            --color-bg: #f3f4f6;
            --color-white: #ffffff;

            --color-pending-bg: #fef9c2;
            --color-pending-text: #a65f00;

            --radius-card: 16px;
            --radius-pill: 9999px;
        }

        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 32px;
            background: linear-gradient(135deg, #eff6ff 0%, #ffffff 50%, #fffbeb 100%);
            color: var(--color-dark);
        }
        .shell {
            max-width: 1120px;
            margin: 0 auto;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 14px;
            font-weight: 500;
            color: var(--color-primary);
            text-decoration: none;
            margin-bottom: 16px;
        }
        .back-link:hover { text-decoration: underline; }

        .header-card {
            background: var(--color-white);
            border-radius: var(--radius-card);
            border: 1px solid var(--color-border);
            padding: 20px 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,.08);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }
        .header-main {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .header-avatar {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: linear-gradient(135deg, #155dfc 0%, #9810fa 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 700;
            color: #ffffff;
        }
        .header-text-primary {
            font-size: 20px;
            font-weight: 700;
            color: var(--color-dark);
        }
        .header-text-sub {
            font-size: 13px;
            color: var(--color-body);
        }
        .header-meta {
            text-align: right;
            font-size: 13px;
            color: var(--color-body);
        }
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: var(--radius-pill);
            background: var(--color-pending-bg);
            color: var(--color-pending-text);
            font-size: 12px;
            font-weight: 600;
        }

        .card {
            background: var(--color-white);
            border-radius: var(--radius-card);
            border: 1px solid var(--color-border);
            padding: 24px 28px;
            box-shadow: 0 1px 3px rgba(0,0,0,.08);
            margin-bottom: 20px;
        }
        .card__title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 16px;
            color: var(--color-dark);
        }
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px 32px;
        }
        .field-label {
            font-size: 11px;
            font-weight: 600;
            color: var(--color-muted);
            text-transform: uppercase;
            letter-spacing: .06em;
        }
        .field-value {
            font-size: 14px;
            font-weight: 500;
            color: var(--color-dark);
        }
        .req-list {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px 24px;
            font-size: 14px;
        }
        .req-link {
            color: var(--color-primary);
            text-decoration: none;
            font-weight: 500;
        }
        .req-link:hover { text-decoration: underline; }

        @media (max-width: 768px) {
            body { padding: 16px; }
            .grid-2, .req-list { grid-template-columns: 1fr; }
            .header-card { flex-direction: column; align-items: flex-start; }
            .header-meta { text-align: left; }
        }
    </style>
</head>
<body>
<div class="shell">
    <a href="application.php" class="back-link">← Back to Applications</a>

    <?php
        $nameParts = preg_split('/\s+/', trim($app['full_name'] ?? ''));
        $initials  = '';
        if (!empty($nameParts[0])) {
            $initials .= strtoupper(substr($nameParts[0], 0, 1));
        }
        if (count($nameParts) > 1 && !empty($nameParts[count($nameParts)-1])) {
            $initials .= strtoupper(substr($nameParts[count($nameParts)-1], 0, 1));
        }
    ?>

    <div class="header-card">
        <div class="header-main">
            <div class="header-avatar" aria-hidden="true"><?php echo h($initials ?: 'SA'); ?></div>
            <div>
                <div class="header-text-primary"><?php echo h($app['full_name']); ?></div>
                <div class="header-text-sub">Student ID: <?php echo h($app['student_id']); ?> • <?php echo h($app['course']); ?></div>
            </div>
        </div>
        <div class="header-meta">
            <div class="status-pill">Pending review</div>
            <?php if ($createdDisplay): ?>
                <div style="margin-top:6px;">Submitted <?php echo h($createdDisplay); ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card__title">Applicant Information</div>
        <div class="grid-2">
            <div>
                <div class="field-label">Full Name</div>
                <div class="field-value"><?php echo h($app['full_name']); ?></div>
            </div>
            <div>
                <div class="field-label">Student ID</div>
                <div class="field-value"><?php echo h($app['student_id']); ?></div>
            </div>
            <div>
                <div class="field-label">Email</div>
                <div class="field-value"><?php echo h($app['email']); ?></div>
            </div>
            <div>
                <div class="field-label">Contact Number</div>
                <div class="field-value"><?php echo h($app['contact_number']); ?></div>
            </div>
            <div>
                <div class="field-label">Date of Birth</div>
                <div class="field-value"><?php echo h($app['date_of_birth']); ?></div>
            </div>
            <div>
                <div class="field-label">Gender</div>
                <div class="field-value"><?php echo h($app['gender']); ?></div>
            </div>
            <div>
                <div class="field-label">Submitted At</div>
                <div class="field-value"><?php echo h($createdDisplay); ?></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card__title">Academic Information</div>
        <div class="grid-2">
            <div>
                <div class="field-label">Course / Program</div>
                <div class="field-value"><?php echo h($app['course']); ?></div>
            </div>
            <div>
                <div class="field-label">Year Level</div>
                <div class="field-value"><?php echo h($app['year_level']); ?></div>
            </div>
            <div>
                <div class="field-label">GPA</div>
                <div class="field-value"><?php echo h($app['gpa']); ?></div>
            </div>
            <div>
                <div class="field-label">Previous SDAO Experience</div>
                <div class="field-value"><?php echo h($app['sdao_experience']); ?></div>
            </div>
            <div>
                <div class="field-label">Available Hours per Week</div>
                <div class="field-value"><?php echo h($app['hours_per_week']); ?></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card__title">Preferences & Skills</div>
        <div class="grid-2">
            <div>
                <div class="field-label">Preferred Work Location</div>
                <div class="field-value"><?php echo h($app['work_location']); ?></div>
            </div>
            <div>
                <div class="field-label">Preferred Work Schedule</div>
                <div class="field-value"><?php echo h($app['work_schedule']); ?></div>
            </div>
            <div style="grid-column: 1 / -1;">
                <div class="field-label">Skills</div>
                <div class="field-value"><?php echo h($app['skills']); ?></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card__title">Submitted Requirements</div>
        <div class="req-list">
            <div>
                <span class="field-label">Resume</span><br />
                <?php if (!empty($app['resume_path'])): ?>
                    <a class="req-link" href="<?php echo h(file_url($app['resume_path'])); ?>" target="_blank">View file</a>
                <?php else: ?>
                    <span class="field-value">Not uploaded</span>
                <?php endif; ?>
            </div>
            <div>
                <span class="field-label">Letter of Intent</span><br />
                <?php if (!empty($app['letter_intent_path'])): ?>
                    <a class="req-link" href="<?php echo h(file_url($app['letter_intent_path'])); ?>" target="_blank">View file</a>
                <?php else: ?>
                    <span class="field-value">Not uploaded</span>
                <?php endif; ?>
            </div>
            <div>
                <span class="field-label">Parent Consent</span><br />
                <?php if (!empty($app['letter_consent_parent_path'])): ?>
                    <a class="req-link" href="<?php echo h(file_url($app['letter_consent_parent_path'])); ?>" target="_blank">View file</a>
                <?php else: ?>
                    <span class="field-value">Not uploaded</span>
                <?php endif; ?>
            </div>
            <div>
                <span class="field-label">Recommendation Letter</span><br />
                <?php if (!empty($app['recommendation_letter_path'])): ?>
                    <a class="req-link" href="<?php echo h(file_url($app['recommendation_letter_path'])); ?>" target="_blank">View file</a>
                <?php else: ?>
                    <span class="field-value">Not uploaded</span>
                <?php endif; ?>
            </div>
            <div>
                <span class="field-label">Photocopy of Grades</span><br />
                <?php if (!empty($app['photocopy_grades_path'])): ?>
                    <a class="req-link" href="<?php echo h(file_url($app['photocopy_grades_path'])); ?>" target="_blank">View file</a>
                <?php else: ?>
                    <span class="field-value">Not uploaded</span>
                <?php endif; ?>
            </div>
            <div>
                <span class="field-label">Class Schedule</span><br />
                <?php if (!empty($app['class_schedule_path'])): ?>
                    <a class="req-link" href="<?php echo h(file_url($app['class_schedule_path'])); ?>" target="_blank">View file</a>
                <?php else: ?>
                    <span class="field-value">Not uploaded</span>
                <?php endif; ?>
            </div>
            <div>
                <span class="field-label">Good Moral</span><br />
                <?php if (!empty($app['good_moral_path'])): ?>
                    <a class="req-link" href="<?php echo h(file_url($app['good_moral_path'])); ?>" target="_blank">View file</a>
                <?php else: ?>
                    <span class="field-value">Not uploaded</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>
