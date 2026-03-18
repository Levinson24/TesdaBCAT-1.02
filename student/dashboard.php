<?php
/**
 * Student Dashboard
 * TESDA-BCAT Grade Management System
 */

$pageTitle = 'Student Dashboard';
require_once '../includes/header.php';

requireRole('student');

$conn = getDBConnection();
$userId = getCurrentUserId();

// Get student profile
$stmt = $conn->prepare("SELECT * FROM students WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    echo showError('Student profile not found. Please contact administrator.');
    require_once '../includes/footer.php';
    exit();
}

$studentId = $student['student_id'];

// Get GPA
$gpa = calculateGPA($studentId);

// Get total units earned
$stmt = $conn->prepare("
    SELECT SUM(c.units) as total_units
    FROM grades g
    JOIN enrollments e ON g.enrollment_id = e.enrollment_id
    JOIN class_sections cs ON e.section_id = cs.section_id
    JOIN courses c ON cs.course_id = c.course_id
    WHERE g.student_id = ? AND g.status = 'approved' AND g.remarks = 'Passed'
");
$stmt->bind_param("i", $studentId);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$totalUnits = $result['total_units'] ?? 0;
$stmt->close();

// Get current enrollments
$currentEnrollments = $conn->prepare("
    SELECT COUNT(*) as total
    FROM enrollments e
    JOIN class_sections cs ON e.section_id = cs.section_id
    WHERE e.student_id = ? AND e.status = 'enrolled' AND cs.status = 'active'
");
$currentEnrollments->bind_param("i", $studentId);
$currentEnrollments->execute();
$enrollmentCount = $currentEnrollments->get_result()->fetch_assoc()['total'];
$currentEnrollments->close();

// Get recent grades
$recentGrades = $conn->prepare("
    SELECT 
        c.course_code,
        c.course_name,
        cs.semester,
        cs.school_year,
        g.midterm,
        g.final,
        g.grade,
        g.remarks,
        g.status
    FROM grades g
    JOIN enrollments e ON g.enrollment_id = e.enrollment_id
    JOIN class_sections cs ON e.section_id = cs.section_id
    JOIN courses c ON cs.course_id = c.course_id
    WHERE g.student_id = ?
    ORDER BY cs.school_year DESC, cs.semester DESC
    LIMIT 10
");
$recentGrades->bind_param("i", $studentId);
$recentGrades->execute();
$recentGrades = $recentGrades->get_result();

// Get current enrolled classes with schedules
$mySchedules = $conn->prepare("
    SELECT 
        cs.*,
        c.course_code,
        c.course_name,
        c.units,
        CONCAT(i.first_name, ' ', i.last_name) as instructor_name
    FROM enrollments e
    JOIN class_sections cs ON e.section_id = cs.section_id
    JOIN courses c ON cs.course_id = c.course_id
    JOIN instructors i ON cs.instructor_id = i.instructor_id
    WHERE e.student_id = ? AND e.status = 'enrolled' AND cs.status = 'active'
    ORDER BY cs.semester DESC, c.course_code
");
$mySchedules->bind_param("i", $studentId);
$mySchedules->execute();
$mySchedules = $mySchedules->get_result();

// NEW: Get grades approved in the last 7 days for notifications
$newGradesStmt = $conn->prepare("
    SELECT c.course_code, c.course_name, g.grade, g.approved_at
    FROM grades g
    JOIN enrollments e ON g.enrollment_id = e.enrollment_id
    JOIN class_sections cs ON e.section_id = cs.section_id
    JOIN courses c ON cs.course_id = c.course_id
    WHERE g.student_id = ? AND g.status = 'approved' 
    AND g.approved_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY g.approved_at DESC
");
$newGradesStmt->bind_param("i", $studentId);
$newGradesStmt->execute();
$newGrades = $newGradesStmt->get_result();
?>

<?php if ($newGrades->num_rows > 0): ?>
<div class="row mb-4">
    <div class="col-md-12">
        <div class="alert alert-success border-0 shadow-sm rounded-4 p-4 mb-0">
            <div class="d-flex align-items-center mb-2">
                <div class="bg-success bg-opacity-10 p-2 rounded-circle me-3">
                    <i class="fas fa-bell text-success fa-lg"></i>
                </div>
                <h5 class="fw-bold mb-0">New Grades Finalized!</h5>
            </div>
            <p class="text-muted mb-3">Your grades for the following subjects have been finalized by the registrar in the last 7 days:</p>
            <div class="row g-2">
                <?php while ($ng = $newGrades->fetch_assoc()): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="bg-white p-3 rounded-3 border d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($ng['course_code']); ?></div>
                            <div class="small text-muted"><?php echo htmlspecialchars($ng['course_name']); ?></div>
                        </div>
                        <div class="badge bg-primary fs-6 px-3"><?php echo number_format($ng['grade'], 2); ?></div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <hr class="opacity-10 my-3">
            <div class="text-end">
                <a href="my_grades.php" class="btn btn-success btn-sm px-4 rounded-pill fw-bold">View Full Grade Report</a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row mb-5">
    <div class="col-md-12">
        <div class="card premium-card overflow-hidden shadow-lg border-0" style="background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);">
            <div class="card-body p-0">
                <div class="row g-0">
                    <div class="col-lg-3 bg-primary bg-opacity-10 d-flex flex-column align-items-center justify-content-center p-5 position-relative overflow-hidden">
                        <div style="position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: rgba(26, 58, 92, 0.05); border-radius: 50%;"></div>
                        <div class="user-avatar bg-white border shadow-sm p-3 mb-3" style="width: 140px; height: 140px; border-radius: 2.5rem; display: flex; align-items: center; justify-content: center; overflow: hidden; z-index: 1;">
                            <img src="../BCAT logo 2024.png" alt="BCAT Logo" class="img-fluid" style="max-height: 100%; width: auto;">
                        </div>
                        <span class="badge bg-success text-white px-3 py-2 rounded-pill fw-bold shadow-sm" style="z-index: 1;">
                            <i class="fas fa-check-circle me-1"></i> ACTIVE STUDENT
                        </span>
                    </div>
                    <div class="col-lg-9 p-5">
                        <div class="d-flex justify-content-between align-items-start mb-4">
                            <div>
                                <h1 class="display-5 fw-bold mb-1 text-primary"><?php echo htmlspecialchars(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')); ?></h1>
                                <p class="text-accent-indigo fw-semibold lead mb-0">
                                    <i class="fas fa-graduation-cap me-1"></i> Official Student Portal Access
                                </p>
                            </div>
                            <div class="text-end d-none d-md-block">
                                <div class="text-muted small fw-bold text-uppercase mb-1"><i class="far fa-calendar-alt me-1"></i> Academic Cycle</div>
                                <div class="fw-bold text-primary"><?php echo getSetting('current_semester', '1st') . ' - ' . getSetting('academic_year', '2024-2025'); ?></div>
                            </div>
                        </div>
                        <div class="row g-4 mt-2">
                            <div class="col-sm-4 border-start ps-4">
                                <div class="text-muted small text-uppercase fw-bold mb-1">Student Identity</div>
                                <div class="fw-bold text-dark fs-5"><?php echo htmlspecialchars($student['student_no'] ?? ''); ?></div>
                            </div>
                            <div class="col-sm-4 border-start ps-4">
                                <div class="text-muted small text-uppercase fw-bold mb-1">Degree Program</div>
                                <div class="fw-bold text-dark fs-5"><?php echo htmlspecialchars($student['course'] ?? 'Not Set'); ?></div>
                            </div>
                            <div class="col-sm-4 border-start ps-4">
                                <div class="text-muted small text-uppercase fw-bold mb-1">Academic Status</div>
                                <div class="fw-bold text-accent-indigo fs-5">Year Level <?php echo $student['year_level']; ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Get honors status
$hasBacklog = hasAcademicBacklog($studentId);
$honors = getLatinHonors($gpa, $hasBacklog);
?>

<div class="responsive-grid mb-5">
    <!-- GWA Card -->
    <div class="card premium-card p-4 border-0 shadow-sm position-relative">
        <?php if ($honors): ?>
            <div class="position-absolute top-0 end-0 p-3">
                <span class="badge bg-warning text-dark shadow-sm px-3 py-2 rounded-pill fw-bold">
                    <i class="fas fa-medal me-1"></i> <?php echo $honors; ?>
                </span>
            </div>
        <?php elseif ($hasBacklog && $gpa <= 2.00): ?>
            <div class="position-absolute top-0 end-0 p-3" title="Ineligible for honors due to academic backlogs (INC/Fail/Drop)">
                <span class="badge bg-danger-subtle text-danger shadow-sm px-3 py-2 rounded-pill fw-bold">
                    <i class="fas fa-exclamation-circle me-1"></i> Disqualified
                </span>
            </div>
        <?php endif; ?>
        
        <div class="stat-card-icon-v2 bg-primary bg-opacity-10 text-primary mb-3">
            <i class="fas fa-chart-line"></i>
        </div>
        <div class="text-muted small fw-bold text-uppercase opacity-75 mb-1">General Weighted Average</div>
        <h2 class="mb-0 fw-bold display-6 text-primary"><?php echo $gpa > 0 ? number_format($gpa, 2) : '0.00'; ?></h2>
    </div>
    
    <!-- Units Card -->
    <div class="card premium-card p-4 border-0 shadow-sm">
        <div class="stat-card-icon-v2 bg-success bg-opacity-10 text-success mb-3">
            <i class="fas fa-book-open"></i>
        </div>
        <div class="text-muted small fw-bold text-uppercase opacity-75 mb-1">Total Units Earned</div>
        <h2 class="mb-0 fw-bold display-6 text-success"><?php echo $totalUnits; ?></h2>
    </div>
    
    <!-- Enrollments Card -->
    <div class="card premium-card p-4 border-0 shadow-sm">
        <div class="stat-card-icon-v2 bg-info bg-opacity-10 text-info mb-3">
            <i class="fas fa-graduation-cap"></i>
        </div>
        <div class="text-muted small fw-bold text-uppercase opacity-75 mb-1">Active Enrollments</div>
        <h2 class="mb-0 fw-bold display-6 text-info"><?php echo $enrollmentCount; ?></h2>
    </div>

    <!-- Quick Link Card -->
    <div class="card premium-card p-4 border-0 shadow-sm bg-accent-indigo text-white" style="background: var(--sidebar-gradient);">
        <div class="d-flex flex-column h-100 justify-content-between">
            <div>
                <h5 class="fw-bold mb-1">Academic Records</h5>
                <p class="small opacity-75 mb-3">Instant access to your official documents.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="cor.php" class="btn btn-light btn-sm rounded-pill px-3 fw-bold">View COR</a>
                <a href="my_grades.php" class="btn btn-primary btn-sm rounded-pill px-3 fw-bold border-white">Full Transcript</a>
            </div>
        </div>
    </div>
</div>

<div class="row mb-5">
    <div class="col-md-12">
        <div class="card premium-card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 p-4 d-flex align-items-center">
                <div class="stat-card-icon-v2 bg-primary bg-opacity-10 text-primary me-3" style="width: 45px; height: 45px; font-size: 1.1rem;">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <h5 class="mb-0 fw-bold text-dark">Active Class Schedule</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 table-mobile-card">
                        <thead class="bg-light text-muted small text-uppercase">
                            <tr>
                                <th class="ps-4">Academic Subject</th>
                                <th>Faculty Member</th>
                                <th>Schedule Info</th>
                                <th>Venue</th>
                                <th class="pe-4">Section</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($mySchedules->num_rows > 0): ?>
                                <?php while ($sched = $mySchedules->fetch_assoc()): ?>
                                <tr>
                                    <td class="ps-4" data-label="Academic Subject">
                                        <div class="fw-bold text-primary"><?php echo htmlspecialchars($sched['course_code'] ?? ''); ?></div>
                                        <div class="text-muted small"><?php echo htmlspecialchars($sched['course_name'] ?? ''); ?></div>
                                    </td>
                                    <td data-label="Faculty Member">
                                        <div class="small fw-semibold"><?php echo htmlspecialchars($sched['instructor_name'] ?? ''); ?></div>
                                    </td>
                                    <td data-label="Schedule Info">
                                        <span class="badge bg-light text-primary border px-3 rounded-pill fw-semibold">
                                            <i class="fas fa-clock me-1 opacity-75"></i>
                                            <?php echo htmlspecialchars($sched['schedule'] ?? 'TBA'); ?>
                                        </span>
                                    </td>
                                    <td data-label="Venue">
                                        <div class="small fw-bold text-dark">
                                            <i class="fas fa-door-open me-1 text-accent-indigo"></i>
                                            <?php echo htmlspecialchars($sched['room'] ?? 'TBA'); ?>
                                        </div>
                                    </td>
                                    <td class="pe-4" data-label="Section">
                                        <span class="badge gradient-navy text-white px-3"><?php echo htmlspecialchars($sched['section_name'] ?? ''); ?></span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5">
                                        <div class="stat-card-icon-v2 bg-light text-muted mx-auto mb-3">
                                            <i class="fas fa-calendar-times"></i>
                                        </div>
                                        <p class="text-muted mb-0">No active enrollments for this semester.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card premium-card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 p-4 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <div class="stat-card-icon-v2 bg-success bg-opacity-10 text-success me-3" style="width: 45px; height: 45px; font-size: 1.1rem;">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <h5 class="mb-0 fw-bold text-dark">Recent Academic Performance</h5>
                </div>
                <a href="my_grades.php" class="btn btn-primary rounded-pill px-4 shadow-sm">
                    View Complete Records
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 table-mobile-card">
                        <thead class="bg-light text-muted small text-uppercase">
                            <tr>
                                <th class="ps-4">Cycle</th>
                                <th>Subject Code</th>
                                <th>Description</th>
                                <th class="text-center">Mid/Final</th>
                                <th class="text-center">Final Grade</th>
                                <th>Standing</th>
                                <th class="pe-4">Record Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recentGrades->num_rows > 0): ?>
                                <?php while ($grade = $recentGrades->fetch_assoc()): ?>
                                <tr>
                                    <td class="ps-4" data-label="Cycle">
                                        <div class="small fw-bold"><?php echo htmlspecialchars($grade['school_year'] ?? ''); ?></div>
                                        <div class="text-muted" style="font-size: 0.65rem;"><?php echo htmlspecialchars($grade['semester'] ?? ''); ?></div>
                                    </td>
                                    <td data-label="Subject Code">
                                        <div class="fw-bold text-primary small"><?php echo htmlspecialchars($grade['course_code'] ?? ''); ?></div>
                                    </td>
                                    <td data-label="Description">
                                        <div class="text-muted small text-truncate" style="max-width: 200px;"><?php echo htmlspecialchars($grade['course_name'] ?? ''); ?></div>
                                    </td>
                                    <td class="text-center" data-label="Mid/Final">
                                        <span class="text-muted small">M:</span> <?php echo $grade['midterm'] ?? '-'; ?>
                                        <span class="text-muted small ms-2">F:</span> <?php echo $grade['final'] ?? '-'; ?>
                                    </td>
                                    <td class="text-center" data-label="Final Grade">
                                        <?php if ($grade['grade']): ?>
                                            <span class="badge bg-primary px-3 rounded-pill fs-6"><?php echo number_format($grade['grade'], 2); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Standing">
                                        <?php
                                            $remarkClass = 'secondary';
                                            $icon = 'fa-clock';
                                            switch ($grade['remarks']) {
                                                case 'Passed': $remarkClass = 'success'; $icon = 'fa-check-circle'; break;
                                                case 'Failed': $remarkClass = 'danger'; $icon = 'fa-times-circle'; break;
                                                case 'INC': $remarkClass = 'warning'; $icon = 'fa-exclamation-triangle'; break;
                                            }
                                        ?>
                                        <span class="badge bg-<?php echo $remarkClass; ?> bg-opacity-10 text-<?php echo $remarkClass; ?> px-3 border border-<?php echo $remarkClass; ?> border-opacity-25">
                                            <i class="fas <?php echo $icon; ?> me-1 small"></i> <?php echo htmlspecialchars($grade['remarks'] ?? 'Pending'); ?>
                                        </span>
                                    </td>
                                    <td class="pe-4" data-label="Record Status">
                                        <?php
                                            $statusClass = 'secondary';
                                            $statusText = 'Unknown';
                                            switch ($grade['status']) {
                                                case 'approved': $statusClass = 'success'; $statusText = 'Official'; break;
                                                case 'submitted': $statusClass = 'info'; $statusText = 'In Review'; break;
                                                case 'pending': $statusClass = 'warning'; $statusText = 'Faculty Pending'; break;
                                            }
                                        ?>
                                        <span class="badge bg-<?php echo $statusClass; ?> rounded-pill" style="font-size: 0.65rem;"><?php echo $statusText; ?></span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted small">Academic registry is currently empty.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
