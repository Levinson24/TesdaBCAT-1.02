<?php
/**
 * Instructor - Submit Grades
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_grades'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        redirectWithMessage('submit_grades.php?section_id=' . $sectionId, 'Invalid security token. Please try again.', 'danger');
    }

    // Save actual section hours (units remain default)
    $actual_lec_hrs = isset($_POST['actual_lec_hrs']) && $_POST['actual_lec_hrs'] !== '' ? floatval($_POST['actual_lec_hrs']) : null;
    $actual_lab_hrs = isset($_POST['actual_lab_hrs']) && $_POST['actual_lab_hrs'] !== '' ? floatval($_POST['actual_lab_hrs']) : null;

    $updateSecStmt = $conn->prepare("UPDATE class_sections SET actual_lec_hrs = ?, actual_lab_hrs = ? WHERE section_id = ? AND instructor_id = ?");
    $updateSecStmt->bind_param("ddii", $actual_lec_hrs, $actual_lab_hrs, $sectionId, $instructorId);
    $updateSecStmt->execute();
    $updateSecStmt->close();

    $gradesDesktop = $_POST['grades'] ?? [];
    $gradesMobile = $_POST['m_grades'] ?? [];

    $mergedGrades = [];
    $allIds = array_unique(array_merge(array_keys($gradesDesktop), array_keys($gradesMobile)));
    foreach ($allIds as $eid) {
        $g1 = $gradesDesktop[$eid]['grade'] ?? '';
        $s1 = $gradesDesktop[$eid]['special_status'] ?? '';
        $g2 = $gradesMobile[$eid]['grade'] ?? '';
        $s2 = $gradesMobile[$eid]['special_status'] ?? '';

        $mergedGrades[$eid] = [
            'grade' => $g1 !== '' ? $g1 : $g2,
            'special_status' => $s1 !== '' ? $s1 : $s2,
        ];
    }

    foreach ($mergedGrades as $enrollmentId => $gradeData) {
        $gradeRaw = $gradeData['grade'] ?? '';
        $specialStatus = sanitizeInput($gradeData['special_status'] ?? '');

        // Save if at least one meaningful piece of data is present
        if ($specialStatus !== '' || is_numeric($gradeRaw)) {
            $midterm = null; // Unused
            $final = null;   // Unused
            $finalGrade = is_numeric($gradeRaw) ? floatval($gradeRaw) : null;
            
            $passingGrade = floatval(getSetting('passing_grade', 3.00));

            $remarks = 'Incomplete';

            if ($specialStatus !== '') {
                $remarks = $specialStatus;
            } 
            elseif ($finalGrade !== null) {
                $remarks = getGradeRemark($finalGrade, $passingGrade);
            }

            // Standardize Nulls for bind_param (types: d, d, d, s, i, i, i)
            // Use 'NULL' compatible binding logic or ternary
            
            $checkStmt = $conn->prepare("SELECT student_id, section_id FROM enrollments WHERE enrollment_id = ?");
            $checkStmt->bind_param("i", $enrollmentId);
            $checkStmt->execute();
            $enrollment = $checkStmt->get_result()->fetch_assoc();
            $checkStmt->close();

            if (!$enrollment) continue; // Safety skip

            $stuId = $enrollment['student_id'];
            $secId = $enrollment['section_id'];

            // Check if grade already exists
            $gStmt = $conn->prepare("SELECT grade_id FROM grades WHERE enrollment_id = ?");
            $gStmt->bind_param("i", $enrollmentId);
            $gStmt->execute();
            $existing = $gStmt->get_result()->fetch_assoc();
            $gStmt->close();

            if ($existing) {
                $updateStmt = $conn->prepare("
                    UPDATE grades 
                    SET midterm = ?, final = ?, grade = ?, remarks = ?, 
                        status = 'approved', submitted_by = ?, submitted_at = NOW(),
                        approved_by = ?, approved_at = NOW()
                    WHERE enrollment_id = ?
                ");
                $updateStmt->bind_param("dddsiii", $midterm, $final, $finalGrade, $remarks, $instructorId, $instructorId, $enrollmentId);
                if (!$updateStmt->execute()) {
                    error_log("Grade Update Failed: " . $updateStmt->error);
                }
                $updateStmt->close();
            }
            else {
                $insertStmt = $conn->prepare("
                    INSERT INTO grades (enrollment_id, student_id, section_id, midterm, final, grade, remarks, status, submitted_by, submitted_at, approved_by, approved_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'approved', ?, NOW(), ?, NOW())
                ");
                $insertStmt->bind_param("iiidddsii", $enrollmentId, $stuId, $secId, $midterm, $final, $finalGrade, $remarks, $instructorId, $instructorId);
                if (!$insertStmt->execute()) {
                    error_log("Grade Insert Failed: " . $insertStmt->error);
                }
                $insertStmt->close();
            }
        }
    }

    // Log the action
    logAudit(getCurrentUserId(), 'UPDATE', 'grades', $sectionId, null, 'Finalized grades for section: ' . ($section['course_code'] ?? $sectionId));

    redirectWithMessage('submit_grades.php?section_id=' . $sectionId, 'Grades submitted successfully', 'success');
}

$pageTitle = 'Submit Grades';
require_once '../includes/header.php';

// Get instructor profile
$stmt = $conn->prepare("SELECT * FROM instructors WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$instructor = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$instructor) {
    echo showError('Instructor profile not found.');
    require_once '../includes/footer.php';
    exit();
}

$instructorId = $instructor['instructor_id'];
$sectionId = isset($_GET['section_id']) ? intval($_GET['section_id']) : 0;


// Get section information
if ($sectionId > 0) {
    $sectionStmt = $conn->prepare("
        SELECT cs.*, cur.class_code, s.subject_id as course_code, s.subject_name as course_name, 
               s.lec_hrs, s.lab_hrs, s.lec_units, s.lab_units
        FROM class_sections cs
        JOIN curriculum cur ON cs.curriculum_id = cur.curriculum_id
        JOIN subjects s ON cur.subject_id = s.subject_id
        WHERE cs.section_id = ? AND cs.instructor_id = ?
    ");
    $sectionStmt->bind_param("ii", $sectionId, $instructorId);
    $sectionStmt->execute();
    $section = $sectionStmt->get_result()->fetch_assoc();
    $sectionStmt->close();

    if (!$section) {
        echo showError('Section not found or you do not have permission to access it.');
        require_once '../includes/footer.php';
        exit();
    }

    // Get enrolled students and their grades
    $studentsStmt = $conn->prepare("
        SELECT 
            e.enrollment_id,
            s.student_id,
            s.student_no,
            CONCAT(s.last_name, ', ', s.first_name, ' ', COALESCE(s.middle_name, '')) as student_name,
            g.grade_id,
            g.midterm,
            g.final,
            g.grade,
            g.remarks,
            g.status
        FROM enrollments e
        JOIN students s ON e.student_id = s.student_id
        LEFT JOIN grades g ON e.enrollment_id = g.enrollment_id
        WHERE e.section_id = ? AND e.status = 'enrolled'
        ORDER BY s.last_name, s.first_name
    ");
    $studentsStmt->bind_param("i", $sectionId);
    $studentsStmt->execute();
    $students = $studentsStmt->get_result();
}

// Get instructor's class sections for selection
$sectionsStmt = $conn->prepare("
    SELECT cs.section_id, cs.section_name, cs.school_year, cs.semester,
           cur.class_code, s.subject_id as course_code, s.subject_name as course_name
    FROM class_sections cs
    JOIN curriculum cur ON cs.curriculum_id = cur.curriculum_id
    JOIN subjects s ON cur.subject_id = s.subject_id
    WHERE cs.instructor_id = ? AND cs.status = 'active'
    ORDER BY cs.school_year DESC, cs.semester DESC
");
$sectionsStmt->bind_param("i", $instructorId);
$sectionsStmt->execute();
$sections = $sectionsStmt->get_result();
?>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card premium-card border-0 shadow-sm">
            <div class="card-body p-3 p-md-4">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-9">
                        <label class="form-label fw-bold text-muted x-small text-uppercase ls-1">Load Active Registry</label>
                        <select name="section_id" class="form-select rounded-3 border-light-subtle shadow-none" required onchange="this.form.submit()">
                            <option value="">-- Select Section --</option>
                            <?php while ($sec = $sections->fetch_assoc()): ?>
                                <option value="<?php echo $sec['section_id']; ?>" 
                                    <?php echo $sectionId == $sec['section_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(($sec['course_code'] ?? '') . ' [' . ($sec['section_name'] ?? '') . ']'); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100 rounded-3 py-2 btn-mobile-full">
                            <i class="fas fa-sync-alt me-2"></i> Sync
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if (isset($section)): ?>
<form method="POST">
    <?php csrfField(); ?>
<div class="card premium-card border-0 shadow-sm mb-4">
    <div class="card-body p-3 p-md-4">
        <div class="row g-3">
            <div class="col-lg-8">
                <div class="d-flex align-items-start mb-3">
                    <div class="stat-card-icon-v2 bg-primary bg-opacity-10 text-primary me-3 flex-shrink-0" style="width: 44px; height: 44px;">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold text-dark">Section Intelligence</h6>
                        <div class="text-primary fw-bold small text-truncate" style="max-width: 200px;"><?php echo htmlspecialchars($section['course_name']); ?></div>
                    </div>
                </div>
                
                <div class="row g-2">
                    <div class="col-6 col-md-4">
                        <div class="text-muted x-small text-uppercase fw-bold ls-1">Subject Code</div>
                        <div class="fw-bold text-dark small"><?php echo htmlspecialchars($section['course_code']); ?></div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="text-muted x-small text-uppercase fw-bold ls-1">Class ID</div>
                        <div class="badge bg-indigo bg-opacity-10 text-indigo rounded-pill px-2 x-small"><?php echo htmlspecialchars($section['class_code'] ?? 'N/A'); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 mt-3 mt-lg-0">
                <div class="p-3 bg-light rounded-4">
                    <div class="text-muted x-small text-uppercase fw-bold ls-1 mb-2"><i class="fas fa-sliders-h me-1 text-primary"></i> Target Metrics</div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="x-small fw-bold text-muted mb-1">LEC Hours</label>
                            <input type="number" name="actual_lec_hrs" class="form-control form-control-sm text-center fw-bold border-0" value="<?php echo floatval($section['actual_lec_hrs'] ?? $section['lec_hrs']); ?>" step="0.5">
                        </div>
                        <div class="col-6">
                            <label class="x-small fw-bold text-muted mb-1">LAB Hours</label>
                            <input type="number" name="actual_lab_hrs" class="form-control form-control-sm text-center fw-bold border-0" value="<?php echo floatval($section['actual_lab_hrs'] ?? $section['lab_hrs']); ?>" step="0.5">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-5 g-4">
    <div class="col-lg-8">
        <div class="card premium-card border-0 shadow-sm h-100">
            <div class="card-header bg-white p-3 p-md-4 border-0 d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
                <h6 class="mb-0 fw-bold text-dark">
                    <i class="fas fa-edit me-2 text-primary"></i> Registry Input
                </h6>
                <div class="search-box">
                    <div class="input-group input-group-sm shadow-none border rounded-pill overflow-hidden bg-light px-2">
                        <span class="input-group-text bg-transparent border-0"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" id="studentSearch" class="form-control bg-transparent border-0" placeholder="Filter roster...">
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <!-- Desktop Table Output -->
                <div class="table-responsive d-none d-md-block">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light text-muted small text-uppercase">
                            <tr>
                                <th class="ps-4">Student ID</th>
                                <th>Identity</th>
                                <th style="width: 120px;">Grade Input</th>
                                <th style="width: 130px;">Status</th>
                                <th class="text-center">GWA</th>
                                <th class="text-end pe-4">Sync</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($students->num_rows > 0): ?>
                                <?php 
                                $students->data_seek(0);
                                while ($student = $students->fetch_assoc()): ?>
                                <tr class="<?php echo $student['status'] == 'approved' ? 'bg-success bg-opacity-5' : ''; ?>">
                                    <td class="ps-4"><span class="fw-bold x-small text-muted"><?php echo htmlspecialchars($student['student_no'] ?? ''); ?></span></td>
                                    <td><div class="fw-bold text-dark small text-truncate" style="max-width: 150px;"><?php echo htmlspecialchars($student['student_name'] ?? ''); ?></div></td>
                                    <td><input type="number" name="grades[<?php echo $student['enrollment_id']; ?>][grade]" class="form-control form-control-sm grade-input rounded-3 border-light-subtle" value="<?php echo $student['grade'] ?? ''; ?>" step="0.01"></td>
                                    <td>
                                        <select name="grades[<?php echo $student['enrollment_id']; ?>][special_status]" class="form-select form-select-sm special-status-select rounded-3 border-light-subtle x-small">
                                            <option value="">Normal</option>
                                            <option value="INC" <?php echo($student['remarks'] == 'INC') ? 'selected' : ''; ?>>INC</option>
                                            <option value="Dropped" <?php echo($student['remarks'] == 'Dropped') ? 'selected' : ''; ?>>Dropped</option>
                                        </select>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 fw-bold"><?php echo $student['grade'] ? number_format($student['grade'], 2) : '--'; ?></span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <i class="fas <?php echo $student['status'] == 'approved' ? 'fa-check-circle text-success' : 'fa-circle text-muted opacity-25'; ?> small"></i>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Submission Cards (Prototype Style) -->
                <div class="d-block d-md-none p-3">
                    <?php if ($students->num_rows > 0): ?>
                        <?php 
                        $students->data_seek(0);
                        while ($student = $students->fetch_assoc()): ?>
                        <div class="card p-3 mb-3 border shadow-none rounded-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="fw-bold text-dark small"><?php echo strtoupper($student['student_name']); ?></div>
                                <span class="badge bg-light text-muted x-small"><?php echo htmlspecialchars($student['student_no']); ?></span>
                            </div>
                            <div class="row g-2 align-items-end mb-2">
                                <div class="col-8">
                                    <label class="x-small text-muted mb-1">Grade Input</label>
                                    <input type="number" name="m_grades[<?php echo $student['enrollment_id']; ?>][grade]" class="form-control grade-input rounded-3 text-center py-2" value="<?php echo $student['grade'] ?? ''; ?>" step="0.01" placeholder="0.00">
                                </div>
                                <div class="col-4">
                                    <label class="x-small text-muted mb-1">Status</label>
                                    <select name="m_grades[<?php echo $student['enrollment_id']; ?>][special_status]" class="form-select special-status-select rounded-3 py-2 x-small">
                                        <option value="">Normal</option>
                                        <option value="INC" <?php echo($student['remarks'] == 'INC') ? 'selected' : ''; ?>>INC</option>
                                        <option value="Dropped" <?php echo($student['remarks'] == 'Dropped') ? 'selected' : ''; ?>>DRP</option>
                                    </select>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center border-top pt-2 mt-1">
                                <div class="x-small text-muted">Current GWA: <strong><?php echo $student['grade'] ? number_format($student['grade'], 2) : 'N/A'; ?></strong></div>
                                <i class="fas <?php echo $student['status'] == 'approved' ? 'fa-check-circle text-success' : 'fa-circle text-muted opacity-25'; ?> x-small"></i>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center py-5 text-muted small italic">No active enrollments for this section.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Analysis & Actions -->
    <div class="col-lg-4">
        <div class="card premium-card border-0 shadow-lg sticky-top" style="top: 100px; z-index: 10;">
            <div class="card-body p-4">
                <h6 class="fw-bold text-primary text-uppercase x-small ls-1 mb-3">
                    <i class="fas fa-chart-pie me-2"></i> Registry Analytics
                </h6>
                <div style="height: 180px; position: relative;" class="mb-4">
                    <canvas id="livePerformanceChart"></canvas>
                </div>
                
                <div class="stats-breakdown">
                    <div class="d-flex justify-content-between mb-1 x-small">
                        <span class="text-muted">Class Success Rate</span>
                        <span id="passRateLabel" class="fw-bold text-success">0%</span>
                    </div>
                    <div class="progress rounded-pill mb-4" style="height: 4px;">
                        <div id="passRateBar" class="progress-bar bg-success" role="progressbar" style="width: 0%"></div>
                    </div>
                    
                    <div class="row g-2 text-center mb-4">
                        <div class="col-4">
                            <div class="p-2 bg-light rounded-3">
                                <div id="passedCount" class="small fw-bold text-primary">0</div>
                                <div class="text-muted" style="font-size: 0.6rem;">PASSED</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 bg-light rounded-3">
                                <div id="failedCount" class="small fw-bold text-danger">0</div>
                                <div class="text-muted" style="font-size: 0.6rem;">FAILED</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 bg-light rounded-3">
                                <div id="backlogCount" class="small fw-bold text-warning">0</div>
                                <div class="text-muted" style="font-size: 0.6rem;">INC/DRP</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" name="submit_grades" class="btn btn-primary btn-lg rounded-pill shadow-sm py-3 fw-bold">
                        <i class="fas fa-check-double me-2"></i> Finalize Registry
                    </button>
                    <a href="grade_import.php?section_id=<?php echo $sectionId; ?>" class="btn btn-outline-secondary btn-sm rounded-pill border-0 text-muted x-small">
                        <i class="fas fa-file-import me-1"></i> Excel Import Utility
                    </a>
                </div>

                <div class="alert alert-info border-0 x-small py-3 mb-0 mt-3 rounded-4 d-none d-sm-block">
                    <strong>Finalization Clause:</strong> Verification is mandatory. Once finalized, records will be locked for official processing.
                </div>
            </div>
        </div>
    </div>
</div>

</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const specialSelects = document.querySelectorAll('.special-status-select');
    const gradeInputs = document.querySelectorAll('.grade-input');
    const midWeight = <?php echo floatval(getSetting('midterm_weight', 0.5)); ?>;
    const finWeight = <?php echo floatval(getSetting('final_weight', 0.5)); ?>;
    const passThreshold = <?php echo floatval(getSetting('passing_grade', 3.00)); ?>;

    // Initialize Chart
    const ctx = document.getElementById('livePerformanceChart').getContext('2d');
    const liveChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Passed', 'Failed', 'Special (INC/DRP)', 'Incomplete Info'],
            datasets: [{
                data: [0, 0, 0, <?php echo $students->num_rows; ?>],
                backgroundColor: ['#1a8754', '#dc3545', '#ffc107', '#f8f9fa'],
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '75%',
            plugins: {
                legend: { display: false }
            }
        }
    });

    function updateAnalytics() {
        let passed = 0;
        let failed = 0;
        let special = 0;
        let incomplete = 0;
        
        // Grab grades from either Desktop rows or Mobile cards based on visibility
        const isMobile = window.innerWidth < 768;
        const containers = isMobile ? document.querySelectorAll('.d-md-none .card') : document.querySelectorAll('tbody tr');
        
        containers.forEach(container => {
            if (!isMobile && container.querySelector('td[colspan]')) return; // Skip "No students" row
            
            const gradeInput = container.querySelector('.grade-input');
            const statusSelect = container.querySelector('.special-status-select');
            
            if (!gradeInput || !statusSelect) return;
            
            const gradeVal = parseFloat(gradeInput.value);
            const specialStatus = statusSelect.value;
            
            if (specialStatus) {
                special++;
            } else if (!isNaN(gradeVal) && gradeVal > 0) {
                if (gradeVal <= passThreshold) {
                    passed++;
                } else {
                    failed++;
                }
            } else {
                incomplete++;
            }
        });

        // Update counts
        document.getElementById('passedCount').textContent = passed;
        document.getElementById('failedCount').textContent = failed;
        document.getElementById('backlogCount').textContent = special;
        
        const totalDetermined = passed + failed;
        const passRate = totalDetermined > 0 ? Math.round((passed / totalDetermined) * 100) : 0;
        
        document.getElementById('passRateLabel').textContent = passRate + '%';
        document.getElementById('passRateBar').style.width = passRate + '%';
        
        // Update Chart
        liveChart.data.datasets[0].data = [passed, failed, special, incomplete];
        liveChart.update();
    }

    // Event Listeners
    gradeInputs.forEach(input => {
        input.addEventListener('input', updateAnalytics);
    });

    specialSelects.forEach(select => {
        select.addEventListener('change', function() {
            const container = this.closest('tr') || this.closest('.card');
            const inputs = container.querySelectorAll('.grade-input');
            
            if (this.value !== '') {
                inputs.forEach(input => {
                    input.value = '';
                    input.disabled = true;
                });
            } else {
                inputs.forEach(input => {
                    input.disabled = false;
                });
            }
            updateAnalytics();
        });
    });

    // Student Search Filter
    const studentSearch = document.getElementById('studentSearch');
    if (studentSearch) {
        studentSearch.addEventListener('input', function() {
            const query = this.value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(query)) {
                    row.style.display = '';
                } else if (!row.querySelector('td[colspan]')) { // Don't hide "No Students" row
                    row.style.display = 'none';
                }
            });
        });
    }

    // Run once on load
    updateAnalytics();
});
</script>
<?php
endif; ?>

<?php require_once '../includes/footer.php'; ?>
