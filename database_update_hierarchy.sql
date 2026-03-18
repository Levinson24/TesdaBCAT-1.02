-- ============================================
-- Database Update: Hierarchy Restructuring
-- College -> Diploma Program -> Program (Course) -> Subject
-- ============================================

USE tesda_db;

-- 1. Create colleges table
CREATE TABLE IF NOT EXISTS colleges (
    college_id INT(11) PRIMARY KEY AUTO_INCREMENT,
    college_name VARCHAR(150) NOT NULL,
    college_code VARCHAR(20) NOT NULL UNIQUE,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Modify departments table (Diploma Programs)
ALTER TABLE departments ADD COLUMN IF NOT EXISTS college_id INT(11) DEFAULT NULL AFTER dept_id;
ALTER TABLE departments ADD CONSTRAINT fk_dept_college FOREIGN KEY (college_id) REFERENCES colleges(college_id) ON DELETE SET NULL;

-- 3. Create programs table (Courses)
CREATE TABLE IF NOT EXISTS programs (
    program_id INT(11) PRIMARY KEY AUTO_INCREMENT,
    program_name VARCHAR(150) NOT NULL,
    program_code VARCHAR(50) NOT NULL UNIQUE,
    dept_id INT(11) NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (dept_id) REFERENCES departments(dept_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Modify courses table (Subjects)
ALTER TABLE courses ADD COLUMN IF NOT EXISTS program_id INT(11) DEFAULT NULL AFTER dept_id;
ALTER TABLE courses ADD CONSTRAINT fk_course_program FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE SET NULL;

-- 5. Modify students table
ALTER TABLE students ADD COLUMN IF NOT EXISTS program_id INT(11) DEFAULT NULL AFTER dept_id;
ALTER TABLE students ADD CONSTRAINT fk_student_program FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE SET NULL;

-- 6. Insert default college
INSERT IGNORE INTO colleges (college_name, college_code) VALUES ('College of Arts and Trades', 'BCAT');

-- 7. Assign existing departments to default college
UPDATE departments SET college_id = (SELECT college_id FROM colleges WHERE college_code = 'BCAT' LIMIT 1) WHERE college_id IS NULL;
