<?php
/**
 * Registrar - View Student Details
 * TESDA-BCAT Grade Management System
 */
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole(['registrar', 'registrar_staff']);

$conn = getDBConnection();

$studentId = isset($_GET['id']) ? intval($_GET['id']) : (isset($_GET['student_id']) ? intval($_GET['student_id']) : 0);
if (!$studentId) {
    redirectWithMessage('students.php', 'Invalid student ID.', 'danger');
}

// Handle Academic Honor Assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_honor'])) {
    if (getCurrentUserRole() !== 'registrar') {
        redirectWithMessage("view_student.php?id=$studentId", 'Unauthorized: Only the Head Registrar can assign academic honors.', 'danger');
    }
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        redirectWithMessage("view_student.php?id=$studentId", 'Invalid security token.', 'danger');
    }

    $honor = $_POST['academic_honor'];
    $evaluatorId = getCurrentUserId();

    // Check for backlogs before assigning exception-less honors
    if ($honor !== 'None' && hasAcademicBacklog($studentId)) {
        redirectWithMessage("view_student.php?id=$studentId", 'Cannot assign honor. Student has an academic backlog.', 'warning');
    } else {
        $honorValue = ($honor === 'None') ? null : $honor;
        
        $stmt = $conn->prepare("UPDATE students SET academic_honor = ?, honor_evaluated_by = ? WHERE student_id = ?");
        $stmt->bind_param("sii", $honorValue, $evaluatorId, $studentId);
        
        if ($stmt->execute()) {
            logAudit($evaluatorId, 'UPDATE', 'students', $studentId, null, "Assigned academic honor: " . ($honorValue ?? 'None'));
            redirectWithMessage("view_student.php?id=$studentId", 'Academic honor updated successfully.', 'success');
        } else {
            redirectWithMessage("view_student.php?id=$studentId", 'Failed to update academic honor.', 'danger');
        }
        $stmt->close();
    }
}

// Fetch student details - Full access for registrar
$stmt = $conn->prepare("
    SELECT s.*, u.username, d.title_diploma_program as dept_name, p.program_name as program_name, col.college_name
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

if (!$student) {
    redirectWithMessage('students.php', 'Student not found.', 'danger');
}

// Department Access Check for Staff
$userRole = getCurrentUserRole();
if ($userRole === 'registrar_staff') {
    $userProfile = getUserProfile(getCurrentUserId(), $userRole);
    $staffDeptId = $userProfile['dept_id'] ?? 0;
    if ($student['dept_id'] != $staffDeptId) {
        redirectWithMessage('students.php', 'Unauthorized: You do not have access to this student profile.', 'danger');
    }
}
$stmt->close();

// Calculate GWA
$gwa = calculateGWA($studentId);
$hasBacklog = hasAcademicBacklog($studentId);

// Fetch all enrolled subjects
$enrollmentsStmt = $conn->prepare("
    SELECT e.enrollment_id, cs.section_name, cs.schedule, cs.room, cs.semester, cs.school_year,
           c.course_code, c.course_name,
           CONCAT(i.first_name, ' ', i.last_name) as instructor_name
    FROM enrollments e
    JOIN class_sections cs ON e.section_id = cs.section_id
    JOIN courses c ON cs.course_id = c.course_id
    JOIN instructors i ON cs.instructor_id = i.instructor_id
    WHERE e.student_id = ?
    ORDER BY cs.school_year DESC, cs.semester DESC
");
$enrollmentsStmt->bind_param("i", $studentId);
$enrollmentsStmt->execute();
$enrollments = $enrollmentsStmt->get_result();
$enrollmentsStmt->close();

$pageTitle = 'Student Details: ' . $student['first_name'] . ' ' . $student['last_name'];
require_once '../includes/header.php';
?>

<div class="row mb-4 no-print">
    <div class="col-md-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="students.php">Students</a></li>
                <li class="breadcrumb-item active">Student Details</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <!-- Left Column: Profile Card -->
    <div class="col-lg-4">
        <div class="card premium-card border-0 mb-4 shadow-sm">
            <div class="card-body text-center p-4">
                <div class="mb-3">
                    <div class="rounded-circle bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center" style="width: 100px; height: 100px;">
                        <i class="fas fa-user-graduate fa-4x text-primary"></i>
                    </div>
                </div>
                <h4 class="mb-1 fw-bold"><?php echo htmlspecialchars(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')); ?></h4>
                <p class="text-muted mb-3 fw-semibold"><?php echo htmlspecialchars($student['student_no'] ?? ''); ?></p>
                <div class="badge bg-<?php echo $student['status'] === 'active' ? 'success' : 'secondary'; ?> rounded-pill px-4 py-2 mb-4">
                    <?php echo strtoupper($student['status']); ?>
                </div>
                
                <div class="row text-center mt-2 border-top pt-3 g-0">
                    <div class="col-6 border-end">
                        <div class="small text-muted mb-1 text-uppercase fw-bold" style="font-size: 0.6rem;">Year Level</div>
                        <div class="fw-bold fs-5 text-dark"><?php echo $student['year_level']; ?></div>
                    </div>
                    <div class="col-6">
                        <div class="small text-muted mb-1 text-uppercase fw-bold" style="font-size: 0.6rem;">Compute GWA</div>
                        <div class="fw-bold fs-5 text-primary"><?php echo $gwa !== null ? number_format($gwa, 2) : '0.00'; ?></div>
                    </div>
                </div>

                <?php if (!empty($student['academic_honor'])): ?>
                <div class="mt-3 p-2 bg-success bg-opacity-10 border border-success border-opacity-25 rounded-3">
                    <div class="small text-success fw-bold text-uppercase" style="font-size: 0.65rem;"><i class="fas fa-medal me-1"></i> Academic Honor</div>
                    <div class="fw-bold text-success fs-6"><?php echo htmlspecialchars($student['academic_honor']); ?></div>
                </div>
                <?php endif; ?>

                <div class="mt-4 d-grid gap-2">
                    <?php if (getCurrentUserRole() === 'registrar'): ?>
                    <button type="button" class="btn btn-warning rounded-pill shadow-sm text-dark fw-bold" data-bs-toggle="modal" data-bs-target="#assignHonorModal">
                        <i class="fas fa-award me-2"></i> Assign Honor
                    </button>
                    <?php endif; ?>
                    <a href="../dept_head/manage_student_schedule.php?student_id=<?php echo $studentId; ?>" class="btn btn-dark rounded-pill shadow-sm">
                        <i class="fas fa-calendar-check me-2"></i> Manage Enrollment
                    </a>
                    <a href="curriculum_evaluation.php?id=<?php echo $studentId; ?>" class="btn btn-primary rounded-pill shadow-sm" target="_blank">
                        <i class="fas fa-file-invoice me-2"></i> Official Evaluation
                    </a>
                    <a href="print_grade_slip.php?student_id=<?php echo $studentId; ?>" class="btn btn-outline-primary rounded-pill shadow-sm bg-white" target="_blank">
                        <i class="fas fa-receipt me-2"></i> Print Grade Slip
                    </a>
                    <a href="transcript_print.php?student_id=<?php echo $studentId; ?>" class="btn btn-outline-info rounded-pill shadow-sm bg-white" target="_blank">
                        <i class="fas fa-print me-2"></i> Print Transcript
                    </a>
                </div>
            </div>
        </div>

        <div class="card premium-card border-0 mb-4 shadow-sm">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="mb-0 fw-bold text-primary"><i class="fas fa-info-circle me-2"></i> Contact & Bio</h6>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush border-0">
                    <li class="list-group-item d-flex justify-content-between py-3 bg-transparent">
                        <span class="text-muted small fw-bold text-uppercase">Email ID</span>
                        <span class="fw-bold text-primary small"><?php echo htmlspecialchars($student['email'] ?? 'N/A'); ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between py-3 bg-transparent">
                        <span class="text-muted small fw-bold text-uppercase">Phone</span>
                        <span class="fw-bold text-dark"><?php echo htmlspecialchars($student['contact_number'] ?? 'N/A'); ?></span>
                    </li>
                    <li class="list-group-item d-flex flex-column py-3 bg-transparent border-0">
                        <span class="text-muted small fw-bold text-uppercase mb-1">Mailing Address</span>
                        <span class="fw-medium text-dark"><?php echo htmlspecialchars(($student['address'] ?? '') . ' ' . ($student['municipality'] ?? '')); ?></span>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Right Column: Enrollment & Academic Info -->
    <div class="col-lg-8">
        <div class="card premium-card border-0 mb-4 shadow-sm">
            <div class="card-header bg-white py-4 border-0">
                <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-clock-rotate-left me-2"></i> Active Academic Schedule</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="gradient-navy text-white">
                            <tr>
                                <th class="ps-4 border-0 small fw-bold text-uppercase text-warning">Subject / Code</th>
                                <th class="border-0 small fw-bold text-uppercase">Section / Room</th>
                                <th class="border-0 small fw-bold text-uppercase text-end pe-4">Assigned Prof</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($enrollments->num_rows > 0): ?>
                                <?php while ($e = $enrollments->fetch_assoc()): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-bold text-primary"><?php echo htmlspecialchars($e['course_code'] ?? ''); ?></div>
                                            <div class="small text-muted fw-semibold"><?php echo htmlspecialchars($e['course_name'] ?? ''); ?></div>
                                        </td>
                                        <td>
                                            <div class="badge bg-secondary rounded-pill mb-1"><?php echo htmlspecialchars($e['section_name'] ?? ''); ?></div>
                                            <div class="small fw-bold text-dark"><?php echo htmlspecialchars($e['schedule'] ?? 'TBA'); ?></div>
                                            <div class="small text-muted" style="font-size: 0.7rem;"><i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($e['room'] ?? 'TBA'); ?></div>
                                        </td>
                                        <td class="text-end pe-4">
                                            <div class="small fw-bold text-dark"><?php echo htmlspecialchars($e['instructor_name'] ?? ''); ?></div>
                                            <div class="text-muted" style="font-size: 0.65rem;"><?php echo htmlspecialchars(($e['semester'] ?? '') . ' ' . ($e['school_year'] ?? '')); ?></div>
                                        </td>
                                    </tr>
                                <?php
    endwhile; ?>
                            <?php
else: ?>
                                <tr>
                                    <td colspan="3" class="text-center py-5 text-muted">
                                        <i class="fas fa-folder-open fa-3x mb-3 opacity-25"></i>
                                        <div class="fw-bold">No Active Enrollments</div>
                                        <div class="small">The student is not currently enrolled in any class sections.</div>
                                    </td>
                                </tr>
                            <?php
endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card premium-card border-0 mb-4 shadow-sm">
            <div class="card-header bg-white py-4 border-0">
                <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-university me-2"></i> Program Membership</h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-4">
                    <div class="col-md-7">
                        <div class="p-3 bg-light rounded-4 h-100">
                            <label class="text-muted small fw-bold text-uppercase mb-1 d-block" style="font-size: 0.65rem;">Departmental Affiliation</label>
                            <div class="fw-bold text-dark fs-6"><?php echo htmlspecialchars($student['dept_name'] ?? 'Unassigned'); ?></div>
                            <div class="small text-muted mt-1 text-uppercase fw-bold"><?php echo htmlspecialchars($student['college_name'] ?? ''); ?></div>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="p-3 bg-light rounded-4 h-100 border-start border-primary border-4">
                            <label class="text-muted small fw-bold text-uppercase mb-1 d-block" style="font-size: 0.65rem;">Degree Program / Course</label>
                            <div class="fw-bold text-primary"><?php echo htmlspecialchars($student['program_name'] ?? 'N/A'); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Assign Honor Modal -->
<div class="modal fade" id="assignHonorModal" tabindex="-1" aria-labelledby="assignHonorModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header gradient-navy text-white border-0 py-3">
                <h5 class="modal-title fw-bold" id="assignHonorModalLabel"><i class="fas fa-award me-2 text-warning"></i> Assign Academic Honor</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <?php csrfField(); ?>
                <div class="modal-body">
                    <?php if ($hasBacklog): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Warning:</strong> This student has an academic backlog (failed/dropped grades or uncompleted subjects). They may not be eligible for academic honors.
                    </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="academic_honor" class="form-label fw-bold small text-muted text-uppercase">Select Honor</label>
                        <select class="form-select" id="academic_honor" name="academic_honor" required>
                            <option value="None" <?php echo empty($student['academic_honor']) ? 'selected' : ''; ?>>None (Remove Honor)</option>
                            <option value="Summa Cum Laude" <?php echo ($student['academic_honor'] ?? '') === 'Summa Cum Laude' ? 'selected' : ''; ?>>Summa Cum Laude</option>
                            <option value="Magna Cum Laude" <?php echo ($student['academic_honor'] ?? '') === 'Magna Cum Laude' ? 'selected' : ''; ?>>Magna Cum Laude</option>
                            <option value="Cum Laude" <?php echo ($student['academic_honor'] ?? '') === 'Cum Laude' ? 'selected' : ''; ?>>Cum Laude</option>
                            <option value="Academic Distinction" <?php echo ($student['academic_honor'] ?? '') === 'Academic Distinction' ? 'selected' : ''; ?>>Academic Distinction</option>
                            <option value="With Honors" <?php echo ($student['academic_honor'] ?? '') === 'With Honors' ? 'selected' : ''; ?>>With Honors</option>
                            <option value="With High Honors" <?php echo ($student['academic_honor'] ?? '') === 'With High Honors' ? 'selected' : ''; ?>>With High Honors</option>
                            <option value="With Highest Honors" <?php echo ($student['academic_honor'] ?? '') === 'With Highest Honors' ? 'selected' : ''; ?>>With Highest Honors</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="assign_honor" class="btn btn-warning fw-bold text-dark"><i class="fas fa-save me-1"></i> Save Honor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
