-- ============================================
-- TESDA-BCAT Grade Management System Database
-- Database: tesda_db
-- Version: 1.0.3 (Hierarchy Update)
-- ============================================

-- Drop database if exists and create new
DROP DATABASE IF EXISTS tesda_db;
CREATE DATABASE tesda_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tesda_db;

-- ============================================
-- Table: colleges
-- Description: Top-level organizational units (e.g., College of Arts and Trades)
-- ============================================
CREATE TABLE colleges (
    college_id INT(11) PRIMARY KEY AUTO_INCREMENT,
    college_name VARCHAR(100) NOT NULL,
    college_code VARCHAR(20) NOT NULL UNIQUE,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Table: departments
-- Description: Academic diploma programs (now nested under Colleges)
-- ============================================
CREATE TABLE departments (
    dept_id INT(11) PRIMARY KEY AUTO_INCREMENT,
    college_id INT(11) DEFAULT NULL,
    title_diploma_program VARCHAR(100) NOT NULL,
    dept_code VARCHAR(20) NOT NULL UNIQUE,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (college_id) REFERENCES colleges(college_id) ON DELETE SET NULL,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Table: programs
-- Description: Specific academic programs/courses (e.g., BSIT, Automotive)
-- ============================================
CREATE TABLE programs (
    program_id INT(11) PRIMARY KEY AUTO_INCREMENT,
    dept_id INT(11) NOT NULL,
    program_name VARCHAR(150) NOT NULL,
    program_code VARCHAR(20) NOT NULL UNIQUE,
    description TEXT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (dept_id) REFERENCES departments(dept_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Table: users
-- Description: Main users table for authentication and role management
-- ============================================
CREATE TABLE users (
    user_id INT(11) PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'registrar', 'registrar_staff', 'instructor', 'student', 'dept_head') NOT NULL,
    email VARCHAR(100) DEFAULT NULL,
    dept_id INT(11) DEFAULT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME NULL,
    FOREIGN KEY (dept_id) REFERENCES departments(dept_id) ON DELETE SET NULL,
    INDEX idx_username (username),
    INDEX idx_role (role),
    INDEX idx_status (status),
    INDEX idx_dept_id (dept_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Table: students
-- Description: Student profile information
-- ============================================
CREATE TABLE students (
    student_id INT(11) PRIMARY KEY AUTO_INCREMENT,
    user_id INT(11) NOT NULL,
    student_no VARCHAR(30) NOT NULL UNIQUE,
    program_id INT(11) DEFAULT NULL,
    dept_id INT(11) DEFAULT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50) NULL,
    date_of_birth DATE NULL,
    gender ENUM('Male', 'Female') NULL,
    elem_school VARCHAR(150) NULL,
    elem_year VARCHAR(20) NULL,
    secondary_school VARCHAR(150) NULL,
    secondary_year VARCHAR(20) NULL,
    address TEXT NULL,
    municipality VARCHAR(100) NULL,
    contact_number VARCHAR(20) NULL,
    email VARCHAR(100) NULL,
    religion VARCHAR(100) NULL,
    year_level INT(11) DEFAULT 1,
    enrollment_date DATE NULL,
    status ENUM('active', 'inactive', 'graduated', 'dropped') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE SET NULL,
    FOREIGN KEY (dept_id) REFERENCES departments(dept_id) ON DELETE SET NULL,
    INDEX idx_student_no (student_no),
    INDEX idx_year_level (year_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Table: instructors
-- Description: Instructor profile information
-- ============================================
CREATE TABLE instructors (
    instructor_id INT(11) PRIMARY KEY AUTO_INCREMENT,
    user_id INT(11) NOT NULL,
    instructor_id_no VARCHAR(30) NOT NULL UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50) NULL,
    date_of_birth DATE NULL,
    dept_id INT(11) DEFAULT NULL,
    specialization VARCHAR(100) NULL,
    contact_number VARCHAR(20) NULL,
    email VARCHAR(100) NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (dept_id) REFERENCES departments(dept_id) ON DELETE SET NULL,
    INDEX idx_instructor_id_no (instructor_id_no),
    INDEX idx_dept_id (dept_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Table: courses
-- Description: Available subjects (nested under Programs)
-- ============================================
CREATE TABLE courses (
    course_id INT(11) PRIMARY KEY AUTO_INCREMENT,
    class_code VARCHAR(10) DEFAULT NULL,
    course_code VARCHAR(20) NOT NULL UNIQUE,
    course_name VARCHAR(100) NOT NULL,
    pre_requisites VARCHAR(100) DEFAULT NULL,
    program_id INT(11) DEFAULT NULL,
    dept_id INT(11) DEFAULT NULL,
    description TEXT NULL,
    lec_hrs DECIMAL(5,2) DEFAULT 0.00,
    lab_hrs DECIMAL(5,2) DEFAULT 0.00,
    lec_units DECIMAL(5,2) DEFAULT 0.00,
    lab_units DECIMAL(5,2) DEFAULT 0.00,
    units INT(11) DEFAULT 3,
    course_type ENUM('Major', 'Minor') DEFAULT 'Minor',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE SET NULL,
    FOREIGN KEY (dept_id) REFERENCES departments(dept_id) ON DELETE SET NULL,
    INDEX idx_course_code (course_code),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Table: class_sections
-- Description: Class sections with instructor assignments
-- ============================================
CREATE TABLE class_sections (
    section_id INT(11) PRIMARY KEY AUTO_INCREMENT,
    course_id INT(11) NOT NULL,
    instructor_id INT(11) NOT NULL,
    section_name VARCHAR(50) NOT NULL,
    semester ENUM('1st', '2nd', 'Summer') NOT NULL,
    school_year VARCHAR(20) NOT NULL,
    schedule VARCHAR(100) NULL,
    room VARCHAR(50) NULL,
    max_students INT(11) DEFAULT 40,
    actual_lec_hrs DECIMAL(5,2) DEFAULT NULL,
    actual_lab_hrs DECIMAL(5,2) DEFAULT NULL,
    actual_lec_units DECIMAL(5,2) DEFAULT NULL,
    actual_lab_units DECIMAL(5,2) DEFAULT NULL,
    status ENUM('active', 'inactive', 'completed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    FOREIGN KEY (instructor_id) REFERENCES instructors(instructor_id) ON DELETE CASCADE,
    INDEX idx_semester (semester),
    INDEX idx_school_year (school_year),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Table: enrollments
-- Description: Student enrollments in class sections
-- ============================================
CREATE TABLE enrollments (
    enrollment_id INT(11) PRIMARY KEY AUTO_INCREMENT,
    student_id INT(11) NOT NULL,
    section_id INT(11) NOT NULL,
    enrollment_date DATE NOT NULL,
    status ENUM('enrolled', 'dropped', 'completed') DEFAULT 'enrolled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (section_id) REFERENCES class_sections(section_id) ON DELETE CASCADE,
    UNIQUE KEY unique_enrollment (student_id, section_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Table: grades
-- Description: Student grades for enrolled courses
-- ============================================
CREATE TABLE grades (
    grade_id INT(11) PRIMARY KEY AUTO_INCREMENT,
    enrollment_id INT(11) NOT NULL,
    student_id INT(11) NOT NULL,
    section_id INT(11) NOT NULL,
    midterm DECIMAL(5,2) NULL,
    final DECIMAL(5,2) NULL,
    grade DECIMAL(5,2) NULL COMMENT 'Final computed grade',
    remarks VARCHAR(20) NULL COMMENT 'Passed, Failed, INC, etc.',
    status ENUM('pending', 'submitted', 'approved', 'rejected') DEFAULT 'pending',
    submitted_by INT(11) NULL COMMENT 'Instructor who submitted',
    submitted_at DATETIME NULL,
    approved_by INT(11) NULL COMMENT 'Registrar who approved',
    approved_at DATETIME NULL,
    is_reviewed TINYINT(1) DEFAULT 0,
    comments TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (enrollment_id) REFERENCES enrollments(enrollment_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (section_id) REFERENCES class_sections(section_id) ON DELETE CASCADE,
    FOREIGN KEY (submitted_by) REFERENCES instructors(instructor_id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_student (student_id),
    INDEX idx_is_reviewed (is_reviewed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Table: transcripts
-- Description: Official transcript generation records
-- ============================================
CREATE TABLE transcripts (
    transcript_id INT(11) PRIMARY KEY AUTO_INCREMENT,
    student_id INT(11) NOT NULL,
    generated_by INT(11) NOT NULL COMMENT 'User ID who generated',
    date_generated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    transcript_file VARCHAR(255) NULL,
    purpose VARCHAR(100) NULL,
    status ENUM('draft', 'official') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (generated_by) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_student (student_id),
    INDEX idx_date (date_generated)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Table: system_settings
-- Description: System configuration and settings
-- ============================================
CREATE TABLE system_settings (
    setting_id INT(11) PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(50) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    setting_type VARCHAR(20) DEFAULT 'text',
    description TEXT NULL,
    updated_by INT(11) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Table: audit_logs
-- Description: System activity logging
-- ============================================
CREATE TABLE audit_logs (
    log_id INT(11) PRIMARY KEY AUTO_INCREMENT,
    user_id INT(11) NULL,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50) NULL,
    record_id INT(11) NULL,
    old_values TEXT NULL,
    new_values TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Insert Default Data
-- ============================================

-- Default Admin User (password: admin123)
INSERT INTO users (username, password, role, status) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active');

-- Default College
INSERT INTO colleges (college_name, college_code) VALUES 
('College of Arts and Trades', 'BCAT');

-- Insert Default System Settings
INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
('school_name', 'BALICUATRO COLLEGE OF ARTS AND TRADES', 'text', 'Official school name'),
('school_address', 'Allen, Northern Samar', 'text', 'School address'),
('school_region', 'Region VIII', 'text', 'TESDA Region'),
('academic_year', '2024-2025', 'text', 'Current academic year'),
('current_semester', '1st', 'select', 'Current semester'),
('grading_system', 'Numeric', 'select', 'Grading system type'),
('passing_grade', '75', 'number', 'Minimum passing grade'),
('app_name', 'TESDA-BCAT GMS', 'text', 'Application name'),
('items_per_page', '20', 'number', 'Default pagination items'),
('midterm_weight', '0.5', 'number', 'Weight for midterm grade (0.0 to 1.0)'),
('final_weight', '0.5', 'number', 'Weight for final grade (0.0 to 1.0)'),
('student_id_prefix', 'STU-', 'text', 'Prefix for auto-generated student numbers (e.g. STU-)'),
('student_id_counter', '1', 'number', 'Current counter for auto-generated student numbers'),
('instructor_id_prefix', 'INS-', 'text', 'Prefix for auto-generated instructor IDs'),
('instructor_id_counter', '1', 'number', 'Current counter for auto-generated instructor IDs'),
('logo_size', '120', 'number', 'Logo size in pixels for official documents (TOR/COR)'),
('student_doc_title', 'CERTIFICATION OF RECORD (COR)', 'text', 'Main title for the grade report in the student portal'),
('registrar_doc_title', 'OFFICIAL TRANSCRIPT OF RECORDS', 'text', 'Main title for the official TOR in the registrar portal');

-- ============================================
-- Views for Reporting
-- ============================================

-- View: Student Grade Summary
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

-- View: Instructor Class List
CREATE VIEW vw_instructor_classes AS
SELECT 
    cs.section_id,
    cs.section_name,
    c.course_code,
    c.course_name,
    cs.semester,
    cs.school_year,
    cs.schedule,
    cs.room,
    i.instructor_id,
    CONCAT(i.last_name, ', ', i.first_name) AS instructor_name,
    COUNT(DISTINCT e.student_id) AS total_students,
    cs.status
FROM class_sections cs
INNER JOIN courses c ON cs.course_id = c.course_id
INNER JOIN instructors i ON cs.instructor_id = i.instructor_id
LEFT JOIN enrollments e ON cs.section_id = e.section_id AND e.status = 'enrolled'
GROUP BY cs.section_id, cs.section_name, c.course_code, c.course_name, 
         cs.semester, cs.school_year, cs.schedule, cs.room, 
         i.instructor_id, instructor_name, cs.status;

-- ============================================
-- Stored Procedures
-- ============================================

DELIMITER //

-- Procedure: Calculate Final Grade
CREATE PROCEDURE sp_calculate_final_grade(
    IN p_grade_id INT
)
BEGIN
    DECLARE v_midterm DECIMAL(5,2);
    DECLARE v_final DECIMAL(5,2);
    DECLARE v_computed_grade DECIMAL(5,2);
    DECLARE v_remarks VARCHAR(20);
    DECLARE v_passing_grade DECIMAL(5,2);
    DECLARE v_m_weight DECIMAL(5,2);
    DECLARE v_f_weight DECIMAL(5,2);
    
    -- Get passing grade from settings
    SELECT CAST(setting_value AS DECIMAL(5,2)) INTO v_passing_grade
    FROM system_settings WHERE setting_key = 'passing_grade';
    
    -- Get weights from settings
    SELECT CAST(setting_value AS DECIMAL(5,2)) INTO v_m_weight
    FROM system_settings WHERE setting_key = 'midterm_weight';
    SELECT CAST(setting_value AS DECIMAL(5,2)) INTO v_f_weight
    FROM system_settings WHERE setting_key = 'final_weight';
    
    -- Get midterm and final grades
    SELECT midterm, final INTO v_midterm, v_final
    FROM grades WHERE grade_id = p_grade_id;
    
    -- Calculate final grade using weights
    IF v_midterm IS NOT NULL AND v_final IS NOT NULL THEN
        SET v_computed_grade = (v_midterm * v_m_weight) + (v_final * v_f_weight);
        
        -- Determine remarks
        IF v_computed_grade >= v_passing_grade THEN
            SET v_remarks = 'Passed';
        ELSE
            SET v_remarks = 'Failed';
        END IF;
        
        -- Update grade record
        UPDATE grades 
        SET grade = v_computed_grade, remarks = v_remarks
        WHERE grade_id = p_grade_id;
    END IF;
END//

-- Procedure: Get Student Transcript Data
CREATE PROCEDURE sp_get_student_transcript(
    IN p_student_id INT
)
BEGIN
    SELECT 
        s.student_no,
        CONCAT(s.last_name, ', ', s.first_name, ' ', COALESCE(s.middle_name, '')) AS full_name,
        s.address,
        s.municipality,
        p.program_name AS program,
        s.date_of_birth,
        c.course_code,
        c.course_name,
        c.units,
        cs.semester,
        cs.school_year,
        g.midterm,
        g.final,
        g.grade,
        g.remarks
    FROM students s
    INNER JOIN programs p ON s.program_id = p.program_id
    INNER JOIN enrollments e ON s.student_id = e.student_id
    INNER JOIN grades g ON e.enrollment_id = g.enrollment_id
    INNER JOIN class_sections cs ON e.section_id = cs.section_id
    INNER JOIN courses c ON cs.course_id = c.course_id
    WHERE s.student_id = p_student_id 
    AND g.status = 'approved'
    ORDER BY cs.school_year, cs.semester, c.course_code;
END//

DELIMITER ;

-- ============================================
-- Triggers
-- ============================================

DELIMITER //

-- Trigger: Log user creation
CREATE TRIGGER tr_after_user_insert
AFTER INSERT ON users
FOR EACH ROW
BEGIN
    INSERT INTO audit_logs (user_id, action, table_name, record_id, new_values)
    VALUES (NEW.user_id, 'CREATE_USER', 'users', NEW.user_id, 
            CONCAT('Username: ', NEW.username, ', Role: ', NEW.role));
END//

-- Trigger: Log grade submission
CREATE TRIGGER tr_after_grade_update
AFTER UPDATE ON grades
FOR EACH ROW
BEGIN
    DECLARE v_user_id INT;
    IF OLD.status != NEW.status THEN
        -- Resolve correct user_id for audit log
        IF NEW.status = 'approved' THEN
            SET v_user_id = NEW.approved_by;
        ELSEIF NEW.status = 'submitted' THEN
            SELECT user_id INTO v_user_id FROM instructors WHERE instructor_id = NEW.submitted_by;
        ELSE
            SET v_user_id = NEW.submitted_by; -- Fallback
        END IF;

        INSERT INTO audit_logs (user_id, action, table_name, record_id, old_values, new_values)
        VALUES (v_user_id, 'UPDATE_GRADE_STATUS', 'grades', NEW.grade_id,
                CONCAT('Status: ', OLD.status), CONCAT('Status: ', NEW.status));
    END IF;
END//

DELIMITER ;
