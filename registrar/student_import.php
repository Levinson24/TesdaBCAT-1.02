<?php
/**
 * Bulk Student Import
 * TESDA-BCAT Grade Management System
 */
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole(['registrar', 'registrar_staff']);

$pageTitle = 'Bulk Student Import';
require_once '../includes/header.php';

$conn = getDBConnection();
$progs = $conn->query("SELECT program_id, program_name FROM programs WHERE status = 'active' ORDER BY program_name ASC");
$depts = $conn->query("SELECT dept_id, title_diploma_program FROM departments WHERE status = 'active' ORDER BY title_diploma_program ASC");
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="students.php">Students</a></li>
                <li class="breadcrumb-item active" aria-current="page">Bulk Import</li>
            </ol>
        </nav>

        <div class="card premium-card shadow-lg border-0 overflow-hidden">
            <div class="card-header gradient-navy p-4 text-white">
                <div class="d-flex align-items-center">
                    <div class="bg-white bg-opacity-10 p-3 rounded-3 me-3">
                        <i class="fas fa-file-import fa-2x"></i>
                    </div>
                    <div>
                        <h4 class="mb-0 fw-bold">Bulk Student Import</h4>
                        <p class="small mb-0 opacity-75">Upload an Excel or CSV file to enroll multiple students at once.</p>
                    </div>
                </div>
            </div>
            
            <div class="card-body p-4 p-md-5">
                <!-- Instructions -->
                <div class="alert alert-info border-0 shadow-sm rounded-4 mb-4">
                    <div class="d-flex">
                        <div class="me-3">
                            <i class="fas fa-info-circle fa-lg mt-1"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-1">Import Instructions:</h6>
                            <ul class="small mb-0 ps-3">
                                <li>Download the <a href="?action=download_template" class="fw-bold text-decoration-none">Sample Template</a> for the correct column format.</li>
                                <li>Supported formats: <strong>.xlsx, .xls, .csv</strong></li>
                                <li>Required columns: <strong>Student No, First Name, Last Name, Gender, Birth Date (YYYY-MM-DD or MM/DD/YYYY)</strong>.</li>
                                <li>Programs and Departments must exist in the system before importing.</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <form id="importForm" enctype="multipart/form-data">
                    <?php csrfField(); ?>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold">1. Select Target Department & Program (Default)</label>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <select name="default_dept_id" class="form-select border-0 bg-light p-3 rounded-4 shadow-sm" required>
                                    <option value="">-- All Departments --</option>
                                    <?php while ($d = $depts->fetch_assoc()): ?>
                                        <option value="<?php echo $d['dept_id']; ?>"><?php echo htmlspecialchars($d['title_diploma_program']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                                <small class="text-muted mt-2 d-block ps-2">Used if the file doesn't specify a department.</small>
                            </div>
                            <div class="col-md-6">
                                <select name="default_program_id" class="form-select border-0 bg-light p-3 rounded-4 shadow-sm" required>
                                    <option value="">-- All Programs --</option>
                                    <?php while ($p = $progs->fetch_assoc()): ?>
                                        <option value="<?php echo $p['program_id']; ?>"><?php echo htmlspecialchars($p['program_name']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                                <small class="text-muted mt-2 d-block ps-2">Used if the file doesn't specify a program.</small>
                            </div>
                        </div>
                    </div>

                    <div class="mb-5">
                        <label class="form-label fw-bold">2. Upload Student List File</label>
                        <div class="upload-area border-dashed border-2 p-5 text-center rounded-4 cursor-pointer hover-bg-light transition-all" id="dropZone" style="border: 2px dashed #dee2e6;">
                            <input type="file" name="import_file" id="fileInput" class="d-none" accept=".xlsx, .xls, .csv" required>
                            <div id="uploadPlaceholder">
                                <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3"></i>
                                <h5 class="fw-bold">Drag and drop your file here</h5>
                                <p class="text-muted small">or click to browse your computer</p>
                            </div>
                            <div id="fileInfo" class="d-none">
                                <i class="fas fa-file-excel fa-3x text-success mb-3"></i>
                                <h5 class="fw-bold" id="fileName">Filename.xlsx</h5>
                                <button type="button" class="btn btn-link btn-sm text-danger" id="removeFile">Remove File</button>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary p-3 rounded-4 fw-bold shadow-lg" id="submitBtn">
                            <i class="fas fa-rocket me-2"></i> Start Importing Process
                        </button>
                    </div>
                </form>

                <div id="progressArea" class="mt-5 d-none">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold" id="progressStatus">Processing file...</span>
                        <span class="fw-bold text-primary" id="progressPercent">0%</span>
                    </div>
                    <div class="progress rounded-pill" style="height: 12px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" id="progressBar" role="progressbar" style="width: 0%"></div>
                    </div>
                </div>

                <div id="resultArea" class="mt-5 d-none">
                    <div class="card border-0 bg-light rounded-4">
                        <div class="card-body p-4">
                            <h6 class="fw-bold mb-3"><i class="fas fa-clipboard-list me-2"></i> Import Summary</h6>
                            <div id="summaryHtml"></div>
                            <div class="mt-4 text-center">
                                <a href="students.php" class="btn btn-outline-primary px-4 py-2 rounded-3 fw-bold">Go to Student List</a>
                                <button type="button" class="btn btn-link text-muted" onclick="location.reload()">Import Another File</button>
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
.transition-all { transition: all 0.3s ease; }
.hover-bg-light:hover { background-color: #f8fafc; border-color: #0038A8 !important; }
.border-dashed { border-style: dashed !important; }
.upload-area {
    border: 2px dashed #e2e8f0;
    background: #f8fafc;
    transition: all 0.3s ease;
}
.upload-area:hover {
    border-color: #0038A8;
    background: #fff;
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(0, 56, 168, 0.05);
}
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

    // Drag and drop events
    dropZone.onclick = () => fileInput.click();
    dropZone.ondragover = (e) => {
        e.preventDefault();
        dropZone.classList.add('bg-light');
    };
    dropZone.ondragleave = () => dropZone.classList.remove('bg-light');
    dropZone.ondrop = (e) => {
        e.preventDefault();
        dropZone.classList.remove('bg-light');
        if (e.dataTransfer.files.length) {
            fileInput.files = e.dataTransfer.files;
            handleFileSelect();
        }
    };

    fileInput.onchange = handleFileSelect;

    function handleFileSelect() {
        if (fileInput.files.length) {
            fileName.textContent = fileInput.files[0].name;
            uploadPlaceholder.classList.add('d-none');
            fileInfo.classList.remove('d-none');
        }
    }

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
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
        
        document.getElementById('progressArea').classList.remove('d-none');
        document.getElementById('progressBar').style.width = '20%';
        document.getElementById('progressPercent').textContent = '20%';
        document.getElementById('progressStatus').textContent = 'Validating file format...';

        try {
            const response = await fetch('ajax/process_student_import.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            document.getElementById('progressBar').style.width = '100%';
            document.getElementById('progressPercent').textContent = '100%';
            document.getElementById('progressStatus').textContent = 'Import complete!';
            
            showSummary(result);
        } catch (error) {
            console.error('Import error:', error);
            alert('An error occurred during the import process. Please check the file and try again.');
            submitBtn.disabled = false;
        }
    };

    function showSummary(data) {
        const summary = document.getElementById('summaryHtml');
        let html = '';
        
        if (data.success) {
            html = `
                <div class="row text-center g-3">
                    <div class="col-4">
                        <div class="p-3 bg-white rounded-3 shadow-sm">
                            <h3 class="fw-bold text-success mb-0">${data.imported}</h3>
                            <small class="text-muted">Imported</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-3 bg-white rounded-3 shadow-sm">
                            <h3 class="fw-bold text-warning mb-0">${data.skipped}</h3>
                            <small class="text-muted">Skipped</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-3 bg-white rounded-3 shadow-sm">
                            <h3 class="fw-bold text-danger mb-0">${data.errors.length}</h3>
                            <small class="text-muted">Errors</small>
                        </div>
                    </div>
                </div>
            `;
            
            if (data.errors.length > 0) {
                html += '<div class="mt-4 p-3 bg-white rounded-3 small border-start border-danger border-4 overflow-auto" style="max-height: 200px;">';
                html += '<p class="fw-bold text-danger mb-2">Error Details:</p><ul class="mb-0">';
                data.errors.forEach(err => {
                    html += `<li>Row ${err.row}: ${err.msg}</li>`;
                });
                html += '</ul></div>';
            }
        } else {
            html = `<div class="alert alert-danger">${data.message || 'Import failed'}</div>`;
        }
        
        summary.innerHTML = html;
        document.getElementById('resultArea').classList.remove('d-none');
        document.getElementById('importForm').classList.add('opacity-50');
        document.getElementById('importForm').style.pointerEvents = 'none';
        
        submitBtn.innerHTML = '<i class="fas fa-check-circle me-2"></i> Process Finished';
    }
});
</script>

<?php 
// Handle template download
if (isset($_GET['action']) && $_GET['action'] === 'download_template') {
    $headers = ['Student No', 'First Name', 'Last Name', 'Middle Name', 'Gender', 'Date of Birth (YYYY-MM-DD)', 'Program Code', 'Diploma Program Code', 'Address', 'Municipality', 'Email'];
    $filename = "student_import_template_" . date('Y-m-d') . ".csv";
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, $headers);
    // Add sample row
    fputcsv($output, ['STU-00001', 'John', 'Doe', 'Quincy', 'Male', '2000-01-01', 'BSIT', 'D-IT', 'Sample Address', 'Allen', 'john@example.com']);
    fclose($output);
    exit();
}

require_once '../includes/footer.php'; 
?>
