# SAMS - Student Assistant Management System
## Production-Ready Database Architecture & Backend

### System Overview

SAMS is a centralized, scalable system for managing student work schedules by analyzing their course schedules (COR) and automatically generating optimized working hours.

**Key Features:**
- Student registration and authentication
- COR (Certificate of Registration) file upload and parsing
- Automatic schedule analysis using Google Gemini AI
- Free time slot identification
- AI-powered work schedule generation
- Work attendance tracking
- Admin dashboard for schedule management

---

## Project Structure

```
sams/
├── .env                              # Local environment config (DO NOT COMMIT)
├── .env.example                      # Template for .env
├── .gitignore                        # Git ignore rules
│
├── config/
│   ├── Environment.php              # Load .env variables
│   ├── Database.php                 # PDO database connection (replaces old db.php)
│   ├── FileUpload.php               # File upload validation & handling
│   └── gemini.php                   # Gemini API config (local fallback)
│
├── src/
│   ├── Auth/
│   │   └── AuthManager.php          # User authentication & authorization
│   ├── Schedule/
│   │   ├── ScheduleParser.php       # COR parsing & schedule extraction
│   │   └── AutoScheduler.php        # AI-powered schedule generation
│   └── Models/
│       ├── User.php                 # User model (TODO)
│       ├── StudentProfile.php       # Student profile model (TODO)
│       └── Schedule.php             # Schedule model (TODO)
│
├── database/
│   ├── schema.sql                   # Complete database schema (3NF normalized)
│   └── migrations/                  # Future: database migrations
│
├── public/
│   ├── index.php                    # Landing page
│   ├── login.php                    # Login page
│   ├── register.php                 # Registration page
│   └── student/
│       └── profile.php              # Student dashboard
│
├── admin/
│   └── scheduling.php               # Admin schedule management
│
├── uploads/
│   └── cors/                        # Uploaded COR files
│
├── logs/                            # Application logs
│
├── DEPLOYMENT.md                    # Deployment guide (XAMPP → Hostinger)
├── README.md                        # This file
└── composer.json                    # PHP dependencies (Composer)
```

---

## Database Schema (3NF Normalized)

### Tables Overview

1. **users** - Base user information (students, admins, staff)
2. **student_profiles** - Student-specific data (course, year level, status)
3. **cor_uploads** - Uploaded COR file records
4. **class_schedules** - Parsed class schedule (subject, time, room)
5. **free_time_slots** - Identified available time slots
6. **generated_work_schedules** - AI-recommended schedules
7. **work_attendance** - Actual work hour tracking
8. **admin_users** - Admin-specific permissions
9. **audit_logs** - Security and compliance logs
10. **system_config** - System settings and constraints

### Key Relationships
```
users (1) ──→ (many) student_profiles
users (1) ──→ (many) cor_uploads
users (1) ──→ (many) class_schedules
users (1) ──→ (many) free_time_slots
users (1) ──→ (many) generated_work_schedules
users (1) ──→ (many) work_attendance
```

All tables use `InnoDB`, `utf8mb4` charset for international support, and include proper indexing for performance.

---

## Configuration Management

### Environment Variables (.env)
Located in root directory. Works on both XAMPP and Hostinger.

**Example .env:**
```bash
# Database (auto-adjusts for local vs production)
DB_HOST=localhost              # or Hostinger host
DB_PORT=3306
DB_NAME=sams_db               # or production DB name
DB_USER=root                  # or Hostinger user
DB_PASS=                      # or Hostinger password

# Environment
APP_ENV=development           # or 'production' on Hostinger
APP_DEBUG=true               # false in production

# File Upload
UPLOAD_DIR=uploads
MAX_FILE_SIZE=5242880        # 5MB
ALLOWED_EXTENSIONS=pdf,jpg,jpeg,png

# Security
SESSION_TIMEOUT=3600
BCRYPT_COST=10

# Gemini API (optional)
GEMINI_API_KEY=your_key_here
GEMINI_MODEL=gemini-2.0-flash
```

**Loading in PHP:**
```php
require_once 'config/Environment.php';

$dbHost = Environment::get('DB_HOST');
$isDebug = Environment::isDebug();
$isProd = Environment::isProduction();
```

---

## Database Connection

### Old Way (db.php with MySQLi)
```php
$mysqli = new mysqli('localhost', 'root', '', 'sams_db');
```

### New Way (Database class with PDO)
```php
require_once 'config/Database.php';

// Query multiple rows
$users = Database::query(
    "SELECT * FROM users WHERE user_type = ?",
    ['student']
);

// Query single row
$user = Database::queryOne(
    "SELECT * FROM users WHERE id = ?",
    [1]
);

// Insert
$id = Database::insert('users', [
    'username' => 'john_doe',
    'email' => 'john@example.com',
    'password_hash' => password_hash('password123', PASSWORD_BCRYPT)
]);

// Update
Database::update('users', 
    ['email' => 'newemail@example.com'],
    'id = ?',
    [1]
);

// Delete
Database::delete('users', 'id = ?', [1]);

// Transactions
Database::beginTransaction();
// ... multiple operations
Database::commit();  // or Database::rollback();
```

**Benefits:**
✓ Prepared statements (prevents SQL injection)
✓ Works on MySQL, PostgreSQL, SQLite
✓ Consistent error handling
✓ Easier to test and mock

---

## Authentication System

### Register New User
```php
require_once 'src/Auth/AuthManager.php';

$result = AuthManager::register([
    'username' => 'john_doe',
    'email' => 'john@example.com',
    'password' => 'SecurePass123',
    'full_name' => 'John Doe',
    'contact_number' => '09123456789',
    'user_type' => 'student',
    'student_id' => 'NU-2024-001',
    'course' => 'BS Computer Science',
    'year_level' => '2nd Year'
]);

if ($result['success']) {
    echo "User created: " . $result['user_id'];
} else {
    echo "Error: " . implode(', ', $result['errors']);
}
```

### Login User
```php
$result = AuthManager::login('john_doe', 'SecurePass123');

if ($result['success']) {
    // User authenticated, session set
    redirect('/student/profile.php');
} else {
    echo "Login failed: " . $result['error'];
}
```

### Check Authentication
```php
if (AuthManager::isAuthenticated()) {
    if (AuthManager::isStudent()) {
        $userId = AuthManager::getUserId();
    } elseif (AuthManager::isAdmin()) {
        // Admin only
    }
} else {
    redirect('/login.php');
}
```

### Security Features
✓ Passwords hashed with bcrypt (cost: 10)
✓ Session regeneration on login
✓ Password validation (8+ chars, uppercase, number)
✓ Session timeout (default: 1 hour)
✓ Timing attack prevention (1 second delay on failed login)

---

## File Upload Handler

### Upload COR File
```php
require_once 'config/FileUpload.php';

$upload = new FileUpload();

if ($_FILES['cor_file']) {
    $result = $upload->upload($_FILES['cor_file'], $studentId);
    
    if ($result['success']) {
        $filename = $result['filename'];
        $filepath = $result['filepath'];
        $mimeType = $result['mime_type'];
        
        // Save to database
        $corId = Database::insert('cor_uploads', [
            'student_id' => $studentId,
            'file_name' => $filename,
            'file_path' => $filepath,
            'file_type' => $mimeType,
            'file_size' => $result['size']
        ]);
    } else {
        echo "Upload error: " . implode(', ', $result['errors']);
    }
}
```

### Security Features
✓ File type validation (MIME type + extension)
✓ File size limits (5MB default, configurable)
✓ PDF integrity check
✓ Image validation
✓ Secure filename generation

---

## Schedule Parsing & Processing

### 1. Parse COR using Gemini AI
```php
require_once 'src/Schedule/ScheduleParser.php';

$parser = new ScheduleParser($studentId);

// Parse using Gemini (reads PDF, extracts schedule)
$result = $parser->parseUsingGemini($filepath);

if ($result['success']) {
    $parser->saveToDatabase($corUploadId);
    
    // Identify free time slots
    $freeTimeResult = $parser->identifyFreeTimeSlots([
        'start' => '08:00',
        'end' => '17:00'
    ]);
    
    echo "Parsed " . count($freeTimeResult['free_slots']) . " free slots";
}
```

### 2. Add Schedule Manually (for testing)
```php
$parser->addSchedule([
    'subject_code' => 'CS101',
    'subject_name' => 'Introduction to Computer Science',
    'day_of_week' => 'Monday',
    'start_time' => '08:00',
    'end_time' => '09:30',
    'room' => 'Lab Building 101',
    'instructor' => 'Dr. Maria Santos',
    'units' => 3
]);

$parser->saveToDatabase();
```

### 3. Get Student Schedule
```php
$result = $parser->getStudentSchedule();

foreach ($result['schedules'] as $class) {
    echo $class['subject_name'] . " on " . $class['day_of_week'];
    echo " from " . $class['start_time'] . " to " . $class['end_time'];
}
```

---

## Auto-Scheduling Algorithm

### Generate Optimized Work Schedule
```php
require_once 'src/Schedule/AutoScheduler.php';

$scheduler = new AutoScheduler($studentId);

// Optional: Set custom constraints
$scheduler->setConstraints([
    'target_hours_per_week' => 20,   // Default from config
    'max_hours_per_day' => 8,         // Default from config
    'min_rest_minutes' => 30          // Default from config
]);

// Generate schedule
$result = $scheduler->generate();

if ($result['success']) {
    echo "Schedule generated: " . $result['schedule_id'];
    
    // See recommended hours per day
    foreach ($result['schedule']['hours_per_day'] as $day => $hours) {
        echo "$day: $hours hours\n";
    }
    
    // Total recommended hours
    echo "Total: " . $result['schedule']['total_hours'] . " hours/week";
}
```

### Algorithm Logic
1. Retrieves all free time slots for student
2. Groups slots by day of week
3. Allocates hours to each day:
   - Respects max_hours_per_day limit
   - Fills highest priority slots first (morning → afternoon → evening)
   - Targets total_hours_per_week goal
4. Applies min_rest_time between shifts
5. Stores in `generated_work_schedules` table

### Get Scheduling Recommendations
```php
$recommendations = $scheduler->getRecommendations();

echo $recommendations['total_free_hours_per_week'];  // e.g., 22.5
echo $recommendations['recommended_hours'];          // e.g., 20
echo $recommendations['feasible'];                    // true/false
echo $recommendations['message'];
```

---

## Work Attendance Tracking

### Record Work Attendance
```php
$attendanceId = Database::insert('work_attendance', [
    'student_id' => $studentId,
    'work_date' => date('Y-m-d'),
    'start_time' => '14:00',
    'end_time' => '17:00',
    'hours_worked' => 3,
    'tasks_completed' => 'Organized filing, answered phone calls',
    'notes' => 'Helped with event setup',
    'status' => 'present'  // 'present', 'absent', 'incomplete'
]);
```

### Get Attendance Statistics
```php
$stats = Database::query(
    "SELECT 
        COUNT(*) as total_days,
        SUM(hours_worked) as total_hours,
        AVG(hours_worked) as avg_hours_per_day,
        SUM(CASE WHEN status='present' THEN 1 ELSE 0 END) as days_present
     FROM work_attendance 
     WHERE student_id = ? AND work_date >= ?",
    [$studentId, date('Y-m-d', strtotime('-30 days'))]
);
```

---

## Integration Example (Complete Flow)

```php
<?php
session_start();
require_once 'config/Database.php';
require_once 'config/Environment.php';
require_once 'config/FileUpload.php';
require_once 'src/Auth/AuthManager.php';
require_once 'src/Schedule/ScheduleParser.php';
require_once 'src/Schedule/AutoScheduler.php';

// 1. Verify student is logged in
if (!AuthManager::isStudent()) {
    die('Not authenticated');
}
$studentId = AuthManager::getUserId();

// 2. Handle COR file upload
if ($_FILES['cor_file']) {
    $upload = new FileUpload();
    $uploadResult = $upload->upload($_FILES['cor_file'], $studentId);
    
    if ($uploadResult['success']) {
        $corId = Database::insert('cor_uploads', [
            'student_id' => $studentId,
            'file_name' => $uploadResult['filename'],
            'file_path' => $uploadResult['filepath'],
            'file_type' => $uploadResult['mime_type'],
            'file_size' => $uploadResult['size']
        ]);
        
        // 3. Parse COR schedule
        $parser = new ScheduleParser($studentId);
        $parseResult = $parser->parseUsingGemini($uploadResult['filepath']);
        
        if ($parseResult['success']) {
            $parser->saveToDatabase($corId);
            
            // 4. Identify free time slots
            $freeTimeResult = $parser->identifyFreeTimeSlots();
            
            if ($freeTimeResult['success']) {
                // 5. Generate auto schedule
                $scheduler = new AutoScheduler($studentId);
                $scheduleResult = $scheduler->generate();
                
                if ($scheduleResult['success']) {
                    echo "Schedule generated! ID: " . $scheduleResult['schedule_id'];
                }
            }
        }
    }
}

// 6. Display student's generated schedule
$scheduler = new AutoScheduler($studentId);
$schedule = $scheduler->getSchedule();

if ($schedule['success']) {
    echo json_encode($schedule['schedule'], JSON_PRETTY_PRINT);
}
?>
```

---

## Deployment Instructions

### Quick Start (Local - XAMPP)
```bash
# 1. Copy .env.example → .env
# 2. Run database schema:
mysql -u root -p < database/schema.sql

# 3. Start XAMPP
# 4. Open http://localhost/sams/
# 5. Login: admin / Admin123
```

### Production (Hostinger)
See [DEPLOYMENT.md](DEPLOYMENT.md) for complete step-by-step guide:
- Database migration
- FTP/Git upload
- .env configuration for production
- File permissions
- Testing & troubleshooting

---

## Security Checklist

- [x] Prepared statements (Database class)
- [x] Password hashing (bcrypt, cost 10)
- [x] File upload validation (MIME type, size)
- [x] Session security (regeneration, timeout)
- [x] Environment variables for secrets (.env)
- [x] Input validation (email, password strength)
- [x] HTTPS ready (for production)
- [x] SQL injection prevention (prepared statements)
- [x] CSRF tokens (to be added to forms)
- [x] Audit logging (audit_logs table)

---

## Performance Considerations

### Database Indexes
```sql
-- All tables have proper indexes on:
-- Foreign keys
-- Frequently queried columns
-- Date ranges (for range queries)
-- Status columns
```

### Query Optimization
- Use `Database::queryOne()` for single record (LIMIT 1)
- Join student_profiles on users for student queries
- Use indexes on date ranges for attendance reports

### Caching (Future)
Consider implementing:
- Redis for session storage
- APCu for frequently accessed config
- Query result caching for reports

---

## Monitoring & Maintenance

### Logs
- Check `logs/` directory for errors
- PHP errors in XAMPP: `c:\xampp\apache\logs\error.log`
- Database errors logged via exceptions

### Database Health
```sql
-- Check table sizes
SELECT table_name, ROUND(((data_length + index_length) / 1024 / 1024), 2) as MB
FROM information_schema.tables
WHERE table_schema = 'sams_db';

-- Optimize
OPTIMIZE TABLE users, student_profiles, class_schedules;
```

---

## Future Enhancements

- [ ] Student models/repositories for better OOP
- [ ] API endpoints for mobile app
- [ ] Email notifications
- [ ] Advanced reporting
- [ ] Schedule conflict detection
- [ ] Performance analytics
- [ ] Redis caching layer
- [ ] GraphQL API

---

## Support & Documentation

- Database Schema: See [database/schema.sql](database/schema.sql)
- Deployment Guide: See [DEPLOYMENT.md](DEPLOYMENT.md)
- Configuration: See [.env.example](.env.example)

---

**Version:** 1.0.0  
**Last Updated:** May 2, 2026  
**Environment:** XAMPP (local) and Hostinger (production)  
**PHP Version:** 7.4+  
**Database:** MySQL 5.7+
#   s a m s  
 