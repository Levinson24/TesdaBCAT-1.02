<?php
/**
 * Official Transcript of Records Print View
 * TESDA-BCAT Grade Management System
 */

require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole(['registrar', 'registrar_staff', 'admin']);

$conn = getDBConnection();

$studentId = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
if (!$studentId) {
    redirectWithMessage('students.php', 'Invalid student ID.', 'danger');
}

// Fetch the Registrar's name
$regStmt = $conn->prepare("SELECT username FROM users WHERE role = 'registrar' LIMIT 1");
$regStmt->execute();
$regUser = $regStmt->get_result()->fetch_assoc();
$registrarName = $regUser ? $regUser['username'] : 'The Registrar';
$registrarPosition = getSetting('registrar_position', 'Registrar');
$regStmt->close();

$userId = getCurrentUserId();
// 1. Get student information FIRST
$stmt = $conn->prepare("
    SELECT s.*, u.username, d.title_diploma_program as dept_name, p.program_name
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    LEFT JOIN departments d ON s.dept_id = d.dept_id
    LEFT JOIN programs p ON s.program_id = p.program_id
    WHERE s.student_id = ?
");
$stmt->bind_param("i", $studentId);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    redirectWithMessage('students.php', 'Student not found.', 'danger');
}

// Department Access Check for Staff
$userRole = getCurrentUserRole();
if ($userRole === 'registrar_staff') {
    $userProfile = getUserProfile(getCurrentUserId(), $userRole);
    $staffDeptId = $userProfile['dept_id'] ?? 0;
    if ($student['dept_id'] != $staffDeptId) {
        redirectWithMessage('students.php', 'Unauthorized: You do not have access to this student\'s records.', 'danger');
    }
}

// 2. Record transcript generation
$stmt = $conn->prepare("INSERT INTO transcripts (student_id, generated_by, transcript_file, status) VALUES (?, ?, 'Printed PDF', 'official')");
$stmt->bind_param("ii", $studentId, $userId);
$stmt->execute();
$transcriptId = $stmt->insert_id;
$stmt->close();

// Generate Verification Hash (using a secret salt)
$vHash = hash('sha256', 'BCAT_TRANSCRIPT_' . $transcriptId);

// Update record with hash (we can use transcript_file column or add a hash column if we had one)
// For now, let's just generate it on the fly and use the ID in the URL for simpler lookup, 
// but we will use the hash for security.

// Construct Verification URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$verifyUrl = "$protocol://$host/verify.php?tid=$transcriptId&v=$vHash";

// Log audit action
logAudit($userId, 'PRINT', 'transcripts', $studentId, null, 'Generated official Transcript of Records for student: ' . ($student['student_no'] ?? $studentId));

if (!$student) {
    redirectWithMessage('students.php', 'Student not found.', 'danger');
}

// Get student grades
$stmt = $conn->prepare("
    SELECT 
        c.class_code,
        c.course_code,
        c.course_name,
        c.course_type,
        c.units,
        cs.semester,
        cs.school_year,
        cs.schedule,
        cs.room,
        g.midterm,
        g.final,
        g.grade,
        g.remarks,
        g.status
    FROM enrollments e
    JOIN class_sections cs ON e.section_id = cs.section_id
    JOIN courses c ON cs.course_id = c.course_id
    LEFT JOIN grades g ON e.enrollment_id = g.enrollment_id
    WHERE e.student_id = ?
    ORDER BY cs.school_year, cs.semester, c.course_code
");
$stmt->bind_param("i", $studentId);
$stmt->execute();
$grades_res = $stmt->get_result();

// Group grades by school year and semester
$groupedGrades = [];
while ($grade = $grades_res->fetch_assoc()) {
    $key = $grade['school_year'] . ' - ' . $grade['semester'];
    if (!isset($groupedGrades[$key])) {
        $groupedGrades[$key] = [];
    }
    $groupedGrades[$key][] = $grade;
}
$stmt->close();

// Calculate total units (Approved only for Official TOR)
$stmt = $conn->prepare("
    SELECT SUM(c.units) as total_units
    FROM grades g
    JOIN enrollments e ON g.enrollment_id = e.enrollment_id
    JOIN class_sections cs ON e.section_id = cs.section_id
    JOIN courses c ON cs.course_id = c.course_id
    WHERE e.student_id = ? 
    AND (
        (g.status = 'approved' AND (g.remarks IN ('Passed', 'Excellent', 'Very Good', 'Good', 'Satisfactory') OR (g.grade > 0 AND g.grade <= 3.00)))
        OR (c.course_type = 'Minor' AND g.status = 'approved' AND g.remarks = 'Passed')
    )
");
$stmt->bind_param("i", $studentId);
$stmt->execute();
$totalUnits = $stmt->get_result()->fetch_assoc()['total_units'] ?? 0;
if (!$totalUnits)
    $totalUnits = 0;
$stmt->close();

$fullName = strtoupper($student['last_name'] . ', ' . $student['first_name'] . ' ' . ($student['middle_name'] ?? ''));
$gwa = calculateGWA($studentId);
$hasBacklog = hasAcademicBacklog($studentId);
$honors = $hasBacklog ? null : $student['academic_honor']; // Disqualified if backlog exists


$schoolName = getSetting('school_name', 'BALICUATRO COLLEGE OF ARTS AND TRADES');
$schoolRegion = getSetting('school_region', 'Region VIII');
$schoolAddress = getSetting('school_address', 'Allen, Northern Samar');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Transcript - <?php echo htmlspecialchars($student['student_no']); ?></title>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS for mirroring the student style -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Document Layout Styles - Matching student/transcript.php exactly */
        body {
            background-color: #f1f5f9;
        }

        .transcript-container {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 1000px;
            margin: 20px auto;
            color: #1a202c;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border-radius: 0.5rem;
            padding: 3rem;
            position: relative; /* Required for absolute watermark */
            min-height: 297mm;
        }

        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 550px;
            height: auto;
            opacity: 0.08;
            pointer-events: none;
            z-index: 0;
            user-select: none;
            transition: opacity 0.3s ease;
        }

        /* In browsers that support it, ensure the watermark stays truly centered relative to viewport regardless of document margins */
        @supports (position: fixed) {
            .watermark {
                margin: 0 !important;
            }
        }

        .official-title {
            font-weight: 800;
            letter-spacing: 2px;
            color: #2d3748;
            margin-bottom: 5px;
        }

        .title-underline {
            height: 3px;
            width: 60px;
            background-color: #0d6efd;
        }

        .header-double-line {
            border-top: 2.5px solid #0d6efd;
            border-bottom: 1px solid #0d6efd;
            height: 5px;
            margin: 15px 0 20px 0;
            width: 100%;
        }

        .info-group {
            display: flex;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }

        .info-label {
            width: 130px;
            font-weight: 600;
            color: #718096;
        }

        .info-value {
            flex: 1;
            font-weight: 700;
            color: #2d3748;
            border-bottom: 1px dotted #cbd5e0;
        }

        .transcript-table {
            font-size: 0.85rem;
            border-collapse: collapse !important;
        }

        .transcript-table thead {
            display: table-header-group;
        }

        .transcript-table thead th {
            background: #2d3748 !important;
            color: white !important;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.7rem;
            padding: 10px 6px;
            border: 1px solid #2d3748 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .transcript-table tbody tr {
            page-break-inside: avoid;
        }

        .remarks-text {
            font-weight: 700;
            font-size: 0.75rem;
            text-transform: uppercase;
        }

        .grading-system-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-left: 4px solid #3182ce;
            border-radius: 0 4px 4px 0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .summary-table {
            border-radius: 8px;
            overflow: hidden;
            page-break-inside: avoid;
        }

        /* Registrar specific signatures */
        .signature-section {
            display: flex;
            justify-content: flex-end;
            margin-top: 30px;
            page-break-inside: avoid;
        }
        .signature-block {
            width: 280px;
            text-align: center;
        }
        .sig-line {
            border-bottom: 1px solid #1a202c;
            margin-bottom: 4px;
            height: 30px;
        }
        .sig-name { font-weight: 700; font-size: 10pt; text-transform: uppercase; }
        .sig-title { color: #4a5568; font-size: 0.8rem; }

        /* Print Controls */
        .print-controls {
            position: fixed;
            top: 20px;
            right: 20px;
            display: flex; gap: 10px;
            z-index: 1000;
            background: rgba(255,255,255,0.9);
            padding: 10px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        /* Print Specifics */
        @media print {
            @page {
                margin: 1cm;
                size: portrait;
            }

            * {
                box-sizing: border-box !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            body {
                background: white !important;
                font-size: 10pt !important;
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
            }
            
            .print-controls {
                display: none !important;
            }

            .transcript-container {
                border: none !important;
                box-shadow: none !important;
                padding: 2mm !important;
                margin: 0 !important;
                width: 100% !important; 
                max-width: 100% !important;
                min-height: auto !important;
                border-radius: 0 !important;
            }

            .header-logo img {
                max-height: 70px !important;
            }

            .header-text h4 {
                font-size: 1.1rem !important;
                margin-top: 1px !important;
            }

            .header-text h6 {
                font-size: 8.5pt !important;
            }

            .official-title {
                font-size: 1.1rem !important;
            }

            .info-group {
                margin-bottom: 3px !important;
                font-size: 10.5px !important;
            }

            .info-label {
                width: 110px !important;
            }

            .transcript-table {
                font-size: 9px !important;
                width: 100% !important;
            }

            .transcript-table thead th {
                padding: 6px 4px !important;
            }

            .transcript-table td {
                padding: 4px 4px !important;
            }

            .summary-table {
                font-size: 10.5px !important;
            }

            .grading-system-box {
                margin-top: 15px !important;
                padding: 10px !important;
            }

            .grading-system-box h6 {
                font-size: 10.5px !important;
            }

            .grading-system-box ul {
                font-size: 9.5px !important;
            }
            
            .info-value {
                border-bottom: 1px solid #e2e8f0;
            }

            .watermark {
                opacity: 0.05 !important;
            }
        }
    </style>
</head>
<body>

    <div class="print-controls">
        <button class="btn btn-secondary" onclick="window.close()">Close</button>
        <button class="btn btn-primary" onclick="window.print()"><i class="fas fa-print me-2"></i> Print PDF</button>
    </div>

    <div class="transcript-container" id="transcriptCard">
        <img src="../BCAT logo 2024.png" class="watermark" alt="BCAT Watermark">
        <!-- Header Information -->
        <div class="d-flex justify-content-center align-items-center mb-3 transcript-header">
            <div class="header-logo">
                <img src="../BCAT logo 2024.png" alt="Logo Left" class="img-fluid" style="max-height: 115px; margin-right: 25px;">
            </div>
            <div class="header-text text-center mx-3" style="max-width: 650px;">
                <h6 class="mb-1 text-uppercase fw-normal" style="font-size: 0.9rem; letter-spacing: 0.5px;">Republic of the Philippines</h6>
                <h6 class="mb-1 fw-bold" style="font-size: 1rem;">Technical Education and Skills Development Authority</h6>
                <h6 class="mb-1" style="font-size: 0.9rem;"><?php echo htmlspecialchars($schoolRegion); ?></h6>
                <h4 class="mb-1 mt-1"><strong><?php echo htmlspecialchars($schoolName); ?></strong></h4>
                <h6 class="mb-0 text-muted" style="font-size: 0.85rem;"><?php echo htmlspecialchars($schoolAddress); ?></h6>
            </div>
            <div class="header-logo">
                <img src="../tesda_logo.png" alt="TESDA Logo" class="img-fluid" style="max-height: 115px; margin-left: 25px;">
            </div>
        </div>
        
        <div class="header-double-line"></div>
        
        <div class="text-center mb-4">
            <h5 class="official-title"><?php echo strtoupper(getSetting('registrar_doc_title', 'OFFICIAL TRANSCRIPT OF RECORDS')); ?></h5>
            <div class="title-underline mx-auto"></div>
        </div>
        
        <!-- Student Information -->
        <div class="row mb-4 student-info-grid">
            <div class="col-md-6">
                <div class="info-group">
                    <span class="info-label">Name:</span>
                    <span class="info-value text-uppercase"><?php echo htmlspecialchars($fullName); ?></span>
                </div>
                <div class="info-group">
                    <span class="info-label">Student No:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['student_no'] ?? ''); ?></span>
                </div>
                <div class="info-group">
                    <span class="info-label">Date of Birth:</span>
                    <span class="info-value"><?php echo formatDate($student['date_of_birth']); ?></span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="info-group">
                    <span class="info-label">Address:</span>
                    <span class="info-value"><?php echo htmlspecialchars(($student['address'] ?? '') . ', ' . ($student['municipality'] ?? '')); ?></span>
                </div>
                <div class="info-group">
                    <span class="info-label">Course:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['program_name'] ?? $student['course'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-group">
                    <span class="info-label">Year Level:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['year_level'] ?? ''); ?></span>
                </div>
                <div class="info-group">
                    <span class="info-label">Diploma Program:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['dept_name'] ?? 'N/A'); ?></span>
                </div>
            </div>
        </div>
        
        <!-- Academic Records - Consolidated Table -->
        <div class="table-responsive">
            <table class="table table-bordered transcript-table bg-white">
                <thead>
                    <tr>
                        <th width="12%">Period</th>
                        <th width="10%">Subject Code</th>
                        <th width="25%">Subject Description</th>
                        <th width="15%">Schedule / Room</th>
                        <th width="5%" class="text-center">Units</th>
                        <th width="8%" class="text-center">Midterm</th>
                        <th width="8%" class="text-center">Final</th>
                        <th width="8%" class="text-center">Grade</th>
                        <th width="9%" class="text-center">Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($groupedGrades)): ?>
                        <?php foreach ($groupedGrades as $period => $periodGrades): ?>
                            <tr class="bg-light no-break">
                                <td colspan="9" class="fw-bold text-primary py-2 px-3" style="background-color: #f1f5f9 !important; border-left: 4px solid #0d6efd !important;">
                                    <i class="fas fa-calendar-alt me-2"></i> <?php echo htmlspecialchars($period); ?>
                                </td>
                            </tr>
                            <?php foreach ($periodGrades as $grade): ?>
                            <tr>
                                <td class="text-center small"><?php echo htmlspecialchars($grade['class_code'] ?? '-'); ?></td>
                                <td class="fw-bold"><?php echo htmlspecialchars($grade['course_code'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($grade['course_name'] ?? ''); ?></td>
                                <td>
                                    <div class="small fw-bold"><?php echo htmlspecialchars($grade['schedule'] ?? 'TBA'); ?></div>
                                    <div class="small text-muted"><?php echo htmlspecialchars($grade['room'] ?? 'TBA'); ?></div>
                                </td>
                                <td class="text-center"><?php echo $grade['units']; ?></td>
                                <td class="text-center"><?php echo $grade['midterm'] !== null ? number_format($grade['midterm'], 2) : '-'; ?></td>
                                <td class="text-center"><?php echo $grade['final'] !== null ? number_format($grade['final'], 2) : '-'; ?></td>
                                <td class="text-center fw-bold"><?php echo $grade['grade'] !== null ? number_format($grade['grade'], 2) : '—'; ?></td>
                                <td class="text-center">
                                    <?php
                                        $remarkClass = 'text-muted';
                                        if ($grade['remarks'] === 'Passed' || $grade['remarks'] === 'Excellent' || $grade['remarks'] === 'Very Good' || $grade['remarks'] === 'Good' || $grade['remarks'] === 'Satisfactory')
                                            $remarkClass = 'text-success';
                                        elseif ($grade['remarks'] === 'Failed')
                                            $remarkClass = 'text-danger';
                                        elseif ($grade['remarks'] === 'INC')
                                            $remarkClass = 'text-warning';
                                        elseif ($grade['remarks'] === 'Dropped')
                                            $remarkClass = 'text-secondary';
                                    ?>
                                    <span class="remarks-text <?php echo $remarkClass; ?>">
                                        <?php echo htmlspecialchars($grade['remarks'] ?? 'No Grade'); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center py-4">No approved grades exist for this student yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Summary Section -->
        <div class="row mt-4 align-items-center">
            <div class="col-md-7">
                <div style="font-size: 10pt; color: #4a5568; font-style: italic; border: 1px dashed #cbd5e0; padding: 12px; display: inline-block; border-radius: 6px; background-color: #f8fafc;">
                    <i class="fas fa-stamp me-2"></i><strong>REMINDER:</strong> This document is <u>NOT VALID</u> without the official <strong>TESDA-BCAT Seal</strong>.
                </div>
            </div>
            <div class="col-md-5">
                <table class="table table-bordered summary-table shadow-sm bg-white mb-0">
                    <tr>
                        <td class="bg-light fw-bold" width="60%">Total Units Earned:</td>
                        <td class="text-center fw-bold"><?php echo $totalUnits; ?></td>
                    </tr>
                    <?php if (!$hasBacklog && $honors): ?>
                    <tr>
                        <td class="bg-light fw-bold">Academic Honor:</td>
                        <td class="text-center fw-bold text-success" style="background-color: #ffffff;"><i class="fas fa-medal me-1"></i> <?php echo htmlspecialchars($honors); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td class="bg-light fw-bold">Cumulative GWA:</td>
                        <td class="text-center fw-bold"><?php echo $gwa !== null ? number_format($gwa, 2) : '0.00'; ?></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Grading System & Signatures -->
        <div class="row mt-4 align-items-end">
            <div class="col-7">
                <div class="d-flex align-items-center mb-3">
                    <div class="me-3 p-2 bg-white border rounded">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=<?php echo urlencode($verifyUrl); ?>" alt="Verification QR" width="100">
                    </div>
                    <div>
                        <div class="fw-bold text-dark small">DOCUMENT VERIFICATION</div>
                        <div class="text-muted" style="font-size: 0.7rem;">Scan this QR code to verify the authenticity of this official transcript of records.</div>
                        <div class="mt-1 fw-bold text-primary" style="font-size: 0.65rem;">REF ID: <?php echo str_pad($transcriptId, 8, '0', STR_PAD_LEFT); ?></div>
                    </div>
                </div>
                <div class="p-3 grading-system-box">
                    <h6 class="mb-2 fw-bold text-primary" style="font-size: 0.9rem;"><i class="fas fa-info-circle me-2"></i>Grading System</h6>
                    <div class="row text-muted" style="font-size: 0.75rem;">
                        <div class="col-6">
                            <ul class="list-unstyled mb-0">
                                <li><strong>1.00-1.25</strong>: Excellent | <strong>1.50-1.75</strong>: Very Good</li>
                                <li><strong>2.00-2.25</strong>: Good | <strong>2.50-2.75</strong>: Satisfactory</li>
                            </ul>
                        </div>
                        <div class="col-6">
                            <ul class="list-unstyled mb-0">
                                <li><strong>3.00</strong>: Passing | <strong>5.00</strong>: Failure</li>
                                <li><strong>INC</strong>: Incomplete | <strong>DRP</strong>: Dropped</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-5">
                <div class="signature-section">
                    <div class="signature-block">
                        <div class="sig-name" style="font-size: 12pt; border-bottom: 1px solid #1a202c; padding-bottom: 2px; margin-bottom: 4px;">
                            <?php echo htmlspecialchars($registrarName); ?>
                        </div>
                        <div class="sig-title" style="text-transform: uppercase; font-weight: 600; font-size: 0.85rem;"><?php echo htmlspecialchars($registrarPosition); ?></div>
                        <div style="font-size: 7.5pt; margin-top: 15px; color: #718096; line-height: 1.4;">
                            Authorized Signature<br>
                            Date Generated: <?php echo date('M d, Y h:i A'); ?><br>
                            <em>Not valid without official dry seal.</em>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Auto trigger Print Dialog on load -->
    <script>
        window.onload = function() {
            setTimeout(() => {
                window.print();
            }, 500); // 500ms delay to ensure proper rendering
        }
    </script>
</body>
</html>
