<?php
/**
 * Department Head - Dashboard
 */

require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('dept_head');

$conn = getDBConnection();
$userId = getCurrentUserId();
$profile = getUserProfile($userId, 'dept_head');
$deptId = $profile['dept_id'];
$deptName = $profile['dept_name'] ?? 'Your Diploma Program';

$pageTitle = 'Diploma Program Head Dashboard';
require_once '../includes/header.php';

// Stats for this department only
$facultyCount = $conn->prepare("SELECT COUNT(*) FROM instructors WHERE dept_id = ? AND status = 'active'");
$facultyCount->bind_param("i", $deptId);
$facultyCount->execute();
$facultyCount = $facultyCount->get_result()->fetch_row()[0];

$studentCount = $conn->prepare("SELECT COUNT(*) FROM students WHERE dept_id = ? AND status = 'active'");
$studentCount->bind_param("i", $deptId);
$studentCount->execute();
$studentCount = $studentCount->get_result()->fetch_row()[0];

$courseCount = $conn->prepare("SELECT COUNT(*) FROM courses WHERE dept_id = ? AND status = 'active'");
$courseCount->bind_param("i", $deptId);
$courseCount->execute();
$courseCount = $courseCount->get_result()->fetch_row()[0];

// ─── Analytics ─────────────────────────────────────────────────────────────
// Passing Rate for the entire department
$passStats = $conn->prepare("
    SELECT 
        SUM(CASE WHEN g.remarks = 'Passed' THEN 1 ELSE 0 END) as passed,
        COUNT(*) as total
    FROM grades g
    JOIN enrollments e ON g.enrollment_id = e.enrollment_id
    JOIN class_sections cs ON e.section_id = cs.section_id
    JOIN courses c ON cs.course_id = c.course_id
    WHERE c.dept_id = ? AND g.status = 'approved'
");
$passStats->bind_param("i", $deptId);
$passStats->execute();
$passData = $passStats->get_result()->fetch_assoc();
$deptPassRate = ($passData['total'] > 0) ? round(($passData['passed'] / $passData['total']) * 100, 1) : 0;

// Top performing subjects by pass rate
$topSubjects = $conn->prepare("
    SELECT c.course_code, c.course_name, 
           ROUND((SUM(CASE WHEN g.remarks = 'Passed' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as pass_rate
    FROM grades g
    JOIN enrollments e ON g.enrollment_id = e.enrollment_id
    JOIN class_sections cs ON e.section_id = cs.section_id
    JOIN courses c ON cs.course_id = c.course_id
    WHERE c.dept_id = ? AND g.status = 'approved'
    GROUP BY c.course_id
    HAVING COUNT(*) > 5
    ORDER BY pass_rate DESC
    LIMIT 3
");
$topSubjects->bind_param("i", $deptId);
$topSubjects->execute();
$topSubjects = $topSubjects->get_result();

// Recent grade submissions in this department
$recentGrades = $conn->prepare("
    SELECT g.*, s.first_name, s.last_name, c.course_name, i.first_name as inst_first, i.last_name as inst_last
    FROM grades g
    JOIN enrollments e ON g.enrollment_id = e.enrollment_id
    JOIN class_sections cs ON e.section_id = cs.section_id
    JOIN students s ON e.student_id = s.student_id
    JOIN courses c ON cs.course_id = c.course_id
    JOIN instructors i ON cs.instructor_id = i.instructor_id
    WHERE c.dept_id = ?
    ORDER BY g.updated_at DESC
    LIMIT 5
");
$recentGrades->bind_param("i", $deptId);
$recentGrades->execute();
$recentGrades = $recentGrades->get_result();
?>

<div class="row mb-5">
    <div class="col-md-12">
        <div class="card premium-card overflow-hidden shadow-lg border-0" style="background: linear-gradient(135deg, #ffffff 0%, #f1f5f9 100%);">
            <div class="card-body p-0">
                <div class="row g-0">
                    <div class="col-lg-3 gradient-navy d-flex flex-column align-items-center justify-content-center p-5 text-white position-relative overflow-hidden">
                        <div style="position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: rgba(255,255,255,0.05); border-radius: 50%;"></div>
                        <div class="user-avatar bg-white p-3 mb-3 shadow-lg" style="width: 130px; height: 130px; border-radius: 3rem; display: flex; align-items: center; justify-content: center;">
                            <img src="../BCAT logo 2024.png" alt="BCAT Logo" class="img-fluid">
                        </div>
                        <h5 class="fw-bold mb-0">DEPT HEAD</h5>
                        <p class="small opacity-75 mb-0">Management Portal</p>
                    </div>
                    <div class="col-lg-9 p-5">
                        <div class="d-flex justify-content-between align-items-start mb-4">
                            <div>
                                <h1 class="display-5 fw-800 mb-2 text-primary" style="letter-spacing: -0.03em;"><?php echo htmlspecialchars($deptName); ?></h1>
                                <p class="text-muted lead mb-0">Overseeing academic excellence and faculty performance for your department.</p>
                            </div>
                            <div class="text-end d-none d-md-block">
                                <span class="badge glass-effect text-primary px-4 py-3 rounded-pill fw-bold" style="font-size: 0.85rem;">
                                    <i class="fas fa-user-shield me-2 text-warning"></i> PROGRAM ADMINISTRATOR
                                </span>
                            </div>
                        </div>
                        <div class="row g-4">
                            <div class="col-sm-4">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="bg-primary bg-opacity-10 p-2 rounded-3 text-primary">
                                        <i class="fas fa-university fa-lg"></i>
                                    </div>
                                    <div>
                                        <div class="text-muted small text-uppercase fw-bold">Academic Unit</div>
                                        <div class="fw-bold text-dark">Diploma Program</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="bg-info bg-opacity-10 p-2 rounded-3 text-info">
                                        <i class="fas fa-calendar-alt fa-lg"></i>
                                    </div>
                                    <div>
                                        <div class="text-muted small text-uppercase fw-bold">Current Cycle</div>
                                        <div class="fw-bold text-dark"><?php echo getSetting('current_semester', '1st'); ?> Sem, <?php echo getSetting('academic_year', '2024-2025'); ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="bg-success bg-opacity-10 p-2 rounded-3 text-success">
                                        <i class="fas fa-clock fa-lg"></i>
                                    </div>
                                    <div>
                                        <div class="text-muted small text-uppercase fw-bold">Last Sync</div>
                                        <div class="fw-bold text-dark"><?php echo date('h:i A'); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="responsive-grid mb-5">
    <div class="card premium-card p-4 border-0 shadow-sm">
        <div class="stat-card-icon-v2 bg-primary bg-opacity-10 text-primary mb-3">
            <i class="fas fa-chalkboard-teacher"></i>
        </div>
        <h6 class="text-muted fw-bold text-uppercase small opacity-75 mb-1">Total Faculty</h6>
        <h3 class="fw-bold mb-0 display-6"><?php echo number_format($facultyCount); ?></h3>
    </div>
    
    <div class="card premium-card p-4 border-0 shadow-sm">
        <div class="stat-card-icon-v2 bg-info bg-opacity-10 text-info mb-3">
            <i class="fas fa-user-graduate"></i>
        </div>
        <h6 class="text-muted fw-bold text-uppercase small opacity-75 mb-1">Enrolled Students</h6>
        <h3 class="fw-bold mb-0 display-6"><?php echo number_format($studentCount); ?></h3>
    </div>
    
    <div class="card premium-card p-4 border-0 shadow-sm">
        <div class="stat-card-icon-v2 bg-success bg-opacity-10 text-success mb-3">
            <i class="fas fa-book"></i>
        </div>
        <h6 class="text-muted fw-bold text-uppercase small opacity-75 mb-1">Active Courses</h6>
        <h3 class="fw-bold mb-0 display-6"><?php echo number_format($courseCount); ?></h3>
    </div>

    <div class="card premium-card p-4 border-0 shadow-sm bg-primary text-white" style="background: var(--sidebar-gradient);">
        <div class="d-flex flex-column h-100 justify-content-between">
            <div>
                <h5 class="fw-bold mb-1">Performance Index</h5>
                <p class="small opacity-75 mb-0">Departmental Passing Rate</p>
            </div>
            <h2 class="fw-bold mb-0"><?php echo $deptPassRate; ?>%</h2>
        </div>
    </div>
</div>

<div class="row g-4 mb-5">
    <div class="col-lg-7">
        <div class="card premium-card h-100 border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 p-4">
                <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-chart-line me-2 text-accent-indigo"></i> Academic Analytics</h5>
            </div>
            <div class="card-body px-4 pb-4">
                <div class="d-flex align-items-center mb-4 p-3 bg-light rounded-4">
                    <div class="display-5 fw-bold text-primary me-4"><?php echo $deptPassRate; ?>%</div>
                    <div class="flex-grow-1">
                        <div class="text-muted small text-uppercase fw-bold mb-1">Average Passing rate</div>
                        <div class="progress mt-1" style="height: 10px; border-radius: 5px;">
                            <div class="progress-bar gradient-navy" style="width: <?php echo $deptPassRate; ?>%"></div>
                        </div>
                    </div>
                </div>
                
                <h6 class="small fw-bold text-muted text-uppercase mb-3 mt-4">Top Performing Subjects</h6>
                <div class="list-group list-group-flush border-0">
                    <?php if ($topSubjects->num_rows > 0): ?>
                        <?php while ($subj = $topSubjects->fetch_assoc()): ?>
                            <div class="list-group-item bg-transparent px-0 py-3 border-0 d-flex align-items-center">
                                <div class="bg-success bg-opacity-10 text-success p-2 rounded-3 me-3 fw-bold small" style="min-width: 65px; text-align: center;">
                                    <?php echo htmlspecialchars($subj['course_code']); ?>
                                </div>
                                <div class="flex-grow-1 me-3">
                                    <div class="fw-semibold small mb-1"><?php echo htmlspecialchars($subj['course_name']); ?></div>
                                    <div class="progress" style="height: 4px; border-radius: 2px;">
                                        <div class="progress-bar bg-success" style="width: <?php echo $subj['pass_rate']; ?>%"></div>
                                    </div>
                                </div>
                                <div class="fw-bold text-success small"><?php echo $subj['pass_rate']; ?>%</div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center py-4 text-muted small">Insufficient data for subject ranking.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card premium-card h-100 border-0 shadow-sm overflow-hidden">
            <div class="card-header bg-transparent border-0 p-4">
                <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-th-large me-2 text-accent-indigo"></i> Quick Actions</h5>
            </div>
            <div class="card-body p-4 pt-0">
                <div class="list-group list-group-flush">
                    <a href="instructors.php" class="list-group-item list-group-item-action border-0 px-0 rounded-4 mb-2 d-flex align-items-center p-3 transition-all hover-bg-light">
                        <div class="stat-card-icon-v2 bg-primary bg-opacity-10 text-primary me-3" style="width: 40px; height: 40px; font-size: 1rem;">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold">Faculty Loading</h6>
                            <small class="text-muted">Assignments & Scheduling</small>
                        </div>
                        <i class="fas fa-arrow-right ms-auto text-muted small"></i>
                    </a>
                    <a href="courses.php" class="list-group-item list-group-item-action border-0 px-0 rounded-4 mb-2 d-flex align-items-center p-3 transition-all hover-bg-light">
                        <div class="stat-card-icon-v2 bg-success bg-opacity-10 text-success me-3" style="width: 40px; height: 40px; font-size: 1rem;">
                            <i class="fas fa-book"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold">Course Catalog</h6>
                            <small class="text-muted">Curriculum Oversight</small>
                        </div>
                        <i class="fas fa-arrow-right ms-auto text-muted small"></i>
                    </a>
                    <a href="reports.php" class="list-group-item list-group-item-action border-0 px-0 rounded-4 mb-2 d-flex align-items-center p-3 transition-all hover-bg-light">
                        <div class="stat-card-icon-v2 bg-info bg-opacity-10 text-info me-3" style="width: 40px; height: 40px; font-size: 1rem;">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold">Dept. Analytics</h6>
                            <small class="text-muted">Performance Statistics</small>
                        </div>
                        <i class="fas fa-arrow-right ms-auto text-muted small"></i>
                    </a>
                    <a href="grades.php" class="list-group-item list-group-item-action border-0 px-0 rounded-4 d-flex align-items-center p-3 transition-all hover-bg-light">
                        <div class="stat-card-icon-v2 bg-warning bg-opacity-10 text-warning me-3" style="width: 40px; height: 40px; font-size: 1rem;">
                            <i class="fas fa-star"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold">Grade Oversight</h6>
                            <small class="text-muted">Final Review & Approval</small>
                        </div>
                        <i class="fas fa-arrow-right ms-auto text-muted small"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card premium-card border-0 shadow-sm">
    <div class="card-header bg-transparent border-0 p-4 d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-history me-2 text-warning"></i> Recent Activity in <?php echo htmlspecialchars($deptName); ?></h5>
        <a href="grades.php" class="btn btn-light bg-primary bg-opacity-10 text-primary rounded-pill px-4 fw-bold">Review All Grades</a>
    </div>
    <div class="card-body p-0">
        <?php if ($recentGrades->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 table-mobile-card">
                    <thead class="bg-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-4">Student Identity</th>
                            <th>Offered Course</th>
                            <th>Faculty Member</th>
                            <th class="text-center">Official Grade</th>
                            <th class="pe-4">Submission Timeline</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($g = $recentGrades->fetch_assoc()): ?>
                        <tr>
                            <td class="ps-4" data-label="Student Identity">
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($g['first_name'] . ' ' . $g['last_name']); ?></div>
                                <div class="text-muted small">Enrolled Student</div>
                            </td>
                            <td data-label="Offered Course">
                                <div class="small fw-semibold text-primary"><?php echo htmlspecialchars($g['course_name']); ?></div>
                            </td>
                            <td data-label="Faculty Member">
                                <div class="small"><?php echo htmlspecialchars($g['inst_first'] . ' ' . $g['inst_last']); ?></div>
                                <div class="text-muted" style="font-size: 0.65rem;">Course Instructor</div>
                            </td>
                            <td class="text-center" data-label="Official Grade">
                                <?php
                                    $bgClass = 'primary';
                                    if ($g['grade'] !== null) {
                                        $bgClass = ($g['grade'] <= 3.0) ? 'success' : 'danger';
                                    } elseif ($g['remarks'] === 'INC') {
                                        $bgClass = 'warning';
                                    }
                                ?>
                                <span class="badge bg-<?php echo $bgClass; ?> rounded-pill fs-6 px-3">
                                    <?php echo $g['grade'] !== null ? number_format($g['grade'], 2) : ($g['remarks'] ?: '—'); ?>
                                </span>
                            </td>
                            <td class="pe-4" data-label="Timeline">
                                <div class="text-muted small"><i class="far fa-clock me-1"></i><?php echo date('M d, Y', strtotime($g['updated_at'])); ?></div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5 text-muted">
                <div class="stat-card-icon-v2 bg-light text-muted mx-auto mb-3">
                    <i class="fas fa-folder-open"></i>
                </div>
                <p>No recent academic activity found in this department.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
