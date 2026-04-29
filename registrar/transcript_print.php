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
// Fetch the person printing the document (Prepared By)
$prepStmt = $conn->prepare("SELECT username, role FROM users WHERE user_id = ?");
$prepStmt->bind_param("i", $userId);
$prepStmt->execute();
$prepRes = $prepStmt->get_result()->fetch_assoc();
$preparedByName = $prepRes ? $prepRes['username'] : 'System Generated';
$preparedByRole = $prepRes ? $prepRes['role'] : '';

// Map roles to professional titles
$roleTitles = [
    'registrar' => 'Registrar',
    'registrar_staff' => 'Clerk',
    'admin' => 'Administrator'
];
$preparedByTitle = $roleTitles[$preparedByRole] ?? 'Staff';
$prepStmt->close();

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

// Construct Verification URL using BASE_URL
$verifyUrl = BASE_URL . "verify.php?tid=$transcriptId&v=$vHash";

// Log audit action
logAudit($userId, 'PRINT_TOR', 'transcripts', $studentId, null, 'Generated official Transcript of Records for student: ' . ($student['student_no'] ?? $studentId));

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
        /* Removed Midterm/Final */
        g.grade,
        g.remarks,
        g.status
    FROM enrollments e
    JOIN class_sections cs ON e.section_id = cs.section_id
    JOIN curriculum cur ON cs.curriculum_id = cur.curriculum_id
    JOIN subjects c ON cur.subject_id = c.subject_id
    LEFT JOIN grades g ON e.enrollment_id = g.enrollment_id
    WHERE e.student_id = ?
    ORDER BY cs.school_year, cs.semester, c.course_code
");
$stmt->bind_param("i", $studentId);
$stmt->execute();
$grades_res = $stmt->get_result();

// Group grades by school year and semester
$allGrades = [];
while ($grade = $grades_res->fetch_assoc()) {
    $key = $grade['school_year'] . ' - ' . $grade['semester'];
    $allGrades[$key][] = $grade;
}
$stmt->close();

// --- Pagination Logic (Dynamic 10-row Chunks) ---
// Flatten grades into a display list (headers + rows)
$displayList = [];
foreach ($allGrades as $period => $periodGrades) {
    $displayList[] = ['type' => 'header', 'content' => $period];
    foreach ($periodGrades as $grade) {
        $displayList[] = ['type' => 'row', 'content' => $grade];
    }
}

$paperSize = 'legal';

$pageConfig = [
    'legal' => ['width' => 216, 'height' => 356, 'rows' => 22],
];

$config = $pageConfig[$paperSize];
$rowsPerPage = $config['rows'];
$pages = array_chunk($displayList, $rowsPerPage);
if (empty($pages))
    $pages = [[]]; // Ensure at least one page
$totalPageCount = count($pages);

// Reusable Document Components
function renderTORHeader($schoolName, $schoolRegion, $schoolAddress, $docTitle, $pageNumber, $totalPageCount)
{
?>
    <div class="d-flex justify-content-between align-items-center mb-0 transcript-header">
        <div class="header-logo">
            <img src="../BCAT logo 2024.png" alt="Logo Left" class="img-fluid" style="max-height: 100px;">
        </div>
        <div class="header-text text-center mx-3" style="flex: 1;">
            <h6 class="mb-0 text-uppercase fw-normal small" style="letter-spacing: 1px; color: #64748b;">Republic of the Philippines</h6>
            <h6 class="mb-1 fw-bold" style="font-size: 0.95rem; color: #0038A8;">TECHNICAL EDUCATION AND SKILLS DEVELOPMENT AUTHORITY</h6>
            <h6 class="mb-0 text-muted small"><?php echo htmlspecialchars($schoolRegion); ?></h6>
            <h4 class="mb-1 mt-1" style="font-weight: 800; color: #0038A8; letter-spacing: -0.5px;"><?php echo htmlspecialchars($schoolName); ?></h4>
            <h6 class="mb-0 text-muted small"><?php echo htmlspecialchars($schoolAddress); ?></h6>
        </div>
        <div class="header-logo">
            <img src="../tesda_logo.png" alt="TESDA Logo" class="img-fluid" style="max-height: 100px;">
        </div>
    </div>
    <div class="header-double-line"></div>
    <?php if ($totalPageCount > 1): ?>
        <div class="text-end w-100" style="margin-top: -5px; margin-bottom: 0;">
            <span class="fw-bold text-muted" style="font-size: 0.75rem; letter-spacing: 0.5px;">PAGE <?php echo $pageNumber; ?> OF <?php echo $totalPageCount; ?></span>
        </div>
    <?php
    endif; ?>
    <div class="text-center mb-3">
        <h5 class="official-title mb-1"><?php echo strtoupper($docTitle); ?></h5>
        <div class="title-underline mx-auto"></div>
    </div>
<?php
}

function renderTORFooter($isLastPage, $totalPageCount, $pageNumber, $totalUnits, $gwa, $registrarName, $registrarPosition, $verifyUrl, $torId, $preparedByName, $preparedByTitle)
{
?>
    <div class="mt-auto">
        <?php if ($isLastPage): ?>
            <div class="row align-items-end mb-4 g-4">
                <div class="col-7">
                    <!-- Verification Frame -->
                    <div class="verification-card mb-3">
                        <div class="row align-items-center g-0">
                            <div class="col-3 text-center border-end py-2">
                                <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode($verifyUrl); ?>" alt="QR" class="img-fluid" style="max-width: 85px;">
                            </div>
                            <div class="col-9 ps-3 py-2">
                                <div class="fw-bold text-dark small">OFFICIAL VERIFICATION</div>
                                <div class="text-muted" style="font-size: 0.65rem;">Scan this QR code to verify the authenticity of this Official Transcript of Records.</div>
                                <div class="fw-bold text-primary mt-1" style="font-size: 0.75rem;">REF ID: TOR-<?php echo str_pad($torId, 8, "0", STR_PAD_LEFT); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Grading Legend Frame -->
                    <div class="grading-legend-card p-2">
                        <div class="fw-bold text-primary small mb-2 border-bottom pb-1" style="font-size: 0.7rem;">MASTER GRADING SYSTEM</div>
                        <div class="d-flex flex-wrap align-items-center gap-1">
                            <span class="fw-bold" style="font-size: 0.70rem;">1.00 - 1.25</span> <span class="small me-2" style="font-size: 0.65rem;">Excellent</span>
                            <span class="fw-bold" style="font-size: 0.70rem;">1.50 - 1.75</span> <span class="small me-2" style="font-size: 0.65rem;">Very Good</span>
                            <span class="fw-bold" style="font-size: 0.70rem;">2.00 - 2.25</span> <span class="small me-2" style="font-size: 0.65rem;">Good</span>
                            <span class="fw-bold" style="font-size: 0.70rem;">2.50 - 2.75</span> <span class="small me-2" style="font-size: 0.65rem;">Satisfactory</span>
                            <span class="fw-bold" style="font-size: 0.70rem;">3.00</span> <span class="small me-2" style="font-size: 0.65rem;">Passing</span>
                            <span class="fw-bold" style="font-size: 0.70rem;">5.00</span> <span class="small me-2" style="font-size: 0.65rem;">Failed</span>
                            <span class="fw-bold" style="font-size: 0.70rem;">INC</span> <span class="small me-2" style="font-size: 0.65rem;">Incomplete</span>
                            <span class="fw-bold" style="font-size: 0.70rem;">DRP</span> <span class="small" style="font-size: 0.65rem;">Dropped</span>
                        </div>
                    </div>
                </div>

                <div class="col-5">
                    <!-- Summary Totals Frame -->
                    <div class="totals-card mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold text-muted small">TOTAL UNITS EARNED:</span>
                            <span class="fw-bold h5 mb-0 text-primary"><?php echo $totalUnits; ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold text-muted small">CUMULATIVE GWA:</span>
                            <span class="fw-bold h5 mb-0 text-primary"><?php echo number_format($gwa, 2); ?></span>
                        </div>
                    </div>

                    <div class="signature-block w-100 text-center">
                        <div class="prepared-by-section text-start mb-5" style="margin-top: 10px;">
                            <div class="text-muted" style="font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Prepared By:</div>
                            <div class="fw-bold text-dark d-inline-block mt-1" style="font-size: 0.9rem; min-width: 15rem; border-bottom: 2px solid #cbd5e0;"><?php echo htmlspecialchars($preparedByName); ?></div>
                            <div class="text-muted fw-bold text-uppercase mt-1" style="font-size: 0.7rem; letter-spacing: 0.05em;"><?php echo htmlspecialchars($preparedByTitle); ?></div>
                        </div>

                        <div style="border-bottom: 2px solid #1e293b; width: 85%; margin: 20px auto 6px auto;"></div>
                        <div class="fw-bold text-uppercase text-dark" style="font-size: 1rem;"><?php echo $registrarName; ?></div>
                        <div class="text-muted fw-bold text-uppercase mt-2" style="font-size: 0.7rem;"><?php echo $registrarPosition; ?></div>
                        <div class="text-muted small mt-1">Authorized Official Signature</div>
                    </div>
                </div>
            </div>
        <?php
    endif; ?>

        <div class="d-flex justify-content-center align-items-center text-center text-muted small border-top pt-2 mt-2">
            <div style="width: 70px; height: 70px; border: 2px solid #0038A8; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.65rem; font-weight: 900; color: #0038A8; margin-right: 15px; background: rgba(0, 56, 168, 0.02); text-transform: uppercase; line-height: 1.1;">
                OFFICIAL<br>DRY SEAL
            </div>
            <div class="text-start">
                <span class="text-primary me-2">●</span>
                <strong>OFFICIAL TRANSCRIPT:</strong> NOT VALID without official dry seal.<br>
                <span>Date Printed: <?php echo date('F d, Y h:i A'); ?></span>
                <?php if (!$isLastPage): ?>
                    <span class="ms-3 fw-bold text-primary text-uppercase">[ CONTINUED ON PAGE <?php echo $pageNumber + 1; ?> OF <?php echo $totalPageCount; ?> ]</span>
                <?php
    endif; ?>
            </div>
        </div>
    </div>
<?php
}

// Calculate total units (Approved only for Official TOR)
$stmt = $conn->prepare("
    SELECT SUM(c.units) as total_units
    FROM grades g
    JOIN enrollments e ON g.enrollment_id = e.enrollment_id
    JOIN class_sections cs ON e.section_id = cs.section_id
    JOIN curriculum cur ON cs.curriculum_id = cur.curriculum_id
    JOIN subjects c ON cur.subject_id = c.subject_id
    WHERE e.student_id = ? 
    AND (
        (g.status = 'approved' AND (g.remarks IN ('Passed', 'Excellent', 'Very Good', 'Good', 'Satisfactory', 'Fair') OR (g.grade > 0 AND g.grade <= 3.00)))
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
    <title>Transcript - <?php echo htmlspecialchars($student['student_no']); ?> - TESDA-BCAT GMS</title>
    <link rel="icon" href="../BCAT logo 2024.png" type="image/png">
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
            font-family: 'Outfit', system-ui, -apple-system, sans-serif !important;
            max-width: 1000px;
            margin: 30px auto;
            color: #1a202c;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            border-radius: 0.75rem;
            padding: 40px;
            position: relative; 
            min-height: <?php echo $config['height']; ?>mm;
            display: flex;
            flex-direction: column;
            overflow: visible; /* Prevent internal scrollbars */
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
            background-color: #0038A8;
        }

        .header-double-line {
            border-top: 2.5px solid #0038A8;
            border-bottom: 1px solid #0038A8;
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
            color: #1e293b;
            border-bottom: 1.5px dotted #a0aec0;
            padding-left: 5px;
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
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.75rem;
            padding: 8px 6px;
            border: 1px solid #2d3748 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .transcript-table tr {
            page-break-inside: avoid !important;
        }

        .transcript-table-wrapper {
            page-break-inside: auto;
        }

        .no-break {
            page-break-inside: avoid !important;
        }

        .remarks-text {
            font-weight: 700;
            font-size: 0.75rem;
            text-transform: uppercase;
        }

        .grading-system-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-left: 4px solid #0038A8;
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
            justify-content: space-between;
            margin-top: 40px;
            page-break-inside: avoid;
        }
        .signature-block {
            width: 45%;
            text-align: center;
        }
        .sig-line {
            border-bottom: 2px solid #0038A8;
            margin-bottom: 6px;
            height: 40px;
            width: 90%;
            margin-left: auto;
            margin-right: auto;
        }
        .sig-name { font-weight: 800; font-size: 1rem; text-transform: uppercase; color: #1e293b; }
        .sig-title { color: #64748b; font-size: 0.75rem; font-weight: 600; margin-top: 2px; }
        .sig-caption { color: #718096; font-size: 0.7rem; margin-top: 4px; }

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
                size: <?php echo($paperSize === 'legal') ? 'legal' : 'letter'; ?>;
                margin: 8mm 10mm;
            }

            * {
                box-sizing: border-box !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            body {
                background: white !important;
                font-size: 11pt !important;
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
                padding: 8mm 12mm !important;
                margin: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                height: auto !important;
                border-radius: 0 !important;
                display: flex !important;
                flex-direction: column !important;
            }

            .header-logo img {
                max-height: 90px !important;
            }

            .header-text h4 {
                font-size: 1.25rem !important;
                margin-top: 2px !important;
            }

            .header-text h6 {
                font-size: 9pt !important;
            }

            .official-title {
                font-size: 1.2rem !important;
            }

            .info-group {
                margin-bottom: 6px !important;
                font-size: 11px !important;
            }

            .info-label {
                width: 120px !important;
            }

            .transcript-table {
                font-size: 10px !important;
                width: 100% !important;
            }

            .transcript-table thead th {
                padding: 10px 6px !important;
                background: #2d3748 !important;
                color: white !important;
            }

            .transcript-table td {
                padding: 6px 6px !important;
            }

            .summary-table {
                font-size: 11px !important;
            }

            .grading-system-box {
                margin-top: 20px !important;
                padding: 12px !important;
                background-color: #f8fafc !important;
                border-left: 4px solid #3182ce !important;
            }

            .grading-system-box h6 {
                font-size: 11px !important;
            }

            .grading-system-box ul {
                font-size: 10px !important;
            }
            
            .info-value {
                border-bottom: 1px dotted #cbd5e0 !important;
            }

            .watermark {
                opacity: 0.06 !important;
            }
        }
    </style>
</head>
<body>

    <div class="print-controls d-flex justify-content-center align-items-center gap-3">

        <button class="btn btn-secondary shadow-sm" onclick="window.close()">Close</button>
        <button class="btn btn-primary shadow-sm" onclick="window.print()"><i class="fas fa-print me-2"></i> Print PDF</button>
    </div>
    <?php foreach ($pages as $pIndex => $pageContent):
    $pageNumber = $pIndex + 1;
    $isLastPage = ($pageNumber === $totalPageCount);
?>
    <div class="transcript-container" id="transcriptCard<?php echo $pageNumber; ?>" style="<?php echo $pageNumber < $totalPageCount ? 'page-break-after: always;' : ''; ?>; <?php echo $pageNumber > 1 ? 'margin-top: 50px;' : ''; ?>">
        <img src="../BCAT logo 2024.png" class="watermark" alt="BCAT Watermark">
        
        <?php renderTORHeader($schoolName, $schoolRegion, $schoolAddress, 'Transcript Of Records', $pageNumber, $totalPageCount); ?>
        
        <!-- Student Information Block (Repeated on every page for context) -->
        <div class="row mb-3 student-info-grid">
            <div class="col-6">
                <div class="info-group">
                    <span class="info-label">Name:</span>
                    <div class="info-value">
                        <span class="text-uppercase fw-bold" style="font-size: 1.1rem;"><?php echo htmlspecialchars($fullName); ?></span>
                        <?php if (!empty($honors)): ?>
                            <div class="text-primary fw-bold small mt-1" style="letter-spacing: 0.5px;">
                                <i class="fas fa-medal me-1"></i> <?php echo strtoupper($honors); ?>
                            </div>
                        <?php
    endif; ?>
                    </div>
                </div>
                <div class="info-group">
                    <span class="info-label">Student No:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['student_no'] ?? ''); ?></span>
                </div>
                <div class="info-group">
                    <span class="info-label">Gender:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['gender'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-group">
                    <span class="info-label">Date of Birth:</span>
                    <span class="info-value"><?php echo formatDate($student['date_of_birth']); ?></span>
                </div>
                <div class="info-group">
                    <span class="info-label">Address:</span>
                    <span class="info-value" style="font-size: 0.85rem;"><?php echo htmlspecialchars(($student['address'] ?? '') . ', ' . ($student['municipality'] ?? '')); ?></span>
                </div>
            </div>
            <div class="col-6">
                <div class="info-group">
                    <span class="info-label">Diploma Program:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['dept_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-group">
                    <span class="info-label">Course/Major:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['program_name'] ?? $student['course'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-group">
                    <span class="info-label">Year Level:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['year_level'] ?? ''); ?></span>
                </div>
                <div class="info-group">
                    <span class="info-label">Admission Date:</span>
                    <span class="info-value"><?php echo formatDate($student['enrollment_date']); ?></span>
                </div>
            </div>
        </div>

        <?php if ($pageNumber === 1): ?>
        <div class="p-2 mb-4 bg-light border-start border-4 border-primary rounded-end">
            <div class="row text-muted small fw-bold text-uppercase border-bottom mb-2 pb-1 mx-0">
                <div class="col-12"><i class="fas fa-graduation-cap me-2 text-primary"></i>Educational Background</div>
            </div>
            <div class="row g-2 px-2">
                <div class="col-6 border-end">
                    <div class="small text-muted">Secondary:</div>
                    <div class="fw-bold"><?php echo htmlspecialchars($student['secondary_school'] ?? 'N/A'); ?> (<?php echo htmlspecialchars($student['secondary_year'] ?? '—'); ?>)</div>
                </div>
                <div class="col-6">
                    <div class="small text-muted">Elementary:</div>
                    <div class="fw-bold"><?php echo htmlspecialchars($student['elem_school'] ?? 'N/A'); ?> (<?php echo htmlspecialchars($student['elem_year'] ?? '—'); ?>)</div>
                </div>
            </div>
        </div>
        <?php
    endif; ?>
        
        <!-- MIDDLE CONTENT (Grades Table) -->
        <div class="transcript-table-wrapper">
            <table class="table table-bordered transcript-table bg-white">
                <thead>
                    <tr class="bg-dark text-white">
                        <th width="12%">Period</th>
                        <th width="10%">Subject Code</th>
                        <th width="30%">Subject Description</th>
                        <th width="15%">Schedule / Room</th>
                        <th class="text-center">Units</th>
                        <th class="text-center">Grade</th>
                        <th class="text-center">Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($pageContent)): ?>
                        <?php foreach ($pageContent as $item): ?>
                            <?php if ($item['type'] === 'header'): ?>
                                <tr class="bg-light no-break">
                                    <td colspan="9" class="fw-bold py-2 px-3" style="background-color: #f8fafc !important; border-left: 5px solid #0d6efd !important; font-size: 0.75rem; color: #1e293b; text-transform: uppercase; letter-spacing: 0.5px;">
                                        <?php echo htmlspecialchars($item['content']); ?>
                                    </td>
                                </tr>
                            <?php
            else:
                $grade = $item['content'];
?>
                            <tr>
                                <td class="text-center small"><?php echo htmlspecialchars($grade['class_code'] ?? '-'); ?></td>
                                <td class="fw-bold"><?php echo htmlspecialchars($grade['course_code'] ?? ''); ?></td>
                                <td class="small"><?php echo htmlspecialchars($grade['course_name'] ?? ''); ?></td>
                                <td class="small">
                                    <div class="fw-bold"><?php echo htmlspecialchars($grade['schedule'] ?? 'TBA'); ?></div>
                                    <div class="text-muted"><?php echo htmlspecialchars($grade['room'] ?? 'TBA'); ?></div>
                                </td>
                                <td class="text-center"><?php echo $grade['units']; ?></td>
                                <td class="text-center fw-bold text-primary"><?php echo $grade['grade'] !== null ? number_format($grade['grade'], 2) : '—'; ?></td>
                                <td class="text-center">
                                    <?php
                $remark = $grade['remarks'] ?? 'Passed';
                $remarkClass = 'text-muted';
                if (in_array($remark, ['Passed', 'Excellent', 'Very Good', 'Good', 'Satisfactory', 'Fair']))
                    $remarkClass = 'text-success';
                elseif ($remark === 'Failed')
                    $remarkClass = 'text-danger';
                elseif ($remark === 'INC')
                    $remarkClass = 'text-warning';
                elseif ($remark === 'Dropped')
                    $remarkClass = 'text-secondary';
                echo '<span class="remarks-text ' . $remarkClass . ' fw-bold">' . htmlspecialchars($remark) . '</span>';
?>
                                </td>
                            </tr>
                            <?php
            endif; ?>
                        <?php
        endforeach; ?>
                    <?php
    else: ?>
                        <tr><td colspan="9" class="text-center py-5 text-muted">No academic records found for this page.</td></tr>
                    <?php
    endif; ?>
                </tbody>
            </table>
        </div>

                <!-- Remarks Explanation -->
        <div class="remarks-note" style="font-size:0.75rem; margin-top:10px; color:#555;">
            <strong>Remarks:</strong> This column indicates the status of each subject (Passed, Failed, INC, etc.). It is used for official purposes such as employment verification, promotion eligibility, and other transactions that require proof of academic performance.
        </div>

        <?php if ($isLastPage): ?>
        <div style="flex: 1;"></div>
        <?php
    endif; ?>

        <?php renderTORFooter($isLastPage, $totalPageCount, $pageNumber, $totalUnits, $gwa, $registrarName, $registrarPosition, $verifyUrl, $transcriptId, $preparedByName, $preparedByTitle); ?>
    </div>
    <?php
endforeach; ?>

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
