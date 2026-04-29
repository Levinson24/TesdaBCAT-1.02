<?php
/**
 * Student Certificate of Registration (COR)
 * TESDA-BCAT Grade Management System
 */
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('student');
$conn = getDBConnection();
$userId = getCurrentUserId();

// Get student profile
$stmt = $conn->prepare("
    SELECT s.*, d.title_diploma_program as dept_name, p.program_name, col.college_name
    FROM students s 
    LEFT JOIN departments d ON s.dept_id = d.dept_id 
    LEFT JOIN colleges col ON d.college_id = col.college_id
    LEFT JOIN programs p ON s.program_id = p.program_id
    WHERE s.user_id = ?
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    redirectWithMessage('dashboard.php', 'Student profile not found.', 'danger');
}

$studentId = $student['student_id'];

// Log audit action
logAudit($userId, 'VIEW_COR', 'enrollments', $studentId, null, 'Viewed personal Certificate of Registration (COR)');

$pageTitle = 'Certificate of Registration (COR)';

// Get current academic settings
$currentSemester = getSetting('current_semester', '1st');
$academicYear = getSetting('academic_year', '2024-2025');

// Record COR generation (only if student is actually enrolled)
$checkStmt = $conn->prepare("SELECT COUNT(*) as total FROM enrollments e JOIN class_sections cs ON e.section_id = cs.section_id WHERE e.student_id = ? AND cs.semester = ? AND cs.school_year = ?");
$checkStmt->bind_param("iss", $studentId, $currentSemester, $academicYear);
$checkStmt->execute();
$isEnrolled = $checkStmt->get_result()->fetch_assoc()['total'] > 0;
$checkStmt->close();

$corId = 0;
$verifyUrl = "";
if ($isEnrolled) {
    $stmt = $conn->prepare("INSERT INTO cors (student_id, semester, school_year, generated_by) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("issi", $studentId, $currentSemester, $academicYear, $userId);
    $stmt->execute();
    $corId = $stmt->insert_id;
    $stmt->close();

    // Generate Verification Hash
    $vHash = hash('sha256', 'BCAT_COR_' . $corId);
    $verifyUrl = BASE_URL . "verify.php?cid=$corId&v=$vHash";
}

// Fetch the Registrar's name
$regStmt = $conn->prepare("SELECT username FROM users WHERE role = 'registrar' LIMIT 1");
$regStmt->execute();
$regUser = $regStmt->get_result()->fetch_assoc();
$registrarName = $regUser ? strtoupper($regUser['username']) : 'AUTHORIZED REGISTRAR';
$registrarPosition = getSetting('registrar_position', 'Registrar');
$regStmt->close();

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
?>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=Lora:ital,wght@0,600;0,700;1,600&family=Outfit:wght@400;700;800&display=swap" rel="stylesheet">
<?php
$additionalCSS = <<<'CSS'
<style>
/* Document Layout Styles */
.transcript-container {
    font-family: 'Inter', sans-serif; /* Clean baseline */
    width: 285mm;
    height: auto;
    margin: 5px auto;
    color: #1a202c;
    border: 1px solid #e2e8f0;
    border-top: 6px solid #0038A8 !important;
    background: #ffffff;
    padding: 12px 25px; /* Clean balanced padding */
    position: relative;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
}

/* Institutional Security Border */
.inner-border {
    position: absolute;
    top: 6px;
    left: 6px;
    right: 6px;
    bottom: 6px;
    border: 1px solid #0038A8;
    pointer-events: none;
    z-index: 10;
    opacity: 0.2;
}

@media (max-width: 768px) {
    .transcript-container {
        padding: 20px !important;
        margin: 10px auto !important;
        min-height: auto !important;
        border-radius: 0.5rem !important;
    }
}

@media (max-width: 576px) {
    .transcript-header {
        flex-direction: column !important;
        text-align: center !important;
        gap: 15px !important;
    }
    .header-text {
        order: 2 !important;
        margin: 0 !important;
    }
    .header-logo.text-start {
        order: 1 !important;
    }
    .header-logo.text-end {
        order: 3 !important;
    }
    .official-title {
        font-size: 1rem !important;
    }
    .info-group {
        flex-direction: column !important;
    }
    .info-label {
        width: 100% !important;
        margin-bottom: 2px !important;
    }
    .info-value {
        width: 100% !important;
        padding-left: 0 !important;
    }
    
    /* Ensure COR buttons stack and fill width on mobile */
    .no-print-pdf .btn {
        width: 100% !important;
        margin: 5px 0 !important;
    }
}


.official-title {
    font-family: 'Lora', serif; /* Prestige Serif Font */
    font-weight: 700;
    letter-spacing: 1px;
    color: #0f172a;
    margin-bottom: 2px;
}

.title-underline {
    height: 1.5px;
    width: 80px;
    background-color: #0038A8;
}

.info-group-grid {
    border: 1px solid #e2e8f0;
    background: #fdfdfd;
    border-radius: 4px;
}

.info-cell {
    padding: 8px 12px;
    border-right: 1px solid #e2e8f0;
    border-bottom: 1px solid #e2e8f0;
}

.info-cell:last-child {
    border-right: none;
}

.cell-label {
    font-size: 0.6rem;
    font-weight: 800;
    color: #64748b;
    text-transform: uppercase;
    display: block;
    margin-bottom: 1px;
}

.cell-value {
    font-size: 0.85rem;
    font-weight: 700;
    color: #1e293b;
    display: block;
}

.watermark {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 480px;
    height: auto;
    opacity: 0.04; /* Ultra-subtle */
    pointer-events: none;
    z-index: 0;
    user-select: none;
}

.period-header {
    display: flex;
    align-items: center;
    margin-top: 30px;
    margin-bottom: 15px;
    font-weight: 700;
    color: #4a5568;
    text-transform: uppercase;
    font-size: 0.9rem;
}

.period-header span {
    background: #edf2f7;
    padding: 4px 12px;
    border-radius: 4px;
}

.transcript-table {
    font-size: 0.9rem;
}

.transcript-table thead th {
    background: #0f172a !important; /* Deep Navy Night */
    color: white !important;
    font-weight: 700;
    text-transform: uppercase;
    font-size: 0.7rem;
    padding: 8px 10px;
    border: none;
    letter-spacing: 0.5px;
}

.transcript-table tbody td {
    padding: 8px 12px; /* Standard readable padding */
    vertical-align: middle;
    font-size: 0.85rem; /* Standard readable size */
    background: transparent;
    border-color: #f1f5f9 !important;
}

.transcript-table tbody tr:nth-child(even) {
    background-color: #f8fafc !important; /* Zebra striping */
}

.remarks-text {
    font-weight: 700;
    font-size: 0.8rem;
    text-transform: uppercase;
}

/* Hide action buttons in PDF */
.generating-pdf .no-print-pdf {
    display: none !important;
}

/* Ensure no shadows or large margins during capture */
.generating-pdf {
    box-shadow: none !important;
    margin: 0 !important;
    padding: 8mm !important; /* Capture padding for A4 boundary */
    border: none !important;
    background: white !important;
    width: 297mm !important;
    max-width: 297mm !important;
    height: auto !important;
    min-height: auto !important;
    position: relative !important;
    box-sizing: border-box !important;
    overflow: hidden !important;
}

/* Force high-density desktop styles during PDF capture even on mobile */
.generating-pdf .transcript-header {
    display: flex !important;
    flex-direction: row !important;
    text-align: left !important;
    gap: 0 !important;
}

.generating-pdf .header-text {
    order: 0 !important;
    margin: 0 15px !important;
    text-align: center !important;
}

.generating-pdf .info-group {
    display: flex !important;
    flex-direction: row !important;
}

.generating-pdf .info-label {
    width: 110px !important;
}

.generating-pdf .info-value {
    flex: 1 !important;
}

.generating-pdf .transcript-container {
    border: none !important;
    box-shadow: none !important;
    padding: 0 !important;
    width: 100% !important;
}

.generating-pdf .inner-border {
    opacity: 0.4 !important; /* Sharper in print */
}

.generating-pdf .transcript-table td, 
.generating-pdf .transcript-table th {
    padding: 7px 8px !important;
    font-size: 0.8rem !important;
}

    @media print {
        @page {
            margin: 5mm;
        }
        
        /* HIDE ALL DASHBOARD UI */
        .sidebar, 
        .top-navbar, 
        .sidebar-toggle, 
        .sidebar-overlay,
        .no-print-pdf,
        .no-print,
        footer,
        .btn,
        .no-print-pdf *,
        body > nav,
        #sidebar {
            display: none !important;
        }

        html, body {
            width: 100% !important;
            height: auto !important;
            margin: 0 !important;
            padding: 0 !important;
            background: white !important;
            -webkit-print-color-adjust: exact;
        }

        .main-content {
            margin-left: 0 !important;
            padding: 0 !important;
            width: 100% !important;
        }

        .content-area {
            padding: 0 !important;
        }

        .transcript-container {
            border: none !important;
            box-shadow: none !important;
            padding: 5mm !important; 
            margin: 0 auto !important;
            width: 100% !important;
            max-width: 100% !important;
            height: auto !important; 
            border-radius: 0 !important;
            page-break-after: avoid !important;
            page-break-inside: avoid !important;
            overflow: visible !important;
            display: flex !important;
            flex-direction: column !important;
            background: white !important;
            zoom: 1 !important;
        }

        .transcript-table-wrapper {
            flex-grow: 1 !important;
        }

        .inner-border {
            opacity: 0.5 !important;
            bottom: 6px !important;
            height: calc(100% - 12px) !important;
            width: calc(100% - 12px) !important;
        }
        
        .container-fluid { padding: 0 !important; margin: 0 !important; max-width: 100% !important; }
        .official-title { font-size: 1.2rem !important; }
        .info-label { width: 110px !important; font-size: 10.5pt !important; }
        .info-value { font-size: 10.5pt !important; border-bottom: 1px dotted #cbd5e0 !important; }
        .transcript-table thead th { background: #2d3748 !important; color: white !important; padding: 4px 6px !important; font-size: 10pt !important; }
        .transcript-table td { padding: 4px 6px !important; font-size: 10pt !important; }
    }
</style>
CSS;

require_once '../includes/header.php';

// Get current grades for the COR
$grades = $conn->prepare("
    SELECT 
        cs.school_year,
        cs.semester,
        cur.class_code,
        c.subject_id as course_code,
        c.subject_name as course_name,
        'Academic' as course_type,
        c.units,
        g.midterm,
        g.final,
        g.grade,
        g.remarks,
        g.status
    FROM enrollments e
    JOIN class_sections cs ON e.section_id = cs.section_id
    JOIN curriculum cur ON cs.curriculum_id = cur.curriculum_id
    JOIN subjects c ON cur.subject_id = c.subject_id
    LEFT JOIN grades g ON e.enrollment_id = g.enrollment_id
    WHERE e.student_id = ? 
    AND cs.semester = ? 
    AND cs.school_year = ?
    ORDER BY c.subject_id ASC
");
$grades->bind_param("iss", $studentId, $currentSemester, $academicYear);
$grades->execute();
$grades_res = $grades->get_result();

$currentGrades = [];
while ($grade = $grades_res->fetch_assoc()) {
    $currentGrades[] = $grade;
}
$grades->close();

// --- Single-Page Rendering (No Chunking) ---
$pageGrades = $currentGrades;
$totalPageCount = 1;

$finalTotalUnits = 0;
foreach ($currentGrades as $grade) {
    if (is_numeric($grade['units'])) {
        $finalTotalUnits += (float)$grade['units'];
    }
}

$gwa = calculateGWA($studentId);
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<div class="row mb-4 no-print-pdf">
    <div class="col-12 text-center">
        <div class="d-inline-flex flex-wrap justify-content-center gap-2">
            <a href="dashboard.php" class="btn btn-outline-secondary px-4 py-2 shadow-sm rounded-pill">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
            <button onclick="downloadPDF()" class="btn btn-success px-4 py-2 shadow-sm rounded-pill">
                <i class="fas fa-file-pdf me-2"></i>Download Official COR
            </button>
            <button onclick="window.print()" class="btn btn-primary px-4 py-2 shadow-sm rounded-pill">
                <i class="fas fa-print me-2"></i>Print COR
            </button>
        </div>
    </div>
</div>

<div id="corDownloadArea">

<div class="transcript-container bg-white" id="corCard" style="height: auto; display: flex; flex-direction: column; position: relative;">
    <div class="inner-border"></div>
    <img src="../BCAT logo 2024.png" class="watermark" alt="BCAT Watermark">
    <div class="d-flex align-items-center mb-0 transcript-header" style="gap: 20px; padding-bottom: 10px;">
        <img src="../BCAT logo 2024.png" alt="Logo Left" style="max-height: 55px;">
        <div class="header-text text-center" style="flex: 1; line-height: 1.2;">
            <div class="text-uppercase fw-normal x-small" style="letter-spacing: 0.5px; color: #64748b; font-size: 0.6rem;">Republic of the Philippines</div>
            <div class="fw-bold" style="font-size: 0.75rem; color: #1e293b;">TECHNICAL EDUCATION AND SKILLS DEVELOPMENT AUTHORITY</div>
            <div style="font-size: 0.6rem; color: #64748b; font-style: italic;">Region VIII</div>
            <div style="font-weight: 800; color: #0b1120; font-size: 1.15rem; letter-spacing: -0.5px; font-family: 'Lora', serif;"><?php echo getSetting('school_name', 'BALICUATRO COLLEGE OF ARTS AND TRADES'); ?></div>
            <div style="font-size: 0.6rem; color: #64748b;"><?php echo getSetting('school_address', 'Allen, Northern Samar'); ?></div>
        </div>
        <img src="../tesda_logo.png" alt="TESDA Logo" style="max-height: 55px;">
    </div>
    <div style="border-top: 2px solid #0038A8; border-bottom: 2px solid #0038A8; height: 5px; margin: 0 0 10px 0;"></div>
    
    <?php if ($totalPageCount > 1): ?>
        <div class="text-end w-100" style="margin-top: -10px; margin-bottom: -5px;">
            <span class="fw-bold text-muted" style="font-size: 0.75rem; letter-spacing: 0.5px;">PAGE <?php echo $pageNumber; ?> OF <?php echo $totalPageCount; ?></span>
        </div>
    <?php endif; ?>

    <div class="text-center mb-3">
        <div class="text-end w-100 mb-0 no-print-pdf" style="margin-top: -15px;">
            <span class="text-muted" style="font-size: 0.55rem; letter-spacing: 1px;">SERIAL: COR-24-<?php echo str_pad($corId, 6, '0', STR_PAD_LEFT); ?></span>
        </div>
        <h5 class="official-title fw-bold" style="font-size: 1.4rem; margin-bottom: 2px;">CERTIFICATE OF REGISTRATION</h5>
        <div class="title-underline mx-auto"></div>
        <p class="mt-2 fw-bold text-primary" style="font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px;"><?php echo $currentSemester . " Semester, SY " . $academicYear; ?></p>
    </div>
    
    <!-- PREMIUM BOXED GRID DATA -->
    <div class="info-group-grid row g-0 mb-3" style="border-bottom: none;">
        <div class="col-md-3 info-cell">
            <span class="cell-label">Student Name</span>
            <span class="cell-value"><?php echo strtoupper(htmlspecialchars($student['last_name'] . ', ' . $student['first_name'])); ?></span>
        </div>
        <div class="col-md-2 info-cell">
            <span class="cell-label">Student Number</span>
            <span class="cell-value"><?php echo htmlspecialchars($student['student_no']); ?></span>
        </div>
        <div class="col-md-4 info-cell">
            <span class="cell-label">Qualification / Program enrolled</span>
            <span class="cell-value" style="font-size: 0.75rem;"><?php echo htmlspecialchars($student['program_name'] ?? 'N/A'); ?></span>
        </div>
        <div class="col-md-1 info-cell">
            <span class="cell-label">Year</span>
            <span class="cell-value"><?php echo htmlspecialchars($student['year_level']); ?></span>
        </div>
        <div class="col-md-2 info-cell" style="border-right: none;">
            <span class="cell-label">Date of Issuance</span>
            <span class="cell-value"><?php echo date('M d, Y'); ?></span>
        </div>
    </div>

    <!-- Mobile Student Info (Card Style) -->
    <div class="d-block d-sm-none mb-3">
        <div class="card p-3 bg-light border-0 rounded-4">
            <div class="row g-2">
                <div class="col-6">
                    <div class="x-small text-muted text-uppercase fw-bold">ID Number</div>
                    <div class="small fw-bold"><?php echo htmlspecialchars($student['student_no']); ?></div>
                </div>
                <div class="col-6 text-end">
                    <div class="x-small text-muted text-uppercase fw-bold">Year Level</div>
                    <div class="small fw-bold"><?php echo htmlspecialchars($student['year_level']); ?></div>
                </div>
                <div class="col-12 mt-2">
                    <div class="x-small text-muted text-uppercase fw-bold">Program</div>
                    <div class="small fw-bold text-primary"><?php echo htmlspecialchars($student['program_name'] ?? 'N/A'); ?></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Subjects Selection -->
    <?php if (!empty($pageGrades)): ?>
        <!-- Desktop Table view -->
        <div class="transcript-table-wrapper d-none d-sm-block">
            <table class="table table-bordered transcript-table">
                <thead>
                    <tr class="bg-dark text-white">
                        <th width="15%">Class Code</th>
                        <th width="15%">Subject Code</th>
                        <th width="40%">Subject Description</th>
                        <th width="10%" class="text-center">Units</th>
                        <th width="20%" class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    foreach ($pageGrades as $grade):
                    ?>
                    <tr>
                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($grade['class_code'] ?? 'N/A'); ?></span></td>
                        <td><strong><?php echo htmlspecialchars($grade['course_code']); ?></strong></td>
                        <td><?php echo htmlspecialchars($grade['course_name']); ?></td>
                        <td class="text-center"><?php echo $grade['units']; ?></td>
                        <td class="text-center text-uppercase small fw-bold">
                            <?php echo htmlspecialchars($grade['remarks'] ?? 'ENROLLED'); ?>
                        </td>
                    </tr>
                    <?php
                    endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile Card View -->
        <div class="d-block d-sm-none">
            <?php foreach ($pageGrades as $grade): ?>
                <div class="card subject-card-mobile p-3 mb-2 shadow-none border">
                    <div class="subject-header-mobile">
                        <div class="subject-name-mobile"><?php echo htmlspecialchars($grade['course_name']); ?></div>
                        <div class="subject-grade-mobile" style="font-size: 14px; opacity: 0.7;">
                            <?php echo $grade['units']; ?>u
                        </div>
                    </div>
                    <div class="subject-info-mobile">
                        <?php echo htmlspecialchars($grade['course_code']); ?> | <?php echo htmlspecialchars($grade['class_code'] ?? 'N/A'); ?>
                    </div>
                    <div class="subject-footer-mobile pt-1">
                        <span class="remark" style="font-size: 10px; color: var(--primary-color);">
                            <?php echo htmlspecialchars($grade['remarks'] ?? 'ENROLLED'); ?>
                        </span>
                        <div class="x-small text-muted"><i class="fas fa-check-circle me-1"></i>Official</div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-warning text-center py-5">
            <h5 class="fw-bold">No Records Found</h5>
            <p>No enrollment records found for this page.</p>
        </div>
    <?php endif; ?>

    <!-- BALANCED FOOTER & SIGNATURES -->
    <div class="pt-3 mt-2 border-top">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <?php if ($corId > 0): ?>
            <div class="d-flex align-items-center p-2 border rounded bg-light" style="font-size: 0.7rem; min-width: 40%;">
                <div class="bg-white p-1 border rounded me-3 line-height-0">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=<?php echo urlencode($verifyUrl); ?>" alt="Verification QR" width="65">
                </div>
                <div>
                    <div class="fw-bold text-dark mb-1">DOCUMENT VERIFICATION</div>
                    <div class="text-muted mb-1" style="font-size: 0.6rem;">Scan to verify this document's authenticity.</div>
                    <div class="fw-bold text-primary" style="font-size: 0.8rem;">REF: COR-<?php echo str_pad($corId, 8, '0', STR_PAD_LEFT); ?></div>
                </div>
            </div>
            <?php endif; ?>
            <div class="bg-primary text-white p-3 rounded-4 shadow-sm" style="min-width: 220px;">
                <div class="text-center">
                    <div class="text-white-50 x-small text-uppercase fw-bold mb-1" style="font-size: 0.6rem;">Total Enrolled Units</div>
                    <div class="h3 mb-0 fw-bold"><?php echo $finalTotalUnits; ?></div>
                </div>
            </div>
        </div>

        <div class="row mt-4 g-4" style="font-size: 0.85rem;">
            <div class="col-4 text-center">
                <div style="border-bottom: 2px solid #0038A8; width: 90%; margin: 0 auto 8px auto;"></div>
                <div class="fw-bold text-uppercase"><?php echo htmlspecialchars($student['last_name'] . ', ' . $student['first_name']); ?></div>
                <div class="text-muted small">Student Signature</div>
            </div>
            <div class="col-4 text-center">
                <div style="border-bottom: 2px solid #0038A8; width: 90%; margin: 0 auto 8px auto;"></div>
                <div class="fw-bold text-uppercase"><?php echo htmlspecialchars($deptHeadName); ?></div>
                <div class="text-muted small">Department Head / Dean</div>
            </div>
            <div class="col-4 text-center">
                <div style="border-bottom: 2px solid #0038A8; width: 90%; margin: 0 auto 8px auto;"></div>
                <div class="fw-bold text-uppercase"><?php echo htmlspecialchars($registrarName); ?></div>
                <div class="text-muted small"><?php echo htmlspecialchars($registrarPosition); ?></div>
                <div class="text-muted mt-1" style="font-size: 0.45rem; opacity: 0.7;">Generated: <?php echo date('M d, Y h:i A'); ?></div>
            </div>
        </div>
    </div>
</div>
</div>

<script>
function downloadPDF() {
    const element = document.getElementById('corDownloadArea');
    const card = document.getElementById('corCard');
    const studentNo = '<?php echo $student['student_no']; ?>';
    
    // Add PDF capture class
    card.classList.add('generating-pdf');
    
    // SMART SCALER: Measure height and shrink if needed
    // A4 Landscape limit is approx 780-800px at common 96dpi
    const height = card.scrollHeight;
    const limit = 780; 
    let scale = 1.0;
    
    if (height > limit) {
        scale = limit / height;
        card.style.transform = `scale(${scale})`; // Apply dynamic shrink
        card.style.transformOrigin = "top center";
        card.style.width = "calc(100% / " + scale + ")"; // Compensate width for scale
    }

    const opt = {
        margin: [5, 5, 5, 5],
        filename: 'BCAT_COR_' + studentNo + '_' + Date.now() + '.pdf',
        image: { type: 'jpeg', quality: 1.0 },
        html2canvas: { 
            scale: 3, // Ultra-high resolution capture
            useCORS: true,
            letterRendering: true,
            logging: false,
            width: 1210
        },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'landscape', compress: true },
        pagebreak: { mode: 'avoid-all' }
    };

    html2pdf().set(opt).from(element).save().then(() => {
        // Cleanup: Reset scaling and classes
        card.classList.remove('generating-pdf');
        card.style.transform = "none";
        card.style.transformOrigin = "initial";
        card.style.width = "100%";
        
        // Track the download
        fetch('../includes/ajax/track_download.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                'action': 'DOWNLOAD_COR',
                'type': 'cor',
                'target_id': '<?php echo $studentId; ?>',
                'details': 'Student downloaded personal COR PDF'
            })
        });
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>
