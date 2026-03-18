<?php
/**
 * Diploma Program Head - Grade Review
 */
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('dept_head');

$conn = getDBConnection();
$userProfile = getUserProfile($_SESSION['user_id'], 'dept_head');
$deptId = $userProfile['dept_id'] ?? 0;

// Handle "Reviewed" toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_review'])) {
    $gradeId = $_POST['grade_id'];
    $newStatus = $_POST['current_status'] ? 0 : 1;

    $update = $conn->prepare("UPDATE grades SET is_reviewed = ? WHERE grade_id = ?");
    $update->bind_param("ii", $newStatus, $gradeId);
    if ($update->execute()) {
        header("Location: grades.php?success=1");
        exit;
    }
}

$pageTitle = 'Diploma Program Grade Oversight';
require_once '../includes/header.php';

// Fetch grades for courses in this department
$grades = $conn->prepare("
    SELECT g.*, s.student_no, s.first_name, s.last_name, c.course_code, c.course_name, i.first_name as inst_first, i.last_name as inst_last
    FROM grades g
    JOIN students s ON g.student_id = s.student_id
    JOIN enrollments e ON g.enrollment_id = e.enrollment_id
    JOIN class_sections cs ON e.section_id = cs.section_id
    JOIN courses c ON cs.course_id = c.course_id
    JOIN instructors i ON cs.instructor_id = i.instructor_id
    WHERE c.dept_id = ?
    ORDER BY g.submitted_at DESC
");
$grades->bind_param("i", $deptId);
$grades->execute();
$result = $grades->get_result();

?>

<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-star"></i> Diploma Program Grade Oversight</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover data-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Subject</th>
                        <th>Instructor</th>
                        <th>Grade</th>
                        <th>Status</th>
                        <th>Reviewed</th>
                        <th>Submitted</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($g = $result->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <b><?php echo htmlspecialchars($g['last_name'] . ', ' . $g['first_name']); ?></b><br>
                            <small class="text-muted"><?php echo htmlspecialchars($g['student_no']); ?></small>
                        </td>
                        <td>
                            <b><?php echo htmlspecialchars($g['course_code']); ?></b><br>
                            <small><?php echo htmlspecialchars($g['course_name']); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($g['inst_last'] . ', ' . $g['inst_first']); ?></td>
                        <td>
                            <span class="fw-bold">
                                <?php echo $g['grade'] !== null ? number_format($g['grade'], 2) : '—'; ?>
                            </span>
                            <?php if ($g['remarks'] && $g['grade'] === null): ?>
                                <br><small class="badge bg-secondary"><?php echo htmlspecialchars($g['remarks']); ?></small>
                            <?php
    endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo($g['status'] === 'submitted' ? 'warning' : ($g['status'] === 'approved' ? 'success' : 'info')); ?>">
                                <?php echo ucfirst($g['status']); ?>
                            </span>
                        </td>
                        <td>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="grade_id" value="<?php echo $g['grade_id']; ?>">
                                <input type="hidden" name="current_status" value="<?php echo $g['is_reviewed']; ?>">
                                <button type="submit" name="toggle_review" class="btn btn-sm btn-<?php echo $g['is_reviewed'] ? 'success' : 'outline-secondary'; ?>">
                                    <i class="fas <?php echo $g['is_reviewed'] ? 'fa-check-circle' : 'fa-circle'; ?>"></i>
                                    <?php echo $g['is_reviewed'] ? 'Reviewed' : 'Mark'; ?>
                                </button>
                            </form>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($g['submitted_at'])); ?></td>
                    </tr>
                    <?php
endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
