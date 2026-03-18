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
    SELECT c.course_code, c.course_name, c.units,
           cs.semester, cs.school_year,
           g.midterm, g.final, g.grade, g.remarks,
           CONCAT(i.last_name, ', ', i.first_name) AS instructor_name
    FROM grades g
    JOIN enrollments e ON g.enrollment_id = e.enrollment_id
    JOIN class_sections cs ON e.section_id = cs.section_id
    JOIN courses c ON cs.course_id = c.course_id
    JOIN instructors i ON cs.instructor_id = i.instructor_id
    WHERE g.student_id = ? AND g.status = 'approved'
    ORDER BY cs.school_year, cs.semester, c.course_code
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
logAudit($studentUserId, 'GRADE_PDF_DOWNLOAD', 'students', $studentId, null, 'Student downloaded grade report PDF');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Grade Report — <?php echo htmlspecialchars($studentName); ?></title>
    <style>
        @page { size: A4; margin: 15mm 12mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 9.5pt; color: #111; }

        .header-band { background: #1a3a5c; color: white; padding: 10px 16px; display: flex;
                        align-items: center; gap: 14px; border-radius: 4px 4px 0 0; }
        .header-band img { width: 46px; height: 46px; border-radius: 50%; background: white; padding: 2px; }
        .header-band .school-name { font-size: 11pt; font-weight: bold; line-height: 1.3; }
        .header-band .school-sub  { font-size: 7.5pt; opacity: 0.85; }

        .doc-title { text-align: center; margin: 8px 0; font-size: 11pt; font-weight: bold;
                     letter-spacing: 0.05em; text-transform: uppercase; color: #1a3a5c; border-bottom: 2px solid #1a3a5c; padding-bottom: 5px; }

        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 24px; margin: 8px 0 10px; font-size: 8.5pt; }
        .info-grid .label { color: #555; font-weight: bold; }

        table { width: 100%; border-collapse: collapse; font-size: 8.5pt; margin-bottom: 4px; }
        thead tr { background: #1a3a5c; color: white; }
        thead th { padding: 5px 6px; text-align: left; font-weight: 600; }
        tbody tr:nth-child(even) { background: #f0f4f8; }
        tbody td { padding: 4px 6px; border-bottom: 1px solid #e2e8f0; }

        .sem-header { background: #e8f0fb; color: #1a3a5c; font-weight: 700; font-size: 8pt;
                      padding: 4px 6px; margin: 8px 0 2px; border-left: 3px solid #1a3a5c; }
        .sem-summary { text-align: right; font-size: 8pt; color: #555; padding: 2px 4px; }

        .passed { color: #16a34a; font-weight: 600; }
        .failed { color: #dc2626; font-weight: 600; }
        .inc    { color: #d97706; font-weight: 600; }

        .footer { margin-top: 14px; border-top: 1px solid #ccc; padding-top: 8px;
                  font-size: 7.5pt; color: #666; display: flex; justify-content: space-between; }

        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="header-band">
        <img src="../tesda_logo.png" alt="TESDA" onerror="this.style.display='none'">
        <div>
            <div class="school-name"><?php echo htmlspecialchars($schoolName); ?></div>
            <div class="school-sub"><?php echo htmlspecialchars($schoolAddress); ?> &nbsp;|&nbsp; Grade Management System</div>
        </div>
    </div>

    <div class="doc-title">Official Grade Report</div>

    <div class="info-grid">
        <div><span class="label">Student Name:</span> <?php echo htmlspecialchars($studentName); ?></div>
        <div><span class="label">Student No.:</span>  <?php echo htmlspecialchars($student['student_no']); ?></div>
        <div><span class="label">Program:</span>      <?php echo htmlspecialchars($student['program_name'] ?? '—'); ?></div>
        <div><span class="label">Department:</span>   <?php echo htmlspecialchars($student['dept_name'] ?? '—'); ?></div>
        <div><span class="label">Year Level:</span>   <?php echo $student['year_level']; ?></div>
        <div><span class="label">Date Generated:</span> <?php echo $generatedDate; ?></div>
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
                    <th style="text-align:center;">Midterm</th>
                    <th style="text-align:center;">Final</th>
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
                    <td style="text-align:center;"><?php echo $g['midterm'] ?? '—'; ?></td>
                    <td style="text-align:center;"><?php echo $g['final'] ?? '—'; ?></td>
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
