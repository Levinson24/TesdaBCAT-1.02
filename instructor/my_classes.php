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
        c.class_code,
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
    WHERE cs.instructor_id = ?
    GROUP BY cs.section_id
    ORDER BY cs.school_year DESC, cs.semester DESC, c.course_code
");
$classes->bind_param("i", $instructorId);
$classes->execute();
$classes = $classes->get_result();
?>

<div class="card custom-table">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-chalkboard"></i> My Class Sections</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover data-table">
                <thead>
                    <tr>
                        <th>Class Code</th>
                        <th>Subject Code</th>
                        <th>Subject Description</th>
                        <th>Section</th>
                        <th>School Year</th>
                        <th>Semester</th>
                        <th>Schedule</th>
                        <th>Room</th>
                        <th>Students</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($class = $classes->fetch_assoc()): ?>
                    <tr>
                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($class['class_code'] ?? 'N/A'); ?></span></td>
                        <td><strong><?php echo htmlspecialchars($class['course_code']); ?></strong></td>
                        <td><?php echo htmlspecialchars($class['course_name']); ?></td>
                        <td><?php echo htmlspecialchars($class['section_name']); ?></td>
                        <td><?php echo htmlspecialchars($class['school_year']); ?></td>
                        <td><?php echo htmlspecialchars($class['semester']); ?></td>
                        <td><?php echo htmlspecialchars($class['schedule'] ?? 'TBA'); ?></td>
                        <td><?php echo htmlspecialchars($class['room'] ?? 'TBA'); ?></td>
                        <td class="text-center">
                            <span class="badge bg-primary"><?php echo $class['student_count']; ?></span>
                        </td>
                        <td>
                            <?php
    $statusColors = [
        'active' => 'success',
        'inactive' => 'secondary',
        'completed' => 'info'
    ];
    $color = $statusColors[$class['status']] ?? 'secondary';
?>
                            <span class="badge bg-<?php echo $color; ?>">
                                <?php echo ucfirst($class['status']); ?>
                            </span>
                        </td>
                        <td>
                            <a href="submit_grades.php?section_id=<?php echo $class['section_id']; ?>" 
                               class="btn btn-sm btn-primary" title="Submit Grades">
                                <i class="fas fa-edit"></i> Grades
                            </a>
                            <a href="class_roster.php?section_id=<?php echo $class['section_id']; ?>" 
                               class="btn btn-sm btn-info" title="View Roster">
                                <i class="fas fa-list"></i> Roster
                            </a>
                        </td>
                    </tr>
                    <?php
endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
