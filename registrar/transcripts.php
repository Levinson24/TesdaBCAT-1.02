<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole(['registrar', 'registrar_staff']);
$conn = getDBConnection();

$pageTitle = 'Generate Transcripts';
require_once '../includes/header.php';

$userRole = getCurrentUserRole();
$userProfile = getUserProfile(getCurrentUserId(), $userRole);
$deptId = $userProfile['dept_id'] ?? 0;
$isStaff = ($userRole === 'registrar_staff');

$whereClause = "WHERE s.status = 'active'";
if ($isStaff) {
    $whereClause .= " AND s.dept_id = $deptId";
}

// Get students with approved grades
$students = $conn->query("
    SELECT s.student_id, s.student_no, CONCAT(s.first_name, ' ', s.last_name) as name,
           COUNT(DISTINCT g.grade_id) as total_grades
    FROM students s
    LEFT JOIN grades g ON s.student_id = g.student_id AND g.status = 'approved'
    $whereClause
    GROUP BY s.student_id
    HAVING total_grades > 0
    ORDER BY s.student_no
");
?>

<div class="row">
    <div class="col-12 mb-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white pt-4 pb-3">
                <h5 class="mb-0 fw-bold"><i class="fas fa-file-pdf me-2"></i> Official Transcripts</h5>
                <p class="mb-0 small opacity-75 mt-1">Generate official printable PDF transcripts for enrolled students.</p>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4 border-0">Student No</th>
                                <th class="border-0">Student Name</th>
                                <th class="text-center border-0">Grades</th>
                                <th class="text-end pe-4 border-0">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($s = $students->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-4 fw-medium text-muted"><?php echo htmlspecialchars($s['student_no'] ?? ''); ?></td>
                                <td>
                                    <a href="grades.php?tab=records&search=<?php echo urlencode($s['student_no']); ?>" 
                                       class="text-decoration-none fw-bold text-primary" 
                                       title="View full grade records">
                                        <?php echo htmlspecialchars($s['name'] ?? ''); ?>
                                    </a>
                                </td>
                                <td class="text-center"><span class="badge bg-secondary"><?php echo $s['total_grades']; ?> subjects</span></td>
                                <td class="text-end pe-4">
                                    <a href="transcript_print.php?student_id=<?php echo $s['student_id']; ?>" target="_blank" class="btn btn-sm btn-primary">
                                        <i class="fas fa-print me-1"></i> Print PDF
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
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
