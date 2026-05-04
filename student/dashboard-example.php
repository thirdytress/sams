<?php
/**
 * SAMS Integration Example
 * Shows how to use the new database architecture with existing files
 * 
 * This file demonstrates:
 * 1. Using the new Environment + Database classes
 * 2. Authentication flow
 * 3. File upload handling
 * 4. Schedule parsing
 * 5. Auto-scheduling
 * 6. Attendance tracking
 */

// ============================================
// STEP 1: Initialize System
// ============================================

session_start();

// Load environment and database
require_once __DIR__ . '/config/Environment.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/config/FileUpload.php';
require_once __DIR__ . '/src/Auth/AuthManager.php';
require_once __DIR__ . '/src/Schedule/ScheduleParser.php';
require_once __DIR__ . '/src/Schedule/AutoScheduler.php';

// ============================================
// STEP 2: Authentication
// ============================================

// Check if user is logged in
if (!AuthManager::isAuthenticated()) {
    header('Location: /login.php');
    exit;
}

$userId = AuthManager::getUserId();
$userType = $_SESSION['user_type'] ?? 'student';

// For students, get student_id
$studentId = null;
if ($userType === 'student') {
    $studentId = $_SESSION['student_id'] ?? null;
    
    if (!$studentId) {
        // Fetch from database if not in session
        $profile = Database::queryOne(
            "SELECT user_id FROM student_profiles WHERE user_id = ?",
            [$userId]
        );
        if ($profile) {
            $_SESSION['student_id'] = $profile['user_id'];
            $studentId = $profile['user_id'];
        }
    }
}

// ============================================
// STEP 3: Handle File Uploads (COR)
// ============================================

$uploadMessage = '';
$uploadError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['cor_file'])) {
    try {
        $upload = new FileUpload();
        $result = $upload->upload($_FILES['cor_file'], $studentId);
        
        if ($result['success']) {
            // Save upload record to database
            $corId = Database::insert('cor_uploads', [
                'student_id' => $studentId,
                'file_name' => $result['filename'],
                'file_path' => $result['filepath'],
                'file_type' => $result['mime_type'],
                'file_size' => $result['size']
            ]);
            
            $uploadMessage = "COR file uploaded successfully! Processing...";
            
            // Automatically parse the COR
            $parser = new ScheduleParser($studentId);
            $parseResult = $parser->parseUsingGemini($result['filepath']);
            
            if ($parseResult['success']) {
                $parser->saveToDatabase($corId);
                
                // Identify free time slots
                $freeTimeResult = $parser->identifyFreeTimeSlots();
                
                if ($freeTimeResult['success']) {
                    $uploadMessage .= " Schedule extracted and free time slots identified!";
                    
                    // Generate auto schedule
                    $scheduler = new AutoScheduler($studentId);
                    $scheduleResult = $scheduler->generate();
                    
                    if ($scheduleResult['success']) {
                        $uploadMessage .= " Work schedule generated!";
                    }
                }
            } else {
                $uploadError = "Parsing failed: " . ($parseResult['error'] ?? 'Unknown error');
            }
        } else {
            $uploadError = "Upload failed: " . implode(', ', $result['errors']);
        }
    } catch (Exception $e) {
        $uploadError = "Error: " . $e->getMessage();
    }
}

// ============================================
// STEP 4: Get Student Data
// ============================================

// Get student profile
$student = Database::queryOne(
    "SELECT u.*, sp.* FROM users u 
     LEFT JOIN student_profiles sp ON u.id = sp.user_id 
     WHERE u.id = ?",
    [$userId]
);

// Get class schedule
$schedule = Database::query(
    "SELECT * FROM class_schedules WHERE student_id = ? ORDER BY day_of_week, start_time",
    [$studentId]
);

// Get free time slots
$freeSlots = Database::query(
    "SELECT * FROM free_time_slots WHERE student_id = ? ORDER BY day_of_week, start_time",
    [$studentId]
);

// Get generated work schedule
$workSchedule = Database::queryOne(
    "SELECT * FROM generated_work_schedules WHERE student_id = ? AND is_active = ? 
     ORDER BY generated_at DESC LIMIT 1",
    [$studentId, true]
);

// Get attendance statistics
$stats = Database::queryOne(
    "SELECT 
        COUNT(*) as total_shifts,
        SUM(hours_worked) as total_hours,
        AVG(hours_worked) as avg_hours_per_day,
        SUM(CASE WHEN status='present' THEN 1 ELSE 0 END) as days_present,
        SUM(CASE WHEN status='present' THEN hours_worked ELSE 0 END) as hours_present
     FROM work_attendance 
     WHERE student_id = ?",
    [$studentId]
);

// ============================================
// STEP 5: Record Attendance (Example)
// ============================================

if ($_GET['action'] === 'record_attendance' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $attendanceId = Database::insert('work_attendance', [
            'student_id' => $studentId,
            'work_date' => $_POST['work_date'],
            'start_time' => $_POST['start_time'],
            'end_time' => $_POST['end_time'],
            'hours_worked' => (float) $_POST['hours_worked'],
            'tasks_completed' => $_POST['tasks_completed'],
            'notes' => $_POST['notes'],
            'status' => 'present'
        ]);
        
        $uploadMessage = "Attendance recorded successfully!";
    } catch (Exception $e) {
        $uploadError = "Error recording attendance: " . $e->getMessage();
    }
}

// ============================================
// STEP 6: Export Data as JSON (for AJAX)
// ============================================

if ($_GET['export'] === 'json') {
    header('Content-Type: application/json');
    
    echo json_encode([
        'student' => $student,
        'schedule' => $schedule,
        'free_slots' => $freeSlots,
        'work_schedule' => $workSchedule ? json_decode($workSchedule['schedule_data'], true) : null,
        'stats' => $stats,
        'recommendations' => (new AutoScheduler($studentId))->getRecommendations()
    ], JSON_PRETTY_PRINT);
    exit;
}

// ============================================
// STEP 7: Display HTML (Template)
// ============================================

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - SAMS</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .header { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .card h3 { margin-bottom: 10px; color: #003087; }
        .stat { font-size: 2em; font-weight: bold; color: #155DFC; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155020; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        table th, table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        table th { background: #f8f9fa; font-weight: 600; }
        .btn { padding: 10px 20px; background: #003087; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .btn:hover { background: #002060; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 500; }
        input, textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        textarea { resize: vertical; min-height: 80px; }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>Welcome, <?php echo htmlspecialchars($student['full_name']); ?></h1>
            <p>Student ID: <?php echo htmlspecialchars($student['student_id']); ?></p>
            <p>Course: <?php echo htmlspecialchars($student['course'] ?? 'Not specified'); ?></p>
            <p><a href="?logout=1">Logout</a></p>
        </div>

        <!-- Messages -->
        <?php if ($uploadMessage): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($uploadMessage); ?></div>
        <?php endif; ?>
        <?php if ($uploadError): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($uploadError); ?></div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="grid">
            <div class="card">
                <h3>Total Hours</h3>
                <div class="stat"><?php echo number_format($stats['total_hours'] ?? 0, 1); ?></div>
                <p>hours worked</p>
            </div>
            <div class="card">
                <h3>Attendance Rate</h3>
                <div class="stat">
                    <?php 
                    $rate = ($stats['total_shifts'] ?? 0) > 0 
                        ? (($stats['days_present'] ?? 0) / $stats['total_shifts']) * 100 
                        : 0;
                    echo number_format($rate, 1) . '%';
                    ?>
                </div>
                <p><?php echo htmlspecialchars($stats['total_shifts'] ?? 0); ?> shifts</p>
            </div>
            <div class="card">
                <h3>Free Time Slots</h3>
                <div class="stat"><?php echo count($freeSlots); ?></div>
                <p>slots identified</p>
            </div>
        </div>

        <!-- COR Upload -->
        <div class="card" style="margin-top: 20px;">
            <h3>Upload COR (Certificate of Registration)</h3>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Select PDF or Image:</label>
                    <input type="file" name="cor_file" accept=".pdf,.jpg,.jpeg,.png" required>
                </div>
                <button type="submit" class="btn">Upload & Process</button>
            </form>
        </div>

        <!-- Class Schedule -->
        <div class="card" style="margin-top: 20px;">
            <h3>Your Class Schedule</h3>
            <?php if ($schedule): ?>
                <table>
                    <tr>
                        <th>Day</th>
                        <th>Subject</th>
                        <th>Time</th>
                        <th>Room</th>
                        <th>Instructor</th>
                    </tr>
                    <?php foreach ($schedule as $class): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($class['day_of_week']); ?></td>
                            <td><?php echo htmlspecialchars($class['subject_name']); ?></td>
                            <td><?php echo htmlspecialchars($class['start_time'] . ' - ' . $class['end_time']); ?></td>
                            <td><?php echo htmlspecialchars($class['room'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($class['instructor'] ?? 'N/A'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <p>No schedule found. Upload your COR to get started.</p>
            <?php endif; ?>
        </div>

        <!-- Free Time Slots -->
        <div class="card" style="margin-top: 20px;">
            <h3>Available Time Slots</h3>
            <?php if ($freeSlots): ?>
                <table>
                    <tr>
                        <th>Day</th>
                        <th>Time</th>
                        <th>Duration</th>
                        <th>Type</th>
                    </tr>
                    <?php foreach ($freeSlots as $slot): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($slot['day_of_week']); ?></td>
                            <td><?php echo htmlspecialchars($slot['start_time'] . ' - ' . $slot['end_time']); ?></td>
                            <td><?php echo htmlspecialchars($slot['duration_minutes']); ?> minutes</td>
                            <td><?php echo htmlspecialchars($slot['slot_type']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <p>No free slots identified yet.</p>
            <?php endif; ?>
        </div>

        <!-- Generated Work Schedule -->
        <?php if ($workSchedule): ?>
            <div class="card" style="margin-top: 20px;">
                <h3>Recommended Work Schedule</h3>
                <?php $scheduleData = json_decode($workSchedule['schedule_data'], true); ?>
                <table>
                    <tr>
                        <th>Day</th>
                        <th>Time</th>
                        <th>Hours</th>
                        <th>Type</th>
                    </tr>
                    <?php foreach ($scheduleData as $day => $info): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($day); ?></td>
                            <td><?php echo htmlspecialchars($info['start_time'] . ' - ' . $info['end_time']); ?></td>
                            <td><?php echo htmlspecialchars($info['hours']); ?></td>
                            <td><?php echo htmlspecialchars($info['slot_type']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <p><strong>Total Recommended Hours:</strong> <?php echo $workSchedule['total_recommended_hours']; ?>/week</p>
            </div>
        <?php endif; ?>

        <!-- Record Attendance -->
        <div class="card" style="margin-top: 20px;">
            <h3>Record Work Hours</h3>
            <form method="POST" action="?action=record_attendance">
                <div class="form-group">
                    <label>Date:</label>
                    <input type="date" name="work_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label>Start Time:</label>
                    <input type="time" name="start_time" required>
                </div>
                <div class="form-group">
                    <label>End Time:</label>
                    <input type="time" name="end_time" required>
                </div>
                <div class="form-group">
                    <label>Hours Worked:</label>
                    <input type="number" name="hours_worked" step="0.5" required>
                </div>
                <div class="form-group">
                    <label>Tasks Completed:</label>
                    <textarea name="tasks_completed" required></textarea>
                </div>
                <div class="form-group">
                    <label>Notes (Optional):</label>
                    <textarea name="notes"></textarea>
                </div>
                <button type="submit" class="btn">Record Attendance</button>
            </form>
        </div>

        <!-- API Endpoint -->
        <div class="card" style="margin-top: 20px;">
            <h3>API</h3>
            <p><a href="?export=json">Get all data as JSON</a> (for AJAX/mobile apps)</p>
        </div>
    </div>

    <script>
        // Auto-refresh statistics
        setInterval(function() {
            fetch('?export=json')
                .then(r => r.json())
                .then(data => {
                    console.log('Latest data:', data);
                    // Update UI with new data
                });
        }, 30000); // Every 30 seconds
    </script>
</body>
</html>

<?php
// ============================================
// Logout handler
// ============================================
if (isset($_GET['logout'])) {
    AuthManager::logout();
    header('Location: /login.php');
    exit;
}
