<?php
/**
 * Bulk Grade Import - Instructor View
 * TESDA-BCAT Grade Management System
 */
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('instructor');
$conn = getDBConnection();
$userId = getCurrentUserId();

// Get instructor profile
$stmt = $conn->prepare("SELECT * FROM instructors WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$instructor = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$instructor) {
    redirectWithMessage('dashboard.php', 'Instructor profile not found.', 'danger');
}

$instructorId = $instructor['instructor_id'];
$sectionId = isset($_GET['section_id']) ? intval($_GET['section_id']) : 0;

if ($sectionId > 0) {
    // Verify section belongs to instructor
    $sectionStmt = $conn->prepare("
        SELECT cs.*, c.course_code, c.course_name
        FROM class_sections cs
        JOIN courses c ON cs.course_id = c.course_id
        WHERE cs.section_id = ? AND cs.instructor_id = ?
    ");
    $sectionStmt->bind_param("ii", $sectionId, $instructorId);
    $sectionStmt->execute();
    $section = $sectionStmt->get_result()->fetch_assoc();
    $sectionStmt->close();

    if (!$section) {
        redirectWithMessage('dashboard.php', 'Section not found or access denied.', 'danger');
    }
} else {
    redirectWithMessage('dashboard.php', 'Please select a section first.', 'warning');
}

// Handle template download
if (isset($_GET['action']) && $_GET['action'] === 'download_template') {
    $headers = ['Enrollment ID', 'Student No', 'Student Name', 'Midterm', 'Final', 'Special Status (INC/Dropped)'];
    $filename = "grade_template_" . sanitizeInput($section['course_code']) . "_" . date('Y-m-d') . ".csv";
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, $headers);
    
    // Get enrolled students
    $studentsStmt = $conn->prepare("
        SELECT e.enrollment_id, s.student_no, CONCAT(s.last_name, ', ', s.first_name) as student_name, g.midterm, g.final, g.remarks
        FROM enrollments e
        JOIN students s ON e.student_id = s.student_id
        LEFT JOIN grades g ON e.enrollment_id = g.enrollment_id
        WHERE e.section_id = ? AND e.status = 'enrolled'
        ORDER BY s.last_name, s.first_name
    ");
    $studentsStmt->bind_param("i", $sectionId);
    $studentsStmt->execute();
    $students = $studentsStmt->get_result();
    
    while ($row = $students->fetch_assoc()) {
        fputcsv($output, [
            $row['enrollment_id'],
            $row['student_no'],
            $row['student_name'],
            $row['midterm'] ?? '',
            $row['final'] ?? '',
            in_array($row['remarks'], ['INC', 'Dropped']) ? $row['remarks'] : ''
        ]);
    }
    
    fclose($output);
    exit();
}

$pageTitle = 'Bulk Grade Import';
require_once '../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="submit_grades.php?section_id=<?php echo $sectionId; ?>">Grade Registry</a></li>
                <li class="breadcrumb-item active">Bulk Import</li>
            </ol>
        </nav>

        <div class="card premium-card shadow-sm border-0 overflow-hidden">
            <div class="card-header gradient-navy p-4 text-white">
                <div class="d-flex align-items-center">
                    <div class="bg-white bg-opacity-10 p-3 rounded-3 me-3">
                        <i class="fas fa-file-excel fa-2x"></i>
                    </div>
                    <div>
                        <h4 class="mb-0 fw-bold">Bulk Grade Import</h4>
                        <p class="small mb-0 opacity-75"><?php echo htmlspecialchars($section['course_code'] . ' - ' . $section['course_name']); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="card-body p-4 p-md-5">
                <div class="alert alert-info border-0 shadow-sm rounded-4 mb-4">
                    <div class="d-flex">
                        <div class="me-3">
                            <i class="fas fa-info-circle fa-lg mt-1"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1">How it works:</h6>
                            <ul class="small mb-0 ps-3">
                                <li>Download the <a href="?section_id=<?php echo $sectionId; ?>&action=download_template" class="fw-bold text-decoration-none">Pre-filled CSV Template</a>.</li>
                                <li>Fill in the <strong>Midterm</strong> and <strong>Final</strong> columns (1.00 - 5.00).</li>
                                <li>For special cases, use <strong>INC</strong> or <strong>Dropped</strong> in the last column.</li>
                                <li>Upload the same file back to this page.</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <form id="importForm">
                    <?php csrfField(); ?>
                    <input type="hidden" name="section_id" value="<?php echo $sectionId; ?>">
                    
                    <div class="mb-5">
                        <label class="form-label fw-bold">Select CSV File</label>
                        <div class="upload-area border-dashed border-2 p-5 text-center rounded-4 cursor-pointer" id="dropZone" style="border: 2px dashed #dee2e6;">
                            <input type="file" name="import_file" id="fileInput" class="d-none" accept=".csv" required>
                            <div id="uploadPlaceholder">
                                <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3"></i>
                                <h5 class="fw-bold">Drag and drop CSV here</h5>
                                <p class="text-muted small">Only the generated template is supported.</p>
                            </div>
                            <div id="fileInfo" class="d-none">
                                <i class="fas fa-file-csv fa-3x text-success mb-3"></i>
                                <h5 class="fw-bold" id="fileName">Filename.csv</h5>
                                <button type="button" class="btn btn-link btn-sm text-danger" id="removeFile">Remove File</button>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary p-3 rounded-4 fw-bold shadow-sm" id="submitBtn">
                            <i class="fas fa-upload me-2"></i> Upload and Preview Grades
                        </button>
                    </div>
                </form>

                <div id="progressArea" class="mt-5 d-none">
                    <div class="progress rounded-pill" style="height: 10px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" id="progressBar" role="progressbar" style="width: 0%"></div>
                    </div>
                    <p class="text-center small text-muted mt-2" id="progressStatus">Reading file...</p>
                </div>

                <div id="resultArea" class="mt-5 d-none">
                    <div class="card border-0 bg-light rounded-4">
                        <div class="card-body p-4">
                            <h6 class="fw-bold mb-3"><i class="fas fa-clipboard-check me-2"></i> Import Results</h6>
                            <div id="summaryHtml"></div>
                            <div class="mt-4 text-center">
                                <a href="submit_grades.php?section_id=<?php echo $sectionId; ?>" class="btn btn-success px-4 py-2 rounded-pill fw-bold">
                                    <i class="fas fa-arrow-right me-2"></i> Review and Finalize in Registry
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.cursor-pointer { cursor: pointer; }
.upload-area:hover { background-color: #f8fafc; border-color: #0d6efd !important; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const importForm = document.getElementById('importForm');
    const uploadPlaceholder = document.getElementById('uploadPlaceholder');
    const fileInfo = document.getElementById('fileInfo');
    const fileName = document.getElementById('fileName');
    const removeFile = document.getElementById('removeFile');
    const submitBtn = document.getElementById('submitBtn');

    dropZone.onclick = () => fileInput.click();
    fileInput.onchange = () => {
        if (fileInput.files.length) {
            fileName.textContent = fileInput.files[0].name;
            uploadPlaceholder.classList.add('d-none');
            fileInfo.classList.remove('d-none');
        }
    };

    removeFile.onclick = (e) => {
        e.stopPropagation();
        fileInput.value = '';
        uploadPlaceholder.classList.remove('d-none');
        fileInfo.classList.add('d-none');
    };

    importForm.onsubmit = async (e) => {
        e.preventDefault();
        const formData = new FormData(importForm);
        submitBtn.disabled = true;
        
        document.getElementById('progressArea').classList.remove('d-none');
        document.getElementById('progressBar').style.width = '100%';
        
        try {
            const response = await fetch('ajax/process_grade_import.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            
            if (result.success) {
                document.getElementById('summaryHtml').innerHTML = `
                    <div class="alert alert-success border-0 shadow-sm">
                        <strong>Success!</strong> Successfully imported <strong>${result.imported}</strong> grades.
                    </div>
                `;
                document.getElementById('resultArea').classList.remove('d-none');
            } else {
                alert(result.message || 'Import failed');
                submitBtn.disabled = false;
            }
        } catch (error) {
            console.error(error);
            alert('An error occurred.');
            submitBtn.disabled = false;
        }
    };
});
</script>

<?php require_once '../includes/footer.php'; ?>
