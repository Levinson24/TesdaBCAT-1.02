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

    $grades = $_POST['grades'] ?? [];

    foreach ($grades as $enrollmentId => $gradeData) {
        $midterm = floatval($gradeData['midterm'] ?? 0);
        $final = floatval($gradeData['final'] ?? 0);

        if (!empty($gradeData['special_status']) || ($midterm > 0 && $final > 0)) {
            // Get weights from settings
            $mWeight = floatval(getSetting('midterm_weight', 0.5));
            $fWeight = floatval(getSetting('final_weight', 0.5));

            // Calculate final grade
            $specialStatus = sanitizeInput($gradeData['special_status'] ?? '');

            if (!empty($specialStatus)) {
                $finalGrade = null;
                $remarks = $specialStatus;
            }
            else {
                $finalGrade = ($midterm * $mWeight) + ($final * $fWeight);
                $passingGrade = floatval(getSetting('passing_grade', 3.00));
                $remarks = getGradeRemark($finalGrade, $passingGrade);
            }

            // Check if grade exists
            $checkStmt = $conn->prepare("SELECT grade_id FROM grades WHERE enrollment_id = ?");
            $checkStmt->bind_param("i", $enrollmentId);
            $checkStmt->execute();
            $existing = $checkStmt->get_result()->fetch_assoc();
            $checkStmt->close();

            if ($existing) {
                // Update existing grade - auto-approved (academic freedom)
                $updateStmt = $conn->prepare("
                    UPDATE grades 
                    SET midterm = ?, final = ?, grade = ?, remarks = ?, 
                        status = 'approved', submitted_by = ?, submitted_at = NOW(),
                        approved_by = ?, approved_at = NOW()
                    WHERE enrollment_id = ?
                ");
                $updateStmt->bind_param("dddsiii", $midterm, $final, $finalGrade, $remarks, $instructorId, $instructorId, $enrollmentId);
                $updateStmt->execute();
                $updateStmt->close();
            }
            else {
                // Insert new grade - auto-approved (academic freedom)
                $insertStmt = $conn->prepare("
                    INSERT INTO grades (enrollment_id, student_id, section_id, midterm, final, grade, remarks, status, submitted_by, submitted_at, approved_by, approved_at)
                    SELECT ?, student_id, section_id, ?, ?, ?, ?, 'approved', ?, NOW(), ?, NOW()
                    FROM enrollments WHERE enrollment_id = ?
                ");
                $insertStmt->bind_param("idddsiii", $enrollmentId, $midterm, $final, $finalGrade, $remarks, $instructorId, $instructorId, $enrollmentId);
                $insertStmt->execute();
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
        SELECT cs.*, c.class_code, c.course_code, c.course_name, c.lec_hrs, c.lab_hrs, c.lec_units, c.lab_units
        FROM class_sections cs
        JOIN courses c ON cs.course_id = c.course_id
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
           c.class_code, c.course_code, c.course_name
    FROM class_sections cs
    JOIN courses c ON cs.course_id = c.course_id
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
            <div class="card-body p-4">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-9">
                        <label class="form-label fw-bold text-muted small text-uppercase">Select Active Class Section</label>
                        <select name="section_id" class="form-select form-select-lg rounded-3 border-light-subtle shadow-sm" required onchange="this.form.submit()">
                            <option value="">-- Choose a registry --</option>
                            <?php while ($sec = $sections->fetch_assoc()): ?>
                                <option value="<?php echo $sec['section_id']; ?>" 
                                    <?php echo $sectionId == $sec['section_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(($sec['class_code'] ?? '') . ' | ' . ($sec['course_code'] ?? '') . ' - ' . ($sec['course_name'] ?? '') . ' [' . ($sec['section_name'] ?? '') . ']'); ?>
                                </option>
                            <?php
endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary btn-lg w-100 rounded-3 shadow-sm py-2">
                            <i class="fas fa-sync-alt me-2"></i> Load Registry
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
    <div class="card-body p-0">
        <div class="row g-0">
            <div class="col-lg-8 p-4">
                <div class="d-flex align-items-center mb-3">
                    <div class="stat-card-icon-v2 bg-primary bg-opacity-10 text-primary me-3" style="width: 50px; height: 50px;">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <div>
                        <h5 class="mb-0 fw-bold text-dark">Section Intelligence</h5>
                        <p class="text-muted small mb-0">Registry configuration and session details</p>
                    </div>
                </div>
                
                <div class="row g-4 mt-2">
                    <div class="col-md-6 border-end border-light">
                        <div class="text-muted small text-uppercase fw-bold mb-1 ls-1">Subject Description</div>
                        <div class="fw-bold text-dark fs-5"><?php echo htmlspecialchars($section['course_name']); ?></div>
                        <div class="text-primary fw-bold small"><?php echo htmlspecialchars($section['course_code']); ?></div>
                    </div>
                    <div class="col-md-6 ps-md-4">
                        <div class="row">
                            <div class="col-6 mb-3">
                                <div class="text-muted small text-uppercase fw-bold mb-1 ls-1">Class ID</div>
                                <div class="badge bg-indigo bg-opacity-10 text-indigo border border-indigo border-opacity-10 px-3"><?php echo htmlspecialchars($section['class_code'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="col-6 mb-3">
                                <div class="text-muted small text-uppercase fw-bold mb-1 ls-1">Section</div>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($section['section_name'] ?? ''); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 bg-light bg-opacity-50 p-4 rounded-end">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="text-muted small text-uppercase fw-bold ls-1"><i class="fas fa-sliders-h me-1 text-primary"></i> Session Parameters</div>
                    <span class="badge bg-white text-dark shadow-sm border-0 px-3 py-2 rounded-pill small">
                        <?php echo htmlspecialchars($section['semester'] . ' SY ' . $section['school_year']); ?>
                    </span>
                </div>
                
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label small fw-bold text-muted mb-1">Lecture Hrs</label>
                        <div class="input-group input-group-sm mb-2 shadow-sm">
                            <input type="number" name="actual_lec_hrs" class="form-control text-center bg-white border-0 fw-bold" value="<?php echo floatval($section['actual_lec_hrs'] ?? $section['lec_hrs']); ?>" step="0.5">
                            <span class="input-group-text bg-white border-0 text-muted small">h</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold text-muted mb-1">Lab Hrs</label>
                        <div class="input-group input-group-sm mb-2 shadow-sm">
                            <input type="number" name="actual_lab_hrs" class="form-control text-center bg-white border-0 fw-bold" value="<?php echo floatval($section['actual_lab_hrs'] ?? $section['lab_hrs']); ?>" step="0.5">
                            <span class="input-group-text bg-white border-0 text-muted small">h</span>
                        </div>
                    </div>
                </div>
                <div class="mt-3 d-flex align-items-center text-primary small bg-primary bg-opacity-10 p-2 rounded-3">
                    <i class="fas fa-info-circle me-2"></i>
                    <span style="font-size: 0.75rem;">Changes are saved upon finalizing the registry.</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-5">
    <div class="col-lg-8">
        <div class="card premium-card border-0 shadow-sm h-100">
            <div class="card-header bg-white p-4 border-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold text-dark">
                    <i class="fas fa-edit me-2 text-primary"></i> Academic Performance Entry
                </h5>
                <div class="search-box" style="width: 250px;">
                    <div class="input-group input-group-sm shadow-sm">
                        <span class="input-group-text bg-white border-0"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" id="studentSearch" class="form-control border-0 px-2" placeholder="Search student...">
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <!-- Table will go here -->
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 table-mobile-card">
                    <thead class="bg-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-4" style="width: 15%;">Student No</th>
                            <th style="width: 25%;">Full Name</th>
                            <th style="width: 12%;">Midterm</th>
                            <th style="width: 12%;">Finals</th>
                            <th style="width: 18%;">Special Status</th>
                            <th class="text-center" style="width: 10%;">GWA</th>
                            <th class="text-end pe-4" style="width: 8%;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($students->num_rows > 0): ?>
                            <?php while ($student = $students->fetch_assoc()): ?>
                            <tr class="<?php echo $student['status'] == 'approved' ? 'bg-success bg-opacity-10' : ''; ?>">
                                <td class="ps-4" data-label="Student No">
                                    <span class="fw-bold text-muted small"><?php echo htmlspecialchars($student['student_no'] ?? ''); ?></span>
                                </td>
                                <td data-label="Full Name">
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($student['student_name'] ?? ''); ?></div>
                                    <div class="mt-1">
                                        <a href="print_grade_slip.php?student_id=<?php echo $student['student_id']; ?>&section_id=<?php echo $sectionId; ?>" target="_blank" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 0.75rem;" title="Print Grade Slip">
                                            <i class="fas fa-print me-1"></i> Print Slip
                                        </a>
                                    </div>
                                </td>
                                <td data-label="Midterm">
                                    <input type="number" 
                                           name="grades[<?php echo $student['enrollment_id']; ?>][midterm]" 
                                           class="form-control grade-input rounded-3 shadow-none border-light-subtle" 
                                           min="1.00" 
                                           max="5.00" 
                                           step="0.01"
                                           value="<?php echo $student['midterm'] ?? ''; ?>"
                                           placeholder="1.00"
                                           <?php echo in_array($student['remarks'], ['INC', 'Dropped']) ? 'disabled' : ''; ?>
                                           >
                                </td>
                                <td data-label="Finals">
                                    <input type="number" 
                                           name="grades[<?php echo $student['enrollment_id']; ?>][final]" 
                                           class="form-control grade-input rounded-3 shadow-none border-light-subtle" 
                                           min="1.00" 
                                           max="5.00" 
                                           step="0.01"
                                           value="<?php echo $student['final'] ?? ''; ?>"
                                           placeholder="1.00"
                                           <?php echo in_array($student['remarks'], ['INC', 'Dropped']) ? 'disabled' : ''; ?>
                                           >
                                </td>
                                <td data-label="Special Status">
                                    <select name="grades[<?php echo $student['enrollment_id']; ?>][special_status]" class="form-select special-status-select rounded-3 border-light-subtle shadow-none">
                                        <option value="">-- Normal --</option>
                                        <option value="INC" <?php echo($student['remarks'] == 'INC') ? 'selected' : ''; ?>>INC (Incomplete)</option>
                                        <option value="Dropped" <?php echo($student['remarks'] == 'Dropped') ? 'selected' : ''; ?>>Dropped</option>
                                    </select>
                                </td>
                                <td class="text-center" data-label="GWA">
                                    <?php if ($student['grade'] !== null): ?>
                                        <span class="badge bg-primary px-3 rounded-pill fw-bold" style="font-size: 0.9rem;"><?php echo number_format($student['grade'], 2); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted opacity-50">—</span>
                                    <?php
            endif; ?>
                                </td>
                                <td class="text-end pe-4" data-label="Status">
                                    <?php
            $statusClass = 'secondary';
            $statusText = 'Pending';
            if ($student['status'] == 'approved' || $student['status'] == 'submitted') {
                $statusClass = 'success';
                $statusText = 'Finalized';
            }
?>
                                    <span class="badge bg-<?php echo $statusClass; ?> bg-opacity-10 text-<?php echo $statusClass; ?> border border-<?php echo $statusClass; ?> border-opacity-25 px-2">
                                        <i class="fas <?php echo $statusText == 'Finalized' ? 'fa-check-circle' : 'fa-clock'; ?> me-1"></i> <?php echo $statusText; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php
        endwhile; ?>
                        <?php
    else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <div class="stat-card-icon-v2 bg-light text-muted mx-auto mb-3">
                                        <i class="fas fa-users-slash"></i>
                                    </div>
                                    <h5 class="fw-bold text-muted">No Enrolled Students</h5>
                                    <p class="text-muted small">This section doesn't have any active enrollments.</p>
                                </td>
                            </tr>
                        <?php
    endif; ?>
                    </tbody>
                </table>
            </div>
            
            </div>
        </div>
    </div>
    
    <!-- Live Analytics Sticky Sidebar -->
    <div class="col-lg-4">
        <div class="card premium-card border-0 shadow-lg sticky-top" style="top: 100px; z-index: 10;">
            <div class="card-header border-0 bg-transparent p-4 pb-0">
                <h6 class="fw-bold text-primary text-uppercase small ls-1">
                    <i class="fas fa-chart-line me-2"></i> Live Class Analytics
                </h6>
            </div>
            <div class="card-body p-4">
                <div style="height: 250px; position: relative;" class="mb-4">
                    <canvas id="livePerformanceChart"></canvas>
                </div>
                
                <div class="stats-breakdown">
                    <div class="d-flex justify-content-between mb-2 small">
                        <span class="text-muted">Class Pass Rate:</span>
                        <span id="passRateLabel" class="fw-bold text-success">0%</span>
                    </div>
                    <div class="progress rounded-pill mb-4" style="height: 6px;">
                        <div id="passRateBar" class="progress-bar bg-success" role="progressbar" style="width: 0%"></div>
                    </div>
                    
                    <div class="row g-2 text-center">
                        <div class="col-4">
                            <div class="p-2 bg-light rounded-3">
                                <div id="passedCount" class="fw-bold text-primary">0</div>
                                <div class="text-muted" style="font-size: 0.65rem;">PASSED</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 bg-light rounded-3">
                                <div id="failedCount" class="fw-bold text-danger">0</div>
                                <div class="text-muted" style="font-size: 0.65rem;">FAILED</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 bg-light rounded-3">
                                <div id="backlogCount" class="fw-bold text-warning">0</div>
                                <div class="text-muted" style="font-size: 0.65rem;">INC/DRP</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <hr class="my-4 opacity-10">
                
                <?php if ($students->num_rows > 0): ?>
                <div class="d-grid gap-2">
                    <button type="submit" name="submit_grades" class="btn btn-primary btn-lg rounded-pill shadow-sm py-3 fw-bold mb-2">
                        <i class="fas fa-check-double me-2"></i> Finalize & Submit
                    </button>
                    <a href="grade_import.php?section_id=<?php echo $sectionId; ?>" class="btn btn-outline-info rounded-pill border-light shadow-sm py-2 fw-bold text-dark">
                        <i class="fas fa-file-import me-1"></i> Bulk Import Excel
                    </a>
                </div>
                <?php endif; ?>

                <div class="alert alert-info border-0 small py-3 mb-0 mt-3 rounded-4">
                    <div class="d-flex">
                        <i class="fas fa-info-circle me-2 mt-1"></i>
                        <div>
                            <strong>Finalization Rule:</strong><br>
                            Ensure all grades are correctly entered. Finalizing will lock the entries for this section.
                        </div>
                    </div>
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
        
        const rows = document.querySelectorAll('tbody tr');
        rows.forEach(row => {
            const midterm = parseFloat(row.querySelector('input[name*="[midterm]"]').value);
            const final = parseFloat(row.querySelector('input[name*="[final]"]').value);
            const specialStatus = row.querySelector('select[name*="[special_status]"]').value;
            
            if (specialStatus) {
                special++;
            } else if (!isNaN(midterm) && !isNaN(final) && midterm > 0 && final > 0) {
                const calculated = (midterm * midWeight) + (final * finWeight);
                if (calculated <= passThreshold) {
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
            const row = this.closest('tr');
            const inputs = row.querySelectorAll('.grade-input');
            
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
