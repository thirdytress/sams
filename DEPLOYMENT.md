# SAMS Deployment Guide
## From Local Development (XAMPP) to Production (Hostinger)

---

## Table of Contents
1. [Local Development Setup](#local-development-setup)
2. [Database Migration](#database-migration)
3. [Hostinger Deployment](#hostinger-deployment)
4. [Configuration Management](#configuration-management)
5. [Troubleshooting](#troubleshooting)

---

## Local Development Setup

### Prerequisites
- XAMPP installed (PHP 7.4+, MySQL 5.7+)
- Git (optional, for version control)
- Composer (for PHP dependencies)

### Step 1: Initial Setup
```bash
# Navigate to XAMPP htdocs
cd c:\xampp\htdocs\sams

# Copy environment template
copy .env.example .env

# Edit .env with local credentials (default XAMPP settings)
DB_HOST=localhost
DB_PORT=3306
DB_NAME=sams_db
DB_USER=root
DB_PASS=

APP_ENV=development
APP_DEBUG=true
```

### Step 2: Create Database
```bash
# Open phpMyAdmin
# http://localhost/phpmyadmin

# OR use MySQL command line:
mysql -u root -p < database/schema.sql

# OR import manually in phpMyAdmin:
# 1. Go to Import tab
# 2. Select database/schema.sql file
# 3. Click Go
```

### Step 3: Set Folder Permissions
```bash
# In Command Prompt (as Administrator):
cd c:\xampp\htdocs\sams

# Create uploads directory
mkdir uploads

# Set permissions
icacls uploads /grant Everyone:F /T

# Set write permission for logs
mkdir logs
icacls logs /grant Everyone:F /T
```

### Step 4: Install Dependencies
```bash
# Install Composer dependencies
composer install

# Or if using Gemini API:
# The gemini_ai.php uses curl (built-in), no additional packages needed
```

### Step 5: Test Local Installation
```
Open browser: http://localhost/sams/
You should see the landing page
Try login: admin / Admin123
```

---

## Database Migration

### Local Development Database
1. Database is created via `database/schema.sql`
2. Contains 10 normalized tables with proper relationships
3. Includes sample admin user

### Tables Created
```
1. users - Base user information
2. student_profiles - Student-specific data
3. cor_uploads - Uploaded COR files
4. class_schedules - Parsed class schedules
5. free_time_slots - Identified free time
6. generated_work_schedules - AI recommendations
7. work_attendance - Track actual work hours
8. admin_users - Admin-specific data
9. audit_logs - Security/compliance logs
10. system_config - System settings
```

### To Reset Database
```bash
mysql -u root -p sams_db < /dev/null
mysql -u root -p < database/schema.sql
```

---

## Hostinger Deployment

### Prerequisites
- Hostinger hosting account with:
  - SSH access enabled
  - MySQL database
  - PHP 7.4+ support
  - Composer available on server

### Step 1: Create Hostinger Database

**Method A: Via hPanel**
1. Log into Hostinger hPanel
2. Go to Databases
3. Create new MySQL database
4. Note: Database Host, Database Name, Username, Password

**Method B: Via SSH/Command**
```bash
# SSH into Hostinger server
ssh your-username@your-domain.com

# Login to MySQL
mysql -u your_db_user -p

# Create database
CREATE DATABASE your_db_name;

# Exit MySQL
exit;
```

### Step 2: Upload Project to Hostinger

**Method A: Using Git (Recommended)**
```bash
# SSH into server
ssh your-username@your-domain.com

# Navigate to public_html
cd public_html

# Clone repository (or pull if already cloned)
git clone https://your-repo-url.git sams
cd sams

# Install dependencies
composer install --no-dev
```

**Method B: Using FTP/File Manager**
1. Download entire project folder
2. Use Hostinger File Manager or FTP client
3. Upload to public_html/sams/
4. Upload Composer files or run `composer install` via SSH

### Step 3: Setup .env for Production

**SSH into server and edit .env:**
```bash
nano .env
```

**Update with Hostinger database credentials:**
```bash
DB_HOST=your-hostinger-db-host
DB_PORT=3306
DB_NAME=your_database_name
DB_USER=your_db_user
DB_PASS=your_db_password

APP_ENV=production
APP_DEBUG=false

# File upload directory (must be writable)
UPLOAD_DIR=/home/your-user/public_html/sams/uploads

# Gemini API (if using AI features)
GEMINI_API_KEY=your_api_key
```

**Save and exit:**
- Press `Ctrl+X`
- Press `Y` to confirm
- Press `Enter` to save

### Step 4: Setup File Permissions
```bash
# SSH into server
ssh your-username@your-domain.com
cd public_html/sams

# Create necessary directories
mkdir -p uploads logs

# Set permissions (very important!)
chmod 755 .
chmod 755 uploads
chmod 755 logs
chmod 644 .env

# If files are owned by root, change owner
sudo chown -R your-user:your-user .
```

### Step 5: Import Database Schema
```bash
# SSH into server
ssh your-username@your-domain.com

# Navigate to project
cd public_html/sams

# Import schema
mysql -u your_db_user -p your_database_name < database/schema.sql

# Enter password when prompted
```

### Step 6: Test Production Installation
```
Open browser: https://your-domain.com/sams/
Try login: admin / Admin123

If error occurs:
1. Check logs at public_html/sams/logs/
2. Verify database connection
3. Check file permissions
```

---

## Configuration Management

### Environment Variables (.env)
The system automatically loads `.env` using `Environment::load()`

**Local vs Production differences:**
```
# LOCAL (.env.development)
APP_ENV=development
APP_DEBUG=true
DB_HOST=localhost
DB_NAME=sams_db
DB_USER=root
DB_PASS=

# PRODUCTION (.env.production)
APP_ENV=production
APP_DEBUG=false
DB_HOST=hostinger-db-host.com
DB_NAME=prod_database_name
DB_USER=prod_user
DB_PASS=strong_password
```

### Using Environment Variables
In PHP code:
```php
require_once 'config/Environment.php';

$dbHost = Environment::get('DB_HOST');
$isDebug = Environment::isDebug();
$isProd = Environment::isProduction();
```

### Important: Never Commit .env
Add to `.gitignore`:
```
.env
config/gemini.php
uploads/
logs/
```

---

## Database Connection Classes

### Using the PDO Database Class
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
    'email' => 'john@example.com'
]);

// Update
Database::update('users', 
    ['email' => 'newemail@example.com'],
    'id = ?',
    [1]
);

// Delete
Database::delete('users', 'id = ?', [1]);

// Transaction
Database::beginTransaction();
// ... do multiple operations
Database::commit();
// or
Database::rollback();
```

---

## File Upload Configuration

### Directory Structure
```
sams/
├── uploads/
│   ├── cors/          # COR files
│   └── avatars/       # User avatars (optional)
├── logs/              # Application logs
└── database/
    └── backups/       # Database backups (optional)
```

### Upload Settings
In `.env`:
```
UPLOAD_DIR=uploads
MAX_FILE_SIZE=5242880        # 5MB in bytes
ALLOWED_EXTENSIONS=pdf,jpg,jpeg,png
```

### File Upload Usage
```php
require_once 'config/FileUpload.php';

$upload = new FileUpload();

if ($_FILES['cor']) {
    $result = $upload->upload($_FILES['cor'], $studentId);
    
    if ($result['success']) {
        echo "Uploaded: " . $result['filename'];
    } else {
        echo "Error: " . implode(', ', $result['errors']);
    }
}
```

---

## Using the Scheduling System

### 1. Parse COR Schedule
```php
require_once 'src/Schedule/ScheduleParser.php';

$parser = new ScheduleParser($studentId);

// Parse using Gemini AI
$result = $parser->parseUsingGemini('/path/to/cor.pdf');

// Or add manually
$parser->addSchedule([
    'subject_code' => 'CS101',
    'subject_name' => 'Intro to CS',
    'day_of_week' => 'Monday',
    'start_time' => '08:00',
    'end_time' => '10:00',
    'room' => 'Lab A',
    'instructor' => 'Dr. Smith'
]);

// Save to database
$parser->saveToDatabase($corUploadId);

// Identify free time slots
$parser->identifyFreeTimeSlots();
```

### 2. Generate Auto Schedule
```php
require_once 'src/Schedule/AutoScheduler.php';

$scheduler = new AutoScheduler($studentId);

// Set custom constraints
$scheduler->setConstraints([
    'target_hours_per_week' => 20,
    'max_hours_per_day' => 8,
    'min_rest_minutes' => 30
]);

// Generate schedule
$result = $scheduler->generate();

if ($result['success']) {
    echo "Generated schedule ID: " . $result['schedule_id'];
}

// Get recommendations
$recommendations = $scheduler->getRecommendations();
echo $recommendations['message'];
```

### 3. Track Work Attendance
```php
// Insert work attendance record
$attendanceId = Database::insert('work_attendance', [
    'student_id' => $studentId,
    'work_date' => date('Y-m-d'),
    'start_time' => '14:00',
    'end_time' => '17:00',
    'hours_worked' => 3,
    'tasks_completed' => 'Managed mail room, filed documents',
    'status' => 'present'
]);
```

---

## Security Best Practices

### 1. Database Security
✓ Use prepared statements (Database class handles this)
✓ Store passwords as bcrypt hash
✓ Never store sensitive data in plaintext
✓ Use strong database passwords

### 2. File Upload Security
✓ Validate file type by MIME type (not just extension)
✓ Limit file size
✓ Store uploads outside web root (if possible)
✓ Use secure filename generation

### 3. Session Security
✓ Regenerate session ID on login
✓ Implement session timeout
✓ Set session cookie flags securely:
```php
session_set_cookie_params([
    'secure' => true,      // HTTPS only
    'httponly' => true,    // No JavaScript access
    'samesite' => 'Strict' // CSRF protection
]);
```

### 4. API Security
✓ Store API keys in .env (not in code)
✓ Use HTTPS for API calls
✓ Implement rate limiting for API endpoints

---

## Troubleshooting

### Database Connection Errors
```
Error: "Database connection failed"

Solutions:
1. Verify DB_HOST, DB_NAME, DB_USER, DB_PASS in .env
2. Check if MySQL service is running
3. For Hostinger: Verify database credentials in hPanel
4. Check firewall rules (for remote connections)
```

### File Upload Errors
```
Error: "File size exceeds maximum allowed"

Solutions:
1. Increase MAX_FILE_SIZE in .env
2. Check server PHP upload limits in php.ini:
   upload_max_filesize = 50M
   post_max_size = 50M
3. On Hostinger: Use hPanel to adjust limits
```

### Permission Errors
```
Error: "Permission denied" on uploads directory

Solutions:
# Linux/Hostinger:
chmod 755 uploads/

# Windows:
icacls uploads /grant Everyone:F /T

# Via Hostinger File Manager:
Right-click uploads → Change Permissions → Set to 755
```

### Session/Login Issues
```
Error: "Session timeout" or "Not authenticated"

Solutions:
1. Check SESSION_TIMEOUT in .env (default: 3600 seconds)
2. Verify session.save_path in php.ini is writable
3. Clear browser cookies
4. Check server time synchronization
```

### Gemini API Issues
```
Error: "Quota exceeded" (429)

Solutions:
1. Wait for quota reset (24 hours)
2. Add payment method to Google Cloud Console
3. Upgrade to paid plan for higher limits
4. Use demo mode (see gemini_ai.php)
```

---

## Backup & Recovery

### Database Backup
```bash
# Create backup
mysqldump -u your_user -p your_database > backup.sql

# Restore backup
mysql -u your_user -p your_database < backup.sql
```

### Files Backup
```bash
# Backup entire project (excluding .env)
tar --exclude='.env' --exclude='uploads' -czf sams-backup.tar.gz .
```

---

## Monitoring & Maintenance

### Check Logs
```bash
# Application logs
tail -f logs/app.log

# Database errors
tail -f logs/database.log

# PHP errors
tail -f /var/log/php-fpm.log  # Hostinger
tail -f c:\xampp\apache\logs\error.log  # XAMPP
```

### Database Optimization
```sql
-- Check table sizes
SELECT table_name, ROUND(((data_length + index_length) / 1024 / 1024), 2) as size_mb
FROM information_schema.tables
WHERE table_schema = 'sams_db';

-- Optimize tables
OPTIMIZE TABLE users, student_profiles, class_schedules;
```

---

## Support & Documentation

For issues or questions:
1. Check logs in `logs/` directory
2. Review error messages carefully
3. Test locally first before deploying to Hostinger
4. Use debug mode (APP_DEBUG=true) to see detailed errors
5. Check Hostinger support: https://support.hostinger.com/

---

**Last Updated:** May 2, 2026
**Version:** 1.0.0
