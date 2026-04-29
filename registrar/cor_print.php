<?php
/**
 * Official Certificate of Registration (COR) Print View
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
$registrarName = $regUser ? strtoupper($regUser['username']) : 'AUTHORIZED REGISTRAR';
$registrarPosition = getSetting('registrar_position', 'Registrar');
$regStmt->close();

// Get student information with Department
$stmt = $conn->prepare("
    SELECT s.*, u.username, d.title_diploma_program as dept_name, p.program_name, col.college_name
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    LEFT JOIN departments d ON s.dept_id = d.dept_id
    LEFT JOIN colleges col ON d.college_id = col.college_id
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

// Fetch Department Head Name
$deptStmt = $conn->prepare("
    SELECT u.username, i.first_name, i.last_name, i.middle_name 
    FROM users u 
    LEFT JOIN instructors i ON u.user_id = i.user_id 
    WHERE u.role = 'dept_head' AND (i.dept_id = ? OR i.dept_id IS NULL)
    LIMIT 1
");
$deptStmt->bind_param("i", $student['dept_id']);
$deptStmt->execute();
$deptHeadResult = $deptStmt->get_result()->fetch_assoc();
$deptHeadName = 'DEPARTMENT HEAD';
if ($deptHeadResult) {
    if (!empty($deptHeadResult['first_name']) && !empty($deptHeadResult['last_name'])) {
        $mi = !empty($deptHeadResult['middle_name']) ? substr($deptHeadResult['middle_name'], 0, 1) . '. ' : '';
        $deptHeadName = strtoupper($deptHeadResult['first_name'] . ' ' . $mi . $deptHeadResult['last_name']);
    } else {
        $deptHeadName = strtoupper($deptHeadResult['username']);
    }
}
$deptStmt->close();

// Department Access Check for Staff
$userRole = getCurrentUserRole();
if ($userRole === 'registrar_staff') {
    $userProfile = getUserProfile(getCurrentUserId(), $userRole);
    $staffDeptId = $userProfile['dept_id'] ?? 0;
    if ($student['dept_id'] != $staffDeptId) {
        redirectWithMessage('students.php', 'Unauthorized: You do not have access to this student\'s COR.', 'danger');
    }
}

// Log audit action
logAudit(getCurrentUserId(), 'PRINT_COR', 'enrollments', $studentId, null, 'Generated official Certificate of Registration (COR) for student: ' . ($student['student_no'] ?? $studentId));

// Get current academic settings
$currentSemester = getSetting('current_semester', '1st');
$academicYear = getSetting('academic_year', '2024-2025');
$userId = getCurrentUserId();

// Record COR generation (Safe Execution)
$stmt = $conn->prepare("INSERT INTO cors (student_id, semester, school_year, generated_by) VALUES (?, ?, ?, ?)");
if ($stmt) {
    if (!$stmt->bind_param("issi", $studentId, $currentSemester, $academicYear, $userId) || !$stmt->execute()) {
        error_log("COR Insertion Error: " . ($stmt->error ?: 'Unknown'));
    }
    $corId = $stmt->insert_id ?: time(); // Fallback ID if insert fails
    $stmt->close();
} else {
    error_log("COR Prepared Statement Failed: " . $conn->error);
    $corId = time(); // Total fallback
}

// Generate Verification Hash (using a secret salt)
$vHash = hash('sha256', 'BCAT_COR_' . $corId);

// Construct Verification URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$baseDir = rtrim(dirname(dirname($_SERVER['PHP_SELF'])), '/\\');
$verifyUrl = "$protocol://$host$baseDir/verify.php?cid=$corId&v=$vHash";

// Get current enrollment records (filtered by current semester/year)
$stmt = $conn->prepare("
    SELECT 
        c.class_code,
        c.course_code,
        c.course_name,
        c.course_type,
        COALESCE(c.units, 0) as units,
        cs.section_name,
        cs.semester,
        cs.school_year,
        cs.schedule,
        cs.room,
        g.grade,
        g.remarks
    FROM enrollments e
    JOIN class_sections cs ON e.section_id = cs.section_id
    JOIN curriculum cur ON cs.curriculum_id = cur.curriculum_id
    JOIN subjects c ON cur.subject_id = c.subject_id
    LEFT JOIN grades g ON e.enrollment_id = g.enrollment_id
    WHERE e.student_id = ? 
    AND cs.semester = ? 
    AND cs.school_year = ?
    AND cs.status = 'active'
    ORDER BY cs.school_year DESC, cs.semester DESC
");
$stmt->bind_param("iss", $studentId, $currentSemester, $academicYear);
$stmt->execute();
$enrollments_res = $stmt->get_result();

$enrollments = [];
while ($row = $enrollments_res->fetch_assoc()) {
    $enrollments[] = $row;
}
$stmt->close();

$periodTitle = strtoupper($currentSemester . " Semester, SY " . $academicYear);

// --- Single-Page Rendering (No Chunking) ---
$pageEnrollments = $enrollments;
$totalPageCount = 1;

// Reusable Document Components
function renderDocumentHeader($schoolName, $schoolRegion, $schoolAddress) {
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
<?php
}

function renderDocumentFooter($isLastPage, $totalPageCount, $pageNumber, $verifyUrl, $corId, $finalTotalUnits, $gwa, $fullName, $registrarName, $registrarPosition, $deptHeadName) {
?>
    <div class="mt-auto pt-3">
        <?php if ($isLastPage): ?>
        <div class="row g-3 mb-4 mt-2">
            <div class="col-8">
                <div class="d-flex align-items-center p-3 border rounded bg-white shadow-sm mb-3">
                    <div class="me-3 p-1 bg-white border rounded">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=<?php echo urlencode($verifyUrl); ?>" alt="Verification QR" width="80">
                    </div>
                    <div>
                        <div class="fw-bold text-dark small">DOCUMENT VERIFICATION</div>
                        <div class="text-muted small" style="font-size: 0.65rem;">Scan QR to verify this official Certificate of Registration.</div>
                        <div class="fw-bold text-primary small" style="color: #0038A8 !important;">REF: COR-<?php echo str_pad($corId, 8, '0', STR_PAD_LEFT); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-4">
                <div class="card border-primary shadow-sm">
                    <div class="card-body p-2">
                        <div class="d-flex justify-content-between border-bottom pb-1 mb-1">
                            <span class="fw-bold small">Total Units:</span>
                            <span class="fw-bold text-primary" style="color: #0038A8 !important;"><?php echo $finalTotalUnits; ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="fw-bold small">GWA:</span>
                            <span class="fw-bold text-primary" style="color: #0038A8 !important;"><?php echo number_format($gwa, 2); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-3 g-2">
            <div class="col-4 text-center">
                <div style="border-bottom: 2px solid #0038A8; width: 85%; margin: 35px auto 6px auto;"></div>
                <div class="fw-bold text-uppercase" style="font-size: 0.70rem;"><?php echo $fullName; ?></div>
                <div class="text-muted" style="font-size: 0.65rem;">Student Signature</div>
            </div>
            <div class="col-4 text-center">
                <div style="border-bottom: 2px solid #0038A8; width: 85%; margin: 35px auto 6px auto;"></div>
                <div class="fw-bold text-uppercase" style="font-size: 0.70rem;"><?php echo htmlspecialchars($deptHeadName); ?></div>
                <div class="text-muted" style="font-size: 0.65rem;">Department Head / Dean</div>
            </div>
            <div class="col-4 text-center">
                <div style="border-bottom: 2px solid #0038A8; width: 85%; margin: 35px auto 6px auto;"></div>
                <div class="fw-bold text-uppercase" style="font-size: 0.70rem;"><?php echo htmlspecialchars($registrarName); ?></div>
                <div class="text-muted" style="font-size: 0.65rem;"><?php echo htmlspecialchars($registrarPosition); ?> / Authorized Officer</div>
            </div>
        </div>
        <?php else: ?>
        <div class="text-center py-4 border-top">
            <div class="text-muted italic">
                <i class="fas fa-chevron-circle-down me-1"></i>
                <em>Continued on Page <?php echo $pageNumber + 1; ?> of <?php echo $totalPageCount; ?>...</em>
            </div>
        </div>
        <?php endif; ?>

        <div class="text-center text-muted small border-top pt-2 mt-2">
            <span>Date Generated: <?php echo date('M d, Y h:i A'); ?></span>
        </div>
    </div>
<?php
}

$fullName = strtoupper($student['last_name'] . ', ' . $student['first_name'] . ' ' . ($student['middle_name'] ?? ''));
$schoolName = getSetting('school_name', 'BALICUATRO COLLEGE OF ARTS AND TRADES');
$schoolRegion = getSetting('school_region', 'Region VIII');
$schoolAddress = getSetting('school_address', 'Allen, Northern Samar');

$finalTotalUnits = 0;
foreach($enrollments as $item) $finalTotalUnits += (isset($item['units']) ? $item['units'] : 0);
$gwa = calculateGWA($studentId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>COR - <?php echo htmlspecialchars($student['student_no']); ?> - TESDA-BCAT GMS</title>
    <link rel="icon" href="../BCAT logo 2024.png" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f1f5f9; padding: 20px; font-family: 'Outfit', sans-serif !important; }
        .cor-container {
            width: 210mm;
            min-height: 279.4mm; /* Letter height to fit both A4 and Short Bond */
            margin: 30px auto;
            background: white;
            padding: 25px 35px;
            border-radius: 0.75rem;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            position: relative;
        }
        .header-logo img { max-height: 70px; }
        .official-title { font-weight: 800; letter-spacing: 1px; border-bottom: 2px solid #0038A8; display: inline-block; padding-bottom: 5px; }
        .info-label { font-weight: 600; color: #64748b; width: 110px; display: inline-block; font-size: 0.8rem; }
        .info-value { font-weight: 700; border-bottom: 1.5px dotted #cbd5e0; flex-grow: 1; padding-left: 5px; font-size: 0.8rem; }
        .table thead th { background: #2d3748; color: white; font-size: 0.65rem; text-transform: uppercase; padding: 4px 3px !important; border: none; }
        .table td { font-size: 0.70rem; padding: 3px 4px !important; }
        .cor-container { position: relative; }
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 500px;
            height: auto;
            opacity: 0.08;
            pointer-events: none;
            z-index: 0;
            user-select: none;
        }
        .header-double-line {
            border-top: 2px solid #0038A8;
            border-bottom: 1px solid #0038A8;
            height: 4px;
            margin: 10px 0 15px 0;
        }
        
        @media print {
            body { padding: 0; background: white; margin: 0; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .cor-container { 
                box-shadow: none; 
                margin: 0 auto !important; 
                border-radius: 0; 
                padding: 8mm 12mm !important; 
                width: 210mm !important;
                max-width: 210mm !important;
                min-height: auto;
                height: auto;
                overflow: visible !important;
            }
            html, body { overflow-x: hidden !important; width: 100% !important; margin: 0 !important; padding: 0 !important; }
            footer, .no-print { display: none !important; }
                min-height: auto;
                height: auto;
                overflow: hidden;
            }
            .no-print { display: none !important; }
            .official-title { font-size: 1.1rem !important; }
            .badge { border: 1px solid #000; color: #000 !important; }
            @page { margin: 0; size: auto; }
        }
    </style>
</head>
<body>
    <div class="no-print text-center mb-4">
        <button class="btn btn-secondary px-4 me-2" onclick="window.close()">Close</button>
        <button class="btn btn-primary px-4" onclick="window.print()"><i class="fas fa-print me-2"></i> Print COR</button>
    </div>

    <?php 
        $pageNumber = 1;
        $isLastPage = true;
    ?>
    <div class="cor-container" style="min-height: 250mm;">
        <img src="../BCAT logo 2024.png" class="watermark" alt="BCAT Watermark">
        
        <?php renderDocumentHeader($schoolName, $schoolRegion, $schoolAddress); ?>

        <?php if ($totalPageCount > 1): ?>
            <div class="text-end w-100" style="margin-top: -10px; margin-bottom: -5px;">
                <span class="fw-bold text-muted" style="font-size: 0.75rem; letter-spacing: 0.5px;">PAGE <?php echo $pageNumber; ?> OF <?php echo $totalPageCount; ?></span>
            </div>
        <?php endif; ?>

        <div class="text-center my-3">
            <h5 class="official-title" style="font-size: 1.1rem;">CERTIFICATE OF REGISTRATION (COR)</h5>
            <div class="title-underline mx-auto"></div>
            <p class="mt-1 fw-bold text-primary" style="font-size: 0.85rem;"><?php echo $periodTitle; ?></p>
        </div>

        <!-- Student Information Block (Persistent) -->
        <div class="row mb-3 g-3">
            <div class="col-6">
                <div class="info-group mb-1 d-flex">
                    <span class="info-label" style="width: 100px;">Name:</span>
                    <span class="info-value text-uppercase"><?php echo $fullName; ?></span>
                </div>
                <div class="info-group mb-1 d-flex">
                    <span class="info-label" style="width: 100px;">Student No:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['student_no']); ?></span>
                </div>
                <div class="info-group mb-1 d-flex">
                    <span class="info-label" style="width: 100px;">Program:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['program_name'] ?? 'N/A'); ?></span>
                </div>
            </div>
            <div class="col-6">
                <div class="info-group mb-1 d-flex">
                    <span class="info-label" style="width: 110px;">College/DP:</span>
                    <span class="info-value" style="font-size: 0.8rem;"><?php echo htmlspecialchars($student['dept_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-group mb-1 d-flex">
                    <span class="info-label" style="width: 110px;">Year Level:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['year_level']); ?></span>
                </div>
                <div class="info-group mb-1 d-flex">
                    <span class="info-label" style="width: 110px;">Date Issued:</span>
                    <span class="info-value"><?php echo date('M d, Y'); ?></span>
                </div>
            </div>
        </div>

        <table class="table table-bordered mt-2 table-record">
            <thead>
                <tr>
                    <th width="10%">Class Code</th>
                    <th width="12%">Course Code</th>
                    <th width="35%">Subject Description</th>
                    <th width="23%">Schedule / Room</th>
                    <th class="text-center" width="8%">Units</th>
                    <th class="text-center" width="12%">Grade</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (!empty($pageEnrollments)):
                    foreach ($pageEnrollments as $e):
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($e['class_code'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($e['course_code'] ?? ''); ?></td>
                    <td>
                        <?php echo htmlspecialchars($e['course_name'] ?? ''); ?>
                        <span class="text-muted small ms-1">(<?php echo htmlspecialchars($e['course_type'] ?? 'Minor'); ?>)</span>
                    </td>
                    <td>
                        <div class="small fw-bold text-dark"><?php echo htmlspecialchars($e['schedule'] ?? 'TBA'); ?></div>
                        <div class="small text-muted"><?php echo htmlspecialchars($e['room'] ?? 'TBA'); ?></div>
                    </td>
                    <td class="text-center"><?php echo $e['units']; ?></td>
                    <td class="text-center">
                        <span class="fw-bold"><?php echo isset($e['grade']) ? number_format($e['grade'], 2) : '—'; ?></span>
                        <?php if(!empty($e['remarks'])): ?>
                            <div class="x-small text-muted" style="font-size: 0.65rem;"><?php echo htmlspecialchars($e['remarks']); ?></div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php
                    endforeach; 
                endif; 
                ?>
            </tbody>
        </table>

        <div style="flex-grow: 1;"></div>

        <?php 
        renderDocumentFooter($isLastPage, $totalPageCount, $pageNumber, $verifyUrl, $corId, $finalTotalUnits, $gwa, $fullName, $registrarName, $registrarPosition, $deptHeadName); 
        ?>
    </div>
    <?php // endforeach; ?>
</body>
</html>
