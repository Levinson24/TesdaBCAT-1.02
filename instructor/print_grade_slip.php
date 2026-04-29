<?php
/**
 * Instructor - Print Student Grade Slip
 * TESDA-BCAT Grade Management System
 */
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('instructor');

$conn = getDBConnection();

$studentId = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
// We require the section ID so we know WHICH semester and school year context we are printing for
$sectionId = isset($_GET['section_id']) ? intval($_GET['section_id']) : 0;

if ($studentId <= 0 || $sectionId <= 0) {
    redirectWithMessage('dashboard.php', 'Student ID and Section ID must be provided.', 'danger');
}

$user = getUserProfile(getCurrentUserId(), getCurrentUserRole());

// Fetch the Registrar's name
$regStmt = $conn->prepare("SELECT username FROM users WHERE role = 'registrar' LIMIT 1");
$regStmt->execute();
$regUser = $regStmt->get_result()->fetch_assoc();
$registrarName = $regUser ? $regUser['username'] : '';
$regStmt->close();

// 1. Get the context (Semester, School Year) from the selected section
$contextStmt = $conn->prepare("SELECT semester, school_year FROM class_sections WHERE section_id = ?");
$contextStmt->bind_param("i", $sectionId);
$contextStmt->execute();
$context = $contextStmt->get_result()->fetch_assoc();
$contextStmt->close();

if (!$context) {
    redirectWithMessage('dashboard.php', 'Context section not found.', 'danger');
}
$targetSemester = $context['semester'];
$targetSY = $context['school_year'];

// 2. Fetch the Student's Info
$studentStmt = $conn->prepare("
    SELECT s.*, p.program_name, d.title_diploma_program as dept_name 
    FROM students s
    LEFT JOIN programs p ON s.program_id = p.program_id
    LEFT JOIN departments d ON s.dept_id = d.dept_id
    WHERE s.student_id = ?
");
$studentStmt->bind_param("i", $studentId);
$studentStmt->execute();
$student = $studentStmt->get_result()->fetch_assoc();
$studentStmt->close();

if (!$student) {
    redirectWithMessage('dashboard.php', 'Student not found.', 'danger');
}

// 3. Fetch all enrollments & grades for this student in the TARGET SEMESTER & SY
$gradesStmt = $conn->prepare("
    SELECT g.*, 
           c.course_code, c.course_name, c.pre_requisites, 
           c.lec_hrs, c.lab_hrs, c.lec_units, c.lab_units, c.units as total_units,
           cs.actual_lec_hrs, cs.actual_lab_hrs, cs.actual_lec_units, cs.actual_lab_units
    FROM enrollments e
    JOIN class_sections cs ON e.section_id = cs.section_id
    JOIN curriculum cur ON cs.curriculum_id = cur.curriculum_id
    JOIN subjects c ON cur.subject_id = c.subject_id
    LEFT JOIN grades g ON e.enrollment_id = g.enrollment_id
    WHERE e.student_id = ? AND cs.school_year = ? AND cs.semester = ? AND e.status = 'enrolled'
    ORDER BY c.course_code ASC
");
$gradesStmt->bind_param("iss", $studentId, $targetSY, $targetSemester);
$gradesStmt->execute();
$gradesRes = $gradesStmt->get_result();

$courses = [];
$totalLecHrs = 0;
$totalLecUnits = 0;
$totalLabHrs = 0;
$totalLabUnits = 0;
$totalTotalHrs = 0;
$totalUnitsEarned = 0;
$totalWeightedGrades = 0;
$totalUnitsForGWA = 0;

while ($row = $gradesRes->fetch_assoc()) {
    $courses[] = $row;

    // Sum hours and units (Regardless of passed/failed for hours, but units earned requires a passing grade technically, 
    // though the provided Excel sheet simply sums the units mapped to the course).
    // To match the sheet exactly, we sum the course definitions or their overrides for hours, but units are fixed to course.
    $lec_hrs = isset($row['actual_lec_hrs']) ? floatval($row['actual_lec_hrs']) : floatval($row['lec_hrs']);
    $lec_units = floatval($row['lec_units']);
    $lab_hrs = isset($row['actual_lab_hrs']) ? floatval($row['actual_lab_hrs']) : floatval($row['lab_hrs']);
    $lab_units = floatval($row['lab_units']);

    // Usually Total Hours = Lec Hrs + Lab Hrs
    $rowTotalHrs = $lec_hrs + $lab_hrs;
    // Units Earned = Lec Units + Lab Units (or total_units)
    $rowTotalUnits = $lec_units + $lab_units > 0 ? ($lec_units + $lab_units) : floatval($row['total_units']);

    $totalLecHrs += $lec_hrs;
    $totalLecUnits += $lec_units;
    $totalLabHrs += $lab_hrs;
    $totalLabUnits += $lab_units;
    $totalTotalHrs += $rowTotalHrs;
    $totalUnitsEarned += $rowTotalUnits;

    if (isset($row['grade']) && is_numeric($row['grade']) && $row['grade'] > 0) {
        $totalWeightedGrades += floatval($row['grade']) * $rowTotalUnits;
        $totalUnitsForGWA += $rowTotalUnits;
    }
}

$gwa = $totalUnitsForGWA > 0 ? ($totalWeightedGrades / $totalUnitsForGWA) : null;

// Helper to determine year level string
$yearLevelStr = "First Year";
if ($student['year_level'] == 2) {
    $yearLevelStr = "Second Year";
}
elseif ($student['year_level'] == 3) {
    $yearLevelStr = "Third Year";
}
elseif ($student['year_level'] == 4) {
    $yearLevelStr = "Fourth Year";
}

$semStr = strtoupper($targetSemester) . " SEMESTER";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Grade Slip - <?php echo htmlspecialchars($student['last_name']); ?> - TESDA-BCAT GMS</title>
    <link rel="icon" href="../BCAT logo 2024.png" type="image/png">
    <style>
        @page {
            size: landscape; /* Excel sheets with many columns are often landscape */
            margin: 0.5in;
        }
        body {
            font-family: 'Calibri', 'Segoe UI', Tahoma, Arial, sans-serif;
            font-size: 11pt;
            color: #000;
            margin: 0;
            padding: 20px;
        }
        .header-info {
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 12pt;
        }
        .excel-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .excel-table th, .excel-table td {
            border: 1px solid #000;
            padding: 4px;
            vertical-align: middle;
            text-align: center;
        }
        /* Color Mapping matching the User Request Image */
        .bg-green {
            background-color: #A9D08E !important; /* Soft green */
            font-weight: bold;
        }
        .bg-yellow {
            background-color: #FFD966 !important; /* Soft yellow */
            font-weight: bold;
        }
        .bg-blue {
            background-color: #5B9BD5 !important; /* Soft blue */
            font-weight: bold;
            color: #000;
        }
        .bg-orange {
            background-color: #F8CBAD !important; /* Soft orange/peach for Final Grade if needed, or stick to Yellow */
            font-weight: bold;
        }
        .bg-gold {
            background-color: #FFC000 !important;
            font-weight: bold;
        }
        .bg-gray {
            background-color: #D9D9D9 !important;
            font-weight: bold;
        }
        
        /* Specific Alignments */
        .text-left {
            text-align: left !important;
        }
        .code-cell {
            width: 120px;
        }
        .desc-cell {
            width: auto;
            text-align: left;
            padding-left: 8px !important;
        }
        .small-col {
            width: 60px;
        }
        
        /* New Official Header Styles */
        .report-header {
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #1a4d8c;
        }
        .report-header-text {
            text-align: center;
            flex-grow: 1;
            line-height: 1.2;
            color: #2c3e50;
        }
        
        .header-republic {
            font-size: 20pt;
            font-family: 'Times New Roman', Times, serif;
        }
        
        .header-tesda {
            font-size: 16pt;
            font-weight: bold;
            font-family: 'Times New Roman', Times, serif;
            letter-spacing: 0.5px;
        }
        
        .header-region {
            font-size: 20pt;
            font-family: 'Times New Roman', Times, serif;
            margin-bottom: 5px;
        }
        
        .header-school {
            font-weight: 900;
            font-family: 'Arial Black', Impact, sans-serif;
            font-size: 18pt;
            color: #1a4d8c;
            letter-spacing: 1px;
            text-transform: uppercase;
            text-shadow: 1px 1px 0px rgba(0,0,0,0.1);
        }
        
        .header-address {
            font-size: 18pt;
            font-family: 'Calibri', sans-serif;
            font-style: italic;
            color: #1a4d8c;
        }
        
        .report-header img {
            width: 95px;
            height: 95px;
            object-fit: contain;
        }
        
        /* New Student Info Layout */
        .program-title {
            text-align: center;
            font-weight: 900;
            font-size: 15pt;
            color: #1a4d8c;
            margin-bottom: 25px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .student-info-table {
            width: 100%;
            margin-bottom: 20px;
            font-size: 11pt;
            table-layout: fixed;
            border-collapse: collapse;
        }
        
        .student-info-table td {
            vertical-align: bottom;
            padding: 2px 0;
            border: none;
        }
        
        /* Row pairs */
        .info-row {
            display: flex;
            margin-bottom: 5px;
            align-items: flex-end;
        }
        
        .info-label {
            white-space: nowrap;
            margin-right: 5px;
        }
        
        .info-value {
            flex-grow: 1;
            border-bottom: 1px solid #000;
            min-height: 20px;
            padding-left: 5px;
            font-weight: bold;
        }
        
        .year-label {
            margin-left: 10px;
            white-space: nowrap;
            margin-right: 5px;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                padding: 0;
            }
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
            position: fixed;
            bottom: 20px;
            right: 20px;
        }
        .btn-print:hover {
            background-color: #123766;
        }
        /* --- Signatures --- */
        .signatures {
            width: 100%;
            margin-top: 40px;
            page-break-inside: avoid;
        }
        .signatures td {
            width: 50%;
            text-align: left;
            vertical-align: top;
            padding: 10px;
        }
        .sig-box {
            display: inline-block;
            text-align: center;
        }
        .sig-line {
            width: 250px;
            border-bottom: 1px solid #000;
            margin-bottom: 5px;
            font-size: 11pt;
            font-weight: bold;
            font-family: Arial, sans-serif;
            text-transform: uppercase;
        }
        .sig-title {
            font-size: 10pt;
            font-family: Arial, sans-serif;
            color: #333;
        }
    </style>
</head>
<body>

    <button class="btn-print no-print" onclick="window.print()">
        <svg style="width:16px;height:16px;vertical-align:middle;margin-right:5px;fill:currentColor;" viewBox="0 0 512 512">
            <path d="M128 0C92.7 0 64 28.7 64 64v96h64V64c0-8.8 7.2-16 16-16h226.5c4.2 0 8.3 1.7 11.3 4.7l43.5 43.5c3 3 4.7 7.1 4.7 11.3V160h64v-30.1c0-25.5-10.1-49.9-28.1-67.9L402.5 18.5C384.5.5 360.1 0 334.6 0H128zM512 256c0-35.3-28.7-64-64-64H64c-35.3 0-64 28.7-64 64v152c0 13.3 10.7 24 24 24h64v64c0 35.3 28.7 64 64 64h224c35.3 0 64-28.7 64-64v-64h64c13.3 0 24-10.7 24-24V256zM176 464c-8.8 0-16-7.2-16-16v-160h192v160c0 8.8-7.2 16-16 16H176z"/>
        </svg>
        Print Document
    </button>

    <div class="report-header">
        <img src="../tesda_logo.png" alt="TESDA Logo" onerror="this.style.display='none'">
        <div class="report-header-text">
            <div class="header-republic">Republic of the Philippines</div>
            <div class="header-tesda">TECHNICAL EDUCATION AND SKILLS DEVELOPMENT AUTHORITY</div>
            <div class="header-region">Region VIII</div>
            <div class="header-school">BALICUATRO COLLEGE OF ARTS AND TRADES</div>
            <div class="header-address">Allen, Northern Samar</div>
        </div>
        <img src="../BCAT logo 2024.png" alt="BCAT Logo" onerror="this.src='../bcat updated.png'; this.onerror=function(){this.style.display='none';};">
    </div>
    
    <div class="program-title">
        <?php echo htmlspecialchars($student['program_name'] ?? $student['dept_name']); ?>
    </div>
    
    <table class="student-info-table">
        <tr>
            <td style="width: 55%; padding-right: 20px;">
                <div class="info-row">
                    <span class="info-label">Name:</span>
                    <span class="info-value text-uppercase"><?php echo htmlspecialchars($student['last_name'] . ', ' . $student['first_name'] . ' ' . $student['middle_name']); ?></span>
                </div>
            </td>
            <td style="width: 45%;">
                <div class="info-row">
                    <span class="info-label">Address:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['address'] ?? ''); ?></span>
                </div>
            </td>
        </tr>
        <tr>
            <td style="padding-right: 20px;">
                <div class="info-row">
                    <span class="info-label">Elem. Educ.</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['elem_school'] ?? ''); ?></span>
                    <span class="year-label">Year:</span>
                    <span class="info-value" style="flex-grow: 0; min-width: 60px; padding-left:0; text-align:center;"><?php echo htmlspecialchars($student['elem_year'] ?? ''); ?></span>
                </div>
            </td>
            <td>
                <div class="info-row">
                    <span class="info-label">Municipality:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['municipality'] ?? ''); ?></span>
                </div>
            </td>
        </tr>
        <tr>
            <td style="padding-right: 20px;">
                <div class="info-row">
                    <span class="info-label">Secondary Educ.</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['secondary_school'] ?? ''); ?></span>
                    <span class="year-label">Year:</span>
                    <span class="info-value" style="flex-grow: 0; min-width: 60px; padding-left:0; text-align:center;"><?php echo htmlspecialchars($student['secondary_year'] ?? ''); ?></span>
                </div>
            </td>
            <td>
                <div class="info-row">
                    <span class="info-label">Term:</span>
                    <span class="info-value"><?php echo $yearLevelStr; ?> - <?php echo $semStr; ?> (SY <?php echo htmlspecialchars($targetSY); ?>)</span>
                </div>
            </td>
        </tr>
    </table>

    <table class="excel-table">
        <thead>
            <!-- Top Header Row -->
            <tr>
                <th rowspan="2" class="bg-green code-cell">CODE</th>
                <th rowspan="2" class="bg-green desc-cell">DESCRIPTIVE TITLE</th>
                <th rowspan="2" class="bg-green">PRE-<br>REQUISITE</th>
                <th colspan="2" class="bg-green">Lecture</th>
                <th colspan="2" class="bg-green">Laboratory/RL</th>
                <th rowspan="2" class="bg-green small-col">Total No.<br>of Hours</th>
                <th rowspan="2" class="bg-blue" style="color: black !important;">UNITS<br>EARNED</th>
                <th rowspan="2" class="bg-gold">FINAL GRADE</th>
            </tr>
            <!-- Sub Header Row -->
            <tr>
                <th class="bg-green small-col">Hours</th>
                <th class="bg-yellow small-col">Units</th>
                <th class="bg-green small-col">Hours</th>
                <th class="bg-yellow small-col">Units</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($courses as $c):
    $lec_hrs = isset($c['actual_lec_hrs']) ? floatval($c['actual_lec_hrs']) : floatval($c['lec_hrs']);
    $lec_units = floatval($c['lec_units']);
    $lab_hrs = isset($c['actual_lab_hrs']) ? floatval($c['actual_lab_hrs']) : floatval($c['lab_hrs']);
    $lab_units = floatval($c['lab_units']);
    $total_hrs = $lec_hrs + $lab_hrs;
    $units_earned = $lec_units + $lab_units > 0 ? ($lec_units + $lab_units) : floatval($c['total_units']);
?>
            <tr>
                <td class="text-left"><?php echo htmlspecialchars($c['course_code']); ?></td>
                <td class="desc-cell"><?php echo htmlspecialchars($c['course_name']); ?></td>
                <td><?php echo htmlspecialchars($c['pre_requisites'] ?? ''); ?></td>
                <td><?php echo $lec_hrs > 0 ? $lec_hrs : ''; ?></td>
                <td><?php echo $lec_units > 0 ? $lec_units : ''; ?></td>
                <td><?php echo $lab_hrs > 0 ? $lab_hrs : ''; ?></td>
                <td><?php echo $lab_units > 0 ? $lab_units : ''; ?></td>
                <td><?php echo $total_hrs > 0 ? $total_hrs : ''; ?></td>
                <td class="bg-gray"><?php echo $units_earned; ?></td>
                <!-- Show grade if it exists, otherwise leave blank -->
                <td style="font-weight: bold;">
                    <?php
    if (!empty($c['remarks']) && !is_numeric($c['grade'])) {
        echo htmlspecialchars($c['remarks']);
    }
    else {
        echo rtrim(rtrim(number_format($c['grade'], 2), '0'), '.') ?: '';
    }
?>
                </td>
            </tr>
            <?php
endforeach; ?>
            
            <!-- Totals Row -->
            <tr>
                <td colspan="3" class="bg-gray" style="text-align: right; padding-right: 15px;">TOTAL</td>
                <td class="bg-gray"><?php echo $totalLecHrs; ?></td>
                <td class="bg-gray"><?php echo $totalLecUnits; ?></td>
                <td class="bg-gray"><?php echo $totalLabHrs; ?></td>
                <td class="bg-gray"><?php echo $totalLabUnits; ?></td>
                <td class="bg-gray"><?php echo $totalTotalHrs; ?></td>
                <td class="bg-gray"><?php echo $totalUnitsEarned; ?></td>
                <td class="bg-gray" style="font-weight: bold;">
                    <?php echo $gwa !== null ? number_format($gwa, 2) : ''; ?>
                </td>
            </tr>
        </tbody>
    </table>

    <table class="signatures">
        <tr>
            <td>
                <div style="font-family: 'Times New Roman', Times, serif; font-size: 11pt; margin-bottom: 30px;">Prepared by:</div>
                <div class="sig-box">
                    <div class="sig-line"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                    <div class="sig-title">Instructor</div>
                </div>
            </td>
            <td>
                <div style="font-family: 'Times New Roman', Times, serif; font-size: 11pt; margin-bottom: 30px;">Noted by:</div>
                <div class="sig-box">
                    <div class="sig-line"><?php echo htmlspecialchars($registrarName); ?></div>
                    <div class="sig-title">Registrar</div>
                </div>
            </td>
        </tr>
    </table>

</body>
</html>
