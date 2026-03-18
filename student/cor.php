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
logAudit($userId, 'VIEW', 'enrollments', $studentId, null, 'Viewed/Generated personal Certificate of Registration (COR)');

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
    $verifyUrl = "$protocol://$host/verify.php?cid=$corId&v=$vHash";
}

$additionalCSS = <<<'CSS'
<style>
/* Document Layout Styles */
.transcript-container {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    max-width: 1000px;
    margin: 20px auto;
    color: #1a202c;
    border: 1px solid #e2e8f0;
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
    color: #2d3748;
    border-bottom: 1px dotted #cbd5e0;
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
    .sidebar, .top-navbar, .action-buttons, .alert, .mt-4.text-center, .content-area > .flash-message, .btn, .footer {
        display: none !important;
    }
    
    body {
        background: white !important;
        font-size: 10pt !important; /* Slightly smaller base font for print */
        width: auto !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    
    .transcript-container {
        border: none !important;
        box-shadow: none !important;
        padding: 8mm !important; /* Internal margins for print */
        margin: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
    }

    .transcript-table { font-size: 9pt !important; }
    .transcript-table td, .transcript-table th { padding: 4px 3px !important; }
    
    .my-3 { margin-top: 1rem !important; margin-bottom: 1rem !important; }
    .mb-3 { margin-bottom: 0.75rem !important; }
    .mt-3 { margin-top: 0.75rem !important; }

    @page {
        size: A4 portrait;
        margin: 0; /* Let the container padding handle margins */
    }
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

$gwa = calculateGWA($studentId);
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<div class="transcript-container shadow-sm p-4 p-md-5 mb-5 bg-white rounded" id="corCard">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 transcript-header">
        <div class="header-logo text-start">
            <img src="../BCAT logo 2024.png" alt="Logo Left" class="img-fluid" style="max-height: <?php echo getSetting('logo_size', '120'); ?>px;">
        </div>
        <div class="header-text text-center flex-grow-1 mx-3">
            <h6 class="mb-1 text-uppercase fw-normal">Republic of the Philippines</h6>
            <h6 class="mb-1 fw-bold">Technical Education and Skills Development Authority</h6>
            <h4 class="mb-1 mt-2"><strong><?php echo getSetting('school_name', 'BALICUATRO COLLEGE OF ARTS AND TRADES'); ?></strong></h4>
            <h6 class="mb-0 text-muted"><?php echo getSetting('school_address', 'Allen, Northern Samar'); ?></h6>
        </div>
        <div class="header-logo text-end">
            <img src="../tesda_logo.png" alt="TESDA Logo" class="img-fluid" style="max-height: <?php echo getSetting('logo_size', '120'); ?>px;">
        </div>
    </div>
    
    <div class="text-center my-3">
        <h5 class="official-title" style="font-size: 1.1rem;">CERTIFICATE OF REGISTRATION (COR)</h5>
        <div class="title-underline mx-auto"></div>
        <p class="mt-1 fw-bold text-primary" style="font-size: 0.85rem;"><?php echo strtoupper($currentSemester . " Semester, SY " . $academicYear); ?></p>
    </div>
    
    <!-- Student Info -->
    <div class="row mb-3 student-info-grid">
        <div class="col-md-6">
            <div class="info-group">
                <span class="info-label">Name:</span>
                <span class="info-value text-uppercase"><?php echo htmlspecialchars($student['last_name'] . ', ' . $student['first_name'] . ' ' . ($student['middle_name'] ?? '')); ?></span>
            </div>
            <div class="info-group">
                <span class="info-label">Student No:</span>
                <span class="info-value"><?php echo htmlspecialchars($student['student_no']); ?></span>
            </div>
        </div>
        <div class="col-md-6">
            <div class="info-group">
                <span class="info-label">Program:</span>
                <span class="info-value"><?php echo htmlspecialchars($student['program_name'] ?? 'N/A'); ?></span>
            </div>
            <div class="info-group">
                <span class="info-label">Year Level:</span>
                <span class="info-value"><?php echo htmlspecialchars($student['year_level']); ?></span>
            </div>
        </div>
    </div>
    
    <!-- Subjects -->
    <?php if (!empty($currentGrades)): ?>
        <div class="table-responsive">
            <table class="table table-bordered transcript-table">
                <thead>
                    <tr>
                        <th width="15%">Class Code</th>
                        <th width="15%">Subject Code</th>
                        <th width="40%">Subject Description</th>
                        <th width="10%" class="text-center">Units</th>
                        <th width="20%" class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
    $totalUnits = 0;
    foreach ($currentGrades as $grade):
        $totalUnits += $grade['units'];
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
                <tfoot>
                    <tr class="table-light">
                        <td colspan="3" class="text-end fw-bold">Period Total Units:</td>
                        <td class="text-center fw-bold"><?php echo $totalUnits; ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <div class="mt-5 text-center no-print-pdf">
            <button onclick="downloadPDF()" class="btn btn-success btn-lg px-4 shadow">
                <i class="fas fa-file-pdf me-2"></i>Download PDF
            </button>
            <button onclick="window.print()" class="btn btn-primary btn-lg px-4 shadow ms-2">
                <i class="fas fa-print me-2"></i>Print COR
            </button>
        </div>
    <?php
else: ?>
        <div class="alert alert-warning text-center py-5">
            <i class="fas fa-exclamation-triangle fa-3x mb-3 opacity-50"></i><br>
            <h5 class="fw-bold">No Enrollment Record Found</h5>
            <p>You are not enrolled in the current semester (<?php echo $currentSemester . " " . $academicYear; ?>).</p>
        </div>
    <?php
endif; ?>

    <div class="mt-3 pt-2 text-center border-top">
        <div class="row align-items-center">
            <?php if ($corId > 0): ?>
            <div class="col-md-7 text-start">
                <div class="d-flex align-items-center">
                    <div class="me-3 p-1 bg-white border rounded">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=<?php echo urlencode($verifyUrl); ?>" alt="Verification QR" width="80">
                    </div>
                    <div>
                        <div class="fw-bold text-dark small">DOCUMENT VERIFICATION</div>
                        <div class="text-muted" style="font-size: 0.65rem;">Scan to verify this official Certificate of Registration.</div>
                        <div class="mt-1 fw-bold text-primary" style="font-size: 0.65rem;">REF ID: COR-<?php echo str_pad($corId, 8, '0', STR_PAD_LEFT); ?></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <div class="<?php echo $corId > 0 ? 'col-md-5 text-end' : 'col-12 text-center'; ?>">
                <p class="text-muted small mb-0">
                    <strong>Date Issued:</strong> <?php echo date('F d, Y'); ?><br>
                    Generated by TESDA-BCAT GMS
                </p>
            </div>
        </div>
    </div>
</div>

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
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>
