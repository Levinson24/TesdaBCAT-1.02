<?php
/**
 * Admin - View Student Details
 * TESDA-BCAT Grade Management System
 */
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('admin');

$conn = getDBConnection();

$studentId = isset($_GET['id']) ? intval($_GET['id']) : (isset($_GET['student_id']) ? intval($_GET['student_id']) : 0);
if (!$studentId) {
    redirectWithMessage('students.php', 'Invalid student ID.', 'danger');
}

// Fetch student details - Full access for admin
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
$stmt->close();

if (!$student) {
    redirectWithMessage('students.php', 'Student not found.', 'danger');
}

// Calculate GWA
$gwa = calculateGWA($studentId);

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

$pageTitle = 'Student Profile: ' . $student['first_name'] . ' ' . $student['last_name'];
require_once '../includes/header.php';
?>

<div class="row mb-4 no-print">
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
        <div class="card premium-card border-0 mb-4 shadow-sm">
            <div class="card-body text-center p-4">
                <div class="mb-3">
                    <div class="rounded-circle bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center" style="width: 100px; height: 100px;">
                        <i class="fas fa-user-graduate fa-4x text-primary"></i>
                    </div>
                </div>
                <h4 class="mb-1 fw-bold"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h4>
                <p class="text-muted mb-3 fw-semibold"><?php echo htmlspecialchars($student['student_no']); ?></p>
                <div class="badge bg-<?php echo $student['status'] === 'active' ? 'success' : 'secondary'; ?> rounded-pill px-4 py-2 mb-4">
                    <?php echo strtoupper($student['status']); ?>
                </div>
                
                <div class="row text-center mt-2 border-top pt-3">
                    <div class="col-6 border-end">
                        <div class="small text-muted mb-1 text-uppercase fw-bold" style="font-size: 0.65rem;">Year Level</div>
                        <div class="fw-bold fs-5 text-dark"><?php echo $student['year_level']; ?></div>
                    </div>
                    <div class="col-6">
                        <div class="small text-muted mb-1 text-uppercase fw-bold" style="font-size: 0.65rem;">Total GWA</div>
                        <div class="fw-bold fs-5 text-primary"><?php echo $gwa !== null ? number_format($gwa, 2) : '0.00'; ?></div>
                    </div>
                </div>

                <?php
$hasBacklog = hasAcademicBacklog($studentId);
$honors = getLatinHonors($gwa, $hasBacklog);
if ($honors): ?>
                <div class="mt-3">
                    <span class="badge bg-warning text-dark p-2 w-100 shadow-sm border border-warning border-opacity-50">
                        <i class="fas fa-medal me-1"></i> <?php echo $honors; ?>
                    </span>
                </div>
                <?php
elseif ($hasBacklog && $gwa <= 2.00): ?>
                <div class="mt-3">
                    <span class="badge bg-danger-subtle text-danger p-2 w-100 shadow-sm" title="Ineligible for honors due to academic backlogs (INC/Fail/Drop)">
                        <i class="fas fa-exclamation-circle me-1"></i> Honors Disqualified
                    </span>
                </div>
                <?php
endif; ?>
            </div>
        </div>

        <div class="card premium-card border-0 mb-4 shadow-sm">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="mb-0 fw-bold text-primary"><i class="fas fa-info-circle me-2"></i> Personal Information</h6>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush border-0">
                    <li class="list-group-item d-flex justify-content-between py-3 bg-transparent">
                        <span class="text-muted small fw-bold text-uppercase">Gender</span>
                        <span class="fw-bold text-dark"><?php echo htmlspecialchars($student['gender'] ?? 'Not set'); ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between py-3 bg-transparent">
                        <span class="text-muted small fw-bold text-uppercase">Birth Date</span>
                        <span class="fw-bold text-dark"><?php echo formatDate($student['date_of_birth']); ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between py-3 bg-transparent">
                        <span class="text-muted small fw-bold text-uppercase">Email</span>
                        <span class="fw-bold text-primary"><?php echo htmlspecialchars($student['email'] ?? 'N/A'); ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between py-3 bg-transparent">
                        <span class="text-muted small fw-bold text-uppercase">Contact</span>
                        <span class="fw-bold text-dark"><?php echo htmlspecialchars($student['contact_number'] ?? 'N/A'); ?></span>
                    </li>
                    <li class="list-group-item d-flex flex-column py-3 bg-transparent border-0">
                        <span class="text-muted small fw-bold text-uppercase mb-1">Permanent Address</span>
                        <span class="fw-medium text-dark"><?php echo htmlspecialchars(($student['address'] ?? '') . ' ' . ($student['municipality'] ?? '')); ?></span>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Right Column: Enrollment & Academic Info -->
    <div class="col-lg-8">
        <div class="card premium-card border-0 mb-4 shadow-sm">
            <div class="card-header bg-white py-4 border-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-book-open me-2"></i> Academic Enrollments</h5>
                <div>
                   <a href="../registrar/curriculum_evaluation.php?id=<?php echo $studentId; ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3 me-2" target="_blank">
                        <i class="fas fa-print me-1"></i> Evaluation Report
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4 border-0 small fw-bold text-uppercase">Course & Code</th>
                                <th class="border-0 small fw-bold text-uppercase">Sect / Schedule</th>
                                <th class="border-0 small fw-bold text-uppercase">Faculty Assignment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($enrollments->num_rows > 0): ?>
                                <?php while ($e = $enrollments->fetch_assoc()): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-bold text-primary"><?php echo htmlspecialchars($e['course_code']); ?></div>
                                            <div class="small text-muted fw-semibold"><?php echo htmlspecialchars($e['course_name']); ?></div>
                                        </td>
                                        <td>
                                            <div class="badge bg-secondary rounded-pill mb-1"><?php echo htmlspecialchars($e['section_name']); ?></div>
                                            <div class="small fw-bold text-dark"><?php echo htmlspecialchars($e['schedule'] ?? 'TBA'); ?></div>
                                            <div class="small text-muted" style="font-size: 0.7rem;"><i class="fas fa-door-open me-1"></i> <?php echo htmlspecialchars($e['room'] ?? 'TBA'); ?></div>
                                        </td>
                                        <td>
                                            <div class="small fw-bold text-dark"><?php echo htmlspecialchars($e['instructor_name']); ?></div>
                                            <div class="badge bg-light text-primary border border-primary border-opacity-10 mt-1" style="font-size: 0.65rem;"><?php echo htmlspecialchars($e['semester'] . ' ' . $e['school_year']); ?></div>
                                        </td>
                                    </tr>
                                <?php
    endwhile; ?>
                            <?php
else: ?>
                                <tr>
                                    <td colspan="3" class="text-center py-5 text-muted">
                                        <img src="../BCAT logo 2024.png" class="opacity-10 mb-3" style="width: 80px; filter: grayscale(1);">
                                        <div class="fw-bold">No Records Found</div>
                                        <div class="small">This student hasn't been enrolled in any sections yet.</div>
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
                <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-graduation-cap me-2"></i> Degree Audit Placement</h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded-4 h-100">
                            <label class="text-muted small fw-bold text-uppercase mb-1 d-block" style="font-size: 0.65rem;">Diploma Program / Department</label>
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($student['dept_name'] ?? 'Not Assigned'); ?></div>
                            <div class="small text-muted mt-1"><?php echo htmlspecialchars($student['college_name'] ?? ''); ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded-4 h-100">
                            <label class="text-muted small fw-bold text-uppercase mb-1 d-block" style="font-size: 0.65rem;">Academic Major (Program)</label>
                            <div class="fw-bold text-primary"><?php echo htmlspecialchars($student['program_name'] ?? 'General Education'); ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded-4">
                            <label class="text-muted small fw-bold text-uppercase mb-1 d-block" style="font-size: 0.65rem;">Admission Date</label>
                            <div class="fw-bold text-dark"><?php echo formatDate($student['enrollment_date']); ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded-4">
                            <label class="text-muted small fw-bold text-uppercase mb-1 d-block" style="font-size: 0.65rem;">User Account link</label>
                            <div class="fw-bold text-dark"><i class="fas fa-id-card me-2 text-muted"></i><?php echo htmlspecialchars($student['username']); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
