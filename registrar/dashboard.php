<?php
/**
 * Registrar Dashboard
 * TESDA-BCAT Grade Management System
 */

$pageTitle = 'Registrar Dashboard';
require_once '../includes/header.php';

requireRole(['registrar', 'registrar_staff']);

$conn = getDBConnection();
$userRole = getCurrentUserRole();
$userProfile = getUserProfile(getCurrentUserId(), $userRole);
$deptId = $userProfile['dept_id'] ?? 0;
$isStaff = ($userRole === 'registrar_staff');

// Get statistics
$studentWhere = "WHERE status = 'active'";
$instructorWhere = "WHERE status = 'active'";
$courseWhere = "WHERE status = 'active'";
$sectionWhere = "WHERE cs.status = 'active'";
$gradeWhere = "WHERE g.status = 'submitted'";
$enrollmentWhere = "WHERE e.status = 'enrolled' AND cs.status = 'active'";

if ($isStaff) {
    $studentWhere .= " AND dept_id = $deptId";
    $instructorWhere .= " AND dept_id = $deptId";
    $courseWhere .= " AND dept_id = $deptId";
    $sectionWhere .= " AND c.dept_id = $deptId";
    $gradeWhere .= " AND s.dept_id = $deptId";
    $enrollmentWhere .= " AND s.dept_id = $deptId";
}

$result = $conn->query("SELECT COUNT(*) as total FROM students $studentWhere");
$totalStudents = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM instructors $instructorWhere");
$totalInstructors = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM courses $courseWhere");
$totalCourses = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM class_sections cs JOIN courses c ON cs.course_id = c.course_id $sectionWhere");
$activeSections = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM grades g JOIN students s ON g.student_id = s.student_id $gradeWhere");
$pendingApprovals = $result->fetch_assoc()['total'];

$result = $conn->query("
    SELECT COUNT(*) as total FROM enrollments e
    JOIN class_sections cs ON e.section_id = cs.section_id
    JOIN students s ON e.student_id = s.student_id
    $enrollmentWhere
");
$currentEnrollments = $result->fetch_assoc()['total'];

// Get pending grade approvals
$pendingGrades = $conn->query("
    SELECT 
        g.*,
        s.student_no,
        CONCAT(IFNULL(s.first_name,''), ' ', IFNULL(s.last_name,'')) as student_name,
        c.course_code,
        c.course_name,
        cs.section_name,
        cs.semester,
        cs.school_year,
        CONCAT(IFNULL(i.first_name,''), ' ', IFNULL(i.last_name,'')) as instructor_name,
        g.submitted_at
    FROM grades g
    JOIN students s ON g.student_id = s.student_id
    JOIN enrollments e ON g.enrollment_id = e.enrollment_id
    JOIN class_sections cs ON e.section_id = cs.section_id
    JOIN courses c ON cs.course_id = c.course_id
    JOIN instructors i ON cs.instructor_id = i.instructor_id
    $gradeWhere
    ORDER BY g.submitted_at DESC
    LIMIT 10
");
?>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card premium-card overflow-hidden shadow-sm border-0">
            <div class="card-body p-0">
                <div class="row g-0">
                    <div class="col-md-3 bg-primary bg-opacity-10 d-flex align-items-center justify-content-center p-4">
                        <div class="user-avatar bg-white border shadow-sm p-3" style="width: 120px; height: 120px; border-radius: 2rem; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                            <img src="../BCAT logo 2024.png" alt="BCAT Logo" class="img-fluid" style="max-height: 100%; width: auto;">
                        </div>
                    </div>
                    <div class="col-md-9 p-4">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h2 class="display-6 fw-bold mb-1 text-primary"><?php echo htmlspecialchars($currentUser['username'] ?? 'Registrar'); ?></h2>
                                <p class="text-accent-indigo fw-semibold mb-3">
                                    <i class="fas fa-id-badge me-1"></i> <?php echo (getCurrentUserRole() === 'registrar') ? 'Official Registrar Portal' : 'Registrar Staff Access'; ?>
                                </p>
                            </div>
                            <div class="d-flex flex-column align-items-end">
                                <span class="badge glass-effect text-primary px-3 py-2 rounded-pill fw-bold border border-primary border-opacity-10 mb-2">
                                    <i class="fas fa-shield-alt me-1"></i> Verified Official
                                </span>
                                <div class="text-muted small"><i class="far fa-calendar-alt me-1"></i> <?php echo date('F d, Y'); ?></div>
                            </div>
                        </div>
                        <div class="row g-4 mt-1">
                            <div class="col-sm-4 border-start ps-4">
                                <div class="text-muted small text-uppercase fw-bold mb-1">Account Role</div>
                                <div class="fw-semibold text-dark"><?php 
                                    if ($userRole === 'registrar') echo 'Head Registrar';
                                    elseif ($userRole === 'registrar_staff') echo 'Registrar Staff';
                                    else echo ucfirst($userRole); 
                                ?></div>
                            </div>
                            <div class="col-sm-4 border-start ps-4">
                                <div class="text-muted small text-uppercase fw-bold mb-1">Email Connection</div>
                                <div class="fw-semibold text-truncate"><?php echo htmlspecialchars($currentUser['email'] ?? 'Not Set'); ?></div>
                            </div>
                            <div class="col-sm-4 border-start ps-4">
                                <div class="text-muted small text-uppercase fw-bold mb-1">Academic Cycle</div>
                                <div class="fw-semibold text-accent-indigo"><?php echo getSetting('current_semester', '1st') . ' - ' . getSetting('academic_year', '2024-2025'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card premium-card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="stat-card-icon-v2 bg-primary bg-opacity-10 text-primary mb-3">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div>
                    <div class="text-muted small fw-bold text-uppercase opacity-75">Active Students</div>
                    <h3 class="mb-0 fw-bold display-6 text-primary"><?php echo number_format($totalStudents); ?></h3>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card premium-card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="stat-card-icon-v2 bg-info bg-opacity-10 text-info mb-3">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
                <div>
                    <div class="text-muted small fw-bold text-uppercase opacity-75">Faculty Registry</div>
                    <h3 class="mb-0 fw-bold display-6 text-info"><?php echo number_format($totalInstructors); ?></h3>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card premium-card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="stat-card-icon-v2 bg-success bg-opacity-10 text-success mb-3">
                    <i class="fas fa-layer-group"></i>
                </div>
                <div>
                    <div class="text-muted small fw-bold text-uppercase opacity-75">Active Sections</div>
                    <h3 class="mb-0 fw-bold display-6 text-success"><?php echo number_format($activeSections); ?></h3>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card premium-card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="stat-card-icon-v2 bg-warning bg-opacity-10 text-warning mb-3">
                    <i class="fas fa-clipboard-check"></i>
                </div>
                <div>
                    <div class="text-muted small fw-bold text-uppercase opacity-75">Grade Approvals</div>
                    <h3 class="mb-0 fw-bold display-6 text-warning"><?php echo number_format($pendingApprovals); ?></h3>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6 mb-4 mb-md-0">
        <div class="card premium-card h-100 border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 p-4 d-flex align-items-center">
                <i class="fas fa-chart-pie me-2 text-primary"></i>
                <h5 class="mb-0 fw-bold">Academic Statistics</h5>
            </div>
            <div class="card-body px-4 pb-4 pt-0">
                <div class="list-group list-group-flush">
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0 bg-transparent py-3">
                        <span class="text-muted"><i class="fas fa-book me-2 text-success"></i> Total Course Offerings</span>
                        <span class="badge bg-light text-dark border px-3"><?php echo $totalCourses; ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0 bg-transparent py-3">
                        <span class="text-muted"><i class="fas fa-user-plus me-2 text-info"></i> Active Enrollments</span>
                        <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-3"><?php echo $currentEnrollments; ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0 bg-transparent py-3">
                        <span class="text-muted"><i class="fas fa-calendar-alt me-2 text-primary"></i> Current Year</span>
                        <span class="fw-bold text-primary"><?php echo getSetting('academic_year', '2024-2025'); ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0 bg-transparent py-3 border-0">
                        <span class="text-muted"><i class="fas fa-clock me-2 text-warning"></i> Active Semester</span>
                        <span class="badge gradient-navy text-white border-0 px-3"><?php echo getSetting('current_semester', '1st'); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card premium-card h-100 border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 p-4 d-flex align-items-center">
                <i class="fas fa-tasks me-2 text-success"></i>
                <h5 class="mb-0 fw-bold">Registrar Quick Actions</h5>
            </div>
            <div class="card-body px-4 pb-4 pt-0">
                <div class="row g-3">
                    <div class="col-6">
                        <a href="students.php" class="btn btn-light bg-info bg-opacity-10 border-0 text-info w-100 py-4 d-flex flex-column align-items-center rounded-4 transition-all">
                            <i class="fas fa-user-graduate mb-2 fa-2x"></i>
                            <span class="fw-bold">Student Registry</span>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="enrollments.php" class="btn btn-light bg-primary bg-opacity-10 border-0 text-primary w-100 py-4 d-flex flex-column align-items-center rounded-4 transition-all">
                            <i class="fas fa-user-plus mb-2 fa-2x"></i>
                            <span class="fw-bold">Enroll Student</span>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="grades.php" class="btn btn-light bg-warning bg-opacity-10 border-0 text-warning w-100 py-4 d-flex flex-column align-items-center rounded-4 transition-all position-relative">
                            <i class="fas fa-clipboard-check mb-2 fa-2x"></i>
                            <span class="fw-bold">Process Grades</span>
                            <?php if ($pendingApprovals > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger shadow">
                                    <?php echo $pendingApprovals; ?>
                                </span>
                            <?php
endif; ?>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="transcripts.php" class="btn btn-light bg-success bg-opacity-10 border-0 text-success w-100 py-4 d-flex flex-column align-items-center rounded-4 transition-all">
                            <i class="fas fa-file-alt mb-2 fa-2x"></i>
                            <span class="fw-bold">Transcripts</span>
                        </a>
                    </div>
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
                    <i class="fas fa-clipboard-list me-2 text-warning"></i>
                    <h5 class="mb-0 fw-bold">Recent Grade Submissions</h5>
                </div>
                <a href="grades.php" class="btn btn-primary rounded-pill px-4">
                    Full Review Registry
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 table-mobile-card">
                        <thead class="gradient-navy text-white">
                            <tr>
                                <th class="ps-4 text-warning border-0 small text-uppercase">Student Identity</th>
                                <th class="border-0 small text-uppercase">Academic Subject</th>
                                <th class="border-0 small text-uppercase">Cycle Info</th>
                                <th class="border-0 small text-uppercase">Faculty</th>
                                <th class="border-0 small text-uppercase">Mid/Final</th>
                                <th class="border-0 small text-uppercase">Grade</th>
                                <th class="border-0 small text-uppercase">Timeline</th>
                                <th class="text-end pe-4 border-0 small text-uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($pendingGrades->num_rows > 0): ?>
                                <?php while ($grade = $pendingGrades->fetch_assoc()): ?>
                                <tr>
                                    <td class="ps-4" data-label="Student Identity">
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($grade['student_name'] ?? ''); ?></div>
                                        <div class="text-muted small"><?php echo htmlspecialchars($grade['student_no'] ?? ''); ?></div>
                                    </td>
                                    <td data-label="Academic Subject">
                                        <div class="fw-bold text-primary small"><?php echo htmlspecialchars($grade['course_code'] ?? ''); ?></div>
                                        <div class="text-muted" style="font-size: 0.7rem;"><?php echo htmlspecialchars($grade['course_name'] ?? ''); ?></div>
                                    </td>
                                    <td data-label="Cycle Info">
                                        <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($grade['section_name'] ?? ''); ?></span>
                                        <div class="text-muted" style="font-size: 0.65rem;"><?php echo htmlspecialchars($grade['semester'] . ' ' . $grade['school_year']); ?></div>
                                    </td>
                                    <td data-label="Faculty">
                                        <div class="small fw-semibold"><?php echo htmlspecialchars($grade['instructor_name'] ?? ''); ?></div>
                                    </td>
                                    <td data-label="Mid/Final">
                                        <span class="text-muted small">M:</span> <?php echo $grade['midterm'] !== null ? number_format($grade['midterm'], 2) : '-'; ?>
                                        <span class="text-muted small ms-1">F:</span> <?php echo $grade['final'] !== null ? number_format($grade['final'], 2) : '-'; ?>
                                    </td>
                                    <td data-label="Grade">
                                        <span class="badge bg-primary px-3"><?php echo $grade['grade'] !== null ? number_format($grade['grade'], 2) : '—'; ?></span>
                                    </td>
                                    <td data-label="Timeline">
                                        <div class="small text-muted"><i class="far fa-clock me-1"></i><?php echo formatDate($grade['submitted_at'], 'M d, Y'); ?></div>
                                    </td>
                                    <td class="text-end pe-4" data-label="Actions">
                                        <?php if (getCurrentUserRole() === 'registrar'): ?>
                                        <div class="d-flex justify-content-end gap-1">
                                            <form method="POST" action="grades.php" class="d-inline">
                                                <input type="hidden" name="grade_id" value="<?php echo $grade['grade_id']; ?>">
                                                <button type="submit" name="action" value="approve" class="btn btn-sm btn-success rounded-circle shadow-sm" style="width: 32px; height: 32px;" title="Approve" onclick="return confirm('Approve this grade?')">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button type="submit" name="action" value="reject" class="btn btn-sm btn-danger rounded-circle shadow-sm" style="width: 32px; height: 32px;" title="Reject" onclick="return confirm('Reject this grade?')">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form>
                                        </div>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted border">Review Only</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php
    endwhile; ?>
                            <?php
else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5">
                                        <div class="stat-card-icon-v2 bg-success bg-opacity-10 text-success mx-auto mb-3">
                                            <i class="fas fa-check-double"></i>
                                        </div>
                                        <h5 class="fw-bold text-success">Registry is Clear!</h5>
                                        <p class="text-muted small">All submitted grades have been processed.</p>
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
</div>

<?php require_once '../includes/footer.php'; ?>
