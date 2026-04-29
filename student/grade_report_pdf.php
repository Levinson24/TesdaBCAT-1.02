<?php
/**
 * Student Grade Report PDF Download
 * Generates a nicely formatted PDF of the student's grade report
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('student', '../index.php');

$studentUserId = getCurrentUserId();
$conn = getDBConnection();

// Get student profile
$stmt = $conn->prepare("
    SELECT s.*, p.program_name, d.title_diploma_program as dept_name,
           u.email
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    LEFT JOIN programs p ON s.program_id = p.program_id
    LEFT JOIN departments d ON s.dept_id = d.dept_id
    WHERE s.user_id = ?
");
$stmt->bind_param("i", $studentUserId);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    die("Student profile not found.");
}

$studentId = $student['student_id'];

// Get all approved grades, grouped by semester/year
$gradesStmt = $conn->prepare("
    SELECT subj.subject_id as course_code, subj.subject_name as course_name, subj.units,
           cs.semester, cs.school_year,
           g.grade, g.remarks,
           CONCAT(i.last_name, ', ', i.first_name) AS instructor_name
    FROM grades g
    JOIN enrollments e ON g.enrollment_id = e.enrollment_id
    JOIN class_sections cs ON e.section_id = cs.section_id
    JOIN curriculum cur ON cs.curriculum_id = cur.curriculum_id
    JOIN subjects subj ON cur.subject_id = subj.subject_id
    JOIN instructors i ON cs.instructor_id = i.instructor_id
    WHERE g.student_id = ? AND g.status = 'approved'
    ORDER BY cs.school_year, cs.semester, subj.subject_id
");
$gradesStmt->bind_param("i", $studentId);
$gradesStmt->execute();
$gradesResult = $gradesStmt->get_result();

$gradesBySemester = [];
while ($row = $gradesResult->fetch_assoc()) {
    $key = $row['school_year'] . ' — ' . $row['semester'] . ' Semester';
    $gradesBySemester[$key][] = $row;
}
$gradesStmt->close();

$schoolName    = getSetting('school_name', 'BALICUATRO COLLEGE OF ARTS AND TRADES');
$schoolAddress = getSetting('school_address', 'Allen, Northern Samar');
$studentName   = trim("{$student['last_name']}, {$student['first_name']} " . ($student['middle_name'] ?? ''));
$generatedDate = date('F d, Y');

// Log
logAudit($studentUserId, 'DOWNLOAD_GRADE_REPORT', 'students', $studentId, null, 'Student downloaded personal grade report PDF');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Grade Report - <?php echo htmlspecialchars($studentName); ?> - TESDA-BCAT GMS</title>
    <link rel="icon" href="../BCAT logo 2024.png" type="image/png">
    <style>
        @page { size: A4; margin: 10mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', Arial, sans-serif; font-size: 9pt; color: #1e293b; line-height: 1.4; }

        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #0d6efd;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .header-logo { height: 70px; }
        .header-text { text-align: center; flex: 1; }
        .header-text h6 { font-size: 0.75rem; margin-bottom: 2px; color: #64748b; font-weight: normal; text-transform: uppercase; }
        .header-text h4 { font-size: 1rem; margin-bottom: 2px; color: #0f172a; font-weight: 800; }
        
        .doc-title { 
            text-align: center; 
            margin: 15px 0; 
            font-size: 1.1rem; 
            font-weight: 800; 
            color: #1e293b; 
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .info-card { 
            background: #f8fafc; 
            border: 1px solid #e2e8f0; 
            border-radius: 6px; 
            padding: 12px; 
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 8px 20px;
        }
        .info-item { display: flex; font-size: 0.85rem; }
        .info-label { width: 100px; color: #64748b; font-weight: 600; }
        .info-value { font-weight: 700; color: #1e293b; border-bottom: 1px dotted #cbd5e0; flex: 1; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        thead th { background: #2d3748; color: white; padding: 8px 6px; text-align: left; font-size: 0.7rem; text-transform: uppercase; }
        tbody td { padding: 6px 6px; border-bottom: 1px solid #e2e8f0; font-size: 0.8rem; }
        tbody tr:nth-child(even) { background: #fdfdfd; }

        .sem-header { 
            background: #f1f5f9; 
            color: #0d6efd; 
            font-weight: 800; 
            font-size: 0.85rem;
            padding: 8px 12px; 
            margin: 20px 0 5px; 
            border-left: 4px solid #0d6efd;
            text-transform: uppercase;
        }
        
        .sem-summary { 
            text-align: right; 
            font-size: 0.8rem; 
            color: #475569; 
            padding: 5px; 
            background: #f8fafc;
            border-radius: 0 0 4px 4px;
        }

        .passed { color: #059669; font-weight: 700; }
        .failed { color: #dc2626; font-weight: 700; }
        .inc    { color: #d97706; font-weight: 700; }

        .footer { 
            margin-top: 30px; 
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
            font-size: 0.7rem; 
            color: #94a3b8; 
            display: flex; 
            justify-content: space-between; 
            font-style: italic;
        }

        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="header-container">
        <img src="../BCAT logo 2024.png" class="header-logo" alt="BCAT">
        <div class="header-text">
            <h6>Republic of the Philippines</h6>
            <h4><?php echo htmlspecialchars($schoolName); ?></h4>
            <h6><?php echo htmlspecialchars($schoolAddress); ?> | GMS Official Report</h6>
        </div>
        <img src="../tesda_logo.png" class="header-logo" alt="TESDA">
    </div>

    <div class="doc-title">Student Grade Report</div>

    <div class="info-card">
        <div class="info-item"><span class="info-label">Student Name:</span> <span class="info-value"><?php echo htmlspecialchars($studentName); ?></span></div>
        <div class="info-item"><span class="info-label">Student No:</span>  <span class="info-value"><?php echo htmlspecialchars($student['student_no']); ?></span></div>
        <div class="info-item"><span class="info-label">Program:</span>     <span class="info-value"><?php echo htmlspecialchars($student['program_name'] ?? '—'); ?></span></div>
        <div class="info-item"><span class="info-label">Year Level:</span>  <span class="info-value"><?php echo $student['year_level']; ?></span></div>
    </div>

    <?php if (empty($gradesBySemester)): ?>
        <p style="color:#666;text-align:center;padding:20px;">No approved grades found.</p>
    <?php else: ?>
        <?php foreach ($gradesBySemester as $semLabel => $grades):
            $totalUnits = 0; $earnedUnits = 0;
        ?>
        <div class="sem-header"><?php echo htmlspecialchars($semLabel); ?></div>
        <table>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Subject</th>
                    <th style="text-align:center;">Units</th>
                    <!-- Removed Midterm/Final -->
                    <th style="text-align:center;">Grade</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($grades as $g):
                    $totalUnits += $g['units'];
                    $remarkClass = '';
                    if ($g['remarks'] === 'Passed') { $remarkClass = 'passed'; $earnedUnits += $g['units']; }
                    elseif ($g['remarks'] === 'Failed') $remarkClass = 'failed';
                    elseif (in_array($g['remarks'], ['INC', 'Incomplete'])) $remarkClass = 'inc';
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($g['course_code']); ?></td>
                    <td><?php echo htmlspecialchars($g['course_name']); ?></td>
                    <td style="text-align:center;"><?php echo $g['units']; ?></td>
                    <!-- Removed Midterm/Final -->
                    <td style="text-align:center;font-weight:bold;"><?php echo $g['grade'] ?? '—'; ?></td>
                    <td class="<?php echo $remarkClass; ?>"><?php echo htmlspecialchars($g['remarks'] ?? '—'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="sem-summary">
            Total Units Enrolled: <strong><?php echo $totalUnits; ?></strong> &nbsp;|&nbsp;
            Units Earned: <strong><?php echo $earnedUnits; ?></strong>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="footer">
        <span><em>This is a computer-generated document. No signature required.</em></span>
        <span>Printed: <?php echo $generatedDate; ?> &nbsp;|&nbsp; <?php echo htmlspecialchars($schoolName); ?></span>
    </div>

    <script>window.onload = function() { window.print(); };</script>
</body>
</html>
