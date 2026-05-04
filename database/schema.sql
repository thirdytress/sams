-- ============================================
-- SAMS Database Schema
-- Student Assistant Management System
-- Production-ready MySQL (3NF normalized)
-- ============================================

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS sams_db 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE sams_db;

-- ============================================
-- 1. USERS TABLE (Base user information)
-- ============================================
CREATE TABLE users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_type ENUM('student', 'admin', 'staff') NOT NULL DEFAULT 'student',
  username VARCHAR(100) UNIQUE NOT NULL,
  email VARCHAR(255) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(255) NOT NULL,
  contact_number VARCHAR(20),
  is_active BOOLEAN DEFAULT true,
  is_verified BOOLEAN DEFAULT false,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  INDEX idx_username (username),
  INDEX idx_email (email),
  INDEX idx_user_type (user_type),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 2. STUDENT_PROFILES TABLE (Student-specific data)
-- ============================================
CREATE TABLE student_profiles (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT UNIQUE NOT NULL,
  student_id VARCHAR(50) UNIQUE NOT NULL,
  course VARCHAR(255),
  year_level VARCHAR(50),
  section VARCHAR(100),
  application_status ENUM('pending', 'approved', 'rejected', 'inactive') DEFAULT 'pending',
  preferred_hours_per_week INT DEFAULT 20,
  bio TEXT,
  avatar_path VARCHAR(500),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_student_id (student_id),
  INDEX idx_course (course),
  INDEX idx_application_status (application_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 3. COR_UPLOADS TABLE (Certificate of Registration files)
-- ============================================
CREATE TABLE cor_uploads (
  id INT PRIMARY KEY AUTO_INCREMENT,
  student_id INT NOT NULL,
  file_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  file_type VARCHAR(50),
  file_size INT,
  upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  is_processed BOOLEAN DEFAULT false,
  processed_date TIMESTAMP NULL,
  
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_student_id (student_id),
  INDEX idx_upload_date (upload_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4. CLASS_SCHEDULES TABLE (Parsed class schedule from COR)
-- ============================================
CREATE TABLE class_schedules (
  id INT PRIMARY KEY AUTO_INCREMENT,
  student_id INT NOT NULL,
  cor_upload_id INT,
  subject_code VARCHAR(50),
  subject_name VARCHAR(255),
  day_of_week VARCHAR(20),
  start_time TIME,
  end_time TIME,
  room VARCHAR(100),
  instructor VARCHAR(255),
  units INT,
  parsed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (cor_upload_id) REFERENCES cor_uploads(id) ON DELETE SET NULL,
  INDEX idx_student_id (student_id),
  INDEX idx_day_of_week (day_of_week),
  INDEX idx_start_time (start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 5. FREE_TIME_SLOTS TABLE (Identified free time)
-- ============================================
CREATE TABLE free_time_slots (
  id INT PRIMARY KEY AUTO_INCREMENT,
  student_id INT NOT NULL,
  day_of_week VARCHAR(20),
  start_time TIME,
  end_time TIME,
  duration_minutes INT,
  slot_type ENUM('morning', 'afternoon', 'evening') DEFAULT 'afternoon',
  identified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_student_id (student_id),
  INDEX idx_day_of_week (day_of_week)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 6. GENERATED_WORK_SCHEDULES TABLE (AI-recommended schedules)
-- ============================================
CREATE TABLE generated_work_schedules (
  id INT PRIMARY KEY AUTO_INCREMENT,
  student_id INT NOT NULL,
  schedule_data JSON NOT NULL,
  total_recommended_hours INT,
  hours_per_day JSON,
  constraints_applied JSON,
  generated_by VARCHAR(50) DEFAULT 'gemini',
  generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  is_active BOOLEAN DEFAULT true,
  applied_at TIMESTAMP NULL,
  
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_student_id (student_id),
  INDEX idx_generated_at (generated_at),
  INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 7. WORK_ATTENDANCE TABLE (Track actual work hours)
-- ============================================
CREATE TABLE work_attendance (
  id INT PRIMARY KEY AUTO_INCREMENT,
  student_id INT NOT NULL,
  work_date DATE,
  start_time TIME,
  end_time TIME,
  hours_worked DECIMAL(5, 2),
  tasks_completed TEXT,
  notes TEXT,
  status ENUM('present', 'absent', 'incomplete') DEFAULT 'present',
  recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_student_id (student_id),
  INDEX idx_work_date (work_date),
  INDEX idx_recorded_at (recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 8. ADMIN_USERS TABLE (Admin-specific data)
-- ============================================
CREATE TABLE admin_users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT UNIQUE NOT NULL,
  admin_role VARCHAR(100),
  department VARCHAR(255),
  permissions JSON,
  last_login TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_admin_role (admin_role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 9. AUDIT_LOGS TABLE (Security and compliance)
-- ============================================
CREATE TABLE audit_logs (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT,
  action VARCHAR(100),
  table_name VARCHAR(100),
  record_id INT,
  old_values JSON,
  new_values JSON,
  ip_address VARCHAR(45),
  user_agent VARCHAR(500),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_user_id (user_id),
  INDEX idx_created_at (created_at),
  INDEX idx_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 10. SYSTEM_CONFIG TABLE (System settings)
-- ============================================
CREATE TABLE system_config (
  id INT PRIMARY KEY AUTO_INCREMENT,
  config_key VARCHAR(100) UNIQUE NOT NULL,
  config_value TEXT,
  data_type ENUM('string', 'integer', 'boolean', 'json'),
  description TEXT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INITIAL DATA
-- ============================================

-- Insert default admin
INSERT INTO users (user_type, username, email, password_hash, full_name, is_active, is_verified)
VALUES ('admin', 'admin', 'admin@sams.local', '$2y$10$abc123', 'System Administrator', true, true);

-- Insert admin profile
INSERT INTO admin_users (user_id, admin_role, department)
VALUES (LAST_INSERT_ID(), 'SDAO Head', 'NU Lipa - Student Development and Activities Office');

-- Insert default system config
INSERT INTO system_config (config_key, config_value, data_type, description)
VALUES 
  ('min_rest_time_minutes', '30', 'integer', 'Minimum rest time between work shifts'),
  ('max_daily_hours', '8', 'integer', 'Maximum work hours per day'),
  ('week_target_hours', '20', 'integer', 'Target work hours per week');

-- ============================================
-- CREATE VIEWS FOR COMMON QUERIES
-- ============================================

-- Student Dashboard View
CREATE VIEW student_dashboard_view AS
SELECT 
  u.id,
  u.username,
  u.email,
  u.full_name,
  u.contact_number,
  sp.student_id,
  sp.course,
  sp.year_level,
  sp.application_status,
  COUNT(DISTINCT wa.id) as total_shifts,
  COALESCE(SUM(wa.hours_worked), 0) as total_hours_worked,
  COALESCE(AVG(CASE WHEN wa.status = 'present' THEN 1 ELSE 0 END) * 100, 0) as attendance_rate
FROM users u
LEFT JOIN student_profiles sp ON u.id = sp.user_id
LEFT JOIN work_attendance wa ON u.id = wa.student_id
WHERE u.user_type = 'student'
GROUP BY u.id;

-- ============================================
-- INDEXES FOR PERFORMANCE
-- ============================================

-- Performance indexes
CREATE INDEX idx_schedule_student_day ON class_schedules(student_id, day_of_week);
CREATE INDEX idx_free_slots_student_day ON free_time_slots(student_id, day_of_week, start_time);
CREATE INDEX idx_attendance_student_date ON work_attendance(student_id, work_date);
CREATE INDEX idx_audit_timestamp ON audit_logs(created_at DESC);

-- ============================================
-- END OF SCHEMA
-- ============================================
