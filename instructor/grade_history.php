<?php
/**
 * Instructor - Grade History
 * TESDA-BCAT Grade Management System
 */

$pageTitle = 'Grade History';
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

$instructorId = $instructor['instructor_id'];

// Get all submitted grades
$grades = $conn->query("
    SELECT 
        g.*,
        s.student_no,
        CONCAT(s.last_name, ', ', s.first_name) as student_name,
        c.course_code,
        c.course_name,
        cs.section_name,
        cs.semester,
        cs.school_year
    FROM grades g
    JOIN students s ON g.student_id = s.student_id
    JOIN enrollments e ON g.enrollment_id = e.enrollment_id
    JOIN class_sections cs ON e.section_id = cs.section_id
    JOIN courses c ON cs.course_id = c.course_id
    WHERE g.submitted_by = $instructorId
    ORDER BY g.submitted_at DESC
");
?>

<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-history"></i> Grade Submission History</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover data-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Course</th>
                        <th>Section</th>
                        <th>SY/Sem</th>
                        <th>Midterm</th>
                        <th>Final</th>
                        <th>Grade</th>
                        <th>Remarks</th>
                        <th>Status</th>
                        <th>Submitted</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($grade = $grades->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($grade['student_name']); ?></strong><br>
                            <small class="text-muted"><?php echo $grade['student_no']; ?></small>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($grade['course_code']); ?></strong><br>
                            <small><?php echo htmlspecialchars($grade['course_name']); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($grade['section_name']); ?></td>
                        <td>
                            <?php echo htmlspecialchars($grade['semester'] . ' ' . $grade['school_year']); ?>
                        </td>
                        <td class="text-center"><?php echo number_format($grade['midterm'] ?? 0, 2); ?></td>
                        <td class="text-center"><?php echo number_format($grade['final'] ?? 0, 2); ?></td>
                        <td class="text-center"><strong><?php echo $grade['grade'] !== null ? number_format($grade['grade'], 2) : '—'; ?></strong></td>
                        <td>
                            <?php
    $bgStatus = 'danger';
    if ($grade['remarks'] === 'Passed')
        $bgStatus = 'success';
    if ($grade['remarks'] === 'INC')
        $bgStatus = 'warning';
    if ($grade['remarks'] === 'Dropped')
        $bgStatus = 'secondary';
?>
                            <span class="badge bg-<?php echo $bgStatus; ?>">
                                <?php echo $grade['remarks']; ?>
                            </span>
                        </td>
                        <td>
                            <?php
    $statusColors = [
        'pending' => 'secondary',
        'submitted' => 'success', // legacy: treat as graded
        'approved' => 'success',
        'rejected' => 'danger'
    ];
    $statusLabels = [
        'pending' => 'Not Graded',
        'submitted' => 'Graded',
        'approved' => 'Graded',
        'rejected' => 'Rejected'
    ];
?>
                            <span class="badge bg-<?php echo $statusColors[$grade['status']] ?? 'secondary'; ?>">
                                <?php echo $statusLabels[$grade['status']] ?? ucfirst($grade['status']); ?>
                            </span>
                        </td>
                        <td><?php echo formatDateTime($grade['submitted_at']); ?></td>
                    </tr>
                    <?php
endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
