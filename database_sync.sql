-- TESDA-BCAT Database Synchronization Script
-- This script ensures all tables have the necessary columns for the latest hierarchy updates.

USE tesda_db;

-- 1. Terminology & Structure Update
ALTER TABLE departments CHANGE COLUMN IF EXISTS dept_name title_diploma_program VARCHAR(100);

-- 2. Create New Hierarchy Tables
CREATE TABLE IF NOT EXISTS colleges (
    college_id INT(11) PRIMARY KEY AUTO_INCREMENT,
    college_name VARCHAR(100) NOT NULL,
    college_code VARCHAR(20) NOT NULL UNIQUE,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS programs (
    program_id INT(11) PRIMARY KEY AUTO_INCREMENT,
    dept_id INT(11) NOT NULL,
    program_name VARCHAR(150) NOT NULL,
    program_code VARCHAR(20) NOT NULL UNIQUE,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (dept_id) REFERENCES departments(dept_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Modify Existing Tables
ALTER TABLE departments ADD COLUMN IF NOT EXISTS college_id INT(11) DEFAULT NULL AFTER dept_id;
ALTER TABLE departments ADD CONSTRAINT fk_dept_college FOREIGN KEY IF NOT EXISTS (college_id) REFERENCES colleges(college_id) ON DELETE SET NULL;

ALTER TABLE students ADD COLUMN IF NOT EXISTS program_id INT(11) DEFAULT NULL AFTER student_no;
ALTER TABLE students ADD COLUMN IF NOT EXISTS dept_id INT(11) DEFAULT NULL AFTER program_id;
-- Remove legacy course column if desired (optional, keeping for safety or migrating later)
-- ALTER TABLE students DROP COLUMN IF EXISTS course;

ALTER TABLE courses ADD COLUMN IF NOT EXISTS program_id INT(11) DEFAULT NULL AFTER course_name;
ALTER TABLE courses ADD COLUMN IF NOT EXISTS dept_id INT(11) DEFAULT NULL AFTER program_id;
ALTER TABLE courses ADD COLUMN IF NOT EXISTS course_type ENUM('Major', 'Minor') DEFAULT 'Minor' AFTER units;

-- 4. Sync Baseline Data
ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'registrar', 'registrar_staff', 'instructor', 'student', 'dept_head') NOT NULL;

-- 4. Sync Baseline Data
INSERT IGNORE INTO colleges (college_name, college_code) VALUES ('College of Arts and Trades', 'BCAT');
SET @default_college = (SELECT college_id FROM colleges WHERE college_code = 'BCAT' LIMIT 1);
UPDATE departments SET college_id = @default_college WHERE college_id IS NULL;

-- 5. System Settings for Documents
INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_type, description) VALUES 
('logo_size', '120', 'number', 'Logo size in pixels for official documents (TOR/COR)'),
('student_doc_title', 'CERTIFICATION OF RECORD (COR)', 'text', 'Main title for COR'),
('registrar_doc_title', 'OFFICIAL TRANSCRIPT OF RECORDS', 'text', 'Main title for TOR');

-- 6. Refresh Views
DROP VIEW IF EXISTS vw_student_grades;
CREATE VIEW vw_student_grades AS
SELECT 
    s.student_id,
    s.student_no,
    CONCAT(s.last_name, ', ', s.first_name, ' ', COALESCE(s.middle_name, '')) AS student_name,
    p.program_name as program,
    s.year_level,
    c.course_code,
    c.course_name,
    c.units,
    cs.semester,
    cs.school_year,
    g.midterm,
    g.final,
    g.grade,
    g.remarks,
    g.status AS grade_status,
    CONCAT(i.last_name, ', ', i.first_name) AS instructor_name
FROM students s
INNER JOIN programs p ON s.program_id = p.program_id
INNER JOIN enrollments e ON s.student_id = e.student_id
INNER JOIN grades g ON e.enrollment_id = g.enrollment_id
INNER JOIN class_sections cs ON e.section_id = cs.section_id
INNER JOIN courses c ON cs.course_id = c.course_id
INNER JOIN instructors i ON cs.instructor_id = i.instructor_id
WHERE g.status = 'approved';

-- 7. Verification: Periodic Refresh of Auto-Counters
INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('student_id_counter', '1'), ('instructor_id_counter', '1');
