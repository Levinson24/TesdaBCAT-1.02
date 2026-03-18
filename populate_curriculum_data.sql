-- ============================================================
-- TESDA-BCAT Official Curriculum Data Population (REVISED)
-- This script adds the missing Diploma Programs and their full courses.
-- ============================================================

USE tesda_db;

-- 1. Ensure all Diploma Programs exist in departments with codes
INSERT INTO departments (title_diploma_program, dept_code, status) VALUES 
('3-YEAR DIPLOMA IN REFRIGERATION AND AIRCONDITIONING TECHNOLOGY', 'RE-RAC-2024', 'active'),
('3-YEAR DIPLOMA IN ELECTRICAL TECHNOLOGY', 'RE-ELT-2024', 'active'),
('3-YEAR DIPLOMA IN CIVIL TECHNOLOGY', 'RE-CVT-2024', 'active')
ON DUPLICATE KEY UPDATE title_diploma_program = VALUES(title_diploma_program);

-- Ensure HRT has correct code
UPDATE departments SET dept_code = 'RE-HRT-2024' WHERE title_diploma_program = '3-YEAR DIPLOMA IN HOTEL AND RESTAURANT TECHNOLOGY';

-- 2. Ensure all Diploma Programs exist in programs table with codes
INSERT INTO programs (program_name, program_code, dept_id, status) 
SELECT '3-YEAR DIPLOMA IN REFRIGERATION AND AIRCONDITIONING TECHNOLOGY', 'RAC-DIP-3Y', dept_id, 'active' 
FROM departments WHERE title_diploma_program = '3-YEAR DIPLOMA IN REFRIGERATION AND AIRCONDITIONING TECHNOLOGY'
ON DUPLICATE KEY UPDATE program_name = VALUES(program_name);

INSERT INTO programs (program_name, program_code, dept_id, status) 
SELECT '3-YEAR DIPLOMA IN ELECTRICAL TECHNOLOGY', 'ELT-DIP-3Y', dept_id, 'active' 
FROM departments WHERE title_diploma_program = '3-YEAR DIPLOMA IN ELECTRICAL TECHNOLOGY'
ON DUPLICATE KEY UPDATE program_name = VALUES(program_name);

INSERT INTO programs (program_name, program_code, dept_id, status) 
SELECT '3-YEAR DIPLOMA IN CIVIL TECHNOLOGY', 'CVT-DIP-3Y', dept_id, 'active' 
FROM departments WHERE title_diploma_program = '3-YEAR DIPLOMA IN CIVIL TECHNOLOGY'
ON DUPLICATE KEY UPDATE program_name = VALUES(program_name);

INSERT INTO programs (program_name, program_code, dept_id, status) 
SELECT '3-YEAR DIPLOMA IN HOTEL AND RESTAURANT TECHNOLOGY', 'HRT-DIP-3Y', dept_id, 'active' 
FROM departments WHERE title_diploma_program = '3-YEAR DIPLOMA IN HOTEL AND RESTAURANT TECHNOLOGY'
ON DUPLICATE KEY UPDATE program_name = VALUES(program_name);

-- 3. HELPER: Get Program IDs
SET @rac_prog = (SELECT program_id FROM programs WHERE program_name = '3-YEAR DIPLOMA IN REFRIGERATION AND AIRCONDITIONING TECHNOLOGY' LIMIT 1);
SET @elt_prog = (SELECT program_id FROM programs WHERE program_name = '3-YEAR DIPLOMA IN ELECTRICAL TECHNOLOGY' LIMIT 1);
SET @cvt_prog = (SELECT program_id FROM programs WHERE program_name = '3-YEAR DIPLOMA IN CIVIL TECHNOLOGY' LIMIT 1);
SET @hrt_prog = (SELECT program_id FROM programs WHERE program_name = '3-YEAR DIPLOMA IN HOTEL AND RESTAURANT TECHNOLOGY' LIMIT 1);

-- 4. Clean up previous attempt
DELETE FROM courses WHERE program_id IN (@rac_prog, @elt_prog, @cvt_prog, @hrt_prog);

-- 5. POPULATE COURSES - RAC TECHNOLOGY
INSERT INTO courses (course_code, course_name, pre_requisites, units, program_id, course_type, status) VALUES
('RAC 1', 'RAC Fundamentals 1', NULL, 3, @rac_prog, 'major', 'active'),
('RAC 1L', 'Benchmark Operation', NULL, 3, @rac_prog, 'major', 'active'),
('Mech Draw 1', 'Mechanical Drawing', NULL, 3, @rac_prog, 'major', 'active'),
('Nat Sci', 'Environmental Science', NULL, 3, @rac_prog, 'general', 'active'),
('Hist 1', 'Readings in Philippine History', NULL, 3, @rac_prog, 'general', 'active'),
('RACT2', 'Masonry NC II', NULL, 4, @rac_prog, 'major', 'active'),
('PE 1', 'Physical Fitness Health and Wellness', NULL, 2, @rac_prog, 'general', 'active'),
('NSTP 1', 'Civic Welfare Training Service', NULL, 3, @rac_prog, 'general', 'active'),
('RAC 2', 'Electrical Refrigeration and Airconditioning/Electrical Benchwork, Circuits and Control', 'RAC 1', 4, @rac_prog, 'major', 'active'),
('Mech. Draw 2', 'Adv. Mechanical Drawing', NULL, 3, @rac_prog, 'major', 'active'),
('Math 1', 'Industrial Mathematics', NULL, 3, @rac_prog, 'general', 'active'),
('Comm 1', 'Purposive Communication', NULL, 3, @rac_prog, 'general', 'active'),
('Comp 1', 'Computer Application (CSS NC II)', NULL, 4, @rac_prog, 'major', 'active'),
('PE 2', 'Games and Sports', 'PE 1', 2, @rac_prog, 'general', 'active'),
('RACT 3', 'EIM NC II', NULL, 4, @rac_prog, 'major', 'active'),
('NSTP 2', 'CWTS', NULL, 3, @rac_prog, 'general', 'active'),
('SIL 1', 'Supervised Industry Learning (SIL)', NULL, 3, @rac_prog, 'major', 'active'),
('RAC 3', 'Vapour Compression Refrigeration', 'RAC 2', 3, @rac_prog, 'major', 'active'),
('RAC 3L', 'Domestic Refrigeration Servicing and Troubleshooting (DomRAC NC II)', 'RAC 2', 3, @rac_prog, 'major', 'active'),
('Physics 1', 'Fundamental Physics', NULL, 3, @rac_prog, 'general', 'active'),
('Mech Draw 3', 'Project Drawing with Basic CAD', 'Mech. Draw 2', 3, @rac_prog, 'major', 'active'),
('MSP 1', 'Maintenance and Safety Practice', NULL, 3, @rac_prog, 'major', 'active'),
('RACT 4', 'EIM NC III', NULL, 4, @rac_prog, 'major', 'active'),
('PE 3', 'Fund. of Dance and Rhythmic', 'PE 2', 2, @rac_prog, 'general', 'active'),
('RAC 4', 'Vapour Compression Airconditioning', 'RAC 3', 3, @rac_prog, 'major', 'active'),
('RAC 4L', 'Airconditioning Servicing and Troubleshooting', 'RAC 3', 4, @rac_prog, 'major', 'active'),
('CAD', 'Computer-Aided Drafting and Design', 'Mech Draw 3', 3, @rac_prog, 'major', 'active'),
('Elective 4', 'Ethics', NULL, 3, @rac_prog, 'general', 'active'),
('RACT 5', 'Photovoltaic System Installation NC II', NULL, 4, @rac_prog, 'major', 'active'),
('RACT 7', 'SMAW NC I', NULL, 4, @rac_prog, 'major', 'active'),
('SIL 2', 'Supervised Industry Learning (SIL)', NULL, 3, @rac_prog, 'major', 'active'),
('Math 2', 'Mathematics in the Modern World', NULL, 3, @rac_prog, 'general', 'active'),
('PD 1', 'Understanding the Self', NULL, 3, @rac_prog, 'general', 'active'),
('RAC 5', 'Commercial Refrigeration', 'RAC 4', 3, @rac_prog, 'major', 'active'),
('RAC 5L', 'Commercial Refrigeration Servicing and Troubleshooting', 'RAC 4', 4, @rac_prog, 'major', 'active'),
('RACT 8', 'Scaffolding Works (Supported Type) NC II', NULL, 4, @rac_prog, 'major', 'active'),
('RACT 9', 'Driving NC II', NULL, 4, @rac_prog, 'major', 'active'),
('OJT', 'Supervise Industry Immersion (SIL)', NULL, 6, @rac_prog, 'major', 'active');

-- 6. POPULATE COURSES - HRT TECHNOLOGY
INSERT INTO courses (course_code, course_name, pre_requisites, units, program_id, course_type, status) VALUES
('PE 1', 'Health and Wellness', NULL, 2, @hrt_prog, 'general', 'active'),
('Math 2', 'Mathematics in the Modern World', NULL, 3, @hrt_prog, 'general', 'active'),
('Comm 1', 'Purposive Communication', NULL, 3, @hrt_prog, 'general', 'active'),
('Soc Sci 1', 'Trends and Issues in the Hospitality', NULL, 3, @hrt_prog, 'major', 'active'),
('HRT 1', 'Fundamentals in Lodging Operations (HOUSEKEEPING NC II)', NULL, 4, @hrt_prog, 'major', 'active'),
('Hist 1', 'Readings in Philippine History', NULL, 3, @hrt_prog, 'general', 'active'),
('Soc Sci 2', 'Contemporary World', NULL, 3, @hrt_prog, 'general', 'active'),
('Fil 1', 'Masining na Pagpapahayag', NULL, 3, @hrt_prog, 'general', 'active'),
('HRT 2', 'Kitchen Essentials and Basic Food Preparation (Cookery NC II)', NULL, 4, @hrt_prog, 'major', 'active'),
('CWTS 1', 'Civic Welfare Training Service 1', NULL, 3, @hrt_prog, 'general', 'active'),
('Sci 1', 'Science Technology and Society', NULL, 3, @hrt_prog, 'general', 'active'),
('Tourism 1', 'Macro Perspective of Tourism and Hospitality', NULL, 3, @hrt_prog, 'major', 'active'),
('Mgt 2', 'Risk Management as applied to Safety, Security and Sanitation', NULL, 3, @hrt_prog, 'major', 'active'),
('Fil 3', 'Pagbasa at pagsusulat tungo sa Pananaliksik', NULL, 3, @hrt_prog, 'general', 'active'),
('PE 3', 'Rhythmic Activities', NULL, 2, @hrt_prog, 'general', 'active'),
('Elective 3', 'Philippine Indigenous Communities', NULL, 3, @hrt_prog, 'general', 'active'),
('Elective 4', 'Ethics', NULL, 3, @hrt_prog, 'general', 'active'),
('HRT 3', 'Housekeeping NC III', NULL, 4, @hrt_prog, 'major', 'active'),
('HRT 4', 'Bread and Pastry Production NC II', NULL, 4, @hrt_prog, 'major', 'active'),
('NSTP 2', 'Civic Welfare Training Service 2', NULL, 3, @hrt_prog, 'general', 'active'),
('Tourism 2', 'Micro Perspective in Tourism and Hospitality', 'Tourism 1', 3, @hrt_prog, 'major', 'active'),
('FL 1', 'Foreign Language 1 (Korean)', NULL, 3, @hrt_prog, 'major', 'active'),
('PMS', 'Applied Business Tools and Techniques (PMS) with Lab (Bookkeeping NC III)', NULL, 3, @hrt_prog, 'major', 'active'),
('PD 1', 'Understanding the Self', NULL, 3, @hrt_prog, 'general', 'active'),
('Mgt 4', 'Menu Design and Revenue Management', NULL, 3, @hrt_prog, 'major', 'active'),
('HRT 5', 'Bar and Beverage Management (Bartending NC II)', NULL, 4, @hrt_prog, 'major', 'active'),
('HRT 6', 'Fundamentals in Food Service Operations (FBS NC II)', NULL, 4, @hrt_prog, 'major', 'active'),
('HRT 7', 'Front Office Operation (FOS NC II)', NULL, 4, @hrt_prog, 'major', 'active'),
('FL 2', 'Foreign Language 2 (Spanish)', 'FL 1', 3, @hrt_prog, 'major', 'active'),
('MICE', 'Introduction to Meetings, Incentives, Conferences and Events Management', NULL, 3, @hrt_prog, 'major', 'active'),
('WE 1', 'Professional Development and Applied Ethics', NULL, 3, @hrt_prog, 'general', 'active'),
('Eng 1', 'Business Correspondence', NULL, 3, @hrt_prog, 'general', 'active'),
('Hum 1', 'Art Appreciation', NULL, 3, @hrt_prog, 'general', 'active'),
('Nat Sci 1', 'Environmental Science', NULL, 3, @hrt_prog, 'general', 'active'),
('Mgt 3', 'Supply Chain Management in Hospitality Industry', NULL, 3, @hrt_prog, 'major', 'active'),
('Elective 6', 'Philippine Culture and Tourism Geography', NULL, 3, @hrt_prog, 'major', 'active'),
('HRT 8', 'Barista NC II', NULL, 4, @hrt_prog, 'major', 'active'),
('HRT 9', 'Food Processing NC II', NULL, 4, @hrt_prog, 'major', 'active'),
('Entrep 1', 'Entrepreneurship in Tourism and Hospitality', NULL, 3, @hrt_prog, 'major', 'active'),
('HMPE 4', 'Catering Management', NULL, 3, @hrt_prog, 'major', 'active'),
('Fil 5', 'Panitikan nang Pilipinas', NULL, 3, @hrt_prog, 'general', 'active'),
('PE 4', 'Recreational Activities', 'PE 3', 2, @hrt_prog, 'general', 'active'),
('Tourism 3', 'Tourism and Hospitality Marketing', NULL, 3, @hrt_prog, 'major', 'active'),
('Mgt 5', 'Organization and Management', NULL, 3, @hrt_prog, 'major', 'active'),
('Tourism 4', 'Legal Aspect in Tourism Industry', NULL, 3, @hrt_prog, 'major', 'active'),
('HRT 11', 'DomWork NC II', NULL, 4, @hrt_prog, 'major', 'active'),
('Comp 1', 'Computer Application (CSS NC II)', NULL, 4, @hrt_prog, 'major', 'active'),
('SIL', 'Supervised Industry Learning (SIL)', NULL, 3, @hrt_prog, 'major', 'active'),
('OJT', 'Supervise Industry Immersion (SIL)', NULL, 6, @hrt_prog, 'major', 'active');

-- 7. POPULATE COURSES - ELECTRICAL TECHNOLOGY
INSERT INTO courses (course_code, course_name, pre_requisites, units, program_id, course_type, status) VALUES
('ELT 1', 'Basic Electricity Theory and Measurements Lec & Lab', NULL, 4, @elt_prog, 'major', 'active'),
('Tech Draw 1', 'Technical Drawing', NULL, 3, @elt_prog, 'major', 'active'),
('Fil 2', 'Kontekstwalisadong Komunikasyon sa Filipino', NULL, 3, @elt_prog, 'general', 'active'),
('Math 1', 'Industrial Mathematics', NULL, 3, @elt_prog, 'general', 'active'),
('ET 1', 'Shielded Metal Arc Welding NC I', NULL, 4, @elt_prog, 'major', 'active'),
('ET 2', 'Masonry NC II', NULL, 4, @elt_prog, 'major', 'active'),
('PE 1', 'Physical Fitness Health & Wellness', NULL, 2, @elt_prog, 'general', 'active'),
('NSTP 1', 'Civic Welfare Training Service', NULL, 3, @elt_prog, 'general', 'active'),
('ELT 2', 'Residential, Commercial, Industrial Wiring Lec', 'ELT 1', 3, @elt_prog, 'major', 'active'),
('ELT 2L', 'Residential, Commercial, Industrial Wiring Lab (EIM NC II)', 'ELT 1', 4, @elt_prog, 'major', 'active'),
('Tech Draw 2', 'Adv. Technical Drawing', 'Tech Draw 1', 3, @elt_prog, 'major', 'active'),
('Math 2', 'Mathematics in Modern World', NULL, 3, @elt_prog, 'general', 'active'),
('Comp 1', 'Computer Applications (CSS NC II)', NULL, 3, @elt_prog, 'major', 'active'),
('PD 2', 'Essence of Personality', NULL, 3, @elt_prog, 'general', 'active'),
('Elective 3', 'Philippine Popular Culture', NULL, 3, @elt_prog, 'general', 'active'),
('PE 2', 'Games and Sports', 'PE 1', 2, @elt_prog, 'general', 'active'),
('NSTP 2', 'Civic Welfare Training Service', NULL, 3, @elt_prog, 'general', 'active'),
('SIL', 'Supervised Industry Learning (SIL)', NULL, 4, @elt_prog, 'major', 'active'),
('ELT 3', 'Direct Current Machineries & Controller (Lec & Lab)', 'ELT 2', 4, @elt_prog, 'major', 'active'),
('ET 3', 'Photovoltaic System Installation NC II', NULL, 4, @elt_prog, 'major', 'active'),
('ET 4', 'DomRAC NC II', NULL, 4, @elt_prog, 'major', 'active'),
('Tech Draw 3', 'Project Design', 'Tech Draw 2', 3, @elt_prog, 'major', 'active'),
('Hum 1', 'Art Appreciation', NULL, 3, @elt_prog, 'general', 'active'),
('Hist 1', 'Readings In Philippine History', NULL, 3, @elt_prog, 'general', 'active'),
('MSP 1', 'Maintenance and Safety Practices', NULL, 3, @elt_prog, 'major', 'active'),
('PE 3', 'Fund. Of Dances & Rhythmic Actv.', 'PE 2', 2, @elt_prog, 'general', 'active'),
('ELT 4', 'Alternating Current Machineries & Controller (Lec & Lab)', 'ELT 3', 4, @elt_prog, 'major', 'active'),
('ET 5', 'Scaffold NC II', NULL, 4, @elt_prog, 'major', 'active'),
('ET 7', 'Carpentry NC II', NULL, 4, @elt_prog, 'major', 'active'),
('Comm 1', 'Purposive Communication', NULL, 3, @elt_prog, 'general', 'active'),
('CAD', 'Computer-Aided Drafting and Design', 'Tech Draw 3', 3, @elt_prog, 'major', 'active'),
('Fil 4', 'Sosyedad at Literatura/Panitikan Panlipunan', NULL, 3, @elt_prog, 'general', 'active'),
('Elective 3_2', 'Disaster Readiness and Risk Reduction Mgt.', NULL, 3, @elt_prog, 'general', 'active'),
('PD 3', 'Social Graces and Social Relations', NULL, 3, @elt_prog, 'general', 'active'),
('ELT 5', 'Power Wiring, Distribution, Planning & Estimating & Electrical Instrument Lec', 'ELT 4', 3, @elt_prog, 'major', 'active'),
('ELT 5L', 'Power Wiring, Distribution, Planning & Estimating & Electrical Instrument Lab', 'ELT 4L', 4, @elt_prog, 'major', 'active'),
('ET 8', 'DRIVING NC II', NULL, 4, @elt_prog, 'major', 'active'),
('Elective 1', 'The Entrepreneurial Mind', NULL, 3, @elt_prog, 'major', 'active'),
('PD 1', 'Understanding the Self', NULL, 3, @elt_prog, 'general', 'active'),
('Mgt 1', 'Operational Management (Bookkeeping NC)', NULL, 3, @elt_prog, 'major', 'active'),
('Psych 1', 'Industrial Psychology', NULL, 3, @elt_prog, 'general', 'active'),
('HRM 1', 'Human Resource Mgt', NULL, 3, @elt_prog, 'major', 'active'),
('Nat Sci 1', 'Environmental Science', NULL, 3, @elt_prog, 'general', 'active'),
('OJT', 'Supervised Industry Immersion (SIL)', NULL, 6, @elt_prog, 'major', 'active');

-- 8. POPULATE COURSES - CIVIL TECHNOLOGY
INSERT INTO courses (course_code, course_name, pre_requisites, units, program_id, course_type, status) VALUES
('Tech Draw 1', 'Technical Drawing', NULL, 3, @cvt_prog, 'major', 'active'),
('PD 1', 'Understanding the Self', NULL, 3, @cvt_prog, 'general', 'active'),
('Fil 2', 'Kontekstwalisadong Komunikasyon sa Filipino', NULL, 3, @cvt_prog, 'general', 'active'),
('Math 1', 'Industrial Mathematics', NULL, 3, @cvt_prog, 'general', 'active'),
('PE 1', 'Physical Fitness, Health and Wellness', NULL, 2, @cvt_prog, 'general', 'active'),
('CT 1', 'Scaffolding Works (Supported Type) NC II', NULL, 4, @cvt_prog, 'major', 'active'),
('CT 2', 'Tile Setting NC II', NULL, 4, @cvt_prog, 'major', 'active'),
('NSTP 1', 'Civi Welfare Training Service I', NULL, 3, @cvt_prog, 'general', 'active'),
('Elective 1', 'The Entrepreneurial Mind', NULL, 3, @cvt_prog, 'major', 'active'),
('Tech Draw 2', 'Advance Technical Drawing', 'Tech Draw 1', 3, @cvt_prog, 'major', 'active'),
('Elective 2', 'Philippine Popular Culture', NULL, 3, @cvt_prog, 'general', 'active'),
('Hum 1', 'Art Appreciation', NULL, 3, @cvt_prog, 'general', 'active'),
('PE 2', 'Games and Sports', 'PE 1', 2, @cvt_prog, 'general', 'active'),
('CT 3', 'Masonry NC II', NULL, 4, @cvt_prog, 'major', 'active'),
('CT 4', 'Carpentry NC II', NULL, 4, @cvt_prog, 'major', 'active'),
('NSTP 2', 'Civic Welfare Training Service II', NULL, 3, @cvt_prog, 'general', 'active'),
('SIL', 'Supervised Industry Learning', NULL, 4, @cvt_prog, 'major', 'active'),
('Tech Draw 3', 'Project Design', 'Tech Draw 2', 3, @cvt_prog, 'major', 'active'),
('Hist 1', 'Reading in Phil. History', NULL, 3, @cvt_prog, 'general', 'active'),
('MSP 1', 'Maintenance and Safety Practices', NULL, 3, @cvt_prog, 'major', 'active'),
('PD 2', 'Essence of Personality', NULL, 3, @cvt_prog, 'general', 'active'),
('Math 2', 'Mathematics in the Modern World', NULL, 3, @cvt_prog, 'general', 'active'),
('PE 3', 'Fund. Of Dance and Rythmic Activities', 'PE 2', 2, @cvt_prog, 'general', 'active'),
('Physics 1', 'Fundamental Physics', NULL, 3, @cvt_prog, 'general', 'active'),
('CT 5', 'Electrical Installation and Maintenance NC', NULL, 4, @cvt_prog, 'major', 'active'),
('CT 6', 'Plumbing NC II', NULL, 4, @cvt_prog, 'major', 'active'),
('CAD', 'Computer Aided Drafting', 'Tech Draw 3', 3, @cvt_prog, 'major', 'active'),
('Comm 1', 'Purposive Communication', NULL, 3, @cvt_prog, 'general', 'active'),
('Fil 4', 'Sosyedad at Literatura/Panitikan Panlipunan', NULL, 3, @cvt_prog, 'general', 'active'),
('Comp 1', 'Computer Application (CSS NC II)', NULL, 4, @cvt_prog, 'major', 'active'),
('Elective 3', 'Disaster Readiness & Risk Reduction Management', NULL, 3, @cvt_prog, 'general', 'active'),
('PD 3', 'Social Graces and Social Relations', NULL, 3, @cvt_prog, 'general', 'active'),
('CT 7', 'Automotive Servicing NC I', NULL, 4, @cvt_prog, 'major', 'active'),
('Elective 4', 'Ethics', NULL, 3, @cvt_prog, 'general', 'active'),
('Mgt 1', 'Operational Management (Bookkeeping NC)', NULL, 3, @cvt_prog, 'major', 'active'),
('Psycho 1', 'Industrial Psychology', NULL, 3, @cvt_prog, 'general', 'active'),
('HRM 1', 'Human Resource Management', NULL, 3, @cvt_prog, 'major', 'active'),
('Nat Sci 1', 'Environmental Science', NULL, 3, @cvt_prog, 'general', 'active'),
('CT 10', 'Driving NC II', NULL, 4, @cvt_prog, 'major', 'active'),
('CT 12', 'SMAW NC I', NULL, 4, @cvt_prog, 'major', 'active'),
('OJT', 'Supervised Industry Learning (SIL)', NULL, 6, @cvt_prog, 'major', 'active');
