-- Add academic_honor column to students table
USE tesda_db;
ALTER TABLE students ADD COLUMN academic_honor VARCHAR(50) DEFAULT NULL AFTER status;
ALTER TABLE students ADD COLUMN honor_evaluated_by INT(11) DEFAULT NULL AFTER academic_honor;
ALTER TABLE students ADD CONSTRAINT fk_honor_evaluator FOREIGN KEY (honor_evaluated_by) REFERENCES users(user_id) ON DELETE SET NULL;
