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
$pendingGrades = $result->fetch_assoc()['total'];

// Get my classes
$myClasses = $conn->prepare("
    SELECT 
        cs.*,
        s.subject_id,
        s.subject_name,
        s.units,
        COUNT(DISTINCT e.student_id) as student_count,
        SUM(CASE WHEN g.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN g.status = 'submitted' THEN 1 ELSE 0 END) as submitted_count,
        SUM(CASE WHEN g.status = 'approved' THEN 1 ELSE 0 END) as approved_count
    FROM class_sections cs
    JOIN curriculum cur ON cs.curriculum_id = cur.curriculum_id
    JOIN subjects s ON cur.subject_id = s.subject_id
    LEFT JOIN enrollments e ON cs.section_id = e.section_id AND e.status = 'enrolled'
    LEFT JOIN grades g ON e.enrollment_id = g.enrollment_id
    WHERE cs.instructor_id = ? AND cs.status = 'active'
    GROUP BY cs.section_id, cs.section_name, s.subject_id, s.subject_name, s.units
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

<!-- Instructor Stats: Desktop -->
<div class="row mb-4 d-none d-sm-flex">
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
                    <div class="text-muted small fw-bold text-uppercase opacity-75">Pending Grades</div>
                    <h3 class="mb-0 fw-bold display-6 text-warning"><?php echo $pendingGrades; ?></h3>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Instructor Stats: Mobile (Prototype Style) -->
<div class="stat-grid-mobile d-grid d-sm-none mb-4">
    <div class="card stat-card-mobile p-3">
        <div class="stat-value-mobile"><?php echo $totalClasses; ?></div>
        <div class="stat-label-mobile">Classes</div>
    </div>
    <div class="card stat-card-mobile p-3">
        <div class="stat-value-mobile"><?php echo $totalStudents; ?></div>
        <div class="stat-label-mobile">Students</div>
    </div>
    <div class="card stat-card-mobile p-3">
        <div class="stat-value-mobile" style="color: var(--secondary);"><?php echo $pendingGrades; ?></div>
        <div class="stat-label-mobile">Pending</div>
    </div>
    <div class="card stat-card-mobile p-3 d-flex align-items-center justify-content-center">
        <a href="submit_grades.php" class="text-primary fw-bold text-decoration-none small">
            <i class="fas fa-plus-circle"></i> Grades
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card premium-card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 p-4 d-flex justify-content-between align-items-center pb-0">
                <div class="d-flex align-items-center">
                    <i class="fas fa-chalkboard-teacher me-2 text-primary"></i>
                    <h5 class="mb-0 fw-bold text-dark">Teaching Load Registry</h5>
                </div>
                <a href="my_classes.php" class="btn btn-primary rounded-pill px-4 btn-sm d-none d-sm-inline-block">
                    Full History
                </a>
            </div>
            <div class="card-body p-0">
                <!-- Desktop Table -->
                <div class="table-responsive d-none d-sm-block">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light text-muted small text-uppercase">
                            <tr>
                                <th class="ps-4">Academic Course</th>
                                <th>Section & Cycle</th>
                                <th>Schedule Info</th>
                                <th class="text-center">Population</th>
                                <th class="text-center">Grading</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($myClasses->num_rows > 0): ?>
                                <?php 
                                $myClasses->data_seek(0);
                                while ($class = $myClasses->fetch_assoc()): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold text-primary"><?php echo htmlspecialchars($class['subject_id']); ?></div>
                                        <div class="text-muted small text-truncate" style="max-width: 250px;"><?php echo htmlspecialchars($class['subject_name']); ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($class['section_name']); ?></div>
                                        <div class="text-accent-indigo x-small">
                                            <?php echo htmlspecialchars($class['semester'] . ' SY ' . $class['school_year']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="small"><i class="far fa-clock me-1 text-muted"></i> <?php echo htmlspecialchars($class['schedule'] ?? 'TBA'); ?></div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border px-3 rounded-pill"><?php echo $class['student_count']; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-1">
                                            <span class="x-small badge <?php echo $class['pending_count'] > 0 ? 'bg-warning' : 'bg-light text-muted border'; ?> rounded-pill"><?php echo $class['pending_count']; ?>P</span>
                                            <span class="x-small badge <?php echo $class['approved_count'] > 0 ? 'bg-success' : 'bg-light text-muted border'; ?> rounded-pill"><?php echo $class['approved_count']; ?>A</span>
                                        </div>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="d-flex justify-content-end gap-1">
                                            <a href="submit_grades.php?section_id=<?php echo $class['section_id']; ?>" class="btn btn-sm btn-light text-primary rounded-circle"><i class="fas fa-edit"></i></a>
                                            <a href="class_roster.php?section_id=<?php echo $class['section_id']; ?>" class="btn btn-sm btn-light text-info rounded-circle"><i class="fas fa-list"></i></a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="text-center py-4">No assignments found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card View (Prototype Style) -->
                <div class="d-block d-sm-none p-3">
                    <?php if ($myClasses->num_rows > 0): ?>
                        <?php 
                        $myClasses->data_seek(0);
                        while ($class = $myClasses->fetch_assoc()): ?>
                        <div class="card p-3 mb-3 border shadow-none">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div class="fw-bold text-primary small"><?php echo htmlspecialchars($class['subject_id']); ?></div>
                                <span class="badge bg-primary bg-opacity-10 text-primary x-small"><?php echo htmlspecialchars($class['section_name']); ?></span>
                            </div>
                            <div class="fw-bold mb-2 small text-dark"><?php echo htmlspecialchars($class['subject_name']); ?></div>
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <div class="x-small text-muted">Students</div>
                                    <div class="small fw-bold"><?php echo $class['student_count']; ?> Enrolled</div>
                                </div>
                                <div class="col-6 text-end">
                                    <div class="x-small text-muted">Grading</div>
                                    <div class="d-flex justify-content-end gap-1 mt-1">
                                        <span class="x-small badge <?php echo $class['pending_count'] > 0 ? 'bg-warning' : 'bg-light text-muted'; ?> rounded-pill"><?php echo $class['pending_count']; ?>P</span>
                                        <span class="x-small badge <?php echo $class['approved_count'] > 0 ? 'bg-success' : 'bg-light text-muted'; ?> rounded-pill"><?php echo $class['approved_count']; ?>A</span>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex gap-2 border-top pt-2">
                                <a href="submit_grades.php?section_id=<?php echo $class['section_id']; ?>" class="btn btn-primary btn-sm flex-grow-1"><i class="fas fa-edit me-1"></i> Grades</a>
                                <a href="class_roster.php?section_id=<?php echo $class['section_id']; ?>" class="btn btn-outline-primary btn-sm flex-grow-1"><i class="fas fa-list me-1"></i> Roster</a>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center py-4 text-muted small">No assignments found.</div>
                    <?php endif; ?>
                </div>
                
                <?php if ($pendingGrades > 0): ?>
                <div class="p-3 bg-warning bg-opacity-10 border-top border-warning border-opacity-25 rounded-bottom-4">
                    <p class="mb-0 text-muted small"><i class="fas fa-exclamation-triangle text-warning me-1"></i> <strong><?php echo $pendingGrades; ?></strong> pending student record(s) awaiting grade finalization. <a href="submit_grades.php" class="text-accent-indigo fw-bold text-decoration-none ms-1">Action Registry <i class="fas fa-arrow-right small"></i></a></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
            </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
