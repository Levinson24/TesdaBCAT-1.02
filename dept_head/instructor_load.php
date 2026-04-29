<?php
/**
 * Diploma Program Head - Instructor Teaching Load
 */
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('dept_head');

$conn = getDBConnection();
$deptHeadProfile = getUserProfile($_SESSION['user_id'], 'dept_head');
$deptId = $deptHeadProfile['dept_id'] ?? 0;

$instructorId = $_GET['id'] ?? 0;

// Fetch instructor details and verify they belong to this department
$stmt = $conn->prepare("
    SELECT i.*, u.username, u.profile_image, d.title_diploma_program as dept_name 
    FROM instructors i
    JOIN users u ON i.user_id = u.user_id
    LEFT JOIN departments d ON i.dept_id = d.dept_id
    WHERE i.instructor_id = ? AND i.dept_id = ?
");
$stmt->bind_param("ii", $instructorId, $deptId);
$stmt->execute();
$instructor = $stmt->get_result()->fetch_assoc();

if (!$instructor) {
    redirectWithMessage('instructors.php', 'Instructor not found or unauthorized access.', 'danger');
}

// Fetch sections handled by this instructor
$sections = $conn->prepare("
    SELECT cs.*, s.subject_id as course_code, s.subject_name as course_name,
           (SELECT COUNT(*) FROM enrollments WHERE section_id = cs.section_id) as enrolled_count
    FROM class_sections cs
    JOIN curriculum cur ON cs.curriculum_id = cur.curriculum_id
    JOIN subjects s ON cur.subject_id = s.subject_id
    WHERE cs.instructor_id = ?
    ORDER BY cs.school_year DESC, cs.semester DESC
");
$sections->bind_param("i", $instructorId);
$sections->execute();
$sectionsResult = $sections->get_result();

$pageTitle = 'Instructor Load';
require_once '../includes/header.php';
?>

<div class="mb-4">
    <a href="instructors.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back to Faculty List</a>
</div>

<div class="row g-4">
    <div class="col-12 col-lg-4">
        <!-- Instructor Profile Card -->
        <div class="card shadow-sm border-0 rounded-4 overflow-hidden h-100">
            <div class="card-body p-4 text-center">
                <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center mx-auto mb-3 overflow-hidden" style="width: 100px; height: 100px;">
                    <?php if (!empty($instructor['profile_image'])): ?>
                        <img src="<?php echo BASE_URL; ?>uploads/profile_pics/<?php echo htmlspecialchars($instructor['profile_image']); ?>?v=<?php echo time(); ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <i class="fas fa-chalkboard-teacher fa-3x text-primary"></i>
                    <?php endif; ?>
                </div>
                <h4 class="fw-bold mb-1"><?php echo htmlspecialchars($instructor['first_name'] . ' ' . $instructor['last_name']); ?></h4>
                <p class="text-muted small mb-3"><?php echo htmlspecialchars($instructor['instructor_id_no']); ?></p>
                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-2 mb-4"><?php echo htmlspecialchars($instructor['specialization'] ?? 'Faculty Member'); ?></span>
                
                <div class="border-top pt-4 text-start">
                    <div class="mb-3">
                        <small class="text-muted d-block text-uppercase fw-bold mb-1" style="font-size: 0.7rem;">Contact Number</small>
                        <div class="fw-bold"><?php echo htmlspecialchars($instructor['contact_number'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted d-block text-uppercase fw-bold mb-1" style="font-size: 0.7rem;">Email Address</small>
                        <div class="fw-bold"><?php echo htmlspecialchars($instructor['email'] ?? 'N/A'); ?></div>
                    </div>
                    <div>
                        <small class="text-muted d-block text-uppercase fw-bold mb-1" style="font-size: 0.7rem;">Account Status</small>
                        <span class="badge bg-<?php echo $instructor['status'] === 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($instructor['status']); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-8">
        <!-- Teaching Load Card -->
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold"><i class="fas fa-book-reader text-primary me-2"></i> Current Teaching Load</h5>
                <span class="badge bg-light text-dark border"><?php echo $sectionsResult->num_rows; ?> Sections Total</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th>Course</th>
                                <th>Section</th>
                                <th>Schedule</th>
                                <th class="text-center">Students</th>
                                <th class="text-center">SY/Sem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($sectionsResult->num_rows > 0): ?>
                                <?php while ($s = $sectionsResult->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($s['course_code']); ?></div>
                                            <div class="small text-muted"><?php echo htmlspecialchars($s['course_name']); ?></div>
                                        </td>
                                        <td><span class="badge bg-primary bg-opacity-10 text-primary"><?php echo htmlspecialchars($s['section_name']); ?></span></td>
                                        <td><small><?php echo htmlspecialchars($s['schedule'] ?? 'No schedule set'); ?></small></td>
                                        <td class="text-center">
                                            <div class="fw-bold"><?php echo $s['enrolled_count']; ?> / <?php echo $s['max_students']; ?></div>
                                            <div class="progress" style="height: 4px; width: 60px; margin: 4px auto 0;">
                                                <?php $cap = ($s['enrolled_count'] / $s['max_students']) * 100; ?>
                                                <div class="progress-bar bg-<?php echo $cap >= 90 ? 'danger' : ($cap >= 75 ? 'warning' : 'success'); ?>" style="width: <?php echo $cap; ?>%"></div>
                                            </div>
                                        </td>
                                        <td class="text-center small">
                                            <?php echo htmlspecialchars($s['school_year']); ?><br>
                                            <span class="text-muted"><?php echo htmlspecialchars($s['semester']); ?></span>
                                        </td>
                                    </tr>
                                <?php
    endwhile; ?>
                            <?php
else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        <i class="fas fa-calendar-times fa-2x mb-3 d-block"></i>
                                        No active teaching assignments found for this instructor.
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
