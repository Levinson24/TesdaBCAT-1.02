-- ============================================
-- TESDA-BCAT Grade Management System
-- Performance Optimization Script
-- ============================================

-- Use the correct database
USE tesda_db;

-- 1. Add missing indices for frequently searched/joined columns

-- Grades table optimizations
-- Index for status to speed up dashboard pending view
ALTER TABLE grades ADD INDEX IF NOT EXISTS idx_status (status);
-- Index for student_id for transcript generation
ALTER TABLE grades ADD INDEX IF NOT EXISTS idx_student_id (student_id);
-- Composite index for pending grades query efficiency
ALTER TABLE grades ADD INDEX IF NOT EXISTS idx_status_submitted (status, submitted_at);

-- Enrollments table optimizations
-- Index for student_id and section_id (already has unique constraint, but explicit index helps)
ALTER TABLE enrollments ADD INDEX IF NOT EXISTS idx_student_section (student_id, section_id);
-- Index for status
ALTER TABLE enrollments ADD INDEX IF NOT EXISTS idx_status (status);

-- Class Sections optimizations
-- Index for instructor_id mapping
ALTER TABLE class_sections ADD INDEX IF NOT EXISTS idx_instructor_id (instructor_id);
-- Index for course_id
ALTER TABLE class_sections ADD INDEX IF NOT EXISTS idx_course_id (course_id);

-- 2. Analyze tables to update statistics
ANALYZE TABLE grades;
ANALYZE TABLE enrollments;
ANALYZE TABLE students;
ANALYZE TABLE class_sections;
ANALYZE TABLE courses;

-- 3. Optimization Recommendations for Queries
-- Use prepared statements for all dynamic queries (already mostly implemented)
-- Avoid SELECT * in production where possible (select specific columns)
-- Use LIMIT for large datasets

-- Audit logs cleanup (optional - keep logs for 1 year)
-- DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR);
