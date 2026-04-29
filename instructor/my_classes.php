<?php
/**
 * Instructor - My Classes
 * TESDA-BCAT Grade Management System
 */

$pageTitle = 'My Classes';
require_once '../includes/header.php';

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
    echo showError('Instructor profile not found.');
    require_once '../includes/footer.php';
    exit();
}

$instructorId = $instructor['instructor_id'];

// Get all classes assigned to this instructor
$classes = $conn->prepare("
    SELECT 
        cs.*,
        cur.class_code,
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
    WHERE cs.instructor_id = ?
    GROUP BY cs.section_id, cur.class_code, s.subject_id, s.subject_name, s.units
    ORDER BY cs.school_year DESC, cs.semester DESC, s.subject_id
");
$classes->bind_param("i", $instructorId);
$classes->execute();
$classes = $classes->get_result();
?>

<div class="card premium-card border-0 shadow-sm overflow-hidden">
    <div class="card-header bg-primary text-white p-3 p-md-4 d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold"><i class="fas fa-chalkboard me-2"></i> Teaching Load History</h5>
        <span class="badge bg-white text-primary rounded-pill px-3"><?php echo $classes->num_rows; ?> Sections</span>
    </div>
    <div class="card-body p-0">
        <!-- Desktop Table View -->
        <div class="table-responsive d-none d-md-block">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light text-muted small text-uppercase">
                    <tr>
                        <th class="ps-4">Code</th>
                        <th>Subject Details</th>
                        <th>Cycle & Section</th>
                        <th class="text-center">Students</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $classes->data_seek(0);
                    while ($class = $classes->fetch_assoc()): ?>
                    <tr>
                        <td class="ps-4"><span class="badge bg-secondary opacity-75"><?php echo htmlspecialchars($class['class_code'] ?? 'N/A'); ?></span></td>
                        <td>
                            <div class="fw-bold text-primary"><?php echo htmlspecialchars($class['subject_id']); ?></div>
                            <div class="text-muted small"><?php echo htmlspecialchars($class['subject_name']); ?></div>
                        </td>
                        <td>
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($class['section_name']); ?></div>
                            <div class="x-small text-accent-indigo"><?php echo htmlspecialchars($class['semester'] . ' SY ' . $class['school_year']); ?></div>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-light text-primary border rounded-pill px-3"><?php echo $class['student_count']; ?></span>
                        </td>
                        <td>
                            <?php
                            $statusColors = ['active' => 'success', 'inactive' => 'secondary', 'completed' => 'info'];
                            $color = $statusColors[$class['status']] ?? 'secondary';
                            ?>
                            <span class="badge x-small bg-<?php echo $color; ?> bg-opacity-10 text-<?php echo $color; ?> border border-<?php echo $color; ?> border-opacity-25 rounded-pill px-2">
                                <?php echo strtoupper($class['status']); ?>
                            </span>
                        </td>
                        <td class="text-end pe-4">
                            <div class="d-flex justify-content-end gap-1">
                                <a href="submit_grades.php?section_id=<?php echo $class['section_id']; ?>" class="btn btn-sm btn-light text-primary rounded-pill px-3">Grades</a>
                                <a href="class_roster.php?section_id=<?php echo $class['section_id']; ?>" class="btn btn-sm btn-light text-info rounded-pill px-3">Roster</a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile Card View -->
        <div class="d-block d-md-none p-3">
            <?php 
            $classes->data_seek(0);
            while ($class = $classes->fetch_assoc()): ?>
            <div class="card p-3 mb-3 border shadow-none rounded-4">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div class="fw-bold text-primary small"><?php echo htmlspecialchars($class['subject_id']); ?></div>
                    <?php
                    $statusColors = ['active' => 'success', 'inactive' => 'secondary', 'completed' => 'info'];
                    $color = $statusColors[$class['status']] ?? 'secondary';
                    ?>
                    <span class="badge x-small bg-<?php echo $color; ?> rounded-pill"><?php echo strtoupper($class['status']); ?></span>
                </div>
                <div class="fw-bold mb-2 small text-dark"><?php echo htmlspecialchars($class['subject_name']); ?></div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="x-small text-muted">
                        <i class="fas fa-layer-group me-1"></i> <?php echo htmlspecialchars($class['section_name']); ?>
                    </div>
                    <div class="x-small fw-bold text-accent-indigo">
                        <?php echo htmlspecialchars($class['semester'] . ' ' . $class['school_year']); ?>
                    </div>
                </div>
                <div class="row g-2 mb-3 bg-light p-2 rounded-3 mx-0">
                    <div class="col-4 border-end">
                        <div class="x-small text-muted opacity-75">Students</div>
                        <div class="small fw-bold"><?php echo $class['student_count']; ?></div>
                    </div>
                    <div class="col-4 border-end text-center">
                        <div class="x-small text-muted opacity-75">Units</div>
                        <div class="small fw-bold"><?php echo $class['units']; ?>.0</div>
                    </div>
                    <div class="col-4 text-end">
                        <div class="x-small text-muted opacity-75">Graded</div>
                        <div class="small fw-bold text-success"><?php echo $class['approved_count']; ?></div>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <a href="submit_grades.php?section_id=<?php echo $class['section_id']; ?>" class="btn btn-primary btn-sm flex-grow-1 py-2 rounded-pill"><i class="fas fa-edit me-1"></i> Grades</a>
                    <a href="class_roster.php?section_id=<?php echo $class['section_id']; ?>" class="btn btn-outline-primary btn-sm flex-grow-1 py-2 rounded-pill"><i class="fas fa-list me-1"></i> Roster</a>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
