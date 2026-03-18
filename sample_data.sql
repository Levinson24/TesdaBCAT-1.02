-- ============================================
-- Sample Data for Testing
-- TESDA-BCAT Grade Management System
-- ============================================

USE tesda_db;

-- Sample Instructors
INSERT INTO users (username, password, role, status) VALUES
('instructor1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'instructor', 'active'),
('instructor2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'instructor', 'active');

INSERT INTO instructors (user_id, first_name, last_name, middle_name, department, specialization, email) VALUES
((SELECT user_id FROM users WHERE username = 'instructor1'), 'Juan', 'Dela Cruz', 'Santos', 'General Education', 'Mathematics', 'juan.delacruz@tesda-bcat.edu.ph'),
((SELECT user_id FROM users WHERE username = 'instructor2'), 'Maria', 'Garcia', 'Lopez', 'Technical', 'Computer Programming', 'maria.garcia@tesda-bcat.edu.ph');

-- Sample Registrar
INSERT INTO users (username, password, role, status) VALUES
('registrar', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'registrar', 'active');

-- Sample Students
INSERT INTO users (username, password, role, status) VALUES
('student1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active'),
('student2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active'),
('student3', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active');

INSERT INTO students (user_id, student_no, first_name, last_name, middle_name, date_of_birth, gender, address, contact_number, email, course, year_level, enrollment_date) VALUES
((SELECT user_id FROM users WHERE username = 'student1'), '2024-001', 'Trisha', 'Calagos', 'A.', '2003-07-08', 'Female', 'Magallanes St., Sabang 2, Allen, Northern Samar', '09123456789', 'trisha.calagos@email.com', 'Automotive Servicing NC I', 1, '2024-08-15'),
((SELECT user_id FROM users WHERE username = 'student2'), '2024-002', 'Pedro', 'Reyes', 'B.', '2003-05-15', 'Male', 'Poblacion, Allen, Northern Samar', '09234567890', 'pedro.reyes@email.com', 'Computer Programming', 1, '2024-08-15'),
((SELECT user_id FROM users WHERE username = 'student3'), '2024-003', 'Ana', 'Santos', 'C.', '2003-09-20', 'Female', 'San Isidro, Allen, Northern Samar', '09345678901', 'ana.santos@email.com', 'Automotive Servicing NC I', 1, '2024-08-15');

-- Sample Courses
INSERT INTO courses (course_code, course_name, description, units, status) VALUES
('HIST 1', 'Reading in Philippine History', 'Study of Philippine history', 3, 'active'),
('MSP 1', 'Maintenance and Safety Practices', 'Vehicle maintenance and safety', 2, 'active'),
('PD 2', 'Essence of Personality', 'Personal development', 2, 'active'),
('MATH 2', 'Mathematics in the Modern World', 'Applied mathematics', 3, 'active'),
('PE 3', 'Fund. of Dance and Rhythmic Activities', 'Physical education - dance', 2, 'active'),
('PHYSICS 1', 'Fundamental Physics', 'Basic physics concepts', 2, 'active'),
('CT 5', 'Electrical Installation and Maintenance NC II', 'Electrical systems', 2, 'active'),
('CT 6', 'Plumbing NC II', 'Plumbing fundamentals', 2, 'active'),
('CAD', 'Computer Aided Drafting', 'Technical drawing using CAD', 2, 'active'),
('COMM 1', 'Purposive Communication', 'Communication skills', 2, 'active');

-- Sample Class Sections for First Semester 2024-2025
INSERT INTO class_sections (course_id, instructor_id, section_name, semester, school_year, schedule, room, status) VALUES
((SELECT course_id FROM courses WHERE course_code = 'HIST 1'), 
 (SELECT instructor_id FROM instructors WHERE first_name = 'Juan'), 
 'A', '1st', '2024-2025', 'MW 8:00-9:30 AM', 'Room 101', 'active'),
 
((SELECT course_id FROM courses WHERE course_code = 'MSP 1'), 
 (SELECT instructor_id FROM instructors WHERE first_name = 'Maria'), 
 'A', '1st', '2024-2025', 'TTH 10:00-11:30 AM', 'Workshop 1', 'active'),
 
((SELECT course_id FROM courses WHERE course_code = 'MATH 2'), 
 (SELECT instructor_id FROM instructors WHERE first_name = 'Juan'), 
 'A', '1st', '2024-2025', 'MW 1:00-2:30 PM', 'Room 102', 'active'),
 
((SELECT course_id FROM courses WHERE course_code = 'CAD'), 
 (SELECT instructor_id FROM instructors WHERE first_name = 'Maria'), 
 'A', '1st', '2024-2025', 'TTH 1:00-2:30 PM', 'Computer Lab', 'active');

-- Enroll students in sections
INSERT INTO enrollments (student_id, section_id, enrollment_date, status) VALUES
-- Student 1 (Trisha) enrollments
((SELECT student_id FROM students WHERE student_no = '2024-001'),
 (SELECT section_id FROM class_sections WHERE course_id = (SELECT course_id FROM courses WHERE course_code = 'HIST 1') AND section_name = 'A'),
 '2024-08-15', 'enrolled'),
 
((SELECT student_id FROM students WHERE student_no = '2024-001'),
 (SELECT section_id FROM class_sections WHERE course_id = (SELECT course_id FROM courses WHERE course_code = 'MSP 1') AND section_name = 'A'),
 '2024-08-15', 'enrolled'),
 
((SELECT student_id FROM students WHERE student_no = '2024-001'),
 (SELECT section_id FROM class_sections WHERE course_id = (SELECT course_id FROM courses WHERE course_code = 'MATH 2') AND section_name = 'A'),
 '2024-08-15', 'enrolled'),

-- Student 2 (Pedro) enrollments
((SELECT student_id FROM students WHERE student_no = '2024-002'),
 (SELECT section_id FROM class_sections WHERE course_id = (SELECT course_id FROM courses WHERE course_code = 'HIST 1') AND section_name = 'A'),
 '2024-08-15', 'enrolled'),
 
((SELECT student_id FROM students WHERE student_no = '2024-002'),
 (SELECT section_id FROM class_sections WHERE course_id = (SELECT course_id FROM courses WHERE course_code = 'CAD') AND section_name = 'A'),
 '2024-08-15', 'enrolled'),

-- Student 3 (Ana) enrollments
((SELECT student_id FROM students WHERE student_no = '2024-003'),
 (SELECT section_id FROM class_sections WHERE course_id = (SELECT course_id FROM courses WHERE course_code = 'MSP 1') AND section_name = 'A'),
 '2024-08-15', 'enrolled'),
 
((SELECT student_id FROM students WHERE student_no = '2024-003'),
 (SELECT section_id FROM class_sections WHERE course_id = (SELECT course_id FROM courses WHERE course_code = 'MATH 2') AND section_name = 'A'),
 '2024-08-15', 'enrolled');

-- Sample grades (some submitted, some pending)
-- Note: Grades are created automatically when enrollments are created, but we can update them

-- Trisha's grades for HIST 1 (approved)
INSERT INTO grades (enrollment_id, student_id, section_id, midterm, final, grade, remarks, status, submitted_by, submitted_at, approved_by, approved_at)
SELECT 
    e.enrollment_id,
    e.student_id,
    e.section_id,
    88.00,
    92.00,
    90.00,
    'Passed',
    'approved',
    cs.instructor_id,
    '2024-11-15 10:00:00',
    (SELECT user_id FROM users WHERE role = 'registrar' LIMIT 1),
    '2024-11-16 14:00:00'
FROM enrollments e
JOIN class_sections cs ON e.section_id = cs.section_id
JOIN courses c ON cs.course_id = c.course_id
WHERE e.student_id = (SELECT student_id FROM students WHERE student_no = '2024-001')
AND c.course_code = 'HIST 1';

-- Trisha's grades for MSP 1 (submitted, pending approval)
INSERT INTO grades (enrollment_id, student_id, section_id, midterm, final, grade, remarks, status, submitted_by, submitted_at)
SELECT 
    e.enrollment_id,
    e.student_id,
    e.section_id,
    85.00,
    87.00,
    86.00,
    'Passed',
    'submitted',
    cs.instructor_id,
    '2024-12-01 09:00:00'
FROM enrollments e
JOIN class_sections cs ON e.section_id = cs.section_id
JOIN courses c ON cs.course_id = c.course_id
WHERE e.student_id = (SELECT student_id FROM students WHERE student_no = '2024-001')
AND c.course_code = 'MSP 1';

-- Pedro's grades for CAD (approved)
INSERT INTO grades (enrollment_id, student_id, section_id, midterm, final, grade, remarks, status, submitted_by, submitted_at, approved_by, approved_at)
SELECT 
    e.enrollment_id,
    e.student_id,
    e.section_id,
    95.00,
    96.00,
    95.50,
    'Passed',
    'approved',
    cs.instructor_id,
    '2024-11-20 11:00:00',
    (SELECT user_id FROM users WHERE role = 'registrar' LIMIT 1),
    '2024-11-21 15:00:00'
FROM enrollments e
JOIN class_sections cs ON e.section_id = cs.section_id
JOIN courses c ON cs.course_id = c.course_id
WHERE e.student_id = (SELECT student_id FROM students WHERE student_no = '2024-002')
AND c.course_code = 'CAD';

-- ============================================
-- All sample data inserted successfully
-- ============================================

-- NOTE: All test accounts use the same password: admin123
-- This should be changed in production!

SELECT 'Sample data inserted successfully!' as Message;
