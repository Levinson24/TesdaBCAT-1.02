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
$registrarName = $regUser ? $regUser['username'] : 'Authorized Registrar';
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
logAudit(getCurrentUserId(), 'PRINT', 'enrollments', $studentId, null, 'Generated official Certificate of Registration (COR) for student: ' . ($student['student_no'] ?? $studentId));

// Get current academic settings
$currentSemester = getSetting('current_semester', '1st');
$academicYear = getSetting('academic_year', '2024-2025');
$userId = getCurrentUserId();

// Record COR generation
$stmt = $conn->prepare("INSERT INTO cors (student_id, semester, school_year, generated_by) VALUES (?, ?, ?, ?)");
$stmt->bind_param("issi", $studentId, $currentSemester, $academicYear, $userId);
$stmt->execute();
$corId = $stmt->insert_id;
$stmt->close();

// Generate Verification Hash (using a secret salt)
$vHash = hash('sha256', 'BCAT_COR_' . $corId);

// Construct Verification URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$verifyUrl = "$protocol://$host/verify.php?cid=$corId&v=$vHash";

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
        cs.room
    FROM enrollments e
    JOIN class_sections cs ON e.section_id = cs.section_id
    JOIN courses c ON cs.course_id = c.course_id
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
if ($enrollments_res->num_rows > 0) {
    while ($row = $enrollments_res->fetch_assoc()) {
        $enrollments[] = $row;
    }
}
else {
    // Audit check: Check if student has ANY enrollments at all to diagnose zero-units issue
    $check_res = $conn->query("SELECT COUNT(*) as total FROM enrollments WHERE student_id = $studentId");
    $has_any = $check_res->fetch_assoc()['total'] > 0;
    if ($has_any) {
        $debug_msg = "Note: Student has enrollments, but not for the current cycle ($currentSemester, $academicYear).";
    }
}
$stmt->close();
$periodTitle = $currentSemester . " SEMESTER, SY " . $academicYear;

$fullName = strtoupper($student['last_name'] . ', ' . $student['first_name'] . ' ' . ($student['middle_name'] ?? ''));
$schoolName = getSetting('school_name', 'BALICUATRO COLLEGE OF ARTS AND TRADES');
$schoolRegion = getSetting('school_region', 'Region VIII');
$schoolAddress = getSetting('school_address', 'Allen, Northern Samar');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>COR - <?php echo htmlspecialchars($student['student_no']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f1f5f9; padding: 20px; font-family: 'Inter', sans-serif; }
        .cor-container {
            max-width: 850px;
            margin: auto;
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .header-logo img { max-height: 80px; }
        .official-title { font-weight: 800; letter-spacing: 1px; border-bottom: 2px solid #0d6efd; display: inline-block; padding-bottom: 5px; }
        .info-label { font-weight: 600; color: #64748b; width: 120px; display: inline-block; }
        .info-value { font-weight: 700; border-bottom: 1px solid #e2e8f0; flex-grow: 1; }
        .table thead th { background: #2d3748; color: white; font-size: 0.75rem; text-transform: uppercase; padding: 6px 4px; border: none; }
        .table td { font-size: 0.85rem; padding: 4px 6px; }
        .table-record { font-size: 8pt !important; table-layout: fixed; width: 100%; }
        .table-record thead th { background: #2d3748 !important; color: white !important; font-size: 7.5pt !important; padding: 4px 6px !important; text-transform: uppercase; border: 1px solid #2d3748 !important; }
        .table-record td { padding: 3px 6px !important; vertical-align: middle; word-wrap: break-word; overflow-wrap: break-word; }
        @media print {
            @page {
                margin: 10mm;
                size: A4 portrait;
            }

            * {
                box-sizing: border-box !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            body { background: white !important; padding: 0 !important; margin: 0 !important; font-size: 10pt; width: auto !important; max-width: 100% !important; }
            .cor-container { 
                box-shadow: none !important; 
                border: none !important; 
                width: 100% !important; 
                max-width: 100% !important; 
                margin: 0 !important;
                padding: 10mm !important; /* Added internal padding instead of body margin */
            }
            .no-print { display: none !important; }
            .official-title { font-size: 1.15rem !important; border-bottom: 2px solid #000 !important; }
            .table { font-size: 8pt !important; width: 100% !important; }
            .table thead th { padding: 4px 6px !important; background: #2d3748 !important; color: white !important; }
            .table td { padding: 3px 6px !important; }
            .mt-3 { margin-top: 0.75rem !important; }
            
            @page {
                margin: 0;
                size: A4 portrait;
            }
    </style>
</head>
<body>
    <div class="no-print text-end mb-3">
        <button class="btn btn-secondary" onclick="window.close()">Close</button>
        <button class="btn btn-primary" onclick="window.print()">Print COR</button>
    </div>

    <div class="cor-container">
        <div class="d-flex justify-content-center align-items-center mb-2 text-center">
            <img src="../BCAT logo 2024.png" alt="Logo" style="max-height: 75px; margin-right: 15px;">
            <div style="max-width: 600px;">
                <h6 class="mb-0 text-uppercase" style="font-size: 0.75rem;">Republic of the Philippines</h6>
                <h6 class="mb-0 fw-bold" style="font-size: 0.85rem;">Technical Education and Skills Development Authority</h6>
                <h5 class="mb-0 fw-bold mt-1" style="font-size: 1rem;"><?php echo htmlspecialchars($schoolName); ?></h5>
                <small class="text-muted" style="font-size: 0.75rem;"><?php echo htmlspecialchars($schoolAddress); ?></small>
            </div>
            <img src="../tesda_logo.png" alt="TESDA" style="max-height: 75px; margin-left: 15px;">
        </div>

        <div class="text-center my-2">
            <h4 class="official-title" style="font-size: 1.15rem;">CERTIFICATE OF REGISTRATION</h4>
            <p class="text-muted fw-bold mb-0" style="font-size: 0.8rem;"><?php echo htmlspecialchars($periodTitle); ?></p>
        </div>

        <div class="row mb-2 g-2">
            <div class="col-md-7">
                <div class="d-flex mb-1"><span class="info-label">Name:</span><span class="info-value"><?php echo $fullName; ?></span></div>
                <div class="d-flex mb-1"><span class="info-label">Student No:</span><span class="info-value"><?php echo htmlspecialchars($student['student_no']); ?></span></div>
                <div class="d-flex mb-1"><span class="info-label">Program:</span><span class="info-value"><?php echo htmlspecialchars($student['program_name'] ?? 'N/A'); ?></span></div>
            </div>
            <div class="col-md-5">
                <div class="d-flex mb-1"><span class="info-label">College / DP:</span><span class="info-value"><?php echo htmlspecialchars(($student['college_name'] ? $student['college_name'] . ' - ' : '') . ($student['dept_name'] ?? 'N/A')); ?></span></div>
                <div class="d-flex mb-1"><span class="info-label">Year Level:</span><span class="info-value"><?php echo htmlspecialchars($student['year_level']); ?></span></div>
                <div class="d-flex mb-1"><span class="info-label">Date:</span><span class="info-value"><?php echo date('M d, Y'); ?></span></div>
            </div>
        </div>

        <table class="table table-bordered mt-2 table-record">
            <thead>
                <tr>
                    <th width="12%">Class Code</th>
                    <th width="15%">Course Code</th>
                    <th width="40%">Subject Description</th>
                    <th width="23%">Schedule / Room</th>
                    <th class="text-center" width="10%">Units</th>
                </tr>
            </thead>
            <tbody>
                <?php
$totalUnits = 0;
if (!empty($enrollments)):
    foreach ($enrollments as $e):
        $totalUnits += $e['units'];
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
                </tr>
                <?php
    endforeach; ?>
                <tr class="fw-bold bg-light">
                    <td colspan="4" class="text-end">Total Units:</td>
                    <td class="text-center"><?php echo $totalUnits; ?></td>
                </tr>
                <?php
    $gwa = calculateGWA($studentId);
    $hasBacklog = hasAcademicBacklog($studentId);
    $honors = $student['academic_honor']; // Use manually assigned honor
?>
                <tr class="fw-bold">
                    <td colspan="4" class="text-end bg-primary text-white">General Weighted Average (GWA):</td>
                    <td class="text-center bg-primary text-white"><?php echo $gwa !== null ? number_format($gwa, 2) : '0.00'; ?></td>
                </tr>
                <?php if (!$hasBacklog && $honors): ?>
                <tr class="fw-bold">
                    <td colspan="4" class="text-end bg-success text-white">Academic Honor:</td>
                    <td class="text-center bg-success text-white"><i class="fas fa-medal me-1"></i> <?php echo htmlspecialchars($honors); ?></td>
                </tr>
                <?php endif; ?>
                <?php
else: ?>
                <tr><td colspan="4" class="text-center py-4 text-muted">No active enrollment found for this semester.</td></tr>
                <?php
endif; ?>
            </tbody>
        </table>

        <div class="mt-3">
            <div class="row align-items-center">
                <div class="col-8">
                    <div class="d-flex align-items-center mb-0">
                        <div class="me-3 p-1 bg-white border rounded">
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=<?php echo urlencode($verifyUrl); ?>" alt="Verification QR" width="90">
                        </div>
                        <div>
                            <div class="fw-bold text-dark" style="font-size: 0.75rem;">DOCUMENT VERIFICATION</div>
                            <div class="text-muted" style="font-size: 0.65rem;">Scan this QR code to verify the authenticity of this official Certificate of Registration.</div>
                            <div class="mt-1 fw-bold text-primary" style="font-size: 0.6rem;">REF ID: COR-<?php echo str_pad($corId, 8, '0', STR_PAD_LEFT); ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-4">
                    <div style="font-size: 7.5pt; color: #718096; line-height: 1.4; text-align: right;">
                        Authorized Signature<br>
                        Date: <?php echo date('M d, Y h:i A'); ?><br>
                        <em>Not valid without dry seal.</em>
                    </div>
                </div>
            </div>
            
            <div class="row mt-4">
                <div class="col-6 text-center">
                    <div style="border-bottom: 1.5px solid #1e293b; width: 80%; margin: 40px auto 4px auto;"></div>
                    <div class="fw-bold text-uppercase" style="font-size: 0.9rem;"><?php echo $fullName; ?></div>
                    <small class="text-muted">Student Signature</small>
                </div>
                <div class="col-6 text-center">
                    <div style="border-bottom: 1.5px solid #1e293b; width: 80%; margin: 40px auto 4px auto;"></div>
                    <div class="fw-bold text-uppercase" style="font-size: 0.9rem;"><?php echo htmlspecialchars($registrarName); ?></div>
                    <small class="text-muted"><?php echo htmlspecialchars($registrarPosition); ?> / Authorized Officer</small>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
