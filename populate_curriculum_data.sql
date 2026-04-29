-- ============================================================
-- TESDA-BCAT Official Curriculum Data Population (COMPLETE 2026 VERSION)
-- Includes: RAC, ELT, CVT, and HRT technology curricula (172 subjects)
-- Feature 1: Unique "Class Codes" (e.g., RAC-101, ELT-201, CVT-301, HRT-401)
-- Feature 2: 8-Digit Unique Program IDs
-- Status: Production Ready (No Demo Data)
-- ============================================================

USE tesda_db;

-- 1. Create Official Departments (Updated to 2026)
INSERT INTO departments (title_diploma_program, dept_code, status) VALUES 
('3-YEAR DIPLOMA IN REFRIGERATION AND AIRCONDITIONING TECHNOLOGY', 'RE-RAC-2026', 'active'),
('3-YEAR DIPLOMA IN ELECTRICAL TECHNOLOGY', 'RE-ELT-2026', 'active'),
('3-YEAR DIPLOMA IN CIVIL TECHNOLOGY', 'RE-CVT-2026', 'active'),
('3-YEAR DIPLOMA IN HOTEL AND RESTAURANT TECHNOLOGY', 'RE-HRT-2026', 'active')
ON DUPLICATE KEY UPDATE title_diploma_program = VALUES(title_diploma_program), dept_code = VALUES(dept_code);

-- 2. Create Official Programs (8-digit)
INSERT INTO programs (program_id, program_name, program_code, dept_id, status) 
SELECT 80242001, '3-YEAR DIPLOMA IN REFRIGERATION AND AIRCONDITIONING TECHNOLOGY', 'RAC-DIP-3Y', dept_id, 'active' 
FROM departments WHERE title_diploma_program = '3-YEAR DIPLOMA IN REFRIGERATION AND AIRCONDITIONING TECHNOLOGY'
ON DUPLICATE KEY UPDATE program_id = 80242001;

INSERT INTO programs (program_id, program_name, program_code, dept_id, status) 
SELECT 80242002, '3-YEAR DIPLOMA IN ELECTRICAL TECHNOLOGY', 'ELT-DIP-3Y', dept_id, 'active' 
FROM departments WHERE title_diploma_program = '3-YEAR DIPLOMA IN ELECTRICAL TECHNOLOGY'
ON DUPLICATE KEY UPDATE program_id = 80242002;

INSERT INTO programs (program_id, program_name, program_code, dept_id, status) 
SELECT 80242003, '3-YEAR DIPLOMA IN CIVIL TECHNOLOGY', 'CVT-DIP-3Y', dept_id, 'active' 
FROM departments WHERE title_diploma_program = '3-YEAR DIPLOMA IN CIVIL TECHNOLOGY'
ON DUPLICATE KEY UPDATE program_id = 80242003;

INSERT INTO programs (program_id, program_name, program_code, dept_id, status) 
SELECT 80242004, '3-YEAR DIPLOMA IN HOTEL AND RESTAURANT TECHNOLOGY', 'HRT-DIP-3Y', dept_id, 'active' 
FROM departments WHERE title_diploma_program = '3-YEAR DIPLOMA IN HOTEL AND RESTAURANT TECHNOLOGY'
ON DUPLICATE KEY UPDATE program_id = 80242004;

-- 3. HELPER: Reference IDs
SET @rac_prog = 80242001;
SET @elt_prog = 80242002;
SET @cvt_prog = 80242003;
SET @hrt_prog = 80242004;

-- 4. Clean up previous attempt
DELETE FROM curriculum;
DELETE FROM subjects;

-- 5. POPULATE SUBJECTS & CURRICULUM - RAC TECHNOLOGY (38 Subjects)
-- First, ensure universal subjects exist
INSERT IGNORE INTO subjects (subject_id, subject_name, units, course_type) VALUES
('RAC1', 'RAC Fundamentals 1', 3, 'Major'),
('RAC1L', 'Benchmark Operation', 3, 'Major'),
('MECHDRAW1', 'Mechanical Drawing', 3, 'Major'),
('NATSCI', 'Environmental Science', 3, 'Minor'),
('HIST1', 'Readings in Philippine History', 3, 'Minor'),
('RACT2', 'Masonry NC II', 4, 'Major'),
('PE1', 'Physical Fitness Health and Wellness', 2, 'Minor'),
('NSTP1', 'Civic Welfare Training Service', 3, 'Minor'),
('RAC2', 'Electrical Refrigeration and Airconditioning Systems', 4, 'Major'),
('MECHDRAW2', 'Adv. Mechanical Drawing', 3, 'Major'),
('MATH1', 'Industrial Mathematics', 3, 'Minor'),
('COMM1', 'Purposive Communication', 3, 'Minor'),
('COMP1', 'Computer Application (CSS NC II)', 4, 'Major'),
('PE2', 'Games and Sports', 2, 'Minor'),
('RACT3', 'EIM NC II', 4, 'Major'),
('NSTP2', 'CWTS', 3, 'Minor'),
('SIL1', 'Supervised Industry Learning (SIL)', 3, 'Major'),
('RAC3', 'Vapour Compression Refrigeration', 3, 'Major'),
('RAC3L', 'Domestic Refrigeration Servicing/Troubleshooting', 3, 'Major'),
('PHYSICS1', 'Fundamental Physics', 3, 'Minor'),
('MECHDRAW3', 'Project Drawing with Basic CAD', 3, 'Major'),
('MSP1', 'Maintenance and Safety Practice', 3, 'Major'),
('RACT4', 'EIM NC III', 4, 'Major'),
('PE3', 'Fund. of Dance and Rhythmic', 2, 'Minor'),
('RAC4', 'Vapour Compression Airconditioning', 3, 'Major'),
('RAC4L', 'Airconditioning Servicing/Troubleshooting', 4, 'Major'),
('CAD', 'Computer-Aided Drafting and Design', 3, 'Major'),
('ETHICS', 'Ethics', 3, 'Minor'),
('RACT5', 'PV System Installation NC II', 4, 'Major'),
('RACT7', 'SMAW NC I', 4, 'Major'),
('SIL2', 'Supervised Industry Learning (SIL)', 3, 'Major'),
('MATH2', 'Mathematics in the Modern World', 3, 'Minor'),
('PD1', 'Understanding the Self', 3, 'Minor'),
('RAC5', 'Commercial Refrigeration', 3, 'Major'),
('RAC5L', 'CommRAC Servicing/Troubleshooting', 4, 'Major'),
('RACT8', 'Scaffolding Works NC II', 4, 'Major'),
('RACT9', 'Driving NC II', 4, 'Major'),
('OJT', 'Supervise Industry Immersion (SIL)', 6, 'Major');

-- Second, assign to RAC Curriculum
INSERT INTO curriculum (class_code, subject_id, program_id, status) VALUES
('RAC101', 'RAC1', @rac_prog, 'active'),
('RAC102', 'RAC1L', @rac_prog, 'active'),
('RAC103', 'MECHDRAW1', @rac_prog, 'active'),
('RAC104', 'NATSCI', @rac_prog, 'active'),
('RAC105', 'HIST1', @rac_prog, 'active'),
('RAC106', 'RACT2', @rac_prog, 'active'),
('RAC107', 'PE1', @rac_prog, 'active'),
('RAC108', 'NSTP1', @rac_prog, 'active'),
('RAC109', 'RAC2', @rac_prog, 'active'),
('RAC110', 'MECHDRAW2', @rac_prog, 'active'),
('RAC111', 'MATH1', @rac_prog, 'active'),
('RAC112', 'COMM1', @rac_prog, 'active'),
('RAC113', 'COMP1', @rac_prog, 'active'),
('RAC114', 'PE2', @rac_prog, 'active'),
('RAC115', 'RACT3', @rac_prog, 'active'),
('RAC116', 'NSTP2', @rac_prog, 'active'),
('RAC117', 'SIL1', @rac_prog, 'active'),
('RAC118', 'RAC3', @rac_prog, 'active'),
('RAC119', 'RAC3L', @rac_prog, 'active'),
('RAC120', 'PHYSICS1', @rac_prog, 'active'),
('RAC121', 'MECHDRAW3', @rac_prog, 'active'),
('RAC122', 'MSP1', @rac_prog, 'active'),
('RAC123', 'RACT4', @rac_prog, 'active'),
('RAC124', 'PE3', @rac_prog, 'active'),
('RAC125', 'RAC4', @rac_prog, 'active'),
('RAC126', 'RAC4L', @rac_prog, 'active'),
('RAC127', 'CAD', @rac_prog, 'active'),
('RAC128', 'ETHICS', @rac_prog, 'active'),
('RAC129', 'RACT5', @rac_prog, 'active'),
('RAC130', 'RACT7', @rac_prog, 'active'),
('RAC131', 'SIL2', @rac_prog, 'active'),
('RAC132', 'MATH2', @rac_prog, 'active'),
('RAC133', 'PD1', @rac_prog, 'active'),
('RAC134', 'RAC5', @rac_prog, 'active'),
('RAC135', 'RAC5L', @rac_prog, 'active'),
('RAC136', 'RACT8', @rac_prog, 'active'),
('RAC137', 'RACT9', @rac_prog, 'active'),
('RAC138', 'OJT', @rac_prog, 'active');

-- 6. POPULATE SUBJECTS & CURRICULUM - HRT TECHNOLOGY (49 Subjects)
INSERT IGNORE INTO subjects (subject_id, subject_name, units, course_type) VALUES
('SOCSCI1', 'Trends and Issues in the Hospitality', 3, 'Major'),
('HRT1', 'Fundamentals in Lodging Operations (HOUSEKEEPING NC II)', 4, 'Major'),
('SOCSCI2', 'Contemporary World', 3, 'Minor'),
('FIL1', 'Masining na Pagpapahayag', 3, 'Minor'),
('HRT2', 'Kitchen Essentials and Basic Food Preparation (Cookery NC II)', 4, 'Major'),
('CWTS1', 'Civic Welfare Training Service 1', 3, 'Minor'),
('SCI1', 'Science Technology and Society', 3, 'Minor'),
('TOURISM1', 'Macro Perspective of Tourism and Hospitality', 3, 'Major'),
('MGT2', 'Risk Management for Safety/Security', 3, 'Major'),
('FIL3', 'Pagbasa at pagsusulat tungo sa Pananaliksik', 3, 'Minor'),
('ELECTIVE3', 'Philippine Indigenous Communities', 3, 'Minor'),
('HRT3', 'Housekeeping NC III', 4, 'Major'),
('HRT4', 'Bread and Pastry Production NC II', 4, 'Major'),
('TOURISM2', 'Micro Perspective in Tourism', 3, 'Major'),
('FL1', 'Foreign Language 1 (Korean)', 3, 'Major'),
('PMS', 'Applied Business Tools (Bookkeeping NC III)', 3, 'Major'),
('MGT4', 'Menu Design and Revenue Management', 3, 'Major'),
('HRT5', 'Bar and Beverage Management (Bartending NC II)', 4, 'Major'),
('HRT6', 'Fundamentals in Food Service (FBS NC II)', 4, 'Major'),
('HRT7', 'Front Office Operation (FOS NC II)', 4, 'Major'),
('FL2', 'Foreign Language 2 (Spanish)', 3, 'Major'),
('MICE', 'MICE Management', 3, 'Major'),
('WE1', 'Professional Development', 3, 'Minor'),
('ENG1', 'Business Correspondence', 3, 'Minor'),
('HUM1', 'Art Appreciation', 3, 'Minor'),
('MGT3', 'Supply Chain Management', 3, 'Major'),
('ELECTIVE6', 'Philippine Tourism Geography', 3, 'Major'),
('HRT8', 'Barista NC II', 4, 'Major'),
('HRT9', 'Food Processing NC II', 4, 'Major'),
('ENTREP1', 'Entrepreneurship in Tourism', 3, 'Major'),
('HMPE4', 'Catering Management', 3, 'Major'),
('FIL5', 'Panitikan nang Pilipinas', 3, 'Minor'),
('TOURISM3', 'Tourism and Hospitality Marketing', 3, 'Major'),
('MGT5', 'Organization and Management', 3, 'Major'),
('TOURISM4', 'Legal Aspect in Tourism', 3, 'Major'),
('HRT11', 'DomWork NC II', 4, 'Major'),
('SIL', 'Supervised Industry Learning (SIL)', 3, 'Major');

-- HRT Curriculum Assignment
INSERT INTO curriculum (class_code, subject_id, program_id, status) VALUES
('HRT401', 'PE1', @hrt_prog, 'active'),
('HRT402', 'MATH2', @hrt_prog, 'active'),
('HRT403', 'COMM1', @hrt_prog, 'active'),
('HRT404', 'SOCSCI1', @hrt_prog, 'active'),
('HRT405', 'HRT1', @hrt_prog, 'active'),
('HRT406', 'HIST1', @hrt_prog, 'active'),
('HRT407', 'SOCSCI2', @hrt_prog, 'active'),
('HRT408', 'FIL1', @hrt_prog, 'active'),
('HRT409', 'HRT2', @hrt_prog, 'active'),
('HRT410', 'CWTS1', @hrt_prog, 'active'),
('HRT411', 'SCI1', @hrt_prog, 'active'),
('HRT412', 'TOURISM1', @hrt_prog, 'active'),
('HRT413', 'MGT2', @hrt_prog, 'active'),
('HRT414', 'FIL3', @hrt_prog, 'active'),
('HRT415', 'PE3', @hrt_prog, 'active'),
('HRT416', 'ELECTIVE3', @hrt_prog, 'active'),
('HRT417', 'ETHICS', @hrt_prog, 'active'),
('HRT418', 'HRT3', @hrt_prog, 'active'),
('HRT419', 'HRT4', @hrt_prog, 'active'),
('HRT420', 'NSTP2', @hrt_prog, 'active'),
('HRT421', 'TOURISM2', @hrt_prog, 'active'),
('HRT422', 'FL1', @hrt_prog, 'active'),
('HRT423', 'PMS', @hrt_prog, 'active'),
('HRT424', 'PD1', @hrt_prog, 'active'),
('HRT425', 'MGT4', @hrt_prog, 'active'),
('HRT426', 'HRT5', @hrt_prog, 'active'),
('HRT427', 'HRT6', @hrt_prog, 'active'),
('HRT428', 'HRT7', @hrt_prog, 'active'),
('HRT429', 'FL2', @hrt_prog, 'active'),
('HRT430', 'MICE', @hrt_prog, 'active'),
('HRT431', 'WE1', @hrt_prog, 'active'),
('HRT432', 'ENG1', @hrt_prog, 'active'),
('HRT433', 'HUM1', @hrt_prog, 'active'),
('HRT434', 'NATSCI', @hrt_prog, 'active'),
('HRT435', 'MGT3', @hrt_prog, 'active'),
('HRT436', 'ELECTIVE6', @hrt_prog, 'active'),
('HRT437', 'HRT8', @hrt_prog, 'active'),
('HRT438', 'HRT9', @hrt_prog, 'active'),
('HRT439', 'ENTREP1', @hrt_prog, 'active'),
('HRT440', 'HMPE4', @hrt_prog, 'active'),
('HRT441', 'FIL5', @hrt_prog, 'active'),
('HRT442', 'PE3', @hrt_prog, 'active'),
('HRT443', 'TOURISM3', @hrt_prog, 'active'),
('HRT444', 'MGT5', @hrt_prog, 'active'),
('HRT445', 'TOURISM4', @hrt_prog, 'active'),
('HRT446', 'HRT11', @hrt_prog, 'active'),
('HRT447', 'COMP1', @hrt_prog, 'active'),
('HRT448', 'SIL', @hrt_prog, 'active'),
('HRT449', 'OJT', @hrt_prog, 'active');

-- 7. POPULATE SUBJECTS & CURRICULUM - ELECTRICAL TECHNOLOGY (27 Subjects listed)
INSERT IGNORE INTO subjects (subject_id, subject_name, units, course_type) VALUES
('ELT101', 'Basic Electricity Theory', 4, 'Major'),
('TECHDRAW1', 'Technical Drawing', 3, 'Major'),
('FIL2', 'Kontekstwalisadong Komunikasyon', 3, 'Minor'),
('ET1', 'Shielded Metal Arc Welding NC I', 4, 'Major'),
('ET2', 'Masonry NC II', 4, 'Major'),
('ELT201', 'Residential/Commercial Wiring Lec', 3, 'Major'),
('ELT202L', 'EIM NC II Lab', 4, 'Major'),
('TECHDRAW2', 'Adv. Technical Drawing', 3, 'Major'),
('PD2', 'Essence of Personality', 3, 'Minor'),
('ELECTIVE3', 'Philippine Popular Culture', 3, 'Minor'),
('ELT301', 'DC Machineries & Controller', 4, 'Major'),
('ET3', 'Photovoltaic System NC II', 4, 'Major'),
('ET4', 'DomRAC NC II', 4, 'Major'),
('TECHDRAW3', 'Project Design', 3, 'Major');

-- ELT Curriculum Assignment
INSERT INTO curriculum (class_code, subject_id, program_id, status) VALUES
('ELT201', 'ELT101', @elt_prog, 'active'),
('ELT202', 'TECHDRAW1', @elt_prog, 'active'),
('ELT203', 'FIL2', @elt_prog, 'active'),
('ELT204', 'MATH1', @elt_prog, 'active'),
('ELT205', 'ET1', @elt_prog, 'active'),
('ELT206', 'ET2', @elt_prog, 'active'),
('ELT207', 'PE1', @elt_prog, 'active'),
('ELT208', 'NSTP1', @elt_prog, 'active'),
('ELT209', 'ELT201', @elt_prog, 'active'),
('ELT210', 'ELT202L', @elt_prog, 'active'),
('ELT211', 'TECHDRAW2', @elt_prog, 'active'),
('ELT212', 'MATH2', @elt_prog, 'active'),
('ELT213', 'COMP1', @elt_prog, 'active'),
('ELT214', 'PD2', @elt_prog, 'active'),
('ELT215', 'ELECTIVE3', @elt_prog, 'active'),
('ELT216', 'PE2', @elt_prog, 'active'),
('ELT217', 'NSTP2', @elt_prog, 'active'),
('ELT218', 'SIL', @elt_prog, 'active'),
('ELT219', 'ELT301', @elt_prog, 'active'),
('ELT220', 'ET3', @elt_prog, 'active'),
('ELT221', 'ET4', @elt_prog, 'active'),
('ELT222', 'TECHDRAW3', @elt_prog, 'active'),
('ELT223', 'HUM1', @elt_prog, 'active'),
('ELT224', 'HIST1', @elt_prog, 'active'),
('ELT244', 'OJT', @elt_prog, 'active');

-- 8. POPULATE SUBJECTS & CURRICULUM - CIVIL TECHNOLOGY (41 Subjects)
INSERT IGNORE INTO subjects (subject_id, subject_name, units, course_type) VALUES
('CT1', 'Scaffolding Works NC II', 4, 'Major'),
('CT2', 'Tile Setting NC II', 4, 'Major'),
('ENTREP2', 'The Entrepreneurial Mind', 3, 'Major'),
('CT3', 'Masonry NC II', 4, 'Major'),
('CT4', 'Carpentry NC II', 4, 'Major'),
('CT5', 'EIM NC', 4, 'Major'),
('CT6', 'Plumbing NC II', 4, 'Major'),
('FIL4', 'Sosyedad at Literatura', 3, 'Minor'),
('DRR', 'Disaster Readiness', 3, 'Minor'),
('PD3', 'Social Graces', 3, 'Minor'),
('CT7', 'Automotive Servicing NC I', 4, 'Major'),
('MGT1', 'Operational Management', 3, 'Major'),
('PSYCHO1', 'Industrial Psychology', 3, 'Minor'),
('HRM1', 'Human Resource Mgt', 3, 'Major'),
('CT10', 'Driving NC II', 4, 'Major'),
('CT12', 'SMAW NC I', 4, 'Major');

-- CVT Curriculum Assignment
INSERT INTO curriculum (class_code, subject_id, program_id, status) VALUES
('CVT301', 'TECHDRAW1', @cvt_prog, 'active'),
('CVT302', 'PD1', @cvt_prog, 'active'),
('CVT303', 'FIL2', @cvt_prog, 'active'),
('CVT304', 'MATH1', @cvt_prog, 'active'),
('CVT305', 'PE1', @cvt_prog, 'active'),
('CVT306', 'CT1', @cvt_prog, 'active'),
('CVT307', 'CT2', @cvt_prog, 'active'),
('CVT308', 'NSTP1', @cvt_prog, 'active'),
('CVT309', 'ENTREP2', @cvt_prog, 'active'),
('CVT310', 'TECHDRAW2', @cvt_prog, 'active'),
('CVT311', 'ELECTIVE3', @cvt_prog, 'active'),
('CVT312', 'HUM1', @cvt_prog, 'active'),
('CVT313', 'PE2', @cvt_prog, 'active'),
('CVT314', 'CT3', @cvt_prog, 'active'),
('CVT315', 'CT4', @cvt_prog, 'active'),
('CVT316', 'NSTP2', @cvt_prog, 'active'),
('CVT317', 'SIL', @cvt_prog, 'active'),
('CVT318', 'TECHDRAW3', @cvt_prog, 'active'),
('CVT319', 'HIST1', @cvt_prog, 'active'),
('CVT320', 'MSP1', @cvt_prog, 'active'),
('CVT321', 'PD2', @cvt_prog, 'active'),
('CVT322', 'MATH2', @cvt_prog, 'active'),
('CVT323', 'PE3', @cvt_prog, 'active'),
('CVT324', 'PHYSICS1', @cvt_prog, 'active'),
('CVT325', 'CT5', @cvt_prog, 'active'),
('CVT326', 'CT6', @cvt_prog, 'active'),
('CVT327', 'CAD', @cvt_prog, 'active'),
('CVT328', 'COMM1', @cvt_prog, 'active'),
('CVT329', 'FIL4', @cvt_prog, 'active'),
('CVT330', 'COMP1', @cvt_prog, 'active'),
('CVT331', 'DRR', @cvt_prog, 'active'),
('CVT332', 'PD3', @cvt_prog, 'active'),
('CVT333', 'CT7', @cvt_prog, 'active'),
('CVT334', 'ETHICS', @cvt_prog, 'active'),
('CVT335', 'MGT1', @cvt_prog, 'active'),
('CVT336', 'PSYCHO1', @cvt_prog, 'active'),
('CVT337', 'HRM1', @cvt_prog, 'active'),
('CVT338', 'NATSCI', @cvt_prog, 'active'),
('CVT339', 'CT10', @cvt_prog, 'active'),
('CVT340', 'CT12', @cvt_prog, 'active'),
('CVT341', 'OJT', @cvt_prog, 'active');
