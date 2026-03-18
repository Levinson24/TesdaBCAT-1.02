<?php
$pageTitle = 'View Grades';
require_once '../includes/header.php';
requireRole('admin');
$conn = getDBConnection();

$filter = $_GET['filter'] ?? 'all';
$where = "";
if ($filter === 'pending')
    $where = "AND g.status = 'pending'";
elseif ($filter === 'submitted')
    $where = "AND g.status = 'submitted'";
elseif ($filter === 'approved')
    $where = "AND g.status = 'approved'";

$grades = $conn->query("
    SELECT g.*, s.student_no, CONCAT(s.first_name, ' ', s.last_name) as student_name,
           c.course_code, c.course_name, cs.section_name, cs.semester, cs.school_year,
           CONCAT(i.first_name, ' ', i.last_name) as instructor_name
    FROM grades g
    JOIN students s ON g.student_id = s.student_id
    JOIN enrollments e ON g.enrollment_id = e.enrollment_id
    JOIN class_sections cs ON e.section_id = cs.section_id
    JOIN courses c ON cs.course_id = c.course_id
    JOIN instructors i ON cs.instructor_id = i.instructor_id
    WHERE 1=1 $where
    ORDER BY g.submitted_at DESC
");
?>

<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-chart-line"></i> Grade Overview</h5>
    </div>
    <div class="card-body">
        <div class="mb-3">
            <a href="?filter=all" class="btn btn-sm btn-<?php echo $filter === 'all' ? 'primary' : 'outline-primary'; ?>">All</a>
            <a href="?filter=pending" class="btn btn-sm btn-<?php echo $filter === 'pending' ? 'secondary' : 'outline-secondary'; ?>">Drafts (Pending)</a>
            <a href="?filter=submitted" class="btn btn-sm btn-<?php echo $filter === 'submitted' ? 'warning' : 'outline-warning'; ?>">Pending Approval</a>
            <a href="?filter=approved" class="btn btn-sm btn-<?php echo $filter === 'approved' ? 'success' : 'outline-success'; ?>">Approved</a>
        </div>
        
        <table class="table table-hover data-table">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Course</th>
                    <th>Section</th>
                    <th>Instructor</th>
                    <th>Midterm</th>
                    <th>Final</th>
                    <th>Grade</th>
                    <th>Remarks</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($g = $grades->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($g['student_name']); ?><br><small><?php echo $g['student_no']; ?></small></td>
                    <td><?php echo htmlspecialchars($g['course_code']); ?></td>
                    <td><?php echo htmlspecialchars($g['section_name']); ?></td>
                    <td><?php echo htmlspecialchars($g['instructor_name']); ?></td>
                    <td><?php echo $g['midterm'] !== null ? number_format($g['midterm'], 2) : '0.00'; ?></td>
                    <td><?php echo $g['final'] !== null ? number_format($g['final'], 2) : '0.00'; ?></td>
                    <td><strong><?php echo $g['grade'] !== null ? number_format($g['grade'], 2) : '—'; ?></strong></td>
                    <td>
                        <?php
    $bgStatus = 'danger';
    if ($g['remarks'] === 'Passed')
        $bgStatus = 'success';
    if ($g['remarks'] === 'INC')
        $bgStatus = 'warning';
    if ($g['remarks'] === 'Dropped')
        $bgStatus = 'secondary';
?>
                        <span class="badge bg-<?php echo $bgStatus; ?>"><?php echo $g['remarks']; ?></span>
                    </td>
                    <td>
                        <?php
    $sc = ['pending' => 'warning', 'submitted' => 'info', 'approved' => 'success', 'rejected' => 'danger'];
    echo '<span class="badge bg-' . $sc[$g['status']] . '">' . ucfirst($g['status']) . '</span>';
?>
                    </td>
                </tr>
                <?php
endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
