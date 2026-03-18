<?php
/**
 * Diploma Program Head - View Student Details
 */
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('dept_head');

$conn = getDBConnection();
$userProfile = getUserProfile($_SESSION['user_id'], 'dept_head');
$deptId = $userProfile['dept_id'] ?? 0;

$studentId = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
if (!$studentId) {
    redirectWithMessage('students.php', 'Invalid student ID.', 'danger');
}

// Fetch student details - scoped to department
$stmt = $conn->prepare("
    SELECT s.*, u.username, d.title_diploma_program as dept_name, p.program_name as program_name, col.college_name
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    LEFT JOIN departments d ON s.dept_id = d.dept_id
    LEFT JOIN colleges col ON d.college_id = col.college_id
    LEFT JOIN programs p ON s.program_id = p.program_id
    WHERE s.student_id = ? AND s.dept_id = ?
");
$stmt->bind_param("ii", $studentId, $deptId);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    redirectWithMessage('students.php', 'Student not found or access denied.', 'danger');
}

// Calculate GWA
$gwa = calculateGWA($studentId);

// Fetch current enrolled subjects
$enrollmentsStmt = $conn->prepare("
    SELECT e.enrollment_id, cs.section_name, cs.schedule, cs.room, cs.semester, cs.school_year,
           c.course_code, c.course_name,
           CONCAT(i.first_name, ' ', i.last_name) as instructor_name
    FROM enrollments e
    JOIN class_sections cs ON e.section_id = cs.section_id
    JOIN courses c ON cs.course_id = c.course_id
    JOIN instructors i ON cs.instructor_id = i.instructor_id
    WHERE e.student_id = ? AND cs.status = 'active'
    ORDER BY cs.school_year DESC, cs.semester DESC
");
$enrollmentsStmt->bind_param("i", $studentId);
$enrollmentsStmt->execute();
$enrollments = $enrollmentsStmt->get_result();
$enrollmentsStmt->close();

$pageTitle = 'Student Profile: ' . $student['first_name'] . ' ' . $student['last_name'];
require_once '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="students.php">Students</a></li>
                <li class="breadcrumb-item active">Student Profile</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <!-- Left Column: Profile Card -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body text-center p-4">
                <div class="mb-3">
                    <div class="rounded-circle bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center" style="width: 100px; height: 100px;">
                        <i class="fas fa-user-graduate fa-4x text-primary"></i>
                    </div>
                </div>
                <h4 class="mb-1"><?php echo htmlspecialchars(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')); ?></h4>
                <p class="text-muted mb-3"><?php echo htmlspecialchars($student['student_no'] ?? ''); ?></p>
                <div class="badge bg-<?php echo $student['status'] === 'active' ? 'success' : 'secondary'; ?> rounded-pill px-3 mb-4">
                    <?php echo ucfirst($student['status']); ?>
                </div>
                
                <div class="row text-center mt-2 border-top pt-3">
                    <div class="col-6 border-end">
                        <div class="small text-muted mb-1">Year Level</div>
                        <div class="fw-bold fs-5"><?php echo $student['year_level']; ?></div>
                    </div>
                    <div class="col-6">
                        <div class="small text-muted mb-1">GWA</div>
                        <div class="fw-bold fs-5 text-primary"><?php echo $gwa !== null ? number_format($gwa, 2) : '0.00'; ?></div>
                    </div>
                </div>

                <?php if (!empty($student['academic_honor'])): ?>
                <div class="mt-3 p-2 bg-success bg-opacity-10 border border-success border-opacity-25 rounded-3">
                    <div class="small text-success fw-bold text-uppercase" style="font-size: 0.65rem;"><i class="fas fa-medal me-1"></i> Academic Honor</div>
                    <div class="fw-bold text-success fs-6"><?php echo htmlspecialchars($student['academic_honor'] ?? ''); ?></div>
                </div>
                <?php else:
                    $hasBacklog = hasAcademicBacklog($studentId);
                    if ($hasBacklog && $gwa <= 2.00): 
                ?>
                <div class="mt-3">
                    <span class="badge bg-danger-subtle text-danger p-2 w-100" title="Ineligible for honors due to academic backlogs (INC/Fail/Drop)">
                        <i class="fas fa-exclamation-circle me-1"></i> Honors Disqualified
                    </span>
                </div>
                <?php endif; endif; ?>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="fas fa-info-circle me-2 text-primary"></i> Personal Details</h6>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between py-3">
                        <span class="text-muted">Gender</span>
                        <span class="fw-medium"><?php echo htmlspecialchars($student['gender'] ?? 'Not set'); ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between py-3">
                        <span class="text-muted">Birth Date</span>
                        <span class="fw-medium"><?php echo formatDate($student['date_of_birth']); ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between py-3">
                        <span class="text-muted">Email</span>
                        <span class="fw-medium"><?php echo htmlspecialchars($student['email'] ?? 'N/A'); ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between py-3">
                        <span class="text-muted">Contact</span>
                        <span class="fw-medium"><?php echo htmlspecialchars($student['contact_number'] ?? 'N/A'); ?></span>
                    </li>
                    <li class="list-group-item d-flex flex-column py-3">
                        <span class="text-muted mb-1">Address</span>
                        <span class="fw-medium"><?php echo htmlspecialchars(($student['address'] ?? '') . ' ' . ($student['municipality'] ?? '')); ?></span>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Right Column: Enrollment & Academic Info -->
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="fas fa-book me-2 text-primary"></i> Current Enrolled Subjects</h6>
                <a href="manage_student_schedule.php?student_id=<?php echo $studentId; ?>" class="btn btn-primary btn-sm rounded-pill px-3">
                    <i class="fas fa-calendar-check me-1"></i> Manage Enrollment
                </a>
            </div>
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th>Subject</th>
                                <th>Section / Schedule</th>
                                <th>Instructor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($enrollments->num_rows > 0): ?>
                                <?php while ($e = $enrollments->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-primary"><?php echo htmlspecialchars($e['course_code'] ?? ''); ?></div>
                                            <div class="small text-muted"><?php echo htmlspecialchars($e['course_name'] ?? ''); ?></div>
                                        </td>
                                        <td>
                                            <div class="badge bg-secondary mb-1"><?php echo htmlspecialchars($e['section_name'] ?? ''); ?></div>
                                            <div class="small fw-bold"><?php echo htmlspecialchars($e['schedule'] ?? 'TBA'); ?></div>
                                            <div class="small text-muted"><i class="fas fa-door-open me-1 text-xs"></i> <?php echo htmlspecialchars($e['room'] ?? 'TBA'); ?></div>
                                        </td>
                                        <td>
                                            <div class="small fw-medium text-dark"><?php echo htmlspecialchars($e['instructor_name'] ?? ''); ?></div>
                                            <div class="small text-muted"><?php echo htmlspecialchars(($e['semester'] ?? '') . ' ' . ($e['school_year'] ?? '')); ?></div>
                                        </td>
                                    </tr>
                                <?php
    endwhile; ?>
                            <?php
else: ?>
                                <tr>
                                    <td colspan="3" class="text-center py-5 text-muted italic">
                                        No active enrollments found for this student.
                                    </td>
                                </tr>
                            <?php
endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 text-start">
                <h6 class="mb-0 fw-bold"><i class="fas fa-graduation-cap me-2 text-primary"></i> Academic Program</h6>
            </div>
            <div class="card-body p-4 text-start">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small text-start">College / Diploma Program</label>
                        <div class="fw-bold"><?php echo htmlspecialchars(($student['college_name'] ? $student['college_name'] . ' - ' : '') . ($student['dept_name'] ?? 'N/A')); ?></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">Program (Course)</label>
                        <div class="fw-bold"><?php echo htmlspecialchars($student['program_name'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">Enrollment Date</label>
                        <div class="fw-bold"><?php echo formatDate($student['enrollment_date']); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
