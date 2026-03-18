<?php
/**
 * Instructor Dashboard
 * TESDA-BCAT Grade Management System
 */

$pageTitle = 'Instructor Dashboard';
require_once '../includes/header.php';

requireRole('instructor');

$conn = getDBConnection();
$userId = getCurrentUserId();

// Get instructor profile with Department
$stmt = $conn->prepare("
    SELECT i.*, d.title_diploma_program as dept_name 
    FROM instructors i 
    LEFT JOIN departments d ON i.dept_id = d.dept_id 
    WHERE i.user_id = ?
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$instructor = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$instructor) {
    echo showError('Instructor profile not found. Please contact administrator.');
    require_once '../includes/footer.php';
    exit();
}

$instructorId = $instructor['instructor_id'];

// Get total classes
$stmt = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM class_sections 
    WHERE instructor_id = ? AND status = 'active'
");
$stmt->bind_param("i", $instructorId);
$stmt->execute();
$totalClasses = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Get total students
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT e.student_id) as total
    FROM enrollments e
    JOIN class_sections cs ON e.section_id = cs.section_id
    WHERE cs.instructor_id = ? AND e.status = 'enrolled' AND cs.status = 'active'
");
$stmt->bind_param("i", $instructorId);
$stmt->execute();
$totalStudents = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Get pending grades
$stmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM grades g
    JOIN enrollments e ON g.enrollment_id = e.enrollment_id
    JOIN class_sections cs ON e.section_id = cs.section_id
    WHERE cs.instructor_id = ? AND g.status = 'pending'
");
$stmt->bind_param("i", $instructorId);
$stmt->execute();
$pendingGrades = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Get my classes
$myClasses = $conn->prepare("
    SELECT 
        cs.*,
        c.course_code,
        c.course_name,
        c.units,
        COUNT(DISTINCT e.student_id) as student_count,
        SUM(CASE WHEN g.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN g.status = 'submitted' THEN 1 ELSE 0 END) as submitted_count,
        SUM(CASE WHEN g.status = 'approved' THEN 1 ELSE 0 END) as approved_count
    FROM class_sections cs
    JOIN courses c ON cs.course_id = c.course_id
    LEFT JOIN enrollments e ON cs.section_id = e.section_id AND e.status = 'enrolled'
    LEFT JOIN grades g ON e.enrollment_id = g.enrollment_id
    WHERE cs.instructor_id = ? AND cs.status = 'active'
    GROUP BY cs.section_id, cs.section_name, c.course_code, c.course_name, c.units
    ORDER BY cs.school_year DESC, cs.semester DESC
");
$myClasses->bind_param("i", $instructorId);
$myClasses->execute();
$myClasses = $myClasses->get_result();
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
                                <h2 class="display-6 fw-bold mb-1 text-primary"><?php echo htmlspecialchars($instructor['first_name'] . ' ' . $instructor['last_name']); ?></h2>
                                <p class="text-accent-indigo fw-semibold mb-3">
                                    <i class="fas fa-id-badge me-1"></i> Professional Instructor Portal
                                </p>
                            </div>
                            <div class="d-flex flex-column align-items-end">
                                <span class="badge glass-effect text-success px-3 py-2 rounded-pill fw-bold border border-success border-opacity-10 mb-2">
                                    <i class="fas fa-check-circle me-1"></i> Active Faculty
                                </span>
                                <div class="text-muted small"><i class="far fa-calendar-alt me-1"></i> <?php echo date('F d, Y'); ?></div>
                            </div>
                        </div>
                        <div class="row g-4 mt-1">
                            <div class="col-sm-4 border-start ps-4">
                                <div class="text-muted small text-uppercase fw-bold mb-1">Diploma Program</div>
                                <div class="fw-semibold text-dark"><?php echo htmlspecialchars($instructor['dept_name'] ?? 'Not Set'); ?></div>
                            </div>
                            <div class="col-sm-4 border-start ps-4">
                                <div class="text-muted small text-uppercase fw-bold mb-1">Specialization</div>
                                <div class="fw-semibold text-accent-indigo"><?php echo htmlspecialchars($instructor['specialization'] ?? 'Not Set'); ?></div>
                            </div>
                            <div class="col-sm-4 border-start ps-4">
                                <div class="text-muted small text-uppercase fw-bold mb-1">Official Email</div>
                                <div class="fw-semibold text-truncate"><?php echo htmlspecialchars($instructor['email'] ?? 'Not Set'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4 mb-3">
        <div class="card premium-card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="stat-card-icon-v2 bg-primary bg-opacity-10 text-primary mb-3">
                    <i class="fas fa-chalkboard"></i>
                </div>
                <div>
                    <div class="text-muted small fw-bold text-uppercase opacity-75">Assigned Classes</div>
                    <h3 class="mb-0 fw-bold display-6 text-primary"><?php echo $totalClasses; ?></h3>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-3">
        <div class="card premium-card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="stat-card-icon-v2 bg-info bg-opacity-10 text-info mb-3">
                    <i class="fas fa-users"></i>
                </div>
                <div>
                    <div class="text-muted small fw-bold text-uppercase opacity-75">Active Students</div>
                    <h3 class="mb-0 fw-bold display-6 text-info"><?php echo $totalStudents; ?></h3>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-3">
        <div class="card premium-card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="stat-card-icon-v2 bg-warning bg-opacity-10 text-warning mb-3">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div>
                    <div class="text-muted small fw-bold text-uppercase opacity-75">Pending Submissions</div>
                    <h3 class="mb-0 fw-bold display-6 text-warning"><?php echo $pendingGrades; ?></h3>
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
                    <i class="fas fa-chalkboard-teacher me-2 text-primary"></i>
                    <h5 class="mb-0 fw-bold text-dark">Teaching Load Registry</h5>
                </div>
                <a href="my_classes.php" class="btn btn-primary rounded-pill px-4">
                    View Complete History
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 table-mobile-card">
                        <thead class="bg-light text-muted small text-uppercase">
                            <tr>
                                <th class="ps-4">Academic Course</th>
                                <th>Section & Cycle</th>
                                <th>Schedule Info</th>
                                <th class="text-center">Student Population</th>
                                <th class="text-center">Grading Status</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($myClasses->num_rows > 0): ?>
                                <?php while ($class = $myClasses->fetch_assoc()): ?>
                                <tr>
                                    <td class="ps-4" data-label="Academic Course">
                                        <div class="fw-bold text-primary"><?php echo htmlspecialchars($class['course_code']); ?></div>
                                        <div class="text-muted small text-truncate" style="max-width: 250px;"><?php echo htmlspecialchars($class['course_name']); ?></div>
                                    </td>
                                    <td data-label="Section & Cycle">
                                        <div class="fw-bold"><?php echo htmlspecialchars($class['section_name']); ?></div>
                                        <div class="text-accent-indigo" style="font-size: 0.75rem;">
                                            <?php echo htmlspecialchars($class['semester'] . ' SY ' . $class['school_year']); ?>
                                        </div>
                                    </td>
                                    <td data-label="Schedule Info">
                                        <div class="small"><i class="far fa-clock me-1 text-muted"></i> <?php echo htmlspecialchars($class['schedule'] ?? 'TBA'); ?></div>
                                        <div class="small"><i class="fas fa-map-marker-alt me-1 text-muted"></i> <?php echo htmlspecialchars($class['room'] ?? 'TBA'); ?></div>
                                    </td>
                                    <td class="text-center" data-label="Population">
                                        <div class="d-flex flex-column align-items-center">
                                            <span class="badge bg-light text-dark border px-3 rounded-pill"><?php echo $class['student_count']; ?></span>
                                            <span class="text-muted mt-1" style="font-size: 0.65rem;">Enrolled</span>
                                        </div>
                                    </td>
                                    <td class="text-center" data-label="Grading Status">
                                        <div class="d-flex justify-content-center gap-1">
                                            <span class="badge <?php echo $class['pending_count'] > 0 ? 'bg-warning' : 'bg-light text-muted border'; ?> rounded-pill" title="Pending" style="min-width: 35px;"><?php echo $class['pending_count']; ?>P</span>
                                            <span class="badge <?php echo $class['submitted_count'] > 0 ? 'bg-info' : 'bg-light text-muted border'; ?> rounded-pill" title="Submitted" style="min-width: 35px;"><?php echo $class['submitted_count']; ?>S</span>
                                            <span class="badge <?php echo $class['approved_count'] > 0 ? 'bg-success' : 'bg-light text-muted border'; ?> rounded-pill" title="Approved" style="min-width: 35px;"><?php echo $class['approved_count']; ?>A</span>
                                        </div>
                                        <div class="progress mt-2" style="height: 4px; width: 100px; margin: 0 auto;">
                                            <?php
        $total = max(1, $class['student_count']);
        $perc = ($class['approved_count'] / $total) * 100;
?>
                                            <div class="progress-bar bg-success" style="width: <?php echo $perc; ?>%"></div>
                                        </div>
                                    </td>
                                    <td class="text-end pe-4" data-label="Actions">
                                        <div class="d-flex justify-content-end gap-1">
                                            <a href="submit_grades.php?section_id=<?php echo $class['section_id']; ?>" 
                                               class="btn btn-sm btn-light bg-primary bg-opacity-10 text-primary rounded-circle shadow-sm" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;" title="Process Grades">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="class_roster.php?section_id=<?php echo $class['section_id']; ?>" 
                                               class="btn btn-sm btn-light bg-info bg-opacity-10 text-info rounded-circle shadow-sm" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;" title="View Roster">
                                                <i class="fas fa-list"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php
    endwhile; ?>
                            <?php
else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <div class="stat-card-icon-v2 bg-light text-muted mx-auto mb-3">
                                            <i class="fas fa-folder-open"></i>
                                        </div>
                                        <h5 class="fw-bold text-muted">No Assignments Found</h5>
                                        <p class="text-muted small">You don't have any active classes assigned for this cycle.</p>
                                    </td>
                                </tr>
                            <?php
endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($pendingGrades > 0): ?>
                <div class="p-4 bg-warning bg-opacity-10 border-top border-warning border-opacity-25 rounded-bottom-4">
                    <div class="d-flex align-items-center">
                        <div class="stat-card-icon-v2 bg-warning bg-opacity-20 text-warning me-3" style="width: 40px; height: 40px; font-size: 1rem;">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div>
                            <p class="mb-0 text-dark fw-bold">Academic Submission Required</p>
                            <p class="mb-0 text-muted small">You have <strong><?php echo $pendingGrades; ?></strong> pending student record(s) awaiting grade finalization. <a href="submit_grades.php" class="text-accent-indigo fw-bold text-decoration-none ms-1">Action Registry <i class="fas fa-arrow-right small ms-1"></i></a></p>
                        </div>
                    </div>
                </div>
                <?php
endif; ?>
            </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
