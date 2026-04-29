<?php
/**
 * Official Curriculum Evaluation Model Print View
 * TESDA-BCAT Grade Management System
 */
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole(['registrar', 'registrar_staff', 'admin', 'dept_head']);
$conn = getDBConnection();

$studentId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($studentId <= 0) {
    redirectWithMessage('../registrar/students.php', 'Student ID required.', 'danger');
}

// Fetch student info with program and department details
$student_stmt = $conn->prepare("
    SELECT s.*, p.program_name, d.title_diploma_program as dept_name 
    FROM students s
    LEFT JOIN programs p ON s.program_id = p.program_id
    LEFT JOIN departments d ON s.dept_id = d.dept_id
    WHERE s.student_id = ?
");
$student_stmt->bind_param("i", $studentId);
$student_stmt->execute();
$student = $student_stmt->get_result()->fetch_assoc();

if (!$student) {
    redirectWithMessage('../registrar/students.php', 'Student not found.', 'danger');
}

// Fetch grades/curriculum records
// Note: In a real scenario, we might want to fetch the entire curriculum for the program 
// and join with grades. For now, we fetch all subjects the student has grades for.
$grades_stmt = $conn->prepare("
    SELECT g.*, c.course_code, c.course_name, c.pre_requisites, c.lec_hrs, c.lab_hrs, c.lec_units, c.lab_units, c.units as total_units,
           cs.section_name, cs.semester,
           CASE 
               WHEN cs.semester = '1st' THEN 1
               WHEN cs.semester = '2nd' THEN 2
               ELSE 3
           END as sem_num,
           -- We'll assume year level can be derived from the student if not in class_sections
           -- But typically curriculum models have year/sem. We'll use the course's year/sem if available
           -- or fallback to a default.
           1 as year_level 
    FROM enrollments e
    LEFT JOIN grades g ON e.enrollment_id = g.enrollment_id
    JOIN class_sections cs ON e.section_id = cs.section_id
    JOIN curriculum cur ON cs.curriculum_id = cur.curriculum_id
    JOIN subjects c ON cur.subject_id = c.subject_id
    WHERE e.student_id = ?
    ORDER BY year_level ASC, sem_num ASC, c.course_code ASC
");
$grades_stmt->bind_param("i", $studentId);
$grades_stmt->execute();
$grades_res = $grades_stmt->get_result();

$curriculum_data = [];
while ($row = $grades_res->fetch_assoc()) {
    $year = intval($row['year_level']);
    $sem = intval($row['sem_num']);
    $curriculum_data[$year][$sem][] = $row;
}

$schoolName = getSetting('school_name', 'BALICUATRO COLLEGE OF ARTS AND TRADES');
$schoolRegion = getSetting('school_region', 'Region VIII');
$schoolAddress = getSetting('school_address', 'Allen, Northern Samar');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Curriculum Evaluation - <?php echo htmlspecialchars($student['last_name']); ?> - TESDA-BCAT GMS</title>
    <link rel="icon" href="../BCAT logo 2024.png" type="image/png">
    <!-- Import Premium Typography -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --navy-blue: #0038A8;
            --accent-navy: #002366;
        }
        @page {
            size: A4;
            margin: 0.5in;
        }
        body {
            font-family: 'Outfit', system-ui, -apple-system, sans-serif !important;
            font-size: 10pt;
            line-height: 1.4;
            color: #1e293b;
            margin: 0;
            padding: 0;
            background: #f8fafc;
        }
        .container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
        }
        .header {
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            margin-bottom: 25px;
            border-bottom: 3px solid var(--navy-blue);
            padding-bottom: 20px;
        }
        .header h1 {
            font-size: 20pt;
            margin: 0;
            color: var(--navy-blue);
            text-transform: uppercase;
            font-weight: 800;
        }
        .header h2 {
            font-size: 12pt;
            margin: 5px 0;
            font-weight: normal;
        }
        .header .agency {
            font-size: 10pt;
            font-weight: bold;
            color: #555;
        }
        .student-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 20px;
            padding: 10px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .info-item {
            margin-bottom: 5px;
        }
        .info-label {
            font-weight: 700;
            color: var(--navy-blue);
            width: 140px;
            display: inline-block;
        }
        .curriculum-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
            table-layout: fixed;
        }
        .curriculum-table th, .curriculum-table td {
            border: 1px solid #444;
            padding: 4px 6px;
            text-align: center;
            font-size: 8.5pt;
            word-wrap: break-word;
        }
        .curriculum-table th {
            background-color: var(--navy-blue);
            color: white;
            font-weight: 700;
            text-transform: uppercase;
        }
        .year-sem-header {
            background-color: #e9ecef !important;
            font-weight: bold !important;
            text-align: left !important;
            color: #000 !important;
            font-size: 10pt !important;
        }
        .col-code { width: 80px; }
        .col-desc { width: auto; text-align: left !important; }
        .col-prereq { width: 90px; }
        .col-hrs { width: 45px; }
        .col-units { width: 45px; }
        .col-grade { width: 50px; font-weight: bold; }
        .col-remarks { width: 70px; }

        .footer {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
        }
        .signature-block {
            width: 250px;
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #000;
            margin-top: 50px;
            padding-top: 5px;
            font-weight: bold;
        }
        .signature-title {
            font-size: 9pt;
            color: #666;
        }
        
        .print-btn-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
        }
        .btn-print {
            background-color: #1a4d8c;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 11pt;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        @media print {
            .print-btn-container { display: none; }
            body { 
                margin: 0; 
                padding: 0; 
                font-size: 8pt; /* Reduced font size for print */
                line-height: 1.1; 
            }
            .container {
                max-width: 100%;
                margin: 0 !important;
                padding: 0 !important;
            }
            .header {
                margin-bottom: 10px;
                padding-bottom: 5px;
            }
            .header h1 { font-size: 13pt; }
            .header h2 { font-size: 10pt; margin: 2px 0; }
            .dept-title { font-size: 10pt !important; margin-top: 5px !important; }
            .student-info { margin-bottom: 10px; padding: 4px; }
            
            /* Aggressively shrink table padding for print */
            .curriculum-table th, .curriculum-table td {
                padding: 3px;
                font-size: 8pt;
                line-height: 1.1;
            }
            .year-sem-header { font-size: 8pt !important; padding: 3px !important; }
            
            /* Prevent awkward page breaks */
            .curriculum-table { page-break-inside: auto; margin-bottom: 15px; }
            tr { page-break-inside: avoid; page-break-after: auto; }
            thead { display: table-header-group; }
            tfoot { display: table-footer-group; }
            
            .footer { margin-top: 20px; page-break-inside: avoid; }
            .signature-line { margin-top: 35px; }
            
            @page {
                size: A4;
                margin: 0.5in 0.75in; /* 0.5in top/bottom, 0.75in sides to prevent 2nd page spill */
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="../BCAT logo 2024.png" alt="Logo" style="max-height: 80px; margin-right: 20px;">
            <div style="flex-grow: 1;">
                <div class="agency" style="font-size: 9pt; text-transform: uppercase;">Republic of the Philippines</div>
                <div class="agency" style="font-size: 10pt; font-weight: bold; color: #555;">TECHNICAL EDUCATION AND SKILLS DEVELOPMENT AUTHORITY</div>
                <h1 style="font-size: 18pt; margin: 5px 0; color: #1a4d8c; font-weight: 800;"><?php echo htmlspecialchars($schoolName); ?></h1>
                <div class="school-info" style="font-size: 9pt; color: #555; font-weight: 600;">
                    <?php echo htmlspecialchars($schoolAddress); ?> | <?php echo htmlspecialchars($schoolRegion); ?>
                </div>
            </div>
            <img src="../tesda_logo.png" alt="TESDA" style="max-height: 80px; margin-left: 20px;">
        </div>

        <div class="text-center mb-4" style="text-align: center; margin-bottom: 25px;">
            <h2 style="font-size: 16pt; margin: 10px 0; font-weight: 800; letter-spacing: 1.5px; color: #1e293b;">OFFICIAL CURRICULUM EVALUATION RECORD</h2>
            <div class="dept-title" style="font-weight: 800; font-size: 14pt; color: var(--navy-blue); text-transform: uppercase;">
                <?php echo htmlspecialchars($student['dept_name'] ?? 'DIPLOMA PROGRAM'); ?>
            </div>
        </div>

        <div class="student-info">
            <div class="left-col">
                <div class="info-item"><span class="info-label">Student Name:</span> <?php echo htmlspecialchars($student['first_name'] . ' ' . ($student['middle_name'] ? $student['middle_name'][0] . '. ' : '') . $student['last_name']); ?></div>
                <div class="info-item"><span class="info-label">Student No.:</span> <?php echo htmlspecialchars($student['student_no']); ?></div>
                <div class="info-item"><span class="info-label">Program:</span> <?php echo htmlspecialchars($student['program_name'] ?? 'N/A'); ?></div>
            </div>
            <div class="right-col">
                <div class="info-item"><span class="info-label">Elementary:</span> <?php echo htmlspecialchars($student['elem_school'] ?? 'N/A'); ?> (<?php echo htmlspecialchars($student['elem_year'] ?? ''); ?>)</div>
                <div class="info-item"><span class="info-label">Secondary:</span> <?php echo htmlspecialchars($student['secondary_school'] ?? 'N/A'); ?> (<?php echo htmlspecialchars($student['secondary_year'] ?? ''); ?>)</div>
                <div class="info-item"><span class="info-label">Date Evaluated:</span> <?php echo date('F d, Y'); ?></div>
            </div>
        </div>

        <table class="curriculum-table">
            <thead>
                <tr>
                    <th rowspan="2" class="col-code">Code</th>
                    <th rowspan="2" class="col-desc">Subject Description</th>
                    <th rowspan="2" class="col-prereq">Pre-requisite</th>
                    <th colspan="3" class="col-hrs-group">Hours</th>
                    <th colspan="3" class="col-units-group">Units</th>
                    <th rowspan="2" class="col-grade">Grade</th>
                    <th rowspan="2" class="col-remarks">Remarks</th>
                </tr>
                <tr>
                    <th class="col-hrs">Lec</th>
                    <th class="col-hrs">Lab</th>
                    <th class="col-hrs">Total</th>
                    <th class="col-units">Lec</th>
                    <th class="col-units">Lab</th>
                    <th class="col-units">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($curriculum_data)): ?>
                    <tr><td colspan="11" style="text-align: center; padding: 20px;">No records found for this student.</td></tr>
                <?php
else: ?>
                    <?php foreach ($curriculum_data as $year => $semesters): ?>
                        <?php foreach ($semesters as $sem => $subjects): ?>
                            <tr>
                                <td colspan="11" class="year-sem-header">
                                    <?php
            $ordinal = ['1st', '2nd', '3rd', '4th'];
            echo($ordinal[$year - 1] ?? $year . 'th') . ' Year - ' . ($ordinal[$sem - 1] ?? $sem . 'th') . ' Semester';
?>
                                </td>
                            </tr>
                            <?php
            $sem_lec_hrs = 0;
            $sem_lab_hrs = 0;
            $sem_total_hrs = 0;
            $sem_lec_units = 0;
            $sem_lab_units = 0;
            $sem_total_units = 0;
?>
                            <?php foreach ($subjects as $s): ?>
                                <?php
                $l_hrs = $s['lec_hrs'] > 0 ? $s['lec_hrs'] : $s['total_units'];
                $row_total_hrs = $l_hrs + $s['lab_hrs'];
                $l_units = $s['lec_units'] > 0 ? $s['lec_units'] : $s['total_units'];
                $row_total_units = $l_units + $s['lab_units'];

                $sem_lec_hrs += $s['lec_hrs'];
                $sem_lab_hrs += $s['lab_hrs'];
                $sem_total_hrs += $row_total_hrs;

                $sem_lec_units += $s['lec_units'];
                $sem_lab_units += $s['lab_units'];
                $sem_total_units += $row_total_units;
?>
                                <tr>
                                    <td><?php echo htmlspecialchars($s['course_code'] ?? ''); ?></td>
                                    <td class="col-desc"><?php echo htmlspecialchars($s['course_name'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($s['pre_requisites'] ?? 'None'); ?></td>
                                    <td><?php echo number_format($s['lec_hrs'] ?? 0, 1); ?></td>
                                    <td><?php echo number_format($s['lab_hrs'] ?? 0, 1); ?></td>
                                    <td style="background-color: #fcfcfc;"><?php echo number_format($row_total_hrs ?? 0, 1); ?></td>
                                    <td><?php echo number_format($s['lec_units'] ?? 0, 1); ?></td>
                                    <td><?php echo number_format($s['lab_units'] ?? 0, 1); ?></td>
                                    <td style="font-weight: bold;"><?php echo number_format($row_total_units ?? 0, 1); ?></td>
                                    <td class="col-grade"><?php echo $s['grade'] !== null ? number_format($s['grade'], 2) : '---'; ?></td>
                                    <td class="col-remarks"><?php echo htmlspecialchars($s['remarks'] ?? '---'); ?></td>
                                </tr>
                            <?php
            endforeach; ?>
                            <tr style="background-color: #f8f9fa; font-weight: bold;">
                                <td colspan="3" style="text-align: right;">Semester Totals:</td>
                                <td><?php echo number_format($sem_lec_hrs ?? 0, 1); ?></td>
                                <td><?php echo number_format($sem_lab_hrs ?? 0, 1); ?></td>
                                <td><?php echo number_format($sem_total_hrs ?? 0, 1); ?></td>
                                <td><?php echo number_format($sem_lec_units ?? 0, 1); ?></td>
                                <td><?php echo number_format($sem_lab_units ?? 0, 1); ?></td>
                                <td><?php echo number_format($sem_total_units ?? 0, 1); ?></td>
                                <td colspan="2"></td>
                            </tr>
                        <?php
        endforeach; ?>
                    <?php
    endforeach; ?>
                <?php
endif; ?>
            </tbody>
        </table>

        <div class="footer">
            <div class="signature-block">
                <div class="signature-line">
                    <?php
$registrar_name = getSetting('registrar_name', 'MS/MR. REGISTRAR NAME');
echo htmlspecialchars($registrar_name);
?>
                </div>
                <div class="signature-title">Registrar</div>
            </div>
            <div class="signature-block">
                <div class="signature-line">
                    <?php
$evaluator = $student['honor_evaluated_by_name'] ?? 'EVALUATOR NAME';
echo htmlspecialchars($evaluator);
?>
                </div>
                <div class="signature-title">Curriculum Evaluator</div>
            </div>
        </div>
    </div>

    <div class="print-btn-container">
        <button class="btn-print" onclick="window.print()"><i class="fas fa-print"></i> Print Evaluation</button>
    </div>
</body>
</html>
