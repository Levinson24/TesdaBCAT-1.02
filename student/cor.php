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
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $baseDir = rtrim(dirname(dirname($_SERVER['PHP_SELF'])), '/\\');
    $verifyUrl = "$protocol://$host$baseDir/verify.php?cid=$corId&v=$vHash";
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

$additionalCSS = <<<'CSS'
<style>
/* Document Layout Styles */
.transcript-container {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    max-width: 950px;
    margin: 30px auto;
    color: #1a202c;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
    border-radius: 0.75rem;
    padding: 40px;
    position: relative;
    min-height: 297mm;
    display: flex;
    flex-direction: column;
    overflow: visible;
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
    background-color: var(--primary-color, #0d6efd);
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

.watermark {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 500px;
    height: auto;
    opacity: 0.05;
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
    background: #2d3748 !important;
    color: white !important;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.75rem;
    padding: 12px 8px;
    border: none;
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
    padding: 8mm !important; /* Slightly reduced margin to save space */
    border: none !important;
    background: white !important;
    width: 210mm !important;
    max-width: 210mm !important;
    position: relative !important;
    box-sizing: border-box !important;
}

.generating-pdf .transcript-container {
    border: none !important;
    box-shadow: none !important;
    padding: 0 !important;
    width: 100% !important;
}

.generating-pdf .transcript-table td, 
.generating-pdf .transcript-table th {
    padding: 6px 4px !important; /* Compacter table rows in PDF */
    font-size: 0.8rem !important;
}

.generating-pdf .transcript-header {
    margin-bottom: 15px !important;
}

    @media print {
        @page {
            size: auto;
            margin: 5mm;
        }
        
        body {
            background: white !important;
            font-size: 11pt !important;
            width: auto !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        
        .transcript-container {
            border: none !important;
            box-shadow: none !important;
            padding: 8mm 12mm !important;
            margin: 0 !important;
            width: 100% !important;
            max-width: 8.5in !important;
            height: auto !important; /* Letter adaptive height */
            min-height: 100% !important;
            border-radius: 0 !important;
            display: flex !important;
            flex-direction: column !important;
        }
        .official-title { font-size: 1.2rem !important; }
        .info-label { width: 110px !important; font-size: 10.5pt !important; }
        .info-value { font-size: 10.5pt !important; border-bottom: 1px dotted #cbd5e0 !important; }
        .table thead th { background: #2d3748 !important; color: white !important; padding: 8px 6px !important; }
        .table td { padding: 6px 6px !important; }
    }
</style>
CSS;

require_once '../includes/header.php';

// Get current grades for the COR
$grades = $conn->prepare("
    SELECT 
        cs.school_year,
        cs.semester,
        c.class_code,
        c.course_code,
        c.course_name,
        c.course_type,
        c.units,
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
    AND cs.semester = ? 
    AND cs.school_year = ?
    ORDER BY c.course_code ASC
");
$grades->bind_param("iss", $studentId, $currentSemester, $academicYear);
$grades->execute();
$grades_res = $grades->get_result();

$currentGrades = [];
while ($grade = $grades_res->fetch_assoc()) {
    $currentGrades[] = $grade;
}
$grades->close();

// --- Pagination Logic (Dynamic 10-row Chunks) ---
$rowsPerPage = 8; 
$pages = array_chunk($currentGrades, $rowsPerPage);
if (empty($pages)) $pages = [[]]; // Ensure at least one page for empty state
$totalPageCount = count($pages);

$gwa = calculateGWA($studentId);
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<div class="row mb-4 no-print-pdf">
    <div class="col-12 text-center">
        <a href="dashboard.php" class="btn btn-secondary px-4 py-2 shadow-sm rounded-pill me-2">
            <i class="fas fa-arrow-left me-2"></i>Back
        </a>
        <button onclick="downloadPDF()" class="btn btn-success px-4 py-2 shadow-sm rounded-pill me-2">
            <i class="fas fa-file-pdf me-2"></i>Download Official COR
        </button>
        <button onclick="window.print()" class="btn btn-outline-primary px-4 py-2 shadow-sm rounded-pill">
            <i class="fas fa-print me-2"></i>Print
        </button>
    </div>
</div>

<?php foreach ($pages as $pIndex => $pageGrades): 
    $pageNumber = $pIndex + 1;
    $isLastPage = ($pageNumber === $totalPageCount);
?>
<div class="transcript-container shadow-sm p-4 p-md-5 mb-5 bg-white rounded <?php echo $pageNumber > 1 ? 'mt-4' : ''; ?>" id="corCard<?php echo $pageNumber === 1 ? '' : $pageNumber; ?>" style="<?php echo $pageNumber < $totalPageCount ? 'page-break-after: always;' : ''; ?>; min-height: 297mm; display: flex; flex-direction: column; position: relative;">
    <img src="../BCAT logo 2024.png" class="watermark" alt="BCAT Watermark">
    <!-- Header Information (Image 4 Frame) -->
    <div class="d-flex justify-content-between align-items-center mb-0 transcript-header">
        <div class="header-logo text-start">
            <img src="../BCAT logo 2024.png" alt="Logo Left" class="img-fluid" style="max-height: 80px;">
        </div>
        <div class="header-text text-center mx-3" style="flex: 1;">
            <h6 class="mb-0 text-uppercase fw-normal small" style="letter-spacing: 1px; color: #64748b;">Republic of the Philippines</h6>
            <h6 class="mb-1 fw-bold" style="font-size: 0.85rem; color: #1e293b;">TECHNICAL EDUCATION AND SKILLS DEVELOPMENT AUTHORITY</h6>
            <h6 class="mb-0 text-muted small"><?php echo getSetting('school_region', 'Region VIII'); ?></h6>
            <h4 class="mb-1 mt-1" style="font-weight: 800; color: #0f172a; letter-spacing: -0.5px;"><?php echo getSetting('school_name', 'BALICUATRO COLLEGE OF ARTS AND TRADES'); ?></h4>
            <h6 class="mb-0 text-muted small"><?php echo getSetting('school_address', 'Allen, Northern Samar'); ?></h6>
        </div>
        <div class="header-logo text-end">
            <img src="../tesda_logo.png" alt="TESDA Logo" class="img-fluid" style="max-height: 80px;">
        </div>
    </div>
    <div style="border-top: 2px solid #0d6efd; border-bottom: 1px solid #0d6efd; height: 4px; margin: 10px 0 15px 0;"></div>
    
    <?php if ($totalPageCount > 1): ?>
        <div class="text-end w-100" style="margin-top: -10px; margin-bottom: -5px;">
            <span class="fw-bold text-muted" style="font-size: 0.75rem; letter-spacing: 0.5px;">PAGE <?php echo $pageNumber; ?> OF <?php echo $totalPageCount; ?></span>
        </div>
    <?php endif; ?>

    <div class="text-center my-3">
        <h5 class="official-title" style="font-size: 1.1rem;">CERTIFICATE OF REGISTRATION (COR)</h5>
        <div class="title-underline mx-auto"></div>
        <p class="mt-1 fw-bold text-primary" style="font-size: 0.85rem;"><?php echo strtoupper($currentSemester . " Semester, SY " . $academicYear); ?></p>
    </div>
    
    <!-- Student Info Block (Persistent Frame) -->
    <div class="row mb-3 g-3">
        <div class="col-6">
            <div class="info-group mb-1 d-flex">
                <span class="info-label" style="width: 100px;">Name:</span>
                <span class="info-value text-uppercase"><?php echo htmlspecialchars($student['last_name'] . ', ' . $student['first_name'] . ' ' . ($student['middle_name'] ?? '')); ?></span>
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
    
    <!-- Subjects Selection -->
    <?php if (!empty($pageGrades)): ?>
        <div class="transcript-table-wrapper">
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
    <?php else: ?>
        <div class="alert alert-warning text-center py-5">
            <h5 class="fw-bold">No Records Found</h5>
            <p>No enrollment records found for this page.</p>
        </div>
    <?php endif; ?>

    <!-- FOOTER FRAME (Image 3) - Anchored to bottom -->
    <div style="flex-grow: 1;"></div>
    <div class="pt-4">
        <?php if ($isLastPage): ?>
        <div class="row align-items-center border-top pt-3 mb-4">
            <?php if ($corId > 0): ?>
            <div class="col-md-7 text-start">
                <div class="d-flex align-items-center p-3 border rounded bg-light shadow-sm">
                    <div class="me-3 p-1 bg-white border rounded">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=<?php echo urlencode($verifyUrl); ?>" alt="Verification QR" width="70">
                    </div>
                    <div>
                        <div class="fw-bold text-dark small">DOCUMENT VERIFICATION</div>
                        <div class="text-muted small" style="font-size: 0.6rem;">Scan this QR to verify authenticity.</div>
                        <div class="fw-bold text-primary small">REF ID: COR-<?php echo str_pad($corId, 8, '0', STR_PAD_LEFT); ?></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <div class="col-md-5">
                <div class="card bg-primary text-white shadow-sm">
                    <div class="card-body p-2 text-center">
                        <?php
                        $finalTotalUnits = 0;
                        foreach($currentGrades as $item) $finalTotalUnits += $item['units'];
                        ?>
                        <div class="small text-white-50">Total Enrolled Units</div>
                        <div class="h4 mb-0 fw-bold"><?php echo $finalTotalUnits; ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-3 g-2">
            <div class="col-4 text-center">
                <div style="border-bottom: 2px solid #1e293b; width: 85%; margin: 35px auto 6px auto;"></div>
                <div class="fw-bold text-uppercase" style="font-size: 0.70rem;"><?php echo htmlspecialchars($student['last_name'] . ', ' . $student['first_name'] . ' ' . ($student['middle_name'] ?? '')); ?></div>
                <div class="text-muted" style="font-size: 0.65rem;">Student Signature</div>
            </div>
            <div class="col-4 text-center">
                <div style="border-bottom: 2px solid #1e293b; width: 85%; margin: 35px auto 6px auto;"></div>
                <div class="fw-bold text-uppercase" style="font-size: 0.70rem;"><?php echo htmlspecialchars($deptHeadName); ?></div>
                <div class="text-muted" style="font-size: 0.65rem;">Department Head / Dean</div>
            </div>
            <div class="col-4 text-center">
                <div style="border-bottom: 2px solid #1e293b; width: 85%; margin: 35px auto 6px auto;"></div>
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

        <div class="d-flex justify-content-center align-items-center text-center text-muted small border-top pt-2 mt-2">
            <div style="width: 50px; height: 50px; border: 1.5px dashed #cbd5e0; display: flex; align-items: center; justify-content: center; font-size: 0.55rem; font-weight: bold; color: #94a3b8; margin-right: 12px; border-radius: 4px; line-height: 1.1;">
                DRY<br>SEAL
            </div>
            <div class="text-start">
                <strong>REMINDER:</strong> NOT VALID without official dry seal.<br>
                <span>Date Generated: <?php echo date('M d, Y h:i A'); ?></span>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<script>
function downloadPDF() {
    const element = document.getElementById('corCard');
    const studentNo = '<?php echo $student['student_no']; ?>';
    
    // Add specific class for PDF generation to override layout
    element.classList.add('generating-pdf');
    
    const opt = {
        margin: 0,
        filename: 'COR_' + studentNo + '.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { 
            scale: 2, 
            useCORS: true,
            letterRendering: true,
            scrollY: 0,
            width: 794 // 210mm at 96 DPI
        },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
        pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
    };

    html2pdf().set(opt).from(element).save().then(() => {
        element.classList.remove('generating-pdf');
        
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
